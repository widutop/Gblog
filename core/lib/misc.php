<?php
/* 其它 —— 小说聚合数据、安全回跳。
 * 由 core/bootstrap.php 统一加载；本文件只放函数定义，不要写顶层执行代码。 */

// $where 由调用方给出（必须带占位符），停用栏目隔离由本函数统一挂上
function gblog_novel_works(string $where, array $params, int $limit = 8): array
{
    $limit = max(1, min(30, $limit));
    $sql = "SELECT p.id, p.title, p.cover, p.excerpt, p.author,
                   c.name AS category_name,
                   (SELECT COUNT(*) FROM novel_chapters nc WHERE nc.novel_id = p.id AND nc.status = 'published') AS chapter_count,
                   (SELECT nc2.title FROM novel_chapters nc2 WHERE nc2.novel_id = p.id AND nc2.status = 'published' ORDER BY nc2.sort_order DESC, nc2.id DESC LIMIT 1) AS latest_title,
                   (SELECT nc3.id FROM novel_chapters nc3 WHERE nc3.novel_id = p.id AND nc3.status = 'published' ORDER BY nc3.sort_order DESC, nc3.id DESC LIMIT 1) AS latest_id
            FROM posts p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.status = 'published' AND " . gblog_active_site_clause('p.site_id') .
            ($where !== '' ? ' AND ' . $where : '') .
            " ORDER BY p.created_at DESC LIMIT " . $limit;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function gblog_novel_book_data(int $postId, array $post, array $chapters): array
{
    $author = trim((string)($post['author'] ?? ''));
    $siteId = (int)($post['site_id'] ?? 0);
    $catId  = (int)($post['category_id'] ?? 0);

    $latest = array_slice(array_reverse($chapters), 0, 8);
    $other  = $author !== ''
        ? gblog_novel_works('p.author = ? AND p.id <> ? AND p.site_id = ?', [$author, $postId, $siteId], 8)
        : [];
    $similar = $catId > 0
        ? gblog_novel_works('p.category_id = ? AND p.id <> ?', [$catId, $postId], 5)
        : [];
    if (count($similar) < 5) {
        $exclude = [$postId];
        foreach ($similar as $s) $exclude[] = (int)$s['id'];
        $ph = implode(',', array_fill(0, count($exclude), '?'));
        $params = $exclude;
        $params[] = $siteId;
        $fill = gblog_novel_works("p.id NOT IN ($ph) AND p.site_id = ?", $params, 5 - count($similar));
        $similar = array_merge($similar, $fill);
    }

    $works = 0; $words = 0; $firstTs = 0;
    if ($author !== '') {
        $q = db()->prepare(
            "SELECT p.id, p.created_at FROM posts p
             WHERE p.status = 'published' AND p.author = ? AND p.site_id = ?
               AND " . gblog_active_site_clause('p.site_id')
        );
        $q->execute([$author, $siteId]);
        $ids = [];
        foreach ($q->fetchAll() as $r) {
            $works++;
            $ids[] = (int)$r['id'];
            $ts = gblog_parse_time((string)$r['created_at']);
            if ($ts > 0 && ($firstTs === 0 || $ts < $firstTs)) $firstTs = $ts;
        }
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            // 字数统计放到 SQL 侧：长篇（数千章、每章数千字）不能把正文全拉进 PHP。
            // MySQL 用 CHAR_LENGTH / CHAR(n)，SQLite 用 LENGTH / char(n)；都按字符计并去掉空白。
            $sqlite = defined('DB_TYPE') && DB_TYPE === 'sqlite';
            $len    = $sqlite ? 'LENGTH' : 'CHAR_LENGTH';
            $lf     = $sqlite ? 'char(10)' : 'CHAR(10)';
            $tab    = $sqlite ? 'char(9)'  : 'CHAR(9)';
            $cr     = $sqlite ? 'char(13)' : 'CHAR(13)';
            $expr   = $len . "(REPLACE(REPLACE(REPLACE(REPLACE(content, ' ', ''), $lf, ''), $tab, ''), $cr, ''))";
            $w = db()->prepare("SELECT SUM($expr) FROM novel_chapters WHERE novel_id IN ($in) AND status = 'published'");
            $w->execute($ids);
            $words = (int)$w->fetchColumn();
        }
    }
    if ($firstTs === 0) $firstTs = gblog_parse_time((string)($post['created_at'] ?? ''));
    $days = $firstTs > 0 ? max(1, (int)floor((time() - $firstTs) / 86400)) : 0;

    return [
        'latest'  => $latest,
        'other'   => $other,
        'similar' => $similar,
        'stats'   => ['works' => $works, 'words' => $words, 'days' => $days, 'author' => $author],
    ];
}

function gblog_safe_back_url(string $fallback = 'index.php'): string
{
    // 相对 index.php 地址在路径式/扁平 URL 页面会被浏览器按目录解析，统一根绝对化
    $abs = static function (string $u): string {
        return function_exists('gblog_root_absolute') ? gblog_root_absolute($u) : $u;
    };
    $cand = trim((string)($_POST['back'] ?? ''));
    if ($cand !== '' && (str_starts_with($cand, 'index.php') || $cand[0] === '/')
        && !str_starts_with($cand, '//') && !preg_match('#[a-z][a-z0-9+.-]*:#i', $cand)) {
        return $abs($cand);
    }
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref !== '' && !empty($_SERVER['HTTP_HOST'])) {
        $p = parse_url($ref);
        if (!empty($p['host']) && strcasecmp($p['host'], $_SERVER['HTTP_HOST']) === 0) {
            $path = (string)($p['path'] ?? '');
            if ($path !== '' && !preg_match('#do=(login|register|forgot|logout)#i', (string)($p['query'] ?? ''))) {
                return $abs($path . (isset($p['query']) ? '?' . $p['query'] : ''));
            }
        }
    }
    return $abs($fallback);
}
