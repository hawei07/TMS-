# PRD：报价单列表 Inline 编辑

> **版本**: v1.0 | **日期**: 2026-07-07 | **状态**: 待确认
> **关联模块**: 价格管理 → 报价单列表
> **影响范围**: `main.js` (renderItemList/saveItem/editItem)、`style.css` (inline 编辑样式)、`index.php`（无需改动）

---

## 1. 需求概述

报价单列表（`#price-item-table`）当前为只读表格，编辑需点击「编辑」按钮 → 弹窗修改 → 保存。需求改为**表格内直接 inline 编辑**，像电子表格一样：

| # | 字段 | 编辑方式 | 联动 |
|---|------|---------|------|
| 1 | 报价单名称 | text input | — |
| 2 | 课时数量 | number input | — |
| 3 | 课时价格 | number input | 联动实际价格 |
| 4 | 实际价格 | **只读显示** | = 课时价格 − 优惠方案金额 − 优惠券金额 |
| 5 | 优惠方案 | select（按方案类型筛选） | 联动实际价格 |
| 6 | 优惠券 | select（仅课程券） | 联动实际价格 |
| 7 | 操作 | 删除按钮（保留）；编辑按钮 → 删除（inline 取代） | — |

## 2. 交互方案

### 2.1 进入编辑

| 触发方式 | 行为 |
|----------|------|
| **单击单元格** | 该单元格进入编辑态（text→input，display→select），同时**同一行所有可编辑列**全部进入编辑态 |
| Tab 键 | 在已展开的编辑控件间切换焦点 |

> ⚠ **设计决策：全行展开 vs 单格编辑**
>
> **选择：单击任意可编辑列 → 整行进入编辑态。**
>
> 理由：
> 1. 用户通常需要连续修改多个字段（改课时价格→看实际价格联动→选优惠方案），逐格点开体验差
> 2. 电子表格风格天然是全行编辑
> 3. 避免逐格打开/关闭带来的频繁 DOM 重绘和失焦保存竞态

### 2.2 编辑态样式

- 编辑态单元格：`.inline-editing` class，边框高亮为紫色（`border: 2px solid #7C3AED`），背景 `#FAF5FF`
- input 复用现有 `.inline-edit-input`（`style.css:4445`）
- select 新增 `.inline-edit-select` class
- 实际价格列：编辑态下显示为灰色背景只读框，醒目提示「自动计算」

### 2.3 退出编辑

| 触发方式 | 行为 |
|----------|------|
| **Enter** | 确认当前行编辑 → 保存 → 退出编辑态 |
| **Esc** | 放弃当前行所有修改 → 还原为原始值 → 退出编辑态 |
| **单击表格外区域**（blur） | 自动保存 → 退出编辑态 |
| **单击另一行** | 先保存当前行 → 新行进入编辑态 |
| **单击左侧方案** | 先保存当前行 → 切换方案（数据刷新） |

### 2.4 实际价格联动（客户端实时计算）

编辑态下，修改「课时价格」「优惠方案」「优惠券」任一项后，**实时**重新计算实际价格并更新只读显示：

```
实际价格 = max(0, 课时价格 − 优惠方案金额 − 优惠券金额)
```

- 优惠方案金额：从缓存 `priceItemDiscountPlans` 按选中 ID 查 `amount`
- 优惠券金额：从缓存 `priceItemCoupons` 按选中 ID 查 `amount`
- 计算结果实时反映在只读单元格中，无需等 blur

### 2.5 保存机制

| 项目 | 说明 |
|------|------|
| 触发 | Enter / 失焦 / 点其他行 |
| 保存范围 | 全方案报价单（`save_price_plan` API，整批替换） |
| 构建数据 | 从 `plan.items`（内存）patched 当前编辑行的修改值 → 构建完整 items 数组 |
| 保存中 | 编辑行显示 spinner / 保存中提示，禁用该行输入 |
| 保存后 | `loadPricePlans()` 刷新 → 表格重渲染 → 编辑态自然消除 |
| 校验失败 | `showToast` 报错 → **保持编辑态**，不退出（让用户修正） |

### 2.6 Esc 取消

