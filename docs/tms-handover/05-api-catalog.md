# 05-api-catalog.md — API 目录

> 版本：v1.0  
> 日期：2026-07-16  
> 格式：按目标领域分组，标注 HTTP 方法、权限、数据范围、写操作、事务、幂等性、代码位置、风险等级。

---

## 路由架构

```
请求入口: index.php → ?action=XXX
├── 路由层: api/router.php → buildExtractedApiRoutes()
│   ├── api/dictionaries.php (16 个 action)
│   ├── api/settings.php (6 个 action)
│   ├── api/organizations.php (4 个 action)
│   ├── api/teaching_aids.php (9 个 action)
│   ├── api/discounts.php (13 个 action)
│   └── api/orders.php (8 个 action)
└── 遗留路由: index.php switch-case (~117 个 action)
```

总计约 **173** 个 API action。

---

## 1. CRM 与招生 (crm-enrollment)

### 线索/资源

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| get_resources | GET | index.php:249 | 否 | 否 | 无分页 |
| add_resource | POST | index.php:295 | INSERT resources | 否 | 无权限 |
| update_resource | POST | index.php:323 | UPDATE resources | 否 | 无权限 |
| delete_resource | POST | index.php:362 | DELETE resources | 否 | 无权限 |
| batch_import | POST | index.php:370 | INSERT N 条 resources | 否 | 无分页；大数据量超时 |
| download_template | GET | index.php:551 | 否 | 否 | - |
| batch_assign | POST | index.php:566 | UPDATE resources | 否 | 无权限；批量无确认 |
| batch_pool | POST | index.php:616 | UPDATE resources | 否 | 无权限 |
| get_stats | GET | index.php:966 | 否 | 否 | - |
| export_resources | GET | index.php:981 | 否 | 否 | 全量导出无限制 |

### 预约/试听

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| get_appointments | GET | index.php:630 | 否 | 否 | 无分页 |
| get_trial_campuses | GET | index.php:712 | 否 | 否 | - |
| get_trial_subjects | GET | index.php:716 | 否 | 否 | - |
| get_trial_courses | GET | index.php:727 | 否 | 否 | - |
| get_trial_classes | GET | index.php:744 | 否 | 否 | - |
| get_trial_sessions | GET | index.php:763 | 否 | 否 | - |
| search_trial_sessions | GET | index.php:778 | 否 | 否 | - |
| book_trial | POST | index.php:845 | INSERT appointments | 否 | 无权限 |
| cancel_trial | POST | index.php:888 | UPDATE appointments + DELETE class_attendance | 是 | 无权限 |
| add_appointment | POST | index.php:909 | INSERT appointments | 否 | 重复接口(book_trial 已存在) |
| update_appointment | POST | index.php:925 | UPDATE appointments | 否 | 无权限 |
| delete_appointment | POST | index.php:939 | DELETE appointments | 否 | 无权限 |

### 沟通记录

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| get_communications | GET | index.php:945 | 否 | 否 | 无分页 |
| add_communication | POST | index.php:952 | INSERT communication_records | 否 | 无权限 |

### 学员

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| list_students | GET | index.php:1997 | 否 | 否 | 无分页 |
| get_student | GET | index.php:2157 | 否 | 否 | - |
| add_student | POST | index.php:2188 | INSERT students + UPDATE resources | 否 | 无权限 |
| update_student | POST | index.php:2217 | UPDATE students + DELETE/INSERT sst | 否 | 无权限 |
| delete_student | POST | index.php:2255 | DELETE students | 否 | 无权限 |
| search_students | GET | index.php:1637 | 否 | 否 | - |
| get_student_courses | GET | index.php:2460 | 否 | 否 | 无分页 |

