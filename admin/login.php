<?php
require_once __DIR__ . '/../core/data/db.php';
require_once __DIR__ . '/../core/route/permalink.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $ip       = gblog_client_ip();

    if (!gblog_rate_allow('admin_login_ip', $ip, 20, 900)
        || !gblog_rate_allow('admin_login_user', strtolower($username), 8, 900)) {
        $error = '尝试次数过多，请 15 分钟后再试。';
        gblog_audit('admin.login.throttled', 0, $username);
    } elseif (!csrf_check()) {
        $error = '表单校验失败，请重试。';
    } elseif ($username === '' || $password === '') {
        $error = '请输入用户名和密码。';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!gblog_role_can((string)$user['role'], 'admin.login')) {
                $error = '该账号无权登录后台。';
            } elseif ($user['status'] !== 'active') {
                $error = '账号已被禁用。';
            } else {
                session_regenerate_id(true);
                $_SESSION['admin_id']       = (int)$user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_role']     = $user['role'];
                $_SESSION['admin_pv']       = (int)($user['password_version'] ?? 0);
                $_SESSION['admin_expires']  = time() + ADMIN_SESSION_TTL;
                gblog_rate_reset('admin_login_user', strtolower($username));
                gblog_audit('admin.login.ok', (int)$user['id'], (string)$user['role']);
                $target = ((int)($user['must_change_password'] ?? 0) === 1) ? gblog_admin_url('account/password.php?force=1') : gblog_admin_url('index.php');
                header('Location: ' . $target);
                exit;
            }
        } else {
            $error = '用户名或密码错误。';
        }
        gblog_audit('admin.login.failed', 0, $username);
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>登录 - 博客后台</title>
<link rel="stylesheet" href="<?= gblog_admin_url('assets/admin.css') ?>">
</head>
<body class="login-body">
  <div class="login-box">
    <h1>博客后台登录</h1>
    <p class="hint">请使用管理员账号登录；如需修改密码，请进入安全管理中的统一密码页面。</p>
    <?php if ($error !== ''): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= gblog_admin_url('login.php') ?>">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="form-field">
        <label>用户名</label>
        <input type="text" name="username" required autofocus>
      </div>
      <div class="form-field">
        <label>密码</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary">登 录</button>
    </form>
  </div>
</body>
</html>
