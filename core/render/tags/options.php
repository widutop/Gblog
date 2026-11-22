<?php

if (!defined('GBLOG_ROOT')) exit;

// 读站点设置项；$default 兜底。settings 键名只增不删
function gblog_opt(string $key, string $default = ''): string
{
    return function_exists('get_setting') ? get_setting($key, $default) : $default;
}

function gblog_opt_url(string $key, string $default = ''): string
{
    $v = gblog_opt($key, $default);
    // 站点 Logo / 封面等上传地址必须根绝对，路径式 URL 下相对地址会 404
    if (preg_match('#^uploads/#', $v)) {
        $base = function_exists('gblog_web_base') ? gblog_web_base() : '/';
        return $base . 'usr/' . $v;
    }
    return $v;
}
