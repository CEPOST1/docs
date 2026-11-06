<?php

declare(strict_types=1);

namespace App;

/**
 * Hidden admin panel at /<admin_path>.
 * Security: secret path + password + per-IP lockout (5 tries / 15 min) + optional IP allow-list,
 * HttpOnly/Secure/SameSite=Strict session cookie (token stored hashed), CSRF tokens on every POST,
 * no caching, no indexing, strict CSP. Read-only database view (prepared statements, table allow-list).
 */
final class Admin
{
    private const COOKIE = 'tl_adm';
    private static string $base = '';
    private static ?string $tokenHash = null;

    public static function handle(string $base): void
    {
        self::$base = $base;
        I18n::set('ar');
        if (!self::configured() || !self::ipAllowed() || Db::pdo() === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo Db::pdo() === null && self::configured() ? 'SQLite (pdo_sqlite) is required for the admin panel.' : 'Not Found';
            return;
        }
        Security::htmlHeaders();
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');

        $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        if (!self::isLoggedIn()) {
            $error = null;
            if ($post && ($_POST['action'] ?? '') === 'login') {
                $error = self::login();
                if ($error === null) {
                    self::redirect('');
                    return;
                }
            }
            self::view('login', ['error' => $error]);
            return;
        }

        if ($post) {
            // Login form re-sent (double tap / refresh after logging in): just open the panel.
            if (($_POST['action'] ?? '') === 'login') {
                self::redirect('');
                return;
            }
            if (!hash_equals(self::csrf(), (string) ($_POST['csrf'] ?? ''))) {
                // Stale page (opened before a new login, or from cache): go back and retry from a fresh page.
                self::redirect((string) ($_GET['v'] ?? ''), ['err' => 'csrf']);
                return;
            }
            self::action((string) ($_POST['action'] ?? ''));
            return;
        }

        $v = (string) ($_GET['v'] ?? 'overview');
        if ($v === 'db' && isset($_GET['csv'])) {
            self::csv((string) ($_GET['t'] ?? ''));
            return;
        }
        match ($v) {
            'live' => self::view('live', self::liveData()),
            'searches' => self::view('searches', self::searchData()),
            'security' => self::view('security', self::securityData()),
            'db' => self::view('db', self::dbData()),
            default => self::view('overview', self::overviewData()),
        };
    }

    // ------------------------------------------------------------------ auth

    private static function configured(): bool
    {
        return strlen((string) Config::get('admin_password', '')) >= 12 || (string) Config::get('admin_password_hash', '') !== '';
    }

    private static function ipAllowed(): bool
    {
        $list = (array) Config::get('admin_ips', []);
        return !$list || in_array(Security::clientIp(), $list, true);
    }

    private static function uaHash(): string
    {
        return substr(hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 16);
    }

    private static function isLoggedIn(): bool
    {
        $c = (string) ($_COOKIE[self::COOKIE] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/', $c)) {
            return false;
        }
        $h = hash('sha256', $c);
        $row = Db::one('SELECT expires, ua FROM admin_sessions WHERE token = ?', [$h]);
        if (!$row || (int) $row['expires'] < time() || !hash_equals((string) $row['ua'], self::uaHash())) {
            return false;
        }
        self::$tokenHash = $h;
        return true;
    }

    /** @return string|null error message, null on success */
    private static function login(): ?string
    {
        $ip = Security::clientIp();
        $fails = (int) Db::val('SELECT COUNT(*) FROM admin_fails WHERE ip = ? AND ts > ?', [$ip, time() - 900]);
        if ($fails >= 5) {
            return 'محاولات كثيرة. انتظر 15 دقيقة.';
        }
        if (!hash_equals(self::loginCsrf(), (string) ($_POST['csrf'] ?? '')) && !hash_equals(self::loginCsrf(-1), (string) ($_POST['csrf'] ?? ''))) {
            return 'انتهت صلاحية الصفحة، حدّثها وحاول مجدداً.';
        }
        $pass = (string) ($_POST['password'] ?? '');
        $hash = (string) Config::get('admin_password_hash', '');
        $ok = $hash !== '' ? password_verify($pass, $hash) : hash_equals((string) Config::get('admin_password', ''), $pass);
        usleep(random_int(300_000, 700_000)); // slow down guessing
        if (!$ok || strlen($pass) < 12) {
            Db::run('INSERT INTO admin_fails(ip, ts) VALUES(?, ?)', [$ip, time()]);
            Guard::block('admin_login_failed');
            return 'كلمة المرور غير صحيحة.';
        }
        $token = bin2hex(random_bytes(32));
        Db::run('INSERT INTO admin_sessions(token, ip, ua, created, expires) VALUES(?,?,?,?,?)',
            [hash('sha256', $token), $ip, self::uaHash(), time(), time() + 12 * 3600]);
        Db::run('DELETE FROM admin_fails WHERE ip = ?', [$ip]);
        setcookie(self::COOKIE, $token, [
            'expires' => time() + 12 * 3600, 'path' => self::$base, 'secure' => Security::isHttps(),
            'httponly' => true, 'samesite' => 'Strict',
        ]);
        return null;
    }

