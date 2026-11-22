<?php
// 验证通过只发一次性令牌，绝不改密或回显密码。
require_once __DIR__ . '/../../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?forgot=1');
    exit;
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
       && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$generic = '验证未通过：请检查用户名与验证信息是否准确。连续失败将临时锁定。';

function forgot_reply(bool $ok, string $message, bool $isAjax, string $captcha, string $redirect = ''): void
{
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'       => $ok,
            'error'    => $ok ? '' : $message,
            'message'  => $ok ? $message : '',
            'redirect' => $redirect,
            'captcha'  => $captcha,
        ]);
        exit;
    }
    header('Location: index.php?forgot=1');
    exit;
}

$ip       = gblog_client_ip();
$username = trim((string)($_POST['username'] ?? ''));

if (!gblog_rate_allow('forgot_ip', $ip, 15, 900)
    || ($username !== '' && !gblog_rate_allow('forgot_user', strtolower($username), 5, 900))) {
    gblog_audit('password.reset.throttled', 0, $username);
    forgot_reply(false, '尝试次数过多，请 15 分钟后再试。', $isAjax, gblog_captcha_get());
}

if (!csrf_check()) {
    forgot_reply(false, $generic, $isAjax, gblog_captcha_get());
}
if (!gblog_captcha_check($_POST['captcha'] ?? '')) {
    forgot_reply(false, '验证问题回答不正确，请重新计算。', $isAjax, gblog_captcha_get());
}

$mode = ($_POST['mode'] ?? 'code') === '3in1' ? '3in1' : 'code';
$securityCode = trim((string)($_POST['security_code'] ?? ''));
$phone  = trim((string)($_POST['phone'] ?? ''));
$qq     = trim((string)($_POST['qq'] ?? ''));
$wechat = trim((string)($_POST['wechat'] ?? ''));

if ($username === '') {
    forgot_reply(false, $generic, $isAjax, gblog_captcha_get());
}

$st = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
$st->execute([$username]);
$u = $st->fetch();

if (!$u || $u['status'] !== 'active' || gblog_is_backend_role((string)$u['role'])) {
    gblog_audit('password.reset.denied', 0, $username);
    forgot_reply(false, $generic, $isAjax, gblog_captcha_get());
}

$passed = false;
if ($mode === 'code') {
    if ($securityCode !== '') {
        $passed = gblog_recovery_verify($securityCode, $u['security_code'] ?? null);
        if ($passed) {
            gblog_recovery_upgrade_hash((int)$u['id'], $securityCode, $u['security_code'] ?? null);
        }
    }
} else {
// 三号必须三项都已绑定、且用户三项都填写，否则该通道不可用
    $bound = ((string)($u['phone'] ?? '') !== '')
          && ((string)($u['qq'] ?? '') !== '')
          && ((string)($u['wechat'] ?? '') !== '');
    if ($bound && $phone !== '' && $qq !== '' && $wechat !== '') {
        $passed = hash_equals((string)$u['phone'], $phone)
               && hash_equals((string)$u['qq'], $qq)
               && hash_equals((string)$u['wechat'], $wechat);
    }
}

if (!$passed) {
    gblog_audit('password.reset.failed', (int)$u['id'], 'channel=' . $mode);
    forgot_reply(false, $generic, $isAjax, gblog_captcha_get());
}

try {
    $token = gblog_reset_token_create((int)$u['id'], $mode);
} catch (Throwable $e) {
    forgot_reply(false, '服务暂时不可用，请稍后再试。', $isAjax, gblog_captcha_get());
}
gblog_audit('password.reset.issued', (int)$u['id'], 'channel=' . $mode);
gblog_rate_reset('forgot_user', strtolower($username));

forgot_reply(
    true,
    '验证通过，正在进入设置新密码…',
    $isAjax,
    gblog_captcha_get(),
    'index.php?do=reset&token=' . $token
);
