<?php
/**
 * Template Name: 聚合首页·混排信息流（数据契约 $aggSections，全站/栏目聚合通用）
 * Context: $homePosts, $homeTabItems
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$aggSections = $aggSections ?? [];
$aggIcons    = $aggIcons    ?? ['default' => 'file'];

$rows = [];
foreach ($aggSections as $s) {
    foreach (($s['posts'] ?? []) as $p) {
        $p['_section'] = $s['name'] ?? '';
        $p['_icon']    = $s['icon'] ?? $aggIcons['default'];
        $p['_slug']    = $s['slug'] ?? '';
        $rows[] = $p;
    }
}
usort($rows, function ($a, $b) {
    return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
});
?>
<section class="agg-section">
  <div class="agg-section-head">
    <span class="agg-section-title"><span class="agg-icon"><?= gblog_icon('clock') ?></span>全部动态</span>
    <span class="agg-section-more">共 <?= count($rows) ?> 条</span>
  </div>
  <div class="agg-section-body">
    <?php if (!$rows): ?>
      <p style="color:var(--text-muted);margin:0;">暂无动态内容。</p>
    <?php else: ?>
      <div class="agg-recent-list">
        <?php foreach ($rows as $p): ?>
          <a class="agg-recent-item" href="index.php?do=post&id=<?= (int)$p['id'] ?>">
            <span class="agg-recent-thumb<?= empty($p['cover']) ? ' gb-cover-agg' : '' ?>"<?= empty($p['cover']) ? ' data-cover="' . e($p['_slug'] ?? '') . '"' : '' ?>><?php if (!empty($p['cover'])): ?><img src="<?= e($p['cover']) ?>" alt=""><?php else: ?><?= gblog_icon($p['_icon']) ?><?php endif; ?></span>
            <span class="agg-recent-body">
              <span class="agg-recent-title"><?= e($p['title']) ?></span>
              <span class="agg-recent-excerpt"><?= e(excerpt($p['excerpt'] ?? '', 100)) ?></span>
              <span class="agg-recent-meta">
                <span class="agg-recent-meta-left">
                  <span><?= e(fmt_date($p['created_at'])) ?></span>
                  <span><?= e($p['_section']) ?></span>
                </span>
                <span class="agg-recent-meta-right">
                  <span class="meta-info"><?= gblog_icon('eye') ?> <?= (int)($p['view_count'] ?? 0) ?></span>
                </span>
              </span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
