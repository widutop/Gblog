<?php
/* 输出缓冲铁律：后台 HTML 先输出，后续 header() 跳转与下载响应头都依赖此层缓冲；下载分支 header 前循环 ob_end_clean()。 */
ob_start();

require_once __DIR__ . '/../../core/data/db.php';
require_once __DIR__ . '/../../core/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function gblog_admin_deny(): void
{
    session_unset();
    session_destroy();
    header('Location: ' . gblog_admin_url('login.php'));
    exit;
}

function gblog_admin_require_cap(string $cap): void
{
    $role = (string)($GLOBALS['gblog_admin_role'] ?? '');
    if (!gblog_role_can($role, $cap)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        exit('当前账号无权执行此操作（需要权限：' . htmlspecialchars($cap, ENT_QUOTES, 'UTF-8') . '）。');
    }
}

if (!is_admin_logged_in()) gblog_admin_deny();

if (!empty($_SESSION['admin_expires']) && time() > $_SESSION['admin_expires']) {
    gblog_admin_deny();
}

$aid = (int)$_SESSION['admin_id'];
$st  = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$st->execute([$aid]);
$adminUser = $st->fetch();
$adminRole = (string)($adminUser['role'] ?? '');

if (!$adminUser || $adminUser['status'] !== 'active' || !gblog_role_can($adminRole, 'admin.login')) {
    gblog_admin_deny();
}
if ((int)($_SESSION['admin_pv'] ?? -1) !== (int)($adminUser['password_version'] ?? 0)) {
    gblog_admin_deny();
}
if ((int)($adminUser['must_change_password'] ?? 0) === 1) {
    $_SESSION['force_pw_change'] = 1;
}

$_SESSION['admin_username'] = $adminUser['username'];
$_SESSION['admin_role']     = $adminRole;
$_SESSION['admin_expires']  = time() + ADMIN_SESSION_TTL;
$GLOBALS['gblog_admin_user'] = $adminUser;
$GLOBALS['gblog_admin_role'] = $adminRole;

// 必须用 SCRIPT_NAME：PHP_SELF 含 PATH_INFO，basename() 会绕过能力判定。
$gblogPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$gblogCap  = gblog_admin_page_cap($gblogPage);
if ($gblogCap === null || !gblog_role_can($adminRole, $gblogCap)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>无权限</title>'
       . '<style>body{font-family:system-ui,sans-serif;background:#f6f7f9;color:#333;display:flex;align-items:center;'
       . 'justify-content:center;height:100vh;margin:0}.box{background:#fff;padding:32px 40px;border-radius:12px;'
       . 'box-shadow:0 6px 24px rgba(0,0,0,.08);text-align:center}a{color:#1d6fe0}</style></head><body>'
       . '<div class="box"><h2 style="margin:0 0 10px;">当前账号无权访问此页面</h2>'
       . '<p style="color:#888;font-size:14px;">需要权限：' . htmlspecialchars($gblogCap ?? '未知页面（已拒绝）', ENT_QUOTES, 'UTF-8') . '</p>'
       . '<a href="' . gblog_admin_url('index.php') . '">返回后台首页</a></div></body></html>';
    exit;
}
