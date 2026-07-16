# 01-domain-map.md — 领域映射

> 版本：v1.0  
> 日期：2026-07-16  
> 原则：按业务能力映射，不以现有代码结构/数据表为边界

---

## 目标领域列表

| 领域编号 | 领域名称 | 英文标识 |
|----------|----------|----------|
| D1 | 身份与访问控制 | identity-access |
| D2 | 组织与校区 | organization-campus |
| D3 | 课程与定价 | catalog-pricing |
| D4 | CRM 与招生 | crm-enrollment |
| D5 | 订单与权益 | order-entitlement |
| D6 | 钱包与账本 | wallet-ledger |
| D7 | 教务与排课 | education-teaching |
| D8 | 学习与活动 | study-activity |
| D9 | 营销与增长 | marketing-growth |
| D10 | 通知与工作流 | notification-workflow |
| D11 | 数据分析 | data-analytics |
| D12 | 开放平台 | open-platform |

---

## 现有功能 → 目标领域映射

### D1: identity-access — 身份与访问控制

| 现有功能 | 代码位置 | 数据表 | 领域归属理由 | 跨领域交互 |
|----------|----------|--------|------------|-----------|
| 员工管理（增删改查/导入导出） | index.php:?action=get_employees 系列 | employees | 身份主体管理 | D2(组织归属) |
| 学员建档 | index.php:?action=add_student | students | 学员身份注册 | D4(线索关联), D5(订单关联) |
| 学员查询 | index.php:?action=get_students | students | 身份查询 | |
| 学号生成 | index.php:?action=add_student | students | 身份标识 | |
| 家庭关系 | 代码中存在 family_member 逻辑 | -- | 身份关联 | D5(合并订单) |

**归属理由**: 以上功能直接操作身份主体（员工/学员）的创建、查询与标识，属于 identity-access 领域的核心职责。学员虽然被 students 表承载，但 student_type 的判定逻辑（小课包→常规）是身份状态管理。

---

### D2: organization-campus — 组织与校区

| 现有功能 | 代码位置 | 数据表 | 领域归属理由 | 跨领域交互 |
|----------|----------|--------|------------|-----------|
| 组织管理（树形结构） | index.php:?action=list_organizations | organizations | 组织架构管理 | |
| 岗位字典 | index.php:?action=list_positions | positions | 组织内角色定义 | D1(员工绑定) |
| 渠道字典 | api/dictionaries.php:list_channels | channels | 招生渠道配置 | D4(线索来源) |
| 意向等级字典 | api/dictionaries.php:list_intention_levels | intention_levels | 招生评级配置 | D4(线索评估) |
| 基础类型字典 | api/dictionaries.php:list_basic_types | basic_types | 通用枚举配置 | D7(班级类型) |

**归属理由**: 以上功能均为组织/校区维度的配置与结构管理，包含树形组织架构、岗位定义、以及招生相关的字典配置。

---

### D3: catalog-pricing — 课程与定价

| 现有功能 | 代码位置 | 数据表 | 领域归属理由 | 跨领域交互 |
|----------|----------|--------|------------|-----------|
| 学科管理 | index.php:?action=add_subject 系列 | subjects | 学科目录管理 | |
| 课程管理 | index.php:?action=add_course 系列 | courses | 课程目录管理（含小课包/幼儿标记） | D7(班级关联) |
| 报价方案 | api/orders.php:add_price_plan / get_price_plans | price_plans | 价格方案配置 | D9(优惠方案关联) |
| 报价单元 | api/orders.php:add_price_item | price_items | 定价明细管理 | D9(优惠方案/优惠券/画具关联) |
| 教材画具 | api/teaching_aids.php:add_teaching_aid 系列 | teaching_aids | 商品/产品目录 | D5(销售订单) |
| 校区-学科-老师 | index.php:save_subject_teacher | student_subject_teacher | 资源分配 | D7(排课) |
| 上课时段 | index.php:?action=get_class_periods | class_periods | 时间资源目录 | D7(排课) |

**归属理由**: 以上功能定义和管理系统中最核心的商业资源目录（学科/课程/报价方案/教材/时段），是教务和招生运营的基础配置。

---

### D4: crm-enrollment — CRM 与招生

| 现有功能 | 代码位置 | 数据表 | 领域归属理由 | 跨领域交互 |
|----------|----------|--------|------------|-----------|
| 线索录入 | index.php:?action=add_resource | resources | 线索创建 | D2(渠道来源) |
| 线索分配 | index.php:?action=assign_resource | resources | 线索归属管理 | D1(员工) |
| 公海领取 | index.php:?action=claim_resource | resources | 线索池管理 | |
| 线索转化 | index.php:convert-to-student 逻辑 | resources→students | 线索→学员转化 | D1(学员), D5(订单) |
| 沟通记录 | index.php:?action=add_communication | communication_records | 跟进历史 | |
| 试听预约 | index.php:?action=add_appointment / cancel_appointment | appointments | 试听预约管理 | D7(班级/课次), D3(课程) |
| 试听到场/转化 | class_attendance + effective_status 逻辑 | appointments | 试听结果与转化 | D7(考勤), D5(新报) |
| 批量导入线索 | index.php:?action=import_resources | resources | 批量录入 | |
| 资源导出 | index.php:?action=export_resources | resources | 数据导出 | |

