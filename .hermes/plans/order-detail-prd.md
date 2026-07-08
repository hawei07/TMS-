# TMS交易订单列表 —「查看详情」功能 PRD

> 版本：v1.0  
> 日期：2026-07-08  
> 作者：Product Manager  
> 项目：TMS管理系统（D:\market-system-php\，PHP 8.4 + MySQL 8.4 + Vanilla JS）

---

## 一、功能概述

在交易订单列表（`#panel-orders`）中，为每行子订单增加「查看详情」按钮。点击后弹出详情弹窗，展示两大类信息：

1. **报价单详情**：该笔订单所属录单（同一 `parent_order_no`）的全部报价项明细
2. **支付方式详情**：现金 / 美团 / 账户余额各自的支付金额及合计

**目标用户**：教务人员、财务人员，需要快速查看某笔订单的完整报价构成和支付构成。

---

## 二、功能交互设计

### 2.1 入口

- **位置**：订单列表每行末「操作」列
- **形式**：蓝色文字链接按钮「详情」
- **当前操作列**内容：`作废` 按钮（当 `is_voided === '否'` 时显示）
- **改动后**：操作列显示 `作废` | `详情` 两个按钮，水平排列

### 2.2 点击行为

| 步骤 | 行为 |
|------|------|
| 1 | 点击某子订单行的「详情」按钮 |
| 2 | 前端调用 API 获取该 `parent_order_no` 对应的完整数据 |
| 3 | 显示 Loading 态（弹窗内容区显示"加载中..."） |
| 4 | 数据返回后渲染弹窗内容 |
| 5 | 用户可点击关闭按钮、遮罩层、或 ESC 键关闭 |

### 2.3 弹窗样式

- **类名**：`.modal-overlay` + `.modal.modal-lg`（风格统一现有弹窗体系）
- **宽度**：`max-width: 700px`（介于普通弹窗和 modal-xl 之间）
- **圆角**：8px（与现有弹窗一致）
- **Header**：标题「订单详情」+ 右侧关闭按钮 `×`
- **Body**：两个 section，纵向排列

### 2.4 弹窗布局

```
┌─────────────────────────────────────────────────────┐
│  订单详情                                          × │  ← modal-header
├─────────────────────────────────────────────────────┤
│  ┌─ 基本信息（只读摘要）─────────────────────────┐  │
│  │  学员：张三 | 学号：2024000001                  │  │
│  │  父订单号：2024070800123456                      │  │
│  │  课程：数学思维 | 校区：曲江龙湖                  │  │
│  └───────────────────────────────────────────────┘  │
│                                                      │
│  ┌─ 📋 报价明细 ─────────────────────────────────┐  │
│  │  ┌──────────────────────────────────────────┐  │  │
│  │  │ 报价项名称 │课时│ 单价 │ 实际价格 │优惠方案│优惠券││
│  │  ├──────────────────────────────────────────┤  │  │
│  │  │ 基础课程   │ 30 │ ¥200│ ¥5,400  │ 暑期优惠│  -  ││  │
│  │  │ 进阶课程   │ 20 │ ¥250│ ¥4,500  │   -    │满减券││  │
│  │  ├──────────────────────────────────────────┤  │  │
│  │  │          合计│ 50 │     │ ¥9,900  │       │     ││  │
│  │  └──────────────────────────────────────────┘  │  │
│  └───────────────────────────────────────────────┘  │
│                                                      │
│  ┌─ 💰 支付详情 ─────────────────────────────────┐  │
│  │  ┌──────────┬──────────┬──────────┬──────────┐  │  │
│  │  │  💵 现金  │  🟡 美团  │  💳 账户  │  📊 合计  │  │  │
│  │  │ ¥5,000   │ ¥3,000   │ ¥1,900   │ ¥9,900   │  │  │
│  │  └──────────┴──────────┴──────────┴──────────┘  │  │
│  └───────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────┤
│                              [ 关闭 ]               │  ← modal-footer（可选）
└─────────────────────────────────────────────────────┘
```

---

## 三、数据获取方案

### 3.1 方案 A：新增 API `get_order_detail`（推荐 ✅）

**新增 API**：`?action=get_order_detail&parent_order_no=XXX`

**后端逻辑**：
1. 根据 `parent_order_no` 查询 `orders` 表中所有同组子订单
2. 对每个子订单，通过 `plan_name` + `course_id` 关联 `price_plans` → `price_items` → LEFT JOIN `discount_plans` 和 `coupons` 获取优惠方案名称和优惠券名称
3. 查询 `parent_orders` 表获取父订单支付汇总
4. 汇总计算各支付方式总额

