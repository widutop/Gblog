<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/upload_helper.php';

$pdo = db();
$allSites = get_all_sites();
$sites = array_values(array_filter($allSites, function ($s) { return (int)$s['enabled'] === 1; }));
$disabledSiteIds = [];
foreach ($allSites as $s) {
    if ((int)$s['enabled'] !== 1) $disabledSiteIds[(int)$s['id']] = true;
}
$allTags = [];
$id = (int)($_GET['id'] ?? 0);
$post = null;
$postTags = [];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    if (!$post) {
        $id = 0;
    } else {
        // 作者/协作者只能改自己的内容；协作者提交一律存草稿
        $gbRole = (string)($GLOBALS['gblog_admin_role'] ?? 'admin');
        if (in_array($gbRole, ['author', 'contributor'], true)
            && (int)($post['user_id'] ?? 0) !== (int)($_SESSION['admin_id'] ?? 0)) {
            http_response_code(403);
            exit('只能编辑自己发布的文章。');
        }
        $ptStmt = $pdo->prepare("SELECT tag_id FROM post_tags WHERE post_id = ?");
        $ptStmt->execute([$id]);
        $postTags = array_map(function ($r) { return (int)$r['tag_id']; }, $ptStmt->fetchAll());
    }
}

$originalSiteId = $post ? (int)($post['site_id'] ?? 0) : 0;
// 标签按栏目归属：编辑时只列出该栏目与通用（site_id=0）标签，避免跨栏目串用
$__tagSiteId = $originalSiteId > 0 ? $originalSiteId : (int)($sites[0]['id'] ?? 0);
$allTags = $pdo->prepare("SELECT * FROM tags WHERE site_id = 0 OR site_id = ? ORDER BY name ASC");
$allTags->execute([$__tagSiteId]);
$allTags = $allTags->fetchAll();
$originalStatus = $post ? (string)($post['status'] ?? '') : '';
if ($originalSiteId > 0 && isset($disabledSiteIds[$originalSiteId])) {
    foreach ($allSites as $s) {
        if ((int)$s['id'] === $originalSiteId) { $sites[] = $s; break; }
    }
}

$error = '';

$originSite = (int)($_POST['origin_site'] ?? $_GET['site'] ?? 0);
$presetSite = $originSite;
$blockedNew = ($id === 0) && (($originSite > 0 && !gblog_site_is_active($originSite)) || !$sites);
$selSite = (int)($_POST['site_id'] ?? $post['site_id'] ?? 0);
if ($selSite === 0) $selSite = $presetSite;
if ($blockedNew && $sites) $selSite = (int)$sites[0]['id'];
$defaultCatId = 0;
if ($selSite > 0 && (int)($post['site_id'] ?? 0) === 0) {
    $dc = $pdo->query("SELECT id FROM categories WHERE site_id = " . (int)$selSite . " ORDER BY id LIMIT 1")->fetchColumn();
    if ($dc) $defaultCatId = (int)$dc;
}

