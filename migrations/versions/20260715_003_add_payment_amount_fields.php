<?php
declare(strict_types=1);

return [
    'version' => '20260715_003_add_payment_amount_fields',
    'description' => '订单表新增线上支付、通联二维码、智收银、抖音四个金额字段',
    'up' => static function (PDO $db): void {
        $db->exec("ALTER TABLE orders
          ADD COLUMN online_pay_amount DOUBLE DEFAULT 0,
          ADD COLUMN tonglian_amount DOUBLE DEFAULT 0,
          ADD COLUMN zhishouyin_amount DOUBLE DEFAULT 0,
          ADD COLUMN douyin_amount DOUBLE DEFAULT 0");
    },
];
