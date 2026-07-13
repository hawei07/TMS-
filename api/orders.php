<?php

declare(strict_types=1);

/**
 * @return array<string,callable(PDO,string,array,array):void>
 */
function orderApiRoutes(): array
{
    return [
        'list_price_plans' => 'listPricePlans',
        'get_course_plans' => 'getCoursePlans',
        'list_orders' => 'listOrders',
        'get_order_detail' => 'getOrderDetail',
        'list_parent_orders' => 'listParentOrders',
    ];
}

/**
 * @return list<array<string,mixed>>
 */
function fetchCoursePricePlans(PDO $db, int $courseId): array
{
    $planStmt = $db->prepare(
        'SELECT * FROM price_plans WHERE course_id = :course_id ORDER BY sort_order, id'
    );
    $planStmt->execute([':course_id' => $courseId]);

    $itemStmt = $db->prepare(
        "SELECT pi.*,
                d.name AS discount_plan_name,
                c.name AS coupon_name,
                ta.name AS teaching_aid_name,
                ta.price AS teaching_aid_price,
                pc.name AS product_coupon_name,
                d.discount_amount AS discount_plan_amount,
                c.discount_amount AS coupon_amount,
                pc.discount_amount AS product_coupon_amount
         FROM price_items pi
         LEFT JOIN discount_plans d ON pi.discount_plan_id = d.id
         LEFT JOIN coupons c ON pi.coupon_id = c.id
         LEFT JOIN teaching_aids ta ON pi.teaching_aid_id = ta.id
         LEFT JOIN coupons pc ON pi.product_coupon_id = pc.id
         WHERE pi.plan_id = :plan_id
         ORDER BY pi.sort_order, pi.id"
    );

    $plans = [];
    while ($plan = $planStmt->fetch(PDO::FETCH_ASSOC)) {
        $itemStmt->execute([':plan_id' => (int)$plan['id']]);
        $plan['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        $plans[] = $plan;
    }

    return $plans;
}

function listPricePlans(PDO $db, string $method, array $query, array $input): void
{
    $courseId = (int)($query['course_id'] ?? 0);
    if ($courseId <= 0) {
        json(['error' => '缺少 course_id']);
    }

    json(['data' => fetchCoursePricePlans($db, $courseId)]);
}

function getCoursePlans(PDO $db, string $method, array $query, array $input): void
{
    $courseId = (int)($query['course_id'] ?? 0);
    if ($courseId <= 0) {
        json(['error' => '缺少 course_id']);
    }

    json(['data' => fetchCoursePricePlans($db, $courseId)]);
}

/**
 * @return array{0:string,1:array<string,string>}
 */
function buildOrderListWhere(array $query): array
{
    $conditions = [];
    $params = [];

    $keyword = trim($query['keyword'] ?? '');
    if ($keyword !== '') {
        $conditions[] = '(s.name LIKE :student_keyword OR c.name LIKE :course_keyword)';
        $like = '%' . $keyword . '%';
        $params[':student_keyword'] = $like;
        $params[':course_keyword'] = $like;
    }

    $payStatus = trim($query['pay_status'] ?? '');
    if ($payStatus !== '') {
        $conditions[] = 'o.pay_status = :pay_status';
        $params[':pay_status'] = $payStatus;
    }

    $isVoided = trim($query['is_voided'] ?? '');
    if ($isVoided !== '') {
        $conditions[] = 'o.is_voided = :is_voided';
        $params[':is_voided'] = $isVoided;
    }

    $campus = trim($query['campus'] ?? '');
    if ($campus !== '') {
        $campuses = array_values(array_filter(array_map('trim', explode(',', $campus))));
        if (count($campuses) === 1) {
            $conditions[] = 'o.campus = :campus';
            $params[':campus'] = $campuses[0];
        } elseif (count($campuses) > 1) {
            $placeholders = [];
            foreach ($campuses as $index => $campusName) {
                $placeholder = ':campus_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $campusName;
            }
            $conditions[] = 'o.campus IN (' . implode(',', $placeholders) . ')';
        }
    }

    $payDateStart = trim($query['pay_date_start'] ?? '');
    if ($payDateStart !== '') {
        $conditions[] = 'o.paid_at >= :pay_date_start';
        $params[':pay_date_start'] = $payDateStart . ' 00:00:00';
    }

    $payDateEnd = trim($query['pay_date_end'] ?? '');
    if ($payDateEnd !== '') {
        $conditions[] = 'o.paid_at <= :pay_date_end';
        $params[':pay_date_end'] = $payDateEnd . ' 23:59:59';
    }

    return [
        $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions),
        $params,
    ];
}

/**
 * @param array<string,string> $params
 */
