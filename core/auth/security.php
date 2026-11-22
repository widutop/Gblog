<?php

function gblog_secret(): string
{
    static $secret = null;
    if ($secret !== null) return $secret;
    if (defined('GBLOG_SECRET') && is_string(GBLOG_SECRET)
        && preg_match('/^[a-f0-9]{64}$/i', GBLOG_SECRET)) {
        return $secret = strtolower((string)GBLOG_SECRET);
    }
    try {
        if (function_exists('get_setting')) {
            $s = (string)get_setting('app_secret', '');
            if ($s === '' && function_exists('save_setting')) {
                $s = bin2hex(random_bytes(32));
                save_setting('app_secret', $s);
            }
            if ($s !== '') return $secret = $s;
        }
    } catch (Throwable $e) {
    }
    throw new RuntimeException('站点密钥缺失：请在 config.php 中定义 GBLOG_SECRET（64 位十六进制随机串）。');
}

function gblog_hmac(string $data): string
{
    return hash_hmac('sha256', $data, gblog_secret());
}

function gblog_client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function gblog_audit(string $action, int $userId = 0, string $detail = ''): void
{
    try {
        db()->prepare('INSERT INTO security_logs (action, user_id, detail, ip, created_at) VALUES (?,?,?,?,?)')
            ->execute([$action, $userId, substr($detail, 0, 240), substr(gblog_client_ip(), 0, 64), time()]);
    } catch (Throwable $e) {
    }
}

