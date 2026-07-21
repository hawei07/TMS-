<?php
declare(strict_types=1);

return [
    'version' => '20260721_003_resale_lessons_transfer_tables',
    'description' => 'Add resale_lessons column to course_transfer_records and transfer_records for resale tracking',
    'up' => static function (PDO $db): void {
        // Add resale_lessons to course_transfer_records
        try {
            $db->exec("ALTER TABLE course_transfer_records ADD COLUMN resale_lessons INT DEFAULT 0 COMMENT '已转卖课时数'");
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1060) {
                throw $e;
            }
        }
        // Add resale_lessons to transfer_records
        try {
            $db->exec("ALTER TABLE transfer_records ADD COLUMN resale_lessons INT DEFAULT 0 COMMENT '已转卖课时数'");
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1060) {
                throw $e;
            }
        }
    },
];
