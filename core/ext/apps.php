<?php
require_once __DIR__ . '/../route/context.php';

// builtin 标记只认本机 manifest；第三方包不得自带，核心文件永不被插件改写
// 包 id 白名单，防目录穿越
function gblog_app_id_valid(string $id): bool
{
    return (bool)preg_match('/^[a-zA-Z0-9_-]{1,60}$/', $id) && $id[0] !== '_' && $id !== '.' && $id !== '..';
}

function gblog_app_base(string $type): ?string
{
    if ($type === 'theme')  return THEMES_DIR;
    if ($type === 'plugin') return PLUGINS_DIR;
    return null;
}

function gblog_app_dir(string $type, string $id): ?string
{
    $base = gblog_app_base($type);
    if ($base === null || !gblog_app_id_valid($id)) return null;
    $dir = $base . '/' . $id;
    return is_dir($dir) ? $dir : null;
}

function gblog_read_manifest(string $type, string $id): ?array
{
    $dir = gblog_app_dir($type, $id);
    if ($dir === null) return null;

    $file = $dir . '/manifest.json';
    if (!is_file($file) && $type === 'theme') $file = $dir . '/theme.json';
    $raw  = is_file($file) ? (string)file_get_contents($file) : '';
    $json = $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($json)) $json = [];

    $defaultEntry = $type === 'plugin' ? 'plugin.php' : 'theme.php';
    return [
        'id'            => $id,
        'type'          => $type,
        'name'          => (string)($json['name'] ?? $id),
        'version'       => (string)($json['version'] ?? '1.0'),
        'author'        => (string)($json['author'] ?? ''),
        'desc'          => (string)($json['desc'] ?? ($json['description'] ?? '')),
        'requires_core' => (string)($json['requires_core'] ?? '1.0.0'),
        'entry'         => (string)($json['entry'] ?? $defaultEntry),
        'assets'        => is_array($json['assets'] ?? null) ? $json['assets'] : [],
        'hooks'         => is_array($json['hooks'] ?? null) ? $json['hooks'] : [],
        'settings'      => (string)($json['settings'] ?? ''),
        'builtin'       => (bool)($json['builtin'] ?? false),
        'dir'           => $dir,
    ];
}

function gblog_app_is_builtin(string $type, string $id): bool
{
    $m = gblog_read_manifest($type, $id);
    return $m !== null && !empty($m['builtin']);
}

// manifest.settings 只允许应用目录内的相对 php 文件
function gblog_app_settings_file(array $manifest): ?string
{
    $rel = trim((string)($manifest['settings'] ?? ''));
    if ($rel === '' || strpos($rel, '..') !== false) return null;
    if (!preg_match('~^[a-zA-Z0-9_\-/]+\.php$~', $rel)) return null;
    $file = rtrim((string)($manifest['dir'] ?? ''), '/\\') . '/' . ltrim($rel, '/\\');
    return is_file($file) ? $file : null;
}

function gblog_core_compatible(string $requires): bool
{
    if (trim($requires) === '') return true;
    return version_compare(GBLOG_CORE_VERSION, trim($requires), '>=');
}

function gblog_app_records(): array
{
    $out = [];
    try {
        $rows = db()->query('SELECT * FROM apps')->fetchAll();
        foreach ($rows as $r) $out[$r['type'] . ':' . $r['app_id']] = $r;
    } catch (Throwable $e) {
    }
    return $out;
}

function gblog_theme_in_use(string $id): bool
{
    try {
        if ((string)get_active_theme() === $id) return true;
        if ((string)get_setting('home_theme', 'default') === $id) return true;
    } catch (Throwable $e) {
    }
    return false;
}

function gblog_scan_apps(): array
{
    $records = gblog_app_records();
    $out     = [];
    foreach (['theme', 'plugin'] as $type) {
        $base = gblog_app_base($type);
        if ($base === null || !is_dir($base)) continue;
        foreach (scandir($base) as $id) {
            if (!gblog_app_id_valid($id)) continue;
            $m = gblog_read_manifest($type, $id);
            if ($m === null) continue;
            $key = $type . ':' . $id;
            $m['installed']   = isset($records[$key]);
            $m['compatible']  = gblog_core_compatible($m['requires_core']);
            $m['enabled']     = $type === 'theme'
                ? gblog_theme_in_use($id)
                : ((int)($records[$key]['enabled'] ?? 0) === 1);
            $m['has_entry']   = is_file($m['dir'] . '/' . $m['entry']);
            $out[] = $m;
        }
    }
    usort($out, function ($a, $b) {
        return strcmp($a['type'] . $a['id'], $b['type'] . $b['id']);
    });
    return $out;
}

