<?php
$page_title = '评论管理';
require_once __DIR__ . '/../inc/header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = '表单校验失败，请重试。';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $cid = (int)($_POST['id'] ?? 0);
        if ($cid <= 0) {
            $error = '参数错误。';
        } elseif ($action === 'approve') {
            set_comment_status($cid, 'approved');
            $success = '评论已通过。';
        } elseif ($action === 'hide') {
            set_comment_status($cid, 'hidden');
            $success = '评论已隐藏。';
        } elseif ($action === 'pending') {
            set_comment_status($cid, 'pending');
            $success = '评论已标记为待审核。';
        } elseif ($action === 'delete') {
            delete_comment($cid);
            $success = '评论已删除。';
        }
    }
}

$f_status = (string)($_GET['status'] ?? '');
$f_keyword = trim((string)($_GET['q'] ?? ''));
$comments = get_all_comments($f_status, $f_keyword);
$countPending = (int)db()->query("SELECT COUNT(*) FROM comments WHERE status='pending'")->fetchColumn();
?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
    <h2 style="margin:0;">评论管理
      <?php if ($countPending > 0): ?>
        <span class="badge badge-pending"><?= $countPending ?> 待审核</span>
      <?php endif; ?>
    </h2>
    <form method="get" action="<?= gblog_admin_url('content/comments.php') ?>" style="display:flex;gap:8px;flex-wrap:wrap;">
      <select name="status" class="admin-input-sm">
        <option value="">全部状态</option>
        <option value="pending" <?= $f_status === 'pending' ? 'selected' : '' ?>>待审核</option>
        <option value="approved" <?= $f_status === 'approved' ? 'selected' : '' ?>>已通过</option>
        <option value="hidden" <?= $f_status === 'hidden' ? 'selected' : '' ?>>已隐藏</option>
      </select>
      <input type="text" name="q" value="<?= e($f_keyword) ?>" placeholder="搜索昵称 / 内容" class="admin-input-sm">
      <button type="submit" class="btn btn-sm">筛选</button>
    </form>
  </div>

  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>评论</th><th>内容</th><th>状态</th><th>时间</th><th>操作</th></tr></thead>
      <tbody>
      <?php if (!$comments): ?>
        <tr><td colspan="5" style="color:#6b7280;">暂无评论。</td></tr>
      <?php else: ?>
        <?php foreach ($comments as $c): ?>
          <tr>
            <td style="max-width:320px;">
              <b><?= e($c['name']) ?></b>
              <?php if ($c['email']): ?> <span style="color:#8a919c;font-size:12px;">(<?= e($c['email']) ?>)</span><?php endif; ?>
              <div style="font-size:13px;color:#4a5161;margin-top:4px;word-break:break-all;"><?= nl2br(e($c['content'])) ?></div>
            </td>
            <td>
              <?php if (($c['target_type'] ?? 'post') === 'song'): ?>
                <a href="../index.php?do=song&id=<?= (int)$c['target_id'] ?>" target="_blank"><?= e($c['song_title'] ?: '（歌曲已删除）') ?></a>
                <span style="color:#8a919c;font-size:12px;">· 歌曲</span>
              <?php else: ?>
                <a href="../index.php?do=post&id=<?= (int)($c['target_id'] ?? 0) ?>" target="_blank"><?= e($c['post_title'] ?: '（文章已删除）') ?></a>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge <?= $c['status'] === 'approved' ? 'badge-on' : ($c['status'] === 'pending' ? 'badge-pending' : 'badge-off') ?>">
                <?= $c['status'] === 'approved' ? '已通过' : ($c['status'] === 'pending' ? '待审核' : '已隐藏') ?>
              </span>
            </td>
            <td><?= e(fmt_date($c['created_at'])) ?></td>
            <td style="white-space:nowrap;">
              <?php if ($c['status'] !== 'approved'): ?>
                <form method="post" action="<?= gblog_admin_url('content/comments.php') ?>" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-success">通过</button>
                </form>
              <?php endif; ?>
              <?php if ($c['status'] !== 'hidden'): ?>
                <form method="post" action="<?= gblog_admin_url('content/comments.php') ?>" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="hide"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <button type="submit" class="btn btn-sm">隐藏</button>
                </form>
              <?php else: ?>
                <form method="post" action="<?= gblog_admin_url('content/comments.php') ?>" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="pending"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <button type="submit" class="btn btn-sm">恢复待审</button>
                </form>
              <?php endif; ?>
              <form method="post" action="<?= gblog_admin_url('content/comments.php') ?>" style="display:inline;"
                    onsubmit="return confirm('确定删除这条评论？');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger">删除</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>