    public static function loginCsrf(int $hourOffset = 0): string
    {
        return substr(Security::hmac('admlogin|' . gmdate('YmdH', time() + $hourOffset * 3600) . '|' . self::uaHash()), 0, 40);
    }

    public static function csrf(): string
    {
        return substr(Security::hmac('admcsrf|' . (self::$tokenHash ?? '')), 0, 40);
    }

    public static function url(string $v = '', array $q = []): string
    {
        if ($v !== '') {
            $q = ['v' => $v] + $q;
        }
        return self::$base . ($q ? '?' . http_build_query($q) : '');
    }

    private static function redirect(string $v, array $q = []): void
    {
        header('Location: ' . self::url($v, $q), true, 303);
    }

    // ------------------------------------------------------------------ actions

    private static function action(string $a): void
    {
        $ip = trim((string) ($_POST['ip'] ?? ''));
        switch ($a) {
            case 'logout':
                Db::run('DELETE FROM admin_sessions WHERE token = ?', [self::$tokenHash]);
                setcookie(self::COOKIE, '', ['expires' => 1, 'path' => self::$base, 'secure' => Security::isHttps(), 'httponly' => true, 'samesite' => 'Strict']);
                self::redirect('');
                return;
            case 'ban':
                $hours = max(1, min(24 * 365, (int) ($_POST['hours'] ?? 24)));
                if (filter_var($ip, FILTER_VALIDATE_IP) && $ip !== Security::clientIp()) {
                    Guard::ban($ip, 'admin: ' . mb_substr((string) ($_POST['reason'] ?? ''), 0, 80), $hours * 3600);
                }
                self::redirect('security');
                return;
            case 'unban':
                Db::run('DELETE FROM bans WHERE ip = ?', [$ip]);
                self::redirect('security');
                return;
            case 'purge':
                Db::purge();
                self::redirect('db');
                return;
        }
        self::redirect('');
    }

    // ------------------------------------------------------------------ data

