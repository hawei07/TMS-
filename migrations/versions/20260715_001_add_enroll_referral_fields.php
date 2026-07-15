<?php
declare(strict_types=1);

return [
    'version' => '20260715_001_add_enroll_referral_fields',
    'description' => '录单页面新增课程顾问/试听老师/扩科老师/续费老师/转介绍老师/转介绍学员字段',
    'up' => static function (PDO $db): void {
        $db->exec('ALTER TABLE orders
          ADD COLUMN advisor_id INT DEFAULT 0,
          ADD COLUMN trial_teacher_id INT DEFAULT 0,
          ADD COLUMN expansion_teacher_id INT DEFAULT 0,
          ADD COLUMN renewal_teacher_id INT DEFAULT 0,
          ADD COLUMN referral_teacher_id INT DEFAULT 0,
          ADD COLUMN referral_student_id INT DEFAULT 0');
    },
];
