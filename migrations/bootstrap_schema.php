<?php

declare(strict_types=1);

require_once __DIR__ . '/MigrationRunner.php';

function createMigrationRunner(PDO $db): MigrationRunner
{
    return new MigrationRunner($db, __DIR__ . '/versions');
}

function ensureSchema(PDO $db): void
{
    $force = PHP_SAPI !== 'cli'
        && isset($_GET['migrate'])
        && $_GET['migrate'] === '1';

    createMigrationRunner($db)->migrate($force);
}

function applyLegacySchema(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS channels (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(500) NOT NULL DEFAULT '',
        created_at VARCHAR(500) DEFAULT ''
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS resources (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(500) NOT NULL DEFAULT '',
        phone VARCHAR(500) DEFAULT '',
        source VARCHAR(500) DEFAULT '',
        source_detail VARCHAR(500) DEFAULT '',
        intention_level VARCHAR(500) DEFAULT '',
        status VARCHAR(500) DEFAULT '待跟进',
        assigned_to VARCHAR(500) DEFAULT '',
        pool_type VARCHAR(500) DEFAULT '我的资源',
        created_at VARCHAR(500) DEFAULT '',
        updated_at VARCHAR(500) DEFAULT '',
        converted VARCHAR(500) DEFAULT '未转化'
    )");$db->exec("CREATE TABLE IF NOT EXISTS appointments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        resource_id INT NOT NULL DEFAULT 0,
        resource_name VARCHAR(500) DEFAULT '',
        student_name VARCHAR(500) DEFAULT '',
        phone VARCHAR(500) DEFAULT '',
        course_type VARCHAR(500) DEFAULT '',
        appointment_time VARCHAR(500) DEFAULT '',
        status VARCHAR(500) DEFAULT '已预约',
        notes VARCHAR(500) DEFAULT '',
        created_at VARCHAR(500) DEFAULT ''
    )");
    // 预约试听重构
    foreach ([
        ["campus", "VARCHAR(500) DEFAULT ''"],
        ["subject_level1", "VARCHAR(500) DEFAULT ''"],
        ["course_id", "INT DEFAULT 0"],
        ["class_id", "INT DEFAULT 0"],
        ["schedule_id", "INT DEFAULT 0"],
    ] as $col) {
        $existing = [];
        $r = $db->query("SHOW COLUMNS FROM appointments");
        while ($c = $r->fetch(PDO::FETCH_ASSOC)) $existing[] = $c["Field"];
        if (!in_array($col[0], $existing)) {
            $db->exec("ALTER TABLE appointments ADD COLUMN {$col[0]} {$col[1]}");
        }
    }
    // 预约试听: class_attendance needs student_name
    $db->exec("CREATE TABLE IF NOT EXISTS class_attendance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        class_id INT NOT NULL DEFAULT 0,
        schedule_id INT NOT NULL DEFAULT 0,
        session_date VARCHAR(500) DEFAULT '',
        student_id INT NOT NULL DEFAULT 0,
        student_name VARCHAR(500) DEFAULT '',
        status VARCHAR(500) DEFAULT '出勤',
        is_temporary INT DEFAULT 0,
        deducted_lessons INT DEFAULT 0,
        created_at VARCHAR(500) DEFAULT ''
    )");
    $caCols = [];
    $caRes = $db->query("SHOW COLUMNS FROM class_attendance");
    while ($c = $caRes->fetch(PDO::FETCH_ASSOC)) $caCols[] = $c['Field'];
    if (!in_array('student_name', $caCols)) $db->exec("ALTER TABLE class_attendance ADD COLUMN student_name VARCHAR(500) DEFAULT ''");
    $db->exec("CREATE TABLE IF NOT EXISTS communication_records (
        id INT PRIMARY KEY AUTO_INCREMENT,
        resource_id INT NOT NULL DEFAULT 0,
        resource_name VARCHAR(500) DEFAULT '',
        content VARCHAR(500) DEFAULT '',
        comm_type VARCHAR(500) DEFAULT '电话',
        created_at VARCHAR(500) DEFAULT ''
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS intention_levels (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(500) NOT NULL DEFAULT '',
        sort_order INT DEFAULT 0,
        created_at VARCHAR(500) DEFAULT ''
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS basic_types (
        id INT PRIMARY KEY AUTO_INCREMENT,
        category VARCHAR(500) NOT NULL DEFAULT '',
        name VARCHAR(500) NOT NULL DEFAULT '',
        sort_order INT DEFAULT 0,
        created_at VARCHAR(500) DEFAULT ''
    )");

    // 兼容旧数据库：增量添加新字段
    try { $db->exec("ALTER TABLE resources ADD COLUMN gender VARCHAR(500) DEFAULT ''"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE resources ADD COLUMN birth_date VARCHAR(500) DEFAULT ''"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE resources ADD COLUMN follow_status VARCHAR(500) DEFAULT ''"); } catch (PDOException $e) {}

    // 兼容已有数据库：resources 表新增转化状态字段
    $existingColsR = [];
    $colResR = $db->query("SHOW COLUMNS FROM resources");
    while ($colRowR = $colResR->fetch(PDO::FETCH_ASSOC)) $existingColsR[] = $colRowR['Field'];
    if (!in_array('converted', $existingColsR)) {
        $db->exec("ALTER TABLE resources ADD COLUMN converted VARCHAR(500) DEFAULT '未转化'");
        // 历史数据：已关联学员记录（即已报名）的资源标记为已转化
        $db->exec("UPDATE resources SET converted = '已转化' WHERE id IN (SELECT resource_id FROM students WHERE resource_id IS NOT NULL)");
    }


    $db->exec("CREATE TABLE IF NOT EXISTS employees (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(500) NOT NULL DEFAULT '',
        phone VARCHAR(500) DEFAULT '',
        department VARCHAR(500) DEFAULT '',
        position VARCHAR(500) DEFAULT '',
        entry_date VARCHAR(500) DEFAULT '',
        status VARCHAR(500) DEFAULT '在职',
        is_teacher VARCHAR(500) DEFAULT '',
        created_at VARCHAR(500) DEFAULT '',
        updated_at VARCHAR(500) DEFAULT ''
    )");

    // 兼容已有数据库：employees 表新增 is_teacher 字段
    $existingColsEmp = [];
    $colResEmp = $db->query("SHOW COLUMNS FROM employees");
    while ($colRowEmp = $colResEmp->fetch(PDO::FETCH_ASSOC)) $existingColsEmp[] = $colRowEmp['Field'];
    if (!in_array('is_teacher', $existingColsEmp)) {
        $db->exec("ALTER TABLE employees ADD COLUMN is_teacher VARCHAR(500) DEFAULT ''");
    }

    $db->exec("CREATE TABLE IF NOT EXISTS organizations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(500) NOT NULL DEFAULT '',
        type VARCHAR(500) NOT NULL DEFAULT '部门',
        parent_id INT NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        created_at VARCHAR(500) DEFAULT ''
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS positions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(500) NOT NULL UNIQUE,
        sort_order INT DEFAULT 0,
        created_at VARCHAR(500) DEFAULT ''
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS subjects (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(500) NOT NULL,
        parent_id INT DEFAULT 0,
        sort_order INT DEFAULT 0
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS courses (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(500) NOT NULL,
        subject VARCHAR(500) DEFAULT '',
        grade VARCHAR(500) DEFAULT '',
        description VARCHAR(500) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 兼容旧数据库：增量添加新字段
    try { $db->exec("ALTER TABLE courses ADD COLUMN small_package VARCHAR(500) DEFAULT ''"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE courses ADD COLUMN toddler VARCHAR(500) DEFAULT ''"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE courses ADD COLUMN campus_permission VARCHAR(500) DEFAULT ''"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE courses ADD COLUMN subject_level1 VARCHAR(500) DEFAULT ''"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE courses ADD COLUMN subject_level2 VARCHAR(500) DEFAULT ''"); } catch (PDOException $e) {}

    // 价格方案表
    $db->exec("CREATE TABLE IF NOT EXISTS price_plans (
        id INT PRIMARY KEY AUTO_INCREMENT,
        course_id INT NOT NULL,
        name VARCHAR(500) NOT NULL,
        plan_type VARCHAR(500) DEFAULT '',
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    // 兼容已有数据库：price_plans 添加 plan_type 字段
    try { $db->exec("ALTER TABLE price_plans ADD COLUMN plan_type VARCHAR(500) DEFAULT ''"); } catch (PDOException $e) {}
    // 报价单表
    $db->exec("CREATE TABLE IF NOT EXISTS price_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        plan_id INT NOT NULL,
        name VARCHAR(500) NOT NULL,
        lesson_count INT NOT NULL,
        unit_price REAL NOT NULL,
        actual_price REAL NOT NULL,
        sort_order INT DEFAULT 0
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS students (
        id INT PRIMARY KEY AUTO_INCREMENT,
        resource_id INTEGER,
        name VARCHAR(500) NOT NULL,
        phone VARCHAR(500) NOT NULL UNIQUE,
        source VARCHAR(500),
        follow_status VARCHAR(500),
        student_type VARCHAR(500) DEFAULT '小课包',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        plan_name VARCHAR(500),
        item_name VARCHAR(500),
        lesson_count INTEGER,
        actual_price REAL,
        status VARCHAR(500) DEFAULT '已报名',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        paid_at VARCHAR(500) DEFAULT '',
        order_type VARCHAR(500) DEFAULT '',
        consumed_lessons INT DEFAULT 0
    )");

    // 兼容已有数据库：添加支付方式字段
    $existingCols = [];
    $colRes = $db->query("SHOW COLUMNS FROM orders");
    while ($colRow = $colRes->fetch(PDO::FETCH_ASSOC)) $existingCols[] = $colRow['Field'];
    if (!in_array('payment_method', $existingCols)) {
        $db->exec("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(500) DEFAULT ''");
    }
    if (!in_array('paid_amount', $existingCols)) {
        $db->exec("ALTER TABLE orders ADD COLUMN paid_amount REAL DEFAULT 0");
    }
    if (!in_array('order_no', $existingCols)) {
        $db->exec("ALTER TABLE orders ADD COLUMN order_no VARCHAR(500) DEFAULT ''");
    }
    if (!in_array('cash_amount', $existingCols)) {
        $db->exec("ALTER TABLE orders ADD COLUMN cash_amount REAL DEFAULT 0");
    }
    if (!in_array('meituan_amount', $existingCols)) {
        $db->exec("ALTER TABLE orders ADD COLUMN meituan_amount REAL DEFAULT 0");
    }
    if (!in_array('parent_order_no', $existingCols)) {
        $db->exec("ALTER TABLE orders ADD COLUMN parent_order_no VARCHAR(500) DEFAULT ''");
    }
    if (!in_array('paid_at', $existingCols)) {
        $db->exec("ALTER TABLE orders ADD COLUMN paid_at VARCHAR(500) DEFAULT ''");
        $db->exec("UPDATE orders SET paid_at = created_at WHERE paid_at = ''");
    }
    if (!in_array('order_type', $existingCols)) {
        $db->exec("ALTER TABLE orders ADD COLUMN order_type VARCHAR(500) DEFAULT ''");
    }
    if (!in_array('consumed_lessons', $existingCols)) {
        $db->exec("ALTER TABLE orders ADD COLUMN consumed_lessons INT DEFAULT 0");
    }
    if (!in_array('campus', $existingCols)) {
        $db->exec("ALTER TABLE orders ADD COLUMN campus VARCHAR(500) DEFAULT ''");
    }
    if (!in_array('pay_status', $existingCols)) {
        $db->exec("ALTER TABLE orders ADD COLUMN pay_status VARCHAR(20) DEFAULT '待支付'");
    }
    if (!in_array('is_voided', $existingCols)) {
        $db->exec("ALTER TABLE orders ADD COLUMN is_voided VARCHAR(5) DEFAULT '否'");
    }
    if (!in_array('subject_level1', $existingCols)) {
        $db->exec("ALTER TABLE orders ADD COLUMN subject_level1 VARCHAR(500) DEFAULT ''");
    }
    if (!in_array('subject_level2', $existingCols)) {
        $db->exec("ALTER TABLE orders ADD COLUMN subject_level2 VARCHAR(500) DEFAULT ''");
    }
    if (!in_array('account_amount', $existingCols)) {
        $db->exec("ALTER TABLE orders ADD COLUMN account_amount REAL DEFAULT 0");
    }
    if (!in_array('gifted_lessons', $existingCols)) {
        $db->exec("ALTER TABLE orders ADD COLUMN gifted_lessons INT DEFAULT 0");
    }

    // 订单优惠金额快照列（v2.x）
    $snapshotCols = [
        'discount_plan_name' => "VARCHAR(200) DEFAULT ''",
        'discount_plan_amount' => 'DECIMAL(10,2) DEFAULT 0.00',
        'coupon_name' => "VARCHAR(200) DEFAULT ''",
        'coupon_amount' => 'DECIMAL(10,2) DEFAULT 0.00',
        'teaching_aid_name' => "VARCHAR(200) DEFAULT ''",
        'teaching_aid_price' => 'DECIMAL(10,2) DEFAULT 0.00',
        'product_coupon_name' => "VARCHAR(200) DEFAULT ''",
        'product_coupon_amount' => 'DECIMAL(10,2) DEFAULT 0.00',
    ];
    foreach ($snapshotCols as $col => $def) {
        $exists = $db->query("SHOW COLUMNS FROM orders LIKE '$col'")->fetch();
        if (!$exists) $db->exec("ALTER TABLE orders ADD COLUMN $col $def");
    }

    // 活动报名：orders 表新增 8 列
    $activityOrderCols = [
        'activity_id' => 'INT DEFAULT 0',
        'activity_name' => "VARCHAR(200) DEFAULT ''",
        'activity_campus' => "VARCHAR(200) DEFAULT ''",
        'activity_adult_count' => 'INT DEFAULT 0',
        'activity_student_count' => 'INT DEFAULT 0',
        'adult_unit_price' => 'DECIMAL(10,2) DEFAULT 0.00',
        'student_unit_price' => 'DECIMAL(10,2) DEFAULT 0.00',
        'activity_fee_type' => "VARCHAR(20) DEFAULT ''",
    ];
    foreach ($activityOrderCols as $col => $def) {
        $exists = $db->query("SHOW COLUMNS FROM orders LIKE '$col'")->fetch();
        if (!$exists) $db->exec("ALTER TABLE orders ADD COLUMN $col $def");
    }

    // 历史数据回填
    $snapshotTablesReady = true;
    foreach (['price_items', 'discount_plans', 'coupons', 'teaching_aids'] as $tableName) {
        if (!$db->query("SHOW TABLES LIKE " . $db->quote($tableName))->fetch()) {
            $snapshotTablesReady = false;
            break;
        }
    }
    if ($snapshotTablesReady && $db->query("SELECT COUNT(*) FROM orders WHERE (discount_plan_name IS NULL OR discount_plan_name='') AND lesson_count > 0 LIMIT 1")->fetchColumn() > 0) {
        $db->exec("UPDATE orders o LEFT JOIN price_plans pp ON pp.name = o.plan_name AND pp.course_id = o.course_id LEFT JOIN price_items pi ON pi.plan_id = pp.id AND pi.name = o.item_name LEFT JOIN discount_plans d ON pi.discount_plan_id = d.id LEFT JOIN coupons c ON pi.coupon_id = c.id LEFT JOIN teaching_aids ta ON pi.teaching_aid_id = ta.id LEFT JOIN coupons pc ON pi.product_coupon_id = pc.id SET o.discount_plan_name = COALESCE(d.name,''), o.discount_plan_amount = COALESCE(d.discount_amount,0), o.coupon_name = COALESCE(c.name,''), o.coupon_amount = COALESCE(c.discount_amount,0), o.teaching_aid_name = COALESCE(ta.name,''), o.teaching_aid_price = COALESCE(ta.price,0), o.product_coupon_name = COALESCE(pc.name,''), o.product_coupon_amount = COALESCE(pc.discount_amount,0) WHERE (o.discount_plan_name IS NULL OR o.discount_plan_name='')");
    }

    // 兼容已有数据库：学生表添加学号字段
    $existingColsS = [];
    $colResS = $db->query("SHOW COLUMNS FROM students");
    while ($colRowS = $colResS->fetch(PDO::FETCH_ASSOC)) $existingColsS[] = $colRowS['Field'];
    if (!in_array('student_no', $existingColsS)) {
        $db->exec("ALTER TABLE students ADD COLUMN student_no VARCHAR(500) DEFAULT ''");
    }
    if (!in_array('student_type', $existingColsS)) {
        $db->exec("ALTER TABLE students ADD COLUMN student_type VARCHAR(500) DEFAULT '小课包'");
    }

    $db->exec("CREATE TABLE IF NOT EXISTS attendance_records (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        lesson_date VARCHAR(500) DEFAULT '',
        status VARCHAR(500) DEFAULT '出勤',
        notes VARCHAR(500) DEFAULT '',
        created_at VARCHAR(500) DEFAULT ''
    )");

    // 为已有表补充 order_id 字段（如果不存在）
    $colCheck = $db->query("SHOW COLUMNS FROM attendance_records LIKE 'order_id'")->fetch();
    if (!$colCheck) {
        $db->exec("ALTER TABLE attendance_records ADD COLUMN order_id INT DEFAULT 0");
    }
    // 补充新装缺失的列
    foreach ([
        "campus VARCHAR(500) DEFAULT ''",
        "class_id INT DEFAULT 0",
        "schedule_id INT DEFAULT 0",
        "class_name VARCHAR(500) DEFAULT ''",
        "teacher VARCHAR(500) DEFAULT ''",
        "subject_level1 VARCHAR(500) DEFAULT ''",
        "subject_level2 VARCHAR(500) DEFAULT ''",
        "class_time VARCHAR(500) DEFAULT ''",
        "attended_at VARCHAR(500) DEFAULT ''",
        "deducted_lessons INT DEFAULT 0",
        "consumed_amount DECIMAL(10,2) DEFAULT 0"
    ] as $colDef) {
        $colName = explode(' ', $colDef)[0];
        $check = $db->query("SHOW COLUMNS FROM attendance_records LIKE '$colName'")->fetch();
        if (!$check) $db->exec("ALTER TABLE attendance_records ADD COLUMN $colDef");
    }
    // 回填旧记录的 order_id（按 student_id + course_id 匹配订单）—— 兼容新装/列不存在
    try {
        $db->exec("UPDATE attendance_records a JOIN orders o ON o.student_id = a.student_id AND o.course_id = a.course_id SET a.order_id = o.id WHERE a.order_id = 0");
    } catch (PDOException $e) {
        // 新装数据库，列尚未完全迁移，静默跳过
    }

    $db->exec("CREATE TABLE IF NOT EXISTS absence_records (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        class_id INT DEFAULT 0,
        schedule_id INT DEFAULT 0,
        class_name VARCHAR(500) DEFAULT '',
        campus VARCHAR(500) DEFAULT '',
        teacher VARCHAR(500) DEFAULT '',
        subject_level1 VARCHAR(500) DEFAULT '',
        subject_level2 VARCHAR(500) DEFAULT '',
        class_time VARCHAR(500) DEFAULT '',
        lesson_date VARCHAR(500) DEFAULT '',
        student_name VARCHAR(500) DEFAULT '',
        phone VARCHAR(500) DEFAULT '',
        attendance_id INT DEFAULT 0,
        created_at VARCHAR(500) DEFAULT ''
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS parent_orders (
        id INT PRIMARY KEY AUTO_INCREMENT,
        parent_order_no VARCHAR(500) DEFAULT '',
        child_order_nos VARCHAR(500) DEFAULT '',
        course_name VARCHAR(500) DEFAULT '',
        total_lessons INT DEFAULT 0,
        student_name VARCHAR(500) DEFAULT '',
        phone VARCHAR(500) DEFAULT '',
        student_no VARCHAR(500) DEFAULT '',
        enroll_time VARCHAR(500) DEFAULT '',
        total_price REAL DEFAULT 0,
        cash_amount REAL DEFAULT 0,
        meituan_amount REAL DEFAULT 0,
        created_at VARCHAR(500) DEFAULT ''
    )");
    // 兼容已有数据库：parent_orders 新增 campus 字段
    $existingColsPO = [];
    $colResPO = $db->query("SHOW COLUMNS FROM parent_orders");
    while ($colRowPO = $colResPO->fetch(PDO::FETCH_ASSOC)) $existingColsPO[] = $colRowPO['Field'];
    if (!in_array('campus', $existingColsPO)) {
        $db->exec("ALTER TABLE parent_orders ADD COLUMN campus VARCHAR(500) DEFAULT ''");
    }
    $db->exec("CREATE TABLE IF NOT EXISTS classes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        course_id INT NOT NULL DEFAULT 0,
        name VARCHAR(500) NOT NULL DEFAULT '',
        class_type VARCHAR(500) NOT NULL DEFAULT '标准班',
        max_students INT NOT NULL DEFAULT 0,
        lesson_hours INT NOT NULL DEFAULT 0,
        can_trial VARCHAR(500) NOT NULL DEFAULT '是',
        campus VARCHAR(500) NOT NULL DEFAULT '',
        remark VARCHAR(500) NOT NULL DEFAULT '',
        created_at VARCHAR(500) NOT NULL DEFAULT ''
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS schedules (
        id INT PRIMARY KEY AUTO_INCREMENT,
        class_id INT NOT NULL DEFAULT 0,
        rule_type VARCHAR(500) NOT NULL DEFAULT '按规则排课',
        start_date VARCHAR(500) NOT NULL DEFAULT '',
        end_date VARCHAR(500) NOT NULL DEFAULT '',
        weekdays VARCHAR(500) NOT NULL DEFAULT '',
        time_slots VARCHAR(500) NOT NULL DEFAULT '{}',
        holiday_enabled INT NOT NULL DEFAULT 0,
        teacher VARCHAR(500) NOT NULL DEFAULT '',
        classroom VARCHAR(500) NOT NULL DEFAULT '',
        created_at VARCHAR(500) NOT NULL DEFAULT ''
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS classrooms (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(500) NOT NULL UNIQUE,
        capacity INT DEFAULT 0,
        campus VARCHAR(500) DEFAULT '',
        remark VARCHAR(500) DEFAULT '',
        created_at VARCHAR(500) DEFAULT ''
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS class_students (
        id INT PRIMARY KEY AUTO_INCREMENT,
        class_id INT NOT NULL,
        student_id INT NOT NULL,
        created_at VARCHAR(500) NOT NULL DEFAULT '',
        UNIQUE(class_id, student_id)
    )");
    try { $db->exec("ALTER TABLE class_students ADD COLUMN left_at VARCHAR(500) DEFAULT ''"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE class_students ADD COLUMN joined_at VARCHAR(500) DEFAULT ''"); } catch (PDOException $e) {}

    $db->exec("CREATE TABLE IF NOT EXISTS class_attendance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        class_id INT NOT NULL DEFAULT 0,
        schedule_id INT NOT NULL DEFAULT 0,
        session_date VARCHAR(500) NOT NULL DEFAULT '',
        student_id INT NOT NULL DEFAULT 0,
        status VARCHAR(500) NOT NULL DEFAULT '出勤',
        deducted_lessons INT DEFAULT 0,
        deducted_order_id INT DEFAULT 0,
        deduction_json TEXT,
        is_temporary INT DEFAULT 0,
        created_at VARCHAR(500) NOT NULL DEFAULT ''
    )");

    // 兼容已有数据库：class_attendance 新增缺失字段
    foreach ([
        "deducted_order_id INT DEFAULT 0",
        "deduction_json TEXT",
        "is_temporary INT DEFAULT 0",
    ] as $colDef) {
        $colName = explode(' ', $colDef)[0];
        $check = $db->query("SHOW COLUMNS FROM class_attendance LIKE '$colName'")->fetch();
        if (!$check) $db->exec("ALTER TABLE class_attendance ADD COLUMN $colDef");
    }

    // 活动考勤：class_attendance 新增字段
    foreach ([
        "activity_id INT DEFAULT 0",
        "activity_order_id INT DEFAULT 0",
        "consumed_amount DECIMAL(10,2) DEFAULT 0.00",
        "adult_attended INT DEFAULT 0",
        "student_attended INT DEFAULT 0",
        "deduction_breakdown TEXT",
    ] as $colDef) {
        $colName = explode(' ', $colDef)[0];
        $check = $db->query("SHOW COLUMNS FROM class_attendance LIKE '$colName'")->fetch();
        if (!$check) $db->exec("ALTER TABLE class_attendance ADD COLUMN $colDef");
    }


    // 退费记录表
    $db->exec("CREATE TABLE IF NOT EXISTS refund_records (
        id INT PRIMARY KEY AUTO_INCREMENT,
        order_id INT NOT NULL DEFAULT 0,
        student_id INT NOT NULL DEFAULT 0,
        campus VARCHAR(500) DEFAULT '',
        course_name VARCHAR(500) DEFAULT '',
        total_lessons INT DEFAULT 0,
        total_amount DECIMAL(10,2) DEFAULT 0.00,
        consumed_lessons INT DEFAULT 0,
        consumed_amount DECIMAL(10,2) DEFAULT 0.00,
        remaining_lessons INT DEFAULT 0,
        remaining_amount DECIMAL(10,2) DEFAULT 0.00,
        custom_deduction DECIMAL(10,2) DEFAULT 0.00,
        actual_refund DECIMAL(10,2) DEFAULT 0.00,
        bank_name VARCHAR(500) DEFAULT '',
        bank_account VARCHAR(500) DEFAULT '',
        account_holder VARCHAR(500) DEFAULT '',
        refund_reason TEXT,
        status VARCHAR(20) DEFAULT '待审批',
        approval_stage VARCHAR(10) DEFAULT '一级审批',
        reject_reason TEXT,
        approver1 VARCHAR(500) DEFAULT '',
        approver2 VARCHAR(500) DEFAULT '',
        approver3 VARCHAR(500) DEFAULT '',
        created_at VARCHAR(500) DEFAULT '',
        updated_at VARCHAR(500) DEFAULT ''
    )");

    // orders 表新增退款状态字段
    $refundStatusCol = $db->query("SHOW COLUMNS FROM orders LIKE 'refund_status'")->fetch();
    if (!$refundStatusCol) {
        $db->exec("ALTER TABLE orders ADD COLUMN refund_status VARCHAR(10) DEFAULT '正常'");
    }

    // 学员-校区-学科-授课老师 关联表
    $db->exec("CREATE TABLE IF NOT EXISTS student_subject_teacher (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        campus_id INT NOT NULL,
        subject_id INT NOT NULL,
        teacher_id INT NOT NULL DEFAULT 0,
        created_at VARCHAR(500) DEFAULT '',
        UNIQUE KEY uk_sct (student_id, campus_id, subject_id)
    )");

    // 学员账户表
    $db->exec("CREATE TABLE IF NOT EXISTS student_accounts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL UNIQUE,
        balance DECIMAL(10,2) DEFAULT 0.00,
        total_deposit DECIMAL(10,2) DEFAULT 0.00,
        total_consume DECIMAL(10,2) DEFAULT 0.00,
        total_refund DECIMAL(10,2) DEFAULT 0.00,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 账户流水表
    $db->exec("CREATE TABLE IF NOT EXISTS account_transactions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        type ENUM('deposit','consume','refund') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        balance_after DECIMAL(10,2) NOT NULL,
        ref_type VARCHAR(50) DEFAULT '',
        ref_id INT DEFAULT 0,
        campus VARCHAR(500) DEFAULT '',
        note VARCHAR(500) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student (student_id),
        INDEX idx_created (created_at)
    )");

    // 兼容已有数据库：account_transactions 添加支付方式字段
    $colPM = $db->query("SHOW COLUMNS FROM account_transactions LIKE 'payment_method'")->fetch();
    if (!$colPM) $db->exec("ALTER TABLE account_transactions ADD COLUMN payment_method VARCHAR(50) DEFAULT ''");

    // 兼容已有数据库：account_transactions 添加学科字段
    $colSub = $db->query("SHOW COLUMNS FROM account_transactions LIKE 'subject'")->fetch();
    if (!$colSub) $db->exec("ALTER TABLE account_transactions ADD COLUMN subject VARCHAR(200) DEFAULT '' AFTER campus");

    // 兼容已有数据库：refund_records 添加项目/内容字段
    $colProj = $db->query("SHOW COLUMNS FROM refund_records LIKE 'project'")->fetch();
    if (!$colProj) $db->exec("ALTER TABLE refund_records ADD COLUMN project VARCHAR(20) DEFAULT '课程' AFTER id");
    $colCont = $db->query("SHOW COLUMNS FROM refund_records LIKE 'content'")->fetch();
    if (!$colCont) {
        $db->exec("ALTER TABLE refund_records ADD COLUMN content VARCHAR(500) DEFAULT '' AFTER project");
        // 历史数据回填
        $db->exec("UPDATE refund_records SET content = course_name WHERE project = '课程'");
    }
    // 兼容已有数据库：refund_records 添加学科/退费方式
    $colSubj = $db->query("SHOW COLUMNS FROM refund_records LIKE 'subject_level1'")->fetch();
    if (!$colSubj) $db->exec("ALTER TABLE refund_records ADD COLUMN subject_level1 VARCHAR(100) DEFAULT '' AFTER content");
    $colRfM = $db->query("SHOW COLUMNS FROM refund_records LIKE 'refund_method'")->fetch();
    if (!$colRfM) {
        $db->exec("ALTER TABLE refund_records ADD COLUMN refund_method VARCHAR(20) DEFAULT '转账' AFTER subject_level1");
        $db->exec("UPDATE refund_records SET refund_method='转账' WHERE refund_method=''");
        }

        // 优惠管理模块建表
        $db->exec("CREATE TABLE IF NOT EXISTS discount_plans (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(200) NOT NULL DEFAULT '',
            plan_type VARCHAR(20) NOT NULL DEFAULT '新报',
            discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            start_date VARCHAR(20) DEFAULT '',
            end_date VARCHAR(20) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS discount_plan_campuses (
            id INT PRIMARY KEY AUTO_INCREMENT,
            plan_id INT NOT NULL,
            campus_id INT NOT NULL,
            INDEX idx_dpc_plan (plan_id),
            INDEX idx_dpc_campus (campus_id),
            FOREIGN KEY (plan_id) REFERENCES discount_plans(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS discount_plan_subjects (
            id INT PRIMARY KEY AUTO_INCREMENT,
            plan_id INT NOT NULL,
            subject_id INT NOT NULL,
            INDEX idx_dps_plan (plan_id),
            INDEX idx_dps_subject (subject_id),
            FOREIGN KEY (plan_id) REFERENCES discount_plans(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 优惠券模块建表
            try { $db->exec("ALTER TABLE price_items ADD COLUMN discount_plan_id INT DEFAULT NULL AFTER actual_price"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE price_items ADD COLUMN coupon_id INT DEFAULT NULL AFTER discount_plan_id"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE price_items ADD COLUMN teaching_aid_id INT DEFAULT NULL AFTER coupon_id"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE price_items ADD COLUMN product_coupon_id INT DEFAULT NULL AFTER teaching_aid_id"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE price_items ADD COLUMN gifted_lessons INT DEFAULT 0 AFTER product_coupon_id"); } catch (PDOException $e) {}

    $db->exec("CREATE TABLE IF NOT EXISTS coupons (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(200) NOT NULL DEFAULT '',
            coupon_type VARCHAR(20) NOT NULL DEFAULT '课程券',
            discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            start_date VARCHAR(20) DEFAULT '',
            end_date VARCHAR(20) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS coupon_campuses (
            id INT PRIMARY KEY AUTO_INCREMENT, coupon_id INT NOT NULL, campus_id INT NOT NULL,
            INDEX idx_cc_coupon (coupon_id), FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS coupon_subjects (
            id INT PRIMARY KEY AUTO_INCREMENT, coupon_id INT NOT NULL, subject_id INT NOT NULL,
            INDEX idx_cs_coupon (coupon_id), FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS coupon_records (
            id INT PRIMARY KEY AUTO_INCREMENT, coupon_id INT NOT NULL,
            coupon_name VARCHAR(200) DEFAULT '', student_name VARCHAR(200) DEFAULT '',
            phone VARCHAR(50) DEFAULT '', issuer VARCHAR(100) DEFAULT '',
            issued_at DATETIME DEFAULT CURRENT_TIMESTAMP, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cr_coupon (coupon_id), FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 兼容已有数据库：coupon_records 添加 usage_status 字段
            $colCR = $db->query("SHOW COLUMNS FROM coupon_records LIKE 'usage_status'")->fetch();
            if (!$colCR) $db->exec("ALTER TABLE coupon_records ADD COLUMN usage_status VARCHAR(20) DEFAULT '未使用' AFTER phone");

            // 画具管理
            $db->exec("CREATE TABLE IF NOT EXISTS teaching_aids (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(200) NOT NULL DEFAULT '',
                unit VARCHAR(20) NOT NULL DEFAULT '个',
                subject_id INT NOT NULL DEFAULT 0,
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                status VARCHAR(10) NOT NULL DEFAULT '上架',
                remark TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 兼容已有数据库：teaching_aids 添加 type 字段（教材包/画具）
            $colTA = $db->query("SHOW COLUMNS FROM teaching_aids LIKE 'type'")->fetch();
            if (!$colTA) $db->exec("ALTER TABLE teaching_aids ADD COLUMN type VARCHAR(20) DEFAULT '画具' AFTER subject_id");

            $db->exec("CREATE TABLE IF NOT EXISTS teaching_aid_campuses (
                id INT PRIMARY KEY AUTO_INCREMENT,
                teaching_aid_id INT NOT NULL,
                campus_id INT NOT NULL,
                INDEX idx_tac_aid (teaching_aid_id),
                INDEX idx_tac_campus (campus_id),
                FOREIGN KEY (teaching_aid_id) REFERENCES teaching_aids(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $db->exec("CREATE TABLE IF NOT EXISTS teaching_aid_sales (
                id INT PRIMARY KEY AUTO_INCREMENT,
                teaching_aid_id INT NOT NULL,
                student_id INT NOT NULL,
                student_name VARCHAR(200) NOT NULL DEFAULT '',
                student_no VARCHAR(100) NOT NULL DEFAULT '',
                teaching_aid_name VARCHAR(200) NOT NULL DEFAULT '',
                type VARCHAR(50) NOT NULL DEFAULT '',
                quantity INT NOT NULL DEFAULT 1,
                unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                cash_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                meituan_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                account_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                campus VARCHAR(500) NOT NULL DEFAULT '',
                sold_at DATETIME,
                sold_by VARCHAR(100) NOT NULL DEFAULT '',
                remark VARCHAR(500) NOT NULL DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (teaching_aid_id) REFERENCES teaching_aids(id) ON DELETE RESTRICT,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // teaching_aid_sales 表迁移（加列兼容块）
            $tasCols = [];
            $tasRes = $db->query("SHOW COLUMNS FROM teaching_aid_sales");
            while ($c = $tasRes->fetch(PDO::FETCH_ASSOC)) $tasCols[] = $c['Field'];
            // 重命名旧列 + 新增缺失列
            if (in_array('teaching_aid_type', $tasCols) && !in_array('type', $tasCols))
                try { $db->exec("ALTER TABLE teaching_aid_sales CHANGE COLUMN teaching_aid_type type VARCHAR(50) NOT NULL DEFAULT ''"); } catch (PDOException $e) {}
            if (in_array('total_price', $tasCols) && !in_array('total_amount', $tasCols))
                try { $db->exec("ALTER TABLE teaching_aid_sales CHANGE COLUMN total_price total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00"); } catch (PDOException $e) {}
            if (in_array('campus_name', $tasCols) && !in_array('campus', $tasCols))
                try { $db->exec("ALTER TABLE teaching_aid_sales CHANGE COLUMN campus_name campus VARCHAR(500) NOT NULL DEFAULT ''"); } catch (PDOException $e) {}
            if (!in_array('sold_at', $tasCols))
                try { $db->exec("ALTER TABLE teaching_aid_sales ADD COLUMN sold_at DATETIME"); } catch (PDOException $e) {}
            if (!in_array('sold_by', $tasCols))
                try { $db->exec("ALTER TABLE teaching_aid_sales ADD COLUMN sold_by VARCHAR(100) NOT NULL DEFAULT ''"); } catch (PDOException $e) {}
            // 修改 student_name 长度
            try { $db->exec("ALTER TABLE teaching_aid_sales MODIFY COLUMN student_name VARCHAR(200) NOT NULL DEFAULT ''"); } catch (PDOException $e) {}
            // 修改 remark 类型
            try { $db->exec("ALTER TABLE teaching_aid_sales MODIFY COLUMN remark VARCHAR(500) NOT NULL DEFAULT ''"); } catch (PDOException $e) {}
            // 外键兼容（可能因历史数据失败）
            try { $db->exec("ALTER TABLE teaching_aid_sales ADD FOREIGN KEY (teaching_aid_id) REFERENCES teaching_aids(id) ON DELETE RESTRICT"); } catch (PDOException $e) {}
            try { $db->exec("ALTER TABLE teaching_aid_sales ADD FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT"); } catch (PDOException $e) {}
            // 回填 sold_at（历史数据用 created_at）
            try { $db->exec("UPDATE teaching_aid_sales SET sold_at = created_at WHERE sold_at IS NULL"); } catch (PDOException $e) {}

            $db->exec("CREATE TABLE IF NOT EXISTS class_periods (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(200) NOT NULL DEFAULT '',
        start_time VARCHAR(5) NOT NULL DEFAULT '',
        end_time VARCHAR(5) NOT NULL DEFAULT '',
        sort_order INT NOT NULL DEFAULT 0,
        campus VARCHAR(500) NOT NULL DEFAULT '',
        created_at VARCHAR(500) NOT NULL DEFAULT ''
    )");

    // 兼容已有数据库：class_periods 添加校区字段
    $colCP = $db->query("SHOW COLUMNS FROM class_periods LIKE 'campus'")->fetch();
    if (!$colCP) $db->exec("ALTER TABLE class_periods ADD COLUMN campus VARCHAR(500) NOT NULL DEFAULT ''");

    // ==================== 活动管理建表 ====================
    $db->exec("CREATE TABLE IF NOT EXISTS activities (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(200) NOT NULL DEFAULT '',
        subject_level1 VARCHAR(200) NOT NULL DEFAULT '',
        reg_start_date DATE,
        reg_end_date DATE,
        adult_fee_mode VARCHAR(20) NOT NULL DEFAULT 'fee_only' COMMENT '仅收费/收费+扣课时/仅扣课时',
        student_fee_mode VARCHAR(20) NOT NULL DEFAULT 'fee_only',
        adult_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        student_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS activity_campuses (
        id INT PRIMARY KEY AUTO_INCREMENT,
        activity_id INT NOT NULL,
        campus_name VARCHAR(200) NOT NULL DEFAULT '',
        max_capacity INT NOT NULL DEFAULT 0 COMMENT '0=不限',
        FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS activity_subject_deductions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        activity_id INT NOT NULL,
        fee_type VARCHAR(10) NOT NULL DEFAULT 'student' COMMENT 'adult/student',
        subject_level1 VARCHAR(200) NOT NULL DEFAULT '',
        deduct_lessons INT NOT NULL DEFAULT 1,
        FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 活动报名人数统计缓存表
    $db->exec("CREATE TABLE IF NOT EXISTS activity_enrollment_counts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        activity_id INT NOT NULL,
        campus_name VARCHAR(200) NOT NULL DEFAULT '',
        adult_count INT NOT NULL DEFAULT 0,
        student_count INT NOT NULL DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_activity_campus (activity_id, campus_name),
        FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ==================== 税率设置表 ====================
    $db->exec("CREATE TABLE IF NOT EXISTS tax_rates (
        id INT PRIMARY KEY AUTO_INCREMENT,
        campus_id INT NOT NULL DEFAULT 0,
        course_tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        product_tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        updated_at VARCHAR(500) DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 常用列表/详情查询索引（重复创建会被捕获忽略）
    foreach ([
        ['orders', 'idx_orders_parent_order_no', 'parent_order_no'],
        ['orders', 'idx_orders_order_no', 'order_no'],
        ['orders', 'idx_orders_created_at', 'created_at'],
        ['orders', 'idx_orders_paid_at', 'paid_at'],
        ['orders', 'idx_orders_student_id', 'student_id'],
        ['orders', 'idx_orders_course_id', 'course_id'],
        ['orders', 'idx_orders_campus', 'campus'],
        ['orders', 'idx_orders_pay_status', 'pay_status'],
        ['students', 'idx_students_student_no', 'student_no'],
        ['students', 'idx_students_phone', 'phone'],
        ['students', 'idx_students_name', 'name'],
        ['resources', 'idx_resources_phone', 'phone'],
        ['resources', 'idx_resources_updated_at', 'updated_at'],
        ['resources', 'idx_resources_pool_type', 'pool_type'],
        ['employees', 'idx_employees_name', 'name'],
        ['employees', 'idx_employees_department', 'department'],
        ['appointments', 'idx_appointments_time', 'appointment_time'],
        ['class_attendance', 'idx_class_attendance_session', 'class_id, schedule_id, session_date'],
        ['class_attendance', 'idx_class_attendance_activity', 'activity_id'],
        ['teaching_aid_sales', 'idx_tas_sold_at', 'sold_at'],
        ['teaching_aid_sales', 'idx_tas_student_name', 'student_name'],
        ['teaching_aid_sales', 'idx_tas_teaching_aid_name', 'teaching_aid_name']
    ] as $idxDef) {
        try {
            $db->exec("CREATE INDEX {$idxDef[1]} ON {$idxDef[0]} ({$idxDef[2]})");
        } catch (PDOException $e) {}
    }
}
