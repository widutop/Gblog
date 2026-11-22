<?php
$page_title = '删除歌曲';
require_once __DIR__ . '/../inc/header.php';

$id = (int)($_GET['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare("SELECT id, title FROM songs WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$song = $stmt->fetch();

if (!$song) {
    echo '<div class="alert alert-error">歌曲不存在或已被删除。</div>';
    require __DIR__ . '/../inc/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $pdo->prepare("DELETE FROM comments WHERE target_type = 'song' AND target_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM songs WHERE id = ?")->execute([$id]);
    $returnTab = gblog_pick($_POST, 'return_tab', ['all', 'post', 'song', 'software', 'novel'], 'song');
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
  <p style="margin-bottom:16px;">确定要删除歌曲 <b><?= e($song['title']) ?></b> 吗？此操作不可恢复。</p>
  <form method="post" action="<?= gblog_admin_url('content/song_delete.php?id=' . $id) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <button type="submit" class="btn btn-danger">确认删除</button>
    <a class="btn" href="<?= gblog_admin_url('index.php?tab=song') ?>">取消</a>
  </form>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>
