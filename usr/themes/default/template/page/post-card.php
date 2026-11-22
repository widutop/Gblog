<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/**
 * 通用内容小卡：入参 $item（一行内容）。
 * 列表页由 post_card_list 预设 $GLOBALS['gblog_card_ctx']（meta/cover_site/react）复用同一份标记；
 * 模板里直接 post_card($row) 也能用，缺省 meta=cat、无互动态。
 */
$item = $item ?? [];
if (!$item) return;
$ctx       = (array)($GLOBALS['gblog_card_ctx'] ?? []);
$metaName  = (string)($ctx['meta'] ?? 'cat');
$coverSite = (string)($ctx['cover_site'] ?? '');
$reactMap  = (array)($ctx['react'] ?? []);
$isSong    = !empty($item['is_song']);
$cardUrl   = (string)($item['url'] ?? '');
if ($cardUrl === '') $cardUrl = gblog_post_url($item);
$pid       = (int)($item['id'] ?? 0);
?>
<article class="post-card card<?= $isSong ? ' post-card-song' : '' ?>">
  <a class="post-card-cover-link" href="<?= e($cardUrl) ?>">
    <?php if (!empty($item['cover'])): ?>
      <img src="<?= e($item['cover']) ?>" alt="<?= e($item['title'] ?? '') ?>">
    <?php else: ?>
      <span class="post-card-placeholder" data-cover="<?= e((string)($item['site_slug'] ?? $coverSite)) ?>"><?= gblog_icon('file') ?></span>
    <?php endif; ?>
  </a>
  <div class="post-card-body">
    <h2 class="post-title"><a href="<?= e($cardUrl) ?>"><?= e($item['title'] ?? '') ?></a></h2>
    <p class="post-excerpt"><?= e(excerpt($item['excerpt'] ?: ($item['content'] ?? ''), 120)) ?></p>
    <div class="post-meta">
      <span class="post-meta-left">
        <span><?= e(($item['author'] ?? '') ?: '匿名') ?></span>
        <span><?= e(fmt_date($item['created_at'] ?? '')) ?></span>
        <?php if ($metaName === 'site'): ?>
        <span class="cat"><?= e($item['site_name'] ?? ($item['category_name'] ?? '未分类')) ?></span>
        <?php else: ?>
        <span class="cat"><?= e($item['category_name'] ?? '未分类') ?></span>
        <?php endif; ?>
      </span>
      <span class="post-meta-right">
        <span class="meta-info"><?= gblog_icon($isSong ? 'music' : 'eye') ?> <?= (int)($item['view_count'] ?? 0) ?></span>
        <span class="meta-info"><?= gblog_icon('comment') ?> <?= (int)($item['comment_count'] ?? 0) ?></span>
        <?php if (!$isSong && $pid > 0): ?>
        <button type="button" class="react-btn<?= !empty($reactMap[$pid . ':favorite']) ? ' active' : '' ?>" data-post-id="<?= $pid ?>" data-type="favorite"><span class="react-ico"><?= gblog_icon('star') ?></span> <span class="rc"><?= (int)($item['favorite_count'] ?? 0) ?></span></button>
        <button type="button" class="react-btn<?= !empty($reactMap[$pid . ':like']) ? ' active' : '' ?>" data-post-id="<?= $pid ?>" data-type="like"><?= gblog_icon('like') ?> <span class="rc"><?= (int)($item['like_count'] ?? 0) ?></span></button>
        <?php endif; ?>
      </span>
    </div>
  </div>
</article>
