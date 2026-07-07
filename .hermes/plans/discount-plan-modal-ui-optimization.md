# 优惠方案弹窗 UI 优化方案设计

> 版本: v1.0 | 日期: 2026-07-07 | 作者: Hermes Agent (UI Designer)

---

## 一、现状分析

### 1.1 当前结构 (index.php L8640–8661)

```
┌─ modal-header ──────────────────────────────────────┐
│ 新增优惠方案                                    [×] │
├─ modal-body ────────────────────────────────────────┤
│ 方案名称:         [________________]                │
│                                                     │
│ 类型: [新报 ▼]    优惠金额(元): [____]              │
│                                                     │
│ 有效期开始: [____] 有效期结束: [____]               │
│                                                     │
│ 适用校区: ┌──────────────────────┐                  │
│           │ ▸ 西安                        │
│           │   ☐ 高新校区               │
│           │   ☐ 曲江校区               │
│           │   ...                             │
│           └──────────────────────┘                  │
│                                                     │
│ 适用学科: ┌──────────────────────┐                  │
│           │ ▾ 语文                        │
│           │   ☐ 小学语文               │
│           │   ☐ 初中语文               │
│           │ ▸ 数学                        │
│           │   ...                             │
│           └──────────────────────┘                  │
├─ modal-footer ──────────────────────────────────────┤
│                              [取消]  [保存]         │
└─────────────────────────────────────────────────────┘
```

### 1.2 现状问题

| 问题 | 详情 |
|------|------|
| 表单扁平 | 5 个 form-group 无分区，视觉层次弱 |
| 树选择简陋 | `border: 1px solid #e0e0e0` 硬编码，无紫色主题联动 |
| 选中态弱 | 原生 checkbox，仅 accent-color 染色，无行高亮 |
| 间距不足 | 树区域 padding 8px，行 padding 0，拥挤压 |
| 缺少辅助信息 | 无已选计数、无必填标记、无占位提示 |
| 主题不统一 | 树区域灰框 vs 紫色 focus ring 不协调 |

### 1.3 现有 CSS 变量 (style.css :root)

```css
--color-primary: #7C3AED;        --color-primary-light: #A78BFA;
--color-primary-dark: #6D28D9;   --color-primary-bg: #F5F0FF;
--color-primary-hover: #EDE5FF;  --color-bg: #F8F7FC;
--color-surface: #FFFFFF;        --color-text: #1E1B2E;
--color-text-secondary: #6B6880; --color-text-muted: #9895A8;
--color-border: #E2E0E7;         --color-border-light: #F0EFF4;
--shadow-sm: 0 1px 3px rgba(0,0,0,.04);
--shadow-md: 0 4px 12px rgba(0,0,0,.06);
--shadow-lg: 0 12px 32px rgba(0,0,0,.10);
--radius-sm: 6px; --radius-md: 8px; --radius-lg: 12px; --radius-xl: 16px;
--transition: 0.2s ease;
```

---

## 二、优化方案总览

### 2.1 布局重构：卡片式分区

**分区策略**：将 5 个表单字段分为 3 张卡片

| 卡片 | 内容 | 说明 |
|------|------|------|
| 📋 基本信息 | 方案名称 + 类型/金额 | 核心字段 |
| 📅 有效期 | 开始日期 + 结束日期 | 日期范围 |
| 🏫 适用范围 | 适用校区 + 适用学科 | 树选择区 |

