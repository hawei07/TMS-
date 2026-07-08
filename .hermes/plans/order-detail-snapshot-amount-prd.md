# 订单详情金额保持录单时快照不变 — PRD

> 版本：v1.0 | 作者：Product Manager | 日期：2026-07-08
> 状态：待评审

---

## 1. 现状分析

### 1.1 问题描述

订单详情弹窗（`renderOrderDetail`）在展示每笔子订单的优惠金额时，通过多层 JOIN 实时查询 `discount_plans` / `coupons` / `teaching_aids` 表来获取优惠金额。由于这些表的金额字段随时可能被管理员修改，导致**历史订单展示的优惠金额与实际录单时不符**，属于数据一致性问题。

### 1.2 代码定位表

| 层面 | 文件 | 关键行号 | 说明 |
|------|------|----------|------|
| 建表 | index.php | 222–235 | orders 表 CREATE TABLE |
| 建表 | index.php | 555–563 | discount_plans 表 (`discount_amount`) |
| 建表 | index.php | 588–597 | coupons 表 (`discount_amount`) |
| 建表 | index.php | 619–628 | teaching_aids 表 (`price`) |
| 建表 | index.php | 583–586 | price_items 加列（discount_plan_id / coupon_id / teaching_aid_id / product_coupon_id） |
| 录单 POST | index.php | 2415–2590 | `pay_enroll`：写入 orders 表，**未存储优惠金额快照** |
| 详情 GET | index.php | 4158–4280 | `get_order_detail`：JOIN discount_plans/coupons/teaching_aids 获取**当前**优惠金额 |
| 前端详情 | static/js/main.js | 7093–7164 | `renderOrderDetail`：渲染优惠方案、课时券、教材包、商品券 |
| 前端录单 | static/js/main.js | 6219–6258 | `selectEnrollPlan`：录单页展示优惠信息 |

### 1.3 当前数据流

```
录单时 (pay_enroll):
  price_items
    ├── JOIN discount_plans (d.discount_amount) ──→ 展示给用户，但 ⚠️不写入 orders
    ├── JOIN coupons (c.discount_amount)          ──→ 展示给用户，但 ⚠️不写入 orders
    ├── JOIN teaching_aids (ta.price)              ──→ 展示给用户，但 ⚠️不写入 orders
    └── JOIN coupons pc (product_coupon_id)        ──→ 展示给用户，但 ⚠️不写入 orders

  orders 写入列: actual_price, cash_amount, meituan_amount, account_amount,
                 plan_name, item_name, lesson_count, ...
                 ❌ 无 discount_plan_amount / coupon_amount / teaching_aid_price / product_coupon_amount

查看详情 (get_order_detail):
  orders
    ├── JOIN price_plans → price_items
    │   ├── LEFT JOIN discount_plans d  → d.discount_amount   ⚠️ 实时值
    │   ├── LEFT JOIN coupons c         → c.discount_amount   ⚠️ 实时值
    │   ├── LEFT JOIN teaching_aids ta  → ta.price            ⚠️ 实时值
    │   └── LEFT JOIN coupons pc        → pc.discount_amount  ⚠️ 实时值
    └── 结果：展示的是当前优惠金额，非录单时金额
```

### 1.4 受影响的优惠字段（4 类 × 2 = 8 字段）

| 中文名称 | 来源表 | 来源列 | JOIN 条件 |
|----------|--------|--------|-----------|
| 优惠方案名称 | discount_plans | name | pi.discount_plan_id = d.id |
| 优惠方案金额 | discount_plans | discount_amount | pi.discount_plan_id = d.id |
| 课时优惠券名称 | coupons | name | pi.coupon_id = c.id |
| 课时优惠券金额 | coupons | discount_amount | pi.coupon_id = c.id |
| 教材包名称 | teaching_aids | name | pi.teaching_aid_id = ta.id |
| 教材包价格 | teaching_aids | price | pi.teaching_aid_id = ta.id |
| 商品券名称 | coupons | name | pi.product_coupon_id = pc.id |
| 商品券金额 | coupons | discount_amount | pi.product_coupon_id = pc.id |

### 1.5 前端展示位置（3 处）