function gblog_rate_allow(string $bucket, string $identity, int $max, int $window): bool
{
    if ($max <= 0) return true;
    $identity = substr($identity, 0, 120);
    $now = time();
    try {
        if (random_int(1, 100) === 1) gblog_rate_gc();
        $pdo = db();
        $st = $pdo->prepare('SELECT hits, window_start FROM rate_limits WHERE bucket = ? AND identity = ? LIMIT 1');
        $st->execute([$bucket, $identity]);
        $row = $st->fetch();
        if (!$row) {
            $pdo->prepare('INSERT INTO rate_limits (bucket, identity, hits, window_start) VALUES (?,?,1,?)')
                ->execute([$bucket, $identity, $now]);
            return true;
        }
        $hits = (int)$row['hits'];
        $start = (int)$row['window_start'];
        if ($now - $start >= $window) {
            $pdo->prepare('UPDATE rate_limits SET hits = 1, window_start = ? WHERE bucket = ? AND identity = ?')
                ->execute([$now, $bucket, $identity]);
            return true;
        }
        if ($hits >= $max) return false;
        $pdo->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE bucket = ? AND identity = ?')
            ->execute([$bucket, $identity]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function gblog_rate_reset(string $bucket, string $identity): void
{
    try {
        db()->prepare('DELETE FROM rate_limits WHERE bucket = ? AND identity = ?')->execute([$bucket, substr($identity, 0, 120)]);
    } catch (Throwable $e) {
    }
}

function gblog_rate_gc(): void
{
    try {
        db()->prepare('DELETE FROM rate_limits WHERE window_start < ?')->execute([time() - 86400]);
    } catch (Throwable $e) {
    }
}

function gblog_captcha_get(): string
{
    $a = random_int(2, 9);
    $b = random_int(1, 9);
    $_SESSION['gblog_captcha_answer'] = (string)($a + $b);
    return $a . ' + ' . $b . ' = ?';
}

function gblog_captcha_check($answer): bool
{
    $expect = (string)($_SESSION['gblog_captcha_answer'] ?? '');
    unset($_SESSION['gblog_captcha_answer']);
    if ($expect === '' || !is_scalar($answer)) return false;
    return hash_equals($expect, trim((string)$answer));
}

function gblog_password_strong(string $pw): bool
{
    return strlen($pw) >= 8
        && preg_match('/[A-Z]/', $pw) === 1
        && preg_match('/[a-z]/', $pw) === 1;
}

function gblog_recovery_code_error(string $code): string
{
    if (strlen($code) < 8) return '恢复码至少 8 位。';
    if (preg_match('/[A-Z]/', $code) !== 1) return '恢复码必须包含大写字母。';
    if (preg_match('/[a-z]/', $code) !== 1) return '恢复码必须包含小写字母。';
    return '';
}

function gblog_recovery_hash(string $code): string
{
    return password_hash($code, PASSWORD_DEFAULT);
}

// 管理员发放用：12 位强随机（必含大小写与数字），排除易混的 0O1lI。
function gblog_recovery_generate(int $len = 12): string
{
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $digit = '23456789';
    $all   = $upper . $lower . $digit;
    $out   = $upper[random_int(0, strlen($upper) - 1)]
           . $lower[random_int(0, strlen($lower) - 1)]
           . $digit[random_int(0, strlen($digit) - 1)];
    for ($i = 3; $i < max(3, $len); $i++) {
        $out .= $all[random_int(0, strlen($all) - 1)];
    }
    $chars = str_split($out);
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }
    return implode('', $chars);
}

function gblog_recovery_verify(string $code, ?string $stored): bool
{
    if ($stored === null || $stored === '') return false;
    if (preg_match('/^\$(2[aby]|argon2)/', $stored)) return password_verify($code, $stored);
    return hash_equals($stored, $code);
}

function gblog_recovery_upgrade_hash(int $userId, string $code, ?string $stored): void
{
    if ($stored === null || $stored === '' || preg_match('/^\$(2[aby]|argon2)/', $stored)) return;
    try {
        db()->prepare('UPDATE users SET security_code = ? WHERE id = ?')
            ->execute([gblog_recovery_hash($code), $userId]);
        gblog_audit('recovery_code.upgraded_hash', $userId, 'forgot');
    } catch (Throwable $e) {
    }
}

function gblog_recovery_is_weak(?string $stored): bool
{
    if ($stored === null || $stored === '') return true;
    return !preg_match('/^\$(2[aby]|argon2)/', $stored);
}

function gblog_reset_token_create(int $userId, string $channel): string
{
    $pdo = db();
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    try {
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND (used = 1 OR expires_at < ?)')
            ->execute([$userId, time()]);
    } catch (Throwable $e) {
    }
    $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, channel, expires_at, used, ip, created_at) VALUES (?,?,?,?,0,?,?)')
        ->execute([$userId, $hash, $channel, time() + 900, substr(gblog_client_ip(), 0, 64), time()]);
    return $token;
}

function gblog_reset_token_lookup(string $token): ?array
{
    if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) return null;
    try {
        $st = db()->prepare('SELECT * FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at >= ? LIMIT 1');
        $st->execute([hash('sha256', $token), time()]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function gblog_reset_token_consume(int $id): bool
{
    try {
        $st = db()->prepare('UPDATE password_resets SET used = 1 WHERE id = ? AND used = 0');
        $st->execute([$id]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function gblog_reset_token_invalidate_user(int $userId): void
{
    try {
        db()->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ?')->execute([$userId]);
    } catch (Throwable $e) {
    }
}

function gblog_url_safe_for_fetch(string $url): bool
{
    $url = gblog_url_clean($url);
    if (!preg_match('#^https?://#i', $url)) return false;
    $p = parse_url($url);
    if (!$p || empty($p['host']) || isset($p['user']) || isset($p['pass'])) return false;
    if (isset($p['port']) && !in_array((int)$p['port'], [80, 443, 8080, 8443], true)) return false;
    $host = (string)$p['host'];
    $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
    return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

function gblog_url_clean(string $url): string
{
    $url = trim(str_replace(["\0", "\r", "\n", "\t"], '', $url));
    return $url;
}

function gblog_safe_redirect(string $url, string $fallback = 'index.php'): string
{
    // 相对 index.php 地址在漂亮 URL 页面会被浏览器按目录解析，统一根绝对化
    $abs = static function (string $u): string {
        return function_exists('gblog_root_absolute') ? gblog_root_absolute($u) : $u;
    };
    $url = gblog_url_clean($url);
    if ($url === '') return $abs($fallback);
    if (preg_match('#^(javascript|data|vbscript|file|php):#i', $url)) return $abs($fallback);
    if (strpos($url, '//') === 0) {
        $url = 'https:' . $url;
    }
    if (preg_match('#^https?://#i', $url)) {
        $p = parse_url($url);
        if (!$p || empty($p['host']) || preg_match('/[\x00-\x1f\x7f]/', $url)) return $abs($fallback);
        return $url;
    }
    if (strpos($url, '\\') === 0) return $abs($fallback);
    if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $url)) return $abs($fallback);
    return $abs($url);
}

// HTML 属性中的 URL 白名单：只放行 http/https/mailto/tel 与相对路径
function gblog_safe_url_attr(string $url): string
{
    $url = gblog_url_clean($url);
    if ($url === '') return '';
    if (preg_match('#^(javascript|data|vbscript|file|php):#i', $url)) return '';
    if (preg_match('#^([a-zA-Z][a-zA-Z0-9+.-]*):#', $url, $m)) {
        if (!in_array(strtolower($m[1]), ['http', 'https', 'mailto', 'tel'], true)) return '';
    }
    return $url;
}

function gblog_safe_style(string $css): string
{
    $css = gblog_url_clean($css);
    $css = preg_replace('/(expression\s*\(|behavior\s*:|-moz-binding|@import)/i', '', $css);
    $css = preg_replace_callback('#url\s*\(\s*([^)]*)\)#i', function ($m) {
        $u = trim(str_replace(["'", '"'], '', $m[1]));
        if (preg_match('#^(javascript|data|vbscript):#i', $u)) return '';
        return 'url(' . $u . ')';
    }, $css);
    return trim(str_replace(['<', '>'], '', (string)$css));
}


function gblog_html_whitelist(): array
{
    return [
        'p' => ['align'], 'br' => [], 'hr' => [], 'div' => ['align'],
        'span' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'u' => [], 's' => [], 'del' => [], 'ins' => [], 'sub' => [], 'sup' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'ul' => [], 'ol' => ['start'], 'li' => [], 'blockquote' => ['cite'],
        'pre' => [], 'code' => ['class'], 'figure' => [], 'figcaption' => [],
        'a' => ['href', 'target', 'rel', 'name'],
        'img' => ['src', 'alt', 'width', 'height', 'loading', 'class'],
        'table' => ['border', 'cellpadding', 'cellspacing', 'width'],
        'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [],
        'td' => ['colspan', 'rowspan', 'width'], 'th' => ['colspan', 'rowspan', 'width'],
        'video' => ['src', 'poster', 'width', 'height', 'controls', 'preload', 'loop', 'muted'],
        'audio' => ['src', 'controls', 'preload', 'loop', 'muted'],
        'source' => ['src', 'type'],
        'iframe' => ['src', 'width', 'height', 'loading', 'allow', 'allowfullscreen', 'frameborder', 'scrolling', 'referrerpolicy'],
    ];
}

function gblog_sanitize_attrs(string $tag, string $rest): string
{
    $global = ['class', 'id', 'title', 'style', 'dir', 'lang'];
    $allow  = array_merge($global, gblog_html_whitelist()[$tag] ?? []);
    $out = '';
    $n = preg_match_all('/([a-zA-Z_:][a-zA-Z0-9_:.\-]*)\s*(?:=\s*("[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?/', $rest, $ms, PREG_SET_ORDER);
    if (!$n) return '';
    foreach ($ms as $m) {
        $name = strtolower($m[1]);
        if (strpos($name, 'on') === 0) continue;
        if (!in_array($name, $allow, true)) continue;
        $raw = isset($m[2]) ? $m[2] : '';
        $val = $raw;
        if ($val !== '' && ($val[0] === '"' || $val[0] === "'")) $val = substr($val, 1, -1);
        if ($val === '') $val = $name;
        if (in_array($name, ['href', 'src', 'poster', 'cite'], true)) {
            $val = gblog_safe_url_attr($val);
            if ($val === '') continue;
        }
        if ($name === 'style') {
            $val = gblog_safe_style($val);
            if ($val === '') continue;
        }
        if ($name === 'target' && strtolower($val) !== '_blank') $val = '_self';
        $out .= ' ' . $name . '="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"';
        if ($name === 'target' && strtolower($val) === '_blank' && strpos($rest, 'rel=') === false) {
            $out .= ' rel="noopener noreferrer"';
        }
    }
    return $out;
}

// 富文本净化：'<' 要么进白名单标签，要么转义成文本。
function gblog_sanitize_html(string $html): string
{
    if ($html === '') return '';
    $html = str_replace("\0", '', $html);
    $parts = preg_split('/(<[^>]*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
    $white = gblog_html_whitelist();
    $out = '';
    foreach ($parts as $part) {
        if ($part === '') continue;
        if ($part[0] !== '<' || substr($part, -1) !== '>') {
            $out .= htmlspecialchars($part, ENT_QUOTES, 'UTF-8', false);
            continue;
        }
        $inner = substr($part, 1, -1);
        if ($inner === '' || $inner[0] === '!' || $inner[0] === '?') continue;
        if (!preg_match('#^(/?)\s*([a-zA-Z][a-zA-Z0-9]*)\b(.*)$#s', $inner, $m)) {
            $out .= htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
            continue;
        }
        $tag = strtolower($m[2]);
        if (!isset($white[$tag])) {
            $out .= htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
            continue;
        }
        if ($m[1] === '/') { $out .= '</' . $tag . '>'; continue; }
        $rest = rtrim($m[3]);
        $self = (substr($rest, -1) === '/');
        if ($self) $rest = rtrim(substr($rest, 0, -1));
        $attrs = gblog_sanitize_attrs($tag, $rest);
        $out .= '<' . $tag . $attrs . ($self ? ' />' : '>');
    }
    return $out;
}


function gblog_known_tables(): array
{
    return [
        'sites', 'categories', 'tags', 'posts', 'post_tags', 'songs', 'comments',
        'view_history', 'post_reactions', 'links', 'link_categories', 'ads', 'settings', 'apps', 'users', 'coin_logs', 'download_logs',
        'novel_chapters', 'password_resets', 'rate_limits', 'security_logs', 'content_ids',
    ];
}

// 备份完整性：已知表齐全（按名比对）+ schema_version 匹配
function gblog_verify_install(PDO $pdo = null): array
{
    $res = [
        'ok' => false,
        'driver' => '',
        'tables_expected' => gblog_known_tables(),
        'tables_present' => [],
        'tables_missing' => [],
        'tables_extra' => [],
        'schema_version' => null,
        'schema_expected' => defined('GBLOG_SCHEMA_VERSION') ? (int)GBLOG_SCHEMA_VERSION : 4,
        'sites_total' => null,
        'article_count' => null,
        'article_ok' => false,
        'fk_count' => null,
        'error' => '',
    ];
    if ($pdo === null) {
        if (function_exists('db')) {
            try { $pdo = db(); } catch (Throwable $e) { $pdo = null; }
        }
        if ($pdo === null && function_exists('gblog_install_pdo')) {
            $pdo = gblog_install_pdo();
        }
    }
    if (!$pdo) {
        $res['error'] = '无法获取数据库连接，无法校验安装完整性。';
        return $res;
    }
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $res['driver'] = $driver;
        $actual = $pdo->query(
            $driver === 'sqlite'
                ? "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
                : 'SHOW TABLES'
        )->fetchAll(PDO::FETCH_COLUMN);
        $actual = array_map('strval', $actual);
        $known = $res['tables_expected'];
        $res['tables_present'] = array_values(array_intersect($known, $actual));
        $res['tables_missing'] = array_values(array_diff($known, $actual));
        $res['tables_extra']   = array_values(array_diff($actual, $known));

        try {
            $sv = $pdo->query("SELECT svalue FROM settings WHERE skey='schema_version' LIMIT 1")->fetchColumn();
            $res['schema_version'] = ($sv === false || $sv === null) ? null : (int)$sv;
        } catch (Throwable $e) { $res['schema_version'] = null; }

        try {
            $res['sites_total'] = (int)$pdo->query('SELECT COUNT(*) FROM sites')->fetchColumn();
// 栏目 slug 允许自定义，只要求至少一个启用的文章类栏目承载首页
            $res['article_count'] = (int)$pdo->query("SELECT COUNT(*) FROM sites WHERE type='article' AND enabled=1")->fetchColumn();
            $res['article_ok'] = ($res['article_count'] >= 1);
        } catch (Throwable $e) { /* sites 表可能不存在 */ }

        if ($driver === 'mysql') {
            try {
                $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
                $names = implode("','", [
                    'fk_cat_site', 'fk_post_site', 'fk_post_cat', 'fk_pt_post', 'fk_pt_tag',
                    'fk_song_post', 'fk_comment_post', 'fk_react_user', 'fk_react_post',
                ]);
                $st = $pdo->prepare(
                    "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA = ? AND CONSTRAINT_NAME IN ('" . $names . "')"
                );
                $st->execute([$db]);
                $res['fk_count'] = (int)$st->fetchColumn();
            } catch (Throwable $e) { $res['fk_count'] = null; }
        }

        $res['ok'] = ($res['tables_missing'] === [])
                  && ($res['schema_version'] === $res['schema_expected'])
                  && $res['article_ok'];
    } catch (Throwable $e) {
        $res['error'] = $e->getMessage();
    }
    return $res;
}

function gblog_backup_sign(string $sql): string
{
    $sql = (string)preg_replace('/^-- GBLOG-SIG:.*\R?/m', '', $sql);
    return '-- GBLOG-SIG: ' . gblog_hmac($sql) . "\n" . $sql;
}

// 备份必须带本站 HMAC 签名
function gblog_backup_verify(string $sql): bool
{
    if (!preg_match('/^-- GBLOG-SIG:\s*([a-f0-9]{64})\s*$/m', $sql, $m)) return false;
    $body = (string)preg_replace('/^-- GBLOG-SIG:.*\R?/m', '', $sql);
    try {
        return hash_equals((string)$m[1], gblog_hmac($body));
    } catch (Throwable $e) {
        return false;
    }
}

// 恢复语句白名单：只允许本程序导出的四类语句，且表名必须在已知表集合内
function gblog_sql_allowed(string $stmt): bool
{
    $s = trim($stmt);
    if ($s === '') return false;
    $struct = (string)preg_replace("/'(?:\\\\.|''|[^'])*'|\"(?:\\\\.|\"\"|[^\"])*\"/s", "''", $s);
    if (preg_match('~/\*|\*/|;\s*[a-zA-Z_]~', $struct)) return false;
    if (preg_match('/\b(load_file|outfile|dumpfile|@@|information_schema|mysql\.|sys\.|performance_schema|grant|drop\s+database|create\s+(function|procedure|trigger|user)|set\s+global)\b/i', $struct)) return false;

    if (preg_match('/^SET\s+FOREIGN_KEY_CHECKS\s*=\s*[01]\s*$/i', $s)) return true;

    $tables = implode('|', gblog_known_tables());
    if (preg_match('/^DROP\s+TABLE\s+IF\s+EXISTS\s+`?(' . $tables . ')`?\s*$/i', $s)) return true;
    if (preg_match('/^CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?`?(' . $tables . ')`?\s*\(/i', $s)) return true;
    if (preg_match('/^INSERT\s+INTO\s+`?(' . $tables . ')`?\s*\([^)]*\)\s*VALUES\s*\(/i', $s)) return true;
    return false;
}

// 恢复码是备份签名之外的第二道门
function gblog_restore_code_ttl(): int
{
    return 900;
}

function gblog_restore_code_normalize(string $code): string
{
    return strtolower(str_replace(['-', ' ', "\t", "\r", "\n"], '', $code));
}

function gblog_restore_code_pending(): bool
{
    $c = $_SESSION['gblog_restore_code'] ?? null;
    if (!is_array($c) || empty($c['hash']) || (int)($c['expires'] ?? 0) < time()) {
        unset($_SESSION['gblog_restore_code']);
        return false;
    }
    return true;
}

function gblog_restore_code_create(): string
{
    $raw = bin2hex(random_bytes(10));
    $_SESSION['gblog_restore_code'] = [
        'hash'    => hash('sha256', $raw),
        'expires' => time() + gblog_restore_code_ttl(),
        'issued'  => time(),
    ];
    return implode('-', str_split($raw, 5));
}

function gblog_restore_code_verify(string $code): bool
{
    if (!gblog_restore_code_pending()) return false;
    if (!gblog_rate_allow('restore_code', gblog_client_ip(), 5, gblog_restore_code_ttl())) return false;
    $norm = gblog_restore_code_normalize($code);
    if (preg_match('/^[0-9a-f]{20}$/', $norm) !== 1) return false;
    return hash_equals((string)$_SESSION['gblog_restore_code']['hash'], hash('sha256', $norm));
}

function gblog_restore_code_consume(): void
{
    unset($_SESSION['gblog_restore_code']);
}

function gblog_restore_code_remaining(): int
{
    if (!gblog_restore_code_pending()) return 0;
    return max(0, (int)$_SESSION['gblog_restore_code']['expires'] - time());
}

function gblog_restore_protected_stmt(string $stmt): bool
{
    $s = trim($stmt);
    $tables = 'users|password_resets|rate_limits|security_logs';
    if (preg_match('/^DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?(' . $tables . ')`?\s*$/i', $s)) return true;
    if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(' . $tables . ')`?\s*\(/i', $s)) return true;
    if (preg_match('/^INSERT\s+INTO\s+`?(' . $tables . ')`?\s*[(\s]/i', $s)) return true;
    if (preg_match('/^INSERT\s+INTO\s+`?settings`?\s*\([^)]*\)\s*VALUES\s*\(/i', $s)
        && preg_match("/'app_secret'/i", $s)) return true;
    return false;
}

function gblog_role_caps(string $role): array
{
    $map = [
        'admin' => ['*'],
        'editor' => ['admin.login', 'post.view', 'post.edit', 'post.delete', 'song.edit', 'song.delete',
                     'comment.manage', 'category.manage', 'tag.manage', 'profile.edit'],
        'author' => ['admin.login', 'post.view', 'post.edit', 'song.edit', 'profile.edit'],
        'contributor' => ['admin.login', 'post.view', 'post.edit', 'profile.edit'],
        'member' => ['profile.edit'],
    ];
    return $map[$role] ?? [];
}

function gblog_role_can(string $role, string $cap): bool
{
    $caps = gblog_role_caps($role);
    return in_array('*', $caps, true) || in_array($cap, $caps, true);
}

// 是否属于「可登录后台」的账号类型（管理员这类账号禁止前台找回）
function gblog_is_backend_role(string $role): bool
{
    return gblog_role_can($role, 'admin.login') || $role === 'admin';
}

// 未知页面必须拒绝，绝不回退到最低能力 post.view。
function gblog_admin_page_cap(string $page): ?string
{
    $map = [
        'index.php' => 'post.view', 'post_edit.php' => 'post.edit', 'post_delete.php' => 'post.delete',
        'song_edit.php' => 'song.edit', 'song_delete.php' => 'song.delete',
        'content_preview.php' => 'post.edit', 'upload_image.php' => 'post.edit',
        'editor_assets.php' => 'post.edit',
        'novel_chapters.php' => 'post.edit', 'comments.php' => 'comment.manage',
        'users.php' => 'user.manage', 'sites.php' => 'settings.manage',
        'themes.php' => 'settings.manage',
        'links.php' => 'settings.manage',
        'settings.php' => 'settings.manage', 'backup.php' => 'system.backup',
        'appcenter.php' => 'system.apps',
        'password.php' => 'profile.edit', 'logout.php' => 'profile.edit',
    ];
    return $map[$page] ?? null;
}

// 从请求数组按白名单取字符串值：缺字段 / 非标量 / 不在白名单统一回退默认值
function gblog_pick(array $src, string $key, array $allowed, string $default = ''): string
{
    $v = (isset($src[$key]) && is_scalar($src[$key])) ? (string)$src[$key] : $default;
    return in_array($v, $allowed, true) ? $v : $default;
}

// MySQL 外键约束 best-effort 施加（共享主机缺 REFERENCES 权限时绝不能阻断安装）
function gblog_apply_mysql_constraints(PDO $pdo): array
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') return [];
    // 约束脚本在 install/ 下（历史版本放在 sql/）。路径写错会静默 return，
    // 表现是新装站点永久没有外键、完成页却显示「0 条属正常」，所以这里不再静默。
    $file = __DIR__ . '/../../install/mysql_constraints.sql';
    if (!is_file($file)) $file = __DIR__ . '/../../sql/mysql_constraints.sql';
    if (!is_file($file)) return ['未找到 install/mysql_constraints.sql，外键约束未创建。'];
    $sql   = (string)file_get_contents($file);
    $sql   = preg_replace('/^--.*$/m', '', $sql);
    $stmts = array_filter(array_map('trim', explode(';', $sql)));
    $warnings = [];
    foreach ($stmts as $stmt) {
        if ($stmt === '') continue;
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            $code = (int)$e->getCode();
            $msg  = $e->getMessage();
            if (stripos($msg, 'duplicate') !== false
                || stripos($msg, 'already exists') !== false
                || $code === 1022 || $code === 1061 || $code === 1826) {
                continue;
            }
            if ($code === 1142) {
                $warnings[] = '数据库账号缺少 REFERENCES 权限，外键约束未创建（级联删除将不生效）。';
                continue;
            }
            $warnings[] = '外键约束未创建：' . substr($msg, 0, 120);
        }
    }
    return array_values(array_unique($warnings));
}