**返回 JSON 结构**：
```json
{
  "success": true,
  "data": {
    "parent_order_no": "2024070800123456",
    "student_name": "张三",
    "student_no": "2024000001",
    "course_name": "数学思维",
    "campus": "曲江龙湖",
    "enroll_time": "2024-07-08 14:30:00",
    "total_price": "9900.00",
    "total_lessons": 50,
    "payment": {
      "cash_amount": "5000.00",
      "meituan_amount": "3000.00",
      "account_amount": "1900.00",
      "total": "9900.00"
    },
    "items": [
      {
        "order_id": 123,
        "order_no": "2024070800123457",
        "item_name": "基础课程",
        "lesson_count": 30,
        "unit_price": "200.00",
        "actual_price": "5400.00",
        "discount_plan_name": "暑期优惠",
        "coupon_name": null,
        "cash_amount": "3000.00",
        "meituan_amount": "2000.00",
        "account_amount": "400.00",
        "pay_status": "已支付"
      },
      {
        "order_id": 124,
        "order_no": "2024070800123458",
        "item_name": "进阶课程",
        "lesson_count": 20,
        "unit_price": "250.00",
        "actual_price": "4500.00",
        "discount_plan_name": null,
        "coupon_name": "满减券",
        "cash_amount": "2000.00",
        "meituan_amount": "1000.00",
        "account_amount": "1500.00",
        "pay_status": "已支付"
      }
    ]
  }
}
```

### 3.2 方案 B：复用现有 `list_orders` API + 前端过滤

- 前端已持有当前页 `list_orders` 数据
- 根据被点击行的 `parent_order_no`，在本地数组中过滤出同组子订单
- 问题：前端无法获取优惠方案/优惠券名称（orders 表未冗余存储）

**缺点**：
- 跨页问题：同一 `parent_order_no` 的子订单可能不在当前页
- 缺失优惠信息：需额外 API 调用或前端手工拼接
- 缺少 `unit_price`（单价）字段（现有 list_orders 不返回）
- 缺少父订单汇总信息

### 3.3 推荐方案

**推荐方案 A（新增 API）**，理由：

| 维度 | 方案 A | 方案 B |
|------|--------|--------|
| 数据完整性 | ✅ 一次返回所有需要的数据 | ❌ 跨页缺失、缺少优惠信息 |
| 前端复杂度 | ✅ 调用一次 API 即可 | ❌ 需多次查询 + 跨页处理 |
| 后端复杂度 | 中等（一个查询+一次 JOIN 链） | 无需后端改动 |
| 性能 | 单次查询，4-5 表 JOIN | 可能多次请求 |
| 维护性 | 独立接口，职责清晰 | 逻辑分散在渲染函数中 |

---

## 四、优惠方案和优惠券数据来源分析

### 4.1 当前数据存储

| 表 | 字段 | 说明 |
|-----|------|------|
| `price_items` | `discount_plan_id` | 关联 `discount_plans.id` |
| `price_items` | `coupon_id` | 关联 `coupons.id` |
| `orders` | `plan_name` | 价格方案名称（冗余存储） |
| `orders` | `item_name` | 报价项名称（冗余存储） |
| `orders` | — | **没有** `discount_plan_id` / `coupon_id` |

### 4.2 三种获取方案

| 方案 | 做法 | 优点 | 缺点 |
|------|------|------|------|
| **C1：通过 plan_name 反查** | `orders.plan_name` → `price_plans.name` → `price_plans.id` → `price_items` → JOIN `discount_plans` / `coupons` | 不改表结构，不改写入逻辑 | 若 plan_name 重名（不同课程下同名方案）可能定位不准；需额外 JOIN |
| **C2：orders 表新增冗余字段** | 给 orders 表加 `discount_plan_id` 和 `coupon_id` 字段，pay_enroll 写入时一并写入 | 查询最简单，一次 JOIN | 需改表结构 + 改 pay_enroll 写入 + 历史数据回填；数据冗余 |
| **C3：price_items 新增冗余字段** | 不改 orders，在 pay_enroll 时额外写入一张关联表 | 不改 orders 表 | 多一张关联表 |

### 4.3 推荐：C1（通过 plan_name + item_name 反查）

**理由**：
- **零数据库变更**：不需要 ALTER TABLE，不需要历史数据回填
- **写入逻辑不变**：pay_enroll 不需要改
- **查询准确度**：在 `get_order_detail` 中通过 `plan_name = orders.plan_name AND item_name = price_items.name AND price_plans.course_id = orders.course_id` 三条件联合定位，唯一性可靠（同一课程下 plan_name + item_name 组合唯一）

**SQL 示意**：
```sql
SELECT o.*, pi.unit_price,
       d.name AS discount_plan_name,
       c.name AS coupon_name
FROM orders o
LEFT JOIN price_plans pp ON pp.name = o.plan_name AND pp.course_id = o.course_id
LEFT JOIN price_items pi ON pi.plan_id = pp.id AND pi.name = o.item_name
LEFT JOIN discount_plans d ON pi.discount_plan_id = d.id
LEFT JOIN coupons c ON pi.coupon_id = c.id
WHERE o.parent_order_no = :pono
ORDER BY o.id
```

