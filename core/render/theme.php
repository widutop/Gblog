<?php
// 改动样式文件后必须递增 $ver，强制刷新浏览器缓存。

require_once __DIR__ . '/../../usr/data/config.php';
require_once __DIR__ . '/../route/context.php';

function get_theme_list(): array
{
    $themes = [];
    if (!is_dir(THEMES_DIR)) {
        return $themes;
    }
    $dirs = scandir(THEMES_DIR);
    foreach ($dirs as $dir) {
        if ($dir === '.' || $dir === '..' || $dir[0] === '.' || $dir[0] === '_') {
            continue;
        }
        $themeDir = THEMES_DIR . '/' . $dir;
        if (!is_dir($themeDir)) {
            continue;
        }
        $themes[] = read_theme_info($dir);
    }
    usort($themes, function ($a, $b) { return strcmp($a['name'], $b['name']); });
    return $themes;
}

function read_theme_info(string $dir): array
{
    $infoFile = THEMES_DIR . '/' . $dir . '/theme.json';
    $info = [
        'name'        => $dir,
        'slug'        => $dir,
        'author'      => '',
        'author_uri'  => '',
        'description' => '',
        'desc'        => '',
        'version'     => '1.0',
        'screenshot'  => '',
        'parent'      => '',
        'home_mode'   => 'aggregate',
        'tags'        => [],
        'supports'    => [],
    ];
    if (is_file($infoFile)) {
        $json = json_decode((string)file_get_contents($infoFile), true);
        if (is_array($json)) {
            $strKeys = ['name', 'slug', 'author', 'author_uri', 'description', 'desc', 'version', 'screenshot', 'parent', 'home_mode'];
            foreach ($strKeys as $k) {
                if (isset($json[$k]) && is_string($json[$k]) && $json[$k] !== '') {
                    $info[$k] = $json[$k];
                }
            }
            foreach (['tags', 'supports'] as $k) {
                if (isset($json[$k]) && is_array($json[$k])) {
                    $info[$k] = array_values(array_filter($json[$k], 'is_string'));
                }
            }
        }
    }
    $info['dir'] = $dir;
    return $info;
}

// 兜底取磁盘上第一个主题目录：主题目录被删/改名后前台仍可用，而不是空白
function gblog_first_available_theme(): string
{
    static $first = null;
    if ($first !== null) return $first;
    $first = '';
    if (is_dir(THEMES_DIR)) {
        foreach (scandir(THEMES_DIR) ?: [] as $d) {
            if ($d === '.' || $d === '..' || $d[0] === '.' || $d[0] === '_' || !is_dir(THEMES_DIR . '/' . $d)) {
                continue;
            }
            $first = $d;
            break;
        }
    }
    return $first;
}

function get_active_theme(): string
{
    $name = (string)get_setting('current_theme', '');
    if ($name === '') {
        $name = (string)get_setting('home_theme', '');
    }
    if (!preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $name) || !is_dir(THEMES_DIR . '/' . $name)) {
        $name = (string)get_setting('home_theme', '');
    }
    if (!preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $name) || !is_dir(THEMES_DIR . '/' . $name)) {
        $name = gblog_first_available_theme();
    }
    return $name;
}

function gblog_active_theme_chain(): array
{
    $theme = get_active_theme();
    $chain = [$theme];
    $parent = (string)(read_theme_info($theme)['parent'] ?? '');
    if ($parent !== '' && $parent !== $theme && is_dir(THEMES_DIR . '/' . $parent)) {
        $chain[] = $parent;
    }
    return $chain;
}

// 模板索引：递归收集主题链 template/ 下全部 .php。键 = 文件名（去 .php），即模板 ID。
// 目录只做物理分类，不参与识别——文件挪进任何子目录、子目录改名都不影响查找。
// 激活主题优先；同一主题内浅层路径优先（兼容「根目录旧结构与分类子目录并存」的过渡期）。
function gblog_tpl_index(): array
{
    static $idx = null;
    if ($idx !== null) return $idx;
    $idx = [];
    if (!defined('THEMES_DIR')) return $idx;
    foreach (gblog_active_theme_chain() as $theme) {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $theme)) continue;
        $root = THEMES_DIR . '/' . $theme . '/template';
        if (!is_dir($root)) continue;
        $rootC = str_replace('\\', '/', $root);
        $local = [];
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($it as $f) {
                if (!$f->isFile() || strtolower((string)$f->getExtension()) !== 'php') continue;
                $abs  = str_replace('\\', '/', $f->getPathname());
                $base = (string)$f->getBasename('.php');
                if (!preg_match('/^[a-zA-Z0-9_-]{1,60}$/', $base)) continue;
                $rel   = ltrim(substr($abs, strlen($rootC)), '/');
                $depth = substr_count($rel, '/');
                if (isset($local[$base]) && $local[$base]['depth'] <= $depth) continue;
                $local[$base] = [
                    'rel'   => 'template/' . $rel,
                    'abs'   => $abs,
                    'depth' => $depth,
                    'part'  => strpos($rel, 'parts/') === 0,
                    'theme' => $theme,
                ];
            }
        } catch (Throwable $e) {
            continue;
        }
        foreach ($local as $base => $hit) {
            if (!isset($idx[$base])) $idx[$base] = $hit;
        }
    }
    return $idx;
}