### 字典配置（渠道/意向/基础类型）

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| list_channels | GET | api/dictionaries.php | 否 | 否 | - |
| add_channel | POST | api/dictionaries.php | INSERT channels | 否 | - |
| update_channel | POST | api/dictionaries.php | UPDATE channels + resources | 是 | ⚠️ 同步 resources.source |
| delete_channel | POST | api/dictionaries.php | DELETE channels | 否 | ⚠️ 未级联清理 resources |
| list_intention_levels | GET | api/dictionaries.php | 否 | 否 | - |
| add_intention_level | POST | api/dictionaries.php | INSERT intention_levels | 否 | - |
| update_intention_level | POST | api/dictionaries.php | UPDATE intention_levels + resources | 是 | ⚠️ 同步 resources |
| delete_intention_level | POST | api/dictionaries.php | DELETE intention_levels | 否 | ⚠️ 未级联 |
| list_basic_types | GET | api/dictionaries.php | 否 | 否 | 需 category 参数 |
| add_basic_type | POST | api/dictionaries.php | INSERT basic_types | 否 | - |
| update_basic_type | POST | api/dictionaries.php | UPDATE basic_types + 关联表 | 是 | 影响预约/沟通 |
| delete_basic_type | POST | api/dictionaries.php | DELETE basic_types | 否 | ⚠️ 无级联检查 |

---

## 2. 订单与权益 (order-entitlement)

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| pay_enroll | POST | index.php:1659 | INSERT parent_orders + orders + account_transactions | 部分 | 🔴 多表写入无统一事务 |
| enroll_course | POST | index.php:2265 | INSERT orders + UPDATE student_accounts | 否 | 🔴 纯账户支付 |
| enroll_from_resource | POST | index.php:2362 | INSERT student + order | 否 | 从线索一键建档+报名 |
| create_student_from_resource | POST | index.php:2433 | INSERT students + UPDATE resources | 否 | - |
| list_orders | GET | api/orders.php | 否 | 否 | 无分页上限 |
| get_order_detail | GET | api/orders.php | 否 | 否 | - |
| list_parent_orders | GET | api/orders.php | 否 | 否 | 无分页 |
| void_order | POST | api/orders.php | UPDATE orders + account_transactions | 是 | 🔴 多重前置校验，行锁 |
| list_price_plans | GET | api/orders.php | 否 | 否 | - |
| get_course_plans | GET | api/orders.php | 否 | 否 | - |
| save_price_plan | POST | api/orders.php | INSERT price_plans + REPLACE price_items | 是 | 全量替换 |
| delete_price_plan | POST | api/orders.php | DELETE price_plans + price_items | 否 | ⚠️ 无报价单引用检查 |

### 退费

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| submit_refund | POST | index.php:2934 | INSERT refund_records + UPDATE orders | 部分 | 🔴 多状态联动 |
| list_refund_records | GET | index.php:3134 | 否 | 否 | 无分页 |
| approve_refund | POST | index.php:3211 | UPDATE refund_records + orders + account | 部分 | 🔴 多表+审批状态机 |
| cancel_refund | POST | index.php:3341 | DELETE refund_records + UPDATE orders | 否 | 恢复退费申请 |
| get_refund_record | GET | index.php:3608 | 否 | 否 | - |

### 转校

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| submit_transfer | POST | index.php:3384 | INSERT transfer_records | 否 | 无权限 |
| list_transfer_records | GET | index.php:3527 | 否 | 否 | - |
| approve_transfer | POST | index.php:3548 | UPDATE orders + INSERT 新 orders | 否 | 🔴 复杂课时转移逻辑 |

---

## 3. 钱包与账本 (wallet-ledger)

| action | 方法 | 代码位置 | 写操作 | 事务 | 行锁 | 风险 |
|--------|------|---------|--------|------|------|------|
| get_student_account | GET | index.php:3623 | 否（首次自动初始化） | - | - | 幂等 |
| top_up_account | POST | index.php:3681 | UPDATE student_accounts + INSERT account_transactions + orders | 是 | FOR UPDATE | 🔴 金额操作 |

---

## 4. 目录与定价 (catalog-pricing)

### 课程

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| list_courses | GET | index.php:1305 | 否 | 否 | 无分页 |
| add_course | POST | index.php:1370 | INSERT courses | 否 | 无权限 |
| update_course | POST | index.php:1385 | UPDATE courses | 否 | 无权限 |
| delete_course | POST | index.php:1405 | DELETE courses | 否 | ⚠️ 无关联检查 |
| export_courses | GET | index.php:1411 | 否 | 否 | - |

