<?php
/**
 * Template Name: 搜索结果
 * View: search
 * Context: $q, $posts, $page, $totalPages, $total
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$q          = (string)($q ?? '');
$posts      = $posts ?? [];
$page       = max(1, (int)($page ?? 1));
$totalPages = max(1, (int)($totalPages ?? 1));
$total      = (int)($total ?? 0);
?>
<div class="gb-search-head">
  <form class="gb-search-form" action="index.php" method="get">
    <input type="hidden" name="do" value="search">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="搜索文章标题 / 正文…">
    <button type="submit">搜索</button>
  </form>
</div>
<?php if ($q === ''): ?>
<div class="gb-generic-list gb-search-empty"><p>输入关键词开始搜索。</p></div>
<?php else: ?>
<?php render_slot('post_card_list', [
    'posts'       => $posts,
    'slug'        => '',
    'page'        => $page,
    'totalPages'  => $totalPages,
    'gbTabItems'  => [
        ['name' => '全部内容', 'url' => gblog_pretty_base('index.php?do=home'), 'active' => false],
        ['name' => '搜索「' . mb_substr($q, 0, 20) . '」 · ' . $total . ' 篇', 'url' => gblog_pretty_base('index.php?do=search&q=' . rawurlencode($q)), 'active' => true],
    ],
    'gbPageBase'  => gblog_pretty_base('index.php?do=search&q=' . rawurlencode($q)),
    'gbEmptyText' => '没有找到与「' . $q . '」相关的文章。',
    'gbMetaName'  => 'site',
]); ?>
<?php endif; ?>
