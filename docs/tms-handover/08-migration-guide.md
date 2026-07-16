# 08-migration-guide.md — 迁移指南

> 版本：v1.0
> 日期：2026-07-16
> 目标：MySQL 5.7 (utf8mb4, PHP PDO, 无 ORM) → PostgreSQL (DDD + JPA/MyBatis)

---

## 1. 旧表到目标领域映射

| 旧表 (MySQL) | 行数级别 | 目标领域 | 新表建议 | 映射复杂度 |
|-------------|---------|----------|---------|-----------|
| resources | 主表 | crm-enrollment | crm_leads | 中 |
| appointments | 主表 | crm-enrollment | crm_trial_appointments | 中 |
| communication_records | 主表 | crm-enrollment | crm_communication_logs | 低 |
| channels | 字典 | organization-campus | dict_channels | 低 |
| intention_levels | 字典 | organization-campus | dict_intention_levels | 低 |
| basic_types | 字典 | organization-campus | dict_basic_types | 低 |
| employees | 主表 | identity-access | iam_users | 中 |
| organizations | 主表 | organization-campus | org_departments | 中 |
| positions | 字典 | organization-campus | dict_positions | 低 |
| students | 主表 | identity-access | iam_students | 高 |
| subjects | 主表 | catalog-pricing | catalog_subjects | 低 |
| courses | 主表 | catalog-pricing | catalog_courses | 中 |
| price_plans | 主表 | catalog-pricing | catalog_price_plans | 中 |
| price_items | 主表 | catalog-pricing | catalog_price_items | 中 |
| teaching_aids | 主表 | catalog-pricing | catalog_products | 低 |
| teaching_aid_campuses | 关联 | catalog-pricing | catalog_product_campuses | 低 |
| class_periods | 字典 | catalog-pricing | dict_class_periods | 低 |
| tax_rates | 配置 | catalog-pricing | org_tax_configs | 低 |
| student_subject_teacher | 关联 | education-teaching | edu_student_assignments | 低 |
| orders | 主表 | order-entitlement | ord_orders | 极高 |
| parent_orders | 主表 | order-entitlement | ord_parent_orders | 高 |
| student_accounts | 主表 | wallet-ledger | wlt_accounts | 中 |
| account_transactions | 主表 | wallet-ledger | wlt_transactions | 中 |
| refund_records | 主表 | wallet-ledger + notification-workflow | wlt_refunds | 中 |
| transfer_records | 主表 | order-entitlement + education-teaching | ord_transfers | 中 |
| classes | 主表 | education-teaching | edu_classes | 中 |
| class_students | 关联 | education-teaching | edu_class_enrollments | 低 |
| schedules | 主表 | education-teaching | edu_schedules | 中 |
| classrooms | 主表 | education-teaching | edu_classrooms | 低 |
| class_attendance | 主表 | education-teaching | edu_attendance_records | 极高 |
| attendance_records | 历史/冗余 | education-teaching | 合并到 edu_attendance_records | 中 |
| absence_records | 快照 | education-teaching | 合并到 edu_attendance_records | 低 |
| activities | 主表 | study-activity | act_activities | 中 |
| activity_campuses | 关联 | study-activity | act_activity_campuses | 低 |
| activity_subject_deductions | 关联 | study-activity | act_activity_deduction_rules | 低 |
| activity_enrollment_counts | 缓存 | study-activity | 实时计算或物化视图 | 低 |
| discount_plans | 主表 | marketing-growth | mkt_discount_plans | 中 |
| discount_plan_campuses | 关联 | marketing-growth | mkt_discount_plan_campuses | 低 |
| discount_plan_subjects | 关联 | marketing-growth | mkt_discount_plan_subjects | 低 |
| coupons | 主表 | marketing-growth | mkt_coupons | 中 |
| coupon_campuses | 关联 | marketing-growth | mkt_coupon_campuses | 低 |
| coupon_subjects | 关联 | marketing-growth | mkt_coupon_subjects | 低 |
| coupon_records | 主表 | marketing-growth | mkt_coupon_records | 中 |
| teaching_aid_sales | 主表 | order-entitlement | ord_product_sales | 低 |

