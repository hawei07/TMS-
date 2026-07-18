<?php
require 'app/bootstrap.php';

$stmt = $db->query('SHOW COLUMNS FROM campuses');
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "campuses columns: " . count($cols) . "\n";
foreach ($cols as $c) echo "  " . $c['Field'] . " (" . $c['Type'] . ")\n";

$stmt = $db->query('SHOW COLUMNS FROM class_attendance');
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nclass_attendance columns: " . count($cols) . "\n";
foreach ($cols as $c) echo "  " . $c['Field'] . " (" . $c['Type'] . ")\n";

echo "\nDone.\n";
