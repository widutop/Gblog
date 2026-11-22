<?php
require_once __DIR__ . '/../../core/bootstrap.php';

$slug = (string)($_GET['slug'] ?? '');
$id   = (int)($_GET['id'] ?? 0);
$type = (string)($_GET['type'] ?? '');
$site = null;
if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM sites WHERE id = ? AND enabled = 1 LIMIT 1');
    $stmt->execute([$id]);
    $site = $stmt->fetch();
} elseif ($slug !== '') {
    $stmt = db()->prepare('SELECT * FROM sites WHERE slug = ? AND enabled = 1 LIMIT 1');
    $stmt->execute([$slug]);
    $site = $stmt->fetch();
} elseif ($type !== '') {
    // 顶级栏目页 /post.html 等按 type 定位（不硬编码 slug），取该类型第一个启用栏目。
    $stmt = db()->prepare('SELECT * FROM sites WHERE type = ? AND enabled = 1 ORDER BY id ASC LIMIT 1');
    $stmt->execute([$type]);
    $site = $stmt->fetch();
}

// 按 id / type（.html 形态）命中时回填 slug，保证分页基址与模板里的 $slug 一致，
// 也让 navbar 的当前栏目高亮判断（读 $_GET['slug']）继续生效。
if ($site && $slug === '') {
    $slug = (string)($site['slug'] ?? '');
    $_GET['slug'] = $slug;
}

if (($slug !== '' || $id > 0 || $type !== '') && !$site) {
    gblog_not_found('栏目不可用', '栏目不存在或已停用。');
}

$page = max(1, (int)($_GET['page'] ?? 1));
$page_title = $site ? $site['name'] : '栏目';

$GLOBALS['current_site_data'] = $site;
if ($site) { $GLOBALS['site'] = $site; }

$softHead = [];
if ($site && (string)$site['type'] === 'software') {
    $sh = db()->prepare(
        "SELECT p.id, p.title, p.excerpt, p.cover, p.author, p.file_size,
                p.software_type, p.software_platform, p.software_version, p.language,
                p.download_count, p.download_level, p.download_price, p.created_at,
                c.name AS category_name
         FROM posts p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.status = 'published' AND p.site_id = ?
           AND " . gblog_active_site_clause('p.site_id') . "
         ORDER BY p.created_at DESC, p.id DESC
         LIMIT 11"
    );
    $sh->execute([(int)$site['id']]);
    $softHead = $sh->fetchAll();
}

$gbCats = [];
$gbActiveCat = 0;   // 激活分类的 id（categories.slug 已移除，分类一律按 id）
$gbActiveCatId = 0;
if ($site) {
    $cs = db()->prepare('SELECT id, name FROM categories WHERE site_id = ? ORDER BY id ASC');
    $cs->execute([(int)$site['id']]);
    $gbCats = $cs->fetchAll();
    $gbCatParam = (int)($_GET['cat'] ?? 0);
    if ($gbCatParam > 0) {
        foreach ($gbCats as $gc) {
            if ((int)$gc['id'] === $gbCatParam) {
                $gbActiveCat   = $gbCatParam;
                $gbActiveCatId = $gbCatParam;
                break;
            }
        }
    }
}

$gbTabItems = [];
if ($site && $gbCats) {
    $gbSiteUrl    = gblog_pretty_base('index.php?do=site&slug=' . rawurlencode((string)$site['slug']));
    $gbTabItems[] = ['name' => '全部内容', 'url' => $gbSiteUrl, 'active' => ($gbActiveCat === 0)];
    foreach ($gbCats as $gc) {
        $gbTabItems[] = [
            'name'   => (string)$gc['name'],
            // 分类参数按 id 传给 gblog_pretty_base：漂亮基址 post.html 没有 ?，直接拼 &cat= 会 404
            'url'    => gblog_pretty_base('index.php?do=site&slug=' . rawurlencode((string)$site['slug']) . '&cat=' . (int)$gc['id']),
            'active' => ($gbActiveCat === (int)$gc['id']),
        ];
    }
}
$gbPageBase = gblog_pretty_base('index.php?do=site&slug=' . rawurlencode((string)$slug) . ($gbActiveCat > 0 ? '&cat=' . $gbActiveCat : ''));

$posts = [];
$total = 0;
$musicCats = [];
$musicTotal = 0;
$showTopicPlayer = (get_setting('music_player_topic', '1') === '1');

$gbWhere  = $site ? 'p.site_id = ?' : '';
$gbParams = $site ? [(int)$site['id']] : [];
if ($gbActiveCatId > 0) {
    $gbWhere   .= ' AND p.category_id = ?';
    $gbParams[] = $gbActiveCatId;
}

