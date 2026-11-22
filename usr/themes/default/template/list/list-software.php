<?php
/**
 * Template Name: 分类列表·软件
 * Context: $posts, $cat, $page, $totalPages
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
render_slot('post_card_list', get_defined_vars());
