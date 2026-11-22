<?php
$page_title = '修改密码';
require_once __DIR__ . '/../inc/header.php';

$pdo = db();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = '表单校验失败，请重试。';
    } else {
        $old = (string)($_POST['old_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$_SESSION['admin_id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($old, (string)$hash)) {
            $error = '原密码不正确。';
        } elseif (!gblog_password_strong($new)) {
            $error = '新密码至少 8 位，且必须同时包含大写字母和小写字母。';
        } elseif ($new !== $confirm) {
            $error = '两次输入的新密码不一致。';
        } else {
            $aid = (int)$_SESSION['admin_id'];
            $pdo->prepare('UPDATE users SET password_hash = ?, password_version = password_version + 1, must_change_password = 0 WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $aid]);
            gblog_reset_token_invalidate_user($aid);
            $_SESSION['admin_pv'] = (int)($GLOBALS['gblog_admin_user']['password_version'] ?? 0) + 1;
            unset($_SESSION['force_pw_change']);
            gblog_audit('password.changed', $aid, 'admin.password');
            $success = '密码已修改，其他设备的旧登录已失效。';
        }
    }
}
?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($_GET['force']) || !empty($_SESSION['force_pw_change'])): ?>
<div class="alert alert-error">管理员已重置你的密码，请先设置新密码（至少 8 位，含大小写字母）再继续。</div>
<?php endif; ?>

<div class="card" style="max-width:480px;">
  <h2>修改密码</h2>
  <form method="post" action="<?= gblog_admin_url('account/password.php') ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="form-field" style="margin-bottom:14px;">
      <label>原密码</label>
      <input type="password" name="old_password" required>
    </div>
    <div class="form-field" style="margin-bottom:14px;">
      <label>新密码（≥8 位，含大小写字母）</label>
      <input type="password" name="new_password" required>
    </div>
    <div class="form-field" style="margin-bottom:18px;">
      <label>确认新密码</label>
      <input type="password" name="confirm_password" required>
    </div>
    <button type="submit" class="btn btn-primary">保存新密码</button>
  </form>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>