$__titleVerb = $id > 0 ? '编辑' : '发布';
$__titleType = 'article';
foreach ($allSites as $__s) {
    if ((int)$__s['id'] === $selSite) { $__titleType = (string)($__s['type'] ?? 'article'); break; }
}
$__titleMeta = gblog_site_type_meta($__titleType);
$page_title  = $__titleVerb . $__titleMeta['label'];
$headingText = $page_title . ($id > 0 ? ' #' . $id : '');
require_once __DIR__ . '/../inc/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = '表单校验失败，请重试。';
    } else {
        $siteId    = (int)($_POST['site_id'] ?? 0);
// 音乐栏目走独立的歌曲发布页（songs 表），文章发布页不受理
        $__postSiteType = '';
        foreach ($allSites as $__s) {
            if ((int)$__s['id'] === $siteId) { $__postSiteType = (string)($__s['type'] ?? ''); break; }
        }
        if ($__postSiteType === 'song') {
            header('Location: ' . gblog_admin_url('content/song_edit.php' . ($id > 0 ? '?id=' . $id : '')));
            exit;
        }
// 停用栏目不能新建或迁入内容
        if (!gblog_site_is_active($siteId) && !($id > 0 && $originalSiteId === $siteId)) {
            $error = '该栏目已停用，不能发布或移动内容到停用栏目。';
        }
        $title     = trim((string)($_POST['title'] ?? ''));
        $status    = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
        if ((string)($GLOBALS['gblog_admin_role'] ?? 'admin') === 'contributor') $status = 'draft';
        $excerpt   = trim((string)($_POST['excerpt'] ?? ''));
        $content   = (string)($_POST['content'] ?? '');
        $cover     = trim((string)($_POST['cover'] ?? ''));
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $tagIds    = array_map('intval', (array)($_POST['tags'] ?? []));
// 排序在内容管理列表里直改，本页不再提供：编辑时保留原值，新建为 0
        $sortOrder  = (int)($_POST['sort_order'] ?? ($post['sort_order'] ?? 0));
        $sourceUrl  = trim((string)($_POST['source_url'] ?? ''));
        $author     = trim((string)($_POST['author'] ?? ''));
        $template   = trim((string)($_POST['template'] ?? ''));
        $dlLevel    = gblog_pick($_POST, 'download_level', ['free', 'login', 'coin'], 'free');
        $dlPrice    = max(0, (int)($_POST['download_price'] ?? 0));
        $dlUrl      = trim((string)($_POST['download_url'] ?? ''));
        $fileSize   = mb_substr(trim((string)($_POST['file_size'] ?? '')), 0, 50);
        $swType     = mb_substr(trim((string)($_POST['software_type'] ?? '')), 0, 100);
        $swPlatform = mb_substr(trim((string)($_POST['software_platform'] ?? '')), 0, 100);
        $swVersion  = mb_substr(trim((string)($_POST['software_version'] ?? '')), 0, 60);
        $officialUrl = trim((string)($_POST['official_url'] ?? ''));
        $swLanguage = mb_substr(trim((string)($_POST['language'] ?? '')), 0, 50);
        $rating     = max(0.0, min(5.0, (float)($_POST['rating'] ?? 0)));
        $rating     = round($rating, 1);

        $post = $post ?: [];
        $post['site_id']        = $siteId;
        $post['title']          = $title;
        $post['status']         = $status;
        $post['excerpt']        = $excerpt;
        $post['content']        = $content;
        $post['cover']          = $cover;
        $post['category_id']    = $categoryId;
        $post['author']         = $author;
        $post['source_url']     = $sourceUrl;
        $post['template']       = $template;
        $post['download_level'] = $dlLevel;
        $post['download_price'] = $dlPrice;
        $post['download_url']   = $dlUrl;
        $post['file_size']      = $fileSize;
        $post['software_type']     = $swType;
        $post['software_platform'] = $swPlatform;
        $post['software_version'] = $swVersion;
        $post['official_url']     = $officialUrl;
        $post['language']         = $swLanguage;
        $post['rating']           = $rating;
        $post['sort_order']     = $sortOrder;
        $postTags = $tagIds;

        if ($siteId <= 0) {
            $error = '请选择所属栏目';
        } elseif ($title === '') {
            $error = '请填写文章标题';
        } elseif ($status === 'published' && trim(strip_tags($content)) === '') {
            $error = '请填写正文内容';
        }

        if ($error === '' && $id === 0 && $originSite > 0 && !gblog_site_is_active($originSite)) {
            $error = '该栏目已停用，不能新建内容。';
        }
        if ($error === '' && $id > 0 && $originalSiteId > 0 && !gblog_site_is_active($originalSiteId)
            && $originalStatus !== 'published' && $status === 'published') {
            $error = '该栏目已停用，不能把内容改为发布状态。';
        }

        if ($error === '' && !empty($_FILES['cover_upload']['tmp_name'])) {
            $r = handle_upload($_FILES['cover_upload'], 'image');
            if ($r['ok']) {
                $cover = $r['url'];
            } else {
                $error = '封面上传失败：' . $r['error'];
            }
        }

// 分类必须属于所选栏目
        if ($error === '' && $categoryId > 0) {
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND site_id = ? LIMIT 1");
            $stmt->execute([$categoryId, $siteId]);
            if (!$stmt->fetch()) {
                $categoryId = 0;
            }
        }

        if ($error === '') {
            $categoryId = $categoryId ?: null;

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    "UPDATE posts SET site_id=?, title=?, content=?, excerpt=?, cover=?, status=?, category_id=?,
                            sort_order=?, source_url=?, author=?, template=?, download_level=?, download_price=?, download_url=?, file_size=?, software_type=?, software_platform=?, software_version=?, official_url=?, language=?, rating=?, updated_at=CURRENT_TIMESTAMP
                     WHERE id=?"
                );
                $stmt->execute([$siteId, $title, $content, $excerpt ?: null, $cover ?: null, $status, $categoryId,
                    $sortOrder, $sourceUrl ?: null, $author ?: null, $template ?: null, $dlLevel, $dlPrice, $dlUrl ?: null, $fileSize ?: null,
                    $swType ?: null, $swPlatform ?: null, $swVersion ?: null, $officialUrl ?: null, $swLanguage ?: null, $rating, $id]);
            } else {
                $cid = gblog_next_content_id();
                $stmt = $pdo->prepare(
                    "INSERT INTO posts (id, site_id, title, content, excerpt, cover, status, category_id,
                            sort_order, source_url, author, template, download_level, download_price, download_url, file_size, software_type, software_platform, software_version, official_url, language, rating, user_id, updated_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)"
                );
                $stmt->execute([$cid, $siteId, $title, $content, $excerpt ?: null, $cover ?: null, $status, $categoryId,
                    $sortOrder, $sourceUrl ?: null, $author ?: null, $template ?: null, $dlLevel, $dlPrice, $dlUrl ?: null, $fileSize ?: null,
                    $swType ?: null, $swPlatform ?: null, $swVersion ?: null, $officialUrl ?: null, $swLanguage ?: null, $rating,
                    (int)($_SESSION['admin_id'] ?? 0)]);
                $id = $cid;
            }

            $pdo->prepare("DELETE FROM post_tags WHERE post_id = ?")->execute([$id]);
            if ($tagIds) {
                $tagStmt = $pdo->prepare(gblog_insert_ignore() . " post_tags (post_id, tag_id) VALUES (?,?)");
                foreach ($tagIds as $tagId) {
                    if ($tagId > 0) {
                        $tagStmt->execute([$id, $tagId]);
                    }
                }
            }

            header('Location: ' . gblog_admin_url('index.php?tab=post'));
            exit;
        }
    }
}

