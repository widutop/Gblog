<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
if (!function_exists('add_action')) return;

/**
 * 分享工具条插件
 * - 后台在「应用中心 → 已安装 → share-toolbar」可逐平台开关 + 返回顶部/二维码/复制链接/收藏。
 * - 前端通过 add_action('footer') 在 </body> 前输出固定于网页右边缘的竖排工具条；
 *   CSS/JS 由 manifest.assets 自动注入。
 */

// 平台元信息（顺序即前端展示顺序）。qr=true 表示点击后引导扫码（微信等无网页分享接口的平台）。
function gblog_share_platforms(): array
{
    return [
        'weibo'    => ['name' => '微博',     'color' => '#E6162D', 'glyph' => '微', 'qr' => false, 'dark' => false],
        'qqzone'   => ['name' => 'QQ空间',   'color' => '#FFCC00', 'glyph' => 'Q',  'qr' => false, 'dark' => true],
        'qqfriend' => ['name' => 'QQ好友',   'color' => '#12B7F5', 'glyph' => 'QQ', 'qr' => false, 'dark' => false],
        'weixin'   => ['name' => '微信',     'color' => '#07C160', 'glyph' => '信', 'qr' => true,  'dark' => false],
        'douban'   => ['name' => '豆瓣',     'color' => '#2E963A', 'glyph' => '豆', 'qr' => false, 'dark' => false],
        'facebook' => ['name' => 'Facebook', 'color' => '#1877F2', 'glyph' => 'f',  'qr' => false, 'dark' => false],
        'twitter'  => ['name' => 'Twitter',  'color' => '#1DA1F2', 'glyph' => 't',  'qr' => false, 'dark' => false],
        'linkedin' => ['name' => 'Linkedin', 'color' => '#0A66C2', 'glyph' => 'in', 'qr' => false, 'dark' => false],
    ];
}

// 工具条图标（内联 SVG，避免依赖主题图标集）
function gblog_share_icons(): array
{
    return [
        // 分享：三节点连线
        'share' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>'
            . '<line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>',
        // 二维码
        'qr' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>'
            . '<rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3z"/>'
            . '<path d="M20 14h1M14 20h1M17.5 17.5h3v3h-3z"/></svg>',
        // 收藏（书签）
        'star' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>',
        // 返回顶部
        'up' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . '<line x1="12" y1="20" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>',
    ];
}

function gblog_share_config(): array
{
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $defaults = [
        'platforms' => ['weibo' => 1, 'qqzone' => 1, 'qqfriend' => 1, 'weixin' => 1,
                       'douban' => 1, 'facebook' => 0, 'twitter' => 0, 'linkedin' => 0],
        'back2top'  => 1,
        'qr'        => 1,
        'copy'      => 1,
        'favorite'  => 1,
        'scroll'    => 300,
    ];
    $saved = [];
    $raw = function_exists('get_setting') ? get_setting('share_toolbar', '') : '';
    if ($raw !== '') {
        $dec = json_decode($raw, true);
        if (is_array($dec)) $saved = $dec;
    }
    $cfg = array_merge($defaults, $saved);
    $cfg['platforms'] = array_merge($defaults['platforms'], is_array($cfg['platforms'] ?? null) ? $cfg['platforms'] : []);
    foreach (array_keys($defaults['platforms']) as $k) {
        $cfg['platforms'][$k] = !empty($saved['platforms'][$k]);
    }
    return $cfg;
}

function gblog_share_toolbar_render(): void
{
    $cfg = gblog_share_config();
    $anyEnabled = !empty($cfg['back2top']) || !empty($cfg['qr'])
        || !empty($cfg['copy']) || !empty($cfg['favorite'])
        || !empty(array_filter($cfg['platforms']));
    if (!$anyEnabled) return;

    $plats = gblog_share_platforms();
    $icons = gblog_share_icons();
    $order = ['weibo', 'qqzone', 'qqfriend', 'weixin', 'douban', 'facebook', 'twitter', 'linkedin'];
    $hasPlat = !empty(array_filter($cfg['platforms']));

    ob_start(); ?>
<div class="gb-share" data-gb-share="1" data-scroll="<?php echo (int)$cfg['scroll']; ?>">

  <?php /* ---------- 分享按钮（悬浮即展开平台面板） ---------- */ ?>
  <div class="gb-share-slot" data-gb-share-slot="share">
    <button type="button" class="gb-share-btn gb-share-toggle" data-gb-share-toggle
            aria-label="分享" title="分享"><?php echo $icons['share']; ?></button>
    <div class="gb-share-panel" data-gb-share-panel hidden>
      <?php if ($hasPlat): ?>
      <div class="gb-share-grid">
        <?php foreach ($order as $id):
            if (empty($cfg['platforms'][$id])) continue;
            $p = $plats[$id];
            $fg = !empty($p['dark']) ? '#3a2f00' : '#fff';
        ?>
        <button type="button" class="gb-share-item" data-share="<?php echo $id; ?>" data-qr="<?php echo !empty($p['qr']) ? '1' : '0'; ?>"
                style="--c:<?php echo $p['color']; ?>;--fg:<?php echo $fg; ?>"
                title="分享到<?php echo $p['name']; ?>" aria-label="分享到<?php echo $p['name']; ?>">
          <span class="gb-share-ico"><?php echo $p['glyph']; ?></span>
          <span class="gb-share-name"><?php echo $p['name']; ?></span>
        </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($cfg['copy'])): ?>
      <button type="button" class="gb-share-copy" data-gb-share-copy>复制当前页链接</button>
      <?php endif; ?>
    </div>
  </div>

  <?php /* ---------- 二维码（独立图标，点击展开二维码） ---------- */ ?>
  <?php if (!empty($cfg['qr'])): ?>
  <div class="gb-share-slot" data-gb-share-slot="qr">
    <button type="button" class="gb-share-btn gb-share-qrbtn" data-gb-share-qrbtn
            aria-label="当前页二维码" title="当前页二维码"><?php echo $icons['qr']; ?></button>
    <div class="gb-share-qrpop" data-gb-share-qrpop hidden>
      <div class="gb-share-qr-wrap" data-gb-share-qrwrap>
        <div class="gb-share-qr" data-gb-share-qr></div>
        <p class="gb-share-qr-tip">扫码用微信 / 浏览器打开本页</p>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php /* ---------- 收藏（加入浏览器收藏夹） ---------- */ ?>
  <?php if (!empty($cfg['favorite'])): ?>
  <div class="gb-share-slot" data-gb-share-slot="fav">
    <button type="button" class="gb-share-btn gb-share-fav" data-gb-share-fav
            aria-label="收藏本页" title="收藏本页"><?php echo $icons['star']; ?></button>
  </div>
  <?php endif; ?>

  <?php /* ---------- 返回顶部（列末位，滚动后显形） ---------- */ ?>
  <?php if (!empty($cfg['back2top'])): ?>
  <div class="gb-share-slot" data-gb-share-slot="top">
    <button type="button" class="gb-share-btn gb-share-top" data-gb-share-top
            aria-label="返回顶部" title="返回顶部"><?php echo $icons['up']; ?></button>
  </div>
  <?php endif; ?>

  <div class="gb-share-toast" data-gb-share-toast hidden></div>
</div>
<?php
    echo ob_get_clean();
}

add_action('footer', 'gblog_share_toolbar_render', 20);
