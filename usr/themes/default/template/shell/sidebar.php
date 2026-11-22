<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/**
 * Template Name: 内页侧栏
 * Context: $site
 */

$site   = (isset($GLOBALS['site']) && !empty($GLOBALS['site']['id'])) ? $GLOBALS['site'] : current_site();
$isSong = ($site['type'] ?? '') === 'song';
$sb_ad1 = render_ad('sidebar');
$sb_ad2 = render_ad('article_sidebar');

$sbRankTitle    = '本栏目热门';
$sbRankIcon     = 'fire';
$sbRecentTitle  = '最新发布';
$sbRecentIcon   = 'clock';
$sbCommentTitle = '最新评论';
$sbCommentIcon  = 'comment';
$sbEmpty        = '暂无内容';
// 侧栏评论模块：统一取本栏目「最新评论」15 条
$sbCommentLimit = 15;

if ($isSong) {
    $sbRecSongs   = gblog_random_ranked_songs('rec');
    $sbRecFirstId = $sbRecSongs ? (int)$sbRecSongs[0]['id'] : 0;
    $sbDeepSongs  = gblog_random_ranked_songs('deep');
    $sbDeepFirstId= $sbDeepSongs ? (int)$sbDeepSongs[0]['id'] : 0;

    $sbComments = get_hot_comments($sbCommentLimit, (int)$site['id'], 'latest');
} else {
    $sbRankItems = [];
    foreach (get_hot_posts('specific', [(int)$site['id']], 10) as $hp) {
        $sbRankItems[] = [
            'url'   => 'index.php?do=post&id=' . (int)$hp['id'],
            'title' => (string)$hp['title'],
            'extra' => (int)$hp['view_count'] . '阅',
        ];
    }
    $sbRecentItems = [];
    foreach (get_recent_posts(10, (int)$site['id']) as $rp) {
        $sbRecentItems[] = [
            'url'   => 'index.php?do=post&id=' . (int)$rp['id'],
            'title' => (string)$rp['title'],
            'icon'  => 'file',
            'extra' => (string)fmt_short_date($rp['created_at']),
        ];
    }
    $sbComments = get_hot_comments($sbCommentLimit, (int)$site['id'], 'latest');
}

// 评论跳转链接：文章按全局 id，歌曲按歌曲 id（歌曲评论 target_type='song'）
$sbCommentUrl = function (array $hc): string {
    if (($hc['target_type'] ?? '') === 'song' && (int)($hc['song_id'] ?? 0) > 0) {
        return gblog_url('song', ['id' => (int)$hc['song_id']]);
    }
    return 'index.php?do=post&id=' . (int)($hc['target_id'] ?? 0);
};
?>
<aside class="sidebar">
<?php
$sbDo = $_GET['do'] ?? '';
if ($sbDo === 'category' && empty($GLOBALS['gblog_hide_sidebar_categories'])): ?>
  <div class="widget"><h3>分类</h3><ul>
    <?php foreach (get_categories_with_count() as $c): ?>
      <li><a href="<?= e(gblog_url('category', ['id' => (int)$c['id']])) ?>"><?= e($c['name']) ?> (<?= (int)$c['post_count'] ?>)</a></li>
    <?php endforeach; ?>
  </ul></div>
<?php elseif ($sbDo === 'tag'): ?>
  <div class="widget"><h3>标签</h3><div class="tag-cloud">
    <?php foreach (get_tags_with_count() as $t): ?>
      <a href="<?= e(gblog_url('tag', ['id' => (int)$t['id']])) ?>"><?= e($t['name']) ?></a>
    <?php endforeach; ?>
  </div></div>