### 学科/科目

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| list_subjects | GET | index.php:1895 | 否 | 否 | - |
| add_subject | POST | index.php:1917 | INSERT subjects | 否 | - |
| update_subject | POST | index.php:1930 | UPDATE subjects | 否 | - |
| delete_subject | POST | index.php:1947 | DELETE subjects | 否 | ⚠️ 无关联检查 |
| batch_delete_subjects | POST | index.php:1957 | DELETE N 条 subjects | 否 | ⚠️ 批量删除无确认 |
| get_campus_subjects | GET | index.php:1975 | 否 | 否 | - |

### 画具/教材

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| list_teaching_aids | GET | api/teaching_aids.php | 否 | 否 | 无分页 |
| get_teaching_aid | GET | api/teaching_aids.php | 否 | 否 | - |
| add_teaching_aid | POST | api/teaching_aids.php | INSERT teaching_aids + campuses | 否 | - |
| update_teaching_aid | POST | api/teaching_aids.php | UPDATE teaching_aids | 否 | - |
| delete_teaching_aid | POST | api/teaching_aids.php | DELETE teaching_aids | 否 | ⚠️ 无引用检查 |

### 画具销售

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| search_students_for_sale | GET | api/teaching_aids.php | 否 | 否 | - |
| list_available_teaching_aids | GET | api/teaching_aids.php | 否 | 否 | - |
| create_teaching_aid_sale | POST | api/teaching_aids.php | INSERT teaching_aid_sales + account_transactions | 是 | 🔴 金额校验 |
| list_teaching_aid_sales | GET | api/teaching_aids.php | 否 | 否 | 无分页 |

---

## 5. 教务与教学 (education-teaching)

### 班级

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| list_classes | GET | index.php:3769 | 否 | 否 | 无分页 |
| add_class | POST | index.php:3798 | INSERT classes | 否 | 无权限 |
| update_class | POST | index.php:3832 | UPDATE classes | 否 | 无权限 |
| delete_class | POST | index.php:3861 | DELETE classes | 否 | ⚠️ 无关联检查 |

### 排课

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| list_schedules | GET | index.php:3873 | 否 | 否 | - |
| get_schedule | GET | index.php:3887 | 否 | 否 | - |
| add_schedule | POST | index.php:3896 | INSERT schedules | 否 | - |
| update_schedule | POST | index.php:3930 | UPDATE schedules | 否 | - |
| delete_schedule | POST | index.php:3950 | DELETE schedules | 否 | - |
| cancel_schedule_session | POST | index.php:3958 | 特殊标记 | 否 | - |
| create_schedule_from_grid | POST | index.php:3977 | INSERT class + schedules | 是 | 从拖拽网格批量创建 |
| get_schedule_view | GET | index.php:4050 | 否 | 否 | 无分页 |

### 教室

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| list_classrooms | GET | index.php:4331 | 否 | 否 | - |
| add_classroom | POST | index.php:4344 | INSERT classrooms | 否 | - |
| update_classroom | POST | index.php:4365 | UPDATE classrooms | 否 | - |
| delete_classroom | POST | index.php:... | DELETE classrooms | 否 | - |

### 班级学员

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| list_class_students | GET | index.php | 否 | 否 | - |
| add_class_student | POST | index.php | INSERT class_students | 否 | 课时校验 |
| remove_class_student | POST | index.php | UPDATE class_students.left_at | 否 | - |
| get_available_students | GET | index.php | 否 | 否 | - |
| get_temp_student_candidates | GET | index.php | 否 | 否 | - |
| get_class_enrollable | GET | index.php | 否 | 否 | - |

