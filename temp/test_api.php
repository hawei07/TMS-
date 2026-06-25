<?php
ob_start();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'add_class';

// Simulate php://input with a temp stream
$tempFile = sys_get_temp_dir() . '/test_input.json';
file_put_contents($tempFile, json_encode(["course_id" => 1, "name" => "测试班级", "class_type" => "标准班", "max_students" => 30, "campus" => "总部校区"]));

// Override file_get_contents for php://input
$GLOBALS['__mock_input'] = file_get_contents($tempFile);

// We need to patch the index.php. Let's just test the handleApi logic directly
$_POST = json_decode($GLOBALS['__mock_input'], true) ?? [];

// Actually let's just directly execute the SQL part
$dbPath = __DIR__ . '/market_system.db';
$db = new SQLite3($dbPath);
$db->exec("PRAGMA journal_mode=WAL");

// Test if classes table exists
$result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='classes'");
$row = $result->fetchArray();
echo "Table exists: " . ($row ? 'YES' : 'NO') . "\n";

// Test insert
$n = date('Y-m-d H:i:s');
try {
    $stmt = $db->prepare("INSERT INTO classes (course_id, name, class_type, max_students, lesson_hours, can_trial, campus, remark, created_at) VALUES (1, '测试班级', '标准班', 30, 2, '是', '总部校区', '', '$n')");
    $stmt->execute();
    echo "Insert OK, id=" . $db->lastInsertRowID() . "\n";
} catch (Exception $e) {
    echo "Insert FAILED: " . $e->getMessage() . "\n";
}