- 记录进入编辑态时该行的原始快照（浅拷贝）
- Esc → 还原 DOM 为该快照 → 移除编辑态 class

## 3. 与现有弹窗编辑如何共存

| 功能 | 入口 | 实现方式 |
|------|------|----------|
| **新增报价单** | 底部「+ 新增报价单」按钮 | 保留现有 `modal-price-item` 弹窗，不变 |
| **编辑报价单** | 表格行单击 | 改为 inline 编辑（本 PRD），**删除**表格行的「编辑」按钮 |
| **删除报价单** | 表格行「删除」按钮 | 保留，不变 |

> **原则**：弹窗用于「创建」（有完整表单流），inline 用于「修改」（快速调整已有数据）。

## 4. JS 实现方案

### 4.1 数据预加载

选择左侧价格方案时（现有 `selectPlan()` 流程），预加载优惠数据到模块变量：

```javascript
// 现有变量即缓存（main.js:3481-3482）
let priceItemDiscountPlans = [];  // 已存在
let priceItemCoupons = [];        // 已存在
```

改造 `selectPlan()` → 选择方案后异步预加载两批数据，`renderItemList()` 渲染时数据已就绪。

### 4.2 渲染改造：`renderItemList()`

当前用 `items.map(...)` 生成纯文本 `<td>`。改造为：

- 每行加 `data-item-id="${item.id}"` 属性
- 可编辑列的 `<td>` 加 `class="pi-editable"` 和 `data-field="xxx"` 属性
- 实际价格列加 `class="pi-readonly"` 
- 操作列：保留「删除」按钮，**移除「编辑」按钮**
- 单元格内容：显示文本同时存储 `data-original-value` 用于 Esc 还原

```javascript
// 伪代码示意
<tr data-item-id="${item.id}">
  <td class="pi-editable" data-field="name" data-original="${esc(item.name)}">${esc(item.name)}</td>
  <td class="pi-editable" data-field="lesson_count" data-original="${item.lesson_count}">${item.lesson_count}</td>
  <td class="pi-editable" data-field="unit_price" data-original="${item.unit_price}">${parseFloat(item.unit_price).toFixed(2)}</td>
  <td class="pi-readonly" data-field="actual_price">${parseFloat(item.actual_price).toFixed(2)}</td>
  <td class="pi-editable" data-field="discount_plan_id" data-original="${item.discount_plan_id||''}">${esc(item.discount_plan_name||'-')}</td>
  <td class="pi-editable" data-field="coupon_id" data-original="${item.coupon_id||''}">${esc(item.coupon_name||'-')}</td>
  <td><button onclick="deleteItem(${item.id})">删除</button></td>
</tr>
```

### 4.3 事件委托：`startInlineEdit(rowElement)`

在 `#price-item-table-body` 上挂**单击事件委托**：

```javascript
// 伪代码
tbody.addEventListener('click', (e) => {
    const td = e.target.closest('td.pi-editable');
    if (!td) return;
    
    const tr = td.closest('tr');
    if (tr.classList.contains('inline-editing')) return; // 已在编辑态
    
    // 如果另一行在编辑 → 先保存
    const prev = tbody.querySelector('tr.inline-editing');
    if (prev) await saveInlineRow(prev);
    
    // 进入编辑
    enterInlineEdit(tr);
});
```

### 4.4 `enterInlineEdit(tr)`

```javascript
function enterInlineEdit(tr) {
    tr.classList.add('inline-editing');
    
    // 快照原始值，用于 Esc 还原
    tr._snapshot = {};
    
    tr.querySelectorAll('td.pi-editable, td.pi-readonly').forEach(td => {
        const field = td.dataset.field;
        const original = td.dataset.original || td.textContent.trim();
        tr._snapshot[field] = original;
        
        if (field === 'actual_price') {
            // 只读：灰色背景显示
            td.innerHTML = `<span class="pi-calc-display">${original}</span>`;
        } else if (field === 'discount_plan_id' || field === 'coupon_id') {
            // select 下拉
            td.innerHTML = buildInlineSelect(field, original);
        } else {
            // text/number input
            const type = (field === 'lesson_count' || field === 'unit_price') ? 'number' : 'text';
            td.innerHTML = `<input type="${type}" class="inline-edit-input" value="${escAttr(original)}" data-field="${field}">`;
        }
    });
    
    // 自动 focus 第一个 input
    const first = tr.querySelector('input.inline-edit-input');
    if (first) first.focus();
    
    // 绑定联动事件
    bindInlinePriceRecalc(tr);
    
    // 绑定键盘事件
    bindInlineKeyboard(tr);
}
```

