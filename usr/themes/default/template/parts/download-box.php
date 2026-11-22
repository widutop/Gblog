<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/**
 * 资源下载卡：入参 $post、$dlMsg（可选）、$dlOk（可选）。
 * 无 download_url 的内容整块不输出；扣费/登录态由 download_state() 判定。
 */
$post  = $post  ?? [];
if (!$post && function_exists('gblog_current_item')) $post = (array)gblog_current_item();
$dlMsg = $dlMsg ?? '';
$dlOk  = !empty($dlOk);
if (trim((string)($post['download_url'] ?? '')) === '') return;
$dl = download_state($post, current_user());
?>
<section class="download-box">
  <h3><?= gblog_icon('package') ?> 资源下载</h3>
  <?php if ($dlMsg !== ''): ?><p class="comment-msg <?= $dlOk ? 'ok' : 'err' ?>"><?= e($dlMsg) ?></p><?php endif; ?>
  <?php if ($dl['allowed']): ?>
    <a class="btn btn-primary" href="<?= e(apply_filters('download_link', 'index.php?do=download&id=' . (int)$post['id'], $post)) ?>" target="_blank" rel="noopener"><?= gblog_icon('download') ?> 立即下载</a>
  <?php else: ?>
    <p class="dl-need"><?= e($dl['reason']) ?></p>
    <?php if ($dl['level'] === 'login'): ?>
      <a class="btn btn-primary" href="index.php?do=login">登录后下载</a>
    <?php else: ?>
      <form method="post" action="index.php?do=post&id=<?= (int)$post['id'] ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit" name="buy_dl" value="1" class="btn btn-primary">消耗 <?= (int)$dl['price'] ?> 金币下载</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</section>
