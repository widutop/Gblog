<?php
require_once __DIR__ . '/../../core/bootstrap.php';

set_page_context(gblog_infer_site('home'), 'page');

$page_title = '友情链接';
$page_desc  = '本站友情链接';
$links      = get_links();
$linkGroups = get_link_groups();

get_header();
if (!render_view('links', get_defined_vars())) {
    echo '<section class="content"><div class="card"><p class="empty">暂无友情链接。</p></div></section>';
}
get_footer();
