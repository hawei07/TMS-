# TMS 退费记录页面 UI 优化设计方案

> **设计日期**: 2026-07-07  
> **影响范围**: CSS only — `static/css/style.css`  
> **涉及面板**: `#panel-work-records` → `#tab-refund-records`  
> **设计原则**: 渐进增强，不改动 HTML 结构，不改动 JS 逻辑  
> **CSS 变量基础**: 系统已有紫色主题 `--color-primary: #7C3AED`

---

## 一、现状分析

### 1.1 当前 12 列表格

| 列 | 宽度 | 内容 | 问题 |
|---|------|------|------|
| 订单号 | 80px | monospace 12px | 字体偏小，行高不足 |
| 学员 | auto | 文本 | — |
| 项目 | 60px | 内联 style badge | 内联样式不一致，无 hover 反馈 |
| 内容 | auto | 课程名 | 长文本无省略 |
| 学科 | auto | subject_level1 | — |
| 校区 | auto | 文本 | — |
| 实退金额 | auto | 红粗 ¥xxx.xx | 无右对齐/衬线字体 |
| 扣减金额 | auto | ¥0.00 or ¥xxx.xx | 无右对齐 |
| 退费方式 | auto | 内联 style badge | 同上项目列问题 |
| 状态 | 80px | `.tag` class | 颜色方案与紫色主题不协调 |
| 申请时间 | 120px | 文本 | — |
| 操作 | 100px | 按钮组 | 按钮换行风险 |

### 1.2 当前筛选栏

```
[区域▾] [校区▾] [项目▾] [状态▾]                    [搜索...] [日期From]至[日期To] [搜索]
```

问题：
- 标签文字 `区域：` 与 select 视觉不统一
- 左右分区用 `margin-left:auto` 在窄屏下断裂
- select 和搜索框与 date input 圆角不一致
- 搜索按钮无焦点态

### 1.3 全局表格已有样式

系统已有斑马纹（`tbody tr:nth-child(even)`）、hover 高亮（`primary-bg`）、表头底色（`--color-bg`）、下划线改用 `primary-light`。但 `.tag` 颜色方案仍是原始 Ant Design 色板（橙/蓝/绿/红），未与紫色主题协调。

---

## 二、优化方案

### 方向 1：表格视觉优化

#### 1.1 统一 Badge 系统（替换内联 style）

**问题**：项目 badge 和退费方式 badge 使用内联 `style="..."`，无复用，无 hover，圆角不一致。

**方案**：创建 `.refund-badge` 基础类 + 语义化变体。

```css
/* === 退费记录 Badge 系统 === */

/* 基础圆角 pill */
.refund-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 500;
    line-height: 1.4;
    letter-spacing: 0.01em;
    white-space: nowrap;
    transition: all 0.15s ease;
}

/* 项目类型：课程 — 紫色系（与主题统一） */
.refund-badge-course {
    background: #EDE9FE;
    color: #7C3AED;
}

/* 项目类型：账户 — 琥珀色系（暖色微调，降低饱和度） */
.refund-badge-account {
    background: #FEF3C7;
    color: #B45309;
}

/* 退费方式：转账 — 紫色系 */
.refund-badge-transfer {
    background: #E0E7FF;
    color: #4338CA;
}

/* 退费方式：账户 — 翠绿系 */
.refund-badge-balance {
    background: #DCFCE7;
    color: #15803D;
}

/* hover 微交互 */
.refund-badge:hover {
    filter: brightness(0.96);
    transform: translateY(-1px);
}
```

HTML 映射关系：
| 旧 | 新 |
|---|-----|
| `<span style="...background:#e0e7ff;color:#4f46e5...">课程</span>` | `<span class="refund-badge refund-badge-course">课程</span>` |
| `<span style="...background:#fef3c7;color:#d97706...">账户</span>` | `<span class="refund-badge refund-badge-account">账户</span>` |
| `<span style="...background:#dcfce7;color:#16a34a...">账户</span>` | `<span class="refund-badge refund-badge-balance">账户</span>` |
| `<span style="...background:#e0e7ff;color:#4f46e5...">转账</span>` | `<span class="refund-badge refund-badge-transfer">转账</span>` |