```
┌─ modal-header ──────────────────────────────────────┐
│ 📋 新增优惠方案                                [×] │
├─ modal-body ────────────────────────────────────────┤
│ ┌── 基本信息 ──────────────────────────────────┐   │
│ │  方案名称                                      │   │
│ │  ┌─────────────────────────────────────────┐  │   │
│ │  │ 请输入方案名称                    (必填) │  │   │
│ │  └─────────────────────────────────────────┘  │   │
│ │  类型                │  优惠金额 (元)         │   │
│ │  ┌──────────────┐   │  ┌──────────────┐      │   │
│ │  │ 新报     ▼   │   │  │ 0.00         │      │   │
│ │  └──────────────┘   │  └──────────────┘      │   │
│ └──────────────────────────────────────────────┘   │
│                                                     │
│ ┌── 有效期 ────────────────────────────────────┐   │
│ │  开始日期                │  结束日期           │   │
│ │  ┌──────────────┐       │  ┌──────────────┐  │   │
│ │  │ 2026-07-07   │       │  │ 2026-12-31   │  │   │
│ │  └──────────────┘       │  └──────────────┘  │   │
│ └──────────────────────────────────────────────┘   │
│                                                     │
│ ┌── 适用范围 ─────────────────────────────────┐   │
│ │  适用校区  [已选 3]                           │   │
│ │  ┌───────────────────────────────────────┐   │   │
│ │  │ ▾ 西安                         全选   │   │   │
│ │  │   ☑ 高新校区                         │   │   │
│ │  │   ☑ 曲江校区  ← 选中行高亮           │   │   │
│ │  │   ☐ 雁塔校区                         │   │   │
│ │  │ ▸ 北京                                │   │   │
│ │  └───────────────────────────────────────┘   │   │
│ │                                               │   │
│ │  适用学科  [已选 5]                           │   │
│ │  ┌───────────────────────────────────────┐   │   │
│ │  │ ▾ 语文                         全选   │   │   │
│ │  │   ☑ 小学语文                         │   │   │
│ │  │   ☑ 初中语文                         │   │   │
│ │  │ ▸ 数学                                │   │   │
│ │  └───────────────────────────────────────┘   │   │
│ └──────────────────────────────────────────────┘   │
├─ modal-footer ──────────────────────────────────────┤
│                              [取消]  [💾 保存]      │
└─────────────────────────────────────────────────────┘
```

### 2.2 交互增强

| 功能 | 实现方式 |
|------|----------|
| 已选计数 | 标签右侧 badge（如「已选 3」），实时更新 |
| 全选/取消全选 | 父节点行右侧加「全选」文字按钮 |
| 选中行高亮 | `.campus-tree-row.checked` 紫色背景 + 左边框指示 |
| 树节点半选态 | indeterminate 时 checkbox 半选 + 行背景淡化 |
| 选中节点置顶 | 选中的校区自动排序到顶部，方便查看 |
| 搜索筛选 | 校区/学科区域顶部可折叠搜索框（可选，Phase 2） |

---

## 三、HTML 重构 (index.php L8640–8659)

### 3.1 修改后结构

```html
<div class="modal-dialog modal-lg">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="discount-plan-modal-title">新增优惠方案</h4>
            <button class="modal-close" onclick="closeModal('modal-discount-plan')">&times;</button>
        </div>
        <div class="modal-body">

            <!-- ====== 卡片 1: 基本信息 ====== -->
            <div class="dp-card">
                <div class="dp-card-title">
                    <span class="dp-card-icon">📋</span> 基本信息
                </div>
                <div class="dp-card-body">
                    <div class="form-group">
                        <label class="required">方案名称</label>
                        <input type="text" id="discount-plan-name" class="form-input"
                               placeholder="请输入方案名称" maxlength="50">
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="flex:1;">
                            <label>类型</label>
                            <select id="discount-plan-type" class="form-input">
                                <option value="新报">新报</option>
                                <option value="续费">续费</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>优惠金额 (元)</label>
                            <input type="number" id="discount-plan-amount" class="form-input"
                                   step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ====== 卡片 2: 有效期 ====== -->
            <div class="dp-card">
                <div class="dp-card-title">
                    <span class="dp-card-icon">📅</span> 有效期
                </div>
                <div class="dp-card-body">
                    <div class="form-row">
                        <div class="form-group" style="flex:1;">
                            <label>开始日期</label>
                            <input type="date" id="discount-plan-start" class="form-input">
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>结束日期</label>
                            <input type="date" id="discount-plan-end" class="form-input">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ====== 卡片 3: 适用范围 ====== -->
            <div class="dp-card">
                <div class="dp-card-title">
                    <span class="dp-card-icon">🏫</span> 适用范围
                </div>
                <div class="dp-card-body">
                    <div class="form-group">
                        <div class="dp-tree-header">
                            <label>适用校区</label>
                            <span class="dp-badge" id="dp-campus-count">已选 0</span>
                        </div>
                        <div class="dp-tree-wrap" id="discount-campus-tree"></div>
                    </div>
                    <div class="form-group">
                        <div class="dp-tree-header">
                            <label>适用学科</label>
                            <span class="dp-badge" id="dp-subject-count">已选 0</span>
                        </div>
                        <div class="dp-tree-wrap" id="discount-subject-tree"></div>
                    </div>
                </div>
            </div>

        </div>
        <div class="modal-footer">
            <button class="btn btn-default" onclick="closeModal('modal-discount-plan')">取消</button>
            <button class="btn btn-primary" id="btn-discount-plan-save" onclick="saveDiscountPlan()">
                💾 保存
            </button>
        </div>
    </div>
</div>
```

