<?php
/**
 * robots.txt（路由 /robots.txt 或 ?do=robots）。
 * 必须由 PHP 输出：Sitemap 指令要求完全限定的绝对 URL，静态文件写不出域名。
 */
require_once __DIR__ . '/../../core/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');

$b = gblog_web_base();
$lines = [
    'User-agent: *',
    'Allow: ' . $b,
    'Disallow: ' . $b . 'admin/',
    'Disallow: ' . $b . 'install.php',
    'Disallow: ' . $b . 'install/',
    'Disallow: ' . $b . 'usr/data/',
    'Disallow: ' . $b . 'index.php?do=login',
    'Disallow: ' . $b . 'index.php?do=register',
    'Disallow: ' . $b . 'index.php?do=profile',
    'Disallow: ' . $b . 'index.php?do=forgot',
    'Disallow: ' . $b . 'index.php?do=reset',
    'Disallow: ' . $b . 'search.html',
    '',
    '# sitemap 随伪静态开关 / 形态自动变化，始终为绝对地址',
    'Sitemap: ' . gblog_absolute_url(gblog_url('sitemap')),
];
echo implode("\n", $lines) . "\n";
