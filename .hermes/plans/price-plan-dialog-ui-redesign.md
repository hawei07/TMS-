# 价格方案弹窗 UI 重新设计

> **版本**: v1.0 | **日期**: 2026-07-07 | **状态**: 待实施
>
> **关联模块**: 价格管理 / 报价单 / modal-price / modal-price-item / modal-price-plan

---

## 1. 现状分析

### 1.1 代码定位表

| 层面 | 文件 | 行号 |
|------|------|------|
| HTML (主弹窗) | `index.php` | 7788–7816 |
| HTML (价格方案名) | `index.php` | 7819–7827 |
| HTML (报价单) | `index.php` | 7830–7840 |
| CSS | `static/css/style.css` | 1576–1667 |
| JS | `static/js/main.js` | 2980–3457 |

### 1.2 当前 DOM 结构（主弹窗 `modal-price`）

```
#modal-price
└─ .modal.modal-xl (max-width:900px)
   ├─ .modal-header
   │  ├─ h3#modal-price-title
   │  └─ button.modal-close
   ├─ .modal-body (display:flex; height:420px; overflow:hidden; padding:0)  ← inline style
   │  ├─ .price-left (220px, border-right)
   │  │  ├─ .price-left-header "价格方案"
   │  │  ├─ .price-plan-list-wrap#price-plan-list
   │  │  │  └─ .price-plan-item (每个方案，含 .active 态)
   │  │  │     ├─ .price-plan-name (名称 + tag)
   │  │  │     └─ .price-plan-actions (hover 显示 编辑/删除)
   │  │  └─ .price-left-footer
   │  │     └─ button "+ 新增方案"
   │  └─ .price-right (flex:1, flex column)
   │     ├─ .price-right-header "报价单列表" + span#price-plan-type-tag
   │     ├─ .price-item-table-wrap
   │     │  └─ table.price-item-table
   │     │     ├─ thead (5 列: 名称/课时/单价/实付/操作)
   │     │     └─ tbody#price-item-table-body
   │     └─ .price-right-footer
   │        └─ button "+ 新增报价单"
   └─ .modal-footer
      └─ button "关闭"
```

### 1.3 问题清单

| # | 问题 | 位置 | 影响 |
|---|------|------|------|
| P1 | modal-body 用 inline style（`display:flex; height:420px...`） | index.php:7790 | 维护困难，无法响应式调整 |
| P2 | 方案列表与右侧表格视觉割裂——边框是 `#E2E0E7`，表格斑马纹缺失，hover 毫无反馈 | style.css:1576-1667 | 行定位困难，密集数据阅读疲劳 |
| P3 | 价格方案卡片样式单调——仅 2px margin + 6px radius，active 态仅颜色变化 | style.css:1596-1607 | 选中反馈弱 |
| P4 | 右侧表格无 sticky 表头 | style.css:1642-1651 | 行多时滚动看不到列名 |
| P5 | 总计行背景 `#f0f4ff`（浅蓝），与紫色主题不一致 | style.css:1657-1663 | 配色不协调 |
| P6 | 模态底部 footer 仅一个「关闭」按钮，缺乏层次感 | index.php:7815 | — |
| P7 | 新增/编辑报价单弹窗（modal-price-item）无任何专属 CSS 作用域优化 | index.php:7830-7840 | 输入框圆角/间距/光晕全依赖全局 form-group，无增强 |
| P8 | 报价单表格列间距 8px 偏拥挤，操作列裸 `btn-link` | style.css:1653-1655 | 误触风险 |
| P9 | 没有使用 `--color-*` CSS 变量体系中的 `--color-primary-bg` / `--color-primary-hover`（只在 `.price-plan-item.active` 用了一次，hover 不可见） | style.css:1596-1608 | 不符合项目 UI 现代化方向 |

---

## 2. 设计目标

