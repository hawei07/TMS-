# PRD：报价单(price_items)关联优惠方案 + 优惠券

> **版本**: v1.0 | **日期**: 2026-07-07 | **状态**: 待确认
>
> **关联模块**: 价格管理 / 优惠管理 / 优惠券管理
>
> **关键更正**: 关联加在 `price_items`（报价单），**不是** `price_plans`（价格方案）。
> 每条报价单可独立选择不同的优惠方案和优惠券。

---

## 1. 需求概述

### 1.1 业务背景

当前价格方案下的报价单仅包含：名称、课时数、单价、实际价格。用户在报名时无法直接对单条报价单应用优惠方案和优惠券，需要在报名流程外另行处理折扣，体验碎片化。

### 1.2 需求描述

在「课程管理 → 价格方案 → 报价单列表」的**每条报价单编辑弹窗**中：

| # | 新增功能 | 数据来源 | 筛选条件 |
|---|---------|---------|---------|
| 1 | **优惠方案** 下拉选择 | `discount_plans` 表 | 按当前价格方案的 `plan_type` 过滤（新报→`新报`方案，续费→`续费`方案） |
| 2 | **课时优惠券** 下拉选择 | `coupons` 表 | 仅 `coupon_type='课程券'` |

### 1.3 可用性约束

- 两个字段均**可选**（默认空 = 不使用优惠/券）
- 每条报价单**独立选择**，互不影响
- 报价单列表中**展示**已选的优惠方案名和券名
- 后续报名支付（`pay_enroll`）将基于此关联自动计算折扣

### 1.4 影响范围

| 影响层 | 变更项 |
|--------|--------|
| 数据库 | `price_items` 表 +2 字段 |
| 后端 API | `list_price_plans`、`get_course_plans`、`save_price_plan`、`pay_enroll`（V2） |
| 前端 HTML | `modal-price-item` 弹窗 +2 form-group |
| 前端 JS | `addItem()`、`editItem()`、`saveItem()`、`renderItemList()` 及两个新 API 调用 |

---

## 2. 数据库变更

### 2.1 price_items 新增字段

```sql
ALTER TABLE price_items ADD COLUMN discount_plan_id INT DEFAULT NULL AFTER actual_price;
ALTER TABLE price_items ADD COLUMN coupon_id INT DEFAULT NULL AFTER discount_plan_id;
```

### 2.2 兼容块（index.php，price_items 建表语句后）

```php
// 兼容已有数据库：price_items 添加 discount_plan_id / coupon_id
try { $db->exec("ALTER TABLE price_items ADD COLUMN discount_plan_id INT DEFAULT NULL AFTER actual_price"); } catch (PDOException $e) {}
try { $db->exec("ALTER TABLE price_items ADD COLUMN coupon_id INT DEFAULT NULL AFTER discount_plan_id"); } catch (PDOException $e) {}
```

> ⚠ 遵循 TMS 陷阱 #26：ALTER TABLE 必须放在 CREATE TABLE 之后、业务逻辑之前。

### 2.3 完整表结构（变更后）

```sql
price_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plan_id INT NOT NULL,
    name VARCHAR(500) NOT NULL,
    lesson_count INT NOT NULL,
    unit_price REAL NOT NULL,
    actual_price REAL NOT NULL,
    discount_plan_id INT DEFAULT NULL,   -- NEW：关联 discount_plans.id
    coupon_id INT DEFAULT NULL,           -- NEW：关联 coupons.id
    sort_order INT DEFAULT 0
)
```

> 不设 FOREIGN KEY（与现有 TMS 惯例一致，price_items 表无外键约束），通过应用层保证引用完整性。

---

## 3. 后端 API 变更

### 3.1 `list_price_plans` — 查询价格方案列表（报价单带优惠信息）

**当前代码位置**: `index.php:2334-2346`

**改造后**（item 子查询改用 LEFT JOIN）：

