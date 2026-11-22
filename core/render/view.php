<?php
/** 渲染契约：render_view() = 唯一视图入口（选版 → 类型专属 → 基础兜底）；render_slot() = 钩子 + parts 零件。 */
require_once __DIR__ . '/../route/context.php';

function gblog_name_valid(string $name): bool
{
    return (bool)preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $name);
}

function gblog_view_type(): string
{
    $do = (string)($_GET['do'] ?? '');
    if ($do === 'song') return 'song';
    if ($do === 'chapter') return 'novel';
    $ctx  = function_exists('page_context') ? page_context() : [];
    $type = (string)($ctx['site']['type'] ?? 'article');
    return $type !== '' ? $type : 'article';
}

/**
 * 唯一视图入口：选版（layout:{role}:{slug} / page_template:{view}）→ 类型专属 → 基础兜底。
 * 页面类 view = page|404|links|archive|tag|search；$siteSlug 供选版与零件解析。
 */
function render_view(string $view, array $vars = [], ?string $siteSlug = null): bool
{
    if (!gblog_name_valid($view)) return false;
    $ctx       = function_exists('page_context') ? page_context() : [];
    $__g_slug  = $siteSlug ?? (string)($ctx['site']['slug'] ?? 'post');
    $roleMap   = ['index' => 'aggregate', 'home' => 'home', 'list' => 'list', 'detail' => 'detail'];
    $baseMap   = ['index' => 'index', 'home' => 'home', 'list' => 'list', 'detail' => 'single'];
    $role      = $roleMap[$view] ?? 'page';
    $base      = $baseMap[$view] ?? $view;
    if ($role === 'aggregate' && empty($ctx['is_home']) && ($siteSlug === null || $siteSlug === '')) {
        $__g_slug = '_home';
    }
    $type      = gblog_view_type();
    $typeAlias = ['software' => 'software', 'song' => 'song', 'music' => 'song', 'novel' => 'novel', 'image' => 'image'];

    $__g_file = null;
    if ($role !== 'page' && function_exists('gblog_layout')) {
        $__g_file = gblog_layout($role, $__g_slug);
    } elseif ($role === 'page' && function_exists('gblog_page_template_file')) {
        $__g_file = gblog_page_template_file($view);
    }
    if ($__g_file === null && $role !== 'aggregate' && $role !== 'page' && isset($typeAlias[$type])) {
        $__g_try = gblog_tpl_path($base . '-' . $typeAlias[$type]);
        if ($__g_try !== '') $__g_file = $__g_try;
    }
    if ($__g_file === null) {
        if ($view === 'index' && $__g_slug !== '_home') {
            $__g_try = gblog_tpl_path('site-aggregate');
            if ($__g_try !== '') $__g_file = $__g_try;
        } else {
            $__g_try = gblog_tpl_path($base);
            if ($__g_try !== '') {
                $__g_file = $__g_try;
            } elseif ($view === 'index') {
                $__g_try = gblog_tpl_path('agg_layout');
                if ($__g_try !== '') $__g_file = $__g_try;
            }
        }
    }
    if ($__g_file === null) return false;
    extract($vars, EXTR_SKIP);
    include $__g_file;
    return true;
}

function render_slot(string $slot, array $vars = [], ?string $siteSlug = null): void
{
    if (!gblog_name_valid($slot)) return;
    if (function_exists('do_action')) do_action($slot, $vars);

    $ctx      = function_exists('page_context') ? page_context() : [];
    $__g_slug = $siteSlug ?? (string)($ctx['site']['slug'] ?? 'post');
    if (!gblog_name_valid($__g_slug)) return;
// 零件唯一归口：template/parts/，主题链解析（激活主题 → 父主题）
    $__g_file = gblog_tpl_path($slot);
    if ($__g_file === '') return;
    extract($vars, EXTR_SKIP);
    include $__g_file;
}

function gblog_not_found(string $title = '页面不存在', string $message = '抱歉，你访问的内容不存在或已被删除。'): void
{
    http_response_code(404);
    $page_title = $title;
    if (function_exists('set_page_context')) {
        set_page_context(gblog_infer_site((string)($_GET['do'] ?? '')), 'page');
    }
    get_header();
    if (!render_view('404', ['page_title' => $title, 'message' => $message])) {
        echo '<section class="content"><div class="card"><p>' . e($message) . '</p></div></section>';
    }
    get_footer();
    exit;
}