// 按模板 ID（文件名）取真实路径，与目录结构无关。
function gblog_tpl_path(string $base): string
{
    $base = trim($base);
    if ($base === '' || strpos($base, '/') !== false || strpos($base, '..') !== false) return '';
    $hit = gblog_tpl_index()[$base] ?? null;
    return ($hit && is_file($hit['abs'])) ? $hit['abs'] : '';
}

// 模板 ID => 所在分类目录（后台提示用；改名/移动后自动跟随）
function gblog_tpl_dir_label(string $base): string
{
    $hit = gblog_tpl_index()[$base] ?? null;
    if (!$hit) return '';
    $rel = (string)substr($hit['rel'], strlen('template/'));
    $pos = strrpos($rel, '/');
    return $pos === false ? '根目录' : substr($rel, 0, $pos);
}

function load_active_theme(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    foreach (gblog_active_theme_chain() as $theme) {
        $fn = THEMES_DIR . '/' . $theme . '/functions.php';
        if (is_file($fn)) {
            require_once $fn;
        }
    }
}

function active_theme_name(): string
{
    return get_active_theme();
}

function theme_stylesheets(): string
{
    $theme = active_theme_name();
    load_active_theme();
    $ver  = '20261115b';
    $ctx  = function_exists('page_context') ? page_context() : [];
    $link = static function (string $rel) use ($ver): string {
        $u = function_exists('gblog_theme_asset_url') ? gblog_theme_asset_url($rel) : '';
        return $u !== '' ? '<link rel="stylesheet" href="' . $u . '?v=' . $ver . '">' . "\n" : '';
    };

    $html  = $link('assets/css/base.css');
    $html .= $link('assets/css/style.css');
    // 首页聚合布局（assets/css/aggregate.css）必须排在皮肤 style.css 之后加载：
    if (($ctx['home_mode'] ?? '') === 'aggregate' && ($ctx['view'] ?? '') === 'home') {
        $html .= $link('assets/css/aggregate.css');
    }
    $html .= $link('assets/css/layout.css');
    $html .= $link('assets/css/cover-placeholder.css');
    // 封面图填充零件（全站「图片铺满容器」唯一归口，:where() 零特异性，不覆盖皮肤规则）
    $html .= $link('assets/css/cover-fill.css');

    $vw = (string)($ctx['view'] ?? 'page');
    if (preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $vw) && function_exists('gblog_theme_asset_url')) {
        $type  = (string)($ctx['site']['type'] ?? '');
        $alias = ['software' => 'software', 'song' => 'music', 'novel' => 'novel', 'image' => 'image'];
        $dirs  = isset($alias[$type]) ? [$alias[$type]] : [];
        foreach ($dirs as $dir) {
            $u = gblog_theme_asset_url('assets/css/view/' . $dir . '/' . $vw . '.css');
            if ($u !== '') {
                $html .= '<link rel="stylesheet" href="' . $u . '?v=' . $ver . '">' . "\n";
                break;
            }
        }
    }
    if (!empty($GLOBALS['gblog_user_center'])) {
        $html .= $link('assets/css/user.css');
    }
    if (function_exists('gblog_theme_asset_url') && gblog_theme_asset_url('assets/css/soft-preview.css') !== '') {
        $html .= $link('assets/css/soft-preview.css');
    }
    // 全站零件间距统一表必须最后加载：它要覆盖上面所有样式里的历史间距值。
    $html .= $link('assets/css/spacing.css');
    return $html;
}

function custom_css_block(): string
{
    if (!function_exists('page_context') || !function_exists('get_setting')) return '';
    $ctx  = page_context();
    $slug = !empty($ctx['is_home']) ? '_home' : (string)($ctx['site']['slug'] ?? 'post');
    $view = (string)$ctx['view'];
    $css  = get_setting('css:' . $slug . ':' . $view, '');
    if (trim($css) === '') return '';
    $css = str_ireplace(['</style', '@import', 'expression(', 'javascript:'], '', $css);
    $scope = htmlspecialchars($slug . ':' . $view, ENT_QUOTES, 'UTF-8');
    return '<style data-scope="' . $scope . '">' . "\n" . $css . "\n" . '</style>' . "\n";
}