**归属理由**: 以上功能覆盖从线索获取、跟进管理、分配到试听预约/到场/转化的完整招生漏斗，是 CRM 的核心业务流程。

---

### D5: order-entitlement — 订单与权益

| 现有功能 | 代码位置 | 数据表 | 领域归属理由 | 跨领域交互 |
|----------|----------|--------|------------|-----------|
| 新报/续费/扩科下单 | index.php:?action=save_order | orders | 核心订单创建 | D3(课程/报价), D6(账户支付) |
| 小课包下单 | index.php:?action=save_order (small_package) | orders | 小额体验课订单 | |
| 赠课订单 | index.php:?action=gift_lessons | orders | 运营赠送 | |
| 父子订单 | index.php:?action=save_parent_order | parent_orders | 多子订单合并支付 | D6(支付分摊) |
| 活动报名订单 | index.php:?action=enroll_activity | orders(activity字段) | 活动报名付费 | D8(活动) |
| 画具销售订单 | index.php:?action=sell_teaching_aid | teaching_aid_sales | 商品销售 | D3(画具), D6(支付) |
| 储值订单 | index.php:?action=save_order (deposit) | orders | 充值记录 | D6(账户) |
| 订单支付 | index.php:?action=pay_order | orders | 支付状态变更 | D6(资金流水) |
| 订单作废 | index.php:?action=void_order | orders | 权益取消+课时还原 | D6(退款), D7(课时) |
| 转校 | index.php:?action=transfer_campus_order | transfer_records | 校区间权益转移 | D6(金额转移) |
| 退费 | index.php:?action=create_refund | refund_records | 课程退费 | D6(退款), D7(课时) |
| 订单查询 | index.php:?action=get_orders | orders | 订单列表 | |

**归属理由**: 以上功能均为订单生命周期管理（创建 → 支付 → 履约/退费/作废/转校），是连接前端招生与后端教务的核心枢纽。orders 表承载了多种业务语义（课程订单/活动/画具/储值），但均属于订单与权益领域。

---

### D6: wallet-ledger — 钱包与账本

| 现有功能 | 代码位置 | 数据表 | 领域归属理由 | 跨领域交互 |
|----------|----------|--------|------------|-----------|
| 账户充值 | index.php:?action=deposit_account | student_accounts, account_transactions | 储值充值 | |
| 余额支付 | index.php:?action=pay_order (account_amount) | student_accounts | 消费扣款 | D5(订单) |
| 账户退款 | index.php:?action=refund_account | student_accounts, refund_records | 余额退款 | |
| 账户余额查询 | index.php:?action=get_student_account | student_accounts | 账户查询 | |
| 账户流水 | account_transactions 查询 | account_transactions | 交易历史 | |
| 退费审批 | index.php:?action=approve_refund | refund_records | 退费审批流程 | D5(订单) |

**归属理由**: 以上功能涉及资金管理（充值/消费/退款/查询/审批），构成完整的钱包与账本领域。FOR UPDATE 行锁保障余额操作并发安全。

---

### D7: education-teaching — 教务与排课

| 现有功能 | 代码位置 | 数据表 | 领域归属理由 | 跨领域交互 |
|----------|----------|--------|------------|-----------|
| 班级管理 | index.php:?action=add_class 系列 | classes | 班级CRUD | D3(课程) |
| 排课 | index.php:?action=add_schedule | schedules | 排课规则管理 | D3(时段), D7(教室) |
| 教室管理 | index.php:?action=add_classroom | classrooms | 教学场地管理 | |
| 分班 | index.php:?action=add_class_student | class_students | 学员入班 | D1(学员) |
| 出班 | class_students.left_at | class_students | 学员出班 | |
| 临时学员管理 | class_attendance.is_temporary | class_attendance | 试听/临时 | D4(试听) |
| 考勤 | index.php:?action=save_attendance | class_attendance | 出勤/缺勤/请假 | D5(扣课) |
| 扣课 | class_attendance.deduction_json | class_attendance | 课消管理 | D6(课耗金额) |
| 考勤冲正 | index.php:?action=reverse_attendance | class_attendance | 考勤修正 | |
| 考勤修改 | index.php:?action=update_attendance | class_attendance | 考勤调整 | D5(退还课时) |
| 考勤记录 | attendance_records | attendance_records | 考勤历史 | |
| 缺勤记录 | absence_records | absence_records | 缺勤快照 | |

**归属理由**: 以上功能构成完整的教务运营闭环：班级→排课→分班→考勤→扣课，是教育培训机构的核心教学管理能力。