```php
case 'list_price_plans':
    $courseId = intval($_GET['course_id'] ?? 0);
    if (!$courseId) json(['error' => '缺少 course_id']);
    $plans = [];
    $planRes = $db->query("SELECT * FROM price_plans WHERE course_id=$courseId ORDER BY sort_order, id");
    while ($plan = $planRes->fetch(PDO::FETCH_ASSOC)) {
        $items = [];
        $itemRes = $db->query(
            "SELECT pi.*, 
                dp.name AS discount_plan_name, dp.discount_amount,
                c.name AS coupon_name, c.discount_amount AS coupon_amount
             FROM price_items pi
             LEFT JOIN discount_plans dp ON pi.discount_plan_id = dp.id
             LEFT JOIN coupons c ON pi.coupon_id = c.id
             WHERE pi.plan_id=" . intval($plan['id']) . " 
             ORDER BY pi.sort_order, pi.id"
        );
        while ($item = $itemRes->fetch(PDO::FETCH_ASSOC)) $items[] = $item;
        $plan['items'] = $items;
        $plans[] = $plan;
    }
    json(['data' => $plans]);
```

**变更点**：
- `SELECT pi.*` → `SELECT pi.*, dp.name AS discount_plan_name, ...`
- 两个 LEFT JOIN

### 3.2 `get_course_plans` — 同 3.1 改造

**当前代码位置**: `index.php:2348-2360`

同样的 LEFT JOIN 模式。

### 3.3 `save_price_plan` — 保存报价单时接收新字段

**当前代码位置**: `index.php:2515-2548`，items 循环插入部分（~2539-2547）

**改造后**（INSERT 语句增加两个字段）：

```php
foreach ($items as $idx => $item) {
    $itemName = trim($item['name'] ?? '');
    $lessonCount = intval($item['lesson_count'] ?? 0);
    $unitPrice = floatval($item['unit_price'] ?? 0);
    $actualPrice = floatval($item['actual_price'] ?? $unitPrice);
    $sortOrder = intval($item['sort_order'] ?? $idx);
    $discountPlanId = intval($item['discount_plan_id'] ?? 0);
    $couponId = intval($item['coupon_id'] ?? 0);
    if (!$itemName || $lessonCount <= 0) continue;
    $db->exec("INSERT INTO price_items (plan_id, name, lesson_count, unit_price, actual_price, discount_plan_id, coupon_id, sort_order) 
        VALUES ($planId, " . $db->quote($itemName) . ", $lessonCount, $unitPrice, $actualPrice, " 
        . ($discountPlanId > 0 ? $discountPlanId : 'NULL') . ", " 
        . ($couponId > 0 ? $couponId : 'NULL') . ", $sortOrder)");
}
```

> ⚠ 注意：`discount_plan_id` / `coupon_id` 为 0 时视为 NULL。

### 3.4 `pay_enroll` — 折扣计算（V2，本期不做）

> 本期仅完成关联存储和展示。支付时基于 `discount_plan_id` + `coupon_id` 自动计算实际应付金额的逻辑留到 V2。
>
> V2 需在 `pay_enroll` (index.php:2362-2513) 中：
> 1. 对每条 item 查询关联的 discount_plan.discount_amount 和 coupon.discount_amount
> 2. 折扣逻辑（叠加/叠加上限等）需与产品确认后实施

---

## 4. 前端变更

### 4.1 HTML：`modal-price-item` 弹窗增加两个 select

**当前代码位置**: `index.php:7827-7837`

**改造后**（在 `actual_price` 的 form-group 之后插入）：

```html
<div class="modal-overlay" id="modal-price-item">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-price-item-title">新增报价单</h3>
            <button class="modal-close" onclick="closeModal('modal-price-item')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="edit-price-item-id">
            <div class="form-group"><label>报价单名称 <span class="required">*</span></label><input type="text" id="price-item-name" maxlength="50" placeholder="如：32课时包"></div>
            <div class="form-group"><label>课时数量 <span class="required">*</span></label><input type="number" id="price-item-lesson-count" min="1" placeholder="请输入课时数量"></div>
            <div class="form-group"><label>课时价格 <span class="required">*</span></label><input type="number" id="price-item-unit-price" step="0.01" min="0" placeholder="请输入课时价格" oninput="onUnitPriceChange()"></div>
            <div class="form-group"><label>实际支付价格</label><input type="number" id="price-item-actual-price" step="0.01" min="0" readonly style="background:#f5f7fa;"></div>
            <!-- NEW: 优惠方案 -->
            <div class="form-group"><label>优惠方案</label><select id="price-item-discount-plan"><option value="">不使用优惠方案</option></select></div>
            <!-- NEW: 课时优惠券 -->
            <div class="form-group"><label>课时优惠券</label><select id="price-item-coupon"><option value="">不使用优惠券</option></select></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-price-item')">取消</button>
            <button class="btn btn-primary" onclick="saveItem()">保存</button>
        </div>
    </div>
</div>
```