### 考勤

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| get_class_attendance | GET | index.php | 否 | 否 | - |
| save_temp_attendance | POST | index.php | INSERT/UPDATE class_attendance | 否 | 临时学员 |
| save_class_attendance | POST | index.php | INSERT/UPDATE class_attendance + attendance_records + UPDATE orders.consumed_lessons | 是 | 🔴 三级扣课+课时归还 |
| list_attendance | GET | index.php:2648 | 否 | 否 | 无分页 |
| add_attendance | POST | index.php:2757 | INSERT class_attendance | 否 | ⚠️ 建议统一用 save_class_attendance |
| update_attendance | POST | index.php:2825 | UPDATE class_attendance | 否 | ⚠️ 同上 |
| delete_attendance | POST | index.php:2895 | DELETE class_attendance | 否 | ⚠️ 不会归还课时！ |
| list_absence_records | GET | index.php:2904 | 否 | 否 | 无分页 |
| list_attendance_sessions | GET | index.php | 否 | 否 | - |
| list_all_attendance | GET | index.php | 否 | 否 | 无分页；⚠️ JOIN 多表大数据可能超时 |

### 上课时段

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| list_class_periods | GET | api/settings.php | 否 | 否 | - |
| add_class_period | POST | api/settings.php | INSERT class_periods | 否 | - |
| update_class_period | POST | api/settings.php | UPDATE class_periods | 否 | - |
| delete_class_period | POST | api/settings.php | DELETE class_periods | 否 | - |

---

## 6. 学习与活动 (study-activity)

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| list_activities | GET | index.php:1454 | 否 | 否 | 无分页 |
| get_activity | GET | index.php:1488 | 否 | 否 | - |
| save_activity | POST | index.php:1524 | INSERT activities + activity_campuses + activity_subject_deductions | 否 | 🔴 多表写入无事务 |
| delete_activity | POST | index.php:1601 | DELETE activities | 否 | ⚠️ 无关联检查 |
| pay_activity_enroll | POST | index.php | INSERT orders（活动报名） | 否 | - |
| list_activities_for_enroll | GET | index.php | 否 | 否 | - |
| get_student_activities | GET | index.php | 否 | 否 | - |
| list_activity_attendance | GET | index.php | 否 | 否 | - |
| get_activity_deduction_rules | GET | index.php | 否 | 否 | - |
| save_activity_attendance | POST | index.php | 同 save_class_attendance | 是 | 复用课程考勤逻辑 |
| delete_activity_attendance | POST | index.php | DELETE class_attendance | 否 | - |
| list_activity_consumptions | GET | index.php | 否 | 否 | - |
| get_activity_enroll_detail | GET | index.php | 否 | 否 | - |

---

## 7. 营销与增长 (marketing-growth)

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| list_discount_plans | GET | api/discounts.php | 否 | 否 | - |
| get_discount_plan | GET | api/discounts.php | 否 | 否 | - |
| add_discount_plan | POST | api/discounts.php | INSERT discount_plans + 关联表 | 否 | - |
| update_discount_plan | POST | api/discounts.php | UPDATE discount_plans | 否 | - |
| delete_discount_plan | POST | api/discounts.php | DELETE discount_plans | 否 | ⚠️ 无报价单引用检查 |
| list_coupons | GET | api/discounts.php | 否 | 否 | - |
| get_coupon | GET | api/discounts.php | 否 | 否 | - |
| add_coupon | POST | api/discounts.php | INSERT coupons | 否 | - |
| update_coupon | POST | api/discounts.php | UPDATE coupons | 否 | - |
| delete_coupon | POST | api/discounts.php | DELETE coupons | 否 | ⚠️ 无引用检查 |
| list_coupon_records | GET | api/discounts.php | 否 | 否 | 无分页 |
| add_coupon_record | POST | api/discounts.php | INSERT coupon_records | 否 | 发放优惠券 |
| delete_coupon_record | POST | api/discounts.php | DELETE coupon_records | 否 | - |

---

