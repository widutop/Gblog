<?php
require_once __DIR__ . '/../../core/bootstrap.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$per  = (int)list_per_page();
$off  = ($page - 1) * $per;

$where       = "p.status = 'published' AND " . gblog_active_site_clause('p.site_id');
$total       = (int)db()->query("SELECT COUNT(*) FROM posts p WHERE $where")->fetchColumn();
$totalPages  = max(1, (int)ceil($total / $per));

$stmt = db()->prepare(
    "SELECT p.id, p.title, p.excerpt, p.content, p.cover, p.author, p.created_at, p.view_count,
            p.like_count, p.favorite_count,
            s.name AS site_name, s.slug AS site_slug, s.type AS site_type,
            c.name AS category_name,
            (SELECT COUNT(*) FROM comments cm WHERE cm.target_type = 'post' AND cm.target_id = p.id AND cm.status = 'approved') AS comment_count
     FROM posts p
     LEFT JOIN sites s ON s.id = p.site_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE $where
     ORDER BY p.created_at DESC
     LIMIT $per OFFSET $off"
);
$stmt->execute();
$posts = $stmt->fetchAll();

$sites = db()->query("SELECT * FROM sites WHERE enabled = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
$gbTabItems = [['name' => '全部内容', 'url' => 'index.php?do=home', 'active' => true]];
foreach ($sites as $s) {
    $gbTabItems[] = [
        'name'   => (string)$s['name'],
        'url'    => 'index.php?do=site&slug=' . rawurlencode((string)$s['slug']),
        'active' => false,
    ];
}

$page_title = get_setting('site_name', SITE_NAME);
get_header();

if (!$posts):
?>
<div class="card"><p>还没有文章，去 <a href="admin/">后台</a> 写第一篇吧。</p></div>
<?php else: ?>
<?php render_slot('post_card_list', [
    'posts'      => $posts,
    'slug'       => '',
    'page'       => $page,
    'totalPages' => $totalPages,
    'gbTabItems' => $gbTabItems,
    'gbPageBase' => 'index.php?do=home',
    'gbMetaName' => 'site',
]); ?>
<?php endif; ?>

<?php get_footer(); ?>
