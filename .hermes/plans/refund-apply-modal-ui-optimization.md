# TMS 退费申请弹窗 UI 优化方案

> 版本: 1.0 · 日期: 2026-07-07 · 状态: 设计阶段 · 目标弹窗: `#modal-refund-apply`

---

## 1. 现状分析

### 1.1 代码位置
| 层面 | 文件 | 行号 |
|------|------|------|
| HTML 模板 | `index.php` | 8212–8294 |
| JS 逻辑 | `static/js/main.js` | 7501–7598 |
| CSS（radio/提示条） | `static/css/style.css` | 5165–5236 |

### 1.2 当前结构
```
┌─ modal-header: "退费申请"
├─ modal-body
│  ├─ hidden: refund-apply-order-id
│  ├─ 自动计算信息 (inline style, 2-col grid, gray #f7f9fc bg)
│  │   └─ 校区/课程/报读课时/报读金额/消耗课时/消耗金额/剩余课时/剩余金额
│  ├─ 费用调整 (inline style, white bg)
│  │   ├─ 自定义扣减金额 input
│  │   └─ 实退金额 <span> (red bold)
│  ├─ 退费方式 (inline style, white bg, styled radio via CSS)
│  │   ├─ radio: 退到银行卡 / 退到学员账户
│  │   └─ 绿色提示条 (.refund-account-hint, default hidden)
│  ├─ 收款信息 (id=refund-bank-info-section, inline style, white bg)
│  │   └─ 银行名称·卡号·开户人 (form-row flex)
│  └─ 退费原因 (always visible, inline style, white bg)
│      └─ textarea
└─ modal-footer: 取消 / 提交申请
```

### 1.3 核心问题
| # | 问题 | 影响 |
|---|------|------|
| 1 | **大面积 inline style** — 每个 section 都是 `style="background:#fff;border:...;padding:16px;..."` 重复代码 | 维护噩梦，修改需逐行 |
| 2 | **信息层次扁平** — 5 个 section 视觉权重相同，都是白底灰字标题 | 用户扫描困难，关键金额不突出 |
| 3 | **缺少视觉分组图标** — 各 section 无 icon/symbol 区分 | 语义识别弱 |
| 4 | **银行区切换无动画** — `display:none/block` 瞬间切换，生硬 | 交互体验差 |
| 5 | **紫色主题贯彻不彻底** — 自动计算区用灰色 `#f7f9fc` 而非紫色系 | 与全局紫色主题脱节 |
| 6 | **输入控件风格不统一** — 3 个银行输入用 `form-row` flex，扣减用独立 `form-group` | 视觉碎片化 |
| 7 | **金额计算流不直观** — "剩余 → 扣减 → 实退"三者散落在两个 section | 逻辑断层 |
| 8 | **Footer 按钮缺少加载态** — 提交卡顿时无反馈 | 体验不足 |

---

## 2. 设计目标

1. **信息层次清晰** — 卡片式分组 + 图标 + 三级视觉权重（只读 > 输入 > 按钮）
2. **紫色主题协调** — 全面使用 `--color-*` 变量和紫色语义色
3. **交互流畅** — 银行区收起/展开带 CSS transition 动画
4. **控件统一** — 圆角/间距/字体一致，input 风格对齐
5. **实时反馈** — 金额实时计算、提交按钮 loading 态
6. **不改 JS 逻辑** — 仅优化 DOM 结构和 CSS，JS 保持兼容

---

## 3. 优化方案

### 3.1 整体布局：三段式卡片流

```
┌─ modal-header (保持)
├─ modal-body (新 layout)
│  │
│  ├─ 【卡片 1】📊 订单摘要（只读 · 紫色渐变头部 · 2-col grid）
│  │   ├─ header: icon + "订单摘要" + 紫色底线
│  │   ├─ body: campus/course, total_lessons/amount, consumed_lessons/amount
│  │   └─ highlight: 剩余课时 + 剩余金额（大字 · 紫色强调）
│  │
│  ├─ 【计算流】💰 费用计算（互动区 · 计算连接线视觉）
│  │   ├─ header: icon + "费用计算"
│  │   ├─ 剩余可退金额（大号 display，灰色只读）
│  │   ├─   − 自定义扣减（input · step=0.01 · 右对齐）
│  │   ├─  ───────────────── (虚线分隔)
│  │   └─   = 实退金额（大号 display，紫色渐变 · 实时联动 calcActualRefund）
│  │
│  ├─ 【卡片 3】💳 退费方式
│  │   ├─ header: icon + "退费方式"
│  │   ├─ radio group: 退到银行卡 / 退到学员账户 (保持现有样式)
│  │   └─ 绿色提示条 (保持)
│  │
│  ├─ 【卡片 4】🏦 收款信息（条件显示 · slideDown/Up 动画）
│  │   ├─ header: icon + "收款信息"
│  │   ├─ 三输入框 (统一 form-row)
│  │   └─ ✅ CSS transition: max-height + opacity
│  │
│  └─ 【卡片 5】📝 退费原因（始终可见）
│      ├─ header: icon + "退费原因" + 必填标记
│      └─ textarea
│
└─ modal-footer
    ├─ 取消 (btn-outline)
    └─ 提交申请 (btn-primary · loading spinner)
```

