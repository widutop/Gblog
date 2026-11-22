<?php
/**
 * 核心契约见《Gblog 主题制作指南》，以下为不可删改的硬约定：
 * 字段 / 钩子 / 过滤器只增不删；停用栏目一律走 gblog_active_site_clause() 系列判定；
 * 模板 / 零件 / 静态资源只经主题链解析；CSS 变量名不可改名。
 */
if (!defined('GBLOG_CORE_VERSION')) define('GBLOG_CORE_VERSION', '1.4.0');
if (!defined('GBLOG_ROOT'))    define('GBLOG_ROOT', dirname(__DIR__));
if (!defined('PLUGINS_DIR'))   define('PLUGINS_DIR', GBLOG_ROOT . '/usr/plugins');
if (!defined('THEMES_DIR'))    define('THEMES_DIR', GBLOG_ROOT . '/usr/themes');

function set_page_context(array $site, string $view = ''): void
{
    $GLOBALS['gblog_page_context'] = ['site' => $site, 'view' => $view];
}

function gblog_infer_site(string $do): array
{
    if (!empty($GLOBALS['gblog_page_context']['site'])) {
        return $GLOBALS['gblog_page_context']['site'];
    }
    if (!empty($GLOBALS['current_site_data'])) {
        return $GLOBALS['current_site_data'];
    }
    if (function_exists('db')) {
        try {
            $pdo  = db();
            $slug = (string)($_GET['slug'] ?? '');
            $id   = (int)($_GET['id'] ?? 0);
            if ($do === 'site' && $slug !== '') {
                $st = $pdo->prepare('SELECT * FROM sites WHERE slug = ? AND enabled = 1 LIMIT 1');
                $st->execute([$slug]);
                $row = $st->fetch();
                if ($row) return $row;
            }
            if ($do === 'post' && $id > 0) {
                // 文章按全局 id 定位（posts.slug 已移除）
                $st = $pdo->prepare('SELECT s.* FROM posts p JOIN sites s ON s.id = p.site_id WHERE p.id = ? AND s.enabled = 1 LIMIT 1');
                $st->execute([$id]);
                $row = $st->fetch();
                if ($row) return $row;
            }
// 分类页按 id（扁平 .html 形态）命中时反查所属栏目
            if ($do === 'category' && $id > 0) {
                $st = $pdo->prepare('SELECT s.* FROM categories c JOIN sites s ON s.id = c.site_id WHERE c.id = ? AND s.enabled = 1 LIMIT 1');
                $st->execute([$id]);
                $row = $st->fetch();
                if ($row) return $row;
            }
            if (in_array($do, ['song', 'songs', 'song_lyrics', 'song_play'], true)) {
                $row = $pdo->query("SELECT * FROM sites WHERE type = 'song' AND enabled = 1 ORDER BY id ASC LIMIT 1")->fetch();
                if ($row) return $row;
            }
            if ($do === 'chapter') {
                $row = $pdo->query("SELECT * FROM sites WHERE type = 'novel' AND enabled = 1 ORDER BY id ASC LIMIT 1")->fetch();
                if ($row) return $row;
            }
        } catch (Throwable $e) {
        }
    }
    if (function_exists('current_site')) {
        $row = current_site();
        if (is_array($row)) return $row;
    }
    return ['id' => 0, 'slug' => 'post', 'name' => '', 'theme' => 'default', 'type' => 'article'];
}

function gblog_infer_view(string $do, bool $isHome): string
{
    if (!empty($GLOBALS['gblog_page_context']['view'])) {
        return (string)$GLOBALS['gblog_page_context']['view'];
    }
    if ($isHome || $do === 'site') return 'home';
    if (in_array($do, ['post', 'song', 'chapter'], true)) return 'detail';
    if (in_array($do, ['songs', 'home', 'category', 'tag', 'search', 'archive'], true)) return 'list';
    return 'page';
}

function page_context(): array
{
    static $cached = null;
    if ($cached !== null) return $cached;

    $do     = (string)($_GET['do'] ?? '');
    $isHome = ($do === '') || !empty($GLOBALS['is_home']);
    $site   = gblog_infer_site($do);
    $view   = gblog_infer_view($do, $isHome);

    $skin = function_exists('get_active_theme')
        ? get_active_theme()
        : (function_exists('get_setting') ? get_setting('home_theme', 'default') : 'default');
    if (!preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $skin) || !is_dir(THEMES_DIR . '/' . $skin)) {
        $skin = function_exists('gblog_first_available_theme') ? gblog_first_available_theme() : 'default';
    }

    $homeMode = $isHome
        ? 'aggregate'
        : (($view === 'home') ? (string)($site['home_mode'] ?? 'default') : 'default');
    if (!in_array($homeMode, ['default', 'aggregate'], true)) $homeMode = 'default';

    $cached = [
        'site'      => $site,
        'skin'      => $skin,
        'view'      => $view,
        'home_mode' => $homeMode,
        'is_home'   => $isHome,
    ];
    return $cached;
}
