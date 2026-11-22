<?php
/* 文章与内容 —— 文章查询、归档、热度、正文渲染。
 * 由 core/bootstrap.php 统一加载；本文件只放函数定义，不要写顶层执行代码。 */

function get_categories_with_count(): array
{
    $site = current_site();
    $sql = "SELECT c.id, c.name, COUNT(p.id) AS post_count
            FROM categories c
            LEFT JOIN posts p ON p.category_id = c.id AND p.status = 'published'
                              AND " . gblog_active_site_clause('p.site_id') . "
            WHERE c.site_id = ?
            GROUP BY c.id
            ORDER BY c.id ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute([$site['id']]);
    return $stmt->fetchAll();
}

function get_tags_with_count(): array
{
    $site = current_site();
    $sql = "SELECT t.id, t.name, COUNT(pt.post_id) AS post_count
            FROM tags t
            LEFT JOIN post_tags pt ON pt.tag_id = t.id
            LEFT JOIN posts p ON p.id = pt.post_id AND p.status = 'published' AND p.site_id = ?
                              AND " . gblog_active_site_clause('p.site_id') . "
            WHERE t.site_id = 0 OR t.site_id = ?
            GROUP BY t.id
            ORDER BY t.id ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute([$site['id'], $site['id']]);
    return $stmt->fetchAll();
}

