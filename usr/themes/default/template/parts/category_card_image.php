<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/**
 * 分类列表卡片零件·图片（瀑布流单列封面卡）。Context: $p, $cat
 */
$p  = $p  ?? [];
$cat = $cat ?? [];
?>
        <a href="<?= e(gblog_post_url($p)) ?>" class="gb-image-card" style="display:block;break-inside:avoid;border-radius:8px;overflow:hidden;background:var(--bg-card);box-shadow:0 1px 4px rgba(0,0,0,.08);">
          <?php if (!empty($p['cover'])): ?><img src="<?= e($p['cover']) ?>" alt="<?= e($p['title']) ?>" style="width:100%;display:block;"><?php else: ?><span class="gb-cover-blank" data-cover="<?= e($cat['site_slug'] ?? 'image') ?>"><?= gblog_icon('image') ?></span><?php endif; ?>
          <div style="padding:10px 12px;">
            <div style="font-size:15px;font-weight:600;line-height:1.4;color:var(--text);"><?= e($p['title']) ?></div>
            <div style="font-size:13px;color:var(--text-muted);margin-top:4px;"><?= e(fmt_date($p['created_at'])) ?></div>
          </div>
        </a>
