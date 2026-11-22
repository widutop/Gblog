<?php
/**
 * Template Name: 分类列表·小说
 * Context: $posts, $cat, $page, $totalPages
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
// 列表外壳与分页走公共零件 category_list；卡片结构在 category_card_novel 零件
render_slot('category_list', get_defined_vars() + [
    'gbCatListTitle'     => '',
    'gbCatListGridClass' => 'novel-grid',
    'gbCatListPgLabel'   => '小说分类分页',
    'gbCatListCardSlot'  => 'category_card_novel',
]);