#### 1.2 状态标签重新配色（与紫色主题协调）

```css
/* === 退费记录状态标签 — 紫色主题配色 === */

/* 覆盖全局 .tag，作用域限定在退费面板 */
#tab-refund-records .tag {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 500;
    line-height: 1.4;
    transition: all 0.15s ease;
}

/* 待审批 — 暖橙 */
#tab-refund-records .tag-orange {
    background: #FFF7ED;
    color: #C2410C;
}

/* 一级/二级审批通过 — 紫色调 */
#tab-refund-records .tag-blue {
    background: #EDE9FE;
    color: #7C3AED;
}

/* 已退费 — 翠绿 */
#tab-refund-records .tag-green {
    background: #ECFDF5;
    color: #059669;
}

/* 审批驳回 — 红（保持较高对比度） */
#tab-refund-records .tag-red {
    background: #FEF2F2;
    color: #DC2626;
}

/* 通用 hover */
#tab-refund-records .tag:hover {
    filter: brightness(0.95);
    transform: translateY(-1px);
}
```

#### 1.3 金额列对齐与字体优化

```css
/* 实退金额 — 数字右对齐 + tabular-nums + 衬体 */
#table-refund-records td:nth-child(7) {
    text-align: right;
    font-weight: 700;
    color: #DC2626;
    font-variant-numeric: tabular-nums;
    font-family: 'SF Mono', 'JetBrains Mono', 'Consolas', 'Menlo', monospace;
    font-size: 13px;
    letter-spacing: -0.02em;
}

/* 扣减金额 — 同右对齐 */
#table-refund-records td:nth-child(8) {
    text-align: right;
    font-variant-numeric: tabular-nums;
    font-family: 'SF Mono', 'JetBrains Mono', 'Consolas', 'Menlo', monospace;
    font-size: 13px;
    color: #6B7280;
}
```

#### 1.4 表格行高与 hover 增强

```css
/* 退费记录表格行 — 增强行间距 */
#table-refund-records tbody td {
    padding-top: 14px;
    padding-bottom: 14px;
}

/* hover 左竖条指示 + 微上浮 */
#table-refund-records tbody tr {
    border-left: 3px solid transparent;
    transition: all 0.18s ease;
}

#table-refund-records tbody tr:hover {
    background: var(--color-primary-bg) !important;
    border-left-color: var(--color-primary-light);
    transform: translateX(2px);
}

/* 斑马纹改用更淡的紫色底色 */
#tab-refund-records table tbody tr:nth-child(even) {
    background: #FAFAFE;
}
```

#### 1.5 订单号列 monospace 增强

```css
#table-refund-records td:first-child {
    font-family: 'SF Mono', 'JetBrains Mono', 'Consolas', monospace;
    font-size: 12px;
    color: var(--color-text-secondary);
    letter-spacing: 0.02em;
}
```

#### 1.6 内容列长文本省略

```css
#table-refund-records td:nth-child(4) {
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
```

#### 1.7 表头固定

```css
#tab-refund-records .table-wrap {
    max-height: calc(100vh - 340px);
    overflow-y: auto;
}

#tab-refund-records .table-wrap thead {
    position: sticky;
    top: 0;
    z-index: 10;
}

#tab-refund-records .table-wrap thead th {
    background: var(--color-bg);
    backdrop-filter: blur(8px);
}
```

---

### 方向 2：筛选栏优化

#### 2.1 紧凑表单组（Pill 风格统一）

将筛选栏的 label+select 转化为一致的外观：

