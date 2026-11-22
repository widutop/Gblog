<?php
require_once __DIR__ . '/../../core/bootstrap.php';

// 分类按 id 过滤（categories.slug 已移除）
$catId  = (int)($_GET['cat'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));

$st = db()->prepare("SELECT * FROM sites WHERE slug = 'music' AND enabled = 1 LIMIT 1");
$st->execute();
$musicSite = $st->fetch();

if (!$musicSite) {
    gblog_not_found('音乐栏目不可用', '音乐栏目不存在或已关闭。');
}

$cs = db()->prepare("SELECT id, name FROM categories WHERE site_id = ? ORDER BY id");
$cs->execute([$musicSite['id']]);
$cats = $cs->fetchAll();

$currentCat = null;
$catMissing = false;
if ($catId > 0) {
    $cc = db()->prepare("SELECT id, name FROM categories WHERE id = ? AND site_id = ? LIMIT 1");
    $cc->execute([$catId, $musicSite['id']]);
    $currentCat = $cc->fetch();
    if (!$currentCat) $catMissing = true;
}

$where  = "status = 'published' AND site_id = ?";
$params = [(int)$musicSite['id']];
if ($currentCat) {
    $where .= " AND category_id = ?";
    $params[] = $currentCat['id'];
}
$cnt = db()->prepare("SELECT COUNT(*) FROM songs WHERE " . $where);
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();

$perPage    = posts_per_page();
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$ls = db()->prepare("SELECT * FROM songs WHERE " . $where . " ORDER BY id DESC LIMIT " . $offset . "," . $perPage);
$ls->execute($params);
$songs = $ls->fetchAll();

$showListPlayer = get_setting('music_player_list', '1') === '1';

$page_title = $currentCat ? ($currentCat['name'] . ' · 歌曲列表') : '全部歌曲';
$_site_data = $musicSite;
$GLOBALS['current_site_data'] = $musicSite;
get_header();

$GLOBALS['gblog_loop'] = $songs;
$GLOBALS['gblog_pagination'] = [
    'page'  => $page,
    'total' => $totalPages,
    'base'  => 'index.php?do=songs' . ($currentCat ? '&cat=' . (int)$currentCat['id'] : ''),
];
if (!render_view('list', get_defined_vars(), (string)$musicSite['slug'])) {
    echo '<section class="content"><div class="card"><p>缺少歌曲列表模板。</p></div></section>';
}

get_footer();
