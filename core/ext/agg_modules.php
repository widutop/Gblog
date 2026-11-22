<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/** home_modules 开关的唯一消费端；栏目停用判定必须生效。 */

function gblog_home_modules(): array
{
    $default = [
        ['key' => 'carousel', 'enabled' => 0, 'sort' => 1, 'mode' => 'manual', 'post_ids' => '', 'site_id' => 0, 'count' => 4, 'seconds' => 4, 'ids' => ''],
        ['key' => 'cards',    'enabled' => 0, 'sort' => 2, 'items' => [], 'ids' => ''],
    ];
    $defByKey = [];
    $order    = [];
    foreach ($default as $i => $d) { $defByKey[$d['key']] = $d; $order[$d['key']] = $i; }

    $raw  = function_exists('get_setting') ? (string)get_setting('home_modules', '') : '';
    $mods = $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($mods) || !$mods) $mods = $default;

    $out  = [];
    $have = [];
    foreach ($mods as $m) {
        if (!is_array($m)) continue;
        $k = (string)($m['key'] ?? '');
        if ($k === '' || !isset($defByKey[$k])) continue;
        $m = array_merge($defByKey[$k], $m);
        $m['key']     = $k;
        $m['enabled'] = (int)($m['enabled'] ?? 0) === 1;
        $m['sort']    = max(1, (int)($m['sort'] ?? 1));
        $have[$k]     = true;
        $out[]        = $m;
    }
    foreach ($default as $d) {
        if (isset($have[$d['key']])) continue;
        $d['enabled'] = (bool)$d['enabled'];
        $out[] = $d;
    }
    usort($out, function ($a, $b) use ($order) {
        if ($a['sort'] !== $b['sort']) return $a['sort'] <=> $b['sort'];
        return ($order[$a['key']] ?? 99) <=> ($order[$b['key']] ?? 99);
    });
    return $out;
}

function gblog_agg_item_rows(array $items): array
{
    $rows = [];
    foreach ($items as $it) {
        if (!is_array($it)) continue;
        if (!isset($it['on']) || (int)$it['on'] !== 1) continue;
        $source = (string)($it['source'] ?? 'custom');
        if ($source === 'post') {
            $pid = (int)($it['post_id'] ?? 0);
            if ($pid <= 0) continue;
            try {
                $st = db()->prepare(
                    'SELECT p.id,p.title,p.excerpt,p.content,p.cover, s.slug AS site_slug, s.type AS site_type
                     FROM posts p LEFT JOIN sites s ON s.id = p.site_id
                     WHERE p.id = ? AND p.status = \'published\' AND ' . gblog_active_site_clause('p.site_id')
                );
                $st->execute([$pid]);
                $p = $st->fetch();
            } catch (Throwable $e) { $p = false; }
            if (!$p) continue;
            $rows[] = [
                'id'        => (int)$p['id'],
                'title'     => (string)$p['title'],
                'subtitle'  => '',
                'excerpt'   => (string)($p['excerpt'] ?? ''),
                'content'   => (string)($p['content'] ?? ''),
                'cover'     => (string)($p['cover'] ?? ''),
                'site_slug' => (string)($p['site_slug'] ?? ''),
                'site_type' => (string)($p['site_type'] ?? ''),
                'url'       => 'index.php?do=post&id=' . (int)$p['id'],
                'icon'      => '',
                'icon_name' => false,
            ];
            continue;
        }
        $url = trim((string)($it['url'] ?? ''));
        if (!preg_match('~^(https?://|/|index\.php|\?|#)~i', $url)) continue;
        $sub = trim((string)($it['subtitle'] ?? ''));
        $rows[] = [
            'id'        => 0,
            'title'     => trim((string)($it['title'] ?? '')),
            'subtitle'  => $sub,
            'excerpt'   => $sub,
            'content'   => '',
            'cover'     => trim((string)($it['image'] ?? '')),
            'site_slug' => '',
            'site_type' => '',
            'url'       => $url,
            'icon'      => '',
            'icon_name' => false,
        ];
    }
    return $rows;
}

/** 解析逗号 / 空格分隔的内容 ID 列表：正整数、去重、按输入顺序，最多 $max 个。 */
function gblog_parse_id_list(string $raw, int $max = 20): array
{
    $parts = preg_split('/[,，;；\s]+/u', trim($raw)) ?: [];
    $out   = [];
    foreach ($parts as $p) {
        $n = (int)$p;
        if ($n > 0 && !in_array($n, $out, true)) $out[] = $n;
    }
    return array_slice($out, 0, $max);
}

