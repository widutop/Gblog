<?php
require_once __DIR__ . '/../../core/bootstrap.php';

$page_title = '设置新密码';
$token = (string)($_GET['token'] ?? ($_POST['token'] ?? ''));
$row   = gblog_reset_token_lookup($token);
$error = '';
$done  = false;
$weakCodeNotice = false;

if (!$row) {
    $error = '链接无效或已过期，请重新找回密码。';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw  = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');
    $ident = substr(hash('sha256', $token), 0, 32);

    if (!gblog_rate_allow('reset_submit', $ident, 8, 900)) {
        $error = '尝试次数过多，请稍后再试。';
    } elseif (!csrf_check()) {
        $error = '表单校验失败，请刷新页面重试。';
    } elseif (!gblog_password_strong($pw)) {
        $error = '新密码至少 8 位，且必须同时包含大写字母和小写字母。';
    } elseif ($pw !== $pw2) {
        $error = '两次输入的新密码不一致。';
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            if (!gblog_reset_token_consume((int)$row['id'])) {
                $pdo->rollBack();
                $error = '链接已被使用，请重新找回密码。';
            } else {
                $uid = (int)$row['user_id'];
                $st  = $pdo->prepare('SELECT security_code FROM users WHERE id = ? LIMIT 1');
                $st->execute([$uid]);
                $weakCodeNotice = gblog_recovery_is_weak($st->fetchColumn() ?: null);

                $pdo->prepare('UPDATE users SET password_hash = ?, password_version = password_version + 1, must_change_password = 0 WHERE id = ?')
                    ->execute([password_hash($pw, PASSWORD_DEFAULT), $uid]);
                $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ?')->execute([$uid]);
                $pdo->commit();

                gblog_audit('password.reset.completed', $uid, 'channel=' . (string)$row['channel']);
                gblog_rate_reset('forgot_user', '');
                unset($_SESSION['user_id'], $_SESSION['admin_id'], $_SESSION['user_pv'], $_SESSION['admin_pv']);
                $done = true;
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = '设置失败，请稍后重试。';
        }
    }
}

get_header();
?>
<section class="content">
  <div class="card" style="max-width:460px;margin:24px auto;padding:24px;">
    <h2 style="margin:0 0 14px;font-size:18px;">设置新密码</h2>
    <?php if ($done): ?>
      <p style="color:var(--text-muted);line-height:1.7;">密码已更新，该账号所有旧登录状态均已失效。请用新密码重新登录。</p>
      <?php if ($weakCodeNotice): ?>
        <p style="margin-top:10px;font-size:14px;color:#b26a00;">提示：你的恢复码强度不足，请联系管理员重新生成一个。</p>
      <?php endif; ?>
      <a class="btn btn-primary" href="index.php?login=1" style="margin-top:14px;display:inline-block;">去登录</a>
    <?php elseif ($error !== '' && !$row): ?>
      <p style="color:#c0392b;"><?= e($error) ?></p>
      <a class="btn btn-primary" href="index.php?forgot=1" style="margin-top:10px;display:inline-block;">重新找回</a>
    <?php else: ?>
      <?php if ($error !== ''): ?><div style="margin-bottom:12px;padding:8px 10px;border:1px solid #f5c2c2;background:#fde8e8;color:#c0392b;border-radius:6px;font-size:14px;"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="index.php?do=reset">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div style="margin-bottom:12px;">
          <label style="font-size:14px;display:block;margin-bottom:4px;">新密码 *</label>
          <input type="password" name="password" required minlength="8" style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
          <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">至少 8 位，必须同时包含大写字母和小写字母。</div>
        </div>
        <div style="margin-bottom:16px;">
          <label style="font-size:14px;display:block;margin-bottom:4px;">确认新密码 *</label>
          <input type="password" name="password2" required minlength="8" style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;background:var(--bg);color:var(--text);">
        </div>
        <button type="submit" style="width:100%;padding:10px;background:var(--primary);color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:15px;">保存新密码</button>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php get_footer(); ?>