| 位置 | 函数 | 文件:行号 |
|------|------|-----------|
| 订单详情弹窗 | `renderOrderDetail` | main.js:7118–7122 |
| 录单-方案详情 | `selectEnrollPlan` | main.js:6241–6253 |
| 设置价格-方案列表 | `renderItemList` | main.js:3260–3276 |

> 注：录单页和设置价格页显示的是**当前**方案/券/教材包的值，属于正常行为（用户在配置/查阅当前报价）。只有**订单详情**需要展示历史快照。

---

## 2. 设计目标

1. **数据一致性**：订单详情中优惠金额 = 录单时刻的值，不再随后续修改而变化
2. **最小侵入**：不改变前端界面，用户无感知
3. **可回填**：历史订单能通过现有数据关系回填出正确快照
4. **性能优化**：减少 `get_order_detail` 中的 JOIN 数量（从 6 个 LEFT JOIN 减到 2 个）

---

## 3. 方案对比

### 3.1 方案 A（推荐）：录单时冗余存储优惠金额到 orders 表

**核心思路**：`pay_enroll` 写入 orders 时，把 8 个优惠字段一并写入；`get_order_detail` 直接从 orders 读。

**改动范围**：

| 步骤 | 文件 | 内容 |
|------|------|------|
| 1 | index.php (初始化) | ALTER TABLE orders 加 8 列 |
| 2 | index.php (pay_enroll) | INSERT 语句增加 8 个字段 |
| 3 | index.php (get_order_detail) | 去掉 4 个 LEFT JOIN，直接从 orders 读 |
| 4 | index.php (一次性脚本) | 回填历史订单的 8 个字段 |

**优点**：
- ✅ 数据永远准确，不受后续任何表变更影响
- ✅ 大幅简化 `get_order_detail` SQL（减少 4 个 LEFT JOIN）
- ✅ 前端零改动（字段名不变）
- ✅ 符合"写时快照"的最佳实践

**缺点**：
- ❌ 需 ALTER TABLE（8 列）
- ❌ 需一次性回填历史数据
- ❌ orders 表列数增加（当前 ~21 列，增加到 ~29 列）

**风险**：低。ALTER TABLE 加列是纯新增，不影响已有查询；回填脚本可独立运行验证。

---

### 3.2 方案 B：去掉订单详情中的优惠金额展示

**核心思路**：不在订单详情弹窗中显示折扣方案/优惠券/教材包/商品券金额。

**改动范围**：

| 步骤 | 文件 | 内容 |
|------|------|------|
| 1 | index.php (get_order_detail) | 去掉 4 个 LEFT JOIN |
| 2 | static/js/main.js (renderOrderDetail) | 去掉 4 列（优惠方案、课时券、教材包价格、商品券） |

**优点**：
- ✅ 改动量最小
- ✅ 无数据一致性问题（因为不显示了）

**缺点**：
- ❌ 用户体验差：看不到录单时用了什么优惠、优惠了多少
- ❌ 与录单页信息不对称（录单时能看到，查看详情时看不到）
- ❌ 失去审计追溯能力

**结论**：方案 B 不推荐，仅作备选。

---

## 4. 推荐方案：方案 A 详细设计

### 4.1 数据库变更

#### 4.1.1 ALTER TABLE orders 加列

```sql
ALTER TABLE orders
  ADD COLUMN discount_plan_name VARCHAR(500) DEFAULT '' AFTER plan_name,
  ADD COLUMN discount_plan_amount DECIMAL(10,2) DEFAULT 0.00 AFTER discount_plan_name,
  ADD COLUMN coupon_name VARCHAR(500) DEFAULT '' AFTER discount_plan_amount,
  ADD COLUMN coupon_amount DECIMAL(10,2) DEFAULT 0.00 AFTER coupon_name,
  ADD COLUMN teaching_aid_name VARCHAR(500) DEFAULT '' AFTER coupon_amount,
  ADD COLUMN teaching_aid_price DECIMAL(10,2) DEFAULT 0.00 AFTER teaching_aid_name,
  ADD COLUMN product_coupon_name VARCHAR(500) DEFAULT '' AFTER teaching_aid_price,
  ADD COLUMN product_coupon_amount DECIMAL(10,2) DEFAULT 0.00 AFTER product_coupon_name;
```

