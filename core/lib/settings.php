<?php
/* 站点与设置 —— 站点解析、设置读写、分页尺寸、日志。
 * 由 core/bootstrap.php 统一加载；本文件只放函数定义，不要写顶层执行代码。 */

function current_site(): array
{
    static $site = null;
    if (!empty($GLOBALS['site']) && is_array($GLOBALS['site']) && !empty($GLOBALS['site']['id'])) {
        return $GLOBALS['site'];
    }
    if ($site !== null) {
        return $site;
    }

    $pdo = db();
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));

    if ($host !== '') {
        $stmt = $pdo->prepare(
            "SELECT * FROM sites WHERE enabled = 1 AND domain IS NOT NULL AND domain <> '' AND LOWER(domain) = ? LIMIT 1"
        );
        $stmt->execute([$host]);
        $site = $stmt->fetch();
        if ($site) {
            return $site;
        }
    }

    // 域名未匹配时直接用第一个启用栏目（后台「默认栏目」设置项已移除）。
    $stmt = $pdo->query("SELECT * FROM sites WHERE enabled = 1 ORDER BY sort_order ASC, id ASC LIMIT 1");
    $site = $stmt->fetch() ?: ['id' => 0, 'name' => SITE_NAME, 'slug' => 'post', 'description' => SITE_DESC, 'theme' => 'default'];
    return $site;
}

function current_site_name(): string
{
    $site = current_site();
    return $site['name'] ?? SITE_NAME;
}

function get_setting(string $key, string $default = ''): string
{
    if (!isset($GLOBALS['__gb_settings_cache'])) {
        $GLOBALS['__gb_settings_cache'] = [];
        try {
            $st = db()->query('SELECT skey, svalue FROM settings');
            foreach ($st->fetchAll() as $r) {
                $GLOBALS['__gb_settings_cache'][(string)$r['skey']] = (string)$r['svalue'];
            }
        } catch (Throwable $e) {
        }
    }
    return array_key_exists($key, $GLOBALS['__gb_settings_cache']) ? $GLOBALS['__gb_settings_cache'][$key] : $default;
}

function save_setting(string $key, string $value): void
{
    $pdo = db();
    unset($GLOBALS['__gb_settings_cache']);
    $stmt = $pdo->prepare("SELECT skey FROM settings WHERE skey = ? LIMIT 1");
    $stmt->execute([$key]);
    if ($stmt->fetchColumn() !== false) {
        $upd = $pdo->prepare("UPDATE settings SET svalue = ? WHERE skey = ?");
        $upd->execute([$value, $key]);
    } else {
        $ins = $pdo->prepare("INSERT INTO settings (skey, svalue) VALUES (?, ?)");
        $ins->execute([$key, $value]);
    }
}

function get_all_settings(): array
{
    $rows = db()->query("SELECT skey, svalue FROM settings")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['skey']] = $r['svalue'];
    }
    return $out;
}

/** 音乐「全部歌曲」列表每页条数；后台不再提供该设置，统一取安装常量。 */
function posts_per_page(): int
{
    $v = defined('POSTS_PER_PAGE') ? (int)POSTS_PER_PAGE : 6;
    return max(1, min(50, $v > 0 ? $v : 6));
}

// 文章/软件/图片/小说/聚合首页列表每页条数（与音乐列表互不影响）
function list_per_page(): int
{
    $v = (int)get_setting('list_per_page', '10');
    return max(1, min(50, $v > 0 ? $v : 10));
}

function gblog_log(string $action, string $detail = ''): void
{
    $action = substr(preg_replace('/[^a-zA-Z0-9_.-]/', '_', $action), 0, 64);
    $detail = substr(str_replace(["\r", "\n", "\0"], ' ', $detail), 0, 240);
    try {
        db()->prepare('INSERT INTO security_logs (action, user_id, detail, ip, created_at) VALUES (?,0,?,?,?)')
            ->execute(['app.' . $action, $detail, substr(gblog_client_ip(), 0, 64), time()]);
        return;
    } catch (Throwable $e) {
        try {
            $dir = __DIR__ . '/../../usr/data/logs';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $file = $dir . '/app-' . date('Y-m-d') . '.log';
            @file_put_contents($file, date('Y-m-d H:i:s') . "\t" . $action . "\t" . $detail . "\n", FILE_APPEND | LOCK_EX);
        } catch (Throwable $ignored) {
        }
    }
}

function get_all_sites(): array
{
    $stmt = db()->query("SELECT * FROM sites ORDER BY sort_order ASC, id ASC");
    return $stmt->fetchAll();
}

function gblog_site_is_builtin($site): bool
{
    return is_array($site) && (int)($site['is_builtin'] ?? 0) === 1;
}

// 栏目类型 → 中文名 / 后台图标 的唯一映射（后台各页共用，勿再各写一份）
function gblog_site_type_meta(?string $type = null): array
{
    static $map = [
        'article'  => ['label' => '文章', 'icon' => 'post'],
        'song'     => ['label' => '音乐', 'icon' => 'song'],
        'software' => ['label' => '软件', 'icon' => 'software'],
        'image'    => ['label' => '图片', 'icon' => 'image'],
        'novel'    => ['label' => '小说', 'icon' => 'novel'],
    ];
    if ($type === null) return $map;
    return $map[$type] ?? $map['article'];
}

// enabled=0 的栏目前台全链路不可见，后台也不能新建或迁入内容
function gblog_active_site_clause(string $col = 'p.site_id'): string
{
    return $col . ' IN (SELECT id FROM sites WHERE enabled = 1)';
}

function gblog_site_is_active(int $siteId): bool
{
    if ($siteId <= 0) return false;
    $st = db()->prepare('SELECT enabled FROM sites WHERE id = ? LIMIT 1');
    $st->execute([$siteId]);
    $on = (int)$st->fetchColumn() === 1;
    $st->closeCursor();
    return $on;
}

function gblog_music_site_active(): bool
{
    $st = db()->query("SELECT COUNT(*) FROM sites WHERE slug = 'music' AND enabled = 1");
    $n = (int)$st->fetchColumn();
    $st->closeCursor();
    return $n > 0;
}

/** 当前启用的音乐站点 ID（多音乐站点隔离用；缓存避免重复查询）。 */
function gblog_music_site_id(): int
{
    static $id = null;
    if ($id !== null) return $id;
    $st = db()->query("SELECT id FROM sites WHERE type = 'song' AND enabled = 1 ORDER BY id ASC LIMIT 1");
    $row = $st->fetchColumn();
    $st->closeCursor();
    $id = $row === false ? 0 : (int)$row;
    return $id;
}