### 4.5 联动计算：`bindInlinePriceRecalc(tr)`

```javascript
function bindInlinePriceRecalc(tr) {
    const unitPriceEl = tr.querySelector('[data-field="unit_price"]');
    const discountEl = tr.querySelector('[data-field="discount_plan_id"]');
    const couponEl = tr.querySelector('[data-field="coupon_id"]');
    const displayEl = tr.querySelector('.pi-calc-display');
    
    const recalc = () => {
        const base = parseFloat(unitPriceEl?.value) || 0;
        let discount = 0;
        if (discountEl?.value) {
            const dp = priceItemDiscountPlans.find(d => d.id == discountEl.value);
            if (dp) discount += Number(dp.amount || 0);
        }
        if (couponEl?.value) {
            const cp = priceItemCoupons.find(c => c.id == couponEl.value);
            if (cp) discount += Number(cp.amount || 0);
        }
        if (displayEl) displayEl.textContent = Math.max(0, base - discount).toFixed(2);
    };
    
    unitPriceEl?.addEventListener('input', recalc);
    discountEl?.addEventListener('change', recalc);
    couponEl?.addEventListener('change', recalc);
}
```

### 4.6 键盘事件：`bindInlineKeyboard(tr)`

```javascript
function bindInlineKeyboard(tr) {
    tr.addEventListener('keydown', async (e) => {
        if (e.key === 'Enter' && e.target.tagName !== 'SELECT') {
            e.preventDefault();
            await saveInlineRow(tr);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            cancelInlineEdit(tr);
        }
    });
}
```

> ⚠ `e.target.tagName !== 'SELECT'`：select 的 Enter 用于展开下拉，不应触发保存。

### 4.7 保存：`saveInlineRow(tr)`

```javascript
async function saveInlineRow(tr) {
    const itemId = parseInt(tr.dataset.itemId);
    const plan = currentPlans.find(p => p.id === currentSelectedPlanId);
    if (!plan) return;
    
    // 收集编辑后的值（从 input/select 读取）
    const editedValues = {};
    tr.querySelectorAll('input.inline-edit-input, select.inline-edit-select').forEach(el => {
        editedValues[el.dataset.field] = el.value;
    });
    
    // 客户端校验
    const name = (editedValues.name || '').trim();
    if (!name) { showToast('报价单名称不能为空', 'error'); return; }
    const lessonCount = parseInt(editedValues.lesson_count) || 0;
    if (lessonCount <= 0) { showToast('课时数量必须大于0', 'error'); return; }
    
    // 构建完整 items 数组（从内存 plan.items patched）
    const items = (plan.items || []).map((item, idx) => {
        if (item.id === itemId) {
            const unitPrice = parseFloat(editedValues.unit_price) || 0;
            const discountPlanId = parseInt(editedValues.discount_plan_id) || 0;
            const couponId = parseInt(editedValues.coupon_id) || 0;
            // 计算实际价格
            let discount = 0;
            if (discountPlanId > 0) {
                const dp = priceItemDiscountPlans.find(d => d.id === discountPlanId);
                if (dp) discount += Number(dp.amount || 0);
            }
            if (couponId > 0) {
                const cp = priceItemCoupons.find(c => c.id === couponId);
                if (cp) discount += Number(cp.amount || 0);
            }
            const actualPrice = Math.max(0, unitPrice - discount);
            return {
                name, lesson_count: lessonCount, unit_price: unitPrice,
                actual_price: actualPrice,
                discount_plan_id: discountPlanId,
                coupon_id: couponId,
                sort_order: idx
            };
        }
        return {
            name: item.name,
            lesson_count: item.lesson_count,
            unit_price: item.unit_price,
            actual_price: item.actual_price,
            discount_plan_id: parseInt(item.discount_plan_id) || 0,
            coupon_id: parseInt(item.coupon_id) || 0,
            sort_order: idx
        };
    });
    
    // 保存中状态
    tr.classList.add('inline-saving');
    
    try {
        const r = await api('save_price_plan', {
            course_id: currentPriceCourseId,
            plan_id: currentSelectedPlanId,
            plan_name: plan.name,
            plan_type: plan.plan_type || '',
            items: items
        }, 'POST');
        if (r && r.error) { showToast(r.error, 'error'); return; } // 保持编辑态
        
        showToast('报价单已更新', 'success');
        await loadPricePlans(currentPriceCourseId); // 重渲染
    } catch (e) {
        showToast('保存失败', 'error');
    } finally {
        tr.classList.remove('inline-saving');
    }
}
```

