# 预约试听重构计划

> 链式选择：校区→一级学科→课程→试听班级→课次→插班试听

## Step 1: DB — appointments表扩展
- 新增字段：campus, subject_level1, course_id, class_id, schedule_id
- ALTER TABLE via PHP init

## Step 2: PHP — 新增级联查询API
- get_trial_campuses — 有课程的校区列表
- get_trial_subjects — 某校区下的一级学科
- get_trial_courses — 某校区+学科下的课程
- get_trial_classes — 某课程下支持试听的班级(can_trial=1)
- get_trial_sessions — 某班级的已排课次(按时间升序)

## Step 3: PHP — 新增预约创建API
- book_trial — 创建appointment记录 + 学习记录插入考勤
- 状态：已预约待试听
- attendance_records 中标记 is_trial=1（新增字段或通过class_students关联）

## Step 4: HTML — 新预约弹窗
- 5级级联下拉+课次可选卡片

## Step 5: JS — 级联交互逻辑

## Step 6: 考勤处理
- 试听学员出勤时不扣课时，改预约状态为已试听

## Step 7: Git提交