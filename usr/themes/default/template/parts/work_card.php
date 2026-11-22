<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$p         = $p         ?? [];
$coverSlug = (string)($coverSlug ?? 'novel');
?>
<div class="novel-card nb-work-card">
  <?php if (!empty($p['cover'])): ?>
    <img class="novel-cover" src="<?= e($p['cover']) ?>" alt="<?= e($p['title']) ?>">
  <?php else: ?>
    <div class="novel-cover placeholder" data-cover="<?= e($coverSlug) ?>"><?= gblog_icon('book') ?></div>
  <?php endif; ?>
  <div class="novel-info">
    <div class="novel-title"><a href="index.php?do=post&id=<?= (int)$p['id'] ?>"><?= e($p['title']) ?></a></div>
    <div class="novel-meta">
      <?= e($p['author'] ?: '佚名') ?>
      <?php if (!empty($p['category_name'])): ?><span class="novel-cat"><?= e($p['category_name']) ?></span><?php endif; ?>
      <span class="novel-status"><?= (int)($p['chapter_count'] ?? 0) ?> 章</span>
    </div>
    <?php if (!empty($p['excerpt'])): ?><div class="novel-desc"><?= e($p['excerpt']) ?></div><?php endif; ?>
    <?php if (!empty($p['latest_title']) && !empty($p['latest_id'])): ?>
      <div class="novel-latest">最新：<a href="index.php?do=chapter&id=<?= (int)$p['latest_id'] ?>"><?= e($p['latest_title']) ?></a></div>
    <?php endif; ?>
  </div>
</div>
