<?php
/**
 * Template Name: 栏目首页·图片
 * Context: $posts, $site, $page, $totalPages
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$posts      = $posts      ?? [];
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$slug       = $slug       ?? '';
?>
<section class="content list-posts">
  <?php if (!$posts): ?>
    <div class="card"><p>这个栏目还没有内容。</p></div>
  <?php else: ?>
    <div class="gb-image-grid">
      <?php foreach ($posts as $p): ?>
        <a href="<?= e(gblog_post_url($p)) ?>" class="gb-image-card" style="display:block;break-inside:avoid;border-radius:8px;overflow:hidden;position:relative;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.08);">
          <?php if (!empty($p['cover'])): ?>
            <img src="<?= e($p['cover']) ?>" alt="<?= e($p['title']) ?>" style="width:100%;display:block;">
          <?php else: ?>
            <span class="gb-cover-blank" data-cover="<?= e($slug) ?>"><?= gblog_icon('image') ?></span>
          <?php endif; ?>
          <div style="padding:10px 12px;">
            <div style="font-size:15px;font-weight:600;line-height:1.4;color:#333;"><?= e($p['title']) ?></div>
            <div style="font-size:13px;color:#999;margin-top:4px;"><?= e(fmt_date($p['created_at'])) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php render_slot('pagination', [
        'pgPage'  => $page,
        'pgPages' => $totalPages,
        'pgBase'  => gblog_pretty_base('index.php?do=site&slug=' . rawurlencode((string)$slug)),
        'pgLabel' => '图片列表分页',
    ]); ?>
  <?php endif; ?>
</section>
