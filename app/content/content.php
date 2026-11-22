<?php
/**
 * .html 伪静态内容统一入口（三种形态共用）：
 *   s1  /post/101.html          s2  /post/20261114101.html          s3  /post-101.html
 * 前缀 = 所属栏目 slug（内置栏目 post/soft/music/image/novel，自建栏目如 ganwu），
 * 仅作可读性与 SEO，不参与归属校验；s2 的「发布日期 8 位 + id」在此剥离日期。
 *
 * 文章与歌曲共用 content_ids 序列，id 全局唯一、永不撞号，按 id 即可唯一定位。
 * 先查文章（含小说章节型内容），再查歌曲；都查不到则 404。
 */
require_once __DIR__ . '/../../core/bootstrap.php';

$id = gblog_parse_content_raw_id($_GET['id'] ?? 0);
if ($id <= 0) {
    gblog_not_found('内容不存在', '缺少内容编号。');
}

// 文章优先
$post = get_post_by_id($id);
if ($post) {
    // 关键：把内部路由归一成真实的 post 控制器（并回填 id）。
    // page_context() / gblog_layout_attr() 直接读 $_GET['do']：若留着 'content'，
    // data-view 会退化成 'page'、data-layout 为空，导致软件信息卡、小说简介、
    // 站点专属版式 CSS（view/{type}/detail.css）全部不加载——表现为「伪静态页格式丢失」。
    $_GET['do'] = 'post';
    $_GET['id'] = $id;
    unset($_GET['site'], $_GET['slug']);
    require __DIR__ . '/post.php';
    return;
}

// 否则按歌曲处理
$songCheck = db()->prepare('SELECT id FROM songs WHERE id = ? LIMIT 1');
$songCheck->execute([$id]);
if ($songCheck->fetch()) {
    $_GET['do'] = 'song';
    $_GET['id'] = $id;
    unset($_GET['site'], $_GET['slug']);
    require __DIR__ . '/../music/song.php';
    return;
}

gblog_not_found('内容不存在', '内容不存在或已被删除。');
