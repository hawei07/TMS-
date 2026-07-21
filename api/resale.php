<?php

declare(strict_types=1);

/**
 * @return array<string,callable(PDO,string,array,array):void>
 */
function resaleApiRoutes(): array
{
    return [
        'resale_create' => 'resaleCreate',
        'resale_list'   => 'resaleList',
        'resale_detail' => 'resaleDetail',
    ];
}

/**
 * 创建转卖记录
 * POST body: {seller_order_id, buyer_type, buyer_student_id?, buyer_resource_id?, transfer_lessons, buyer_amount, created_by?,
 *             seller_source_type?, seller_transfer_record_id?}
 * seller_source_type: 'order'(default) | 'course_transfer' | 'school_transfer'
 */
function resaleCreate(PDO $db, string $method, array $query, array $input): void
{
    if ($method !== 'POST') {
        json(['error' => 'Method not allowed']);
    }

    $sellerOrderId    = (int)($input['seller_order_id'] ?? 0);
    $buyerType        = trim($input['buyer_type'] ?? 'student');
    $buyerStudentId   = (int)($input['buyer_student_id'] ?? 0);
    $buyerResourceId  = (int)($input['buyer_resource_id'] ?? 0);
    $transferLessons  = (float)($input['transfer_lessons'] ?? 0);
    $buyerAmount      = (float)($input['buyer_amount'] ?? 0);
    $sellerSourceType = trim($input['seller_source_type'] ?? 'order');
    $sellerTransferRecordId = (int)($input['seller_transfer_record_id'] ?? 0);

    if ($transferLessons <= 0 || $buyerAmount <= 0) {
        json(['success' => false, 'message' => '参数不完整']);
    }
    if ($sellerSourceType === 'order' && $sellerOrderId <= 0) {
        json(['success' => false, 'message' => '参数不完整']);
    }
    if (($sellerSourceType === 'course_transfer' || $sellerSourceType === 'school_transfer') && $sellerTransferRecordId <= 0) {
        json(['success' => false, 'message' => '参数不完整']);
    }
    if (!in_array($sellerSourceType, ['order', 'course_transfer', 'school_transfer'], true)) {
        json(['success' => false, 'message' => '无效的卖方来源类型']);
    }
    if ($buyerType === 'student' && $buyerStudentId <= 0) {
        json(['success' => false, 'message' => '请选择买方学员']);
    }
    if ($buyerType === 'resource' && $buyerResourceId <= 0) {
        json(['success' => false, 'message' => '请选择买方资源']);
    }
    if (!in_array($buyerType, ['student', 'resource'], true)) {
        json(['success' => false, 'message' => '无效的买入方类型']);
    }

    $db->beginTransaction();
    try {
        // ===== 1. 根据来源类型锁定并获取卖方源数据 =====
        $sellerStudentId = 0;
        $lessonCount     = 0;
        $consumedLessons = 0;
        $transferred     = 0;
        $resaleLessons   = 0;
        $actualPrice     = 0.0;
        $courseId        = 0;
        $courseName      = '';
        $campusName      = '';
        $campusId        = 0;
        $itemName        = '';
        $planName        = '';

        if ($sellerSourceType === 'course_transfer') {
            // 转课课包：从 course_transfer_records 获取
            $stmt = $db->prepare("SELECT * FROM course_transfer_records WHERE id = :id AND status = '正常' FOR UPDATE");
            $stmt->execute([':id' => $sellerTransferRecordId]);
            $ctr = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ctr) {
                throw new RuntimeException('转课记录不存在或已失效');
            }
            $sellerStudentId = (int)$ctr['student_id'];
            $lessonCount     = (int)($ctr['target_lessons'] ?? 0);
            $consumedLessons = (int)($ctr['consumed_lessons'] ?? 0);
            $transferred     = 0; // 转课课包的转出由后续转课/转校记录追踪
            $resaleLessons   = (int)($ctr['resale_lessons'] ?? 0);
            $actualPrice     = (float)($ctr['target_value'] ?? 0);
            $courseId        = (int)($ctr['target_course_id'] ?? 0);
            $courseName      = $ctr['target_course_name'] ?? '';
            $campusName      = $ctr['campus'] ?? '';
            $itemName        = $ctr['target_course_name'] ?? '';
            $planName        = '转卖(转课课包)';
        } elseif ($sellerSourceType === 'school_transfer') {
            // 转校课包：从 transfer_records 获取
            $stmt = $db->prepare("SELECT * FROM transfer_records WHERE id = :id AND status = '已通过' FOR UPDATE");
            $stmt->execute([':id' => $sellerTransferRecordId]);
            $tr = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tr) {
                throw new RuntimeException('转校记录不存在或未通过');
            }
            $sellerStudentId = (int)$tr['student_id'];
            $lessonCount     = (int)($tr['transfer_lessons'] ?? 0);
            $consumedLessons = 0;
            $transferred     = (int)($tr['total_transferred'] ?? 0);
            $resaleLessons   = (int)($tr['resale_lessons'] ?? 0);
            $actualPrice     = (float)($tr['transfer_amount'] ?? 0);
            $courseId        = (int)($tr['course_id'] ?? 0);
            $courseName      = $tr['course_name'] ?? '';
            $campusName      = $tr['to_campus'] ?? '';
            $itemName        = ($tr['item_name'] ?? '') . '（转校）';
            $planName        = '转卖(转校课包)';
        } else {
            // 普通订单
            $stmt = $db->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $sellerOrderId]);
            $sellerOrder = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sellerOrder) {
                throw new RuntimeException('订单不存在');
            }
            $sellerStudentId = (int)$sellerOrder['student_id'];
            $lessonCount     = (int)($sellerOrder['lesson_count'] ?? 0);
            $consumedLessons = (int)($sellerOrder['consumed_lessons'] ?? 0);
            $transferred     = (int)($sellerOrder['transferred_lessons'] ?? 0);
            $resaleLessons   = (int)($sellerOrder['resale_lessons'] ?? 0);
            $actualPrice     = (float)($sellerOrder['actual_price'] ?? 0);
            $courseId        = (int)$sellerOrder['course_id'];
            $campusName      = $sellerOrder['campus'] ?? '';
            $campusId        = (int)($sellerOrder['campus_id'] ?? 0);
            $itemName        = $sellerOrder['item_name'] ?? '';
            $planName        = '转卖';
        }

        $remaining = $lessonCount - $consumedLessons - $transferred - $resaleLessons;
        if ($transferLessons > $remaining) {
            throw new RuntimeException("可转卖课时不足，剩余 {$remaining} 课时");
        }

        // 2. 计算卖出金额（按课时比例）
        $transferAmount = $lessonCount > 0
            ? round($actualPrice * $transferLessons / $lessonCount, 2)
            : 0;

        if ($buyerAmount > $transferAmount) {
            throw new RuntimeException('买入金额不能超过卖出金额');
        }

        // 3. 处理买方（若为资源，自动创建学员）
        if ($buyerType === 'resource') {
            $stmt = $db->prepare('SELECT * FROM resources WHERE id = :id');
            $stmt->execute([':id' => $buyerResourceId]);
            $resource = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resource) {
                throw new RuntimeException('资源不存在');
            }

            $resPhone = $resource['phone'] ?? '';
            if ($resPhone !== '') {
                $stmt = $db->prepare('SELECT id FROM students WHERE phone = :phone AND phone != \'\'');
                $stmt->execute([':phone' => $resPhone]);
                $existingStudent = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($existingStudent) {
                    $buyerStudentId = (int)$existingStudent['id'];
                }
            }

            if ($buyerStudentId <= 0) {
                $studentNo = generateStudentNo($db);
                $stmt = $db->prepare(
                    'INSERT INTO students (student_no, name, phone, resource_id, created_at)
                     VALUES (:student_no, :name, :phone, :resource_id, NOW())'
                );
                $stmt->execute([
                    ':student_no'  => $studentNo,
                    ':name'        => $resource['name'] ?? '',
                    ':phone'       => $resPhone,
                    ':resource_id' => $buyerResourceId,
                ]);
                $buyerStudentId = (int)$db->lastInsertId();
            }
        }

        // 4. 校验买方 ≠ 卖方
        if ($buyerStudentId === $sellerStudentId) {
            throw new RuntimeException('买方与卖方不能是同一人');
        }

        // 5. 查询税率（按校区）
        $taxRate = 0.0;
        if ($campusName !== '') {
            $stmt = $db->prepare(
                'SELECT t.course_tax_rate FROM tax_rates t
                 JOIN organizations o ON t.campus_id = o.id
                 WHERE o.name = :campus AND o.type = \'校区\''
            );
            $stmt->execute([':campus' => $campusName]);
            $taxRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $taxRate = $taxRow ? (float)$taxRow['course_tax_rate'] : 0.0;
        }

        $confirmedRevenue = round($transferAmount - $buyerAmount, 2);
        $confirmedRevenueAfterTax = $taxRate > 0
            ? round($confirmedRevenue * (1 - $taxRate / 100), 2)
            : $confirmedRevenue;

        // 校区 ID
        if ($campusId <= 0 && $campusName !== '') {
            $stmt = $db->prepare('SELECT id FROM organizations WHERE name = :name AND type = \'校区\'');
            $stmt->execute([':name' => $campusName]);
            $campusId = (int)($stmt->fetchColumn() ?: 0);
        }

        $isFullTransfer = (abs($remaining - $transferLessons) < 0.01) ? 1 : 0;
        $operator = trim($input['created_by'] ?? '');
        $now = date('Y-m-d H:i:s');

        // 6. INSERT resale_records
        $stmt = $db->prepare(
            'INSERT INTO resale_records
                (seller_student_id, seller_order_id, buyer_student_id, buyer_resource_id,
                 buyer_type, course_id, transfer_lessons, is_full_transfer,
                 transfer_amount, buyer_amount, confirmed_revenue,
                 confirmed_revenue_after_tax, tax_rate, campus_id, campus_name,
                 status, created_by, created_at, updated_at)
             VALUES
                (:seller_student_id, :seller_order_id, :buyer_student_id, :buyer_resource_id,
                 :buyer_type, :course_id, :transfer_lessons, :is_full_transfer,
                 :transfer_amount, :buyer_amount, :confirmed_revenue,
                 :confirmed_revenue_after_tax, :tax_rate, :campus_id, :campus_name,
                 :status, :created_by, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':seller_student_id'          => $sellerStudentId,
            ':seller_order_id'            => $sellerSourceType === 'order' ? $sellerOrderId : 0,
            ':buyer_student_id'           => $buyerStudentId,
            ':buyer_resource_id'          => $buyerResourceId,
            ':buyer_type'                 => $buyerType,
            ':course_id'                  => $courseId,
            ':transfer_lessons'           => $transferLessons,
            ':is_full_transfer'           => $isFullTransfer,
            ':transfer_amount'            => $transferAmount,
            ':buyer_amount'               => $buyerAmount,
            ':confirmed_revenue'          => $confirmedRevenue,
            ':confirmed_revenue_after_tax'=> $confirmedRevenueAfterTax,
            ':tax_rate'                   => $taxRate,
            ':campus_id'                  => $campusId,
            ':campus_name'                => $campusName,
            ':status'                     => 'confirmed',
            ':created_by'                 => $operator,
            ':created_at'                 => $now,
            ':updated_at'                 => $now,
        ]);
        $resaleId = (int)$db->lastInsertId();

        // 7. UPDATE 卖方源记录：累计已转卖课时
        if ($sellerSourceType === 'course_transfer') {
            $stmt = $db->prepare('UPDATE course_transfer_records SET resale_lessons = resale_lessons + :add WHERE id = :id');
            $stmt->execute([':add' => $transferLessons, ':id' => $sellerTransferRecordId]);
        } elseif ($sellerSourceType === 'school_transfer') {
            $stmt = $db->prepare('UPDATE transfer_records SET resale_lessons = resale_lessons + :add WHERE id = :id');
            $stmt->execute([':add' => $transferLessons, ':id' => $sellerTransferRecordId]);
        } else {
            $stmt = $db->prepare('UPDATE orders SET resale_lessons = resale_lessons + :add WHERE id = :id');
            $stmt->execute([':add' => $transferLessons, ':id' => $sellerOrderId]);
        }

        // 8. CREATE buyer order（买方新报读订单）
        $buyerOrderNo = generateOrderNo($db);
        $newItemName  = $itemName . '(转卖)';
        $stmt = $db->prepare(
            'INSERT INTO orders
                (student_id, course_id, lesson_count, actual_price, teaching_aid_price,
                 product_coupon_amount, campus, order_no, plan_name, item_name,
                 status, is_voided, is_resale_received, created_at,
                 discount_plan_amount, coupon_amount)
             VALUES
                (:student_id, :course_id, :lesson_count, :actual_price, 0,
                 0, :campus, :order_no, :plan_name, :item_name,
                 :status, :is_voided, :is_resale_received, :created_at,
                 0, 0)'
        );
        $stmt->execute([
            ':student_id'          => $buyerStudentId,
            ':course_id'           => $courseId,
            ':lesson_count'        => $transferLessons,
            ':actual_price'        => $buyerAmount,
            ':campus'              => $campusName,

            ':order_no'            => $buyerOrderNo,
            ':plan_name'           => $planName,
            ':item_name'           => $newItemName,
            ':status'              => '已报名',
            ':is_voided'           => '否',
            ':is_resale_received'  => '是',
            ':created_at'          => $now,
        ]);
        $buyerOrderId = (int)$db->lastInsertId();

        // 9. UPDATE resale_records with buyer_order_id
        $stmt = $db->prepare('UPDATE resale_records SET buyer_order_id = :oid WHERE id = :id');
        $stmt->execute([':oid' => $buyerOrderId, ':id' => $resaleId]);

        $db->commit();
        json([
            'success'           => true,
            'resale_id'         => $resaleId,
            'buyer_order_id'    => $buyerOrderId,
            'confirmed_revenue' => $confirmedRevenue,
        ]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('resale_create failed: ' . $e->getMessage());
        json(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * 查询转卖记录列表
 * GET params: campus, keyword, date_from, date_to, page, page_size
 */
function resaleList(PDO $db, string $method, array $query, array $input): void
{
    if ($method !== 'GET') {
        json(['error' => 'Method not allowed']);
    }

    $campus   = trim($query['campus'] ?? '');
    $keyword  = trim($query['keyword'] ?? '');
    $dateFrom = trim($query['date_from'] ?? '');
    $dateTo   = trim($query['date_to'] ?? '');
    $page     = max(1, (int)($query['page'] ?? 1));
    $pageSize = min(50, max(1, (int)($query['page_size'] ?? 20)));

    $conditions = [];
    $params     = [];

    if ($campus !== '') {
        $conditions[]    = 'rr.campus_name = :campus';
        $params[':campus'] = $campus;
    }
    if ($dateFrom !== '') {
        $conditions[]      = 'rr.created_at >= :date_from';
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $conditions[]    = 'rr.created_at <= :date_to';
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }
    if ($keyword !== '') {
        $conditions[] = '(ss.name LIKE :kw1 OR ss.student_no LIKE :kw2 OR c.name LIKE :kw3)';
        $kw = '%' . $keyword . '%';
        $params[':kw1'] = $kw;
        $params[':kw2'] = $kw;
        $params[':kw3'] = $kw;
    }

    $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // 总数
    $countSql = "SELECT COUNT(*)
                 FROM resale_records rr
                 LEFT JOIN students ss ON ss.id = rr.seller_student_id
                 LEFT JOIN courses c ON c.id = rr.course_id
                 {$where}";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // 分页数据
    $offset   = ($page - 1) * $pageSize;
    $dataSql  = "SELECT rr.*,
                        ss.name AS seller_name, ss.student_no AS seller_no,
                        bs.name AS buyer_name, bs.student_no AS buyer_no,
                        c.name AS course_name
                 FROM resale_records rr
                 LEFT JOIN students ss ON ss.id = rr.seller_student_id
                 LEFT JOIN students bs ON bs.id = rr.buyer_student_id
                 LEFT JOIN courses c ON c.id = rr.course_id
                 {$where}
                 ORDER BY rr.id DESC
                 LIMIT {$pageSize} OFFSET {$offset}";
    $dataStmt = $db->prepare($dataSql);
    $dataStmt->execute($params);
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    json([
        'data'      => $rows,
        'total'     => $total,
        'page'      => $page,
        'page_size' => $pageSize,
    ]);
}

/**
 * 单条转卖记录详情
 * GET params: id
 */
function resaleDetail(PDO $db, string $method, array $query, array $input): void
{
    if ($method !== 'GET') {
        json(['error' => 'Method not allowed']);
    }

    $id = (int)($query['id'] ?? 0);
    if ($id <= 0) {
        json(['success' => false, 'message' => '参数错误：缺少记录ID']);
    }

    $stmt = $db->prepare(
        'SELECT rr.*,
                ss.name AS seller_name, ss.student_no AS seller_no, ss.phone AS seller_phone,
                bs.name AS buyer_name, bs.student_no AS buyer_no, bs.phone AS buyer_phone,
                c.name AS course_name, c.subject_level1, c.subject_level2
         FROM resale_records rr
         LEFT JOIN students ss ON ss.id = rr.seller_student_id
         LEFT JOIN students bs ON bs.id = rr.buyer_student_id
         LEFT JOIN courses c ON c.id = rr.course_id
         WHERE rr.id = :id'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json(['success' => false, 'message' => '转卖记录不存在']);
    }

    json(['success' => true, 'data' => $row]);
}
