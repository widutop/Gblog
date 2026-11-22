<?php if (!defined('GBLOG_CORE')) { http_response_code(403); exit; } ?>
<footer class="site-footer">
  <div class="container footer-inner">
    <?php if ($links): ?>
    <div class="footer-links" style="display:flex;align-items:center;justify-content:center;gap:6px;flex-wrap:wrap;padding-bottom:10px;margin-bottom:10px;">
      <span style="font-size:14px;color:var(--text-muted);">友情链接：</span>
      <?php foreach ($links as $lk): ?>
        <a href="<?= e($lk['url']) ?>" target="_blank" rel="noopener nofollow" style="font-size:14px;color:var(--text);text-decoration:none;"><?= e($lk['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="footer-nav" style="display:flex;justify-content:center;align-items:center;gap:16px;padding-bottom:10px;margin-bottom:10px;">
      <a href="index.php?do=about" style="font-size:14px;color:var(--text);text-decoration:none;">关于<?= e($site_name) ?></a>
      <a href="index.php?do=archive" style="font-size:14px;color:var(--text);text-decoration:none;">归档</a>
      <a href="index.php?do=links" style="font-size:14px;color:var(--text);text-decoration:none;">友情链接</a>
    </div>
    <p style="margin:0;">
      &copy; <?= date('Y') ?> <?= e($site_name) ?> <?= e($footerCopyright) ?>
      <?php if ($footerIcp !== ''): ?><a href="https://beian.miit.gov.cn" target="_blank" rel="noopener" style="color:var(--text);text-decoration:none;"><?= e($footerIcp) ?></a><?php endif; ?>
      <?php if ($footerPowered !== ''): ?>| <?php if ($footerPoweredUrl !== ''): ?><a href="<?= e($footerPoweredUrl) ?>" target="_blank" rel="noopener" style="color:var(--text);text-decoration:none;"><?= e($footerPowered) ?></a><?php else: ?><a href="index.php" style="color:var(--text);text-decoration:none;"><?= e($footerPowered) ?></a><?php endif; ?><?php endif; ?>
    </p>
  </div>
</footer>
