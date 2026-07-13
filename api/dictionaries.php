<?php

declare(strict_types=1);

/**
 * @return array<string,callable(PDO,string,array,array):void>
 */
function dictionaryApiRoutes(): array
{
    return [
        'list_channels' => 'listChannels',
        'add_channel' => 'addChannel',
        'update_channel' => 'updateChannel',
        'delete_channel' => 'deleteChannel',
        'list_intention_levels' => 'listIntentionLevels',
        'add_intention_level' => 'addIntentionLevel',
        'update_intention_level' => 'updateIntentionLevel',
        'delete_intention_level' => 'deleteIntentionLevel',
        'list_basic_types' => 'listBasicTypes',
        'add_basic_type' => 'addBasicType',
        'update_basic_type' => 'updateBasicType',
        'delete_basic_type' => 'deleteBasicType',
        'list_positions' => 'listPositions',
        'add_position' => 'addPosition',
        'update_position' => 'updatePosition',
        'delete_position' => 'deletePosition',
    ];
}

function dictionaryRequirePost(string $method): void
{
    if ($method !== 'POST') {
        json(['error' => 'Method not allowed']);
    }
}

function listChannels(PDO $db, string $method, array $query, array $input): void
{
    $rows = $db->query('SELECT * FROM channels ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    json(['data' => $rows]);
}

function addChannel(PDO $db, string $method, array $query, array $input): void
{
    dictionaryRequirePost($method);
    $name = trim($input['name'] ?? '');
    if (!$name) {
        json(['error' => '渠道名称不能为空']);
    }

    $stmt = $db->prepare('SELECT COUNT(*) FROM channels WHERE name = :name');
    $stmt->execute([':name' => $name]);
    if ((int)$stmt->fetchColumn() > 0) {
        json(['error' => '渠道名称已存在']);
    }

    $stmt = $db->prepare('INSERT INTO channels (name, created_at) VALUES (:name, :created_at)');
    $stmt->execute([':name' => $name, ':created_at' => now()]);
    json(['id' => $db->lastInsertId(), 'message' => '渠道添加成功']);
}

function updateChannel(PDO $db, string $method, array $query, array $input): void
{
    dictionaryRequirePost($method);
    $channelId = (int)($input['id'] ?? 0);
    if (!$channelId) {
        json(['error' => '渠道ID无效']);
    }

    $newName = trim($input['name'] ?? '');
    if (!$newName) {
        json(['error' => '渠道名称不能为空']);
    }

    $stmt = $db->prepare('SELECT COUNT(*) FROM channels WHERE name = :name AND id != :id');
    $stmt->execute([':name' => $newName, ':id' => $channelId]);
    if ((int)$stmt->fetchColumn() > 0) {
        json(['error' => '渠道名称已存在']);
    }

    $stmt = $db->prepare('SELECT name FROM channels WHERE id = :id');
    $stmt->execute([':id' => $channelId]);
    $oldName = $stmt->fetchColumn();
    if (!$oldName) {
        json(['error' => '渠道不存在']);
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('UPDATE channels SET name = :name WHERE id = :id');
        $stmt->execute([':name' => $newName, ':id' => $channelId]);

        $stmt = $db->prepare('UPDATE resources SET source = :new_name WHERE source = :old_name');
        $stmt->execute([':new_name' => $newName, ':old_name' => $oldName]);
        $updatedResources = $stmt->rowCount();

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    json(['message' => '渠道修改成功', 'updated_resources' => $updatedResources]);
}

function deleteChannel(PDO $db, string $method, array $query, array $input): void
{
    dictionaryRequirePost($method);
    $stmt = $db->prepare('DELETE FROM channels WHERE id = :id');
    $stmt->execute([':id' => (int)($input['id'] ?? 0)]);
    json(['message' => '渠道删除成功']);
}

function listIntentionLevels(PDO $db, string $method, array $query, array $input): void
{
    $rows = $db
        ->query('SELECT * FROM intention_levels ORDER BY sort_order ASC, id ASC')
        ->fetchAll(PDO::FETCH_ASSOC);
    json($rows);
}

function addIntentionLevel(PDO $db, string $method, array $query, array $input): void
{
    dictionaryRequirePost($method);
    $name = trim($input['name'] ?? '');
    if (!$name) {
        json(['error' => '意向等级名称不能为空']);
    }

    $stmt = $db->prepare('SELECT COUNT(*) FROM intention_levels WHERE name = :name');
    $stmt->execute([':name' => $name]);
    if ((int)$stmt->fetchColumn() > 0) {
        json(['error' => '意向等级名称已存在']);
    }

    $stmt = $db->prepare(
        'INSERT INTO intention_levels (name, sort_order, created_at)
         VALUES (:name, :sort_order, :created_at)'
    );
    $stmt->execute([
        ':name' => $name,
        ':sort_order' => (int)($input['sort_order'] ?? 0),
        ':created_at' => now(),
    ]);
    json(['id' => $db->lastInsertId(), 'message' => '意向等级添加成功']);
}

function updateIntentionLevel(PDO $db, string $method, array $query, array $input): void
{
    dictionaryRequirePost($method);
    $intentionId = (int)($input['id'] ?? 0);
    if (!$intentionId) {
        json(['error' => '意向等级ID无效']);
    }

    $newName = trim($input['name'] ?? '');
    if (!$newName) {
        json(['error' => '意向等级名称不能为空']);
    }

    $stmt = $db->prepare('SELECT COUNT(*) FROM intention_levels WHERE name = :name AND id != :id');
    $stmt->execute([':name' => $newName, ':id' => $intentionId]);
    if ((int)$stmt->fetchColumn() > 0) {
        json(['error' => '意向等级名称已存在']);
    }

    $stmt = $db->prepare('SELECT name FROM intention_levels WHERE id = :id');
    $stmt->execute([':id' => $intentionId]);
    $oldName = $stmt->fetchColumn();
    if (!$oldName) {
        json(['error' => '意向等级不存在']);
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'UPDATE intention_levels SET name = :name, sort_order = :sort_order WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $newName,
            ':sort_order' => (int)($input['sort_order'] ?? 0),
            ':id' => $intentionId,
        ]);

        $stmt = $db->prepare(
            'UPDATE resources SET intention_level = :new_name WHERE intention_level = :old_name'
        );
        $stmt->execute([':new_name' => $newName, ':old_name' => $oldName]);
        $updatedResources = $stmt->rowCount();

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    json(['message' => '意向等级修改成功', 'updated_resources' => $updatedResources]);
}

function deleteIntentionLevel(PDO $db, string $method, array $query, array $input): void
{
    dictionaryRequirePost($method);
    $stmt = $db->prepare('DELETE FROM intention_levels WHERE id = :id');
    $stmt->execute([':id' => (int)($input['id'] ?? 0)]);
    json(['message' => '意向等级删除成功']);
}

function listBasicTypes(PDO $db, string $method, array $query, array $input): void
{
    $category = $query['category'] ?? '';
    if (!$category) {
        json(['error' => 'category参数不能为空']);
    }

    $stmt = $db->prepare(
        'SELECT * FROM basic_types WHERE category = :category ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute([':category' => $category]);
    json($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function addBasicType(PDO $db, string $method, array $query, array $input): void
{
    dictionaryRequirePost($method);
    $category = trim($input['category'] ?? '');
    $name = trim($input['name'] ?? '');
    $sortOrder = (int)($input['sort_order'] ?? 0);

    if (!$name) {
        json(['error' => '名称不能为空']);
    }
    if (!$category) {
        json(['error' => 'category不能为空']);
    }

    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM basic_types WHERE category = :category AND name = :name'
    );
    $stmt->execute([':category' => $category, ':name' => $name]);
    if ((int)$stmt->fetchColumn() > 0) {
        json(['error' => '该类别下已存在同名类型']);
    }

    $stmt = $db->prepare(
        'INSERT INTO basic_types (category, name, sort_order, created_at)
         VALUES (:category, :name, :sort_order, :created_at)'
    );
    $stmt->execute([
        ':category' => $category,
        ':name' => $name,
        ':sort_order' => $sortOrder,
        ':created_at' => now(),
    ]);
    json(['id' => $db->lastInsertId(), 'message' => '添加成功']);
}

function updateBasicType(PDO $db, string $method, array $query, array $input): void
{
    dictionaryRequirePost($method);
    $basicTypeId = (int)($input['id'] ?? 0);
    if (!$basicTypeId) {
        json(['error' => 'ID无效']);
    }

    $stmt = $db->prepare('SELECT * FROM basic_types WHERE id = :id');
    $stmt->execute([':id' => $basicTypeId]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$old) {
        json(['error' => '记录不存在']);
    }

    $newName = trim($input['name'] ?? '');
    $finalName = $newName !== '' ? $newName : $old['name'];
    $finalSort = array_key_exists('sort_order', $input)
        ? (int)$input['sort_order']
        : (int)$old['sort_order'];

    if ($newName !== '' && $newName !== $old['name']) {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM basic_types
             WHERE category = :category AND name = :name AND id != :id'
        );
        $stmt->execute([
            ':category' => $old['category'],
            ':name' => $newName,
            ':id' => $basicTypeId,
        ]);
        if ((int)$stmt->fetchColumn() > 0) {
            json(['error' => '该类别下已存在同名类型']);
        }
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'UPDATE basic_types SET name = :name, sort_order = :sort_order WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $finalName,
            ':sort_order' => $finalSort,
            ':id' => $basicTypeId,
        ]);

        if ($newName !== '' && $newName !== $old['name']) {
            syncBasicTypeReferences($db, $old['category'], $old['name'], $newName);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    json(['message' => '更新成功']);
}

function syncBasicTypeReferences(PDO $db, string $category, string $oldName, string $newName): void
{
    if ($category === 'course_type') {
        $stmt = $db->prepare(
            'UPDATE appointments SET course_type = :new_name WHERE course_type = :old_name'
        );
    } elseif ($category === 'comm_type') {
        $stmt = $db->prepare(
            'UPDATE communication_records SET comm_type = :new_name WHERE comm_type = :old_name'
        );
    } else {
        return;
    }

    $stmt->execute([':new_name' => $newName, ':old_name' => $oldName]);
}

function deleteBasicType(PDO $db, string $method, array $query, array $input): void
{
    dictionaryRequirePost($method);
    $stmt = $db->prepare('DELETE FROM basic_types WHERE id = :id');
    $stmt->execute([':id' => (int)($input['id'] ?? 0)]);
    json(['message' => '删除成功']);
}

function listPositions(PDO $db, string $method, array $query, array $input): void
{
    $rows = $db
        ->query('SELECT * FROM positions ORDER BY sort_order ASC, id ASC')
        ->fetchAll(PDO::FETCH_ASSOC);
    json($rows);
}

function addPosition(PDO $db, string $method, array $query, array $input): void
{
    dictionaryRequirePost($method);
    $name = trim($input['name'] ?? '');
    if (!$name) {
        json(['error' => '岗位名称不能为空']);
    }

    $stmt = $db->prepare('SELECT COUNT(*) FROM positions WHERE name = :name');
    $stmt->execute([':name' => $name]);
    if ((int)$stmt->fetchColumn() > 0) {
        json(['error' => '岗位名称已存在']);
    }

    $stmt = $db->prepare(
        'INSERT INTO positions (name, sort_order, created_at)
         VALUES (:name, :sort_order, :created_at)'
    );
    $stmt->execute([
        ':name' => $name,
        ':sort_order' => (int)($input['sort_order'] ?? 0),
        ':created_at' => now(),
    ]);
    json(['id' => $db->lastInsertId(), 'message' => '岗位添加成功']);
}

function updatePosition(PDO $db, string $method, array $query, array $input): void
{
    dictionaryRequirePost($method);
    $positionId = (int)($input['id'] ?? 0);
    if (!$positionId) {
        json(['error' => '岗位ID无效']);
    }

    $stmt = $db->prepare('SELECT * FROM positions WHERE id = :id');
    $stmt->execute([':id' => $positionId]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$old) {
        json(['error' => '岗位不存在']);
    }

    $newName = trim($input['name'] ?? '');
    $finalName = $newName !== '' ? $newName : $old['name'];
    $finalSort = array_key_exists('sort_order', $input)
        ? (int)$input['sort_order']
        : (int)$old['sort_order'];

    if ($newName !== '' && $newName !== $old['name']) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM positions WHERE name = :name AND id != :id');
        $stmt->execute([':name' => $newName, ':id' => $positionId]);
        if ((int)$stmt->fetchColumn() > 0) {
            json(['error' => '岗位名称已存在']);
        }
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'UPDATE positions SET name = :name, sort_order = :sort_order WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $finalName,
            ':sort_order' => $finalSort,
            ':id' => $positionId,
        ]);

        if ($newName !== '' && $newName !== $old['name']) {
            $stmt = $db->prepare(
                'UPDATE employees SET position = :new_name WHERE position = :old_name'
            );
            $stmt->execute([':new_name' => $newName, ':old_name' => $old['name']]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    json(['message' => '岗位更新成功']);
}

function deletePosition(PDO $db, string $method, array $query, array $input): void
{
    dictionaryRequirePost($method);
    $positionId = (int)($input['id'] ?? 0);

    $stmt = $db->prepare('SELECT name FROM positions WHERE id = :id');
    $stmt->execute([':id' => $positionId]);
    $oldName = $stmt->fetchColumn();
    if (!$oldName) {
        json(['error' => '岗位不存在']);
    }

    $stmt = $db->prepare('SELECT COUNT(*) FROM employees WHERE position = :position');
    $stmt->execute([':position' => $oldName]);
    $inUse = (int)$stmt->fetchColumn();
    if ($inUse > 0) {
        json(['error' => "该岗位下有 {$inUse} 名员工，不可删除"]);
    }

    $stmt = $db->prepare('DELETE FROM positions WHERE id = :id');
    $stmt->execute([':id' => $positionId]);
    json(['message' => '岗位删除成功']);
}
