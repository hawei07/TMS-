<?php
// PHP内置服务器路由器：静态文件直接由服务器处理
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) {
    return false;
}
// 否则走 index.php
require __DIR__ . '/index.php';
