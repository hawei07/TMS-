# 06-data-dictionary.md — 数据字典

> 版本：v1.0  
> 日期：2026-07-16  
> 数据库：MySQL (utf8mb4)，PHP PDO 无 ORM

---

## 概述

| 属性 | 值 |
|------|-----|
| 引擎 | MySQL 5.7+ with InnoDB |
| 字符集 | utf8mb4 |
| 总表数 | ~44 张 |
| 状态字段风格 | VARCHAR(500)，无 ENUM 约束 |
| 时间字段风格 | DATETIME 或 VARCHAR(500)（不统一） |
| 金额字段风格 | DECIMAL(10,2) 或 REAL |
| 统一自增主键 | id INT PRIMARY KEY AUTO_INCREMENT |

---

## 表索引

### CRM 与招生域

| 表名 | 用途 | 行数 |
|------|------|------|
| resources | 线索/资源池 | 主表 |
| appointments | 试听预约 | 主表 |
| communication_records | 沟通记录 | 主表 |
| intention_levels | 意向等级字典 | 字典 |
| channels | 渠道字典 | 字典 |
| basic_types | 可扩展基础类型（课程类型/沟通类型） | 字典 |

### 身份与组织域

| 表名 | 用途 |
|------|------|
| employees | 员工名册 |
| organizations | 组织架构树（大区/校区/部门） |
| positions | 岗位字典 |

### 课程与定价域

| 表名 | 用途 |
|------|------|
| subjects | 学科树（二级：一级学科 / 二级学科） |
| courses | 课程 |
| price_plans | 报价方案 |
| price_items | 报价单明细 |
| teaching_aids | 教材/画具 |
| teaching_aid_campuses | 画具关联校区 |
| class_periods | 上课时段字典 |
| tax_rates | 校区税率设置 |

### 学员与教务域

| 表名 | 用途 |
|------|------|
| students | 学员主表 |
| student_subject_teacher | 学员-校区-学科-授课老师关联 |
| classes | 班级 |
| class_students | 班级学员关联 |
| schedules | 排课规则 |
| classrooms | 教室 |
| class_attendance | 班级考勤/活动考勤 |
| attendance_records | 考勤记录（历史/冗余） |
| absence_records | 缺勤记录（快照） |
| activities | 活动 |
| activity_campuses | 活动关联校区 |
| activity_subject_deductions | 活动学科扣课规则 |
| activity_enrollment_counts | 活动报名人数缓存 |

### 订单与账户域

| 表名 | 用途 |
|------|------|
| parent_orders | 父子订单主表 |
| orders | 订单明细（报名/画具/活动/赠课/储值） |
| student_accounts | 学员储值账户 |
| account_transactions | 账户流水 |
| refund_records | 退费审批记录 |
| transfer_records | 转校审批记录 |

### 营销域

| 表名 | 用途 |
|------|------|
| discount_plans | 优惠方案 |
| discount_plan_campuses | 方案关联校区 |
| discount_plan_subjects | 方案关联学科 |
| coupons | 优惠券模板 |
| coupon_campuses | 优惠券关联校区 |
| coupon_subjects | 优惠券关联学科 |
| coupon_records | 优惠券发放/核销记录 |

---

## 详细定义

### resources — 线索/资源池

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | 线索ID |
| name | VARCHAR(500) | 姓名 |
| phone | VARCHAR(500) | 电话 |
| source | VARCHAR(500) | 渠道（关联 channels.name） |
| source_detail | VARCHAR(500) | 渠道明细 |
| intention_level | VARCHAR(500) | 意向等级（关联 intention_levels.name） |
| status | VARCHAR(500) | 状态：待跟进/已联系/无意向/试听预约/已报名 |
| assigned_to | VARCHAR(500) | 归属人 |
| pool_type | VARCHAR(500) | 资源池类型：我的资源/公海 |
| converted | VARCHAR(500) | 转化状态：未转化/已转化 |
| gender | VARCHAR(500) | 性别（ALTER 兼容新增） |
| birth_date | VARCHAR(500) | 出生日期 |
| follow_status | VARCHAR(500) | 跟进状态 |
| created_at | VARCHAR(500) | 创建时间 |
| updated_at | VARCHAR(500) | 更新时间 |

