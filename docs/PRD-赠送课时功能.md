# 报价单赠送课时功能 — 完整PRD文档

> 版本：v1.0  
> 日期：2026-07-09  
> 项目：D:/market-system-php/  
> 技术栈：PHP 8.4 + MySQL 8.4.9 + Vanilla JS  
> 服务地址：127.0.0.1:5001

---

## 目录

1. [需求概述](#1-需求概述)
2. [数据库变更](#2-数据库变更)
3. [API 变更清单](#3-api-变更清单)
4. [前端变更清单](#4-前端变更清单)
5. [录单流程变更（pay_enroll）](#5-录单流程变更pay_enroll)
6. [边界情况](#6-边界情况)
7. [实现步骤（按依赖顺序）](#7-实现步骤按依赖顺序)
8. [验证清单](#8-验证清单)

---

## 1. 需求概述

### 功能1：报价单新增赠送课时字段

- 在 `price_items` 表新增 `gifted_lessons` 字段（INT，默认 0）
- 在新增/编辑报价单弹窗（`modal-price-item`）中增加步进器控件
- 步进器规则：只能选偶数（0, 2, 4, 6...），最小 0，步进 2
- `save_price_plan` API 支持该字段的写入和读取
- `list_price_plans` / `get_course_plans` 等 API 的 SELECT 和 LEFT JOIN 返回该字段

### 功能2：录单时自动生成赠送课包

- 学员购买了带赠送课时（`gifted_lessons > 0`）的报价单后
- `pay_enroll` 在创建正常订单的同时，额外创建赠送课时订单：
  - `actual_price = 0`
  - `lesson_count = gifted_lessons`
  - 同一 `parent_order_no`
  - `order_type = '赠送'`
  - 关联同一学员、同一课程
- 在学员管理-报读课程中，赠送课包作为独立记录展示，实付价格为 0

### 关键约束

- **小课包不需要赠送课时**：步进器不显示或禁用
- **赠送课时不计入实际价格公式**：`actual_price` 公式不受 `gifted_lessons` 影响
- **赠送课包不参与退费计算**：order_type='赠送' 的订单在退费逻辑中排除

---

## 2. 数据库变更

### 2.1 price_items 表新增字段

参照现有兼容块模式（`index.php` ~618-622 行），在 `product_coupon_id` 之后添加：

```sql
ALTER TABLE price_items ADD COLUMN gifted_lessons INT DEFAULT 0 AFTER product_coupon_id;
```

**PHP 兼容块（`index.php`，紧接 ~622 行之后）**：

```php
// 报价单赠送课时字段
try { $db->exec("ALTER TABLE price_items ADD COLUMN gifted_lessons INT DEFAULT 0 AFTER product_coupon_id"); } catch (PDOException $e) {}
```

### 2.2 orders 表无需新增字段

赠送课包通过以下现有字段区分：
- `order_type = '赠送'`：标识为赠送课时订单
- `actual_price = 0`：实付价格为 0
- `item_name`：沿用原报价单名称（前端展示时可通过 `order_type` 追加赠送标记）

### 2.3 无需数据迁移

`gifted_lessons` 默认为 0，现有数据无需回填。

---

## 3. API 变更清单

根据 TMS 陷阱 #83（price_items 加新字段需同步修改 N 个位置），以下位置必须全部同步。

| # | 位置 | 文件 | 行号（约） | 改动内容 |
|---|------|------|-----------|----------|
| **1** | `list_price_plans` SQL | `index.php` | ~2413 | `SELECT pi.*` 自动包含新字段，**无需改动** |
| **2** | `get_course_plans` SQL | `index.php` | ~2436 | `SELECT pi.*` 自动包含新字段，**无需改动** |
| **3** | `get_order_detail` SQL | `index.php` | ~4311 | 当前查询 `orders` 表（非 `price_items`），**无需改动** |
| **4** | `get_order_detail` items 数组构建 | `index.php` | ~4397-4417 | **无需改动**（赠课标识通过 `order_type` 传递） |
| **5** | `save_price_plan` INSERT | `index.php` | ~2678 | **需改动**：INSERT 列名 + 值 |
| **6** | `pay_enroll` INSERT orders | `index.php` | ~2521 | **需改动**：新增赠课订单创建逻辑 |
| **7** | `get_student_courses` SQL | `index.php` | ~3921 | `SELECT ... FROM orders` 不涉及 price_items，**无需改动**（赠课订单的 `order_type='赠送'` 自动返回） |
| **8** | `pay_enroll` 支付金额校验 | `index.php` | ~2482-2484 | **需改动**：排除赠送课时的影响 |

### 3.1 save_price_plan — INSERT 语句（第 2678 行）

**现有代码**（~2678 行）：
```php
$db->exec("INSERT INTO price_items (plan_id, name, lesson_count, unit_price, actual_price, discount_plan_id, coupon_id, teaching_aid_id, product_coupon_id, sort_order) VALUES ($planId, " . $db->quote($itemName) . ", $lessonCount, $unitPrice, $actualPrice, "
    . ($discountPlanId > 0 ? $discountPlanId : 'NULL') . ", "
    . ($couponId > 0 ? $couponId : 'NULL') . ", "
    . ($teachingAidId > 0 ? $teachingAidId : 'NULL') . ", "
    . ($productCouponId > 0 ? $productCouponId : 'NULL') . ", $sortOrder)");
```

**改为**：
```php
$giftedLessons = intval($item['gifted_lessons'] ?? 0);
$db->exec("INSERT INTO price_items (plan_id, name, lesson_count, unit_price, actual_price, discount_plan_id, coupon_id, teaching_aid_id, product_coupon_id, gifted_lessons, sort_order) VALUES ($planId, " . $db->quote($itemName) . ", $lessonCount, $unitPrice, $actualPrice, "
    . ($discountPlanId > 0 ? $discountPlanId : 'NULL') . ", "
    . ($couponId > 0 ? $couponId : 'NULL') . ", "
    . ($teachingAidId > 0 ? $teachingAidId : 'NULL') . ", "
    . ($productCouponId > 0 ? $productCouponId : 'NULL') . ", "
    . $giftedLessons . ", $sortOrder)");
```

### 3.2 pay_enroll — 完整重写（第 2451-2641 行）

详见 [第 5 节](#5-录单流程变更pay_enroll)。

### 3.3 aktual_price 公式不受影响

```
actual_price = MAX(0, unit_price - discount_plan_amount - coupon_amount + teaching_aid_price - product_coupon_amount)
```

`gifted_lessons` 不参与此公式，前端 4 个保存函数的 IIFE 重算公式**无需修改**。

---

## 4. 前端变更清单

### 4.1 HTML：modal-price-item 表单新增步进器（index.php ~8451 行之后）

在"课时数量"字段下方新增一个卡片区域（受小课包控制显隐）：

```html
<!-- 卡片：赠送课时（仅非小课包方案显示） -->
<div class="pi-card" id="pi-gift-card">
    <div class="pi-card-title">🎁 赠送课时</div>
    <div class="pi-card-body">
        <div class="form-group">
            <label>赠送课时数（选偶数）</label>
            <div class="stepper-group">
                <button type="button" class="stepper-btn" onclick="stepGiftedLessons(-2)">−</button>
                <input type="number" id="price-item-gifted-lessons" value="0" min="0" step="2" readonly>
                <button type="button" class="stepper-btn" onclick="stepGiftedLessons(2)">+</button>
            </div>
            <small style="color:#999;">最小 0，步进 2（只能选偶数），小课包不适用</small>
        </div>
    </div>
</div>
```

**位置**：紧接「基本信息」卡片的 `</div>`（~8454 行）之后。

### 4.2 前端 JS 函数变更

以下函数均位于 `static/js/main.js`，每个函数的 `items.map()` 都必须包含 `gifted_lessons` 字段。

| # | 函数 | 行号（约） | 改动内容 |
|---|------|-----------|----------|
| **1** | `addItem()` | ~3777 | 清空 `#price-item-gifted-lessons` 为 0；小课包时隐藏 `#pi-gift-card` |
| **2** | `saveItem()` | ~3797 | 读取 `gifted_lessons`；`items.map()` IIFE 中包含 `gifted_lessons`；`newItem` 中包含 |
| **3** | `saveInlineEdit()` | ~3530 | `items.map()` 编辑项 + 其他项 IIFE 中均包含 `gifted_lessons` |
| **4** | `deleteItem()` | ~3872 | `items.map()` IIFE 中包含 `gifted_lessons` |
| **5** | `savePlan()` | ~3706 | `items.map()` IIFE 中包含 `gifted_lessons` |
| **6** | `renderItemList()` | ~3251 | 表格新增「赠送课时」列（小课包显示 `—`） |
| **7** | `selectEnrollPlan()` | ~6240 | 录单报价明细表新增「赠送课时」列 |
| **8** | `renderOrderDetail()` | ~7114 | 订单详情弹窗中赠课订单特殊标记 |
| **9** | `renderOrderDetailPage()` | ~7200 | 订单详情全屏页面中赠课订单特殊标记 |
| **10** | `stepGiftedLessons()` | 新增 | 步进器回调函数 |
| **11** | `editItem()` | ~（需定位） | 编辑已有报价单时回填 `gifted_lessons` 值 |

#### 4.2.1 新增 `stepGiftedLessons(delta)` 函数

```javascript
function stepGiftedLessons(delta) {
    const el = document.getElementById('price-item-gifted-lessons');
    let val = parseInt(el.value) || 0;
    val = Math.max(0, val + delta);
    // 确保为偶数
    if (val % 2 !== 0) val = Math.max(0, val + (delta > 0 ? 1 : -1));
    el.value = val;
}
```

#### 4.2.2 `addItem()` 改动

```javascript
function addItem() {
    // ... 现有清空代码 ...
    document.getElementById('price-item-gifted-lessons').value = '0';
    
    // 小课包时隐藏赠送课时卡片
    const plan = currentPlans.find(p => p.id === currentSelectedPlanId);
    const isSmall = plan && plan.plan_type === '小课包';
    document.getElementById('pi-gift-card').style.display = isSmall ? 'none' : '';
    
    // ... 其余不变 ...
}
```

#### 4.2.3 `saveItem()` 改动

`items.map()` 中的 IIFE 增加 `gifted_lessons`：

```javascript
let items = (plan.items || []).map((item, idx) => ({
    // ... 现有字段 ...
    actual_price: (() => { /* 现有公式不变 */ })(),
    discount_plan_id: parseInt(item.discount_plan_id) || 0,
    coupon_id: parseInt(item.coupon_id) || 0,
    teaching_aid_id: parseInt(item.teaching_aid_id) || 0,
    product_coupon_id: parseInt(item.product_coupon_id) || 0,
    gifted_lessons: parseInt(item.gifted_lessons) || 0,  // 新增
    sort_order: idx
}));
```

`newItem` 中增加：

```javascript
const newItem = {
    // ... 现有字段 ...
    product_coupon_id: parseInt(document.getElementById('price-item-coupon').value) || 0,
    gifted_lessons: parseInt(document.getElementById('price-item-gifted-lessons').value) || 0,  // 新增
    sort_order: items.length
};
```

#### 4.2.4 `saveInlineEdit()` 改动

编辑项 return 中增加：
```javascript
gifted_lessons: parseInt(editedValues.gifted_lessons) || 0,
```

其他项 IIFE 中增加：
```javascript
gifted_lessons: parseInt(item.gifted_lessons) || 0,
```

#### 4.2.5 `deleteItem()` + `savePlan()` 改动

各自的 `items.map()` IIFE 中增加：
```javascript
gifted_lessons: parseInt(item.gifted_lessons) || 0,
```

#### 4.2.6 `renderItemList()` 改动

表头新增 `<th>赠送课时</th>`，表格行新增：
```javascript
<td>${isSmallPack ? '—' : (item.gifted_lessons || 0)}</td>
```

总计行需跳过 `gifted_lessons` 列（不参与 sum）——总计行只需加一个空 `<td></td>`。

#### 4.2.7 `selectEnrollPlan()` 改动

录单明细表新增「赠送课时」列：
```javascript
<td class="col-num">${item.gifted_lessons || 0}</td>
```

#### 4.2.8 订单详情渲染改动

`renderOrderDetail()` 和 `renderOrderDetailPage()` 中，对于 `order_type === '赠送'` 的订单行：
- `actual_price` 列显示 `¥0.00（赠送）` 并高亮
- 或使用不同背景色标记整行

---

## 5. 录单流程变更（pay_enroll）

### 5.1 当前流程（index.php ~2451-2641）

```
1. 解析参数（student_id, plan_id, course_id）
2. 校验方案存在、课程小课包类型
3. 读取 price_items 列表
4. 计算 totalPrice = SUM(actual_price)
5. 校验支付金额一致性
6. 余额扣款
7. 循环创建子订单（foreach items）
8. 自动发券+用券
9. 写入 parent_orders
10. 余额流水
11. 标记来源资源已转化
12. 升级学员类型
```

### 5.2 改动后流程

在步骤 7（循环创建子订单）之后，新增步骤 7b：

```
7a. 循环创建正常子订单（现有逻辑不变，赠送课时不计入 totalPrice）
7b. 循环创建赠送课包子订单（仅当 item.gifted_lessons > 0）
8. 自动发券+用券（跳过赠送课包）
...
```

### 5.3 关键改动点

#### 5.3.1 `totalPrice` 计算（~2481-2482 行）

**无需修改**。`totalPrice = SUM(actual_price)`，赠送课时 `actual_price` 在 `price_items` 中不涉及（赠送课时是额外课时，不改变报价单的 `actual_price`）。

支付金额校验（~2483-2485 行）也无需修改，因为赠送订单 `actual_price=0`，不影响支付总额。

#### 5.3.2 正常子订单创建（~2526-2568 行）

**无需修改**。现有循环正常创建每个报价单对应的订单。

#### 5.3.3 新增：赠送订单创建

在 `foreach ($items as $i => $item)` 循环结束（~2568 行 `}`）之后，紧接 `$totalLessons` 累加完成之后：

```php
// ============ 赠送课时订单创建 ============
foreach ($items as $item) {
    $giftedLessons = intval($item['gifted_lessons'] ?? 0);
    if ($giftedLessons <= 0) continue;
    
    // 赠送课包：actual_price = 0，lesson_count = gifted_lessons
    $orderNo = generateOrderNo($db);
    $giftStmt = $db->prepare("INSERT INTO orders 
        (student_id, course_id, plan_name, item_name, lesson_count, actual_price,
         cash_amount, meituan_amount, account_amount, paid_amount,
         order_no, parent_order_no, created_at, paid_at, order_type, campus,
         pay_status, is_voided,
         discount_plan_name, discount_plan_amount, coupon_name, coupon_amount,
         teaching_aid_name, teaching_aid_price, product_coupon_name, product_coupon_amount)
        VALUES 
        (:sid, :cid, :pn, :inm, :lc, :ap,
         :ca, :ma, :aa, :pa,
         :ono, :pono, :ct, :pat, :ot, :campus,
         :ps, :iv,
         :dpn, :dpa, :cn, :coa,
         :tan, :tap, :pcn, :pca)");
    
    $giftItemName = ($item['name'] ?? '报价单') . '（赠送）';
    $di = $itemDiscounts[$item['id']] ?? [];
    
    $giftStmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
    $giftStmt->bindValue(':cid', $courseId, PDO::PARAM_INT);
    $giftStmt->bindValue(':pn', $plan['name'], PDO::PARAM_STR);
    $giftStmt->bindValue(':inm', $giftItemName, PDO::PARAM_STR);
    $giftStmt->bindValue(':lc', $giftedLessons, PDO::PARAM_INT);
    $giftStmt->bindValue(':ap', 0, PDO::PARAM_STR);       // 实付 0
    $giftStmt->bindValue(':ca', 0, PDO::PARAM_STR);
    $giftStmt->bindValue(':ma', 0, PDO::PARAM_STR);
    $giftStmt->bindValue(':aa', 0, PDO::PARAM_STR);
    $giftStmt->bindValue(':pa', 0, PDO::PARAM_STR);
    $giftStmt->bindValue(':ono', $orderNo, PDO::PARAM_STR);
    $giftStmt->bindValue(':pono', $parentOrderNo, PDO::PARAM_STR);
    $giftStmt->bindValue(':ct', $n, PDO::PARAM_STR);
    $giftStmt->bindValue(':pat', $n, PDO::PARAM_STR);
    $giftStmt->bindValue(':ot', '赠送', PDO::PARAM_STR);   // 标识为赠送
    $giftStmt->bindValue(':campus', $campusName, PDO::PARAM_STR);
    $giftStmt->bindValue(':ps', '已支付', PDO::PARAM_STR);
    $giftStmt->bindValue(':iv', '否', PDO::PARAM_STR);
    // 优惠快照（沿用原报价单的优惠信息，但金额均为 0）
    $giftStmt->bindValue(':dpn', $di['dp_name'] ?? '', PDO::PARAM_STR);
    $giftStmt->bindValue(':dpa', 0, PDO::PARAM_STR);
    $giftStmt->bindValue(':cn', $di['cp_name'] ?? '', PDO::PARAM_STR);
    $giftStmt->bindValue(':coa', 0, PDO::PARAM_STR);
    $giftStmt->bindValue(':tan', $di['ta_name'] ?? '', PDO::PARAM_STR);
    $giftStmt->bindValue(':tap', 0, PDO::PARAM_STR);
    $giftStmt->bindValue(':pcn', $di['pc_name'] ?? '', PDO::PARAM_STR);
    $giftStmt->bindValue(':pca', 0, PDO::PARAM_STR);
    $giftStmt->execute();
    
    $orderIds[] = $db->lastInsertId();
    $childOrderNos[] = $orderNo;
    $totalLessons += $giftedLessons;
}
// ============ 赠送课时订单创建结束 ============
```

#### 5.3.4 自动发券跳过赠送课包（~2572-2593 行）

发券逻辑中 `$itemCouponId` 来自原始 `price_items`，赠送订单不参与，**无需修改**。

#### 5.3.5 学员类型升级排除赠送订单（~2632-2640 行）

```php
// 现有代码：
$hasNonXKB = $db->query("SELECT COUNT(*) FROM orders WHERE student_id=$studentId AND is_voided='否' AND order_type != '小课包' AND order_type != ''")->fetchColumn();

// 需改为（排除赠送类型）：
$hasNonXKB = $db->query("SELECT COUNT(*) FROM orders WHERE student_id=$studentId AND is_voided='否' AND order_type NOT IN ('小课包', '赠送') AND order_type != ''")->fetchColumn();
```

---

## 6. 边界情况

### 6.1 小课包处理

| 场景 | 行为 |
|------|------|
| 方案类型为「小课包」 | 赠送课时卡片隐藏；`stepGiftedLessons()` 不可用 |
| 课程标记为小课包 | 强制 `order_type='小课包'`，赠送逻辑不适用（`gifted_lessons` 在前端被隐藏无法设置） |
| 后端安全守卫 | `save_price_plan` 中可不额外校验，前端已隐藏；若需后端兜底：小课包方案 `gifted_lessons` 强制为 0 |

### 6.2 编辑已有报价单

| 场景 | 行为 |
|------|------|
| 编辑已有报价单（非小课包） | 回填 `gifted_lessons` 当前值到步进器 |
| 编辑已有报价单（小课包） | 卡片隐藏，`gifted_lessons` 保持为 0 |
| 方案从非小课包改为小课包 | `savePlan()` 中 `items.map()` 当前会清空所有优惠字段（现有行为），`gifted_lessons` 同理强制为 0 |
| 方案从小课包改为非小课包 | 已有项的 `gifted_lessons` 为 0，用户可重新设置 |

### 6.3 删除操作

| 场景 | 行为 |
|------|------|
| 删除带赠送课时的报价单 | 正常删除，无额外影响 |
| 删除方案（级联删除报价单） | `delete_price_plan` 中 `DELETE FROM price_items WHERE plan_id=$planId` 已覆盖 |

### 6.4 赠课订单的特殊处理

| 场景 | 行为 |
|------|------|
| 退费排除 | `submit_refund` 中查询可用订单时排除 `order_type='赠送'`（或 `actual_price=0`） |
| 课耗排除 | `save_class_attendance` 扣课查询中排除 `order_type='赠送'` — 赠课课时只作为额外课时消耗 |
| 学员课耗展示 | `get_student_courses` 中赠课订单正常展示，`actual_price=0`，`remaining_amount=0`，`consumed_amount=0` |
| 订单详情 | 赠课订单行标记「赠送」标签，实际价格为 ¥0.00 |
| 订单列表筛选 | 如需在订单管理面板中区分，可用 `order_type` 筛选 |

### 6.5 父订单汇总

| 字段 | 行为 |
|------|------|
| `parent_orders.total_lessons` | 包含赠课课时（已在循环中累加） |
| `parent_orders.total_price` | 不包含赠课金额（始终为 0） |
| `parent_orders.child_order_nos` | 包含赠课子订单号 |

### 6.6 偶数值校验

- **前端**：`stepGiftedLessons()` 中步进 ±2 并保证偶数
- **前端**：步进器 readonly，防止手动输入奇数
- **后端兜底**（可选）：`save_price_plan` 中 `$giftedLessons = intval($item['gifted_lessons'] ?? 0)`，可加 `if ($giftedLessons % 2 !== 0) $giftedLessons = max(0, $giftedLessons - 1);`

### 6.7 赠送课时为 0 时不生成赠课订单

`pay_enroll` 中 `if ($giftedLessons <= 0) continue;` 保证 0 时不创建。

---

## 7. 实现步骤（按依赖顺序）

| 步骤 | 位置 | 说明 | 依赖 |
|------|------|------|------|
| **S1** | `index.php` ~622 行之后 | 添加 ALTER TABLE 兼容块（`gifted_lessons`） | 无 |
| **S2** | `index.php` ~2678 行 | `save_price_plan` INSERT 增加 `gifted_lessons` 列 | S1 |
| **S3** | `index.php` 8436-8501 行 | HTML：`modal-price-item` 表单新增赠送课时卡片+步进器 | S2 |
| **S4** | `static/js/main.js` 新增 | `stepGiftedLessons(delta)` 函数 | S3 |
| **S5** | `static/js/main.js` ~3777 行 | `addItem()`：清空步进器 + 小课包隐藏卡片 | S3, S4 |
| **S6** | `static/js/main.js` ~3797 行 | `saveItem()`：items.map() 和 newItem 包含 `gifted_lessons` | S5 |
| **S7** | `static/js/main.js` ~3530 行 | `saveInlineEdit()`：items.map() 包含 `gifted_lessons` | S6 |
| **S8** | `static/js/main.js` ~3872 行 | `deleteItem()`：items.map() 包含 `gifted_lessons` | S6 |
| **S9** | `static/js/main.js` ~3706 行 | `savePlan()`：items.map() 包含 `gifted_lessons` | S6 |
| **S10** | `static/js/main.js` ~3251 行 | `renderItemList()`：表格新增赠送课时列 | S6 |
| **S11** | `static/js/main.js` ~6240 行 | `selectEnrollPlan()`：录单明细表新增赠送课时列 | S6 |
| **S12** | `index.php` ~2451-2641 行 | `pay_enroll`：赠课订单创建逻辑 | S1 |
| **S13** | `index.php` ~2633-2638 行 | 学员类型升级 SQL 排除 `order_type='赠送'` | S12 |
| **S14** | `index.php` `submit_refund` | 退费查询排除 `order_type='赠送'` 订单 | S12 |
| **S15** | `index.php` `save_class_attendance` | 扣课三级优先级查询排除 `order_type='赠送'` | S12 |
| **S16** | `static/js/main.js` ~7114 行 | `renderOrderDetail()`：赠课订单标记 | S12 |
| **S17** | `static/js/main.js` ~7200 行 | `renderOrderDetailPage()`：赠课订单标记 | S12 |
| **S18** | `index.php` 重启服务 | `taskkill /f /im php.exe && php -S 127.0.0.1:5001` | S1-S17 |
| **S19** | curl / 浏览器 | 全链路验证 | S18 |

---

## 8. 验证清单

### 8.1 数据库验证

```bash
# 确认字段存在
mysql -u root -e "SHOW COLUMNS FROM price_items LIKE 'gifted_lessons';" tms
```

### 8.2 API 验证

```bash
# 1. 创建含赠送课时的报价单
curl -s "http://127.0.0.1:5001/?action=list_price_plans&course_id=1" | python -c "import sys,json; d=json.loads(sys.stdin.buffer.read().decode('utf-8-sig')); print([i.get('gifted_lessons') for p in d['data'] for i in p['items']])"

# 2. 录单后检查赠课订单
curl -s "http://127.0.0.1:5001/?action=get_student_courses&student_id=1" | python -c "import sys,json; d=json.loads(sys.stdin.buffer.read().decode('utf-8-sig')); [print(r['order_type'],r['actual_price'],r['lesson_count']) for r in d['data']]"
```

### 8.3 前端验证

| 测试项 | 预期 |
|--------|------|
| 小课包方案 → 新增报价单 | 赠送课时卡片隐藏 |
| 非小课包方案 → 新增报价单 | 赠送课时卡片显示，步进器初始为 0 |
| 点击步进器 + | 值变为 2, 4, 6... |
| 点击步进器 − | 最小为 0 |
| 保存报价单（赠送=4） | 列表显示赠送课时=4 |
| inline 编辑修改赠送课时 | 保存后生效 |
| 删除报价单 | 其他项正常 |
| 录单选择含赠送的方案 | 明细表显示赠送课时列 |
| 录单支付 | 正常订单 + 赠课订单均创建，parent_order_no 相同 |
| 学员报读课程列表 | 赠课订单独立显示，actual_price=0 |
| 订单详情 | 赠课订单标记「赠送」 |

---

## 附录 A：影响范围速查

### 不受 `gifted_lessons` 影响的逻辑

- `actual_price` 公式（4 个 JS 函数的 IIFE 重算）
- `update_price_items_actual` 批量更新 SQL（~2833/3019 行）
- `discount_plans` / `coupons` / `teaching_aids` 的删改联动更新
- 优惠管理模块全部 API

### 受 `order_type='赠送'` 影响的逻辑

| API | 影响 | 处理方式 |
|-----|------|----------|
| `get_student_courses` | 赠课订单展示 | 自动返回，前端按 `actual_price=0` 展示 |
| `submit_refund` | 退费排除赠课 | SQL WHERE 加 `AND order_type != '赠送'` |
| `save_class_attendance` | 扣课排除赠课 | 三级优先级 WHERE 加 `AND order_type != '赠送'` |
| `list_orders` | 订单列表 | `order_type` 列展示「赠送」标签 |
| 学员类型升级 | 排除赠课 | `NOT IN ('小课包', '赠送')` |

---

## 附录 B：CSS 建议（步进器样式）

```css
.stepper-group {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.stepper-btn {
    width: 36px;
    height: 36px;
    font-size: 18px;
    font-weight: bold;
    border: 1px solid #d4d4d8;
    border-radius: 6px;
    background: #f4f4f5;
    cursor: pointer;
    color: #52525b;
    user-select: none;
}
.stepper-btn:hover { background: #e4e4e7; }
.stepper-btn:active { background: #d4d4d8; }
#price-item-gifted-lessons {
    width: 80px;
    text-align: center;
    font-size: 16px;
    font-weight: 600;
    border: 1px solid #d4d4d8;
    border-radius: 6px;
    padding: 6px 0;
    background: #fafafa;
}
```
