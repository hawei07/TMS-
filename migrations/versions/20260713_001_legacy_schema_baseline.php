<?php

declare(strict_types=1);

return [
    'version' => '20260713_001_legacy_schema_baseline',
    'description' => 'Apply the legacy idempotent schema bootstrap',
    'up' => static function (PDO $db): void {
        applyLegacySchema($db);
    },
];
