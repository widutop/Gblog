<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/**
 * Template Name: 页脚
 * Context: $site（内部调用 get_sidebar）
 */

$site_name = get_setting('site_name', SITE_NAME);
$links = get_links();
$footerCopyright = get_setting('footer_copyright', '版权所有');
$footerIcp       = get_setting('footer_icp', '');
$footerPowered   = get_setting('footer_powered', '由 Gblog 驱动');
$footerPoweredUrl= get_setting('footer_powered_url', '');
?>
  </div><!-- /content-col -->
<?php get_sidebar(); ?>
</main>
<?php $__gb_fb = gblog_theme_part('template/parts/footer_bar.php'); if ($__gb_fb !== '') require $__gb_fb; ?>
<?= function_exists('gblog_app_assets') ? gblog_app_assets('js') : '' ?>
<?php if (function_exists('do_action')) do_action('footer'); ?>
</body>
</html>