### 4.2 报价单列表渲染增加列

**当前**: `renderItemList()` (`main.js:3201-3246`) — 5 列表格（名称、课时、单价、实际价格、操作）

**改造**: 增加「优惠方案」和「优惠券」两列 → **7 列**（colspan 同步更新）

```javascript
// 表头在 index.php HTML 中增加 2 个 th
// tbody 渲染（在 items.map 中每行增加 2 个 td）:
<td>${esc(item.discount_plan_name || '-')}</td>
<td>${esc(item.coupon_name || '-')}</td>
```

> ⚠ 遵循 TMS 陷阱 #27：colspan 必须从 5 改为 7。

### 4.3 JS 函数变更

#### 4.3.1 `addItem()` — 打开弹窗时加载下拉选项

```javascript
function addItem() {
    if (!currentSelectedPlanId) { showToast('请先选择左侧价格方案', 'error'); return; }
    editingItemId = 0;
    document.getElementById('modal-price-item-title').textContent = '新增报价单';
    document.getElementById('edit-price-item-id').value = '';
    document.getElementById('price-item-name').value = '';
    document.getElementById('price-item-lesson-count').value = '';
    document.getElementById('price-item-unit-price').value = '';
    document.getElementById('price-item-actual-price').value = '';
    document.getElementById('price-item-discount-plan').value = '';
    document.getElementById('price-item-coupon').value = '';
    loadPriceItemDiscountOptions();   // NEW
    loadPriceItemCouponOptions();     // NEW
    openModal('modal-price-item');
}
```

#### 4.3.2 `editItem(itemId)` — 回填选中值

```javascript
function editItem(itemId) {
    const plan = currentPlans.find(p => p.id === currentSelectedPlanId);
    if (!plan) return;
    const item = (plan.items || []).find(i => i.id === itemId);
    if (!item) return;
    editingItemId = itemId;
    document.getElementById('modal-price-item-title').textContent = '编辑报价单';
    document.getElementById('edit-price-item-id').value = item.id;
    document.getElementById('price-item-name').value = item.name;
    document.getElementById('price-item-lesson-count').value = item.lesson_count;
    document.getElementById('price-item-unit-price').value = item.unit_price;
    document.getElementById('price-item-actual-price').value = item.actual_price;
    loadPriceItemDiscountOptions();                          // NEW
    document.getElementById('price-item-discount-plan').value = item.discount_plan_id || '';  // NEW
    loadPriceItemCouponOptions();                            // NEW
    document.getElementById('price-item-coupon').value = item.coupon_id || '';                // NEW
    openModal('modal-price-item');
}
```

#### 4.3.3 `saveItem()` — 提交时包含新字段

```javascript
const newItem = {
    name: name,
    lesson_count: lessonCount,
    unit_price: unitPrice,
    actual_price: actualPrice,
    discount_plan_id: parseInt(document.getElementById('price-item-discount-plan').value) || 0,  // NEW
    coupon_id: parseInt(document.getElementById('price-item-coupon').value) || 0,                // NEW
    sort_order: items.length
};
```

#### 4.3.4 新增函数：`loadPriceItemDiscountOptions()`

```javascript
async function loadPriceItemDiscountOptions() {
    const select = document.getElementById('price-item-discount-plan');
    if (!select) return;
    
    const plan = currentPlans.find(p => p.id === currentSelectedPlanId);
    const planType = plan ? (plan.plan_type || '') : '';
    
    // 小课包不加载优惠方案（暂无小课包类型的优惠方案）
    if (!planType || planType === '小课包') {
        select.innerHTML = '<option value="">无可用优惠方案</option>';
        return;
    }
    
    try {
        const res = await api('list_discount_plans?plan_type=' + encodeURIComponent(planType) + '&page_size=200', null, 'GET');
        const data = res.data || [];
        select.innerHTML = '<option value="">不使用优惠方案</option>' +
            data.map(d => `<option value="${d.id}">${esc(d.name)}（¥${Number(d.discount_amount || 0).toFixed(2)}）</option>`).join('');
    } catch (e) {
        select.innerHTML = '<option value="">加载失败</option>';
    }
}
```