**索引**: idx_phone, idx_updated_at, idx_pool_type

**数据质量风险**:
- `assigned_to` 存人名而非员工 ID，无外键
- `status` 与 `converted` 可能不一致

**目标领域**: crm-enrollment

---

### appointments — 试听预约

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| resource_id | INT | 线索ID |
| resource_name | VARCHAR(500) | 线索名 |
| student_name | VARCHAR(500) | 学员名 |
| phone | VARCHAR(500) | 电话 |
| course_type | VARCHAR(500) | 课程类型 |
| appointment_time | VARCHAR(500) | 预约时间 |
| campus | VARCHAR(500) | 校区（重构新增） |
| subject_level1 | VARCHAR(500) | 一级学科 |
| course_id | INT | 课程ID |
| class_id | INT | 班级ID |
| schedule_id | INT | 课次ID |
| status | VARCHAR(500) | 已预约待试听/已试听/缺勤/已取消 |
| notes | VARCHAR(500) | 备注 |
| created_at | VARCHAR(500) | |

**索引**: idx_appointments_time

**数据质量风险**:
- `status` 通过 `class_attendance` 动态计算（effective_status），与存储值可能不一致
- `resource_name` 和 `student_name` 快照冗余

**目标领域**: crm-enrollment

---

### students — 学员主表

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| resource_id | INT | 来源线索ID |
| name | VARCHAR(500) | 姓名 |
| phone | VARCHAR(500) UNIQUE | 电话（全局唯一） |
| student_no | VARCHAR(500) | 学号 |
| source | VARCHAR(500) | 来源渠道 |
| follow_status | VARCHAR(500) | |
| student_type | VARCHAR(500) | 小课包/常规（默认小课包） |
| created_at | DATETIME | |

**索引**: idx_students_student_no, idx_students_phone, idx_students_name

**关键规则**: student_type 自动升级：检测到有效非小课包订单时升级为"常规"，不可逆回退。

**目标领域**: identity-access（也参与 crm-enrollment）

---

### student_subject_teacher — 学员-学科-老师

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| student_id | INT | |
| campus_id | INT | |
| subject_id | INT | |
| teacher_id | INT | 默认 0 |
| created_at | VARCHAR(500) | |

**唯一约束**: uk_sct (student_id, campus_id, subject_id)

**目标领域**: education-teaching

---

### courses — 课程

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| name | VARCHAR(500) | 课程名称 |
| subject | VARCHAR(500) | 学科分类 |
| grade | VARCHAR(500) | 年级 |
| description | VARCHAR(500) | 描述 |
| small_package | VARCHAR(500) | 是否小课包（ALTER 新增） |
| toddler | VARCHAR(500) | 是否幼儿班（ALTER 新增） |
| campus_permission | VARCHAR(500) | 校区权限（ALTER 新增） |
| subject_level1 | VARCHAR(500) | 一级学科 |
| subject_level2 | VARCHAR(500) | 二级学科 |
| created_at | DATETIME | |

**数据质量风险**: `subject` 字段（旧）与 `subject_level1`/`subject_level2`（新）共存，语义可能不统一

**目标领域**: catalog-pricing

---

### price_plans — 报价方案

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| course_id | INT | 关联课程 |
| name | VARCHAR(500) | 方案名 |
| plan_type | VARCHAR(500) | 方案类型 |
| sort_order | INT | 排序 |
| created_at | DATETIME | |

**外键约束**: 无

**目标领域**: catalog-pricing

---