if ($site) {
    if ($site['type'] === 'song') {
        $cs = db()->prepare("SELECT c.id, c.name FROM categories c JOIN sites s ON s.id = c.site_id WHERE s.slug = 'music' ORDER BY c.id");
        $cs->execute();
        $musicCats = $cs->fetchAll();
        $musicTotal = (int)db()->query("SELECT COUNT(*) FROM songs WHERE status = 'published' AND site_id = " . (int)$site['id'])->fetchColumn();
        $musicTopSongs = db()->query("SELECT id, title, artist, cover_url, audio_url FROM songs WHERE status = 'published' AND site_id = " . (int)$site['id'] . " ORDER BY sort_order DESC, id DESC LIMIT 10")->fetchAll();

        $musicChartCols = [];
        if (gblog_music_site_active()) {
            $mcBase = "SELECT id, title, artist, cover_url, audio_url FROM songs WHERE status = 'published' AND site_id = " . (int)$site['id'];
            $mcNew  = db()->query($mcBase . " ORDER BY id DESC LIMIT 10")->fetchAll();
            $mcHot  = db()->query($mcBase . " ORDER BY play_count DESC, id DESC LIMIT 10")->fetchAll();
            $mcRandOrder = db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'RANDOM()' : 'RAND()';
            $mcRand = db()->query($mcBase . " ORDER BY " . $mcRandOrder)->fetchAll();
            $musicChartCols = [
                ['title' => '最新发布', 'tone' => 'red',   'songs' => $mcNew],
                ['title' => '热门排行', 'tone' => 'amber', 'songs' => $mcHot],
                ['title' => '随便听听', 'tone' => 'green', 'songs' => array_slice($mcRand, 0, 10)],
            ];
        }

        $mgCatId = (int)($_GET['cat'] ?? 0);
        $musicListCat = null;
        foreach ($musicCats as $c) {
            if ((int)$c['id'] === $mgCatId) { $musicListCat = $c; break; }
        }
        $musicListCatMissing = ($mgCatId > 0 && !$musicListCat);

        $mgWhere  = "status = 'published' AND site_id = " . (int)$site['id'];
        $mgParams = [];
        if ($musicListCat) {
            $mgWhere   .= " AND category_id = ?";
            $mgParams[] = (int)$musicListCat['id'];
        }
        $mgCount = db()->prepare("SELECT COUNT(*) FROM songs WHERE " . $mgWhere);
        $mgCount->execute($mgParams);
        $musicListTotal = (int)$mgCount->fetchColumn();

        $musicListPer   = 50;
        $musicListPages = max(1, (int)ceil($musicListTotal / $musicListPer));
        $musicListPage  = min(max(1, (int)($_GET['page'] ?? 1)), $musicListPages);
        $mgOffset = ($musicListPage - 1) * $musicListPer;
        $mgList = db()->prepare("SELECT id, title, artist, cover_url, audio_url FROM songs WHERE " . $mgWhere . " ORDER BY id DESC LIMIT " . $mgOffset . "," . $musicListPer);
        $mgList->execute($mgParams);
        $musicListSongs = $mgList->fetchAll();

        $musicListBase = gblog_pretty_base('index.php?do=site&slug=' . rawurlencode((string)$slug));
        $musicListTabs = [['name' => '全部歌曲', 'url' => $musicListBase, 'active' => !$musicListCat]];
        foreach ($musicCats as $c) {
            $musicListTabs[] = [
                'name'   => (string)$c['name'],
                'url'    => gblog_pretty_base('index.php?do=site&slug=' . rawurlencode((string)$slug) . '&cat=' . (int)$c['id']),
                'active' => $musicListCat && (int)$musicListCat['id'] === (int)$c['id'],
            ];
        }
    } elseif ($site['type'] === 'novel') {
        $offset = ($page - 1) * list_per_page();
        $stmt = db()->prepare("SELECT p.id, p.title, p.cover, p.excerpt, p.author, c.name AS category_name, (SELECT COUNT(*) FROM novel_chapters nc WHERE nc.novel_id = p.id AND nc.status='published') AS chapter_count, (SELECT title FROM novel_chapters nc WHERE nc.novel_id = p.id AND nc.status='published' ORDER BY nc.sort_order ASC, nc.id DESC LIMIT 1) AS latest_title, (SELECT id FROM novel_chapters nc WHERE nc.novel_id = p.id AND nc.status='published' ORDER BY nc.sort_order ASC, nc.id DESC LIMIT 1) AS latest_id FROM posts p LEFT JOIN categories c ON c.id = p.category_id WHERE p.status='published' AND " . $gbWhere . " ORDER BY p.created_at DESC LIMIT " . $offset . "," . list_per_page());
        $stmt->execute($gbParams);
        $posts = $stmt->fetchAll();
        $total = count_published_posts($gbWhere, $gbParams);
    } else {
        $total = count_published_posts($gbWhere, $gbParams);
        $posts = get_published_posts($gbWhere, $gbParams, $page, list_per_page());
    }
}