```css
/* === 退费记录筛选栏优化 === */

/* 筛选行整行 pill 化 */
#tab-refund-records .toolbar {
    padding: 14px 24px;
    background: linear-gradient(180deg, #FFFFFF 0%, #FAFAFE 100%);
    border-bottom: 1px solid var(--color-border-light);
}

/* 左侧筛选组 */
#tab-refund-records .toolbar-left {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}

/* 筛选栏标签 — 轻量化 */
#tab-refund-records .toolbar-left label {
    font-size: 12px;
    color: var(--color-text-muted);
    margin: 0 2px 0 8px;
    font-weight: 500;
    white-space: nowrap;
}

/* 筛选栏 label 左间距更小 */
#tab-refund-records .toolbar-left label:first-child {
    margin-left: 0;
}

/* 统一 select 外观 */
#tab-refund-records .toolbar-left select {
    padding: 7px 28px 7px 12px;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    font-size: 13px;
    color: var(--color-text);
    background: var(--color-surface);
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: auto;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239895A8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
}

#tab-refund-records .toolbar-left select:hover {
    border-color: var(--color-primary-light);
}

#tab-refund-records .toolbar-left select:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.08);
}

/* 右侧搜索区 */
#tab-refund-records .toolbar-right {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

/* 搜索框增强 */
#tab-refund-records .toolbar-right input[type="text"] {
    padding: 7px 14px 7px 34px;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    font-size: 13px;
    width: 200px;
    transition: all 0.25s ease;
    background: var(--color-surface);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='%239895A8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'%3E%3C/circle%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'%3E%3C/line%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: left 10px center;
}

#tab-refund-records .toolbar-right input[type="text"]:focus {
    outline: none;
    width: 250px;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
}

/* 日期输入框统一 */
#tab-refund-records .toolbar-right input[type="date"] {
    padding: 7px 10px;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    font-size: 13px;
    width: 135px;
    color: var(--color-text);
    background: var(--color-surface);
    transition: all 0.2s ease;
}

#tab-refund-records .toolbar-right input[type="date"]:hover {
    border-color: var(--color-primary-light);
}

#tab-refund-records .toolbar-right input[type="date"]:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.08);
}

/* 「至」分隔符 */ 
#tab-refund-records .toolbar-right span {
    color: var(--color-text-muted);
    font-size: 12px;
}

/* 搜索按钮优化 */
#tab-refund-records .btn-primary.btn-sm {
    padding: 7px 18px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 8px;
    background: linear-gradient(135deg, #7C3AED, #6D28D9);
    border: none;
    color: #fff;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(124, 58, 237, 0.25);
}

#tab-refund-records .btn-primary.btn-sm:hover {
    background: linear-gradient(135deg, #8B5CF6, #7C3AED);
    box-shadow: 0 3px 10px rgba(124, 58, 237, 0.35);
    transform: translateY(-1px);
}

#tab-refund-records .btn-primary.btn-sm:active {
    transform: translateY(0);
    box-shadow: 0 1px 2px rgba(124, 58, 237, 0.2);
}
```

#### 2.2 操作列按钮优化

```css
/* 操作按钮组对齐 */
#table-refund-records td:last-child > div {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: nowrap;
}

/* 审批按钮 — 紫色主题主按钮 */
#table-refund-records .btn-primary.btn-sm {
    /* 继承上面的搜索按钮样式，此处特化表格内 */
    padding: 4px 14px;
    font-size: 12px;
}

/* 撤销按钮改用 link 风格（减少视觉重量） */
#table-refund-records .btn-warn.btn-sm {
    padding: 4px 14px;
    font-size: 12px;
    border-radius: 8px;
    background: transparent;
    color: var(--color-danger);
    border: 1px solid transparent;
}

#table-refund-records .btn-warn.btn-sm:hover {
    background: var(--color-danger-bg);
    border-color: rgba(229, 62, 62, 0.2);
    color: var(--color-danger);
}

/* 查看详情链接 */
#table-refund-records .btn-link {
    padding: 4px 12px;
    font-size: 12px;
    border-radius: 8px;
    background: transparent;
    border: 1px solid var(--color-border);
    color: var(--color-text-secondary);
}

#table-refund-records .btn-link:hover {
    background: var(--color-primary-bg);
    border-color: var(--color-primary-light);
    color: var(--color-primary);
}
```