<?php endif; ?>
<?php if ($isSong): ?>
  <div class="widget widget-chart">
    <div class="gb-music-chart gb-music-chart--blue" data-rand-chart data-kind="rec">
      <div class="gb-music-chart-head">
        <h2>推荐榜</h2>
        <a class="gb-music-chart-play" data-rand-play href="<?= $sbRecFirstId ? 'index.php?do=song&amp;id=' . $sbRecFirstId : '#' ?>" title="查看本榜第一首" aria-label="查看本榜第一首"<?= $sbRecFirstId ? '' : ' hidden' ?>></a>
        <?php if ($sbRecSongs): ?><button type="button" class="gb-music-chart-refresh" data-rand-refresh title="随机换一批" aria-label="随机换一批">&#8635;</button><?php endif; ?>
      </div>
      <ol class="gb-music-chart-list" data-rand-list>
        <?php if ($sbRecSongs): ?>
        <?php $songs = $sbRecSongs; render_slot('song_chart_rows', get_defined_vars()); ?>
        <?php else: ?>
        <li class="gb-music-chart-empty">暂无更多推荐歌曲</li>
        <?php endif; ?>
      </ol>
    </div>
  </div>

  <div class="widget widget-chart">
    <div class="gb-music-chart gb-music-chart--purple" data-rand-chart data-kind="deep">
      <div class="gb-music-chart-head">
        <h2>深度推荐</h2>
        <a class="gb-music-chart-play" data-rand-play href="<?= $sbDeepFirstId ? 'index.php?do=song&amp;id=' . $sbDeepFirstId : '#' ?>" title="查看本榜第一首" aria-label="查看本榜第一首"<?= $sbDeepFirstId ? '' : ' hidden' ?>></a>
        <?php if ($sbDeepSongs): ?><button type="button" class="gb-music-chart-refresh" data-rand-refresh title="随机换一批" aria-label="随机换一批">&#8635;</button><?php endif; ?>
      </div>
      <ol class="gb-music-chart-list" data-rand-list>
        <?php if ($sbDeepSongs): ?>
        <?php $songs = $sbDeepSongs; render_slot('song_chart_rows', get_defined_vars()); ?>
        <?php else: ?>
        <li class="gb-music-chart-empty">暂无更多推荐歌曲</li>
        <?php endif; ?>
      </ol>
    </div>
  </div>
<?php endif; ?>

