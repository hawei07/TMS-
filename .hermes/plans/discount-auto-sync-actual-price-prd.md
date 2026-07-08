# PRD：优惠金额变动自动同步报价单实际价格

> **版本**: v1.0 | **日期**: 2026-07-08 | **状态**: 待确认
> **关联模块**: 优惠管理 / 优惠券管理 / 价格管理
> **目标表**: `price_items`

---

## 1. 需求概述

当运营人员修改优惠方案（`discount_plans`）的 `discount_amount` 或优惠券（`coupons`）的 `discount_amount` 后，所有关联了该优惠的报价单（`price_items`）必须自动重新计算 `actual_price`。

### 为什么需要

`price_items.actual_price` 是**当时计算并固化存储**的值。一旦优惠金额变了，已存的 `actual_price` 就过时了。后续录单（`pay_enroll`）直接用 `price_items.actual_price` 计算订单总额，不会再次查询优惠表实时计算——所以必须主动同步。

---

## 2. 数据关系

### 2.1 price_items 与优惠的关联

```
price_items
├── discount_plan_id  ──→  discount_plans.id       （优惠方案）
├── coupon_id         ──→  coupons.id              （课时优惠券，coupon_type='课程券'）
├── product_coupon_id ──→  coupons.id              （商品券，coupon_type='商品券'）
└── teaching_aid_id   ──→  teaching_aids.id        （教材包）
```

### 2.2 优惠金额字段

| 表 | 金额字段 | 类型 |
|----|---------|------|
| `discount_plans` | `discount_amount` | DECIMAL(10,2) |
| `coupons` | `discount_amount` | DECIMAL(10,2) |
| `teaching_aids` | `price` | — |

### 2.3 实际价格计算公式

```
actual_price = unit_price
             - discount_amount       ← 来自 discount_plans.discount_amount
             - coupon_amount         ← 来自 coupons.discount_amount（课时券）
             + teaching_aid_price    ← 来自 teaching_aids.price
             - product_coupon_amount ← 来自 coupons.discount_amount（商品券）

最小值: 0（GREATEST(0, 计算结果)）
```

### 2.4 边界规则

| 场景 | 处理 |
|------|------|
| 某项优惠未关联（外键为 NULL） | `COALESCE(xxx.discount_amount, 0)` → 金额视为 0 |
| 教材包已删除（teaching_aid_id 有值但 teaching_aids 中无记录） | `LEFT JOIN` → `COALESCE(ta.price, 0)` → 视为 0 |
| 计算结果为负数 | `GREATEST(0, ...)` → 截断为 0 |

---

## 3. 方案设计

### 3.1 改动范围：两个 API

| API | 文件 | 行号 | 触发时机 |
|-----|------|------|---------|
| `update_discount_plan` | `index.php` | ~2733-2787 | 优惠方案金额/名称等变更 |
| `update_coupon` | `index.php` | ~2916-2970 | 优惠券金额/名称等变更 |

> **原则**：只在这两个写入入口加重算逻辑。其他 API 不需要改——`save_price_plan` 由前端直接传入 `actual_price` 不需要后端算；`pay_enroll` 直接从 `price_items` 读已存好的 `actual_price` 无需实时 JOIN。

### 3.2 在哪加重算（精确位置）

在 **事务中、UPDATE coupon/discount 成功后、commit 之前**，执行一条 UPDATE 语句批量同步 `price_items.actual_price`。

理由：
- 在事务内 → UPDATE 优惠表和 UPDATE price_items 原子化，不会出现优惠改了但价格没同步的半截状态
- 在 UPDATE 成功后 → 此时新金额已写入，可以直接用新值计算

### 3.3 SQL 写法

#### 3.3.1 update_discount_plan 末尾（~2760 行后，commit 前）

```php
// ===== 新增：同步关联报价单的实际价格 =====
$db->exec("
    UPDATE price_items pi
    LEFT JOIN discount_plans d ON pi.discount_plan_id = d.id
    LEFT JOIN coupons c ON pi.coupon_id = c.id
    LEFT JOIN coupons pc ON pi.product_coupon_id = pc.id
    LEFT JOIN teaching_aids ta ON pi.teaching_aid_id = ta.id
    SET pi.actual_price = GREATEST(0,
        pi.unit_price
        - COALESCE(d.discount_amount, 0)
        - COALESCE(c.discount_amount, 0)
        + COALESCE(ta.price, 0)
        - COALESCE(pc.discount_amount, 0)
    )
    WHERE pi.discount_plan_id = $id
");
```

