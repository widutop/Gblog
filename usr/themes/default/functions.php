<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/**
 * 主题入口：激活时加载一次，只用来挂钩子/注册资源，文件顶层禁止输出内容。
 * 模板覆盖放 template/（同名文件优先），整页替换可用 filter layout_file。
 */

if (!defined('GBLOG_ROOT')) {
    return;
}