### 4.8 取消：`cancelInlineEdit(tr)`

```javascript
function cancelInlineEdit(tr) {
    if (!tr._snapshot) return;
    
    tr.querySelectorAll('td.pi-editable, td.pi-readonly').forEach(td => {
        const field = td.dataset.field;
        td.textContent = tr._snapshot[field] || '';
    });
    
    tr.classList.remove('inline-editing');
    delete tr._snapshot;
}
```

### 4.9 改造 `addItem()` / `editItem()` / `deleteItem()`

| 函数 | 改动 |
|------|------|
| `addItem()` | **不变** — 仍打开弹窗新增 |
| `editItem(itemId)` | **删除** — inline 编辑取代（或保留但仅作为兼容/降级入口） |
| `deleteItem(itemId)` | **不变** |
| `saveItem()` | **保留** — 弹窗新增仍用它 |

### 4.10 改造 `selectPlan()` — 预加载优惠数据

```javascript
// 选择方案时预加载优惠方案 + 优惠券数据
async function selectPlan(planId) {
    // ... 现有代码 ...
    
    // 预加载 inline select 所需数据
    const plan = currentPlans.find(p => p.id === planId);
    if (plan && plan.plan_type && plan.plan_type !== '小课包') {
        // 异步预加载，不阻塞渲染
        preloadInlineDiscountData(plan.plan_type);
    }
}

async function preloadInlineDiscountData(planType) {
    try {
        const [discountRes, couponRes] = await Promise.all([
            api('list_discount_plans', { plan_type: planType, page_size: 200 }, 'GET'),
            api('list_coupons', { coupon_type: '课程券', page_size: 200 }, 'GET')
        ]);
        priceItemDiscountPlans = discountRes.data || [];
        priceItemCoupons = couponRes.data || [];
    } catch (e) {
        // 预加载失败不阻塞
    }
}
```

## 5. CSS 实现方案

### 5.1 新增样式

在 `style.css` 末尾 `#price-item-table` 区域追加：

