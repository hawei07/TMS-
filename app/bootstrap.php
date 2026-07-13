<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/php_errors.log');
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/order_helpers.php';
require_once dirname(__DIR__) . '/migrations/bootstrap_schema.php';

$databaseConfig = require dirname(__DIR__) . '/config/database.php';

try {
    $db = createDatabaseConnection($databaseConfig);
    ensureSchema($db);
} catch (Throwable $e) {
    error_log($e->__toString());
    die('数据库初始化失败，请检查数据库配置、服务状态和迁移日志。');
}

return $db;