| # | 目标 | 可验证标准 |
|---|------|-----------|
| G1 | **左右面板布局现代化** — 方案列表区和报价单列表区视觉统一，卡片式 + 紫色主题贯穿 | 所有 inline style 替换为 CSS class，`.price-left`/`.price-right` 共享渐变背景 |
| G2 | **新增/编辑报价单弹窗卡片式** — `modal-price-item` 使用卡片分区，与优惠管理弹窗统一风格 | form-group → dp-card 卡片分组；输入控件统一圆角 + focus 4px 紫光晕 |
| G3 | **紫色主题全面贯彻** — 选中态、hover、总计行、footer 渐变全部使用 `--color-primary*` 变量 | grep `#7C3AED` 价格弹窗区域结果为 0 |
| G4 | **输入控件统一增强** — 报价单弹窗的 input/select 统一 8px 圆角 + focus 紫光晕 + hover 浅紫边框 | 所有 `#modal-price-item input` / `#modal-price-item select` 有专属规则 |
| G5 | **表格间距/hover 优化** — td padding 增至 10px 14px + hover 左紫竖线 + 行入场动画 | 视觉对比验收 |
| G6 | **不改 JS 逻辑 / DOM ID** — 仅换 CSS + HTML 容器 class | 所有 `getElementById` 参数不变 |

---

## 3. 优化方案

### 3.1 新版 DOM 结构（主弹窗 `modal-price`）

```
#modal-price
└─ .modal.modal-xl (max-width:960px)                                    ← 宽增至 960px
   ├─ .modal-header                                                    ← 不变
   │  ├─ h3#modal-price-title
   │  └─ button.modal-close
   ├─ .modal-body.price-body                                            ← 新增 class
   │  ├─ .price-left.price-panel                                        ← 新 class
   │  │  ├─ .price-panel-header                                        ← 新 class（原 .price-left-header）
   │  │  │  ├─ 📦 价格方案
   │  │  │  └─ span.price-plan-count-badge                             ← 新增：方案计数
   │  │  ├─ .price-panel-list#price-plan-list                          ← 新 class
   │  │  │  └─ .price-plan-card (每项)                                 ← 原 .price-plan-item → 升级为 card
   │  │  │     ├─ .price-plan-card-body
   │  │  │     │  ├─ .price-plan-card-name
   │  │  │     │  └─ .tag (type tag)
   │  │  │     └─ .price-plan-card-actions (hover 显示)
   │  │  └─ .price-panel-footer
   │  │     └─ button.btn.btn-primary.btn-sm (紫色渐变)
   │  └─ .price-right.price-detail                                      ← 新 class
   │     ├─ .price-detail-header                                       ← 新 class
   │     │  ├─ 报价单列表
   │     │  └─ span#price-plan-type-tag
   │     ├─ .price-detail-table-wrap                                   ← 新 class（sticky header + scroll）
   │     │  └─ table#price-item-table                                  ← 新 id
   │     │     ├─ thead (sticky)
   │     │     └─ tbody#price-item-table-body
   │     │        └─ tr.price-total-row                                ← 紫色总行
   │     └─ .price-detail-footer
   │        └─ button.btn.btn-primary.btn-sm (紫色渐变)
   └─ .modal-footer (关闭按钮)
```

### 3.2 CSS 类体系（新增/改动）

以下所有 CSS 规则追加到 `static/css/style.css` 末尾，或替换现有 `.price-*` 规则。

#### 3.2.1 弹窗容器增强