---

## 2. 主键映射方式

### 2.1 建议方案：UUID 为主，保留旧 ID 为映射列

| 决策 | 理由 |
|------|------|
| **新表主键全部使用 UUID v7** | 分布式友好、避免自增 ID 冲突、支持多库合并 |
| **保留旧自增 ID 为 `legacy_id` 列** | 便于数据核对、回滚、旧系统引用追踪 |
| **唯一约束使用业务标识** | 学号/订单号/手机号使用业务自然键做 UK |

### 2.2 具体实施

```sql
-- 示例：students 新表
CREATE TABLE iam_students (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    legacy_id   INTEGER,                          -- 旧 students.id
    student_no  VARCHAR(50) NOT NULL UNIQUE,       -- 学号（唯一约束）
    phone       VARCHAR(20) UNIQUE,                -- 手机号唯一约束
    ...
);

-- 迁移时记录映射
INSERT INTO migration_id_map (legacy_table, legacy_id, new_id)
VALUES ('students', 12345, '0190e4a5-...');
```

### 2.3 外键迁移

- 旧外键（如 `orders.student_id`）→ 迁移时通过 `migration_id_map` 查找对应 UUID
- 迁移脚本分两阶段：
  - 阶段 1：INSERT 所有主表记录 + 写入映射表
  - 阶段 2：UPDATE 所有外键列为 UUID

---

## 3. 手机号、学号、订单号的处理

### 3.1 手机号

| 项目 | 旧系统 | 新系统 |
|------|--------|--------|
| 存储 | VARCHAR(500)，明文 | VARCHAR(20)，加密存储（AES-256-GCM） |
| 唯一约束 | students.phone 有 UNIQUE 索引 | 哈希值 UK `phone_hash_idx`（SHA-256） |
| 查询 | SELECT ... WHERE phone='...' | 先 hash 再查 `phone_hash` |
| 脱敏展示 | 无脱敏 | 展示时 `138****1234`，API 响应脱敏 |

**迁移注意事项**：
- 迁移时一次性加密所有手机号，生成 hash
- 迁移后不再存储明文。如果旧系统还有未迁移数据需要按手机号关联，先完成所有关联再加密

### 3.2 学号 (student_no)

| 项目 | 旧系统 | 新系统 |
|------|--------|--------|
| 生成规则 | 代码生成（index.php: add_student） | 保持生成规则或改用雪花 ID |
| 唯一性 | UK `idx_students_student_no` | UK 约束 |
| 迁移 | 直接迁移 | 直接迁移 |

**证据**：students.student_no VARCHAR(500)，有独立唯一索引 idx_students_student_no

### 3.3 订单号 (order_no)

| 项目 | 旧系统 | 新系统 |
|------|--------|--------|
| 生成规则 | 代码生成 | 保持规则或采用 `ORD-{yyyyMMdd}-{seq}` |
| 唯一性 | 无显式索引声明（代码层面保证） | UK 约束 |
| 迁移 | 直接迁移 | 直接迁移 |

---

## 4. 历史状态标准化方案

> 旧系统 ALL 状态字段使用 VARCHAR(500)，中文枚举值，无 ENUM/CHECK 约束。

### 4.1 通用标准化规则

| 旧格式 | 新格式 |
|--------|--------|
| VARCHAR(500)，中文文本 | VARCHAR(50)，英文 code 为主，中文为 display_name |
| 无约束 | CHECK 约束 + 字典表 |
| 代码中 if-else 字符串比较 | 枚举类 / Java Enum |
| 可随意写入任意字符串 | 严格校验 |

### 4.2 各表状态映射

#### resources (线索)

| 旧值 (中文) | 新 code | 说明 |
|------------|---------|------|
| 待跟进 | PENDING_FOLLOW | 待跟进 |
| 已联系 | CONTACTED | 已联系 |
| 无意向 | NOT_INTERESTED | 无意向 |
| 试听预约 | TRIAL_BOOKED | 已预约试听 |
| 已报名 | ENROLLED | 已转化为学员 |

