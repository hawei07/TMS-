<?php

declare(strict_types=1);

$env = static function (string $name, string $default): string {
    $value = getenv($name);
    return $value === false ? $default : $value;
};
$passwords = [$env('TMS_DB_PASSWORD', 'root')];
$fallbackPassword = getenv('TMS_DB_PASSWORD_FALLBACK');
if ($fallbackPassword !== false && !in_array($fallbackPassword, $passwords, true)) {
    $passwords[] = $fallbackPassword;
}
$config = [
    'host' => $env('TMS_DB_HOST', '127.0.0.1'),
    'port' => (int)$env('TMS_DB_PORT', '3306'),
    'database' => $env('TMS_DB_NAME', 'tms_db'),
    'username' => $env('TMS_DB_USER', 'root'),
    'passwords' => $passwords,
    'charset' => $env('TMS_DB_CHARSET', 'utf8mb4'),
];
$localConfigFile = __DIR__ . '/database.local.php';
if (is_file($localConfigFile)) {
    $localConfig = require $localConfigFile;
    if (!is_array($localConfig)) {
        throw new RuntimeException('config/database.local.php must return an array.');
    }
    $config = array_replace($config, $localConfig);
}
$config['port'] = (int)$config['port'];
$config['passwords'] = array_values(array_map('strval', (array)$config['passwords']));
if ($config['passwords'] === []) {
    $config['passwords'] = [''];
}
return $config;
