<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/upload_helper.php';

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$error = '';
$song = [
    'id' => 0, 'post_id' => 0, 'title' => '', 'artist' => '',
    'cover_url' => '', 'audio_url' => '', 'download_url' => '',
    'download_level' => 'free', 'download_price' => 0, 'lyrics' => '', 'note' => '', 'sort_order' => 0,
    'status' => 'published', 'category_id' => 0,
];

if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM songs WHERE id=?");
    $st->execute([$id]);
    $existing = $st->fetch();
    if ($existing) {
        $song = array_merge($song, $existing);
        // 口径同 post_edit.php：作者/协作者只能改自己的内容，协作者只能存草稿
        $gbRole = (string)($GLOBALS['gblog_admin_role'] ?? 'admin');
        if (in_array($gbRole, ['author', 'contributor'], true)
            && (int)($song['user_id'] ?? 0) !== (int)($_SESSION['admin_id'] ?? 0)) {
            http_response_code(403);
            exit('只能编辑自己发布的歌曲。');
        }
    }
}

if ($id === 0) {
    $song['note'] = get_setting('last_song_note', '');
}

$musicSite = $pdo->query("SELECT id, name FROM sites WHERE slug = 'music' LIMIT 1")->fetch();
$musicSiteId = $musicSite ? (int)$musicSite['id'] : 0;
if ($musicSiteId) {
    $cs = $pdo->prepare("SELECT * FROM categories WHERE site_id = ? ORDER BY id");
    $cs->execute([$musicSiteId]);
    $catList = $cs->fetchAll();
} else {
    $catList = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = '表单校验失败，请重试。';
    }
    if ($error === '') {
    $title    = trim((string)($_POST['title'] ?? ''));
    $artist   = trim((string)($_POST['artist'] ?? ''));
    $lyricist = trim((string)($_POST['lyricist'] ?? ''));
    $postId   = isset($_POST['post_id']) ? ((int)$_POST['post_id'] > 0 ? (int)$_POST['post_id'] : null) : ($id > 0 ? ($song['post_id'] ?: null) : null);
    $coverUrl = trim((string)($_POST['cover_url'] ?? ''));
    $audioUrl = trim((string)($_POST['audio_url'] ?? ''));
    $dlUrl    = trim((string)($_POST['download_url'] ?? ''));
    $dlLevel  = gblog_pick($_POST, 'download_level', ['free','login','coin'], 'free');
    $dlPrice  = max(0, (int)($_POST['download_price'] ?? 0));
    $lyrics   = trim((string)($_POST['lyrics'] ?? ''));
    $note     = trim((string)($_POST['note'] ?? ''));
    if ($note !== '') save_setting('last_song_note', $note);
    $sortOrder= array_key_exists('sort_order', $_POST) ? (int)$_POST['sort_order'] : (int)($song['sort_order'] ?? 0);
    $status   = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
    if ((string)($GLOBALS['gblog_admin_role'] ?? 'admin') === 'contributor') $status = 'draft';
    $catId    = (int)($_POST['category_id'] ?? 0);
// 分类必须属于音乐栏目，防止手工提交串到别的栏目
    if ($catId > 0) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = ? AND site_id = ?");
        $chk->execute([$catId, $musicSiteId]);
        if (!$chk->fetchColumn()) $catId = 0;
    }

    $song['title']          = $title;
    $song['artist']         = $artist;
    $song['lyricist']       = $lyricist;
    $song['cover_url']      = $coverUrl;
    $song['audio_url']      = $audioUrl;
    $song['download_url']   = $dlUrl;
    $song['lyrics']         = $lyrics;
    $song['note']           = $note;
    $song['sort_order']     = $sortOrder;
    $song['status']         = $status;
    $song['download_level'] = $dlLevel;
    $song['download_price'] = $dlPrice;
    $song['category_id']    = $catId;

    $hasAudio = $audioUrl !== '' || !empty($_FILES['audio_upload']['tmp_name']) || (!empty($song['audio_url']) && $id > 0);
    if ($title === '') {
        $error = '请填写歌曲名';
    } elseif ($id === 0 && !gblog_music_site_active()) {
        $error = '音乐栏目已停用，不能发布新歌曲。';
    } elseif ($status === 'published' && $lyrics === '') {
        $error = '请填写歌词内容';
    } elseif ($status === 'published' && !$hasAudio) {
        $error = '发布歌曲需要音频：请上传音频文件，或填写「音乐外链 URL（播放地址）」';
    }

    if ($error === '') {
        if (!empty($_FILES['cover_upload']['tmp_name'])) {
            $r = handle_upload($_FILES['cover_upload'], 'image');
            if ($r['ok']) $coverUrl = $r['url'];
            else $error = '封面上传：' . $r['error'];
        }
        if ($error === '' && !empty($_FILES['audio_upload']['tmp_name'])) {
            $r = handle_upload($_FILES['audio_upload'], 'audio');
            if ($r['ok']) $audioUrl = $r['url'];
            else $error = '音频上传：' . $r['error'];
        }

        if ($error === '') {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE songs SET post_id=?, title=?, artist=?, lyricist=?, cover_url=?, audio_url=?, download_url=?, download_level=?, download_price=?, lyrics=?, note=?, sort_order=?, status=?, category_id=?, site_id=? WHERE id=?");
                $stmt->execute([$postId, $title, $artist, $lyricist ?: null, $coverUrl ?: null, $audioUrl ?: null, $dlUrl ?: null, $dlLevel, $dlPrice, $lyrics ?: null, $note ?: null, $sortOrder, $status, $catId, $musicSiteId, $id]);
                $newId = $id;
            } else {
                $cid  = gblog_next_content_id();
                $stmt = $pdo->prepare("INSERT INTO songs (id, post_id, title, artist, lyricist, cover_url, audio_url, download_url, download_level, download_price, lyrics, note, sort_order, status, category_id, site_id, user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$cid, $postId, $title, $artist, $lyricist ?: null, $coverUrl ?: null, $audioUrl ?: null, $dlUrl ?: null, $dlLevel, $dlPrice, $lyrics ?: null, $note ?: null, $sortOrder, $status, $catId, $musicSiteId, (int)($_SESSION['admin_id'] ?? 1)]);
                $newId = $cid;
            }
            if (!empty($_POST['open_maker'])) {
                header('Location: ' . gblog_admin_url('content/song_edit.php?id=' . (int)$newId . '&open_maker=1'));
            } else {
                header('Location: ' . gblog_admin_url('index.php?tab=song'));
            }
            exit;
        }
    }
    }
}

