# PRD：交易订单新增课程金额和商品金额两列

> 版本：v1.0 | 日期：2026-07-09 | 项目：TMS管理系统

---

## 1. 需求背景

TMS 交易订单列表（`panel-orders`，当前展示 `orders` 表子订单明细）中，`actual_price`（订单金额）是一个混合值，包含了课程费用、教材包费用和商品券抵扣。管理员无法直观分辨一笔订单中「纯课程收入」和「画具/教材包商品收入」各占多少。需要在"订单金额"列后新增**课程金额**和**商品金额**两列。

同时，独立的画具销售（`teaching_aid_sales` 表）与课程订单（`orders` 表）目前没有关联，需要设计关联方案使其也能纳入交易视图。

---

## 2. 现有数据模型分析

### 2.1 `orders` 表（课程报名订单）

当前与金额/商品相关的关键字段（已存在的快照列）：

| 字段 | 类型 | 说明 |
|------|------|------|
| `actual_price` | REAL | 实付金额（混合值） |
| `teaching_aid_price` | DECIMAL(10,2) | 教材包价格快照 |
| `teaching_aid_name` | VARCHAR(200) | 教材包名称快照 |
| `product_coupon_name` | VARCHAR(200) | 商品券名称快照 |
| `product_coupon_amount` | DECIMAL(10,2) | 商品券金额快照（正值=抵扣） |
| `discount_plan_amount` | DECIMAL(10,2) | 优惠方案金额 |
| `coupon_amount` | DECIMAL(10,2) | 课时优惠券金额 |
| `cash_amount` / `meituan_amount` / `account_amount` | REAL | 三种支付方式拆分 |
| `parent_order_no` | VARCHAR(500) | 父订单号（同一录单的子订单共用） |
| `student_id` | INT | 学员ID |

**当前 actual_price 公式**（从录单 `pay_enroll` 写入逻辑反推）：

```
actual_price = unit_price - discount_plan_amount - coupon_amount
               + teaching_aid_price - product_coupon_amount
```

### 2.2 `teaching_aid_sales` 表（独立画具销售）

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INT PK | |
| `student_id` | INT | 学员ID |
| `teaching_aid_id` | INT | 商品ID（外键→teaching_aids） |
| `total_amount` | DECIMAL(10,2) | 销售总金额 |
| `cash_amount` / `meituan_amount` / `account_amount` | DECIMAL(10,2) | 三种支付方式 |
| `sold_at` | DATETIME | 销售时间 |

**关键问题**：`teaching_aid_sales` 表**没有 `parent_order_no` 字段**，与 `orders` 表仅共享 `student_id`，无法精确关联到某次录单。

### 2.3 `parent_orders` 表（父订单汇总）

| 字段 | 说明 |
|------|------|
| `parent_order_no` | 唯一父订单号 |
| `total_price` | 总金额（当前=所有子订单 actual_price 求和） |
| `cash_amount` / `meituan_amount` | 支付汇总 |

---

## 3. 核心设计决策

### 3.1 课程金额和商品金额的定义

利用 `orders` 表已有的快照列，**无需 JOIN 额外表**：

| 列名 | 公式 | 说明 |
|------|------|------|
| **课程金额** | `actual_price - teaching_aid_price + product_coupon_amount` | 纯课程费用（= classPrice），排除教材包成本，加回商品券抵扣 |
| **商品金额** | `teaching_aid_price` | 该订单中捆绑的教材包/画具金额 |

**公式解释**：
- `actual_price` 中已包含教材包加价（`+teaching_aid_price`）和商品券抵扣（`-product_coupon_amount`）
- 课程金额 = 实付 − 教材包 + 商品券 = 回归纯课程定价
- 商品金额 = 教材包原价（用户为画具支付的金额）

**验证**：`课程金额 + 商品金额 = actual_price + product_coupon_amount`（商品券是虚拟抵扣，不产生实际支付，所以两列之和可能略大于 actual_price，这是正确的——商品券金额代表"省了多少钱"）。

> 💡 **建议**：若希望「课程金额 + 商品金额 = actual_price」，可定义为：
> - 课程金额 = `actual_price - teaching_aid_price`
> - 商品金额 = `teaching_aid_price`
> 
> 这样更直观，商品券作为课程优惠的一部分体现在课程金额中。**本 PRD 推荐此方案**，与现有 classPrice 计算（`actual_price - teaching_aid_price + product_coupon_amount`）区分开来，避免混淆。

