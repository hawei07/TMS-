<?php

declare(strict_types=1);

/**
 * @return array<string,callable(PDO,string,array,array):void>
 */
function courseTransferApiRoutes(): array
{
    return [
        'create_course_transfer' => 'createCourseTransfer',
        'list_course_transfer_records' => 'listCourseTransferRecords',
        'revoke_course_transfer' => 'revokeCourseTransfer',
    ];
}

/**
 * 创建转课记录
 * POST body: {source_order_id, target_course_id, transfer_lessons, target_lessons?}
 */
function createCourseTransfer(PDO $db, string $method, array $query, array $input): void
{
    if ($method !== 'POST') {
        json(['error' => 'Method not allowed']);
    }

    $sourceOrderId = (int)($input['source_order_id'] ?? 0);
    $targetCourseId = (int)($input['target_course_id'] ?? 0);
    $transferLessons = (int)($input['transfer_lessons'] ?? 0);
    $transferRecordId = (int)($input['transfer_record_id'] ?? 0);
    $isTransferSource = $transferRecordId > 0;

    if ($targetCourseId <= 0) {
        json(['success' => false, 'message' => '参数错误：缺少目标课程']);
    }
    if ($transferLessons <= 0 || $transferLessons % 2 !== 0) {
        json(['success' => false, 'message' => '转出课时数必须为大于0的偶数']);
    }

    $db->beginTransaction();
    try {
        // === 源校验（分两路：转课源 vs 订单源）===
        if ($isTransferSource) {
            $stmt = $db->prepare("SELECT * FROM course_transfer_records WHERE id = :id AND status = '正常' FOR UPDATE");
            $stmt->execute([':id' => $transferRecordId]);
            $sourceTransfer = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sourceTransfer) { $db->rollBack(); json(['success' => false, 'message' => '转课记录不存在或已撤销']); }
            if ($transferLessons > (int)$sourceTransfer['target_lessons']) { $db->rollBack(); json(['success' => false, 'message' => '转出课时超出剩余课时']); }

            $studentId    = (int)$sourceTransfer['student_id'];
            $sourceCampus = $sourceTransfer['campus'] ?? '';
            $sourceCourseId = (int)$sourceTransfer['target_course_id'];
            $sourceCourseName = $sourceTransfer['target_course_name'] ?? '';
            $underlyingOid  = (int)$sourceTransfer['source_order_id'];
            $inheritedOrderNo = $sourceTransfer['order_no'] ?? '';
            $sourceSubjectName = $sourceTransfer['target_subject_level1'] ?? ''; // will be filled from courses below

            // 从底层订单获取价值参数
            $ord = $db->query("SELECT actual_price, teaching_aid_price, product_coupon_amount, lesson_count, subject_level1 FROM orders WHERE id = $underlyingOid")->fetch(PDO::FETCH_ASSOC);
            if (!$ord) { $db->rollBack(); json(['success' => false, 'message' => '底层订单不存在']); }
            $actualPrice = (float)$ord['actual_price'];
            $teachingAidPrice = (float)$ord['teaching_aid_price'];
            $productCouponAmount = (float)$ord['product_coupon_amount'];
            $lessonCount = (int)$ord['lesson_count'];
            $sourceSubjectName = $ord['subject_level1'] ?? '';
        } else {
            if ($sourceOrderId <= 0) { json(['success' => false, 'message' => '参数错误：缺少源订单']); }
            $stmt = $db->prepare(
                "SELECT o.id, o.student_id, o.course_id, o.lesson_count, o.actual_price,
                        o.consumed_lessons, o.transferred_lessons, o.is_voided, o.order_type,
                        o.refund_status, o.teaching_aid_price, o.product_coupon_amount,
                        o.campus, o.order_no,
                        c.name AS course_name, c.subject_level1
                 FROM orders o JOIN courses c ON o.course_id = c.id
                 WHERE o.id = :id FOR UPDATE"
            );
            $stmt->execute([':id' => $sourceOrderId]);
            $sourceOrder = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sourceOrder) { $db->rollBack(); json(['success' => false, 'message' => '源订单不存在']); }
            if (($sourceOrder['is_voided'] ?? '') === '是') { $db->rollBack(); json(['success' => false, 'message' => '源订单已作废']); }
            if (($sourceOrder['order_type'] ?? '') === '活动') { $db->rollBack(); json(['success' => false, 'message' => '活动订单不支持转课']); }
            if (in_array(($sourceOrder['refund_status'] ?? ''), ['已退费', '退费申请中'], true)) { $db->rollBack(); json(['success' => false, 'message' => '该订单已退费或退费申请中，无法转课']); }

            $lessonCount  = (int)$sourceOrder['lesson_count'];
            $transferredLessons = (int)($sourceOrder['transferred_lessons'] ?? 0);
            $remaining = $lessonCount - (int)$sourceOrder['consumed_lessons'] - $transferredLessons;
            if ($transferLessons > $remaining) { $db->rollBack(); json(['success' => false, 'message' => "转出课时($transferLessons)超出剩余课时($remaining)"]); }

            $studentId    = (int)$sourceOrder['student_id'];
            $sourceCampus = $sourceOrder['campus'] ?? '';
            $sourceCourseId  = (int)$sourceOrder['course_id'];
            $sourceCourseName = $sourceOrder['course_name'] ?? '';
            $sourceSubjectName = $sourceOrder['subject_level1'] ?? '';
            $inheritedOrderNo = $sourceOrder['order_no'] ?? '';
            $actualPrice = (float)$sourceOrder['actual_price'];
            $teachingAidPrice = (float)$sourceOrder['teaching_aid_price'];
            $productCouponAmount = (float)$sourceOrder['product_coupon_amount'];
        }

        // === 目标课程校验（两路共享）===
        $stmt = $db->prepare('SELECT id, name, subject_level1, subject_level2, campus_permission FROM courses WHERE id = :id');
        $stmt->execute([':id' => $targetCourseId]);
        $targetCourse = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$targetCourse) { $db->rollBack(); json(['success' => false, 'message' => '目标课程不存在']); }

        $sourceCampusId = $db->query("SELECT id FROM organizations WHERE name = " . $db->quote($sourceCampus) . " AND type='校区'")->fetchColumn();
        $stmt = $db->prepare('SELECT FIND_IN_SET(:cid, :perm) AS m');
        $stmt->execute([':cid' => $sourceCampusId, ':perm' => $targetCourse['campus_permission'] ?? '']);
        if (!$sourceCampusId || !(int)$stmt->fetchColumn()) { $db->rollBack(); json(['success' => false, 'message' => '目标课程不在同一校区']); }

        if ((int)$targetCourse['id'] === $sourceCourseId) { $db->rollBack(); json(['success' => false, 'message' => '目标课程不能与源课程相同']); }

        // 防重复
        $stmt = $db->prepare("SELECT COUNT(*) FROM course_transfer_records WHERE student_id = :sid AND target_course_id = :cid AND campus = :campus AND status = '正常'");
        $stmt->execute([':sid' => $studentId, ':cid' => $targetCourseId, ':campus' => $sourceCampus]);
        if ($stmt->fetchColumn() > 0) { $db->rollBack(); json(['success' => false, 'message' => '该学员在目标课程已有转课记录，请勿重复转课']); }

        // === 跨学科判断 ===
        $targetSubjectName = $targetCourse['subject_level1'] ?? '';
        $isCrossSubject = ($sourceSubjectName !== $targetSubjectName);

        // === 价值计算 ===
        $unitValue = $lessonCount > 0 ? ($actualPrice - $teachingAidPrice + $productCouponAmount) / $lessonCount : 0;
        $transferValue = round($unitValue * $transferLessons, 2);
        if ($isCrossSubject) {
            $targetLessons = isset($input['target_lessons']) ? (int)$input['target_lessons'] : $transferLessons;
            if ($targetLessons <= 0) $targetLessons = $transferLessons;
            if ($targetLessons % 2 !== 0) { $db->rollBack(); json(['success' => false, 'message' => '转入课时数必须为偶数！']); }
        } else {
            $targetLessons = $transferLessons;
        }
        $targetValue = $transferValue;

        // === 事务操作 ===
        if ($isTransferSource) {
            // 减掉源转课记录的目标课时
            $stmt = $db->prepare('UPDATE course_transfer_records SET target_lessons = target_lessons - :d WHERE id = :id');
            $stmt->execute([':d' => $transferLessons, ':id' => $transferRecordId]);
        } else {
            // 累计源订单的转出课时
            $stmt = $db->prepare('UPDATE orders SET transferred_lessons = transferred_lessons + :a WHERE id = :id');
            $stmt->execute([':a' => $transferLessons, ':id' => $sourceOrderId]);
        }

        // 写入新转课记录
        $stmt = $db->prepare(
            "INSERT INTO course_transfer_records (source_order_id, source_course_id, source_course_name, target_order_id, target_course_id, target_course_name, student_id, campus, transfer_lessons, transfer_value, target_lessons, target_value, is_cross_subject, order_no, status, created_at)
             VALUES (:s_oid, :s_cid, :s_cname, 0, :t_cid, :t_cname, :sid, :campus, :tl, :tv, :tgl, :tgv, :ics, :ono, '正常', NOW())"
        );
        $stmt->execute([
            ':s_oid'   => $isTransferSource ? $underlyingOid : $sourceOrderId,
            ':s_cid'   => $sourceCourseId,
            ':s_cname' => $sourceCourseName,
            ':t_cid'   => $targetCourseId,
            ':t_cname' => $targetCourse['name'] ?? '',
            ':sid'     => $studentId,
            ':campus'  => $sourceCampus,
            ':tl'      => $transferLessons,
            ':tv'      => $transferValue,
            ':tgl'     => $targetLessons,
            ':tgv'     => $targetValue,
            ':ics'     => $isCrossSubject ? 1 : 0,
            ':ono'     => $inheritedOrderNo,
        ]);
        $recordId = (int)$db->lastInsertId();

        $db->commit();
        json(['success' => true, 'record' => ['id' => $recordId, 'source_course_name' => $sourceCourseName, 'target_course_name' => $targetCourse['name'] ?? '', 'transfer_lessons' => $transferLessons, 'target_lessons' => $targetLessons, 'transfer_value' => $transferValue, 'is_cross_subject' => $isCrossSubject]]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('create_course_transfer failed: ' . $e->getMessage());
        json(['success' => false, 'message' => '转课失败：' . $e->getMessage()]);
    }
}

