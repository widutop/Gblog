<?php
require_once __DIR__ . '/../../core/bootstrap.php';

set_page_context(gblog_infer_site('search'), 'page');

$q    = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = max(1, (int)list_per_page());

$where = '';
$params = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $where = '(p.title LIKE ? OR p.content LIKE ? OR p.excerpt LIKE ?)';
    $params = [$like, $like, $like];
}

$total      = $q !== '' ? count_published_posts($where, $params) : 0;
$totalPages = max(1, (int)ceil($total / $per));

$posts = [];
if ($q !== '') {
    $baseWhere = "p.status = 'published' AND " . gblog_active_site_clause('p.site_id') . ' AND ' . $where;
    $off = ($page - 1) * $per;
    $stmt = db()->prepare(
        "SELECT p.id, p.title, p.excerpt, p.content, p.cover, p.author, p.created_at, p.view_count,
                p.like_count, p.favorite_count,
                s.name AS site_name, s.slug AS site_slug, s.type AS site_type,
                c.name AS category_name,
                (SELECT COUNT(*) FROM comments cm WHERE cm.target_type = 'post' AND cm.target_id = p.id AND cm.status = 'approved') AS comment_count
         FROM posts p
         LEFT JOIN sites s ON s.id = p.site_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE $baseWhere
         ORDER BY p.created_at DESC
         LIMIT $per OFFSET $off"
    );
    $stmt->execute($params);
    $posts = $stmt->fetchAll();
}

$page_title = $q !== '' ? '搜索：' . $q : '搜索';

get_header();
if (!render_view('search', get_defined_vars())) {
    echo '<section class="content"><div class="card"><p class="empty">搜索暂不可用。</p></div></section>';
}
get_footer();
