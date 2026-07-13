<?php

declare(strict_types=1);

/**
 * @return array<string,callable(PDO,string,array,array):void>
 */
function discountApiRoutes(): array
{
    return [
        'list_discount_plans' => 'listDiscountPlans',
        'add_discount_plan' => 'addDiscountPlan',
        'update_discount_plan' => 'updateDiscountPlan',
        'delete_discount_plan' => 'deleteDiscountPlan',
        'get_discount_plan' => 'getDiscountPlan',
        'list_coupons' => 'listCoupons',
        'add_coupon' => 'addCoupon',
        'update_coupon' => 'updateCoupon',
        'delete_coupon' => 'deleteCoupon',
        'get_coupon' => 'getCoupon',
        'list_coupon_records' => 'listCouponRecords',
        'add_coupon_record' => 'addCouponRecord',
        'delete_coupon_record' => 'deleteCouponRecord',
    ];
}

function discountRequirePost(string $method): void
{
    if ($method !== 'POST') {
        json(['error' => 'Method not allowed']);
    }
}

/**
 * @param array<int|string,mixed>|string $values
 * @return list<int>
 */
function normalizeDiscountRelationIds(array|string $values): array
{
    $raw = is_string($values) ? explode(',', $values) : $values;
    return array_values(array_filter(array_map('intval', $raw), static fn(int $id): bool => $id > 0));
}

/**
 * @param array<int|string,mixed>|string $values
 */
function replaceDiscountRelation(
    PDO $db,
    string $table,
    string $ownerColumn,
    string $relatedColumn,
    int $ownerId,
    array|string $values
): void {
    $allowed = [
        'discount_plan_campuses' => ['plan_id', 'campus_id'],
        'discount_plan_subjects' => ['plan_id', 'subject_id'],
        'coupon_campuses' => ['coupon_id', 'campus_id'],
        'coupon_subjects' => ['coupon_id', 'subject_id'],
    ];
    if (($allowed[$table] ?? null) !== [$ownerColumn, $relatedColumn]) {
        throw new InvalidArgumentException('Invalid discount relation mapping.');
    }

    $delete = $db->prepare("DELETE FROM {$table} WHERE {$ownerColumn} = :owner_id");
    $delete->execute([':owner_id' => $ownerId]);

    $ids = normalizeDiscountRelationIds($values);
    if ($ids === []) {
        return;
    }

    $insert = $db->prepare(
        "INSERT INTO {$table} ({$ownerColumn}, {$relatedColumn})
         VALUES (:owner_id, :related_id)"
    );
    foreach ($ids as $relatedId) {
        $insert->execute([
            ':owner_id' => $ownerId,
            ':related_id' => $relatedId,
        ]);
    }
}

/**
 * @return list<int>
 */
