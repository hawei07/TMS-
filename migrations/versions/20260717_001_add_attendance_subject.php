<?php
declare(strict_types=1);

return [
    'version' => '20260717_001_add_attendance_subject',
    'description' => '活动报名订单新增考勤学科提示字段',
    'up' => static function (PDO $db): void {
        $db->exec("ALTER TABLE orders ADD COLUMN activity_attendance_subject VARCHAR(200) DEFAULT ''");
    },
];