/** 按内容 ID 列表取已发布内容：posts 与 songs 共用一套全局编号，按输入顺序返回；停用栏目 / 音乐栏目停用时自动排除。 */
function gblog_content_by_ids(array $ids, int $max = 20): array
{
    $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), function ($n) { return $n > 0; }))), 0, $max);
    if (!$ids) return [];
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $map = [];
    try {
        $st = db()->prepare(
            'SELECT p.id,p.title,p.excerpt,p.content,p.cover, s.slug AS site_slug, s.type AS site_type
             FROM posts p LEFT JOIN sites s ON s.id = p.site_id
             WHERE p.status = \'published\' AND p.id IN (' . $ph . ') AND ' . gblog_active_site_clause('p.site_id')
        );
        $st->execute($ids);
        foreach ($st->fetchAll() as $p) {
            $map[(int)$p['id']] = [
                'title'     => (string)$p['title'],
                'subtitle'  => '',
                'excerpt'   => (string)($p['excerpt'] ?? ''),
                'content'   => (string)($p['content'] ?? ''),
                'cover'     => (string)($p['cover'] ?? ''),
                'site_slug' => (string)($p['site_slug'] ?? ''),
                'site_type' => (string)($p['site_type'] ?? ''),
                'url'       => 'index.php?do=post&id=' . (int)$p['id'],
            ];
        }
    } catch (Throwable $e) {}
    if (function_exists('gblog_music_site_active') && gblog_music_site_active()) {
        try {
            $st = db()->prepare(
                'SELECT sg.id, sg.title, sg.artist, sg.cover_url FROM songs sg
                 WHERE sg.status = \'published\' AND sg.site_id = ' . gblog_music_site_id() . ' AND sg.id IN (' . $ph . ')'
            );
            $st->execute($ids);
            foreach ($st->fetchAll() as $s) {
                $sid = (int)$s['id'];
                if (isset($map[$sid])) continue;
                $map[$sid] = [
                    'title'     => (string)$s['title'],
                    'subtitle'  => (string)($s['artist'] ?? ''),
                    'excerpt'   => (string)($s['artist'] ?? ''),
                    'content'   => '',
                    'cover'     => (string)($s['cover_url'] ?? ''),
                    'site_slug' => 'music',
                    'site_type' => 'song',
                    'url'       => 'index.php?do=song&id=' . $sid,
                ];
            }
        } catch (Throwable $e) {}
    }
    $rows = [];
    foreach ($ids as $id) {
        if (!isset($map[$id])) continue;
        $r = $map[$id];
        $rows[] = [
            'id'        => $id,
            'title'     => $r['title'],
            'subtitle'  => $r['subtitle'],
            'excerpt'   => $r['excerpt'],
            'content'   => $r['content'],
            'cover'     => $r['cover'],
            'site_slug' => $r['site_slug'],
            'site_type' => $r['site_type'],
            'url'       => $r['url'],
            'icon'      => '',
            'icon_name' => false,
        ];
    }
    return $rows;
}

function gblog_agg_module_rows(array $m): array
{
    $key = (string)$m['key'];

    if ($key === 'cards') {
        $ids = gblog_parse_id_list((string)($m['ids'] ?? ''));
        if ($ids) return gblog_content_by_ids($ids);
        $items = (array)($m['items'] ?? []);
        if (!empty($items)) return gblog_agg_item_rows($items);
        $rows = [];
        try {
            $st = db()->prepare(
                'SELECT p.id,p.title,p.cover, s.slug AS site_slug
                 FROM posts p LEFT JOIN sites s ON s.id = p.site_id
                 WHERE p.status = \'published\' AND ' . gblog_active_site_clause('p.site_id') .
                ' ORDER BY p.created_at DESC, p.id DESC LIMIT 4'
            );
            $st->execute();
            foreach ($st->fetchAll() as $p) {
                $rows[] = [
                    'title'     => (string)$p['title'],
                    'subtitle'  => '',
                    'cover'     => (string)($p['cover'] ?? ''),
                    'site_slug' => (string)($p['site_slug'] ?? ''),
                    'url'       => 'index.php?do=post&id=' . (int)$p['id'],
                    'icon'      => '',
                    'icon_name' => false,
                ];
            }
        } catch (Throwable $e) {}
        return $rows;
    }

    $where  = "p.status = 'published' AND " . gblog_active_site_clause('p.site_id');
    $params = [];
    $order  = 'p.created_at DESC, p.id DESC';
    $limit  = min(10, max(1, (int)($m['count'] ?? 5)));
    $manualIds = [];

    if ($key === 'carousel') {
        $ids = gblog_parse_id_list((string)($m['ids'] ?? ''));
        if ($ids) return gblog_content_by_ids($ids);
        $limit = min(6, max(1, (int)($m['count'] ?? 4)));
        $items = (array)($m['items'] ?? []);
        if (!empty($items)) return gblog_agg_item_rows($items);
        $mode  = (string)($m['mode'] ?? 'manual');
        if ($mode === 'manual') {
            $manualIds = array_values(array_filter(array_map('intval', explode(',', (string)($m['post_ids'] ?? '')))));
            if ($manualIds) {
                $ph = implode(',', array_fill(0, count($manualIds), '?'));
                $where .= ' AND p.id IN (' . $ph . ')';
                $params = $manualIds;
            }
        } elseif ($mode === 'auto') {
            // 置顶已移除：auto 退化为「按默认排序取最新」，指定内容请用 manual/id/items
        } elseif ($mode === 'site') {
            $sid = (int)($m['site_id'] ?? 0);
            if ($sid > 0) { $where .= ' AND p.site_id = ?'; $params[] = $sid; }
        }
    } else {
        return [];
    }

    $sql = "SELECT p.id, p.title, p.excerpt, p.content, p.cover, p.author, p.created_at,
                   p.view_count, p.like_count, p.favorite_count,
                   s.name AS site_name, s.slug AS site_slug, s.type AS site_type,
                   c.name AS category_name,
                   (SELECT COUNT(*) FROM comments cm WHERE cm.target_type = 'post' AND cm.target_id = p.id AND cm.status = 'approved') AS comment_count
            FROM posts p
            LEFT JOIN sites s ON s.id = p.site_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE " . $where . "
            ORDER BY " . $order . "
            LIMIT " . (int)$limit;
    try {
        $st = db()->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    if ($manualIds && $rows) {
        $map = [];
        foreach ($rows as $r) { $map[(int)$r['id']] = $r; }
        $ordered = [];
        foreach ($manualIds as $id) { if (isset($map[$id])) $ordered[] = $map[$id]; }
        $rows = $ordered;
    }
    foreach ($rows as &$r) {
        if (empty($r['url'])) $r['url'] = 'index.php?do=post&id=' . (int)$r['id'];
    }
    unset($r);
    return $rows;
}