---

### 方向 3：整体色调 — 浅紫色主题

#### 3.1 Section Tabs 紫色化

```css
/* 工作记录 section tabs 紫色主题 */
#panel-work-records .section-tabs {
    background: linear-gradient(180deg, #FFFFFF 0%, #F8F7FE 100%);
    border-bottom: 1px solid var(--color-border-light);
    padding: 0 24px;
}

#panel-work-records .sec-tab {
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 500;
    color: var(--color-text-muted);
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
}

#panel-work-records .sec-tab:hover {
    color: var(--color-primary);
    background: rgba(124, 58, 237, 0.04);
}

#panel-work-records .sec-tab.active {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
    font-weight: 600;
    background: transparent;
}

/* 面板头部 */
#panel-work-records .panel-header {
    background: linear-gradient(135deg, #FFFFFF 0%, #F8F7FE 100%);
}

#panel-work-records .panel-header h3 {
    color: var(--color-text);
}
```

#### 3.2 面板整体色温

```css
/* 退费记录面板底色微调 */
#tab-refund-records {
    background: #FAFAFE;
    border-radius: 0 0 var(--radius-xl) var(--radius-xl);
}

/* 表格包装器从纯白过渡 */
#tab-refund-records .table-wrap {
    background: var(--color-surface);
    border-radius: 0 0 var(--radius-xl) var(--radius-xl);
    box-shadow: 
        0 1px 3px rgba(0, 0, 0, 0.02),
        0 4px 16px rgba(124, 58, 237, 0.04);
}
```

---

### 方向 4：空态与加载态

#### 4.1 空态设计

```css
/* 退费记录空态 — 居中插画风格 */
#table-refund-records tbody tr.empty-row td {
    padding: 56px 20px;
    text-align: center;
    color: var(--color-text-muted);
    font-size: 14px;
}

#table-refund-records tbody tr.empty-row td::before {
    content: '';
    display: block;
    width: 64px;
    height: 64px;
    margin: 0 auto 16px;
    border-radius: 50%;
    background: linear-gradient(135deg, #EDE9FE, #DDD6FE);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='28' height='28' viewBox='0 0 24 24' fill='none' stroke='%237C3AED' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'%3E%3C/path%3E%3Cpolyline points='14 2 14 8 20 8'%3E%3C/polyline%3E%3Cline x1='16' y1='13' x2='8' y2='13'%3E%3C/line%3E%3Cline x1='16' y1='17' x2='8' y2='17'%3E%3C/line%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: center;
    background-size: 28px;
}

/* 空态第二行提示文字更淡 */
#table-refund-records tbody tr.empty-row td .empty-subtitle {
    display: block;
    font-size: 12px;
    color: var(--color-text-muted);
    margin-top: 4px;
    opacity: 0.7;
}
```

对应的 JS 空态 HTML 更新（`renderRefundRecordTable`）：
```javascript
// 原：
tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;color:#999;padding:30px;">暂无退费记录</td></tr>';

// 优化后：
tbody.innerHTML = '<tr class="empty-row"><td colspan="12">暂无退费记录<span class="empty-subtitle">退费申请将在此处显示</span></td></tr>';
```

#### 4.2 加载态

```css
/* 加载骨架屏 */
#table-refund-records tbody tr.skeleton-row td {
    padding: 14px 16px;
}

.skeleton-cell {
    height: 14px;
    border-radius: 4px;
    background: linear-gradient(90deg, #F3F0FF 25%, #EDE9FE 50%, #F3F0FF 75%);
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.5s infinite ease-in-out;
}

@keyframes skeleton-shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* 不同列宽的骨架块 */
.skeleton-sm { width: 60px; }
.skeleton-md { width: 100px; }
.skeleton-lg { width: 140px; }
```

