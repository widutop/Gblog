<?php
require_once __DIR__ . '/core/bootstrap.php';

$do = $_GET['do'] ?? '';

// 重复 URL 规范化（前导零章序 / 旧 chapter-N.html → 301），只作用于 GET 的漂亮页面
gblog_redirect_noncanonical();

// 伪静态形态：非 query 形态时对 HTML 页面挂输出缓冲，统一改写老模板里
// 残留的 index.php?do= 内容链接 / 表单 action（AJAX、下载、sitemap/robots 不参与）。
gblog_maybe_start_href_rewrite((string)$do);

$routeMap = [
    'site'      => __DIR__ . '/app/content/site.php',
    'post'      => __DIR__ . '/app/content/post.php',
    'category'  => __DIR__ . '/app/content/category.php',
    'tag'       => __DIR__ . '/app/content/tag.php',
    'search'    => __DIR__ . '/app/content/search.php',
    'archive'   => __DIR__ . '/app/content/archive.php',
    'about'     => __DIR__ . '/app/content/about.php',
    'links'     => __DIR__ . '/app/content/links.php',
    'sitemap'   => __DIR__ . '/app/content/sitemap.php',
    'robots'    => __DIR__ . '/app/content/robots.php',
    'home'      => __DIR__ . '/app/content/home.php',
    'songs'         => __DIR__ . '/app/music/songs.php',
    'song'          => __DIR__ . '/app/music/song.php',
    'song_lyrics'   => __DIR__ . '/app/music/song_lyrics.php',
    'song_play'     => __DIR__ . '/app/music/song_play.php',
    'song_download' => __DIR__ . '/app/music/song_download.php',
    'random_song'   => __DIR__ . '/app/music/random_song.php',
    'recommend_batch' => __DIR__ . '/app/music/recommend_batch.php',
    'chapter'   => __DIR__ . '/app/novel/chapter.php',
    'content'   => __DIR__ . '/app/content/content.php',
    'login'     => __DIR__ . '/app/user/login.php',
    'register'  => __DIR__ . '/app/user/register.php',
    'logout'    => __DIR__ . '/app/user/logout.php',
    'forgot'    => __DIR__ . '/app/user/forgot.php',
    'reset'     => __DIR__ . '/app/user/reset.php',
    'profile'   => __DIR__ . '/app/user/profile.php',
    'like'      => __DIR__ . '/app/user/like.php',
    'download'  => __DIR__ . '/app/content/download.php',
];

$routeMap = apply_filters('route', $routeMap);

if (array_key_exists($do, $routeMap)) {
    require $routeMap[$do];
} else {
    require __DIR__ . '/app/content/agg_home.php';
}