/**
 * 查询转课记录列表
 * GET params: campus, student_id, date_from, date_to, status, keyword, page, page_size
 */
function listCourseTransferRecords(PDO $db, string $method, array $query, array $input): void
{
    if ($method !== 'GET') {
        json(['error' => 'Method not allowed']);
    }

    $campus = trim($query['campus'] ?? '');
    $studentId = (int)($query['student_id'] ?? 0);
    $dateFrom = trim($query['date_from'] ?? '');
    $dateTo = trim($query['date_to'] ?? '');
    $status = trim($query['status'] ?? '');
    $keyword = trim($query['keyword'] ?? '');
    $page = max(1, (int)($query['page'] ?? 1));
    $pageSize = min(50, max(1, (int)($query['page_size'] ?? 20)));

    $conditions = [];
    $params = [];

    if ($campus !== '') {
        $conditions[] = 'ctr.campus = :campus';
        $params[':campus'] = $campus;
    }
    if ($studentId > 0) {
        $conditions[] = 'ctr.student_id = :student_id';
        $params[':student_id'] = $studentId;
    }
    if ($dateFrom !== '') {
        $conditions[] = 'ctr.created_at >= :date_from';
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $conditions[] = 'ctr.created_at <= :date_to';
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }
    if ($status !== '') {
        $conditions[] = 'ctr.status = :status';
        $params[':status'] = $status;
    }
    if ($keyword !== '') {
        $conditions[] = '(s.name LIKE :kw OR s.phone LIKE :kw2 OR s.student_no LIKE :kw3 OR ctr.source_course_name LIKE :kw4 OR ctr.target_course_name LIKE :kw5)';
        $kw = '%' . $keyword . '%';
        $params[':kw'] = $kw;
        $params[':kw2'] = $kw;
        $params[':kw3'] = $kw;
        $params[':kw4'] = $kw;
        $params[':kw5'] = $kw;
    }

    $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // 总数
    $countSql = "SELECT COUNT(*) FROM course_transfer_records ctr LEFT JOIN students s ON s.id = ctr.student_id {$where}";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // 分页数据
    $offset = ($page - 1) * $pageSize;
    $dataSql = "SELECT ctr.*, s.name AS student_name, s.phone AS student_phone, s.student_no AS student_no
                FROM course_transfer_records ctr
                LEFT JOIN students s ON s.id = ctr.student_id
                {$where}
                ORDER BY ctr.id DESC
                LIMIT {$pageSize} OFFSET {$offset}";
    $dataStmt = $db->prepare($dataSql);
    $dataStmt->execute($params);
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    json([
        'data' => $rows,
        'total' => $total,
        'page' => $page,
        'page_size' => $pageSize,
    ]);
}

