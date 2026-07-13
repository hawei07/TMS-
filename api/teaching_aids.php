<?php

declare(strict_types=1);

/**
 * @return array<string,callable(PDO,string,array,array):void>
 */
function teachingAidApiRoutes(): array
{
    return [
        'add_teaching_aid' => 'addTeachingAid',
        'delete_teaching_aid' => 'deleteTeachingAid',
        'get_teaching_aid' => 'getTeachingAid',
        'list_teaching_aids' => 'listTeachingAids',
        'update_teaching_aid' => 'updateTeachingAid',
        'search_students_for_sale' => 'searchStudentsForSale',
        'list_available_teaching_aids' => 'listAvailableTeachingAids',
        'create_teaching_aid_sale' => 'createTeachingAidSale',
        'list_teaching_aid_sales' => 'listTeachingAidSales',
    ];
}

function teachingAidRequirePost(string $method): void
{
    if ($method !== 'POST') {
        json(['error' => 'Method not allowed']);
    }
}

/**
 * @param array<int|string,mixed>|string $campusIds
 * @return list<int>
 */
function normalizeTeachingAidCampusIds(array|string $campusIds): array
{
    $values = is_string($campusIds) ? explode(',', $campusIds) : $campusIds;
    return array_values(array_filter(array_map('intval', $values), static fn(int $id): bool => $id > 0));
}

/**
 * @param array<int|string,mixed>|string $campusIds
 */
function replaceTeachingAidCampuses(PDO $db, int $teachingAidId, array|string $campusIds): void
{
    $delete = $db->prepare(
        'DELETE FROM teaching_aid_campuses WHERE teaching_aid_id = :teaching_aid_id'
    );
    $delete->execute([':teaching_aid_id' => $teachingAidId]);

    $ids = normalizeTeachingAidCampusIds($campusIds);
    if ($ids === []) {
        return;
    }

    $insert = $db->prepare(
        'INSERT INTO teaching_aid_campuses (teaching_aid_id, campus_id)
         VALUES (:teaching_aid_id, :campus_id)'
    );
    foreach ($ids as $campusId) {
        $insert->execute([
            ':teaching_aid_id' => $teachingAidId,
            ':campus_id' => $campusId,
        ]);
    }
}

function addTeachingAid(PDO $db, string $method, array $query, array $input): void
{
    teachingAidRequirePost($method);
    $name = trim($input['name'] ?? '');
    $unit = trim($input['unit'] ?? '个');
    $subjectId = (int)($input['subject_id'] ?? 0);
    $price = (float)($input['price'] ?? 0);
    $status = trim($input['status'] ?? '上架');
    $remark = trim($input['remark'] ?? '');
    $type = trim($input['type'] ?? '画具');
    $campusIds = $input['campus_ids'] ?? [];

    if ($name === '') {
        json(['error' => '画具名称不能为空']);
    }
    if ($unit === '') {
        json(['error' => '计量单位不能为空']);
    }
    if ($subjectId <= 0) {
        json(['error' => '请选择学科']);
    }
    if ($price < 0) {
        json(['error' => '售价不能为负数']);
    }
    if (!in_array($status, ['上架', '下架'], true)) {
        json(['error' => '状态无效']);
    }
    if (!in_array($type, ['教材包', '画具'], true)) {
        json(['error' => '画具类型无效']);
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'INSERT INTO teaching_aids
                (name, unit, subject_id, type, price, status, remark, created_at)
             VALUES
                (:name, :unit, :subject_id, :type, :price, :status, :remark, :created_at)'
        );
        $stmt->execute([
            ':name' => $name,
            ':unit' => $unit,
            ':subject_id' => $subjectId,
            ':type' => $type,
            ':price' => $price,
            ':status' => $status,
            ':remark' => $remark,
            ':created_at' => now(),
        ]);
        $teachingAidId = (int)$db->lastInsertId();

        if (!empty($campusIds)) {
            replaceTeachingAidCampuses($db, $teachingAidId, $campusIds);
        }

        $db->commit();
        json(['id' => (string)$teachingAidId, 'message' => '画具添加成功']);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        json(['error' => '添加失败：' . $e->getMessage()]);
    }
}

function deleteTeachingAid(PDO $db, string $method, array $query, array $input): void
{
    teachingAidRequirePost($method);
    $teachingAidId = (int)($input['id'] ?? 0);
    if ($teachingAidId <= 0) {
        json(['error' => 'ID无效']);
    }

    $stmt = $db->prepare('SELECT id FROM teaching_aids WHERE id = :id');
    $stmt->execute([':id' => $teachingAidId]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        json(['error' => '画具不存在']);
    }

    $stmt = $db->prepare('DELETE FROM teaching_aids WHERE id = :id');
    $stmt->execute([':id' => $teachingAidId]);
    json(['message' => '画具已删除']);
}

