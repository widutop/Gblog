<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$posts      = $posts      ?? [];
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$slug       = $slug       ?? '';
$gbTabItems  = $gbTabItems  ?? [];
$gbEmptyText = $gbEmptyText ?? '该栏目暂无内容。';
$gbBaseUrl   = 'index.php?do=site&slug=' . rawurlencode((string)$slug);
$gbPageBase  = $gbPageBase  ?? $gbBaseUrl;
$gbCoverSite = $gbCoverSite ?? $slug;
// meta 第三格显示什么：cat=分类名（单栏目列表默认）/ site=栏目名（首页、搜索等跨栏目混排）
$gbMetaName  = $gbMetaName  ?? 'cat';

$reactMap = [];
if (function_exists('current_user')) {
    $me = current_user();
    if ($me && $posts) {
        $ids = [];
        foreach ($posts as $pp) { if (empty($pp['is_song'])) $ids[] = (int)$pp['id']; }
        $reactMap = get_user_reactions((int)$me['id'], $ids);
    }
}
?>
<div class="gb-list-region" data-gb-region>
<?php if ($gbTabItems): ?>
<?php $GLOBALS['gblog_hide_sidebar_categories'] = true; ?>
<nav class="gb-list-tabs">
  <span class="gb-tab-group">
  <?php foreach ($gbTabItems as $gt): ?>
  <a class="gb-list-tab<?= !empty($gt['active']) ? ' is-active' : '' ?>" href="<?= e((string)$gt['url']) ?>"><?= e((string)$gt['name']) ?></a>
  <?php endforeach; ?>
  </span>
</nav>
<?php endif; ?>
<section class="content list-posts gb-generic-list">
  <?php if (!$posts): ?>
    <div class="card gb-list-empty"><p><?= e($gbEmptyText) ?></p></div>
  <?php else: ?>
    <?php $GLOBALS['gblog_card_ctx'] = ['meta' => $gbMetaName, 'cover_site' => $gbCoverSite, 'react' => $reactMap]; ?>
    <?php foreach ($posts as $p): ?>
      <?php post_card($p); ?>
    <?php endforeach; ?>
    <?php unset($GLOBALS['gblog_card_ctx']); ?>

    <?php render_slot('pagination', [
        'pgPage'  => $page,
        'pgPages' => $totalPages,
        'pgBase'  => $gbPageBase,
        'pgLabel' => '列表分页',
    ]); ?>
  <?php endif; ?>
</section>
</div>
