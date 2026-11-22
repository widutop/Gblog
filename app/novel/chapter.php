<?php
require_once __DIR__ . '/../../core/bootstrap.php';

/**
 * 章节页两种入口：
 *   新链接 /novel-{作品id}-{本书内第几章}.html（s3）或 /novel/{作品id}-{本书内第几章}.html（s1/s2）
 *         → ?do=chapter&novel=N&no=M
 *   旧链接 /chapter-{章节全局id}.html、?do=chapter&id=N → ?do=chapter&id=N
 * 一律归一成章节全局 id 再走后续取数，评论 / 上下章 / get_item() 都依赖它。
 */
$id      = (int)($_GET['id'] ?? 0);
$novelId = (int)($_GET['novel'] ?? 0);
$chNo    = (int)($_GET['no'] ?? 0);

$ch           = null;
$curNo        = 0;   // 本章在全书已发布章节中的位置（从 1 起）
$draftPreview = false;
if ($novelId > 0 && $chNo > 0) {
    // 排序口径必须与 gblog_chapter_meta_by_id() 完全一致，否则生成的链接打不开自己。
    // 长篇不能再把整本 id 拉进 PHP：SQL 侧直接 LIMIT 1 OFFSET M-1。
    $st = db()->prepare(
        "SELECT * FROM novel_chapters WHERE novel_id = ? AND status = 'published'
         ORDER BY sort_order ASC, id ASC LIMIT 1 OFFSET " . max(0, $chNo - 1)
    );
    $st->execute([$novelId]);
    $ch = $st->fetch();
    if ($ch) $curNo = $chNo;
} elseif ($id > 0) {
    // 后台「预览」草稿章节：仅管理员可用，普通人拼 preview=1 也看不到未发布内容
    $draftPreview = (string)($_GET['preview'] ?? '') === '1'
        && function_exists('is_admin_logged_in') && is_admin_logged_in();
    $statusSql = $draftPreview ? '' : " AND status = 'published'";
    $st = db()->prepare("SELECT * FROM novel_chapters WHERE id = ?$statusSql LIMIT 1");
    $st->execute([$id]);
    $ch = $st->fetch();
    if ($ch && !$draftPreview) {
        // 旧入口：用 COUNT 算章序，口径与出链层一致（sort_order ASC, id ASC）
        $cnt = db()->prepare(
            "SELECT COUNT(*) FROM novel_chapters WHERE novel_id = ? AND status = 'published'
             AND (sort_order < ? OR (sort_order = ? AND id < ?))"
        );
        $cnt->execute([(int)$ch['novel_id'], (int)$ch['sort_order'], (int)$ch['sort_order'], (int)$ch['id']]);
        $curNo = (int)$cnt->fetchColumn() + 1;
    }
}

if (!$ch) {
    gblog_not_found('章节不存在', '抱歉，该章节不存在或已被删除。');
}
$id      = (int)$ch['id'];
$novelId = (int)$ch['novel_id'];
$_GET['id'] = $id;

$novelStmt = db()->prepare("SELECT p.*, c.name AS category_name, s.name AS site_name, s.slug AS site_slug FROM posts p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN sites s ON s.id = p.site_id WHERE p.id = ? AND p.status = 'published' AND s.enabled = 1 LIMIT 1");
$novelStmt->execute([$ch['novel_id']]);
$novel = $novelStmt->fetch();
if (!$novel) {
    gblog_not_found('作品不存在', '抱歉，该作品不存在或未上架。');
}

// 全书章节数（一次 COUNT）；目录按页切片，避免长篇整本输出到 HTML
$totSt = db()->prepare("SELECT COUNT(*) FROM novel_chapters WHERE novel_id = ? AND status = 'published'");
$totSt->execute([$novelId]);
$chapterTotal = (int)$totSt->fetchColumn();

$chapterSize  = $chapterTotal > 200 ? 100 : max(1, $chapterTotal);
$chapterPages = max(1, (int)ceil($chapterTotal / $chapterSize));
$chapterPage  = (int)($_GET['chpage'] ?? 0);
if ($chapterPage < 1) $chapterPage = max(1, (int)ceil($curNo / $chapterSize)); // 默认定位到当前章所在页
$chapterPage  = min(max(1, $chapterPage), $chapterPages);
$chOff        = ($chapterPage - 1) * $chapterSize;

$allCh = db()->prepare(
    "SELECT id, title FROM novel_chapters WHERE novel_id = ? AND status = 'published'
     ORDER BY sort_order ASC, id ASC LIMIT $chapterSize OFFSET $chOff"
);
$allCh->execute([$novelId]);
$chapters     = $allCh->fetchAll();
$chapterStart = $chOff + 1;
// 草稿预览没有漂亮章序 URL，分页 / 链接一律保持带 preview 的 ?do= 形态
$chapterBase  = $draftPreview
    ? 'index.php?do=chapter&id=' . $id . '&preview=1'
    : gblog_url('chapter', ['id' => $id]);

// 上下章同样走 SQL 偏移，不再依赖整本数组
$curIdx = max(0, $curNo - 1);
$prevCh = null;
$nextCh = null;
if ($curIdx > 0) {
    $p = db()->prepare(
        "SELECT id, title FROM novel_chapters WHERE novel_id = ? AND status = 'published'
         ORDER BY sort_order ASC, id ASC LIMIT 1 OFFSET " . ($curIdx - 1)
    );
    $p->execute([$novelId]);
    $prevCh = $p->fetch() ?: null;
}
if ($curIdx + 1 < $chapterTotal) {
    $n = db()->prepare(
        "SELECT id, title FROM novel_chapters WHERE novel_id = ? AND status = 'published'
         ORDER BY sort_order ASC, id ASC LIMIT 1 OFFSET " . ($curIdx + 1)
    );
    $n->execute([$novelId]);
    $nextCh = $n->fetch() ?: null;
}

if (!$draftPreview) {
    db()->prepare("UPDATE novel_chapters SET view_count = view_count + 1 WHERE id = ?")->execute([$id]);
}

// 章节页评论区：挂在本作品（与简介页同一 target），提交后 PRG 回本页
$__cm         = gblog_handle_comments((int)$novel['id']);
$comment_open = $__cm['open'];
$comment_msg  = $__cm['msg'];
$comment_ok   = $__cm['ok'];
$comments     = $__cm['comments'];
$commentAction = ($draftPreview
    ? gblog_root_absolute('index.php?do=chapter&id=' . $id . '&preview=1')
    : gblog_url('chapter', ['id' => $id])) . '#comments';

$GLOBALS['gblog_no_sidebar'] = true;

$page_title = $ch['title'];
$page_desc = $novel['title'] . ' · ' . $ch['title'];
$bookUrl = 'index.php?do=post&id=' . (int)$novel['id'];
get_header();
if (!render_view('chapter', get_defined_vars())) {
    echo '<section class="rd-page"><div class="card"><p>缺少章节阅读页视图。</p></div></section>';
}
get_footer();