### price_items — 报价单明细

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| plan_id | INT | 关联方案 |
| name | VARCHAR(500) | 报价单元名称 |
| lesson_count | INT | 课时数 |
| unit_price | REAL | 原价 |
| actual_price | REAL | 实价（primary key of pricing） |
| discount_plan_id | INT | 关联优惠方案（ALTER 新增） |
| coupon_id | INT | 关联优惠券（ALTER 新增） |
| teaching_aid_id | INT | 关联画具（ALTER 新增） |
| product_coupon_id | INT | 关联商品券（ALTER 新增） |
| gifted_lessons | INT | 赠课数（ALTER 新增） |
| sort_order | INT | 排序 |

**关键规则**: `actual_price = unit_price - discount - coupon + teaching_aid - product_coupon`

**目标领域**: catalog-pricing

---

### orders — 订单明细

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| student_id | INT | 学员ID |
| course_id | INT | 课程ID |
| order_no | VARCHAR(500) | 订单号 |
| parent_order_no | VARCHAR(500) | 父订单号 |
| plan_name | VARCHAR(500) | 报价方案名 |
| item_name | VARCHAR(500) | 报价单元名 |
| lesson_count | INT | 购买课时 |
| gifted_lessons | INT | 赠送课时（ALTER 新增） |
| consumed_lessons | INT | 已消耗课时（默认 0） |
| transferred_lessons | INT | 已转校课时（迁移新增，默认 0） |
| actual_price | REAL | 实付金额 |
| order_type | VARCHAR(500) | 新报/续费/扩科/小课包/赠送/活动报名/储值/画具销售 |
| status | VARCHAR(500) | 已报名/已取消 |
| is_voided | VARCHAR(5) | 是否作废：是/否 |
| pay_status | VARCHAR(20) | 支付状态：待支付/已支付 |
| refund_status | VARCHAR(10) | 退款状态：正常/退费申请中/已退费 |
| payment_method | VARCHAR(500) | 支付方式 |
| paid_amount | REAL | 支付总金额 |
| cash_amount | REAL | 现金金额 |
| meituan_amount | REAL | 美团金额 |
| account_amount | REAL | 账户余额支付金额 |
| campus | VARCHAR(500) | 校区 |
| subject_level1 | VARCHAR(500) | 一级学科 |
| subject_level2 | VARCHAR(500) | 二级学科 |
| paid_at | VARCHAR(500) | 支付时间 |
| created_at | DATETIME | 创建时间 |

**优惠快照列（ALTER 新增）**:

| 字段 | 类型 |
|------|------|
| discount_plan_name | VARCHAR(200) |
| discount_plan_amount | DECIMAL(10,2) |
| coupon_name | VARCHAR(200) |
| coupon_amount | DECIMAL(10,2) |
| teaching_aid_name | VARCHAR(200) |
| teaching_aid_price | DECIMAL(10,2) |
| product_coupon_name | VARCHAR(200) |
| product_coupon_amount | DECIMAL(10,2) |

**活动报名列（ALTER 新增）**:

| 字段 | 类型 | 说明 |
|------|------|------|
| activity_id | INT | 活动ID |
| activity_name | VARCHAR(200) | 活动名 |
| activity_campus | VARCHAR(200) | 活动校区 |
| activity_adult_count | INT | 成人数量 |
| activity_student_count | INT | 学员数量 |
| adult_unit_price | DECIMAL(10,2) | 成人单价 |
| student_unit_price | DECIMAL(10,2) | 学员单价 |
| activity_fee_type | VARCHAR(20) | 收费模式 |

**索引**: idx_orders_parent_order_no, idx_orders_order_no, idx_orders_created_at, idx_orders_paid_at, idx_orders_student_id, idx_orders_course_id, idx_orders_campus, idx_orders_pay_status

**数据质量风险**:
- `plan_name` / `item_name` 快照冗余，不与 price_plans / price_items 同步更新
- `gifted_lessons` 不参与退费计算
- `consumed_lessons` 无触发器和 deduction_json 无强一致性校验
- 一条 orders 记录承载多种语义（课程订单 / 活动报名 / 画具销售 / 储值 / 赠课）

**目标领域**: order-entitlement（同时跨越 wallet-ledger / study-activity）

