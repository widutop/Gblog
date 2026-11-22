<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/**
 * 分类列表外壳零件（图片 / 小说分类列表共用）。
 *
 * 原本两个模板的「空态 → 标题 → 卡片循环 → 分页」骨架逐行重复，
 * 现统一由本零件拼装；差异通过入参控制：
 *   $gbCatListTitle      标题后缀（图片为「· 瀑布流」，小说为空）
 *   $gbCatListGridClass  卡片容器 class（gb-image-grid / novel-grid）
 *   $gbCatListPgLabel    分页文案
 *   $gbCatListCardSlot   卡片零件名（category_card_image / category_card_novel）
 *
 * 卡片零件自行输出完整缩进，本层只负责循环与分页。
 */

$cat        = $cat        ?? null;
$posts      = $posts      ?? [];
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;

$gbCatListTitle      = $gbCatListTitle      ?? '';
$gbCatListGridClass  = $gbCatListGridClass  ?? '';
$gbCatListPgLabel    = $gbCatListPgLabel    ?? '分类分页';
$gbCatListCardSlot   = $gbCatListCardSlot   ?? '';
?>
<section class="content list-posts">
  <?php if (!$cat): ?>
    <p class="empty">该分类不存在。</p>
  <?php elseif (!$posts): ?>
    <p class="empty">「<?= e($cat['name']) ?>」下还没有内容。</p>
  <?php else: ?>
    <h2 class="list-title">分类：<?= e($cat['name']) ?><?= $gbCatListTitle !== '' ? ' ' . e($gbCatListTitle) : '' ?></h2>
    <div class="<?= e($gbCatListGridClass) ?>">
      <?php foreach ($posts as $p): ?>
        <?php if ($gbCatListCardSlot !== '') render_slot($gbCatListCardSlot, get_defined_vars()); ?>
      <?php endforeach; ?>
    </div>
    <?php render_slot('pagination', [
        'pgPage'  => $page,
        'pgPages' => $totalPages,
        'pgBase'  => gblog_pretty_base('index.php?do=category&id=' . (int)$cat['id']),
        'pgLabel' => $gbCatListPgLabel,
    ]); ?>
  <?php endif; ?>
</section>
