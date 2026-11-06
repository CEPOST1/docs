<?php

declare(strict_types=1);

namespace App;

/**
 * SQLite storage for analytics, security events and the admin panel.
 * File: storage/toklens.sqlite (outside the web root / denied by .htaccess).
 * Every query uses prepared statements. If SQLite is unavailable the site keeps working without stats.
 */
final class Db
{
    private static ?\PDO $pdo = null;
    private static bool $failed = false;

    /** Tables the admin "database" view may show (never admin_sessions: it holds login tokens). */
    public const BROWSABLE = ['sessions', 'visits', 'searches', 'blocks', 'bans', 'geo', 'crawlers', 'admin_fails'];

    public static function pdo(): ?\PDO
    {
        if (self::$pdo !== null || self::$failed) {
            return self::$pdo;
        }
        if (!extension_loaded('pdo_sqlite')) {
            self::$failed = true;
            return null;
        }
        try {
            $file = ROOT . '/storage/toklens.sqlite';
            $new = !is_file($file);
            $pdo = new \PDO('sqlite:' . $file, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT => 3,
            ]);
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA synchronous=NORMAL');
            $pdo->exec('PRAGMA busy_timeout=3000');
            self::migrate($pdo);
            if ($new) {
                @chmod($file, 0600);
            }
            self::$pdo = $pdo;
        } catch (\Throwable $e) {
            error_log('[toklens] sqlite unavailable: ' . $e->getMessage());
            self::$failed = true;
        }
        return self::$pdo;
    }

    private static function migrate(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS sessions (
  sid TEXT PRIMARY KEY, ip TEXT, country TEXT, ua TEXT, device TEXT, agent TEXT, lang TEXT,
  first_ts INTEGER, last_ts INTEGER, last_path TEXT, hits INTEGER DEFAULT 0, lookups INTEGER DEFAULT 0,
  verified_ts INTEGER DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_sessions_last ON sessions(last_ts);
CREATE TABLE IF NOT EXISTS visits (
  id INTEGER PRIMARY KEY AUTOINCREMENT, ts INTEGER, sid TEXT, ip TEXT, country TEXT, path TEXT,
  lang TEXT, status INTEGER, device TEXT, ref TEXT
);
CREATE INDEX IF NOT EXISTS idx_visits_ts ON visits(ts);
CREATE TABLE IF NOT EXISTS searches (
  id INTEGER PRIMARY KEY AUTOINCREMENT, ts INTEGER, sid TEXT, ip TEXT, country TEXT, kind TEXT, query TEXT, ok INTEGER
);
CREATE INDEX IF NOT EXISTS idx_searches_ts ON searches(ts);
CREATE TABLE IF NOT EXISTS blocks (
  id INTEGER PRIMARY KEY AUTOINCREMENT, ts INTEGER, ip TEXT, reason TEXT, path TEXT, ua TEXT
);
CREATE INDEX IF NOT EXISTS idx_blocks_ip_ts ON blocks(ip, ts);
CREATE TABLE IF NOT EXISTS bans (ip TEXT PRIMARY KEY, reason TEXT, ts INTEGER, until INTEGER);
CREATE TABLE IF NOT EXISTS geo (ip TEXT PRIMARY KEY, cc TEXT, country TEXT, city TEXT, ts INTEGER);
CREATE TABLE IF NOT EXISTS crawlers (ip TEXT PRIMARY KEY, ok INTEGER, host TEXT, ts INTEGER);
CREATE TABLE IF NOT EXISTS admin_sessions (token TEXT PRIMARY KEY, ip TEXT, ua TEXT, created INTEGER, expires INTEGER);
CREATE TABLE IF NOT EXISTS admin_fails (id INTEGER PRIMARY KEY AUTOINCREMENT, ip TEXT, ts INTEGER);
CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT);
SQL);
    }

    public static function run(string $sql, array $params = []): ?\PDOStatement
    {
        $pdo = self::pdo();
        if ($pdo === null) {
            return null;
        }
        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return $st;
        } catch (\Throwable $e) {
            error_log('[toklens] sql: ' . $e->getMessage());
            return null;
        }
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)?->fetchAll() ?: [];
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $r = self::run($sql, $params)?->fetch();
        return is_array($r) ? $r : null;
    }

    public static function val(string $sql, array $params = []): mixed
    {
        $r = self::run($sql, $params)?->fetchColumn();
        return $r === false ? null : $r;
    }

    /** Delete old analytics rows (called occasionally). */
    public static function purge(): void
    {
        $days = max(1, (int) Config::get('guard.retention_days', 60));
        $cut = time() - $days * 86400;
        self::run('DELETE FROM visits WHERE ts < ?', [$cut]);
        self::run('DELETE FROM searches WHERE ts < ?', [$cut]);
        self::run('DELETE FROM blocks WHERE ts < ?', [$cut]);
        self::run('DELETE FROM sessions WHERE last_ts < ?', [$cut]);
        self::run('DELETE FROM bans WHERE until < ?', [time()]);
        self::run('DELETE FROM admin_sessions WHERE expires < ?', [time()]);
        self::run('DELETE FROM admin_fails WHERE ts < ?', [time() - 86400]);
        self::run('DELETE FROM crawlers WHERE ts < ?', [time() - 7 * 86400]);
    }
}