```css
/* === 报价单 inline 编辑 === */

/* 可编辑单元格 hover 提示 */
#price-item-table td.pi-editable {
    cursor: pointer;
    position: relative;
}
#price-item-table td.pi-editable:hover {
    background: rgba(124, 58, 237, 0.04);
}
#price-item-table td.pi-editable::after {
    content: '';
    position: absolute;
    right: 4px; top: 50%;
    transform: translateY(-50%);
    width: 0; height: 0;
    opacity: 0;
    transition: opacity 0.15s;
}
#price-item-table tr:hover td.pi-editable::after {
    /* 编辑图标提示 — 用 CSS 绘制小铅笔或直接用 ✎ */
    content: '✎';
    width: auto; height: auto;
    transform: translateY(-50%);
    opacity: 0.3;
    font-size: 11px;
    color: var(--color-primary);
}

/* 编辑态行 */
#price-item-table tbody tr.inline-editing {
    background: #FAF5FF !important;
    border-left-color: var(--color-primary) !important;
}
#price-item-table tbody tr.inline-editing td {
    padding: 4px 6px;  /* 减少 padding 腾空间给 input */
}

/* 编辑态下隐藏 hover 伪元素 */
#price-item-table tr.inline-editing td.pi-editable::after {
    display: none;
}

/* 编辑态 input */
#price-item-table .inline-edit-input {
    width: 100%;
    padding: 6px 8px;
    border: 2px solid var(--color-primary);
    border-radius: 6px;
    font-size: 13px;
    font-family: inherit;
    outline: none;
    box-sizing: border-box;
    background: #fff;
    transition: border-color 0.15s;
}
#price-item-table .inline-edit-input:focus {
    border-color: #6D28D9;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
}

/* 编辑态 select */
#price-item-table .inline-edit-select {
    width: 100%;
    padding: 6px 8px;
    border: 2px solid var(--color-primary);
    border-radius: 6px;
    font-size: 12px;
    font-family: inherit;
    outline: none;
    box-sizing: border-box;
    background: #fff;
    cursor: pointer;
    max-width: 180px;
}

/* 实际价格只读显示 */
#price-item-table .pi-calc-display {
    display: inline-block;
    padding: 6px 10px;
    background: #F3F4F6;
    border-radius: 4px;
    font-weight: 700;
    color: var(--color-primary-dark);
    font-variant-numeric: tabular-nums;
    min-width: 60px;
    text-align: right;
}

/* 保存中状态 */
#price-item-table tbody tr.inline-saving {
    opacity: 0.6;
    pointer-events: none;
}
#price-item-table tbody tr.inline-saving td {
    position: relative;
}
#price-item-table tbody tr.inline-saving::after {
    content: '保存中...';
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 11px;
    color: var(--color-primary);
    z-index: 5;
}
```

### 5.2 `nth-child` 列宽调整

当前 CSS 用 `nth-child(3)` / `nth-child(4)` 定位金额列（右对齐）。新增 inline 编辑后列顺序不变，但需确认 `nth-child` 索引仍然正确：1=名称, 2=课时数量, 3=课时价格, 4=实际价格, 5=优惠方案, 6=优惠券, 7=操作。**当前 CSS `nth-child(3)` 和 `nth-child(4)` 定位正确，无需修改。**

## 6. 业务规则

| 场景 | 行为 |
|------|------|
| 单击编辑态行外的空白区域 | 保存当前编辑 → 退出 |
| 编辑中切换左侧方案 | 先保存 → 切换 → 数据刷新 |
| 校验失败（名称为空/课时≤0） | showToast 报错，保持编辑态，不退出 |
| 保存 API 返回 error | showToast，保持编辑态 |
| 网络异常 | showToast，保持编辑态 |
| 最后一条报价单 | 可编辑，不可删除（deleteItem 已保护） |
| 小课包方案 | 优惠方案列显示「—」，单击无效（`pi-editable` 不加 class 或加 `pi-noedit`） |
| 新增报价单 | 仍走弹窗，保存后刷新列表 |

## 7. 实施计划

| Phase | 内容 | 预估 | 文件 |
|-------|------|------|------|
| P1 | CSS 追加（inline-edit 样式） | 10 min | `style.css` |
| P2 | 改造 `renderItemList()`（data 属性 + 移除编辑按钮） | 15 min | `main.js` |
| P3 | 实现 `enterInlineEdit()` / `cancelInlineEdit()` | 20 min | `main.js` |
| P4 | 实现 `saveInlineRow()` / 联动计算 | 20 min | `main.js` |
| P5 | 事件委托 + 键盘事件 + 预加载 | 15 min | `main.js` |
| P6 | 改造 `selectPlan()` 预加载数据 | 10 min | `main.js` |
| P7 | 验证（列对齐、Esc还原、校验、联动） | 15 min | — |

**总预估**: ~105 min

## 8. 关键陷阱（按 TMS 陷阱索引）

| 陷阱 # | 风险 | 预防 |
|--------|------|------|
| #27 | colspan 变更：删除编辑按钮后操作列仍 1 列，colspan 仍为 7，无需改 | 确认 `colspan="7"` 在空态/错误态各分支正确 |
| #52 | td 数量 = th 数量（7 列） | 渲染时逐行核对 |
| #51 | CSS `replace_all` 破坏多行选择器 | inline 编辑样式追加到末尾，不改现有规则 |
| #50 | JS DOM ID 与 HTML 不一致 | inline 编辑不引入新 ID，全部用 `data-*` + class |
| #29 | `parseFloat('')` → NaN | 统一用 `Number()` 或 `\|\| 0` 兜底 |
| #60 | 异步加载下拉选项后 `.value` 赋值丢失 | inline select 选项从缓存 `priceItemDiscountPlans`/`priceItemCoupons` 同步构建，无异步问题 |
| #1 | 函数重复定义 | 新函数命名 `inlineEditXxx` 前缀，`grep` 查重后提交 |