**注意**：
- 若某条历史订单对应的 price_plan / price_item 已被删除或改名，`discount_plan_name` 和 `coupon_name` 返回 `null`，前端显示 `-`，完全降级处理
- 若同一课程下存在同名 plan + 同名 item，`LEFT JOIN` 可能返回多条匹配 → 用子查询 `LIMIT 1` 或 `GROUP BY` 去重

---

## 五、后端改动范围

### 5.1 新增 API

**文件**：`index.php`

**位置**：在 `list_orders` case 之后（约 3958 行附近）新增一个 case：

```php
case 'get_order_detail':
```

**功能**：
1. 接收 `parent_order_no` 参数（GET）
2. 查询该父订单下所有子订单
3. JOIN price_plans / price_items / discount_plans / coupons 获取优惠信息
4. 查询 parent_orders 表获取父订单汇总
5. 聚合支付方式总额
6. 返回 JSON

**SQL（两条）**：
```sql
-- 1. 子订单+优惠信息
SELECT o.id, o.order_no, o.item_name, o.lesson_count, o.actual_price,
       o.cash_amount, o.meituan_amount, o.account_amount,
       o.pay_status, o.plan_name, o.course_id,
       pi.unit_price,
       d.name AS discount_plan_name,
       c.name AS coupon_name
FROM orders o
LEFT JOIN price_plans pp ON pp.name = o.plan_name AND pp.course_id = o.course_id
LEFT JOIN price_items pi ON pi.plan_id = pp.id AND pi.name = o.item_name
LEFT JOIN discount_plans d ON pi.discount_plan_id = d.id
LEFT JOIN coupons c ON pi.coupon_id = c.id
WHERE o.parent_order_no = :pono
ORDER BY o.id

-- 2. 父订单基本信息
SELECT * FROM parent_orders WHERE parent_order_no = :pono
```

### 5.2 改动量

| 文件 | 改动 | 行数 |
|------|------|------|
| `index.php` | 新增 `case 'get_order_detail':` | ~50 行 |

---

## 六、前端改动范围

### 6.1 HTML：新增弹窗

**文件**：`index.php`

**位置**：在最后一个 `<div class="modal-overlay">` 之后（约 7935 行之后），`</body>` 之前

```html
<!-- 弹窗：订单详情 -->
<div class="modal-overlay" id="modal-order-detail">
    <div class="modal modal-lg" style="max-width:700px;">
        <div class="modal-header">
            <h3>订单详情</h3>
            <button class="modal-close" onclick="closeModal('modal-order-detail')">&times;</button>
        </div>
        <div class="modal-body" id="order-detail-body">
            <div style="text-align:center;color:#999;padding:30px;">加载中...</div>
        </div>
    </div>
</div>
```

### 6.2 HTML：操作列增加「详情」按钮

**文件**：`index.php`

**位置**：`renderOrderTable()` 函数在 `main.js`（约 6851 行）

**改动**：操作列 `<td>` 改为：

```js
<td>
    ${r.is_voided === '否' 
        ? `<button class="btn btn-danger btn-sm" onclick="voidOrder(${r.id})" style="font-size:11px;padding:1px 6px;">作废</button> ` 
        : ''}
    <button class="btn btn-link btn-sm" onclick="showOrderDetail('${esc(r.parent_order_no)}')" style="font-size:11px;padding:1px 6px;color:#1890ff;">详情</button>
</td>
```

### 6.3 JS：新增函数

**文件**：`static/js/main.js`

| 函数名 | 功能 | 行数 |
|--------|------|------|
| `showOrderDetail(parentOrderNo)` | 打开弹窗，调用 API，渲染内容 | ~30 行 |
| `renderOrderDetail(data)` | 渲染弹窗 HTML（基本信息 + 报价明细表 + 支付卡片） | ~80 行 |

**`showOrderDetail` 逻辑**：
1. `openModal('modal-order-detail')`
2. 设置 body 为「加载中...」
3. `fetch(API_BASE + 'get_order_detail&parent_order_no=' + encodeURIComponent(parentOrderNo))`
4. 调用 `renderOrderDetail(data)`

**`renderOrderDetail` 逻辑**：
- 顶部：基本信息卡片（学员、学号、父订单号、课程、校区、报名时间）
- 中间：报价明细 `<table>`，列：报价项名称 | 课时数 | 单价 | 实际价格 | 优惠方案 | 优惠券
  - 最后一行：合计行（课时合计 + 金额合计）
- 底部：支付详情（4 格卡片：现金 / 美团 / 账户 / 合计）

### 6.4 改动量汇总

