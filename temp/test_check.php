<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate the web request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'add_class';

// Create a temp file to serve as php://input
$inputFile = __DIR__ . '/temp/_test_input.txt';
$inputData = '{"course_id":1,"name":"test123","class_type":"标准班","max_students":30,"campus":"总部校区"}';
file_put_contents($inputFile, $inputData);

// Override php://input by using a stream wrapper... 
// Actually, let's just include index.php and see what happens
// Since index.php does file_get_contents('php://input'), we need to mock it

// Let me check if the BOM causes issues
$indexContent = file_get_contents(__DIR__ . '/index.php');
echo "First 3 bytes: " . bin2hex(substr($indexContent, 0, 3)) . "\n";
echo "File size: " . strlen($indexContent) . "\n";

// Check if our classes table code is in there
echo "Has classes CREATE TABLE: " . (strpos($indexContent, 'CREATE TABLE IF NOT EXISTS classes') !== false ? 'YES' : 'NO') . "\n";
echo "Has case add_class: " . (strpos($indexContent, "case 'add_class':") !== false ? 'YES' : 'NO') . "\n";
echo "Has case list_classes: " . (strpos($indexContent, "case 'list_classes':") !== false ? 'YES' : 'NO') . "\n";
echo "Has case update_class: " . (strpos($indexContent, "case 'update_class':") !== false ? 'YES' : 'NO') . "\n";
echo "Has case delete_class: " . (strpos($indexContent, "case 'delete_class':") !== false ? 'YES' : 'NO') . "\n";
echo "Has panel-classes: " . (strpos($indexContent, 'panel-classes') !== false ? 'YES' : 'NO') . "\n";
echo "Has modal-class-form: " . (strpos($indexContent, 'modal-class-form') !== false ? 'YES' : 'NO') . "\n";
