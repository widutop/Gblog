<?php
require_once __DIR__ . '/../../core/bootstrap.php';

if (is_user_logged_in()) {
    header('Location: index.php?do=profile');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?login=1');
    exit;
}

$error = '';
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$username = trim((string)($_POST['username'] ?? ''));
$ip       = gblog_client_ip();

if (!gblog_rate_allow('login_ip', $ip, 20, 900)
    || !gblog_rate_allow('login_user', strtolower($username), 8, 900)) {
    $error = '尝试次数过多，请 15 分钟后再试。';
    gblog_audit('login.throttled', 0, $username);
} elseif (!csrf_check()) {
    $error = '表单校验失败，请重试。';
} else {
    $u = verify_login($username, (string)($_POST['password'] ?? ''));
    if ($u) {
        session_regenerate_id(true);
        $_SESSION['user_id']  = (int)$u['id'];
        $_SESSION['user_pv']  = (int)($u['password_version'] ?? 0);
        $redirect = ((int)($u['must_change_password'] ?? 0) === 1)
            ? 'index.php?do=profile&forcepw=1'
            : gblog_safe_back_url('index.php?do=profile');
        // 从 /post/xxx 等漂亮 URL 页面登录时，相对 Location 会跳错目录
        if (function_exists('gblog_root_absolute')) $redirect = gblog_root_absolute($redirect);
        gblog_rate_reset('login_user', strtolower($username));
        gblog_audit('login.ok', (int)$u['id'], 'frontend');
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'redirect' => $redirect]);
            exit;
        }
        header('Location: ' . $redirect);
        exit;
    }
    $error = '用户名或密码错误。';
    gblog_audit('login.failed', 0, $username);
}

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
}
header('Location: index.php?login=1');
exit;
