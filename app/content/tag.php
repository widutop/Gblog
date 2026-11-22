<?php
require_once __DIR__ . '/../../core/bootstrap.php';

set_page_context(gblog_infer_site('tag'), 'page');

// 标签按 id 定位（tags.slug 已移除；旧 ?do=tag&slug= 链接不再支持）
$id    = (int)($_GET['id'] ?? 0);
$tag   = null;
$posts = [];
$total = 0;
$page  = max(1, (int)($_GET['page'] ?? 1));

if ($id > 0) {
    $stmt = db()->prepare('SELECT id, name, site_id FROM tags WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $tag = $stmt->fetch();
}

if ($tag) {
    // 标签按栏目归属：专属标签只取该栏目文章，通用标签跨栏目
    $__tagSiteWhere = '';
    $__tagSiteParams = [];
    if ((int)($tag['site_id'] ?? 0) > 0) {
        $__tagSiteWhere = ' AND p.site_id = ?';
        $__tagSiteParams = [(int)$tag['site_id']];
    }
    $__tagWhere = 'EXISTS (SELECT 1 FROM post_tags pt WHERE pt.post_id = p.id AND pt.tag_id = ?)';
    $__tagParams = array_merge([$tag['id']], $__tagSiteParams);
    $total = count_published_posts($__tagWhere, $__tagParams);
    $posts = get_published_posts($__tagWhere, $__tagParams, $page, list_per_page());
}
$totalPages = max(1, (int)ceil($total / list_per_page()));

$page_title = $tag ? $tag['name'] : '标签';

get_header();
if (!render_view('tag', get_defined_vars())) {
    echo '<section class="content"><div class="card"><p class="empty">该标签不存在。</p></div></section>';
}
get_footer();
