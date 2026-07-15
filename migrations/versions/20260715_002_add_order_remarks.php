<?php
declare(strict_types=1);

return [
    'version' => '20260715_002_add_order_remarks',
    'description' => '订单表新增对内备注和对外备注字段',
    'up' => static function (PDO $db): void {
        $db->exec("ALTER TABLE orders
          ADD COLUMN internal_remark VARCHAR(100) DEFAULT '',
          ADD COLUMN external_remark VARCHAR(100) DEFAULT ''");
    },
];