### 3.2 卡片 CSS 类体系

#### 3.2.1 基础卡片容器 `.refund-card`
```css
#modal-refund-apply .refund-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border-light);
    border-radius: var(--radius-lg);      /* 12px */
    padding: 20px;
    margin-bottom: 16px;
    box-shadow: var(--shadow-sm);
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
}
#modal-refund-apply .refund-card:hover {
    border-color: var(--color-primary-light);
    box-shadow: 0 2px 8px rgba(124,58,237,0.08);
}
```

#### 3.2.2 卡片头部 `.refund-card-header`
```css
#modal-refund-apply .refund-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--color-border-light);
    font-size: 14px;
    font-weight: 600;
    color: var(--color-text);
}
#modal-refund-apply .refund-card-header .card-icon {
    width: 28px;
    height: 28px;
    border-radius: var(--radius-sm);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}
/* 图标颜色语义 */
#modal-refund-apply .card-icon--info   { background: #EDE9FE; color: var(--color-primary); }   /* 摘要 */
#modal-refund-apply .card-icon--calc   { background: #FEF3C7; color: #D97706; }               /* 计算 */
#modal-refund-apply .card-icon--method { background: #E0E7FF; color: #4338CA; }               /* 方式 */
#modal-refund-apply .card-icon--bank   { background: #DCFCE7; color: #15803D; }               /* 收款 */
#modal-refund-apply .card-icon--reason { background: #FEE2E2; color: #DC2626; }               /* 原因 */
```

#### 3.2.3 摘要区高亮卡片 `.refund-card--highlight`
```css
#modal-refund-apply .refund-card--highlight {
    background: linear-gradient(135deg, #FAF8FF 0%, #F5F0FF 100%);
    border-color: var(--color-primary-light);
}
#modal-refund-apply .refund-card--highlight .refund-card-header {
    border-bottom-color: rgba(124,58,237,0.15);
}
```

#### 3.2.4 双列 Grid 信息行
```css
#modal-refund-apply .info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 24px;
    font-size: 13px;
}
#modal-refund-apply .info-grid .info-label {
    color: var(--color-text-muted);
    font-size: 12px;
    margin-bottom: 2px;
}
#modal-refund-apply .info-grid .info-value {
    color: var(--color-text);
    font-weight: 500;
}
#modal-refund-apply .info-grid .info-value--large {
    font-size: 18px;
    font-weight: 700;
    color: var(--color-primary-dark);
}
```

### 3.3 费用计算流视觉设计

#### HTML 结构（新）
```html
<div class="refund-card refund-card--calc">
    <div class="refund-card-header">
        <span class="card-icon card-icon--calc">💰</span>
        <span>费用计算</span>
    </div>
    <!-- 剩余金额（大号只读） -->
    <div class="calc-row calc-row--base">
        <span class="calc-label">剩余可退金额</span>
        <span class="calc-value" id="refund-actual-amount-display">¥0.00</span>
    </div>
    <!-- 减号分隔 -->
    <div class="calc-separator">
        <span class="calc-operator">−</span>
        <span class="calc-desc">自定义扣减</span>
        <div class="calc-line"></div>
    </div>
    <!-- 扣减输入 -->
    <div class="calc-row calc-row--deduct">
        <input type="number" id="refund-custom-deduction"
               class="calc-input"
               step="0.01" min="0" value="0"
               oninput="calcActualRefund()">
    </div>
    <!-- 等号线 -->
    <div class="calc-divider">
        <div class="calc-divider-line"></div>
    </div>
    <!-- 实退金额 -->
    <div class="calc-row calc-row--result">
        <span class="calc-label">实退金额</span>
        <span class="calc-value calc-value--result" id="refund-actual-amount-display">¥0.00</span>
    </div>
</div>
```