---

### parent_orders — 父订单

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| parent_order_no | VARCHAR(500) | 父订单号 |
| child_order_nos | VARCHAR(500) | 子订单号列表 |
| course_name | VARCHAR(500) | |
| total_lessons | INT | 总课时 |
| student_name | VARCHAR(500) | |
| phone | VARCHAR(500) | |
| student_no | VARCHAR(500) | |
| campus | VARCHAR(500) | ALTER 新增 |
| enroll_time | VARCHAR(500) | |
| total_price | REAL | 总金额 |
| cash_amount | REAL | |
| meituan_amount | REAL | |
| created_at | VARCHAR(500) | |

**数据质量风险**: `child_order_nos` 存拼接字符串而非 JSON

**目标领域**: order-entitlement

---

### student_accounts — 学员储值账户

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| student_id | INT UNIQUE | 学员ID |
| balance | DECIMAL(10,2) | 当前余额 |
| total_deposit | DECIMAL(10,2) | 累计充值 |
| total_consume | DECIMAL(10,2) | 累计消费 |
| total_refund | DECIMAL(10,2) | 累计退款 |
| created_at / updated_at | DATETIME | |

**关键规则**: 余额操作必须使用 `FOR UPDATE` 行锁。
**幂等性**: `get_student_account` 对不存在的 student_id 自动初始化（balance=0）。

**目标领域**: wallet-ledger

---

### account_transactions — 账户流水

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| student_id | INT | |
| type | ENUM('deposit','consume','refund') | 流水类型 |
| amount | DECIMAL(10,2) | 金额 |
| balance_after | DECIMAL(10,2) | 操作后余额 |
| ref_type | VARCHAR(50) | 关联类型 |
| ref_id | INT | 关联ID |
| campus | VARCHAR(500) | 校区 |
| subject | VARCHAR(200) | 学科（ALTER 新增） |
| payment_method | VARCHAR(50) | 支付方式（ALTER 新增） |
| note | VARCHAR(500) | 备注 |
| created_at | DATETIME | |

**索引**: idx_student, idx_created

**目标领域**: wallet-ledger

---

### refund_records — 退费记录

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| project | VARCHAR(20) | 退费类型：课程/账户/画具（ALTER 新增） |
| content | VARCHAR(500) | 退费内容（ALTER 新增） |
| subject_level1 | VARCHAR(100) | 学科（ALTER 新增） |
| refund_method | VARCHAR(20) | 退费方式（ALTER 新增） |
| order_id | INT | 关联订单 |
| student_id | INT | 学员ID |
| campus | VARCHAR(500) | 校区 |
| course_name | VARCHAR(500) | 课程名 |
| total_lessons | INT | 总课时 |
| total_amount | DECIMAL(10,2) | 总金额 |
| consumed_lessons | INT | 已消耗课时 |
| consumed_amount | DECIMAL(10,2) | 课耗金额 |
| remaining_lessons | INT | 剩余课时 |
| remaining_amount | DECIMAL(10,2) | 剩余金额 |
| custom_deduction | DECIMAL(10,2) | 自定义扣除 |
| actual_refund | DECIMAL(10,2) | 实际退款金额 |
| bank_name / bank_account / account_holder | VARCHAR(500) | 银行信息 |
| refund_reason | TEXT | 原因 |
| status | VARCHAR(20) | 待审批/一级审批通过/二级审批通过/已退费/审批驳回 |
| approval_stage | VARCHAR(10) | 一级审批/二级审批 |
| reject_reason | TEXT | 驳回原因 |
| approver1 / approver2 / approver3 | VARCHAR(500) | 审批人 |
| created_at / updated_at | VARCHAR(500) | |

**数据质量风险**:
- `actual_refund = total_amount - consumed_amount - custom_deduction`（课程退款）
- 未自动计算（依赖前端传入）

**目标领域**: wallet-ledger

---