```css
/* ===== 价格方案弹窗容器 ===== */
#modal-price .modal {
    width: 960px;
    max-width: 95vw;
}

#modal-price .modal-body.price-body {
    display: flex;
    height: 500px;                     /* 420px → 500px，更舒适 */
    overflow: hidden;
    padding: 0;
    background: var(--color-bg);       /* 浅紫灰底 */
}

/* ===== 左侧面板 ===== */
.price-panel {
    width: 240px;                      /* 220→240 */
    min-width: 240px;
    display: flex;
    flex-direction: column;
    border-right: 1px solid var(--color-border);
    background: var(--color-surface);
}

.price-panel-header {
    padding: 14px 16px;
    font-weight: 700;
    font-size: 14px;
    color: var(--color-primary-dark);
    background: linear-gradient(135deg, var(--color-primary-bg), #FAF8FF);
    border-bottom: 1px solid var(--color-border-light);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* 方案计数 badge */
.price-plan-count-badge {
    display: inline-flex;
    align-items: center;
    height: 20px;
    padding: 0 8px;
    font-size: 11px;
    font-weight: 600;
    color: var(--color-primary);
    background: rgba(124,58,237,0.08);
    border-radius: 9999px;
}

.price-panel-list {
    flex: 1;
    overflow-y: auto;
    padding: 6px;
}

/* 右侧滚动条美化 */
.price-panel-list::-webkit-scrollbar { width: 5px; }
.price-panel-list::-webkit-scrollbar-track { background: transparent; }
.price-panel-list::-webkit-scrollbar-thumb {
    background: var(--color-border);
    border-radius: 3px;
}
.price-panel-list::-webkit-scrollbar-thumb:hover {
    background: var(--color-primary-light);
}

/* ===== 价格方案卡片 ===== */
.price-plan-card {
    display: flex;
    align-items: center;
    padding: 10px 14px;
    margin: 3px 0;
    border-radius: var(--radius-md);
    cursor: pointer;
    font-size: 13px;
    border: 1.5px solid transparent;
    border-left: 3px solid transparent;
    transition: all 0.18s ease;
}

.price-plan-card:hover {
    background: var(--color-primary-hover);
    border-color: var(--color-primary-light);
    transform: translateX(2px);
}

.price-plan-card.active {
    background: var(--color-primary-bg);
    border-color: var(--color-primary);
    border-left-color: var(--color-primary);
    color: var(--color-primary);
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(124,58,237,0.12);
    transform: none;
}

.price-plan-card-body {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 6px;
    overflow: hidden;
}

.price-plan-card-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.price-plan-card-actions {
    display: none;
    gap: 2px;
    flex-shrink: 0;
}

.price-plan-card:hover .price-plan-card-actions {
    display: flex;
}

.price-plan-card-actions .btn-link {
    font-size: 11px;
    padding: 2px 6px;
    color: var(--color-primary);
}

.price-plan-card-actions .btn-link-danger {
    font-size: 11px;
    padding: 2px 6px;
}

/* 面板底部 */
.price-panel-footer {
    padding: 10px 8px;
    border-top: 1px solid var(--color-border-light);
    background: var(--color-bg);
}

.price-panel-footer .btn-primary {
    background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
    border: none;
    box-shadow: 0 1px 4px rgba(124,58,237,0.2);
    transition: all 0.18s;
}
.price-panel-footer .btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 12px rgba(124,58,237,0.3);
}

/* ===== 右侧报价单区 ===== */
.price-detail {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--color-surface);
}

.price-detail-header {
    padding: 14px 20px;
    font-weight: 700;
    font-size: 14px;
    color: var(--color-text);
    border-bottom: 1px solid var(--color-border);
    display: flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(180deg, #FFFFFF, var(--color-bg));
}

.price-detail-table-wrap {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}

/* 表格滚动条 */
.price-detail-table-wrap::-webkit-scrollbar { width: 6px; }
.price-detail-table-wrap::-webkit-scrollbar-thumb {
    background: var(--color-border);
    border-radius: 3px;
}

/* ===== 报价单表格 ===== */
#price-item-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

/* 表头 sticky */
#price-item-table thead {
    position: sticky;
    top: 0;
    z-index: 10;
}

#price-item-table th {
    padding: 12px 14px;
    text-align: left;
    font-weight: 600;
    font-size: 12px;
    color: var(--color-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    background: var(--color-bg);
    backdrop-filter: blur(8px);
    border-bottom: 2px solid var(--color-border);
    white-space: nowrap;
}

/* 表格单元格 */
#price-item-table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--color-border-light);
    transition: background 0.15s;
}

/* 行 hover — 左紫竖线 */
#price-item-table tbody tr {
    transition: all 0.15s ease;
    border-left: 3px solid transparent;
}

#price-item-table tbody tr:hover {
    background: var(--color-primary-hover);
    border-left-color: var(--color-primary);
}

/* 行交替颜色 */
#price-item-table tbody tr:nth-child(even) {
    background: rgba(245,240,255,0.25);
}
#price-item-table tbody tr:nth-child(even):hover {
    background: var(--color-primary-hover);
}

/* 金额列右对齐 */
#price-item-table td:nth-child(3),
#price-item-table td:nth-child(4),
#price-item-table th:nth-child(3),
#price-item-table th:nth-child(4) {
    text-align: right;
}

/* 金额列 tabular-nums */
#price-item-table td:nth-child(3),
#price-item-table td:nth-child(4) {
    font-variant-numeric: tabular-nums;
    font-weight: 500;
}

/* 实付金额列强调 */
#price-item-table td:nth-child(4) {
    color: var(--color-primary-dark);
    font-weight: 700;
}

/* 操作列 */
#price-item-table td:last-child,
#price-item-table th:last-child {
    text-align: center;
    width: 120px;
}

/* ===== 总计行 ===== */
#price-item-table .price-total-row td {
    font-weight: bold;
    border-top: 2px solid var(--color-primary-light);
    border-bottom: 2px solid var(--color-primary-light);
    background: linear-gradient(90deg, var(--color-primary-bg), rgba(245,240,255,0.4));
    padding: 12px 14px;
}

/* 行入场动画（渐入 + 上移） */
#price-item-table tbody tr {
    animation: priceRowFadeIn 0.3s ease both;
}
#price-item-table tbody tr:nth-child(1)  { animation-delay: 0.00s; }
#price-item-table tbody tr:nth-child(2)  { animation-delay: 0.03s; }
#price-item-table tbody tr:nth-child(3)  { animation-delay: 0.06s; }
#price-item-table tbody tr:nth-child(4)  { animation-delay: 0.09s; }
#price-item-table tbody tr:nth-child(5)  { animation-delay: 0.12s; }
#price-item-table tbody tr:nth-child(6)  { animation-delay: 0.15s; }
#price-item-table tbody tr:nth-child(7)  { animation-delay: 0.18s; }
#price-item-table tbody tr:nth-child(8)  { animation-delay: 0.21s; }
#price-item-table tbody tr:nth-child(9)  { animation-delay: 0.24s; }
#price-item-table tbody tr:nth-child(10) { animation-delay: 0.27s; }
#price-item-table tbody tr:nth-child(n+11) { animation-delay: 0.30s; }
#price-item-table.paginating tbody tr { animation: none; }

@keyframes priceRowFadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* 细节底部 */
.price-detail-footer {
    padding: 12px 20px;
    border-top: 1px solid var(--color-border-light);
    background: var(--color-bg);
}

.price-detail-footer .btn-primary {
    background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
    border: none;
    box-shadow: 0 1px 4px rgba(124,58,237,0.2);
    transition: all 0.18s;
}
.price-detail-footer .btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 12px rgba(124,58,237,0.3);
}

/* ===== Footer 增强 ===== */
#modal-price .modal-footer {
    padding: 14px 24px;
    border-top: 1px solid var(--color-border-light);
    background: var(--color-bg);
}

#modal-price .modal-footer .btn-outline {
    padding: 8px 24px;
    border-radius: var(--radius-md);
    border: 1.5px solid var(--color-border);
    color: var(--color-text-secondary);
    transition: all 0.15s;
}
#modal-price .modal-footer .btn-outline:hover {
    border-color: var(--color-primary);
    color: var(--color-primary);
    background: var(--color-primary-bg);
}
```