    private static function countries(int $since, int $limit = 12): array
    {
        return Db::all("SELECT COALESCE(country, '??') AS cc, COUNT(DISTINCT sid) AS visitors, COUNT(*) AS views
                        FROM visits WHERE ts > ? AND device NOT IN ('bot','crawler') GROUP BY cc ORDER BY visitors DESC LIMIT $limit", [$since]);
    }

    private static function overviewData(): array
    {
        Geo::fill();
        $now = time();
        $day = $now - 86400;
        $today = strtotime('today UTC');
        $days = [];
        for ($i = 13; $i >= 0; $i--) {
            $start = $today - $i * 86400;
            $days[] = [
                'label' => gmdate('m/d', $start),
                'views' => (int) Db::val('SELECT COUNT(*) FROM visits WHERE ts >= ? AND ts < ?', [$start, $start + 86400]),
                'visitors' => (int) Db::val('SELECT COUNT(DISTINCT sid) FROM visits WHERE ts >= ? AND ts < ?', [$start, $start + 86400]),
            ];
        }
        return [
            'cards' => [
                ['متصلون الآن', (int) Db::val('SELECT COUNT(*) FROM sessions WHERE last_ts > ?', [$now - 300]), 'users'],
                ['زوار اليوم', (int) Db::val('SELECT COUNT(DISTINCT sid) FROM visits WHERE ts >= ?', [$today]), 'user'],
                ['مشاهدات اليوم', (int) Db::val('SELECT COUNT(*) FROM visits WHERE ts >= ?', [$today]), 'eye'],
                ['عمليات بحث اليوم', (int) Db::val('SELECT COUNT(*) FROM searches WHERE ts >= ?', [$today]), 'search'],
                ['محاولات محظورة (24س)', (int) Db::val('SELECT COUNT(*) FROM blocks WHERE ts > ?', [$day]), 'shield'],
                ['IP محظورة الآن', (int) Db::val('SELECT COUNT(*) FROM bans WHERE until > ?', [$now]), 'lock'],
            ],
            'days' => $days,
            'countries' => self::countries($now - 30 * 86400),
            'topSearches' => Db::all('SELECT kind, query, COUNT(*) AS n FROM searches WHERE ts > ? GROUP BY kind, query ORDER BY n DESC LIMIT 15', [$now - 7 * 86400]),
            'pages' => Db::all("SELECT CASE WHEN instr(path, '?') > 0 THEN substr(path, 1, instr(path, '?') - 1) ELSE path END AS p, COUNT(*) AS n
                                FROM visits WHERE ts > ? GROUP BY p ORDER BY n DESC LIMIT 12", [$now - 7 * 86400]),
            'langs' => Db::all('SELECT lang, COUNT(DISTINCT sid) AS n FROM visits WHERE ts > ? GROUP BY lang ORDER BY n DESC', [$now - 30 * 86400]),
            'devices' => Db::all('SELECT device, COUNT(DISTINCT sid) AS n FROM visits WHERE ts > ? GROUP BY device ORDER BY n DESC', [$now - 30 * 86400]),
            'refs' => Db::all("SELECT ref, COUNT(*) AS n FROM visits WHERE ts > ? AND ref != '' GROUP BY ref ORDER BY n DESC LIMIT 10", [$now - 30 * 86400]),
        ];
    }

    private static function liveData(): array
    {
        Geo::fill();
        return [
            'online' => Db::all('SELECT * FROM sessions WHERE last_ts > ? ORDER BY last_ts DESC LIMIT 200', [time() - 300]),
            'recent' => Db::all('SELECT * FROM sessions ORDER BY last_ts DESC LIMIT 100'),
            'countries' => self::countries(time() - 86400, 20),
        ];
    }

    private static function searchData(): array
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $rows = $q === ''
            ? Db::all('SELECT * FROM searches ORDER BY id DESC LIMIT 300')
            : Db::all('SELECT * FROM searches WHERE query LIKE ? OR ip = ? ORDER BY id DESC LIMIT 300', ['%' . $q . '%', $q]);
        return ['rows' => $rows, 'q' => $q];
    }

    private static function securityData(): array
    {
        return [
            'bans' => Db::all('SELECT * FROM bans WHERE until > ? ORDER BY ts DESC', [time()]),
            'blocks' => Db::all('SELECT * FROM blocks ORDER BY id DESC LIMIT 200'),
            'topIps' => Db::all('SELECT ip, COUNT(*) AS n, MAX(reason) AS reason FROM blocks WHERE ts > ? GROUP BY ip ORDER BY n DESC LIMIT 20', [time() - 86400]),
            'reasons' => Db::all('SELECT reason, COUNT(*) AS n FROM blocks WHERE ts > ? GROUP BY reason ORDER BY n DESC', [time() - 86400]),
        ];
    }

    private static function dbData(): array
    {
        $tables = [];
        foreach (Db::BROWSABLE as $t) {
            $tables[$t] = (int) Db::val("SELECT COUNT(*) FROM $t");
        }
        $t = (string) ($_GET['t'] ?? '');
        $rows = [];
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $filter = trim((string) ($_GET['q'] ?? ''));
        if (in_array($t, Db::BROWSABLE, true)) {
            $off = ($page - 1) * 50;
            $hasIp = in_array($t, ['sessions', 'visits', 'searches', 'blocks', 'bans', 'geo', 'crawlers', 'admin_fails'], true);
            $rows = ($filter !== '' && $hasIp)
                ? Db::all("SELECT * FROM $t WHERE ip LIKE ? ORDER BY rowid DESC LIMIT 50 OFFSET $off", [$filter . '%'])
                : Db::all("SELECT * FROM $t ORDER BY rowid DESC LIMIT 50 OFFSET $off");
        } else {
            $t = '';
        }
        $file = ROOT . '/storage/toklens.sqlite';
        return ['tables' => $tables, 't' => $t, 'rows' => $rows, 'page' => $page, 'q' => $filter, 'size' => is_file($file) ? filesize($file) : 0];
    }

    private static function csv(string $t): void
    {
        if (!in_array($t, Db::BROWSABLE, true)) {
            http_response_code(404);
            return;
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="toklens_' . $t . '_' . gmdate('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // Excel UTF-8 BOM
        $st = Db::run("SELECT * FROM $t ORDER BY rowid DESC LIMIT 100000");
        $first = true;
        while ($st && ($row = $st->fetch())) {
            if ($first) {
                fputcsv($out, array_keys($row));
                $first = false;
            }
            // Neutralise spreadsheet formulas (CSV injection).
            fputcsv($out, array_map(static fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $row));
        }
        fclose($out);
    }

    // ------------------------------------------------------------------ view

    private static function view(string $view, array $data): void
    {
        $data['view'] = $view;
        extract($data, EXTR_SKIP);
        require ROOT . '/templates/admin.php';
    }

    public static function flag(?string $cc): string
    {
        if (!is_string($cc) || !preg_match('/^[A-Z]{2}$/', $cc)) {
            return '🌐';
        }
        return mb_chr(0x1F1E6 + ord($cc[0]) - 65) . mb_chr(0x1F1E6 + ord($cc[1]) - 65);
    }

    public static function countryName(?string $cc): string
    {
        if (!is_string($cc) || !preg_match('/^[A-Z]{2}$/', $cc)) {
            return 'غير معروف';
        }
        if (class_exists(\Locale::class)) {
            $n = \Locale::getDisplayRegion('-' . $cc, 'ar');
            if (is_string($n) && $n !== '' && $n !== $cc) {
                return $n;
            }
        }
        return $cc;
    }

    public static function ago(int $ts): string
    {
        $d = time() - $ts;
        return match (true) {
            $d < 60 => 'الآن',
            $d < 3600 => 'منذ ' . intdiv($d, 60) . ' د',
            $d < 86400 => 'منذ ' . intdiv($d, 3600) . ' س',
            default => gmdate('Y-m-d H:i', $ts),
        };
    }
}