**最终推荐公式（简单直观）**：

```
课程金额 = actual_price - teaching_aid_price（教材包成本剔除后即课程实收）
商品金额 = teaching_aid_price（教材包产生的收入）
```

> 对于没有教材包的订单（`teaching_aid_price = 0`），课程金额 = actual_price，商品金额 = 0。

### 3.2 `teaching_aid_sales` 关联方案

独立画具销售（不在录单流程中的画具购买）需要关联到父订单才能出现在交易订单列表中。

**方案 A（推荐）：给 `teaching_aid_sales` 加 `parent_order_no` 字段**

- 在画具购买流程中（`#tab-ta-purchase` 面板），新增可选字段「关联父订单号」
- 用户在购买画具时可选择填入已有的父订单号（下拉搜索或手动输入）
- `teaching_aid_sales.parent_order_no` 默认 `''`（空=独立销售，不关联）
- 交易订单列表 API 通过 UNION 合并 `orders` 和 `teaching_aid_sales`（仅限有 parent_order_no 的记录）

**方案 B（备选）：按时间窗口自动匹配**

- 不推荐：同一学员可能同一天有多次独立操作，时间匹配不可靠

**方案 C：不关联，teaching_aid_sales 保持独立**

- 画具销售在「画具管理 → 销售记录」Tab 中查看
- 交易订单列表仅显示 `orders` 表课程订单
- 简单，但两个销售渠道割裂

**本 PRD 采用方案 A**，理由：
1. 业务上画具销售常伴随课程报名发生（学员报名时顺便买画具）
2. 通过 parent_order_no 关联后，可在交易订单详情中展示完整消费画像
3. 可选填入，不破坏现有独立画具销售流程

---

## 4. 数据库变更

### 4.1 `teaching_aid_sales` 表新增字段

```sql
-- 添加父订单号关联字段
ALTER TABLE teaching_aid_sales 
  ADD COLUMN parent_order_no VARCHAR(500) DEFAULT '' COMMENT '关联的父订单号';

-- 索引加速查询
ALTER TABLE teaching_aid_sales 
  ADD INDEX idx_parent_order_no (parent_order_no);
```

### 4.2 `parent_orders` 表新增字段（可选）

```sql
-- 汇总用的商品总额
ALTER TABLE parent_orders 
  ADD COLUMN goods_total DECIMAL(10,2) DEFAULT 0.00 COMMENT '商品总金额';
```

此字段为非必需——可在查询时动态计算。但若未来报表需求频繁，建议冗余存储。

---

## 5. API 变更

### 5.1 `list_orders` — 新增返回字段

**变更类型**：增强（向后兼容）

**当前 SQL**（index.php ~4561）:
```sql
SELECT o.id, o.student_id, o.course_id, o.plan_name, o.item_name, 
       o.lesson_count, o.actual_price, ... 
FROM orders o ...
```

**变更后 SQL**：新增 `teaching_aid_price` 和 `product_coupon_amount` 到 SELECT 列表中（这两个字段在 orders 表已存在，当前 SQL 未选取）：

```sql
SELECT o.id, o.student_id, o.course_id, o.plan_name, o.item_name, 
       o.lesson_count, o.actual_price,
       o.teaching_aid_price,              -- ← 新增
       o.product_coupon_amount,           -- ← 新增
       ...
FROM orders o ...
```

**返回数据新增字段**（PHP 计算后附加）：

```php
// 在 while ($row = $stmt->fetch(...)) 循环中追加：
$row['course_amount'] = round(
    floatval($row['actual_price']) - floatval($row['teaching_aid_price'] ?? 0), 2
);
$row['goods_amount'] = round(floatval($row['teaching_aid_price'] ?? 0), 2);
```

也可在前端计算（见 §6.1），减少后端改动。

**支付汇总 SQL 同步更新**：在 `summarySql` 中添加 goods 总金额汇总。

### 5.2 `list_orders` — UNION `teaching_aid_sales`（可选，方案A）

如果需要将关联了 parent_order_no 的画具销售也展示在交易订单列表中：