#### CSS 关键规则
```css
/* 计算卡片 */
#modal-refund-apply .refund-card--calc {
    background: linear-gradient(135deg, #FFFBEB 0%, #FFFFFF 100%);
    border-color: #FDE68A;
}

/* 行 */
#modal-refund-apply .calc-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 0;
}
#modal-refund-apply .calc-row--base {
    background: var(--color-primary-bg);
    border-radius: var(--radius-md);
    padding: 12px 16px;
    margin-bottom: 8px;
}
#modal-refund-apply .calc-row--result {
    background: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%);
    border-radius: var(--radius-md);
    padding: 14px 16px;
}
#modal-refund-apply .calc-label {
    font-size: 13px; color: var(--color-text-muted);
}
#modal-refund-apply .calc-value {
    font-size: 22px; font-weight: 700; color: var(--color-primary-dark);
    font-variant-numeric: tabular-nums;
    font-family: 'SF Mono','Consolas',monospace;
}
#modal-refund-apply .calc-value--result {
    color: #FFFFFF; font-size: 26px;
}

/* 分隔符 */
#modal-refund-apply .calc-separator {
    display: flex; align-items: center; gap: 8px;
    padding: 4px 0 4px 16px;
}
#modal-refund-apply .calc-operator {
    font-size: 18px; color: var(--color-text-muted); font-weight: 300;
}
#modal-refund-apply .calc-line {
    flex: 1; height: 1px; background: var(--color-border-light);
    margin-left: 8px;
}
#modal-refund-apply .calc-divider {
    padding: 8px 0;
}
#modal-refund-apply .calc-divider-line {
    height: 1px;
    background: repeating-linear-gradient(90deg, var(--color-border-light) 0, var(--color-border-light) 6px, transparent 6px, transparent 12px);
}

/* 输入框 */
#modal-refund-apply .calc-input {
    width: 100%; padding: 10px 14px;
    border: 1.5px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: 18px; font-weight: 600;
    text-align: right;
    color: var(--color-text);
    font-variant-numeric: tabular-nums;
    font-family: 'SF Mono','Consolas',monospace;
    background: #FFFBEB;
    transition: border-color 0.2s, box-shadow 0.2s;
}
#modal-refund-apply .calc-input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(124,58,237,0.12);
    background: #FFFFFF;
}
```

### 3.4 收款信息区收起/展开动画

#### 当前问题
```javascript
// 当前: display:none/block 瞬间切换，无动画
bankSection.style.display = 'none';
bankSection.style.display = '';
```

#### 优化方案：CSS transition + max-height
```css
/* 可折叠容器 */
#modal-refund-apply #refund-bank-info-section {
    overflow: hidden;
    max-height: 300px;           /* 展开高度 */
    opacity: 1;
    transition: max-height 0.35s ease, opacity 0.25s ease, margin-bottom 0.35s ease, padding 0.35s ease;
}
/* 收起状态 */
#modal-refund-apply #refund-bank-info-section.collapsed {
    max-height: 0;
    opacity: 0;
    margin-bottom: 0;
    padding-top: 0;
    padding-bottom: 0;
    border-width: 0;
}
```

#### JS 适配（向后兼容，无需改逻辑核心）
```javascript
function onRefundMethodChange() {
    const method = document.querySelector('input[name="refund-method"]:checked')?.value || 'cash';
    const bankSection = document.getElementById('refund-bank-info-section');
    const hint = document.querySelector('#modal-refund-apply .refund-account-hint');

    if (method === 'account') {
        bankSection.classList.add('collapsed');
        hint.style.display = 'flex';
    } else {
        bankSection.classList.remove('collapsed');
        hint.style.display = 'none';
    }
}
```

**关键注意**: 删除 `refund-bank-info-section` 上原有的 `transition: opacity 0.2s ease`（style.css 5222-5224 行），避免与新 multi-property transition 冲突。

### 3.5 输入控件统一

#### 统一 `.form-input` 增强
```css
/* 在 style.css 中追加，作用于弹窗内所有输入 */
#modal-refund-apply .form-input,
#modal-refund-apply input[type="text"],
#modal-refund-apply input[type="number"],
#modal-refund-apply select,
#modal-refund-apply textarea {
    border-radius: var(--radius-md);      /* 8px — 统一圆角 */
    border: 1.5px solid var(--color-border);
    padding: 9px 14px;
    font-size: 14px;
    color: var(--color-text);
    background: #FAFAFE;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
}
#modal-refund-apply .form-input:focus,
#modal-refund-apply input[type="text"]:focus,
#modal-refund-apply input[type="number"]:focus,
#modal-refund-apply select:focus,
#modal-refund-apply textarea:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(124,58,237,0.10);
    background: #FFFFFF;
}
```

