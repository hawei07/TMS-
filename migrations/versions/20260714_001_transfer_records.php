<?php
declare(strict_types=1);

return [
    'version' => '20260714_001_transfer_records',
    'description' => '新增转校记录表及订单转校课时字段',
    'up' => static function (PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS transfer_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL COMMENT '原订单ID',
            student_id INT NOT NULL COMMENT '学员ID',
            student_name VARCHAR(200) NOT NULL DEFAULT '' COMMENT '学员姓名',
            order_no VARCHAR(20) NOT NULL DEFAULT '' COMMENT '原订单号',
            course_id INT NOT NULL DEFAULT 0 COMMENT '课程ID',
            course_name VARCHAR(200) NOT NULL DEFAULT '' COMMENT '课程名称',
            subject_level1 VARCHAR(200) NOT NULL DEFAULT '' COMMENT '一级学科',
            subject_level2 VARCHAR(200) NOT NULL DEFAULT '' COMMENT '二级学科',
            plan_name VARCHAR(200) NOT NULL DEFAULT '' COMMENT '价格方案',
            item_name VARCHAR(200) NOT NULL DEFAULT '' COMMENT '报价单',
            from_campus VARCHAR(200) NOT NULL DEFAULT '' COMMENT '原校区',
            to_campus VARCHAR(200) NOT NULL DEFAULT '' COMMENT '目标校区',
            transfer_lessons INT NOT NULL DEFAULT 0 COMMENT '转移课时',
            transfer_amount DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '转移金额',
            original_remaining_lessons INT NOT NULL DEFAULT 0 COMMENT '转移前剩余课时',
            status VARCHAR(20) NOT NULL DEFAULT '待审批' COMMENT '待审批/已通过/已驳回',
            applicant VARCHAR(100) NOT NULL DEFAULT '' COMMENT '申请人',
            approver VARCHAR(100) NOT NULL DEFAULT '' COMMENT '审批人',
            reject_reason VARCHAR(500) NOT NULL DEFAULT '' COMMENT '驳回原因',
            new_order_id INT NOT NULL DEFAULT 0 COMMENT '新生成的订单ID',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='转校记录表'");

        $db->exec("ALTER TABLE orders ADD COLUMN transferred_lessons INT NOT NULL DEFAULT 0 COMMENT '已转校课时数'");
    },
];