#### 3.2.2 新增/编辑报价单弹窗样式（`modal-price-item`）

```css
/* ===== 报价单编辑弹窗 ===== */
#modal-price-item .modal {
    width: 560px;
    max-width: 95vw;
}

#modal-price-item .modal-body {
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* 卡片包裹 */
#modal-price-item .pi-card {
    background: var(--color-bg);
    border: 1px solid var(--color-border-light);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

#modal-price-item .pi-card:focus-within {
    border-color: var(--color-primary-light);
    box-shadow: 0 0 0 3px rgba(124,58,237,0.08);
}

#modal-price-item .pi-card-title {
    padding: 12px 18px;
    font-weight: 700;
    font-size: 13px;
    color: var(--color-primary-dark);
    background: linear-gradient(135deg, var(--color-primary-bg), rgba(245,240,255,0.3));
    border-bottom: 1px solid var(--color-border-light);
}

#modal-price-item .pi-card-body {
    padding: 16px 18px;
    background: var(--color-surface);
}

/* 双列布局 */
#modal-price-item .pi-card-body .form-row {
    display: flex;
    gap: 16px;
}

#modal-price-item .pi-card-body .form-row .form-group {
    flex: 1;
    margin-bottom: 0;
}

/* 输入控件统一增强 */
#modal-price-item input,
#modal-price-item select {
    padding: 10px 14px;
    border: 1.5px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: 14px;
    width: 100%;
    box-sizing: border-box;
    transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
    background: var(--color-surface);
    outline: none;
}

#modal-price-item input:hover:not(:disabled):not([readonly]),
#modal-price-item select:hover {
    border-color: var(--color-primary-light);
}

#modal-price-item input:focus,
#modal-price-item select:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 4px rgba(124,58,237,0.10);
    background: #FAF8FF;
}

/* readonly 态 */
#modal-price-item input[readonly] {
    background: var(--color-bg);
    color: var(--color-text-secondary);
    cursor: not-allowed;
}

/* placeholder */
#modal-price-item input::placeholder {
    color: var(--color-text-muted);
    opacity: 0.6;
}

/* form-group 间距微调 */
#modal-price-item .form-group {
    margin-bottom: 14px;
}

#modal-price-item .form-group:last-child {
    margin-bottom: 0;
}

#modal-price-item .form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 600;
    color: var(--color-text-secondary);
}

#modal-price-item .form-group label .required {
    color: var(--color-danger);
}

/* 实际支付价格字段高亮 */
#modal-price-item .pi-actual-price-group label {
    color: var(--color-primary-dark);
}

/* Footer */
#modal-price-item .modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--color-border-light);
    background: var(--color-bg);
}

#modal-price-item .modal-footer .btn-primary {
    padding: 10px 28px;
    font-weight: 600;
    border-radius: var(--radius-md);
    background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
    border: none;
    box-shadow: 0 2px 8px rgba(124,58,237,0.25);
    transition: all 0.18s;
}
#modal-price-item .modal-footer .btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(124,58,237,0.35);
}

#modal-price-item .modal-footer .btn-outline {
    padding: 10px 24px;
    border-radius: var(--radius-md);
    border: 1.5px solid var(--color-border);
    color: var(--color-text-secondary);
    transition: all 0.15s;
}
#modal-price-item .modal-footer .btn-outline:hover {
    border-color: var(--color-primary);
    color: var(--color-primary);
    background: var(--color-primary-bg);
}
```

