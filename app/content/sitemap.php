<?php
/**
 * sitemap.xml（路由 /sitemap.html 或 ?do=sitemap）。
 *
 * 规则：
 *  - 每条 <loc> 必须是绝对 URL，且整体经 XML 转义（& → &amp;）；
 *  - 跟随后台伪静态开关 / 形态，出链一律走 gblog_url() / gblog_content_url()，
 *    不再手写 index.php?do=...；
 *  - 覆盖首页 / 栏目 / 功能页 / 分类 / 标签 / 文章 / 歌曲 / 已发布章节；
 *  - 超过 45000 条自动输出 sitemapindex 并按 ?part=N 分片（协议单文件上限 5 万条）。
 */
require_once __DIR__ . '/../../core/bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');

$LIMIT   = 45000;
$hasPart = isset($_GET['part']);
$part    = max(1, (int)($_GET['part'] ?? 1));

$entries = [];
$add = function (string $path, string $lastmod = '', string $freq = 'weekly',
                 string $priority = '0.6') use (&$entries): void {
    $entries[] = [
        'loc'        => gblog_absolute_url($path),
        'lastmod'    => $lastmod,
        'changefreq' => $freq,
        'priority'   => $priority,
    ];
};
$lastMod = static function ($value): string {
    $ts = gblog_parse_time((string)($value ?? ''));
    return $ts > 0 ? date('Y-m-d', $ts) : '';
};
// 关闭伪静态时退回控制器可用的 ?do= 形态，启用时用漂亮的按 type 前缀地址
$contentUrl = static function (string $do, array $row): string {
    $id = (int)$row['id'];
    if (!gblog_permalink_enabled()) return gblog_url($do, ['id' => $id]);
    return gblog_content_url($row);
};

$add(gblog_url('home'), '', 'daily', '1.0');

// 栏目页（仅启用栏目）
foreach (db()->query('SELECT id FROM sites WHERE enabled = 1 ORDER BY sort_order ASC, id ASC')->fetchAll() as $s) {
    $add(gblog_url('site', ['id' => (int)$s['id']]), '', 'daily', '0.8');
}

// 固定功能页（站内搜索结果不收录）
foreach (['about', 'links', 'archive', 'songs'] as $page) {
    $add(gblog_url($page), '', 'weekly', '0.5');
}

// 只收录确实挂着已发布内容的分类 / 标签，避免空页进索引
$activePosts = gblog_active_site_clause('p.site_id');
foreach (db()->query(
    "SELECT DISTINCT c.id FROM categories c
     JOIN posts p ON p.category_id = c.id AND p.status = 'published' AND $activePosts
     ORDER BY c.id ASC"
)->fetchAll() as $c) {
    $add(gblog_url('category', ['id' => (int)$c['id']]), '', 'weekly', '0.5');
}
foreach (db()->query(
    "SELECT DISTINCT t.id FROM tags t
     JOIN post_tags pt ON pt.tag_id = t.id
     JOIN posts p ON p.id = pt.post_id AND p.status = 'published' AND $activePosts
     ORDER BY t.id ASC"
)->fetchAll() as $t) {
    $add(gblog_url('tag', ['id' => (int)$t['id']]), '', 'weekly', '0.4');
}

// 文章（含软件 / 图片 / 小说作品本身）
$posts = db()->query(
    "SELECT p.id, p.created_at, p.updated_at, s.type AS site_type
     FROM posts p LEFT JOIN sites s ON s.id = p.site_id
     WHERE p.status = 'published' AND $activePosts
     ORDER BY p.id ASC"
)->fetchAll();
foreach ($posts as $p) {
    $row = [
        'id'         => (int)$p['id'],
        'site_type'  => (string)($p['site_type'] ?? '') ?: 'article',
        'created_at' => (string)($p['created_at'] ?? ''),
    ];
    $add($contentUrl('post', $row), $lastMod($p['updated_at'] ?? ''), 'weekly', '0.8');
}

// 歌曲（与文章共用全局 id，但走 songs 表 / music 前缀）
$songs = db()->query(
    "SELECT sg.id, sg.created_at FROM songs sg
     WHERE sg.status = 'published' AND " . gblog_active_site_clause('sg.site_id') . "
     ORDER BY sg.id ASC"
)->fetchAll();
foreach ($songs as $s) {
    $row = [
        'id'         => (int)$s['id'],
        'site_type'  => 'song',
        'created_at' => (string)($s['created_at'] ?? ''),
    ];
    $add($contentUrl('song', $row), $lastMod($s['created_at']), 'weekly', '0.7');
}

// 章节：只收录「已发布作品 + 已发布章节」，章序按本书内 sort_order,id 的位置（从 1 起），
// 与控制器 / 出链层完全同口径；直接把 novel_id + no 交给 gblog_url()，避免 N+1 反查整本。
$chapters = db()->query(
    "SELECT c.id, c.novel_id, c.created_at, c.updated_at
     FROM novel_chapters c
     JOIN posts p ON p.id = c.novel_id AND p.status = 'published' AND $activePosts
     WHERE c.status = 'published'
     ORDER BY c.novel_id ASC, c.sort_order ASC, c.id ASC
     LIMIT 200000"
)->fetchAll();
$seq = [];
foreach ($chapters as $c) {
    $novelId = (int)$c['novel_id'];
    $seq[$novelId] = ($seq[$novelId] ?? 0) + 1;
    $add(gblog_url('chapter', [
        'id'       => (int)$c['id'],
        'novel_id' => $novelId,
        'no'       => $seq[$novelId],
    ]), $lastMod($c['updated_at'] ?? $c['created_at']), 'monthly', '0.6');
}

$total = count($entries);
$pages = max(1, (int)ceil($total / $LIMIT));

// 越界分片必须 404；状态码要赶在任何输出之前设置，否则 PHP 已发完头改不动
if ($hasPart && $part > $pages) {
    http_response_code(404);
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    exit;
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

// 超过单文件上限：主入口改输出 sitemapindex，由 ?part=N 提供分片
if (!$hasPart && $pages > 1) {
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    $base = gblog_url('sitemap');
    for ($i = 1; $i <= $pages; $i++) {
        $slice = $base . (strpos($base, '?') === false ? '?' : '&') . 'part=' . $i;
        echo '  <sitemap>' . "\n";
        echo '    <loc>' . gblog_xml_escape(gblog_absolute_url($slice)) . '</loc>' . "\n";
        echo '    <lastmod>' . gmdate('Y-m-d') . '</lastmod>' . "\n";
        echo '  </sitemap>' . "\n";
    }
    echo '</sitemapindex>';
    exit;
}

$slice = $hasPart ? array_slice($entries, ($part - 1) * $LIMIT, $LIMIT) : $entries;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($slice as $e) {
    echo "  <url>\n";
    echo '    <loc>' . gblog_xml_escape($e['loc']) . '</loc>' . "\n";
    if ($e['lastmod'] !== '') echo '    <lastmod>' . $e['lastmod'] . '</lastmod>' . "\n";
    echo '    <changefreq>' . $e['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $e['priority'] . '</priority>' . "\n";
    echo "  </url>\n";
}
echo '</urlset>';