> **注意：** 当前弹窗 `<div class="modal-dialog">` 无 `modal-lg` 类。为容纳卡片布局，建议增加 `modal-lg`（宽度 680px），或新建 `modal-xl`（800px）。

---

## 四、CSS 方案 (style.css 新增段)

### 4.1 卡片系统

```css
/* ==================== 优惠方案弹窗 — 卡片系统 ==================== */
#modal-discount-plan .modal-body {
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.dp-card {
    background: var(--color-bg);
    border: 1px solid var(--color-border-light);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: border-color var(--transition);
}
.dp-card:focus-within {
    border-color: var(--color-primary-light);
    box-shadow: var(--shadow-md);
}

.dp-card-title {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 18px;
    font-size: 14px;
    font-weight: 700;
    color: var(--color-primary-dark);
    background: linear-gradient(135deg, var(--color-primary-bg), rgba(245,240,255,0.4));
    border-bottom: 1px solid var(--color-border-light);
}

.dp-card-icon { font-size: 16px; }

.dp-card-body {
    padding: 16px 18px;
    background: var(--color-surface);
}
```

### 4.2 表单字段增强

```css
/* 统一输入控件 — 圆角 + focus 紫光晕 */
#modal-discount-plan .form-input,
#modal-discount-plan input[type="text"],
#modal-discount-plan input[type="number"],
#modal-discount-plan input[type="date"],
#modal-discount-plan select {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: 14px;
    color: var(--color-text);
    background: var(--color-surface);
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}
#modal-discount-plan .form-input:hover,
#modal-discount-plan input:hover,
#modal-discount-plan select:hover {
    border-color: var(--color-primary-light);
}
#modal-discount-plan .form-input:focus,
#modal-discount-plan input:focus,
#modal-discount-plan select:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 4px rgba(124,58,237,0.10);
    background: #FAF8FF;
}

/* 必填标记 */
#modal-discount-plan label.required::after {
    content: ' *';
    color: var(--color-danger);
    font-weight: 600;
}

#modal-discount-plan .form-group { margin-bottom: 14px; }
#modal-discount-plan .form-group:last-child { margin-bottom: 0; }
#modal-discount-plan .form-row { display: flex; gap: 20px; }

#modal-discount-plan label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-secondary);
    margin-bottom: 6px;
}
```

### 4.3 树选择区域视觉优化

```css
/* ==================== 树区域容器 ==================== */
.dp-tree-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.dp-tree-header label {
    margin-bottom: 0 !important;  /* 覆盖上方 label margin */
}

/* 已选计数 Badge */
.dp-badge {
    display: inline-flex;
    align-items: center;
    height: 22px;
    padding: 0 10px;
    font-size: 12px;
    font-weight: 600;
    color: var(--color-primary);
    background: var(--color-primary-bg);
    border-radius: 9999px;
    transition: all var(--transition);
}
.dp-badge.has-selection {
    color: #fff;
    background: var(--color-primary);
}

/* 树容器包装 — 替代原 inline style */
.dp-tree-wrap {
    max-height: 200px;
    overflow-y: auto;
    border: 1.5px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface);
    transition: border-color var(--transition), box-shadow var(--transition);
}
.dp-tree-wrap:focus-within,
.dp-tree-wrap:hover {
    border-color: var(--color-primary-light);
}
.dp-tree-wrap:has(.campus-tree-node:focus-within) {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 4px rgba(124,58,237,0.08);
}

/* 滚动条美化 */
.dp-tree-wrap::-webkit-scrollbar { width: 6px; }
.dp-tree-wrap::-webkit-scrollbar-track { background: transparent; }
.dp-tree-wrap::-webkit-scrollbar-thumb {
    background: var(--color-border);
    border-radius: 3px;
}
.dp-tree-wrap::-webkit-scrollbar-thumb:hover {
    background: var(--color-primary-light);
}
```

### 4.4 树节点选中态增强