#### 3.2.3 新增/编辑价格方案弹窗微调（`modal-price-plan`）

```css
/* ===== 价格方案名称弹窗微调 ===== */
#modal-price-plan input,
#modal-price-plan select {
    padding: 10px 14px;
    border: 1.5px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: 14px;
    width: 100%;
    box-sizing: border-box;
    transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
    outline: none;
}

#modal-price-plan input:focus,
#modal-price-plan select:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 4px rgba(124,58,237,0.10);
    background: #FAF8FF;
}

#modal-price-plan .modal-footer .btn-primary {
    background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
    border: none;
    box-shadow: 0 2px 8px rgba(124,58,237,0.25);
}
#modal-price-plan .modal-footer .btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(124,58,237,0.35);
}
```

### 3.3 HTML 重构对照表

#### 3.3.1 主弹窗 `modal-price`

| 当前 | 优化后 |
|------|--------|
| `<div class="modal modal-xl" style="max-width:900px;">` | `<div class="modal modal-xl">`（宽度由 CSS 控制 960px） |
| `<div class="modal-body" style="display:flex;height:420px;overflow:hidden;padding:0;">` | `<div class="modal-body price-body">` |
| `<div class="price-left">` | `<div class="price-left price-panel">` |
| `<div class="price-left-header">价格方案</div>` | `<div class="price-panel-header">📦 价格方案 <span class="price-plan-count-badge" id="price-plan-count">0</span></div>` |
| `<div class="price-plan-list-wrap" id="price-plan-list">` | `<div class="price-panel-list" id="price-plan-list">` |
| `<div class="price-left-footer"><button class="btn btn-primary btn-sm" onclick="addPlan()" style="width:100%;">+ 新增方案</button></div>` | `<div class="price-panel-footer"><button class="btn btn-primary btn-sm" onclick="addPlan()" style="width:100%;">+ 新增方案</button></div>` |
| `<div class="price-right">` | `<div class="price-right price-detail">` |
| `<div class="price-right-header">报价单列表 <span id="price-plan-type-tag"></span></div>` | `<div class="price-detail-header">报价单列表 <span id="price-plan-type-tag"></span></div>` |
| `<div class="price-item-table-wrap">` | `<div class="price-detail-table-wrap">` |
| `<table class="price-item-table">` | `<table id="price-item-table">`（同时保留 class 向下兼容） |
| `<div class="price-right-footer">` | `<div class="price-detail-footer">` |
| 总计行 `<tr class="price-total-row">` | 不变（CSS 接管） |

#### 3.3.2 报价单弹窗 `modal-price-item`（卡片式重构）

