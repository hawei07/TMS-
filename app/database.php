<?php

declare(strict_types=1);

/**
 * @param array{host:string,port:int,database:string,username:string,passwords:list<string>,charset:string} $config
 */
function createDatabaseConnection(array $config): PDO
{
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['database'],
        $config['charset']
    );
    $errors = [];

    foreach ($config['passwords'] as $password) {
        try {
            return new PDO($dsn, $config['username'], $password, $pdoOptions);
        } catch (PDOException $e) {
            $errors[] = $e->getMessage();
        }
    }

    error_log('Database connection failed: ' . implode(' | ', $errors));
    throw new RuntimeException('Database connection failed. Check the database configuration and service status.');
}
