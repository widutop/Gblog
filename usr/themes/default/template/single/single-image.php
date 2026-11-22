<?php
/**
 * Template Name: 详情页·图片
 * Context: $post, $site, $comments, $adjacent
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
// 正文结构与文章、软件详情页一致，统一走 detail_body 零件拼装
render_slot('detail_body', get_defined_vars());