JS 加载态插入（在 `loadRefundRecords` 中 fetch 前）：
```javascript
// 加载前显示骨架屏
tbody.innerHTML = Array.from({length: 5}, () => `
    <tr class="skeleton-row">
        <td><div class="skeleton-cell skeleton-sm"></div></td>
        <td><div class="skeleton-cell skeleton-md"></div></td>
        <td><div class="skeleton-cell skeleton-sm"></div></td>
        <td><div class="skeleton-cell skeleton-lg"></div></td>
        <td><div class="skeleton-cell skeleton-sm"></div></td>
        <td><div class="skeleton-cell skeleton-md"></div></td>
        <td><div class="skeleton-cell skeleton-md"></div></td>
        <td><div class="skeleton-cell skeleton-md"></div></td>
        <td><div class="skeleton-cell skeleton-sm"></div></td>
        <td><div class="skeleton-cell skeleton-md"></div></td>
        <td><div class="skeleton-cell skeleton-lg"></div></td>
        <td><div class="skeleton-cell skeleton-sm"></div></td>
    </tr>
`).join('');
```

---

### 方向 5：响应式适配

#### 5.1 筛选栏折叠

```css
/* 768px 以下：筛选栏垂直堆叠 */
@media (max-width: 768px) {
    #tab-refund-records .toolbar {
        flex-direction: column;
        padding: 12px 16px;
        gap: 10px;
    }

    #tab-refund-records .toolbar-left {
        flex-wrap: wrap;
        gap: 6px;
        width: 100%;
    }

    #tab-refund-records .toolbar-right {
        margin-left: 0;
        width: 100%;
        flex-wrap: wrap;
    }

    #tab-refund-records .toolbar-right input[type="text"] {
        width: 100%;
        flex: 1 1 auto;
    }

    #tab-refund-records .toolbar-right input[type="text"]:focus {
        width: 100%;
    }

    #tab-refund-records .toolbar-right input[type="date"] {
        flex: 1;
        min-width: 120px;
    }

    /* 搜索按钮全宽 */
    #tab-refund-records .btn-primary.btn-sm {
        width: 100%;
    }

    /* 表格横向滚动 */
    #tab-refund-records .table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    #table-refund-records {
        min-width: 900px;
    }
}
```

#### 5.2 1024px 中等屏适配

```css
@media (max-width: 1024px) {
    #tab-refund-records .toolbar {
        padding: 12px 20px;
    }

    #tab-refund-records .toolbar-left label {
        font-size: 11px;
    }

    #tab-refund-records .toolbar-left select,
    #tab-refund-records .toolbar-right select {
        font-size: 12px;
        padding: 6px 24px 6px 10px;
    }

    #table-refund-records td:nth-child(4) {
        max-width: 120px;
    }
}
```

---

### 方向 6：微交互动效

#### 6.1 表格行入场动画

```css
/* 行入场淡入 */
#table-refund-records tbody tr {
    animation: rowFadeIn 0.3s ease both;
}

#table-refund-records tbody tr:nth-child(1) { animation-delay: 0.00s; }
#table-refund-records tbody tr:nth-child(2) { animation-delay: 0.03s; }
#table-refund-records tbody tr:nth-child(3) { animation-delay: 0.06s; }
#table-refund-records tbody tr:nth-child(4) { animation-delay: 0.09s; }
#table-refund-records tbody tr:nth-child(5) { animation-delay: 0.12s; }
#table-refund-records tbody tr:nth-child(6) { animation-delay: 0.15s; }
#table-refund-records tbody tr:nth-child(7) { animation-delay: 0.18s; }
#table-refund-records tbody tr:nth-child(8) { animation-delay: 0.21s; }
#table-refund-records tbody tr:nth-child(9) { animation-delay: 0.24s; }
#table-refund-records tbody tr:nth-child(10) { animation-delay: 0.27s; }
#table-refund-records tbody tr:nth-child(n+11) { animation-delay: 0.30s; }

@keyframes rowFadeIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* 翻页时禁用动画（避免闪烁） */
#table-refund-records.paginating tbody tr {
    animation: none;
}
```