## 9. 测试用例

### 9.1 基础编辑

| # | 操作 | 预期 |
|---|------|------|
| 1 | 单击「报价单名称」单元格 | 整行进入编辑态，名称变 text input，其他可编辑列变 input/select |
| 2 | 修改名称 → 按 Enter | 保存成功，表格刷新，新名称显示 |
| 3 | 修改名称 → 单击行外空白 | 同上，自动保存 |
| 4 | 修改名称 → 按 Esc | 放弃修改，还原为原始名称，退出编辑态 |

### 9.2 联动计算

| # | 操作 | 预期 |
|---|------|------|
| 5 | 编辑态下修改「课时价格」 | 实际价格实时更新（= 课时价格 − 优惠 − 券） |
| 6 | 编辑态下切换「优惠方案」 | 实际价格实时更新 |
| 7 | 编辑态下切换「优惠券」 | 实际价格实时更新 |
| 8 | 保存后刷新 | 实际价格显示与编辑态计算结果一致 |

### 9.3 校验

| # | 操作 | 预期 |
|---|------|------|
| 9 | 名称清空 → Enter | showToast「报价单名称不能为空」，保持编辑态 |
| 10 | 课时数量填 0 → Enter | showToast「课时数量必须大于0」，保持编辑态 |

### 9.4 切换行

| # | 操作 | 预期 |
|---|------|------|
| 11 | 编辑行 A → 单击行 B | A 自动保存 → B 进入编辑态 |
| 12 | 编辑行 A → 单击左侧其他方案 | A 自动保存 → 方案切换 → 数据刷新 |

### 9.5 删除

| # | 操作 | 预期 |
|---|------|------|
| 13 | 编辑态下单击「删除」按钮 | 正常弹出确认，确认后删除 |
| 14 | 仅剩 1 条时删除 | showToast「至少保留一个报价单」 |

## 10. 后续扩展（V2 可选）

| 项目 | 说明 |
|------|------|
| 行拖拽排序 | 拖拽行调整 `sort_order`，配合 `save_price_plan` |
| 批量编辑 | 选中多行 → 批量修改优惠方案/优惠券 |
| 键盘导航 | ↑↓ 键在行间移动，Tab 在列间切换 |
| 撤销 | Ctrl+Z 撤销最近一次 inline 编辑 |
| 乐观更新 | 保存后不重载全表，仅更新内存数据 + DOM 局部刷新 |

---

## 附录 A：当前代码引用

| 文件 | 行号 | 内容 |
|------|------|------|
| `index.php` | 7819-7831 | 报价单列表 HTML（`#price-item-table`） |
| `index.php` | 7847-7903 | 报价单编辑弹窗（`#modal-price-item`） |
| `main.js` | 3212-3262 | `renderItemList()` — 表格渲染 |
| `main.js` | 3358-3372 | `addItem()` — 打开新增弹窗 |
| `main.js` | 3374-3390 | `editItem()` — 打开编辑弹窗 |
| `main.js` | 3392-3448 | `saveItem()` — 保存报价单 |
| `main.js` | 3450-3478 | `deleteItem()` — 删除报价单 |
| `main.js` | 3481-3482 | `priceItemDiscountPlans` / `priceItemCoupons` 缓存变量 |
| `main.js` | 3484-3505 | `recalcItemActualPrice()` / `onUnitPriceChange()` — 弹窗内联动 |
| `main.js` | 3507-3539 | `loadPriceItemDiscountOptions()` |
| `main.js` | 3541-3560 | `loadPriceItemCouponOptions()` |
| `style.css` | 4445-4449 | `.inline-edit-input` 现有样式 |
| `style.css` | 6443-6556 | `#price-item-table` 表格样式 |