function getTeachingAid(PDO $db, string $method, array $query, array $input): void
{
    $teachingAidId = (int)($query['id'] ?? 0);
    if ($teachingAidId <= 0) {
        json(['error' => 'ID无效']);
    }

    $stmt = $db->prepare(
        'SELECT ta.*, s.name AS subject_name
         FROM teaching_aids ta
         LEFT JOIN subjects s ON ta.subject_id = s.id
         WHERE ta.id = :id'
    );
    $stmt->execute([':id' => $teachingAidId]);
    $teachingAid = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$teachingAid) {
        json(['error' => '画具不存在']);
    }

    $stmt = $db->prepare(
        'SELECT campus_id
         FROM teaching_aid_campuses
         WHERE teaching_aid_id = :id
         ORDER BY campus_id'
    );
    $stmt->execute([':id' => $teachingAidId]);
    $campusIds = array_map(
        static fn(array $row): int => (int)$row['campus_id'],
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );

    $teachingAid['price'] = (float)$teachingAid['price'];
    $teachingAid['campus_ids'] = $campusIds;
    json(['data' => $teachingAid]);
}

function listTeachingAids(PDO $db, string $method, array $query, array $input): void
{
    $page = max(1, (int)($query['page'] ?? 1));
    $pageSize = max(1, min(100, (int)($query['page_size'] ?? 20)));
    $keyword = trim($query['keyword'] ?? '');
    $offset = ($page - 1) * $pageSize;

    $where = ['1=1'];
    $params = [];
    if ($keyword !== '') {
        $where[] = 'ta.name LIKE :keyword';
        $params[':keyword'] = '%' . $keyword . '%';
    }
    $whereSql = implode(' AND ', $where);

    $count = $db->prepare("SELECT COUNT(*) FROM teaching_aids ta WHERE {$whereSql}");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $sql = "SELECT ta.*,
                s.name AS subject_name,
                (SELECT GROUP_CONCAT(DISTINCT tac2.campus_id ORDER BY tac2.campus_id SEPARATOR ',')
                 FROM teaching_aid_campuses tac2
                 WHERE tac2.teaching_aid_id = ta.id) AS campus_ids,
                (SELECT GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ')
                 FROM teaching_aid_campuses tac2
                 LEFT JOIN organizations o ON tac2.campus_id = o.id
                 WHERE tac2.teaching_aid_id = ta.id) AS campus_names
            FROM teaching_aids ta
            LEFT JOIN subjects s ON ta.subject_id = s.id
            WHERE {$whereSql}
            ORDER BY ta.id DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['price'] = (float)$row['price'];
        $rows[] = $row;
    }

    json(['data' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
}

function updateTeachingAid(PDO $db, string $method, array $query, array $input): void
{
    teachingAidRequirePost($method);
    $teachingAidId = (int)($input['id'] ?? 0);
    if ($teachingAidId <= 0) {
        json(['error' => 'ID无效']);
    }

    $stmt = $db->prepare('SELECT * FROM teaching_aids WHERE id = :id');
    $stmt->execute([':id' => $teachingAidId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        json(['error' => '画具不存在']);
    }

    $name = trim($input['name'] ?? $existing['name']);
    $unit = trim($input['unit'] ?? $existing['unit']);
    $subjectId = isset($input['subject_id'])
        ? (int)$input['subject_id']
        : (int)$existing['subject_id'];
    $price = isset($input['price']) ? (float)$input['price'] : (float)$existing['price'];
    $status = trim($input['status'] ?? $existing['status']);
    $remark = isset($input['remark']) ? trim($input['remark']) : $existing['remark'];
    $type = trim($input['type'] ?? $existing['type']);
    $campusIds = $input['campus_ids'] ?? null;

    if ($name === '') {
        json(['error' => '画具名称不能为空']);
    }
    if ($unit === '') {
        json(['error' => '计量单位不能为空']);
    }
    if ($subjectId <= 0) {
        json(['error' => '请选择学科']);
    }
    if ($price < 0) {
        json(['error' => '售价不能为负数']);
    }
    if (!in_array($status, ['上架', '下架'], true)) {
        json(['error' => '状态无效']);
    }
    if (!in_array($type, ['教材包', '画具'], true)) {
        json(['error' => '画具类型无效']);
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'UPDATE teaching_aids
             SET name = :name,
                 unit = :unit,
                 subject_id = :subject_id,
                 type = :type,
                 price = :price,
                 status = :status,
                 remark = :remark,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $name,
            ':unit' => $unit,
            ':subject_id' => $subjectId,
            ':type' => $type,
            ':price' => $price,
            ':status' => $status,
            ':remark' => $remark,
            ':id' => $teachingAidId,
        ]);

        if ($campusIds !== null) {
            replaceTeachingAidCampuses($db, $teachingAidId, $campusIds);
        }

        $db->commit();
        json(['message' => '画具更新成功']);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        json(['error' => '更新失败：' . $e->getMessage()]);
    }
}