function getDiscountRelationIds(
    PDO $db,
    string $table,
    string $ownerColumn,
    string $relatedColumn,
    int $ownerId
): array {
    $allowed = [
        'discount_plan_campuses' => ['plan_id', 'campus_id'],
        'discount_plan_subjects' => ['plan_id', 'subject_id'],
        'coupon_campuses' => ['coupon_id', 'campus_id'],
        'coupon_subjects' => ['coupon_id', 'subject_id'],
    ];
    if (($allowed[$table] ?? null) !== [$ownerColumn, $relatedColumn]) {
        throw new InvalidArgumentException('Invalid discount relation mapping.');
    }

    $stmt = $db->prepare(
        "SELECT {$relatedColumn}
         FROM {$table}
         WHERE {$ownerColumn} = :owner_id
         ORDER BY {$relatedColumn}"
    );
    $stmt->execute([':owner_id' => $ownerId]);

    return array_map(
        static fn(array $row): int => (int)$row[$relatedColumn],
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
}

function recalculateDiscountedPriceItems(PDO $db, string $column, int $id): void
{
    if (!in_array($column, ['discount_plan_id', 'coupon_id', 'product_coupon_id'], true)) {
        throw new InvalidArgumentException('Invalid price item discount column.');
    }

    $where = $column === 'coupon_id'
        ? '(coupon_id = :id OR product_coupon_id = :id2)'
        : "{$column} = :id";
    $stmt = $db->prepare(
        "UPDATE price_items
         SET actual_price = GREATEST(
             0,
             unit_price
             - COALESCE((SELECT discount_amount FROM discount_plans WHERE id = price_items.discount_plan_id), 0)
             - COALESCE((SELECT discount_amount FROM coupons WHERE id = price_items.coupon_id), 0)
             + COALESCE((SELECT price FROM teaching_aids WHERE id = price_items.teaching_aid_id), 0)
             - COALESCE((SELECT discount_amount FROM coupons WHERE id = price_items.product_coupon_id), 0)
         )
         WHERE {$where}"
    );
    $params = [':id' => $id];
    if ($column === 'coupon_id') {
        $params[':id2'] = $id;
    }
    $stmt->execute($params);
}

function listDiscountPlans(PDO $db, string $method, array $query, array $input): void
{
    $page = max(1, (int)($query['page'] ?? 1));
    $pageSize = max(1, min(100, (int)($query['page_size'] ?? 15)));
    $keyword = trim($query['keyword'] ?? '');
    $planType = trim($query['plan_type'] ?? '');
    $campusId = (int)($query['campus_id'] ?? 0);
    $offset = ($page - 1) * $pageSize;

    $where = ['1=1'];
    $params = [];
    if ($keyword !== '') {
        $where[] = 'dp.name LIKE :keyword';
        $params[':keyword'] = '%' . $keyword . '%';
    }
    if ($planType !== '') {
        $where[] = 'dp.plan_type = :plan_type';
        $params[':plan_type'] = $planType;
    }
    if ($campusId > 0) {
        $where[] = '(dp.id IN (
                SELECT plan_id FROM discount_plan_campuses WHERE campus_id = :campus_id
            ) OR dp.id NOT IN (SELECT plan_id FROM discount_plan_campuses))';
        $params[':campus_id'] = $campusId;
    }
    $whereSql = implode(' AND ', $where);

    $count = $db->prepare("SELECT COUNT(*) FROM discount_plans dp WHERE {$whereSql}");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $sql = "SELECT dp.*,
                (SELECT GROUP_CONCAT(DISTINCT dpc2.campus_id ORDER BY dpc2.campus_id SEPARATOR ',')
                 FROM discount_plan_campuses dpc2 WHERE dpc2.plan_id = dp.id) AS campus_ids,
                (SELECT GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ')
                 FROM discount_plan_campuses dpc2
                 LEFT JOIN organizations o ON dpc2.campus_id = o.id
                 WHERE dpc2.plan_id = dp.id) AS campus_names,
                (SELECT GROUP_CONCAT(DISTINCT dps2.subject_id ORDER BY dps2.subject_id SEPARATOR ',')
                 FROM discount_plan_subjects dps2 WHERE dps2.plan_id = dp.id) AS subject_ids,
                (SELECT GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ')
                 FROM discount_plan_subjects dps2
                 LEFT JOIN subjects s ON dps2.subject_id = s.id
                 WHERE dps2.plan_id = dp.id) AS subject_names
            FROM discount_plans dp
            WHERE {$whereSql}
            ORDER BY dp.created_at DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['amount'] = (float)$row['discount_amount'];
        unset($row['discount_amount']);
        $rows[] = $row;
    }

    json(['data' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
}

function addDiscountPlan(PDO $db, string $method, array $query, array $input): void
{
    discountRequirePost($method);
    $name = trim($input['name'] ?? '');
    $planType = trim($input['plan_type'] ?? '新报');
    $amount = (float)($input['discount_amount'] ?? $input['amount'] ?? 0);
    $startDate = trim($input['start_date'] ?? '');
    $endDate = trim($input['end_date'] ?? '');
    $campusIds = $input['campus_ids'] ?? [];
    $subjectIds = $input['subject_ids'] ?? [];

    validateDiscountPlan($name, $planType, $amount, $startDate, $endDate);

    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM discount_plans WHERE name = :name AND plan_type = :plan_type'
    );
    $stmt->execute([':name' => $name, ':plan_type' => $planType]);
    if ((int)$stmt->fetchColumn() > 0) {
        json(['error' => '同类型下方案名称已存在']);
    }

    $db->beginTransaction();
    try {
        $now = now();
        $stmt = $db->prepare(
            'INSERT INTO discount_plans
                (name, plan_type, discount_amount, start_date, end_date, created_at, updated_at)
             VALUES
                (:name, :plan_type, :amount, :start_date, :end_date, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':name' => $name,
            ':plan_type' => $planType,
            ':amount' => $amount,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $planId = (int)$db->lastInsertId();

        if (!empty($campusIds)) {
            replaceDiscountRelation(
                $db,
                'discount_plan_campuses',
                'plan_id',
                'campus_id',
                $planId,
                $campusIds
            );
        }
        if (!empty($subjectIds)) {
            replaceDiscountRelation(
                $db,
                'discount_plan_subjects',
                'plan_id',
                'subject_id',
                $planId,
                $subjectIds
            );
        }

        $db->commit();
        json(['message' => '优惠方案创建成功', 'id' => (string)$planId]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        json(['error' => '创建失败: ' . $e->getMessage()]);
    }
}

function validateDiscountPlan(
    string $name,
    string $planType,
    float $amount,
    string $startDate,
    string $endDate
): void {
    if ($name === '') {
        json(['error' => '方案名称不能为空']);
    }
    if (!in_array($planType, ['新报', '续费'], true)) {
        json(['error' => '类型无效']);
    }
    if ($amount <= 0) {
        json(['error' => '优惠金额必须大于0']);
    }
    if ($startDate === '' || $endDate === '') {
        json(['error' => '日期不能为空']);
    }
    if ($endDate < $startDate) {
        json(['error' => '结束日期不能早于开始日期']);
    }
}

function updateDiscountPlan(PDO $db, string $method, array $query, array $input): void
{
    discountRequirePost($method);
    $planId = (int)($input['id'] ?? 0);
    if ($planId <= 0) {
        json(['error' => 'ID无效']);
    }

    $stmt = $db->prepare('SELECT * FROM discount_plans WHERE id = :id');
    $stmt->execute([':id' => $planId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        json(['error' => '优惠方案不存在']);
    }

    $name = trim($input['name'] ?? $existing['name']);
    $planType = trim($input['plan_type'] ?? $existing['plan_type']);
    $amount = isset($input['discount_amount'])
        ? (float)$input['discount_amount']
        : (isset($input['amount']) ? (float)$input['amount'] : (float)$existing['discount_amount']);
    $startDate = trim($input['start_date'] ?? $existing['start_date']);
    $endDate = trim($input['end_date'] ?? $existing['end_date']);
    validateDiscountPlan($name, $planType, $amount, $startDate, $endDate);

    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM discount_plans
         WHERE name = :name AND plan_type = :plan_type AND id != :id'
    );
    $stmt->execute([':name' => $name, ':plan_type' => $planType, ':id' => $planId]);
    if ((int)$stmt->fetchColumn() > 0) {
        json(['error' => '同类型下方案名称已存在']);
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'UPDATE discount_plans
             SET name = :name,
                 plan_type = :plan_type,
                 discount_amount = :amount,
                 start_date = :start_date,
                 end_date = :end_date,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $name,
            ':plan_type' => $planType,
            ':amount' => $amount,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':updated_at' => now(),
            ':id' => $planId,
        ]);

        if (array_key_exists('campus_ids', $input)) {
            replaceDiscountRelation(
                $db,
                'discount_plan_campuses',
                'plan_id',
                'campus_id',
                $planId,
                $input['campus_ids'] ?? []
            );
        }
        if (array_key_exists('subject_ids', $input)) {
            replaceDiscountRelation(
                $db,
                'discount_plan_subjects',
                'plan_id',
                'subject_id',
                $planId,
                $input['subject_ids'] ?? []
            );
        }

        recalculateDiscountedPriceItems($db, 'discount_plan_id', $planId);
        $db->commit();
        json(['message' => '优惠方案更新成功']);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        json(['error' => '更新失败: ' . $e->getMessage()]);
    }
}

function deleteDiscountPlan(PDO $db, string $method, array $query, array $input): void
{
    discountRequirePost($method);
    $planId = (int)($input['id'] ?? 0);
    if ($planId <= 0) {
        json(['error' => 'ID无效']);
    }

    $stmt = $db->prepare('SELECT id FROM discount_plans WHERE id = :id');
    $stmt->execute([':id' => $planId]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        json(['error' => '优惠方案不存在']);
    }

    $stmt = $db->prepare('DELETE FROM discount_plans WHERE id = :id');
    $stmt->execute([':id' => $planId]);
    json(['message' => '优惠方案已删除']);
}

function getDiscountPlan(PDO $db, string $method, array $query, array $input): void
{
    $planId = (int)($query['id'] ?? 0);
    if ($planId <= 0) {
        json(['error' => 'ID无效']);
    }

    $stmt = $db->prepare('SELECT * FROM discount_plans WHERE id = :id');
    $stmt->execute([':id' => $planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$plan) {
        json(['error' => '优惠方案不存在']);
    }

    $plan['amount'] = (float)$plan['discount_amount'];
    unset($plan['discount_amount']);
    $plan['campus_ids'] = getDiscountRelationIds(
        $db,
        'discount_plan_campuses',
        'plan_id',
        'campus_id',
        $planId
    );
    $plan['subject_ids'] = getDiscountRelationIds(
        $db,
        'discount_plan_subjects',
        'plan_id',
        'subject_id',
        $planId
    );
    json($plan);
}

function listCoupons(PDO $db, string $method, array $query, array $input): void
{
    $page = max(1, (int)($query['page'] ?? 1));
    $pageSize = max(1, min(100, (int)($query['page_size'] ?? 15)));
    $offset = ($page - 1) * $pageSize;
    $filters = [
        'keyword' => trim($query['keyword'] ?? ''),
        'coupon_type' => trim($query['coupon_type'] ?? ''),
        'plan_type' => trim($query['plan_type'] ?? ''),
        'campus_id' => (int)($query['campus_id'] ?? 0),
        'subject_id' => (int)($query['subject_id'] ?? 0),
    ];

    $where = ['1=1'];
    $params = [];
    if ($filters['keyword'] !== '') {
        $where[] = 'c.name LIKE :keyword';
        $params[':keyword'] = '%' . $filters['keyword'] . '%';
    }
    if ($filters['coupon_type'] !== '') {
        $where[] = 'c.coupon_type = :coupon_type';
        $params[':coupon_type'] = $filters['coupon_type'];
    }
    if ($filters['plan_type'] !== '') {
        $where[] = "(c.plan_type = :plan_type OR c.plan_type IS NULL OR c.plan_type = '')";
        $params[':plan_type'] = $filters['plan_type'];
    }
    if ($filters['campus_id'] > 0) {
        $where[] = '(c.id IN (
                SELECT coupon_id FROM coupon_campuses WHERE campus_id = :campus_id
            ) OR c.id NOT IN (SELECT coupon_id FROM coupon_campuses))';
        $params[':campus_id'] = $filters['campus_id'];
    }
    if ($filters['subject_id'] > 0) {
        $where[] = '(c.id IN (
                SELECT coupon_id FROM coupon_subjects WHERE subject_id = :subject_id
            ) OR c.id NOT IN (SELECT coupon_id FROM coupon_subjects))';
        $params[':subject_id'] = $filters['subject_id'];
    }
    $whereSql = implode(' AND ', $where);

    $count = $db->prepare("SELECT COUNT(*) FROM coupons c WHERE {$whereSql}");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $sql = "SELECT c.*,
                (SELECT GROUP_CONCAT(DISTINCT cc2.campus_id ORDER BY cc2.campus_id SEPARATOR ',')
                 FROM coupon_campuses cc2 WHERE cc2.coupon_id = c.id) AS campus_ids,
                (SELECT GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ')
                 FROM coupon_campuses cc2
                 LEFT JOIN organizations o ON cc2.campus_id = o.id
                 WHERE cc2.coupon_id = c.id) AS campus_names,
                (SELECT GROUP_CONCAT(DISTINCT cs2.subject_id ORDER BY cs2.subject_id SEPARATOR ',')
                 FROM coupon_subjects cs2 WHERE cs2.coupon_id = c.id) AS subject_ids,
                (SELECT GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ')
                 FROM coupon_subjects cs2
                 LEFT JOIN subjects s ON cs2.subject_id = s.id
                 WHERE cs2.coupon_id = c.id) AS subject_names,
                (SELECT COUNT(*) FROM coupon_records cr WHERE cr.coupon_id = c.id) AS record_count
            FROM coupons c
            WHERE {$whereSql}
            ORDER BY c.created_at DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['amount'] = (float)$row['discount_amount'];
        unset($row['discount_amount']);
        $row['record_count'] = (int)$row['record_count'];
        $rows[] = $row;
    }

    json(['data' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
}

function addCoupon(PDO $db, string $method, array $query, array $input): void
{
    discountRequirePost($method);
    $name = trim($input['name'] ?? '');
    $couponType = trim($input['coupon_type'] ?? '课程券');
    $planType = trim($input['plan_type'] ?? '');
    $amount = (float)($input['discount_amount'] ?? 0);
    $startDate = trim($input['start_date'] ?? '');
    $endDate = trim($input['end_date'] ?? '');
    validateCoupon($name, $couponType, $amount, $startDate, $endDate);

    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM coupons
         WHERE name = :name AND coupon_type = :coupon_type AND plan_type = :plan_type'
    );
    $stmt->execute([
        ':name' => $name,
        ':coupon_type' => $couponType,
        ':plan_type' => $planType,
    ]);
    if ((int)$stmt->fetchColumn() > 0) {
        json(['error' => '同类型下优惠券名称已存在']);
    }

    $db->beginTransaction();
    try {
        $now = now();
        $stmt = $db->prepare(
            'INSERT INTO coupons
                (name, coupon_type, plan_type, discount_amount, start_date, end_date,
                 created_at, updated_at)
             VALUES
                (:name, :coupon_type, :plan_type, :amount, :start_date, :end_date,
                 :created_at, :updated_at)'
        );
        $stmt->execute([
            ':name' => $name,
            ':coupon_type' => $couponType,
            ':plan_type' => $planType,
            ':amount' => $amount,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $couponId = (int)$db->lastInsertId();

        if (!empty($input['campus_ids'] ?? [])) {
            replaceDiscountRelation(
                $db,
                'coupon_campuses',
                'coupon_id',
                'campus_id',
                $couponId,
                $input['campus_ids']
            );
        }
        if (!empty($input['subject_ids'] ?? [])) {
            replaceDiscountRelation(
                $db,
                'coupon_subjects',
                'coupon_id',
                'subject_id',
                $couponId,
                $input['subject_ids']
            );
        }

        $db->commit();
        json(['message' => '优惠券创建成功', 'id' => (string)$couponId]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        json(['error' => '创建失败: ' . $e->getMessage()]);
    }
}

function validateCoupon(
    string $name,
    string $couponType,
    float $amount,
    string $startDate,
    string $endDate
): void {
    if ($name === '') {
        json(['error' => '优惠券名称不能为空']);
    }
    if (!in_array($couponType, ['课程券', '商品券'], true)) {
        json(['error' => '类型无效']);
    }
    if ($amount <= 0) {
        json(['error' => '优惠金额必须大于0']);
    }
    if ($startDate === '' || $endDate === '') {
        json(['error' => '日期不能为空']);
    }
    if ($endDate < $startDate) {
        json(['error' => '结束日期不能早于开始日期']);
    }
}

function updateCoupon(PDO $db, string $method, array $query, array $input): void
{
    discountRequirePost($method);
    $couponId = (int)($input['id'] ?? 0);
    if ($couponId <= 0) {
        json(['error' => 'ID无效']);
    }

    $stmt = $db->prepare('SELECT * FROM coupons WHERE id = :id');
    $stmt->execute([':id' => $couponId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        json(['error' => '优惠券不存在']);
    }

    $name = trim($input['name'] ?? $existing['name']);
    $couponType = trim($input['coupon_type'] ?? $existing['coupon_type']);
    $planType = trim($input['plan_type'] ?? $existing['plan_type']);
    $amount = (float)($input['discount_amount'] ?? $existing['discount_amount']);
    $startDate = trim($input['start_date'] ?? $existing['start_date']);
    $endDate = trim($input['end_date'] ?? $existing['end_date']);
    validateCoupon($name, $couponType, $amount, $startDate, $endDate);

    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM coupons
         WHERE name = :name
           AND coupon_type = :coupon_type
           AND plan_type = :plan_type
           AND id != :id'
    );
    $stmt->execute([
        ':name' => $name,
        ':coupon_type' => $couponType,
        ':plan_type' => $planType,
        ':id' => $couponId,
    ]);
    if ((int)$stmt->fetchColumn() > 0) {
        json(['error' => '同类型下优惠券名称已存在']);
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'UPDATE coupons
             SET name = :name,
                 coupon_type = :coupon_type,
                 plan_type = :plan_type,
                 discount_amount = :amount,
                 start_date = :start_date,
                 end_date = :end_date,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $name,
            ':coupon_type' => $couponType,
            ':plan_type' => $planType,
            ':amount' => $amount,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':updated_at' => now(),
            ':id' => $couponId,
        ]);

        if (array_key_exists('campus_ids', $input)) {
            replaceDiscountRelation(
                $db,
                'coupon_campuses',
                'coupon_id',
                'campus_id',
                $couponId,
                $input['campus_ids'] ?? []
            );
        }
        if (array_key_exists('subject_ids', $input)) {
            replaceDiscountRelation(
                $db,
                'coupon_subjects',
                'coupon_id',
                'subject_id',
                $couponId,
                $input['subject_ids'] ?? []
            );
        }

        recalculateDiscountedPriceItems($db, 'coupon_id', $couponId);
        $db->commit();
        json(['message' => '优惠券更新成功']);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        json(['error' => '更新失败: ' . $e->getMessage()]);
    }
}

function deleteCoupon(PDO $db, string $method, array $query, array $input): void
{
    discountRequirePost($method);
    $couponId = (int)($input['id'] ?? 0);
    if ($couponId <= 0) {
        json(['error' => 'ID无效']);
    }

    $stmt = $db->prepare('SELECT id FROM coupons WHERE id = :id');
    $stmt->execute([':id' => $couponId]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        json(['error' => '优惠券不存在']);
    }

    $stmt = $db->prepare('DELETE FROM coupons WHERE id = :id');
    $stmt->execute([':id' => $couponId]);
    json(['message' => '优惠券已删除']);
}

function getCoupon(PDO $db, string $method, array $query, array $input): void
{
    $couponId = (int)($query['id'] ?? 0);
    if ($couponId <= 0) {
        json(['error' => 'ID无效']);
    }

    $stmt = $db->prepare('SELECT * FROM coupons WHERE id = :id');
    $stmt->execute([':id' => $couponId]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$coupon) {
        json(['error' => '优惠券不存在']);
    }

    $coupon['amount'] = (float)$coupon['discount_amount'];
    unset($coupon['discount_amount']);
    $coupon['campus_ids'] = getDiscountRelationIds(
        $db,
        'coupon_campuses',
        'coupon_id',
        'campus_id',
        $couponId
    );
    $coupon['subject_ids'] = getDiscountRelationIds(
        $db,
        'coupon_subjects',
        'coupon_id',
        'subject_id',
        $couponId
    );
    json($coupon);
}

function listCouponRecords(PDO $db, string $method, array $query, array $input): void
{
    $page = max(1, (int)($query['page'] ?? 1));
    $pageSize = max(1, min(100, (int)($query['page_size'] ?? 15)));
    $offset = ($page - 1) * $pageSize;
    $keyword = trim($query['keyword'] ?? '');
    $couponId = (int)($query['coupon_id'] ?? 0);
    $dateFrom = trim($query['date_from'] ?? '');
    $dateTo = trim($query['date_to'] ?? '');

    $where = ['1=1'];
    $params = [];
    if ($keyword !== '') {
        $where[] = '(cr.student_name LIKE :student_keyword
            OR cr.phone LIKE :phone_keyword
            OR cr.coupon_name LIKE :coupon_keyword)';
        $like = '%' . $keyword . '%';
        $params[':student_keyword'] = $like;
        $params[':phone_keyword'] = $like;
        $params[':coupon_keyword'] = $like;
    }
    if ($couponId > 0) {
        $where[] = 'cr.coupon_id = :coupon_id';
        $params[':coupon_id'] = $couponId;
    }
    if ($dateFrom !== '') {
        $where[] = 'cr.issued_at >= :date_from';
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $where[] = 'cr.issued_at <= :date_to';
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }
    $whereSql = implode(' AND ', $where);

    $count = $db->prepare(
        "SELECT COUNT(*)
         FROM coupon_records cr
         LEFT JOIN coupons c ON cr.coupon_id = c.id
         WHERE {$whereSql}"
    );
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $stmt = $db->prepare(
        "SELECT cr.id,
                cr.coupon_id,
                cr.student_name,
                cr.phone,
                cr.issuer AS distributor,
                cr.issued_at AS distributed_at,
                cr.created_at,
                COALESCE(cr.coupon_name, c.name) AS coupon_name,
                c.coupon_type,
                c.discount_amount AS amount,
                cr.usage_status
         FROM coupon_records cr
         LEFT JOIN coupons c ON cr.coupon_id = c.id
         WHERE {$whereSql}
         ORDER BY cr.issued_at DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['amount'] = (float)$row['amount'];
        $rows[] = $row;
    }

    json(['data' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
}

function addCouponRecord(PDO $db, string $method, array $query, array $input): void
{
    discountRequirePost($method);
    $couponId = (int)($input['coupon_id'] ?? 0);
    $studentName = trim($input['student_name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $distributor = trim($input['issuer'] ?? $input['distributor'] ?? '');
    $distributedAt = trim($input['issued_at'] ?? $input['distributed_at'] ?? now());

    if ($couponId <= 0) {
        json(['error' => '优惠券ID无效']);
    }
    if ($studentName === '') {
        json(['error' => '学员姓名不能为空']);
    }
    if ($phone === '') {
        json(['error' => '手机号不能为空']);
    }
    if ($distributor === '') {
        json(['error' => '发放人不能为空']);
    }

    $stmt = $db->prepare('SELECT name, coupon_type FROM coupons WHERE id = :id');
    $stmt->execute([':id' => $couponId]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$coupon) {
        json(['error' => '优惠券不存在']);
    }

    $stmt = $db->prepare(
        'INSERT INTO coupon_records
            (coupon_id, coupon_name, student_name, phone, issuer, issued_at, created_at)
         VALUES
            (:coupon_id, :coupon_name, :student_name, :phone, :issuer, :issued_at, :created_at)'
    );
    $stmt->execute([
        ':coupon_id' => $couponId,
        ':coupon_name' => $coupon['name'],
        ':student_name' => $studentName,
        ':phone' => $phone,
        ':issuer' => $distributor,
        ':issued_at' => $distributedAt,
        ':created_at' => now(),
    ]);

    json(['message' => '发放记录添加成功', 'id' => $db->lastInsertId()]);
}

function deleteCouponRecord(PDO $db, string $method, array $query, array $input): void
{
    discountRequirePost($method);
    $recordId = (int)($input['id'] ?? 0);
    if ($recordId <= 0) {
        json(['error' => 'ID无效']);
    }

    $stmt = $db->prepare('SELECT id FROM coupon_records WHERE id = :id');
    $stmt->execute([':id' => $recordId]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        json(['error' => '发放记录不存在']);
    }

    $stmt = $db->prepare('DELETE FROM coupon_records WHERE id = :id');
    $stmt->execute([':id' => $recordId]);
    json(['message' => '发放记录已删除']);
}
