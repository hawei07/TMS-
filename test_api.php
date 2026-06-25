<?php
header('Content-Type: application/json');
echo json_encode(['action' => $_GET['action'] ?? 'none', 'method' => $_SERVER['REQUEST_METHOD'], 'time' => date('H:i:s')]);
