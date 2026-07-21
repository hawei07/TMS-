<?php
declare(strict_types=1);

return [
    'version' => '20260721_001_transfer_from_course_transfer',
    'description' => 'Allow school transfers to originate from course-transfer packages',
    'up' => static function (PDO $db): void {
        $columns = [];
        foreach ($db->query('SHOW COLUMNS FROM transfer_records') as $column) {
            $columns[] = $column['Field'];
        }
        if (!in_array('total_transferred', $columns, true)) {
            $db->exec("ALTER TABLE transfer_records ADD COLUMN total_transferred INT NOT NULL DEFAULT 0 COMMENT 'Lessons transferred onward'");
        }
        if (!in_array('source_transfer_id', $columns, true)) {
            $db->exec("ALTER TABLE transfer_records ADD COLUMN source_transfer_id INT NOT NULL DEFAULT 0 COMMENT 'Source school transfer record ID'");
        }
        if (!in_array('source_course_transfer_id', $columns, true)) {
            $db->exec("ALTER TABLE transfer_records ADD COLUMN source_course_transfer_id INT NOT NULL DEFAULT 0 COMMENT 'Source course transfer record ID'");
        }

        $indexes = [];
        foreach ($db->query('SHOW INDEX FROM transfer_records') as $index) {
            $indexes[] = $index['Key_name'];
        }
        if (!in_array('idx_source_course_transfer', $indexes, true)) {
            $db->exec('ALTER TABLE transfer_records ADD INDEX idx_source_course_transfer (source_course_transfer_id)');
        }
    },
];