<?php if (!$isSong): ?>
  <div class="widget widget-hot-posts">
    <h3><?= $sbRankIcon ? gblog_icon($sbRankIcon) . ' ' : '' ?><?= e($sbRankTitle) ?></h3>
    <?php if ($sbRankItems): ?>
      <ul class="player-list">
        <?php foreach ($sbRankItems as $i => $it): ?>
          <li>
            <span class="sb-no"><?= $i + 1 ?></span>
            <a class="sb-title" href="<?= e($it['url']) ?>"><?= e($it['title']) ?></a>
            <span class="sb-extra"><?= e($it['extra']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="sb-empty"><?= e($sbEmpty) ?></p>
    <?php endif; ?>
  </div>

  <div class="widget widget-recent-songs">
    <h3><?= $sbRecentIcon ? gblog_icon($sbRecentIcon) . ' ' : '' ?><?= e($sbRecentTitle) ?></h3>
    <?php if ($sbRecentItems): ?>
      <ul class="recent-songs">
        <?php foreach ($sbRecentItems as $it): ?>
          <li>
            <span class="sb-icon"><?= gblog_icon($it['icon']) ?></span>
            <?php if ($it['url'] !== ''): ?>
              <a class="sb-title" href="<?= e($it['url']) ?>"><?= e($it['title']) ?></a>
            <?php else: ?>
              <span class="sb-title"><?= e($it['title']) ?></span>
            <?php endif; ?>
            <?php if ($it['extra'] !== ''): ?><span class="sb-extra"><?= e($it['extra']) ?></span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="sb-empty"><?= e($sbEmpty) ?></p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($isSong): ?>
  <div class="widget widget-chart">
    <div class="gb-music-chart gb-music-chart--slate">
      <div class="gb-music-chart-head"><h2><?= e($sbCommentTitle) ?></h2></div>
      <?php if ($sbComments): ?>
      <ol class="gb-music-chart-list">
        <?php foreach ($sbComments as $i => $hc): ?>
          <li class="gb-music-chart-row">
            <a class="gb-music-chart-link" href="<?= e($sbCommentUrl($hc)) ?>#comments" title="<?= e($hc['name'] . '：' . $hc['content']) ?>">
              <span class="gb-music-chart-no"><?= str_pad((int)$i + 1, 2, '0', STR_PAD_LEFT) ?></span>
              <span class="gb-comment-author"><?= e($hc['name']) ?></span>
              <span class="gb-music-chart-title"><?= e(excerpt($hc['content'], 40)) ?></span>
            </a>
            <span class="gb-music-chart-artist"><?= e(fmt_short_date($hc['created_at'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
      <?php else: ?>
      <div class="gb-music-chart-empty">暂无评论</div>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <div class="widget widget-hot-comments">
    <h3><?= $sbCommentIcon ? gblog_icon($sbCommentIcon) . ' ' : '' ?><?= e($sbCommentTitle) ?></h3>
    <?php if ($sbComments): ?>
      <ul class="hot-comments">
        <?php foreach ($sbComments as $hc): ?>
          <li><a href="<?= e($sbCommentUrl($hc)) ?>#comments"><b><?= e($hc['name']) ?></b>：<?= e(excerpt($hc['content'], 40)) ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="sb-empty">暂无评论</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

  <?php if ($sb_ad1 !== ''): ?><div class="widget widget-ad"><?php echo $sb_ad1; ?></div><?php endif; ?>
  <?php if ($sb_ad2 !== ''): ?><div class="widget widget-ad"><?php echo $sb_ad2; ?></div><?php endif; ?>
</aside>
<?php if ($isSong): ?>
<script>
(function () {
  var charts = document.querySelectorAll('[data-rand-chart]');
  if (!charts.length) return;

  // 歌曲详情 URL 由服务端随 AJAX 数据一起返回（s1/s2/s3 都能正确生成，尤其 s2 的日期）。
  // 不再用占位 ID 拼模板：s2 形态需要发布日期，纯前端拼不出来。
  var SONG_HREF_FALLBACK = <?= json_encode(gblog_web_base() . 'index.php?do=song&id=', JSON_UNESCAPED_SLASHES) ?>;
  function songHref(s) { return (s && s.href) ? s.href : SONG_HREF_FALLBACK + encodeURIComponent(s ? s.id : 0); }

  function row(s, i) {
    var li = document.createElement('li');
    li.className = 'gb-music-chart-row';
    var a = document.createElement('a');
    a.className = 'gb-music-chart-link';
    a.href = songHref(s);
    a.setAttribute('data-song-id', s.id);
    var no = document.createElement('span');
    no.className = 'gb-music-chart-no';
    no.textContent = ('0' + (i + 1)).slice(-2);
    a.appendChild(no);
    if (i < 3) {
      var cov = document.createElement('span');
      cov.className = 'gb-music-chart-cover';
      cov.setAttribute('aria-hidden', 'true');
      if (s.cover_url) {
        var img = document.createElement('img');
        img.src = s.cover_url; img.alt = ''; img.loading = 'lazy';
        cov.appendChild(img);
      } else {
        cov.textContent = '\u266A';
      }
      a.appendChild(cov);
    }
    var t = document.createElement('span');
    t.className = 'gb-music-chart-title';
    t.textContent = s.title || '';
    a.appendChild(t);
    li.appendChild(a);
    var p = document.createElement('a');
    p.className = 'gb-music-chart-songplay';
    p.href = a.href;
    p.setAttribute('aria-label', '查看 ' + (s.title || ''));
    li.appendChild(p);
    var ar = document.createElement('span');
    ar.className = 'gb-music-chart-artist';
    ar.textContent = s.artist || '未知歌手';
    li.appendChild(ar);
    return li;
  }

  Array.prototype.forEach.call(charts, function (chart) {
    var btn  = chart.querySelector('[data-rand-refresh]');
    var list = chart.querySelector('[data-rand-list]');
    var play = chart.querySelector('[data-rand-play]');
    if (!btn || !list) return;
    var busy = false;

    btn.addEventListener('click', function () {
      if (busy) return;
      busy = true;
      btn.disabled = true;
      btn.classList.add('is-spin');
      var ids = [];
      Array.prototype.forEach.call(list.querySelectorAll('.gb-music-chart-link'), function (a) {
        var sid = a.getAttribute('data-song-id') || '';
        if (sid && ids.indexOf(sid) === -1) ids.push(sid);
      });
      var url = 'index.php?do=recommend_batch&kind=' + encodeURIComponent(chart.getAttribute('data-kind') || 'rec')
              + (ids.length ? '&exclude=' + encodeURIComponent(ids.join(',')) : '');
      fetch(url, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d || !d.ok) return;
          list.textContent = '';
          var songs = d.songs || [];
          if (!songs.length) {
            var em = document.createElement('li');
            em.className = 'gb-music-chart-empty';
            em.textContent = '暂无更多推荐歌曲';
            list.appendChild(em);
          } else {
            songs.forEach(function (s, i) { list.appendChild(row(s, i)); });
          }
          if (play) {
            play.hidden = !songs.length;
            if (songs.length) play.href = songHref(songs[0]);
          }
        })
        .catch(function () {})
        .then(function () {
          busy = false;
          btn.disabled = false;
          window.setTimeout(function () { btn.classList.remove('is-spin'); }, 420);
        });
    });
  });
})();
</script>
<?php endif; ?>
