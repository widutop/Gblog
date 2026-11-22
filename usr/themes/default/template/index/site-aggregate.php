<?php
/**
 * Template Name: 栏目聚合（Tab 列表）
 * Context: $posts, $site, $page, $totalPages, $gbTabItems, $gbPageBase —— 栏目页
 *          home_mode=aggregate 专用，与全站聚合首页（index.php，吃 $homePosts）契约不同。
 *          整条列表调用 post_card_list 统一组件（tabs + post-card + 分页），与分类页同款。
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
render_slot('post_card_list', [
    'posts'       => $posts ?? [],
    'slug'        => (string)($site['slug'] ?? ''),
    'page'        => (int)($page ?? 1),
    'totalPages'  => (int)($totalPages ?? 1),
    'gbTabItems'  => $gbTabItems ?? [],
    'gbPageBase'  => $gbPageBase ?? null,
]);