function gblog_register_app(string $type, string $id, string $version, bool $enabled = false): void
{
    if (!gblog_app_id_valid($id) || gblog_app_base($type) === null) return;
    $pdo = db();
    $pdo->prepare('DELETE FROM apps WHERE app_id = ? AND type = ?')->execute([$id, $type]);
    $st = $pdo->prepare('INSERT INTO apps (app_id, type, enabled, version) VALUES (?,?,?,?)');
    $st->execute([$id, $type, $enabled ? 1 : 0, $version]);
}

function gblog_set_app_enabled(string $type, string $id, bool $enabled): void
{
    if (!gblog_app_id_valid($id)) return;
    $st = db()->prepare('UPDATE apps SET enabled = ? WHERE app_id = ? AND type = ?');
    $st->execute([$enabled ? 1 : 0, $id, $type]);
}

function gblog_remove_app_record(string $type, string $id): void
{
    if (!gblog_app_id_valid($id)) return;
    db()->prepare('DELETE FROM apps WHERE app_id = ? AND type = ?')->execute([$id, $type]);
}

function gblog_load_apps(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $records = gblog_app_records();
        foreach ($records as $key => $r) {
            if ((int)$r['enabled'] !== 1) continue;
            [$type, $id] = explode(':', $key, 2);
            $m = gblog_read_manifest($type, $id);
            if ($m === null || !gblog_core_compatible($m['requires_core'])) continue;
            $entry = $m['dir'] . '/' . $m['entry'];
            if (!is_file($entry)) continue;
            try {
                include_once $entry;
            } catch (Throwable $e) {
            }
        }
    } catch (Throwable $e) {
    }
}

function gblog_app_file_allowed(string $name): bool
{
    $base = strtolower(basename($name));
    if (in_array($base, ['config.php', 'install.php', '.htaccess', 'web.config', '.user.ini'], true)) return false;
    if (preg_match('/\.(phtml|pht|phar|php[3-9]|shtml|shtm|cgi|pl|py|sh|exe|dll|so|svg|svgz|ini|conf|yml|yaml|env)$/i', $name)) return false;
    return (bool)preg_match('/\.(php|css|js|json|md|txt|png|jpe?g|gif|webp|ico|woff2?|ttf|eot|map)$/i', $name);
}

// 兼容文件头的 declare(strict_types=1)（它必须是第一条语句）
function gblog_app_guard_php(string $data): string
{
    $guard = "if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }\n";
    if (preg_match('/^(\xEF\xBB\xBF?<\?php\s*)(declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;)/i', $data, $m)) {
        return $m[1] . $m[2] . "\n" . $guard . substr($data, strlen($m[0]));
    }
    if (preg_match('/^\xEF\xBB\xBF?<\?php\s*/i', $data, $m)) {
        return "<?php " . $guard . substr($data, strlen($m[0]));
    }
    return $data;
}

