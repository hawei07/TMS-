<?php
declare(strict_types=1);

return [
    'version' => '20260722_001_active_student_cache',
    'description' => '活跃学员缓存表：按月缓存 campus+subject 维度的活跃学员ID，当月每小时刷新，往月冻结',
    'up' => static function (PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS active_student_cache (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            campus_name VARCHAR(500) NOT NULL DEFAULT '',
            subject_name VARCHAR(200) NOT NULL DEFAULT '',
            `year_month` VARCHAR(7) NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uk_unique (student_id, campus_name, subject_name, `year_month`),
            INDEX idx_lookup (campus_name, subject_name, `year_month`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    },
];
