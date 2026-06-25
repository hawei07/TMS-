<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'add_class';
file_put_contents('php://input', json_encode(["course_id"=>1,"name"=>"测试班级","class_type"=>"标准班","max_students"=>30,"campus"=>"总部校区"]));
include 'index.php';
