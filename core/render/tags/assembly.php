<?php

if (!defined('GBLOG_ROOT')) exit;

if (!function_exists('gblog_part')) {
    function gblog_part(string $rel): string { return gblog_theme_part($rel); }
}
if (!function_exists('gblog_asset_url')) {
    function gblog_asset_url(string $rel): string { return gblog_theme_asset_url($rel); }
}