### transfer_records — 转校记录

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| order_id | INT | 原订单ID |
| student_id | INT | |
| student_name | VARCHAR(200) | |
| order_no | VARCHAR(20) | |
| course_id | INT | |
| course_name | VARCHAR(200) | |
| subject_level1 | VARCHAR(200) | |
| subject_level2 | VARCHAR(200) | |
| plan_name | VARCHAR(200) | |
| item_name | VARCHAR(200) | |
| from_campus | VARCHAR(200) | 原校区 |
| to_campus | VARCHAR(200) | 目标校区 |
| transfer_lessons | INT | 转移课时 |
| transfer_amount | DECIMAL(10,2) | 转移金额 |
| original_remaining_lessons | INT | 转移前剩余课时 |
| status | VARCHAR(20) | 待审批/已通过/已驳回 |
| applicant / approver | VARCHAR(100) | |
| reject_reason | VARCHAR(500) | |
| new_order_id | INT | 新订单ID |
| created_at / updated_at | DATETIME | |

**目标领域**: order-entitlement

---

### classes — 班级

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| course_id | INT | 关联课程 |
| name | VARCHAR(500) | 班级名 |
| class_type | VARCHAR(500) | 标准班 |
| max_students | INT | 最大人数 |
| lesson_hours | INT | 课时数 |
| can_trial | VARCHAR(500) | 允许试听：是/否 |
| campus | VARCHAR(500) | 校区 |
| remark | VARCHAR(500) | 备注 |
| created_at | VARCHAR(500) | |

**目标领域**: education-teaching

---

### schedules — 排课

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| class_id | INT | 班级ID |
| rule_type | VARCHAR(500) | 按规则排课/手动排课 |
| start_date | VARCHAR(500) | 开始日期 |
| end_date | VARCHAR(500) | 结束日期 |
| weekdays | VARCHAR(500) | 周几 |
| time_slots | VARCHAR(500) = '{}' | 时段（JSON） |
| holiday_enabled | INT | 节假日 |
| teacher | VARCHAR(500) | 老师 |
| classroom | VARCHAR(500) | 教室 |
| created_at | VARCHAR(500) | |

**数据质量风险**: `time_slots` 存 JSON 字符串，`teacher` / `classroom` 存名称而非 ID 外键

**目标领域**: education-teaching

---

### class_students — 班级学员

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| class_id | INT | |
| student_id | INT | |
| left_at | VARCHAR(500) | 出班时间（空=在班） |
| joined_at | VARCHAR(500) | 入班时间（ALTER 新增） |
| created_at | VARCHAR(500) | |

**唯一约束**: UNIQUE(class_id, student_id)

**目标领域**: education-teaching

---

### class_attendance — 班级/活动考勤

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| class_id | INT | 班级ID |
| schedule_id | INT | 课次ID |
| session_date | VARCHAR(500) | 上课日期 |
| student_id | INT | 学员ID |
| student_name | VARCHAR(500) | 学员名（ALTER 新增） |
| status | VARCHAR(500) | 出勤/缺勤/请假/未到 |
| deducted_lessons | INT | 扣除课时数 |
| deducted_order_id | INT | 扣课订单ID（单条扣课时） |
| deduction_json | TEXT | 扣课明细 JSON（多订单分摊时） |
| is_temporary | INT | 是否临时学员 |
| consumed_amount | DECIMAL(10,2) | 课耗金额（ALTER 新增） |
| activity_id | INT | 活动ID（ALTER 新增） |
| activity_order_id | INT | 活动订单ID（ALTER 新增） |
| adult_attended | INT | 成人到场数（ALTER 新增） |
| student_attended | INT | 学员到场数（ALTER 新增） |
| deduction_breakdown | TEXT | 扣费明细 JSON（ALTER 新增） |
| created_at | VARCHAR(500) | |

**索引**: idx_class_attendance_session, idx_class_attendance_activity

**关键规则**:
- 同学员+课次唯一一条记录
- 三级扣课优先级：deduction_json 内排序
- 修改状态/扣课数时先还课时再重新扣