#### 银行三输入框布局优化
```css
/* form-row 增强：gap 统一，响应式 */
#modal-refund-apply .form-row {
    display: flex; gap: 16px;
}
#modal-refund-apply .form-row .form-group {
    flex: 1; min-width: 0;   /* 防止溢出 */
}
```

### 3.6 提交按钮 Loading 态

#### HTML 微调（加 spinner 占位）
```html
<button class="btn btn-primary" id="btn-refund-submit" onclick="submitRefundApply()">
    <span class="btn-text">提交申请</span>
    <span class="btn-spinner" style="display:none;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
            <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
        </svg>
    </span>
</button>
```

#### CSS
```css
#modal-refund-apply .btn-spinner svg {
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
#modal-refund-apply .btn-primary.loading {
    pointer-events: none;
    opacity: 0.75;
}
#modal-refund-apply .btn-primary.loading .btn-text        { display: none; }
#modal-refund-apply .btn-primary.loading .btn-spinner      { display: inline-flex; }
```

#### JS 适配
```javascript
async function submitRefundApply() {
    // ... 校验 ...

    const btn = document.getElementById('btn-refund-submit');
    btn.classList.add('loading');

    try {
        const res = await fetch(API_BASE + 'submit_refund', { /* ... */ });
        // ...
        closeModal('modal-refund-apply');
        loadStudentCourses(currentViewStudentId);
    } catch (e) {
        showToast('网络错误，请重试', 'error');
    } finally {
        btn.classList.remove('loading');
    }
}
```

### 3.7 必填字段视觉标记

```css
#modal-refund-apply label.required::after {
    content: ' *';
    color: var(--color-danger);
    font-weight: 600;
}
```

对应 HTML:
```html
<label class="required">银行卡号</label>
```

---

## 4. HTML 重构对照表

### 4.1 自动计算信息 → 订单摘要卡片
| 当前 | 优化后 |
|------|--------|
| `<div style="background:#f7f9fc;border:...;padding:16px;">` | `<div class="refund-card refund-card--highlight">` |
| `<h5 style="margin:0 0 12px;font-size:14px;color:#666;">自动计算信息</h5>` | `<div class="refund-card-header"><span class="card-icon card-icon--info">📊</span>订单摘要</div>` |
| `<div style="display:grid;grid-template-columns:1fr 1fr;...">` | `<div class="info-grid">` |
| `<span style="color:#888;">报读校区：</span>` | `<div class="info-label">报读校区</div>` |
| `<span id="refund-auto-campus">-</span>` | `<div class="info-value" id="refund-auto-campus">-</div>` |

**特殊处理**: 剩余课时/剩余金额 移到 info-grid 最后一行用 `.info-value--large` 高亮。

### 4.2 费用调整 → 费用计算卡片
完全重新设计为计算流视觉（见 3.3 节）。

### 4.3 退费方式 → 退费方式卡片
| 当前 | 优化后 |
|------|--------|
| `<div style="background:#fff;border:...;padding:16px;">` | `<div class="refund-card">` |
| `<h5>退费方式</h5>` | `<div class="refund-card-header"><span class="card-icon card-icon--method">💳</span>退费方式</div>` |

Radio 组和提示条保持现有结构（CSS 已验证），仅包裹层级调整。

### 4.4 收款信息 → 收款信息卡片
| 当前 | 优化后 |
|------|--------|
| `<div id="refund-bank-info-section" style="...">` | `<div class="refund-card" id="refund-bank-info-section">` |
| `<h5>收款信息</h5>` | `<div class="refund-card-header"><span class="card-icon card-icon--bank">🏦</span>收款信息</div>` |

增加 `collapsed` class 切换，删除内联背景色。

### 4.5 退费原因 → 退费原因卡片
| 当前 | 优化后 |
|------|--------|
| `<div style="background:#fff;border:...;padding:16px;margin-top:16px;">` | `<div class="refund-card">` |
| `<h5>退费原因</h5>` | `<div class="refund-card-header"><span class="card-icon card-icon--reason">📝</span>退费原因<span style="color:#DC2626;margin-left:2px;">*</span></div>` |

---

## 5. 实施计划

### Phase 1: CSS（纯样式，不改 HTML/JS）
1. **style.css 追加** — 在 `/* 退费记录页面 Badge 系统 */` (第5252行) 之前插入全部新 CSS
2. **删除旧规则** — 删除 style.css 5221-5224 行（旧的 `#refund-bank-info-section` transition）
3. **验证** — brace balance + no broken selectors