#### 6.2 分页按钮微交互

```css
/* 退费记录分页增强 */
#tab-refund-records .pagination button {
    transition: all 0.2s ease;
    border-radius: 8px;
}

#tab-refund-records .pagination button:hover:not(:disabled):not(.active) {
    background: var(--color-primary-bg);
    color: var(--color-primary);
    transform: translateY(-1px);
}

#tab-refund-records .pagination button.active {
    background: linear-gradient(135deg, #7C3AED, #6D28D9);
    color: #fff;
    box-shadow: 0 2px 8px rgba(124, 58, 237, 0.3);
}
```

---

## 三、实施计划

### 3.1 文件改动清单

| 文件 | 改动类型 | 内容 |
|------|---------|------|
| `static/css/style.css` | 追加 | ~300行 CSS（全部新样式） |
| `static/js/main.js` | 轻微改动 | `renderRefundRecordTable` 中内联 style 替换为 class 名；空态 HTML 增加 class |
| `index.php` | 不改 | HTML 结构保持不变 |

### 3.2 分步实施

**Phase 1 — CSS 落地（本次产出）**
- 将上面所有 CSS 追加到 `style.css` 末尾
- 使用 `#panel-work-records` / `#tab-refund-records` 作用域限定，不影响其他面板

**Phase 2 — JS 适配（需 Frontend Developer）**
- `renderRefundRecordTable` 函数中：
  - 项目 badge：内联 style → `<span class="refund-badge refund-badge-course">` / `refund-badge-account`
  - 退费方式 badge：内联 style → `<span class="refund-badge refund-badge-transfer">` / `refund-badge-balance`
  - 空态：增加 `class="empty-row"` + `<span class="empty-subtitle">`
  - 加载态：fetch 前显示 skeleton rows
- `loadRefundRecords` 函数中：
  - 切换页码时给 table 加/去 `paginating` class 控制入场动画

**Phase 3 — 验证**
```bash
# JS 重复函数检查
grep -oP 'function \w+' static/js/main.js | sort | uniq -d

# PHP 语法
php -l index.php

# CSS 括号平衡
python -c "c=open('static/css/style.css').read(); print('OK' if c.count('{')==c.count('}') else 'UNBALANCED')"
```

### 3.3 不影响范围

所有新 CSS 均使用 `#tab-refund-records` / `#panel-work-records` / `#table-refund-records` 作用域限定，不会影响：
- 其他面板的 toolbar / table / pagination / tag
- 其他 section tab（因选择器未命中）
- 弹窗样式（因 modal-overlay 不在 panel 内部）

---

## 四、效果预览说明

优化后的退费记录页面将呈现：

1. **筛选栏** — 统一的 8px 圆角 pill 风格表单，紫色渐变搜索按钮，hover 态 border-color 过渡
2. **表格** — 14px 行间距，左侧 3px 紫色竖条 hover 指示，斑马纹淡紫色，monospace 右对齐金额
3. **Badge 系统** — 课程紫/账户琥珀/转账靛/余额翠绿，统一 12px pill，hover 微上浮
4. **状态标签** — 紫色主题配色（待审批暖橙 / 审批中紫 / 已退费绿 / 驳回红）
5. **空态** — 居中文件图标 + 主副文字
6. **加载态** — 紫色 shimmer 骨架屏
7. **响应式** — 768px 折叠筛选栏，1024px 微调间距
8. **动效** — 行入场 staggered fadeIn，hover 左移+变色，按钮 press 反馈

---

*本文档为纯 UI 设计方案，CSS 代码可直接复制到 style.css 末尾（#panel-work-records 区块下）。JS 适配由 Frontend Developer 角色按 Phase 2 执行。*