$page_title = $id > 0 ? '编辑歌曲' : '发布歌曲';
require __DIR__ . '/../inc/header.php';
function v($k, $d = '') { global $song; return e($song[$k] ?? $d); }
?>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <form method="post" action="<?= gblog_admin_url('content/song_edit.php' . ($id > 0 ? '?id=' . $id : '')) ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="open_maker" id="open_maker" value="0">

    <div class="form-grid">
      <div class="form-row">
        <div class="form-field">
          <label>所属栏目</label>
          <select name="site_id">
            <option value="<?= $musicSiteId ?>" selected><?= e($musicSite['name'] ?? '音乐') ?></option>
          </select>
        </div>
        <div class="form-field">
          <label>分类</label>
          <select name="category_id">
            <?php
              $selCat = (int)$song['category_id'];
              if ($id === 0 && $selCat === 0 && $catList) $selCat = (int)$catList[0]['id'];
              $validCatIds = array_map('intval', array_column($catList, 'id'));
              if ($selCat > 0 && !in_array($selCat, $validCatIds, true)):
            ?>
              <option value="<?= $selCat ?>" selected disabled>（原分类不属于音乐栏目，请重新选择）</option>
            <?php endif; ?>
            <option value="0" <?= $selCat === 0 ? 'selected' : '' ?>>无分类</option>
            <?php foreach ($catList as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $selCat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label>状态</label>
          <select name="status">
            <option value="published" <?= $song['status'] === 'published' ? 'selected' : '' ?>>发布</option>
            <option value="draft" <?= $song['status'] === 'draft' ? 'selected' : '' ?>>草稿</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-field">
          <label>歌曲名 *</label>
          <input type="text" name="title" value="<?= v('title') ?>" required>
        </div>
        <div class="form-field">
          <label>作者</label>
          <input type="text" name="artist" value="<?= v('artist') ?>" placeholder="作者名">
        </div>
        <div class="form-field">
          <label>编曲</label>
          <input type="text" name="lyricist" value="<?= v('lyricist') ?>" placeholder="编曲人">
        </div>
        <div class="form-field">
          <label>上传音频文件</label>
          <input type="file" name="audio_upload" accept=".mp3,.m4a,.ogg,.wav,.flac">
        </div>
        <div class="form-field">
          <label>音乐外链 URL（播放地址）</label>
          <input type="text" name="audio_url" value="<?= v('audio_url') ?>" placeholder="https:// 音频直链">
        </div>
      </div>

      <div class="form-row">
        <div class="form-field">
          <label>下载方式</label>
          <select name="download_level" id="dl-level" onchange="document.getElementById('dl-price-wrap').style.display=(this.value==='coin')?'':'none';">
            <option value="free" <?= $song['download_level']==='free'?'selected':'' ?>>免费</option>
            <option value="login" <?= $song['download_level']==='login'?'selected':'' ?>>登录后下载</option>
            <option value="coin" <?= $song['download_level']==='coin'?'selected':'' ?>>金币下载</option>
          </select>
        </div>
        <div class="form-field shrink" id="dl-price-wrap" style="<?= $song['download_level']==='coin'?'':'display:none;' ?>">
          <label>金币数</label>
          <input type="number" name="download_price" value="<?= (int)$song['download_price'] ?>" min="0">
        </div>
        <div class="form-field">
          <label>封面图 URL</label>
          <input type="text" name="cover_url" value="<?= v('cover_url') ?>" placeholder="https:// 或留空">
        </div>
        <div class="form-field">
          <label>上传封面图</label>
          <input type="file" name="cover_upload" accept=".jpg,.jpeg,.png,.webp,.gif">
        </div>
        <div class="form-field">
          <label>下载地址 URL（留空则用播放地址）</label>
          <input type="text" name="download_url" value="<?= v('download_url') ?>" placeholder="https:// 留空=同播放地址">
        </div>
      </div>

      <div class="form-field full">
        <label>歌词（LRC 或纯文本，一行一句）</label>
        <textarea name="lyrics" id="lyrics" rows="20"><?= v('lyrics') ?></textarea>

        <div id="lyric-maker" style="display:none;border:1px solid #cfd6e0;border-radius:8px;padding:12px;background:#fafbfc;min-height:420px;flex-direction:column;">
          <div id="lm-tip" style="display:none;margin-bottom:6px;padding:6px 10px;background:#fff3f3;border:1px solid #ffd0d0;border-radius:6px;color:#c0392b;font-size:12px;"></div>

          <audio id="lm-audio" controls preload="metadata" style="width:100%;margin-bottom:8px;"></audio>

          <div id="lm-past" style="padding:6px 10px;background:#fff;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:6px;color:#9ca3af;font-size:12px;line-height:1.7;min-height:62px;"></div>

          <div id="lm-current" style="padding:12px 10px;background:#eef4ff;border:1px solid #bfdbfe;border-radius:6px;margin-bottom:6px;font-size:17px;font-weight:600;color:#1e40af;text-align:center;min-height:46px;display:flex;align-items:center;justify-content:center;"></div>

          <div id="lm-next" style="padding:6px 10px;background:#fff;border:1px dashed #d1d5db;border-radius:6px;margin-bottom:10px;color:#9ca3af;font-size:12px;line-height:1.7;min-height:62px;"></div>

          <div style="margin-top:auto;display:flex;align-items:center;gap:10px;">
            <button type="button" class="btn btn-primary btn-sm" id="lm-save">保存歌词</button>
            <button type="button" class="btn btn-primary" id="lm-mark" style="flex:1;padding:10px;font-size:15px;">⏱ 唱完这句</button>
            <button type="button" class="btn btn-sm" id="lm-reset">重新打点</button>
            <button type="button" class="btn btn-sm" id="lm-close">关闭</button>
          </div>
          <div style="margin-top:4px;font-size:12px;color:#6b7280;text-align:right;" id="lm-progress"></div>
        </div>

        <div id="lyrics-toolbar" style="margin-top:8px;">
          <button type="button" class="btn btn-sm" id="open-lyric-maker">⏱ 制作歌词（打点同步）</button>
          <span class="hint" style="margin-left:8px;">先粘贴纯文本歌词，点这里逐句打点生成时间轴</span>
        </div>
      </div>

      <div class="form-field full">
        <label>创作说明 / 版权声明（将显示在歌曲页末尾）</label>
        <textarea name="note" rows="3" placeholder="例：歌词本人原创，AI 辅助作曲/编曲，仅供个人欣赏，非商业使用。"><?= v('note') ?></textarea>
      </div>
    </div><!-- /form-grid -->

    <div style="display:flex;gap:10px;margin-top:5px;">
      <button type="submit" class="btn btn-primary">发布歌曲</button>
      <a class="btn" href="<?= gblog_admin_url('index.php?tab=song') ?>">取消</a>
    </div>
  </form>
</div>

<script>
(function () {
  var btn = document.getElementById('open-lyric-maker');
  var panel = document.getElementById('lyric-maker');
  var audio = document.getElementById('lm-audio');
  var lyricsTa = document.getElementById('lyrics');
  var saveBtn = document.getElementById('lm-save');
  var resetBtn = document.getElementById('lm-reset');
  var closeBtn = document.getElementById('lm-close');
  var markBtn = document.getElementById('lm-mark');
  var pastBox = document.getElementById('lm-past');
  var curBox = document.getElementById('lm-current');
  var nextBox = document.getElementById('lm-next');
  var progBox = document.getElementById('lm-progress');
  var toolbar = document.getElementById('lyrics-toolbar');

  var rows = [];
  var nextIdx = 0;

  function fmt(t) {
    var m = Math.floor(t / 60), s = Math.floor(t % 60), cs = Math.floor((t % 1) * 100);
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s + '.' + (cs < 10 ? '0' : '') + cs;
  }

  function parseLines() {
    var raw = lyricsTa.value.split('\n');
    var out = [];
    raw.forEach(function (ln) {
      ln = ln.trim();
      if (!ln) return;
      var m = ln.match(/^\[(\d{1,2}):(\d{1,2})(?:\.(\d{1,3}))?\]\s*(.*)$/);
      if (m) {
        var t = parseInt(m[1]) * 60 + parseInt(m[2]) + (m[3] ? (parseInt(m[3]) / (m[3].length === 2 ? 100 : 1000)) : 0);
        out.push({ time: t, text: m[4] });
      } else {
        out.push({ time: null, text: ln });
      }
    });
    return out;
  }

  function render() {
    var past = rows.slice(Math.max(0, nextIdx - 3), nextIdx);
    pastBox.innerHTML = past.length
      ? past.map(function (r) { return '<div>[' + fmt(r.time) + '] ' + r.text + '</div>'; }).join('')
      : '<span style="color:#c0c4cc;">（已打点歌词会显示在这里）</span>';

    if (nextIdx < rows.length) {
      curBox.textContent = rows[nextIdx].text;
    } else {
      curBox.textContent = nextIdx === 0 ? '（请先在歌词框粘贴纯文本歌词）' : '🎉 全部打点完成！';
    }

    var upcoming = rows.slice(nextIdx + 1, nextIdx + 4);
    nextBox.innerHTML = upcoming.length
      ? upcoming.map(function (r) { return '<div>' + r.text + '</div>'; }).join('')
      : '<span style="color:#c0c4cc;">（没有更多歌词了）</span>';

    progBox.textContent = '已打点 ' + nextIdx + ' / ' + rows.length + ' 句';
  }

  function openPanel(auto) {
    var url = document.querySelector('input[name="audio_url"]').value.trim();
    lyricsTa.style.display = 'none';
    toolbar.style.display = 'none';
    panel.style.display = 'flex';
    rows = parseLines();
    if (rows.length && rows[0].time == null) rows[0].time = 0;
    nextIdx = 0;
    while (nextIdx < rows.length && rows[nextIdx].time != null) nextIdx++;
    if (url) {
      audio.src = url;
      var tip = document.getElementById('lm-tip');
      if (tip) tip.style.display = 'none';
    } else {
      var tip2 = document.getElementById('lm-tip');
      if (tip2) {
        tip2.style.display = 'block';
        tip2.innerHTML = '⚠ 还没音频地址：请先点下方「发布歌曲」保存（上传的 mp3 保存后才有地址），保存后回到这里再点「⏱ 制作歌词」就能播放打点。';
      }
    }
    render();
  }

  function exitPanel() {
    panel.style.display = 'none';
    lyricsTa.style.display = '';
    toolbar.style.display = '';
    if (audio) { audio.pause(); audio.removeAttribute('src'); }
  }

  btn.addEventListener('click', function () { openPanel(false); });

  if (new URLSearchParams(location.search).get('open_maker') === '1') {
    openPanel(true);
  }

  markBtn.addEventListener('click', function () {
    if (nextIdx >= rows.length) return;
    if (!audio.src) { alert('请先确认音频已加载'); return; }
    var nextLineIdx = nextIdx + 1;
    if (nextLineIdx < rows.length) {
      rows[nextLineIdx].time = audio.currentTime || 0;
    }
    nextIdx = nextLineIdx;
    render();
  });

  saveBtn.addEventListener('click', function () {
    var out = rows
      .filter(function (r) { return r.text; })
      .map(function (r) { return (r.time != null ? '[' + fmt(r.time) + ']' : '') + r.text; })
      .join('\n');
    lyricsTa.value = out;
    exitPanel();
  });

  closeBtn.addEventListener('click', function () { exitPanel(); });

  resetBtn.addEventListener('click', function () {
    if (!confirm('清空所有时间轴，从头开始打点？歌词文本会保留。')) return;
    rows.forEach(function (r) { r.time = null; });
    if (rows.length) rows[0].time = 0;
    nextIdx = 0;
    render();
  });
})();
</script>

<?php require __DIR__ . '/../inc/footer.php'; ?>
