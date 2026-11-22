<?php
/* 编辑器资源安装/切换：在线 CDN 与本地服务器双模式。
 * 仅登录且具备 post.edit 能力、CSRF 通过才受理。
 * 本地文件固定落在 usr/uploads/editor/，由接口下载或管理员手动上传，文件名与校验值白名单化。 */
require_once __DIR__ . '/../inc/auth.php';
gblog_admin_require_cap('post.edit');

header('Content-Type: application/json; charset=utf-8');
function gb_assets_json(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function gb_editor_asset_root(): string
{
    return dirname(__DIR__, 2) . '/usr/uploads/editor/';
}

function gb_editor_assets(): array
{
    $jsBases = [
        'https://cdn.jsdelivr.net/npm/@wangeditor/editor@5.1.23/dist/',
        'https://npm.elemecdn.com/@wangeditor/editor@5.1.23/dist/',
        'https://unpkg.com/@wangeditor/editor@5.1.23/dist/',
    ];
    $jsUrls = [];
    $cssUrls = [];
    foreach ($jsBases as $b) {
        $jsUrls[] = $b . 'index.js';
        $cssUrls[] = $b . 'css/style.css';
    }
    return [
        'wangeditor-js' => [
            'rel' => 'wangeditor/index.js', 'urls' => $jsUrls,
            'needle' => 'createEditor', 'min' => 100000,
        ],
        'wangeditor-css' => [
            'rel' => 'wangeditor/css/style.css', 'urls' => $cssUrls,
            'needle' => '.w-e-', 'min' => 1000,
        ],
        'turndown-js' => [
            'rel' => 'turndown.js',
            'urls' => [
                'https://cdn.jsdelivr.net/npm/turndown@7.2.0/dist/turndown.js',
                'https://unpkg.com/turndown@7.2.0/dist/turndown.js',
            ],
            'needle' => 'TurndownService', 'min' => 10000,
        ],
    ];
}

function gb_asset_valid(array $asset, string $body): bool
{
    return strlen($body) >= $asset['min'] && strpos($body, $asset['needle']) !== false;
}

function gb_asset_put(string $rel, string $body): bool
{
    $target = gb_editor_asset_root() . $rel;
    $dir = dirname($target);
    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) return false;
    $tmp = $target . '.tmp-' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $body, LOCK_EX) === false) return false;
    if (!@rename($tmp, $target)) { @unlink($tmp); return false; }
    return true;
}

function gb_asset_fetch(string $url): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Gblog-Editor-Assets/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ($body !== false && $code >= 200 && $code < 300) ? (string)$body : '';
    }
    $ctx = stream_context_create(['http' => ['timeout' => 20, 'header' => "User-Agent: Gblog-Editor-Assets/1.0\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
    return is_string($body) ? $body : '';
}

function gb_asset_state(): array
{
    $root = gb_editor_asset_root();
    $files = [];
    $all = true;
    foreach (gb_editor_assets() as $key => $asset) {
        $path = $root . $asset['rel'];
        $size = is_file($path) ? (int)@filesize($path) : 0;
        $ok = $size >= $asset['min'];
        if (!$ok) $all = false;
        $files[$key] = ['size' => $size, 'ok' => $ok, 'url' => gblog_web_base() . 'usr/uploads/editor/' . $asset['rel']];
    }
    return ['installed' => $all, 'files' => $files];
}

$action = (string)($_POST['action'] ?? $_GET['action'] ?? 'status');
$state = gb_asset_state();
$mode = get_setting('editor_assets_mode', 'online') === 'local' ? 'local' : 'online';

if ($action === 'status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    gb_assets_json(['ok' => true, 'mode' => $mode, 'installed' => $state['installed'], 'files' => $state['files']]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    gb_assets_json(['ok' => false, 'message' => '操作校验失败，请刷新页面后重试'], 403);
}

if ($action === 'mode') {
    $want = (string)($_POST['mode'] ?? '');
    if (!in_array($want, ['online', 'local'], true)) {
        gb_assets_json(['ok' => false, 'message' => '无效的资源模式']);
    }
    if ($want === 'local' && !$state['installed']) {
        gb_assets_json(['ok' => false, 'message' => '本地资源尚未安装完整，请先下载或上传']);
    }
    save_setting('editor_assets_mode', $want);
    gb_assets_json(['ok' => true, 'mode' => $want]);
}

if ($action === 'download') {
    $done = [];
    foreach (gb_editor_assets() as $key => $asset) {
        $body = '';
        foreach ($asset['urls'] as $url) {
            $candidate = gb_asset_fetch($url);
            if (gb_asset_valid($asset, $candidate)) { $body = $candidate; break; }
        }
        if ($body === '' || !gb_asset_put($asset['rel'], $body)) {
            gb_assets_json([
                'ok' => false,
                'message' => '下载失败：' . $key . '（所有节点均不可用或内容校验未通过）',
                'done' => $done,
            ]);
        }
        $done[] = $key;
    }
    save_setting('editor_assets_mode', 'local');
    $state = gb_asset_state();
    gb_assets_json(['ok' => true, 'mode' => 'local', 'installed' => $state['installed'], 'files' => $state['files']]);
}

if ($action === 'upload') {
    $files = $_FILES['file'] ?? $_FILES['files'] ?? null;
    if (is_array($files) && isset($files['name']) && is_array($files['name'])) {
        $list = [];
        foreach ($files['name'] as $i => $name) {
            $list[] = [
                'name' => (string)$name,
                'tmp_name' => (string)($files['tmp_name'][$i] ?? ''),
                'error' => (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            ];
        }
    } else {
        $list = is_array($files) ? [$files] : [];
    }
    if (!$list) gb_assets_json(['ok' => false, 'message' => '未接收到文件']);
    $byName = [];
    foreach (gb_editor_assets() as $key => $asset) {
        $byName[basename($asset['rel'])] = $asset;
    }
    $saved = [];
    foreach ($list as $f) {
        $name = basename((string)($f['name'] ?? ''));
        if (!isset($byName[$name]) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($f['tmp_name'])) {
            gb_assets_json(['ok' => false, 'message' => '文件名或内容不符，只接受 index.js / style.css / turndown.js']);
        }
        $asset = $byName[$name];
        $body = (string)@file_get_contents((string)$f['tmp_name']);
        if (!gb_asset_valid($asset, $body) || !gb_asset_put($asset['rel'], $body)) {
            gb_assets_json(['ok' => false, 'message' => $name . ' 内容校验未通过']);
        }
        $saved[] = $name;
    }
    $state = gb_asset_state();
    if ($state['installed']) save_setting('editor_assets_mode', 'local');
    gb_assets_json(['ok' => true, 'saved' => $saved, 'installed' => $state['installed'], 'files' => $state['files']]);
}

gb_assets_json(['ok' => false, 'message' => '未知操作']);
