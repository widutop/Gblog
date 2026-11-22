<?php
/**
 * Template Name: 详情页·软件
 * Context: $post, $site, $comments, $adjacent
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
// 与文章/图片详情页共用 detail_body 零件；软件页差异：
//   1) 软件信息卡独立在 <article> 之上（不与正文混排）
//   2) 标题由信息卡自身输出（do_action detail_title），不再单独渲染 detail_title 零件
//   3) 不渲染 detail_head 下载卡（下载入口已在软件信息卡内，避免重复）
render_slot('detail_body', get_defined_vars() + [
    'gbDetailPreHead'   => true,
    'gbDetailTitleSlot' => 'action',
    'gbDetailHasHead'   => false,
]);
