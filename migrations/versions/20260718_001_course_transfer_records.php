<?php
declare(strict_types=1);

return [
    'version' => '20260718_001_course_transfer_records',
    'description' => 'Create course_transfer_records table for same-campus course-to-course lesson transfer',
    'up' => static function (PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS course_transfer_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source_order_id INT NOT NULL COMMENT '源订单ID',
            source_course_id INT NOT NULL COMMENT '源课程ID',
            source_course_name VARCHAR(200) NOT NULL DEFAULT '' COMMENT '源课程名称',
            target_order_id INT NOT NULL DEFAULT 0 COMMENT '目标订单ID（生成的转课课包）',
            target_course_id INT NOT NULL COMMENT '目标课程ID',
            target_course_name VARCHAR(200) NOT NULL DEFAULT '' COMMENT '目标课程名称',
            student_id INT NOT NULL COMMENT '学员ID',
            campus VARCHAR(200) NOT NULL DEFAULT '' COMMENT '校区（同校区）',
            transfer_lessons INT NOT NULL DEFAULT 0 COMMENT '转出课时数',
            transfer_value DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '转出价值',
            target_lessons INT NOT NULL DEFAULT 0 COMMENT '目标课时数',
            target_value DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '目标价值',
            is_cross_subject TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否跨学科',
            order_no VARCHAR(20) NOT NULL DEFAULT '' COMMENT '转课流水号',
            status VARCHAR(20) NOT NULL DEFAULT '正常' COMMENT '正常/已撤销',
            revoked_at DATETIME DEFAULT NULL COMMENT '撤销时间',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student (student_id),
            INDEX idx_source_order (source_order_id),
            INDEX idx_target (target_order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='同校区课程间转课记录'");
    },
];