| 当前 | 优化后 |
|------|--------|
| `<div class="modal-body">` (plain) | `<div class="modal-body">`（含卡片结构） |
| `<div class="form-group"><label>报价单名称...</label><input...></div>` | 包裹在 `.pi-card` > `.pi-card-body` 中 |
| `<div class="form-group"><label>课时数量...</label><input...></div>` | 同上，使用 `.form-row` 双列 |
| `<div class="form-group"><label>课时价格...</label><input...></div>` | 同上 |
| `<div class="form-group"><label>实际支付价格</label><input...readonly...></div>` | 同上，加 `.pi-actual-price-group` |

**新版 `modal-price-item` HTML 结构：**

```html
<div class="modal-overlay" id="modal-price-item">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-price-item-title">新增报价单</h3>
            <button class="modal-close" onclick="closeModal('modal-price-item')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="edit-price-item-id">

            <!-- 卡片：基本信息 -->
            <div class="pi-card">
                <div class="pi-card-title">📋 基本信息</div>
                <div class="pi-card-body">
                    <div class="form-group">
                        <label>报价单名称 <span class="required">*</span></label>
                        <input type="text" id="price-item-name" maxlength="50" placeholder="如：32课时包">
                    </div>
                    <div class="form-group">
                        <label>课时数量 <span class="required">*</span></label>
                        <input type="number" id="price-item-lesson-count" min="1" placeholder="请输入课时数量">
                    </div>
                </div>
            </div>

            <!-- 卡片：价格设置 -->
            <div class="pi-card">
                <div class="pi-card-title">💰 价格设置</div>
                <div class="pi-card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>课时价格 <span class="required">*</span></label>
                            <input type="number" id="price-item-unit-price" step="0.01" min="0" placeholder="请输入课时价格" oninput="onUnitPriceChange()">
                        </div>
                        <div class="form-group pi-actual-price-group">
                            <label>实际支付价格</label>
                            <input type="number" id="price-item-actual-price" step="0.01" min="0" readonly placeholder="自动同步课时价格">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 卡片：优惠关联（入口，为 PRD Phase 4 预留） -->
            <!-- <div class="pi-card" id="pi-discount-card" style="display:none;"> ... </div> -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-price-item')">取消</button>
            <button class="btn btn-primary" onclick="saveItem()">保存</button>
        </div>
    </div>
</div>
```

#### 3.3.3 空态文本调整

| 当前 | 优化后 |
|------|--------|
| `<div style="padding:20px;color:#999;text-align:center;">暂无价格方案<br>请点击下方按钮新增</div>` | `<div class="price-empty-state">暂无价格方案<span class="price-empty-subtitle">点击下方按钮新增方案</span></div>` |
| `<tr><td colspan="5" style="text-align:center;color:#999;">暂无报价单...</td></tr>` | `<tr class="price-empty-row"><td colspan="5">暂无报价单<span class="price-empty-subtitle">请点击下方按钮新增</span></td></tr>` |

对应 CSS（追加到 `style.css`）：

```css
/* 空态 */
.price-empty-state {
    padding: 40px 20px;
    text-align: center;
    color: var(--color-text-muted);
    font-size: 13px;
}

.price-empty-subtitle {
    display: block;
    font-size: 11px;
    margin-top: 6px;
    opacity: 0.6;
}

#price-item-table tbody tr.price-empty-row td {
    padding: 48px 20px;
    text-align: center;
    color: var(--color-text-muted);
    font-size: 13px;
    animation: none;
    border-left: none;
}
```

### 3.4 JS 适配变更

**无需 JS 逻辑变更。** 仅需 3 处微小调整：

| # | 函数 | 变更 | 原因 |
|---|------|------|------|
| 1 | `renderPlanList()` (line 3173) | `.price-plan-item` → `.price-plan-card`，内部结构更新 `.price-plan-name` → `.price-plan-card-name`，`.price-plan-actions` → `.price-plan-card-actions` | HTML class 更名 |
| 2 | `renderPlanList()` | 追加 `document.getElementById('price-plan-count').textContent = currentPlans.length` | 更新计数 badge |
| 3 | `renderItemList()` (line 3201, 3207, 3220) | colspan `5` → 不变（暂不加列） | 如果 PRD Phase 3 加优惠方案列，届时改为 `7` |

**修改后的 `renderPlanList()` 模板行：**