**说明**：`$id` 就是被更新的 `discount_plans.id`，WHERE 条件匹配所有关联了该方案的报价单。

#### 3.3.2 update_coupon 末尾（~2942 行后，commit 前）

```php
// ===== 新增：同步关联报价单的实际价格 =====
// 注意：coupon_id 和 product_coupon_id 都引用 coupons 表，
// 所以需要 OR 两个条件
$db->exec("
    UPDATE price_items pi
    LEFT JOIN discount_plans d ON pi.discount_plan_id = d.id
    LEFT JOIN coupons c ON pi.coupon_id = c.id
    LEFT JOIN coupons pc ON pi.product_coupon_id = pc.id
    LEFT JOIN teaching_aids ta ON pi.teaching_aid_id = ta.id
    SET pi.actual_price = GREATEST(0,
        pi.unit_price
        - COALESCE(d.discount_amount, 0)
        - COALESCE(c.discount_amount, 0)
        + COALESCE(ta.price, 0)
        - COALESCE(pc.discount_amount, 0)
    )
    WHERE pi.coupon_id = $id
       OR pi.product_coupon_id = $id
");
```

**说明**：一张优惠券可能同时作为「课时优惠券」（`coupon_id`）或「商品券」（`product_coupon_id`）出现在不同报价单中，两个关联路径都要覆盖。

---

## 4. 改动清单

### 4.1 只改 index.php 一个文件

| 改动 | 位置 | 行数 |
|------|------|------|
| `update_discount_plan`：事务内新增同步 SQL | ~2760 行后（UPDATE 之后、commit 之前）| +6 行 |
| `update_coupon`：事务内新增同步 SQL | ~2942 行后（UPDATE 之后、commit 之前）| +8 行 |

### 4.2 不改的文件

| 文件 | 原因 |
|------|------|
| `main.js` | 无需修改。重算在后端完成，前端下次请求时自动拿到新值 |
| `static/css/style.css` | 无 UI 变更 |
| 其他 PHP API | 无需修改。`list_price_plans` / `get_course_plans` / `get_order_detail` 只是读取，不涉及写入 |

---

## 5. 风险分析

### 5.1 风险矩阵

| # | 风险 | 等级 | 影响 | 缓解措施 |
|---|------|------|------|---------|
| 1 | **重复 JOIN 开销**：每次更新优惠都 5 表 JOIN + UPDATE 全表 price_items | 中 | 如果 price_items 有几万条且每次都全量扫，会锁表时间较长 | 当前业务量级（单校区几百条报价单）可接受。未来量级大了可加索引 `INDEX idx_pi_discount (discount_plan_id)`、`INDEX idx_pi_coupon (coupon_id)`、`INDEX idx_pi_product_coupon (product_coupon_id)` |
| 2 | **事务时间变长**：原本 1 条 UPDATE 变 2 条 | 低 | 增加的 UPDATE 很快（通常只更新几条到几十条） | 保持在事务内（原子性优先于速度） |
| 3 | **历史数据精度丢失**：price_items.unit_price 是 REAL 类型，可能有浮点精度问题 | 低 | 公式中所有金额用 `COALESCE` 返回 DECIMAL，MySQL 自动处理精度 | 已验证当前价格范围（几十到几千元）精度足够 |
| 4 | **product_coupon_id 缺少 ALTER TABLE**：代码引用但旧数据库可能无此列 | **高** | 缺少该列会导致 UPDATE 报 Unknown column 错误，事务回滚，优惠本身也无法更新 | ⚠ **必须先补 ALTER TABLE**（见下文 5.2） |
| 5 | **优惠改了但报价单不在任何 price_plan 中**：孤儿 price_items（plan_id 指向已删除的方案） | 低 | 不影响——WHERE 条件只按外键匹配，同一条 UPDATE 也会处理这些行 | 无需额外处理 |
| 6 | **并发更新同一优惠方案**：两个请求同时改同一个 discount_plan | 低 | MySQL InnoDB 行锁 + 事务串行化，后者等前者提交后再执行 | 无需额外处理 |

### 5.2 ⚠ 前置依赖：补 product_coupon_id ALTER TABLE

当前 `index.php` 第 583-585 行有 `discount_plan_id`、`coupon_id`、`teaching_aid_id` 三个 ALTER TABLE，**缺 `product_coupon_id`**：

```php
// 586 行后需追加：
try { $db->exec("ALTER TABLE price_items ADD COLUMN product_coupon_id INT DEFAULT NULL AFTER teaching_aid_id"); } catch (PDOException $e) {}
```

