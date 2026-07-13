<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command can only run from the CLI.\n");
    exit(1);
}

$projectRoot = dirname(__DIR__);
date_default_timezone_set('Asia/Shanghai');

require_once $projectRoot . '/app/database.php';
require_once __DIR__ . '/bootstrap_schema.php';

$options = array_slice($argv, 1);
$showStatus = in_array('--status', $options, true);
$force = in_array('--force', $options, true);
$allowedOptions = ['--status', '--force'];

foreach ($options as $option) {
    if (!in_array($option, $allowedOptions, true)) {
        fwrite(STDERR, "Unknown option: {$option}\n");
        fwrite(STDERR, "Usage: php migrations/migrate.php [--status] [--force]\n");
        exit(1);
    }
}

try {
    $config = require $projectRoot . '/config/database.php';
    $db = createDatabaseConnection($config);
    $runner = createMigrationRunner($db);

    if ($showStatus) {
        foreach ($runner->status() as $migration) {
            $state = $migration['applied'] ? 'applied' : 'pending';
            $appliedAt = $migration['applied_at'] ? " ({$migration['applied_at']})" : '';
            echo sprintf("%-10s %s%s - %s\n", $state, $migration['version'], $appliedAt, $migration['description']);
        }
        exit(0);
    }

    $executed = $runner->migrate(
        $force,
        static function (string $message): void {
            echo $message . PHP_EOL;
        }
    );

    echo $executed === []
        ? "No pending migrations.\n"
        : sprintf("Completed %d migration(s).\n", count($executed));
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