```javascript
// 第 3185-3191 行 → 改为：
return `<div class="price-plan-card${activeClass}" data-plan-id="${p.id}" onclick="selectPlan(${p.id})">
    <div class="price-plan-card-body">
        <span class="price-plan-card-name">${esc(p.name)}</span>
        ${typeTag}
    </div>
    <span class="price-plan-card-actions">
        <button class="btn-link" onclick="event.stopPropagation();editPlan(${p.id})">编辑</button>
        <button class="btn-link-danger" onclick="event.stopPropagation();deletePlan(${p.id})">删除</button>
    </span>
</div>`;
```

并在 `renderPlanList()` 末尾追加（第 3193 行 `}` 之前）：

```javascript
// 更新方案计数
const countEl = document.getElementById('price-plan-count');
if (countEl) countEl.textContent = currentPlans.length;
```

---

## 4. 实施计划

| Phase | 内容 | 文件 | 预估 |
|-------|------|------|------|
| **Phase 1** | CSS 追加：所有 3.2 节 CSS 规则追加到 `style.css` 末尾 | `style.css` | 10 min |
| **Phase 2** | HTML 重构：按 3.3 节对照表替换 `modal-price` + `modal-price-item` | `index.php` | 10 min |
| **Phase 3** | JS 微调：`renderPlanList` 模板 + 计数 badge | `main.js` | 5 min |
| **Phase 4** | 验证：CSS brace balance + PHP syntax + JS dupe check + 浏览器实测 | — | 5 min |

**总计**: 约 30 min

### Phase 4 验证命令

```bash
# 1. CSS 花括号平衡
python -c "c=open('static/css/style.css','r',encoding='utf-8').read();print('OK' if c.count('{')==c.count('}') else 'UNBALANCED')"

# 2. PHP 语法检查
php -l index.php

# 3. JS 无重复函数
grep -oP 'function \w+' static/js/main.js | sort | uniq -d

# 4. DOM ID 一致性（JS getElementById 参数 vs HTML id）
grep -oP "getElementById\('\\K[^']+" static/js/main.js | sort -u > /tmp/js_ids.txt
grep -oP 'id="\\K[^"]+' index.php | sort -u > /tmp/html_ids.txt
# 手工交叉对比
```

---

## 5. 兼容性矩阵

| 变更项 | 后端 API | 周边弹窗 | 报名页 | 其他页面 |
|--------|----------|----------|--------|----------|
| CSS 追加 `#modal-price` 作用域 | ✅ 无影响 | ✅ `#modal-price` 作用域隔离 | ✅ | ✅ |
| CSS 追加 `#modal-price-item` 作用域 | ✅ | ✅ | ✅ | ✅ |
| HTML class 更名 (`.price-left-header` → `.price-panel-header`) | ✅ | ✅ | ✅ | ✅ |
| HTML 表格 id 新增 (`#price-item-table`) | ✅ | ✅ | ✅ | ✅ |
| JS `renderPlanList` class 字符串替换 | ✅ ID 不变 | ✅ | ✅ | ✅ |
| DOM ID 不变（`price-item-name` 等全部保留） | ✅ | ✅ | ✅ | ✅ |

---

## 6. 效果对比

| 维度 | 当前 | 优化后 |
|------|------|--------|
| **左侧方案列表** | 平铺 item，active 仅颜色变化 | 卡片式，3px 左紫竖线 + 紫 border + 浅阴影 active 态 |
| **表格 hover** | 无任何反馈 | 浅紫背景 + 左紫竖线 |
| **表格总计行** | 蓝色背景 `#f0f4ff` | 紫色渐变 `--color-primary-bg` + 紫 border |
| **表格行长** | td padding 8px 拥挤 | 10px 14px 舒适间距 |
| **金额列** | 左对齐，无强调 | 右对齐 + tabular-nums + 实付紫色加粗 |
| **sticky 表头** | 无（thead 有 sticky 但无 backdrop-filter） | sticky + `backdrop-filter: blur(8px)` |
| **行入场动画** | 无 | staggered fadeIn + translateY(6px) |
| **行交替色** | 无 | 浅紫斑马纹 |
| **输入控件** | 无专属增强，裸全局 form-group | 统一 8px 圆角 + hover 浅紫边框 + focus 4px 紫光晕 |
| **报价单弹窗** | 平铺 4 个 form-group | 2 张卡片分区（基本信息 / 价格设置）+ 双列布局 |
| **紫色主题** | 底部按钮无渐变 | 全部 btn-primary 紫色渐变 + shadow |
| **JS 改动量** | — | ~8 行（仅 class 字符串替换 + 计数 badge） |
| **新增/编辑方案弹窗** | input/select 无专属 CSS | focus 紫光晕 + 按钮紫色渐变 |
| **inline style** | 6 处 | 0 处（全部移入 CSS） |

