<?php

declare(strict_types=1);

namespace App;

/**
 * Country lookup for the admin panel. Runs only when the admin opens the panel (never slows visitors):
 * resolves up to 40 unknown IPs in parallel via ipwho.is (free, HTTPS) and caches them in the `geo` table.
 * Behind Cloudflare (trust_proxy = true) the CF-IPCountry header is used directly instead.
 */
final class Geo
{
    public static function fill(int $max = 40): void
    {
        if (!function_exists('curl_multi_init')) {
            return;
        }
        $ips = array_column(Db::all(
            'SELECT DISTINCT s.ip FROM sessions s LEFT JOIN geo g ON g.ip = s.ip
             WHERE g.ip IS NULL AND s.last_ts > ? ORDER BY s.last_ts DESC LIMIT ' . (int) $max,
            [time() - 3 * 86400]
        ), 'ip');
        $ips = array_values(array_filter($ips, static fn($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)));
        if (!$ips) {
            return;
        }
        $mh = curl_multi_init();
        $handles = [];
        foreach ($ips as $ip) {
            $ch = curl_init('https://ipwho.is/' . rawurlencode($ip) . '?fields=success,country,country_code,city');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'TokLens-admin/1.0',
            ]);
            $proxy = (string) Config::get('outbound_proxy', '');
            if ($proxy !== '') {
                curl_setopt($ch, CURLOPT_PROXY, $proxy);
            }
            curl_multi_add_handle($mh, $ch);
            $handles[$ip] = $ch;
        }
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 0.5);
            }
        } while ($running && $status === CURLM_OK);

        foreach ($handles as $ip => $ch) {
            $j = json_decode((string) curl_multi_getcontent($ch), true);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            if (!is_array($j) || empty($j['success'])) {
                continue;
            }
            $cc = strtoupper((string) ($j['country_code'] ?? ''));
            if (!preg_match('/^[A-Z]{2}$/', $cc)) {
                continue;
            }
            Db::run('INSERT OR REPLACE INTO geo(ip, cc, country, city, ts) VALUES(?,?,?,?,?)',
                [$ip, $cc, mb_substr((string) ($j['country'] ?? ''), 0, 60), mb_substr((string) ($j['city'] ?? ''), 0, 60), time()]);
            Db::run('UPDATE sessions SET country = ? WHERE ip = ? AND country IS NULL', [$cc, $ip]);
            Db::run('UPDATE visits SET country = ? WHERE ip = ? AND country IS NULL', [$cc, $ip]);
            Db::run('UPDATE searches SET country = ? WHERE ip = ? AND country IS NULL', [$cc, $ip]);
        }
        curl_multi_close($mh);
    }
}