// 应用包安装：校验路径穿越 / 文件白名单 / manifest / requires_core / 目标已存在。
function gblog_install_zip(string $zipPath): array
{
    if (!class_exists('ZipArchive')) return [false, '服务器未开启 zip 扩展，无法安装应用包。', '', ''];
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) return [false, '无法打开 zip 文件。', '', ''];

    $entries = [];
    $total   = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name === false) continue;
        $name = str_replace('\\', '/', $name);
        if ($name === '' || $name[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $name) || strpos($name, "\0") !== false) {
            $zip->close();
            return [false, '压缩包包含非法路径，已拒绝安装。', '', ''];
        }
        if (substr($name, -1) === '/') continue;
        if (!gblog_app_file_allowed($name)) {
            $zip->close();
            return [false, '压缩包包含不允许的文件：' . $name, '', ''];
        }
        $data = $zip->getFromIndex($i);
        if ($data === false) { $zip->close(); return [false, '压缩包读取失败。', '', '']; }
        $total += strlen($data);
        if ($total > 8 * 1024 * 1024 || $i > 300) {
            $zip->close();
            return [false, '应用包过大或文件过多，已拒绝安装。', '', ''];
        }
        $entries[$name] = $data;
    }
    $zip->close();

    $prefix = '';
    $manifestName = '';
    foreach (array_keys($entries) as $n) {
        $b = strtolower(basename($n));
        if ($b === 'manifest.json' || $b === 'theme.json') {
            $prefix = (strpos($n, '/') !== false) ? substr($n, 0, strrpos($n, '/') + 1) : '';
            $manifestName = $n;
            break;
        }
    }
    if ($manifestName === '') return [false, '应用包缺少 manifest.json。', '', ''];
    $meta = json_decode($entries[$manifestName], true);
    if (!is_array($meta)) return [false, 'manifest.json 不是合法 JSON。', '', ''];
    if (trim((string)($meta['name'] ?? '')) === '') return [false, 'manifest 缺少 name。', '', ''];
    if (trim((string)($meta['version'] ?? '')) === '') return [false, 'manifest 缺少 version。', '', ''];
    if (!empty($meta['builtin'])) return [false, '内置标记只能由随程序附带的应用包使用，安装包已拒绝。', '', ''];

    $type = (string)($meta['type'] ?? '');
    $id   = (string)($meta['id'] ?? '');
    if (!in_array($type, ['theme', 'plugin'], true) || !gblog_app_id_valid($id)) {
        return [false, 'manifest 的 type/id 不合法（type 只能 theme 或 plugin）。', '', ''];
    }
    if (!gblog_core_compatible((string)($meta['requires_core'] ?? '1.0.0'))) {
        return [false, '该应用要求核心版本 ≥ ' . (string)$meta['requires_core'] . '，当前为 ' . GBLOG_CORE_VERSION . '。', '', ''];
    }
    $entryFile = (string)($meta['entry'] ?? ($type === 'plugin' ? 'plugin.php' : 'theme.php'));
    if ($entryFile === '' || $entryFile[0] === '/' || strpos($entryFile, '..') !== false
        || strpos($entryFile, '\\') !== false || !preg_match('/\.php$/i', $entryFile)) {
        return [false, 'manifest 的 entry 不合法。', '', ''];
    }
    $target = ($type === 'theme' ? THEMES_DIR : PLUGINS_DIR) . '/' . $id;
    if (is_dir($target)) return [false, '应用 ' . $id . ' 已存在，请先卸载或改用其他 id。', '', ''];

    if (!@mkdir($target, 0755, true) && !is_dir($target)) {
        return [false, '目录不可写，请检查 themes/ 或 plugins/ 权限。', '', ''];
    }
    $written = [];
    $fail = function (string $why) use (&$written, $target): array {
        foreach ($written as $f) { @unlink($f); }
        @rmdir($target);
        return [false, $why, '', ''];
    };
    foreach ($entries as $name => $data) {
        if ($prefix !== '' && strpos($name, $prefix) !== 0) continue;
        $rel = $prefix !== '' ? substr($name, strlen($prefix)) : $name;
        if ($rel === '' || substr($rel, -1) === '/') continue;
        if (preg_match('/\.php$/i', $rel)) {
            if (preg_match('/^\xEF\xBB\xBF?<\?php\b/i', $data) !== 1) {
                return $fail('应用包含非标准 PHP 文件：' . $rel);
            }
            $data = gblog_app_guard_php($data);
        }
        $dest = $target . '/' . $rel;
        $dir  = dirname($dest);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        if (@file_put_contents($dest, $data) === false) {
            return $fail('写入文件失败：' . $rel);
        }
        $written[] = $dest;
    }
    if (strtolower(basename($manifestName)) === 'theme.json' && !is_file($target . '/manifest.json')) {
        $meta['id']   = $id;
        $meta['type'] = 'theme';
        @file_put_contents($target . '/manifest.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    gblog_register_app($type, $id, (string)($meta['version'] ?? '1.0'), false);
    return [true, '安装成功：' . (string)($meta['name'] ?? $id) . '（默认未启用）。', $type, $id];
}

function gblog_app_assets(string $kind = 'css'): string
{
    $html = '';
    try {
        $records = gblog_app_records();
        $ctx     = function_exists('page_context') ? page_context() : null;
        $skin    = $ctx['skin'] ?? '';
        foreach ($records as $key => $r) {
            [$type, $id] = explode(':', $key, 2);
            $active = ((int)$r['enabled'] === 1) || ($type === 'theme' && $id === $skin);
            if (!$active) continue;
            $m = gblog_read_manifest($type, $id);
            if ($m === null) continue;
            $files = $m['assets'][$kind] ?? [];
            if (!is_array($files)) continue;
            foreach ($files as $rel) {
                $rel = ltrim((string)$rel, '/');
                if ($rel === '' || strpos($rel, '..') !== false) continue;
                if (!preg_match('/\.' . ($kind === 'css' ? 'css' : 'js') . '$/i', $rel)) continue;
                $relPath = ($type === 'theme' ? 'usr/themes/' : 'usr/plugins/') . rawurlencode($id) . '/' . $rel;
                // Web 地址必须根绝对，避免路径式 URL 下相对地址解析到错误目录
                $url = (function_exists('gblog_web_base') ? gblog_web_base() : '/') . $relPath;
                $stamp = '';
                if (defined('GBLOG_ROOT') && is_file(GBLOG_ROOT . '/' . $relPath)) {
                    $stamp = '?v=' . (int)@filemtime(GBLOG_ROOT . '/' . $relPath);
                }
                $html .= $kind === 'css'
                    ? '<link rel="stylesheet" href="' . e($url . $stamp) . '">' . "\n"
                    : '<script src="' . e($url . $stamp) . '" defer></script>' . "\n";
            }
        }
    } catch (Throwable $e) {
    }
    return $html;
}