### Phase 2: HTML 重构（index.php 8218-8287 行）
1. 将所有 inline style section 替换为 `.refund-card` 结构
2. 自动计算区改为 `.info-grid` 网格
3. 费用调整改为计算流视觉
4. 提交按钮加 spinner
5. **保持所有 ID 不变** — `refund-apply-order-id`, `refund-auto-*`, `refund-custom-deduction`, `refund-actual-amount-display`, `refund-bank-name`, `refund-bank-account`, `refund-account-holder`, `refund-apply-reason` 全部不动
6. **保持 radio name 不变** — `name="refund-method"`, values `cash`/`account`

### Phase 3: JS 微调（main.js）
| 函数 | 改动 | 风险 |
|------|------|------|
| `onRefundMethodChange` | `style.display` → `classList.add/remove('collapsed')` | 极小 — ID 不变 |
| `submitRefundApply` | 加 loading class 切换 | 极小 — 包装 try/finally |
| `showRefundApplyModal` | 无改动 | 零 |
| `calcActualRefund` | 无改动 | 零 |

### Phase 4: 验证
```bash
# CSS 花括号平衡
python -c "c=open('static/css/style.css').read();print('OK'if c.count('{')==c.count('}')else 'UNBALANCED')"

# JS 无重复函数
grep -oP 'function \w+' static/js/main.js | sort | uniq -d

# PHP 语法
php -l index.php

# JS 语法
node --check static/js/main.js
```

---

## 6. 兼容性矩阵

| 改动项 | 后端 API | 周边弹窗 | 退费记录页 | 退费审批弹窗 |
|--------|----------|----------|-----------|-------------|
| CSS 新增 | ✅ 无影响 | ✅ `#modal-refund-apply` 作用域 | ✅ 无影响 | ✅ 无影响 |
| HTML 重构 | ✅ ID 不变 | ✅ `openModal/closeModal` ID 不变 | ✅ 无影响 | ✅ 无影响 |
| JS loading | ✅ 请求体不变 | ✅ 不修改 showToast/closeModal | ✅ 无影响 | ✅ 无影响 |
| 银行区动画 | ✅ 不影响校验 | ✅ 不影响 onRefundMethodChange 调用 | ✅ 无影响 | ✅ 无影响 |

---

## 7. 效果对比

| 维度 | 当前 | 优化后 |
|------|------|--------|
| 样式定义 | 8 处 inline style 重复 | 统一 CSS 类体系 |
| 信息层次 | 5 个等权方块 | 3 级权重：高亮摘要 > 计算 > 输入 |
| 紫色主题 | 灰色主导 | 紫色渐变 + 紫色边框 + 紫色强调 |
| 金额视觉 | 分散两处 | 计算流连接线，一眼看清剩余→扣减→实退 |
| 银行区域 | display 闪烁 | max-height 平滑收起 |
| 输入控件 | 各自为战 | 统一 focus 光晕 + 一致圆角/间距 |
| 提交按钮 | 无反馈 | spinner loading 态 |
| JS 改动量 | — | ~10 行（最小改动） |
| 破坏性 | — | 零（所有 ID/name/API 调用不变） |

---

## 附录 A: CSS 变量引用表

| 变量 | 值 | 用途 |
|------|-----|------|
| `--color-primary` | `#7C3AED` | 主紫色 |
| `--color-primary-dark` | `#6D28D9` | 深紫（金额） |
| `--color-primary-light` | `#A78BFA` | 浅紫（hover 边框） |
| `--color-primary-bg` | `#F5F0FF` | 紫色背景 |
| `--color-border` | `#E2E0E7` | 默认边框 |
| `--color-border-light` | `#F0EFF4` | 浅边框 |
| `--color-text` | `#1E1B2E` | 正文 |
| `--color-text-muted` | `#9895A8` | 辅助文字 |
| `--radius-sm` | `6px` | 图标圆角 |
| `--radius-md` | `8px` | 输入/按钮圆角 |
| `--radius-lg` | `12px` | 卡片圆角 |
| `--shadow-sm` | `0 1px 3px …` | 卡片阴影 |
| `--transition` | `0.2s ease` | 过渡 |

## 附录 B: 关键设计决策

1. **不引入新 JS 依赖** — loading spinner 用纯 CSS 旋转 SVG，无需第三方库
2. **不修改 calcActualRefund 逻辑** — 只改视觉容器，计算函数保持原样
3. **radio 组样式不动** — 已在 style.css 5165-5219 行验证为正确，无需调整
4. **账户提示条样式不动** — 已有 `.refund-account-hint` 绿色样式，仅包裹进 `.refund-card`
5. **Footer 按钮不换 ID** — 保持 `onclick="closeModal('modal-refund-apply')"` 和 `onclick="submitRefundApply()"` 不变
