<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/** 详情页下载卡插槽别名：实体在 parts/download-box.php，保留本文件供已有模板调用。 */
render_slot('download-box', get_defined_vars());