function get_published_posts(string $where = '', array $params = [], int $page = 1, int $perPage = 0): array
{
    $perPage = $perPage > 0 ? $perPage : posts_per_page();
    $offset = max(0, ($page - 1) * $perPage);

    $sql = "SELECT p.id, p.title, p.excerpt, p.content, p.cover, p.created_at, p.updated_at, p.author, p.view_count, p.favorite_count, p.like_count,
                   c.name AS category_name,
                   (SELECT COUNT(*) FROM comments cm WHERE cm.target_type = 'post' AND cm.target_id = p.id AND cm.status = 'approved') AS comment_count
            FROM posts p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.status = 'published'
              AND " . gblog_active_site_clause('p.site_id') .
            ($where !== '' ? ' AND ' . $where : '') .
            " ORDER BY p.created_at DESC
              LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function count_published_posts(string $where = '', array $params = []): int
{
    $sql = "SELECT COUNT(*) FROM posts p
            WHERE p.status = 'published'
              AND " . gblog_active_site_clause('p.site_id') .
            ($where !== '' ? ' AND ' . $where : '');
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

/**
 * 按全局 id 取文章。$withDetail=false 时仅做轻量存在性检查（5 字段）；
 * 详情页传 true，一次带回分类名 / 标签 / 关联歌曲。
 * （posts.slug 已移除，文章一律按 id 定位；旧的 id→slug 两跳取数已合并到这里。）
 */
function get_post_by_id(int $id, bool $withDetail = false): ?array
{
    if ($id <= 0) return null;
    $stmt = db()->prepare("SELECT id, site_id, title, status FROM posts WHERE id = ? AND status = 'published' AND " . gblog_active_site_clause('site_id') . ' LIMIT 1');
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    if (!$post) {
        return null;
    }
    if (!$withDetail) {
        return $post;
    }

    $pdo = db();
    $st  = $pdo->prepare(
        "SELECT p.*, c.name AS category_name
         FROM posts p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.id = ? AND p.status = 'published'
           AND " . gblog_active_site_clause('p.site_id') . "
         LIMIT 1"
    );
    $st->execute([$id]);
    $post = $st->fetch();
    if (!$post) {
        return null;
    }

    $tagStmt = $pdo->prepare(
        "SELECT t.id, t.name FROM tags t
         JOIN post_tags pt ON pt.tag_id = t.id
         WHERE pt.post_id = ?
         ORDER BY t.id ASC"
    );
    $tagStmt->execute([$post['id']]);
    $post['tags'] = $tagStmt->fetchAll();

    $post['songs'] = get_songs_by_post_id($post['id']);

    return $post;
}

function get_adjacent_posts(string $createdAt, int $id, int $siteId = 0): array
{
    $siteId = $siteId > 0 ? $siteId : (int)(current_site()['id'] ?? 0);
    $pdo = db();
    $prev = $pdo->prepare(
        "SELECT id, title, cover, created_at FROM posts
         WHERE site_id = ? AND status='published'
           AND " . gblog_active_site_clause('site_id') . "
           AND (created_at > ? OR (created_at = ? AND id > ?))
         ORDER BY created_at ASC, id ASC LIMIT 1"
    );
    $prev->execute([$siteId, $createdAt, $createdAt, $id]);
    $next = $pdo->prepare(
        "SELECT id, title, cover, created_at FROM posts
         WHERE site_id = ? AND status='published'
           AND " . gblog_active_site_clause('site_id') . "
           AND (created_at < ? OR (created_at = ? AND id < ?))
         ORDER BY created_at DESC, id DESC LIMIT 1"
    );
    $next->execute([$siteId, $createdAt, $createdAt, $id]);
    $prevRow = $prev->fetch() ?: null;
    $nextRow = $next->fetch() ?: null;

    $wrap = "SELECT id, title, cover, created_at FROM posts
             WHERE site_id = ? AND status='published' AND id <> ?
               AND " . gblog_active_site_clause('site_id') . "
             ORDER BY created_at %s, id %s LIMIT 1";
    if ($prevRow === null) {
        $w = $pdo->prepare(sprintf($wrap, 'ASC', 'ASC'));
        $w->execute([$siteId, $id]);
        $r = $w->fetch() ?: null;
        if ($r && (int)($nextRow['id'] ?? 0) !== (int)$r['id']) $prevRow = $r;
    }
    if ($nextRow === null) {
        $w = $pdo->prepare(sprintf($wrap, 'DESC', 'DESC'));
        $w->execute([$siteId, $id]);
        $r = $w->fetch() ?: null;
        if ($r && (int)($prevRow['id'] ?? 0) !== (int)$r['id']) $nextRow = $r;
    }
    return ['prev' => $prevRow, 'next' => $nextRow];
}

function get_recent_posts(int $limit = 5, int $siteId = 0): array
{
    if ($siteId <= 0) {
        $site = current_site();
        $siteId = (int)$site['id'];
    }
    $stmt = db()->prepare(
        "SELECT id, title, created_at FROM posts
         WHERE site_id = ? AND status='published'
           AND " . gblog_active_site_clause('site_id') . "
         ORDER BY created_at DESC LIMIT " . (int)$limit
    );
    $stmt->execute([$siteId]);
    return $stmt->fetchAll();
}

function get_archives(): array
{
    $site  = current_site();
    $local = gblog_sql_local_time('created_at');
    if (gblog_db_driver() === 'sqlite') {
        $ym = "strftime('%Y-%m', {$local})";
        $y  = "MIN(CAST(strftime('%Y', {$local}) AS INTEGER))";
        $m  = "MIN(CAST(strftime('%m', {$local}) AS INTEGER))";
    } else {
        $ym = "DATE_FORMAT(created_at, '%Y-%m')";
        $y  = 'MIN(YEAR(created_at))';
        $m  = 'MIN(MONTH(created_at))';
    }
    $stmt = db()->prepare(
        "SELECT {$ym} AS ym, {$y} AS y, {$m} AS m, COUNT(*) AS cnt
         FROM posts
         WHERE site_id = ? AND status = 'published'
           AND " . gblog_active_site_clause('site_id') . "
         GROUP BY ym
         ORDER BY ym DESC"
    );
    $stmt->execute([$site['id']]);
    return $stmt->fetchAll();
}

function get_posts_by_month(int $year, int $month): array
{
    $site  = current_site();
    $ym    = sprintf('%04d-%02d', $year, $month);
    $local = gblog_sql_local_time('p.created_at');
    $expr  = gblog_db_driver() === 'sqlite'
        ? "strftime('%Y-%m', {$local})"
        : "DATE_FORMAT(p.created_at, '%Y-%m')";
    $stmt = db()->prepare(
        "SELECT p.id, p.title, p.excerpt, p.created_at, c.name AS category_name
         FROM posts p LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.site_id = ? AND p.status = 'published'
           AND " . gblog_active_site_clause('p.site_id') . "
           AND {$expr} = ?
         ORDER BY p.created_at DESC"
    );
    $stmt->execute([$site['id'], $ym]);
    return $stmt->fetchAll();
}

function get_hot_posts(string $scope = 'current', array $siteIds = [], int $limit = 5): array
{
    $sql = "SELECT p.id, p.title, p.view_count FROM posts p WHERE p.status = 'published' AND " . gblog_active_site_clause('p.site_id');
    $params = [];
    if ($scope === 'specific' && $siteIds) {
        $sql .= ' AND p.site_id IN (' . implode(',', array_map('intval', $siteIds)) . ')';
    } elseif ($scope === 'global') {
    } else {
        $site = current_site();
        $sql .= ' AND p.site_id = ?';
        $params[] = $site['id'];
    }
    $sql .= " ORDER BY p.view_count DESC, p.created_at DESC LIMIT " . (int)$limit;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function increment_view(int $postId): void
{
    if (empty($_SESSION['viewed']) || !in_array($postId, $_SESSION['viewed'], true)) {
        db()->prepare("UPDATE posts SET view_count = view_count + 1 WHERE id = ?")->execute([$postId]);
        $_SESSION['viewed'][] = $postId;
    }
}

// 富文本经白名单净化后输出（DB 存原文）
function render_content(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') return '';
    if (preg_match('/<(p|div|h[1-6]|ul|ol|li|blockquote|pre|img|video|iframe|figure|table|section|article)\b/i', $raw)) {
        return gblog_sanitize_html($raw);
    }
    return gblog_sanitize_html(render_markdown($raw));
}

function render_markdown(string $text): string
{
    $blocks = [];

    $text = preg_replace_callback('/^\s*@video\s+(\S+)\s*$/m', function ($m) use (&$blocks) {
        $url = $m[1];
        if (!preg_match('#^(https?://|//)#i', $url)) {
            return htmlspecialchars($m[0], ENT_QUOTES, 'UTF-8');
        }
        $token = '##BLK' . count($blocks) . '##';
        $blocks[$token] = '<div class="video-embed"><iframe src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            . '" loading="lazy" allowfullscreen frameborder="0" scrolling="no"'
            . ' allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"></iframe></div>';
        return $token;
    }, $text);

    $text = preg_replace_callback('/```\w*\s*\n(.*?)```/s', function ($m) use (&$blocks) {
        $token = '##BLK' . count($blocks) . '##';
        $blocks[$token] = '<pre><code>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</code></pre>';
        return $token;
    }, $text);

    $esc = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');

    // 图片：![](https://...) 或 ![] (/usr/uploads/...)；拒绝非 http(s)/根相对协议
    $esc = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', function ($m) {
        $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        if (!preg_match('#^(https?://|//|/)#i', $url)) return $m[0];
        return '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            . '" alt="' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '" loading="lazy">';
    }, $esc);

    $esc = preg_replace('/`([^`]+)`/', '<code>$1</code>', $esc);
    $esc = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', function ($m) {
        return '<a href="' . $m[2] . '" target="_blank" rel="noopener">' . $m[1] . '</a>';
    }, $esc);
    $esc = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $esc);
    $esc = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $esc);

    for ($i = 6; $i >= 1; $i--) {
        $esc = preg_replace('/^' . str_repeat('#', $i) . '\s+(.*)$/m', "<h$i>\$1</h$i>", $esc);
    }

    $esc = preg_replace('/^[-*]\s+(.+)$/m', '<li>$1</li>', $esc);
    $esc = preg_replace('/^(\d+)\.\s+(.+)$/m', '<oli>$2</oli>', $esc);
    $esc = preg_replace('/^&gt;\s?(.*)$/m', '<blockquote>$1</blockquote>', $esc);

    $out = '';
    $listType = '';
    foreach (explode("\n", $esc) as $rawLine) {
        $line = trim($rawLine);
        if ($line === '') {
            if ($listType !== '') { $out .= '</' . $listType . ">\n"; $listType = ''; }
            continue;
        }
        $thisType = '';
        if (preg_match('/^<li>/', $line)) $thisType = 'ul';
        elseif (preg_match('/^<oli>/', $line)) {
            $thisType = 'ol';
            $line = str_replace(['<oli>', '</oli>'], ['<li>', '</li>'], $line);
        }
        if ($thisType !== '') {
            if ($listType !== '' && $listType !== $thisType) $out .= '</' . $listType . ">\n";
            if ($listType !== $thisType) { $out .= '<' . $thisType . ">\n"; $listType = $thisType; }
            $out .= $line . "\n";
            continue;
        }
        if ($listType !== '') { $out .= '</' . $listType . ">\n"; $listType = ''; }
        if (preg_match('~^(<h[1-6]|<pre|<blockquote|##BLK\d+##)~', $line)) {
            $out .= $line . "\n";
        } else {
            $out .= "<p>$line</p>\n";
        }
    }
    if ($listType !== '') $out .= '</' . $listType . ">\n";

    foreach ($blocks as $token => $html) {
        $out = str_replace($token, $html, $out);
    }
    return $out;
}
