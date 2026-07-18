<?php
require 'app/bootstrap.php';

echo "=== campuses ===\n";
$r = $db->query('DESCRIBE campuses')->fetchAll(PDO::FETCH_ASSOC);
foreach ($r as $col) echo $col['Field'] . ' (' . $col['Type'] . ")\n";
if (empty($r)) echo "(no columns or empty)\n";

echo "\n=== class_attendance ===\n";
$r = $db->query('DESCRIBE class_attendance')->fetchAll(PDO::FETCH_ASSOC);
foreach ($r as $col) echo $col['Field'] . ' (' . $col['Type'] . ")\n";

echo "\n=== sample campuses ===\n";
$r = $db->query('SELECT * FROM campuses LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);
foreach ($r as $row) echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
