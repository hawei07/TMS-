<?php

declare(strict_types=1);

/**
 * @return array<string,callable(PDO,string,array,array):void>
 */
function settingsApiRoutes(): array
{
    return [
        'list_campuses' => 'listCampuses',
        'list_tax_rates' => 'listTaxRates',
        'save_tax_rate' => 'saveTaxRate',
        'list_class_periods' => 'listClassPeriods',
        'add_class_period' => 'addClassPeriod',
        'update_class_period' => 'updateClassPeriod',
        'delete_class_period' => 'deleteClassPeriod',
    ];
}

function settingsRequirePost(string $method): void
{
    if ($method !== 'POST') {
        json(['error' => 'Method not allowed']);
    }
}

function listCampuses(PDO $db, string $method, array $query, array $input): void
{
    $rows = $db
        ->query("SELECT id, name FROM organizations WHERE type = '校区' ORDER BY name")
        ->fetchAll(PDO::FETCH_ASSOC);
    json(['data' => $rows]);
}

function listTaxRates(PDO $db, string $method, array $query, array $input): void
{
    $sql = "SELECT
                t.id,
                o.id AS campus_id,
                o.name AS campus_name,
                COALESCE(t.course_tax_rate, 0) AS course_tax_rate,
                COALESCE(t.product_tax_rate, 0) AS product_tax_rate,
                COALESCE(t.updated_at, '') AS updated_at
            FROM organizations o
            LEFT JOIN tax_rates t ON t.campus_id = o.id
            WHERE o.type = '校区'
            ORDER BY o.name";

    json(['data' => $db->query($sql)->fetchAll(PDO::FETCH_ASSOC)]);
}

function saveTaxRate(PDO $db, string $method, array $query, array $input): void
{
    settingsRequirePost($method);
    $campusId = (int)($input['campus_id'] ?? 0);
    $courseTaxRate = (float)($input['course_tax_rate'] ?? 0);
    $productTaxRate = (float)($input['product_tax_rate'] ?? 0);

    if (!$campusId) {
        json(['error' => '校区ID无效']);
    }

    $stmt = $db->prepare('SELECT id FROM tax_rates WHERE campus_id = :campus_id');
    $stmt->execute([':campus_id' => $campusId]);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        $stmt = $db->prepare(
            'UPDATE tax_rates
             SET course_tax_rate = :course_tax_rate,
                 product_tax_rate = :product_tax_rate,
                 updated_at = :updated_at
             WHERE campus_id = :campus_id'
        );
    } else {
        $stmt = $db->prepare(
            'INSERT INTO tax_rates
                (campus_id, course_tax_rate, product_tax_rate, updated_at)
             VALUES
                (:campus_id, :course_tax_rate, :product_tax_rate, :updated_at)'
        );
    }

    $stmt->execute([
        ':campus_id' => $campusId,
        ':course_tax_rate' => $courseTaxRate,
        ':product_tax_rate' => $productTaxRate,
        ':updated_at' => now(),
    ]);

    json(['message' => '税率保存成功']);
}

function listClassPeriods(PDO $db, string $method, array $query, array $input): void
{
    $campus = trim($query['campus'] ?? '');

    if ($campus) {
        $stmt = $db->prepare(
            'SELECT * FROM class_periods WHERE campus = :campus ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([':campus' => $campus]);
    } else {
        $stmt = $db->query('SELECT * FROM class_periods ORDER BY sort_order ASC, id ASC');
    }

    json(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function addClassPeriod(PDO $db, string $method, array $query, array $input): void
{
    settingsRequirePost($method);
    $name = trim($input['name'] ?? '');
    $startTime = trim($input['start_time'] ?? '');
    $endTime = trim($input['end_time'] ?? '');
    $sortOrder = (int)($input['sort_order'] ?? 0);
    $campus = trim($input['campus'] ?? '');

    if (!$name) {
        json(['error' => '时段名称不能为空']);
    }
    if (!$campus) {
        json(['error' => '请选择校区']);
    }
    if (!$startTime || !$endTime) {
        json(['error' => '开始时间和结束时间不能为空']);
    }
    if ($startTime >= $endTime) {
        json(['error' => '开始时间必须早于结束时间']);
    }

    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM class_periods WHERE name = :name AND campus = :campus'
    );
    $stmt->execute([':name' => $name, ':campus' => $campus]);
    if ((int)$stmt->fetchColumn() > 0) {
        json(['error' => '该校区已存在同名时段']);
    }

    $stmt = $db->prepare(
        'INSERT INTO class_periods
            (name, start_time, end_time, sort_order, campus, created_at)
         VALUES
            (:name, :start_time, :end_time, :sort_order, :campus, :created_at)'
    );
    $stmt->execute([
        ':name' => $name,
        ':start_time' => $startTime,
        ':end_time' => $endTime,
        ':sort_order' => $sortOrder,
        ':campus' => $campus,
        ':created_at' => now(),
    ]);

    json(['id' => $db->lastInsertId(), 'message' => '时段添加成功']);
}

function updateClassPeriod(PDO $db, string $method, array $query, array $input): void
{
    settingsRequirePost($method);
    $periodId = (int)($input['id'] ?? 0);
    if (!$periodId) {
        json(['error' => '时段ID无效']);
    }

    $stmt = $db->prepare('SELECT * FROM class_periods WHERE id = :id');
    $stmt->execute([':id' => $periodId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        json(['error' => '时段不存在']);
    }

    $name = array_key_exists('name', $input) ? trim($input['name']) : $current['name'];
    $startTime = array_key_exists('start_time', $input)
        ? trim($input['start_time'])
        : $current['start_time'];
    $endTime = array_key_exists('end_time', $input)
        ? trim($input['end_time'])
        : $current['end_time'];
    $sortOrder = array_key_exists('sort_order', $input)
        ? (int)$input['sort_order']
        : (int)$current['sort_order'];
    $campus = array_key_exists('campus', $input) ? trim($input['campus']) : $current['campus'];

    if (!$name) {
        json(['error' => '时段名称不能为空']);
    }
    if (!$campus) {
        json(['error' => '校区不能为空']);
    }
    if (!$startTime || !$endTime) {
        json(['error' => '开始时间和结束时间不能为空']);
    }
    if ($startTime >= $endTime) {
        json(['error' => '开始时间必须早于结束时间']);
    }

    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM class_periods
         WHERE name = :name AND campus = :campus AND id != :id'
    );
    $stmt->execute([
        ':name' => $name,
        ':campus' => $campus,
        ':id' => $periodId,
    ]);
    if ((int)$stmt->fetchColumn() > 0) {
        json(['error' => '该校区已存在同名时段']);
    }

    $stmt = $db->prepare(
        'UPDATE class_periods
         SET name = :name,
             start_time = :start_time,
             end_time = :end_time,
             sort_order = :sort_order,
             campus = :campus
         WHERE id = :id'
    );
    $stmt->execute([
        ':name' => $name,
        ':start_time' => $startTime,
        ':end_time' => $endTime,
        ':sort_order' => $sortOrder,
        ':campus' => $campus,
        ':id' => $periodId,
    ]);

    json(['message' => '时段修改成功']);
}

function deleteClassPeriod(PDO $db, string $method, array $query, array $input): void
{
    settingsRequirePost($method);
    $periodId = (int)($input['id'] ?? 0);
    if (!$periodId) {
        json(['error' => '时段ID无效']);
    }

    $stmt = $db->prepare('DELETE FROM class_periods WHERE id = :id');
    $stmt->execute([':id' => $periodId]);
    json(['message' => '时段删除成功']);
}
