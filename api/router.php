<?php

declare(strict_types=1);

require_once __DIR__ . '/dictionaries.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/organizations.php';
require_once __DIR__ . '/teaching_aids.php';
require_once __DIR__ . '/discounts.php';

/**
 * @return array<string,callable(PDO,string,array,array):void>
 */
function buildExtractedApiRoutes(): array
{
    $routes = [];

    foreach ([dictionaryApiRoutes(), settingsApiRoutes(), organizationApiRoutes(), teachingAidApiRoutes(), discountApiRoutes()] as $routeGroup) {
        $duplicates = array_intersect_key($routes, $routeGroup);
        if ($duplicates !== []) {
            throw new LogicException(
                'Duplicate extracted API action: ' . implode(', ', array_keys($duplicates))
            );
        }

        $routes += $routeGroup;
    }

    return $routes;
}

/**
 * Dispatch an action that has been extracted from the legacy index.php switch.
 */
function dispatchExtractedApi(
    PDO $db,
    string $action,
    string $method,
    array $query,
    array $input
): bool {
    static $routes = null;
    $routes ??= buildExtractedApiRoutes();

    if (!isset($routes[$action])) {
        return false;
    }

    $routes[$action]($db, $method, $query, $input);
    return true;
}