**数据质量风险**: deduction_json 可能引用已退费的订单

**目标领域**: education-teaching / study-activity

---

### attendance_records — 考勤记录（历史）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| student_id | INT | |
| course_id | INT | |
| order_id | INT | 扣课订单 |
| class_id | INT | |
| schedule_id | INT | |
| class_name | VARCHAR(500) | |
| campus | VARCHAR(500) | |
| teacher | VARCHAR(500) | |
| subject_level1 / subject_level2 | VARCHAR(500) | |
| class_time | VARCHAR(500) | |
| lesson_date | VARCHAR(500) | |
| status | VARCHAR(500) | |
| deducted_lessons | INT | |
| consumed_amount | DECIMAL(10,2) | |
| notes | VARCHAR(500) | |
| attended_at | VARCHAR(500) | |
| created_at | VARCHAR(500) | |

**数据质量风险**: 与 class_attendance 数据可能冗余/不一致

**目标领域**: education-teaching

---

### activities — 活动

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| name | VARCHAR(200) | 活动名 |
| subject_level1 | VARCHAR(200) | 一级学科 |
| reg_start_date | DATE | 报名开始 |
| reg_end_date | DATE | 报名截止 |
| adult_fee_mode | VARCHAR(20) | fee_only / fee_deduct / deduct_only |
| student_fee_mode | VARCHAR(20) | 同上 |
| adult_price | DECIMAL(10,2) | 成人单价 |
| student_price | DECIMAL(10,2) | 学员单价 |
| created_at / updated_at | DATETIME | |

**目标领域**: study-activity

---

### activity_campuses — 活动校区

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| activity_id | INT FK | |
| campus_name | VARCHAR(200) | |
| max_capacity | INT | 0=不限 |

**外键**: ON DELETE CASCADE

**目标领域**: study-activity

---

### activity_subject_deductions — 活动学科扣课

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| activity_id | INT FK | |
| fee_type | VARCHAR(10) | adult / student |
| subject_level1 | VARCHAR(200) | |
| deduct_lessons | INT | 扣课时数 |

**外键**: ON DELETE CASCADE

**目标领域**: study-activity

---

### teaching_aids — 教材画具

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| name | VARCHAR(200) | 名称 |
| unit | VARCHAR(20) | 单位（个/套/本） |
| type | VARCHAR(20) | 画具/教材包（ALTER 新增） |
| subject_id | INT | 学科ID |
| price | DECIMAL(10,2) | 售价 |
| status | VARCHAR(10) | 上架/下架 |
| remark | TEXT | |
| created_at / updated_at | DATETIME | |

**目标领域**: catalog-pricing

---

### teaching_aid_sales — 画具销售记录

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| teaching_aid_id | INT FK | |
| student_id | INT FK | |
| student_name | VARCHAR(200) | |
| student_no | VARCHAR(100) | |
| teaching_aid_name | VARCHAR(200) | |
| type | VARCHAR(50) | |
| quantity | INT | 数量 |
| unit_price | DECIMAL(10,2) | |
| total_amount | DECIMAL(10,2) | |
| cash_amount / meituan_amount / account_amount | DECIMAL(10,2) | 支付分摊 |
| campus | VARCHAR(500) | |
| sold_at | DATETIME | |
| sold_by | VARCHAR(100) | |
| remark | VARCHAR(500) | |
| created_at | DATETIME | |

**外键**: ON DELETE RESTRICT

**索引**: idx_tas_sold_at, idx_tas_student_name, idx_tas_teaching_aid_name

**目标领域**: order-entitlement

---

### discount_plans — 优惠方案

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| name | VARCHAR(200) | |
| plan_type | VARCHAR(20) | 新报/续费/扩科 |
| discount_amount | DECIMAL(10,2) | 优惠金额 |
| start_date / end_date | VARCHAR(20) | 有效期 |
| created_at / updated_at | DATETIME | |

**目标领域**: marketing-growth

---

