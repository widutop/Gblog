<?php
require_once __DIR__ . '/../../core/bootstrap.php';

// 文章按全局 id 定位（posts.slug 已移除；旧 ?do=post&slug= 链接不再支持）
$id   = (int)($_GET['id'] ?? 0);
$post = $id > 0 ? get_post_by_id($id, true) : null;

if (!$post) {
    gblog_not_found('文章不存在', '抱歉，文章不存在或已被删除。');
}

increment_view((int)$post['id']);
record_view_history(current_user(), (int)$post['id']);

$__siteSt = db()->prepare('SELECT * FROM sites WHERE id = ? LIMIT 1');
$__siteSt->execute([(int)$post['site_id']]);
$__siteRow = $__siteSt->fetch();
if ($__siteRow) { $GLOBALS['site'] = $__siteRow; }

$adjacent = get_adjacent_posts($post['created_at'], $post['id'], (int)$post['site_id']);

$related = [];
try {
    $st = db()->prepare(
        "SELECT id, title, cover, excerpt, author, view_count, created_at
         FROM posts WHERE site_id = ? AND status='published' AND id <> ?
         ORDER BY created_at DESC, id DESC LIMIT 6"
    );
    $st->execute([(int)$post['site_id'], (int)$post['id']]);
    $related = $st->fetchAll() ?: [];
} catch (Throwable $e) {
    $related = [];
}

$novelChapters     = [];
$novelBook         = [];
$novelChapterTotal = 0;
$chapterPage       = 1;
$chapterPages      = 1;
$chapterSize       = 0;
$chapterStart      = 1;
$chapterBase       = '';
$nbFirst           = null;
$siteSt = db()->prepare('SELECT type FROM sites WHERE id = ?');
$siteSt->execute([$post['site_id']]);
$postSiteType = (string)$siteSt->fetchColumn();
if ($postSiteType === 'novel') {
    $novelId = (int)$post['id'];
    $totSt = db()->prepare("SELECT COUNT(*) FROM novel_chapters WHERE novel_id = ? AND status = 'published'");
    $totSt->execute([$novelId]);
    $novelChapterTotal = (int)$totSt->fetchColumn();

    // 目录按页切片（>200 章才分页），小数据量一页全出、视觉不变
    $chapterSize  = $novelChapterTotal > 200 ? 100 : max(1, $novelChapterTotal);
    $chapterPages = max(1, (int)ceil($novelChapterTotal / $chapterSize));
    $chapterPage  = min(max(1, (int)($_GET['chpage'] ?? 1)), $chapterPages);
    $chOff        = ($chapterPage - 1) * $chapterSize;

    $chSt = db()->prepare(
        "SELECT id, title, sort_order, created_at FROM novel_chapters
         WHERE novel_id = ? AND status = 'published'
         ORDER BY sort_order ASC, id ASC LIMIT $chapterSize OFFSET $chOff"
    );
    $chSt->execute([$novelId]);
    $novelChapters = $chSt->fetchAll();
    $chapterStart  = $chOff + 1;

    // 「开始阅读」「最新章节」必须取全书口径，不受目录分页影响
    $firstSt = db()->prepare(
        "SELECT id, title, sort_order, created_at FROM novel_chapters
         WHERE novel_id = ? AND status = 'published' ORDER BY sort_order ASC, id ASC LIMIT 1"
    );
    $firstSt->execute([$novelId]);
    $nbFirst = $firstSt->fetch() ?: null;

    $latestSt = db()->prepare(
        "SELECT id, title, sort_order, created_at FROM novel_chapters
         WHERE novel_id = ? AND status = 'published' ORDER BY sort_order DESC, id DESC LIMIT 8"
    );
    $latestSt->execute([$novelId]);
    // gblog_novel_book_data() 内部 array_reverse($chapters) 取末尾 8 章，
    // 传「正序的最新 8 章」结果一致，且不必再拉整本。
    $novelBook   = gblog_novel_book_data($novelId, $post, array_reverse($latestSt->fetchAll()));
    $chapterBase = gblog_url('post', ['id' => $novelId]);
    $GLOBALS['gblog_no_sidebar'] = true;
}

$page_title    = $post['title'];
$page_desc     = $post['excerpt'] ?: '';
$page_keywords = $post['title'];

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $post['title'],
    'description' => $page_desc,
    'datePublished' => $post['published_at'] ?? $post['created_at'],
    'dateModified' => $post['updated_at'],
    'author' => ['@type' => 'Person', 'name' => $post['author_name'] ?? '管理员'],
    'publisher' => ['@type' => 'Organization', 'name' => get_setting('site_name', 'Gblog')],
];

$__cm         = gblog_handle_comments((int)$post['id']);
$comment_open = $__cm['open'];
$comment_msg  = $__cm['msg'];
$comment_ok   = $__cm['ok'];
$comments     = $__cm['comments'];

$dlMsg = '';
$dlOk  = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_dl']) && trim((string)($post['download_url'] ?? '')) !== '') {
    if (!csrf_check()) {
        $dlMsg = '请求失效，请刷新。';
    } else {
        $me = current_user();
        if (!$me) {
            $dlMsg = '请先登录。';
        } elseif ((string)$post['download_level'] === 'coin') {
            if (do_coin_download((int)$me['id'], (int)$post['id'], (int)$post['download_price'])) {
                db()->prepare('UPDATE posts SET download_count = download_count + 1 WHERE id = ?')->execute([(int)$post['id']]);
                $dlMsg = '购买成功，点击下方按钮即可下载。';
                $dlOk  = true;
            }
            $dlMsg = $dlMsg ?: '金币不足，无法下载。';
        }
    }
}

get_header();

if (!render_view('detail', get_defined_vars())) {
    echo '<section class="content"><div class="card"><p>缺少默认内容页视图。</p></div></section>';
}

$ad_ab = render_ad('article_bottom');
if ($ad_ab !== ''): ?>
<div class="ad-zone-article"><?= $ad_ab ?></div>
<?php endif; ?>

<?php
if (empty($GLOBALS['gblog_comments_taken'])) render_slot('comments', get_defined_vars());
?>
<?php get_footer(); ?>
