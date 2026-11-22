<?php
require_once __DIR__ . '/../../core/bootstrap.php';

$GLOBALS['is_home'] = true;

$site_name = get_setting('site_name', SITE_NAME);
$site_desc = get_setting('site_desc', SITE_DESC);

if (function_exists('do_action')) do_action('portal_top_prepare');

get_header();

$sites = db()->query("SELECT * FROM sites WHERE enabled = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();

$page           = max(1, (int)($_GET['page'] ?? 1));
$per            = (int)list_per_page();
$off            = ($page - 1) * $per;

$activeSiteSlug = (string)($_GET['site'] ?? '');
$activeSiteId   = 0;
$activeSiteType = '';
foreach ($sites as $s) {
    if ((string)$s['slug'] === $activeSiteSlug) { $activeSiteId = (int)$s['id']; $activeSiteType = (string)$s['type']; break; }
}

$homeIsSongTab = ($activeSiteType === 'song' && function_exists('gblog_music_site_active') && gblog_music_site_active());

$homeWhere   = "p.status = 'published' AND " . gblog_active_site_clause('p.site_id');
$homeParams  = [];
if ($activeSiteId > 0) {
    $homeWhere   .= ' AND p.site_id = ?';
    $homeParams[] = $activeSiteId;
}

// 音乐站内容在 songs 表（posts 无行）：tab 切到音乐站时改查 songs 并映射成卡片行
if ($homeIsSongTab) {
    $hc = db()->query("SELECT COUNT(*) FROM songs WHERE status = 'published' AND site_id = " . (int)$activeSiteId);
    $homeTotal      = (int)$hc->fetchColumn();
    $homeTotalPages = max(1, (int)ceil($homeTotal / $per));

    $__songStmt = db()->prepare(
        "SELECT sg.id, sg.title, sg.artist, sg.note, sg.lyrics, sg.cover_url, sg.play_count, sg.created_at,
                c.name AS category_name
         FROM songs sg
         LEFT JOIN categories c ON c.id = sg.category_id
         WHERE sg.status = 'published' AND sg.site_id = " . (int)$activeSiteId . "
         ORDER BY sg.created_at DESC
         LIMIT " . (int)$per . " OFFSET " . (int)$off
    );
    $__songStmt->execute();
    $homePosts = [];
    foreach ($__songStmt->fetchAll() as $__sg) {
        $homePosts[] = [
            'id'             => (int)$__sg['id'],
            'title'          => $__sg['title'],
            'slug'           => '',
            'url'            => 'index.php?do=song&id=' . (int)$__sg['id'],
            'excerpt'        => (string)$__sg['note'],
            'content'        => (string)$__sg['lyrics'],
            'cover'          => (string)$__sg['cover_url'],
            'author'         => (string)$__sg['artist'],
            'created_at'     => $__sg['created_at'],
            'view_count'     => (int)$__sg['play_count'],
            'like_count'     => 0,
            'favorite_count' => 0,
            'comment_count'  => 0,
            'site_name'      => '音乐',
            'site_slug'      => $activeSiteSlug,
            'site_type'      => 'song',
            'category_name'  => $__sg['category_name'] ?: '未分类',
            'is_song'        => true,
        ];
    }
} else {
    $hc = db()->prepare("SELECT COUNT(*) FROM posts p WHERE " . $homeWhere);
    $hc->execute($homeParams);
    $homeTotal      = (int)$hc->fetchColumn();
    $homeTotalPages = max(1, (int)ceil($homeTotal / $per));

    $homeStmt = db()->prepare(
        "SELECT p.id, p.title, p.excerpt, p.content, p.cover, p.author, p.created_at, p.view_count,
                p.like_count, p.favorite_count,
                s.name AS site_name, s.slug AS site_slug, s.type AS site_type,
                c.name AS category_name,
                (SELECT COUNT(*) FROM comments cm WHERE cm.target_type = 'post' AND cm.target_id = p.id AND cm.status = 'approved') AS comment_count
         FROM posts p
         LEFT JOIN sites s ON s.id = p.site_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE " . $homeWhere . "
         ORDER BY p.created_at DESC
         LIMIT " . (int)$per . " OFFSET " . (int)$off
    );
    $homeStmt->execute($homeParams);
    $homePosts = $homeStmt->fetchAll();
}

$homePageBase = $activeSiteId > 0
    ? 'index.php?site=' . rawurlencode($activeSiteSlug)
    : 'index.php?';
$homeTabItems = [['name' => '全部内容', 'url' => 'index.php', 'active' => ($activeSiteId === 0)]];
foreach ($sites as $s) {
    $homeTabItems[] = [
        'name'   => (string)$s['name'],
        'url'    => 'index.php?site=' . rawurlencode((string)$s['slug']),
        'active' => ((int)$s['id'] === $activeSiteId),
    ];
}

$aggMoreUrl = 'index.php?do=home';
$GLOBALS['gblog_loop'] = $homePosts;
if (!render_view('index', get_defined_vars(), '_home')) {
    echo '<section class="content"><div class="card"><p>缺少聚合首页模板。</p></div></section>';
}
get_footer();
