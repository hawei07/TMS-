<?php
error_reporting(E_ALL);
// PHP 内置服务器：静态文件直接返回，不经过 PHP 处理
if (php_sapi_name() === 'cli-server') {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $uri;
    if ($uri !== '/' && is_file($file)) {
        return false;
    }
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
header('Content-Type: text/html; charset=utf-8');

try {
    $db = new PDO('mysql:host=127.0.0.1;port=3306;dbname=tms_db;charset=utf8mb4', 'root', 'root', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $db->exec("SET NAMES utf8mb4");
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

// 初始化表
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
    // 按 attendance_records 重算订单 consumed_lessons
    $db->exec("UPDATE orders o SET o.consumed_lessons = COALESCE((SELECT SUM(a.deducted_lessons) FROM attendance_records a WHERE a.order_id = o.id AND a.status = '出勤'), 0) WHERE o.refund_status != '已退费' AND o.id NOT IN (SELECT order_id FROM refund_records WHERE status NOT IN ('已退费', '审批驳回'))");
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

date_default_timezone_set('Asia/Shanghai');

$action = $_GET['action'] ?? '';
if ($action) { handleApi(); exit; }
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function now() { return date('Y-m-d H:i:s'); }
function utf8_strlen($s) { return preg_match_all('/./us', $s ?? ''); }
function generateOrderNo($db) {
    do {
        $ts = substr(strval(time()), -10);
        $rand = str_pad(strval(random_int(0, 999999)), 6, '0', STR_PAD_LEFT);
        $no = $ts . $rand;
        $stmt = $db->query("SELECT COUNT(*) FROM orders WHERE order_no='$no'");
        $exists = $stmt->fetchColumn();
    } while (intval($exists) > 0);
    return $no;
}
function generateStudentNo($db) {
    do {
        $ts = substr(strval(time()), -8);
        $rand = str_pad(strval(random_int(0, 99)), 2, '0', STR_PAD_LEFT);
        $no = $ts . $rand;
        $stmt = $db->query("SELECT COUNT(*) FROM students WHERE student_no='$no'");
        $exists = $stmt->fetchColumn();
    } while (intval($exists) > 0);
    return $no;
}
function json($data) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }

// ==================== Excel 解析工具函数（纯 PHP，ZipArchive + XML） ====================

/**
 * 解析 .xlsx 文件，返回二维数组（每行是一个索引数组）
 */
function parseXlsx($filePath) {
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new Exception('无法打开 xlsx 文件（无效的 ZIP 包）');
    }

    // 1. 读取共享字符串表
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $sx = simplexml_load_string($ssXml);
        $ns = $sx->getNamespaces(true);
        $ssNs = $ns[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        foreach ($sx->si as $si) {
            $t = $si->t;
            if ($t !== null) {
                $sharedStrings[] = (string)$t;
            } else {
                // 富文本：合并所有 t 元素
                $txts = [];
                foreach ($si->r as $r) {
                    $tt = $r->t;
                    if ($tt !== null) $txts[] = (string)$tt;
                }
                $sharedStrings[] = implode('', $txts);
            }
        }
    }

    // 2. 解析第一个工作表
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        throw new Exception('xlsx 文件中未找到工作表');
    }

    $sx = simplexml_load_string($sheetXml);
    $ns = $sx->getNamespaces(true);
    $mainNs = $ns[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    $rows = [];
    foreach ($sx->sheetData->row as $rowEl) {
        $rowData = [];
        foreach ($rowEl->c as $c) {
            $cellRef = (string)$c['r'];
            $col = preg_replace('/\d/', '', $cellRef);
            $colIdx = colLetterToIndex($col);
            $type = (string)$c['t'];
            $v = (string)$c->v;

            if ($type === 's' && $v !== '') {
                // 共享字符串引用
                $idx = (int)$v;
                $val = $sharedStrings[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                $val = (string)$c->is->t;
            } else {
                $val = $v;
            }

            // 确保行数组足够长
            while (count($rowData) <= $colIdx) {
                $rowData[] = '';
            }
            $rowData[$colIdx] = $val;
        }
        $rows[] = $rowData;
    }

    $zip->close();
    return $rows;
}

function colLetterToIndex($col) {
    $col = strtoupper($col);
    $idx = 0;
    $len = strlen($col);
    for ($i = 0; $i < $len; $i++) {
        $idx = $idx * 26 + (ord($col[$i]) - ord('A') + 1);
    }
    return $idx - 1;
}

/**
 * 生成 xlsx 模板文件（与 generate_template.php 相同逻辑）
 */
function generateTemplateXlsx($filePath, $headers) {
    if (!class_exists('ZipArchive')) {
        return false;
    }
    $zip = new ZipArchive();
    if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    $colWidths = [12, 16, 14, 20, 14, 12, 8, 14];
    $colLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    // sharedStrings.xml
    $ssItems = '';
    foreach ($headers as $h) {
        $ssItems .= '<si><t>' . htmlspecialchars($h, ENT_XML1, 'UTF-8') . '</t></si>';
    }
    $cnt = count($headers);
    $sharedStrings = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$cnt.'" uniqueCount="'.$cnt.'">'.$ssItems.'</sst>';
    $zip->addFromString('xl/sharedStrings.xml', $sharedStrings);

    // sheet1.xml
    $colsXml = '';
    for ($i = 0; $i < $cnt; $i++) {
        $colsXml .= '<col min="'.($i+1).'" max="'.($i+1).'" width="'.$colWidths[$i].'" customWidth="1"/>';
    }
    $rowCells = '';
    for ($i = 0; $i < $cnt; $i++) {
        $rowCells .= '<c r="'.$colLetters[$i].'1" t="s"><v>'.$i.'</v></c>';
    }
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><cols>'.$colsXml.'</cols><sheetData><row r="1">'.$rowCells.'</row></sheetData></worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

    // styles.xml
    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Microsoft YaHei"/></font><font><b/><sz val="11"/><name val="Microsoft YaHei"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>';
    $zip->addFromString('xl/styles.xml', $stylesXml);

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $zip->addFromString('xl/workbook.xml', $workbookXml);

    $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $zip->addFromString('xl/_rels/workbook.xml.rels', $relsXml);

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
    $zip->addFromString('[Content_Types].xml', $contentTypes);

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $zip->addFromString('_rels/.rels', $rootRels);

    $zip->close();
    return true;
}

function computeSessions($schedule) {
    $sessions = [];
    $timeSlots = json_decode($schedule['time_slots'] ?? '{}', true) ?: [];
    $weekdaysStr = $schedule['weekdays'] ?? '';
    $ruleType = $schedule['rule_type'] ?? '';
    $startDate = $schedule['start_date'] ?? '';
    $endDate = $schedule['end_date'] ?? '';
    
    if (empty($startDate) || empty($endDate)) return $sessions;
    
    $dayOfWeekMap = ['周一', '周二', '周三', '周四', '周五', '周六', '周日'];
    
    if ($ruleType === '按规则排课') {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $end->modify('+1 day');
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);
        $weekdaySet = array_flip(array_filter(explode(',', $weekdaysStr), 'strlen'));
        foreach ($period as $date) {
            $dow = $date->format('N');
            $dowKey = (string)$dow;
            if (!isset($weekdaySet[$dowKey])) continue;
            if (isset($timeSlots[$dowKey]) && is_array($timeSlots[$dowKey])) {
                $sessions[] = [
                    'date' => $date->format('Y-m-d'),
                    'dayOfWeek' => $dayOfWeekMap[$dow - 1],
                    'start' => $timeSlots[$dowKey]['start'] ?? '',
                    'end' => $timeSlots[$dowKey]['end'] ?? ''
                ];
            }
        }
    } else {
        // 按日期排课: start_date 可能是逗号分隔的多个日期或单个日期范围
        $dates = array_filter(explode(',', $startDate), 'strlen');
        if (count($dates) > 1) {
            // 逗号分隔的多日期
            foreach ($dates as $dateStr) {
                $dateStr = trim($dateStr);
                $date = new DateTime($dateStr);
                $dow = $date->format('N');
                $sessions[] = [
                    'date' => $date->format('Y-m-d'),
                    'dayOfWeek' => $dayOfWeekMap[$dow - 1],
                    'start' => '',
                    'end' => ''
                ];
            }
        } else {
            // 单日期范围
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $end->modify('+1 day');
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($start, $interval, $end);
            foreach ($period as $date) {
                $dow = $date->format('N');
                $sessions[] = [
                    'date' => $date->format('Y-m-d'),
                    'dayOfWeek' => $dayOfWeekMap[$dow - 1],
                    'start' => '',
                    'end' => ''
                ];
            }
        }
    }
    return $sessions;
}

function handleApi() {
    global $db;
    $action = $_GET['action'];
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    error_log('DEBUG: handleApi action=' . $action . ' method=' . $method);
    error_log('DEBUG: handleApi input=' . json_encode($input, JSON_UNESCAPED_UNICODE));

    switch ($action) {
        case 'get_resources':
            $page = max(1, intval($_GET['page'] ?? 1));
            $pageSize = max(1, min(100, intval($_GET['page_size'] ?? 20)));
            $keyword = $_GET['keyword'] ?? '';
            $followStatus = $_GET['follow_status'] ?? '';
            $poolType = $_GET['pool_type'] ?? '我的资源';
            $assignedTo = $_GET['assigned_to'] ?? '';
            $assignedDept = $_GET['assigned_dept'] ?? '';
            $name = $_GET['name'] ?? '';
            $phone = $_GET['phone'] ?? '';
            $source = $_GET['source'] ?? '';
            $resourceId = $_GET['resource_id'] ?? '';
            $createdStart = $_GET['created_start'] ?? '';
            $createdEnd = $_GET['created_end'] ?? '';

            $where = ["pool_type = :pt"];
            $params = [':pt' => $poolType];
            if ($keyword) {
                $where[] = "(name LIKE :kw1 OR phone LIKE :kw2 OR source LIKE :kw3)";
                $params[':kw1'] = "%$keyword%"; $params[':kw2'] = "%$keyword%"; $params[':kw3'] = "%$keyword%"; $params[':kw3'] = "%$keyword%"; $params[':kw3'] = "%$keyword%";
            }
            if ($followStatus) { $where[] = "follow_status = :fs"; $params[':fs'] = $followStatus; }
            if ($assignedTo) { $where[] = "assigned_to = :at"; $params[':at'] = $assignedTo; }
            if ($assignedDept) { $where[] = "assigned_to IN (SELECT name FROM employees WHERE department = :ad)"; $params[':ad'] = $assignedDept; }
            if ($name) { $where[] = "name LIKE :n"; $params[':n'] = "%$name%"; }
            if ($phone) { $where[] = "phone LIKE :ph"; $params[':ph'] = "%$phone%"; }
            if ($source) { $where[] = "source = :src"; $params[':src'] = $source; }
            if ($resourceId) { $where[] = "r.id = :rid"; $params[':rid'] = intval($resourceId); }
            if ($createdStart) { $where[] = "created_at >= :cs"; $params[':cs'] = $createdStart; }
            if ($createdEnd) { $where[] = "created_at <= :ce"; $params[':ce'] = $createdEnd . ' 23:59:59'; }
            $whereStr = implode(' AND ', $where);

            $countStmt = $db->prepare("SELECT COUNT(*) FROM resources r WHERE $whereStr");
            foreach ($params as $k => $v) $countStmt->bindValue($k, $v, $k === ':rid' ? PDO::PARAM_INT : PDO::PARAM_STR);
            $countStmt->execute(); $total = $countStmt->fetch(PDO::FETCH_NUM)[0];
            $total = $total ? intval($total) : 0;
            $offset = ($page - 1) * $pageSize;
            $stmt = $db->prepare("SELECT r.*, e.department AS assigned_dept FROM resources r LEFT JOIN employees e ON r.assigned_to = e.name WHERE $whereStr ORDER BY updated_at DESC LIMIT :lim OFFSET :off");
            foreach ($params as $k => $v) $stmt->bindValue($k, $v, $k === ':rid' ? PDO::PARAM_INT : PDO::PARAM_STR);
            $stmt->bindValue(':lim', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $rows = [];
$stmt->execute();
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $r;
            json(['total' => $total, 'page' => $page, 'page_size' => $pageSize, 'data' => $rows]);

        case 'add_resource':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $name = trim($input['name'] ?? '');
            $phone = trim($input['phone'] ?? '');
            if ($name === '') json(['error' => '姓名不能为空']);
            if ($phone === '') json(['error' => '手机号不能为空']);
            // 手机号唯一性校验
            if ($phone !== '') {
                $stmt = $db->query("SELECT COUNT(*) FROM resources WHERE phone = " . $db->quote($phone) . "");
                $dup = $stmt->fetchColumn();
                if (intval($dup) > 0) json(['error' => '手机号已存在，请勿重复录入']);
            }
            $n = now();
            $stmt = $db->prepare("INSERT INTO resources (name,phone,source,intention_level,gender,birth_date,follow_status,status,assigned_to,pool_type,created_at,updated_at) VALUES (:n,:p,:s,:i,:g,:bd,:fs,:st,:a,:pt,:c,:u)");
            $stmt->bindValue(':n', $name);
            $stmt->bindValue(':p', $phone);
            $stmt->bindValue(':s', $input['source']??'');
            $stmt->bindValue(':i', $input['intention_level']??'');
            $stmt->bindValue(':g', $input['gender']??'');
            $stmt->bindValue(':bd', $input['birth_date']??'');
            $stmt->bindValue(':fs', $input['follow_status']??'');
            $stmt->bindValue(':st', $input['status']??'待跟进');
            $stmt->bindValue(':a', $input['assigned_to']??'');
            $stmt->bindValue(':pt', $input['pool_type']??'我的资源');
            $stmt->bindValue(':c', $n); $stmt->bindValue(':u', $n);
            $stmt->execute();
            json(['id' => $db->lastInsertId(), 'message' => '新增成功']);

        case 'update_resource':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $rid = intval($input['id'] ?? 0);
            // 手机号唯一性校验（仅当传入且非空时校验；排除自身id）
            if (isset($input['phone']) && trim($input['phone'] ?? '') !== '') {
                $phone = trim($input['phone']);
                $stmt = $db->query("SELECT COUNT(*) FROM resources WHERE phone = " . $db->quote($phone) . " AND id != $rid");
                $dup = $stmt->fetchColumn();
                if (intval($dup) > 0) json(['error' => '手机号已存在，请勿重复录入']);
            }
            // 校验归属人是否在员工名册中存在
            if (isset($input['assigned_to']) && trim($input['assigned_to'] ?? '') !== '') {
                $assignedTo = trim($input['assigned_to']);
                $stmt = $db->query("SELECT COUNT(*) FROM employees WHERE name = " . $db->quote($assignedTo) . "");
                $empCount = $stmt->fetchColumn();
                if (intval($empCount) === 0) json(['error' => '归属人不存在于员工名册中，请从员工名册中选择']);
            }
            // 动态构建 UPDATE：仅更新 $input 中实际传入的字段（排除 id）
            $allowedFields = ['name','phone','source','intention_level','gender','birth_date','follow_status','status','assigned_to','pool_type'];
            $sets = [];
            $params = [];
            foreach ($allowedFields as $f) {
                if (array_key_exists($f, $input)) {
                    $sets[] = "$f = :$f";
                    $params[":$f"] = $input[$f];
                }
            }
            if (empty($sets)) json(['error' => '没有要更新的字段']);
            $sets[] = "updated_at = :u";
            $params[':u'] = now();
            $params[':id'] = $rid;
            $sql = "UPDATE resources SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, $k === ':id' ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            json(['message' => '更新成功']);

        case 'delete_resource':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $rid = intval($input['id'] ?? 0);
            $db->exec("DELETE FROM resources WHERE id=$rid");
            $db->exec("DELETE FROM appointments WHERE resource_id=$rid");
            $db->exec("DELETE FROM communication_records WHERE resource_id=$rid");
            json(['message' => '删除成功']);

        case 'batch_import':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);

            // 模式判断：有文件上传走 Excel 模式，否则走 JSON 模式（兼容旧版内联导入）
            $hasFile = isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK;

            if (!$hasFile) {
                // === JSON 模式（兼容旧版内联批量导入） ===
                $items = $input['items'] ?? [];
                $poolType = $input['pool_type'] ?? '我的资源';
                $n = now(); $count = 0; $failCount = 0; $failures = [];
                $stmt = $db->prepare("INSERT INTO resources (name,phone,source,intention_level,gender,birth_date,follow_status,status,assigned_to,pool_type,created_at,updated_at) VALUES (:n,:p,:s,:i,:g,:bd,:fs,:st,:a,:pt,:c,:u)");
                foreach ($items as $idx => $item) {
                    $rowNum = $idx + 1;
                    if (empty(trim($item['name'] ?? '')) || empty(trim($item['phone'] ?? ''))) { $failCount++; $failures[] = ['row' => $rowNum, 'reason' => '缺少必填字段：姓名或手机号']; continue; }
                    $phoneVal = trim($item['phone'] ?? '');
                    // 手机号唯一性校验（空手机号不校验）
                    if ($phoneVal !== '') {
                        $stmt = $db->query("SELECT COUNT(*) FROM resources WHERE phone = " . $db->quote($phoneVal) . "");
                        $dup = $stmt->fetchColumn();
                        if (intval($dup) > 0) { $failCount++; $failures[] = ['row' => $rowNum, 'reason' => "手机号 {$phoneVal} 已存在"]; continue; }
                    }
                    $stmt->bindValue(':n', $item['name']??'');
                    $stmt->bindValue(':p', $phoneVal);
                    $stmt->bindValue(':s', $item['source']??'');
                    $stmt->bindValue(':i', $item['intention_level']??'');
                    $stmt->bindValue(':g', $item['gender']??'');
                    $stmt->bindValue(':bd', $item['birth_date']??'');
                    $stmt->bindValue(':fs', $item['follow_status']??'');
                    $stmt->bindValue(':st', '待跟进');
                    $stmt->bindValue(':a', $item['assigned_to']??'');
                    $stmt->bindValue(':pt', $poolType);
                    $stmt->bindValue(':c', $n); $stmt->bindValue(':u', $n);
                    $stmt->execute();
                    $count++;
                }
                json(['message' => "成功导入 {$count} 条资源" . ($failCount > 0 ? "，跳过 {$failCount} 条" : ''), 'count' => $count, 'skip_count' => $failCount, 'failures' => $failures]);
            }

            // === Excel 文件上传模式 ===
            $file = $_FILES['file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            // 验证文件类型
            if (!in_array($ext, ['xlsx', 'xls'])) {
                json(['error' => '仅支持 .xlsx 或 .xls 格式的 Excel 文件', 'success_count' => 0, 'fail_count' => 0, 'failures' => []]);
            }

            // 验证文件大小（最大 10MB）
            if ($file['size'] > 10 * 1024 * 1024) {
                json(['error' => '文件大小不能超过 10MB', 'success_count' => 0, 'fail_count' => 0, 'failures' => []]);
            }

            // 验证 PHP zip 扩展是否可用
            if (!class_exists('ZipArchive')) {
                json(['error' => '服务器缺少 zip 扩展，无法处理 Excel 文件。请联系管理员启用 PHP zip 扩展。', 'success_count' => 0, 'fail_count' => 0, 'failures' => []]);
            }

            // 保存临时文件
            $tmpPath = $file['tmp_name'];
            $importPath = __DIR__ . '/temp_import_' . time() . '.' . $ext;
            move_uploaded_file($tmpPath, $importPath);

            try {
                // 解析 Excel
                $rows = parseXlsx($importPath);
            } catch (Exception $e) {
                @unlink($importPath);
                json(['error' => '解析 Excel 文件失败: ' . $e->getMessage(), 'success_count' => 0, 'fail_count' => 0, 'failures' => []]);
            }

            @unlink($importPath);

            if (empty($rows)) {
                json(['error' => 'Excel 文件为空', 'success_count' => 0, 'fail_count' => 0, 'failures' => []]);
            }

            // 第一行作为表头
            $header = array_map('trim', $rows[0]);
            // 表头映射：根据中文表头找到对应的字段索引
            $headerMap = [
                '姓名' => 'name',
                '电话' => 'phone',
                '来源' => 'source',
                '意向等级' => 'intention_level',
                '归属人' => 'assigned_to',
                '性别' => 'gender',
                '出生日期' => 'birth_date',
                '跟进状态' => 'follow_status',
            ];

            $colMap = []; // 列索引 => 字段名
            foreach ($header as $idx => $colName) {
                if (isset($headerMap[$colName])) {
                    $colMap[$idx] = $headerMap[$colName];
                }
            }

            // 预加载渠道和意向等级列表
            $chNames = [];
            $chResult = $db->query("SELECT name FROM channels");
            while ($r = $chResult->fetch(PDO::FETCH_ASSOC)) $chNames[$r['name']] = true;

            $lvNames = [];
            $lvResult = $db->query("SELECT name FROM intention_levels");
            while ($r = $lvResult->fetch(PDO::FETCH_ASSOC)) $lvNames[$r['name']] = true;

            $poolType = $_POST['pool_type'] ?? '我的资源';
            $n = now();
            $successCount = 0;
            $failures = [];

            $stmt = $db->prepare("INSERT INTO resources (name,phone,source,intention_level,gender,birth_date,follow_status,status,assigned_to,pool_type,created_at,updated_at) VALUES (:n,:p,:s,:i,:g,:bd,:fs,:st,:a,:pt,:c,:u)");

            for ($rowIdx = 1; $rowIdx < count($rows); $rowIdx++) {
                $row = $rows[$rowIdx];
                $item = ['name' => '', 'phone' => '', 'source' => '',
                         'intention_level' => '', 'assigned_to' => '', 'gender' => '', 'birth_date' => '', 'follow_status' => ''];

                foreach ($colMap as $colIdx => $field) {
                    if (isset($row[$colIdx])) {
                        $item[$field] = trim($row[$colIdx]);
                    }
                }

                // 跳过空行（姓名和手机号均为必填）
                if ($item['name'] === '' || $item['phone'] === '') {
                    $missing = [];
                    if ($item['name'] === '') $missing[] = '姓名';
                    if ($item['phone'] === '') $missing[] = '手机号';
                    $failures[] = ['row' => $rowIdx + 1, 'reason' => '缺少必填字段：' . implode('、', $missing)];
                    continue;
                }

                // 验证渠道
                if ($item['source'] !== '' && !isset($chNames[$item['source']])) {
                    $failures[] = ['row' => $rowIdx + 1, 'reason' => "渠道\"{$item['source']}\"不在已配置渠道中"];
                    continue;
                }

                // 验证意向等级
                if ($item['intention_level'] !== '' && !isset($lvNames[$item['intention_level']])) {
                    $failures[] = ['row' => $rowIdx + 1, 'reason' => "意向等级\"{$item['intention_level']}\"不在已配置等级中"];
                    continue;
                }

                // 手机号唯一性校验（空手机号不校验）
                if ($item['phone'] !== '') {
                    $stmt = $db->query("SELECT COUNT(*) FROM resources WHERE phone = " . $db->quote($item['phone']) . "");
                    $dup = $stmt->fetchColumn();
                    if (intval($dup) > 0) {
                        $failures[] = ['row' => $rowIdx + 1, 'reason' => "手机号 {$item['phone']} 已存在"];
                        continue;
                    }
                }

                $stmt->bindValue(':n', $item['name']);
                $stmt->bindValue(':p', $item['phone']);
                $stmt->bindValue(':s', $item['source']);
                $stmt->bindValue(':i', $item['intention_level']);
                $stmt->bindValue(':g', $item['gender']);
                $stmt->bindValue(':bd', $item['birth_date']);
                $stmt->bindValue(':fs', $item['follow_status']);
                $stmt->bindValue(':st', '待跟进');
                $stmt->bindValue(':a', $item['assigned_to']);
                $stmt->bindValue(':pt', $poolType);
                $stmt->bindValue(':c', $n);
                $stmt->bindValue(':u', $n);
                $stmt->execute();
                $successCount++;
            }

            $failCount = count($failures);
            $result = [
                'message' => "导入完成：成功 {$successCount} 条" . ($failCount > 0 ? "，失败 {$failCount} 条" : ''),
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'failures' => $failures,
            ];
            json($result);

        case 'download_template':
            $templatePath = __DIR__ . '/static/导入模板.xlsx';
            // 如果模板不存在，动态生成
            if (!file_exists($templatePath)) {
                $headers = ['姓名', '电话', '来源', '意向等级', '归属人', '性别', '出生日期', '跟进状态'];
                if (!generateTemplateXlsx($templatePath, $headers)) {
                    json(['error' => '生成模板失败']);
                }
            }
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="导入模板.xlsx"');
            header('Content-Length: ' . filesize($templatePath));
            readfile($templatePath);
            exit;

        case 'batch_assign':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $ids = $input['ids'] ?? [];
            $assignedTo = $input['assigned_to'] ?? '';
            // 支持多人分配：assigned_to 可以是字符串（单人）或数组（多人平均分配）
            if (is_array($assignedTo)) {
                $assignees = array_values(array_filter($assignedTo, function($v) { return trim($v) !== ''; }));
                if (empty($assignees)) json(['error' => '请选择至少一个归属人']);
                $totalIds = count($ids);
                $totalAssignees = count($assignees);
                // 平均分配：每人分配 base 条，余数从第一个开始每人多 1 条
                $base = intdiv($totalIds, $totalAssignees);
                $remainder = $totalIds % $totalAssignees;
                $n = now();
                $stmt = $db->prepare("UPDATE resources SET assigned_to=:a, updated_at=:u WHERE id=:id");
                $assignIdx = 0;
                $assignedCounts = array_fill(0, $totalAssignees, 0);
                foreach ($ids as $i => $rid) {
                    // 先分配当前资源给当前人
                    $targetIdx = $assignIdx;
                    $stmt->bindValue(':a', $assignees[$targetIdx]);
                    $stmt->bindValue(':u', $n);
                    $stmt->bindValue(':id', intval($rid), PDO::PARAM_INT);
                    $stmt->execute();
                    // 分配后再递增计数并判断是否满额，满额则下一轮切换到下一个人
                    $assignedCounts[$targetIdx]++;
                    $quota = $base + ($targetIdx < $remainder ? 1 : 0);
                    if ($assignedCounts[$targetIdx] >= $quota) {
                        $assignIdx++;
                    }
                }
                // 生成分配明细消息
                $detailParts = [];
                foreach ($assignees as $ai => $aname) {
                    $detailParts[] = $aname . ' ' . ($assignedCounts[$ai] ?? 0) . ' 条';
                }
                json(['message' => '成功分配 ' . $totalIds . ' 条资源（' . implode('、', $detailParts) . '）']);
            } else {
                // 单人分配（向后兼容）
                $n = now();
                $stmt = $db->prepare("UPDATE resources SET assigned_to=:a, updated_at=:u WHERE id=:id");
                foreach ($ids as $rid) {
                    $stmt->bindValue(':a', $assignedTo);
                    $stmt->bindValue(':u', $n);
                    $stmt->bindValue(':id', intval($rid), PDO::PARAM_INT);
                    $stmt->execute();
                }
                json(['message' => '成功分配 ' . count($ids) . ' 条资源给 ' . $assignedTo]);
            }

        case 'batch_pool':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $ids = $input['ids'] ?? [];
            $poolType = $input['pool_type'] ?? '资源公海';
            $n = now();
            $stmt = $db->prepare("UPDATE resources SET pool_type=:pt, updated_at=:u WHERE id=:id");
            foreach ($ids as $rid) {
                $stmt->bindValue(':pt', $poolType);
                $stmt->bindValue(':u', $n);
                $stmt->bindValue(':id', intval($rid), PDO::PARAM_INT);
                $stmt->execute();
            }
            json(['message' => '成功更新 ' . count($ids) . ' 条']);

        case 'get_appointments':
            $page = max(1, intval($_GET['page'] ?? 1));
            $pageSize = max(1, min(100, intval($_GET['page_size'] ?? 20)));
            $keyword = $_GET['keyword'] ?? '';
            $status = $_GET['status'] ?? '';

            $where = []; $params = [];
            if ($keyword) {
                $where[] = "(apt.student_name LIKE ? OR apt.resource_name LIKE ? OR apt.phone LIKE ?)";
                $params = ["%$keyword%", "%$keyword%", "%$keyword%"];
            }
            $innerWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $offset = ($page - 1) * $pageSize;
            
            // 外层过滤 effective_status（如果指定）
            $outerWhere = '';
            if ($status) {
                $outerWhere = "WHERE effective_status = " . $db->quote($status);
            }

            $query = "SELECT * FROM (
                SELECT apt.*,
                    cl.name AS class_name,
                    s.teacher AS session_teacher,
                    co.subject AS course_subject,
                    r.converted AS resource_converted,
                    r.source AS resource_channel,
                    r.assigned_to AS resource_assigned_to,
                    ca.status AS attendance_status,
                    CASE 
                        WHEN apt.status='已取消' THEN '已取消'
                        WHEN ca.status='出勤' THEN '已试听'
                        WHEN ca.status='缺勤' THEN '缺勤'
                        ELSE '已预约待试听'
                    END AS effective_status
                    FROM appointments apt
                    LEFT JOIN classes cl ON cl.id=apt.class_id
                    LEFT JOIN schedules s ON s.id=apt.schedule_id
                    LEFT JOIN courses co ON co.id=apt.course_id
                    LEFT JOIN resources r ON r.id=apt.resource_id
                    LEFT JOIN class_attendance ca ON ca.class_id=apt.class_id AND ca.schedule_id=apt.schedule_id AND ca.session_date COLLATE utf8mb4_unicode_ci=apt.appointment_time AND ca.is_temporary=1
                    $innerWhere
            ) sub $outerWhere ORDER BY sub.appointment_time DESC LIMIT $pageSize OFFSET $offset";
            $countQuery = "SELECT COUNT(*) FROM (" .
                "SELECT apt.id, CASE WHEN apt.status='已取消' THEN '已取消' WHEN ca.status='出勤' THEN '已试听' WHEN ca.status='缺勤' THEN '缺勤' ELSE '已预约待试听' END AS effective_status FROM appointments apt LEFT JOIN class_attendance ca ON ca.class_id=apt.class_id AND ca.schedule_id=apt.schedule_id AND ca.session_date COLLATE utf8mb4_unicode_ci=apt.appointment_time AND ca.is_temporary=1 $innerWhere" .
                ") cnt $outerWhere";
            $stmt = $db->query($countQuery);
            $total = $stmt->fetchColumn();
            $total = $total ? intval($total) : 0;
            // 状态分布统计（同筛选条件）
            $statsQuery = "SELECT effective_status, COUNT(*) AS cnt FROM (" .
                "SELECT CASE WHEN apt.status='已取消' THEN '已取消' WHEN ca.status='出勤' THEN '已试听' WHEN ca.status='缺勤' THEN '缺勤' ELSE '已预约待试听' END AS effective_status FROM appointments apt LEFT JOIN class_attendance ca ON ca.class_id=apt.class_id AND ca.schedule_id=apt.schedule_id AND ca.session_date COLLATE utf8mb4_unicode_ci=apt.appointment_time AND ca.is_temporary=1 $innerWhere" .
                ") statsub $outerWhere GROUP BY effective_status";
            $statsStmt = $db->query($statsQuery);
            $stats = ['已预约待试听' => 0, '已试听' => 0, '缺勤' => 0];
            while ($sr = $statsStmt->fetch(PDO::FETCH_ASSOC)) {
                $stats[$sr['effective_status']] = intval($sr['cnt']);
            }
            $rows = [];
            if ($params) {
                $stmt = $db->prepare($query);
                foreach ($params as $i => $v) $stmt->bindValue($i+1, $v, PDO::PARAM_STR);
$stmt->execute();
            } else {
                $stmt = $db->query($query);
            }
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $subjParts = explode(' > ', $r['course_subject'] ?? '');
                $r['subject_level1'] = $subjParts[0] ?? '';
                $r['subject_level2'] = $subjParts[1] ?? '';
                $r['status'] = $r['effective_status'] ?? $r['status'];
                $rows[] = $r;
            }
            json(['total' => $total, 'page' => $page, 'page_size' => $pageSize, 'data' => $rows, 'stats' => $stats]);

        // === 预约试听级联查询 ===
        case 'get_trial_campuses':
            $campusRows = $db->query("SELECT DISTINCT o.name, o.id FROM organizations o WHERE o.type='校区' ORDER BY o.name")->fetchAll(PDO::FETCH_ASSOC);
            json(['data' => array_values($campusRows)]);
            break;
        case 'get_trial_subjects':
            $campusId = intval($_GET['campus'] ?? 0);
            if ($campusId) {
                $stmt = $db->prepare("SELECT DISTINCT s.id, s.name FROM subjects s WHERE s.parent_id=0 AND EXISTS (SELECT 1 FROM courses c WHERE FIND_IN_SET(?, c.campus_permission)) ORDER BY s.name");
                $stmt->execute([$campusId]);
                $subjs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $subjs = $db->query("SELECT DISTINCT id, name FROM subjects WHERE parent_id=0 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            }
            json(['data' => $subjs]);
            break;
        case 'get_trial_courses':
            $campusId = intval($_GET['campus'] ?? 0);
            $subjectName = trim($_GET['subject'] ?? '');
            if ($campusId || $subjectName) {
                $sql = "SELECT id, name FROM courses WHERE 1=1";
                $params = [];
                if ($campusId) { $sql .= " AND FIND_IN_SET(?, campus_permission)"; $params[] = $campusId; }
                if ($subjectName) { $sql .= " AND subject LIKE ?"; $params[] = $subjectName . ' %'; }
                $sql .= " ORDER BY name";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $courses = $db->query("SELECT id, name FROM courses ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            }
            json(['data' => $courses]);
            break;
        case 'get_trial_classes':
            $courseId = intval($_GET['course_id'] ?? 0);
            $campusId = intval($_GET['campus'] ?? 0);
            $classes = [];
            if ($courseId && $campusId) {
                // Look up campus name from ID
                $campusName = '';
                $cnStmt = $db->prepare("SELECT name FROM organizations WHERE id=? AND type='校区'");
                $cnStmt->execute([$campusId]);
                $cnRow = $cnStmt->fetch(PDO::FETCH_ASSOC);
                $campusName = $cnRow ? $cnRow['name'] : '';
                if ($campusName) {
                    $stmt = $db->prepare("SELECT id, name FROM classes WHERE course_id=? AND can_trial=1 AND campus=? ORDER BY name");
                    $stmt->execute([$courseId, $campusName]);
                    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            json(['data' => $classes]);
            break;
        case 'get_trial_sessions':
            $classId = intval($_GET['class_id'] ?? 0);
            $sessions = [];
            if ($classId) {
                $stmt = $db->prepare("SELECT id, start_date, end_date, weekdays, time_slots, teacher, classroom FROM schedules WHERE class_id=? ORDER BY start_date");
                $stmt->execute([$classId]);
                $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($sessions as &$s) {
                    $s['time_slots'] = json_decode($s['time_slots'] ?? '{}', true) ?: [];
                    $s['weekdays'] = array_map('intval', array_filter(explode(',', $s['weekdays'] ?? '')));
                }
            }
            json(['data' => $sessions]);
            break;

        case 'search_trial_sessions':
            $campusId = intval($_GET['campus'] ?? 0);
            $subject = trim($_GET['subject'] ?? '');
            $courseId = intval($_GET['course'] ?? 0);
            $teacher = trim($_GET['teacher'] ?? '');
            $dateFilter = trim($_GET['date'] ?? '');
            // 查出所有 can_trial=1 的班级及其排课，展开为具体日期
            $sql = "SELECT c.id AS class_id, c.name AS class_name, c.campus, c.course_id, co.name AS course_name, co.subject,
                    s.id AS schedule_id, s.weekdays, s.time_slots, s.start_date, s.end_date, s.teacher, s.classroom
                    FROM classes c
                    LEFT JOIN courses co ON co.id=c.course_id
                    INNER JOIN schedules s ON s.class_id=c.id
                    WHERE c.can_trial=1";
            $params = [];
            if ($campusId) {
                $cn = $db->prepare("SELECT name FROM organizations WHERE id=? AND type='校区'");
                $cn->execute([$campusId]);
                $cnRow = $cn->fetch(PDO::FETCH_ASSOC);
                if ($cnRow) { $sql .= " AND c.campus=?"; $params[] = $cnRow['name']; }
            }
            if ($subject) { $sql .= " AND co.subject LIKE ?"; $params[] = $subject . ' %'; }
            if ($courseId) { $sql .= " AND c.course_id=?"; $params[] = $courseId; }
            if ($teacher) { $sql .= " AND s.teacher=?"; $params[] = $teacher; }
            $sql .= " ORDER BY c.campus, c.name, s.start_date";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // 展开为日期+时段
            $results = [];
            $days = ['','一','二','三','四','五','六','日'];
            $today = new DateTime();
            foreach ($rows as $r) {
                $weekdays = array_map('intval', array_filter(explode(',', $r['weekdays'] ?? '')));
                $ts = json_decode($r['time_slots'] ?? '{}', true) ?: [];
                $start = new DateTime($r['start_date']);
                $end = new DateTime($r['end_date']);
                foreach ($weekdays as $wd) {
                    $d = clone $today;
                    $currentWd = (int)$today->format('N');
                    $diff = $wd - $currentWd;
                    if ($diff < 0) $diff += 7;
                    $d->modify('+' . $diff . ' days');
                    for ($w = 0; $w < 8; $w++) {
                        $dt = clone $d; $dt->modify('+' . ($w*7) . ' days');
                        if ($dt >= $start && $dt <= $end) {
                            foreach ($ts as $slotKey => $slot) {
                                if ((int)$slotKey !== $wd) continue; // 只取匹配该星期的时段
                                $dateStr = $dt->format('Y-m-d');
                                if ($dateFilter && $dateStr !== $dateFilter) continue;
                                $results[] = [
                                    'class_id' => $r['class_id'], 'class_name' => $r['class_name'],
                                    'campus' => $r['campus'], 'course_name' => $r['course_name'],
                                    'subject' => $r['subject'],
                                    'schedule_id' => $r['schedule_id'],
                                    'date' => $dateStr, 'day' => '周' . $days[$wd],
                                    'start' => $slot['start'], 'end' => $slot['end'],
                                    'teacher' => $r['teacher'], 'classroom' => $r['classroom'],
                                ];
                            }
                        }
                    }
                }
            }
            usort($results, function($a,$b) { return $a['date'] <=> $b['date'] ?: $a['start'] <=> $b['start']; });
            json(['data' => $results]);
            break;

        case 'book_trial':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $resourceId = intval($input['resource_id'] ?? 0);
            $courseId = intval($input['course_id'] ?? 0);
            $classId = intval($input['class_id'] ?? 0);
            $scheduleId = intval($input['schedule_id'] ?? 0);
            if (!$resourceId || !$classId || !$scheduleId) json(['error' => '请先选择校区并点击查询']);
            // 如果未选课程，从班级反查
            if (!$courseId) {
                $cInfo = $db->query("SELECT course_id FROM classes WHERE id=$classId")->fetch(PDO::FETCH_ASSOC);
                $courseId = $cInfo ? intval($cInfo['course_id']) : 0;
            }
            $campus = trim($input['campus'] ?? '');
            $subjectLevel1 = trim($input['subject_level1'] ?? '');
            $resourceName = trim($input['resource_name'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $trialDate = trim($input['trial_date'] ?? '');
            $n = now();
            $stmt = $db->prepare("INSERT INTO appointments (resource_id,resource_name,student_name,phone,course_type,appointment_time,status,notes,campus,subject_level1,course_id,class_id,schedule_id,created_at) VALUES (:ri,:rn,:sn,:p,:ct,:at,:st,:no,:cp,:sj,:ci,:cli,:si,:c)");
            $stmt->bindValue(':ri', $resourceId, PDO::PARAM_INT);
            $stmt->bindValue(':rn', $resourceName);
            $stmt->bindValue(':sn', $resourceName);
            $stmt->bindValue(':p', $phone);
            $stmt->bindValue(':ct', '试听');
            $stmt->bindValue(':at', $trialDate ?: $n);
            $stmt->bindValue(':st', '已预约待试听');
            $stmt->bindValue(':no', '');
            $stmt->bindValue(':cp', $campus);
            $stmt->bindValue(':sj', $subjectLevel1);
            $stmt->bindValue(':ci', $courseId, PDO::PARAM_INT);
            $stmt->bindValue(':cli', $classId, PDO::PARAM_INT);
            $stmt->bindValue(':si', $scheduleId, PDO::PARAM_INT);
            $stmt->bindValue(':c', $n);
            $stmt->execute();
            $aptId = $db->lastInsertId();
            // 资源作为临时试听学员加入考勤表
            $db->prepare("INSERT INTO class_attendance (class_id, schedule_id, session_date, student_id, student_name, status, is_temporary, deducted_lessons, created_at) VALUES (?,?,?,0,?,?,1,0,?)")->execute([
                $classId, $scheduleId, $trialDate ?: date('Y-m-d'),
                $resourceName . ($phone ? ' ' . $phone : ''),
                '出勤', $n
            ]);
            json(['id' => $aptId, 'message' => '预约成功，状态：已预约待试听']);

        case 'cancel_trial':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $aptId = intval($input['id'] ?? 0);
            $classId = intval($input['class_id'] ?? 0);
            $scheduleId = intval($input['schedule_id'] ?? 0);
            $trialDate = trim($input['trial_date'] ?? '');
            if (!$aptId) json(['error' => '参数无效']);
            $db->exec('START TRANSACTION');
            try {
                $db->exec("UPDATE appointments SET status='已取消' WHERE id=$aptId");
                if ($classId && $scheduleId && $trialDate) {
                    $stmt = $db->prepare("DELETE FROM class_attendance WHERE class_id=? AND schedule_id=? AND session_date=? AND is_temporary=1");
                    $stmt->execute([$classId, $scheduleId, $trialDate]);
                }
                $db->exec('COMMIT');
            } catch (Exception $e) {
                $db->exec('ROLLBACK');
                json(['error' => '取消失败: ' . $e->getMessage()]);
            }
            json(['message' => '已取消试听']);

        case 'add_appointment':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $n = now();
            $stmt = $db->prepare("INSERT INTO appointments (resource_id,resource_name,student_name,phone,course_type,appointment_time,status,notes,created_at) VALUES (:ri,:rn,:sn,:p,:ct,:at,:st,:no,:c)");
            $stmt->bindValue(':ri', intval($input['resource_id']??0), PDO::PARAM_INT);
            $stmt->bindValue(':rn', $input['resource_name']??'');
            $stmt->bindValue(':sn', $input['student_name']??'');
            $stmt->bindValue(':p', $input['phone']??'');
            $stmt->bindValue(':ct', $input['course_type']??'');
            $stmt->bindValue(':at', $input['appointment_time']??'');
            $stmt->bindValue(':st', $input['status']??'已预约');
            $stmt->bindValue(':no', $input['notes']??'');
            $stmt->bindValue(':c', $n);
            $stmt->execute();
            json(['id' => $db->lastInsertId(), 'message' => '预约成功']);

        case 'update_appointment':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $aid = intval($input['id'] ?? 0);
            $stmt = $db->prepare("UPDATE appointments SET student_name=:sn,phone=:p,course_type=:ct,appointment_time=:at,status=:st,notes=:no WHERE id=:id");
            $stmt->bindValue(':sn', $input['student_name']??'');
            $stmt->bindValue(':p', $input['phone']??'');
            $stmt->bindValue(':ct', $input['course_type']??'');
            $stmt->bindValue(':at', $input['appointment_time']??'');
            $stmt->bindValue(':st', $input['status']??'');
            $stmt->bindValue(':no', $input['notes']??'');
            $stmt->bindValue(':id', $aid, PDO::PARAM_INT);
            $stmt->execute();
            json(['message' => '更新成功']);

        case 'delete_appointment':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $aid = intval($input['id'] ?? 0);
            $db->exec("DELETE FROM appointments WHERE id=$aid");
            json(['message' => '删除成功']);

        case 'get_communications':
            $rid = intval($_GET['resource_id'] ?? 0);
            $stmt = $db->query("SELECT * FROM communication_records WHERE resource_id=$rid ORDER BY created_at DESC");
            $rows = [];
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $r;
            json($rows);

        case 'add_communication':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $rid = intval($input['resource_id'] ?? 0);
            $n = now();
            $db->exec("UPDATE resources SET follow_status='{$input['new_status']}', updated_at='$n' WHERE id=$rid");
            $stmt = $db->prepare("INSERT INTO communication_records (resource_id,resource_name,content,comm_type,created_at) VALUES (:ri,:rn,:co,:ct,:c)");
            $stmt->bindValue(':ri', $rid, PDO::PARAM_INT);
            $stmt->bindValue(':rn', $input['resource_name']??'');
            $stmt->bindValue(':co', $input['content']??'');
            $stmt->bindValue(':ct', $input['comm_type']??'电话');
            $stmt->bindValue(':c', $n);
            $stmt->execute();
            json(['id' => $db->lastInsertId(), 'message' => '添加成功']);

        case 'get_stats':
            $stmt = $db->query("SELECT COUNT(*) FROM resources WHERE pool_type='我的资源'") ?: 0;
            $my = $stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) FROM resources WHERE pool_type='资源公海'") ?: 0;
            $sea = $stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) FROM appointments") ?: 0;
            $apt = $stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) FROM employees") ?: 0;
            $emp = $stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) FROM courses") ?: 0;
            $courses = $stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) FROM subjects") ?: 0;
            $subjects = $stmt->fetchColumn();
            json(['my_resources' => intval($my), 'sea_resources' => intval($sea), 'appointments' => intval($apt), 'employees' => intval($emp), 'courses' => intval($courses), 'subjects' => intval($subjects)]);

        case 'list_channels':
            $stmt = $db->query("SELECT * FROM channels ORDER BY created_at DESC");
            $rows = [];
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $r;
            json(['data' => $rows]);

        case 'add_channel':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $name = trim($input['name'] ?? '');
            if (!$name) json(['error' => '渠道名称不能为空']);
            $stmt = $db->query("SELECT COUNT(*) FROM channels WHERE name = " . $db->quote($name) . "");
            $existing = $stmt->fetchColumn();
            if (intval($existing) > 0) json(['error' => '渠道名称已存在']);
            $n = now();
            $db->exec("INSERT INTO channels (name, created_at) VALUES (" . $db->quote($name) . ", '$n')");
            json(['id' => $db->lastInsertId(), 'message' => '渠道添加成功']);

        case 'update_channel':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $cid = intval($input['id'] ?? 0);
            if (!$cid) json(['error' => '渠道ID无效']);
            $newName = trim($input['name'] ?? '');
            if (!$newName) json(['error' => '渠道名称不能为空']);
            // 检查新名称是否与其他渠道重复（排除自身）
            $stmt = $db->query("SELECT COUNT(*) FROM channels WHERE name = " . $db->quote($newName) . " AND id != $cid");
            $dup = $stmt->fetchColumn();
            if (intval($dup) > 0) json(['error' => '渠道名称已存在']);
            // 事务：先取旧名称，再更新 channels，再同步 resources
            $stmt = $db->query("SELECT name FROM channels WHERE id = $cid");
            $oldName = $stmt->fetchColumn();
            if (!$oldName) json(['error' => '渠道不存在']);
            $db->exec("BEGIN");
            $db->exec("UPDATE channels SET name = " . $db->quote($newName) . " WHERE id = $cid");
            $db->exec("UPDATE resources SET source = " . $db->quote($newName) . " WHERE source = " . $db->quote($oldName) . "");
            $updatedCount = $db->changes();
            $db->exec("COMMIT");
            json(['message' => '渠道修改成功', 'updated_resources' => $updatedCount]);

        case 'delete_channel':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $cid = intval($input['id'] ?? 0);
            $db->exec("DELETE FROM channels WHERE id=$cid");
            json(['message' => '渠道删除成功']);

        case 'list_campuses':
            $rows = $db->query("SELECT id, name FROM organizations WHERE type='校区' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            json(['data' => $rows]);

        case 'list_class_periods':
            $campusFilter = trim($_GET['campus'] ?? '');
            $sql = "SELECT * FROM class_periods";
            $params = [];
            if ($campusFilter) { $sql .= " WHERE campus = " . $db->quote($campusFilter); }
            $sql .= " ORDER BY sort_order ASC, id ASC";
            $stmt = $db->query($sql);
            $rows = [];
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $r;
            json(['data' => $rows]);

        case 'add_class_period':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $name = trim($input['name'] ?? '');
            $start_time = trim($input['start_time'] ?? '');
            $end_time = trim($input['end_time'] ?? '');
            $sort_order = intval($input['sort_order'] ?? 0);
            $campus = trim($input['campus'] ?? '');
            if (!$name) json(['error' => '时段名称不能为空']);
            if (!$campus) json(['error' => '请选择校区']);
            if (!$start_time || !$end_time) json(['error' => '开始时间和结束时间不能为空']);
            if ($start_time >= $end_time) json(['error' => '开始时间必须早于结束时间']);
            $stmt = $db->query("SELECT COUNT(*) FROM class_periods WHERE name = " . $db->quote($name) . " AND campus = " . $db->quote($campus));
            $existing = $stmt->fetchColumn();
            if (intval($existing) > 0) json(['error' => '该校区已存在同名时段']);
            $n = now();
            $db->exec("INSERT INTO class_periods (name, start_time, end_time, sort_order, campus, created_at) VALUES (" . $db->quote($name) . ", " . $db->quote($start_time) . ", " . $db->quote($end_time) . ", $sort_order, " . $db->quote($campus) . ", '$n')");
            json(['id' => $db->lastInsertId(), 'message' => '时段添加成功']);

        case 'update_class_period':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $pid = intval($input['id'] ?? 0);
            if (!$pid) json(['error' => '时段ID无效']);
            $stmt = $db->query("SELECT * FROM class_periods WHERE id = $pid");
            $cur = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cur) json(['error' => '时段不存在']);
            $name = array_key_exists('name', $input) ? trim($input['name']) : $cur['name'];
            $start_time = array_key_exists('start_time', $input) ? trim($input['start_time']) : $cur['start_time'];
            $end_time = array_key_exists('end_time', $input) ? trim($input['end_time']) : $cur['end_time'];
            $sort_order = array_key_exists('sort_order', $input) ? intval($input['sort_order']) : $cur['sort_order'];
            $campus = array_key_exists('campus', $input) ? trim($input['campus']) : $cur['campus'];
            if (!$name) json(['error' => '时段名称不能为空']);
            if (!$campus) json(['error' => '校区不能为空']);
            if (!$start_time || !$end_time) json(['error' => '开始时间和结束时间不能为空']);
            if ($start_time >= $end_time) json(['error' => '开始时间必须早于结束时间']);
            $stmt = $db->query("SELECT COUNT(*) FROM class_periods WHERE name = " . $db->quote($name) . " AND campus = " . $db->quote($campus) . " AND id != $pid");
            $dup = $stmt->fetchColumn();
            if (intval($dup) > 0) json(['error' => '该校区已存在同名时段']);
            $db->exec("UPDATE class_periods SET name = " . $db->quote($name) . ", start_time = " . $db->quote($start_time) . ", end_time = " . $db->quote($end_time) . ", sort_order = $sort_order, campus = " . $db->quote($campus) . " WHERE id = $pid");
            json(['message' => '时段修改成功']);

        case 'delete_class_period':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $pid = intval($input['id'] ?? 0);
            if (!$pid) json(['error' => '时段ID无效']);
            $db->exec("DELETE FROM class_periods WHERE id=$pid");
            json(['message' => '时段删除成功']);

        case 'list_intention_levels':
            $stmt = $db->query("SELECT * FROM intention_levels ORDER BY sort_order ASC, id ASC");
            $rows = [];
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $r;
            json($rows);

        case 'add_intention_level':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $name = trim($input['name'] ?? '');
            if (!$name) json(['error' => '意向等级名称不能为空']);
            $stmt = $db->query("SELECT COUNT(*) FROM intention_levels WHERE name = " . $db->quote($name) . "");
            $existing = $stmt->fetchColumn();
            if (intval($existing) > 0) json(['error' => '意向等级名称已存在']);
            $sortOrder = intval($input['sort_order'] ?? 0);
            $n = now();
            $db->exec("INSERT INTO intention_levels (name, sort_order, created_at) VALUES (" . $db->quote($name) . ", $sortOrder, '$n')");
            json(['id' => $db->lastInsertId(), 'message' => '意向等级添加成功']);

        case 'update_intention_level':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $iid = intval($input['id'] ?? 0);
            if (!$iid) json(['error' => '意向等级ID无效']);
            $newName = trim($input['name'] ?? '');
            if (!$newName) json(['error' => '意向等级名称不能为空']);
            $sortOrder = intval($input['sort_order'] ?? 0);
            $stmt = $db->query("SELECT COUNT(*) FROM intention_levels WHERE name = " . $db->quote($newName) . " AND id != $iid");
            $dup = $stmt->fetchColumn();
            if (intval($dup) > 0) json(['error' => '意向等级名称已存在']);
            $stmt = $db->query("SELECT name FROM intention_levels WHERE id = $iid");
            $oldName = $stmt->fetchColumn();
            if (!$oldName) json(['error' => '意向等级不存在']);
            $db->exec("BEGIN");
            $db->exec("UPDATE intention_levels SET name = " . $db->quote($newName) . ", sort_order = $sortOrder WHERE id = $iid");
            $db->exec("UPDATE resources SET intention_level = " . $db->quote($newName) . " WHERE intention_level = " . $db->quote($oldName) . "");
            $updatedCount = $db->changes();
            $db->exec("COMMIT");
            json(['message' => '意向等级修改成功', 'updated_resources' => $updatedCount]);

        case 'delete_intention_level':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $iid = intval($input['id'] ?? 0);
            $db->exec("DELETE FROM intention_levels WHERE id=$iid");
            json(['message' => '意向等级删除成功']);

        case 'export_resources':
            $poolType = $_GET['pool_type'] ?? '我的资源';
            $keyword = $_GET['keyword'] ?? '';
            $followStatus = $_GET['follow_status'] ?? '';

            $where = ["pool_type = :pt"];
            $params = [':pt' => $poolType];
            if ($keyword) {
                $where[] = "(name LIKE :kw1 OR phone LIKE :kw2 OR source LIKE :kw3)";
                $params[':kw1'] = "%$keyword%"; $params[':kw2'] = "%$keyword%"; $params[':kw3'] = "%$keyword%"; $params[':kw3'] = "%$keyword%";
            }
            if ($followStatus) { $where[] = "follow_status = :fs"; $params[':fs'] = $followStatus; }
            $whereStr = implode(' AND ', $where);

            $stmt = $db->prepare("SELECT r.name, r.phone, r.source, r.intention_level, r.gender, r.birth_date, r.follow_status, r.assigned_to, e.department AS assigned_dept, r.created_at, r.updated_at FROM resources r LEFT JOIN employees e ON r.assigned_to = e.name WHERE $whereStr ORDER BY updated_at DESC");
            foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
$stmt->execute();

            $filename = '资源导出_' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');
            fprintf($output, "\xEF\xBB\xBF");
            fputcsv($output, ['姓名', '电话', '来源渠道', '意向等级', '性别', '出生日期', '跟进状态', '归属人', '归属部门', '创建时间', '更新时间']);
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $r['name'], $r['phone'], $r['source'],
                    $r['intention_level'], $r['gender'], $r['birth_date'],
                    $r['follow_status'] ?? '',
                    $r['assigned_to'], $r['assigned_dept'] ?? '',
                    $r['created_at'], $r['updated_at']
                ]);
            }
            fclose($output);
            exit;

        case 'list_basic_types':
            $category = $_GET['category'] ?? '';
            if (!$category) json(['error' => 'category参数不能为空']);
            $stmt = $db->query("SELECT * FROM basic_types WHERE category = " . $db->quote($category) . " ORDER BY sort_order ASC, id ASC");
            $rows = [];
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $r;
            json($rows);

        case 'add_basic_type':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $category = trim($input['category'] ?? '');
            $name = trim($input['name'] ?? '');
            $sortOrder = intval($input['sort_order'] ?? 0);
            if (!$name) json(['error' => '名称不能为空']);
            if (!$category) json(['error' => 'category不能为空']);
            $stmt = $db->query("SELECT COUNT(*) FROM basic_types WHERE category = " . $db->quote($category) . " AND name = " . $db->quote($name) . "");
            $existing = $stmt->fetchColumn();
            if (intval($existing) > 0) json(['error' => '该类别下已存在同名类型']);
            $n = now();
            $db->exec("INSERT INTO basic_types (category, name, sort_order, created_at) VALUES (" . $db->quote($category) . ", " . $db->quote($name) . ", $sortOrder, '$n')");
            json(['id' => $db->lastInsertId(), 'message' => '添加成功']);

        case 'update_basic_type':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $bid = intval($input['id'] ?? 0);
            if (!$bid) json(['error' => 'ID无效']);
            $newName = trim($input['name'] ?? '');
            $sortOrder = $input['sort_order'] ?? null;
            // 获取原记录
            $old = $db->query("SELECT * FROM basic_types WHERE id = $bid")->fetch(PDO::FETCH_ASSOC);
            if (!$old) json(['error' => '记录不存在']);
            $finalName = $newName !== '' ? $newName : $old['name'];
            $finalSort = $sortOrder !== null ? intval($sortOrder) : intval($old['sort_order']);
            // 检查重名
            if ($newName !== '' && $newName !== $old['name']) {
                $stmt = $db->query("SELECT COUNT(*) FROM basic_types WHERE category = " . $db->quote($old['category']) . " AND name = " . $db->quote($newName) . " AND id != $bid");
                $dup = $stmt->fetchColumn();
                if (intval($dup) > 0) json(['error' => '该类别下已存在同名类型']);
            }
            $db->exec("BEGIN");
            $db->exec("UPDATE basic_types SET name = " . $db->quote($finalName) . ", sort_order = $finalSort WHERE id = $bid");
            // 同步关联数据
            if ($newName !== '' && $newName !== $old['name']) {
                if ($old['category'] === 'course_type') {
                    $db->exec("UPDATE appointments SET course_type = " . $db->quote($newName) . " WHERE course_type = " . $db->quote($old['name']) . "");
                } elseif ($old['category'] === 'comm_type') {
                    $db->exec("UPDATE communication_records SET comm_type = " . $db->quote($newName) . " WHERE comm_type = " . $db->quote($old['name']) . "");
                }
            }
            $db->exec("COMMIT");
            json(['message' => '更新成功']);

        case 'delete_basic_type':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $bid = intval($input['id'] ?? 0);
            $db->exec("DELETE FROM basic_types WHERE id=$bid");
            json(['message' => '删除成功']);

        // ==================== 员工管理 ====================
        case 'get_employees':
            $page = max(1, intval($_GET['page'] ?? 1));
            $pageSize = max(1, min(100, intval($_GET['page_size'] ?? 20)));
            $keyword = $_GET['keyword'] ?? '';
            $department = $_GET['department'] ?? '';
            $status = $_GET['status'] ?? '';

            $where = [];
            $params = [];
            if ($keyword) {
                $where[] = "(name LIKE :kw1 OR phone LIKE :kw2 OR department LIKE :kw3 OR position LIKE :kw4)";
                $params[':kw1'] = "%$keyword%"; $params[':kw2'] = "%$keyword%"; $params[':kw3'] = "%$keyword%";
                $params[':kw3'] = "%$keyword%"; $params[':kw4'] = "%$keyword%";
            }
            if ($department) { $where[] = "department = :dept"; $params[':dept'] = $department; }
            if ($status) { $where[] = "status = :st"; $params[':st'] = $status; }
            $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $countStmt = $db->prepare("SELECT COUNT(*) FROM employees $whereStr");
            foreach ($params as $k => $v) $countStmt->bindValue($k, $v, PDO::PARAM_STR);
            $countStmt->execute(); $total = $countStmt->fetch(PDO::FETCH_NUM)[0];
            $total = $total ? intval($total) : 0;
            $offset = ($page - 1) * $pageSize;
            $stmt = $db->prepare("SELECT * FROM employees $whereStr ORDER BY updated_at DESC LIMIT :lim OFFSET :off");
            foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
            $stmt->bindValue(':lim', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $rows = [];
$stmt->execute();
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $r;
            json(['total' => $total, 'page' => $page, 'page_size' => $pageSize, 'data' => $rows]);

        case 'add_employee':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $name = trim($input['name'] ?? '');
            if (!$name) json(['error' => '姓名不能为空']);
            $phone = trim($input['phone'] ?? '');
            // 姓名和手机号唯一性校验（同时检查，两个都重复两个都提示）
            $dupErrors = [];
            $stmt = $db->query("SELECT COUNT(*) FROM employees WHERE name = " . $db->quote($name) . "");
            $dup = $stmt->fetchColumn();
            if (intval($dup) > 0) $dupErrors[] = '姓名已存在，请勿重复录入';
            if ($phone !== '') {
                $stmt = $db->query("SELECT COUNT(*) FROM employees WHERE phone = " . $db->quote($phone) . "");
                $dup = $stmt->fetchColumn();
                if (intval($dup) > 0) $dupErrors[] = '手机号已存在，请勿重复录入';
            }
            if (!empty($dupErrors)) json(['error' => implode('；', $dupErrors)]);
            // 校验 department 是否在 organizations 中存在（可留空）
            $dept = $input['department'] ?? '';
            if ($dept !== '') {
                $stmt = $db->query("SELECT COUNT(*) FROM organizations WHERE name = " . $db->quote($dept) . "");
                $deptExists = $stmt->fetchColumn();
                if (intval($deptExists) === 0) json(['error' => '部门不存在，请从组织管理中选择']);
            }
            $n = now();
            $stmt = $db->prepare("INSERT INTO employees (name,phone,department,position,entry_date,status,is_teacher,created_at,updated_at) VALUES (:n,:p,:d,:pos,:ed,:st,:it,:c,:u)");
            $stmt->bindValue(':n', $name);
            $stmt->bindValue(':p', $phone);
            $stmt->bindValue(':d', $dept);
            $stmt->bindValue(':pos', $input['position']??'');
            $stmt->bindValue(':ed', $input['entry_date']??'');
            $stmt->bindValue(':st', $input['status']??'在职');
            $stmt->bindValue(':it', $input['is_teacher']??'');
            $stmt->bindValue(':c', $n); $stmt->bindValue(':u', $n);
            $stmt->execute();
            json(['id' => $db->lastInsertId(), 'message' => '新增成功']);

        case 'update_employee':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $eid = intval($input['id'] ?? 0);
            // 姓名和手机号唯一性校验（排除自身，同时检查，两个都重复两个都提示）
            $dupErrors = [];
            if (isset($input['name']) && trim($input['name'] ?? '') !== '') {
                $name = trim($input['name']);
                $stmt = $db->query("SELECT COUNT(*) FROM employees WHERE name = " . $db->quote($name) . " AND id != $eid");
                $dup = $stmt->fetchColumn();
                if (intval($dup) > 0) $dupErrors[] = '姓名已存在，请勿重复录入';
            }
            if (isset($input['phone']) && trim($input['phone'] ?? '') !== '') {
                $phone = trim($input['phone']);
                $stmt = $db->query("SELECT COUNT(*) FROM employees WHERE phone = " . $db->quote($phone) . " AND id != $eid");
                $dup = $stmt->fetchColumn();
                if (intval($dup) > 0) $dupErrors[] = '手机号已存在，请勿重复录入';
            }
            if (!empty($dupErrors)) json(['error' => implode('；', $dupErrors)]);
            // 校验 department 是否在 organizations 中存在（可留空）
            if (isset($input['department']) && ($input['department'] ?? '') !== '') {
                $dept = $input['department'];
                $stmt = $db->query("SELECT COUNT(*) FROM organizations WHERE name = " . $db->quote($dept) . "");
                $deptExists = $stmt->fetchColumn();
                if (intval($deptExists) === 0) json(['error' => '部门不存在，请从组织管理中选择']);
            }
            $allowedFields = ['name','phone','department','position','entry_date','status','is_teacher'];
            $sets = [];
            $params = [];
            foreach ($allowedFields as $f) {
                if (array_key_exists($f, $input)) {
                    $sets[] = "$f = :$f";
                    $params[":$f"] = $input[$f];
                }
            }
            if (empty($sets)) json(['error' => '没有要更新的字段']);
            $sets[] = "updated_at = :u";
            $params[':u'] = now();
            $params[':id'] = $eid;
            $sql = "UPDATE employees SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, $k === ':id' ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            json(['message' => '更新成功']);

        case 'delete_employee':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $eid = intval($input['id'] ?? 0);
            $db->exec("DELETE FROM employees WHERE id=$eid");
            json(['message' => '删除成功']);

        // ==================== 岗位管理 ====================
        case 'list_positions':
            $stmt = $db->query("SELECT * FROM positions ORDER BY sort_order ASC, id ASC");
            $rows = [];
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $r;
            json($rows);

        case 'add_position':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $name = trim($input['name'] ?? '');
            if (!$name) json(['error' => '岗位名称不能为空']);
            $stmt = $db->query("SELECT COUNT(*) FROM positions WHERE name = " . $db->quote($name) . "");
            $existing = $stmt->fetchColumn();
            if (intval($existing) > 0) json(['error' => '岗位名称已存在']);
            $sortOrder = intval($input['sort_order'] ?? 0);
            $n = now();
            $db->exec("INSERT INTO positions (name, sort_order, created_at) VALUES (" . $db->quote($name) . ", $sortOrder, '$n')");
            json(['id' => $db->lastInsertId(), 'message' => '岗位添加成功']);

        case 'update_position':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $pid = intval($input['id'] ?? 0);
            if (!$pid) json(['error' => '岗位ID无效']);
            $newName = trim($input['name'] ?? '');
            $sortOrder = $input['sort_order'] ?? null;
            $old = $db->query("SELECT * FROM positions WHERE id = $pid")->fetch(PDO::FETCH_ASSOC);
            if (!$old) json(['error' => '岗位不存在']);
            $finalName = $newName !== '' ? $newName : $old['name'];
            $finalSort = $sortOrder !== null ? intval($sortOrder) : intval($old['sort_order']);
            if ($newName !== '' && $newName !== $old['name']) {
                $stmt = $db->query("SELECT COUNT(*) FROM positions WHERE name = " . $db->quote($newName) . " AND id != $pid");
                $dup = $stmt->fetchColumn();
                if (intval($dup) > 0) json(['error' => '岗位名称已存在']);
            }
            $db->exec("BEGIN");
            $db->exec("UPDATE positions SET name = " . $db->quote($finalName) . ", sort_order = $finalSort WHERE id = $pid");
            if ($newName !== '' && $newName !== $old['name']) {
                $db->exec("UPDATE employees SET position = " . $db->quote($newName) . " WHERE position = " . $db->quote($old['name']) . "");
            }
            $db->exec("COMMIT");
            json(['message' => '岗位更新成功']);

        case 'delete_position':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $pid = intval($input['id'] ?? 0);
            $stmt = $db->query("SELECT name FROM positions WHERE id = $pid");
            $old = $stmt->fetchColumn();
            if (!$old) json(['error' => '岗位不存在']);
            $stmt = $db->query("SELECT COUNT(*) FROM employees WHERE position = " . $db->quote($old) . "");
            $inUse = $stmt->fetchColumn();
            if (intval($inUse) > 0) json(['error' => "该岗位下有 {$inUse} 名员工，不可删除"]);
            $db->exec("DELETE FROM positions WHERE id=$pid");
            json(['message' => '岗位删除成功']);

        case 'batch_import_employees':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $hasFile = isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK;

            if (!$hasFile) {
                // JSON 模式
                $items = $input['items'] ?? [];
                $n = now(); $count = 0; $failCount = 0; $failures = [];
                $stmt = $db->prepare("INSERT INTO employees (name,phone,department,position,entry_date,status,is_teacher,created_at,updated_at) VALUES (:n,:p,:d,:pos,:ed,:st,:it,:c,:u)");
                foreach ($items as $idx => $item) {
                    $rowNum = $idx + 1;
                    $ename = trim($item['name'] ?? '');
                    if ($ename === '') { $failCount++; $failures[] = ['row' => $rowNum, 'reason' => '缺少必填字段：姓名']; continue; }
                    $phoneVal = trim($item['phone'] ?? '');
                    // 姓名和手机号唯一性校验（同时检查，两个都重复两个都提示）
                    $rowErrors = [];
                    $stmt = $db->query("SELECT COUNT(*) FROM employees WHERE name = " . $db->quote($ename) . "");
                    $dup = $stmt->fetchColumn();
                    if (intval($dup) > 0) $rowErrors[] = "姓名 {$ename} 已存在";
                    if ($phoneVal !== '') {
                        $stmt = $db->query("SELECT COUNT(*) FROM employees WHERE phone = " . $db->quote($phoneVal) . "");
                        $dup = $stmt->fetchColumn();
                        if (intval($dup) > 0) $rowErrors[] = "手机号 {$phoneVal} 已存在";
                    }
                    if (!empty($rowErrors)) { $failCount++; $failures[] = ['row' => $rowNum, 'reason' => implode('；', $rowErrors)]; continue; }
                    $stmt->bindValue(':n', $ename);
                    $stmt->bindValue(':p', $phoneVal);
                    $stmt->bindValue(':d', $item['department']??'');
                    $stmt->bindValue(':pos', $item['position']??'');
                    $stmt->bindValue(':ed', $item['entry_date']??'');
                    $stmt->bindValue(':st', $item['status']??'在职');
                    $stmt->bindValue(':it', $item['is_teacher']??'');
                    $stmt->bindValue(':c', $n); $stmt->bindValue(':u', $n);
                    $stmt->execute();
                    $count++;
                }
                json(['message' => "成功导入 {$count} 条" . ($failCount > 0 ? "，跳过 {$failCount} 条" : ''), 'count' => $count, 'skip_count' => $failCount, 'failures' => $failures]);
            }

            // Excel 模式
            $file = $_FILES['file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'xls'])) {
                json(['error' => '仅支持 .xlsx 或 .xls 格式', 'success_count' => 0, 'fail_count' => 0, 'failures' => []]);
            }
            if ($file['size'] > 10 * 1024 * 1024) {
                json(['error' => '文件大小不能超过 10MB', 'success_count' => 0, 'fail_count' => 0, 'failures' => []]);
            }
            if (!class_exists('ZipArchive')) {
                json(['error' => '服务器缺少 zip 扩展，无法处理 Excel 文件', 'success_count' => 0, 'fail_count' => 0, 'failures' => []]);
            }
            $tmpPath = $file['tmp_name'];
            $importPath = __DIR__ . '/temp_emp_import_' . time() . '.' . $ext;
            move_uploaded_file($tmpPath, $importPath);
            try {
                $rows = parseXlsx($importPath);
            } catch (Exception $e) {
                @unlink($importPath);
                json(['error' => '解析 Excel 文件失败: ' . $e->getMessage(), 'success_count' => 0, 'fail_count' => 0, 'failures' => []]);
            }
            @unlink($importPath);
            if (empty($rows)) {
                json(['error' => 'Excel 文件为空', 'success_count' => 0, 'fail_count' => 0, 'failures' => []]);
            }
            $header = array_map('trim', $rows[0]);
            $headerMap = [
                '姓名' => 'name',
                '手机号' => 'phone',
                '电话' => 'phone',
                '部门' => 'department',
                '职位' => 'position',
                '入职日期' => 'entry_date',
                '状态' => 'status',
                '是否教师' => 'is_teacher',
            ];
            $colMap = [];
            foreach ($header as $idx => $colName) {
                if (isset($headerMap[$colName])) {
                    $colMap[$idx] = $headerMap[$colName];
                }
            }
            $n = now();
            $successCount = 0;
            $failures = [];
            $stmt = $db->prepare("INSERT INTO employees (name,phone,department,position,entry_date,status,is_teacher,created_at,updated_at) VALUES (:n,:p,:d,:pos,:ed,:st,:it,:c,:u)");
            for ($rowIdx = 1; $rowIdx < count($rows); $rowIdx++) {
                $row = $rows[$rowIdx];
                $item = ['name' => '', 'phone' => '', 'department' => '', 'position' => '', 'entry_date' => '', 'status' => '在职', 'is_teacher' => ''];
                foreach ($colMap as $colIdx => $field) {
                    if (isset($row[$colIdx])) {
                        $item[$field] = trim($row[$colIdx]);
                    }
                }
                if ($item['name'] === '') {
                    $failures[] = ['row' => $rowIdx + 1, 'reason' => '缺少必填字段：姓名'];
                    continue;
                }
                // 姓名和手机号唯一性校验（同时检查，两个都重复两个都提示）
                $rowErrors = [];
                $stmt = $db->query("SELECT COUNT(*) FROM employees WHERE name = " . $db->quote($item['name']) . "");
                $dup = $stmt->fetchColumn();
                if (intval($dup) > 0) $rowErrors[] = "姓名 {$item['name']} 已存在";
                if ($item['phone'] !== '') {
                    $stmt = $db->query("SELECT COUNT(*) FROM employees WHERE phone = " . $db->quote($item['phone']) . "");
                    $dup = $stmt->fetchColumn();
                    if (intval($dup) > 0) $rowErrors[] = "手机号 {$item['phone']} 已存在";
                }
                if (!empty($rowErrors)) {
                    $failures[] = ['row' => $rowIdx + 1, 'reason' => implode('；', $rowErrors)];
                    continue;
                }
                $stmt->bindValue(':n', $item['name']);
                $stmt->bindValue(':p', $item['phone']);
                $stmt->bindValue(':d', $item['department']);
                $stmt->bindValue(':pos', $item['position']);
                $stmt->bindValue(':ed', $item['entry_date']);
                $stmt->bindValue(':st', $item['status']);
                $stmt->bindValue(':it', $item['is_teacher']);
                $stmt->bindValue(':c', $n); $stmt->bindValue(':u', $n);
                $stmt->execute();
                $successCount++;
            }
            $failCount = count($failures);
            json([
                'message' => "导入完成：成功 {$successCount} 条" . ($failCount > 0 ? "，失败 {$failCount} 条" : ''),
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'failures' => $failures,
            ]);

        case 'export_employees':
            $keyword = $_GET['keyword'] ?? '';
            $department = $_GET['department'] ?? '';
            $status = $_GET['status'] ?? '';

            $where = [];
            $params = [];
            if ($keyword) {
                $where[] = "(name LIKE :kw1 OR phone LIKE :kw2 OR department LIKE :kw3 OR position LIKE :kw4)";
                $params[':kw1'] = "%$keyword%"; $params[':kw2'] = "%$keyword%"; $params[':kw3'] = "%$keyword%";
                $params[':kw3'] = "%$keyword%"; $params[':kw4'] = "%$keyword%";
            }
            if ($department) { $where[] = "department = :dept"; $params[':dept'] = $department; }
            if ($status) { $where[] = "status = :st"; $params[':st'] = $status; }
            $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $stmt = $db->prepare("SELECT name, phone, department, position, entry_date, status, is_teacher, created_at, updated_at FROM employees $whereStr ORDER BY updated_at DESC");
            foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
$stmt->execute();

            $filename = '员工导出_' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $output = fopen('php://output', 'w');
            fprintf($output, "\xEF\xBB\xBF");
            fputcsv($output, ['姓名', '手机号', '部门', '职位', '入职日期', '状态', '是否教师', '创建时间', '更新时间']);
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $r['name'], $r['phone'], $r['department'], $r['position'],
                    $r['entry_date'], $r['status'], $r['is_teacher'], $r['created_at'], $r['updated_at']
                ]);
            }
            fclose($output);
            exit;

// ==================== 课程管理 API ====================
        case 'list_courses':
            $page = max(1, intval($_GET['page'] ?? 1));
            $pageSize = max(1, min(100, intval($_GET['page_size'] ?? 20)));
            $keyword = $_GET['keyword'] ?? '';
            $campusId = intval($_GET['campus_id'] ?? 0);
            $subjectLevel1 = $_GET['subject_level1'] ?? '';
            $subjectLevel2 = $_GET['subject_level2'] ?? '';
            $smallPackage = $_GET['small_package'] ?? '';
            $toddler = $_GET['toddler'] ?? '';
            $campusIds = $_GET['campus_ids'] ?? '';

            $where = [];
            $params = [];
            if ($keyword) {
                $where[] = "(name LIKE :kw1 OR subject_level1 LIKE :kw2 OR subject_level2 LIKE :kw3)";
                $params[':kw1'] = "%$keyword%"; $params[':kw2'] = "%$keyword%"; $params[':kw3'] = "%$keyword%";
            }
            if ($campusId > 0) {
                $where[] = "(campus_permission = '' OR campus_permission IS NULL OR FIND_IN_SET(:cid, campus_permission))";
                $params[':cid'] = $campusId;
            }
            if ($subjectLevel1) {
                $where[] = "subject_level1 = :sl1";
                $params[':sl1'] = $subjectLevel1;
            }
            if ($subjectLevel2) {
                $where[] = "subject_level2 = :sl2";
                $params[':sl2'] = $subjectLevel2;
            }
            if ($smallPackage !== '') {
                $where[] = "small_package = :spk";
                $params[':spk'] = $smallPackage;
            }
            if ($toddler !== '') {
                $where[] = "toddler = :tdl";
                $params[':tdl'] = $toddler;
            }
            if ($campusIds) {
                $ids = array_filter(array_map('intval', explode(',', $campusIds)));
                if ($ids) {
                    $campusClauses = [];
                    foreach ($ids as $i => $cid) {
                        $key = ":cid$i";
                        $campusClauses[] = "FIND_IN_SET($key, campus_permission)";
                        $params[$key] = $cid;
                    }
                    $where[] = '(' . implode(' OR ', $campusClauses) . ')';
                }
            }
            $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $countStmt = $db->prepare("SELECT COUNT(*) FROM courses $whereStr");
            foreach ($params as $k => $v) $countStmt->bindValue($k, $v, PDO::PARAM_STR);
            $countStmt->execute(); $total = $countStmt->fetch(PDO::FETCH_NUM)[0];
            $total = $total ? intval($total) : 0;
            $offset = ($page - 1) * $pageSize;
            $stmt = $db->prepare("SELECT * FROM courses $whereStr ORDER BY id DESC LIMIT :lim OFFSET :off");
            foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
            $stmt->bindValue(':lim', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $rows = [];
$stmt->execute();
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $r;
            json(['total' => $total, 'page' => $page, 'page_size' => $pageSize, 'data' => $rows]);

        case 'add_course':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $name = trim($input['name'] ?? '');
            if (!$name) json(['error' => '课程名称不能为空']);
            $stmt = $db->query("SELECT COUNT(*) FROM courses WHERE name = " . $db->quote($name) . "");
            $dup = $stmt->fetchColumn();
            if (intval($dup) > 0) json(['error' => '课程名称已存在']);
            $subject_level1 = trim($input['subject_level1'] ?? '');
            $subject_level2 = trim($input['subject_level2'] ?? '');
            $small_package = trim($input['small_package'] ?? '');
            $toddler = trim($input['toddler'] ?? '');
            $campus_permission = trim($input['campus_permission'] ?? '');
            $db->exec("INSERT INTO courses (name, subject, subject_level1, subject_level2, small_package, toddler, campus_permission, created_at) VALUES (" . $db->quote($name) . ", " . $db->quote($subject_level1 . ' > ' . $subject_level2) . ", " . $db->quote($subject_level1) . ", " . $db->quote($subject_level2) . ", " . $db->quote($small_package) . ", " . $db->quote($toddler) . ", " . $db->quote($campus_permission) . ", '" . now() . "')");
            json(['id' => $db->lastInsertId(), 'message' => '课程添加成功']);

        case 'update_course':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $cid = intval($input['id'] ?? 0);
            if (!$cid) json(['error' => '课程ID无效']);
            $existing = $db->query("SELECT * FROM courses WHERE id=$cid")->fetch(PDO::FETCH_ASSOC);
            if (!$existing) json(['error' => '课程不存在']);
            $name = trim($input['name'] ?? '');
            if ($name === '') $name = $existing['name'];
            // 名称不可重复（排除自身）
            $stmt = $db->query("SELECT COUNT(*) FROM courses WHERE name = " . $db->quote($name) . " AND id != $cid");
            $dup = $stmt->fetchColumn();
            if (intval($dup) > 0) json(['error' => '课程名称已存在']);
            $subject_level1 = array_key_exists('subject_level1', $input) ? trim($input['subject_level1']) : ($existing['subject_level1'] ?? '');
            $subject_level2 = array_key_exists('subject_level2', $input) ? trim($input['subject_level2']) : ($existing['subject_level2'] ?? '');
            $small_package = array_key_exists('small_package', $input) ? trim($input['small_package']) : ($existing['small_package'] ?? '');
            $toddler = array_key_exists('toddler', $input) ? trim($input['toddler']) : ($existing['toddler'] ?? '');
            $campus_permission = array_key_exists('campus_permission', $input) ? trim($input['campus_permission']) : ($existing['campus_permission'] ?? '');
            $db->exec("UPDATE courses SET name=" . $db->quote($name) . ", subject=" . $db->quote($subject_level1 . ' > ' . $subject_level2) . ", subject_level1=" . $db->quote($subject_level1) . ", subject_level2=" . $db->quote($subject_level2) . ", small_package=" . $db->quote($small_package) . ", toddler=" . $db->quote($toddler) . ", campus_permission=" . $db->quote($campus_permission) . " WHERE id=$cid");
            json(['message' => '课程更新成功']);

        case 'delete_course':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $cid = intval($input['id'] ?? 0);
            $db->exec("DELETE FROM courses WHERE id=$cid");
            json(['message' => '课程删除成功']);

        case 'export_courses':
            $keyword = $_GET['keyword'] ?? '';

            $where = [];
            $params = [];
            if ($keyword) {
                $where[] = "(name LIKE :kw1 OR subject_level1 LIKE :kw2 OR subject_level2 LIKE :kw3)";
                $params[':kw1'] = "%$keyword%"; $params[':kw2'] = "%$keyword%"; $params[':kw3'] = "%$keyword%";
            }
            $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $stmt = $db->prepare("SELECT * FROM courses $whereStr ORDER BY id DESC");
            foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
$stmt->execute();

            $filename = '课程导出_' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $output = fopen('php://output', 'w');
            fprintf($output, "\xEF\xBB\xBF");
            // 查询校区名称映射
            $campusMap = [];
            $campusRes = $db->query("SELECT id, name FROM organizations WHERE type='校区'");
            while ($cr = $campusRes->fetch(PDO::FETCH_ASSOC)) $campusMap[$cr['id']] = $cr['name'];
            fputcsv($output, ['编号', '课程名称', '一级学科', '二级学科', '适用校区', '小课包', '低幼龄', '创建时间']);
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $campusNames = [];
                if (!empty($r['campus_permission'])) {
                    foreach (explode(',', $r['campus_permission']) as $cid) {
                        $cid = intval(trim($cid));
                        if ($cid && isset($campusMap[$cid])) $campusNames[] = $campusMap[$cid];
                    }
                }
                fputcsv($output, [
                    $r['id'], $r['name'], $r['subject_level1'], $r['subject_level2'],
                    implode('，', $campusNames),
                    $r['small_package'] ?? '', $r['toddler'] ?? '', $r['created_at']
                ]);
            }
            fclose($output);
            exit;

// ==================== 价格管理 API ====================
        case 'list_price_plans':
            $courseId = intval($_GET['course_id'] ?? 0);
            if (!$courseId) json(['error' => '缺少 course_id']);
            $plans = [];
            $planRes = $db->query("SELECT * FROM price_plans WHERE course_id=$courseId ORDER BY sort_order, id");
            while ($plan = $planRes->fetch(PDO::FETCH_ASSOC)) {
                $items = [];
                $itemRes = $db->query(
                    "SELECT pi.*, d.name AS discount_plan_name, c.name AS coupon_name
                     FROM price_items pi
                     LEFT JOIN discount_plans d ON pi.discount_plan_id = d.id
                     LEFT JOIN coupons c ON pi.coupon_id = c.id
                     WHERE pi.plan_id=" . intval($plan['id']) . "
                     ORDER BY pi.sort_order, pi.id"
                );
                while ($item = $itemRes->fetch(PDO::FETCH_ASSOC)) $items[] = $item;
                $plan['items'] = $items;
                $plans[] = $plan;
            }
            json(['data' => $plans]);

        case 'get_course_plans':
            $courseId = intval($_GET['course_id'] ?? 0);
            if ($courseId <= 0) json(['error' => '缺少 course_id']);
            $plans = [];
            $planRes = $db->query("SELECT * FROM price_plans WHERE course_id=$courseId ORDER BY sort_order, id");
            while ($plan = $planRes->fetch(PDO::FETCH_ASSOC)) {
                $items = [];
                $itemRes = $db->query(
                    "SELECT pi.*, d.name AS discount_plan_name, c.name AS coupon_name
                     FROM price_items pi
                     LEFT JOIN discount_plans d ON pi.discount_plan_id = d.id
                     LEFT JOIN coupons c ON pi.coupon_id = c.id
                     WHERE pi.plan_id=" . intval($plan['id']) . "
                     ORDER BY pi.sort_order, pi.id"
                );
                while ($item = $itemRes->fetch(PDO::FETCH_ASSOC)) $items[] = $item;
                $plan['items'] = $items;
                $plans[] = $plan;
            }
            json(['data' => $plans]);

        case 'pay_enroll':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $studentId = intval($input['student_id'] ?? 0);
            $planId = intval($input['plan_id'] ?? 0);
            $courseId = intval($input['course_id'] ?? 0);
            if ($studentId <= 0) json(['error' => '学员ID无效']);
            if ($planId <= 0) json(['error' => '方案ID无效']);
            if ($courseId <= 0) json(['error' => '课程ID无效']);
            $plan = $db->query("SELECT * FROM price_plans WHERE id=$planId")->fetch(PDO::FETCH_ASSOC);
            if (!$plan) json(['error' => '价格方案不存在']);
            // 读取课程的小课包字段，若为非空则强制类型为小课包
            $course = $db->query("SELECT small_package FROM courses WHERE id=$courseId")->fetch(PDO::FETCH_ASSOC);
            $orderType = trim($plan['plan_type'] ?? '');
            if (in_array($course['small_package'] ?? '', ['是','1','小课包'], true)) {
                $orderType = '小课包';
            }
            $items = [];
            $itemRes = $db->query("SELECT * FROM price_items WHERE plan_id=$planId ORDER BY sort_order, id");
            while ($item = $itemRes->fetch(PDO::FETCH_ASSOC)) $items[] = $item;
            if (empty($items)) json(['error' => '该方案下无报价单']);
            $paymentCash = floatval($input['payment_cash'] ?? 0);
            $paymentMeituan = floatval($input['payment_meituan'] ?? 0);
            $useBalance = intval($input['use_balance'] ?? 0);
            $balanceAmount = floatval($input['balance_amount'] ?? 0);
            $campusId = intval($input['campus_id'] ?? 0);
            $campusName = '';
            if ($campusId > 0) {
                $campusRow = $db->query("SELECT name FROM organizations WHERE id=$campusId AND type='校区'")->fetch(PDO::FETCH_ASSOC);
                $campusName = $campusRow['name'] ?? '';
            }
            $itemPrices = array_map(function($it) { return floatval($it['actual_price']); }, $items);
            $totalPrice = array_sum($itemPrices);
            if (abs($paymentCash + $paymentMeituan + $balanceAmount - $totalPrice) > 0.01) {
                json(['error' => '支付金额合计（' . ($paymentCash + $paymentMeituan + $balanceAmount) . '）与订单总额（' . $totalPrice . '）不一致，请调整']);
            }
            // 余额支付：扣减账户余额
            $newBalAfter = null;
            if ($useBalance && $balanceAmount > 0) {
                $db->beginTransaction();
                try {
                    $acct = $db->prepare("SELECT balance FROM student_accounts WHERE student_id = :sid FOR UPDATE");
                    $acct->bindValue(':sid', $studentId, PDO::PARAM_INT);
                    $acct->execute();
                    $acct = $acct->fetch(PDO::FETCH_ASSOC);
                    $currentBalance = $acct ? floatval($acct['balance']) : 0.00;
                    if ($currentBalance < $balanceAmount) {
                        $db->rollBack();
                        json(['error' => '账户余额不足（当前 ¥' . number_format($currentBalance, 2) . '，需要 ¥' . number_format($balanceAmount, 2) . '）']);
                    }
                    $newBalance = round($currentBalance - $balanceAmount, 2);
                    $newBalAfter = $newBalance;
                    $upd = $db->prepare("INSERT INTO student_accounts (student_id, balance, total_deposit, total_consume, total_refund) VALUES (:sid, 0, 0, 0, 0) ON DUPLICATE KEY UPDATE balance = :bal, total_consume = total_consume + :tc");
                    $upd->bindValue(':sid', $studentId, PDO::PARAM_INT);
                    $upd->bindValue(':bal', $newBalance);
                    $upd->bindValue(':tc', $balanceAmount);
                    $upd->execute();
                    $db->commit();
                } catch (Exception $e) {
                    $db->rollBack();
                    json(['error' => '余额扣款失败: ' . $e->getMessage()]);
                }
            }
            $n = date('Y-m-d H:i:s');
            $orderIds = [];
            $childOrderNos = [];
            $totalLessons = 0;
            $stmt = $db->prepare("INSERT INTO orders (student_id, course_id, plan_name, item_name, lesson_count, actual_price, cash_amount, meituan_amount, account_amount, paid_amount, order_no, parent_order_no, created_at, paid_at, order_type, campus, pay_status, is_voided) VALUES (:sid, :cid, :pn, :inm, :lc, :ap, :ca, :ma, :aa, :pa, :ono, :pono, :ct, :pat, :ot, :campus, :ps, :iv)");
            $parentOrderNo = generateOrderNo($db);
            $remainingCash = $paymentCash;
            $remainingMeituan = $paymentMeituan;
            $remainingAccount = $balanceAmount;
            foreach ($items as $i => $item) {
                $itemPrice = floatval($item['actual_price']);
                // 先用余额，再用现金，最后美团
                $acctForThis = min($remainingAccount, $itemPrice);
                $remainingAccount -= $acctForThis;
                $cashForThis = min($remainingCash, $itemPrice - $acctForThis);
                $remainingCash -= $cashForThis;
                $mtForThis = min($remainingMeituan, $itemPrice - $acctForThis - $cashForThis);
                $remainingMeituan -= $mtForThis;
                $orderNo = generateOrderNo($db);
                $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
                $stmt->bindValue(':cid', $courseId, PDO::PARAM_INT);
                $stmt->bindValue(':pn', $plan['name'], PDO::PARAM_STR);
                $stmt->bindValue(':inm', $item['name'], PDO::PARAM_STR);
                $stmt->bindValue(':lc', intval($item['lesson_count']), PDO::PARAM_INT);
                $stmt->bindValue(':ap', $itemPrice, PDO::PARAM_STR);
                $stmt->bindValue(':ca', $cashForThis, PDO::PARAM_STR);
                $stmt->bindValue(':ma', $mtForThis, PDO::PARAM_STR);
                $stmt->bindValue(':aa', $acctForThis, PDO::PARAM_STR);
                $stmt->bindValue(':pa', $acctForThis + $cashForThis + $mtForThis, PDO::PARAM_STR);
                $stmt->bindValue(':ono', $orderNo, PDO::PARAM_STR);
                $stmt->bindValue(':pono', $parentOrderNo, PDO::PARAM_STR);
                $stmt->bindValue(':ct', $n, PDO::PARAM_STR);
                $stmt->bindValue(':pat', $n, PDO::PARAM_STR);
                $stmt->bindValue(':ot', $orderType, PDO::PARAM_STR);
                $stmt->bindValue(':campus', $campusName, PDO::PARAM_STR);
                $stmt->bindValue(':ps', '已支付', PDO::PARAM_STR);
                $stmt->bindValue(':iv', '否', PDO::PARAM_STR);
                $stmt->execute();
                $orderIds[] = $db->lastInsertId();
                $childOrderNos[] = $orderNo;
                $totalLessons += intval($item['lesson_count']);
            }
            // 写入父订单汇总
            $student = $db->query("SELECT name, phone, student_no FROM students WHERE id=$studentId")->fetch(PDO::FETCH_ASSOC);
            $course = $db->query("SELECT name FROM courses WHERE id=$courseId")->fetch(PDO::FETCH_ASSOC);
            $childNosStr = implode(',', $childOrderNos);
            $stmtParent = $db->prepare("INSERT INTO parent_orders (parent_order_no, child_order_nos, course_name, total_lessons, student_name, phone, student_no, enroll_time, total_price, cash_amount, meituan_amount, created_at, campus) VALUES (:pono, :cnos, :cname, :tl, :sname, :phone, :sno, :etime, :tp, :ca, :ma, :ct, :campus)");
            $stmtParent->bindValue(':pono', $parentOrderNo, PDO::PARAM_STR);
            $stmtParent->bindValue(':cnos', $childNosStr, PDO::PARAM_STR);
            $stmtParent->bindValue(':cname', $course['name'] ?? '', PDO::PARAM_STR);
            $stmtParent->bindValue(':tl', $totalLessons, PDO::PARAM_INT);
            $stmtParent->bindValue(':sname', $student['name'] ?? '', PDO::PARAM_STR);
            $stmtParent->bindValue(':phone', $student['phone'] ?? '', PDO::PARAM_STR);
            $stmtParent->bindValue(':sno', $student['student_no'] ?? '', PDO::PARAM_STR);
            $stmtParent->bindValue(':etime', $n, PDO::PARAM_STR);
            $stmtParent->bindValue(':tp', $totalPrice, PDO::PARAM_STR);
            $stmtParent->bindValue(':ca', $paymentCash, PDO::PARAM_STR);
            $stmtParent->bindValue(':ma', $paymentMeituan, PDO::PARAM_STR);
            $stmtParent->bindValue(':ct', $n, PDO::PARAM_STR);
            $stmtParent->bindValue(':campus', $campusName, PDO::PARAM_STR);
            $stmtParent->execute();
            // 余额支付：写入账户流水
            if ($useBalance && $balanceAmount > 0 && !empty($orderIds)) {
                $txStmt = $db->prepare("INSERT INTO account_transactions (student_id, type, amount, balance_after, ref_type, ref_id, campus, note) VALUES (:sid, 'consume', :amt, :ba, 'order', :rid, :campus, :note)");
                $txStmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
                $txStmt->bindValue(':amt', $balanceAmount);
                $txStmt->bindValue(':ba', $newBalAfter, PDO::PARAM_STR);
                $txStmt->bindValue(':rid', $orderIds[0], PDO::PARAM_INT);
                $txStmt->bindValue(':campus', $campusName, PDO::PARAM_STR);
                $txStmt->bindValue(':note', '余额支付', PDO::PARAM_STR);
                $txStmt->execute();
            }
            // 标记来源资源为已转化（不可逆）
            $db->exec("UPDATE resources SET converted = '已转化' WHERE id = (SELECT resource_id FROM students WHERE id = $studentId) AND converted = '未转化'");
            $msg = '支付成功，共生成 ' . count($orderIds) . ' 笔订单';
            if ($paymentCash > 0 && $paymentMeituan > 0) {
                $msg .= '（现金 ¥' . number_format($paymentCash, 2) . ' + 美团 ¥' . number_format($paymentMeituan, 2) . '）';
            } else {
                $msg .= '（' . ($paymentCash > 0 ? '现金' : '美团') . '）';
            }
            // 重新计算学员类型：只要存在非小课包订单即升级为常规
            $st = $db->query("SELECT student_type FROM students WHERE id=$studentId")->fetch(PDO::FETCH_ASSOC);
            $currentType = $st['student_type'] ?? '小课包';
            if ($currentType !== '常规') {
                $hasNonXKB = $db->query("SELECT COUNT(*) FROM orders WHERE student_id=$studentId AND is_voided='否' AND order_type != '小课包' AND order_type != ''")->fetchColumn();
                if ($hasNonXKB > 0) {
                    $db->exec("UPDATE students SET student_type='常规' WHERE id=$studentId");
                }
            }
            json(['message' => $msg, 'order_ids' => $orderIds, 'count' => count($orderIds)]);

        case 'save_price_plan':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $courseId = intval($input['course_id'] ?? 0);
            $planName = trim($input['plan_name'] ?? '');
            $planType = trim($input['plan_type'] ?? '');
            $items = $input['items'] ?? [];
            if (!$courseId) json(['error' => '课程ID无效']);
            if (!$planName) json(['error' => '方案名称不能为空']);
            if (!is_array($items) || count($items) === 0) json(['error' => '至少需要一个报价单']);

            $planId = intval($input['plan_id'] ?? 0);
            if ($planId > 0) {
                // 编辑：更新方案名称，全量替换报价单
                $existing = $db->query("SELECT * FROM price_plans WHERE id=$planId")->fetch(PDO::FETCH_ASSOC);
                if (!$existing) json(['error' => '价格方案不存在']);
                $db->exec("UPDATE price_plans SET name=" . $db->quote($planName) . ", plan_type=" . $db->quote($planType) . " WHERE id=$planId");
                $db->exec("DELETE FROM price_items WHERE plan_id=$planId");
            } else {
                // 新增
                $db->exec("INSERT INTO price_plans (course_id, name, plan_type, created_at) VALUES ($courseId, " . $db->quote($planName) . ", " . $db->quote($planType) . ", '" . now() . "')");
                $planId = $db->lastInsertId();
            }

            // 插入报价单
            foreach ($items as $idx => $item) {
                $itemName = trim($item['name'] ?? '');
                $lessonCount = intval($item['lesson_count'] ?? 0);
                $unitPrice = floatval($item['unit_price'] ?? 0);
                $actualPrice = floatval($item['actual_price'] ?? $unitPrice);
                $sortOrder = intval($item['sort_order'] ?? $idx);
                $discountPlanId = intval($item['discount_plan_id'] ?? 0);
                $couponId = intval($item['coupon_id'] ?? 0);
                if (!$itemName || $lessonCount <= 0) continue;
                $db->exec("INSERT INTO price_items (plan_id, name, lesson_count, unit_price, actual_price, discount_plan_id, coupon_id, sort_order) VALUES ($planId, " . $db->quote($itemName) . ", $lessonCount, $unitPrice, $actualPrice, "
                    . ($discountPlanId > 0 ? $discountPlanId : 'NULL') . ", "
                    . ($couponId > 0 ? $couponId : 'NULL') . ", $sortOrder)");
            }
            json(['id' => $planId, 'message' => $planId ? '价格方案保存成功' : '价格方案保存成功']);

        case 'delete_price_plan':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $planId = intval($input['plan_id'] ?? 0);
            if (!$planId) json(['error' => '方案ID无效']);
            $db->exec("DELETE FROM price_items WHERE plan_id=$planId");
            $db->exec("DELETE FROM price_plans WHERE id=$planId");
            json(['message' => '价格方案删除成功']);

// ==================== 优惠管理 API ====================
        case 'list_discount_plans':
            $page = max(1, intval($_GET['page'] ?? 1));
            $pageSize = max(1, min(100, intval($_GET['page_size'] ?? 15)));
            $keyword = trim($_GET['keyword'] ?? '');
            $planType = trim($_GET['plan_type'] ?? '');
            $campusId = intval($_GET['campus_id'] ?? 0);
            $offset = ($page - 1) * $pageSize;

            $where = ['1=1'];
            if ($keyword !== '') {
                $where[] = 'dp.name LIKE ' . $db->quote('%' . $keyword . '%');
            }
            if ($planType !== '') {
                $where[] = 'dp.plan_type = ' . $db->quote($planType);
            }
            if ($campusId > 0) {
                $where[] = '(dp.id IN (SELECT plan_id FROM discount_plan_campuses WHERE campus_id=' . $campusId . ') OR dp.id NOT IN (SELECT plan_id FROM discount_plan_campuses))';
            }
            $whereStr = implode(' AND ', $where);

            $cnt = $db->query("SELECT COUNT(*) FROM discount_plans dp WHERE $whereStr")->fetchColumn();
            $total = intval($cnt);

            $sql = "SELECT dp.*,
                (SELECT GROUP_CONCAT(DISTINCT dpc2.campus_id ORDER BY dpc2.campus_id SEPARATOR ',') FROM discount_plan_campuses dpc2 WHERE dpc2.plan_id=dp.id) AS campus_ids,
                (SELECT GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ') FROM discount_plan_campuses dpc2 LEFT JOIN organizations o ON dpc2.campus_id=o.id WHERE dpc2.plan_id=dp.id) AS campus_names,
                (SELECT GROUP_CONCAT(DISTINCT dps2.subject_id ORDER BY dps2.subject_id SEPARATOR ',') FROM discount_plan_subjects dps2 WHERE dps2.plan_id=dp.id) AS subject_ids,
                (SELECT GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') FROM discount_plan_subjects dps2 LEFT JOIN subjects s ON dps2.subject_id=s.id WHERE dps2.plan_id=dp.id) AS subject_names
            FROM discount_plans dp
            WHERE $whereStr
            ORDER BY dp.created_at DESC
            LIMIT $offset, $pageSize";
            $res = $db->query($sql);
            $rows = [];
            while ($r = $res->fetch(PDO::FETCH_ASSOC)) {
                $r['amount'] = floatval($r['discount_amount']);
                unset($r['discount_amount']);
                $rows[] = $r;
            }
            json(['data' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
            break;

        case 'add_discount_plan':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $name = trim($input['name'] ?? '');
            $planType = trim($input['plan_type'] ?? '新报');
            $discountAmount = floatval($input['discount_amount'] ?? $input['amount'] ?? 0);
            $startDate = trim($input['start_date'] ?? '');
            $endDate = trim($input['end_date'] ?? '');
            $campusIdsRaw = $input['campus_ids'] ?? [];
            $subjectIdsRaw = $input['subject_ids'] ?? [];

            if ($name === '') { json(['error' => '方案名称不能为空']); break; }
            if (!in_array($planType, ['新报', '续费'])) { json(['error' => '类型无效']); break; }
            if ($discountAmount <= 0) { json(['error' => '优惠金额必须大于0']); break; }
            if ($startDate === '' || $endDate === '') { json(['error' => '日期不能为空']); break; }
            if ($endDate < $startDate) { json(['error' => '结束日期不能早于开始日期']); break; }

            $dup = $db->query("SELECT COUNT(*) FROM discount_plans WHERE name=" . $db->quote($name) . " AND plan_type=" . $db->quote($planType))->fetchColumn();
            if ($dup > 0) { json(['error' => '同类型下方案名称已存在']); break; }

            $n = now();
            $db->beginTransaction();
            try {
                $db->exec("INSERT INTO discount_plans (name, plan_type, discount_amount, start_date, end_date, created_at, updated_at) VALUES (" . $db->quote($name) . ", " . $db->quote($planType) . ", $discountAmount, " . $db->quote($startDate) . ", " . $db->quote($endDate) . ", '$n', '$n')");
                $planId = $db->lastInsertId();

                if (!empty($campusIdsRaw)) {
                    $campusIds = is_string($campusIdsRaw) ? array_map('intval', explode(',', $campusIdsRaw)) : array_map('intval', $campusIdsRaw);
                    $vals = [];
                    foreach ($campusIds as $cid) { if ($cid > 0) $vals[] = "($planId, $cid)"; }
                    if (!empty($vals)) $db->exec("INSERT INTO discount_plan_campuses (plan_id, campus_id) VALUES " . implode(', ', $vals));
                }

                if (!empty($subjectIdsRaw)) {
                    $subjectIds = is_string($subjectIdsRaw) ? array_map('intval', explode(',', $subjectIdsRaw)) : array_map('intval', $subjectIdsRaw);
                    $vals = [];
                    foreach ($subjectIds as $sid) { if ($sid > 0) $vals[] = "($planId, $sid)"; }
                    if (!empty($vals)) $db->exec("INSERT INTO discount_plan_subjects (plan_id, subject_id) VALUES " . implode(', ', $vals));
                }

                $db->commit();
                json(['message' => '优惠方案创建成功', 'id' => $planId]);
            } catch (Exception $e) {
                $db->rollBack();
                json(['error' => '创建失败: ' . $e->getMessage()]);
            }
            break;

        case 'update_discount_plan':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) { json(['error' => 'ID无效']); break; }

            $existing = $db->query("SELECT * FROM discount_plans WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            if (!$existing) { json(['error' => '优惠方案不存在']); break; }

            $name = trim($input['name'] ?? $existing['name']);
            $planType = trim($input['plan_type'] ?? $existing['plan_type']);
            $discountAmount = isset($input['discount_amount']) ? floatval($input['discount_amount']) : (isset($input['amount']) ? floatval($input['amount']) : floatval($existing['discount_amount']));
            $startDate = trim($input['start_date'] ?? $existing['start_date']);
            $endDate = trim($input['end_date'] ?? $existing['end_date']);
            $campusIdsRaw = $input['campus_ids'] ?? null;
            $subjectIdsRaw = $input['subject_ids'] ?? null;

            if ($name === '') { json(['error' => '方案名称不能为空']); break; }
            if (!in_array($planType, ['新报', '续费'])) { json(['error' => '类型无效']); break; }
            if ($discountAmount <= 0) { json(['error' => '优惠金额必须大于0']); break; }
            if ($endDate < $startDate) { json(['error' => '结束日期不能早于开始日期']); break; }

            $dup = $db->query("SELECT COUNT(*) FROM discount_plans WHERE name=" . $db->quote($name) . " AND plan_type=" . $db->quote($planType) . " AND id!=$id")->fetchColumn();
            if ($dup > 0) { json(['error' => '同类型下方案名称已存在']); break; }

            $db->beginTransaction();
            try {
                $db->exec("UPDATE discount_plans SET name=" . $db->quote($name) . ", plan_type=" . $db->quote($planType) . ", discount_amount=$discountAmount, start_date=" . $db->quote($startDate) . ", end_date=" . $db->quote($endDate) . ", updated_at='" . now() . "' WHERE id=$id");

                if ($campusIdsRaw !== null) {
                    $db->exec("DELETE FROM discount_plan_campuses WHERE plan_id=$id");
                    if (!empty($campusIdsRaw)) {
                        $campusIds = is_string($campusIdsRaw) ? array_map('intval', explode(',', $campusIdsRaw)) : array_map('intval', $campusIdsRaw);
                        $vals = [];
                        foreach ($campusIds as $cid) { if ($cid > 0) $vals[] = "($id, $cid)"; }
                        if (!empty($vals)) $db->exec("INSERT INTO discount_plan_campuses (plan_id, campus_id) VALUES " . implode(', ', $vals));
                    }
                }

                if ($subjectIdsRaw !== null) {
                    $db->exec("DELETE FROM discount_plan_subjects WHERE plan_id=$id");
                    if (!empty($subjectIdsRaw)) {
                        $subjectIds = is_string($subjectIdsRaw) ? array_map('intval', explode(',', $subjectIdsRaw)) : array_map('intval', $subjectIdsRaw);
                        $vals = [];
                        foreach ($subjectIds as $sid) { if ($sid > 0) $vals[] = "($id, $sid)"; }
                        if (!empty($vals)) $db->exec("INSERT INTO discount_plan_subjects (plan_id, subject_id) VALUES " . implode(', ', $vals));
                    }
                }

                $db->commit();
                json(['message' => '优惠方案更新成功']);
            } catch (Exception $e) {
                $db->rollBack();
                json(['error' => '更新失败: ' . $e->getMessage()]);
            }
            break;

        case 'delete_discount_plan':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) { json(['error' => 'ID无效']); break; }

            $existing = $db->query("SELECT * FROM discount_plans WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            if (!$existing) { json(['error' => '优惠方案不存在']); break; }

            $db->exec("DELETE FROM discount_plans WHERE id=$id");
            json(['message' => '优惠方案已删除']);
            break;

        case 'get_discount_plan':
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) { json(['error' => 'ID无效']); break; }

            $plan = $db->query("SELECT * FROM discount_plans WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            if (!$plan) { json(['error' => '优惠方案不存在']); break; }

            $campusRes = $db->query("SELECT campus_id FROM discount_plan_campuses WHERE plan_id=$id ORDER BY campus_id");
            $campusIds = [];
            while ($cr = $campusRes->fetch(PDO::FETCH_ASSOC)) $campusIds[] = intval($cr['campus_id']);

            $subjectRes = $db->query("SELECT subject_id FROM discount_plan_subjects WHERE plan_id=$id ORDER BY subject_id");
            $subjectIds = [];
            while ($sr = $subjectRes->fetch(PDO::FETCH_ASSOC)) $subjectIds[] = intval($sr['subject_id']);

            $plan['amount'] = floatval($plan['discount_amount']);
            unset($plan['discount_amount']);
            $plan['campus_ids'] = $campusIds;
            $plan['subject_ids'] = $subjectIds;

            json($plan);
            break;

// ==================== 优惠券 API ====================
        case 'list_coupons':
            $page = max(1, intval($_GET['page'] ?? 1));
            $pageSize = max(1, min(100, intval($_GET['page_size'] ?? 15)));
            $keyword = trim($_GET['keyword'] ?? '');
            $couponType = trim($_GET['coupon_type'] ?? '');
            $campusId = intval($_GET['campus_id'] ?? 0);
            $offset = ($page - 1) * $pageSize;

            $where = ['1=1'];
            if ($keyword !== '') {
                $where[] = 'c.name LIKE ' . $db->quote('%' . $keyword . '%');
            }
            if ($couponType !== '') {
                $where[] = 'c.coupon_type = ' . $db->quote($couponType);
            }
            if ($campusId > 0) {
                $where[] = '(c.id IN (SELECT coupon_id FROM coupon_campuses WHERE campus_id=' . $campusId . ') OR c.id NOT IN (SELECT coupon_id FROM coupon_campuses))';
            }
            $whereStr = implode(' AND ', $where);

            $cnt = $db->query("SELECT COUNT(*) FROM coupons c WHERE $whereStr")->fetchColumn();
            $total = intval($cnt);

            $sql = "SELECT c.*,
                (SELECT GROUP_CONCAT(DISTINCT cc2.campus_id ORDER BY cc2.campus_id SEPARATOR ',') FROM coupon_campuses cc2 WHERE cc2.coupon_id=c.id) AS campus_ids,
                (SELECT GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ') FROM coupon_campuses cc2 LEFT JOIN organizations o ON cc2.campus_id=o.id WHERE cc2.coupon_id=c.id) AS campus_names,
                (SELECT GROUP_CONCAT(DISTINCT cs2.subject_id ORDER BY cs2.subject_id SEPARATOR ',') FROM coupon_subjects cs2 WHERE cs2.coupon_id=c.id) AS subject_ids,
                (SELECT GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') FROM coupon_subjects cs2 LEFT JOIN subjects s ON cs2.subject_id=s.id WHERE cs2.coupon_id=c.id) AS subject_names,
                (SELECT COUNT(*) FROM coupon_records cr WHERE cr.coupon_id=c.id) AS record_count
            FROM coupons c
            WHERE $whereStr
            ORDER BY c.created_at DESC
            LIMIT $offset, $pageSize";
            $res = $db->query($sql);
            $rows = [];
            while ($r = $res->fetch(PDO::FETCH_ASSOC)) {
                $r['amount'] = floatval($r['discount_amount']);
                unset($r['discount_amount']);
                $r['record_count'] = intval($r['record_count']);
                $rows[] = $r;
            }
            json(['data' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
            break;

        case 'add_coupon':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $name = trim($input['name'] ?? '');
            $couponType = trim($input['coupon_type'] ?? '课程券');
            $discountAmount = floatval($input['discount_amount'] ?? 0);
            $startDate = trim($input['start_date'] ?? '');
            $endDate = trim($input['end_date'] ?? '');
            $campusIdsRaw = $input['campus_ids'] ?? [];
            $subjectIdsRaw = $input['subject_ids'] ?? [];

            if ($name === '') { json(['error' => '优惠券名称不能为空']); break; }
            if (!in_array($couponType, ['课程券', '商品券'])) { json(['error' => '类型无效']); break; }
            if ($discountAmount <= 0) { json(['error' => '优惠金额必须大于0']); break; }
            if ($startDate === '' || $endDate === '') { json(['error' => '日期不能为空']); break; }
            if ($endDate < $startDate) { json(['error' => '结束日期不能早于开始日期']); break; }

            $dup = $db->query("SELECT COUNT(*) FROM coupons WHERE name=" . $db->quote($name) . " AND coupon_type=" . $db->quote($couponType))->fetchColumn();
            if ($dup > 0) { json(['error' => '同类型下优惠券名称已存在']); break; }

            $n = now();
            $db->beginTransaction();
            try {
                $db->exec("INSERT INTO coupons (name, coupon_type, discount_amount, start_date, end_date, created_at, updated_at) VALUES (" . $db->quote($name) . ", " . $db->quote($couponType) . ", $discountAmount, " . $db->quote($startDate) . ", " . $db->quote($endDate) . ", '$n', '$n')");
                $couponId = $db->lastInsertId();

                if (!empty($campusIdsRaw)) {
                    $campusIds = is_string($campusIdsRaw) ? array_map('intval', explode(',', $campusIdsRaw)) : array_map('intval', $campusIdsRaw);
                    $vals = [];
                    foreach ($campusIds as $cid) { if ($cid > 0) $vals[] = "($couponId, $cid)"; }
                    if (!empty($vals)) $db->exec("INSERT INTO coupon_campuses (coupon_id, campus_id) VALUES " . implode(', ', $vals));
                }

                if (!empty($subjectIdsRaw)) {
                    $subjectIds = is_string($subjectIdsRaw) ? array_map('intval', explode(',', $subjectIdsRaw)) : array_map('intval', $subjectIdsRaw);
                    $vals = [];
                    foreach ($subjectIds as $sid) { if ($sid > 0) $vals[] = "($couponId, $sid)"; }
                    if (!empty($vals)) $db->exec("INSERT INTO coupon_subjects (coupon_id, subject_id) VALUES " . implode(', ', $vals));
                }

                $db->commit();
                json(['message' => '优惠券创建成功', 'id' => $couponId]);
            } catch (Exception $e) {
                $db->rollBack();
                json(['error' => '创建失败: ' . $e->getMessage()]);
            }
            break;

        case 'update_coupon':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) { json(['error' => 'ID无效']); break; }

            $existing = $db->query("SELECT * FROM coupons WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            if (!$existing) { json(['error' => '优惠券不存在']); break; }

            $name = trim($input['name'] ?? $existing['name']);
            $couponType = trim($input['coupon_type'] ?? $existing['coupon_type']);
            $discountAmount = floatval($input['discount_amount'] ?? $existing['discount_amount']);
            $startDate = trim($input['start_date'] ?? $existing['start_date']);
            $endDate = trim($input['end_date'] ?? $existing['end_date']);
            $campusIdsRaw = $input['campus_ids'] ?? null;
            $subjectIdsRaw = $input['subject_ids'] ?? null;

            if ($name === '') { json(['error' => '优惠券名称不能为空']); break; }
            if (!in_array($couponType, ['课程券', '商品券'])) { json(['error' => '类型无效']); break; }
            if ($discountAmount <= 0) { json(['error' => '优惠金额必须大于0']); break; }
            if ($endDate < $startDate) { json(['error' => '结束日期不能早于开始日期']); break; }

            $dup = $db->query("SELECT COUNT(*) FROM coupons WHERE name=" . $db->quote($name) . " AND coupon_type=" . $db->quote($couponType) . " AND id!=$id")->fetchColumn();
            if ($dup > 0) { json(['error' => '同类型下优惠券名称已存在']); break; }

            $db->beginTransaction();
            try {
                $db->exec("UPDATE coupons SET name=" . $db->quote($name) . ", coupon_type=" . $db->quote($couponType) . ", discount_amount=$discountAmount, start_date=" . $db->quote($startDate) . ", end_date=" . $db->quote($endDate) . ", updated_at='" . now() . "' WHERE id=$id");

                if ($campusIdsRaw !== null) {
                    $db->exec("DELETE FROM coupon_campuses WHERE coupon_id=$id");
                    if (!empty($campusIdsRaw)) {
                        $campusIds = is_string($campusIdsRaw) ? array_map('intval', explode(',', $campusIdsRaw)) : array_map('intval', $campusIdsRaw);
                        $vals = [];
                        foreach ($campusIds as $cid) { if ($cid > 0) $vals[] = "($id, $cid)"; }
                        if (!empty($vals)) $db->exec("INSERT INTO coupon_campuses (coupon_id, campus_id) VALUES " . implode(', ', $vals));
                    }
                }

                if ($subjectIdsRaw !== null) {
                    $db->exec("DELETE FROM coupon_subjects WHERE coupon_id=$id");
                    if (!empty($subjectIdsRaw)) {
                        $subjectIds = is_string($subjectIdsRaw) ? array_map('intval', explode(',', $subjectIdsRaw)) : array_map('intval', $subjectIdsRaw);
                        $vals = [];
                        foreach ($subjectIds as $sid) { if ($sid > 0) $vals[] = "($id, $sid)"; }
                        if (!empty($vals)) $db->exec("INSERT INTO coupon_subjects (coupon_id, subject_id) VALUES " . implode(', ', $vals));
                    }
                }

                $db->commit();
                json(['message' => '优惠券更新成功']);
            } catch (Exception $e) {
                $db->rollBack();
                json(['error' => '更新失败: ' . $e->getMessage()]);
            }
            break;

        case 'delete_coupon':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) { json(['error' => 'ID无效']); break; }

            $existing = $db->query("SELECT * FROM coupons WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            if (!$existing) { json(['error' => '优惠券不存在']); break; }

            $db->exec("DELETE FROM coupons WHERE id=$id");
            json(['message' => '优惠券已删除']);
            break;

        case 'get_coupon':
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) { json(['error' => 'ID无效']); break; }

            $coupon = $db->query("SELECT * FROM coupons WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            if (!$coupon) { json(['error' => '优惠券不存在']); break; }

            $campusRes = $db->query("SELECT campus_id FROM coupon_campuses WHERE coupon_id=$id ORDER BY campus_id");
            $campusIds = [];
            while ($cr2 = $campusRes->fetch(PDO::FETCH_ASSOC)) $campusIds[] = intval($cr2['campus_id']);

            $subjectRes = $db->query("SELECT subject_id FROM coupon_subjects WHERE coupon_id=$id ORDER BY subject_id");
            $subjectIds = [];
            while ($sr = $subjectRes->fetch(PDO::FETCH_ASSOC)) $subjectIds[] = intval($sr['subject_id']);

            $coupon['amount'] = floatval($coupon['discount_amount']);
            unset($coupon['discount_amount']);
            $coupon['campus_ids'] = $campusIds;
            $coupon['subject_ids'] = $subjectIds;

            json($coupon);
            break;

// ==================== 优惠券发放记录 API ====================
        case 'list_coupon_records':
            $page = max(1, intval($_GET['page'] ?? 1));
            $pageSize = max(1, min(100, intval($_GET['page_size'] ?? 15)));
            $keyword = trim($_GET['keyword'] ?? '');
            $couponId = intval($_GET['coupon_id'] ?? 0);
            $dateFrom = trim($_GET['date_from'] ?? '');
            $dateTo = trim($_GET['date_to'] ?? '');
            $offset = ($page - 1) * $pageSize;

            $where = ['1=1'];
            if ($keyword !== '') {
                $where[] = '(cr.student_name LIKE ' . $db->quote('%' . $keyword . '%') . ' OR cr.phone LIKE ' . $db->quote('%' . $keyword . '%') . ' OR cr.coupon_name LIKE ' . $db->quote('%' . $keyword . '%') . ')';
            }
            if ($couponId > 0) {
                $where[] = 'cr.coupon_id = ' . $couponId;
            }
            if ($dateFrom !== '') {
                $where[] = 'cr.issued_at >= ' . $db->quote($dateFrom . ' 00:00:00');
            }
            if ($dateTo !== '') {
                $where[] = 'cr.issued_at <= ' . $db->quote($dateTo . ' 23:59:59');
            }
            $whereStr = implode(' AND ', $where);

            $cnt = $db->query("SELECT COUNT(*) FROM coupon_records cr LEFT JOIN coupons c ON cr.coupon_id=c.id WHERE $whereStr")->fetchColumn();
            $total = intval($cnt);

            $sql = "SELECT cr.id, cr.coupon_id, cr.student_name, cr.phone, cr.issuer AS distributor, cr.issued_at AS distributed_at, cr.created_at,
                COALESCE(cr.coupon_name, c.name) AS coupon_name,
                c.coupon_type,
                c.discount_amount
            FROM coupon_records cr
            LEFT JOIN coupons c ON cr.coupon_id=c.id
            WHERE $whereStr
            ORDER BY cr.issued_at DESC
            LIMIT $offset, $pageSize";
            $res = $db->query($sql);
            $rows = [];
            while ($r = $res->fetch(PDO::FETCH_ASSOC)) {
                $r['discount_amount'] = floatval($r['discount_amount']);
                $rows[] = $r;
            }
            json(['data' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
            break;

        case 'add_coupon_record':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $couponId = intval($input['coupon_id'] ?? 0);
            $studentName = trim($input['student_name'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $distributor = trim($input['issuer'] ?? $input['distributor'] ?? '');
            $distributedAt = trim($input['issued_at'] ?? $input['distributed_at'] ?? now());

            if ($couponId <= 0) { json(['error' => '优惠券ID无效']); break; }
            if ($studentName === '') { json(['error' => '学员姓名不能为空']); break; }
            if ($phone === '') { json(['error' => '手机号不能为空']); break; }
            if ($distributor === '') { json(['error' => '发放人不能为空']); break; }

            $cp = $db->query("SELECT name, coupon_type FROM coupons WHERE id=$couponId")->fetch(PDO::FETCH_ASSOC);
            if (!$cp) { json(['error' => '优惠券不存在']); break; }

            $couponName = $cp['name'];
            $n = now();
            $db->exec("INSERT INTO coupon_records (coupon_id, coupon_name, student_name, phone, issuer, issued_at, created_at) VALUES ($couponId, " . $db->quote($couponName) . ", " . $db->quote($studentName) . ", " . $db->quote($phone) . ", " . $db->quote($distributor) . ", " . $db->quote($distributedAt) . ", '$n')");
            json(['message' => '发放记录添加成功', 'id' => $db->lastInsertId()]);
            break;

        case 'delete_coupon_record':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) { json(['error' => 'ID无效']); break; }

            $existing = $db->query("SELECT * FROM coupon_records WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            if (!$existing) { json(['error' => '发放记录不存在']); break; }

            $db->exec("DELETE FROM coupon_records WHERE id=$id");
            json(['message' => '发放记录已删除']);
            break;

// ==================== 组织管理 API ====================
        case 'list_organizations':
            $res = $db->query("SELECT * FROM organizations ORDER BY sort_order, id");
            $orgs = [];
            while ($r = $res->fetch(PDO::FETCH_ASSOC)) $orgs[] = $r;
            // 构建树形结构
            $tree = [];
            $map = [];
            foreach ($orgs as &$org) {
                $org['children'] = [];
                $map[$org['id']] = &$org;
            }
            unset($org);
            foreach ($map as &$org) {
                if ($org['parent_id'] && isset($map[$org['parent_id']])) {
                    $map[$org['parent_id']]['children'][] = &$org;
                } else {
                    $tree[] = &$org;
                }
            }
            unset($org);
            json(['data' => ['tree' => $tree, 'flat' => $orgs]]);
            break;

        case 'add_organization':
            $post = json_decode(file_get_contents('php://input'), true);
            if (empty($post['name'])) { json(['error' => '名称不能为空']); break; }
            $type = $post['type'] ?? '部门';
            if (!in_array($type, ['部门', '校区'])) { json(['error' => '类型无效']); break; }
            $parentId = intval($post['parent_id'] ?? 0);
            $sortOrder = intval($post['sort_order'] ?? 0);
            // 校验：同一父节点下名称不重复（不限type，部门和校区可以同名共存于同一父节点下）
            $stmt = $db->query("SELECT id FROM organizations WHERE name=" . $db->quote($post['name']) . " AND parent_id=$parentId");
            $existing = $stmt->fetchColumn();
            if ($existing) { json(['error' => '同一父节点下名称已存在']); break; }
            $n = now();
            $db->exec("INSERT INTO organizations (name, type, parent_id, sort_order, created_at) VALUES (" . $db->quote($post['name']) . ", " . $db->quote($type) . ", $parentId, $sortOrder, '$n')");
            json(['message' => '新增成功', 'id' => $db->lastInsertId()]);
            break;

        case 'update_organization':
            $post = json_decode(file_get_contents('php://input'), true);
            $id = intval($post['id'] ?? 0);
            if ($id <= 0) { json(['error' => 'ID无效']); break; }
            $existing = $db->query("SELECT * FROM organizations WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            if (!$existing) { json(['error' => '组织不存在']); break; }
            $updates = [];
            if (isset($post['name']) && $post['name'] !== '') {
                $type = $post['type'] ?? $existing['type'];
                $parentId = isset($post['parent_id']) ? intval($post['parent_id']) : $existing['parent_id'];
                $stmt = $db->query("SELECT id FROM organizations WHERE name=" . $db->quote($post['name']) . " AND parent_id=$parentId AND id!=$id");
                $dup = $stmt->fetchColumn();
                if ($dup) { json(['error' => '同一父节点下名称已存在']); break; }
                $updates[] = "name=" . $db->quote($post['name']) . "";
            }
            if (isset($post['type']) && in_array($post['type'], ['部门', '校区'])) {
                $updates[] = "type=" . $db->quote($post['type']) . "";
            }
            if (isset($post['parent_id'])) {
                $pid = intval($post['parent_id']);
                if ($pid == $id) { json(['error' => '不能将自身设为上级']); break; }
                $updates[] = "parent_id=$pid";
            }
            if (isset($post['sort_order'])) {
                $updates[] = "sort_order=" . intval($post['sort_order']);
            }
            if (empty($updates)) { json(['message' => '无变更']); break; }
            $db->exec("UPDATE organizations SET " . implode(', ', $updates) . " WHERE id=$id");
            json(['message' => '更新成功']);
            break;

        case 'delete_organization':
            $post = json_decode(file_get_contents('php://input'), true);
            $id = intval($post['id'] ?? 0);
            if ($id <= 0) { json(['error' => 'ID无效']); break; }
            $stmt = $db->query("SELECT COUNT(*) FROM organizations WHERE parent_id=$id");
            $children = $stmt->fetchColumn();
            if ($children > 0) { json(['error' => '该节点下有子节点，请先删除子节点']); break; }
            $db->exec("DELETE FROM organizations WHERE id=$id");
            json(['message' => '删除成功']);
            break;

// ==================== 学科设置 API ====================
        case 'list_subjects':
            $res = $db->query("SELECT s.* FROM subjects s INNER JOIN (SELECT MIN(id) as mid FROM subjects GROUP BY name, parent_id) AS t ON s.id = t.mid ORDER BY s.sort_order, s.id");
            $subjects = [];
            while ($r = $res->fetch(PDO::FETCH_ASSOC)) $subjects[] = $r;
            // 构建树形结构
            $tree = [];
            $map = [];
            foreach ($subjects as &$sub) {
                $sub['children'] = [];
                $map[$sub['id']] = &$sub;
            }
            unset($sub);
            foreach ($map as &$sub) {
                if ($sub['parent_id'] && isset($map[$sub['parent_id']])) {
                    $map[$sub['parent_id']]['children'][] = &$sub;
                } else {
                    $tree[] = &$sub;
                }
            }
            unset($sub);
            json(['tree' => $tree, 'flat' => $subjects]);

        case 'add_subject':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $name = trim($input['name'] ?? '');
            if (!$name) json(['error' => '学科名称不能为空']);
            $parentId = intval($input['parent_id'] ?? 0);
            $sortOrder = intval($input['sort_order'] ?? 0);
            // 同一父级下 name 不可重复
            $stmt = $db->query("SELECT COUNT(*) FROM subjects WHERE name=" . $db->quote($name) . " AND parent_id=$parentId");
            $dup = $stmt->fetchColumn();
            if (intval($dup) > 0) json(['error' => '同一父级下学科名称已存在']);
            $db->exec("INSERT INTO subjects (name, parent_id, sort_order) VALUES (" . $db->quote($name) . ", $parentId, $sortOrder)");
            json(['id' => $db->lastInsertId(), 'message' => '学科添加成功']);

        case 'update_subject':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $sid = intval($input['id'] ?? 0);
            if ($sid <= 0) json(['error' => '学科ID无效']);
            $existing = $db->query("SELECT * FROM subjects WHERE id=$sid")->fetch(PDO::FETCH_ASSOC);
            if (!$existing) json(['error' => '学科不存在']);
            $name = trim($input['name'] ?? '');
            if ($name === '') $name = $existing['name'];
            $parentId = isset($input['parent_id']) ? intval($input['parent_id']) : $existing['parent_id'];
            // 同一父级下名称唯一（排除自身）
            $stmt = $db->query("SELECT COUNT(*) FROM subjects WHERE name=" . $db->quote($name) . " AND parent_id=$parentId AND id!=$sid");
            $dup = $stmt->fetchColumn();
            if (intval($dup) > 0) json(['error' => '同一父级下学科名称已存在']);
            $sortOrder = isset($input['sort_order']) ? intval($input['sort_order']) : $existing['sort_order'];
            $db->exec("UPDATE subjects SET name=" . $db->quote($name) . ", parent_id=$parentId, sort_order=$sortOrder WHERE id=$sid");
            json(['message' => '学科更新成功']);

        case 'delete_subject':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $sid = intval($input['id'] ?? 0);
            if ($sid <= 0) json(['error' => '学科ID无效']);
            $stmt = $db->query("SELECT COUNT(*) FROM subjects WHERE parent_id=$sid");
            $children = $stmt->fetchColumn();
            if ($children > 0) json(['error' => '该学科下有子学科，请先删除子学科']);
            $db->exec("DELETE FROM subjects WHERE id=$sid");
            json(['message' => '学科删除成功']);

        case 'batch_delete_subjects':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $ids = $input['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) json(['error' => '请提供要删除的学科ID列表']);
            $failed = [];
            $deleted = 0;
            foreach ($ids as $id) {
                $sid = intval($id);
                if ($sid <= 0) { $failed[] = "无效ID: $id"; continue; }
                $stmt = $db->query("SELECT COUNT(*) FROM subjects WHERE parent_id=$sid");
                $children = $stmt->fetchColumn();
                if ($children > 0) { $failed[] = "学科(ID=$sid)下有子学科，跳过"; continue; }
                $db->exec("DELETE FROM subjects WHERE id=$sid");
                $deleted++;
            }
            json(['message' => "成功删除 $deleted 个学科", 'deleted' => $deleted, 'failed' => $failed]);

        // ==================== 校区-学科-老师 关联 API ====================
        case 'get_campus_subjects':
            $campusId = intval($_GET['campus_id'] ?? 0);
            if ($campusId <= 0) json(['error' => '请提供校区ID']);
            // 从 courses 表找出该校区下所有一级学科（GROUP BY name 去重）
            $stmt = $db->prepare("SELECT MIN(s.id) AS id, s.name, s.parent_id
                FROM courses c
                JOIN subjects s ON s.name = c.subject_level1 AND s.parent_id = 0
                WHERE FIND_IN_SET(:cid, c.campus_permission)
                GROUP BY s.name, s.parent_id
                ORDER BY s.name");
            $stmt->bindValue(':cid', $campusId, PDO::PARAM_STR);
            $stmt->execute();
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json(['subjects' => $subjects]);

        case 'get_teachers':
            $res = $db->query("SELECT id, name, department, phone FROM employees WHERE is_teacher='是' AND status!='离职' ORDER BY department, name");
            $teachers = [];
            while ($r = $res->fetch(PDO::FETCH_ASSOC)) $teachers[] = $r;
            json(['teachers' => $teachers]);

        // ==================== 学员管理 API ====================
        case 'list_students':
            $page = max(1, intval($_GET['page'] ?? 1));
            $pageSize = min(50, max(1, intval($_GET['page_size'] ?? 15)));
            $keyword = trim($_GET['keyword'] ?? '');
            $campus = trim($_GET['campus'] ?? '');
            $subjectLevel1 = trim($_GET['subject_level1'] ?? '');
            $studentFilter = trim($_GET['student_filter'] ?? '');
            $offset = ($page - 1) * $pageSize;
            $conditions = [];
            $params = [];
            if ($keyword) {
                $conditions[] = "(s.name LIKE :kw OR s.phone LIKE :kw)";
                $params[':kw'] = "%$keyword%";
            }
            if ($campus) {
                $conditions[] = "EXISTS (SELECT 1 FROM orders o WHERE o.student_id = s.id AND o.campus = :campus AND o.is_voided = '否' AND (o.refund_status IS NULL OR o.refund_status != '已退费'))";
                $params[':campus'] = $campus;
            }
            // 在册学员筛选：student_type=常规 + 指定校区下剩余课时>0（有学科则限定学科）
            if ($studentFilter === 'active') {
                $conditions[] = "s.student_type = '常规'";
                if ($campus) {
                    $subj1Cond = $subjectLevel1 ? "AND c2.subject_level1 = :subj1_active" : "";
                    $conditions[] = "EXISTS (
                        SELECT 1 FROM orders o2
                        JOIN courses c2 ON o2.course_id = c2.id
                        LEFT JOIN (
                            SELECT order_id, COALESCE(SUM(deducted_lessons), 0) AS consumed
                            FROM attendance_records
                            WHERE status = '出勤'
                            GROUP BY order_id
                        ) ar ON ar.order_id = o2.id
                        WHERE o2.student_id = s.id
                            AND o2.is_voided = '否'
                            AND (o2.refund_status IS NULL OR o2.refund_status != '已退费')
                            $subj1Cond
                            AND o2.campus = :campus_active
                        GROUP BY o2.student_id
                        HAVING SUM(
                            CASE WHEN o2.refund_status = '退费申请中' THEN 0
                            ELSE o2.lesson_count - COALESCE(ar.consumed, 0)
                            END
                        ) > 0
                    )";
                    $params[':campus_active'] = $campus;
                    if ($subjectLevel1) {
                        $params[':subj1_active'] = $subjectLevel1;
                    }
                }
            }
            // 一级学科独立筛选（不配合学员筛选时）：筛选有该学科订单的学员
            if ($subjectLevel1 && $studentFilter !== 'active') {
                $conditions[] = "EXISTS (
                    SELECT 1 FROM orders o3
                    JOIN courses c3 ON o3.course_id = c3.id
                    WHERE o3.student_id = s.id
                        AND o3.is_voided = '否'
                        AND c3.subject_level1 = :subj1_only
                )";
                $params[':subj1_only'] = $subjectLevel1;
            }
            $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $stmt = $db->prepare("SELECT COUNT(*) FROM students s $where");
            foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
            $stmt->execute(); $total = $stmt->fetch(PDO::FETCH_NUM)[0];
            // 班级子查询：按校区过滤
            $campusClsJoin = $campus ? "AND c.campus = :campus_cls" : "";
            $campusClsParam = $campus ? [':campus_cls' => $campus] : [];
            // 校区展示列：按校区过滤
            $campusColJoin = $campus ? "AND o.campus = :campus_col" : "AND o.campus IS NOT NULL AND o.campus != ''";
            $campusColParam = $campus ? [':campus_col' => $campus] : [];
            $sql = "SELECT s.*, r.source AS resource_source, (SELECT COUNT(*) FROM orders o WHERE o.student_id=s.id) AS order_count, (SELECT GROUP_CONCAT(DISTINCT o.campus SEPARATOR ', ') FROM orders o WHERE o.student_id=s.id $campusColJoin) AS campus, cg.class_names FROM students s LEFT JOIN resources r ON s.resource_id = r.id LEFT JOIN (SELECT cs.student_id, GROUP_CONCAT(c.name SEPARATOR ', ') AS class_names FROM class_students cs JOIN classes c ON c.id = cs.class_id $campusClsJoin GROUP BY cs.student_id) cg ON cg.student_id = s.id $where ORDER BY s.id DESC LIMIT :limit OFFSET :offset";
            $allParams = array_merge($params, $campusClsParam, $campusColParam);
            $stmt = $db->prepare($sql);
            foreach ($allParams as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $rows = [];
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $row;

            // 计算各学科剩余课时
            if (!empty($rows)) {
                $studentIds = array_column($rows, 'id');
                $idsStr = implode(',', array_map('intval', $studentIds));
                // 批量查询每个学员在各一级学科下的剩余课时
                // 真实消耗 = attendance_records 中 status='出勤' 的 SUM(deducted_lessons)
                // 剩余 = lesson_count - 真实消耗；退费申请中视为 0；已退费/已作废不统计
                $campusSubFilter = $campus ? "AND o.campus = " . $db->quote($campus) : "";
                $subSql = "SELECT t.student_id,
                    GROUP_CONCAT(CONCAT(t.subject_level1, ':', t.remaining) SEPARATOR ', ') AS subject_remaining
                    FROM (
                        SELECT o.student_id, c.subject_level1,
                            SUM(
                                CASE WHEN o.refund_status = '退费申请中' THEN 0
                                ELSE o.lesson_count - COALESCE(ar_sum.consumed, 0)
                                END
                            ) AS remaining
                        FROM orders o
                        JOIN courses c ON o.course_id = c.id
                        LEFT JOIN (
                            SELECT order_id, SUM(deducted_lessons) AS consumed
                            FROM attendance_records
                            WHERE status = '出勤'
                            GROUP BY order_id
                        ) ar_sum ON ar_sum.order_id = o.id
                        WHERE o.student_id IN ($idsStr)
                            AND o.is_voided = '否'
                            AND (o.refund_status IS NULL OR o.refund_status != '已退费')
                            AND c.subject_level1 IS NOT NULL AND c.subject_level1 != ''
                            $campusSubFilter
                        GROUP BY o.student_id, c.subject_level1
                        HAVING remaining > 0
                    ) t
                    GROUP BY t.student_id
                    ORDER BY t.student_id";
                $subRes = $db->query($subSql);
                $subjectRemainingMap = [];
                while ($sr = $subRes->fetch(PDO::FETCH_ASSOC)) {
                    $subjectRemainingMap[$sr['student_id']] = $sr['subject_remaining'];
                }
                foreach ($rows as &$row) {
                    $sid = $row['id'];
                    $row['subject_remaining'] = $subjectRemainingMap[$sid] ?? '-';
                }
                unset($row);

                // 批量查询学员-校区-学科-授课老师关联
                // 若按校区筛选，则只展示该学员在当前校区下的授课老师
                $sstCampusFilter = '';
                if ($campus) {
                    $campusOrgId = $db->query("SELECT id FROM organizations WHERE name = " . $db->quote($campus) . " LIMIT 1")->fetchColumn();
                    if ($campusOrgId) {
                        $sstCampusFilter = "AND sst.campus_id = " . intval($campusOrgId);
                    }
                }
                $sstSql = "SELECT sst.student_id,
                    GROUP_CONCAT(CONCAT(org.name, ':', sub.name, '-', COALESCE(emp.name, '未设置')) SEPARATOR ', ') AS teacher_info
                    FROM student_subject_teacher sst
                    LEFT JOIN organizations org ON org.id = sst.campus_id
                    LEFT JOIN subjects sub ON sub.id = sst.subject_id
                    LEFT JOIN employees emp ON emp.id = sst.teacher_id
                    WHERE sst.student_id IN ($idsStr) $sstCampusFilter
                    GROUP BY sst.student_id
                    ORDER BY sst.student_id";
                $sstRes = $db->query($sstSql);
                $teacherInfoMap = [];
                while ($tr = $sstRes->fetch(PDO::FETCH_ASSOC)) {
                    $teacherInfoMap[$tr['student_id']] = $tr['teacher_info'];
                }
                foreach ($rows as &$row) {
                    $sid = $row['id'];
                    $row['teacher_info'] = $teacherInfoMap[$sid] ?? '-';
                }
                unset($row);
            }

            json(['data' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
            break;

        case 'get_student':
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) { json(['error' => '参数错误']); break; }
            $student = $db->query("SELECT * FROM students WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            if (!$student) { json(['error' => '学员不存在']); break; }
            $orders = [];
            $oRes = $db->query("SELECT o.*, c.name AS course_name FROM orders o LEFT JOIN courses c ON o.course_id=c.id WHERE o.student_id=$id ORDER BY o.id DESC");
            while ($o = $oRes->fetch(PDO::FETCH_ASSOC)) $orders[] = $o;
            // 汇总：累计报读课时、已消耗课时、累计报读金额、已消耗金额
            $summary = $db->query("SELECT
                COALESCE(SUM(lesson_count), 0) AS total_lessons,
                COALESCE(SUM(consumed_lessons), 0) AS consumed_lessons,
                COALESCE(SUM(actual_price), 0) AS total_amount,
                COALESCE(SUM(actual_price * consumed_lessons / NULLIF(lesson_count, 0)), 0) AS consumed_amount
                FROM orders WHERE student_id=$id")->fetch(PDO::FETCH_ASSOC);
            // 学员-校区-学科-授课老师 关联记录
            $sstRecords = [];
            $sstRes = $db->query("SELECT sst.id, sst.student_id, sst.campus_id, sst.subject_id, sst.teacher_id,
                org.name AS campus_name, sub.name AS subject_name, sub.parent_id AS subject_parent_id,
                p.name AS subject_parent_name,
                emp.name AS teacher_name, emp.department AS teacher_department
                FROM student_subject_teacher sst
                LEFT JOIN organizations org ON org.id = sst.campus_id
                LEFT JOIN subjects sub ON sub.id = sst.subject_id
                LEFT JOIN subjects p ON p.id = sub.parent_id
                LEFT JOIN employees emp ON emp.id = sst.teacher_id
                WHERE sst.student_id=$id ORDER BY org.name, p.name, sub.name");
            while ($r = $sstRes->fetch(PDO::FETCH_ASSOC)) $sstRecords[] = $r;
            json(['student' => $student, 'orders' => $orders, 'summary' => $summary, 'sst_records' => $sstRecords]);
            break;

        case 'add_student':
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $name = trim($input['name'] ?? '');
            $phone = trim($input['phone'] ?? '');
            if (!$name || !$phone) { json(['error' => '姓名和手机号不能为空']); break; }
            $stmt = $db->query("SELECT COUNT(*) FROM students WHERE phone=" . $db->quote($phone) . "");
            $exist = $stmt->fetchColumn();
            if ($exist > 0) { json(['error' => '手机号已存在']); break; }
            $resourceId = intval($input['resource_id'] ?? 0);
            $rid = $resourceId > 0 ? $resourceId : 'NULL';
            $n = now();
            $studentNo = generateStudentNo($db);
            $db->exec("INSERT INTO students (resource_id, name, phone, student_no, student_type, created_at) VALUES ($rid, " . $db->quote($name) . ", " . $db->quote($phone) . ", '$studentNo', '小课包', '$n')");
            $newId = $db->lastInsertId();
            // 保存校区-学科-授课老师关联
            $sstItems = $input['sst_items'] ?? [];
            if (is_array($sstItems)) {
                foreach ($sstItems as $item) {
                    $campusId = intval($item['campus_id'] ?? 0);
                    $subjectId = intval($item['subject_id'] ?? 0);
                    $teacherId = intval($item['teacher_id'] ?? 0);
                    if ($campusId > 0 && $subjectId > 0) {
                        $db->exec("INSERT INTO student_subject_teacher (student_id, campus_id, subject_id, teacher_id, created_at) VALUES ($newId, $campusId, $subjectId, $teacherId, '$n')");
                    }
                }
            }
            json(['message' => '新增学员成功', 'id' => $newId]);
            break;

        case 'update_student':
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) { json(['error' => '参数错误']); break; }
            $name = trim($input['name'] ?? '');
            $phone = trim($input['phone'] ?? '');
            if (!$name || !$phone) { json(['error' => '姓名和手机号不能为空']); break; }
            $stmt = $db->query("SELECT COUNT(*) FROM students WHERE phone=" . $db->quote($phone) . " AND id!=$id");
            $exist = $stmt->fetchColumn();
            if ($exist > 0) { json(['error' => '手机号已被其他学员使用']); break; }
            $db->exec("UPDATE students SET name=" . $db->quote($name) . ", phone=" . $db->quote($phone) . " WHERE id=$id");
            // 重新计算学员类型：只要存在非小课包订单即升级为常规（不可逆）
            $st = $db->query("SELECT student_type FROM students WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            $currentType = $st['student_type'] ?? '小课包';
            if ($currentType !== '常规') {
                $hasNonXKB = $db->query("SELECT COUNT(*) FROM orders WHERE student_id=$id AND is_voided='否' AND order_type != '小课包' AND order_type != ''")->fetchColumn();
                if ($hasNonXKB > 0) {
                    $db->exec("UPDATE students SET student_type='常规' WHERE id=$id");
                }
            }
            // 保存校区-学科-授课老师关联
            $sstItems = $input['sst_items'] ?? [];
            if (is_array($sstItems)) {
                // 先删除该学员所有旧关联
                $db->exec("DELETE FROM student_subject_teacher WHERE student_id=$id");
                $n = now();
                foreach ($sstItems as $item) {
                    $campusId = intval($item['campus_id'] ?? 0);
                    $subjectId = intval($item['subject_id'] ?? 0);
                    $teacherId = intval($item['teacher_id'] ?? 0);
                    if ($campusId > 0 && $subjectId > 0) {
                        $db->exec("INSERT INTO student_subject_teacher (student_id, campus_id, subject_id, teacher_id, created_at) VALUES ($id, $campusId, $subjectId, $teacherId, '$n')");
                    }
                }
            }
            json(['message' => '更新成功']);
            break;

        case 'delete_student':
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) { json(['error' => '参数错误']); break; }
            $db->exec("DELETE FROM student_subject_teacher WHERE student_id=$id");
            $db->exec("DELETE FROM orders WHERE student_id=$id");
            $db->exec("DELETE FROM students WHERE id=$id");
            json(['message' => '删除成功']);
            break;

        case 'enroll_course':
            $studentId = intval($input['student_id'] ?? 0);
            $courseId = intval($input['course_id'] ?? 0);
            if ($studentId <= 0 || $courseId <= 0) { json(['error' => '请选择学员和课程']); break; }
            $planName = trim($input['plan_name'] ?? '');
            $itemName = trim($input['item_name'] ?? '');
            $lessonCount = intval($input['lesson_count'] ?? 0);
            $actualPrice = floatval($input['actual_price'] ?? 0);
            if (!$planName || !$itemName) { json(['error' => '请选择价格方案和报价单']); break; }
            $campusId = intval($input['campus_id'] ?? 0);
            $campusName = '';
            if ($campusId > 0) {
                $campusRow = $db->query("SELECT name FROM organizations WHERE id=$campusId AND type='校区'")->fetch(PDO::FETCH_ASSOC);
                $campusName = $campusRow['name'] ?? '';
            }
            // 余额支付
            $useBalance = !empty($input['use_balance']);
            $balanceAmount = $useBalance ? floatval($input['balance_amount'] ?? 0) : 0;
            if ($balanceAmount < 0) $balanceAmount = 0;
            if ($balanceAmount > $actualPrice) { json(['error' => '余额支付金额不能超过订单总额']); break; }
            $n = now();
            $orderNo = generateOrderNo($db);
            $db->beginTransaction();
            try {
                $cashAmount = 0.0;
                $mtAmount = 0.0;
                if ($useBalance && $balanceAmount > 0) {
                    // 查询并锁定账户
                    $acct = $db->prepare("SELECT balance FROM student_accounts WHERE student_id = :sid FOR UPDATE");
                    $acct->bindValue(':sid', $studentId, PDO::PARAM_INT);
                    $acct->execute();
                    $acct = $acct->fetch(PDO::FETCH_ASSOC);
                    $currentBalance = $acct ? floatval($acct['balance']) : 0.00;
                    if ($currentBalance < $balanceAmount) {
                        $db->rollBack();
                        json(['error' => '账户余额不足（当前余额：' . $currentBalance . '，需要：' . $balanceAmount . '）']); break;
                    }
                    $newBalance = round($currentBalance - $balanceAmount, 2);
                    $upd = $db->prepare("INSERT INTO student_accounts (student_id, balance, total_deposit, total_consume, total_refund) VALUES (:sid, 0, 0, 0, 0) ON DUPLICATE KEY UPDATE balance = :bal, total_consume = total_consume + :tc");
                    $upd->bindValue(':sid', $studentId, PDO::PARAM_INT);
                    $upd->bindValue(':bal', $newBalance);
                    $upd->bindValue(':tc', $balanceAmount);
                    $upd->execute();
                    $cashAmount = $balanceAmount;
                }
                $stmt = $db->prepare("INSERT INTO orders (student_id, course_id, plan_name, item_name, lesson_count, actual_price, cash_amount, meituan_amount, order_no, created_at, campus, pay_status, is_voided) VALUES (:sid, :cid, :pn, :inm, :lc, :ap, :ca, :ma, :ono, :ct, :campus, :ps, :iv)");
                $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
                $stmt->bindValue(':cid', $courseId, PDO::PARAM_INT);
                $stmt->bindValue(':pn', $planName, PDO::PARAM_STR);
                $stmt->bindValue(':inm', $itemName, PDO::PARAM_STR);
                $stmt->bindValue(':lc', $lessonCount, PDO::PARAM_INT);
                $stmt->bindValue(':ap', $actualPrice, PDO::PARAM_STR);
                $stmt->bindValue(':ca', $cashAmount, PDO::PARAM_STR);
                $stmt->bindValue(':ma', $mtAmount, PDO::PARAM_STR);
                $stmt->bindValue(':ono', $orderNo, PDO::PARAM_STR);
                $stmt->bindValue(':ct', $n, PDO::PARAM_STR);
                $stmt->bindValue(':campus', $campusName, PDO::PARAM_STR);
                $stmt->bindValue(':ps', '待支付', PDO::PARAM_STR);
                $stmt->bindValue(':iv', '否', PDO::PARAM_STR);
                $stmt->execute();
                $newOrderId = $db->lastInsertId();
                // 写入账户流水
                if ($useBalance && $balanceAmount > 0) {
                    $stmt2 = $db->prepare("INSERT INTO account_transactions (student_id, type, amount, balance_after, ref_type, ref_id, campus, note) VALUES (:sid, 'consume', :amt, :ba, 'order', :rid, :campus, :note)");
                    $stmt2->bindValue(':sid', $studentId, PDO::PARAM_INT);
                    $stmt2->bindValue(':amt', $balanceAmount);
                    $stmt2->bindValue(':ba', $newBalance);
                    $stmt2->bindValue(':rid', $newOrderId, PDO::PARAM_INT);
                    $stmt2->bindValue(':campus', $campusName, PDO::PARAM_STR);
                    $stmt2->bindValue(':note', '报名消费: ' . $planName . ' - ' . $itemName, PDO::PARAM_STR);
                    $stmt2->execute();
                }
                $db->commit();
                json(['message' => '报名成功', 'order_id' => $newOrderId, 'order_no' => $orderNo]);
            } catch (Exception $e) {
                $db->rollBack();
                json(['error' => '报名失败: ' . $e->getMessage()]);
            }
            break;
        case 'enroll_from_resource':
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $resourceId = intval($input['resource_id'] ?? 0);
            $courseId = intval($input['course_id'] ?? 0);
            if ($resourceId <= 0 || $courseId <= 0) { json(['error' => '请选择资源和课程']); break; }
            $planName = trim($input['plan_name'] ?? '');
            $itemName = trim($input['item_name'] ?? '');
            $lessonCount = intval($input['lesson_count'] ?? 0);
            $actualPrice = floatval($input['actual_price'] ?? 0);
            if (!$planName || !$itemName) { json(['error' => '请选择价格方案和报价单']); break; }
            $res = $db->query("SELECT name, phone, source, follow_status FROM resources WHERE id=$resourceId")->fetch(PDO::FETCH_ASSOC);
            if (!$res) { json(['error' => '资源不存在']); break; }
            $name = $res['name'];
            $phone = $res['phone'];
            if (!$phone) { json(['error' => '该资源没有手机号']); break; }
            $stmt = $db->query("SELECT id FROM students WHERE phone=" . $db->quote($phone) . "", true);
            $existing = $stmt->fetchColumn();
            if ($existing) {
                $studentId = $existing;
            } else {
                $source = $db->quote($res['source'] ?? '');
                $followStatus = $db->quote($res['follow_status'] ?? '');
                $ename = $db->quote($name);
                $ephone = $db->quote($phone);
                $studentNo = generateStudentNo($db);
                $n = now();
                $db->exec("INSERT INTO students (resource_id, name, phone, source, follow_status, student_no, created_at) VALUES ($resourceId, $ename, $ephone, $source, $followStatus, '$studentNo', '$n')");
                $studentId = $db->lastInsertId();
            }
            $campusId = intval($input['campus_id'] ?? 0);
            $campusName = '';
            if ($campusId > 0) {
                $campusRow = $db->query("SELECT name FROM organizations WHERE id=$campusId AND type='校区'")->fetch(PDO::FETCH_ASSOC);
                $campusName = $campusRow['name'] ?? '';
            }
            $n = now();
            $orderNo = generateOrderNo($db);
            $stmt = $db->prepare("INSERT INTO orders (student_id, course_id, plan_name, item_name, lesson_count, actual_price, order_no, created_at, campus, pay_status, is_voided) VALUES (:sid, :cid, :pn, :inm, :lc, :ap, :ono, :ct, :campus, :ps, :iv)");
            $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
            $stmt->bindValue(':cid', $courseId, PDO::PARAM_INT);
            $stmt->bindValue(':pn', $planName, PDO::PARAM_STR);
            $stmt->bindValue(':inm', $itemName, PDO::PARAM_STR);
            $stmt->bindValue(':lc', $lessonCount, PDO::PARAM_INT);
            $stmt->bindValue(':ap', $actualPrice, PDO::PARAM_STR);
            $stmt->bindValue(':ono', $orderNo, PDO::PARAM_STR);
            $stmt->bindValue(':ct', $n, PDO::PARAM_STR);
            $stmt->bindValue(':campus', $campusName, PDO::PARAM_STR);
                $stmt->bindValue(':ps', '待支付', PDO::PARAM_STR);
                $stmt->bindValue(':iv', '否', PDO::PARAM_STR);
            $stmt->execute();
            json(['message' => '报名成功，学员ID：' . $studentId, 'id' => $db->lastInsertId(), 'student_id' => $studentId]);
            break;

        case 'create_student_from_resource':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $resourceId = intval($input['resource_id'] ?? 0);
            if ($resourceId <= 0) json(['error' => '资源ID无效']);
            $res = $db->query("SELECT name, phone, source, follow_status FROM resources WHERE id=$resourceId")->fetch(PDO::FETCH_ASSOC);
            if (!$res) json(['error' => '资源不存在']);
            $name = $res['name'];
            $phone = $res['phone'];
            if (!$phone) json(['error' => '该资源没有手机号，无法创建学员记录']);
            $stmt = $db->query("SELECT id FROM students WHERE phone=" . $db->quote($phone) . "", true);
            $existing = $stmt->fetchColumn();
            if ($existing) {
                $studentId = $existing;
            } else {
                $source = $db->quote($res['source'] ?? '');
                $followStatus = $db->quote($res['follow_status'] ?? '');
                $ename = $db->quote($name);
                $ephone = $db->quote($phone);
                $studentNo = generateStudentNo($db);
                $n = now();
                $db->exec("INSERT INTO students (resource_id, name, phone, source, follow_status, student_no, created_at) VALUES ($resourceId, $ename, $ephone, $source, $followStatus, '$studentNo', '$n')");
                $studentId = $db->lastInsertId();
            }
            json(['student_id' => $studentId, 'message' => '学员记录已就绪']);
            break;

        // ==================== 学员课程 API ====================
        case 'get_student_courses':
            $sid = intval($_GET['student_id'] ?? 0);
            if ($sid <= 0) { json(['error' => '参数错误']); break; }
            $rows = [];
            // 收集所有退费申请中的订单（已退费/已驳回的不算），退费期间课时视为0
            $pendingRefundIds = [];
            $refStmt = $db->query("SELECT DISTINCT order_id FROM refund_records WHERE status NOT IN ('已退费', '审批驳回')");
            while ($refR = $refStmt->fetch(PDO::FETCH_ASSOC)) $pendingRefundIds[$refR['order_id']] = true;
            $stmt = $db->query("SELECT DISTINCT c.id, c.name, c.subject_level1, c.subject_level2, o.plan_name, o.item_name, o.lesson_count, o.actual_price, o.status, o.id AS order_id, o.order_no, o.created_at, o.consumed_lessons, o.campus, o.is_voided, o.refund_status FROM orders o JOIN courses c ON o.course_id = c.id WHERE o.student_id = $sid AND o.is_voided = '否' ORDER BY o.id DESC");
            $orderRows = [];
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $orderRows[] = $r;
            // 批量查询考勤记录获取真实消耗课时
            $attMap = [];
            $oids = array_column($orderRows, 'order_id');
            if (!empty($oids)) {
                $idsStr = implode(',', $oids);
                $aStmt = $db->query("SELECT order_id, COALESCE(SUM(deducted_lessons), 0) AS real_consumed FROM attendance_records WHERE order_id IN ($idsStr) AND status='出勤' GROUP BY order_id");
                while ($a = $aStmt->fetch(PDO::FETCH_ASSOC)) $attMap[$a['order_id']] = intval($a['real_consumed']);
            }
            foreach ($orderRows as $r) {
                $lc = intval($r['lesson_count'] ?? 0);
                $ap = floatval($r['actual_price'] ?? 0);
                $realConsumed = $attMap[$r['order_id']] ?? 0;
                $refundStatus = $r['refund_status'] ?? '正常';
                if ($refundStatus === '已退费') {
                    // 已退费：展示真实消耗和退费课时
                    $refundedLessons = max(0, $lc - $realConsumed);
                    $r['consumed_lessons'] = $realConsumed;
                    $r['refunded_lessons'] = $refundedLessons;
                    $r['consumed_amount'] = $lc > 0 ? round(($ap / $lc) * $realConsumed, 2) : 0;
                    $r['remaining_lessons'] = 0;
                    $r['remaining_amount'] = 0;
                } elseif (isset($pendingRefundIds[$r['order_id']])) {
                    // 退费申请中：课时冻结，剩余=0
                    $r['consumed_lessons'] = $realConsumed;
                    $r['refunded_lessons'] = 0;
                    $r['consumed_amount'] = $lc > 0 ? round(($ap / $lc) * $realConsumed, 2) : 0;
                    $r['remaining_lessons'] = 0;
                    $r['remaining_amount'] = 0;
                } elseif ($lc > 0) {
                    // 正常订单：以考勤记录为准
                    $r['consumed_lessons'] = $realConsumed;
                    $r['refunded_lessons'] = 0;
                    $unitPrice = $ap / $lc;
                    $r['consumed_amount'] = round($unitPrice * $realConsumed, 2);
                    $rl = $lc - $realConsumed;
                    $r['remaining_lessons'] = $rl > 0 ? $rl : 0;
                    $r['remaining_amount'] = round($unitPrice * $r['remaining_lessons'], 2);
                } else {
                    $r['consumed_lessons'] = $realConsumed;
                    $r['refunded_lessons'] = 0;
                    $r['consumed_amount'] = 0;
                    $r['remaining_lessons'] = 0;
                    $r['remaining_amount'] = 0;
                }
                $rows[] = $r;
            }
            json(['data' => $rows]);
            break;

        // ==================== 上课记录 API ====================
        case 'list_attendance':
            $sid = intval($_GET['student_id'] ?? 0);
            if ($sid <= 0) { json(['error' => '参数错误']); break; }
            $rows = [];
            $stmt = $db->query("SELECT a.*, c.name AS course_name FROM attendance_records a LEFT JOIN courses c ON a.course_id = c.id WHERE a.student_id = $sid ORDER BY a.lesson_date DESC, a.id DESC");
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $r;
            json(['data' => $rows]);
            break;

        case 'add_attendance':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $sid = intval($input['student_id'] ?? 0);
            $cid = intval($input['course_id'] ?? 0);
            $lessonDate = trim($input['lesson_date'] ?? '');
            $status = trim($input['status'] ?? '出勤');
            $className = trim($input['class_name'] ?? '');
            $campus = trim($input['campus'] ?? '');
            $teacher = trim($input['teacher'] ?? '');
            $subjectLevel1 = trim($input['subject_level1'] ?? '');
            $subjectLevel2 = trim($input['subject_level2'] ?? '');
            $classTime = trim($input['class_time'] ?? '');
            $consumedAmount = floatval($input['consumed_amount'] ?? 0);
            $orderId = intval($input['order_id'] ?? 0);
            if ($sid <= 0 || $cid <= 0) { json(['error' => '学员和课程不能为空']); break; }
            if (!in_array($status, ['出勤', '请假', '缺勤'])) { json(['error' => '状态无效']); break; }
            $n = now();
            $stmt = $db->prepare("INSERT INTO attendance_records (student_id, course_id, order_id, subject_level1, subject_level2, class_time, lesson_date, attended_at, status, class_name, campus, teacher, consumed_amount, created_at) VALUES (:sid, :cid, :oid, :sl1, :sl2, :ct, :dt, :aa, :st, :cn, :cp, :t, :ca2, :ct2)");
            $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
            $stmt->bindValue(':cid', $cid, PDO::PARAM_INT);
            $stmt->bindValue(':oid', $orderId, PDO::PARAM_INT);
            $stmt->bindValue(':sl1', $subjectLevel1, PDO::PARAM_STR);
            $stmt->bindValue(':sl2', $subjectLevel2, PDO::PARAM_STR);
            $stmt->bindValue(':ct', $classTime, PDO::PARAM_STR);
            $stmt->bindValue(':dt', $lessonDate, PDO::PARAM_STR);
            $stmt->bindValue(':aa', $n, PDO::PARAM_STR);
            $stmt->bindValue(':st', $status, PDO::PARAM_STR);
            $stmt->bindValue(':cn', $className, PDO::PARAM_STR);
            $stmt->bindValue(':cp', $campus, PDO::PARAM_STR);
            $stmt->bindValue(':t', $teacher, PDO::PARAM_STR);
            $stmt->bindValue(':ca2', round($consumedAmount, 2), PDO::PARAM_STR);
            $stmt->bindValue(':ct2', $n, PDO::PARAM_STR);
            $stmt->execute();
            $attId = $db->lastInsertId();
            // 缺勤时同步写入缺勤记录表
            if ($status === '缺勤') {
                $studentName = '';
                $phone = '';
                $sr = $db->query("SELECT name, phone FROM students WHERE id=$sid")->fetch(PDO::FETCH_ASSOC);
                if ($sr) { $studentName = $sr['name']; $phone = $sr['phone']; }
                $arStmt = $db->prepare("INSERT INTO absence_records (student_id, course_id, class_name, campus, teacher, subject_level1, subject_level2, class_time, lesson_date, student_name, phone, attendance_id, created_at) VALUES (:sid, :cid, :cn, :cp, :t, :sl1, :sl2, :ct, :dt, :sn, :ph, :aid, :ca)");
                $arStmt->bindValue(':sid', $sid, PDO::PARAM_INT);
                $arStmt->bindValue(':cid', $cid, PDO::PARAM_INT);
                $arStmt->bindValue(':cn', $className, PDO::PARAM_STR);
                $arStmt->bindValue(':cp', $campus, PDO::PARAM_STR);
                $arStmt->bindValue(':t', $teacher, PDO::PARAM_STR);
                $arStmt->bindValue(':sl1', $subjectLevel1, PDO::PARAM_STR);
                $arStmt->bindValue(':sl2', $subjectLevel2, PDO::PARAM_STR);
                $arStmt->bindValue(':ct', $classTime, PDO::PARAM_STR);
                $arStmt->bindValue(':dt', $lessonDate, PDO::PARAM_STR);
                $arStmt->bindValue(':sn', $studentName, PDO::PARAM_STR);
                $arStmt->bindValue(':ph', $phone, PDO::PARAM_STR);
                $arStmt->bindValue(':aid', $attId, PDO::PARAM_INT);
                $arStmt->bindValue(':ca', $n, PDO::PARAM_STR);
                $arStmt->execute();
            }
            json(['id' => $attId, 'message' => '考勤记录添加成功']);
            break;

        case 'update_attendance':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) { json(['error' => '参数错误']); break; }
            $existing = $db->query("SELECT * FROM attendance_records WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            if (!$existing) { json(['error' => '记录不存在']); break; }
            $fields = [];
            if (isset($input['course_id'])) $fields[] = "course_id=" . intval($input['course_id']);
            if (isset($input['lesson_date'])) $fields[] = "lesson_date='" . $db->quote(trim($input['lesson_date'])) . "'";
            $oldStatus = $existing['status'] ?? '';
            $newStatus = $oldStatus;
            if (isset($input['status'])) {
                $st = trim($input['status']);
                if (!in_array($st, ['出勤', '请假', '缺勤'])) { json(['error' => '状态无效']); break; }
                $fields[] = "status=" . $db->quote($st) . "";
                $newStatus = $st;
            }
            if (isset($input['class_name'])) $fields[] = "class_name=" . $db->quote(trim($input['class_name']));
            if (isset($input['campus'])) $fields[] = "campus=" . $db->quote(trim($input['campus']));
            if (isset($input['teacher'])) $fields[] = "teacher='" . $db->quote(trim($input['teacher'])) . "'";
            if (isset($input['subject_level1'])) $fields[] = "subject_level1='" . $db->quote(trim($input['subject_level1'])) . "'";
            if (isset($input['subject_level2'])) $fields[] = "subject_level2='" . $db->quote(trim($input['subject_level2'])) . "'";
            if (isset($input['class_time'])) $fields[] = "class_time='" . $db->quote(trim($input['class_time'])) . "'";
            if (isset($input['consumed_amount'])) $fields[] = "consumed_amount=" . round(floatval($input['consumed_amount']), 2);
            if (isset($input['order_id'])) $fields[] = "order_id=" . intval($input['order_id']);
            if (empty($fields)) { json(['message' => '无变更']); break; }
            $db->exec("UPDATE attendance_records SET " . implode(', ', $fields) . " WHERE id=$id");
            // 缺勤状态切换：从缺勤→非缺勤时删除缺勤记录；从非缺勤→缺勤时新增缺勤记录
            if ($newStatus !== $oldStatus) {
                if ($oldStatus === '缺勤' && $newStatus !== '缺勤') {
                    $db->exec("DELETE FROM absence_records WHERE attendance_id=$id");
                } elseif ($oldStatus !== '缺勤' && $newStatus === '缺勤') {
                    $sid = intval($existing['student_id']);
                    $cid = intval($existing['course_id'] ?? 0);
                    $sn = ''; $ph = '';
                    $sr = $db->query("SELECT name, phone FROM students WHERE id=$sid")->fetch(PDO::FETCH_ASSOC);
                    if ($sr) { $sn = $sr['name']; $ph = $sr['phone']; }
                    $arStmt = $db->prepare("INSERT INTO absence_records (student_id, course_id, class_name, campus, teacher, subject_level1, subject_level2, class_time, lesson_date, student_name, phone, attendance_id, created_at) VALUES (:sid, :cid, :cn, :cp, :t, :sl1, :sl2, :ct, :dt, :sn, :ph, :aid, :ca)");
                    $arStmt->bindValue(':sid', $sid, PDO::PARAM_INT);
                    $arStmt->bindValue(':cid', $cid, PDO::PARAM_INT);
                    $arStmt->bindValue(':cn', $existing['class_name'] ?? '', PDO::PARAM_STR);
                    $arStmt->bindValue(':cp', $existing['campus'] ?? '', PDO::PARAM_STR);
                    $arStmt->bindValue(':t', $existing['teacher'] ?? '', PDO::PARAM_STR);
                    $arStmt->bindValue(':sl1', $existing['subject_level1'] ?? '', PDO::PARAM_STR);
                    $arStmt->bindValue(':sl2', $existing['subject_level2'] ?? '', PDO::PARAM_STR);
                    $arStmt->bindValue(':ct', $existing['class_time'] ?? '', PDO::PARAM_STR);
                    $arStmt->bindValue(':dt', $existing['lesson_date'] ?? '', PDO::PARAM_STR);
                    $arStmt->bindValue(':sn', $sn, PDO::PARAM_STR);
                    $arStmt->bindValue(':ph', $ph, PDO::PARAM_STR);
                    $arStmt->bindValue(':aid', $id, PDO::PARAM_INT);
                    $arStmt->bindValue(':ca', now(), PDO::PARAM_STR);
                    $arStmt->execute();
                }
            }
            json(['message' => '考勤记录更新成功']);
            break;

        case 'delete_attendance':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) { json(['error' => '参数错误']); break; }
            $db->exec("DELETE FROM absence_records WHERE attendance_id=$id");
            $db->exec("DELETE FROM attendance_records WHERE id=$id");
            json(['message' => '考勤记录删除成功']);
            break;

        case 'list_absence_records':
            $page = max(1, intval($_GET['page'] ?? 1));
            $pageSize = min(50, max(1, intval($_GET['page_size'] ?? 20)));
            $offset = ($page - 1) * $pageSize;
            $dateFrom = trim($_GET['date_from'] ?? '');
            $dateTo = trim($_GET['date_to'] ?? '');
            $className = trim($_GET['class_name'] ?? '');
            $where = [];
            $params = [];
            if ($dateFrom) { $where[] = "a.lesson_date >= :df"; $params[':df'] = $dateFrom; }
            if ($dateTo) { $where[] = "a.lesson_date <= :dt"; $params[':dt'] = $dateTo; }
            if ($className) { $where[] = "a.class_name LIKE :cn"; $params[':cn'] = "%$className%"; }
            $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $countSql = "SELECT COUNT(*) FROM absence_records a $whereStr";
            $stmt = $db->prepare($countSql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
            $stmt->execute(); $total = $stmt->fetch(PDO::FETCH_NUM)[0];
            $sql = "SELECT a.*, c.name AS course_name, s.student_no FROM absence_records a LEFT JOIN courses c ON a.course_id = c.id LEFT JOIN students s ON a.student_id = s.id $whereStr ORDER BY a.lesson_date DESC, a.id DESC LIMIT :limit OFFSET :offset";
            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json(['data' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
            break;

        // ==================== 交易订单 API ====================
        case 'list_orders':
            $page = max(1, intval($_GET['page'] ?? 1));
            $pageSize = min(50, max(1, intval($_GET['page_size'] ?? 15)));
            $keyword = trim($_GET['keyword'] ?? '');
            $payStatus = trim($_GET['pay_status'] ?? '');
            $isVoided = trim($_GET['is_voided'] ?? '');
            $campus = trim($_GET['campus'] ?? '');
            $payDateStart = trim($_GET['pay_date_start'] ?? '');
            $payDateEnd = trim($_GET['pay_date_end'] ?? '');
            $offset = ($page - 1) * $pageSize;
            $where = '';
            $params = [];
            $conds = [];
            if ($keyword) {
                $conds[] = "(s.name LIKE :kw OR c.name LIKE :kw)";
                $params[':kw'] = "%$keyword%";
            }
            if ($payStatus) {
                $conds[] = "o.pay_status = :pst";
                $params[':pst'] = $payStatus;
            }
            if ($isVoided) {
                $conds[] = "o.is_voided = :ivd";
                $params[':ivd'] = $isVoided;
            }
            if ($campus) {
                $campusList = array_values(array_filter(array_map('trim', explode(',', $campus))));
                if (count($campusList) === 1) {
                    $conds[] = "o.campus = :cps";
                    $params[':cps'] = $campusList[0];
                } elseif (count($campusList) > 1) {
                    $phs = [];
                    foreach ($campusList as $i => $cp) {
                        $ph = ":cps$i";
                        $phs[] = $ph;
                        $params[$ph] = $cp;
                    }
                    $conds[] = "o.campus IN (" . implode(',', $phs) . ")";
                }
            }
            if ($payDateStart) {
                $conds[] = "o.paid_at >= :pds";
                $params[':pds'] = $payDateStart . ' 00:00:00';
            }
            if ($payDateEnd) {
                $conds[] = "o.paid_at <= :pde";
                $params[':pde'] = $payDateEnd . ' 23:59:59';
            }
            if (!empty($conds)) {
                $where = "WHERE " . implode(' AND ', $conds);
            }
            $countSql = "SELECT COUNT(*) FROM orders o LEFT JOIN students s ON o.student_id=s.id LEFT JOIN courses c ON o.course_id=c.id $where";
            $stmt = $db->prepare($countSql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
            $stmt->execute(); $total = $stmt->fetch(PDO::FETCH_NUM)[0];
            $sql = "SELECT o.id, o.student_id, o.course_id, o.plan_name, o.item_name, o.lesson_count, o.actual_price, o.status, o.created_at, o.paid_at, o.order_no, o.parent_order_no, o.cash_amount, o.meituan_amount, o.account_amount, o.paid_amount, o.order_type, o.campus, o.pay_status, o.is_voided, o.subject_level1, o.subject_level2, s.name AS student_name, s.student_no, c.name AS course_name FROM orders o LEFT JOIN students s ON o.student_id=s.id LEFT JOIN courses c ON o.course_id=c.id $where ORDER BY o.id DESC LIMIT :limit OFFSET :offset";
            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $rows = [];
$stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $row;
            // 支付方式汇总
            $summarySql = "SELECT SUM(COALESCE(o.cash_amount,0)) AS cash_total, SUM(COALESCE(o.meituan_amount,0)) AS meituan_total, SUM(COALESCE(o.account_amount,0)) AS account_total FROM orders o LEFT JOIN students s ON o.student_id=s.id LEFT JOIN courses c ON o.course_id=c.id $where";
            $sumStmt = $db->prepare($summarySql);
            foreach ($params as $k => $v) $sumStmt->bindValue($k, $v, PDO::PARAM_STR);
$sumStmt->execute();
            $paymentSummary = $sumStmt->fetch(PDO::FETCH_ASSOC) ?: ['cash_total' => 0, 'meituan_total' => 0, 'account_total' => 0];
            json(['data' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize, 'payment_summary' => $paymentSummary]);
            break;

        case 'void_order':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $oid = intval($input['order_id'] ?? 0);
            if ($oid <= 0) { json(['success' => false, 'message' => '订单ID无效']); break; }
            $order = $db->query("SELECT student_id, course_id, lesson_count, consumed_lessons, is_voided FROM orders WHERE id = $oid")->fetch();
            if (!$order) { json(['success' => false, 'message' => '订单不存在']); break; }
            if ($order['is_voided'] === '是') { json(['success' => false, 'message' => '该订单已作废']); break; }
            $lc = intval($order['lesson_count']);
            $cl = intval($order['consumed_lessons']);
            $remaining = $lc - $cl;
            if ($lc != $remaining) { json(['success' => false, 'message' => '该订单已有课时消耗，无法作废']); break; }
            $db->exec("UPDATE orders SET is_voided = '是' WHERE id = $oid");
            json(['success' => true]);
            break;

        case 'list_parent_orders':
            $page = max(1, intval($_GET['page'] ?? 1));
            $pageSize = max(1, min(100, intval($_GET['page_size'] ?? 20)));
            $keyword = trim($_GET['keyword'] ?? '');
            $offset = ($page - 1) * $pageSize;
            $where = '';
            $params = [];
            if ($keyword) {
                $where = "WHERE (po.student_name LIKE :kw OR po.course_name LIKE :kw OR po.parent_order_no LIKE :kw OR po.student_no LIKE :kw)";
                $params[':kw'] = "%$keyword%";
            }
            $stmt = $db->prepare("SELECT COUNT(*) FROM parent_orders po $where");
            foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
            $stmt->execute(); $total = $stmt->fetch(PDO::FETCH_NUM)[0];
            $stmt = $db->prepare("SELECT * FROM parent_orders po $where ORDER BY po.id DESC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
$stmt->execute();
            $rows = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $row;
            json(['data' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
            break;

// ==================== 退费记录 API ====================
        // 提交退费申请
        case 'submit_refund':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $refundType = trim($input['refund_type'] ?? 'course'); // 'course' | 'account'

            if ($refundType === 'course') {
                // === 课程退费（原有逻辑，增加 project/content 字段） ===
                $orderId = intval($input['order_id'] ?? 0);
                if ($orderId <= 0) { json(['error' => '订单ID无效']); break; }
                // 查询订单信息
                $order = $db->query("SELECT o.*, c.name AS course_name, c.subject_level1 FROM orders o LEFT JOIN courses c ON o.course_id=c.id WHERE o.id=$orderId")->fetch(PDO::FETCH_ASSOC);
                if (!$order) { json(['error' => '订单不存在']); break; }
                if (($order['refund_status'] ?? '正常') !== '正常') { json(['error' => '该订单已申请退费，不能重复申请']); break; }
                $lessonCount = intval($order['lesson_count'] ?? 0);
                $consumedLessons = intval($order['consumed_lessons'] ?? 0);
                if ($consumedLessons >= $lessonCount) { json(['error' => '该课程已全部消耗，无法退费']); break; }
                $actualPrice = floatval($order['actual_price'] ?? 0);
                // 计算剩余可退课时和金额
                $remainingLessons = $lessonCount - $consumedLessons;
                $remainingAmount = 0;
                if ($lessonCount > 0) {
                    $remainingAmount = round($actualPrice * $remainingLessons / $lessonCount, 2);
                }
                $customDeduction = floatval($input['custom_deduction'] ?? 0);
                if ($customDeduction < 0) { json(['error' => '扣减金额不能为负']); break; }
                $actualRefund = round($remainingAmount - $customDeduction, 2);
                if ($actualRefund < 0) $actualRefund = 0;
                $bankName = trim($input['bank_name'] ?? '');
                $bankAccount = trim($input['bank_account'] ?? '');
                $accountHolder = trim($input['account_holder'] ?? '');
                $refundReason = trim($input['refund_reason'] ?? '');
                $refundTo = trim($input['refund_to'] ?? 'cash');
                $refundMethod = (in_array($refundTo, ['balance', 'account'])) ? '账户' : '转账';
                $courseName = $order['course_name'] ?? '';
                $n = now();
                $db->exec("INSERT INTO refund_records (project, content, subject_level1, refund_method, order_id, student_id, campus, course_name, total_lessons, total_amount, consumed_lessons, consumed_amount, remaining_lessons, remaining_amount, custom_deduction, actual_refund, bank_name, bank_account, account_holder, refund_reason, status, approval_stage, created_at, updated_at) VALUES (" .
                    $db->quote('课程') . ", " .
                    $db->quote($courseName) . ", " .
                    $db->quote($order['subject_level1'] ?? '') . ", " .
                    $db->quote($refundMethod) . ", " .
                    "$orderId, " .
                    intval($order['student_id']) . ", " .
                    $db->quote($order['campus'] ?? '') . ", " .
                    $db->quote($courseName) . ", " .
                    "$lessonCount, " .
                    "$actualPrice, " .
                    "$consumedLessons, " .
                    round($actualPrice * $consumedLessons / max($lessonCount, 1), 2) . ", " .
                    "$remainingLessons, " .
                    "$remainingAmount, " .
                    "$customDeduction, " .
                    "$actualRefund, " .
                    $db->quote($bankName) . ", " .
                    $db->quote($bankAccount) . ", " .
                    $db->quote($accountHolder) . ", " .
                    $db->quote($refundReason) . ", " .
                    "'待审批', '一级审批', '$n', '$n')");
                // 更新订单退款状态为"退费申请中"（审批通过后才变"已退费"）
                $db->exec("UPDATE orders SET refund_status='退费申请中' WHERE id=$orderId");
                $newId = $db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
                json(['message' => '退费申请提交成功', 'id' => intval($newId)]);

            } elseif ($refundType === 'account') {
                // === 账户退费（新增） ===
                $studentId = intval($input['student_id'] ?? 0);
                if ($studentId <= 0) { json(['error' => '学员ID无效']); break; }
                $subjectLevel1 = trim($input['subject_level1'] ?? '');

                $db->beginTransaction();
                try {
                // 查询学员账户余额（FOR UPDATE 锁行）
                $acct = $db->prepare("SELECT balance FROM student_accounts WHERE student_id=:sid FOR UPDATE");
                $acct->bindValue(':sid', $studentId, PDO::PARAM_INT);
                $acct->execute();
                $acctRow = $acct->fetch(PDO::FETCH_ASSOC);
                $currentBalance = $acctRow ? floatval($acctRow['balance']) : 0.00;

                if ($currentBalance <= 0) { throw new Exception('账户余额为0，无法发起退费'); }

                // 退费金额 = 用户申请金额（不能超过余额）
                $refundAmount = floatval($input['refund_amount'] ?? 0);
                if ($refundAmount <= 0) { throw new Exception('退费金额必须大于0'); }
                if ($refundAmount > $currentBalance) {
                    throw new Exception('退费金额不能超过账户余额（当前余额：' . number_format($currentBalance, 2) . '）');
                }

                $bankName = trim($input['bank_name'] ?? '');
                $bankAccount = trim($input['bank_account'] ?? '');
                $accountHolder = trim($input['account_holder'] ?? '');
                $refundReason = trim($input['refund_reason'] ?? '');

                // 查询学员所在校区（取最近一条订单的校区）
                $campus = '';
                $orderCampus = $db->query("SELECT campus FROM orders WHERE student_id=$studentId ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($orderCampus) $campus = $orderCampus['campus'] ?? '';

                $n = now();
                $db->exec("INSERT INTO refund_records (\n                    project, content, subject_level1, refund_method, order_id, student_id, campus,\n                    course_name, total_lessons, total_amount,\n                    consumed_lessons, consumed_amount,\n                    remaining_lessons, remaining_amount,\n                    custom_deduction, actual_refund,\n                    bank_name, bank_account, account_holder,\n                    refund_reason, status, approval_stage, created_at, updated_at\n                ) VALUES (\n                    '账户', '账户退费', " . $db->quote($subjectLevel1) . ", '转账', 0, $studentId, " . $db->quote($campus) . ",\n                    '账户退费', 0, 0,\n                    0, 0,\n                    0, 0,\n                    0, $refundAmount,\n                    " . $db->quote($bankName) . ", " . $db->quote($bankAccount) . ", " . $db->quote($accountHolder) . ",\n                    " . $db->quote($refundReason) . ", '待审批', '一级审批', '$n', '$n'\n                )");

                // 立即扣减余额（冻结）
                $upd = $db->prepare("UPDATE student_accounts SET balance=balance-:amt, total_refund=total_refund+:amt2 WHERE student_id=:sid");
                $upd->bindValue(':amt', $refundAmount);
                $upd->bindValue(':amt2', $refundAmount);
                $upd->bindValue(':sid', $studentId, PDO::PARAM_INT);
                $upd->execute();

                $newBalance = round($currentBalance - $refundAmount, 2);
                $refundId = intval($db->query("SELECT LAST_INSERT_ID()")->fetchColumn());

                // 写流水：提现冻结
                $stmt2 = $db->prepare("INSERT INTO account_transactions (student_id, type, amount, balance_after, ref_type, ref_id, campus, note) VALUES (:sid, 'refund', :amt, :ba, 'refund_account', :rid, :campus, :note)");
                $stmt2->bindValue(':sid', $studentId, PDO::PARAM_INT);
                $stmt2->bindValue(':amt', $refundAmount);
                $stmt2->bindValue(':ba', $newBalance);
                $stmt2->bindValue(':rid', $refundId, PDO::PARAM_INT);
                $stmt2->bindValue(':campus', $campus, PDO::PARAM_STR);
                $stmt2->bindValue(':note', '账户退费申请-提现冻结', PDO::PARAM_STR);
                $stmt2->execute();

                $db->commit();
                json(['message' => '账户退费申请提交成功', 'id' => $refundId]);
                } catch (Exception $e) {
                    $db->rollBack();
                    json(['error' => $e->getMessage()]);
                }

            } else {
                json(['error' => '无效的退费类型']);
            }

        // 查询退费记录列表
        case 'list_refund_records':
            $page = max(1, intval($_GET['page'] ?? 1));
            $pageSize = max(1, min(100, intval($_GET['page_size'] ?? 15)));
            $keyword = trim($_GET['keyword'] ?? '');
            $status = trim($_GET['status'] ?? '');
            $campus = trim($_GET['campus'] ?? '');
            $dateFrom = trim($_GET['date_from'] ?? '');
            $dateTo = trim($_GET['date_to'] ?? '');

            $where = [];
            $params = [];
            if ($keyword) {
                $where[] = "(s.name LIKE :kw1 OR c.name LIKE :kw2 OR o.order_no LIKE :kw3)";
                $params[':kw1'] = "%$keyword%";
                $params[':kw2'] = "%$keyword%";
                $params[':kw3'] = "%$keyword%";
            }
            if ($status) {
                $where[] = "rr.status = :st";
                $params[':st'] = $status;
            }
            if ($campus) {
                $campusList = array_values(array_filter(array_map('trim', explode(',', $campus))));
                if (count($campusList) === 1) {
                    $where[] = "rr.campus = :cps";
                    $params[':cps'] = $campusList[0];
                } elseif (count($campusList) > 1) {
                    $phs = [];
                    foreach ($campusList as $i => $cp) {
                        $ph = ":cps$i";
                        $phs[] = $ph;
                        $params[$ph] = $cp;
                    }
                    $where[] = "rr.campus IN (" . implode(',', $phs) . ")";
                }
            }
            if ($dateFrom) {
                $where[] = "rr.created_at >= :df";
                $params[':df'] = $dateFrom . ' 00:00:00';
            }
            if ($dateTo) {
                $where[] = "rr.created_at <= :dt";
                $params[':dt'] = $dateTo . ' 23:59:59';
            }
            $project = trim($_GET['project'] ?? '');
            if ($project) {
                $where[] = "rr.project = :pj";
                $params[':pj'] = $project;
            }
            $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $countSql = "SELECT COUNT(*) FROM refund_records rr
                LEFT JOIN students s ON rr.student_id = s.id
                LEFT JOIN orders o ON rr.order_id = o.id
                LEFT JOIN courses c ON o.course_id = c.id
                $whereStr";
            $countStmt = $db->prepare($countSql);
            foreach ($params as $k => $v) $countStmt->bindValue($k, $v, PDO::PARAM_STR);
            $countStmt->execute(); $total = intval($countStmt->fetch(PDO::FETCH_NUM)[0]);
            $offset = ($page - 1) * $pageSize;
            $sql = "SELECT rr.*, s.name AS student_name, s.phone AS student_phone, o.order_no
                FROM refund_records rr
                LEFT JOIN students s ON rr.student_id = s.id
                LEFT JOIN orders o ON rr.order_id = o.id
                LEFT JOIN courses c ON o.course_id = c.id
                $whereStr ORDER BY rr.id DESC LIMIT :lim OFFSET :off";
            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
            $stmt->bindValue(':lim', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $rows = [];
            $stmt->execute();
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $r;
            json(['total' => $total, 'page' => $page, 'page_size' => $pageSize, 'data' => $rows]);
            break;

        // 审批退费
        case 'approve_refund':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $id = intval($input['id'] ?? 0);
            $action = trim($input['action'] ?? ''); // approve / reject
            $approver = trim($input['approver'] ?? '');
            $rejectReason = trim($input['reject_reason'] ?? '');
            if ($id <= 0) { json(['error' => 'ID无效']); break; }
            if (!in_array($action, ['approve', 'reject'])) { json(['error' => '操作无效']); break; }
            $rr = $db->query("SELECT * FROM refund_records WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            if (!$rr) { json(['error' => '退费记录不存在']); break; }
            if (in_array($rr['status'], ['已退费', '审批驳回'])) { json(['error' => '该退费已终止，无法继续审批']); break; }
            $n = now();
            if ($action === 'reject') {
                $db->exec("UPDATE refund_records SET status='审批驳回', reject_reason=" . $db->quote($rejectReason) . ", updated_at='$n' WHERE id=$id");
                // 如果是账户退费，恢复已扣减的余额
                $rrProject = $rr['project'] ?? '课程';
                if ($rrProject === '账户') {
                    $refundAmt = floatval($rr['actual_refund'] ?? 0);
                    $sid = intval($rr['student_id']);
                    $db->beginTransaction();
                    try {
                    $db->exec("UPDATE student_accounts SET balance=balance+$refundAmt, total_refund=total_refund-$refundAmt WHERE student_id=$sid");
                    // 写恢复流水
                    $acctBal = $db->query("SELECT balance FROM student_accounts WHERE student_id=$sid")->fetch(PDO::FETCH_ASSOC);
                    $rstmt = $db->prepare("INSERT INTO account_transactions (student_id, type, amount, balance_after, ref_type, ref_id, note) VALUES (:sid, 'deposit', :amt, :ba, 'refund_cancel', :rid, :note)");
                    $rstmt->bindValue(':sid', $sid, PDO::PARAM_INT);
                    $rstmt->bindValue(':amt', $refundAmt);
                    $rstmt->bindValue(':ba', $acctBal['balance']);
                    $rstmt->bindValue(':rid', $id, PDO::PARAM_INT);
                    $rstmt->bindValue(':note', '账户退费申请被驳回-余额恢复', PDO::PARAM_STR);
                    $rstmt->execute();
                    // 标记原冻结流水
                    $db->exec("UPDATE account_transactions SET note='账户退费-已驳回' WHERE ref_type='refund_account' AND ref_id=$id");
                    $db->commit();
                    } catch (Exception $e) {
                        $db->rollBack();
                        json(['error' => '驳回处理失败：' . $e->getMessage()]);
                        break;
                    }
                } else {
                    // 恢复订单状态为正常
                    $db->exec("UPDATE orders SET refund_status='正常' WHERE id=" . intval($rr['order_id']));
                }
                json(['message' => '已驳回退费申请']);
                break;
            }
            // 审批通过
            $currentStage = $rr['approval_stage'];
            if ($currentStage === '一级审批') {
                $db->exec("UPDATE refund_records SET status='一级审批通过', approval_stage='二级审批', approver1=" . $db->quote($approver) . ", updated_at='$n' WHERE id=$id");
                json(['message' => '一级审批通过，等待二级审批']);
            } elseif ($currentStage === '二级审批') {
                // 检查：课程退费 + 退到学员账户 → 直接完成，跳过财务确认
                $rrMethod = $rr['refund_method'] ?? '转账';
                $rrProject = $rr['project'] ?? '课程';
                if ($rrProject === '课程' && $rrMethod === '账户') {
                    // 直接完成退费：余额到账 + 写流水
                    $refundAmount = floatval($rr['actual_refund'] ?? 0);
                    $orderId = intval($rr['order_id']);
                    $studentId = intval($rr['student_id']);
                    $order2 = $db->query("SELECT lesson_count FROM orders WHERE id=$orderId")->fetch(PDO::FETCH_ASSOC);
                    $lc = $order2 ? intval($order2['lesson_count']) : 0;
                    $db->beginTransaction();
                    try {
                        // 1. 更新退费记录：直接标记「已退费」（跳过财务确认）
                        $db->exec("UPDATE refund_records SET status='已退费', approval_stage='已完成', approver2=" . $db->quote($approver) . ", updated_at='$n' WHERE id=$id");
                        // 2. 将订单消耗课时设置为总课时（剩余课时归零）
                        $db->exec("UPDATE orders SET refund_status='已退费', consumed_lessons=$lc WHERE id=$orderId");
                        // 3. 余额到账 + FOR UPDATE 防并发
                        $acct = $db->prepare("SELECT balance FROM student_accounts WHERE student_id = :sid FOR UPDATE");
                        $acct->bindValue(':sid', $studentId, PDO::PARAM_INT);
                        $acct->execute();
                        $acctRow = $acct->fetch(PDO::FETCH_ASSOC);
                        $oldBalance = $acctRow ? floatval($acctRow['balance']) : 0.00;
                        $newBalance = round($oldBalance + $refundAmount, 2);
                        // 4. 更新余额
                        $upd = $db->prepare("INSERT INTO student_accounts (student_id, balance, total_deposit, total_consume, total_refund) VALUES (:sid, :bal, 0, 0, :tr) ON DUPLICATE KEY UPDATE balance = balance + :bal2, total_refund = total_refund + :tr2");
                        $upd->bindValue(':sid', $studentId, PDO::PARAM_INT);
                        $upd->bindValue(':bal', $refundAmount);
                        $upd->bindValue(':tr', $refundAmount);
                        $upd->bindValue(':bal2', $refundAmount);
                        $upd->bindValue(':tr2', $refundAmount);
                        $upd->execute();
                        // 5. 写账户流水
                        $stmt2 = $db->prepare("INSERT INTO account_transactions (student_id, type, amount, balance_after, ref_type, ref_id, campus, note) VALUES (:sid, 'refund', :amt, :ba, 'refund', :rid, :campus, :note)");
                        $stmt2->bindValue(':sid', $studentId, PDO::PARAM_INT);
                        $stmt2->bindValue(':amt', $refundAmount);
                        $stmt2->bindValue(':ba', $newBalance);
                        $stmt2->bindValue(':rid', $id, PDO::PARAM_INT);
                        $stmt2->bindValue(':campus', $rr['campus'] ?? '', PDO::PARAM_STR);
                        $stmt2->bindValue(':note', '课程退费-退回学员账户: ' . ($rr['course_name'] ?? ''), PDO::PARAM_STR);
                        $stmt2->execute();
                        $db->commit();
                        json(['message' => '二级审批通过，退费已自动到账学员账户']);
                    } catch (Exception $e) {
                        $db->rollBack();
                        json(['error' => '退费到账户失败：' . $e->getMessage()]);
                    }
                } else {
                    // 原有逻辑：进入财务确认阶段
                    $db->exec("UPDATE refund_records SET status='二级审批通过', approval_stage='财务确认', approver2=" . $db->quote($approver) . ", updated_at='$n' WHERE id=$id");
                    json(['message' => '二级审批通过，等待财务确认']);
                }
            } elseif ($currentStage === '财务确认') {
                $rrProject = $rr['project'] ?? '课程';

                if ($rrProject === '账户') {
                    // 账户退费：只能退到银行卡（余额已在申请时扣减）
                    $db->exec("UPDATE refund_records SET status='已退费', approver3=" . $db->quote($approver) . ", updated_at='$n' WHERE id=$id");
                    // 更新冻结流水备注
                    $db->exec("UPDATE account_transactions SET note='账户退费-已退至银行卡' WHERE ref_type='refund_account' AND ref_id=$id");
                    json(['message' => '财务确认通过，账户退费已完成（退至银行卡）']);
                } else {
                                    // 课程退费：退到银行卡
                                $refundAmount = floatval($rr['actual_refund'] ?? 0);
                                $orderId = intval($rr['order_id']);
                                $order2 = $db->query("SELECT lesson_count FROM orders WHERE id=$orderId")->fetch(PDO::FETCH_ASSOC);
                                $lc = $order2 ? intval($order2['lesson_count']) : 0;
                                $db->exec("UPDATE refund_records SET status='已退费', approver3=" . $db->quote($approver) . ", updated_at='$n' WHERE id=$id");
                                $db->exec("UPDATE orders SET refund_status='已退费', consumed_lessons=$lc WHERE id=$orderId");
                                json(['message' => '财务确认通过，退费已完成']);
                                }
            } else {
                json(['error' => '当前审批阶段异常']);
            }
            break;

        // 撤销退费申请
        case 'cancel_refund':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) { json(['error' => 'ID无效']); break; }
            $rr = $db->query("SELECT * FROM refund_records WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            if (!$rr) { json(['error' => '退费记录不存在']); break; }
            if ($rr['status'] === '已退费' || $rr['status'] === '审批驳回') { json(['error' => '该退费已终止，无法撤销']); break; }
            $rrProject = $rr['project'] ?? '课程';
            if ($rrProject === '账户') {
                // 恢复余额
                $refundAmount = floatval($rr['actual_refund'] ?? 0);
                $studentId = intval($rr['student_id']);
                $db->beginTransaction();
                try {
                $db->exec("UPDATE student_accounts SET balance=balance+$refundAmount, total_refund=total_refund-$refundAmount WHERE student_id=$studentId");
                // 写恢复流水
                $acct = $db->query("SELECT balance FROM student_accounts WHERE student_id=$studentId")->fetch(PDO::FETCH_ASSOC);
                $stmt = $db->prepare("INSERT INTO account_transactions (student_id, type, amount, balance_after, ref_type, ref_id, note) VALUES (:sid, 'deposit', :amt, :ba, 'refund_cancel', :rid, :note)");
                $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
                $stmt->bindValue(':amt', $refundAmount);
                $stmt->bindValue(':ba', $acct['balance']);
                $stmt->bindValue(':rid', $id, PDO::PARAM_INT);
                $stmt->bindValue(':note', '账户退费撤销-余额恢复', PDO::PARAM_STR);
                $stmt->execute();
                // 标记原冻结流水的备注
                $db->exec("UPDATE account_transactions SET note='账户退费-已撤销' WHERE ref_type='refund_account' AND ref_id=$id");
                $db->commit();
                } catch (Exception $e) {
                    $db->rollBack();
                    json(['error' => '撤销失败：' . $e->getMessage()]);
                    break;
                }
            } else {
                // 恢复订单退费状态
                $orderId = intval($rr['order_id']);
                $db->exec("UPDATE orders SET refund_status='正常' WHERE id=$orderId");
            }
            // 删除退费记录
            $db->exec("DELETE FROM refund_records WHERE id=$id");
            json(['message' => '退费申请已撤销']);
            break;

        // 获取单条退费记录详情
        case 'get_refund_record':
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) { json(['error' => 'ID无效']); break; }
            $rr = $db->query("SELECT rr.*, s.name AS student_name, s.phone AS student_phone, o.order_no
                FROM refund_records rr
                LEFT JOIN students s ON rr.student_id = s.id
                LEFT JOIN orders o ON rr.order_id = o.id
                WHERE rr.id=$id")->fetch(PDO::FETCH_ASSOC);
            if (!$rr) { json(['error' => '退费记录不存在']); break; }
            json(['data' => $rr]);
            break;

// ==================== 学员账户 API ====================
        // 查询学员账户及流水
        case 'get_student_account':
            $studentId = intval($_GET['student_id'] ?? 0);
            if ($studentId <= 0) { json(['error' => '学员ID无效']); break; }
            // 查询账户汇总
            $acct = $db->prepare("SELECT * FROM student_accounts WHERE student_id = :sid");
            $acct->bindValue(':sid', $studentId, PDO::PARAM_INT);
            $acct->execute();
            $acct = $acct->fetch(PDO::FETCH_ASSOC);
            if (!$acct) {
                // 账户不存在则返回默认值
                $acct = ['balance' => 0, 'total_deposit' => 0, 'total_consume' => 0, 'total_refund' => 0];
            }
            // 流水筛选+分页
            $page = max(1, intval($_GET['page'] ?? 1));
            $pageSize = min(50, max(1, intval($_GET['page_size'] ?? 20)));
            $typeFilter = trim($_GET['type_filter'] ?? '');
            $dateFrom = trim($_GET['date_from'] ?? '');
            $dateTo = trim($_GET['date_to'] ?? '');
            $where = ['at.student_id = :sid'];
            $params = [':sid' => $studentId];
            if ($typeFilter) {
                $where[] = 'at.type = :tf';
                $params[':tf'] = $typeFilter;
            }
            if ($dateFrom) {
                $where[] = 'at.created_at >= :df';
                $params[':df'] = $dateFrom . ' 00:00:00';
            }
            if ($dateTo) {
                $where[] = 'at.created_at <= :dt';
                $params[':dt'] = $dateTo . ' 23:59:59';
            }
            $whereStr = 'WHERE ' . implode(' AND ', $where);
            $countStmt = $db->prepare("SELECT COUNT(*) FROM account_transactions at $whereStr");
            foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
            $countStmt->execute(); $total = intval($countStmt->fetch(PDO::FETCH_NUM)[0]);
            $offset = ($page - 1) * $pageSize;
            $sql = "SELECT at.id, at.type, at.amount, at.balance_after, at.ref_type, at.ref_id, at.campus, at.note, at.payment_method, at.created_at, o.order_no AS ref_no FROM account_transactions at LEFT JOIN orders o ON at.ref_id = o.id $whereStr ORDER BY at.created_at DESC LIMIT :lim OFFSET :off";
            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':lim', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $transactions = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $transactions[] = $row;
            json([
                'balance' => floatval($acct['balance']),
                'total_deposit' => floatval($acct['total_deposit']),
                'total_consume' => floatval($acct['total_consume']),
                'total_refund' => floatval($acct['total_refund']),
                'transactions' => $transactions,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize
            ]);
            break;

        // 学员账户充值
        case 'top_up_account':
            error_log('DEBUG: top_up_account input=' . json_encode($input, JSON_UNESCAPED_UNICODE));
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $studentId = intval($input['student_id'] ?? 0);
            $amount = floatval($input['amount'] ?? 0);
            $paymentMethod = trim($input['payment_method'] ?? '现金');
            $note = trim($input['note'] ?? '');
            $campus = trim($input['campus'] ?? '');
            $subjectLevel1 = trim($input['subject_level1'] ?? '');
            if ($studentId <= 0) { json(['error' => '学员ID无效']); break; }
            if ($amount <= 0) { json(['error' => '充值金额必须大于0']); break; }
            // 查询当前余额（先锁行）
            $db->beginTransaction();
            try {
                $acct = $db->prepare("SELECT balance FROM student_accounts WHERE student_id = :sid FOR UPDATE");
                $acct->bindValue(':sid', $studentId, PDO::PARAM_INT);
                $acct->execute();
                $acct = $acct->fetch(PDO::FETCH_ASSOC);
                $oldBalance = $acct ? floatval($acct['balance']) : 0.00;
                $newBalance = round($oldBalance + $amount, 2);
                // INSERT ... ON DUPLICATE KEY UPDATE
                $stmt = $db->prepare("INSERT INTO student_accounts (student_id, balance, total_deposit, total_consume, total_refund) VALUES (:sid, :bal, :td, 0, 0) ON DUPLICATE KEY UPDATE balance = balance + :bal2, total_deposit = total_deposit + :td2");
                $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
                $stmt->bindValue(':bal', $amount);
                $stmt->bindValue(':td', $amount);
                $stmt->bindValue(':bal2', $amount);
                $stmt->bindValue(':td2', $amount);
                $stmt->execute();
                // 查学员信息
                $student = $db->prepare("SELECT name, phone, student_no FROM students WHERE id = :sid");
                $student->bindValue(':sid', $studentId, PDO::PARAM_INT);
                $student->execute();
                $student = $student->fetch(PDO::FETCH_ASSOC);
                // 生成订单号并插入订单
                $orderNo = generateOrderNo($db);
                $n = now();
                $cashAmount = ($paymentMethod === '现金') ? $amount : 0;
                $mtAmount = ($paymentMethod === '美团') ? $amount : 0;
                $stmtOrder = $db->prepare("INSERT INTO orders (student_id, course_id, plan_name, item_name, lesson_count, actual_price, cash_amount, meituan_amount, account_amount, paid_amount, order_no, parent_order_no, created_at, paid_at, order_type, campus, pay_status, is_voided, subject_level1, subject_level2) VALUES (:sid, 0, '', '', 0, :ap, :ca, :ma, 0, :pa, :ono, '', :ct, :ct2, '账户充值', :campus, '已支付', '否', :sl1, '')");
                $stmtOrder->bindValue(':sid', $studentId, PDO::PARAM_INT);
                $stmtOrder->bindValue(':ap', $amount);
                $stmtOrder->bindValue(':ca', $cashAmount);
                $stmtOrder->bindValue(':ma', $mtAmount);
                $stmtOrder->bindValue(':pa', $amount);
                $stmtOrder->bindValue(':ono', $orderNo, PDO::PARAM_STR);
                $stmtOrder->bindValue(':ct', $n, PDO::PARAM_STR);
                $stmtOrder->bindValue(':ct2', $n, PDO::PARAM_STR);
                $stmtOrder->bindValue(':campus', $campus, PDO::PARAM_STR);
                $stmtOrder->bindValue(':sl1', $subjectLevel1, PDO::PARAM_STR);
                $stmtOrder->execute();
                $orderId = $db->lastInsertId();
                // 写入流水（含 payment_method + ref_id）
                $refNote = $note ? ('充值: ' . $note) : ($paymentMethod . '充值');
                $stmt2 = $db->prepare("INSERT INTO account_transactions (student_id, type, amount, balance_after, ref_type, ref_id, campus, note, payment_method) VALUES (:sid, 'deposit', :amt, :ba, 'top_up', :rid, :campus, :note, :pm)");
                $stmt2->bindValue(':sid', $studentId, PDO::PARAM_INT);
                $stmt2->bindValue(':amt', $amount);
                $stmt2->bindValue(':ba', $newBalance);
                $stmt2->bindValue(':rid', $orderId, PDO::PARAM_INT);
                $stmt2->bindValue(':campus', $campus, PDO::PARAM_STR);
                $stmt2->bindValue(':note', $refNote, PDO::PARAM_STR);
                $stmt2->bindValue(':pm', $paymentMethod, PDO::PARAM_STR);
                $stmt2->execute();
                $db->commit();
                json(['success' => true, 'balance' => $newBalance, 'message' => '充值成功', 'order_id' => $orderId, 'order_no' => $orderNo]);
            } catch (Exception $e) {
                $db->rollBack();
                json(['error' => '充值失败: ' . $e->getMessage()]);
            }
            break;

// ==================== 班级管理 API ====================
        case 'list_classes':
            $page = max(1, intval($_GET['page'] ?? 1));
            $pageSize = min(50, max(1, intval($_GET['page_size'] ?? 15)));
            $keyword = trim($_GET['keyword'] ?? '');
            $offset = ($page - 1) * $pageSize;
            $where = '';
            $params = [];
            if ($keyword) {
                $where = "WHERE cl.name LIKE :kw";
                $params[':kw'] = "%$keyword%";
            }
            // has_schedule=1 仅返回已有排课记录的班级
            if (isset($_GET['has_schedule']) && $_GET['has_schedule'] == '1') {
                $where .= ($where ? ' AND' : 'WHERE') . ' cl.id IN (SELECT DISTINCT class_id FROM schedules)';
            }
            $stmt = $db->prepare("SELECT COUNT(*) FROM classes cl $where");
            foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
            $stmt->execute(); $total = $stmt->fetch(PDO::FETCH_NUM)[0];
            $sql = "SELECT cl.*, c.name AS course_name, COALESCE(NULLIF(c.subject_level1,''), SUBSTRING_INDEX(c.subject, ' > ', 1)) AS parent_subject, COALESCE(NULLIF(c.subject_level2,''), SUBSTRING_INDEX(c.subject, ' > ', -1)) AS child_subject FROM classes cl LEFT JOIN courses c ON cl.course_id = c.id $where ORDER BY cl.id DESC LIMIT :limit OFFSET :offset";
            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $rows = [];
$stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $row;
            json(['data' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
            break;

        case 'add_class':
            error_log('DEBUG: add_class reached, method=' . $method);
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $courseId = intval($input['course_id'] ?? 0);
            $name = trim($input['name'] ?? '');
            $classType = trim($input['class_type'] ?? '标准班');
            $maxStudents = intval($input['max_students'] ?? 0);
            $lessonHours = intval($input['lesson_hours'] ?? 0);
            $canTrial = (trim($input['can_trial'] ?? '是') === '否') ? 0 : 1;
            $campus = trim($input['campus'] ?? '');
            $remark = trim($input['remark'] ?? '');
            if (!$courseId) json(['error' => '请选择关联课程']);
            if (!$name) json(['error' => '班级名称不能为空']);
            if (utf8_strlen($name) > 20) json(['error' => '班级名称最长20字']);
            if (!$classType) json(['error' => '请选择班级类型']);
            if ($maxStudents <= 0) json(['error' => '招生人数必须大于0']);
            if ($lessonHours % 2 !== 0) json(['error' => '授课课时必须为偶数']);
            if (!$campus) json(['error' => '请选择当前校区']);
            if (utf8_strlen($remark) > 200) json(['error' => '备注最长200字']);
            $n = now();
            $stmt = $db->prepare("INSERT INTO classes (course_id, name, class_type, max_students, lesson_hours, can_trial, campus, remark, created_at) VALUES (:cid, :nm, :ct, :ms, :lh, :tr, :cp, :rm, :ca)");
            $stmt->bindValue(':cid', $courseId, PDO::PARAM_INT);
            $stmt->bindValue(':nm', $name, PDO::PARAM_STR);
            $stmt->bindValue(':ct', $classType, PDO::PARAM_STR);
            $stmt->bindValue(':ms', $maxStudents, PDO::PARAM_INT);
            $stmt->bindValue(':lh', $lessonHours, PDO::PARAM_INT);
            $stmt->bindValue(':tr', $canTrial, PDO::PARAM_INT);
            $stmt->bindValue(':cp', $campus, PDO::PARAM_STR);
            $stmt->bindValue(':rm', $remark, PDO::PARAM_STR);
            $stmt->bindValue(':ca', $n, PDO::PARAM_STR);
            $stmt->execute();
            json(['id' => $db->lastInsertId(), 'message' => '班级新增成功']);
            break;

        case 'update_class':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) json(['error' => '班级ID无效']);
            $existing = $db->query("SELECT * FROM classes WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            if (!$existing) json(['error' => '班级不存在']);
            $updates = [];
            if (isset($input['course_id'])) { $updates[] = "course_id=" . intval($input['course_id']); }
            if (isset($input['name'])) {
                $nm = trim($input['name']);
                if ($nm === '') json(['error' => '班级名称不能为空']);
                if (utf8_strlen($nm) > 20) json(['error' => '班级名称最长20字']);
                $updates[] = "name=" . $db->quote($nm) . "";
            }
            if (isset($input['class_type'])) { $updates[] = "class_type='" . $db->quote(trim($input['class_type'])) . "'"; }
            if (isset($input['max_students'])) { $updates[] = "max_students=" . intval($input['max_students']); }
            if (isset($input['lesson_hours'])) { $lh = intval($input['lesson_hours']); if ($lh % 2 !== 0) json(['error' => '授课课时必须为偶数']); $updates[] = "lesson_hours=" . $lh; }
            if (isset($input['can_trial'])) { $updates[] = "can_trial=" . ((trim($input['can_trial']) === '否') ? 0 : 1); }
            if (isset($input['campus'])) { $updates[] = "campus=" . $db->quote(trim($input['campus'])); }
            if (isset($input['remark'])) {
                $rm = trim($input['remark']);
                if (utf8_strlen($rm) > 200) json(['error' => '备注最长200字']);
                $updates[] = "remark=" . $db->quote($rm) . "";
            }
            if (empty($updates)) json(['message' => '无变更']);
            $db->exec("UPDATE classes SET " . implode(', ', $updates) . " WHERE id=$id");
            json(['message' => '班级更新成功']);
            break;

        case 'delete_class':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) json(['error' => '班级ID无效']);
            $attCount = $db->query("SELECT COUNT(*) FROM class_attendance WHERE class_id=$id")->fetchColumn();
            if ($attCount > 0) json(['error' => '该班级已有考勤记录，不可删除']);
            $db->exec("DELETE FROM classes WHERE id=$id");
            json(['message' => '班级删除成功']);
            break;


// ==================== 排课管理 API ====================
        case 'list_schedules':
            $classId = intval($_GET['class_id'] ?? 0);
            if ($classId <= 0) json(['error' => '班级ID无效']);
            $stmt = $db->prepare("SELECT * FROM schedules WHERE class_id=:cid ORDER BY id DESC");
            $stmt->bindValue(':cid', $classId, PDO::PARAM_INT);
            $rows = [];
$stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['sessions'] = computeSessions($row);
                $rows[] = $row;
            }
            json(['data' => $rows]);
            break;

        case 'get_schedule':
            $scheduleId = intval($_GET['id'] ?? 0);
            if ($scheduleId <= 0) json(['error' => '排课ID无效']);
            $row = $db->query("SELECT * FROM schedules WHERE id=$scheduleId")->fetch(PDO::FETCH_ASSOC);
            if (!$row) json(['error' => '排课记录不存在']);
            json(['data' => $row]);
            break;

        case 'add_schedule':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $classId = intval($input['class_id'] ?? 0);
            $ruleType = trim($input['rule_type'] ?? '按规则排课');
            $startDate = trim($input['start_date'] ?? '');
            $endDate = trim($input['end_date'] ?? '');
            $weekdays = trim($input['weekdays'] ?? '');
            $timeSlots = trim($input['time_slots'] ?? '{}');
            $holidayEnabled = intval($input['holiday_enabled'] ?? 0);
            $teacher = trim($input['teacher'] ?? '');
            $classroom = trim($input['classroom'] ?? '');
            if ($classId <= 0) json(['error' => '请选择班级']);
            if ($ruleType === '按规则排课') {
                if (!$startDate) json(['error' => '请选择开课日期']);
                if (!$endDate) json(['error' => '请选择结课日期']);
                if (!$weekdays) json(['error' => '请选择上课周期']);
                if (!$timeSlots || $timeSlots === '{}') json(['error' => '请设置上课时间']);
            }
            $n = now();
            $stmt = $db->prepare("INSERT INTO schedules (class_id, rule_type, start_date, end_date, weekdays, time_slots, holiday_enabled, teacher, classroom, created_at) VALUES (:cid, :rt, :sd, :ed, :wd, :ts, :he, :tch, :cr, :ca)");
            $stmt->bindValue(':cid', $classId, PDO::PARAM_INT);
            $stmt->bindValue(':rt', $ruleType, PDO::PARAM_STR);
            $stmt->bindValue(':sd', $startDate, PDO::PARAM_STR);
            $stmt->bindValue(':ed', $endDate, PDO::PARAM_STR);
            $stmt->bindValue(':wd', $weekdays, PDO::PARAM_STR);
            $stmt->bindValue(':ts', $timeSlots, PDO::PARAM_STR);
            $stmt->bindValue(':he', $holidayEnabled, PDO::PARAM_INT);
            $stmt->bindValue(':tch', $teacher, PDO::PARAM_STR);
            $stmt->bindValue(':cr', $classroom, PDO::PARAM_STR);
            $stmt->bindValue(':ca', $n, PDO::PARAM_STR);
            $stmt->execute();
            json(['id' => $db->lastInsertId(), 'message' => '排课新增成功']);
            break;

        case 'update_schedule':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) json(['error' => '排课ID无效']);
            $existing = $db->query("SELECT * FROM schedules WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            if (!$existing) json(['error' => '排课记录不存在']);
            $updates = [];
            if (isset($input['rule_type'])) { $updates[] = "rule_type='" . $db->quote(trim($input['rule_type'])) . "'"; }
            if (isset($input['start_date'])) { $updates[] = "start_date='" . $db->quote(trim($input['start_date'])) . "'"; }
            if (isset($input['end_date'])) { $updates[] = "end_date='" . $db->quote(trim($input['end_date'])) . "'"; }
            if (isset($input['weekdays'])) { $updates[] = "weekdays='" . $db->quote(trim($input['weekdays'])) . "'"; }
            if (isset($input['time_slots'])) { $updates[] = "time_slots='" . $db->quote(trim($input['time_slots'])) . "'"; }
            if (isset($input['holiday_enabled'])) { $updates[] = "holiday_enabled=" . intval($input['holiday_enabled']); }
            if (isset($input['teacher'])) { $updates[] = "teacher='" . $db->quote(trim($input['teacher'])) . "'"; }
            if (isset($input['classroom'])) { $updates[] = "classroom='" . $db->quote(trim($input['classroom'])) . "'"; }
            if (empty($updates)) json(['message' => '无变更']);
            $db->exec("UPDATE schedules SET " . implode(', ', $updates) . " WHERE id=$id");
            json(['message' => '排课更新成功']);
            break;

        case 'delete_schedule':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) json(['error' => '排课ID无效']);
            $db->exec("DELETE FROM schedules WHERE id=$id");
            json(['message' => '排课删除成功']);
            break;

        case 'create_schedule_from_grid':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $courseId = intval($input['course_id'] ?? 0);
            $teacher = trim($input['teacher'] ?? '');
            $classroom = trim($input['classroom'] ?? '');
            $campus = trim($input['campus'] ?? '');
            $dayOfWeek = intval($input['day_of_week'] ?? 0);   // 1=周一..7=周日
            $timeStart = trim($input['time_start'] ?? '');
            $timeEnd = trim($input['time_end'] ?? '');
            $startDate = trim($input['start_date'] ?? '');
            $endDate = trim($input['end_date'] ?? '');
            $className = trim($input['class_name'] ?? '');
            $maxStudents = intval($input['max_students'] ?? 15);
            $lessonHours = intval($input['lesson_hours'] ?? 0);

            if ($courseId <= 0) json(['error' => '请选择课程']);
            if (!$teacher) json(['error' => '请选择教师']);
            if (!$classroom) json(['error' => '请选择教室']);
            if (!$campus) json(['error' => '请选择校区']);
            if ($dayOfWeek < 1 || $dayOfWeek > 7) json(['error' => '无效的星期']);
            if (!$timeStart || !$timeEnd) json(['error' => '请设置上课时间']);
            if (!$startDate) json(['error' => '请选择开课日期']);
            if (!$endDate) json(['error' => '请选择结课日期']);

            // 取课程名
            $courseRow = $db->query("SELECT name FROM courses WHERE id=$courseId")->fetch(PDO::FETCH_ASSOC);
            if (!$courseRow) json(['error' => '课程不存在']);
            $courseName = $courseRow['name'];

            // 自动生成班级名
            if (!$className) {
                $cnt = intval($db->query("SELECT COUNT(*) FROM classes WHERE course_id=$courseId")->fetchColumn()) + 1;
                $className = $courseName . $cnt . '班';
            }
            if (utf8_strlen($className) > 20) json(['error' => '班级名称最长20字']);
            if (utf8_strlen($className) < 2) json(['error' => '班级名称至少2字']);

            $n = now();
            $timeSlots = json_encode(['slot1' => ['start' => $timeStart, 'end' => $timeEnd]], JSON_UNESCAPED_UNICODE);

            $db->beginTransaction();
            try {
                // 1. 创建班级
                $stmt = $db->prepare("INSERT INTO classes (course_id, name, class_type, max_students, lesson_hours, can_trial, campus, remark, created_at) VALUES (:cid, :nm, '标准班', :ms, :lh, 1, :cp, '', :ca)");
                $stmt->bindValue(':cid', $courseId, PDO::PARAM_INT);
                $stmt->bindValue(':nm', $className, PDO::PARAM_STR);
                $stmt->bindValue(':ms', $maxStudents, PDO::PARAM_INT);
                $stmt->bindValue(':lh', $lessonHours, PDO::PARAM_INT);
                $stmt->bindValue(':cp', $campus, PDO::PARAM_STR);
                $stmt->bindValue(':ca', $n, PDO::PARAM_STR);
                $stmt->execute();
                $classId = $db->lastInsertId();

                // 2. 创建排课
                $stmt2 = $db->prepare("INSERT INTO schedules (class_id, rule_type, start_date, end_date, weekdays, time_slots, holiday_enabled, teacher, classroom, created_at) VALUES (:cid, '按规则排课', :sd, :ed, :wd, :ts, 0, :tch, :cr, :ca)");
                $stmt2->bindValue(':cid', $classId, PDO::PARAM_INT);
                $stmt2->bindValue(':sd', $startDate, PDO::PARAM_STR);
                $stmt2->bindValue(':ed', $endDate, PDO::PARAM_STR);
                $stmt2->bindValue(':wd', strval($dayOfWeek), PDO::PARAM_STR);
                $stmt2->bindValue(':ts', $timeSlots, PDO::PARAM_STR);
                $stmt2->bindValue(':tch', $teacher, PDO::PARAM_STR);
                $stmt2->bindValue(':cr', $classroom, PDO::PARAM_STR);
                $stmt2->bindValue(':ca', $n, PDO::PARAM_STR);
                $stmt2->execute();

                $db->commit();
                json(['class_id' => $classId, 'class_name' => $className, 'message' => '排课创建成功']);
            } catch (Exception $e) {
                $db->rollBack();
                json(['error' => '创建失败: ' . $e->getMessage()]);
            }
            break;

        case 'get_schedule_view':
            $campus = trim($_GET['campus'] ?? '');
            $weekStart = trim($_GET['week_start'] ?? '');
            $viewType = trim($_GET['view_type'] ?? 'week'); // week | month
            $teacher = trim($_GET['teacher'] ?? '');
            $classroom = trim($_GET['classroom'] ?? '');
            if (!$weekStart) {
                $weekStart = date('Y-m-d', strtotime('monday this week'));
            }

            $sql = "SELECT s.*, c.name AS class_name, c.id AS class_id, c.course_id, c.campus AS class_campus, co.name AS course_name,
                    (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id = c.id AND cs.left_at = '') AS student_count
                    FROM schedules s
                    JOIN classes c ON s.class_id = c.id
                    JOIN courses co ON c.course_id = co.id
                    WHERE 1=1";
            $params = [];

            if ($viewType === 'month') {
                $monthStart = date('Y-m-01', strtotime($weekStart));
                $monthEnd = date('Y-m-t', strtotime($weekStart));
                $sql .= " AND s.start_date <= :me AND s.end_date >= :ms";
                $params[':ms'] = $monthStart;
                $params[':me'] = $monthEnd;
            } else {
                $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
                $sql .= " AND s.start_date <= :we AND s.end_date >= :ws";
                $params[':ws'] = $weekStart;
                $params[':we'] = $weekEnd;
            }
            if ($campus) {
                $sql .= " AND c.campus = :campus";
                $params[':campus'] = $campus;
            }
            if ($teacher) {
                $sql .= " AND s.teacher = :teacher";
                $params[':teacher'] = $teacher;
            }
            if ($classroom) {
                $sql .= " AND s.classroom = :classroom";
                $params[':classroom'] = $classroom;
            }
            $sql .= " ORDER BY c.campus, co.name, s.id";
            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) { $stmt->bindValue($k, $v, PDO::PARAM_STR); }
            $stmt->execute();
            $schedules = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $schedules[] = $row;
            }

            // 教师列表和教室列表（供前端筛选下拉）
            $teachersStmt = $db->query("SELECT DISTINCT s.teacher FROM schedules s JOIN classes c ON s.class_id = c.id WHERE s.teacher != '' ORDER BY s.teacher");
            $allTeachers = [];
            while ($t = $teachersStmt->fetch(PDO::FETCH_ASSOC)) { $allTeachers[] = $t['teacher']; }
            $classroomsStmt = $db->query("SELECT DISTINCT s.classroom FROM schedules s JOIN classes c ON s.class_id = c.id WHERE s.classroom != '' ORDER BY s.classroom");
            $allClassrooms = [];
            while ($cr = $classroomsStmt->fetch(PDO::FETCH_ASSOC)) { $allClassrooms[] = $cr['classroom']; }

            if ($viewType === 'month') {
                // 月视图：按日期分组
                $monthStart = date('Y-m-01', strtotime($weekStart));
                $monthEnd = date('Y-m-t', strtotime($weekStart));
                $firstDayOfWeek = (int)date('N', strtotime($monthStart));
                $totalDays = (int)date('t', strtotime($monthStart));

                // 构建月视图网格（6周 x 7天），前面补空白
                $monthGrid = [];
                $dayIdx = 0;
                $monthDays = [];

                // 填充前面的空白格
                for ($i = 1; $i < $firstDayOfWeek; $i++) {
                    $monthDays[] = null;
                }

                // 填充当月日期
                for ($d = 1; $d <= $totalDays; $d++) {
                    $dateStr = date('Y-m-d', strtotime($monthStart . ' +' . ($d - 1) . ' days'));
                    $monthDays[] = ['date' => $dateStr, 'day' => $d, 'dayOfWeek' => (int)date('N', strtotime($dateStr))];
                }

                // 收集各日期下的排课
                $dateSlots = [];
                foreach ($schedules as $sch) {
                    $weekdaysArr = array_filter(array_map('intval', explode(',', $sch['weekdays'])));
                    $timeSlots = json_decode($sch['time_slots'] ?? '{}', true) ?: [];
                    $schStart = strtotime($sch['start_date']);
                    $schEnd = strtotime($sch['end_date']);
                    $cur = strtotime($monthStart);
                    $endTs = strtotime($monthEnd);
                    while ($cur <= $endTs) {
                        $curDate = date('Y-m-d', $cur);
                        $dow = (int)date('N', $cur); // 1=周一
                        if ($cur >= $schStart && $cur <= $schEnd && in_array($dow, $weekdaysArr)) {
                            foreach ($timeSlots as $slotKey => $slotVal) {
                        // 若 key 是星期编号（如 "4"="周四"），只匹配对应星期
                        $slotWeekday = intval($slotKey);
                        if ($slotWeekday >= 1 && $slotWeekday <= 7 && $slotWeekday != $dow) continue;
                                $start = '';
                                $end = '';
                                if (is_array($slotVal) && isset($slotVal['start'])) {
                                    $start = $slotVal['start'];
                                    $end = $slotVal['end'] ?? '';
                                } elseif (is_string($slotVal) && strpos($slotVal, '-') !== false) {
                                    list($start, $end) = explode('-', $slotVal, 2);
                                }
                                if (!isset($dateSlots[$curDate])) $dateSlots[$curDate] = [];
                                $dateSlots[$curDate][] = [
                                    'schedule_id' => $sch['id'],
                                    'class_name' => $sch['class_name'],
                                    'course_name' => $sch['course_name'],
                                    'teacher' => $sch['teacher'],
                                    'classroom' => $sch['classroom'],
                                    'campus' => $sch['class_campus'],
                                    'start' => trim($start),
                                    'end' => trim($end),
                                    'course_id' => $sch['course_id'],
                                    'student_count' => intval($sch['student_count']),
                                ];
                            }
                        }
                        $cur = strtotime('+1 day', $cur);
                    }
                }

                // 月视图去重
                foreach ($dateSlots as $date => &$slots) {
                    $seen = [];
                    $slots = array_values(array_filter($slots, function($s) use (&$seen) {
                        $key = $s['schedule_id'] . '|' . $s['start'] . '|' . $s['end'];
                        if (isset($seen[$key])) return false;
                        $seen[$key] = true;
                        return true;
                    }));
                }
                unset($slots);

                // 教室冲突检测
                $conflicts = [];
                foreach ($dateSlots as $date => $slots) {
                    $count = count($slots);
                    for ($i = 0; $i < $count; $i++) {
                        for ($j = $i + 1; $j < $count; $j++) {
                            if ($slots[$i]['classroom'] && $slots[$i]['classroom'] === $slots[$j]['classroom']
                                && $slots[$i]['start'] === $slots[$j]['start'] && $slots[$i]['end'] === $slots[$j]['end']) {
                                $conflicts[$date . '|' . $slots[$i]['classroom'] . '|' . $slots[$i]['start'] . '-' . $slots[$i]['end']] = true;
                            }
                        }
                    }
                }

                json([
                    'data' => [
                        'view_type' => 'month',
                        'month_grid' => $monthDays,
                        'date_slots' => $dateSlots,
                        'conflicts' => array_keys($conflicts),
                        'week_start' => $monthStart,
                        'week_end' => $monthEnd,
                        'today' => date('Y-m-d'),
                        'teachers' => $allTeachers,
                        'classrooms' => $allClassrooms,
                    ]
                ]);
                break;
            }

            // 周视图
            $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
            $days = ['周一', '周二', '周三', '周四', '周五', '周六', '周日'];
            $grid = [];
            foreach ($days as $idx => $label) {
                $dayDate = date('Y-m-d', strtotime($weekStart . ' +' . $idx . ' days'));
                $grid[$label] = ['date' => $dayDate, 'slots' => []];
            }

            foreach ($schedules as $sch) {
                $weekdaysArr = array_filter(array_map('intval', explode(',', $sch['weekdays'])));
                $timeSlots = json_decode($sch['time_slots'] ?? '{}', true) ?: [];
                foreach ($weekdaysArr as $wd) {
                    if ($wd < 1 || $wd > 7) continue;
                    $dayIdx = $wd - 1;
                    $dayLabel = $days[$dayIdx];
                    foreach ($timeSlots as $slotKey => $slotVal) {
                        // 若 key 是星期编号（如 "4"="周四"），只匹配对应星期
                        $slotWeekday = intval($slotKey);
                        if ($slotWeekday >= 1 && $slotWeekday <= 7 && $slotWeekday != $wd) continue;
                        $start = '';
                        $end = '';
                        if (is_array($slotVal) && isset($slotVal['start'])) {
                            $start = $slotVal['start'];
                            $end = $slotVal['end'] ?? '';
                        } elseif (is_string($slotVal) && strpos($slotVal, '-') !== false) {
                            list($start, $end) = explode('-', $slotVal, 2);
                        }
                        $grid[$dayLabel]['slots'][] = [
                            'schedule_id' => $sch['id'],
                            'class_id' => $sch['class_id'],
                            'class_name' => $sch['class_name'],
                            'course_name' => $sch['course_name'],
                            'teacher' => $sch['teacher'],
                            'classroom' => $sch['classroom'],
                            'campus' => $sch['class_campus'],
                            'start' => trim($start),
                            'end' => trim($end),
                            'course_id' => $sch['course_id'],
                            'student_count' => intval($sch['student_count']),
                        ];
                    }
                }
            }

            // 去重：同一schedule在同一天同一时段只保留一条
            foreach ($grid as $dayLabel => &$dayData) {
                $seen = [];
                $dayData['slots'] = array_values(array_filter($dayData['slots'], function($s) use (&$seen) {
                    $key = $s['schedule_id'] . '|' . $s['start'] . '|' . $s['end'];
                    if (isset($seen[$key])) return false;
                    $seen[$key] = true;
                    return true;
                }));
            }
            unset($dayData);

            // 每天按时段排序
            foreach ($grid as $dayLabel => &$dayData) {
                usort($dayData['slots'], function($a, $b) {
                    return strcmp($a['start'], $b['start']);
                });
            }
            unset($dayData);

            // 收集所有时间段并去重排序
            $allSlots = [];
            foreach ($grid as $dayData) {
                foreach ($dayData['slots'] as $s) {
                    $key = $s['start'] . '-' . $s['end'];
                    if (!in_array($key, $allSlots)) $allSlots[] = $key;
                }
            }
            usort($allSlots, function($a, $b) {
                return strcmp(explode('-', $a)[0], explode('-', $b)[0]);
            });

            // 教室冲突检测
            $conflicts = [];
            foreach ($grid as $dayLabel => $dayData) {
                foreach ($dayData['slots'] as $s) {
                    $conflictKey = $dayLabel . '|' . $s['classroom'] . '|' . $s['start'] . '-' . $s['end'];
                    if (!isset($conflicts[$conflictKey])) {
                        $conflicts[$conflictKey] = 0;
                    }
                    $conflicts[$conflictKey]++;
                }
            }
            $conflictKeys = [];
            foreach ($conflicts as $k => $cnt) {
                if ($cnt > 1) $conflictKeys[] = $k;
            }

            json([
                'data' => [
                    'view_type' => 'week',
                    'grid' => $grid,
                    'all_slots' => $allSlots,
                    'week_start' => $weekStart,
                    'week_end' => $weekEnd,
                    'today' => date('Y-m-d'),
                    'conflicts' => $conflictKeys,
                    'teachers' => $allTeachers,
                    'classrooms' => $allClassrooms,
                ]
            ]);
            break;


// ==================== 教室管理 API ====================
        case 'list_classrooms':
            $keyword = $_GET['keyword'] ?? '';
            $where = [];
            if ($keyword) {
                $where[] = "name LIKE '%" . $db->quote($keyword) . "%'";
            }
            $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $rows = [];
            $stmt = $db->query("SELECT * FROM classrooms $whereStr ORDER BY id DESC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $row;
            json(['data' => $rows]);
            break;

        case 'add_classroom':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $name = trim($input['name'] ?? '');
            if ($name === '') json(['error' => '教室名称不能为空']);
            $stmt = $db->query("SELECT COUNT(*) FROM classrooms WHERE name=" . $db->quote($name) . "");
            $existing = $stmt->fetchColumn();
            if (intval($existing) > 0) json(['error' => '教室名称已存在']);
            $capacity = intval($input['capacity'] ?? 0);
            $campus = trim($input['campus'] ?? '');
            $remark = trim($input['remark'] ?? '');
            $n = now();
            $stmt = $db->prepare("INSERT INTO classrooms (name, capacity, campus, remark, created_at) VALUES (:nm, :cp, :ca, :rm, :ct)");
            $stmt->bindValue(':nm', $name, PDO::PARAM_STR);
            $stmt->bindValue(':cp', $capacity, PDO::PARAM_INT);
            $stmt->bindValue(':ca', $campus, PDO::PARAM_STR);
            $stmt->bindValue(':rm', $remark, PDO::PARAM_STR);
            $stmt->bindValue(':ct', $n, PDO::PARAM_STR);
            $stmt->execute();
            json(['id' => $db->lastInsertId(), 'message' => '教室新增成功']);
            break;

        case 'update_classroom':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) json(['error' => '教室ID无效']);
            $existing = $db->query("SELECT * FROM classrooms WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            if (!$existing) json(['error' => '教室不存在']);
            $updates = [];
            if (isset($input['name'])) {
                $nm = trim($input['name']);
                if ($nm === '') json(['error' => '教室名称不能为空']);
                $stmt = $db->query("SELECT COUNT(*) FROM classrooms WHERE name=" . $db->quote($nm) . " AND id!=$id");
                $dup = $stmt->fetchColumn();
                if (intval($dup) > 0) json(['error' => '教室名称已存在']);
                $updates[] = "name=" . $db->quote($nm) . "";
            }
            if (isset($input['capacity'])) { $updates[] = "capacity=" . intval($input['capacity']); }
            if (isset($input['campus'])) { $updates[] = "campus=" . $db->quote(trim($input['campus'])); }
            if (isset($input['remark'])) { $updates[] = "remark=" . $db->quote(trim($input['remark'])); }
            if (empty($updates)) json(['message' => '无变更']);
            $db->exec("UPDATE classrooms SET " . implode(', ', $updates) . " WHERE id=$id");
            json(['message' => '教室更新成功']);
            break;

        case 'delete_classroom':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) json(['error' => '教室ID无效']);
            $db->exec("DELETE FROM classrooms WHERE id=$id");
            json(['message' => '教室删除成功']);
            break;


// ==================== 班级学员管理 API ====================
        case 'list_class_students':
            $classId = intval($_GET['class_id'] ?? 0);
            if ($classId <= 0) json(['error' => '班级ID无效']);
            $rows = [];
            $stmt = $db->query("SELECT cs.id as cs_id, cs.class_id, cs.student_id, cs.created_at as joined_at, cs.left_at,
                s.id, s.student_no, s.name, s.phone, s.source, s.follow_status
                FROM class_students cs
                JOIN students s ON s.id = cs.student_id
                WHERE cs.class_id = $classId AND cs.left_at = ''
                ORDER BY cs.id ASC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $row;
            json(['data' => $rows]);
            break;

        case 'add_class_student':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $classId = intval($input['class_id'] ?? 0);
            $studentId = intval($input['student_id'] ?? 0);
            if ($classId <= 0) json(['error' => '班级ID无效']);
            if ($studentId <= 0) json(['error' => '学员ID无效']);
            $stmt = $db->query("SELECT COUNT(*) FROM class_students WHERE class_id=$classId AND student_id=$studentId AND left_at = ''");
            $exists = $stmt->fetchColumn();
            if (intval($exists) > 0) json(['error' => '该学员已在此班级中']);
            // 检查一级学科下剩余课时
            $classRow = $db->query("SELECT c.course_id, co.subject_level1, co.subject_level2 FROM classes c LEFT JOIN courses co ON c.course_id = co.id WHERE c.id = $classId")->fetch(PDO::FETCH_ASSOC);
            $subjectLevel1 = $classRow['subject_level1'] ?? '';
            $firstSubjectId = 0;
            $subjRow = $db->query("SELECT id, parent_id FROM subjects WHERE name = " . $db->quote($subjectLevel1))->fetch(PDO::FETCH_ASSOC);
            if ($subjRow) {
                if (intval($subjRow['parent_id']) == 0) {
                    $firstSubjectId = intval($subjRow['id']);
                } else {
                    $firstSubjectId = intval($subjRow['parent_id']);
                }
            }
            if ($firstSubjectId > 0) {
                $allCourseIds = [];
                $sr = $db->query("SELECT id FROM courses WHERE subject_level1 IN (SELECT name FROM subjects WHERE parent_id = $firstSubjectId OR id = $firstSubjectId)");
                while ($c = $sr->fetch(PDO::FETCH_ASSOC)) $allCourseIds[] = $c['id'];
                if (count($allCourseIds) > 0) {
                    $sumRow = $db->query("SELECT SUM(lesson_count - consumed_lessons) AS total_remaining FROM orders WHERE student_id = $studentId AND course_id IN (" . implode(',', $allCourseIds) . ") AND is_voided='否' AND id NOT IN (SELECT order_id FROM refund_records WHERE status NOT IN ('已退费', '审批驳回'))")->fetch(PDO::FETCH_ASSOC);
                    $totalRemaining = intval($sumRow['total_remaining'] ?? 0);
                    if ($totalRemaining <= 0) {
                        json(['error' => '该学员在此学科下无剩余课时，无法分班']);
                    }
                }
            }
            $n = now();
            $today = date('Y-m-d');
            // 如果此前已出班（left_at 非空），则清空 left_at 重新激活入班；joined_at 同步为当天
            $existing = $db->query("SELECT id, left_at FROM class_students WHERE class_id = $classId AND student_id = $studentId")->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $db->exec("UPDATE class_students SET left_at = '', joined_at = '$today', created_at = '$n' WHERE id = " . intval($existing['id']));
                json(['id' => intval($existing['id']), 'message' => '学员已重新加入班级']);
            } else {
                $db->exec("INSERT INTO class_students (class_id, student_id, joined_at, created_at) VALUES ($classId, $studentId, '$today', '$n')");
                json(['id' => $db->lastInsertId(), 'message' => '学员已加入班级']);
            }
            break;

        case 'remove_class_student':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) json(['error' => '关联ID无效']);
            $sessionDate = trim($input['session_date'] ?? '');
            $leftAt = $sessionDate ? $sessionDate : date('Y-m-d');
            // 读取 student_id 和 class_id，用于查询历史考勤记录数
            $csRow = $db->query("SELECT student_id, class_id FROM class_students WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
            $studentId = intval($csRow['student_id'] ?? 0);
            $classId = intval($csRow['class_id'] ?? 0);
            // 查询历史考勤记录数
            $cntRow = $db->query("SELECT COUNT(*) AS cnt FROM class_attendance WHERE student_id = $studentId AND class_id = $classId")->fetch(PDO::FETCH_ASSOC);
            $historyCount = intval($cntRow['cnt'] ?? 0);
            // 标记出班日期（不影响历史考勤，课次日期 > left_at 的课次不再出现）
            $db->exec("UPDATE class_students SET left_at = '$leftAt' WHERE id = $id");
            // 如果已有此学员未来课次的考勤记录（还没发生的课次），删除之
            $today = date('Y-m-d');
            $db->exec("DELETE FROM class_attendance WHERE student_id = $studentId AND class_id = $classId AND session_date > '$today'");
            json(['message' => '学员已移出班级', 'history_count' => $historyCount]);
            break;

        case 'get_available_students':
            $classId = intval($_GET['class_id'] ?? 0);
            $keyword = trim($_GET['keyword'] ?? '');
            if ($classId <= 0) json(['error' => '班级ID无效']);
            // 获取班级的一级学科和校区
            $classRow = $db->query("SELECT c.course_id, co.subject_level1, co.subject_level2, c.campus FROM classes c LEFT JOIN courses co ON c.course_id = co.id WHERE c.id = $classId")->fetch(PDO::FETCH_ASSOC);
            $subjectLevel1 = $classRow['subject_level1'] ?? '';
            $classCampus = $classRow['campus'] ?? '';
            $firstSubjectId = 0;
            if ($subjectLevel1) {
                $subjRow = $db->query("SELECT id, parent_id FROM subjects WHERE name = " . $db->quote($subjectLevel1))->fetch(PDO::FETCH_ASSOC);
                if ($subjRow) {
                    $firstSubjectId = intval($subjRow['parent_id']) == 0 ? intval($subjRow['id']) : intval($subjRow['parent_id']);
                }
            }
            $allCourseIds = [];
            if ($firstSubjectId > 0) {
                $sr = $db->query("SELECT id FROM courses WHERE subject_level1 IN (SELECT name FROM subjects WHERE parent_id = $firstSubjectId OR id = $firstSubjectId)");
                while ($c = $sr->fetch(PDO::FETCH_ASSOC)) $allCourseIds[] = intval($c['id']);
            }
            $where = [];
            if ($keyword) {
                $likePattern = $db->quote("%$keyword%");
                $where[] = "(s.name LIKE $likePattern OR s.phone LIKE $likePattern OR s.student_no LIKE $likePattern)";
            }
            $whereStr = $where ? 'AND ' . implode(' AND ', $where) : '';
            $rows = [];
            if (count($allCourseIds) > 0) {
                $sql = "SELECT s.id, s.student_no, s.name, s.phone,
                    COALESCE((SELECT SUM(o.lesson_count - o.consumed_lessons)
                        FROM orders o
                        WHERE o.student_id = s.id
                        AND o.course_id IN (" . implode(',', $allCourseIds) . ")
                        AND o.campus = " . $db->quote($classCampus) . "), 0) AS remaining_hours
                    FROM students s
                    WHERE s.id NOT IN (SELECT student_id FROM class_students WHERE class_id=$classId AND left_at = '')
                    AND EXISTS (
                        SELECT 1 FROM orders o2
                        WHERE o2.student_id = s.id
                        AND o2.course_id IN (" . implode(',', $allCourseIds) . ")
                        AND o2.campus = " . $db->quote($classCampus) . "
                        AND (o2.lesson_count - o2.consumed_lessons) > 0
                    )
                    $whereStr
                    ORDER BY s.id DESC
                    LIMIT 50";
            } else {
                // 无匹配课程时返回空列表
                $sql = "SELECT s.id, s.student_no, s.name, s.phone FROM students s WHERE 1=0";
            }
            $stmt = $db->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $row;
            json(['data' => $rows]);
            break;

        // ==================== 班级考勤 API ====================

        case 'get_temp_student_candidates':
            $classId = intval($_GET['class_id'] ?? 0);
            $scheduleId = intval($_GET['schedule_id'] ?? 0);
            $sessionDate = trim($_GET['session_date'] ?? '');
            $keyword = trim($_GET['keyword'] ?? '');
            if ($classId <= 0) json(['error' => '班级ID无效']);
            if ($scheduleId <= 0) json(['error' => '排课ID无效']);
            if (!$sessionDate) json(['error' => '课次日期无效']);
            // 查询班级的 course_id、campus、subject_level1
            $classInfo = $db->query("SELECT c.course_id, c.campus, co.subject_level1 AS subject_raw FROM classes c LEFT JOIN courses co ON c.course_id = co.id WHERE c.id = $classId")->fetch(PDO::FETCH_ASSOC);
            if (!$classInfo) json(['data' => []]);
            $classCampus = $classInfo['campus'] ?? '';
            // 解析一级学科ID
            $firstSubjectId = 0;
            $subjectRaw = $classInfo['subject_raw'] ?? '';
            if ($subjectRaw) {
                $sj = $db->query("SELECT id, parent_id FROM subjects WHERE name = " . $db->quote($subjectRaw))->fetch(PDO::FETCH_ASSOC);
                if ($sj) {
                    $firstSubjectId = intval($sj['parent_id']) == 0 ? intval($sj['id']) : intval($sj['parent_id']);
                }
            }
            if ($firstSubjectId <= 0) json(['data' => []]);
            // 查询该一级学科下的所有课程ID
            $subjectCourseIds = [];
            $scRes = $db->query("SELECT id FROM courses WHERE subject_level1 IN (SELECT name FROM subjects WHERE parent_id = $firstSubjectId OR id = $firstSubjectId)");
            while ($c = $scRes->fetch(PDO::FETCH_ASSOC)) $subjectCourseIds[] = $c['id'];
            if (count($subjectCourseIds) === 0) json(['data' => []]);
            $idsStr = implode(',', $subjectCourseIds);
            $quotedCampus = $db->quote($classCampus);
            // 查询已在当前课次考勤中的学员ID（含临时学员），一并排除
            $sessionStudentIds = [];
            $ssRes = $db->query("SELECT student_id FROM class_attendance WHERE class_id=$classId AND schedule_id=$scheduleId AND session_date=" . $db->quote($sessionDate));
            while ($s = $ssRes->fetch(PDO::FETCH_NUM)) $sessionStudentIds[] = $s[0];
            // 查询有剩余课时且不在该班级、不在当前课次的学员
            $excludeClause = "AND s.id NOT IN (SELECT cs.student_id FROM class_students cs WHERE cs.class_id = $classId AND cs.left_at = '')";
            if (count($sessionStudentIds) > 0) {
                $excludeClause .= " AND s.id NOT IN (" . implode(',', $sessionStudentIds) . ")";
            }
            $sql = "SELECT s.id, s.student_no, s.name, s.phone, SUM(o.lesson_count - o.consumed_lessons) AS remaining
                FROM students s
                JOIN orders o ON o.student_id = s.id
                WHERE o.course_id IN ($idsStr)
                  AND o.campus = $quotedCampus
                  AND o.lesson_count > o.consumed_lessons
                  $excludeClause" .
                  ($keyword !== '' ? " AND (s.student_no LIKE " . $db->quote("%$keyword%") . " OR s.name LIKE " . $db->quote("%$keyword%") . " OR s.phone LIKE " . $db->quote("%$keyword%") . ")" : "") . "
                GROUP BY s.id
                HAVING remaining > 0
                ORDER BY s.id ASC";
            $stmt = $db->query($sql);
            $rows = [];
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [
                    'id' => intval($r['id']),
                    'student_no' => $r['student_no'],
                    'name' => $r['name'],
                    'phone' => $r['phone'],
                    'remaining_lessons' => intval($r['remaining'])
                ];
            }
            json(['data' => $rows]);
            break;

        case 'get_class_attendance':
            $classId = intval($_GET['class_id'] ?? 0);
            $scheduleId = intval($_GET['schedule_id'] ?? 0);
            $sessionDate = trim($_GET['session_date'] ?? '');
            if ($classId <= 0) json(['error' => '班级ID无效']);
            if (!$sessionDate) json(['error' => '课次日期无效']);
            // 获取班级课程信息
            $classInfo = $db->query("SELECT c.course_id, co.name AS course_name, c.lesson_hours, co.subject_level1 AS subject_raw, c.campus FROM classes c LEFT JOIN courses co ON c.course_id = co.id WHERE c.id = $classId")->fetch(PDO::FETCH_ASSOC);
            $classCourseId = intval($classInfo['course_id'] ?? 0);
            $classCourseName = $classInfo['course_name'] ?? '';
            $classLessonHours = intval($classInfo['lesson_hours'] ?? 0);
            $classCampus = $classInfo['campus'] ?? '';
            // 解析一级学科ID（用于计算该学员一级学科下所有订单的剩余课时）
            $classFirstSubjectId = 0;
            $subjectRaw = $classInfo['subject_raw'] ?? '';
            if ($subjectRaw) {
                $sj = $db->query("SELECT id, parent_id FROM subjects WHERE name = " . $db->quote($subjectRaw))->fetch(PDO::FETCH_ASSOC);
                if ($sj) {
                    $classFirstSubjectId = intval($sj['parent_id']) == 0 ? intval($sj['id']) : intval($sj['parent_id']);
                }
            }
            // 获取班级所有学员（含出班但已有考勤记录的学员）
            $students = [];
            $studentIdsInClass = [];
            $stmt = $db->query("SELECT s.id, s.student_no, s.name, cs.id AS cs_id FROM class_students cs JOIN students s ON s.id = cs.student_id WHERE cs.class_id = $classId AND (cs.left_at = '' OR cs.left_at >= '$sessionDate') AND (cs.joined_at = '' OR cs.joined_at <= '$sessionDate') ORDER BY cs.id ASC");
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $students[] = $r; $studentIdsInClass[$r['id']] = true; }
            // 获取已有考勤记录
            $attMap = [];
            $attRes = $db->query("SELECT * FROM class_attendance WHERE class_id=$classId AND schedule_id=$scheduleId AND session_date='$sessionDate'");
            while ($r = $attRes->fetch(PDO::FETCH_ASSOC)) $attMap[$r['student_id']] = $r;
            // 补充：已有考勤记录但已出班的学员（课时消耗完被移出class_students）
            $extraStudentIds = [];
            foreach ($attMap as $sid => $rec) {
                if (!isset($studentIdsInClass[$sid])) $extraStudentIds[] = $sid;
            }
            if (count($extraStudentIds) > 0) {
                $extraRes = $db->query("SELECT id, student_no, name FROM students WHERE id IN (" . implode(',', $extraStudentIds) . ")");
                while ($r = $extraRes->fetch(PDO::FETCH_ASSOC)) { $students[] = $r; $studentIdsInClass[$r['id']] = false; }
            }
            // 补充：临时学员（is_temporary=1 的考勤记录）
            $tempAttRes = $db->query("SELECT * FROM class_attendance WHERE class_id=$classId AND schedule_id=$scheduleId AND session_date='$sessionDate' AND is_temporary=1");
            while ($r = $tempAttRes->fetch(PDO::FETCH_ASSOC)) {
                if (!isset($studentIdsInClass[$r['student_id']])) {
                    $attMap[$r['student_id']] = $r;
                    $extraStudentIds[] = $r['student_id'];
                }
            }
            // 查询临时学员基本信息
            if (count($extraStudentIds) > 0) {
                $existingIds = array_keys($studentIdsInClass);
                $newTempIds = array_filter($extraStudentIds, function($sid) use ($existingIds) { return !in_array($sid, $existingIds); });
                if (count($newTempIds) > 0) {
                    // 从 students 表查正常临时学员
                    $validIds = array_filter($newTempIds, function($id) { return $id > 0; });
                    if (count($validIds) > 0) {
                        $extraRes2 = $db->query("SELECT id, student_no, name FROM students WHERE id IN (" . implode(',', $validIds) . ")");
                        while ($r = $extraRes2->fetch(PDO::FETCH_ASSOC)) { $students[] = $r; $studentIdsInClass[$r['id']] = false; }
                    }
                    // student_id=0 的试听资源：直接从 class_attendance 取名
                    if (in_array(0, $newTempIds)) {
                        $students[] = ['id' => 0, 'student_no' => '试听', 'name' => ($attMap[0]['student_name'] ?? '试听学员')];
                        $studentIdsInClass[0] = false;
                    }
                }
            }
            // 预取一级学科下所有课程ID（用于计算 max_deductible）
            $flCourseIds = [];
            if ($classFirstSubjectId > 0) {
                $flRes = $db->query("SELECT id FROM courses WHERE subject_level1 IN (SELECT name FROM subjects WHERE parent_id = $classFirstSubjectId OR id = $classFirstSubjectId)");
                while ($c = $flRes->fetch(PDO::FETCH_ASSOC)) $flCourseIds[] = $c['id'];
            }
            $rows = [];
            foreach ($students as $stu) {
                $aid = $attMap[$stu['id']] ?? null;
                // 查询该学员在一级学科下、同校区的订单总剩余课时（用于展示和步进器上限）
                $totalRemaining = 0;
                if ($classFirstSubjectId > 0 && count($flCourseIds) > 0) {
                    $quotedCampus = $db->quote($classCampus);
                    $mdRow = $db->query("SELECT SUM(lesson_count - consumed_lessons) AS total FROM orders WHERE student_id = {$stu['id']} AND campus = $quotedCampus AND course_id IN (" . implode(',', $flCourseIds) . ") AND is_voided='否' AND id NOT IN (SELECT order_id FROM refund_records WHERE status NOT IN ('已退费', '审批驳回'))")->fetch(PDO::FETCH_ASSOC);
                    $totalRemaining = max(0, intval($mdRow['total'] ?? 0));
                }
                // 编辑时步进器上限 = 当前剩余 + 已扣值（因保存时会先退还再重扣）
                $maxDeductible = $totalRemaining;
                if ($aid) $maxDeductible += intval($aid['deducted_lessons']);
                $rows[] = [
                    'student_id' => $stu['id'],
                    'student_no' => $stu['student_no'],
                    'student_name' => $stu['name'],
                    'cs_id' => $stu['cs_id'] ?? 0,
                    'course_name' => $classCourseName,
                    'remaining_lessons' => $totalRemaining,
                    'lesson_hours' => $classLessonHours,
                    'max_deductible' => $maxDeductible,
                    'status' => $aid ? $aid['status'] : '',
                    'deducted_lessons' => $aid ? intval($aid['deducted_lessons']) : 0,
                    'deducted_order_id' => $aid ? intval($aid['deducted_order_id']) : 0,
                    'attendance_id' => $aid ? $aid['id'] : 0,
                    'is_temporary' => ($aid && intval($aid['is_temporary'] ?? 0) === 1) ? 1 : 0
                ];
            }
            json(['data' => $rows]);
            break;

        case 'save_temp_attendance':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $classId = intval($input['class_id'] ?? 0);
            $scheduleId = intval($input['schedule_id'] ?? 0);
            $sessionDate = trim($input['session_date'] ?? '');
            $studentId = intval($input['student_id'] ?? 0);
            if ($classId <= 0 || $studentId <= 0 || !$sessionDate) json(['error' => '参数无效']);
            $existing = $db->query("SELECT id FROM class_attendance WHERE class_id=$classId AND schedule_id=$scheduleId AND session_date='$sessionDate' AND student_id=$studentId")->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $db->exec("UPDATE class_attendance SET is_temporary=1 WHERE id=" . intval($existing['id']));
            } else {
                $db->exec("INSERT INTO class_attendance (class_id, schedule_id, session_date, student_id, status, deducted_lessons, is_temporary) VALUES ($classId, $scheduleId, '$sessionDate', $studentId, '', 0, 1)");
            }
            json(['success' => true]);
            break;

        case 'save_class_attendance':
            if ($method !== 'POST') json(['error' => 'Method not allowed']);
            $classId = intval($input['class_id'] ?? 0);
            $scheduleId = intval($input['schedule_id'] ?? 0);
            $sessionDate = trim($input['session_date'] ?? '');
            $records = $input['records'] ?? [];
            if ($classId <= 0) json(['error' => '班级ID无效']);
            if ($scheduleId <= 0) json(['error' => '排课ID无效']);
            if (!$sessionDate) json(['error' => '课次日期无效']);
            if (!is_array($records) || count($records) === 0) json(['error' => '考勤记录为空']);
            $db->exec('START TRANSACTION');
            try {
                foreach ($records as $rec) {
                    $studentId = intval($rec['student_id'] ?? 0);
                    $status = trim($rec['status'] ?? '出勤');
                    // 试听资源 student_id=0 允许更新状态，但跳过课时扣减
                    $isTrialResource = ($studentId === 0);
                    if (!in_array($status, ['', '出勤', '请假', '缺勤'])) $status = '出勤';
                    if ($isTrialResource) {
                        $db->exec("UPDATE class_attendance SET status='$status' WHERE class_id=$classId AND schedule_id=$scheduleId AND session_date='$sessionDate' AND student_id=0 AND is_temporary=1");
                        continue;
                    }
                    if ($studentId <= 0) continue;
                    // 判断是否为临时学员（不在 class_students 中但有 is_temporary 标记）
                    $isTempRecord = !empty($rec['is_temporary']) && intval($rec['is_temporary']) === 1;
                    if ($isTempRecord) {
                        $inClass = $db->query("SELECT id FROM class_students WHERE class_id=$classId AND student_id=$studentId AND left_at=''")->fetch(PDO::FETCH_ASSOC);
                        $isTempRecord = !$inClass;
                    }
                    $isTemp = $isTempRecord ? 1 : 0;
                    // 查询该学员在此班级课程的一级学科
                    $classRow = $db->query("SELECT c.course_id, c.name AS course_name, c.lesson_hours, co.subject_level1, co.subject_level2, c.campus FROM classes c LEFT JOIN courses co ON c.course_id = co.id WHERE c.id = $classId")->fetch(PDO::FETCH_ASSOC);
                    $courseId = intval($classRow['course_id'] ?? 0);
                    $subjectLevel1 = $classRow['subject_level1'] ?? '';
                    $classCampus = $classRow['campus'] ?? '';
                    // 获取一级学科
                    $firstSubjectId = 0;
                    $courseSubjId = 0; // 课程所属学科ID（可能就是二级学科）
                    $subjRow = $db->query("SELECT id, parent_id FROM subjects WHERE name = " . $db->quote($subjectLevel1))->fetch(PDO::FETCH_ASSOC);
                    if ($subjRow) {
                        $courseSubjId = intval($subjRow['id']);
                        if (intval($subjRow['parent_id']) == 0) {
                            $firstSubjectId = intval($subjRow['id']);
                        } else {
                            $firstSubjectId = intval($subjRow['parent_id']);
                        }
                    }
                    $deductedLessons = intval($rec['deducted_lessons'] ?? 0);
                    $deductedOrderId = 0;
                    if ($status === '出勤' && $deductedLessons <= 0) {
                        $deductedLessons = max(1, intval($classRow['lesson_hours'] ?? 0));
                    }
                    // 退还已扣课时（改状态为缺勤/请假时，按 deduction_json 逐笔归还）
                    // 必须在计算新扣课时之前执行，否则新扣课时计算会基于错误的 consumed_lessons 值
                    $oldAtt = $db->query("SELECT id, deducted_lessons, deduction_json FROM class_attendance WHERE class_id=$classId AND schedule_id=$scheduleId AND session_date='$sessionDate' AND student_id=$studentId")->fetch(PDO::FETCH_ASSOC);
                    // 查所有旧记录（不止一条时用 fetchAll 检查）
                    $oldAttAll = $db->query("SELECT id, deducted_lessons, deduction_json FROM class_attendance WHERE class_id=$classId AND schedule_id=$scheduleId AND session_date='$sessionDate' AND student_id=$studentId")->fetchAll(PDO::FETCH_ASSOC);
                    $logLine = date('Y-m-d H:i:s') . " SCHED=$scheduleId DATE=$sessionDate SID=$studentId CID=$courseId\n";
                    $snapBefore = $db->query("SELECT id, consumed_lessons FROM orders WHERE student_id = $studentId AND course_id = $courseId")->fetchAll(PDO::FETCH_ASSOC);
                    $logLine .= "BEFORE_REVERT: " . json_encode($snapBefore) . "\n";
                    $logLine .= "REVERT oldAtt_count=" . count($oldAttAll) . " oldAtt_ids=" . implode(',', array_column($oldAttAll, 'id')) . " oldAtt_json=" . json_encode($oldAttAll) . "\n";
                    if ($oldAtt && !empty($oldAtt['deduction_json'])) {
                        $oldEntries = json_decode($oldAtt['deduction_json'], true);
                        if (is_array($oldEntries)) {
                            foreach ($oldEntries as $entry) {
                                $oid = intval($entry['order_id'] ?? 0);
                                $amt = intval($entry['amount'] ?? 0);
                                if ($oid > 0 && $amt > 0) {
                                    $stmtR = $db->prepare("UPDATE orders SET consumed_lessons = consumed_lessons - $amt WHERE id = $oid");
                                    $stmtR->execute();
                                    $rcR = $stmtR->rowCount();
                                    $afterRevert = $db->query("SELECT consumed_lessons FROM orders WHERE id = $oid")->fetch(PDO::FETCH_ASSOC);
                                    $logLine .= "REVERTED order=$oid amt=$amt rowsAffected=$rcR newVal=" . ($afterRevert['consumed_lessons'] ?? 'N/A') . "\n";
                                }
                            }
                        }
                    }
                    // 出勤上限校验：扣除课时数不得超过一级学科剩余课时（退还后重新计算）
                    if ($status === '出勤' && $deductedLessons > 0 && $firstSubjectId > 0) {
                        $allSubjCourseIds = [];
                        $srMax = $db->query("SELECT id FROM courses WHERE subject_level1 IN (SELECT name FROM subjects WHERE parent_id = $firstSubjectId OR id = $firstSubjectId)");
                        while ($c = $srMax->fetch(PDO::FETCH_ASSOC)) $allSubjCourseIds[] = $c['id'];
                        if (count($allSubjCourseIds) > 0) {
                            $quotedCampus = $db->quote($classCampus);
                            $maxRow = $db->query("SELECT SUM(lesson_count - consumed_lessons) AS max_deductible FROM orders WHERE student_id = $studentId AND campus = $quotedCampus AND course_id IN (" . implode(',', $allSubjCourseIds) . ") AND is_voided='否' AND id NOT IN (SELECT order_id FROM refund_records WHERE status NOT IN ('已退费', '审批驳回'))")->fetch(PDO::FETCH_ASSOC);
                            $maxDeductible = intval($maxRow['max_deductible'] ?? 0);
                            if ($deductedLessons > $maxDeductible) {
                                throw new Exception("学员「{$rec['student_name']}」剩余课时不足：最多可扣 $maxDeductible 课时，当前请求扣 $deductedLessons 课时");
                            }
                        }
                    }
                    if ($status === '出勤' && $deductedLessons > 0) {
                        // 扣课时逻辑（三级优先级，跨订单连续扣，限定同校区）：
                        // 1. 优先扣同一course_id的订单（有多个时，先报名的优先）
                        // 2. 继续扣同二级学科的订单（先报名的优先）
                        // 3. 继续扣同一级学科的订单（先报名的优先）
                        $allOrderRows = [];

                        // 扣课时按优先级逐级扣减（每级内部先报名优先）
                        $remainingToDeduct = $deductedLessons;
                        $deductionEntries = [];
                        $deductedOrderId = 0;
                        $processedOrderIds = [];
                        $quotedCampus = $db->quote($classCampus);

                        // 优先级1：同一course_id的订单（同校区，排除退费中/已退费）
                        $debugExcluded = $db->query("SELECT order_id FROM refund_records WHERE status NOT IN ('已退费', '审批驳回')")->fetchAll(PDO::FETCH_COLUMN);
                        $logLine .= "excluded_ids=" . json_encode($debugExcluded) . "\n";
                        $logLine .= "AFTER_REVERT: " . json_encode($db->query("SELECT id, consumed_lessons FROM orders WHERE student_id = $studentId AND course_id = $courseId")->fetchAll(PDO::FETCH_ASSOC)) . "\n";
                        $oRes = $db->query("SELECT * FROM orders WHERE student_id = $studentId AND course_id = $courseId AND lesson_count > consumed_lessons AND campus = $quotedCampus AND id NOT IN (SELECT order_id FROM refund_records WHERE status NOT IN ('已退费', '审批驳回')) ORDER BY created_at ASC, id ASC");
                        while ($o = $oRes->fetch(PDO::FETCH_ASSOC)) {
                            if ($remainingToDeduct <= 0) break;
                            $available = intval($o['lesson_count']) - intval($o['consumed_lessons']);
                            if ($available <= 0) continue;
                            $toDeduct = min($remainingToDeduct, $available);
                            $newConsumed = intval($o['consumed_lessons']) + $toDeduct;
                            $oid = intval($o['id']);
                            $stmtD = $db->prepare("UPDATE orders SET consumed_lessons = $newConsumed WHERE id = $oid AND id NOT IN (SELECT order_id FROM refund_records WHERE status NOT IN ('已退费', '审批驳回'))");
                            $stmtD->execute();
                            $rcD = $stmtD->rowCount();
                            $afterDed = $db->query("SELECT consumed_lessons FROM orders WHERE id = $oid")->fetch(PDO::FETCH_ASSOC);
                            $logLine .= "DEDUCT priority=1 order=$oid from=" . intval($o['consumed_lessons']) . " to=$newConsumed rowsAffected=$rcD actualVal=" . ($afterDed['consumed_lessons'] ?? 'N/A') . "\n";
                            $deductionEntries[] = ['order_id' => $oid, 'amount' => $toDeduct];
                            if ($deductedOrderId === 0) $deductedOrderId = $oid;
                            $remainingToDeduct -= $toDeduct;
                            $processedOrderIds[] = $oid;
                        }

                        // 优先级2：同一二级学科的订单（先报名优先，排除已处理订单）
                        if ($remainingToDeduct > 0 && $courseSubjId > 0 && $firstSubjectId > 0 && $courseSubjId != $firstSubjectId) {
                            $sameSecondCourses = [];
                            $sr2 = $db->query("SELECT id FROM courses WHERE subject_level1 IN (SELECT name FROM subjects WHERE id = $courseSubjId)");
                            while ($c = $sr2->fetch(PDO::FETCH_ASSOC)) $sameSecondCourses[] = $c['id'];
                            if (count($sameSecondCourses) > 0) {
                                $excludeClause = count($processedOrderIds) > 0 ? "AND id NOT IN (" . implode(',', $processedOrderIds) . ")" : "";
                                $oRes2 = $db->query("SELECT * FROM orders WHERE student_id = $studentId AND course_id IN (" . implode(',', $sameSecondCourses) . ") AND lesson_count > consumed_lessons $excludeClause AND campus = $quotedCampus AND id NOT IN (SELECT order_id FROM refund_records WHERE status NOT IN ('已退费', '审批驳回')) ORDER BY created_at ASC, id ASC");
                                while ($o = $oRes2->fetch(PDO::FETCH_ASSOC)) {
                                    if ($remainingToDeduct <= 0) break;
                                    $available = intval($o['lesson_count']) - intval($o['consumed_lessons']);
                                    if ($available <= 0) continue;
                                    $toDeduct = min($remainingToDeduct, $available);
                                    $newConsumed = intval($o['consumed_lessons']) + $toDeduct;
                                    $oid = intval($o['id']);
                                    $stmtDx = $db->prepare("UPDATE orders SET consumed_lessons = $newConsumed WHERE id = $oid AND id NOT IN (SELECT order_id FROM refund_records WHERE status NOT IN ('已退费', '审批驳回'))");
                                    $stmtDx->execute();
                                    $rcDx = $stmtDx->rowCount();
                                    $afterDedx = $db->query("SELECT consumed_lessons FROM orders WHERE id = $oid")->fetch(PDO::FETCH_ASSOC);
                                    $logLine .= "DEDUCT priority=P order=$oid from=" . intval($o['consumed_lessons']) . " to=$newConsumed rowsAffected=$rcDx actualVal=" . ($afterDedx['consumed_lessons'] ?? 'N/A') . "\n";
                                    $deductionEntries[] = ['order_id' => $oid, 'amount' => $toDeduct];
                                    if ($deductedOrderId === 0) $deductedOrderId = $oid;
                                    $remainingToDeduct -= $toDeduct;
                                    $processedOrderIds[] = $oid;
                                }
                            }
                        }

                        // 优先级3：同一级学科的订单（先报名优先，排除已处理订单）
                        if ($remainingToDeduct > 0 && $firstSubjectId > 0) {
                            $firstLevelCourses = [];
                            $sr3 = $db->query("SELECT id FROM courses WHERE subject_level1 IN (SELECT name FROM subjects WHERE parent_id = $firstSubjectId OR id = $firstSubjectId)");
                            while ($c = $sr3->fetch(PDO::FETCH_ASSOC)) $firstLevelCourses[] = $c['id'];
                            if (count($firstLevelCourses) > 0) {
                                $excludeClause = count($processedOrderIds) > 0 ? "AND id NOT IN (" . implode(',', $processedOrderIds) . ")" : "";
                                $oRes3 = $db->query("SELECT * FROM orders WHERE student_id = $studentId AND course_id IN (" . implode(',', $firstLevelCourses) . ") AND lesson_count > consumed_lessons $excludeClause AND campus = $quotedCampus AND id NOT IN (SELECT order_id FROM refund_records WHERE status NOT IN ('已退费', '审批驳回')) ORDER BY created_at ASC, id ASC");
                                while ($o = $oRes3->fetch(PDO::FETCH_ASSOC)) {
                                    if ($remainingToDeduct <= 0) break;
                                    $available = intval($o['lesson_count']) - intval($o['consumed_lessons']);
                                    if ($available <= 0) continue;
                                    $toDeduct = min($remainingToDeduct, $available);
                                    $newConsumed = intval($o['consumed_lessons']) + $toDeduct;
                                    $oid = intval($o['id']);
                                    $stmtDx = $db->prepare("UPDATE orders SET consumed_lessons = $newConsumed WHERE id = $oid AND id NOT IN (SELECT order_id FROM refund_records WHERE status NOT IN ('已退费', '审批驳回'))");
                                    $stmtDx->execute();
                                    $rcDx = $stmtDx->rowCount();
                                    $afterDedx = $db->query("SELECT consumed_lessons FROM orders WHERE id = $oid")->fetch(PDO::FETCH_ASSOC);
                                    $logLine .= "DEDUCT priority=P order=$oid from=" . intval($o['consumed_lessons']) . " to=$newConsumed rowsAffected=$rcDx actualVal=" . ($afterDedx['consumed_lessons'] ?? 'N/A') . "\n";
                                    $deductionEntries[] = ['order_id' => $oid, 'amount' => $toDeduct];
                                    if ($deductedOrderId === 0) $deductedOrderId = $oid;
                                    $remainingToDeduct -= $toDeduct;
                                    $processedOrderIds[] = $oid;
                                }
                            }
                        }
                        $deductionJson = json_encode($deductionEntries);
                        $snapAfter = $db->query("SELECT id, consumed_lessons FROM orders WHERE student_id = $studentId AND course_id = $courseId")->fetchAll(PDO::FETCH_ASSOC);
                        $logLine .= "AFTER_DEDUCT: " . json_encode($snapAfter) . "\n";
                        $logLine .= "deductionJson=" . $deductionJson . " deducted_order_id=$deductedOrderId\n\n";
                        file_put_contents('D:/market-system-php/debug_save.log', $logLine, FILE_APPEND);
                    } else {
                        $deductionJson = '';
                    }
                    // 删除旧的考勤记录
                    $db->exec("DELETE FROM class_attendance WHERE class_id=$classId AND schedule_id=$scheduleId AND session_date='$sessionDate' AND student_id=$studentId");
                    $n = now();
                    $stmt = $db->prepare("INSERT INTO class_attendance (class_id, schedule_id, session_date, student_id, status, deducted_lessons, deducted_order_id, deduction_json, is_temporary, created_at) VALUES (:cid, :scid, :sd, :stid, :st, :dl, :doid, :dj, :it, :ca)");
                    $stmt->bindValue(':cid', $classId, PDO::PARAM_INT);
                    $stmt->bindValue(':scid', $scheduleId, PDO::PARAM_INT);
                    $stmt->bindValue(':sd', $sessionDate, PDO::PARAM_STR);
                    $stmt->bindValue(':stid', $studentId, PDO::PARAM_INT);
                    $stmt->bindValue(':st', $status, PDO::PARAM_STR);
                    $stmt->bindValue(':dl', $deductedLessons, PDO::PARAM_INT);
                    $stmt->bindValue(':doid', $deductedOrderId, PDO::PARAM_INT);
                    $stmt->bindValue(':dj', $deductionJson, PDO::PARAM_STR);
                    $stmt->bindValue(':it', $isTemp, PDO::PARAM_INT);
                    $stmt->bindValue(':ca', $n, PDO::PARAM_STR);
                    $stmt->execute();
                    // 同步写入学员考勤明细记录（attendance_records）
                    // 课程信息优先使用实际扣除的订单对应课程，而非班级课程
                    $className = $classRow['course_name'] ?? ''; // c.name 即班级名称（别名误导）
                    $attCourseId = $courseId;
                    $attSubjL1 = $subjectLevel1 ?? '';
                    $attSubjL2 = $classRow['subject_level2'] ?? '';
                    if ($deductedOrderId > 0) {
                        $deductedOrderCourse = $db->query("SELECT co.id, co.subject_level1, co.subject_level2 FROM orders o LEFT JOIN courses co ON o.course_id = co.id WHERE o.id = $deductedOrderId")->fetch(PDO::FETCH_ASSOC);
                        if ($deductedOrderCourse) {
                            $attCourseId = intval($deductedOrderCourse['id']);
                            $attSubjL1 = $deductedOrderCourse['subject_level1'] ?? '';
                            $attSubjL2 = $deductedOrderCourse['subject_level2'] ?? '';
                        }
                    }
                    $schedRow = $db->query("SELECT teacher, time_slots FROM schedules WHERE id=$scheduleId")->fetch(PDO::FETCH_ASSOC);
                    $teacher = $schedRow['teacher'] ?? '';
                    // 根据上课日期的星期几，从排课的 time_slots JSON 中取对应时间段
                    $classTime = '';
                    $timeSlots = json_decode($schedRow['time_slots'] ?? '{}', true) ?: [];
                    $dow = date('N', strtotime($sessionDate));
                    $dowKey = (string)$dow;
                    $slot = $timeSlots[$dowKey] ?? [];
                    if (!empty($slot['start']) && !empty($slot['end'])) {
                        $classTime = $slot['start'] . '-' . $slot['end'];
                    } elseif (!empty($slot['start'])) {
                        $classTime = $slot['start'];
                    }
                    $consumedAmount = 0;
                    if (!empty($deductionEntries)) {
                        foreach ($deductionEntries as $de) {
                            $orderRow = $db->query("SELECT actual_price, lesson_count FROM orders WHERE id={$de['order_id']}")->fetch(PDO::FETCH_ASSOC);
                            if ($orderRow && $orderRow['lesson_count'] > 0) {
                                $unitPrice = floatval($orderRow['actual_price']) / intval($orderRow['lesson_count']);
                                $consumedAmount += $unitPrice * floatval($de['amount']);
                            }
                        }
                    }
                    // 查询旧的考勤记录状态
                    $oldAttRec = $db->query("SELECT id, status FROM attendance_records WHERE student_id=$studentId AND class_id=$classId AND schedule_id=$scheduleId AND lesson_date='$sessionDate'")->fetch(PDO::FETCH_ASSOC);
                    $oldAttStatus = $oldAttRec ? $oldAttRec['status'] : '';
                    $oldAttRecId = $oldAttRec ? intval($oldAttRec['id']) : 0;
                    $db->exec("DELETE FROM attendance_records WHERE student_id=$studentId AND class_id=$classId AND schedule_id=$scheduleId AND lesson_date='$sessionDate'");
                    // 删除旧的缺勤记录（无论旧状态是什么，先清理）
                    if ($oldAttRecId > 0) {
                        $db->exec("DELETE FROM absence_records WHERE attendance_id=$oldAttRecId");
                    }
                    $db->exec("DELETE FROM absence_records WHERE student_id=$studentId AND class_id=$classId AND schedule_id=$scheduleId AND lesson_date='$sessionDate'");
                    $newAttRecId = 0;
                    if ($status === '出勤' && $deductedLessons > 0) {
                        $arStmt = $db->prepare("INSERT INTO attendance_records (student_id, course_id, class_id, schedule_id, class_name, campus, teacher, subject_level1, subject_level2, class_time, lesson_date, attended_at, status, deducted_lessons, consumed_amount, created_at, order_id) VALUES (:sid, :cid, :clid, :scid, :cn, :cp, :t, :sl1, :sl2, :ct, :ld, :aa, :st, :dl, :ca2, :ca, :oid)");
                        $arStmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
                        $arStmt->bindValue(':cid', $attCourseId, PDO::PARAM_INT);
                        $arStmt->bindValue(':clid', $classId, PDO::PARAM_INT);
                        $arStmt->bindValue(':scid', $scheduleId, PDO::PARAM_INT);
                        $arStmt->bindValue(':cn', $className, PDO::PARAM_STR);
                        $arStmt->bindValue(':cp', $classCampus, PDO::PARAM_STR);
                        $arStmt->bindValue(':t', $teacher, PDO::PARAM_STR);
                        $arStmt->bindValue(':sl1', $attSubjL1, PDO::PARAM_STR);
                        $arStmt->bindValue(':sl2', $attSubjL2, PDO::PARAM_STR);
                        $arStmt->bindValue(':ct', $classTime, PDO::PARAM_STR);
                        $arStmt->bindValue(':ld', $sessionDate, PDO::PARAM_STR);
                        $arStmt->bindValue(':aa', $n, PDO::PARAM_STR);
                        $arStmt->bindValue(':st', $status, PDO::PARAM_STR);
                        $arStmt->bindValue(':dl', $deductedLessons, PDO::PARAM_INT);
                        $arStmt->bindValue(':ca2', round($consumedAmount, 2), PDO::PARAM_STR);
                        $arStmt->bindValue(':ca', $n, PDO::PARAM_STR);
                        $arStmt->bindValue(':oid', $deductedOrderId, PDO::PARAM_INT);
                        $arStmt->execute();
                        $newAttRecId = intval($db->lastInsertId());
                    }
                    // 缺勤状态：写入缺勤记录表
                    if ($status === '缺勤') {
                        $studentName = '';
                        $phone = '';
                        $sr = $db->query("SELECT name, phone FROM students WHERE id=$studentId")->fetch(PDO::FETCH_ASSOC);
                        if ($sr) { $studentName = $sr['name']; $phone = $sr['phone']; }
                        $absStmt = $db->prepare("INSERT INTO absence_records (student_id, course_id, class_id, schedule_id, class_name, campus, teacher, subject_level1, subject_level2, class_time, lesson_date, student_name, phone, attendance_id, created_at) VALUES (:sid, :cid, :clid, :scid, :cn, :cp, :t, :sl1, :sl2, :ct, :dt, :sn, :ph, :aid, :ca)");
                        $absStmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
                        $absStmt->bindValue(':cid', $attCourseId, PDO::PARAM_INT);
                        $absStmt->bindValue(':clid', $classId, PDO::PARAM_INT);
                        $absStmt->bindValue(':scid', $scheduleId, PDO::PARAM_INT);
                        $absStmt->bindValue(':cn', $className, PDO::PARAM_STR);
                        $absStmt->bindValue(':cp', $classCampus, PDO::PARAM_STR);
                        $absStmt->bindValue(':t', $teacher, PDO::PARAM_STR);
                        $absStmt->bindValue(':sl1', $attSubjL1, PDO::PARAM_STR);
                        $absStmt->bindValue(':sl2', $attSubjL2, PDO::PARAM_STR);
                        $absStmt->bindValue(':ct', $classTime, PDO::PARAM_STR);
                        $absStmt->bindValue(':dt', $sessionDate, PDO::PARAM_STR);
                        $absStmt->bindValue(':sn', $studentName, PDO::PARAM_STR);
                        $absStmt->bindValue(':ph', $phone, PDO::PARAM_STR);
                        $absStmt->bindValue(':aid', $newAttRecId, PDO::PARAM_INT);
                        $absStmt->bindValue(':ca', $n, PDO::PARAM_STR);
                        $absStmt->execute();
                    }
                    // 考勤完成后，判断是否需要移出班级（按校区统计剩余课时）
                    if ($firstSubjectId > 0) {
                        $allCourseIds = [];
                        $sr3 = $db->query("SELECT id FROM courses WHERE subject_level1 IN (SELECT name FROM subjects WHERE parent_id = $firstSubjectId OR id = $firstSubjectId)");
                        while ($c = $sr3->fetch(PDO::FETCH_ASSOC)) $allCourseIds[] = $c['id'];
                        if (count($allCourseIds) > 0) {
                            $quotedCampus = $db->quote($classCampus);
                            $sumRow = $db->query("SELECT SUM(lesson_count - consumed_lessons) AS total_remaining FROM orders WHERE student_id = $studentId AND campus = $quotedCampus AND course_id IN (" . implode(',', $allCourseIds) . ") AND is_voided='否' AND id NOT IN (SELECT order_id FROM refund_records WHERE status NOT IN ('已退费', '审批驳回'))")->fetch(PDO::FETCH_ASSOC);
                            $totalRemaining = intval($sumRow['total_remaining'] ?? 0);
                            if ($totalRemaining <= 0) {
                                // 移出该学员在此一级学科同校区下所有班级
                                $db->exec("DELETE FROM class_students WHERE student_id = $studentId AND class_id IN (SELECT id FROM classes WHERE campus = $quotedCampus AND course_id IN (" . implode(',', $allCourseIds) . "))");
                            }
                        }
                    }
                }
                $db->exec('COMMIT');
                json(['message' => '考勤保存成功']);
            } catch (Exception $e) {
                $db->exec('ROLLBACK');
                json(['error' => '考勤保存失败：' . $e->getMessage()]);
            }
            break;

        case 'get_class_enrollable':
            $classId = intval($_GET['class_id'] ?? 0);
            $studentId = intval($_GET['student_id'] ?? 0);
            if ($classId <= 0) json(['error' => '班级ID无效']);
            if ($studentId <= 0) json(['error' => '学员ID无效']);
            // 获取班级课程的一级学科及校区
            $classRow = $db->query("SELECT c.course_id, co.subject_level1, co.subject_level2, c.campus FROM classes c LEFT JOIN courses co ON c.course_id = co.id WHERE c.id = $classId")->fetch(PDO::FETCH_ASSOC);
            $subjectLevel1 = $classRow['subject_level1'] ?? '';
            $classCampus = $classRow['campus'] ?? '';
            $firstSubjectId = 0;
            $subjRow = $db->query("SELECT id, parent_id FROM subjects WHERE name = " . $db->quote($subjectLevel1))->fetch(PDO::FETCH_ASSOC);
            if ($subjRow) {
                if (intval($subjRow['parent_id']) == 0) {
                    $firstSubjectId = intval($subjRow['id']);
                } else {
                    $firstSubjectId = intval($subjRow['parent_id']);
                }
            }
            $totalRemaining = 0;
            if ($firstSubjectId > 0) {
                $allCourseIds = [];
                $sr = $db->query("SELECT id FROM courses WHERE subject_level1 IN (SELECT name FROM subjects WHERE parent_id = $firstSubjectId OR id = $firstSubjectId)");
                while ($c = $sr->fetch(PDO::FETCH_ASSOC)) $allCourseIds[] = $c['id'];
                if (count($allCourseIds) > 0) {
                    $quotedCampus = $db->quote($classCampus);
                    $sumRow = $db->query("SELECT SUM(lesson_count - consumed_lessons) AS total_remaining FROM orders WHERE student_id = $studentId AND campus = $quotedCampus AND course_id IN (" . implode(',', $allCourseIds) . ") AND is_voided='否' AND id NOT IN (SELECT order_id FROM refund_records WHERE status NOT IN ('已退费', '审批驳回'))")->fetch(PDO::FETCH_ASSOC);
                    $totalRemaining = intval($sumRow['total_remaining'] ?? 0);
                }
            }
            $enrollable = $totalRemaining > 0;
            json(['enrollable' => $enrollable, 'remaining_lessons' => $totalRemaining]);
            break;

        // ==================== 考勤管理（按日期-全量） API ====================
        case 'list_attendance_sessions':
            $dateFrom = trim($_GET['date_from'] ?? '');
            $dateTo = trim($_GET['date_to'] ?? '');
            $className = trim($_GET['class_name'] ?? '');
            $page = max(1, intval($_GET['page'] ?? 1));
            $pageSize = intval($_GET['page_size'] ?? 20);
            $sessions = [];
            $stmt = $db->query("SELECT s.*, c.name AS class_name, c.campus, co.subject_level1 AS course_subject_level1, co.subject_level2 AS course_subject_level2, co.name AS course_name 
                FROM schedules s 
                JOIN classes c ON s.class_id = c.id 
                LEFT JOIN courses co ON c.course_id = co.id 
                ORDER BY c.name, s.id");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $sesList = computeSessions($row);
                foreach ($sesList as $ses) {
                    $d = $ses['date'];
                    if ($dateFrom && $d < $dateFrom) continue;
                    if ($dateTo && $d > $dateTo) continue;
                    $sessions[] = [
                        'schedule_id' => intval($row['id']),
                        'class_id' => intval($row['class_id']),
                        'class_name' => $row['class_name'],
                        'campus' => $row['campus'],
                        'course_name' => $row['course_name'] ?: trim(($row['course_subject_level1'] ?? '') . ' ' . ($row['course_subject_level2'] ?? '')),
                        'course_subject_level1' => $row['course_subject_level1'] ?? '',
                        'course_subject_level2' => $row['course_subject_level2'] ?? '',
                        'teacher' => $row['teacher'],
                        'classroom' => $row['classroom'],
                        'session_date' => $d,
                        'day_of_week' => $ses['dayOfWeek'],
                        'start_time' => $ses['start'],
                        'end_time' => $ses['end'],
                    ];
                }
            }
            if ($className) {
                $sessions = array_values(array_filter($sessions, function($s) use ($className) {
                    return stripos($s['class_name'], $className) !== false;
                }));
            }
            usort($sessions, function($a, $b) { return strcmp($a['session_date'], $b['session_date']); });
            $total = count($sessions);
            $offset = ($page - 1) * $pageSize;
            $paged = array_slice($sessions, $offset, $pageSize);
            json(['data' => $paged, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
            break;

        case 'list_all_attendance':
            $dateFrom = trim($_GET['date_from'] ?? '');
            $dateTo = trim($_GET['date_to'] ?? '');
            $page = max(1, intval($_GET['page'] ?? 1));
            $pageSize = intval($_GET['page_size'] ?? 20);
            $where = [];
            if ($dateFrom) $where[] = "a.lesson_date >= '$dateFrom'";
            if ($dateTo) $where[] = "a.lesson_date <= '$dateTo'";
            $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
            $sql = "SELECT a.*, c.name AS course_name, s.name AS student_name, s.student_no, s.phone, cl.campus FROM attendance_records a LEFT JOIN courses c ON a.course_id = c.id LEFT JOIN students s ON a.student_id = s.id LEFT JOIN classes cl ON a.class_id = cl.id $whereSql ORDER BY a.lesson_date DESC, a.id DESC";
            $stmt = $db->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total = count($rows);
            $offset = ($page - 1) * $pageSize;
            $paged = array_slice($rows, $offset, $pageSize);
            json(['data' => $paged, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
            break;

        case 'get_revenue_stats':
            // 确收统计逻辑与现金流统计完全相同，复用同一实现
            $_GET['action'] = 'get_cashflow_stats';
            // fall through to get_cashflow_stats
        case 'get_cashflow_stats':
            $granularity = $_GET['granularity'] ?? 'monthly';
            $campusRaw = $_GET['campus'] ?? $_GET['campuses'] ?? '';
            $dateFrom = $_GET['date_from'] ?? '';
            $dateTo = $_GET['date_to'] ?? '';
            $campuses = $campusRaw !== '' ? array_filter(array_map('trim', explode(',', $campusRaw))) : [];
            // 默认近12个月
            if (!$dateFrom) $dateFrom = date('Y-m', strtotime('-11 months'));
            if (!$dateTo) $dateTo = date('Y-m');

            if ($granularity === 'yearly') {
                $fromYear = substr($dateFrom, 0, 4);
                $toYear = substr($dateTo, 0, 4);
                $from = $fromYear . '-01-01';
                $to = $toYear . '-12-31';
                $groupByExpr = "DATE_FORMAT(o.paid_at, '%Y')";
            } elseif ($granularity === 'daily') {
                $from = (strlen($dateFrom) === 10) ? $dateFrom : ($dateFrom . '-01');
                $to   = (strlen($dateTo) === 10)   ? $dateTo   : date('Y-m-t', strtotime($dateTo . '-01'));
                $groupByExpr = "DATE_FORMAT(o.paid_at, '%Y-%m-%d')";
            } else {
                $from = $dateFrom . '-01';
                $to = date('Y-m-t', strtotime($dateTo . '-01'));
                $groupByExpr = "DATE_FORMAT(o.paid_at, '%Y-%m')";
            }

            $campusWhere = '';
            $campusParams = [];
            $campusWhereE = '';
            $campusParamsE = [];
            if (!empty($campuses)) {
                $placeholders = [];
                $placeholdersE = [];
                foreach ($campuses as $i => $c) {
                    $pk = ':c' . $i;
                    $pkE = ':ce' . $i;
                    $placeholders[] = $pk;
                    $placeholdersE[] = $pkE;
                    $campusParams[$pk] = $c;
                    $campusParamsE[$pkE] = $c;
                }
                $campusWhere = "AND o.campus IN (" . implode(',', $placeholders) . ")";
                $campusWhereE = "AND rr.campus IN (" . implode(',', $placeholdersE) . ")";
            }

            // 收入：已支付且未作废且非已退费的订单，按校区+日期统计
            $incomeSql = "SELECT o.campus, $groupByExpr AS period, COUNT(*) AS cnt, COALESCE(SUM(o.actual_price), 0) AS amount
                FROM orders o
                WHERE o.pay_status = '已支付' AND o.is_voided = '否'
                  AND o.paid_at >= :from AND o.paid_at <= :to2
                  $campusWhere
                GROUP BY o.campus, $groupByExpr ORDER BY o.campus, period ASC";
            $stmt = $db->prepare($incomeSql);
            $stmt->bindValue(':from', $from . ' 00:00:00');
            $stmt->bindValue(':to2', $to . ' 23:59:59');
            foreach ($campusParams as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $incomeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 支出：已退费的退款记录，按校区+日期统计
            $expenseGroupByExpr = str_replace('o.paid_at', 'rr.updated_at', $groupByExpr);
            $expenseSql = "SELECT rr.campus, $expenseGroupByExpr AS period, COUNT(*) AS cnt, COALESCE(SUM(rr.actual_refund), 0) AS amount
                FROM refund_records rr
                WHERE rr.status = '已退费'
                  AND rr.updated_at >= :from AND rr.updated_at <= :to2
                  $campusWhereE
                GROUP BY rr.campus, $expenseGroupByExpr ORDER BY rr.campus, period ASC";
            $stmt = $db->prepare($expenseSql);
            $stmt->bindValue(':from', $from . ' 00:00:00');
            $stmt->bindValue(':to2', $to . ' 23:59:59');
            foreach ($campusParamsE as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $expenseRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 按订单类型的收入明细（用于堆叠柱状图）
            $incomeByTypeSql = "SELECT o.order_type, $groupByExpr AS period, COALESCE(SUM(o.actual_price), 0) AS amount, COUNT(*) AS cnt
                FROM orders o
                WHERE o.pay_status = '已支付' AND o.is_voided = '否'
                  AND o.paid_at >= :from AND o.paid_at <= :to2
                  $campusWhere
                GROUP BY o.order_type, $groupByExpr ORDER BY o.order_type, period ASC";
            $stmt = $db->prepare($incomeByTypeSql);
            $stmt->bindValue(':from', $from . ' 00:00:00');
            $stmt->bindValue(':to2', $to . ' 23:59:59');
            foreach ($campusParams as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $incomeByTypeRows = [];
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $incomeByTypeRows[] = [
                    'order_type' => $r['order_type'] ?: '其他',
                    'date' => $r['period'],
                    'amount' => round(floatval($r['amount']), 2),
                    'cnt' => intval($r['cnt']),
                ];
            }

            // 合并数据：key = campus|period
            $incomeMap = []; foreach ($incomeRows as $r) $incomeMap[$r['campus'] . '|' . $r['period']] = $r;
            $expenseMap = []; foreach ($expenseRows as $r) $expenseMap[$r['campus'] . '|' . $r['period']] = $r;

            $allKeys = array_unique(array_merge(array_keys($incomeMap), array_keys($expenseMap)));
            sort($allKeys);

            $rows = [];
            $totalIncome = 0; $totalExpense = 0; $totalIncomeCnt = 0; $totalExpenseCnt = 0;
            foreach ($allKeys as $key) {
                list($campusKey, $period) = explode('|', $key, 2);
                $inc = $incomeMap[$key] ?? ['cnt' => 0, 'amount' => 0];
                $exp = $expenseMap[$key] ?? ['cnt' => 0, 'amount' => 0];
                $incAmt = floatval($inc['amount']);
                $expAmt = floatval($exp['amount']);
                $rows[] = [
                    'campus' => $campusKey ?: '未指定校区',
                    'date' => $period,
                    'income_cnt' => intval($inc['cnt']),
                    'income_amount' => $incAmt,
                    'expense_cnt' => intval($exp['cnt']),
                    'expense_amount' => $expAmt,
                    'net' => round($incAmt - $expAmt, 2),
                ];
                $totalIncome += $incAmt;
                $totalExpense += $expAmt;
                $totalIncomeCnt += intval($inc['cnt']);
                $totalExpenseCnt += intval($exp['cnt']);
            }

            // 按日期倒序，同日期内按校区排序
            usort($rows, function($a, $b) {
                $cmp = strcmp($b['date'], $a['date']);
                return $cmp !== 0 ? $cmp : strcmp($a['campus'], $b['campus']);
            });

            // 校区排名：仅当未筛选单个校区时（campus为空），按校区汇总排名
            $rankings = [];
            if (empty($campuses)) {
                // 各校区收入汇总（不分订单类型）
                $rankIncomeSql = "SELECT o.campus, COALESCE(SUM(o.actual_price), 0) AS amount
                    FROM orders o
                    WHERE o.pay_status = '已支付' AND o.is_voided = '否'
                      AND o.paid_at >= :from AND o.paid_at <= :to2
                    GROUP BY o.campus
                    ORDER BY amount DESC";
                $stmt = $db->prepare($rankIncomeSql);
                $stmt->bindValue(':from', $from . ' 00:00:00');
                $stmt->bindValue(':to2', $to . ' 23:59:59');
                $stmt->execute();
                $rankRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // 各校区支出汇总
                $rankExpenseSql = "SELECT rr.campus, COALESCE(SUM(rr.actual_refund), 0) AS amount
                    FROM refund_records rr
                    WHERE rr.status = '已退费'
                      AND rr.updated_at >= :from_e AND rr.updated_at <= :to_e
                    GROUP BY rr.campus";
                $stmt = $db->prepare($rankExpenseSql);
                $stmt->bindValue(':from_e', $from . ' 00:00:00');
                $stmt->bindValue(':to_e', $to . ' 23:59:59');
                $stmt->execute();
                $expenseRankRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $expenseMap = [];
                foreach ($expenseRankRows as $er) {
                    $c = $er['campus'] ?: '未指定校区';
                    $expenseMap[$c] = round(floatval($er['amount']), 2);
                }

                foreach ($rankRows as $r) {
                    $c = $r['campus'] ?: '未指定校区';
                    $income = round(floatval($r['amount']), 2);
                    $expense = $expenseMap[$c] ?? 0;
                    $rankings[] = [
                        'campus' => $c,
                        'income' => $income,
                        'expense' => $expense,
                        'net' => round($income - $expense, 2),
                    ];
                }
            }
json([
                'data' => $rows,
                'income_by_type' => $incomeByTypeRows,
                'rankings' => $rankings,
                'summary' => [
                    'total_income' => round($totalIncome, 2),
                    'total_expense' => round($totalExpense, 2),
                    'net_cashflow' => round($totalIncome - $totalExpense, 2),
                    'income_cnt' => $totalIncomeCnt,
                    'expense_cnt' => $totalExpenseCnt,
                ],
            ]);
            break;

        default:
            json(['error' => 'Unknown action']);
    }
}

// 意向等级默认数据初始化
$stmt = $db->query("SELECT COUNT(*) FROM intention_levels");
$count = $stmt->fetchColumn();
if (intval($count) === 0) {
    $n = now();
    $db->exec("INSERT INTO intention_levels (name, sort_order, created_at) VALUES ('A-高意向', 1, '$n')");
    $db->exec("INSERT INTO intention_levels (name, sort_order, created_at) VALUES ('B-中意向', 2, '$n')");
    $db->exec("INSERT INTO intention_levels (name, sort_order, created_at) VALUES ('C-低意向', 3, '$n')");
    $db->exec("INSERT INTO intention_levels (name, sort_order, created_at) VALUES ('D-无意向', 4, '$n')");
}

// 基础类型默认数据初始化
$stmt = $db->query("SELECT COUNT(*) FROM basic_types");
$countBt = $stmt->fetchColumn();
if (intval($countBt) === 0) {
    $n = now();
    $db->exec("INSERT INTO basic_types (category, name, sort_order, created_at) VALUES ('course_type', '试听课', 1, '$n')");
    $db->exec("INSERT INTO basic_types (category, name, sort_order, created_at) VALUES ('course_type', '正式课体验', 2, '$n')");
    $db->exec("INSERT INTO basic_types (category, name, sort_order, created_at) VALUES ('course_type', '测评课', 3, '$n')");
    $db->exec("INSERT INTO basic_types (category, name, sort_order, created_at) VALUES ('course_type', '其他', 4, '$n')");
    $db->exec("INSERT INTO basic_types (category, name, sort_order, created_at) VALUES ('comm_type', '电话', 1, '$n')");
    $db->exec("INSERT INTO basic_types (category, name, sort_order, created_at) VALUES ('comm_type', '微信', 2, '$n')");
    $db->exec("INSERT INTO basic_types (category, name, sort_order, created_at) VALUES ('comm_type', '面谈', 3, '$n')");
    $db->exec("INSERT INTO basic_types (category, name, sort_order, created_at) VALUES ('comm_type', '短信', 4, '$n')");
}

// ==================== 以下是 HTML 页面 ====================
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TMS管理系统</title>
    <link rel="stylesheet" href="static/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="mobile-topbar">
        <button class="hamburger-btn" onclick="toggleSidebar()">☰</button>
        <span class="mobile-title">TMS管理系统</span>
    </div>
    <div class="app-layout">
        <!-- 左侧树状导航 -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                </div>
                <h2>TMS管理系统</h2>
            </div>
            <nav class="tree-nav" id="tree-nav">
                <ul class="tree-root">
                    <!-- 市场管理（父节点） -->
                    <li class="tree-node expanded">
                        <div class="tree-parent">
                            <span class="tree-arrow"><svg viewBox="0 0 24 24" width="10" height="10" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg></span>
                            <span class="tree-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span>
                            <span class="tree-label">市场管理</span>
                        </div>
                        <ul class="tree-children">
                            <li class="tree-node">
                                <div class="tree-leaf active" data-panel="panel-my-resources">
                                    <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
                                    <span class="tree-label">我的资源</span>
                                </div>
                            </li>
                            <li class="tree-node">
                                <div class="tree-leaf" data-panel="panel-appointments">
                                    <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
                                    <span class="tree-label">预约试听名单</span>
                                </div>
                            </li>
                            <li class="tree-node">
                                <div class="tree-leaf" data-panel="panel-sea-pool">
                                    <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></span>
                                    <span class="tree-label">资源公海</span>
                                </div>
                            </li>
                            <li class="tree-node">
                                <div class="tree-parent sub-parent">
                                    <span class="tree-arrow"><svg viewBox="0 0 24 24" width="10" height="10" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg></span>
                                    <span class="tree-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span>
                                    <span class="tree-label">基础设置</span>
                                </div>
                                <ul class="tree-children">
                                    <li class="tree-node">
                                        <div class="tree-leaf tree-leaf-deep" data-panel="panel-channel-settings">
                                            <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 21v-7"/><path d="M4 10V3"/><path d="M12 21v-9"/><path d="M12 8V3"/><path d="M20 21v-5"/><path d="M20 12V3"/></svg></span>
                                            <span class="tree-label">渠道设置</span>
                                        </div>
                                    </li>
                                    <li class="tree-node">
                                        <div class="tree-leaf tree-leaf-deep" data-panel="panel-intention-level-settings">
                                            <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                                            <span class="tree-label">意向等级设置</span>
                                        </div>
                                    </li>
                                    <li class="tree-node">
                                        <div class="tree-leaf tree-leaf-deep" data-panel="panel-basic-type-settings">
                                            <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg></span>
                                            <span class="tree-label">基础类型设置</span>
                                        </div>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                    <!-- 教务管理（父节点） -->
                    <li class="tree-node expanded">
                        <div class="tree-parent">
                            <span class="tree-arrow"><svg viewBox="0 0 24 24" width="10" height="10" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg></span>
                            <span class="tree-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span>
                            <span class="tree-label">教务管理</span>
                        </div>
                        <ul class="tree-children">
                            <li class="tree-node">
                                <div class="tree-leaf" data-panel="panel-courses">
                                    <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
                                    <span class="tree-label">课程管理</span>
                                </div>
                            </li>
                            <li class="tree-node">
                                <div class="tree-leaf" data-panel="panel-students">
                                    <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                                    <span class="tree-label">学员管理</span>
                                </div>
                            </li>
                            <li class="tree-node">
                                <div class="tree-leaf" data-panel="panel-attendance">
                                    <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
                                    <span class="tree-label">考勤</span>
                                </div>
                            </li>
                            <li class="tree-node" style="display:none;">
                                <div class="tree-leaf" data-panel="panel-classes">
                                    <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span>
                                    <span class="tree-label">班级管理</span>
                                </div>
                            </li>
                            <li class="tree-node">
                                <div class="tree-leaf" data-panel="panel-orders">
                                    <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span>
                                    <span class="tree-label">交易订单</span>
                                </div>
                            </li>
                            <li class="tree-node">
                                <div class="tree-leaf" data-panel="panel-work-records">
                                    <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
                                    <span class="tree-label">工作记录</span>
                                </div>
                            </li>
                            <li class="tree-node">
                                <div class="tree-leaf" data-panel="panel-discounts">
                                    <span class="tree-icon-sub">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                                            <line x1="7" y1="7" x2="7.01" y2="7"/>
                                        </svg>
                                    </span>
                                    <span class="tree-label">优惠管理</span>
                                </div>
                            </li>
                            <li class="tree-node">
                                <div class="tree-parent sub-parent">
                                    <span class="tree-arrow"><svg viewBox="0 0 24 24" width="10" height="10" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg></span>
                                    <span class="tree-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
                                    <span class="tree-label">基础设置</span>
                                </div>
                                <ul class="tree-children">
                                    <li class="tree-node">
                                        <div class="tree-leaf tree-leaf-deep" data-panel="panel-subjects">
                                            <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span>
                                            <span class="tree-label">学科设置</span>
                                        </div>
                                    </li>
                                    <li class="tree-node">
                                        <div class="tree-leaf tree-leaf-deep" data-panel="panel-classrooms">
                                            <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg></span>
                                            <span class="tree-label">教室管理</span>
                                        </div>
                                    </li>
                                    <li class="tree-node">
                                        <div class="tree-leaf tree-leaf-deep" data-panel="panel-period-settings">
                                            <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                                            <span class="tree-label">上课时段设置</span>
                                        </div>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                    <!-- 数据中心（父节点） -->
                    <li class="tree-node expanded">
                        <div class="tree-parent">
                            <span class="tree-arrow"><svg viewBox="0 0 24 24" width="10" height="10" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg></span>
                            <span class="tree-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>
                            <span class="tree-label">数据中心</span>
                        </div>
                        <ul class="tree-children">
                            <li class="tree-node">
                                <div class="tree-leaf" data-panel="panel-cashflow">
                                    <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
                                    <span class="tree-label">现金流统计</span>
                                </div>
                            </li>
                            <li class="tree-node">
                                <div class="tree-leaf" data-panel="panel-revenue">
                                    <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
                                    <span class="tree-label">确收统计</span>
                                </div>
                            </li>
                        </ul>
                    </li>

                    <li class="tree-node expanded">
                        <div class="tree-parent">
                            <span class="tree-arrow"><svg viewBox="0 0 24 24" width="10" height="10" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg></span>
                            <span class="tree-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                            <span class="tree-label">员工管理</span>
                        </div>
                        <ul class="tree-children">
                            <li class="tree-node">
                                <div class="tree-leaf" data-panel="panel-employees">
                                    <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
                                    <span class="tree-label">员工名册</span>
                                </div>
                            </li>
                            <li class="tree-node">
                                <div class="tree-leaf" data-panel="panel-position-settings">
                                    <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 21v-7"/><path d="M4 10V3"/><path d="M12 21v-9"/><path d="M12 8V3"/><path d="M20 21v-5"/><path d="M20 12V3"/></svg></span>
                                    <span class="tree-label">岗位管理</span>
                                </div>
                            </li>
                            <li class="tree-node">
                                <div class="tree-leaf" data-panel="panel-org">
                                    <span class="tree-icon-sub"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h7v7H3z"/><path d="M14 3h7v7h-7z"/><path d="M14 14h7v7h-7z"/><path d="M3 14h7v7H3z"/></svg></span>
                                    <span class="tree-label">组织管理</span>
                                </div>
                            </li>
                        </ul>
                    </li>
                </ul>
            </nav>

        </aside>

        <!-- 右侧内容区 -->
        <main class="main-content">
            <!-- 面板：我的资源（整合页） -->
            <section class="content-panel active" id="panel-my-resources">
                <div class="panel-header">
                    <h3>我的资源</h3>
                    <div class="header-stats-inline">
                        <span class="stat-badge">我的资源：<strong id="stat-my-inline">0</strong></span>
                        <span class="stat-badge">公海资源：<strong id="stat-sea-inline">0</strong></span>
                        <span class="stat-badge">预约试听：<strong id="stat-apt-inline">0</strong></span>
                    </div>
                </div>
                <!-- 功能按钮组 -->
                <div class="action-button-group">
                    <button class="action-btn" onclick="showAddModal()" title="新增资源">
                        <span class="action-btn-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                        </span>
                        <span class="action-btn-label">新增资源</span>
                    </button>
                    <button class="action-btn" onclick="showBatchImportModal()" title="批量导入">
                        <span class="action-btn-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg>
                        </span>
                        <span class="action-btn-label">批量导入</span>
                    </button>
                    <button class="action-btn" onclick="batchAssign()" title="批量分配">
                        <span class="action-btn-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        </span>
                        <span class="action-btn-label">批量分配</span>
                    </button>
                                                          <button class="action-btn" onclick="openBatchCommunication()" title="添加沟通记录（请先勾选资源）">
                        <span class="action-btn-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
                        </span>
                        <span class="action-btn-label">添加沟通记录</span>
                    </button>
                    <button class="action-btn action-btn-warn" onclick="batchMoveToSea()" title="移入公海">
                        <span class="action-btn-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm-1 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h7c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h7v14z"/></svg>
                        </span>
                        <span class="action-btn-label">移入公海</span>
                    </button>
                    <button class="action-btn" onclick="exportMyResources('我的资源')" title="导出当前筛选结果为 CSV">
                        <span class="action-btn-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                        </span>
                        <span class="action-btn-label">导出资源</span>
                    </button>
                </div>
                <!-- 搜索筛选工具栏 -->
                <div class="toolbar">
                    <div class="toolbar-left">
                        <input type="text" id="filter-name-my" class="filter-input-sm" placeholder="姓名">
                        <input type="text" id="filter-phone-my" class="filter-input-md" placeholder="手机号">
                        <select id="filter-source-my">
                            <option value="">全部渠道</option>
                        </select>
                        <select id="filter-assigned-to-my" onchange="loadMyResources()">
                            <option value="">全部归属人</option>
                        </select>
                        <select id="filter-assigned-dept-my" onchange="loadMyResources()">
                            <option value="">全部归属部门</option>
                        </select>
                        <select id="filter-date-preset-my" onchange="applyDatePreset('my')" style="padding:5px 8px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
                            <option value="">创建时间</option>
                            <option value="today">今天</option>
                            <option value="yesterday">昨天</option>
                            <option value="7days">近7天</option>
                            <option value="30days">近30天</option>
                            <option value="thisMonth">本月</option>
                            <option value="lastMonth">上月</option>
                            <option value="custom">自定义范围</option>
                        </select>
                        <input type="date" id="filter-created-start-my" title="创建时间起" style="display:none;" onchange="onCustomDateChange('my')">
                        <input type="date" id="filter-created-end-my" title="创建时间止" style="display:none;" onchange="onCustomDateChange('my')">
                    </div>
                    <div class="toolbar-right" style="margin-left:auto;">
                        <select id="filter-follow-status-my" onchange="loadMyResources()">
                            <option value="">全部跟进状态</option>
                            <option value="未沟通">未沟通</option>
                            <option value="沟通中">沟通中</option>
                            <option value="已邀约未试听">已邀约未试听</option>
                            <option value="已试听待转化">已试听待转化</option>
                            <option value="已转化—定金">已转化—定金</option>
                            <option value="已转化—全款">已转化—全款</option>
                            <option value="无效客户">无效客户</option>
                        </select>
                        <input type="text" id="search-my" placeholder="搜索姓名/电话/来源..." onkeyup="debounceSearch('my')">
                        <button class="btn btn-primary btn-sm" onclick="loadMyResources()">搜索</button>
                    </div>
                </div>
                <div class="table-wrap">
                    <table id="table-my-resources">
                        <thead><tr>
                            <th width="40"><input type="checkbox" id="select-all-my" onchange="toggleSelectAll('my')"></th>
                            <th>姓名</th><th>电话</th><th>来源</th><th>意向等级</th><th>归属人</th><th>归属部门</th><th>跟进状态</th><th>创建时间</th><th>性别</th><th>出生日期</th><th>更新时间</th><th>转化状态</th><th width="200">操作</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination-my"></div>
            </section>

            <!-- 面板：预约试听名单 -->
            <section class="content-panel" id="panel-appointments">
                <div class="panel-header">
                    <h3>预约试听名单</h3>
                    <div class="header-stats-inline" id="apt-stats-bar">
                        <span class="stat-badge stat-pending">已预约待试听：<strong id="stat-pending">0</strong></span>
                        <span class="stat-badge stat-trialed">已试听：<strong id="stat-trialed">0</strong></span>
                        <span class="stat-badge stat-absent">缺勤：<strong id="stat-absent">0</strong></span>
                    </div>
                </div>
                <div class="toolbar">
                    <div class="toolbar-left">
                        <button class="btn btn-primary" onclick="showAppointmentModal()">+ 新增预约</button>
                    </div>
                    <div class="toolbar-right">
                        <select id="filter-status-apt" onchange="loadAppointments()">
                            <option value="">全部状态</option>
                            <option value="已预约待试听">已预约待试听</option>
                            <option value="已试听">已试听</option>
                            <option value="缺勤">缺勤</option>
                            <option value="已取消">已取消</option>
                        </select>
                        <input type="text" id="search-apt" placeholder="搜索学员/资源/电话..." onkeyup="debounceSearch('apt')">
                    </div>
                </div>
                <div class="table-wrap">
                    <table id="table-appointments">
                        <thead><tr>
                            <th>资源</th><th>电话</th><th>课程类型</th><th>班级名称</th><th>授课老师</th><th>一级学科</th><th>二级学科</th><th>预约时间</th><th>状态</th><th>转化状态</th><th>渠道</th><th>归属人</th><th>备注</th><th width="110">操作</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination-apt"></div>
            </section>

            <!-- 面板：渠道设置 -->
            <section class="content-panel" id="panel-channel-settings">
                <div class="panel-header">
                    <h3>渠道设置</h3>
                </div>
                <div class="channel-settings-panel">
                    <div class="channel-add-row">
                        <input type="text" id="channel-name-input" placeholder="输入渠道名称，如：线上推广、地推、转介绍..." maxlength="50">
                        <button class="btn btn-primary" onclick="addChannel()">添加渠道</button>
                    </div>
                    <div class="table-wrap">
                        <table id="table-channels">
                            <thead><tr>
                                <th>渠道名称</th>
                                <th>创建时间</th>
                                <th width="120">操作</th>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- 面板：意向等级设置 -->
            <section class="content-panel" id="panel-intention-level-settings">
                <div class="panel-header">
                    <h3>意向等级设置</h3>
                </div>
                <div class="channel-settings-panel">
                    <div class="channel-add-row">
                        <input type="text" id="intention-name-input" placeholder="输入意向等级名称，如：A-高意向、B-中意向..." maxlength="50">
                        <input type="number" id="intention-sort-input" placeholder="排序号（可选）" min="0" style="width:140px;">
                        <button class="btn btn-primary" onclick="addIntentionLevel()">添加等级</button>
                    </div>
                    <div class="table-wrap">
                        <table id="table-intention-levels">
                            <thead><tr>
                                <th>名称</th>
                                <th width="100">排序号</th>
                                <th>创建时间</th>
                                <th width="120">操作</th>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- 面板：基础类型设置 -->
            <section class="content-panel" id="panel-basic-type-settings">
                <div class="panel-header">
                    <h3>基础类型设置</h3>
                </div>
                <div class="basic-type-tabs">
                    <button class="bt-tab active" data-cat="course_type">课程类型</button>
                    <button class="bt-tab" data-cat="comm_type">沟通方式</button>
                </div>
                <div class="basic-type-panel">
                    <div class="channel-add-row">
                        <input type="text" id="bt-name-input" placeholder="输入名称..." maxlength="50">
                        <input type="number" id="bt-sort-input" placeholder="排序号" min="0" style="width:100px;">
                        <button class="btn btn-primary" onclick="addBasicType()">添加</button>
                    </div>
                    <div class="table-wrap">
                        <table id="table-basic-types">
                            <thead><tr>
                                <th>名称</th>
                                <th width="100">排序号</th>
                                <th>创建时间</th>
                                <th width="120">操作</th>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- 面板：上课时段设置 -->
            <section class="content-panel" id="panel-period-settings">
                <div class="panel-header">
                    <h3>🕐 上课时段设置</h3>
                </div>
                <div class="channel-settings-panel">
                    <div class="channel-add-row period-add-row">
                        <input type="text" id="period-name-input" placeholder="时段名称，如：上午第一节" maxlength="30" class="period-input-name">
                        <input type="time" id="period-start-input" step="60" class="period-input-time">
                        <span class="period-separator">至</span>
                        <input type="time" id="period-end-input" step="60" class="period-input-time">
                        <select id="period-campus-input" class="period-input-campus"><option value="">选择校区</option></select>
                        <input type="number" id="period-sort-input" placeholder="排序号" min="0" class="period-input-sort">
                        <button class="btn btn-primary" onclick="addPeriod()">添加时段</button>
                    </div>
                    <div class="table-wrap">
                        <table id="table-periods">
                            <thead><tr>
                                <th width="80">排序</th>
                                <th>时段名称</th>
                                <th width="120">开始时间</th>
                                <th width="120">结束时间</th>
                                <th>校区</th>
                                <th>创建时间</th>
                                <th width="120">操作</th>
                            </tr></thead>
                            <tbody>
                                <tr><td colspan="7">加载中...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- 面板：资源公海 -->
            <section class="content-panel" id="panel-sea-pool">
                <div class="panel-header">
                    <h3>资源公海</h3>
                    <div class="header-stats-inline">
                        <span class="stat-badge">我的资源：<strong id="stat-my-inline3">0</strong></span>
                        <span class="stat-badge">公海资源：<strong id="stat-sea-inline3">0</strong></span>
                        <span class="stat-badge">预约试听：<strong id="stat-apt-inline3">0</strong></span>
                    </div>
                </div>
                <div class="toolbar">
                    <div class="toolbar-left">
                        <button class="btn btn-outline" onclick="batchPickFromSea()">领取选中</button>
                        <button class="btn btn-outline" onclick="exportMyResources('资源公海')">导出资源</button>
                    </div>
                    <div class="toolbar-right">
                        <input type="text" id="search-sea" placeholder="搜索姓名/电话/来源..." onkeyup="debounceSearch('sea')">
                    </div>
                </div>
                <div class="table-wrap">
                    <table id="table-sea-pool">
                        <thead><tr>
                            <th width="40"><input type="checkbox" id="select-all-sea" onchange="toggleSelectAll('sea')"></th>
                            <th>姓名</th><th>电话</th><th>来源</th><th>意向等级</th><th>性别</th><th>出生日期</th><th>入池时间</th><th width="160">操作</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination-sea"></div>
            </section>

            <!-- 面板：员工名册 -->
            <section class="content-panel" id="panel-employees">
                <div class="panel-header">
                    <h3>员工名册</h3>
                    <div class="header-stats-inline">
                        <span class="stat-badge">员工总数：<strong id="stat-emp-inline">0</strong></span>
                    </div>
                </div>
                <div class="action-button-group">
                    <button class="action-btn" onclick="showEmpModal()" title="新增员工">
                        <span class="action-btn-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                        </span>
                        <span class="action-btn-label">新增员工</span>
                    </button>
                    <button class="action-btn" onclick="showBatchImportEmpModal()" title="批量导入">
                        <span class="action-btn-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg>
                        </span>
                        <span class="action-btn-label">批量导入</span>
                    </button>
                    <button class="action-btn" onclick="exportEmployees()" title="导出 CSV">
                        <span class="action-btn-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                        </span>
                        <span class="action-btn-label">导出</span>
                    </button>
                </div>
                <div class="toolbar">
                    <div class="toolbar-left">
                        <input type="text" id="filter-emp-name" class="filter-input-sm" placeholder="姓名">
                        <select id="filter-emp-dept">
                            <option value="">全部部门</option>
                        </select>
                        <select id="filter-emp-status">
                            <option value="">全部状态</option>
                            <option value="在职">在职</option>
                            <option value="离职">离职</option>
                        </select>
                    </div>
                    <div class="toolbar-right" style="margin-left:auto;">
                        <input type="text" id="search-emp" placeholder="搜索姓名/电话..." onkeyup="debounceSearch('emp')">
                        <button class="btn btn-primary btn-sm" onclick="loadEmployees()">搜索</button>
                    </div>
                </div>
                <div class="table-wrap">
                    <table id="table-employees">
                        <thead><tr>
                            <th width="40"><input type="checkbox" id="select-all-emp" onchange="toggleSelectAll('emp')"></th>
                            <th>姓名</th><th>电话</th><th>部门</th><th>岗位</th><th>入职日期</th><th>状态</th><th>是否教师</th><th>创建时间</th><th width="180">操作</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination-emp"></div>
            </section>

            <!-- 面板：组织管理 -->
            <section class="content-panel" id="panel-org">
                <div class="panel-header">
                    <h3>组织管理</h3>
                </div>
                <div class="org-layout">
                    <!-- 左侧：组织树 -->
                    <div class="org-tree-panel">
                        <div class="org-tree-toolbar">
                            <button class="btn btn-primary btn-sm" onclick="showOrgModal(0, '', 0)">+ 新增组织</button>
                        </div>
                        <div class="org-tree-wrap" id="org-tree-wrap">
                            <div class="org-tree-loading">加载中...</div>
                        </div>
                    </div>
                    <!-- 右侧：详情/操作区 -->
                    <div class="org-detail-panel" id="org-detail-panel">
                        <div class="org-detail-placeholder">
                            <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="#ccc" stroke-width="1.5"><path d="M3 3h7v7H3z"/><path d="M14 3h7v7h-7z"/><path d="M14 14h7v7h-7z"/><path d="M3 14h7v7H3z"/></svg>
                            <p>请从左侧选择组织节点查看详情</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 面板：岗位管理 -->
            <section class="content-panel" id="panel-position-settings">
                <div class="panel-header">
                    <h3>岗位管理</h3>
                </div>
                <div class="channel-settings-panel">
                    <div class="channel-add-row">
                        <input type="text" id="position-name-input" placeholder="输入岗位名称，如：工程师、经理、销售..." maxlength="50">
                        <input type="number" id="position-sort-input" placeholder="排序号（可选）" min="0" style="width:140px;">
                        <button class="btn btn-primary" onclick="addPosition()">添加岗位</button>
                    </div>
                    <div class="table-wrap">
                        <table id="table-positions">
                            <thead><tr>
                                <th>名称</th>
                                <th width="100">排序号</th>
                                <th>创建时间</th>
                                <th width="120">操作</th>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- 面板：课程管理 -->
            <section class="content-panel" id="panel-courses">
                <div class="panel-header">
                    <h3>课程管理</h3>
                    <div class="header-stats-inline">
                        <span class="stat-badge stat-badge-courses">课程总数：<strong id="stat-courses-inline">0</strong></span>
                    </div>
                </div>
                <div class="action-button-group">
                    <button class="action-btn" onclick="showCourseModal()" title="新增课程">
                        <span class="action-btn-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                        </span>
                        <span class="action-btn-label">新增课程</span>
                    </button>

                </div>
                <div class="filter-bar" id="filter-bar-course">
                    <div class="filter-item filter-item-search">
                        <label class="filter-label">课程名称</label>
                        <div class="filter-search-wrap">
                            <svg class="filter-search-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" id="search-course" placeholder="搜索课程名称..." onkeyup="debounceSearch('course')">
                        </div>
                    </div>
                    <div class="filter-item">
                        <label class="filter-label">一级学科</label>
                        <select id="filter-subject1" onchange="onFilterSubject1Change()"><option value="">全部</option></select>
                    </div>
                    <div class="filter-item">
                        <label class="filter-label">二级学科</label>
                        <select id="filter-subject2" onchange="onFilterChange()"><option value="">全部</option></select>
                    </div>
                    <div class="filter-item">
                        <label class="filter-label">小课包</label>
                        <select id="filter-small-package" onchange="onFilterChange()">
                            <option value="">全部</option>
                            <option value="是">是</option>
                            <option value="否">否</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label class="filter-label">低幼龄</label>
                        <select id="filter-toddler" onchange="onFilterChange()">
                            <option value="">全部</option>
                            <option value="是">是</option>
                            <option value="否">否</option>
                        </select>
                    </div>
                    <div class="filter-item filter-item-campus">
                        <label class="filter-label">适用校区</label>
                        <div class="filter-campus-dropdown" id="filter-campus-dropdown">
                            <button class="filter-campus-trigger" onclick="toggleFilterCampusPanel()" id="filter-campus-trigger">全部校区 ▾</button>
                            <div class="filter-campus-panel" id="filter-campus-panel">
                                <div class="filter-campus-actions">
                                    <a href="javascript:void(0)" onclick="filterCampusToggleAll()" id="filter-campus-toggle-all">全选</a>
                                    <a href="javascript:void(0)" onclick="clearFilterCampus()">清空</a>
                                </div>
                                <div class="filter-campus-tree" id="filter-campus-tree"></div>
                            </div>
                        </div>
                    </div>
                    <button class="filter-reset-btn" onclick="resetCourseFilters()" title="重置筛选">重置</button>
                </div>
                <div class="course-cards-wrap" id="table-courses">
                    <div class="course-cards-empty" style="display:none;">暂无课程数据</div>
                </div>
                <div class="pagination" id="pagination-course"></div>
            </section>

            <!-- 面板：学员管理 -->
            <section class="content-panel" id="panel-students">
                <div class="panel-header">
                    <h3>学员管理</h3>
                    <div class="header-stats-inline">
                        <span class="stat-badge">学员总数：<strong id="stat-students-inline">0</strong></span>
                    </div>
                </div>
                <div class="toolbar">
                    <div class="toolbar-left" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <label style="font-size:13px;white-space:nowrap;">校区：</label>
                        <select id="student-filter-campus" onchange="onStudentFilterChange()" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;min-width:120px;">
                            <option value="">全部校区</option>
                        </select>
                        <label style="font-size:13px;white-space:nowrap;margin-left:4px;">一级学科：</label>
                        <select id="student-filter-subject1" onchange="onStudentFilterChange()" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;min-width:120px;">
                            <option value="">全部学科</option>
                        </select>
                        <label style="font-size:13px;white-space:nowrap;margin-left:4px;">学员筛选：</label>
                        <select id="student-filter-type" onchange="onStudentFilterChange()" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;min-width:120px;">
                            <option value="">全部学员</option>
                            <option value="active">在册学员</option>
                            <option value="active_other" disabled>活跃学员（待开发）</option>
                            <option value="sleeping" disabled>沉睡学员（待开发）</option>
                            <option value="lost" disabled>流失学员（待开发）</option>
                        </select>
                    </div>
                    <div class="toolbar-right" style="margin-left:auto;display:flex;align-items:center;gap:8px;">
                        <input type="text" id="search-student" placeholder="搜索姓名/手机号..." onkeyup="debounceSearch('student')">
                        <button class="btn btn-primary btn-sm" onclick="loadStudents()">搜索</button>
                    </div>
                </div>
                <div class="table-wrap">
                    <table id="table-students">
                        <thead><tr>
                            <th width="70">学号</th><th>姓名</th><th>手机号</th><th width="70">学员类型</th><th>校区</th><th>所在班级</th><th>学科剩余课时</th><th>授课老师</th><th width="180">操作</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination-student"></div>
            </section>

            <!-- 面板：考勤 -->
            <section class="content-panel" id="panel-attendance">
                <div class="panel-header">
                    <h3>考勤</h3>
                </div>
                <div class="attendance-tabs">
                    <button class="att-tab active" data-tab="tab-schedule-view">课表</button>
                    <button class="att-tab" data-tab="tab-attendance-operations">操作考勤</button>
                    <button class="att-tab" data-tab="tab-student-consumption">学员课耗</button>
                    <button class="att-tab" data-tab="tab-absence-records">缺勤记录</button>
                    <button class="att-tab" data-tab="tab-classes">班级管理</button>
                </div>
                <div class="attendance-tab-content">
                    <!-- 操作考勤页签 -->
                    <div class="att-panel" id="tab-attendance-operations">
                        <div class="toolbar">
                            <div class="toolbar-left">
                                <label style="font-size:13px;margin-right:6px;">日期范围：</label>
                                <input type="date" id="attendance-date-from" style="width:140px;" onchange="loadAttendanceSessions()">
                                <span style="margin:0 6px;color:#999;">至</span>
                                <input type="date" id="attendance-date-to" style="width:140px;" onchange="loadAttendanceSessions()">
                                <button class="btn btn-primary btn-sm" onclick="loadAttendanceSessions()">查询</button>
                                <span style="margin-left:16px;font-size:13px;">班级：</span>
                                <input type="text" id="attendance-class-name" placeholder="搜索班级名称" style="width:160px;" onkeydown="if(event.key==='Enter')loadAttendanceSessions()">
                            </div>
                        </div>
                        <div class="table-wrap">
                            <table id="table-attendance-sessions">
                                <thead><tr>
                                    <th width="110">上课日期</th><th>星期</th><th>班级名称</th><th>课程</th><th>一级学科</th><th>二级学科</th><th width="100">上课时间</th><th>上课老师</th><th>教室</th><th>校区</th><th width="70">状态</th><th width="80">操作</th>
                                </tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="pagination" id="pagination-attendance-sessions"></div>
                    </div>
                    <!-- 学员课耗页签 -->
                    <div class="att-panel" id="tab-student-consumption">
                        <div class="toolbar">
                            <div class="toolbar-left">
                                <label style="font-size:13px;margin-right:6px;">日期范围：</label>
                                <input type="date" id="consumption-date-from" style="width:140px;" onchange="loadStudentConsumption()">
                                <span style="margin:0 6px;color:#999;">至</span>
                                <input type="date" id="consumption-date-to" style="width:140px;" onchange="loadStudentConsumption()">
                                <button class="btn btn-primary btn-sm" onclick="loadStudentConsumption()">查询</button>
                            </div>
                        </div>
                        <div class="table-wrap">
                            <table id="table-student-consumption">
                                <thead><tr>
                                    <th>校区</th><th>学号</th><th>学员姓名</th><th>手机号</th><th>课程</th><th>一级学科</th><th>二级学科</th><th>班级</th><th>授课教师</th><th>上课日期</th><th>上课时间</th><th>考勤时间</th><th>出勤状态</th><th>消耗课时</th><th>课耗金额</th>
                                </tr></thead>
                                <tbody id="consumption-tbody">
                                    <tr><td colspan="15" style="text-align:center;color:#999;padding:20px;">加载中...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="pagination" id="pagination-student-consumption"></div>
                    </div>
                    <!-- 缺勤记录页签 -->
                    <div class="att-panel" id="tab-absence-records">
                        <div class="toolbar">
                            <div class="toolbar-left">
                                <label style="font-size:13px;margin-right:6px;">日期范围：</label>
                                <input type="date" id="absence-date-from" style="width:140px;" onchange="loadAbsenceRecords()">
                                <span style="margin:0 6px;color:#999;">至</span>
                                <input type="date" id="absence-date-to" style="width:140px;" onchange="loadAbsenceRecords()">
                                <button class="btn btn-primary btn-sm" onclick="loadAbsenceRecords()">查询</button>
                                <span style="margin-left:16px;font-size:13px;">班级：</span>
                                <input type="text" id="absence-class-name" placeholder="搜索班级名称" style="width:160px;" onkeydown="if(event.key==='Enter')loadAbsenceRecords()">
                            </div>
                        </div>
                        <div class="table-wrap">
                            <table id="table-absence-records">
                                <thead><tr>
                                    <th>姓名</th><th>学号</th><th>手机号</th><th>校区</th><th>课程</th><th>一级学科</th><th>二级学科</th><th>班级</th><th>授课教师</th><th>上课日期</th><th>上课时间</th>
                                </tr></thead>
                                <tbody>
                                    <tr><td colspan="11" style="text-align:center;color:#999;padding:20px;">加载中...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="pagination" id="pagination-absence-records"></div>
                    </div>
                    <!-- 班级管理页签 -->
                    <div class="att-panel" id="tab-classes">
                        <div class="toolbar">
                            <div class="toolbar-left">
                                <button class="btn btn-primary" onclick="showClassForm()">+ 新增班级</button>
                            </div>
                            <div class="toolbar-right" style="margin-left:auto;">
                                <input type="text" id="att-search-class" placeholder="搜索班级名称..." onkeyup="debounceSearch('att-class')">
                                <button class="btn btn-primary btn-sm" onclick="loadClasses(1,'att')">搜索</button>
                            </div>
                        </div>
                        <div class="table-wrap">
                            <table id="att-table-classes">
                                <thead><tr>
                                    <th width="60">编号</th><th>班级名称</th><th>关联课程</th><th>一级学科</th><th>二级学科</th><th>班级类型</th><th>招生人数</th><th>授课课时</th><th>可试听</th><th>当前校区</th><th>备注</th><th>创建时间</th><th width="160">操作</th>
                                </tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="pagination" id="pagination-att-class"></div>
                    </div>
                    <!-- 课表页签 -->
                    <div class="att-panel" id="tab-schedule-view">
                        <div class="schedule-layout" id="schedule-layout">
                            <!-- 左侧资源面板 -->
                            <div class="schedule-resource-panel" id="schedule-resource-panel">
                                <div class="resource-section">
                                    <div class="resource-section-title">📚 课程</div>
                                    <div class="resource-list" id="drag-course-list"><div class="resource-empty">加载中...</div></div>
                                </div>
                                <div class="resource-section">
                                    <div class="resource-section-title">👨‍🏫 教师</div>
                                    <div class="resource-list" id="drag-teacher-list"><div class="resource-empty">加载中...</div></div>
                                </div>
                                <div class="resource-section">
                                    <div class="resource-section-title">🏫 教室</div>
                                    <div class="resource-list" id="drag-classroom-list"><div class="resource-empty">加载中...</div></div>
                                </div>
                            </div>
                            <!-- 右侧课表区域 -->
                            <div class="schedule-main" id="schedule-main">
                                <div class="toolbar">
                                    <div class="toolbar-left" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                        <label style="font-size:13px;white-space:nowrap;">校区：</label>
                                        <select id="filter-schedule-campus" onchange="loadScheduleView()" style="padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
                                            <option value="">全部校区</option>
                                        </select>
                                        <label style="font-size:13px;white-space:nowrap;">教师：</label>
                                        <select id="filter-schedule-teacher" onchange="loadScheduleView()" style="padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
                                            <option value="">全部教师</option>
                                        </select>
                                        <label style="font-size:13px;white-space:nowrap;">教室：</label>
                                        <select id="filter-schedule-classroom" onchange="loadScheduleView()" style="padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
                                            <option value="">全部教室</option>
                                        </select>
                                        <button class="btn btn-sm" onclick="navigateWeek(-1)" style="padding:4px 10px;" title="上一周/月">&lt;</button>
                                        <span id="schedule-week-range" style="font-size:14px;font-weight:600;color:#333;margin:0 4px;"></span>
                                        <button class="btn btn-sm" onclick="navigateWeek(1)" style="padding:4px 10px;" title="下一周/月">&gt;</button>
                                        <button class="btn btn-sm" onclick="navigateToday()" style="padding:4px 10px;background:#1890ff;color:#fff;border:none;border-radius:4px;" title="回到今天">📍 今天</button>
                                        <div style="display:flex;border:1px solid #ddd;border-radius:4px;overflow:hidden;margin-left:8px;">
                                            <button id="view-week-btn" class="schedule-view-toggle active" onclick="switchScheduleView('week')" style="padding:4px 12px;border:none;cursor:pointer;font-size:13px;background:#1890ff;color:#fff;">周</button>
                                            <button id="view-month-btn" class="schedule-view-toggle" onclick="switchScheduleView('month')" style="padding:4px 12px;border:none;cursor:pointer;font-size:13px;background:#fff;color:#333;">月</button>
                                        </div>
                                        <button class="btn btn-sm" id="schedule-drag-toggle" onclick="toggleDragMode()" style="background:#f0f0f0;border:1px solid #ddd;border-radius:4px;padding:4px 12px;font-size:13px;">📋 排课模式</button>
                                    </div>
                                </div>
                                <div class="schedule-table-wrap" id="schedule-week-view">
                                    <table class="schedule-table" id="schedule-table">
                                        <thead id="schedule-thead"></thead>
                                        <tbody id="schedule-tbody"></tbody>
                                    </table>
                                </div>
                                <div class="schedule-month-wrap" id="schedule-month-view" style="display:none;">
                                    <div class="schedule-month-header">
                                        <span>周一</span><span>周二</span><span>周三</span><span>周四</span><span>周五</span><span class="schedule-month-weekend">周六</span><span class="schedule-month-weekend">周日</span>
                                    </div>
                                    <div class="schedule-month-grid" id="schedule-month-grid"></div>
                                </div>
                            </div>
                        </div>
                        <!-- 排课确认弹窗 -->
                        <div class="modal-overlay" id="schedule-create-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;">
                            <div class="modal-content" style="background:#fff;border-radius:8px;padding:24px;max-width:480px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,0.2);">
                                <h3 style="margin:0 0 16px;">确认排课</h3>
                                <div id="schedule-create-summary" style="margin-bottom:12px;line-height:2;font-size:14px;color:#555;"></div>
                                <div style="margin-bottom:12px;">
                                    <label style="font-size:13px;display:block;margin-bottom:4px;">班级名称</label>
                                    <input type="text" id="sc-class-name" style="width:100%;padding:6px 10px;border:1px solid #ddd;border-radius:4px;" placeholder="自动生成">
                                </div>
                                <div style="display:flex;gap:12px;margin-bottom:12px;">
                                    <div style="flex:1;">
                                        <label style="font-size:13px;display:block;margin-bottom:4px;">开课日期</label>
                                        <input type="date" id="sc-start-date" style="width:100%;padding:6px 10px;border:1px solid #ddd;border-radius:4px;">
                                    </div>
                                    <div style="flex:1;">
                                        <label style="font-size:13px;display:block;margin-bottom:4px;">结课日期</label>
                                        <input type="date" id="sc-end-date" style="width:100%;padding:6px 10px;border:1px solid #ddd;border-radius:4px;">
                                    </div>
                                </div>
                                <div style="display:flex;gap:12px;margin-bottom:12px;">
                                    <div style="flex:1;">
                                        <label style="font-size:13px;display:block;margin-bottom:4px;">招生人数</label>
                                        <input type="number" id="sc-max-students" value="15" min="1" style="width:100%;padding:6px 10px;border:1px solid #ddd;border-radius:4px;">
                                    </div>
                                    <div style="flex:1;">
                                        <label style="font-size:13px;display:block;margin-bottom:4px;">授课课时</label>
                                        <input type="number" id="sc-lesson-hours" value="2" min="2" step="2" oninput="this.value=Math.max(2,parseInt(this.value)||2);if(this.value%2!==0)this.value=parseInt(this.value)+1" style="width:100%;padding:6px 10px;border:1px solid #ddd;border-radius:4px;">
                                    </div>
                                </div>
                                <div style="text-align:right;display:flex;gap:8px;justify-content:flex-end;">
                                    <button class="btn btn-sm" onclick="closeScheduleCreateModal()" style="padding:6px 14px;">取消</button>
                                    <button class="btn btn-primary btn-sm" onclick="confirmScheduleCreate()" style="padding:6px 14px;">确认创建</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>


            <!-- 面板：学员详情 -->
            <section class="content-panel" id="panel-student-detail">
                <div class="panel-header">
                    <h3>学员详情</h3>
                    <button class="btn btn-outline btn-sm" onclick="switchToStudents()" style="margin-left:auto;">返回列表</button>
                    <button class="btn btn-primary btn-sm" id="btn-enroll-from-detail" style="margin-left:8px;" onclick="goEnroll(currentViewStudentId)">报名</button>
                </div>
                <!-- 学员基础信息 -->
                <div id="student-detail-info" style="padding:16px 16px 0;"></div>
                <!-- 累计汇总 -->
                <div id="student-summary" style="margin:12px 16px 0;display:none;"></div>
                <!-- 标签页 -->
                <div class="student-detail-tabs">
                    <button class="sdt-tab active" data-tab="tab-courses">报读课程</button>
                    <button class="sdt-tab" data-tab="tab-orders">交易订单</button>
                    <button class="sdt-tab" data-tab="tab-attendance">上课记录</button>
                    <button class="sdt-tab" data-tab="tab-account">账户</button>
                </div>
                <div class="student-detail-tab-content">
                    <!-- 报读课程 -->
                    <div class="sdt-panel active" id="tab-courses">
                        <div id="student-courses-content" style="padding:8px 16px 16px;">
                            <div style="text-align:center;color:#999;padding:20px;">加载中...</div>
                        </div>
                    </div>
                    <!-- 交易订单 -->
                    <div class="sdt-panel" id="tab-orders">
                        <div id="student-orders-content" style="padding:8px 16px 16px;">
                            <div style="text-align:center;color:#999;padding:20px;">加载中...</div>
                        </div>
                    </div>
                    <!-- 上课记录 -->
                    <div class="sdt-panel" id="tab-attendance">
                        <div style="padding:8px 16px 16px;">
                            <button class="btn btn-primary btn-sm" onclick="showAttendanceModal()" style="margin-bottom:12px;">+ 新增上课记录</button>
                            <div class="table-wrap">
                                <table class="attendance-table">
                                    <thead><tr>
                                        <th>校区</th><th>课程</th><th>一级学科</th><th>二级学科</th><th>班级</th><th>授课教师</th><th>上课日期</th><th>上课时间</th><th>考勤时间</th><th>出勤状态</th><th>消耗课时</th><th>课耗金额</th>
                                    </tr></thead>
                                    <tbody id="attendance-tbody">
                                        <tr><td colspan="12" style="text-align:center;color:#999;padding:20px;">加载中...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- 账户 -->
                    <div class="sdt-panel" id="tab-account">
                        <div style="padding:8px 16px 16px;">
                            <!-- 余额卡片 -->
                            <div id="account-balance-cards" style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap;">
                                <div style="flex:1;min-width:220px;background:linear-gradient(135deg, #11998e 0%, #38ef7d 100%);border-radius:10px;padding:20px 24px;color:#fff;box-shadow:0 4px 12px rgba(17,153,142,0.3);">
                                    <div style="font-size:13px;opacity:0.85;margin-bottom:4px;">账户余额</div>
                                    <div style="font-size:32px;font-weight:700;line-height:1.2;" id="account-balance">¥0.00</div>
                                </div>
                                <div style="flex:1;min-width:220px;background:#f8f9fb;border:1px solid #e8ecf1;border-radius:10px;padding:16px 20px;display:flex;flex-direction:column;gap:8px;">
                                    <div style="display:flex;justify-content:space-between;font-size:13px;"><span style="color:#666;">累计充值</span><span style="font-weight:600;color:#11998e;" id="account-total-deposit">¥0.00</span></div>
                                    <div style="display:flex;justify-content:space-between;font-size:13px;"><span style="color:#666;">累计消费</span><span style="font-weight:600;color:#e74c3c;" id="account-total-consume">¥0.00</span></div>
                                    <div style="display:flex;justify-content:space-between;font-size:13px;"><span style="color:#666;">累计退款</span><span style="font-weight:600;color:#e67e22;" id="account-total-refund">¥0.00</span></div>
                                    <button class="btn btn-primary btn-sm" onclick="showRechargeModal()" style="margin-top:4px;align-self:flex-start;">+ 充值</button>
                                    <button class="btn btn-outline btn-sm" id="btn-account-refund" onclick="showAccountRefundModal()" style="margin-top:4px;align-self:flex-start;margin-left:8px;color:#e74c3c;border-color:#e74c3c;">申请退费</button>
                                </div>
                            </div>
                            <!-- 筛选栏 -->
                            <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px;flex-wrap:wrap;" id="account-filter-bar">
                                <label style="font-size:13px;">类型：</label>
                                <select id="account-filter-type" onchange="filterAccountTransactions()" style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                                    <option value="">全部</option>
                                    <option value="deposit">充值</option>
                                    <option value="consume">消费</option>
                                    <option value="refund">退款</option>
                                </select>
                                <label style="font-size:13px;margin-left:8px;">日期：</label>
                                <input type="date" id="account-filter-date-from" onchange="filterAccountTransactions()" style="width:140px;padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                                <span style="color:#999;">至</span>
                                <input type="date" id="account-filter-date-to" onchange="filterAccountTransactions()" style="width:140px;padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                                <button class="btn btn-sm" onclick="filterAccountTransactions()" style="padding:6px 14px;">查询</button>
                            </div>
                            <!-- 流水表格 -->
                            <div class="table-wrap">
                                <table>
                                    <thead><tr>
                                        <th>日期时间</th><th>类型</th><th>支付方式</th><th>金额</th><th>余额变动后</th><th>关联单号</th><th>校区</th><th>备注</th>
                                    </tr></thead>
                                    <tbody id="account-transactions-tbody">
                                        <tr><td colspan="8" style="text-align:center;color:#999;padding:20px;">加载中...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- 分页 -->
                            <div class="pagination" id="pagination-account"></div>
                        </div>
                    </div>
                </div>

            </section>

            <!-- 面板：报名详情 -->
            <section class="content-panel" id="panel-enroll">
                <div class="panel-header">
                    <h3>报名详情</h3>
                    <button class="btn btn-outline" id="btn-enroll-back">返回</button>
                </div>

                <!-- 学员信息卡片 -->
                <div class="enroll-student-info" id="enroll-student-info">
                    <div class="enroll-student-avatar">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-7 8-7s8 3 8 7"/></svg>
                    </div>
                    <div class="enroll-student-details">
                        <div class="enroll-student-name" id="enroll-info-name">-</div>
                        <div class="enroll-student-phone" id="enroll-info-phone">-</div>
                        <div class="enroll-student-balance" id="enroll-info-balance" style="font-size:13px;color:#16a34a;margin-top:2px;">账户余额：-</div>
                    </div>
                </div>

                <!-- 表单区域 -->
                <div class="enroll-form">
                    <!-- 校区/课程 — 上下布局 -->
                    <div class="enroll-form-row" style="flex-direction: column; gap: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>校区 <span class="required">*</span></label>
                            <select id="enroll-campus-select"><option value="">请选择校区</option></select>
                        </div>
                        <div class="enroll-course-picker" id="enroll-course-picker">
                            <label>课程 <span class="required">*</span></label>
                            <div class="course-picker-body">
                                <div class="course-picker-search">
                                    <svg class="search-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#9895A8" stroke-width="2">
                                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                                    </svg>
                                    <input type="text" id="enroll-course-search" placeholder="请先选择校区" disabled autocomplete="off">
                                </div>
                                <div class="course-picker-chips" id="enroll-course-chips"></div>
                                <div class="course-picker-list" id="enroll-course-list">
                                    <div class="course-picker-empty">请先选择校区</div>
                                </div>
                            </div>
                            <input type="hidden" id="enroll-course-id" value="">
                        </div>
                    </div>

                    <!-- 价格方案 -->
                    <div class="enroll-section" id="enroll-plans-section" style="display:none;">
                        <div class="enroll-section-title">选择价格方案</div>
                        <div id="enroll-plans-list"></div>
                    </div>

                    <!-- 报价明细 -->
                    <div class="enroll-section" id="enroll-items-section" style="display:none;">
                        <div class="enroll-section-title">报价明细</div>
                        <div class="enroll-table-wrap">
                            <table class="enroll-items-table">
                                <thead><tr>
                                    <th>报价项名称</th>
                                    <th class="col-num">课时数</th>
                                    <th class="col-num">单价</th>
                                    <th class="col-num">实际价格</th>
                                </tr></thead>
                                <tbody id="enroll-items-tbody"></tbody>
                            </table>
                        </div>
                        <div class="enroll-total-bar">
                            <span class="enroll-total-label">合计金额</span>
                            <span class="enroll-total-amount" id="enroll-total-price">¥0.00</span>
                        </div>

                        <!-- 支付方式 -->
                        <div class="enroll-section" id="enroll-payment-section">
                            <div class="enroll-section-title">支付方式</div>
                            <div class="enroll-payment-row">
                                <div class="enroll-payment-card">
                                    <div class="enroll-payment-header">
                                        <span class="enroll-payment-icon">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="12" y1="10" x2="12" y2="14"/></svg>
                                        </span>
                                        <span class="enroll-payment-label">现金</span>
                                    </div>
                                    <input type="number" id="enroll-payment-cash" class="enroll-payment-input" step="0.01" min="0" value="0" oninput="onPaymentInput()" placeholder="0.00">
                                </div>
                                <div class="enroll-payment-card">
                                    <div class="enroll-payment-header">
                                        <span class="enroll-payment-icon enroll-payment-icon-meituan">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                                        </span>
                                        <span class="enroll-payment-label">美团</span>
                                    </div>
                                    <input type="number" id="enroll-payment-meituan" class="enroll-payment-input" step="0.01" min="0" value="0" oninput="onPaymentInput()" placeholder="0.00">
                                </div>
                                <div class="enroll-payment-card enroll-payment-card-balance">
                                    <div class="enroll-payment-header">
                                        <span class="enroll-payment-icon enroll-payment-icon-balance">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                        </span>
                                        <span class="enroll-payment-label">账户余额 <span id="enroll-balance-avail" style="font-weight:400;font-size:12px;color:#16a34a;">(¥0.00)</span></span>
                                    </div>
                                    <input type="number" id="enroll-payment-balance" class="enroll-payment-input" step="0.01" min="0" value="0" oninput="onPaymentInput()" placeholder="0.00">
                                </div>
                            </div>
                            <div id="enroll-payment-hint" class="enroll-payment-hint" style="display:none;"></div>
                        </div>

                        <!-- 操作栏 -->
                        <div class="enroll-action-bar">
                            <button class="btn btn-primary btn-lg" id="btn-confirm-pay" onclick="confirmPayEnroll()">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                确认支付
                            </button>
                        </div>
                    </div>
                </div>
            </section>


            <!-- 面板：交易订单 -->
            <section class="content-panel" id="panel-orders">
                <div class="panel-header">
                    <h3>交易订单</h3>
                    <div class="header-stats-inline">
                        <span class="stat-badge">订单总数：<strong id="stat-orders-inline">0</strong></span>
                    </div>
                </div>
                <div class="toolbar">
                    <div class="toolbar-left" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        <label style="font-size:13px;white-space:nowrap;">区域：</label>
                        <select id="filter-order-region" onchange="onOrderRegionChange()" style="padding:5px 8px;border:1px solid #ddd;border-radius:4px;min-width:100px;">
                            <option value="">全部区域</option>
                        </select>
                        <label style="font-size:13px;white-space:nowrap;">校区：</label>
                        <select id="filter-order-campus" onchange="loadOrders()" style="padding:5px 8px;border:1px solid #ddd;border-radius:4px;min-width:120px;">
                            <option value="">全部校区</option>
                        </select>
                        <label style="font-size:13px;white-space:nowrap;margin-left:4px;">支付时间：</label>
                        <input type="date" id="filter-pay-date-start" onchange="loadOrders()" style="padding:4px 6px;border:1px solid #ddd;border-radius:4px;width:130px;">
                        <span style="color:#999;">至</span>
                        <input type="date" id="filter-pay-date-end" onchange="loadOrders()" style="padding:4px 6px;border:1px solid #ddd;border-radius:4px;width:130px;">
                    </div>
                    <div class="toolbar-right" style="margin-left:auto;">
                        <input type="text" id="search-order" placeholder="搜索学员/课程..." onkeyup="debounceSearch('order')">
                        <select id="filter-pay-status" onchange="loadOrders()" style="margin-left:6px;padding:4px 8px;border:1px solid #ddd;border-radius:4px;">
                            <option value="">支付状态</option>
                            <option value="已支付">已支付</option>
                            <option value="待支付">待支付</option>
                            <option value="已取消">已取消</option>
                        </select>
                        <select id="filter-is-voided" onchange="loadOrders()" style="margin-left:4px;padding:4px 8px;border:1px solid #ddd;border-radius:4px;">
                            <option value="">是否作废</option>
                            <option value="否">否</option>
                            <option value="是">是</option>
                        </select>
                        <button class="btn btn-primary btn-sm" onclick="loadOrders()">搜索</button>
                    </div>
                </div>
                <div class="payment-summary" id="payment-summary-order" style="display:none;"></div>
                <div class="table-scroll-wrapper">
                    <div class="table-scroll-top"><div class="table-scroll-top-inner"></div></div>
                    <div class="table-scroll-body">
                        <table id="table-orders">
                            <thead><tr>
                                <th width="80">订单号</th><th width="80">父订单号</th><th width="50">学号</th><th width="60">编号</th><th>学员姓名</th><th>校区</th><th>一级学科</th><th>二级学科</th><th>课程名称</th><th>价格方案</th><th>报价单名称</th><th>课时数量</th><th>订单金额</th><th>现金</th><th>美团</th><th>账户</th><th>订单创建时间</th><th>订单支付时间</th><th>订单类型</th><th width="70">支付状态</th><th width="60">是否作废</th><th width="80">操作</th>
                            </tr></thead>
                            <tbody></tbody>
                            <tfoot id="table-orders-foot" style="display:none;"></tfoot>
                        </table>
                    </div>
                </div>
                <div class="pagination" id="pagination-order"></div>
            </section>

            <!-- 面板：工作记录 -->
            <section class="content-panel" id="panel-work-records">
                <div class="panel-header">
                    <h3>工作记录</h3>
                </div>
                <div class="section-tabs">
                    <button class="sec-tab active" data-tab="tab-refund-records">退费记录</button>
                    <button class="sec-tab" data-tab="tab-course-records">课程记录</button>
                </div>
                <div class="section-tab-content">
                    <!-- 退费记录 tab -->
                    <div class="sec-panel active" id="tab-refund-records">
                        <div class="toolbar">
                            <div class="toolbar-left" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                <label style="font-size:13px;white-space:nowrap;">区域：</label>
                                <select id="filter-refund-region" onchange="onRefundRegionChange()" style="padding:5px 8px;border:1px solid #ddd;border-radius:4px;min-width:100px;">
                                    <option value="">全部区域</option>
                                </select>
                                <label style="font-size:13px;white-space:nowrap;">校区：</label>
                                <select id="filter-refund-campus" onchange="loadRefundRecords()" style="padding:5px 8px;border:1px solid #ddd;border-radius:4px;min-width:120px;">
                                    <option value="">全部校区</option>
                                </select>
                                <label style="font-size:13px;white-space:nowrap;">项目：</label>
                                <select id="filter-refund-project" onchange="loadRefundRecords()" style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                                    <option value="">全部</option>
                                    <option value="课程">课程退费</option>
                                    <option value="账户">账户退费</option>
                                </select>
                                <select id="filter-refund-status" onchange="loadRefundRecords()" style="padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
                                    <option value="">全部状态</option>
                                    <option value="待审批">待审批</option>
                                    <option value="一级审批通过">一级审批通过</option>
                                    <option value="二级审批通过">二级审批通过</option>
                                    <option value="已退费">已退费</option>
                                    <option value="审批驳回">审批驳回</option>
                                </select>
                            </div>
                            <div class="toolbar-right" style="margin-left:auto;">
                                <input type="text" id="search-refund" placeholder="搜索学员/课程/订单号..." onkeyup="debounceSearch('refund')">
                                <label style="font-size:13px;margin:0 6px;">申请时间：</label>
                                <input type="date" id="filter-refund-date-from" style="width:140px;" onchange="loadRefundRecords()">
                                <span style="margin:0 4px;color:#999;">至</span>
                                <input type="date" id="filter-refund-date-to" style="width:140px;" onchange="loadRefundRecords()">
                                <button class="btn btn-primary btn-sm" onclick="loadRefundRecords()">搜索</button>
                            </div>
                        </div>
                        <div class="table-wrap">
                            <table id="table-refund-records">
                                <thead><tr>
                                    <th width="80">订单号</th><th>学员</th><th width="60">项目</th><th>内容</th><th>学科</th><th>校区</th><th>实退金额</th><th>扣减金额</th><th>退费方式</th><th width="80">状态</th><th width="120">申请时间</th><th width="100">操作</th>
                                </tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="pagination" id="pagination-refund"></div>
                    </div>
                    <!-- 课程记录 tab（预留） -->
                    <div class="sec-panel" id="tab-course-records">
                        <div style="text-align:center;color:#999;padding:40px;">课程记录功能开发中...</div>
                    </div>
                </div>
            </section>

            <!-- 面板：优惠管理 -->
            <section class="content-panel" id="panel-discounts">
                <div class="panel-header"><h3>优惠管理</h3></div>
                <div class="section-tabs">
                    <span class="sec-tab active" data-tab="tab-discount-plans">优惠方案</span>
                    <span class="sec-tab" data-tab="tab-coupons">优惠券</span>
                    <span class="sec-tab" data-tab="tab-coupon-records">发放记录</span>
                </div>
                <div id="tab-discount-plans">
                    <div class="toolbar">
                        <div class="toolbar-left">
                            <input type="text" id="discount-search" placeholder="搜索方案名称..." oninput="debounceSearch('discount')" style="width:200px;">
                            <select id="discount-type-filter" onchange="loadDiscountPlans()">
                                <option value="">全部类型</option>
                                <option value="新报">新报</option>
                                <option value="续费">续费</option>
                            </select>
                        </div>
                        <div class="toolbar-right">
                            <button class="btn btn-primary btn-sm" onclick="showDiscountPlanForm()">+ 新增方案</button>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table id="table-discount-plans">
                            <thead><tr>
                                <th>方案名称</th><th>类型</th><th>优惠金额</th><th>有效期</th><th>适用校区</th><th>适用学科</th><th width="100">创建时间</th><th width="120">操作</th>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="pagination" id="pagination-discount"></div>
                </div>

                <!-- Tab 2: 优惠券 -->
                <div id="tab-coupons" style="display:none;">
                    <div class="toolbar">
                        <div class="toolbar-left">
                            <input type="text" id="coupon-search" placeholder="搜索优惠券名称..." oninput="debounceSearch('coupon')" style="width:220px;">
                            <select id="coupon-type-filter" onchange="loadCoupons()">
                                <option value="">全部类型</option>
                                <option value="课程券">课程券</option>
                                <option value="商品券">商品券</option>
                            </select>
                        </div>
                        <div class="toolbar-right">
                            <button class="btn btn-primary btn-sm" onclick="showCouponForm()">+ 新增优惠券</button>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table id="table-coupons">
                            <thead><tr>
                                <th>优惠券名称</th><th>类型</th><th>优惠金额</th><th>有效期</th><th>适用校区</th><th>适用学科</th><th>已发放</th><th width="120">操作</th>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="pagination" id="pagination-coupon"></div>
                </div>

                <!-- Tab 3: 发放记录 -->
                <div id="tab-coupon-records" style="display:none;">
                    <div class="toolbar">
                        <div class="toolbar-left">
                            <input type="text" id="cr-search" placeholder="搜索学员/手机号..." oninput="debounceSearch('cr')" style="width:200px;">
                            <input type="date" id="cr-date-from" style="width:135px;" onchange="loadCouponRecords()">
                            <span style="margin:0 4px;color:#999;">至</span>
                            <input type="date" id="cr-date-to" style="width:135px;" onchange="loadCouponRecords()">
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table id="table-coupon-records">
                            <thead><tr>
                                <th>发放时间</th><th>优惠券</th><th>优惠金额</th><th>学员姓名</th><th>手机号</th><th>发放人</th><th width="80">操作</th>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="pagination" id="pagination-cr"></div>
                </div>
            </section>

            <!-- 面板：课表视图 -->
            <!-- 面板：学科设置 -->
            <section class="content-panel" id="panel-subjects">
                <div class="panel-header">
                    <h3>学科设置</h3>
                </div>
                <div class="org-layout">
                    <div class="org-tree-panel" style="flex:1;max-width:100%;">
                        <div class="org-tree-toolbar">
                            <button class="btn btn-primary btn-sm" onclick="showSubjectModal(0, 0)">+ 新增一级学科</button>
                            <button class="btn btn-outline btn-sm" onclick="showSubjectModal(0, -1)" style="margin-left:6px;">+ 新增二级学科</button>
                        </div>
                        <div class="org-tree-wrap" id="subject-tree-wrap">
                            <div class="org-tree-loading">加载中...</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 面板：教室管理 -->
            <section class="content-panel" id="panel-classrooms">
                <div class="panel-header">
                    <h3>教室管理</h3>
                </div>
                <div class="toolbar">
                    <div class="toolbar-left">
                        <button class="btn btn-primary" onclick="showClassroomForm()">+ 新增教室</button>
                    </div>
                    <div class="toolbar-right" style="margin-left:auto;">
                        <input type="text" id="search-classroom" placeholder="搜索教室名称..." onkeyup="debounceSearch('classroom')">
                        <button class="btn btn-primary btn-sm" onclick="loadClassrooms()">搜索</button>
                    </div>
                </div>
                <div class="table-wrap">
                    <table id="table-classrooms">
                        <thead><tr>
                            <th>教室名称</th><th>容纳人数</th><th>所属校区</th><th>备注</th><th>创建时间</th><th width="160">操作</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </section>

            <!-- 面板：确收统计 -->
            <section class="content-panel" id="panel-revenue">
                <div class="panel-header">
                    <h3>确收统计</h3>
                </div>
                <div class="toolbar">
                    <div class="toolbar-left">
                        <label style="font-size:13px;margin-right:6px;">校区：</label>
                        <div class="cf-tree-select" id="rv-campus-wrap">
                            <button type="button" class="cf-tree-btn" id="rv-campus-btn" onclick="toggleRevenueCampusTree()">
                                <span id="rv-campus-text">全部校区</span>
                                <span class="cf-tree-arrow">▾</span>
                            </button>
                            <div class="cf-tree-dropdown" id="rv-campus-dropdown" style="display:none;">
                                <div class="cf-tree-actions">
                                    <label class="cf-tree-check"><input type="checkbox" id="rv-campus-all" onchange="toggleAllRevenueCampuses()"> 全选</label>
                                </div>
                                <div class="cf-tree-list" id="rv-campus-tree"></div>
                            </div>
                        </div>
                        <label style="font-size:13px;margin:0 6px;">日期范围：</label>
                        <input type="month" id="rv-date-from" style="width:150px;padding:5px 8px;border:1px solid #ddd;border-radius:4px;" onchange="loadRevenue()">
                        <span style="margin:0 4px;color:#999;">至</span>
                        <input type="month" id="rv-date-to" style="width:150px;padding:5px 8px;border:1px solid #ddd;border-radius:4px;" onchange="loadRevenue()">
                        <label style="font-size:13px;margin:0 6px;">粒度：</label>
                        <select id="rv-granularity" onchange="onRevenueGranularityChange()" style="padding:5px 8px;border:1px solid #ddd;border-radius:4px;">
                            <option value="monthly">按月</option>
                            <option value="daily">按日</option>
                            <option value="yearly">按年</option>
                        </select>
                    </div>
                    <div class="toolbar-right" style="margin-left:auto;">
                        <button class="btn btn-primary btn-sm" onclick="loadRevenue()" style="margin-left:6px;">查询</button>
                    </div>
                </div>
                <div class="cashflow-summary" id="revenue-summary">
                    <div class="cf-card cf-card-income"><div class="cf-card-label">总收入</div><div class="cf-card-value" id="rv-total-income">--</div></div>
                    <div class="cf-card cf-card-expense"><div class="cf-card-label">总支出</div><div class="cf-card-value" id="rv-total-expense">--</div></div>
                    <div class="cf-card cf-card-net"><div class="cf-card-label">净现金流</div><div class="cf-card-value" id="rv-net-cashflow">--</div></div>
                </div>
                <div class="cf-charts" id="rv-charts" style="display:flex;gap:20px;margin-bottom:16px;flex-wrap:wrap;">
                    <div class="cf-chart-container" style="flex:1;min-width:300px;background:#fff;border-radius:8px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.08);">
                        <h4 style="margin:0 0 12px;font-size:14px;color:#333;">总收入</h4>
                        <div style="position:relative;height:300px;"><canvas id="rv-bar-chart"></canvas></div>
                    </div>
                    <div class="cf-chart-container" style="flex:1;min-width:300px;background:#fff;border-radius:8px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.08);">
                        <h4 style="margin:0 0 12px;font-size:14px;color:#333;">总支出</h4>
                        <div style="position:relative;height:300px;"><canvas id="rv-expense-chart"></canvas></div>
                    </div>
                    <div class="cf-chart-container" style="flex:1;min-width:300px;background:#fff;border-radius:8px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.08);">
                        <h4 style="margin:0 0 12px;font-size:14px;color:#333;">净现金流</h4>
                        <div style="position:relative;height:300px;"><canvas id="rv-line-chart"></canvas></div>
                    </div>
                </div>
                <div id="rv-rank-chart-wrapper" style="margin-bottom:16px;background:#fff;border-radius:8px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.08);">
                    <h4 style="margin:0 0 12px;font-size:14px;color:#333;">校区现金流排名</h4>
                    <div style="position:relative;height:300px;"><canvas id="rv-rank-chart"></canvas></div>
                </div>
                <div class="table-wrap">
                    <table id="table-revenue">
                        <thead><tr>
                            <th>校区</th><th>日期</th><th>收入笔数</th><th>收入金额</th><th>支出笔数</th><th>支出金额</th><th>净现金流</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </section>

            <section class="content-panel" id="panel-cashflow">
                <div class="panel-header">
                    <h3>现金流统计</h3>
                </div>
                <!-- 筛选栏 -->
                <div class="toolbar">
                    <div class="toolbar-left">
                        <label style="font-size:13px;margin-right:6px;">校区：</label>
                        <div class="cf-tree-select" id="cf-campus-wrap">
                            <button type="button" class="cf-tree-btn" id="cf-campus-btn" onclick="toggleCampusTree()">
                                <span id="cf-campus-text">全部校区</span>
                                <span class="cf-tree-arrow">▾</span>
                            </button>
                            <div class="cf-tree-dropdown" id="cf-campus-dropdown" style="display:none;">
                                <div class="cf-tree-actions">
                                    <label class="cf-tree-check"><input type="checkbox" id="cf-campus-all" onchange="toggleAllCashflowCampuses()"> 全选</label>
                                </div>
                                <div class="cf-tree-list" id="cf-campus-tree"></div>
                            </div>
                        </div>
                        <label style="font-size:13px;margin:0 6px;">日期范围：</label>
                        <input type="month" id="cf-date-from" style="width:150px;padding:5px 8px;border:1px solid #ddd;border-radius:4px;" onchange="loadCashflow()">
                        <span style="margin:0 4px;color:#999;">至</span>
                        <input type="month" id="cf-date-to" style="width:150px;padding:5px 8px;border:1px solid #ddd;border-radius:4px;" onchange="loadCashflow()">
                        <label style="font-size:13px;margin:0 6px;">粒度：</label>
                        <select id="cf-granularity" onchange="onCashflowGranularityChange()" style="padding:5px 8px;border:1px solid #ddd;border-radius:4px;">
                            <option value="monthly">按月</option>
                            <option value="daily">按日</option>
                            <option value="yearly">按年</option>
                        </select>
                    </div>
                    <div class="toolbar-right" style="margin-left:auto;">
                        <button class="btn btn-primary btn-sm" onclick="loadCashflow()" style="margin-left:6px;">查询</button>
                    </div>
                </div>
                <!-- 汇总卡片 -->
                <div class="cashflow-summary" id="cashflow-summary">
                    <div class="cf-card cf-card-income"><div class="cf-card-label">总收入</div><div class="cf-card-value" id="cf-total-income">--</div></div>
                    <div class="cf-card cf-card-expense"><div class="cf-card-label">总支出</div><div class="cf-card-value" id="cf-total-expense">--</div></div>
                    <div class="cf-card cf-card-net"><div class="cf-card-label">净现金流</div><div class="cf-card-value" id="cf-net-cashflow">--</div></div>
                </div>
                <!-- 图表区域 -->
                <div class="cf-charts" id="cf-charts" style="display:flex;gap:20px;margin-bottom:16px;flex-wrap:wrap;">
                    <div class="cf-chart-container" style="flex:1;min-width:300px;background:#fff;border-radius:8px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.08);">
                        <h4 style="margin:0 0 12px;font-size:14px;color:#333;">总收入</h4>
                        <div style="position:relative;height:300px;"><canvas id="cf-bar-chart"></canvas></div>
                    </div>
                    <div class="cf-chart-container" style="flex:1;min-width:300px;background:#fff;border-radius:8px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.08);">
                        <h4 style="margin:0 0 12px;font-size:14px;color:#333;">总支出</h4>
                        <div style="position:relative;height:300px;"><canvas id="cf-expense-chart"></canvas></div>
                    </div>
                    <div class="cf-chart-container" style="flex:1;min-width:300px;background:#fff;border-radius:8px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.08);">
                        <h4 style="margin:0 0 12px;font-size:14px;color:#333;">净现金流</h4>
                        <div style="position:relative;height:300px;"><canvas id="cf-line-chart"></canvas></div>
                    </div>
                </div>
                <!-- 校区排名图表 -->
                <div id="cf-rank-chart-wrapper" style="margin-bottom:16px;background:#fff;border-radius:8px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.08);">
                    <h4 style="margin:0 0 12px;font-size:14px;color:#333;">校区现金流排名</h4>
                    <div style="position:relative;height:300px;"><canvas id="cf-rank-chart"></canvas></div>
                </div>
                <div class="table-wrap">
                    <table id="table-cashflow">
                        <thead><tr>
                            <th>校区</th><th>日期</th><th>收入笔数</th><th>收入金额</th><th>支出笔数</th><th>支出金额</th><th>净现金流</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </section>

            <!-- 面板：班级管理 -->
            <section class="content-panel" id="panel-classes">
                <div class="panel-header">
                    <h3>班级管理</h3>
                    <div class="header-stats-inline">
                        <span class="stat-badge">班级总数：<strong id="stat-classes-inline">0</strong></span>
                    </div>
                </div>
                <div class="toolbar">
                    <div class="toolbar-left">
                        <button class="btn btn-primary" onclick="showClassForm()">+ 新增班级</button>
                    </div>
                    <div class="toolbar-right" style="margin-left:auto;">
                        <input type="text" id="search-class" placeholder="搜索班级名称..." onkeyup="debounceSearch('class')">
                        <button class="btn btn-primary btn-sm" onclick="loadClasses(1)">搜索</button>
                    </div>
                </div>
                <div class="table-wrap">
                    <table id="table-classes">
                        <thead><tr>
                            <th width="60">编号</th><th>班级名称</th><th>关联课程</th><th>一级学科</th><th>二级学科</th><th>班级类型</th><th>招生人数</th><th>授课课时</th><th>可试听</th><th>当前校区</th><th>备注</th><th>创建时间</th><th width="160">操作</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination-class"></div>
            </section>

            <!-- 面板：班级详情 -->
            <section class="content-panel" id="panel-class-detail">
                <div class="class-detail-breadcrumb">
                    <a href="javascript:void(0)" onclick="switchToClasses()" class="breadcrumb-back">&larr; 返回班级列表</a>
                    <span class="breadcrumb-sep">|</span>
                    <span class="breadcrumb-title" id="class-detail-name">班级详情</span>
                </div>
                <div class="class-detail-tabs">
                    <button class="cdt-tab active" data-tab="tab-class-students">学员列表</button>
                    <button class="cdt-tab" data-tab="tab-class-schedules">编辑排课</button>
                </div>
                <div class="class-detail-tab-content">
                    <!-- 学员列表 -->
                    <div class="cdt-panel active" id="tab-class-students">
                        <div class="toolbar" style="padding:12px 16px;">
                            <div class="toolbar-left">
                                <button class="btn btn-primary btn-sm" onclick="showAddStudentModal()">+ 添加学员</button>
                            </div>
                            <div class="toolbar-right" style="margin-left:auto;">
                                <input type="text" id="search-available-student" placeholder="搜索姓名/手机号..." onkeyup="debounceSearchAvailableStudent()">
                            </div>
                        </div>
                        <div class="table-wrap" style="margin:0 16px;">
                            <table id="table-class-students">
                                <thead><tr>
                                    <th width="60">学号</th>
                                    <th>姓名</th>
                                    <th>手机号</th>
                                    <th>来源</th>
                                    <th width="100">操作</th>
                                </tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <!-- 排课信息 -->
                    <div class="cdt-panel" id="tab-class-schedules">
                        <div class="toolbar" style="padding:12px 16px;">
                            <div class="toolbar-left">
                                <button class="btn btn-primary btn-sm" onclick="showScheduleForm(currentClassDetailId)">+ 新增排课</button>
                            </div>
                        </div>
                        <div class="table-wrap" style="margin:0 16px;">
                            <table id="table-class-schedules">
                                <thead><tr>
                                    <th width="50">序号</th>
                                    <th>日期</th>
                                    <th>星期</th>
                                    <th>上课时间</th>
                                    <th>授课老师</th>
                                    <th>上课教室</th>
                                    <th width="70">考勤状态</th>
                                    <th width="160">操作</th>
                                </tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- 弹窗：新增/编辑资源 -->
    <div class="modal-overlay" id="modal-resource">
        <div class="modal"><div class="modal-header"><h3 id="modal-resource-title">新增资源</h3><button class="modal-close" onclick="closeModal('modal-resource')">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="edit-rid">
            <div class="form-group"><label>姓名 <span class="required">*</span></label><input type="text" id="res-name"></div>
            <div class="form-group"><label>电话 <span class="required">*</span></label><input type="text" id="res-phone"></div>
            <div class="form-group"><label>来源渠道</label><select id="res-source"><option value="">请选择</option></select></div>
            <div class="form-group"><label>意向等级</label><select id="res-intention"><option value="">请选择</option></select></div>
            <div class="form-group"><label>性别</label><select id="res-gender"><option value="">请选择</option><option value="男">男</option><option value="女">女</option></select></div>
            <div class="form-group"><label>出生日期</label><input type="date" id="res-birth-date"></div>
            <div class="form-group"><label>跟进状态</label><select id="res-follow-status"><option value="">请选择</option><option value="未沟通">未沟通</option><option value="沟通中">沟通中</option><option value="已邀约未试听">已邀约未试听</option><option value="已试听待转化">已试听待转化</option><option value="已转化—定金">已转化—定金</option><option value="已转化—全款">已转化—全款</option><option value="无效客户">无效客户</option></select></div>
            <div class="form-group"><label>归属人</label><select id="res-assigned"><option value="">请选择</option></select></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-resource')">取消</button><button class="btn btn-primary" onclick="saveResource()">保存</button></div></div>
    </div>

    <!-- 弹窗：新增/编辑员工 -->
    <div class="modal-overlay" id="modal-employee">
        <div class="modal"><div class="modal-header"><h3 id="modal-employee-title">新增员工</h3><button class="modal-close" onclick="closeModal('modal-employee')">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="edit-eid">
            <div class="form-group"><label>姓名 <span class="required">*</span></label><input type="text" id="emp-name"></div>
            <div class="form-group"><label>手机号</label><input type="text" id="emp-phone"></div>
            <div class="form-group"><label>部门</label><select id="emp-department"><option value="">请选择（可不填）</option></select></div>
            <div class="form-group"><label>岗位</label><select id="emp-position"><option value="">请选择（可不填）</option></select></div>
            <div class="form-group"><label>入职日期</label><input type="date" id="emp-entry-date"></div>
            <div class="form-group"><label>状态</label><select id="emp-status"><option value="在职">在职</option><option value="离职">离职</option></select></div>
            <div class="form-group"><label>是否教师</label><select id="emp-is-teacher"><option value="否">否</option><option value="是">是</option></select></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-employee')">取消</button><button class="btn btn-primary" onclick="saveEmployee()">保存</button></div></div>
    </div>

    <!-- 弹窗：批量导入员工 -->
    <div class="modal-overlay" id="modal-batch-import-emp">
        <div class="modal modal-lg"><div class="modal-header"><h3>批量导入员工</h3><button class="modal-close" onclick="closeModal('modal-batch-import-emp')">&times;</button></div>
        <div class="modal-body">
            <p class="hint">请上传 Excel 文件（.xlsx / .xls），第一行为表头，从第二行开始读取数据。列顺序不限，系统会根据表头自动匹配。<strong>姓名为必填字段</strong>，缺少该字段的行将被跳过。支持的表头：姓名、手机号/电话、部门、岗位、入职日期、状态、是否教师。</p>
            <div class="form-group">
                <label>选择文件</label>
                <input type="file" id="batch-import-emp-file" accept=".xlsx,.xls" style="display:block;margin-top:4px;">
            </div>
            <div class="form-group">
                <button class="btn btn-outline btn-sm" onclick="downloadEmpTemplate()" style="margin-right:8px;">下载导入模板</button>
            </div>
            <div id="batch-import-emp-result" style="margin-top:12px;display:none;"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-batch-import-emp')">取消</button><button class="btn btn-primary" onclick="doBatchImportEmp()">开始导入</button></div></div>
    </div>

    <!-- 弹窗：新增/编辑课程 -->
    <div class="modal-overlay" id="modal-course">
        <div class="modal" style="max-width:540px;">
            <div class="modal-header">
                <h3 id="modal-course-title">新增课程</h3>
                <button class="modal-close" onclick="closeModal('modal-course')">&times;</button>
            </div>
            <div class="modal-body course-form-body">
                <input type="hidden" id="edit-cid">

                <!-- 基本信息 -->
                <div class="form-section">
                    <div class="form-section-title">基本信息</div>
                    <div class="form-group">
                        <label>课程名称 <span class="required">*</span></label>
                        <input type="text" id="course-name" maxlength="100" placeholder="请输入课程名称" autocomplete="off">
                        <span class="form-error" id="err-course-name"></span>
                    </div>
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label>一级学科</label>
                            <select id="course-subject-level1"><option value="">请选择一级学科</option></select>
                        </div>
                        <div class="form-group form-group-half">
                            <label>二级学科</label>
                            <select id="course-subject-level2"><option value="">请先选择一级学科</option></select>
                        </div>
                    </div>
                </div>

                <!-- 课程属性 -->
                <div class="form-section">
                    <div class="form-section-title">课程属性</div>
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label>小课包 <span class="form-tip" title="是否为短期小课时包课程">?</span></label>
                            <select id="course-small-package">
                                <option value="">请选择类型</option>
                                <option value="是">是（小课包）</option>
                                <option value="否">否（常规课程）</option>
                            </select>
                        </div>
                        <div class="form-group form-group-half">
                            <label>低幼龄 <span class="form-tip" title="是否面向低龄幼儿">?</span></label>
                            <select id="course-toddler">
                                <option value="">请选择类型</option>
                                <option value="是">是（低幼龄）</option>
                                <option value="否">否（常规）</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 适用校区 -->
                <div class="form-section">
                    <div class="form-section-header">
                        <span class="form-section-title">适用校区 <span class="required">*</span></span>
                        <button type="button" class="form-section-action" id="campus-toggle-all" onclick="toggleAllCampuses()">全选</button>
                    </div>
                    <div class="campus-tree" id="course-campus-tree">
                        <span style="color:#999;font-size:13px;">加载中...</span>
                    </div>
                    <span class="form-error" id="err-campus"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('modal-course')">取消</button>
                <button class="btn btn-primary" id="btn-save-course" onclick="saveCourse()">保存</button>
            </div>
        </div>
    </div>

    <!-- 弹窗：设置价格 -->
    <div class="modal-overlay" id="modal-price">
        <div class="modal modal-xl"><div class="modal-header"><h3 id="modal-price-title">设置价格</h3><button class="modal-close" onclick="closeModal('modal-price')">&times;</button></div>
        <div class="modal-body price-body">
            <!-- 左侧：价格方案列表 -->
            <div class="price-left price-panel">
                <div class="price-panel-header">📦 价格方案 <span class="price-plan-count-badge" id="price-plan-count">0</span></div>
                <div class="price-panel-list" id="price-plan-list">
                    <div style="padding:20px;color:#999;">加载中...</div>
                </div>
                <div class="price-panel-footer">
                    <button class="btn btn-primary btn-sm" onclick="addPlan()" style="width:100%;">+ 新增方案</button>
                </div>
            </div>
            <!-- 右侧：报价单列表 -->
            <div class="price-right price-detail">
                <div class="price-detail-header">报价单列表 <span id="price-plan-type-tag"></span></div>
                <div class="price-detail-table-wrap">
                    <table id="price-item-table" class="price-item-table">
                        <thead><tr><th>报价单名称</th><th>课时数量</th><th>课时价格</th><th>实际价格</th><th>优惠方案</th><th>优惠券</th><th width="120">操作</th></tr></thead>
                        <tbody id="price-item-table-body"></tbody>
                    </table>
                </div>
                <div class="price-detail-footer">
                    <button class="btn btn-primary btn-sm" onclick="addItem()">+ 新增报价单</button>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-price')">关闭</button></div></div>
    </div>

    <!-- 弹窗：新增/编辑价格方案名称 -->
    <div class="modal-overlay" id="modal-price-plan">
        <div class="modal"><div class="modal-header"><h3 id="modal-price-plan-title">新增价格方案</h3><button class="modal-close" onclick="closeModal('modal-price-plan')">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="edit-price-plan-id">
            <div class="form-group"><label>方案名称 <span class="required">*</span></label><input type="text" id="price-plan-name-input" maxlength="50" placeholder="如：标准版、暑期特惠"></div>
            <div class="form-group"><label>方案类型</label><select id="price-plan-type-select"><option value="">请选择</option><option value="新报">新报</option><option value="续费">续费</option><option value="小课包">小课包</option></select></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-price-plan')">取消</button><button class="btn btn-primary" onclick="savePlan()">保存</button></div></div>
    </div>

    <!-- 弹窗：新增/编辑报价单 -->
    <div class="modal-overlay" id="modal-price-item">
        <div class="modal"><div class="modal-header"><h3 id="modal-price-item-title">新增报价单</h3><button class="modal-close" onclick="closeModal('modal-price-item')">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="edit-price-item-id">

            <!-- 卡片：基本信息 -->
            <div class="pi-card">
                <div class="pi-card-title">📋 基本信息</div>
                <div class="pi-card-body">
                    <div class="form-group">
                        <label>报价单名称 <span class="required">*</span></label>
                        <input type="text" id="price-item-name" maxlength="50" placeholder="如：32课时包">
                    </div>
                    <div class="form-group">
                        <label>课时数量 <span class="required">*</span></label>
                        <input type="number" id="price-item-lesson-count" min="1" placeholder="请输入课时数量">
                    </div>
                </div>
            </div>

            <!-- 卡片：价格设置 -->
            <div class="pi-card">
                <div class="pi-card-title">💰 价格设置</div>
                <div class="pi-card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>课时价格 <span class="required">*</span></label>
                            <input type="number" id="price-item-unit-price" step="0.01" min="0" placeholder="请输入课时价格" oninput="onUnitPriceChange()">
                        </div>
                        <div class="form-group pi-actual-price-group">
                            <label>实际支付价格</label>
                            <input type="number" id="price-item-actual-price" step="0.01" min="0" readonly placeholder="自动同步课时价格">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 卡片：优惠关联 -->
            <div class="pi-card" id="pi-discount-card">
                <div class="pi-card-title">🎁 优惠关联</div>
                <div class="pi-card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>优惠方案</label>
                            <select id="price-item-discount-plan"><option value="">不使用优惠方案</option></select>
                        </div>
                        <div class="form-group">
                            <label>课时优惠券</label>
                            <select id="price-item-coupon"><option value="">不使用优惠券</option></select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-price-item')">取消</button><button class="btn btn-primary" onclick="saveItem()">保存</button></div></div>
    </div>

    <!-- 弹窗：新增/编辑组织 -->
    <div class="modal-overlay" id="modal-org">
        <div class="modal"><div class="modal-header"><h3 id="modal-org-title">新增组织</h3><button class="modal-close" onclick="closeModal('modal-org')">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="edit-oid">
            <div class="form-group"><label>名称 <span class="required">*</span></label><input type="text" id="org-name" maxlength="50"></div>
            <div class="form-group"><label>类型 <span class="required">*</span></label><select id="org-type"><option value="部门">部门</option><option value="校区">校区</option></select></div>
            <div class="form-group"><label>上级组织</label><select id="org-parent"><option value="0">无（根节点）</option></select></div>
            <div class="form-group"><label>排序号</label><input type="number" id="org-sort" value="0" min="0"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-org')">取消</button><button class="btn btn-primary" onclick="saveOrganization()">保存</button></div></div>
    </div>

    <!-- 弹窗：新增/编辑学科 -->
    <div class="modal-overlay" id="modal-subject">
        <div class="modal"><div class="modal-header"><h3 id="modal-subject-title">新增学科</h3><button class="modal-close" onclick="closeModal('modal-subject')">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="edit-sid">
            <div class="form-group"><label>学科名称 <span class="required">*</span></label><input type="text" id="subject-name" maxlength="50" placeholder="请输入学科名称"></div>
            <div class="form-group"><label>上级学科</label><select id="subject-parent"><option value="0">无（一级学科）</option></select></div>
            <div class="form-group"><label>排序号</label><input type="number" id="subject-sort" value="0" min="0"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-subject')">取消</button><button class="btn btn-primary" onclick="saveSubject()">保存</button></div></div>
    </div>

    <!-- 弹窗：批量导入 -->
    <div class="modal-overlay" id="modal-batch-import">
        <div class="modal modal-lg"><div class="modal-header"><h3>批量导入资源</h3><button class="modal-close" onclick="closeModal('modal-batch-import')">&times;</button></div>
        <div class="modal-body">
            <p class="hint">请上传 Excel 文件（.xlsx / .xls），第一行为表头，从第二行开始读取数据。列顺序不限，系统会根据表头自动匹配。<strong>姓名和手机号均为必填字段</strong>，缺少任一字段的行将被跳过。</p>
            <div class="form-group">
                <label>选择文件</label>
                <input type="file" id="batch-import-file" accept=".xlsx,.xls" style="display:block;margin-top:4px;">
            </div>
            <div class="form-group">
                <button class="btn btn-outline btn-sm" onclick="downloadTemplate()" style="margin-right:8px;">下载导入模板</button>
            </div>
            <div class="form-group" style="margin-top:12px"><label>导入到</label><select id="batch-import-pool"><option value="我的资源">我的资源</option><option value="资源公海">资源公海</option></select></div>
            <div id="batch-import-result" style="margin-top:12px;display:none;"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-batch-import')">取消</button><button class="btn btn-primary" onclick="doBatchImport()">开始导入</button></div></div>
    </div>

    <!-- 弹窗：预约试听 -->
    <div class="modal-overlay" id="modal-appointment">
        <div class="modal" style="max-width:1100px;width:95vw;border-radius:12px;max-height:90vh;display:flex;flex-direction:column;">
            <div class="modal-header" style="padding:14px 20px;border-bottom:1px solid #f0f0f0;">
                <h3 id="modal-appointment-title" style="font-size:16px;margin:0;">预约试听</h3>
                <button class="modal-close" onclick="closeModal('modal-appointment')" style="font-size:20px;">&times;</button>
            </div>
            <div class="modal-body" style="padding:16px 20px;flex:1;">
                <input type="hidden" id="trial-resource-id"><input type="hidden" id="trial-resource-name"><input type="hidden" id="trial-phone">
                <p style="margin:0 0 12px;font-size:13px;color:#555;background:#f5f7fa;padding:8px 14px;border-radius:8px;"><span id="trial-info-text" style="font-weight:600;"></span></p>
                <!-- 筛选行 -->
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;align-items:flex-end;">
                    <select id="trial-campus" onchange="onTrialFilterChange()" style="width:130px;height:34px;border:1px solid #e0e0e0;border-radius:6px;font-size:12px;padding:0 6px;"><option value="">全部校区</option></select>
                    <select id="trial-subject" onchange="onTrialSubjectFilterChange()" style="width:130px;height:34px;border:1px solid #e0e0e0;border-radius:6px;font-size:12px;padding:0 6px;"><option value="">全部学科</option></select>
                    <select id="trial-course" onchange="loadTrialTable()" style="width:130px;height:34px;border:1px solid #e0e0e0;border-radius:6px;font-size:12px;padding:0 6px;"><option value="">全部课程</option></select>
                    <select id="trial-teacher" onchange="loadTrialTable()" style="width:130px;height:34px;border:1px solid #e0e0e0;border-radius:6px;font-size:12px;padding:0 6px;"><option value="">全部老师</option></select>
                    <input type="date" id="trial-date-filter" onchange="loadTrialTable()" style="width:140px;height:34px;border:1px solid #e0e0e0;border-radius:6px;font-size:12px;padding:0 6px;" placeholder="日期">
                    <button class="btn btn-primary btn-sm" onclick="loadTrialTable()" style="height:34px;">查询</button>
                </div>
                <!-- 结果表格 -->
                <div style="border:1px solid #f0f0f0;border-radius:8px;">
                    <table style="width:100%;font-size:13px;border-collapse:collapse;white-space:nowrap;">
                        <thead><tr style="background:#fafafa;position:sticky;top:0;">
                            <th style="padding:8px 10px;text-align:left;border-bottom:1px solid #eee;">班级名称</th>
                            <th style="padding:8px 10px;text-align:left;border-bottom:1px solid #eee;">时间</th>
                            <th style="padding:8px 10px;text-align:left;border-bottom:1px solid #eee;">校区</th>
                            <th style="padding:8px 10px;text-align:left;border-bottom:1px solid #eee;">教师</th>
                            <th style="padding:8px 10px;text-align:left;border-bottom:1px solid #eee;">教室</th>
                            <th style="padding:8px 10px;text-align:center;border-bottom:1px solid #eee;width:80px;">操作</th>
                        </tr></thead>
                        <tbody id="trial-table-body">
                            <tr><td colspan="6" style="text-align:center;color:#bbb;padding:40px;">选择筛选条件后点击查询</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="padding:10px 20px;border-top:1px solid #f0f0f0;display:flex;justify-content:flex-end;gap:8px;">
                <button class="btn btn-outline" onclick="closeModal('modal-appointment')">关闭</button>
            </div>
        </div>
    </div>

    <!-- 弹窗：沟通记录 -->
    <div class="modal-overlay" id="modal-communication">
        <div class="modal modal-lg"><div class="modal-header"><h3>沟通记录 - <span id="comm-resource-name"></span></h3><button class="modal-close" onclick="closeModal('modal-communication')">&times;</button></div>
        <div class="modal-body">
            <div class="comm-history" id="comm-history"></div><hr>
            <div class="form-group"><label>新增沟通记录</label><textarea id="comm-content" rows="3" placeholder="请输入沟通内容..."></textarea></div>
            <div class="form-group"><label>沟通方式</label><select id="comm-type"><option value="电话">电话</option><option value="微信">微信</option><option value="面谈">面谈</option><option value="短信">短信</option></select></div>
            <div class="form-group"><label>更新跟进状态为</label><select id="comm-new-status"><option value="沟通中">沟通中</option><option value="已邀约未试听">已邀约未试听</option><option value="已试听待转化">已试听待转化</option><option value="已转化—定金">已转化—定金</option><option value="已转化—全款">已转化—全款</option><option value="无效客户">无效客户</option></select></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-communication')">关闭</button><button class="btn btn-primary" onclick="addCommunication()">提交记录</button></div></div>
    </div>

    <!-- 弹窗：批量分配 -->
    <div class="modal-overlay" id="modal-batch-assign">
        <div class="modal modal-xl"><div class="modal-header"><h3>批量分配</h3><button class="modal-close" onclick="closeModal('modal-batch-assign')">&times;</button></div>
        <div class="modal-body" style="padding:0;display:flex;height:520px;overflow:hidden;">
            <!-- 左侧：组织架构树 -->
            <div class="ba-left">
                <div class="ba-left-header">部门架构</div>
                <div class="ba-tree-wrap" id="ba-tree-wrap">
                    <div style="text-align:center;padding:24px;color:#999;">加载中...</div>
                </div>
            </div>
            <!-- 右侧：员工列表 -->
            <div class="ba-right">
                <div class="ba-search-bar">
                    <input type="text" id="ba-search-input" placeholder="请输入员工姓名、手机号" onkeydown="if(event.key==='Enter')searchEmployeesInAssign()">
                    <button class="btn btn-primary btn-sm" onclick="searchEmployeesInAssign()">查询</button>
                </div>
                <div class="ba-list-header">
                    <label class="ba-check-all"><input type="checkbox" id="ba-select-all" onchange="toggleSelectAllAssign()"> 全选</label>
                    <span class="ba-selected-count" id="ba-selected-count">已选 0 人</span>
                </div>
                <div class="ba-list-wrap" id="ba-employee-list"></div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline btn-cancel" onclick="closeModal('modal-batch-assign')">取消</button><button class="btn btn-primary" onclick="confirmBatchAssign()">确认</button></div></div>
    </div>

    <!-- 弹窗：新增/编辑学员 -->
    <div class="modal-overlay" id="modal-student">
        <div class="modal"><div class="modal-header"><h3 id="modal-student-title">新增学员</h3><button class="modal-close" onclick="closeModal('modal-student')">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="edit-sid">
            <div class="form-group"><label>姓名 <span class="required">*</span></label><input type="text" id="student-name" maxlength="50"></div>
            <div class="form-group"><label>手机号 <span class="required">*</span></label><input type="text" id="student-phone" maxlength="20"></div>
            <div class="form-group"><label>学员类型</label><input type="text" id="student-type" readonly style="background:#f5f7fa;color:#666;"></div>

            <!-- 校区-学科-授课老师 设置区块 -->
            <div class="form-group" style="margin-top:20px;border-top:1px solid #e8e8e8;padding-top:16px;">
                <label style="font-weight:600;margin-bottom:8px;">校区-学科-授课老师设置</label>
                <div id="sst-rows-container" style="margin-bottom:8px;"></div>
                <button type="button" class="btn btn-outline btn-sm" onclick="addSstRow()" style="font-size:13px;">+ 新增一行</button>
            </div>

        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-student')">取消</button><button class="btn btn-primary" onclick="saveStudent()">保存</button></div></div>
    </div>


    <!-- 弹窗：资源报名课程 -->
    <div class="modal-overlay" id="modal-resource-enroll">
        <div class="modal"><div class="modal-header"><h3>资源报名课程</h3><button class="modal-close" onclick="closeModal('modal-resource-enroll')">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="enroll-resource-id">
            <div class="form-group"><label>选择校区 <span class="required">*</span></label><select id="enroll-resource-campus"><option value="">请选择校区</option></select></div>
            <div class="form-group"><label>选择课程 <span class="required">*</span></label><select id="enroll-resource-course" disabled><option value="">请先选择校区</option></select></div>
            <div class="form-group"><label>价格方案 <span class="required">*</span></label><select id="enroll-resource-plan"><option value="">请先选择课程</option></select></div>
            <div class="form-group"><label>报价单 <span class="required">*</span></label><select id="enroll-resource-item"><option value="">请先选择价格方案</option></select></div>
            <div class="form-group" id="enroll-resource-detail" style="display:none;background:#f5f7fa;padding:12px;border-radius:4px;">
                <div style="display:flex;justify-content:space-between;"><span>课时数量：</span><strong id="enroll-resource-lesson-count">-</strong></div>
                <div style="display:flex;justify-content:space-between;"><span>实际支付价格：</span><strong id="enroll-resource-actual-price" style="color:#e74c3c;">-</strong></div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-resource-enroll')">取消</button><button class="btn btn-primary" onclick="confirmResourceEnroll()">确认报名</button></div></div>
    </div>
    <!-- 弹窗：报名课程 -->
    <div class="modal-overlay" id="modal-enroll">
        <div class="modal"><div class="modal-header"><h3>报名课程</h3><button class="modal-close" onclick="closeModal('modal-enroll')">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="enroll-student-id">
            <div class="form-group"><label>选择校区 <span class="required">*</span></label><select id="enroll-campus"><option value="">请选择校区</option></select></div>
            <div class="form-group"><label>选择课程 <span class="required">*</span></label><select id="enroll-course" disabled><option value="">请先选择校区</option></select></div>
            <div class="form-group"><label>价格方案 <span class="required">*</span></label><select id="enroll-plan"><option value="">请先选择课程</option></select></div>
            <div class="form-group"><label>报价单 <span class="required">*</span></label><select id="enroll-item"><option value="">请先选择价格方案</option></select></div>
            <div class="form-group" id="enroll-detail" style="display:none;background:#f5f7fa;padding:12px;border-radius:4px;">
                <div style="display:flex;justify-content:space-between;"><span>课时数量：</span><strong id="enroll-lesson-count">-</strong></div>
                <div style="display:flex;justify-content:space-between;"><span>实际支付价格：</span><strong id="enroll-actual-price" style="color:#e74c3c;">-</strong></div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-enroll')">取消</button><button class="btn btn-primary" onclick="confirmEnroll()">确认报名</button></div></div>
    </div>

    <!-- 弹窗：从资源转化 -->
    <div class="modal-overlay" id="modal-convert-resource">
        <div class="modal modal-xl" style="max-width:800px;"><div class="modal-header"><h3>从资源转化为学员</h3><button class="modal-close" onclick="closeModal('modal-convert-resource')">&times;</button></div>
        <div class="modal-body">
            <p class="hint">以下显示所有未被转化为学员的资源（手机号不在学员表中）。勾选后批量转化为学员。</p>
            <div class="toolbar" style="padding:8px 0;">
                <input type="text" id="convert-resource-search" placeholder="搜索姓名/电话..." onkeyup="filterConvertResources()" style="width:200px;">
            </div>
            <div class="table-wrap" style="max-height:360px;overflow-y:auto;">
                <table id="table-convert-resources">
                    <thead><tr>
                        <th width="40"><input type="checkbox" id="select-all-convert" onchange="toggleSelectAllConvert()"></th>
                        <th>姓名</th><th>电话</th><th>来源</th><th>跟进状态</th>
                    </tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div id="convert-resource-empty" style="display:none;text-align:center;color:#999;padding:40px;">没有可转化的资源</div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-convert-resource')">取消</button><button class="btn btn-primary" onclick="doConvertResources()">批量转化</button></div></div>
    </div>

    <!-- 弹窗：新增/编辑班级 -->
    <div class="modal-overlay" id="modal-class-form">
        <div class="modal" style="max-width:480px;border-radius:12px;">
            <div class="modal-header" style="padding:16px 20px;border-bottom:1px solid #f0f0f0;">
                <h3 id="modal-class-title" style="font-size:16px;margin:0;">新增班级</h3>
                <button class="modal-close" onclick="closeModal('modal-class-form')" style="font-size:20px;">&times;</button>
            </div>
            <div class="modal-body" style="padding:20px;">
                <input type="hidden" id="edit-class-id">
                <!-- 班级类型 -->
                <div class="form-group" id="class-type-group" style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">班级类型 <span class="required">*</span></label>
                    <div class="segmented-control">
                        <label class="seg-item active"><input type="radio" name="class_type" value="标准班" checked> 标准班</label>
                        <label class="seg-item"><input type="radio" name="class_type" value="活动班"> 活动班</label>
                    </div>
                </div>
                <!-- 关联课程 -->
                <div class="form-group" style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">关联课程 <span class="required">*</span></label>
                    <div style="position:relative;">
                        <select id="class-course" style="width:100%;height:40px;padding:0 32px 0 12px;border:1px solid #e0e0e0;border-radius:8px;font-size:13px;background:#fff;appearance:none;cursor:pointer;color:#333;">
                            <option value="">请选择关联课程</option>
                        </select>
                        <span style="position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:#aaa;">▾</span>
                    </div>
                    <span style="font-size:11px;color:#aaa;margin-top:4px;display:block;">购买关联课程的学员可以分到本班</span>
                </div>
                <!-- 班级名称 -->
                <div class="form-group" style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">班级名称 <span class="required">*</span></label>
                    <div style="position:relative;">
                        <input type="text" id="class-name" maxlength="20" placeholder="请输入班级名称" oninput="updateClassCount('class-name','class-name-count')"
                            style="width:100%;height:40px;padding:0 50px 0 12px;border:1px solid #e0e0e0;border-radius:8px;font-size:13px;color:#333;outline:none;box-sizing:border-box;">
                        <span id="class-name-count" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);color:#bbb;font-size:11px;">0/20</span>
                    </div>
                </div>
                <!-- 招生人数 + 授课课时 并排 -->
                <div style="display:flex;gap:12px;margin-bottom:16px;">
                    <div class="form-group" style="flex:1;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">招生人数 <span class="required">*</span></label>
                        <input type="number" id="class-max-students" min="1" placeholder="请输入"
                            style="width:100%;height:40px;padding:0 12px;border:1px solid #e0e0e0;border-radius:8px;font-size:13px;color:#333;outline:none;box-sizing:border-box;">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">授课课时</label>
                        <input type="number" id="class-lesson-hours" value="2" min="2" step="2"
                            oninput="this.value=Math.max(2,parseInt(this.value)||2);if(this.value%2!==0)this.value=parseInt(this.value)+1" placeholder="2"
                            style="width:100%;height:40px;padding:0 12px;border:1px solid #e0e0e0;border-radius:8px;font-size:13px;color:#333;outline:none;box-sizing:border-box;">
                    </div>
                </div>
                <!-- 是否可试听 -->
                <div class="form-group" style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">是否可试听</label>
                    <div class="segmented-control">
                        <label class="seg-item active"><input type="radio" name="can_trial" value="是" checked> 是</label>
                        <label class="seg-item"><input type="radio" name="can_trial" value="否"> 否</label>
                    </div>
                </div>
                <!-- 当前校区 -->
                <div class="form-group" id="class-campus-group" style="margin-bottom:0;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">当前校区 <span class="required">*</span></label>
                    <div style="position:relative;">
                        <select id="class-campus" style="width:100%;height:40px;padding:0 32px 0 12px;border:1px solid #e0e0e0;border-radius:8px;font-size:13px;background:#fff;appearance:none;cursor:pointer;color:#333;">
                            <option value="">请选择当前校区</option>
                        </select>
                        <span style="position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:#aaa;">▾</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding:12px 20px;border-top:1px solid #f0f0f0;display:flex;justify-content:flex-end;gap:8px;">
                <button class="btn btn-outline" onclick="closeModal('modal-class-form')">取消</button>
                <button class="btn btn-primary" onclick="saveClass()" style="min-width:80px;">确认</button>
            </div>
        </div>
    </div>

    <!-- 弹窗：新增/编辑教室 -->
    <div class="modal-overlay" id="modal-classroom-form">
        <div class="modal"><div class="modal-header"><h3 id="modal-classroom-title">新增教室</h3><button class="modal-close" onclick="closeModal('modal-classroom-form')">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="edit-classroom-id">
            <div class="form-group"><label>教室名称 <span class="required">*</span></label><input type="text" id="classroom-name" maxlength="50" placeholder="请输入教室名称"></div>
            <div class="form-group"><label>容纳人数</label><input type="number" id="classroom-capacity" min="0" placeholder="请输入容纳人数"></div>
            <div class="form-group"><label>所属校区</label><select id="classroom-campus"><option value="">请选择校区</option></select></div>
            <div class="form-group"><label>备注</label><textarea id="classroom-remark" rows="3" placeholder="请输入备注信息"></textarea></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-classroom-form')">取消</button><button class="btn btn-primary" onclick="saveClassroom()">保存</button></div></div>
    </div>

    <!-- 弹窗：排课设置 -->
    <div class="modal-overlay" id="modal-schedule-form">
        <div class="modal" style="max-width:540px;"><div class="modal-header"><h3 id="modal-schedule-title">排课设置</h3><button class="modal-close" onclick="closeModal('modal-schedule-form')">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="schedule-class-id">
            <input type="hidden" id="edit-schedule-id">
            <!-- 时间安排 -->
            <div class="form-section">
                <div class="form-section-header"><span class="form-section-title">🕐 时间安排</span></div>
                <div class="form-group">
                    <label>上课日期范围 <span class="required">*</span></label>
                    <input type="text" id="schedule-date-range" placeholder="点击选择起止日期" readonly>
                </div>
                <div class="form-group">
                    <label>上课周期 <span class="required">*</span></label>
                    <div class="weekday-group" id="weekday-buttons">
                        <button type="button" class="weekday-btn" data-day="1" onclick="toggleWeekday(this)">一</button>
                        <button type="button" class="weekday-btn" data-day="2" onclick="toggleWeekday(this)">二</button>
                        <button type="button" class="weekday-btn" data-day="3" onclick="toggleWeekday(this)">三</button>
                        <button type="button" class="weekday-btn" data-day="4" onclick="toggleWeekday(this)">四</button>
                        <button type="button" class="weekday-btn" data-day="5" onclick="toggleWeekday(this)">五</button>
                        <button type="button" class="weekday-btn" data-day="6" onclick="toggleWeekday(this)">六</button>
                        <button type="button" class="weekday-btn" data-day="7" onclick="toggleWeekday(this)">日</button>
                    </div>
                </div>
                <div class="form-group">
                    <label>上课时段 <span class="required">*</span></label>
                    <div id="schedule-time-slots">
                        <div class="schedule-time-hint">请先选择上课周期</div>
                    </div>
                </div>
            </div>
            <!-- 资源配置 -->
            <div class="form-section" style="margin-top:4px;">
                <div class="form-section-header"><span class="form-section-title">👤 资源配置</span></div>
                <div class="form-row" style="display:flex;gap:12px;">
                    <div class="form-group" style="flex:1;position:relative;">
                        <label>授课老师 <span class="required">*</span></label>
                        <input type="text" id="schedule-teacher" placeholder="搜索或选择老师" autocomplete="off" oninput="filterTeacherDropdown()" onfocus="filterTeacherDropdown()">
                        <div class="teacher-dropdown" id="teacher-dropdown" style="display:none;"></div>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>上课教室</label>
                        <select id="schedule-classroom"><option value="">选择教室</option></select>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-schedule-form')">取消</button><button class="btn btn-primary" onclick="saveSchedule()">确认排课</button></div></div>
    </div>

    <!-- 弹窗：新增/编辑上课记录 -->
    <div class="modal-overlay" id="modal-attendance">
        <div class="modal"><div class="modal-header"><h3 id="modal-attendance-title">新增上课记录</h3><button class="modal-close" onclick="closeModal('modal-attendance')">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="edit-att-id">
            <div class="form-group"><label>课程 <span class="required">*</span></label><select id="att-course" onchange="onCourseChangeInAttendance()"><option value="">请选择课程</option></select></div>
            <div class="form-group"><label>上课日期 <span class="required">*</span></label><input type="date" id="att-lesson-date"></div>
            <div class="form-group"><label>出勤状态</label><select id="att-status"><option value="出勤">出勤</option><option value="请假">请假</option><option value="缺勤">缺勤</option></select></div>
            <div class="form-group"><label>班级</label><select id="att-class" onchange="onClassChangeInAttendance()"><option value="">请选择班级（选填）</option></select></div>
            <div class="form-group"><label>校区</label><input type="text" id="att-campus" placeholder="选填，选择班级后自动填充"></div>
            <div class="form-group"><label>授课教师</label><input type="text" id="att-teacher" placeholder="选填"></div>
            <div class="form-group"><label>一级学科</label><input type="text" id="att-subject1" readonly placeholder="选择课程后自动填充"></div>
            <div class="form-group"><label>二级学科</label><input type="text" id="att-subject2" readonly placeholder="选择课程后自动填充"></div>
            <div class="form-group"><label>上课时间</label><input type="text" id="att-class-time" placeholder="如 09:00-10:30"></div>
            <div class="form-group"><label>课耗金额（元）</label><input type="number" id="att-amount" step="0.01" min="0" placeholder="0.00"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-attendance')">取消</button><button class="btn btn-primary" onclick="saveAttendance()">保存</button></div></div>
    </div>

    <!-- 弹窗：分班 -->
    <div class="modal-overlay" id="modal-class-enroll">
        <div class="modal" style="max-width:800px;width:94vw;"><div class="modal-header"><h3 id="modal-class-enroll-title">分班</h3><button class="modal-close" onclick="closeModal('modal-class-enroll')">&times;</button></div>
        <div class="modal-body">
            <div class="table-wrap"><table><thead><tr>
                <th>班级名称</th><th>关联课程</th><th>班级类型</th><th>校区</th><th>是否可分入</th><th>操作</th>
            </tr></thead><tbody id="class-enroll-tbody"></tbody></table></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-class-enroll')">关闭</button></div></div>
    </div>

    <!-- 弹窗：班级考勤 -->
    <div class="modal-overlay" id="modal-class-attendance">
        <div class="modal" style="max-width:700px;width:94vw;"><div class="modal-header"><h3 id="modal-class-attendance-title">课次考勤</h3><button class="modal-close" onclick="closeModal('modal-class-attendance')">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="ca-class-id">
            <input type="hidden" id="ca-schedule-id">
            <input type="hidden" id="ca-session-date">
            <div class="table-wrap"><table><thead><tr>
                <th>学号</th><th>学员姓名</th><th>出勤状态</th><th>已扣课时</th>
            </tr></thead><tbody id="ca-attendance-tbody"></tbody></table></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('modal-class-attendance')">取消</button><button class="btn btn-outline btn-sm" onclick="showTempStudentModal()" style="margin-right:auto;">+ 添加临时学员</button><button class="btn btn-primary" onclick="saveClassAttendance()">保存</button></div></div>
    </div>

    <!-- 弹窗：添加临时学员 -->
    <div class="modal-overlay" id="modal-temp-student" style="z-index:1100">
        <div class="modal" style="max-width:750px;width:94vw;">
            <div class="modal-header">
                <h3>添加临时学员</h3>
                <button class="modal-close" onclick="closeModal('modal-temp-student')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom:12px;">
                    <input type="text" id="temp-student-search" placeholder="搜索：学号 / 姓名 / 手机号" style="width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:14px;" oninput="onTempStudentSearch()" autocomplete="off">
                </div>
                <div class="table-wrap"><table>
                    <thead><tr><th>学号</th><th>姓名</th><th>手机号</th><th>剩余课时</th><th>操作</th></tr></thead>
                    <tbody id="temp-student-tbody">
                        <tr><td colspan="5" style="text-align:center;color:#999;">加载中...</td></tr>
                    </tbody>
                </table></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('modal-temp-student')">关闭</button>
            </div>
        </div>
    </div>

    <!-- 弹窗：考勤（按课次） -->
    <div class="modal-overlay" id="modal-attendance-session">
        <div class="modal modal-attendance">
            <div class="modal-header">
                <div>
                    <h3>课次考勤</h3>
                    <div class="att-subtitle" id="as-subtitle">-</div>
                </div>
                <button class="modal-close" onclick="closeModal('modal-attendance-session')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="att-info-bar">
                    <div class="att-info-item"><span class="att-info-icon">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                    </span><strong id="as-class-name">-</strong></div>
                    <div class="att-info-item"><span class="att-info-icon">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                    </span><span id="as-campus">-</span></div>
                    <div class="att-info-item"><span class="att-info-icon">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>
                    </span><strong id="as-session-date">-</strong></div>
                    <div class="att-info-item"><span class="att-info-icon">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                    </span><span id="as-teacher">-</span></div>
                    <div class="att-info-item"><span class="att-info-icon">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H8V4h12v12zM10 9h8v2h-8zm0 3h4v2h-4zm0-6h8v2h-8z"/></svg>
                    </span><span id="as-course-name">-</span></div>
                    <div class="att-info-item"><span class="att-info-icon">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 2L4 5v6.09c0 5.05 3.41 9.76 8 10.91 4.59-1.15 8-5.86 8-10.91V5l-8-3zm0 15c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm-1-8v4l3 1.73.55-.95-2.55-1.48V9h-1z"/></svg>
                    </span><span id="as-classroom">-</span></div>
                    <div class="att-info-item"><span class="att-info-icon">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                    </span><span id="as-time">-</span></div>
                </div>

                <div class="att-student-header">
                    <span class="att-section-title">学员考勤</span>
                    <div class="att-student-actions">
                        <button class="btn btn-sm btn-outline" onclick="addTempStudent()">+ 临时学员</button>
                        <button class="btn btn-sm btn-outline" onclick="addMakeupStudent()">+ 补课学员</button>
                        <button class="btn btn-sm btn-outline" onclick="showAddStudentToAttendanceModal()">+ 添加学员</button>
                    </div>
                </div>

                <div class="att-table-wrap">
                    <table class="att-table" id="as-attendance-table">
                        <thead><tr>
                            <th width="50"></th>
                            <th>学员</th>
                            <th>课程</th>
                            <th width="80">剩余课时</th>
                            <th width="120">本次扣课时</th>
                            <th width="120">到课状态</th>
                        </tr></thead>
                        <tbody id="as-attendance-tbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <span class="att-footer-hint">共 <strong id="as-total-count">0</strong> 名学员</span>
                <button class="btn btn-outline" onclick="closeModal('modal-attendance-session')">取消</button>
                <button class="btn btn-primary" onclick="saveAttendanceSession()">保存考勤</button>
            </div>
        </div>
    </div>

    <!-- 排课弹窗专属样式优化 -->
    <style>
        /* 弹窗整体放大 */
        #modal-schedule-form .modal {
            max-width: 700px;
            width: 92vw;
        }
        #modal-schedule-form .modal-header {
            padding: 22px 30px;
        }
        #modal-schedule-form .modal-header h3 {
            font-size: 22px;
            font-weight: 700;
        }
        #modal-schedule-form .modal-body {
            padding: 28px 30px;
        }
        #modal-schedule-form .modal-footer {
            gap: 16px;
            padding: 18px 30px 28px 30px;
        }

        /* 表单组间距 */
        #modal-schedule-form .form-group {
            margin-bottom: 20px;
        }
        #modal-schedule-form .form-group:last-child {
            margin-bottom: 4px;
        }

        /* 标签样式 */
        #modal-schedule-form .form-group > label {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        #modal-schedule-form .required {
            color: #e53e3e;
            font-weight: 700;
        }

        /* 统一控件高度 */
        #modal-schedule-form input[type="text"],
        #modal-schedule-form input[type="date"],
        #modal-schedule-form input[type="time"],
        #modal-schedule-form input[type="number"],
        #modal-schedule-form select {
            height: 40px;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
        }
        #modal-schedule-form textarea {
            border-radius: 6px;
            font-size: 13px;
        }

        /* 排课规则 radio-group */
        #modal-schedule-form .radio-group {
            gap: 24px;
            padding: 4px 0;
        }
        #modal-schedule-form .radio-label {
            font-size: 14px;
            font-weight: 500;
            color: #444;
        }

        /* 日期范围行 */
        #modal-schedule-form .form-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 2px;
        }
        #modal-schedule-form .form-row > .row-sep {
            color: #666;
            flex-shrink: 0;
        }
        #modal-schedule-form .form-row > input,
        #modal-schedule-form .form-row > select {
            flex: 1;
            min-width: 0;
        }

        /* 星期按钮组优化 */
        #modal-schedule-form .weekday-group {
            gap: 8px;
        }
        #modal-schedule-form .weekday-btn {
            width: auto;
            min-width: 40px;
            height: 38px;
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid #d9d9d9;
            background: #fafafa;
            color: #555;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            margin-right: 0;
            margin-bottom: 0;
        }
        #modal-schedule-form .weekday-btn:hover {
            border-color: #7C3AED;
            color: #7C3AED;
            background: #f5f0ff;
        }
        #modal-schedule-form .weekday-btn.active {
            background: #7C3AED;
            color: #fff;
            border-color: #7C3AED;
        }

        /* 具体上课时间区 */
        #modal-schedule-form .schedule-time-hint {
            background: #f9f9f9;
            padding: 16px;
            border-radius: 6px;
            color: #999;
            font-size: 13px;
            text-align: center;
        }
        #modal-schedule-form .schedule-time-row {
            background: #fafafa;
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        #modal-schedule-form .schedule-time-row:last-child {
            margin-bottom: 0;
        }
        #modal-schedule-form .schedule-time-row span {
            white-space: nowrap;
            font-size: 13px;
            color: #333;
        }
        #modal-schedule-form .schedule-time-row input[type="time"] {
            padding: 6px 10px;
            border: 1px solid #d9d9d9;
            border-radius: 4px;
            font-size: 13px;
            height: 36px;
        }

        /* 节假日排课 Switch 控件 */
        #modal-schedule-form .switch-label {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            margin-top: 4px;
            cursor: pointer;
        }
        #modal-schedule-form .switch-label input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        #modal-schedule-form .switch-slider {
            position: absolute;
            inset: 0;
            background: #ccc;
            border-radius: 24px;
            transition: background 0.3s;
        }
        #modal-schedule-form .switch-slider::before {
            content: "";
            position: absolute;
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            border-radius: 50%;
            transition: transform 0.3s;
        }
        #modal-schedule-form .switch-label input:checked + .switch-slider {
            background: #7C3AED;
        }
        #modal-schedule-form .switch-label input:checked + .switch-slider::before {
            transform: translateX(20px);
        }

        /* 节假日排课 + 授课老师间距 */
        #modal-schedule-form #schedule-rule-section > .form-group:nth-child(4) {
            margin-bottom: 22px;
        }

        /* 帮助图标对齐 */
        #modal-schedule-form .help-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 1px solid #bbb;
            color: #999;
            font-size: 12px;
            cursor: help;
            margin-left: 6px;
            background: #f5f5f5;
            font-weight: bold;
            vertical-align: middle;
            position: relative;
            top: -1px;
        }

        /* 底部按钮 */
        #modal-schedule-form .modal-footer .btn {
            padding: 9px 24px;
            font-size: 14px;
            border-radius: 6px;
        }
        #modal-schedule-form .modal-footer .btn-outline {
            background: #fff;
            color: #555;
            border-color: #d9d9d9;
        }
        #modal-schedule-form .modal-footer .btn-outline:hover {
            border-color: #7C3AED;
            color: #7C3AED;
        }
        /* ========== 考勤弹窗样式 ========== */
        .modal-attendance { max-width: 860px; width: 94vw; }

        .att-subtitle {
            font-size: 13px; color: var(--color-text-muted);
            margin-top: 2px;
        }

        /* 上课信息卡片 */
        .att-info-bar {
            display: flex; flex-wrap: wrap;
            background: var(--color-primary-bg);
            border: 1px solid rgba(124,58,237,0.08);
            border-radius: var(--radius-lg);
            padding: 14px 16px;
            margin-bottom: 20px;
        }
        .att-info-item {
            flex: 0 0 25%;
            display: flex; align-items: center;
            gap: 6px;
            font-size: 13px;
            padding: 3px 0;
            color: var(--color-text);
        }
        .att-info-icon {
            display: flex; align-items: center;
            color: var(--color-primary);
            flex-shrink: 0;
        }

        /* 学员表头行 */
        .att-student-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 12px;
        }
        .att-section-title {
            font-size: 15px; font-weight: 700;
            color: var(--color-text);
        }
        .att-student-actions { display: flex; gap: 8px; }

        /* 考勤表格 */
        .att-table-wrap {
            background: var(--color-surface);
            border: 1px solid var(--color-border-light);
            border-radius: var(--radius-lg);
            overflow: hidden;
            max-height: 380px;
            overflow-y: auto;
        }
        .att-table { width: 100%; border-collapse: collapse; }
        .att-table thead th {
            background: #FAFAFC;
            padding: 10px 12px;
            font-size: 12px; font-weight: 600;
            color: var(--color-text-muted);
            border-bottom: 1px solid var(--color-border-light);
            text-align: left;
            position: sticky; top: 0; z-index: 1;
        }
        .att-table thead th:first-child { padding-left: 16px; }
        .att-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--color-border-light);
            font-size: 13px;
            vertical-align: middle;
        }
        .att-table tbody td:first-child { padding-left: 16px; }
        .att-table tbody tr:last-child td { border-bottom: none; }
        .att-table tbody tr:hover { background: #FAFAFC; }

        /* 移除学员按钮 */
        .btn-remove-att {
            background: none; border: 1px solid transparent;
            color: var(--color-text-muted);
            cursor: pointer;
            padding: 4px 8px; border-radius: var(--radius-sm);
            font-size: 12px; transition: all var(--transition);
        }
        .btn-remove-att:hover {
            color: var(--color-danger);
            border-color: #fecaca;
            background: #fef2f2;
        }

        /* 步进器 */
        .att-deduct-stepper {
            display: inline-flex; align-items: center;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-md);
            overflow: hidden;
            background: var(--color-surface);
        }
        .att-deduct-stepper .stepper-btn {
            width: 30px; height: 30px;
            border: none; background: transparent;
            color: var(--color-text-secondary);
            font-size: 17px; cursor: pointer;
            transition: all var(--transition);
            display: flex; align-items: center; justify-content: center;
            line-height: 1;
        }
        .att-deduct-stepper .stepper-btn:hover {
            background: var(--color-primary-bg);
            color: var(--color-primary);
        }
        .att-deduct-stepper .stepper-val {
            min-width: 38px; text-align: center;
            font-size: 14px; font-weight: 700;
            color: var(--color-primary);
            padding: 0 2px;
            border-left: 1.5px solid var(--color-border);
            border-right: 1.5px solid var(--color-border);
        }
        .att-deduct-stepper.disabled {
            opacity: 0.45;
            pointer-events: none;
        }
        .att-deduct-stepper.disabled .stepper-val {
            color: var(--color-text-muted);
        }

        /* 状态芯片组 */
        .att-status-group {
            display: inline-flex;
            border: 1.5px solid var(--color-border);
            border-radius: 20px; overflow: hidden;
        }
        .att-status-chip {
            padding: 5px 16px;
            font-size: 12px; font-weight: 500;
            cursor: pointer; transition: all 0.2s;
            color: var(--color-text-secondary);
            background: var(--color-surface);
            border: none;
            outline: none;
        }
        .att-status-chip:first-child {
            border-right: 1px solid var(--color-border);
        }
        .att-status-chip.active {
            background: var(--color-primary);
            color: #fff;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.08);
        }
        .att-status-chip.active[data-val="缺勤"] {
            background: #e74c3c;
        }
        .att-status-chip:not(.active):hover {
            background: var(--color-primary-bg);
            color: var(--color-primary);
        }

        /* 页脚统计 */
        .att-footer-hint {
            margin-right: auto;
            font-size: 13px; color: var(--color-text-muted);
        }
    </style>

    <!-- 添加学员到班级弹窗 -->
    <div class="modal-overlay" id="modal-add-student">
        <div class="modal" style="width:520px;">
            <div class="modal-header">
                <h3 id="modal-add-student-title">添加学员</h3>
                <button class="modal-close" onclick="closeModal('modal-add-student')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom:12px;">
                    <input type="text" id="add-student-search" placeholder="输入姓名或手机号搜索学员..." onkeyup="searchAvailableStudents()" style="width:100%;height:38px;padding:8px 12px;border:1px solid #d9d9d9;border-radius:6px;font-size:14px;">
                </div>
                <div style="max-height:360px;overflow-y:auto;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead><tr>
                            <th style="text-align:left;padding:8px;border-bottom:1px solid #f0f0f0;color:#888;font-size:12px;">学号</th>
                            <th style="text-align:left;padding:8px;border-bottom:1px solid #f0f0f0;color:#888;font-size:12px;">姓名</th>
                            <th style="text-align:left;padding:8px;border-bottom:1px solid #f0f0f0;color:#888;font-size:12px;">手机号</th>
                            <th style="text-align:left;padding:8px;border-bottom:1px solid #f0f0f0;color:#888;font-size:12px;">剩余课时</th>
                            <th width="60"></th>
                        </tr></thead>
                        <tbody id="available-students-tbody">
                            <tr><td colspan="5" style="text-align:center;color:#999;padding:20px;">加载中...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('modal-add-student')">取消</button>
            </div>
        </div>
    </div>

    <!-- 课耗明细弹窗 -->
    <div class="modal-overlay" id="modal-consumption-detail">
        <div class="modal" style="max-width:820px;width:95vw;">
            <div class="modal-header">
                <h3 id="modal-consumption-title">课耗明细</h3>
                <button class="modal-close" onclick="closeModal('modal-consumption-detail')">&times;</button>
            </div>
            <div class="modal-body" style="padding:0;max-height:70vh;overflow-y:auto;">
                <div class="consumption-summary" id="consumption-summary" style="display:none;">
                    <span class="consumption-summary-count" id="consumption-count"></span>
                    <span class="consumption-summary-total">合计消耗 <strong id="consumption-total-lessons"></strong> 课时，<strong id="consumption-total-amount"></strong></span>
                </div>
                <div class="consumption-list" id="consumption-detail-list">
                    <div class="consumption-empty">加载中...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('modal-consumption-detail')">关闭</button>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/light.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/zh.js"></script>
    <!-- 退费申请弹窗（学员详情页发起） -->
    <div class="modal-overlay" id="modal-refund-apply">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h4>退费申请</h4>
                <button class="modal-close" onclick="closeModal('modal-refund-apply')">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="refund-apply-order-id">

                <!-- 卡片 1：订单摘要 -->
                <div class="refund-card refund-card--highlight">
                    <div class="refund-card-header">
                        <span class="card-icon card-icon--info">📊</span>
                        <span>订单摘要</span>
                    </div>
                    <div class="info-grid">
                        <div><div class="info-label">报读校区</div><div class="info-value" id="refund-auto-campus">-</div></div>
                        <div><div class="info-label">课程名称</div><div class="info-value" id="refund-auto-course">-</div></div>
                        <div><div class="info-label">报读课时</div><div class="info-value" id="refund-auto-total-lessons">0</div></div>
                        <div><div class="info-label">报读金额</div><div class="info-value" id="refund-auto-total-amount">¥0.00</div></div>
                        <div><div class="info-label">消耗课时</div><div class="info-value" id="refund-auto-consumed-lessons">0</div></div>
                        <div><div class="info-label">消耗金额</div><div class="info-value" id="refund-auto-consumed-amount">¥0.00</div></div>
                        <div><div class="info-label">剩余可退课时</div><div class="info-value info-value--large" id="refund-auto-remaining-lessons">0</div></div>
                        <div><div class="info-label">剩余可退金额</div><div class="info-value info-value--large" id="refund-auto-remaining-amount">¥0.00</div></div>
                    </div>
                </div>

                <!-- 卡片 2：费用计算 -->
                <div class="refund-card refund-card--calc">
                    <div class="refund-card-header">
                        <span class="card-icon card-icon--calc">💰</span>
                        <span>费用计算</span>
                    </div>
                    <!-- 剩余金额（只读参考） -->
                    <div class="calc-row calc-row--base">
                        <span class="calc-label">剩余可退金额</span>
                        <span class="calc-value" id="refund-calc-base-amount">¥0.00</span>
                    </div>
                    <!-- 减号分隔 -->
                    <div class="calc-separator">
                        <span class="calc-operator">−</span>
                        <span class="calc-desc" style="font-size:13px;color:var(--color-text-muted);">自定义扣减</span>
                        <div class="calc-line"></div>
                    </div>
                    <!-- 扣减输入 -->
                    <div class="calc-row calc-row--deduct">
                        <input type="number" id="refund-custom-deduction" class="calc-input" step="0.01" min="0" value="0" oninput="calcActualRefund()">
                    </div>
                    <!-- 等号线 -->
                    <div class="calc-divider">
                        <div class="calc-divider-line"></div>
                    </div>
                    <!-- 实退金额 -->
                    <div class="calc-row calc-row--result">
                        <span class="calc-label" style="color:rgba(255,255,255,0.8);">实退金额</span>
                        <span class="calc-value calc-value--result" id="refund-actual-amount-display">¥0.00</span>
                    </div>
                </div>

                <!-- 卡片 3：退费方式 -->
                <div class="refund-card">
                    <div class="refund-card-header">
                        <span class="card-icon card-icon--method">💳</span>
                        <span>退费方式</span>
                    </div>
                    <div class="refund-method-radio-group">
                        <label class="refund-method-radio-label">
                            <input type="radio" name="refund-method" value="cash" checked onchange="onRefundMethodChange()">
                            <span class="refund-method-radio-custom"></span>
                            退到银行卡
                        </label>
                        <label class="refund-method-radio-label">
                            <input type="radio" name="refund-method" value="account" onchange="onRefundMethodChange()">
                            <span class="refund-method-radio-custom"></span>
                            退到学员账户
                        </label>
                    </div>
                    <div class="refund-account-hint" style="display:none;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span>二级审批通过后自动进入学员账户余额</span>
                    </div>
                </div>

                <!-- 卡片 4：收款信息（条件显示，带收起动画） -->
                <div class="refund-card" id="refund-bank-info-section">
                    <div class="refund-card-header">
                        <span class="card-icon card-icon--bank">🏦</span>
                        <span>收款信息</span>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>转账银行</label>
                            <input type="text" id="refund-bank-name" class="form-input" placeholder="请输入银行名称">
                        </div>
                        <div class="form-group">
                            <label>银行卡号</label>
                            <input type="text" id="refund-bank-account" class="form-input" placeholder="请输入银行卡号">
                        </div>
                        <div class="form-group">
                            <label>开户人</label>
                            <input type="text" id="refund-account-holder" class="form-input" placeholder="请输入开户人姓名">
                        </div>
                    </div>
                </div>

                <!-- 卡片 5：退费原因（始终可见） -->
                <div class="refund-card">
                    <div class="refund-card-header">
                        <span class="card-icon card-icon--reason">📝</span>
                        <span>退费原因<span style="color:#DC2626;margin-left:2px;">*</span></span>
                    </div>
                    <textarea id="refund-apply-reason" class="form-input" rows="3" placeholder="请输入退费原因"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" onclick="closeModal('modal-refund-apply')">取消</button>
                <button class="btn btn-primary" id="btn-refund-submit" onclick="submitRefundApply()">
                    <span class="btn-text">提交申请</span>
                    <span class="btn-spinner" style="display:none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                            <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
                        </svg>
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- 账户退费申请弹窗 -->
    <div class="modal-overlay" id="modal-account-refund">
        <div class="modal modal-lg" style="min-width:1100px;">
            <div class="modal-header">
                <h4>账户退费申请</h4>
                <button class="modal-close" onclick="closeModal('modal-account-refund')">&times;</button>
            </div>
            <div class="modal-body">
                <!-- 账户信息区（只读） -->
                <div style="background:#f7f9fc;border:1px solid #e0e0e0;border-radius:8px;padding:16px;margin-bottom:16px;">
                    <h5 style="margin:0 0 12px;font-size:14px;color:#666;">账户信息</h5>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;">
                        <div><span style="color:#888;">账户余额：</span><b style="color:#11998e;font-size:16px;" id="account-refund-balance">¥0.00</b></div>
                        <div><span style="color:#888;">累计充值：</span><span id="account-refund-total-deposit">¥0.00</span></div>
                        <div><span style="color:#888;">累计消费：</span><span id="account-refund-total-consume">¥0.00</span></div>
                        <div><span style="color:#888;">累计退款：</span><span id="account-refund-total-refund">¥0.00</span></div>
                    </div>
                </div>
                <!-- 退费金额 -->
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px;margin-bottom:16px;">
                    <h5 style="margin:0 0 12px;font-size:14px;color:#666;">退费金额</h5>
                    <div class="form-group">
                        <label>申请退费金额 (元) <span style="color:#999;">（不超过账户余额）</span></label>
                        <input type="number" id="account-refund-amount" class="form-input" step="0.01" min="0.01"
                               placeholder="请输入退费金额" oninput="validateAccountRefundAmount()">
                        <div id="account-refund-amount-hint" style="margin-top:6px;font-size:12px;color:#999;"></div>
                    </div>
                </div>
                <!-- 学科选择 -->
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px;margin-bottom:16px;">
                    <h5 style="margin:0 0 12px;font-size:14px;color:#666;">学科信息 <span style="color:#e74c3c;">*</span></h5>
                    <div class="form-group">
                        <label>一级学科</label>
                        <select id="account-refund-subject" class="form-input" style="width:100%;">
                            <option value="">请选择学科</option>
                        </select>
                    </div>
                </div>
                <!-- 收款信息 -->
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px;">
                    <h5 style="margin:0 0 12px;font-size:14px;color:#666;">收款信息（退至银行卡）</h5>
                    <div class="form-row">
                        <div class="form-group" style="flex:1;">
                            <label>转账银行</label>
                            <input type="text" id="account-refund-bank-name" class="form-input" placeholder="请输入银行名称">
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>银行卡号</label>
                            <input type="text" id="account-refund-bank-account" class="form-input" placeholder="请输入银行卡号">
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>开户人</label>
                            <input type="text" id="account-refund-account-holder" class="form-input" placeholder="请输入开户人姓名">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>退费原因</label>
                        <textarea id="account-refund-reason" class="form-input" rows="3" placeholder="请输入退费原因"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" onclick="closeModal('modal-account-refund')">取消</button>
                <button class="btn btn-primary" id="btn-account-refund-submit" onclick="submitAccountRefund()">提交申请</button>
            </div>
        </div>
    </div>

    <!-- 退费审批弹窗 -->
    <div class="modal-overlay" id="modal-refund-approve">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h4>退费审批</h4>
                <button class="modal-close" onclick="closeModal('modal-refund-approve')">&times;</button>
            </div>
            <div class="modal-body" id="refund-approve-content">
                加载中...
            </div>
            <div class="modal-footer" id="refund-approve-footer" style="display:none;">
                <button class="btn btn-default" onclick="closeModal('modal-refund-approve')">关闭</button>
                <button class="btn btn-danger" id="btn-refund-reject" onclick="submitApproval('reject')">驳回</button>
                <button class="btn btn-primary" id="btn-refund-approve" onclick="submitApproval('approve')">审批通过</button>
            </div>
        </div>
    </div>

    <!-- 优惠方案弹窗 -->
    <div class="modal-overlay" id="modal-discount-plan">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h4 id="discount-plan-modal-title">新增优惠方案</h4>
                <button class="modal-close" onclick="closeModal('modal-discount-plan')">&times;</button>
            </div>
            <div class="modal-body">

                <!-- ====== 卡片 1: 基本信息 ====== -->
                <div class="dp-card">
                    <div class="dp-card-title">
                        <span class="dp-card-icon">📋</span> 基本信息
                    </div>
                    <div class="dp-card-body">
                        <div class="form-group">
                            <label class="required">方案名称</label>
                            <input type="text" id="discount-plan-name" class="form-input" placeholder="请输入方案名称" maxlength="50">
                        </div>
                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label>类型</label>
                                <select id="discount-plan-type" class="form-input">
                                    <option value="新报">新报</option>
                                    <option value="续费">续费</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>优惠金额 (元)</label>
                                <input type="number" id="discount-plan-amount" class="form-input" step="0.01" min="0" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ====== 卡片 2: 有效期 ====== -->
                <div class="dp-card">
                    <div class="dp-card-title">
                        <span class="dp-card-icon">📅</span> 有效期
                    </div>
                    <div class="dp-card-body">
                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label>开始日期</label>
                                <input type="date" id="discount-plan-start" class="form-input">
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>结束日期</label>
                                <input type="date" id="discount-plan-end" class="form-input">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ====== 卡片 3: 适用范围 ====== -->
                <div class="dp-card">
                    <div class="dp-card-title">
                        <span class="dp-card-icon">🏫</span> 适用范围
                    </div>
                    <div class="dp-card-body">
                        <div class="form-group">
                            <div class="dp-tree-header">
                                <label>适用校区</label>
                                <span class="dp-badge" id="dp-campus-count">未选择</span>
                            </div>
                            <div class="dp-tree-wrap" id="discount-campus-tree"></div>
                        </div>
                        <div class="form-group">
                            <div class="dp-tree-header">
                                <label>适用学科</label>
                                <span class="dp-badge" id="dp-subject-count">未选择</span>
                            </div>
                            <div class="dp-tree-wrap" id="discount-subject-tree"></div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button class="btn btn-default" onclick="closeModal('modal-discount-plan')">取消</button>
                <button class="btn btn-primary" id="btn-discount-plan-save" onclick="saveDiscountPlan()">保存</button>
            </div>
        </div>
    </div>

    <!-- 优惠券弹窗 -->
    <div class="modal-overlay" id="modal-coupon">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h4 id="coupon-modal-title">新增优惠券</h4>
                <button class="modal-close" onclick="closeModal('modal-coupon')">&times;</button>
            </div>
            <div class="modal-body">

                <!-- 卡片 1: 基本信息 -->
                <div class="dp-card">
                    <div class="dp-card-title">
                        <span class="dp-card-icon">📋</span> 基本信息
                    </div>
                    <div class="dp-card-body">
                        <div class="form-group">
                            <label class="required">优惠券名称</label>
                            <input type="text" id="coupon-name" class="form-input" placeholder="请输入优惠券名称" maxlength="50">
                        </div>
                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label>类型</label>
                                <select id="coupon-type" class="form-input">
                                    <option value="课程券">课程券</option>
                                    <option value="商品券">商品券</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>优惠金额 (元)</label>
                                <input type="number" id="coupon-amount" class="form-input" step="0.01" min="0" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 卡片 2: 有效期 -->
                <div class="dp-card">
                    <div class="dp-card-title">
                        <span class="dp-card-icon">📅</span> 有效期
                    </div>
                    <div class="dp-card-body">
                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label>开始日期</label>
                                <input type="date" id="coupon-start" class="form-input">
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>结束日期</label>
                                <input type="date" id="coupon-end" class="form-input">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 卡片 3: 适用范围 -->
                <div class="dp-card">
                    <div class="dp-card-title">
                        <span class="dp-card-icon">🏫</span> 适用范围
                    </div>
                    <div class="dp-card-body">
                        <div class="form-group">
                            <div class="dp-tree-header">
                                <label>适用校区</label>
                                <span class="dp-badge" id="cp-campus-count">未选择</span>
                            </div>
                            <div class="dp-tree-wrap" id="coupon-campus-tree"></div>
                        </div>
                        <div class="form-group">
                            <div class="dp-tree-header">
                                <label>适用学科</label>
                                <span class="dp-badge" id="cp-subject-count">未选择</span>
                            </div>
                            <div class="dp-tree-wrap" id="coupon-subject-tree"></div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button class="btn btn-default" onclick="closeModal('modal-coupon')">取消</button>
                <button class="btn btn-primary" id="btn-coupon-save" onclick="saveCoupon()">保存</button>
            </div>
        </div>
    </div>

    <!-- 发放记录弹窗 -->
    <div class="modal-overlay" id="modal-coupon-record">
        <div class="modal modal-lg" style="max-width:550px;">
            <div class="modal-header">
                <h4>新增发放记录</h4>
                <button class="modal-close" onclick="closeModal('modal-coupon-record')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="required">优惠券</label>
                    <select id="cr-coupon-select" class="form-input">
                        <option value="">请选择优惠券</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">学员姓名</label>
                    <input type="text" id="cr-student-name" class="form-input" placeholder="请输入学员姓名" maxlength="200">
                </div>
                <div class="form-group">
                    <label class="required">手机号</label>
                    <input type="text" id="cr-phone" class="form-input" placeholder="请输入手机号" maxlength="20">
                </div>
                <div class="form-group">
                    <label class="required">发放人</label>
                    <input type="text" id="cr-distributor" class="form-input" placeholder="请输入发放人" maxlength="100">
                </div>
                <div class="form-group">
                    <label>发放时间</label>
                    <input type="date" id="cr-distributed-at" class="form-input">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" onclick="closeModal('modal-coupon-record')">取消</button>
                <button class="btn btn-primary" id="btn-cr-save" onclick="saveCouponRecord()">保存</button>
            </div>
        </div>
    </div>

    <script src="static/js/main.js?v=20260702a"></script>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
</body>
</html>
