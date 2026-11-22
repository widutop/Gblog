<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/**
 * 详情页正文零件（文章 / 图片 / 软件 三类详情页共用）。
 *
 * 三个详情页模板（single.php / single-image.php / single-software.php）原本逐行重复，
 * 现统一由本零件拼装；差异通过入参控制：
 *   $gbDetailHeadSlot   详情头零件名（软件页为 software-detail-head，文章/图片为 detail_head）
 *   $gbDetailPreHead    是否在 <article> 之前额外输出头部包裹（软件信息卡）
 *   $gbDetailTitleSlot  标题输出方式：'slot' 走 render_slot（文章/图片），'action' 走 do_action（软件页标题在信息卡内）
 *   $gbDetailHasHead    是否渲染详情头零件（软件页为 false：下载入口已在软件信息卡内，不再重复下载卡）
 */

$post     = $post     ?? [];
$adjacent = $adjacent ?? ['prev' => null, 'next' => null];
$dlMsg    = $dlMsg    ?? '';

// 点赞 / 收藏状态：三个详情页都需要，零件内统一取一次
$reactFav = $reactLike = false;
if (function_exists('current_user')) {
    $me = current_user();
    if ($me && !empty($post['id'])) {
        $rm = get_user_reactions((int)$me['id'], [(int)$post['id']]);
        $reactFav  = !empty($rm[$post['id'] . ':favorite']);
        $reactLike = !empty($rm[$post['id'] . ':like']);
    }
}

$gbDetailHeadSlot   = $gbDetailHeadSlot   ?? 'detail_head';
$gbDetailPreHead    = !empty($gbDetailPreHead);
$gbDetailTitleSlot  = $gbDetailTitleSlot  ?? 'slot';
$gbDetailHasHead    = !isset($gbDetailHasHead) ? true : (bool)$gbDetailHasHead;
?>
<?php if ($gbDetailPreHead): ?>
<div class="soft-detail-head-wrap">
  <?php render_slot('software-detail-head', get_defined_vars()); ?>
</div>
<?php endif; ?>

<article class="post-full">
  <?php if ($gbDetailTitleSlot === 'action'): ?>
    <?php if (function_exists('do_action')) do_action('detail_title', get_defined_vars()); ?>
  <?php else: ?>
    <?php render_slot('detail_title', get_defined_vars()); ?>
  <?php endif; ?>

  <?php if ($gbDetailHasHead): ?>
    <?php render_slot($gbDetailHeadSlot, get_defined_vars()); ?>
  <?php endif; ?>

  <?php if (!empty($post['songs'])): ?>
    <section class="music-section">
      <h2 class="music-section-title"><?= gblog_icon('music') ?> 音乐</h2>
      <?php foreach ($post['songs'] as $song): ?>
        <?php render_slot('player', get_defined_vars()); ?>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <?php if (function_exists('do_action')) do_action('before_content', $post); ?>
  <?php if (trim((string)$post['content']) !== ''): ?>
    <div class="post-content">
      <?= render_content($post['content']) ?>
    </div>
  <?php endif; ?>
  <?php if (function_exists('do_action')) do_action('after_content', $post); ?>

  <div class="post-foot">
    <div class="post-foot-left">
      <?php if (!empty($post['category_name'])): ?>
        <a class="meta-act" href="<?= e(gblog_url('category', ['id' => (int)($post['category_id'] ?? 0)])) ?>"><?= gblog_icon('categories') ?> <?= e($post['category_name']) ?></a>
      <?php endif; ?>
      <span class="meta-info"><?= gblog_icon('clock') ?> <?= e(fmt_date($post['created_at'])) ?></span>
      <span class="meta-info"><?= gblog_icon('user') ?> <?= e($post['author'] ?: '匿名') ?></span>
    </div>
    <div class="post-foot-right">
      <span class="meta-info"><?= gblog_icon('eye') ?> <?= (int)($post['view_count'] ?? 0) ?></span>
      <a class="meta-act" href="#comments"><?= gblog_icon('comment') ?> <?= isset($comments) ? (int)count($comments) : 0 ?></a>
      <button type="button" class="react-btn<?= $reactFav ? ' active' : '' ?>" data-post-id="<?= (int)($post['id'] ?? 0) ?>" data-type="favorite"><span class="react-ico"><?= gblog_icon('star') ?></span> <span class="rc"><?= (int)($post['favorite_count'] ?? 0) ?></span></button>
      <button type="button" class="react-btn<?= $reactLike ? ' active' : '' ?>" data-post-id="<?= (int)($post['id'] ?? 0) ?>" data-type="like"><?= gblog_icon('like') ?> <span class="rc"><?= (int)($post['like_count'] ?? 0) ?></span></button>
    </div>
  </div>

  <?php render_slot('post-nav', get_defined_vars()); ?>
</article>

<?php render_slot('related', get_defined_vars()); ?>

<script src="<?= gblog_theme_asset_url('assets/js/player.js') ?>" defer></script>