function bindOrderStringParams(PDOStatement $stmt, array $params): void
{
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value, PDO::PARAM_STR);
    }
}

function listOrders(PDO $db, string $method, array $query, array $input): void
{
    $page = max(1, (int)($query['page'] ?? 1));
    $pageSize = min(50, max(1, (int)($query['page_size'] ?? 15)));
    $offset = ($page - 1) * $pageSize;
    [$where, $params] = buildOrderListWhere($query);

    $countStmt = $db->prepare(
        "SELECT COUNT(*)
         FROM orders o
         LEFT JOIN students s ON o.student_id = s.id
         LEFT JOIN courses c ON o.course_id = c.id
         {$where}"
    );
    bindOrderStringParams($countStmt, $params);
    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_NUM)[0];

    $stmt = $db->prepare(
        "SELECT o.id,
                o.student_id,
                o.course_id,
                o.plan_name,
                o.item_name,
                o.lesson_count,
                o.actual_price,
                o.teaching_aid_price,
                o.product_coupon_amount,
                o.discount_plan_amount,
                o.coupon_amount,
                o.status,
                o.created_at,
                o.paid_at,
                o.order_no,
                o.parent_order_no,
                o.cash_amount,
                o.meituan_amount,
                o.account_amount,
                o.paid_amount,
                o.order_type,
                o.campus,
                o.pay_status,
                o.is_voided,
                o.subject_level1,
                o.subject_level2,
                o.activity_id,
                o.activity_name,
                o.activity_campus,
                o.activity_adult_count,
                o.activity_student_count,
                o.adult_unit_price,
                o.student_unit_price,
                o.activity_fee_type,
                s.name AS student_name,
                s.student_no,
                c.name AS course_name
         FROM orders o
         LEFT JOIN students s ON o.student_id = s.id
         LEFT JOIN courses c ON o.course_id = c.id
         {$where}
         ORDER BY o.id DESC
         LIMIT :limit OFFSET :offset"
    );
    bindOrderStringParams($stmt, $params);
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $teachingAidPrice = (float)($row['teaching_aid_price'] ?? 0);
        $productCouponAmount = (float)($row['product_coupon_amount'] ?? 0);
        $discountPlanAmount = (float)($row['discount_plan_amount'] ?? 0);
        $couponAmount = (float)($row['coupon_amount'] ?? 0);
        $actualPrice = (float)($row['actual_price'] ?? 0);
        $unitPrice = $actualPrice
            - $teachingAidPrice
            + $productCouponAmount
            + $discountPlanAmount
            + $couponAmount;

        $row['course_amount'] = round($unitPrice - $discountPlanAmount - $couponAmount, 2);
        $row['product_amount'] = round($teachingAidPrice - $productCouponAmount, 2);
        if (($row['order_type'] ?? '') === '活动') {
            $row['course_name'] = $row['activity_name'] ?? '';
        }
    }
    unset($row);

    $summaryStmt = $db->prepare(
        "SELECT SUM(COALESCE(o.cash_amount, 0)) AS cash_total,
                SUM(COALESCE(o.meituan_amount, 0)) AS meituan_total,
                SUM(COALESCE(o.account_amount, 0)) AS account_total
         FROM orders o
         LEFT JOIN students s ON o.student_id = s.id
         LEFT JOIN courses c ON o.course_id = c.id
         {$where}"
    );
    bindOrderStringParams($summaryStmt, $params);
    $summaryStmt->execute();
    $paymentSummary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'cash_total' => 0,
        'meituan_total' => 0,
        'account_total' => 0,
    ];

    json([
        'data' => $rows,
        'total' => $total,
        'page' => $page,
        'page_size' => $pageSize,
        'payment_summary' => $paymentSummary,
    ]);
}

