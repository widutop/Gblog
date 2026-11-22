<?php
/**
 * 内容取数引擎（隐形标签原语）：gblog_arclist() 返回数据行，作者自行渲染。
 */
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }

/** 解析「指定 ID」参数：支持逗号/空格/分号/中文标点，去重、最多 20 个。 */
function gblog_cb_parse_ids(string $raw): array
{
    $parts = preg_split('/[,，;；\s]+/u', trim($raw)) ?: [];
    $out   = [];
    foreach ($parts as $p) {
        $n = (int)$p;
        if ($n > 0 && !in_array($n, $out, true)) $out[] = $n;
    }
    return array_slice($out, 0, 20);
}

/** 作用域 SQL 条件：全站＝所有启用栏目；栏目＝当前栏目 ID。整数已强转，可直接拼接。 */
function gblog_cb_clause(string $col, array $z): string
{
    return ($z['scope'] === 'site' && (int)$z['site_id'] > 0)
        ? $col . ' = ' . (int)$z['site_id']
        : $col . ' IN (SELECT id FROM sites WHERE enabled = 1)';
}

/** 文章取数：rule + 作用域 + 可选指定 ID。返回归一化行。 */
function gblog_cb_post_rows(string $rule, string $ids, int $limit, array $z): array
{
    $where = "p.status = 'published' AND " . gblog_cb_clause('p.site_id', $z);
    $order = 'p.created_at DESC, p.id DESC';
    if ($rule === 'hot')       $order = 'p.view_count DESC, p.id DESC';
    if ($rule === 'recommend') $order = 'p.sort_order DESC, p.created_at DESC, p.id DESC';
    $parsed = [];
    if ($rule === 'ids') {
        $parsed = gblog_cb_parse_ids($ids);
        if (!$parsed) return [];
        $where .= ' AND p.id IN (' . implode(',', array_fill(0, count($parsed), '?')) . ')';
    }
    $limit = $rule === 'ids' ? count($parsed) : $limit;
    if ($limit <= 0) return [];
    $sql = "SELECT p.id, p.title, p.cover, p.site_id, p.created_at, p.view_count,
                   s.name AS site_name, s.slug AS site_slug, s.type AS site_type
            FROM posts p LEFT JOIN sites s ON s.id = p.site_id
            WHERE " . $where . " ORDER BY " . $order . " LIMIT " . (int)$limit;
    try {
        $st = db()->prepare($sql);
        $st->execute($rule === 'ids' ? $parsed : []);
        $rows = $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    if ($rule === 'ids') {
        $map = [];
        foreach ($rows as $r) $map[(int)$r['id']] = $r;
        $ordered = [];
        foreach ($parsed as $id) if (isset($map[$id])) $ordered[] = $map[$id];
        $rows = $ordered;
    }
    $out = [];
    foreach ($rows as $r) {
        if ($z['scope'] === 'site' && (int)$r['site_id'] !== (int)$z['site_id']) continue;
        $out[] = [
            'id'        => (int)$r['id'],
            'title'     => (string)$r['title'],
            'url'       => 'index.php?do=post&id=' . (int)$r['id'],
            'cover'     => (string)($r['cover'] ?? ''),
            'meta'      => (string)($r['site_name'] ?: ''),
            'date'      => (string)($r['created_at'] ?? ''),
            'site_slug' => (string)($r['site_slug'] ?? ''),
            'site_type' => (string)($r['site_type'] ?? ''),
        ];
    }
    return array_slice($out, 0, max(1, $limit));
}

function gblog_cb_song_rows(string $rule, string $ids, int $limit): array
{
    $songSiteId = 0;
    try {
        $songSiteId = (int)db()->query("SELECT id FROM sites WHERE type = 'song' AND enabled = 1 ORDER BY id ASC LIMIT 1")->fetchColumn();
    } catch (Throwable $e) {
    }
    if ($songSiteId <= 0) return [];
    $order = 'sg.created_at DESC, sg.id DESC';
    if ($rule === 'hot')       $order = 'sg.play_count DESC, sg.id DESC';
    if ($rule === 'recommend') $order = 'sg.sort_order DESC, sg.id DESC';
    $parsed = [];
    $where  = "sg.status = 'published' AND sg.site_id = " . (int)$songSiteId;
    if ($rule === 'ids') {
        $parsed = gblog_cb_parse_ids($ids);
        if (!$parsed) return [];
        $where .= ' AND sg.id IN (' . implode(',', array_fill(0, count($parsed), '?')) . ')';
    }
    $limit = $rule === 'ids' ? count($parsed) : $limit;
    if ($limit <= 0) return [];
    $sql = "SELECT sg.id, sg.title, sg.artist, sg.cover_url, sg.created_at
            FROM songs sg WHERE " . $where . " ORDER BY " . $order . " LIMIT " . (int)$limit;
    try {
        $st = db()->prepare($sql);
        $st->execute($rule === 'ids' ? $parsed : []);
        $rows = $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    if ($rule === 'ids') {
        $map = [];
        foreach ($rows as $r) $map[(int)$r['id']] = $r;
        $ordered = [];
        foreach ($parsed as $id) if (isset($map[$id])) $ordered[] = $map[$id];
        $rows = $ordered;
    }
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'        => (int)$r['id'],
            'title'     => (string)$r['title'],
            'url'       => 'index.php?do=song&id=' . (int)$r['id'],
            'cover'     => (string)($r['cover_url'] ?? ''),
            'meta'      => (string)($r['artist'] ?? ''),
            'date'      => (string)($r['created_at'] ?? ''),
            'site_slug' => 'music',
            'site_type' => 'song',
        ];
    }
    return array_slice($out, 0, max(1, $limit));
}

