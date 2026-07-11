<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/app/bootstrap.php';

try {
    $result = $db->query("DESCRIBE class_attendance");
    echo "class_attendance columns:\n";
    $hasTeacher = false;
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['Field']} ({$row['Type']})\n";
        if ($row['Field'] === 'teacher') $hasTeacher = true;
    }
    
    if (!$hasTeacher) {
        $db->exec("ALTER TABLE class_attendance ADD COLUMN teacher VARCHAR(50) DEFAULT '' AFTER student_id");
        echo "\nOK: teacher column added\n";
    } else {
        echo "\nteacher column already exists\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