#### 4.3.5 新增函数：`loadPriceItemCouponOptions()`

```javascript
async function loadPriceItemCouponOptions() {
    const select = document.getElementById('price-item-coupon');
    if (!select) return;
    
    try {
        const res = await api('list_coupons?coupon_type=课程券&page_size=200', null, 'GET');
        const data = res.data || [];
        select.innerHTML = '<option value="">不使用优惠券</option>' +
            data.map(c => `<option value="${c.id}">${esc(c.name)}（¥${Number(c.discount_amount || 0).toFixed(2)}）</option>`).join('');
    } catch (e) {
        select.innerHTML = '<option value="">加载失败</option>';
    }
}
```

#### 4.3.6 `deleteItem()` — 无需变更

`deleteItem` 通过 `save_price_plan` API 间接删除（发送不含被删项的 items 列表），无需额外处理新字段。

---

## 5. 业务规则

| 场景 | 行为 | 实现 |
|------|------|------|
| 价格方案 plan_type = `新报` | 优惠方案下拉仅加载 `plan_type='新报'` 的优惠方案 | `loadPriceItemDiscountOptions` 传 `plan_type=新报` |
| 价格方案 plan_type = `续费` | 优惠方案下拉仅加载 `plan_type='续费'` 的优惠方案 | `loadPriceItemDiscountOptions` 传 `plan_type=续费` |
| 价格方案 plan_type = `小课包` | 优惠方案下拉显示「无可用优惠方案」 | `loadPriceItemDiscountOptions` 提前返回 |
| 优惠券下拉 | 始终仅加载 `coupon_type='课程券'` | `loadPriceItemCouponOptions` 硬编码筛选 |
| 两个字段均可不选 | 默认值为空（`''`），后端存 NULL | select 第一项 `<option value="">不使用...</option>` |
| 报价单 A 选方案甲，报价单 B 选方案乙 | 独立生效，互不干扰 | 各 item 独立存储 `discount_plan_id` |
| 已选优惠方案被删除 | 报价单的 `discount_plan_id` 仍保留旧 ID，列表显示为 `-` | LEFT JOIN 找不到匹配时 name=NULL → 前端兜底 `'-'` |
| 优惠方案和优惠券可否叠加 | V2 确定（本期仅存储，不做折扣计算） | 待产品确认 |
| 折扣应用到报名支付 | V2 实现 | 需在 `pay_enroll` 中读取 item 的 discount_plan_id + coupon_id 计算实付 |

---

## 6. 实施计划

| Phase | 内容 | 预估工时 | 涉及文件 | 依赖 |
|-------|------|---------|---------|------|
| **Phase 1** | 数据库：ALTER TABLE + 兼容块 | 5 min | `index.php` | 无 |
| **Phase 2** | 后端 API：list_price_plans / get_course_plans / save_price_plan | 20 min | `index.php` | Phase 1 |
| **Phase 3** | 前端 HTML：modal-price-item + 表格表头 | 10 min | `index.php` | Phase 2 |
| **Phase 4** | 前端 JS：渲染列 + 下拉加载 + saveItem 改造 | 25 min | `main.js` | Phase 3 |
| **Phase 5** | 联调验证 + curl 测试 | 15 min | — | Phase 1-4 |
| **V2** | pay_enroll 折扣计算 | TBD | `index.php` | 需求确认 |

**总计**: 约 75 分钟（Phase 1-5）

---

## 7. 关键陷阱

| 陷阱 # | 内容 | 本次涉及点 |
|--------|------|-----------|
| **#26** | 新增表字段必须加 ALTER TABLE 兼容块 | `price_items` +2 字段 |
| **#27** | 表格增列时 colspan 必须同步更新 | `renderItemList` 空态 + 总计行 colspan: 5→7 |
| **#45** | handleApi 已消费 php://input，case 内直接使用 $input | `save_price_plan` 读取新字段 |
| **#6** | $db->quote() 自带引号 | INSERT 语句拼接 |
| **#50** | JS 与 HTML 字段 ID 不匹配 | `price-item-discount-plan` / `price-item-coupon` 必须一致 |
| **#2** | campus_permission 存 ID | 优惠方案/券的下拉加载如涉及校区筛选需注意 |
| **#32** | 表格增列后核对渲染变量 | `renderItemList` 新增 discount_plan_name / coupon_name 列 |
| **#3** | API 返回格式不统一 | `list_discount_plans` 返回 `{data: [...]}`，`list_coupons` 同 |

