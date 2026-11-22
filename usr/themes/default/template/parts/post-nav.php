<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/** 全站唯一「上一篇 / 下一篇」零件：外观只改主题 style/layout.css。 */
$adjacent = $adjacent ?? ['prev' => null, 'next' => null];
if (empty($adjacent['prev']) && empty($adjacent['next'])) return;

$navCover = static function (array $row): string {
    $c = (string)($row['cover'] ?? '');
    return preg_match('#^/usr/uploads/[A-Za-z0-9_./-]+\.(png|jpe?g|gif|webp)$#i', $c) ? $c : '';
};
?>
<nav class="post-nav">
  <?php foreach (['prev', 'next'] as $navSide):
      $navRow = $adjacent[$navSide] ?? null;
      if (!$navRow) continue;
      $navImg = $navCover($navRow);
  ?>
    <a class="<?= $navSide ?>" href="index.php?do=post&id=<?= (int)$navRow['id'] ?>">
      <span class="post-nav-media" aria-hidden="true"><?php if ($navImg !== ''): ?><img src="<?= e($navImg) ?>" alt="" loading="lazy"><?php endif; ?></span>
      <span class="post-nav-inner">
        <span class="post-nav-title"><?= e($navRow['title']) ?></span>
        <span class="post-nav-meta">
          <span class="nav-label"><?= $navSide === 'prev' ? '&lsaquo; 上一篇' : '下一篇 &rsaquo;' ?></span>
          <span class="nav-date"><?= !empty($navRow['created_at']) ? e(fmt_date((string)$navRow['created_at'])) : '' ?></span>
        </span>
      </span>
    </a>
  <?php endforeach; ?>
</nav>