// 当前页所属栏目 slug；全站页（首页/搜索/归档等）返回 _home。
function gblog_cb_current_zone(): string
{
    $ctx  = function_exists('page_context') ? page_context() : [];
    $slug = (string)($ctx['site']['slug'] ?? '');
    return $slug !== '' ? $slug : '_home';
}

// zone（栏目 slug）→ 作用域描述。_home = 全站；栏目不存在或未启用 → enabled=false，调用方直接返回空。
function gblog_cb_zone(string $zone): array
{
    if ($zone === '' || $zone === '_home') {
        return ['scope' => 'global', 'site_id' => 0, 'slug' => '_home', 'type' => '', 'enabled' => true];
    }
    try {
        $st = db()->prepare('SELECT * FROM sites WHERE slug = ? AND enabled = 1 LIMIT 1');
        $st->execute([$zone]);
        $row = $st->fetch();
        if ($row) {
            return ['scope' => 'site', 'site_id' => (int)$row['id'], 'slug' => (string)$row['slug'],
                    'type' => (string)$row['type'], 'enabled' => true];
        }
    } catch (Throwable $e) {
    }
    return ['scope' => 'invalid', 'site_id' => 0, 'slug' => $zone, 'type' => '', 'enabled' => false];
}

/** 统一取数入口：source/zone/rule/ids/limit。 */
function gblog_query_items(array $args): array
{
    $source = (string)($args['source'] ?? 'auto');
    $zone   = (string)($args['zone'] ?? gblog_cb_current_zone());
    $rule   = (string)($args['rule'] ?? 'recent');
    $ids    = (string)($args['ids'] ?? '');
    $limit  = max(1, min(20, (int)($args['limit'] ?? 6)));
    $z      = gblog_cb_zone($zone);
    if (!$z['enabled']) return [];
    if ($source === 'auto') {
        $source = ($z['type'] === 'song') ? 'songs' : 'posts';
    }
    if ($source === 'songs') {
        if ($zone !== '_home' && $z['type'] !== 'song') return [];
        return gblog_cb_song_rows($rule, $ids, $limit);
    }
    return gblog_cb_post_rows($rule, $ids, $limit, $z);
}

/** 隐形标签取数原语：返回数据行，作者自行渲染；别名 row→limit、orderby→rule、typeid→zone。 */
function gblog_arclist(array $args = []): array
{
    $mapped = [];
    if (isset($args['row']))    $mapped['limit'] = (int)$args['row'];
    if (isset($args['orderby']) && !isset($args['rule'])) $mapped['rule'] = (string)$args['orderby'];
    if (isset($args['typeid'])) $mapped['zone']  = (string)$args['typeid'];
    foreach (['source', 'zone', 'rule', 'ids', 'limit'] as $k) {
        if (isset($args[$k]) && !isset($mapped[$k])) $mapped[$k] = $args[$k];
    }
    return gblog_query_items($mapped);
}
