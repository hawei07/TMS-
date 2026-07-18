<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/app/bootstrap.php';

try {
    // attendance_records
    $result = $db->query("DESCRIBE attendance_records");
    $hasCol = false;
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        if ($row['Field'] === 'consumed_amount_post_tax') $hasCol = true;
    }
    if (!$hasCol) {
        $db->exec("ALTER TABLE attendance_records ADD COLUMN consumed_amount_post_tax DECIMAL(10,2) DEFAULT NULL AFTER consumed_amount");
        echo "OK: attendance_records.consumed_amount_post_tax added\n";
    } else {
        echo "attendance_records.consumed_amount_post_tax already exists\n";
    }

    // class_attendance
    $result = $db->query("DESCRIBE class_attendance");
    $hasCol = false;
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        if ($row['Field'] === 'consumed_amount_post_tax') $hasCol = true;
    }
    if (!$hasCol) {
        $db->exec("ALTER TABLE class_attendance ADD COLUMN consumed_amount_post_tax DECIMAL(10,2) DEFAULT NULL AFTER consumed_amount");
        echo "OK: class_attendance.consumed_amount_post_tax added\n";
    } else {
        echo "class_attendance.consumed_amount_post_tax already exists\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