```css
/* ==================== 树节点选中态 ==================== */
#modal-discount-plan .campus-tree-node {
    user-select: none;
}

/* 行基础样式 */
#modal-discount-plan .campus-tree-row {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    margin: 1px 4px;
    border-radius: var(--radius-sm);
    border-left: 3px solid transparent;
    cursor: pointer;
    transition: background 0.12s, border-color 0.12s;
}
#modal-discount-plan .campus-tree-row:hover {
    background: var(--color-primary-hover);
}

/* 选中态 — 紫色左边框 + 浅紫背景 */
#modal-discount-plan .campus-tree-row.checked {
    background: var(--color-primary-bg);
    border-left-color: var(--color-primary);
}
#modal-discount-plan .campus-tree-row.checked .campus-tree-label {
    color: var(--color-primary-dark);
    font-weight: 600;
}

/* 半选态（父节点部分子节点选中） */
#modal-discount-plan .campus-tree-row.semi-checked {
    background: rgba(245,240,255,0.5);
    border-left-color: var(--color-primary-light);
}

/* 箭头增强 */
#modal-discount-plan .campus-tree-arrow {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: var(--color-text-muted);
    border-radius: 4px;
    cursor: pointer;
    transition: color 0.12s, background 0.12s;
}
#modal-discount-plan .campus-tree-arrow:hover {
    color: var(--color-primary);
    background: var(--color-primary-bg);
}

/* checkbox 自定义样式 */
#modal-discount-plan .campus-tree-check {
    flex-shrink: 0;
    width: 16px;
    height: 16px;
    accent-color: var(--color-primary);
    cursor: pointer;
    margin: 0;
}

/* 标签增强 */
#modal-discount-plan .campus-tree-label {
    font-size: 13px;
    color: var(--color-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
}

/* 父节点标签加粗 */
#modal-discount-plan .campus-tree-node[data-has-children="true"] > .campus-tree-row .campus-tree-label {
    font-weight: 600;
}

/* 全选按钮 */
.dp-select-all {
    font-size: 12px;
    color: var(--color-primary);
    cursor: pointer;
    padding: 2px 8px;
    border-radius: 4px;
    margin-left: auto;
    transition: background 0.12s;
    white-space: nowrap;
    user-select: none;
}
.dp-select-all:hover {
    background: var(--color-primary-bg);
}

/* 子节点缩进 */
#modal-discount-plan .campus-tree-children {
    border-left: 1.5px dashed var(--color-border-light);
    margin-left: 18px;
}
```

### 4.5 Footer 增强

```css
/* Footer 按钮增强 */
#modal-discount-plan .modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--color-border-light);
    background: var(--color-bg);
}

#modal-discount-plan .modal-footer .btn-primary {
    padding: 10px 28px;
    font-size: 14px;
    font-weight: 600;
    border-radius: var(--radius-md);
    transition: all var(--transition);
    box-shadow: 0 2px 8px rgba(124,58,237,0.25);
}
#modal-discount-plan .modal-footer .btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(124,58,237,0.35);
}
```

### 4.6 响应式适配

```css
/* 小屏 (< 600px) 单列布局 */
@media (max-width: 600px) {
    #modal-discount-plan .modal-lg { width: 95vw; }
    #modal-discount-plan .modal-body { padding: 16px 12px; gap: 12px; }
    #modal-discount-plan .form-row {
        flex-direction: column;
        gap: 12px;
    }
    .dp-card-body { padding: 12px; }
    .dp-card-title { padding: 10px 14px; font-size: 13px; }
}
```

---

## 五、JS 修改方案 (main.js)

### 5.1 树渲染增强 — 注入 `dp-select-all` 按钮

需修改 `renderCampusTreeNode` 或为该弹窗写专用渲染函数。**推荐方案**：新增 `renderDiscountCampusTreeNode(node, level)`，注入全选按钮。

```javascript
// 新增：专为优惠方案弹窗的校区树渲染
function renderDiscountCampusTreeNode(node, level) {
    const hasChildren = node.children && node.children.length > 0;
    const isCampus = (node.type === '校区');
    let html = '<div class="campus-tree-node" data-id="' + node.id
        + '" data-type="' + (node.type || '') + '" data-has-children="' + hasChildren + '">';
    html += '<div class="campus-tree-row" style="padding-left:' + (level * 20 + 8) + 'px">';

    // 展开箭头
    if (hasChildren) {
        html += '<span class="campus-tree-arrow" onclick="toggleCampusTreeExpand(this)">▾</span>';
    } else {
        html += '<span class="campus-tree-arrow" style="visibility:hidden;">▸</span>';
    }

    // checkbox
    html += '<input type="checkbox" class="campus-tree-check"'
        + ' onclick="onDiscountCampusCheck(this)" title="' + esc(node.name) + '">';

    // label
    html += '<span class="campus-tree-label">' + esc(node.name) + '</span>';

    // 父节点显示"全选"按钮
    if (hasChildren && !isCampus) {
        html += '<span class="dp-select-all" onclick="dpToggleAllChildren(this)">全选</span>';
    }

    html += '</div>';

    if (hasChildren) {
        html += '<div class="campus-tree-children">';
        node.children.forEach(function(child) {
            html += renderDiscountCampusTreeNode(child, level + 1);
        });
        html += '</div>';
    }
    html += '</div>';
    return html;
}
```

