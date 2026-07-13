<?php

declare(strict_types=1);

/**
 * @return array<string,callable(PDO,string,array,array):void>
 */
function organizationApiRoutes(): array
{
    return [
        'list_organizations' => 'listOrganizations',
        'add_organization' => 'addOrganization',
        'update_organization' => 'updateOrganization',
        'delete_organization' => 'deleteOrganization',
    ];
}

function listOrganizations(PDO $db, string $method, array $query, array $input): void
{
    $organizations = $db
        ->query('SELECT * FROM organizations ORDER BY sort_order, id')
        ->fetchAll(PDO::FETCH_ASSOC);

    $tree = buildOrganizationTree($organizations);
    json(['data' => ['tree' => $tree, 'flat' => $organizations]]);
}

/**
 * @param list<array<string,mixed>> $organizations
 * @return list<array<string,mixed>>
 */
function buildOrganizationTree(array &$organizations): array
{
    $tree = [];
    $map = [];

    foreach ($organizations as &$organization) {
        $organization['children'] = [];
        $map[$organization['id']] = &$organization;
    }
    unset($organization);

    foreach ($map as &$organization) {
        if ($organization['parent_id'] && isset($map[$organization['parent_id']])) {
            $map[$organization['parent_id']]['children'][] = &$organization;
        } else {
            $tree[] = &$organization;
        }
    }
    unset($organization);

    return $tree;
}

function addOrganization(PDO $db, string $method, array $query, array $input): void
{
    if (empty($input['name'])) {
        json(['error' => '名称不能为空']);
    }

    $type = $input['type'] ?? '部门';
    if (!in_array($type, ['部门', '校区'], true)) {
        json(['error' => '类型无效']);
    }

    $parentId = (int)($input['parent_id'] ?? 0);
    $stmt = $db->prepare(
        'SELECT id FROM organizations WHERE name = :name AND parent_id = :parent_id'
    );
    $stmt->execute([
        ':name' => $input['name'],
        ':parent_id' => $parentId,
    ]);
    if ($stmt->fetchColumn()) {
        json(['error' => '同一父节点下名称已存在']);
    }

    $stmt = $db->prepare(
        'INSERT INTO organizations (name, type, parent_id, sort_order, created_at)
         VALUES (:name, :type, :parent_id, :sort_order, :created_at)'
    );
    $stmt->execute([
        ':name' => $input['name'],
        ':type' => $type,
        ':parent_id' => $parentId,
        ':sort_order' => (int)($input['sort_order'] ?? 0),
        ':created_at' => now(),
    ]);

    json(['message' => '新增成功', 'id' => $db->lastInsertId()]);
}

function updateOrganization(PDO $db, string $method, array $query, array $input): void
{
    $organizationId = (int)($input['id'] ?? 0);
    if ($organizationId <= 0) {
        json(['error' => 'ID无效']);
    }

    $stmt = $db->prepare('SELECT * FROM organizations WHERE id = :id');
    $stmt->execute([':id' => $organizationId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        json(['error' => '组织不存在']);
    }

    $updates = [];
    $params = [':id' => $organizationId];

    if (isset($input['name']) && $input['name'] !== '') {
        $parentId = isset($input['parent_id'])
            ? (int)$input['parent_id']
            : (int)$existing['parent_id'];

        $stmt = $db->prepare(
            'SELECT id FROM organizations
             WHERE name = :name AND parent_id = :parent_id AND id != :id'
        );
        $stmt->execute([
            ':name' => $input['name'],
            ':parent_id' => $parentId,
            ':id' => $organizationId,
        ]);
        if ($stmt->fetchColumn()) {
            json(['error' => '同一父节点下名称已存在']);
        }

        $updates[] = 'name = :name';
        $params[':name'] = $input['name'];
    }

    if (isset($input['type']) && in_array($input['type'], ['部门', '校区'], true)) {
        $updates[] = 'type = :type';
        $params[':type'] = $input['type'];
    }

    if (isset($input['parent_id'])) {
        $parentId = (int)$input['parent_id'];
        if ($parentId === $organizationId) {
            json(['error' => '不能将自身设为上级']);
        }
        $updates[] = 'parent_id = :parent_id';
        $params[':parent_id'] = $parentId;
    }

    if (isset($input['sort_order'])) {
        $updates[] = 'sort_order = :sort_order';
        $params[':sort_order'] = (int)$input['sort_order'];
    }

    if ($updates === []) {
        json(['message' => '无变更']);
    }

    $stmt = $db->prepare(
        'UPDATE organizations SET ' . implode(', ', $updates) . ' WHERE id = :id'
    );
    $stmt->execute($params);
    json(['message' => '更新成功']);
}

function deleteOrganization(PDO $db, string $method, array $query, array $input): void
{
    $organizationId = (int)($input['id'] ?? 0);
    if ($organizationId <= 0) {
        json(['error' => 'ID无效']);
    }

    $stmt = $db->prepare('SELECT COUNT(*) FROM organizations WHERE parent_id = :id');
    $stmt->execute([':id' => $organizationId]);
    if ((int)$stmt->fetchColumn() > 0) {
        json(['error' => '该节点下有子节点，请先删除子节点']);
    }

    $stmt = $db->prepare('DELETE FROM organizations WHERE id = :id');
    $stmt->execute([':id' => $organizationId]);
    json(['message' => '删除成功']);
}
