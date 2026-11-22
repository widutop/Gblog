<?php
require_once __DIR__ . '/../../core/bootstrap.php';

set_page_context(gblog_infer_site('home'), 'page');

$page_title   = '关于';
$page_desc    = current_site_name() . '的关于页面';
$page_heading = '关于本站';
$aboutContent = (string)get_setting('about_content', '');
$page_html    = trim($aboutContent) !== '' ? render_markdown($aboutContent) : '';
$page_empty   = '这里是「' . current_site_name() . '」的关于页面。你可以登录后台 →「站点设置」，在「关于页内容」里填写本页内容（支持 Markdown 语法）。';

get_header();
if (!render_view('page', get_defined_vars())) {
    echo '<section class="content"><div class="card"><p>' . e($page_empty) . '</p></div></section>';
}
get_footer();