function getOrderDetail(PDO $db, string $method, array $query, array $input): void
{
    $parentOrderNo = trim($query['parent_order_no'] ?? '');
    $orderNo = trim($query['order_no'] ?? '');
    if ($parentOrderNo === '' && $orderNo === '') {
        json(['success' => false, 'message' => '订单号不能为空']);
    }

    $where = $orderNo !== ''
        ? 'o.order_no = :order_no'
        : 'o.parent_order_no = :parent_order_no';
    $params = $orderNo !== ''
        ? [':order_no' => $orderNo]
        : [':parent_order_no' => $parentOrderNo];

    $itemsStmt = $db->prepare(
        "SELECT o.id,
                o.order_no,
                o.parent_order_no,
                o.created_at,
                o.paid_at,
                o.item_name,
                o.lesson_count,
                o.actual_price,
                o.cash_amount,
                o.meituan_amount,
                o.account_amount,
                o.pay_status,
                o.plan_name,
                o.course_id,
                o.campus,
                pi.unit_price,
                o.discount_plan_name,
                o.discount_plan_amount,
                o.coupon_name,
                o.coupon_amount,
                o.teaching_aid_name,
                o.teaching_aid_price,
                o.product_coupon_name,
                o.product_coupon_amount,
                o.gifted_lessons
         FROM orders o
         LEFT JOIN price_plans pp ON pp.name = o.plan_name AND pp.course_id = o.course_id
         LEFT JOIN price_items pi ON pi.plan_id = pp.id AND pi.name = o.item_name
         WHERE {$where}
         ORDER BY o.id"
    );
    $itemsStmt->execute($params);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($orderNo !== '' && $parentOrderNo === '' && $items !== []) {
        $parentOrderNo = $items[0]['parent_order_no'] ?? '';
    }

    $parentOrder = null;
    if ($parentOrderNo !== '') {
        $parentStmt = $db->prepare(
            'SELECT * FROM parent_orders WHERE parent_order_no = :parent_order_no'
        );
        $parentStmt->execute([':parent_order_no' => $parentOrderNo]);
        $parentOrder = $parentStmt->fetch(PDO::FETCH_ASSOC);
    }

    $studentName = '';
    $studentNo = '';
    $courseName = '';
    $campus = '';
    $enrollTime = $parentOrder['enroll_time'] ?? '';

    if ($items !== []) {
        $firstItem = $items[0];
        $courseId = (int)($firstItem['course_id'] ?? 0);
        $orderId = (int)($firstItem['id'] ?? 0);

        $studentStmt = $db->prepare(
            'SELECT s.name AS student_name, s.student_no
             FROM orders o
             LEFT JOIN students s ON o.student_id = s.id
             WHERE o.id = :order_id'
        );
        $studentStmt->execute([':order_id' => $orderId]);
        $studentInfo = $studentStmt->fetch(PDO::FETCH_ASSOC);
        if ($studentInfo) {
            $studentName = $studentInfo['student_name'] ?? '';
            $studentNo = $studentInfo['student_no'] ?? '';
        }

        if ($parentOrder) {
            $studentName = $parentOrder['student_name'] ?: $studentName;
            $studentNo = $parentOrder['student_no'] ?: $studentNo;
            $courseName = $parentOrder['course_name'] ?: '';
            $campus = $parentOrder['campus'] ?: ($firstItem['campus'] ?? '');
        } else {
            $campus = $firstItem['campus'] ?? '';
        }

        if ($courseName === '' && $courseId > 0) {
            $courseStmt = $db->prepare('SELECT name FROM courses WHERE id = :course_id');
            $courseStmt->execute([':course_id' => $courseId]);
            $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
            $courseName = $course['name'] ?? '';
        }
    } elseif ($parentOrder) {
        $studentName = $parentOrder['student_name'] ?? '';
        $studentNo = $parentOrder['student_no'] ?? '';
        $courseName = $parentOrder['course_name'] ?? '';
        $campus = $parentOrder['campus'] ?? '';
    }

    $totalPrice = 0;
    $totalLessons = 0;
    $cashTotal = 0;
    $meituanTotal = 0;
    $accountTotal = 0;
    $totalCourseAmount = 0;
    $totalProductAmount = 0;
    foreach ($items as $item) {
        $totalPrice += (float)($item['actual_price'] ?? 0);
        $totalLessons += (int)($item['lesson_count'] ?? 0);
        $cashTotal += (float)($item['cash_amount'] ?? 0);
        $meituanTotal += (float)($item['meituan_amount'] ?? 0);
        $accountTotal += (float)($item['account_amount'] ?? 0);
        $teachingAidPrice = (float)($item['teaching_aid_price'] ?? 0);
        $totalCourseAmount += (float)($item['actual_price'] ?? 0) - $teachingAidPrice;
        $totalProductAmount += $teachingAidPrice;
    }

    if ($parentOrder) {
        $totalPrice = (float)($parentOrder['total_price'] ?? $totalPrice);
        $totalLessons = (int)($parentOrder['total_lessons'] ?? $totalLessons);
    }

    $createdAt = $parentOrder['created_at'] ?? '';
    $paidAt = '';
    foreach ($items as $item) {
        if (!$createdAt && !empty($item['created_at'])) {
            $createdAt = $item['created_at'];
        }
        if (!$paidAt && !empty($item['paid_at'])) {
            $paidAt = $item['paid_at'];
        }
    }

    $resultItems = [];
    foreach ($items as $item) {
        $resultItems[] = [
            'order_id' => (int)$item['id'],
            'order_no' => $item['order_no'] ?? '',
            'item_name' => $item['item_name'] ?? '',
            'lesson_count' => (int)($item['lesson_count'] ?? 0),
            'unit_price' => number_format((float)($item['unit_price'] ?? 0), 2, '.', ''),
            'actual_price' => number_format((float)($item['actual_price'] ?? 0), 2, '.', ''),
            'discount_plan_name' => $item['discount_plan_name'] ?? null,
            'discount_plan_amount' => $item['discount_plan_amount']
                ? number_format((float)$item['discount_plan_amount'], 2, '.', '')
                : null,
            'coupon_name' => $item['coupon_name'] ?? null,
            'coupon_amount' => $item['coupon_amount']
                ? number_format((float)$item['coupon_amount'], 2, '.', '')
                : null,
            'teaching_aid_name' => $item['teaching_aid_name'] ?? null,
            'teaching_aid_price' => $item['teaching_aid_price']
                ? number_format((float)$item['teaching_aid_price'], 2, '.', '')
                : null,
            'product_coupon_name' => $item['product_coupon_name'] ?? null,
            'product_coupon_amount' => $item['product_coupon_amount']
                ? number_format((float)$item['product_coupon_amount'], 2, '.', '')
                : null,
            'gifted_lessons' => (int)($item['gifted_lessons'] ?? 0),
            'cash_amount' => number_format((float)($item['cash_amount'] ?? 0), 2, '.', ''),
            'meituan_amount' => number_format((float)($item['meituan_amount'] ?? 0), 2, '.', ''),
            'account_amount' => number_format((float)($item['account_amount'] ?? 0), 2, '.', ''),
            'pay_status' => $item['pay_status'] ?? '',
            'course_amount' => round(
                ((float)($item['actual_price'] ?? 0)
                    - (float)($item['teaching_aid_price'] ?? 0)
                    + (float)($item['product_coupon_amount'] ?? 0)
                    + (float)($item['discount_plan_amount'] ?? 0)
                    + (float)($item['coupon_amount'] ?? 0))
                - (float)($item['discount_plan_amount'] ?? 0)
                - (float)($item['coupon_amount'] ?? 0),
                2
            ),
            'product_amount' => round(
                (float)($item['teaching_aid_price'] ?? 0)
                - (float)($item['product_coupon_amount'] ?? 0),
                2
            ),
        ];
    }

    json([
        'success' => true,
        'data' => [
            'parent_order_no' => $parentOrderNo,
            'student_name' => $studentName,
            'student_no' => $studentNo,
            'course_name' => $courseName,
            'campus' => $campus,
            'enroll_time' => $enrollTime,
            'created_at' => $createdAt,
            'paid_at' => $paidAt,
            'total_price' => number_format($totalPrice, 2, '.', ''),
            'total_lessons' => $totalLessons,
            'total_course_amount' => number_format($totalCourseAmount, 2, '.', ''),
            'total_product_amount' => number_format($totalProductAmount, 2, '.', ''),
            'payment' => [
                'cash_amount' => number_format($cashTotal, 2, '.', ''),
                'meituan_amount' => number_format($meituanTotal, 2, '.', ''),
                'account_amount' => number_format($accountTotal, 2, '.', ''),
                'total' => number_format($cashTotal + $meituanTotal + $accountTotal, 2, '.', ''),
            ],
            'items' => $resultItems,
        ],
    ]);
}