$v = function (string $key, string $default = '') use ($post) {
    return $post ? (string)($post[$key] ?? $default) : $default;
};
$gbRawContent = $v('content');
$gbAutoMode = (trim($gbRawContent) !== '' && !preg_match('/<(p|div|h[1-6]|ul|ol|li|blockquote|pre|img|video|iframe|figure|table|section|article)\b/i', $gbRawContent)) ? 'markdown' : 'rich';
$gbMode = gblog_pick($_POST, 'gb_editor_mode', ['rich', 'markdown'], $gbAutoMode);
?>

<?php if ($error !== ''): ?><div class="alert alert-error"><?= e($error) ?></div>
<?php elseif ($blockedNew): ?><div class="alert alert-error">该栏目已停用或已删除，不能新建内容；请先在栏目管理启用对应栏目。</div><?php endif; ?>

<div class="card">
  <form method="post" action="<?= gblog_admin_url('content/post_edit.php' . ($id > 0 ? '?id=' . $id : '')) ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="origin_site" value="<?= $originSite ?>">

    <div class="form-grid">
      <div class="form-row">
      <div class="form-field">
        <label>所属栏目 *</label>
        <select name="site_id" id="site-select" onchange="toggleDownloadFields(true)">
          <?php foreach ($sites as $s): ?>
            <?php $__optMeta = gblog_site_type_meta((string)($s['type'] ?? 'article')); ?>
            <option value="<?= (int)$s['id'] ?>" data-type="<?= e($s['type'] ?? 'article') ?>" data-label="<?= e($__optMeta['label']) ?>" <?= ($selSite === (int)$s['id']) ? 'selected' : '' ?>><?= e($s['name']) ?><?= (int)$s['enabled'] === 1 ? '' : '（已停用）' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label>分类</label>
        <select name="category_id">
          <option value="0">无分类</option>
          <?php
            $catStmt = $pdo->query("SELECT * FROM categories ORDER BY site_id, id");
            foreach ($catStmt->fetchAll() as $c) {
                $siteName = '';
                foreach ($sites as $s) { if ((int)$s['id'] === (int)$c['site_id']) { $siteName = $s['name']; break; } }
          ?>
            <option value="<?= (int)$c['id'] ?>" <?= (((int)($post['category_id'] ?? 0) === (int)$c['id']) || ((int)($post['category_id'] ?? 0) === 0 && $defaultCatId === (int)$c['id'])) ? 'selected' : '' ?>><?= e($siteName) ?> / <?= e($c['name']) ?></option>
          <?php } ?>
        </select>
      </div>
      <div class="form-field">
        <label>状态</label>
        <select name="status">
          <option value="published" <?= $v('status', 'published') === 'published' ? 'selected' : '' ?>>发布</option>
          <option value="draft" <?= $v('status') === 'draft' ? 'selected' : '' ?>>草稿</option>
        </select>
      </div>
      <div class="form-field">
        <label>文章来源</label>
        <input type="text" name="source_url" value="<?= e($v('source_url')) ?>" placeholder="https:// 原文链接">
      </div>
      </div>
      <div class="form-row">
      <div class="form-field">
        <label>标题 *</label>
        <input type="text" name="title" value="<?= e($v('title')) ?>" required>
      </div>
      <div class="form-field">
        <label>作者</label>
        <input type="text" name="author" value="<?= e($v('author')) ?>" placeholder="作者名">
      </div>
      <div class="form-field">
        <label>封面图 URL</label>
        <input type="text" name="cover" value="<?= e($v('cover')) ?>" placeholder="https:// 或留空">
      </div>
      <div class="form-field">
        <label>上传封面图</label>
        <input type="file" name="cover_upload" accept=".jpg,.jpeg,.png,.webp,.gif">
      </div>
      </div>

      <div class="form-row">
      <div id="download-fields" style="display:none;flex:1 1 100%;">
      <div class="form-field">
        <label>下载门槛</label>
        <select name="download_level" id="dl-level" onchange="document.getElementById('dl-price-wrap').style.display=(this.value==='coin')?'':'none';">
          <option value="free" <?= $v('download_level','free')==='free'?'selected':'' ?>>免费</option>
          <option value="login" <?= $v('download_level')==='login'?'selected':'' ?>>登录后下载</option>
          <option value="coin" <?= $v('download_level')==='coin'?'selected':'' ?>>金币下载</option>
        </select>
      </div>
      <div class="form-field" id="dl-price-wrap" style="<?= $v('download_level')==='coin'?'':'display:none;' ?>">
        <label>金币数</label>
        <input type="number" name="download_price" value="<?= e($v('download_price','0')) ?>" min="0">
      </div>
      <div class="form-field">
        <label>下载地址</label>
        <input type="text" name="download_url" value="<?= e($v('download_url')) ?>" placeholder="https:// 或上传文件">
      </div>
      <div class="form-field">
        <label>软件大小</label>
        <input type="text" name="file_size" value="<?= e($v('file_size')) ?>" placeholder="如 12.4 MB" maxlength="50">
      </div>
      <div class="form-field">
        <label>软件类型（自由文本）</label>
        <input type="text" name="software_type" value="<?= e($v('software_type')) ?>" placeholder="如 PHP 源码 / EXE 安装版 / 绿色软件 / 安卓 APP" maxlength="100">
      </div>
      <div class="form-field">
        <label>运行平台（自由文本）</label>
        <input type="text" name="software_platform" value="<?= e($v('software_platform')) ?>" placeholder="如 Windows 10/11 64位 / macOS / 跨平台 / 需 PHP 环境" maxlength="100">
      </div>
      <div class="form-field">
        <label>软件版本</label>
        <input type="text" name="software_version" value="<?= e($v('software_version')) ?>" placeholder="如 V4.2.1（版本号，平台填上面）" maxlength="60">
      </div>
      <div class="form-field">
        <label>官方网站</label>
        <input type="text" name="official_url" value="<?= e($v('official_url')) ?>" placeholder="https://" maxlength="1000">
      </div>
      <div class="form-field">
        <label>软件语言</label>
        <input type="text" name="language" value="<?= e($v('language')) ?>" placeholder="如 中文 / English" maxlength="50">
      </div>
      <div class="form-field">
        <label>推荐星级（0–5，可含 0.5）</label>
        <input type="number" name="rating" value="<?= e($v('rating','0')) ?>" min="0" max="5" step="0.5">
      </div>
      </div><!-- /#download-fields -->
      </div><!-- /.form-row -->

      <div class="form-field full">
        <label>正文</label>
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:8px;">
          <label style="font-weight:normal;"><input type="radio" name="gb_editor_mode" value="rich" <?= $gbMode === 'rich' ? 'checked' : '' ?>> 富文本（所见即所得）</label>
          <label style="font-weight:normal;"><input type="radio" name="gb_editor_mode" value="markdown" <?= $gbMode === 'markdown' ? 'checked' : '' ?>> Markdown（纯文本）</label>
          <button type="button" class="btn btn-sm" id="gb-md-preview-btn" <?= $gbMode === 'markdown' ? '' : 'style="display:none;"' ?>>预览 Markdown</button>
          <span class="hint" id="gb-md-hint" <?= $gbMode === 'markdown' ? '' : 'style="display:none;"' ?>>支持 # 标题、**粗体**、*斜体*、``代码``、```代码块```、[链接](https://...)、![](图片地址)、- 列表、@video 视频地址</span>
        </div>
        <div class="wangeditor-wrap" id="gb-rich-wrap" <?= $gbMode === 'markdown' ? 'style="display:none;"' : '' ?>>
          <div id="editor-toolbar"></div>
          <div id="editor-box" style="height:480px; overflow-y:hidden;"></div>
        </div>
        <div id="gb-md-wrap" <?= $gbMode === 'markdown' ? '' : 'style="display:none;"' ?>>
          <textarea id="content-md" rows="20" style="width:100%;font-family:Consolas,Monaco,monospace;line-height:1.6;" placeholder="用 Markdown 写作，例如：## 标题&#10;&#10;正文段落..."><?= e($gbMode === 'markdown' ? $gbRawContent : '') ?></textarea>
          <div id="gb-md-preview" class="post-content" style="display:none;border:1px solid #e2e8f0;border-top:none;padding:14px;min-height:120px;background:#fff;"></div>
        </div>
        <textarea name="content" id="content" style="display:none;"><?= e($v('content')) ?></textarea>
        <span class="hint">正文始终以 HTML 存储；Markdown 内容在前台自动渲染。切换模式不会转换已有内容。</span>
      </div>

      <div class="form-field full">
        <label>摘要（列表页显示，留空则自动截取）</label>
        <textarea name="excerpt" rows="2"><?= e($v('excerpt')) ?></textarea>
      </div>

      <div class="form-field full">
        <label>标签（可多选）</label>
        <div>
          <?php foreach ($allTags as $t): ?>
            <label style="display:inline-block;margin-right:12px;font-weight:normal;">
              <input type="checkbox" name="tags[]" value="<?= (int)$t['id'] ?>" <?= in_array((int)$t['id'], $postTags, true) ? 'checked' : '' ?>> <?= e($t['name']) ?>
            </label>
          <?php endforeach; ?>
          <?php if (!$allTags): ?><span class="hint">暂无标签，可到「标签管理」添加。</span><?php endif; ?>
        </div>
      </div>
    </div><!-- /form-grid -->

    <div style="display:flex;gap:10px;">
      <button type="submit" class="btn btn-primary" <?= $blockedNew ? 'disabled' : '' ?>>保存文章</button>
      <a class="btn" href="<?= gblog_admin_url('index.php?tab=post') ?>">取消</a>
    </div>
  </form>