`pool_type`:
| 旧值 | 新 code |
|------|---------|
| 我的资源 | MY_RESOURCE |
| 公海 | PUBLIC_POOL |

`converted`:
| 旧值 | 新 code |
|------|---------|
| 未转化 | NOT_CONVERTED |
| 已转化 | CONVERTED |

#### appointments (预约)

| 旧值 | 新 code |
|------|---------|
| 已预约待试听 | BOOKED |
| 已试听 | ATTENDED |
| 缺勤 | ABSENT |
| 已取消 | CANCELLED |

#### students (学员类型)

| 旧值 | 新 code |
|------|---------|
| 小课包 | SMALL_PACKAGE |
| 常规 | REGULAR |

#### orders (订单)

`order_type`:
| 旧值 | 新 code |
|------|---------|
| 新报 | NEW_ENROLL |
| 续费 | RENEWAL |
| 扩科 | EXPANSION |
| 小课包 | SMALL_PACKAGE |
| 赠送 | GIFT |
| 活动报名 | ACTIVITY_ENROLL |
| 储值 | TOP_UP |
| 画具销售 | PRODUCT_SALE |

`status`:
| 旧值 | 新 code |
|------|---------|
| 已报名 | ENROLLED |
| 已取消 | CANCELLED |
| 已退费 | REFUNDED |

`is_voided`:
| 旧值 | 新 code |
|------|---------|
| 是 | VOIDED |
| 否 | ACTIVE |

#### refund_records (退费)

| 旧值 (status) | 新 code |
|--------------|---------|
| 待审批 | PENDING_APPROVAL |
| 一级审批通过 | APPROVED_L1 |
| 二级审批通过 | APPROVED_L2 |
| 已退费 | REFUNDED |
| 审批驳回 | REJECTED |

#### class_attendance (考勤)

| 旧值 (status) | 新 code |
|--------------|---------|
| 出勤 | PRESENT |
| 缺勤 | ABSENT |
| 请假 | LEAVE |
| 已取消 | CANCELLED |

#### activities (活动)

`adult_fee_mode` / `student_fee_mode`:
| 旧值 | 新 code |
|------|---------|
| —（空或 fee_only） | FEE_ONLY |
| 收费+扣课时 | FEE_DEDUCT |
| 仅扣课时 | DEDUCT_ONLY |

#### coupons / coupon_records

`usage_status`:
| 旧值 | 新 code |
|------|---------|
| 未使用 | UNUSED |
| 已使用 | USED |
| 已过期 | EXPIRED |

---

## 5. 金额字段转换

### 5.1 当前存储格式

| 字段类型 | 示例表 | 问题 |
|----------|--------|------|
| REAL | orders.actual_price / orders.paid_amount | 浮点精度丢失风险，不适合金额 |
| DECIMAL(10,2) | 部分表 | 精度可控，但存在混用 |
| 元为单位 | 全部 | 明确 |

### 5.2 建议方案

| 项目 | 建议 |
|------|------|
| 存储单位 | **分（整数）**。所有金额以分为单位存储，BIGINT |
| 精度 | 无精度问题（整数运算） |
| API 展示 | 展示时除以 100 转为元（两位小数） |
| 迁移 | `new_amount_cents = CAST(old_amount * 100 AS BIGINT)` |

**分 vs 元对比**：

| 对比维度 | 元（DECIMAL） | 分（BIGINT） |
|----------|-------------|-------------|
| 精度 | DECIMAL(12,2) 需指定 | 天然精确 |
| 运算 | 可能有舍入 | 无精度问题 |
| 存储 | ~6 字节 | 8 字节 |
| 跨语言兼容 | 需要 BigDecimal | 普通 Long |

### 5.3 迁移 SQL 示例

