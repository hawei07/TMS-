<?php
declare(strict_types=1);

return [
    'version' => '20260714_002_schedule_cancelled_dates',
    'description' => 'schedules 表新增 cancelled_dates 字段，支持逐课次取消',
    'up' => static function (PDO $db): void {
        // 检查列是否已存在，确保幂等
        try {
            $db->query("SELECT cancelled_dates FROM schedules LIMIT 0");
        } catch (PDOException $e) {
            $db->exec("ALTER TABLE schedules ADD COLUMN cancelled_dates TEXT COMMENT '取消的课次日期 JSON 数组'");
        }
    },
];
