<?php
/**
 * Template Name: 聚合首页（全站首页专用）
 * Context: $homePosts, $homeTabItems, $homeHeaderModules, $page, $totalPages
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$__gb_agg_layout = gblog_theme_part('template/parts/agg_layout.php');
if ($__gb_agg_layout !== '') require $__gb_agg_layout;