```sql
-- 迁移 orders.actual_price
INSERT INTO ord_orders (..., actual_amount_cents, ...)
SELECT ..., CAST(ROUND(actual_price * 100) AS BIGINT), ...
FROM orders;
```

---

## 6. 中文枚举转换为标准化 CODE — 补充方案

除上述状态字段外，以下特殊字段需要额外处理：

### 6.1 数据库存储 vs 代码逻辑

旧系统中部分状态并非存储值，而是通过 **SQL JOIN 动态计算**（effective_status 模式）：

| 字段 | 当前方式 | 迁移后建议 |
|------|----------|-----------|
| appointments.effective_status | `LEFT JOIN class_attendance` 动态计算 | 存储为 computed column 或物化视图，或在查询时显式计算 |

**证据**：index.php: get_appointments 分支，effective_status =
```
CASE WHEN apt.status='已取消' THEN '已取消'
     WHEN ca.status='出勤' THEN '已试听'
     WHEN ca.status='缺勤' THEN '缺勤'
     ELSE '已预约待试听' END
```

### 6.2 课程/学科字段标准化

courses 表中 `subject` 字段（旧）与 `subject_level1`/`subject_level2`（新）共存：

| 策略 | 操作 |
|------|------|
| 迁移时以 subject_level1 / subject_level2 为准 | subject 字段废弃 |
| 无二级学科的 | subject_level2 设为 NULL |
| 旧数据 subject 字段值为复合字符串（如"美术-国画"） | 自动拆分为 level1="美术", level2="国画" |

---

## 7. deduction_json 转换方案

### 7.1 当前结构

`class_attendance.deduction_json` 存储 JSON 数组，每条记录：

```json
[
  {
    "order_id": 123,
    "order_type": "新报",
    "deducted_lessons": 2,
    "is_paid": true,
    "is_gifted": false,
    "created_at": "2026-01-15"
  }
]
```

**证据**：index.php: save_attendance / update_attendance 分支

### 7.2 目标方案：课时消耗分配流水表

```sql
CREATE TABLE edu_lesson_deduction_logs (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    attendance_id   UUID NOT NULL REFERENCES edu_attendance_records(id),
    order_id        UUID NOT NULL REFERENCES ord_orders(id),
    deducted_lessons INTEGER NOT NULL,
    deduction_type  VARCHAR(20) NOT NULL CHECK (deduction_type IN ('PAID','GIFTED')),
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_deduction_attendance ON edu_lesson_deduction_logs(attendance_id);
CREATE INDEX idx_deduction_order ON edu_lesson_deduction_logs(order_id);
```

### 7.3 迁移逻辑

```python
for row in old_class_attendance:
    if row.deduction_json:
        deductions = json.loads(row.deduction_json)
        for d in deductions:
            INSERT INTO edu_lesson_deduction_logs (
                attendance_id,
                order_id,
                deducted_lessons,
                deduction_type
            ) VALUES (
                new_attendance_id,
                lookup_order_uuid(d['order_id']),
                d['deducted_lessons'],
                'GIFTED' if d['is_gifted'] else 'PAID'
            )
```

### 7.4 校验

迁移完成后通过 SQL 对比：

```sql
-- 新表汇总扣课 vs 旧表 consumed_lessons
SELECT
    o.legacy_id,
    o.consumed_lessons AS old_consumed,
    COALESCE(SUM(ldl.deducted_lessons), 0) AS new_consumed
FROM orders o
LEFT JOIN edu_lesson_deduction_logs ldl ON ldl.order_id = new_order_uuid
GROUP BY o.legacy_id, o.consumed_lessons
HAVING o.consumed_lessons != COALESCE(SUM(ldl.deducted_lessons), 0);
```

---

## 8. 赠课订单转换方案

### 8.1 当前存储

赠课作为独立 orders 记录（order_type='赠送', actual_price=0, lesson_count=gifted_lessons）

**证据**：index.php: save_order + price_items.gifted_lessons

### 8.2 目标方案

