<?php
/**
 * Template Name: 详情页·小说/章节
 * Context: $post, $novelBook, $novelChapters, $comments, $related
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
render_slot('book_intro', get_defined_vars());