### 5.2 选中态同步 — 行级 `.checked` class

```javascript
// 校区 checkbox toggle → 同步行高亮
function onDiscountCampusCheck(el) {
    const node = el.closest('.campus-tree-node');
    if (!node) return;
    const checked = el.checked;

    // 传播到子节点
    node.querySelectorAll('.campus-tree-check').forEach(function(c) {
        c.checked = checked;
        c.indeterminate = false;
        syncTreeRowState(c);  // 新增：同步行 class
    });

    // 向上更新父节点三态
    const parent = node.parentElement && node.parentElement.closest('.campus-tree-node');
    if (parent) updateDiscountCampusParentState(parent);

    updateDiscountCampusCount();
}

// 新增：同步单行的选中态 class
function syncTreeRowState(checkEl) {
    const row = checkEl.closest('.campus-tree-row');
    if (!row) return;
    const node = checkEl.closest('.campus-tree-node');
    row.classList.toggle('checked', checkEl.checked);
    row.classList.toggle('semi-checked', checkEl.indeterminate);
}

// 修改父节点状态更新：添加行 class 同步
function updateDiscountCampusParentState(node) {
    const check = node.querySelector(':scope > .campus-tree-row > .campus-tree-check');
    const childChecks = node.querySelectorAll(
        ':scope > .campus-tree-children .campus-tree-row > .campus-tree-check'
    );
    if (!check || childChecks.length === 0) return;

    const checkedCount = Array.from(childChecks).filter(function(c) { return c.checked; }).length;

    if (checkedCount === 0) {
        check.checked = false; check.indeterminate = false;
    } else if (checkedCount === childChecks.length) {
        check.checked = true; check.indeterminate = false;
    } else {
        check.checked = false; check.indeterminate = true;
    }
    syncTreeRowState(check);  // 新增

    const parentNode = node.parentElement && node.parentElement.closest('.campus-tree-node');
    if (parentNode) updateDiscountCampusParentState(parentNode);
}
```

### 5.3 已选计数实时更新

```javascript
// 校区已选计数
function updateDiscountCampusCount() {
    const checks = document.querySelectorAll(
        '#discount-campus-tree .campus-tree-node[data-type="校区"] .campus-tree-check:checked'
    );
    const badge = document.getElementById('dp-campus-count');
    if (!badge) return;
    const n = checks.length;
    badge.textContent = n > 0 ? '已选 ' + n : '未选择';
    badge.classList.toggle('has-selection', n > 0);
}

// 学科已选计数
function updateDiscountSubjectCount() {
    const checks = document.querySelectorAll('#discount-subject-tree .campus-tree-check:checked');
    const badge = document.getElementById('dp-subject-count');
    if (!badge) return;
    const n = checks.length;
    badge.textContent = n > 0 ? '已选 ' + n : '未选择';
    badge.classList.toggle('has-selection', n > 0);
}
```

### 5.4 全选按钮功能

```javascript
// 点击「全选」文字按钮
function dpToggleAllChildren(el) {
    const node = el.closest('.campus-tree-node');
    if (!node) return;
    const childChecks = node.querySelectorAll(
        ':scope > .campus-tree-children .campus-tree-check'
    );
    const allChecked = Array.from(childChecks).every(function(c) { return c.checked; });
    childChecks.forEach(function(c) {
        c.checked = !allChecked;
        c.indeterminate = false;
        syncTreeRowState(c);
    });
    el.textContent = allChecked ? '全选' : '取消全选';
    // 更新父节点状态
    updateDiscountCampusParentState(node);
    updateDiscountCampusCount();
}
```

### 5.5 编辑回填增强

```javascript
// 回填时同步行高亮 + 计数
setTimeout(function() {
    (detail.campus_ids || []).forEach(function(cid) {
        var cb = document.querySelector(
            '#discount-campus-tree .campus-tree-node[data-id="' + cid + '"] .campus-tree-check'
        );
        if (cb) { cb.checked = true; syncTreeRowState(cb); }
    });
    // 更新所有父节点三态
    document.querySelectorAll('#discount-campus-tree .campus-tree-node[data-has-children="true"]')
        .forEach(function(n) { updateDiscountCampusParentState(n); });
    updateDiscountCampusCount();

    (detail.subject_ids || []).forEach(function(sid) {
        var cb = document.querySelector(
            '#discount-subject-tree .campus-tree-node[data-id="' + sid + '"] .campus-tree-check'
        );
        if (cb) { cb.checked = true; syncTreeRowState(cb); }
    });
    updateDiscountSubjectCount();
}, 300);
```