```sql
-- 方案：PHP 端分两次查询，合并结果
-- 1. 先查 orders（同上）
-- 2. 再查关联的 teaching_aid_sales
SELECT tas.*, s.name AS student_name, s.student_no,
       '商品购买' AS order_type,
       '已支付' AS pay_status,
       '否' AS is_voided,
       0 AS lesson_count,
       0 AS course_amount,
       tas.total_amount AS goods_amount,
       tas.total_amount AS actual_price
FROM teaching_aid_sales tas
LEFT JOIN students s ON tas.student_id = s.id
WHERE tas.parent_order_no != ''
  AND tas.parent_order_no IN (SELECT DISTINCT parent_order_no FROM orders WHERE ...)
ORDER BY tas.sold_at DESC
```

**注意**：合并后排序需统一处理（orders 按 `id DESC`，teaching_aid_sales 按 `sold_at DESC`），分页逻辑需调整。

> ⚠ **建议**：第一阶段先不 UNION teaching_aid_sales，仅对 `orders` 表拆分课程/商品金额。第二阶段再引入 teaching_aid_sales 的关联展示。

### 5.3 `create_teaching_aid_sale` — 支持 parent_order_no

在画具销售 API 中新增可选参数：

```php
$parentOrderNo = trim($input['parent_order_no'] ?? '');
// 如果提供了 parent_order_no，验证其存在于 parent_orders 表
if ($parentOrderNo !== '') {
    $poExists = $db->query("SELECT COUNT(*) FROM parent_orders 
        WHERE parent_order_no = " . $db->quote($parentOrderNo))->fetchColumn();
    if (!$poExists) {
        json(['error' => '父订单号不存在']); break;
    }
}
// INSERT 时写入 parent_order_no 字段
```

### 5.4 `get_order_detail` — 新增课程/商品金额汇总

在订单详情返回中新增：

```php
// 汇总课程金额和商品金额
$courseTotal = 0;
$goodsTotal = 0;
foreach ($items as $item) {
    $taPrice = floatval($item['teaching_aid_price'] ?? 0);
    $ap = floatval($item['actual_price'] ?? 0);
    $courseTotal += round($ap - $taPrice, 2);
    $goodsTotal += $taPrice;
}
$result['course_total'] = round($courseTotal, 2);
$result['goods_total'] = round($goodsTotal, 2);
```

### 5.5 `list_parent_orders` — 新增汇总字段（可选）

如果 parent_orders 表新增了 `goods_total`，需要在 `pay_enroll` 写入时同步计算并存储。

### 5.6 `pay_enroll` — 更新 parent_orders 写入

在写入 `parent_orders` 时新增 `goods_total` 的计算：

```php
$goodsTotal = 0;
foreach ($orderItems as $item) {
    $goodsTotal += floatval($item['teaching_aid_price'] ?? 0);
}
// INSERT parent_orders 时加入 goods_total
```

---

## 6. 前端变更

### 6.1 `renderOrderTable()` — 表格新增两列

**文件**：`static/js/main.js`，函数 `renderOrderTable()`（~7151行）

**当前表头**（index.php ~8140）：
```
... <th>订单金额</th><th>现金</th><th>美团</th><th>账户</th> ...
```

**变为**：
```
... <th>订单金额</th><th>课程金额</th><th>商品金额</th><th>现金</th><th>美团</th><th>账户</th> ...
```

**JS 渲染变更**（在 `rows.map()` 中，紧接 `actual_price` 的 `<td>` 之后）：

```javascript
// 在 r.actual_price 行之后插入：
const taPrice = Number(r.teaching_aid_price) || 0;
const courseAmount = (Number(r.actual_price) || 0) - taPrice;
return `
    ...
    <td>${r.actual_price != null ? '¥' + Number(r.actual_price).toFixed(2) : ''}</td>
    <td class="col-num">${courseAmount !== 0 ? '¥' + courseAmount.toFixed(2) : '¥0.00'}</td>
    <td class="col-num">${taPrice > 0 ? '¥' + taPrice.toFixed(2) : '¥0.00'}</td>
    <td>${cash > 0 ? '¥' + cash.toFixed(2) : '-'}</td>
    ...
`;
```

**合计行（tfoot）更新**：colspan 从 14 变为 16（+2 列），新增课程金额和商品金额的合计：

