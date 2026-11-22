<?php
/**
 * Template Name: 栏目首页（列表）
 * Context: $posts, $site, $page, $totalPages, $gbTabItems
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
render_slot('post_card_list', get_defined_vars());