### coupons — 优惠券模板

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| name | VARCHAR(200) | |
| coupon_type | VARCHAR(20) | 课程券/商品券 |
| discount_amount | DECIMAL(10,2) | 优惠金额 |
| start_date / end_date | VARCHAR(20) | 有效期 |
| created_at / updated_at | DATETIME | |

**目标领域**: marketing-growth

---

### coupon_records — 优惠券发放/核销记录

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| coupon_id | INT FK | |
| coupon_name | VARCHAR(200) | 快照 |
| student_name | VARCHAR(200) | |
| phone | VARCHAR(50) | |
| usage_status | VARCHAR(20) | 未使用/已使用/已过期（ALTER 新增） |
| issuer | VARCHAR(100) | 发放人 |
| issued_at | DATETIME | |
| created_at | DATETIME | |

**外键**: ON DELETE CASCADE

**目标领域**: marketing-growth

---

### tax_rates — 税率设置

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INT PK AI | |
| campus_id | INT | 校区 |
| course_tax_rate | DECIMAL(5,2) | 课程税率（如 6.00 = 6%） |
| product_tax_rate | DECIMAL(5,2) | 商品税率 |
| updated_at | VARCHAR(500) | |

**税率应用公式**: `consumed_amount_post_tax = consumed_amount / (1 + tax_rate)`

**目标领域**: data-analytics

---

### 其他表

| 表名 | 用途 |
|------|------|
| channels | 渠道字典：id/name/created_at |
| intention_levels | 意向等级：id/name/sort_order/created_at |
| basic_types | 通用类型：id/category/name/sort_order/created_at |
| positions | 岗位：id/name/sort_order/created_at |
| organizations | 组织架构：id/name/type/parent_id/sort_order/created_at |
| employees | 员工：id/name/phone/department/position/entry_date/status/is_teacher/created_at/updated_at |
| classrooms | 教室：id/name/capacity/campus/remark/created_at |
| class_periods | 上课时段：id/name/start_time/end_time/sort_order/campus/created_at |
| communication_records | 沟通记录：id/resource_id/resource_name/content/comm_type/created_at |
| absence_records | 缺勤快照（详见 schema） |
| activity_enrollment_counts | 活动报名缓存：activity_id+campus_name UNIQUE |
| discount_plan_campuses | 方案-校区 |
| discount_plan_subjects | 方案-学科 |
| coupon_campuses | 券-校区 |
| coupon_subjects | 券-学科 |
| teaching_aid_campuses | 画具-校区 |

---

## 全局数据质量问题

1. **无外键约束（多数表）**：除 discount/coupon/teaching_aid/activity 族外，其余表均无外键。数据和引用完整性依赖应用层代码。
2. **VARCHAR(500) 万能字段**：几乎所有字段都设为 VARCHAR(500)，无类型语义（如 DATE 存为 VARCHAR）。
3. **状态字段无 ENUM 约束**：依赖代码而非数据库约束，脏数据风险高。
4. **快照冗余严重**：name / amount / price 类字段在多表冗余存储，更新源表后快照不会同步。
5. **时间格式不统一**：created_at 在部分表为 VARCHAR(500)，部分表为 DATETIME。
6. **金额精度混合**：REAL（浮点）和 DECIMAL(10,2) 混用，real 类型有精度损失风险。

## 迁移到 PostgreSQL 建议

1. VARCHAR(500) → TEXT（PG 无需长度限制）
2. REAL → NUMERIC(10,2)
3. 状态字段 → 使用 PG ENUM 或 CHECK CONSTRAINT
4. 时间字段统一 → TIMESTAMP WITH TIME ZONE
5. 补充外键约束（允许 ON DELETE SET NULL 的合理策略）
6. 使用 JSONB 替代 TEXT 存 JSON（deduction_json / time_slots）
7. 使用 ARRAY 替代 VARCHAR 存列表（weekdays / child_order_nos）
8. 主键使用 SERIAL / BIGSERIAL，考虑 UUID 用于 orders / students