function searchStudentsForSale(PDO $db, string $method, array $query, array $input): void
{
    $keyword = trim($query['keyword'] ?? '');
    if (strlen($keyword) < 1) {
        json(['data' => []]);
    }

    $stmt = $db->prepare(
        "SELECT s.id,
                s.name,
                s.student_no,
                (SELECT GROUP_CONCAT(DISTINCT o.campus SEPARATOR ', ')
                 FROM orders o
                 WHERE o.student_id = s.id) AS campus
         FROM students s
         WHERE s.name LIKE :name_keyword OR s.student_no LIKE :number_keyword
         ORDER BY s.name
         LIMIT 20"
    );
    $stmt->execute([
        ':name_keyword' => '%' . $keyword . '%',
        ':number_keyword' => '%' . $keyword . '%',
    ]);

    json(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function listAvailableTeachingAids(PDO $db, string $method, array $query, array $input): void
{
    $keyword = trim($query['keyword'] ?? '');
    $where = ["ta.status = '上架'"];
    $params = [];
    if ($keyword !== '') {
        $where[] = 'ta.name LIKE :keyword';
        $params[':keyword'] = '%' . $keyword . '%';
    }
    $whereSql = implode(' AND ', $where);

    $sql = "SELECT ta.id,
                   ta.name,
                   ta.unit,
                   ta.price,
                   ta.type,
                   ta.remark,
                   ta.subject_id,
                   ta.status,
                   s.name AS subject_name,
                   (SELECT GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ')
                    FROM teaching_aid_campuses tac2
                    LEFT JOIN organizations o ON tac2.campus_id = o.id
                    WHERE tac2.teaching_aid_id = ta.id) AS campus_names
            FROM teaching_aids ta
            LEFT JOIN subjects s ON ta.subject_id = s.id
            WHERE {$whereSql}
            ORDER BY ta.id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['price'] = (float)$row['price'];
        $rows[] = $row;
    }

    json(['data' => $rows]);
}

function createTeachingAidSale(PDO $db, string $method, array $query, array $input): void
{
    teachingAidRequirePost($method);
    $studentId = (int)($input['student_id'] ?? 0);
    if ($studentId <= 0) {
        json(['error' => '请选择学员']);
    }

    $stmt = $db->prepare(
        "SELECT s.id,
                s.name,
                s.student_no,
                (SELECT GROUP_CONCAT(DISTINCT o.campus SEPARATOR ', ')
                 FROM orders o
                 WHERE o.student_id = s.id) AS campus
         FROM students s
         WHERE s.id = :student_id"
    );
    $stmt->execute([':student_id' => $studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        json(['error' => '学员不存在']);
    }

    $items = $input['items'] ?? [];
    if (empty($items) || !is_array($items)) {
        json(['error' => '请选择商品']);
    }

    $cashAmount = (float)($input['cash_amount'] ?? 0);
    $meituanAmount = (float)($input['meituan_amount'] ?? 0);
    $accountAmount = (float)($input['account_amount'] ?? 0);
    $remark = trim($input['remark'] ?? '');
    $selectedCampus = trim($input['campus'] ?? '');
    $studentCampus = $student['campus'] ?? '';

    $total = 0.0;
    $itemDetails = [];
    $itemStmt = $db->prepare(
        "SELECT ta.id,
                ta.name,
                ta.price,
                ta.type,
                ta.remark,
                (SELECT GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ')
                 FROM teaching_aid_campuses tac
                 LEFT JOIN organizations o ON tac.campus_id = o.id
                 WHERE tac.teaching_aid_id = ta.id) AS campus_names
         FROM teaching_aids ta
         WHERE ta.id = :id AND ta.status = '上架'"
    );

    foreach ($items as $item) {
        $quantity = max(1, (int)($item['quantity'] ?? 1));
        $teachingAidId = (int)($item['teaching_aid_id'] ?? 0);
        $itemStmt->execute([':id' => $teachingAidId]);
        $teachingAid = $itemStmt->fetch(PDO::FETCH_ASSOC);
        if (!$teachingAid) {
            json(['error' => '画具不存在或已下架（ID:' . $teachingAidId . '）']);
        }

        $unitPrice = (float)$teachingAid['price'];
        $itemTotal = round($unitPrice * $quantity, 2);
        $total += $itemTotal;
        $itemDetails[] = [
            'teaching_aid' => $teachingAid,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => $itemTotal,
        ];
    }

    $paymentTotal = round($cashAmount + $meituanAmount + $accountAmount, 2);
    if (abs($paymentTotal - $total) > 0.01) {
        json(['error' => '支付金额与商品总价不匹配（支付：' . $paymentTotal . '，商品：' . $total . '）']);
    }

    $db->beginTransaction();
    try {
        $newBalance = 0.0;
        if ($accountAmount > 0) {
            $newBalance = deductTeachingAidAccountBalance($db, $studentId, $accountAmount);
        }

        $saleIds = [];
        $soldAt = now();

        foreach ($itemDetails as $itemDetail) {
            $teachingAid = $itemDetail['teaching_aid'];
            $itemTotal = $itemDetail['total_amount'];
            $ratio = $total > 0 ? $itemTotal / $total : 0;
            $itemCash = round($cashAmount * $ratio, 2);
            $itemMeituan = round($meituanAmount * $ratio, 2);
            $itemAccount = round($accountAmount * $ratio, 2);
            $itemCampus = $selectedCampus ?: ($teachingAid['campus_names'] ?: $studentCampus);
            $totalAfterTax = calculateTeachingAidAfterTax($db, $itemTotal, $itemCampus);

            $saleId = insertTeachingAidSale(
                $db,
                $teachingAid,
                $student,
                $itemDetail['quantity'],
                $itemDetail['unit_price'],
                $itemTotal,
                $totalAfterTax,
                $itemCash,
                $itemMeituan,
                $itemAccount,
                $itemCampus,
                $soldAt,
                $remark
            );
            $saleIds[] = $saleId;

            if ($itemAccount > 0) {
                insertTeachingAidAccountTransaction(
                    $db,
                    $studentId,
                    $itemAccount,
                    $newBalance,
                    $saleId,
                    $itemCampus,
                    $teachingAid['name']
                );
            }
        }

        $db->commit();
        json(['success' => true, 'sale_ids' => $saleIds, 'message' => '购买成功']);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        json(['error' => '购买失败：' . $e->getMessage()]);
    }
}

function deductTeachingAidAccountBalance(PDO $db, int $studentId, float $amount): float
{
    $stmt = $db->prepare(
        'SELECT balance FROM student_accounts WHERE student_id = :student_id FOR UPDATE'
    );
    $stmt->execute([':student_id' => $studentId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentBalance = $account ? (float)$account['balance'] : 0.0;

    if ($currentBalance < $amount) {
        $db->rollBack();
        json([
            'error' => '账户余额不足（当前余额：¥' . number_format($currentBalance, 2)
                . '，需要：¥' . number_format($amount, 2) . '）',
        ]);
    }

    $newBalance = round($currentBalance - $amount, 2);
    $stmt = $db->prepare(
        'INSERT INTO student_accounts
            (student_id, balance, total_deposit, total_consume, total_refund)
         VALUES
            (:student_id, 0, 0, 0, 0)
         ON DUPLICATE KEY UPDATE
            balance = :balance,
            total_consume = total_consume + :total_consume'
    );
    $stmt->execute([
        ':student_id' => $studentId,
        ':balance' => $newBalance,
        ':total_consume' => $amount,
    ]);

    return $newBalance;
}

function calculateTeachingAidAfterTax(PDO $db, float $total, string $campus): float
{
    if ($campus === '') {
        return $total;
    }

    $firstCampus = trim(explode(',', $campus)[0]);
    $stmt = $db->prepare(
        "SELECT COALESCE(t.product_tax_rate, 0) AS rate
         FROM tax_rates t
         JOIN organizations o ON t.campus_id = o.id
         WHERE o.name = :campus AND o.type = '校区'"
    );
    $stmt->execute([':campus' => $firstCampus]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $taxRate = (float)($row['rate'] ?? 0);

    return $taxRate > 0 ? round($total / (1 + $taxRate / 100), 2) : $total;
}

/**
 * @param array<string,mixed> $teachingAid
 * @param array<string,mixed> $student
 */
function insertTeachingAidSale(
    PDO $db,
    array $teachingAid,
    array $student,
    int $quantity,
    float $unitPrice,
    float $totalAmount,
    float $totalAfterTax,
    float $cashAmount,
    float $meituanAmount,
    float $accountAmount,
    string $campus,
    string $soldAt,
    string $remark
): string {
    $stmt = $db->prepare(
        'INSERT INTO teaching_aid_sales
            (teaching_aid_id, student_id, student_name, student_no, teaching_aid_name,
             type, quantity, unit_price, total_amount, total_after_tax, cash_amount,
             meituan_amount, account_amount, campus, sold_at, sold_by, remark, created_at)
         VALUES
            (:teaching_aid_id, :student_id, :student_name, :student_no, :teaching_aid_name,
             :type, :quantity, :unit_price, :total_amount, :total_after_tax, :cash_amount,
             :meituan_amount, :account_amount, :campus, :sold_at, :sold_by, :remark, :created_at)'
    );
    $stmt->execute([
        ':teaching_aid_id' => (int)$teachingAid['id'],
        ':student_id' => (int)$student['id'],
        ':student_name' => $student['name'],
        ':student_no' => $student['student_no'] ?? '',
        ':teaching_aid_name' => $teachingAid['name'],
        ':type' => $teachingAid['type'],
        ':quantity' => $quantity,
        ':unit_price' => $unitPrice,
        ':total_amount' => $totalAmount,
        ':total_after_tax' => $totalAfterTax,
        ':cash_amount' => $cashAmount,
        ':meituan_amount' => $meituanAmount,
        ':account_amount' => $accountAmount,
        ':campus' => $campus,
        ':sold_at' => $soldAt,
        ':sold_by' => '',
        ':remark' => $remark,
        ':created_at' => $soldAt,
    ]);

    return $db->lastInsertId();
}

function insertTeachingAidAccountTransaction(
    PDO $db,
    int $studentId,
    float $amount,
    float $balanceAfter,
    string $saleId,
    string $campus,
    string $teachingAidName
): void {
    $stmt = $db->prepare(
        "INSERT INTO account_transactions
            (student_id, type, amount, balance_after, ref_type, ref_id, campus, note)
         VALUES
            (:student_id, 'consume', :amount, :balance_after, 'teaching_aid_sale',
             :ref_id, :campus, :note)"
    );
    $stmt->execute([
        ':student_id' => $studentId,
        ':amount' => $amount,
        ':balance_after' => $balanceAfter,
        ':ref_id' => (int)$saleId,
        ':campus' => $campus,
        ':note' => '购买画具：' . $teachingAidName,
    ]);
}

function listTeachingAidSales(PDO $db, string $method, array $query, array $input): void
{
    $page = max(1, (int)($query['page'] ?? 1));
    $pageSize = max(1, min(100, (int)($query['page_size'] ?? 20)));
    $offset = ($page - 1) * $pageSize;
    $filters = [
        'student_name' => trim($query['student_name'] ?? ''),
        'teaching_aid_name' => trim($query['teaching_aid_name'] ?? ''),
        'date_from' => trim($query['date_from'] ?? ''),
        'date_to' => trim($query['date_to'] ?? ''),
    ];

    $where = ['1=1'];
    $params = [];
    if ($filters['student_name'] !== '') {
        $where[] = 'student_name LIKE :student_name';
        $params[':student_name'] = '%' . $filters['student_name'] . '%';
    }
    if ($filters['teaching_aid_name'] !== '') {
        $where[] = 'teaching_aid_name LIKE :teaching_aid_name';
        $params[':teaching_aid_name'] = '%' . $filters['teaching_aid_name'] . '%';
    }
    if ($filters['date_from'] !== '') {
        $where[] = 'DATE(sold_at) >= :date_from';
        $params[':date_from'] = $filters['date_from'];
    }
    if ($filters['date_to'] !== '') {
        $where[] = 'DATE(sold_at) <= :date_to';
        $params[':date_to'] = $filters['date_to'];
    }
    $whereSql = implode(' AND ', $where);

    $count = $db->prepare("SELECT COUNT(*) FROM teaching_aid_sales WHERE {$whereSql}");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $stmt = $db->prepare(
        "SELECT * FROM teaching_aid_sales
         WHERE {$whereSql}
         ORDER BY sold_at DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['unit_price'] = (float)$row['unit_price'];
        $row['total_amount'] = (float)$row['total_amount'];
        $row['total_after_tax'] = $row['total_after_tax'] !== null
            ? (float)$row['total_after_tax']
            : (float)$row['total_amount'];
        $row['cash_amount'] = (float)$row['cash_amount'];
        $row['meituan_amount'] = (float)$row['meituan_amount'];
        $row['account_amount'] = (float)$row['account_amount'];
        $rows[] = $row;
    }

    json(['data' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
}