> 列位置放在 `plan_name` 后面，与业务语义连贯（方案 → 优惠方案 → 课时券 → 教材包 → 商品券）。

#### 4.1.2 兼容已有数据库（初始化自动迁移）

在 index.php 初始化区域（~240 行附近，`SHOW COLUMNS FROM orders` 循环之后）追加：

```php
// 订单优惠金额快照列（v2.x）
$snapshotCols = [
    'discount_plan_name' => "VARCHAR(500) DEFAULT ''",
    'discount_plan_amount' => 'DECIMAL(10,2) DEFAULT 0.00',
    'coupon_name' => "VARCHAR(500) DEFAULT ''",
    'coupon_amount' => 'DECIMAL(10,2) DEFAULT 0.00',
    'teaching_aid_name' => "VARCHAR(500) DEFAULT ''",
    'teaching_aid_price' => 'DECIMAL(10,2) DEFAULT 0.00',
    'product_coupon_name' => "VARCHAR(500) DEFAULT ''",
    'product_coupon_amount' => 'DECIMAL(10,2) DEFAULT 0.00',
];
foreach ($snapshotCols as $col => $def) {
    if (!in_array($col, $existingCols)) {
        $db->exec("ALTER TABLE orders ADD COLUMN $col $def");
    }
}
```

### 4.2 后端改动

#### 4.2.1 pay_enroll（录单写入快照）

修改 `INSERT INTO orders` 的 prepare 语句，增加 8 个绑定参数。

**当前**（简化）：
```php
$stmt = $db->prepare("INSERT INTO orders
  (student_id, course_id, plan_name, item_name, lesson_count, actual_price, 
   cash_amount, meituan_amount, account_amount, paid_amount, order_no, 
   parent_order_no, created_at, paid_at, order_type, campus, pay_status, is_voided)
  VALUES (...)
");
```

**修改后**：
```php
$stmt = $db->prepare("INSERT INTO orders
  (student_id, course_id, plan_name, 
   discount_plan_name, discount_plan_amount, 
   coupon_name, coupon_amount, 
   teaching_aid_name, teaching_aid_price, 
   product_coupon_name, product_coupon_amount,
   item_name, lesson_count, actual_price, 
   cash_amount, meituan_amount, account_amount, paid_amount, order_no, 
   parent_order_no, created_at, paid_at, order_type, campus, pay_status, is_voided)
  VALUES (...)
");
```

在 foreach 循环内（`$item` 可用时），取值并绑定：

```php
// 优惠方案快照
$discountPlanName = ''; $discountPlanAmount = 0.00;
$couponName = ''; $couponAmount = 0.00;
$taName = ''; $taPrice = 0.00;
$pcName = ''; $pcAmount = 0.00;

$dpId = intval($item['discount_plan_id'] ?? 0);
if ($dpId > 0) {
    $dp = $db->query("SELECT name, discount_amount FROM discount_plans WHERE id=$dpId")->fetch(PDO::FETCH_ASSOC);
    if ($dp) { $discountPlanName = $dp['name']; $discountPlanAmount = floatval($dp['discount_amount']); }
}
$cId = intval($item['coupon_id'] ?? 0);
if ($cId > 0) {
    $c = $db->query("SELECT name, discount_amount FROM coupons WHERE id=$cId")->fetch(PDO::FETCH_ASSOC);
    if ($c) { $couponName = $c['name']; $couponAmount = floatval($c['discount_amount']); }
}
$taId = intval($item['teaching_aid_id'] ?? 0);
if ($taId > 0) {
    $ta = $db->query("SELECT name, price FROM teaching_aids WHERE id=$taId")->fetch(PDO::FETCH_ASSOC);
    if ($ta) { $taName = $ta['name']; $taPrice = floatval($ta['price']); }
}
$pcId = intval($item['product_coupon_id'] ?? 0);
if ($pcId > 0) {
    $pc = $db->query("SELECT name, discount_amount FROM coupons WHERE id=$pcId")->fetch(PDO::FETCH_ASSOC);
    if ($pc) { $pcName = $pc['name']; $pcAmount = floatval($pc['discount_amount']); }
}

// bindValue 追加
$stmt->bindValue(':dpn', $discountPlanName, PDO::PARAM_STR);
$stmt->bindValue(':dpa', $discountPlanAmount, PDO::PARAM_STR);
$stmt->bindValue(':cn',  $couponName, PDO::PARAM_STR);
$stmt->bindValue(':ca',  $couponAmount, PDO::PARAM_STR);
$stmt->bindValue(':tan', $taName, PDO::PARAM_STR);
$stmt->bindValue(':tap', $taPrice, PDO::PARAM_STR);
$stmt->bindValue(':pcn', $pcName, PDO::PARAM_STR);
$stmt->bindValue(':pca', $pcAmount, PDO::PARAM_STR);
```

