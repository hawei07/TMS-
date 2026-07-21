<?php
declare(strict_types=1);

return [
    'version' => '20260721_002_resale_schema',
    'description' => 'Create resale_records table and add resale-related columns to orders',
    'up' => static function (PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS resale_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            seller_student_id INT NOT NULL COMMENT '卖方学员ID',
            seller_order_id INT NOT NULL COMMENT '卖方原始报读订单ID',
            buyer_student_id INT DEFAULT NULL COMMENT '买方学员ID',
            buyer_resource_id INT DEFAULT NULL COMMENT '买方资源ID',
            buyer_type ENUM('student','resource') NOT NULL COMMENT '买入方类型',
            course_id INT NOT NULL COMMENT '课程ID',
            transfer_lessons DECIMAL(8,2) NOT NULL COMMENT '转卖课时数',
            is_full_transfer TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否全部转卖',
            transfer_amount DECIMAL(10,2) NOT NULL COMMENT '卖出课时金额',
            buyer_amount DECIMAL(10,2) NOT NULL COMMENT '买入课时金额',
            confirmed_revenue DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '确认收入',
            confirmed_revenue_after_tax DECIMAL(10,2) DEFAULT NULL COMMENT '确认收入(税后)',
            tax_rate DECIMAL(5,4) DEFAULT NULL COMMENT '适用税率',
            campus_id INT DEFAULT 0 COMMENT '经办校区ID',
            campus_name VARCHAR(500) DEFAULT '' COMMENT '经办校区名称',
            buyer_order_id INT DEFAULT 0 COMMENT '买方新生成的订单ID',
            status ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed' COMMENT '状态',
            created_by VARCHAR(200) DEFAULT '' COMMENT '操作人',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_seller_student (seller_student_id),
            INDEX idx_buyer_student (buyer_student_id),
            INDEX idx_buyer_resource (buyer_resource_id),
            INDEX idx_course (course_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_seller_order (seller_order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='课包转卖记录表'");

        // Add resale_lessons column to orders (skip if already exists)
        try {
            $db->exec("ALTER TABLE orders ADD COLUMN resale_lessons INT DEFAULT 0 COMMENT '已转卖课时数'");
        } catch (PDOException $e) {
            // 1060 = Duplicate column name — column already exists, skip
            if ((int)($e->errorInfo[1] ?? 0) !== 1060) {
                throw $e;
            }
        }

        // Add is_resale_received column to orders (skip if already exists)
        try {
            $db->exec("ALTER TABLE orders ADD COLUMN is_resale_received VARCHAR(5) DEFAULT '' COMMENT '是否转卖买入'");
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1060) {
                throw $e;
            }
        }
    },
];
