<?php
// 主题只允许通过本文件提供的标签装配页面，禁止直接 require 程序目录文件

if (!defined('GBLOG_ROOT'))      define('GBLOG_ROOT', dirname(__DIR__));
if (!defined('THEMES_DIR'))      define('THEMES_DIR', GBLOG_ROOT . '/usr/themes');

// 标签 API 版本（公开契约，只增不改签名；theme.json 可声明 min_tag_api）
if (!defined('GBLOG_TAG_API')) define('GBLOG_TAG_API', '2.0');

require_once __DIR__ . '/tags/assembly.php';
require_once __DIR__ . '/tags/conditionals.php';
require_once __DIR__ . '/tags/data.php';
require_once __DIR__ . '/tags/site.php';
require_once __DIR__ . '/tags/options.php';

// 主题链只有激活主题与父主题，无全局兜底：主题包必须自足，缺文件即缺页面
function gblog_theme_chain(): array
{
    return function_exists('gblog_active_theme_chain') ? gblog_active_theme_chain() : [];
}

function gblog_theme_part(string $rel): string
{
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if ($rel === '' || strpos($rel, '..') !== false) return '';
    foreach (gblog_theme_chain() as $theme) {
        $f = THEMES_DIR . '/' . $theme . '/' . $rel;
        if (is_file($f)) return $f;
    }
    // 显式路径未命中（模板已挪进分类子目录）时，退回按文件名在整个 template/ 里查。
    // 两种入参都要兜住：'template/shell/header.php' 这类路径式，以及 'header' 这类裸 ID。
    if (strpos($rel, '/') === false) {
        return gblog_tpl_path($rel);
    }
    if (strpos($rel, 'template/') === 0) {
        return gblog_tpl_path((string)basename($rel, '.php'));
    }
    return '';
}

function gblog_theme_asset_url(string $rel): string
{
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if ($rel === '' || strpos($rel, '..') !== false) return '';
    foreach (gblog_theme_chain() as $theme) {
        if (is_file(THEMES_DIR . '/' . $theme . '/' . $rel)) {
            // 必须根绝对：路径式 URL (/post/slug、/c/slug) 下相对地址会解析到 /post/usr/... 而 404
            $base = function_exists('gblog_web_base') ? gblog_web_base() : '/';
            $u = $base . 'usr/themes/' . rawurlencode($theme) . '/' . $rel;
// JS 无统一版本入口，用文件时间戳，改动后即时生效（CSS 由 theme_stylesheets 的 $ver 负责）
            if (substr($rel, -3) === '.js') {
                $m = @filemtime(THEMES_DIR . '/' . $theme . '/' . $rel);
                if ($m) $u .= '?v=' . $m;
            }
            return $u;
        }
    }
    return '';
}

// 加载自包含零件（内部只用标签自取数据）。需传控制器变量的零件请用同作用域 require。
function gblog_template_part(string $rel): void
{
    $f = gblog_theme_part($rel);
    if ($f !== '') {
        require $f;
    }
}

function get_header(): void
{
    gblog_template_part('header');
}

function get_footer(): void
{
    gblog_template_part('footer');
}

function get_sidebar(): void
{
    if (!empty($GLOBALS['gblog_no_sidebar'])) return;
    if (!empty($GLOBALS['is_home'])) {
        gblog_template_part('agg-sidebar');
    } else {
        gblog_template_part('sidebar');
    }
}
