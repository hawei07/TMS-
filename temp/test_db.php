<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/temp/php_error.log');

$dbPath = __DIR__ . '/market_system.db';
$db = new SQLite3($dbPath);
$db->exec("PRAGMA journal_mode=WAL");

// Check if classes table exists FIRST
$result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='classes'");
$row = $result->fetchArray();
if (!$row) {
    echo "TABLE DOES NOT EXIST - creating now\n";
    // Run the CREATE TABLE
    $db->exec("CREATE TABLE IF NOT EXISTS classes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL DEFAULT 0,
        name TEXT NOT NULL DEFAULT '',
        class_type TEXT NOT NULL DEFAULT '标准班',
        max_students INTEGER NOT NULL DEFAULT 0,
        lesson_hours INTEGER NOT NULL DEFAULT 0,
        can_trial TEXT NOT NULL DEFAULT '是',
        campus TEXT NOT NULL DEFAULT '',
        remark TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL DEFAULT ''
    )");
    echo "Table created\n";
} else {
    echo "Table exists\n";
}

// Now try to insert
$n = date('Y-m-d H:i:s');
try {
    $stmt = $db->prepare("INSERT INTO classes (course_id, name, class_type, max_students, lesson_hours, can_trial, campus, remark, created_at) VALUES (:cid, :nm, :ct, :ms, :lh, :tr, :cp, :rm, :ca)");
    $stmt->bindValue(':cid', 1, SQLITE3_INTEGER);
    $stmt->bindValue(':nm', '测试班级', SQLITE3_TEXT);
    $stmt->bindValue(':ct', '标准班', SQLITE3_TEXT);
    $stmt->bindValue(':ms', 30, SQLITE3_INTEGER);
    $stmt->bindValue(':lh', 2, SQLITE3_INTEGER);
    $stmt->bindValue(':tr', '是', SQLITE3_TEXT);
    $stmt->bindValue(':cp', '总部校区', SQLITE3_TEXT);
    $stmt->bindValue(':rm', '', SQLITE3_TEXT);
    $stmt->bindValue(':ca', $n, SQLITE3_TEXT);
    $stmt->execute();
    echo "Insert OK, id=" . $db->lastInsertRowID() . "\n";
} catch (Exception $e) {
    echo "Insert FAILED: " . $e->getMessage() . "\n";
}
