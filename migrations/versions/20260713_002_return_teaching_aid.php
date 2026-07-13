<?php
declare(strict_types=1);

return [
    'version' => '20260713_002_return_teaching_aid',
    'description' => '退费记录表增加教材包退还标记',
    'up' => static function (PDO $db): void {
        $col = $db->query("SHOW COLUMNS FROM refund_records LIKE 'return_teaching_aid'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE refund_records ADD COLUMN return_teaching_aid TINYINT(1) DEFAULT 0 AFTER remaining_amount");
        }
    },
];