> 如果 item 没有对应的 discount_plan_id / coupon_id 等（为 0 或 NULL），则快照为空字符串和 0.00，与当前前端展示 '-' 的逻辑一致。

#### 4.2.2 get_order_detail（直接读 orders 表）

修改 SQL，去掉 4 个 LEFT JOIN，直接从 orders 表读取快照列。

**当前 SQL**（6 个 LEFT JOIN）：
```sql
SELECT o.id, o.order_no, o.item_name, o.lesson_count, o.actual_price,
       o.cash_amount, o.meituan_amount, o.account_amount,
       o.pay_status, o.plan_name, o.course_id, o.campus,
       pi.unit_price,
       d.name AS discount_plan_name,
       d.discount_amount AS discount_plan_amount,
       c.name AS coupon_name,
       c.discount_amount AS coupon_amount,
       ta.name AS teaching_aid_name,
       ta.price AS teaching_aid_price,
       pc.name AS product_coupon_name,
       pc.discount_amount AS product_coupon_amount
FROM orders o
LEFT JOIN price_plans pp ON pp.name = o.plan_name AND pp.course_id = o.course_id
LEFT JOIN price_items pi ON pi.plan_id = pp.id AND pi.name = o.item_name
LEFT JOIN discount_plans d ON pi.discount_plan_id = d.id
LEFT JOIN coupons c ON pi.coupon_id = c.id
LEFT JOIN teaching_aids ta ON pi.teaching_aid_id = ta.id
LEFT JOIN coupons pc ON pi.product_coupon_id = pc.id
WHERE o.parent_order_no = ?
ORDER BY o.id
```

**修改后 SQL**（2 个 LEFT JOIN）：
```sql
SELECT o.id, o.order_no, o.item_name, o.lesson_count, o.actual_price,
       o.cash_amount, o.meituan_amount, o.account_amount,
       o.pay_status, o.plan_name, o.course_id, o.campus,
       o.discount_plan_name, o.discount_plan_amount,
       o.coupon_name, o.coupon_amount,
       o.teaching_aid_name, o.teaching_aid_price,
       o.product_coupon_name, o.product_coupon_amount,
       pi.unit_price
FROM orders o
LEFT JOIN price_plans pp ON pp.name = o.plan_name AND pp.course_id = o.course_id
LEFT JOIN price_items pi ON pi.plan_id = pp.id AND pi.name = o.item_name
WHERE o.parent_order_no = ?
ORDER BY o.id
```

> `pi.unit_price` 保留 JOIN，因为 unit_price 属于报价单基础定价，正常运营不会修改。若后续需要，可同样快照到 orders 表。

**items 数组构建**保持不变（字段名、类型均不变），无需改 PHP 逻辑。

### 4.3 前端改动

**无需改动**。`renderOrderDetail` 中的字段名 `discount_plan_name` / `discount_plan_amount` / `coupon_name` / `coupon_amount` / `teaching_aid_name` / `teaching_aid_price` / `product_coupon_name` / `product_coupon_amount` 完全相同。

### 4.4 历史数据回填

一次性执行 SQL，通过 price_items JOIN 重建历史订单的优惠快照：

