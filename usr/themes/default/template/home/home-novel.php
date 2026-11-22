<?php
/**
 * Template Name: 栏目首页·小说
 * Context: $posts, $site, $page, $totalPages
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$posts      = $posts      ?? [];
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$slug       = $slug       ?? '';

foreach ($posts as &$__lp) {
    $__lp['chapter_count'] = $__lp['chapter_count'] ?? 0;
    $__lp['latest_title']  = $__lp['latest_title']  ?? '';
    $__lp['latest_id']     = $__lp['latest_id']     ?? 0;
}
unset($__lp);
?>
<section class="content list-posts">
  <?php if (!$posts): ?>
    <div class="card"><p>这个栏目还没有作品。</p></div>
  <?php else: ?>
    <div class="novel-grid">
      <?php foreach ($posts as $p): ?>
        <div class="novel-card">
          <?php if (!empty($p['cover'])): ?>
            <img class="novel-cover" src="<?= e($p['cover']) ?>" alt="<?= e($p['title']) ?>">
          <?php else: ?>
            <div class="novel-cover placeholder" data-cover="<?= e($slug) ?>"><?= gblog_icon('book') ?></div>
          <?php endif; ?>
          <div class="novel-info">
            <div class="novel-title"><a href="<?= e(gblog_post_url($p)) ?>"><?= e($p['title']) ?></a></div>
            <div class="novel-meta">
              <?= e($p['author'] ?: '佚名') ?>
              <?php if (!empty($p['category_name'])): ?><span class="novel-cat"><?= e($p['category_name']) ?></span><?php endif; ?>
              <span class="novel-status"><?= (int)$p['chapter_count'] ?> 章</span>
            </div>
            <?php if (!empty($p['excerpt'])): ?><div class="novel-desc"><?= e($p['excerpt']) ?></div><?php endif; ?>
            <?php if (!empty($p['latest_title']) && !empty($p['latest_id'])): ?>
              <div class="novel-latest">最新章节：<a href="index.php?do=chapter&id=<?= (int)$p['latest_id'] ?>"><?= e($p['latest_title']) ?></a></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php render_slot('pagination', [
        'pgPage'  => $page,
        'pgPages' => $totalPages,
        'pgBase'  => gblog_pretty_base('index.php?do=site&slug=' . rawurlencode((string)$slug)),
        'pgLabel' => '小说列表分页',
    ]); ?>
  <?php endif; ?>
</section>
