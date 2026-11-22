<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/**
 * Template Name: 页头
 * Context: $site, $page_title
 */

foreach (['template/parts/head_shell.php', 'template/parts/navbar.php', 'template/parts/modals.php', 'template/parts/main_open.php'] as $__gb_part) {
    $__gb_file = gblog_theme_part($__gb_part);
    if ($__gb_file !== '') require $__gb_file;
}