```sql
UPDATE orders o
LEFT JOIN price_plans pp ON pp.name = o.plan_name AND pp.course_id = o.course_id
LEFT JOIN price_items pi ON pi.plan_id = pp.id AND pi.name = o.item_name
LEFT JOIN discount_plans d ON pi.discount_plan_id = d.id
LEFT JOIN coupons c ON pi.coupon_id = c.id
LEFT JOIN teaching_aids ta ON pi.teaching_aid_id = ta.id
LEFT JOIN coupons pc ON pi.product_coupon_id = pc.id
SET
    o.discount_plan_name   = COALESCE(d.name, ''),
    o.discount_plan_amount = COALESCE(d.discount_amount, 0),
    o.coupon_name          = COALESCE(c.name, ''),
    o.coupon_amount        = COALESCE(c.discount_amount, 0),
    o.teaching_aid_name    = COALESCE(ta.name, ''),
    o.teaching_aid_price   = COALESCE(ta.price, 0),
    o.product_coupon_name  = COALESCE(pc.name, ''),
    o.product_coupon_amount = COALESCE(pc.discount_amount, 0)
WHERE o.discount_plan_name = ''
   OR o.discount_plan_name IS NULL;
```

> **局限性说明**：回填使用的是 discount_plans/coupons/teaching_aids 的**当前值**。如果这些表在录单后已被修改，回填值与实际录单时不同（与旧行为无差异）。回填的意义在于**固化现状、阻断未来继续偏移**。真正准确的值从回填之后的新订单开始保证。

---

## 5. 兼容性矩阵

| 变更项 | 后端 API | 前端 | 其他受影响 |
|--------|----------|------|-----------|
| ALTER TABLE orders 加 8 列 | ✅ 纯新增，SELECT * 多出列但不影响逻辑 | ✅ 无影响 | ✅ 无影响 |
| pay_enroll INSERT 加 8 字段 | ✅ 现有参数不变 | ✅ 无影响 | ✅ 无影响 |
| get_order_detail 去 JOIN | ✅ 返回字段名不变 | ✅ 无影响 | ✅ 无影响 |
| 历史数据回填 SQL | ⚠️ 一次性执行，建议低峰期 | ✅ 无影响 | ✅ 无影响 |

---

## 6. 实施计划

```
Phase 1: 数据库迁移
  ├── 在 index.php 初始化区域追加 ALTER TABLE 兼容逻辑
  └── 执行回填 SQL（手动，一次）

Phase 2: 后端改动
  ├── 修改 pay_enroll 的 INSERT prepare + bindValue（加 8 字段，约 40 行新增）
  └── 修改 get_order_detail 的 SQL（去掉 4 个 LEFT JOIN，约 10 行变更）

Phase 3: 验证
  ├── curl pay_enroll 录一笔新单 → 确认 orders 表 8 个快照列非空
  ├── curl get_order_detail → 确认返回的优惠金额与录单时一致
  └── 修改 discount_plans.discount_amount → 再次 get_order_detail → 确认金额不变（快照生效）
```

---

## 7. 附录

### 7.1 关键决策记录

| 决策 | 结论 | 理由 |
|------|------|------|
| 快照存 orders 还是建 snapshot 表 | 存 orders | orders 已存 actual_price/cash 等业务金额，优惠金额属于同一粒度；建新表增加 JOIN 复杂度 |
| unit_price 是否也快照 | 暂不快照 | unit_price 来源 price_items，属于报价单基础数据，正常运营不会修改 |
| 回填用当前值还是放弃 | 用当前值 + 文档说明 | 历史数据无法 100% 还原，但固化现状可阻断继续恶化 |
| 前端是否改动 | 不改 | 字段名/格式完全不变 |

### 7.2 orders 表列变更清单

| 序号 | 列名 | 类型 | 默认值 | 说明 |
|------|------|------|--------|------|
| — | (现有 21 列) | — | — | — |
| 22 | discount_plan_name | VARCHAR(500) | '' | 优惠方案名称快照 |
| 23 | discount_plan_amount | DECIMAL(10,2) | 0.00 | 优惠方案金额快照 |
| 24 | coupon_name | VARCHAR(500) | '' | 课时优惠券名称快照 |
| 25 | coupon_amount | DECIMAL(10,2) | 0.00 | 课时优惠券金额快照 |
| 26 | teaching_aid_name | VARCHAR(500) | '' | 教材包名称快照 |
| 27 | teaching_aid_price | DECIMAL(10,2) | 0.00 | 教材包价格快照 |
| 28 | product_coupon_name | VARCHAR(500) | '' | 商品券名称快照 |
| 29 | product_coupon_amount | DECIMAL(10,2) | 0.00 | 商品券金额快照 |
