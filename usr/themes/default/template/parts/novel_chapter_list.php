<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/**
 * 章节目录唯一零件（章节阅读页抽屉 / 小说简介页目录 tab 共用）。
 * 章节数 <= 200 时一页全出，视觉与旧版完全一致；超过 200 章由控制器按页切片，
 * 避免长篇把整本目录（9999 个 li / 1.3MB HTML）塞进页面。
 */
$chapters     = $chapters  ?? [];
$currentId    = (int)($currentId ?? 0);
$listClass    = (string)($listClass ?? 'novel-chapters');
// 章节页控制器用 $chapterTotal，简介页控制器用 $novelChapterTotal，两者都要认
$chapterTotal = (int)($chapterTotal ?? ($novelChapterTotal ?? count($chapters)));
$chapterPage  = max(1, (int)($chapterPage ?? 1));
$chapterPages = max(1, (int)($chapterPages ?? 1));
$chapterStart = max(1, (int)($chapterStart ?? 1));
$chapterBase  = (string)($chapterBase ?? '');
?>
<?php if (!$chapters): ?>
  <p class="empty">暂无章节。</p>
<?php else: ?>
  <ul class="<?= e($listClass) ?>">
    <?php foreach ($chapters as $i => $c): ?>
      <?php $cid = (int)($c['id'] ?? 0); ?>
      <li<?= $currentId > 0 && $cid === $currentId ? ' class="is-current"' : '' ?>>
        <a href="index.php?do=chapter&id=<?= $cid ?>"><span class="idx"><?= $chapterStart + $i ?>.</span><?= e($c['title']) ?></a>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($chapterPages > 1 && $chapterBase !== ''): ?>
    <?php
    $cpGlue = strpos($chapterBase, '?') === false ? '?' : (in_array(substr($chapterBase, -1), ['?', '&'], true) ? '' : '&amp;');
    $cpNums = [];
    for ($p = 1; $p <= $chapterPages; $p++) {
        if ($p === 1 || $p === $chapterPages || abs($p - $chapterPage) <= 2) $cpNums[] = $p;
    }
    $cpPrevNum = 0;
    ?>
    <nav class="gb-pagination gb-chapter-pager" aria-label="章节目录分页（共 <?= $chapterTotal ?> 章）">
      <?php if ($chapterPage > 1): ?>
        <a class="gb-page-btn" href="<?= e($chapterBase) . $cpGlue ?>chpage=<?= $chapterPage - 1 ?>">上一页</a>
      <?php else: ?>
        <span class="gb-page-btn is-off" aria-disabled="true">上一页</span>
      <?php endif; ?>
      <?php foreach ($cpNums as $p): ?>
        <?php if ($cpPrevNum && $p - $cpPrevNum > 1): ?><span class="is-gap" aria-hidden="true">&hellip;</span><?php endif; ?>
        <?php if ($p === $chapterPage): ?>
          <span class="gb-page-btn is-current" aria-current="page"><?= $p ?></span>
        <?php else: ?>
          <a class="gb-page-btn" href="<?= e($chapterBase) . $cpGlue ?>chpage=<?= $p ?>" aria-label="第 <?= $p ?> 页"><?= $p ?></a>
        <?php endif; ?>
        <?php $cpPrevNum = $p; ?>
      <?php endforeach; ?>
      <?php if ($chapterPage < $chapterPages): ?>
        <a class="gb-page-btn" href="<?= e($chapterBase) . $cpGlue ?>chpage=<?= $chapterPage + 1 ?>">下一页</a>
      <?php else: ?>
        <span class="gb-page-btn is-off" aria-disabled="true">下一页</span>
      <?php endif; ?>
    </nav>
    <p class="empty gb-chapter-range">第 <?= $chapterStart ?>–<?= $chapterStart + count($chapters) - 1 ?> 章 / 共 <?= $chapterTotal ?> 章</p>
  <?php endif; ?>
<?php endif; ?>