| 方案 | 描述 |
|------|------|
| 赠课作为 **订单权益项 (Order Entitlement Item)** | 而非独立订单 |
| ord_orders 表增加 `is_gift` 布尔字段 | 标记为赠课类型 |
| 或创建独立表 `ord_gift_entitlements` | 关联主订单 |

**推荐方案**：保持独立订单 + `parent_order_no` 关联，以确保退费/扣课时可区分。

```sql
-- 新表
ALTER TABLE ord_orders ADD COLUMN is_gift BOOLEAN DEFAULT FALSE;
ALTER TABLE ord_orders ADD COLUMN gift_source_order_id UUID REFERENCES ord_orders(id);
-- 赠送订单 is_gift=true, gift_source_order_id=主订单ID
```

### 8.3 迁移

| 旧数据 | 新数据 |
|--------|--------|
| orders(order_type='赠送', actual_price=0) | ord_orders(is_gift=true, gift_source_order_id=主订单UUID) |
| 赠课课时 = gifted_lessons | lesson_count = gifted_lessons |

---

## 9. 父子订单转换方案

### 9.1 当前结构

| 表 | 用途 |
|-----|------|
| parent_orders | 父订单主表（total_lessons, total_amount, paid_amount, parent_order_no） |
| orders.parent_order_no | 子订单关联父订单号 |

**证据**：parent_orders 表 + orders.parent_order_no 字段

### 9.2 目标方案

```sql
CREATE TABLE ord_parent_orders (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    parent_order_no VARCHAR(50) NOT NULL UNIQUE,
    total_amount_cents BIGINT NOT NULL,
    paid_amount_cents  BIGINT NOT NULL DEFAULT 0,
    status          VARCHAR(20) NOT NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- 子订单关联
ALTER TABLE ord_orders ADD COLUMN parent_order_id UUID REFERENCES ord_parent_orders(id);
```

### 9.3 迁移

| 旧字段 | 新字段 |
|--------|--------|
| parent_orders.id | ord_parent_orders.legacy_id |
| parent_orders.parent_order_no | ord_parent_orders.parent_order_no |
| orders.parent_order_no | ord_orders.parent_order_id (通过 parent_order_no 查找 UUID) |

---

## 10. 账户余额和流水核对方案

### 10.1 当前结构

| 表 | 用途 |
|-----|------|
| student_accounts | 学员账户余额（balance） |
| account_transactions | 流水明细（充值/消费/退款） |

### 10.2 目标方案

```sql
CREATE TABLE wlt_accounts (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    student_id      UUID NOT NULL REFERENCES iam_students(id),
    balance_cents   BIGINT NOT NULL DEFAULT 0 CHECK (balance_cents >= 0),
    frozen_cents    BIGINT NOT NULL DEFAULT 0,       -- 冻结金额（支付中）
    version         INTEGER NOT NULL DEFAULT 0,       -- 乐观锁版本号
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE wlt_transactions (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    account_id      UUID NOT NULL REFERENCES wlt_accounts(id),
    txn_type        VARCHAR(20) NOT NULL CHECK (txn_type IN ('TOP_UP','CONSUME','REFUND','ADJUST')),
    amount_cents    BIGINT NOT NULL,
    balance_after   BIGINT NOT NULL,                  -- 交易后余额（快照）
    reference_type  VARCHAR(50),                      -- 关联业务类型
    reference_id    UUID,                             -- 关联业务 ID
    remark          TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_txns_account ON wlt_transactions(account_id, created_at DESC);
```

### 10.3 迁移核对

```sql
-- 核对：旧表余额 vs 新表余额
SELECT
    sa.legacy_id,
    sa.balance AS old_balance,
    wa.balance_cents / 100.0 AS new_balance
FROM student_accounts sa
JOIN wlt_accounts wa ON wa.legacy_id = sa.id
WHERE ABS(sa.balance - wa.balance_cents / 100.0) > 0.01;

-- 核对：流水总额
SELECT
    sa.legacy_id,
    COALESCE(SUM(CASE WHEN at.txn_type = '入账' THEN at.amount ELSE -at.amount END), 0) AS old_net,
    COALESCE(SUM(CASE WHEN wt.txn_type IN ('TOP_UP','REFUND') THEN wt.amount_cents ELSE -wt.amount_cents END), 0) / 100.0 AS new_net
FROM student_accounts sa
LEFT JOIN account_transactions at ON at.account_id = sa.id
LEFT JOIN wlt_transactions wt ON wt.account_id = wa.id
...
```