---

### D8: study-activity — 学习与活动

| 现有功能 | 代码位置 | 数据表 | 领域归属理由 | 跨领域交互 |
|----------|----------|--------|------------|-----------|
| 活动管理 | index.php:?action=add_activity | activities | 活动CRUD | |
| 活动校区容量 | index.php:?action=add_activity | activity_campuses | 校区报名限制 | |
| 活动扣课规则 | index.php:?action=add_activity | activity_subject_deductions | 扣课配置 | D7(课消) |
| 活动报名 | index.php:?action=enroll_activity | orders(activity字段) | 活动报名 | D5(订单), D6(支付) |
| 活动考勤 | class_attendance(activity字段) | class_attendance | 活动出勤 | D7(扣课) |
| 活动报名统计 | activity_enrollment_counts | activity_enrollment_counts | 人数缓存 | |

**归属理由**: 活动管理与课程教务有明显区分——活动有独立的报名流程、收费模式（仅收费/收费+扣课时/仅扣课时）、容量限制，自成体系。

---

### D9: marketing-growth — 营销与增长

| 现有功能 | 代码位置 | 数据表 | 领域归属理由 | 跨领域交互 |
|----------|----------|--------|------------|-----------|
| 优惠方案 | api/discounts.php:add_discount_plan | discount_plans | 营销方案配置 | D3(学科), D2(校区) |
| 方案校区/学科 | api/discounts.php | discount_plan_campuses / discount_plan_subjects | 适用范围限定 | |
| 优惠券 | api/discounts.php:add_coupon | coupons | 优惠券模板 | D3(学科), D2(校区) |
| 券校区/学科 | api/discounts.php | coupon_campuses / coupon_subjects | 适用范围限定 | |
| 优惠券发放 | api/discounts.php:issue_coupon | coupon_records | 发放核销记录 | D1(学员) |
| 商品券 | product_coupon（price_items） | 同上 | 画具优惠券 | D3(画具) |

**归属理由**: 优惠方案和优惠券是独立的营销增长能力，通过方案/券→报价单元→订单的链路与前链交互。

---

### D10: notification-workflow — 通知与工作流

| 现有功能 | 代码位置 | 领域归属理由 | 跨领域交互 |
|----------|----------|------------|-----------|
| 退费审批流 | index.php:?action=approve_refund | 二级审批工作流 | D6(退费) |
| 转校审批流 | index.php:?action=approve_transfer | 审批工作流 | D5(转校) |
| 通知 | 代码中无独立通知服务 | 待开发 | D4/D5/D7(多处触发) |

**归属理由**: 通知与工作流当前仅有审批逻辑散落在业务处理中，尚未独立成模块。退费和转校审批作为工作流起点。

---

### D11: data-analytics — 数据分析

| 现有功能 | 代码位置 | 领域归属理由 | 跨领域交互 |
|----------|----------|------------|-----------|
| 现金流统计 | index.php:?action=get_cashflow_stats | 营收统计 | D5(订单), D6(资金) |
| 员工绩效 | index.php:?action=get_employee_performance | 业绩统计 | D4(招生), D5(订单) |
| 税率设置 | api/settings.php:list_tax_rates | 税后课耗计算 | D6(金额) |
| 学员分析 | index.php:?action=get_student_analysis | 学员维度分析 | D5(订单), D7(考勤) |

**归属理由**: 数据分析模块提供营收/绩效/学员多维度的统计和报表能力。

---

### D12: open-platform — 开放平台

| 现有功能 | 代码位置 | 领域归属理由 |
|----------|----------|------------|
| — | 无 | 待开发 |

**归属理由**: 当前系统无开放平台相关功能（API Key / Webhook / 第三方对接）。

---

## 跨领域交互关系图

```
D1(identity-access) ─── D2(organization-campus)
 │                           │
 ├── D4(crm-enrollment) ─────┤
 │       │                   │
 │       ├── D3(catalog-pricing) ─── D9(marketing-growth)
 │       │       │                       │
 │       │       └── D7(education-teaching) ─── D8(study-activity)
 │       │               │
 │       └── D5(order-entitlement) ─── D6(wallet-ledger)
 │               │
 └───────────────┴── D10(notification-workflow)
                         │
                         └── D11(data-analytics)
```

## 领域边界的反模式与纠正建议

| 反模式 | 说明 | 建议目标 |
|--------|------|----------|
| orders 表承载 6 种业务语义 | 课程/活动/画具/储值/赠课混在一张表 | 拆分为 D5/D6/D8 独立表 |
| students 表存招生信息 | source / follow_status 属于 CRM | 拆到 D4 领域表 |
| class_attendance 混合班级+活动 | 活动考勤不属于教务 | 拆到 D8 |
| 审批流散落 | 退费/转校审批直接内联 | 独立 D10 |
| 退款共享 refund_records | 课程/账户/画具退款同表 | 按 project 区分或拆表 |