```javascript
tfoot.innerHTML = `<tr>
    <td colspan="16" style="text-align:right;font-weight:bold;">合计</td>
    <td style="font-weight:bold;">¥${总课程金额.toFixed(2)}</td>
    <td style="font-weight:bold;">¥${总商品金额.toFixed(2)}</td>
    <td style="font-weight:bold;color:#7c3aed;">¥${totalCash.toFixed(2)}</td>
    ...
</tr>`;
```

**空态 colspan**：从 22 变为 24（表头 22 列 + 2 = 24）。

### 6.2 `index.php` — 表头 HTML 变更

**文件**：`index.php`，行 ~8140

```html
<!-- 在 <th>订单金额</th> 之后插入两列 -->
<th>订单金额</th>
<th>课程金额</th>
<th>商品金额</th>
<th>现金</th>
```

### 6.3 `renderOrderDetail()` — 订单详情弹窗

**文件**：`static/js/main.js`，函数 `renderOrderDetail()`（~7225行）

在报价明细表格中，在 `actual_price` 列之后新增两列：

```javascript
// 每行 item 的计算（在 forEach 中）：
const itemTaPrice = Number(item.teaching_aid_price) || 0;
const itemAp = Number(item.actual_price) || 0;
const itemCourseAmount = itemAp - itemTaPrice;

itemsHtml += '<tr>' +
    ...
    '<td class="col-num">' + (item.actual_price != null ? '¥' + itemAp.toFixed(2) : '-') + '</td>' +
    '<td class="col-num">' + '¥' + itemCourseAmount.toFixed(2) + '</td>' +
    '<td class="col-num">' + (itemTaPrice > 0 ? '¥' + itemTaPrice.toFixed(2) : '¥0.00') + '</td>' +
    '</tr>';
```

表头也要同步加两列（`<th>课程金额</th><th>商品金额</th>`），colspan 从 10 变为 12。

### 6.4 `renderOrderDetailPage()` — 订单详情独立页面

**文件**：`static/js/main.js`，函数 `renderOrderDetailPage()`（~7314行）

与 6.3 同步修改（弹窗和独立页面共用相同的 `forEach` 渲染逻辑，但各自构建 HTML）。

### 6.5 `renderPaymentSummary()` — 支付汇总栏

**函数**：在 `loadOrders()` ~7133 调用，在筛选栏下方显示汇总。

新增课程金额和商品金额的汇总展示（可选）：

```javascript
function renderPaymentSummary(summary) {
    // 在现有现金/美团/账户汇总后追加
    let html = `... 
        <span class="summary-item">📚 课程总额：<strong>¥${summary.course_total || '0.00'}</strong></span>
        <span class="summary-item">📦 商品总额：<strong>¥${summary.goods_total || '0.00'}</strong></span>
    `;
    document.getElementById('payment-summary-order').innerHTML = html;
}
```

### 6.6 画具购买面板 — 新增关联父订单号输入

**文件**：`static/js/main.js` + `index.php`（`#tab-ta-purchase` 面板）

在购买画具表单中新增可选的输入字段：

```html
<div class="form-group">
    <label>关联父订单号（可选）：</label>
    <input type="text" id="ta-purchase-pono" 
           placeholder="输入父订单号，将销售关联到该订单"
           style="width:200px;">
</div>
```

JS 中 `confirmPurchase()` 提交时将值传给 `create_teaching_aid_sale`：

```javascript
const parentOrderNo = document.getElementById('ta-purchase-pono')?.value.trim() || '';
// 在 api('create_teaching_aid_sale', { ..., parent_order_no: parentOrderNo })
```

---

## 7. 实施计划

### Phase 1：订单金额拆分（核心需求，无破坏性变更）

| 步骤 | 文件 | 说明 |
|------|------|------|
| 1 | `index.php` ~4561 | `list_orders` SQL 加 `teaching_aid_price` 到 SELECT |
| 2 | `index.php` ~8140 | 表头 HTML 加两列 `<th>` |
| 3 | `main.js` `renderOrderTable()` | 渲染逻辑加两列 `<td>` + 合计行 colspan 更新 |
| 4 | `main.js` `renderOrderDetail()` | 弹窗明细加两列 |
| 5 | `main.js` `renderOrderDetailPage()` | 独立页面明细加两列 |
| 6 | `index.php` ~4570 | 支付汇总 SQL 加 goods 汇总 |

