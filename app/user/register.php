<?php
require_once __DIR__ . '/../../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?register=1');
    exit;
}

$registerOpen = get_setting('register_open', '1') === '1';
$error = '';
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($registerOpen) {
    if (!gblog_rate_allow('register_ip', gblog_client_ip(), 5, 3600)) {
        $error = '注册过于频繁，请稍后再试。';
    } elseif (!csrf_check()) {
        $error = '表单校验失败，请重试。';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password2'] ?? '');
        if ($password !== $password2) {
            $error = '两次密码输入不一致。';
        } else {
            $r = register_user(
                trim((string)($_POST['username'] ?? '')),
                $password,
                trim((string)($_POST['email'] ?? '')),
                trim((string)($_POST['nickname'] ?? '')),
                trim((string)($_POST['phone'] ?? '')),
                trim((string)($_POST['qq'] ?? '')),
                trim((string)($_POST['wechat'] ?? ''))
            );
            if ($r['ok']) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$r['user']['id'];
                $_SESSION['user_pv'] = 0;
                gblog_audit('register.ok', (int)$r['user']['id'], '');
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => true, 'redirect' => gblog_safe_back_url('index.php')]);
                    exit;
                }
                header('Location: ' . gblog_safe_back_url('index.php'));
                exit;
            }
            $error = $r['msg'];
        }
    }
} else {
    $error = '本站已关闭注册。';
}

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $error ?: '注册失败，请重试。']);
    exit;
}
header('Location: index.php?register=1');
exit;