---

## 7. 附录

### 7.1 CSS 变量引用表

| 变量 | 值 | 用途 |
|------|-----|------|
| `--color-primary` | `#7C3AED` | 主紫色 |
| `--color-primary-light` | `#A78BFA` | 浅紫（border-hover） |
| `--color-primary-dark` | `#6D28D9` | 深紫（渐变终点、标题色） |
| `--color-primary-bg` | `#F5F0FF` | 紫色背景（active、总计行） |
| `--color-primary-hover` | `#EDE5FF` | 紫色 hover |
| `--color-border` | `#E2E0E7` | 主边框 |
| `--color-border-light` | `#F0EFF4` | 浅边框 |
| `--color-text` | `#1A1A2E` | 主文字 |
| `--color-text-secondary` | `#6B7280` | 次文字（标签、表头） |
| `--color-text-muted` | `#9CA3AF` | 弱文字（placeholder、空态） |
| `--color-surface` | `#FFFFFF` | 卡片/表格白色背景 |
| `--color-bg` | `#F8F7FC` | 页面级浅灰紫背景 |
| `--color-danger` | `#DC2626` | 必填*标记 |
| `--radius-sm` | `6px` | tab/badge |
| `--radius-md` | `8px` | 输入框圆角 |
| `--radius-lg` | `12px` | 卡片圆角 |
| `--radius-xl` | `16px` | modal 大圆角 |

### 7.2 关键设计决策

| 决策 | 理由 |
|------|------|
| 主弹窗宽度 900→960px | 报价单表格列数将随 PRD 增至 7 列（优惠方案 / 优惠券），需更多空间 |
| 高度 420→500px | 更舒适的行高 + padding 增加需要额外 80px |
| 左侧面板 220→240px | 20px 增量用于 padding 升级 + 3px 左线 |
| 不启用 `table-layout:fixed` | 见陷阱 #41 —— 会导致跨浏览器列宽偏移，回归默认 auto layout |
| 不使用 `flex column` 固定表头 | 见陷阱 #55 —— flex 布局与 `.modal { overflow-y:auto }` 冲突，改用 sticky thead |
| 报价单弹窗保留 `modal` 而非 `modal-lg` | 560px 足够 2 卡片布局，不需要 680px |
| 空态不添加紫色 SVG icon | 保持简洁，避免与 system 中已有的 SVG 图标体系冲突 |
| JS class 名改动仅字符串替换 | 避免新增函数 / 新变量，降低引入 bug 风险 |
| 优惠关联卡片预留 `display:none` | PRD Phase 4 实施时直接去掉 `style="display:none"` 即可 |

### 7.3 报价单弹窗可扩展区域

`modal-price-item` 的第三个卡片预留了优惠关联区：

```html
<!-- 卡片：优惠关联（PRD Phase 4 启用） -->
<div class="pi-card" id="pi-discount-card" style="display:none;">
    <div class="pi-card-title">🎁 优惠关联</div>
    <div class="pi-card-body">
        <div class="form-row">
            <div class="form-group">
                <label>优惠方案</label>
                <select id="price-item-discount-plan"><option value="">不使用优惠方案</option></select>
            </div>
            <div class="form-group">
                <label>课时优惠券</label>
                <select id="price-item-coupon"><option value="">不使用优惠券</option></select>
            </div>
        </div>
    </div>
</div>
```

实施 PRD Phase 3（前端 HTML）时：
1. 去掉 `style="display:none"`
2. 新增 JS 函数 `loadPriceItemDiscountOptions()` + `loadPriceItemCouponOptions()`（见 PRD §4.3.4-4.3.5）
3. 修改 `saveItem()` 包含 `discount_plan_id` / `coupon_id` 字段（见 PRD §4.3.3）

---

> **确认后实施。** 本方案仅含 UI 层面的 CSS + HTML 重构，不涉及后端 API 变更。JS 改动量 < 10 行仅限 class 字符串替换。PRD 中的数据库和后端变更为独立 phase，不在本设计文档范围内。
