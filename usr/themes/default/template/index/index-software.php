<?php
/**
 * Template Name: 聚合首页·软件专题（全站首页专用）
 * Context: $homePosts, $homeTabItems
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
render_slot('post_card_list', get_defined_vars());