</div>

<link href="https://unpkg.com/@wangeditor/editor@5.1.23/dist/css/style.css" rel="stylesheet">
<script src="https://unpkg.com/@wangeditor/editor@5.1.23/dist/index.js"></script>
<style>
  .w-e-toolbar { background: #f8fafc !important; border-bottom: 1px solid #e2e8f0; }
  .w-e-toolbar .w-e-toolbar-item:hover { color: #2563eb !important; }
  .w-e-toolbar .w-e-toolbar-item[class*="active"] { color: #1d4ed8 !important; background: #dbeafe !important; border-radius: 4px; }
  .w-e-text-container { border: 1px solid #e2e8f0; border-top: none; }
  .w-e-full-screen-container .w-e-toolbar { position: sticky; top: 0; z-index: 100; }
  .w-e-full-screen-container { z-index: 9999; }
</style>
<script src="https://cdn.jsdelivr.net/npm/turndown@7.2.0/dist/turndown.js"></script>
<script>
var gbEditor = null;
var gbInitialMode = '<?= e($gbMode) ?>';
function gbInitEditor() {
    if (gbEditor || !window.wangEditor) return false;
    var E = window.wangEditor;
    gbEditor = E.createEditor({
        selector: '#editor-box',
        html: document.getElementById('content').value || '<p><br></p>',
        config: {
            placeholder: '请输入内容',
            onChange: function (ed) { document.getElementById('content').value = ed.getHtml(); }
        },
        mode: 'default'
    });
    E.createToolbar({ editor: gbEditor, selector: '#editor-toolbar', mode: 'default' });
    return true;
}
(function () {
    if (gbInitialMode !== 'rich') return;
    if (!gbInitEditor()) {
        var richRadio = document.querySelector('input[name="gb_editor_mode"][value="markdown"]');
        if (richRadio) richRadio.checked = true;
        var hiddenText = document.getElementById('content').value;
        document.getElementById('gb-rich-wrap').style.display = 'none';
        document.getElementById('gb-md-wrap').style.display = '';
        document.getElementById('gb-md-preview-btn').style.display = '';
        document.getElementById('gb-md-hint').style.display = '';
        document.getElementById('content-md').value = window.TurndownService ? new TurndownService().turndown(hiddenText) : hiddenText;
    }
})();

(function () {
    var hidden = document.getElementById('content');
    var mdTa = document.getElementById('content-md');
    var richWrap = document.getElementById('gb-rich-wrap');
    var mdWrap = document.getElementById('gb-md-wrap');
    var prevBtn = document.getElementById('gb-md-preview-btn');
    var hint = document.getElementById('gb-md-hint');
    var preview = document.getElementById('gb-md-preview');
    var radios = document.querySelectorAll('input[name="gb_editor_mode"]');
    var form = hidden.closest('form');
    var previewUrl = '<?= gblog_admin_url('content/content_preview.php') ?>';
    var csrf = document.querySelector('input[name="csrf_token"]').value;

    function renderViaServer(text) {
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('content', text);
        return fetch(previewUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.text(); });
    }
    function setModeUI(mode) {
        var rich = mode === 'rich';
        richWrap.style.display = rich ? '' : 'none';
        mdWrap.style.display = rich ? 'none' : '';
        prevBtn.style.display = rich ? 'none' : '';
        hint.style.display = rich ? 'none' : '';
        if (!rich) preview.style.display = 'none';
    }
    function switchMode(mode) {
        if (mode === 'markdown') {
            var html = gbEditor ? gbEditor.getHtml() : hidden.value;
            mdTa.value = window.TurndownService ? new TurndownService().turndown(html) : html;
            hidden.value = mdTa.value;
            setModeUI(false);
        } else {
            if (!gbInitEditor()) { alert('编辑器未能加载，请检查网络能否访问 unpkg.com。'); return; }
            renderViaServer(mdTa.value).then(function (html) {
                gbEditor.setHtml(html);
                hidden.value = html;
                setModeUI(true);
            }).catch(function () { alert('内容转换失败'); });
        }
    }
    Array.prototype.forEach.call(radios, function (r) {
        r.addEventListener('change', function () { if (r.checked) switchMode(r.value); });
    });
    if (form) form.addEventListener('submit', function () {
        var mode = document.querySelector('input[name="gb_editor_mode"]:checked').value;
        hidden.value = (mode === 'rich' && gbEditor) ? gbEditor.getHtml() : mdTa.value;
    });
    prevBtn.addEventListener('click', function () {
        renderViaServer(mdTa.value).then(function (html) {
            preview.innerHTML = html;
            preview.style.display = 'block';
        }).catch(function () { alert('预览失败'); });
    });
})();

var gbLastSite = null;
function toggleDownloadFields(fromChange) {
    var sel = document.getElementById('site-select');
    var opt = sel.options[sel.selectedIndex];
    var type = opt.getAttribute('data-type') || 'article';
    if (type === 'song') {
        if (fromChange && gbLastSite !== null
            && !confirm('音乐栏目使用独立的音乐发布页。是否跳转？未保存的内容会丢失。')) {
            sel.value = gbLastSite;
            opt = sel.options[sel.selectedIndex];
            type = opt.getAttribute('data-type') || 'article';
        } else {
            location.href = '<?= gblog_admin_url('content/song_edit.php') ?>';
            return;
        }
    }
    document.getElementById('download-fields').style.display = (type === 'software') ? '' : 'none';
    var crumb = document.querySelector('.admin-breadcrumb');
    if (crumb) crumb.textContent = '<?= e($__titleVerb) ?>' + (opt.getAttribute('data-label') || '文章');
    gbLastSite = sel.value;
}
toggleDownloadFields(false);
</script>

<?php require __DIR__ . '/../inc/footer.php'; ?>
