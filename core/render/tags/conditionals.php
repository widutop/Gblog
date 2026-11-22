<?php

if (!defined('GBLOG_ROOT')) exit;

function is_home(): bool
{
    $ctx = function_exists('page_context') ? page_context() : [];
    return !empty($ctx['is_home']);
}

function is_site_home(): bool
{
    return (string)($_GET['do'] ?? '') === 'site';
}

function is_all_home(): bool
{
    return (string)($_GET['do'] ?? '') === 'home';
}

function is_single(): bool
{
    return in_array((string)($_GET['do'] ?? ''), ['post', 'song', 'chapter'], true);
}

function gblog_current_type(): string
{
    $do = (string)($_GET['do'] ?? '');
    if ($do === 'song') return 'song';
    if ($do === 'chapter') return 'novel';
    if ($do === 'post') {
        $ctx = function_exists('page_context') ? page_context() : [];
        $type = (string)($ctx['site']['type'] ?? 'article');
        return $type !== '' ? $type : 'article';
    }
    return '';
}

function is_single_type(string $type): bool
{
    return gblog_current_type() === $type;
}

function is_song(): bool
{
    return (string)($_GET['do'] ?? '') === 'song';
}

function is_novel_chapter(): bool
{
    return (string)($_GET['do'] ?? '') === 'chapter';
}

function is_category(): bool
{
    return (string)($_GET['do'] ?? '') === 'category';
}

function is_search(): bool
{
    return (string)($_GET['do'] ?? '') === 'search';
}

function is_user_center(): bool
{
    return in_array((string)($_GET['do'] ?? ''), ['profile', 'login', 'register', 'reset', 'forgot'], true);
}

function is_sidebar_off(): bool
{
    return !empty($GLOBALS['gblog_no_sidebar']);
}