$totalPages = max(1, (int)ceil($total / list_per_page()));

// 音乐列表局部刷新：tab 切换 / 翻页只取 grid 片段，前端替换 DOM，不重载整页。
if ($site && (string)$site['type'] === 'song' && (string)($_GET['frag'] ?? '') === 'grid') {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    render_slot('music_grid', [
        'musicListSongs'      => $musicListSongs ?? [],
        'musicListTabs'       => $musicListTabs ?? [],
        'musicListPage'       => $musicListPage ?? 1,
        'musicListPages'      => $musicListPages ?? 1,
        'musicListTotal'      => $musicListTotal ?? 0,
        'musicListBase'       => $musicListBase ?? gblog_pretty_base('index.php?do=site&slug=music'),
        'musicListCatMissing' => $musicListCatMissing ?? false,
        'showTopicPlayer'     => $showTopicPlayer,
        'musicGridFrag'       => true,
    ], (string)$site['slug']);
    exit;
}

if ($site && (string)($site['home_mode'] ?? 'default') === 'aggregate') {
    $aggIcons  = ['article' => 'file', 'software' => 'package', 'image' => 'image', 'song' => 'music', 'video' => 'video', 'novel' => 'book', 'default' => 'file'];
    $siteIcon  = $aggIcons[$site['type']] ?? $aggIcons['default'];
    $siteId    = (int)$site['id'];

    $aggSections = [];
    $cats = db()->prepare('SELECT id, name FROM categories WHERE site_id = ? ORDER BY id ASC LIMIT 8');
    $cats->execute([$siteId]);
    foreach ($cats->fetchAll() as $cat) {
        $q = db()->prepare(
            "SELECT p.id, p.title, p.excerpt, p.cover, p.created_at, p.view_count, p.author, p.like_count, p.favorite_count, c.name AS category_name,
                    (SELECT COUNT(*) FROM comments cm WHERE cm.target_type = 'post' AND cm.target_id = p.id AND cm.status = 'approved') AS comment_count
             FROM posts p LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.status = 'published' AND p.site_id = ? AND p.category_id = ?
             ORDER BY p.created_at DESC LIMIT 10"
        );
        $q->execute([$siteId, (int)$cat['id']]);
        $rows = $q->fetchAll();
        if ($rows) {
            $aggSections[] = [
                'name'     => $cat['name'],
                'slug'     => $site['slug'],
                'icon'     => $siteIcon,
                'more_url' => gblog_pretty_base('index.php?do=category&id=' . (int)$cat['id']),
                'posts'    => $rows,
            ];
        }
    }
    if (!$aggSections) {
        $q = db()->prepare(
            "SELECT p.id, p.title, p.excerpt, p.cover, p.created_at, p.view_count, p.author, p.like_count, p.favorite_count, c.name AS category_name,
                    (SELECT COUNT(*) FROM comments cm WHERE cm.target_type = 'post' AND cm.target_id = p.id AND cm.status = 'approved') AS comment_count
             FROM posts p LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.status = 'published' AND p.site_id = ? ORDER BY p.created_at DESC LIMIT 10"
        );
        $q->execute([$siteId]);
        $aggSections[] = [
            'name'     => $site['name'],
            'slug'     => $site['slug'],
            'icon'     => $siteIcon,
            'more_url' => 'index.php?do=home',
            'posts'    => $q->fetchAll(),
        ];
    }

    $_gb_site = $site;
    get_header();
    $site = $_gb_site;
    $aggMoreUrl = 'index.php?do=home';
    render_slot('list_head', get_defined_vars(), (string)$site['slug']);
    if (!render_view('index', get_defined_vars(), (string)$site['slug'])) {
        echo '<section class="content"><div class="card"><p>缺少聚合版式模板。</p></div></section>';
    }
    get_footer();
    exit;
}

$_gb_site = $site;
get_header();
$site = $_gb_site;

if (!$site) {
    echo '<section class="content"><div class="card"><p>栏目不存在或已关闭。</p></div></section>';
} else {
    $GLOBALS['gblog_loop'] = $posts;
    $GLOBALS['gblog_pagination'] = [
        'page'  => $page,
        'total' => $totalPages,
        'base'  => gblog_pretty_base('index.php?do=site&slug=' . rawurlencode((string)$site['slug'])),
    ];
    if (!render_view('home', get_defined_vars())) {
        echo '<section class="content"><div class="card"><p>缺少默认首页视图。</p></div></section>';
    }
}

get_footer();