---

## 11. 退款审批历史转换方案

### 11.1 当前表

refund_records:
- status (VARCHAR): 待审批 / 一级审批通过 / 二级审批通过 / 已退费 / 审批驳回
- approval_stage: 审批阶段
- reject_reason: 驳回原因
- approver1 / approver2: 审批人

### 11.2 目标方案

```sql
CREATE TABLE wlt_refunds (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id        UUID REFERENCES ord_orders(id),
    total_amount_cents BIGINT NOT NULL,
    total_lessons    INTEGER NOT NULL,
    consumed_lessons INTEGER NOT NULL,
    remaining_lessons INTEGER NOT NULL,
    actual_refund_cents BIGINT NOT NULL,
    custom_deduction_cents BIGINT DEFAULT 0,
    status          VARCHAR(20) NOT NULL,  -- PENDING / APPROVED_L1 / APPROVED_L2 / COMPLETED / REJECTED
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE wlt_refund_approvals (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    refund_id       UUID NOT NULL REFERENCES wlt_refunds(id),
    approval_level  INTEGER NOT NULL CHECK (approval_level IN (1,2)),
    approver_id     UUID REFERENCES iam_users(id),
    decision        VARCHAR(20) NOT NULL,  -- APPROVED / REJECTED
    reject_reason   TEXT,
    decided_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### 11.3 迁移

| 旧 refund_records | 新 wlt_refunds + wlt_refund_approvals |
|-------------------|--------------------------------------|
| 1 条记录含 approver1/approver2 | 1 条 refund + 最多 2 条 approval |
| status='审批驳回' + approver1 有值 | approval(level=1, decision=REJECTED) |
| status='已退费' | refund.status=COMPLETED + 2 条 approval(APPROVED) |

---

## 12. 转校记录转换方案

### 12.1 当前表

transfer_records（迁移版本 20260714_001_transfer_records）：

| 字段 | 说明 |
|------|------|
| order_id | 原订单 ID |
| student_id | 学员 ID |
| from_campus | 转出校区 |
| to_campus | 转入校区 |
| transfer_lessons | 转移课时数 |
| transfer_amount | 转移金额 |
| status | 审批状态 |
| new_order_id | 新订单 ID |

### 12.2 目标方案

```sql
CREATE TABLE ord_transfers (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    source_order_id     UUID NOT NULL REFERENCES ord_orders(id),
    target_order_id     UUID REFERENCES ord_orders(id),  -- 新创建的转校订单
    student_id          UUID NOT NULL REFERENCES iam_students(id),
    from_campus_id      UUID NOT NULL REFERENCES org_departments(id),
    to_campus_id        UUID NOT NULL REFERENCES org_departments(id),
    transfer_lessons    INTEGER NOT NULL,
    transfer_amount_cents BIGINT NOT NULL,
    status              VARCHAR(20) NOT NULL,  -- PENDING / APPROVED / COMPLETED / REJECTED
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### 12.3 迁移

| 旧 transfer_records | 新 ord_transfers |
|---------------------|-----------------|
| 直接迁移 + UUID 映射 | from_campus → from_campus_id, to_campus → to_campus_id |
| new_order_id 查找新 UUID | target_order_id |

---

## 13. 新旧系统数据核对指标

### 13.1 核心核对指标

| # | 指标 | 核对方式 | 允许偏差 |
|---|------|----------|----------|
| 1 | 学员总数 | `COUNT(*) FROM students` = `COUNT(*) FROM iam_students` | 0 |
| 2 | 订单总数 | `COUNT(*) FROM orders` = `COUNT(*) FROM ord_orders` | 0 |
| 3 | 订单总金额（元） | `SUM(actual_price)` = `SUM(actual_amount_cents)/100` | ±0.01/条（浮点舍入） |
| 4 | 已消耗课时总数 | `SUM(consumed_lessons)` = `SUM(consumed_lessons)` | 0 |
| 5 | 账户余额总和 | `SUM(balance)` = `SUM(balance_cents)/100` | ±0.01/账户 |
| 6 | 退费总金额 | `SUM(actual_refund)` = `SUM(actual_refund_cents)/100` | ±0.01 |
| 7 | 线索总数 | `COUNT(*) FROM resources` | 0 |
| 8 | 班级总数 | `COUNT(*) FROM classes` | 0 |
| 9 | 考勤记录数 | `COUNT(*) FROM class_attendance` | 0 |
| 10 | deduction_json 反序列化成功数 | 逐条 JSON 解析 | 0 |
| 11 | 每个订单 consumed_lessons vs deduction_logs 汇总 | 逐订单核对 | 0 |
| 12 | 每个账户 balance vs 流水汇总 | 逐账户核对 | 0 |
| 13 | parent_orders.total_lessons vs 子订单 lesson_count 之和 | 逐父订单核对 | 0 |
| 14 | 优惠券已核销数量 | 逐券核对 | 0 |

### 13.2 核对脚本框架

```python
def verify_migration():
    checks = []
    
    # 1. 计数核对
    checks.append(("学员总数", count_old("students"), count_new("iam_students")))
    checks.append(("订单总数", count_old("orders"), count_new("ord_orders")))
    
    # 2. 金额核对（分→元，允许 ±0.01 偏差）
    for table_old, table_new, col_old, col_new in [
        ("orders", "ord_orders", "actual_price", "actual_amount_cents"),
        ("refund_records", "wlt_refunds", "actual_refund", "actual_refund_cents"),
    ]:
        diff = compare_amounts(table_old, col_old, table_new, col_new)
        if diff > 0.01:
            checks.append((f"{table_old} 金额偏差", diff))
    
    # 3. deduction_json → deduction_logs 核对
    deducation_diff = compare_deduction_logs()
    checks.append(("deduction_json 核对", deducation_diff))
    
    return checks
```

---

## 14. 无法自动迁移的数据清单

| # | 数据 | 原因 | 建议 |
|---|------|------|------|
| 1 | resources.assigned_to（存人名非 ID） | 无法精确映射到员工 UUID | 人工录入映射表或基于姓名模糊匹配 + 人工审核 |
| 2 | resources.status='已联系' vs '无意向' 的精确边界 | 无审计日志，无法区分 | 迁移后统一标注来源为"旧系统迁移" |
| 3 | appointments.effective_status 动态计算结果 | 非持久化字段 | 迁移后通过查询实时计算 |
| 4 | communication_records 中无结构化字段的记录 | 沟通内容为自由文本 | 直接迁移文本，不做结构化转换 |
| 5 | subject 字段（旧）vs subject_level1/2（新）不一致的数据 | 两套字段可能不同步 | 以 subject_level1/2 为准，diff 不一致的单独报告 |
| 6 | 考勤修改/冲正的历史轨迹 | deduction_json 只有最新状态，无修改历史 | 无法恢复完整历史，仅迁移最新状态 |
| 7 | activity_enrollment_counts 缓存值 | 缓存表，可能不准确 | 迁移后重新计算 |
| 8 | 税率历史变更记录 | 无 tax_rates 变更日志 | 仅迁移当前值，历史税率无法追溯 |
| 9 | 员工权限和操作历史 | 无用户系统、无审计日志 | 无法迁移任何权限和操作审计数据 |
| 10 | 前后端交互中未持久化的校验规则 | 仅存在于 PHP 代码中 | 需对照 03-business-rules.md 在新系统重新实现 |
| 11 | 并发的 FOR UPDATE 行锁语义 | MySQL→PG 锁机制差异 | 需重新设计并发控制策略（乐观锁或 advisory lock） |
