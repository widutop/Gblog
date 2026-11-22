<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/**
 * 分类列表卡片零件·小说（封面 + 标题 + 作者 + 分类 + 简介）。Context: $p, $cat
 */
$p  = $p  ?? [];
$cat = $cat ?? [];
?>
        <div class="novel-card">
          <?php if (!empty($p['cover'])): ?>
            <img class="novel-cover" src="<?= e($p['cover']) ?>" alt="<?= e($p['title']) ?>">
          <?php else: ?>
            <div class="novel-cover placeholder" data-cover="<?= e($cat['site_slug'] ?? 'novel') ?>"><?= gblog_icon('book') ?></div>
          <?php endif; ?>
          <div class="novel-info">
            <div class="novel-title"><a href="<?= e(gblog_post_url($p)) ?>"><?= e($p['title']) ?></a></div>
            <div class="novel-meta">
              <?= e($p['author'] ?: '佚名') ?>
              <?php if (!empty($p['category_name'])): ?><span class="novel-cat"><?= e($p['category_name']) ?></span><?php endif; ?>
            </div>
            <?php if (!empty($p['excerpt'])): ?><div class="novel-desc"><?= e(excerpt($p['excerpt'], 70)) ?></div><?php endif; ?>
          </div>
        </div>