**验收**：
- 有教材包的订单：课程金额 < 订单金额（差额 = 教材包价格）
- 无教材包的订单：课程金额 = 订单金额，商品金额 = ¥0.00
- 合计行金额正确

### Phase 2：teaching_aid_sales 关联（扩展需求）

| 步骤 | 文件 | 说明 |
|------|------|------|
| 1 | `index.php` ~684 | `teaching_aid_sales` 加 `parent_order_no` 列 |
| 2 | `index.php` ~3913 | `create_teaching_aid_sale` 接收并写入 `parent_order_no` |
| 3 | `main.js` | 购买面板加「关联父订单号」输入框 |
| 4 | `index.php` `list_orders` |（可选）UNION teaching_aid_sales 行 |
| 5 | `index.php` `pay_enroll` |（可选）parent_orders 加 `goods_total` |

### Phase 3（可选）：parent_order_no 自动匹配

在录单页面（`#tab-enroll`）中，如果选择教材包，可自动将后续画具购买关联到同一个 parent_order_no。但此功能需求尚不明确，暂不纳入本次 PRD。

---

## 8. 风险与陷阱

### 8.1 公式一致性（参考陷阱 #93、#110）

`classPrice = actual_price - teaching_aid_price + product_coupon_amount` 已在以下位置使用：
- `save_class_attendance`（考勤 consumed_amount）
- `get_student_courses`（学员展示）
- `submit_refund`（退费金额）

本 PRD 推荐的商品金额公式（`teaching_aid_price`）与 classPrice 公式不同——商品金额不含商品券部分。**两者用途不同，不可混用**：
- 课程金额（交易列表）：`actual_price - teaching_aid_price`（直观拆分）
- classPrice（课时计算）：`actual_price - teaching_aid_price + product_coupon_amount`（业务计算）

### 8.2 colspan 和表头列数（参考陷阱 #123 Bug C）

表头从 22 列变为 24 列，以下位置需同步更新 colspan：
- `renderOrderTable()` 空态：`colspan="24"`
- `renderOrderTable()` tfoot 合计行
- `renderOrderDetail()` 汇总行
- `renderOrderDetailPage()` 汇总行

**检查**：`grep -n 'colspan' static/js/main.js | grep -E '"(22|14|10)"'`

### 8.3 历史数据

历史订单中 `teaching_aid_price` 可能为 NULL 或 0（旧数据未回填）。需要在展示时用 `?? 0` 防御，避免 `NaN`。

### 8.4 PHP 内置服务器重启

修改 `index.php` 后必须重启 PHP 进程：`taskkill /f /im php.exe && php -S 127.0.0.1:5001`

---

## 9. 附录：完整变更文件清单

| 文件 | 变更类型 | 行数估计 |
|------|----------|----------|
| `index.php` | `list_orders` SQL SELECT + 循环计算 | ~5行 |
| `index.php` | 表头 HTML `<th>` | ~2行 |
| `static/js/main.js` | `renderOrderTable()` 渲染 + tfoot | ~15行 |
| `static/js/main.js` | `renderOrderDetail()` 弹窗 | ~10行 |
| `static/js/main.js` | `renderOrderDetailPage()` 页面 | ~10行 |
| `static/js/main.js` | `renderPaymentSummary()` | ~5行 |
| `index.php` | `create_teaching_aid_sale` 支持 pono | ~8行 |
| `main.js` | 画具购买面板加输入框 | ~5行 |

**总计**：约 60 行变更，集中在 2 个文件。

---

## 10. 验收标准

- [ ] 交易订单列表中，每行显示课程金额和商品金额
- [ ] 有教材包的订单：课程金额 + 商品金额 ≈ 订单金额（±0.01 舍入误差）
- [ ] 无教材包的订单：课程金额 = 订单金额，商品金额 = ¥0.00
- [ ] 合计行正确汇总
- [ ] 订单详情弹窗同步展示课程金额和商品金额
- [ ] 订单详情独立页面同步展示
- [ ] 画具销售可关联父订单号（可选）
- [ ] 空列表时 colspan 正确
- [ ] `php -l` 语法检查通过
- [ ] curl API 验证新字段有实际值