| 文件 | 改动 | 行数 |
|------|------|------|
| `index.php` | 新增弹窗 HTML | ~20 行 |
| `main.js` | 修改 `renderOrderTable` 操作列 | 改 ~5 行 |
| `main.js` | 新增 `showOrderDetail()` | ~30 行 |
| `main.js` | 新增 `renderOrderDetail()` | ~80 行 |

---

## 七、弹窗关闭行为

| 方式 | 行为 |
|------|------|
| 点击 `×` 关闭按钮 | 调用 `closeModal('modal-order-detail')` |
| 点击遮罩层（弹窗背景） | `closeModal` 移除 `.show` class |
| 按 ESC 键 | 需额外添加 `keydown` 监听（可选，可在后续迭代实现） |

---

## 八、特殊情况处理

| 场景 | 处理方式 |
|------|----------|
| `parent_order_no` 为空 | 该子订单没有父订单关联（如单独创建），仅展示该单条报价项 |
| 同 parent_order_no 下只有 1 条子订单 | 正常展示（表只有 1 行） |
| 优惠方案/优惠券查询为 null | 显示 `-` |
| 某条子订单 `pay_status !== '已支付'` | 正常展示支付额（可能为 0），备注支付状态 |
| API 请求失败 | 弹窗内显示错误提示「加载失败，请重试」 |
| 数据量很大（如一个父订单下有 20+ 个子订单） | 报价明细区域设 `max-height` + 滚动 |

---

## 九、CSS 新增样式

**文件**：`static/css/style.css`

```css
/* 订单详情弹窗 - 基本信息卡片 */
.order-detail-info {
    background: #f8f9fb;
    border: 1px solid #e8ecf1;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px 24px;
    font-size: 13px;
}
.order-detail-info span { color: #666; }
.order-detail-info strong { color: #1a1a2e; }

/* 订单详情弹窗 - section 标题 */
.order-detail-section-title {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a2e;
    margin-bottom: 10px;
    padding-bottom: 6px;
    border-bottom: 1px solid #f0f0f0;
}

/* 订单详情弹窗 - 报价明细表 */
.order-detail-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    margin-bottom: 12px;
}
.order-detail-table th {
    background: #f8f9fb;
    padding: 8px 10px;
    text-align: left;
    font-weight: 600;
    color: #666;
    border-bottom: 1px solid #e8ecf1;
}
.order-detail-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #f0f0f0;
}
.order-detail-table .col-num { text-align: right; }
.order-detail-table .row-total {
    font-weight: 700;
    background: #fafafa;
}

/* 订单详情弹窗 - 支付卡片行 */
.order-detail-payment-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}
.order-detail-payment-card {
    background: #f8f9fb;
    border: 1px solid #e8ecf1;
    border-radius: 8px;
    padding: 14px 16px;
    text-align: center;
}
.order-detail-payment-card .payment-label {
    font-size: 12px;
    color: #999;
    margin-bottom: 4px;
}
.order-detail-payment-card .payment-amount {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a2e;
}
.order-detail-payment-card.payment-total {
    background: linear-gradient(135deg, #7c3aed, #a855f7);
    border-color: #7c3aed;
}
.order-detail-payment-card.payment-total .payment-label,
.order-detail-payment-card.payment-total .payment-amount {
    color: #fff;
}
```

---

## 十、实现优先级和分阶段建议

### 第一阶段（MVP）

- 新增 API `get_order_detail`
- HTML 弹窗骨架
- `renderOrderTable` 操作列增加「详情」按钮
- 报价明细表格 + 支付卡片
- 基本信息展示

### 第二阶段（优化）

- ESC 键关闭弹窗
- 报价明细表格 sticky 表头
- 加载/错误态动画

### 第三阶段（增强）

- 在弹窗中增加「转退费」快捷操作
- 导出该笔订单明细为 PDF

---

## 十一、验收标准

| # | 标准 | 验收方式 |
|---|------|----------|
| 1 | 订单列表中每行均显示「详情」按钮，与「作废」按钮同列 | 人工检查 |
| 2 | 点击「详情」弹出弹窗，显示正确的报价明细和支付详情 | 人工 + 数据校验 |
| 3 | 报价明细表包含：报价项名称、课时数、单价、实际价格、优惠方案、优惠券 | 人工检查 |
| 4 | 支付详情卡片显示：现金、美团、账户余额、合计 | 人工 + 算术校验 |
| 5 | 合计行课时总数和金额总数正确 | 对比父订单表数据 |
| 6 | 点击关闭按钮或遮罩层可关闭弹窗 | 人工测试 |
| 7 | 对于无 `parent_order_no` 的订单，仅展示单条报价项 | 边缘测试 |
| 8 | 优惠方案/优惠券为空的场景正常显示 `-` | 边缘测试 |
| 9 | API 返回 JSON 格式正确，字段齐全 | Postman / curl 测试 |
