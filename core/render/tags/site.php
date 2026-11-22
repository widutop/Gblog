<?php

if (!defined('GBLOG_ROOT')) exit;

function gblog_current_site(): array
{
    return function_exists('page_context') ? (array)page_context()['site'] : [];
}

function site_name(): string
{
    return (string)(gblog_current_site()['name'] ?? '');
}
function the_site_name(): void
{
    echo e(site_name());
}

function site_url(): string
{
    $slug = (string)(gblog_current_site()['slug'] ?? '');
    return $slug !== '' ? 'index.php?do=site&slug=' . rawurlencode($slug) : 'index.php';
}
function the_site_url(): void
{
    echo e(site_url());
}

function site_desc(): string
{
    return (string)(gblog_current_site()['description'] ?? '');
}
function the_site_desc(): void
{
    echo e(site_desc());
}

function site_type(): string
{
    return (string)(gblog_current_site()['type'] ?? 'article');
}

// 栏目是否启用（新前台查询/端点必须挂判定；按 slug 优先）
function site_is_active($site): bool
{
    if (is_array($site)) {
        $siteId = (int)($site['id'] ?? 0);
        if (isset($site['enabled'])) return (int)$site['enabled'] === 1;
    } else {
        $siteId = (int)$site;
    }
    return function_exists('gblog_site_is_active') ? gblog_site_is_active($siteId) : true;
}
