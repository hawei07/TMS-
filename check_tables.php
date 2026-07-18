<?php
require 'app/bootstrap.php';

echo "=== campuses ===\n";
$r = $db->query('DESCRIBE campuses')->fetchAll(PDO::FETCH_ASSOC);
foreach ($r as $col) echo $col['Field'] . ' (' . $col['Type'] . ")\n";

echo "\n=== class_attendance ===\n";
$r = $db->query('DESCRIBE class_attendance')->fetchAll(PDO::FETCH_ASSOC);
foreach ($r as $col) echo $col['Field'] . ' (' . $col['Type'] . ")\n";
