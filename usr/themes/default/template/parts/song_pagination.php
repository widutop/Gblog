<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$pgPage  = max(1, (int)($pgPage ?? 1));
$pgPages = max(1, (int)($pgPages ?? 1));
$pgBase  = (string)($pgBase ?? '');
$pgLabel = (string)($pgLabel ?? '歌曲分页');
if ($pgPages <= 1 || $pgBase === '') return;
$pgPage = min($pgPage, $pgPages);

// 基址可能是不带查询串的漂亮 URL（/songs、/songs.html），也可能是带参数的
// 原 ?do= 形式；据此选择 ?page= 或 &page=，避免拼出 /songs&page=2 这类坏链。
$pgGlue = strpos($pgBase, '?') === false
    ? '?'
    : (in_array(substr($pgBase, -1), ['?', '&'], true) ? '' : '&amp;');

$pgNums = [];
for ($i = 1; $i <= $pgPages; $i++) {
    if ($i === 1 || $i === $pgPages || abs($i - $pgPage) <= 2) $pgNums[] = $i;
}
?>
<nav class="gb-song-pagination" aria-label="<?= e($pgLabel) ?>">
  <?php if ($pgPage > 1): ?>
  <a class="gb-song-page" href="<?= e($pgBase) . $pgGlue . 'page=' . ($pgPage - 1) ?>">上一页</a>
  <?php else: ?>
  <span class="gb-song-page is-off" aria-disabled="true">上一页</span>
  <?php endif; ?>
  <?php $pgPrevNum = 0; foreach ($pgNums as $i): ?>
    <?php if ($pgPrevNum && $i - $pgPrevNum > 1): ?><span class="is-gap" aria-hidden="true">&hellip;</span><?php endif; ?>
    <?php if ($i === $pgPage): ?>
    <span class="gb-song-page is-current" aria-current="page"><?= $i ?></span>
    <?php else: ?>
    <a class="gb-song-page" href="<?= e($pgBase) . $pgGlue . 'page=' . $i ?>" aria-label="第 <?= $i ?> 页"><?= $i ?></a>
    <?php endif; ?>
  <?php $pgPrevNum = $i; endforeach; ?>
  <?php if ($pgPage < $pgPages): ?>
  <a class="gb-song-page" href="<?= e($pgBase) . $pgGlue . 'page=' . ($pgPage + 1) ?>">下一页</a>
  <?php else: ?>
  <span class="gb-song-page is-off" aria-disabled="true">下一页</span>
  <?php endif; ?>
</nav>