---

## 六、弹窗宽度调整

当前弹窗无 `modal-lg` 类，默认宽度 ~520px。卡片布局建议扩到 **680px**：

```html
<!-- index.php L8640: 将 dialog class 从 -->
<div class="modal-dialog">
<!-- 改为 -->
<div class="modal-dialog modal-lg">
```

或新建 `.modal-xl { width: 800px; }` 如果树区域较宽。

---

## 七、实施步骤

| Phase | 内容 | 预估 | 风险 |
|-------|------|------|------|
| **Phase 1** | HTML 重构（index.php L8640–8659）| 30min | 低：纯 HTML 替换，不改逻辑 |
| **Phase 2** | CSS 写作（style.css 追加 ~120 行）| 45min | 低：`#modal-discount-plan` 限定，不污染全局 |
| **Phase 3** | JS 适配（main.js 修改 ~60 行）| 45min | 中：需确保树函数不破坏其他页面复用 |
| **Phase 4** | 测试验证 | 20min | 低 |

### 实施顺序推荐

1. **先 CSS，后 HTML，再 JS** — CSS 先写好，HTML 替换后立即有视觉效果，JS 最后对接交互
2. 每 Phase 完成后用验证清单自查

### 验证清单

```bash
# 1. PHP 语法
php -l index.php  # "No syntax errors detected"

# 2. JS 无重复函数
grep -oP 'function \w+' static/js/main.js | sort | uniq -d
# 预期无输出（或 formatDate/escHtml 已知无害重复）

# 3. 新函数注册
grep -c 'function \(renderDiscountCampusTreeNode\|onDiscountCampusCheck\|syncTreeRowState\|updateDiscountCampusParentState\|updateDiscountCampusCount\|updateDiscountSubjectCount\|dpToggleAllChildren\)' static/js/main.js
# 应返回 7

# 4. 弹窗打开正常
# 浏览器: 打开优惠方案 → 点新增 → 检查卡片分区、树选中态、badge计数
```

---

## 八、风险与降级

| 风险 | 降级方案 |
|------|----------|
| `campus-tree-node` class 被其他页面复用 | 所有新 CSS 都用 `#modal-discount-plan` 前缀限定 |
| 树渲染函数不能改（课程表单也用）| 为优惠方案弹窗写**专用渲染函数** `renderDiscountCampusTreeNode` |
| 弹窗宽度不够 | 优先用 `modal-lg`（已定义 680px），不够加 `modal-xl` |
| 小屏布局溢出 | 已加 `@media (max-width: 600px)` 单列降级 |
| `nth-child` 偏移 | 本弹窗无隐藏 `<input>`，不需要处理 |

---

## 九、设计效果预览（CSS 渲染关键点）

```
卡片 1「基本信息」
├─ 浅紫渐变标题栏，带 📋 emoji
├─ 白色内容区，方案名称 input + 类型/金额 flex 同行
├─ focus 时卡片 border 变紫色 + 轻微 shadow
└─ 必填 '*' 红色标记

卡片 2「有效期」
├─ 浅紫渐变标题栏，带 📅 emoji
└─ 两个 date input 同行

卡片 3「适用范围」
├─ 浅紫渐变标题栏，带 🏫 emoji
├─ 校区 label + 紫色 badge「已选 3」
│   └─ 树容器：hover 紫边框 + focus 紫光晕
│       └─ 选中行：浅紫背景 + 左边紫竖线 + label 加粗深紫
├─ 学科 label + 紫色 badge「已选 5」
│   └─ 同样视觉
└─ 滚动条 6px 紫色系

Footer
├─ 浅灰背景
└─ 保存按钮：紫色阴影 + hover 上浮 1px
```

---

## 十、附录：CSS 变量扩展建议

如需更多紫色变体，可在 `:root` 补充：

```css
--color-primary-ghost: rgba(124,58,237,0.06);   /* 超浅紫背景 */
--color-primary-outline: rgba(124,58,237,0.15); /* focus ring 半透明 */
--color-primary-shadow: rgba(124,58,237,0.25);  /* 按钮阴影 */
```

当前方案中已用硬编码 rgba 值，后续可统一为变量。