function listParentOrders(PDO $db, string $method, array $query, array $input): void
{
    $page = max(1, (int)($query['page'] ?? 1));
    $pageSize = max(1, min(100, (int)($query['page_size'] ?? 20)));
    $keyword = trim($query['keyword'] ?? '');
    $offset = ($page - 1) * $pageSize;

    $where = '';
    $params = [];
    if ($keyword !== '') {
        $where = 'WHERE (
            po.student_name LIKE :student_keyword
            OR po.course_name LIKE :course_keyword
            OR po.parent_order_no LIKE :order_keyword
            OR po.student_no LIKE :number_keyword
        )';
        $like = '%' . $keyword . '%';
        $params = [
            ':student_keyword' => $like,
            ':course_keyword' => $like,
            ':order_keyword' => $like,
            ':number_keyword' => $like,
        ];
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM parent_orders po {$where}");
    bindOrderStringParams($countStmt, $params);
    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_NUM)[0];

    $stmt = $db->prepare(
        "SELECT *
         FROM parent_orders po
         {$where}
         ORDER BY po.id DESC
         LIMIT :limit OFFSET :offset"
    );
    bindOrderStringParams($stmt, $params);
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    json([
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total' => $total,
        'page' => $page,
        'page_size' => $pageSize,
    ]);
}
