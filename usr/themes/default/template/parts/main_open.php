<?php if (!defined('GBLOG_CORE')) { http_response_code(403); exit; } ?>
<main class="container site-main<?= (($GLOBALS['agg_hero_position'] ?? 'list') === 'page' && trim($GLOBALS['agg_hero_html'] ?? '') !== '') ? ' has-page-hero' : '' ?>">
  <?php if (($GLOBALS['agg_hero_position'] ?? 'list') === 'page' && trim($GLOBALS['agg_hero_html'] ?? '') !== ''): ?>
  <div class="agg-hero agg-hero--page"><?= $GLOBALS['agg_hero_html'] ?></div>
  <?php endif; ?>
  <div class="content-col">

