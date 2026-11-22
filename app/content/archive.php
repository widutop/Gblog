<?php
require_once __DIR__ . '/../../core/bootstrap.php';

set_page_context(gblog_infer_site('home'), 'page');

$year     = (int)($_GET['y'] ?? 0);
$month    = (int)($_GET['m'] ?? 0);
$archives = get_archives();
$posts    = null;

$page_title = '文章归档';
$page_desc  = current_site_name() . '的文章归档';

if ($year > 0 && $month >= 1 && $month <= 12) {
    $posts      = get_posts_by_month($year, $month);
    $page_title = sprintf('%04d年%02d月 的文章', $year, $month);
}

get_header();
if (!render_view('archive', get_defined_vars())) {
    echo '<section class="content"><div class="card"><p class="empty">还没有文章。</p></div></section>';
}
get_footer();