---

## 8. 测试用例

### 8.1 后端 curl 测试

```bash
# 1. 验证报价单带优惠信息（GET）
curl -s "http://127.0.0.1:5001/?action=list_price_plans&course_id=1" | python -c "import sys,json; d=json.loads(sys.stdin.buffer.read().decode('utf-8-sig')); items=d['data'][0]['items'] if d['data'] else []; print(json.dumps(items[0] if items else {}, indent=2, ensure_ascii=False))"
# 预期：输出包含 discount_plan_id, discount_plan_name, coupon_id, coupon_name 字段

# 2. 保存带优惠方案的报价单（POST，需用浏览器验证，见陷阱 #46）
# 浏览器 console：
# await api('save_price_plan', {course_id:1, plan_id:1, plan_name:'测试', plan_type:'新报', items:[{name:'测试报价单', lesson_count:10, unit_price:100, actual_price:100, discount_plan_id:1, coupon_id:2, sort_order:0}]}, 'POST')

# 3. 验证保存后查询
curl -s "http://127.0.0.1:5001/?action=list_price_plans&course_id=1" | python -c "import sys,json; d=json.loads(sys.stdin.buffer.read().decode('utf-8-sig')); print('discount_plan_id:', d['data'][0]['items'][-1].get('discount_plan_id'), 'coupon_id:', d['data'][0]['items'][-1].get('coupon_id'))"
```

### 8.2 前端操作验证

| 测试场景 | 操作步骤 | 预期结果 |
|---------|---------|---------|
| 新增报价单选优惠 | 新增报价单 → 优惠方案下拉显示仅「新报」类型方案 | 下拉仅显示 plan_type='新报' 的方案 |
| 编辑报价单改优惠 | 编辑已有报价单 → 下拉预选之前的值 → 改为其他方案 → 保存 | 报价单列表显示新方案名 |
| 不使用优惠 | 优惠方案和优惠券均留空 → 保存 | 列表中显示 `-` |
| 报价单列表展示 | 查看价格方案下报价单列表 | 每行显示优惠方案名 + 优惠券名 |
| 已删优惠方案 | 选中方案A → 在优惠管理中删除方案A → 回来查看报价单 | 该列显示 `-`（LEFT JOIN 为 NULL） |

---

## 9. 后续扩展（V2）

| 扩展项 | 描述 | 优先级 |
|--------|------|--------|
| 折扣计算 | `pay_enroll` 中读取 discount_plan_id + coupon_id 自动计算折后实付 | 高 |
| 折扣预览 | 报价单编辑弹窗中选中优惠方案/券后实时显示折后价格 | 中 |
| 有效期校验 | 若优惠方案/券已过期，报名时给出警告 | 中 |
| 互斥/叠加规则 | 优惠方案与优惠券叠加规则配置（取最高/叠加/折上折） | 低 |
| 批量设置 | 一键为方案下所有报价单设置相同优惠方案/券 | 低 |

---

## 附录 A：涉及文件清单

| 文件 | 变更类型 |
|------|---------|
| `index.php` (~190-209行) | 建表区：ALTER TABLE 兼容块 |
| `index.php` (~2334-2346行) | `list_price_plans`：LEFT JOIN 查询 |
| `index.php` (~2348-2360行) | `get_course_plans`：LEFT JOIN 查询 |
| `index.php` (~2539-2547行) | `save_price_plan`：INSERT 增加字段 |
| `index.php` (~7827-7837行) | `modal-price-item` HTML：+2 form-group |
| `index.php` (价格面板表格) | 表头：+2 th，colspan 更新 |
| `main.js` (~3201-3246行) | `renderItemList`：+2 列渲染 |
| `main.js` (~3341-3350行) | `addItem`：加载下拉 + 清空 |
| `main.js` (~3353-3366行) | `editItem`：加载下拉 + 回填 |
| `main.js` (~3368-3419行) | `saveItem`：提交含新字段 |
| `main.js` (新增) | `loadPriceItemDiscountOptions` |
| `main.js` (新增) | `loadPriceItemCouponOptions` |

---

> **确认后实施。** 请审阅以上方案，有任何调整需求请指出。
