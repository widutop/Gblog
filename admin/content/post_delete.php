<?php
// 删除文章（二次确认 + CSRF 校验）
$page_title = '删除文章';
require_once __DIR__ . '/../inc/header.php';

$id = (int)($_GET['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare("SELECT id, title FROM posts WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    echo '<div class="alert alert-error">文章不存在或已被删除。</div>';
    require __DIR__ . '/../inc/footer.php';
    exit;
}

$done = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    // 评论已脱离 posts 外键，删除文章时须手动清理其评论（含子回复）
    $pdo->prepare("DELETE FROM comments WHERE target_type = 'post' AND target_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$id]);
    $returnTab = gblog_pick($_POST, 'return_tab', ['all', 'post', 'song', 'software', 'novel'], 'all');
    $returnKw = trim((string)($_POST['return_kw'] ?? ''));
    $returnStatus = gblog_pick($_POST, 'return_status', ['all', 'published', 'draft'], 'all');
    $returnPage = max(1, (int)($_POST['return_page'] ?? 1));
    $returnUrl = gblog_admin_url('index.php?tab=' . urlencode($returnTab) . '&kw=' . urlencode($returnKw) . '&status=' . urlencode($returnStatus) . '&page=' . $returnPage);
    header('Location: ' . $returnUrl);
    exit;
}
?>
<div class="card">
  <h2>删除确认</h2>
  <p style="margin-bottom:16px;">
    确定要删除文章 <b><?= e($post['title']) ?></b> 吗？<br>
    该文章下的<b>所有歌曲、评论、标签关联</b>将一并删除，此操作不可恢复。
  </p>
  <form method="post" action="<?= gblog_admin_url('content/post_delete.php?id=' . $id) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <button type="submit" class="btn btn-danger">确认删除</button>
    <a class="btn" href="<?= gblog_admin_url('index.php') ?>">取消</a>
  </form>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>