如果旧数据库没有此列，`update_coupon` 的重算 SQL 会因为 `LEFT JOIN coupons pc ON pi.product_coupon_id = pc.id` 报 Unknown column 错误，导致整个事务回滚——**优惠本身更新也失败**。这是阻断性 bug，必须先修。

### 5.3 已删记录的处理

- `discount_plans` / `coupons`被删除：`LEFT JOIN` 返回 NULL，`COALESCE(xxx.discount_amount, 0)` = 0，相当于该项优惠金额视为 0
- `teaching_aids`被删除：同上，视为 0
- 此行为与需求一致：删了的优惠/教材包不再抵扣

---

## 6. 验证方案

### 6.1 后端 SQL 验证

```bash
# 1. 通过 PHP 服务器下载一条报价单当前值
curl -s "http://127.0.0.1:5001/?action=list_price_plans&course_id=1" | python -c "
import sys,json; d=json.load(sys.stdin)['data']
for p in d:
    for i in p.get('items',[]):
        print(f\"plan={p['plan_name']} item={i['name']} actual={i['actual_price']} unit={i['unit_price']} disc_name={i.get('discount_plan_name','-')} cou_name={i.get('coupon_name','-')} pc_name={i.get('product_coupon_name','-')} ta_name={i.get('teaching_aid_name','-')} ta_price={i.get('teaching_aid_price',0)}\")
"
```

### 6.2 端到端测试步骤

1. **准备**：找到一条已关联了优惠方案 A（金额=100）的报价单，记录其 `actual_price`
2. **修改优惠方案**：在优惠管理面板把方案 A 的金额从 100 改为 200
3. **验证自动同步**：返回价格方案页面，查看该报价单的 `actual_price` 应减少 100
4. **修改优惠券**：同上，验证关联了该券的报价单 `actual_price` 自动更新
5. **验证最小值 0**：把优惠金额设得很大，验证 `actual_price` ≥ 0

---

## 7. 实施计划

| Phase | 内容 | 预估工时 |
|-------|------|---------|
| P0 | 补 `product_coupon_id` ALTER TABLE（前置依赖） | 2 min |
| P1 | `update_discount_plan` 加重算 SQL | 5 min |
| P2 | `update_coupon` 加重算 SQL | 5 min |
| P3 | 后端 curl 验证 + 浏览器端到端测试 | 10 min |

**总预估**: ~20 min

---

## 8. 潜在扩展（非本次范围）

1. **删除优惠方案/券时也同步**：`delete_discount_plan` / `delete_coupon` 中同样加重算（关联过已删除优惠的报价单，`actual_price` 应该回退）
2. **优惠过期自动失效**：`discount_plans.end_date` / `coupons.end_date` 过期后，`actual_price` 是否应自动回退
3. **前端实时预览**：修改优惠金额时，价格方案页面实时预览影响范围

---

## 附录 A：公式 4 条路径一致性检查

当前 `main.js` 前端有 3 处独立的价格计算逻辑（见陷阱 #61b），本次改动在后端加了第 4 条路径：

| # | 位置 | 触发场景 | 公式 |
|---|------|----------|------|
| 1 | `recalcItemActualPrice()` | 弹窗表单下拉 onchange | `Math.max(0, basePrice - discount + teachingAidPrice).toFixed(2)` |
| 2 | `bindInlinePriceRecalc()` | 表格 inline 编辑实时显示 | 同上（独立 recalc 闭包） |
| 3 | `saveInlineEdit()` | 表格 inline 编辑保存 | `Math.max(0, unitPrice - discount + teachingAidPrice)` |
| **4** | **`update_discount_plan` / `update_coupon`** | **优惠金额变动后自动同步** | **`GREATEST(0, unit_price - COALESCE(d.discount_amount,0) - COALESCE(c.discount_amount,0) + COALESCE(ta.price,0) - COALESCE(pc.discount_amount,0))`** |

> ⚠ 四条路径必须使用**完全一致**的公式。后续如果公式调整（比如增加新扣减项），必须四处同步修改。

## 附录 B：索引建议

当前 `price_items` 表无前缀索引。建议加以下索引以优化重算查询：

```sql
ALTER TABLE price_items ADD INDEX idx_pi_discount_plan (discount_plan_id);
ALTER TABLE price_items ADD INDEX idx_pi_coupon (coupon_id);
ALTER TABLE price_items ADD INDEX idx_pi_product_coupon (product_coupon_id);
```

> 非必须——当前数据量下影响可忽略。量级增长到数千条以上时再加。
