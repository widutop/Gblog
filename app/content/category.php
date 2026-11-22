<?php
require_once __DIR__ . '/../../core/bootstrap.php';

// 分类按 id 定位（categories.slug 已移除；旧 ?do=category&slug= 链接不再支持）
$id = (int)($_GET['id'] ?? 0);

$cat = null;
$posts = [];
$total = 0;
$page = 1;
if ($id > 0) {
    $stmt = db()->prepare('SELECT c.id, c.name, c.site_id, s.slug AS site_slug, s.type AS site_type FROM categories c LEFT JOIN sites s ON s.id = c.site_id WHERE c.id = ? AND s.enabled = 1 LIMIT 1');
    $stmt->execute([$id]);
    $cat = $stmt->fetch();
}

if ($cat) {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $total = count_published_posts('p.category_id = ?', [$cat['id']]);
    $posts = get_published_posts('p.category_id = ?', [$cat['id']], $page, list_per_page());
}
$totalPages = max(1, (int)ceil($total / list_per_page()));

$page_title = $cat ? $cat['name'] : '分类';

$reactMap = [];
if (function_exists('current_user')) {
    $me = current_user();
    if ($me && $posts) {
        $ids = [];
        foreach ($posts as $rp) { $ids[] = (int)$rp['id']; }
        $reactMap = get_user_reactions((int)$me['id'], $ids);
    }
}
$gbTabItems  = [];
$gbPageBase  = gblog_pretty_base('index.php?do=category&id=' . (int)($cat['id'] ?? $id));
$gbCoverSite = (string)($cat['site_slug'] ?? '');
if ($cat && !empty($cat['site_id'])) {
    $cs = db()->prepare('SELECT id, name FROM categories WHERE site_id = ? ORDER BY id ASC');
    $cs->execute([(int)$cat['site_id']]);
    $gbCats     = $cs->fetchAll();
    $gbSiteUrl  = 'index.php?do=site&slug=' . rawurlencode($gbCoverSite);
    $gbTabItems[] = ['name' => '全部内容', 'url' => $gbSiteUrl, 'active' => false];
    foreach ($gbCats as $gc) {
        $gbTabItems[] = [
            'name'   => (string)$gc['name'],
            'url'    => 'index.php?do=category&id=' . (int)$gc['id'],
            'active' => ((int)$gc['id'] === (int)$cat['id']),
        ];
    }
}

get_header();

// 分类列表与分页挂全局标签数据源
$GLOBALS['gblog_loop'] = $posts;
if ($cat) {
    $GLOBALS['gblog_pagination'] = [
        'page'  => $page,
        'total' => $totalPages,
        'base'  => gblog_pretty_base('index.php?do=category&id=' . (int)$cat['id']),
    ];
}
if (!render_view('list', get_defined_vars(), (string)($cat['site_slug'] ?? 'post'))) {
    echo '<section class="content"><div class="card"><p>缺少分类列表模板。</p></div></section>';
}

get_footer();
