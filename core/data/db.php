<?php

$__cfg = __DIR__ . '/../../usr/data/config.php';
if (!is_file($__cfg)) {
    $__self = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($__self !== 'install.php') {
        $__prefix = (basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'admin') ? '../' : '';
        header('Location: ' . $__prefix . 'install.php');
        exit;
    }
} else {
    require_once $__cfg;
}
unset($__cfg, $__self, $__prefix);

// 核心标记：应用包内的 PHP 文件必须经核心加载，Web 直接访问一律 403
if (!defined('GBLOG_CORE')) define('GBLOG_CORE', true);

// 会话 Cookie 加固（必须在任何 session_start 之前）： HttpOnly 挡住 XSS 读取会话 ID
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
// HTTPS 部署自动带 Secure（HTTP 站点不能设，否则会话失效）；反代认 X-Forwarded-Proto
    $__isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
              || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
              || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    if (PHP_VERSION_ID >= 70300) {
        @session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $__isHttps,
        ]);
    } else {
        @session_set_cookie_params(0, '/', '', $__isHttps, true);
    }
}

require_once __DIR__ . '/../auth/security.php';

$__self = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (defined('DB_INSTALLED') && DB_INSTALLED === false && $__self !== 'install.php') {
    $__target = (basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'admin') ? '../install.php' : 'install.php';
    header('Location: ' . $__target);
    exit;
}
unset($__self, $__target);

function db(bool $fresh = false): PDO
{
    static $pdo = null;
    if ($fresh) $pdo = null;
    if ($pdo === null) {
        $type = defined('DB_TYPE') ? DB_TYPE : 'mysql';
        try {
            if ($type === 'sqlite') {
                if (!defined('DB_PATH') || DB_PATH === '') {
                    throw new PDOException('SQLite 模式缺少 DB_PATH 配置');
                }
                $dir = dirname(DB_PATH);
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $dsn = 'sqlite:' . DB_PATH;
            } else {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            }
            $pdo = new PDO($dsn, $type === 'sqlite' ? null : DB_USER, $type === 'sqlite' ? null : DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            if ($type === 'sqlite') {
                $pdo->exec('PRAGMA foreign_keys = ON');
            } else {
// MySQL 会话时区必须跟随 PHP，否则 TIMESTAMP 与 date() 差 8 小时（签到判定与时间显示全错）
                $off = (int)round(date('Z') / 60);
                $tz  = sprintf('%s%02d:%02d', $off >= 0 ? '+' : '-', intdiv(abs($off), 60), abs($off) % 60);
                $pdo->exec("SET time_zone = '{$tz}'");
            }
            if (!defined('GBLOG_SKIP_MIGRATE')) {
                require_once __DIR__ . '/migrate.php';
                gblog_migrate($pdo);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            die('数据库连接失败，请检查 config.php 中的配置。' . (ini_get('display_errors') ? ' 错误：' . $e->getMessage() : ''));
        }
    }
    return $pdo;
}

function gblog_db_driver(): string
{
    return (defined('DB_TYPE') && DB_TYPE === 'sqlite') ? 'sqlite' : 'mysql';
}

function gblog_parse_time($datetime): int
{
    $s = trim((string)$datetime);
    if ($s === '') return 0;
    if (ctype_digit($s)) return (int)$s;
    $ts = gblog_db_driver() === 'sqlite' ? strtotime($s . ' UTC') : strtotime($s);
    return $ts ? (int)$ts : 0;
}

function gblog_sql_local_time(string $col): string
{
    if (gblog_db_driver() !== 'sqlite') return $col;
    $minutes = (int)round(date('Z') / 60);
    if ($minutes === 0) return $col;
    $mod = ($minutes > 0 ? '+' : '') . $minutes . ' minutes';
    return "datetime({$col}, '{$mod}')";
}

// SQLite 的 PRAGMA 不能在事务内切换）
function gblog_db_fk(bool $on): void
{
    try {
        if (gblog_db_driver() === 'sqlite') {
            db()->exec('PRAGMA foreign_keys = ' . ($on ? 'ON' : 'OFF'));
        } else {
            db()->exec('SET FOREIGN_KEY_CHECKS=' . ($on ? '1' : '0'));
        }
    } catch (Throwable $e) {
    }
}

function gblog_db_tables(): array
{
    $pdo = db();
    $sql = gblog_db_driver() === 'sqlite'
        ? "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        : 'SHOW TABLES';
    $st = $pdo->query($sql);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);
    $st->closeCursor();
    return $rows;
}

// 注意：所有查询都必须 closeCursor()
function gblog_db_create(string $table): string
{
    $pdo = db();
    if (gblog_db_driver() === 'sqlite') {
        $st = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?");
        $st->execute([$table]);
        $sql = (string)$st->fetchColumn();
        $st->closeCursor();
        return $sql;
    }
    $st = $pdo->query('SHOW CREATE TABLE `' . $table . '`');
    $r  = $st->fetch(PDO::FETCH_NUM);
    $st->closeCursor();
    return (string)($r[1] ?? '');
}

function gblog_db_columns(string $table): array
{
    $pdo = db();
    if (gblog_db_driver() === 'sqlite') {
        $st = $pdo->query('PRAGMA table_info(`' . $table . '`)');
        $rows = $st->fetchAll();
        $st->closeCursor();
        return array_map(function ($r) { return (string)($r['name'] ?? ''); }, $rows);
    }
    $st = $pdo->query('SHOW COLUMNS FROM `' . $table . '`');
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);
    $st->closeCursor();
    return $rows;
}

function gblog_insert_ignore(): string
{
    return gblog_db_driver() === 'sqlite' ? 'INSERT OR IGNORE INTO' : 'INSERT IGNORE INTO';
}

// 输出到 HTML 的一切用户/数据库内容都必须过这个函数
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fmt_date($datetime): string
{
    $ts = gblog_parse_time($datetime);
    return $ts > 0 ? date('Y年m月d日', $ts) : '';
}

function fmt_short_date($datetime): string
{
    $ts = gblog_parse_time($datetime);
    return $ts > 0 ? date('y/m/d', $ts) : '';
}

function excerpt(string $text, int $len = 120): string
{
    $text = trim(strip_tags($text));
    $text = preg_replace('/\s+/u', ' ', $text);
    if (function_exists('mb_strlen')) {
        if (mb_strlen($text, 'UTF-8') <= $len) {
            return $text;
        }
        return mb_substr($text, 0, $len, 'UTF-8') . '…';
    }
    if (strlen($text) <= $len) {
        return $text;
    }
    return substr($text, 0, $len) . '…';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): bool
{
    $token  = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    if (!is_string($token) || $token === '' || !is_string($stored) || $stored === '') {
        return false;
    }
    return hash_equals($stored, $token);
}

function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}


/** 全局内容发号：posts 与 songs 共用一条序列（content_ids 发号表，保留发号行防回绕）。 */
function gblog_next_content_id(): int
{
    $pdo    = db();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pdo->exec('INSERT INTO content_ids (seq) VALUES (NULL)');
    } else {
        $pdo->exec('INSERT INTO content_ids () VALUES ()');
    }
    return (int)$pdo->lastInsertId();
}