/**
 * 撤销转课记录
 * POST body: {record_id}
 */
function revokeCourseTransfer(PDO $db, string $method, array $query, array $input): void
{
    if ($method !== 'POST') {
        json(['error' => 'Method not allowed']);
    }

    $recordId = (int)($input['record_id'] ?? 0);
    if ($recordId <= 0) {
        json(['success' => false, 'message' => '参数错误：缺少记录ID']);
    }

    $db->beginTransaction();
    try {
        // 1. 查转课记录（行锁）
        $stmt = $db->prepare('SELECT * FROM course_transfer_records WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $recordId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            $db->rollBack();
            json(['success' => false, 'message' => '转课记录不存在']);
        }
        if (($record['status'] ?? '') !== '正常') {
            $db->rollBack();
            json(['success' => false, 'message' => '该转课记录已撤销，无法重复撤销']);
        }

        // 检查目标课程是否已发生二次转出（A→B, B→C 情况下，B→C后 A→B不可撤销）
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM course_transfer_records WHERE source_course_id = :cid AND student_id = :sid AND status = '正常'"
        );
        $stmt->execute([':cid' => $record['target_course_id'], ':sid' => $record['student_id']]);
        if ($stmt->fetchColumn() > 0) {
            $db->rollBack();
            json(['success' => false, 'message' => '目标课程已发生二次转课，无法撤销']);
        }

        // 目标课程有消耗课时则不可撤销
        if ((int)($record['consumed_lessons'] ?? 0) > 0) {
            $db->rollBack();
            json(['success' => false, 'message' => '目标课程已有课时消耗，无法撤销']);
        }

        $sourceOrderId = (int)$record['source_order_id'];
        $transferLessons = (int)$record['transfer_lessons'];
        $sourceCourseId = (int)$record['source_course_id'];
        $studentId = (int)$record['student_id'];

        // 2. 判断撤销来源：是直接订单源还是转课链（A→B→C 中撤销 B→C，课时回 B 而非 A）
        $stmt = $db->prepare(
            "SELECT id, target_lessons FROM course_transfer_records
             WHERE target_course_id = :cid AND student_id = :sid AND status = '正常' AND id != :rid
             LIMIT 1"
        );
        $stmt->execute([':cid' => $sourceCourseId, ':sid' => $studentId, ':rid' => $recordId]);
        $parentTransfer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($parentTransfer) {
            // 撤销的是链上转课（B→C）：课时归还到 B 的转课记录（A→B）
            $stmt = $db->prepare(
                'UPDATE course_transfer_records SET target_lessons = target_lessons + :add WHERE id = :id'
            );
            $stmt->execute([':add' => $transferLessons, ':id' => $parentTransfer['id']]);
        } else {
            // 撤销的是首层转课（A→B）：课时归还到源订单
            $stmt = $db->prepare(
                'UPDATE orders SET transferred_lessons = GREATEST(0, transferred_lessons - :dec) WHERE id = :id'
            );
            $stmt->execute([':dec' => $transferLessons, ':id' => $sourceOrderId]);
        }

        // b. 标记转课记录为已撤销
        $stmt = $db->prepare(
            'UPDATE course_transfer_records SET status = :status, revoked_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':status' => '已撤销', ':id' => $recordId]);

        $db->commit();
        json(['success' => true]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('revoke_course_transfer failed: ' . $e->getMessage());
        json(['success' => false, 'message' => '撤销转课失败：' . $e->getMessage()]);
    }
}