## 8. 身份与访问 (identity-access)

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| get_employees | GET | index.php:1019 | 否 | 否 | 无分页 |
| add_employee | POST | index.php:1051 | INSERT employees | 否 | - |
| update_employee | POST | index.php:1087 | UPDATE employees | 否 | - |
| delete_employee | POST | index.php:1133 | DELETE employees | 否 | - |
| batch_import_employees | POST | index.php:1139 | INSERT N 条 employees | 否 | 数据量大 |
| export_employees | GET | index.php:1269 | 否 | 否 | - |
| search_employees | GET | index.php:1609 | 否 | 否 | - |
| get_teachers | GET | index.php:1990 | 否 | 否 | - |
| list_positions | GET | api/dictionaries.php | 否 | 否 | - |
| add_position | POST | api/dictionaries.php | INSERT positions | 否 | - |
| update_position | POST | api/dictionaries.php | UPDATE positions + employees | 是 | 级联更新员工 |
| delete_position | POST | api/dictionaries.php | DELETE positions | 否 | 有员工引用检查 |

---

## 9. 组织与校区 (organization-campus)

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| list_organizations | GET | api/organizations.php | 否 | 否 | - |
| add_organization | POST | api/organizations.php | INSERT organizations | 否 | - |
| update_organization | POST | api/organizations.php | UPDATE organizations | 否 | 循环引用检查 |
| delete_organization | POST | api/organizations.php | DELETE organizations | 否 | 子节点检查 |
| list_campuses | GET | api/settings.php | 否 | 否 | ⚠️ 重复：index.php 也有 list_campuses |

---

## 10. 数据分析 (data-analytics)

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| get_revenue_stats | GET | index.php | 否 | 否 | - |
| get_cashflow_stats | GET | index.php | 否 | 否 | - |

### 税率设置

| action | 方法 | 代码位置 | 写操作 | 事务 | 风险 |
|--------|------|---------|--------|------|------|
| list_tax_rates | GET | api/settings.php | 否 | 否 | - |
| save_tax_rate | POST | api/settings.php | INSERT/UPDATE tax_rates | 否 | 按校区+学科维度 |

---

## 风险综合分析

### 🔴 高风险接口

| action | 风险说明 |
|--------|---------|
| pay_enroll | 多表写入无统一事务；金额计算部分依赖前端 |
| save_class_attendance | 三级扣课 + 课时归还 + 多表写入，复杂状态联动 |
| approve_refund | 审批状态机 + 余额操作 + 订单状态联动 |
| approve_transfer | 课时转移 + 新订单创建 |
| top_up_account | 金额操作，但已有 FOR UPDATE 锁保护 |
| void_order | 已有事务+行锁，但影响活动人数回滚 |
| save_activity | 多表写入无事务 |

### ⚠️ 无分页接口（可能返回大量数据）

get_resources, get_appointments, get_communications, list_students, get_student_courses, list_orders, list_parent_orders, list_attendance, list_absence_records, list_all_attendance, list_courses, list_activities, get_employees, get_schedule_view, list_teaching_aids, list_teaching_aid_sales, list_coupon_records, list_refund_records

### ⚠️ 无权限校验

**所有接口均无权限校验。当前系统无登录机制。**

### ⚠️ 重复接口

| action | 重复说明 |
|--------|---------|
| add_appointment | book_trial 已存在预约功能 |
| list_campuses (index.php) + list_campuses (api/settings.php) | 功能重复 |
| add_attendance / update_attendance | 建议统一到 save_class_attendance |
| delete_attendance | ⚠️ 不会归还课时！必须通过 save_class_attendance 修改为缺勤 |

---

## REST API 建设议（仅供参考，不修改现有代码）

| 现有 action | 建议 REST 路径 | 方法 |
|-------------|---------------|------|
| list_resources | GET /api/v1/resources | GET |
| add_resource | POST /api/v1/resources | POST |
| update_resource | PUT /api/v1/resources/{id} | PUT |
| delete_resource | DELETE /api/v1/resources/{id} | DELETE |
| get_student | GET /api/v1/students/{id} | GET |
| list_students | GET /api/v1/students | GET (with pagination) |
| pay_enroll | POST /api/v1/enrollments | POST |
| list_orders | GET /api/v1/orders | GET (with pagination) |
| void_order | PUT /api/v1/orders/{id}/void | PUT |
| save_class_attendance | POST /api/v1/classes/{id}/attendance | POST |
| submit_refund | POST /api/v1/refunds | POST |
| approve_refund | PUT /api/v1/refunds/{id}/approve | PUT |
