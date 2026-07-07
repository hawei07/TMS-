# TMS 退费审批弹窗 UI 优化方案

> **目标文件**：`static/css/style.css`（CSS 追加） + `index.php`（HTML 结构调整） + `static/js/main.js`（JS HTML 模板更新）
> **当前代码**：index.php:8364-8380（弹窗外壳）、main.js:7874-7973（showApproveModal 渲染）、style.css:3457-3516（进度条）、style.css:5241-5250（绿色提示条）
> **优化原则**：所有 CSS 用 `#modal-refund-approve` 前缀作用域隔离，遵循项目紫色主题 `--color-primary: #7C3AED`

---

## 一、现状分析

| 区域 | 当前问题 |
|------|---------|
| 信息区 | 纯 2 列网格无卡片感；字号 13px 偏小；`¥` 符号后无格式化；实退金额与其他项混在一起不突出 |
| 进度条 | 16px 圆点偏小；2px 连线单薄；已完成的连线仍是灰色；无动画/过渡 |
| 按钮 | 三按钮等宽同级，驳回与通过视觉权重相同；无图标语义 |
| 整体 | 8px gap 偏紧；无 section 分隔标题；审批人输入区无视觉分组 |

---

## 二、设计方案：四大区域升级

### 区域 1：信息卡片区（原 2 列网格 → 三卡片布局）

**设计目标**：信息有层级、实退金额是视觉重心

#### 1.1 卡片容器
```css
#modal-refund-approve .approve-info-cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}
/* 金额明细卡片横跨两列 */
#modal-refund-approve .approve-info-cards .card-full {
    grid-column: 1 / -1;
}
```

#### 1.2 通用卡片样式
```css
#modal-refund-approve .approve-info-card {
    background: #fff;
    border: 1px solid var(--color-border-light);
    border-radius: var(--radius-md);
    padding: 16px 20px;
}
#modal-refund-approve .approve-info-card .card-title {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--color-text-muted);
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--color-border-light);
}
#modal-refund-approve .approve-info-card .info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
    font-size: 13px;
    line-height: 1.6;
}
#modal-refund-approve .approve-info-card .info-label {
    color: var(--color-text-secondary);
    font-size: 12px;
    flex-shrink: 0;
    margin-right: 12px;
}
#modal-refund-approve .approve-info-card .info-value {
    color: var(--color-text);
    font-weight: 500;
    text-align: right;
    word-break: break-all;
}
#modal-refund-approve .approve-info-card .info-value.mono {
    font-family: 'SF Mono', 'Consolas', 'Menlo', monospace;
    font-size: 12px;
}
```

#### 1.3 金额卡片（Hero Card — 实退金额）
```css
/* 金额明细卡 — 紫色渐变顶部条 */
#modal-refund-approve .card-amount {
    background: linear-gradient(180deg, #FAF8FF 0%, #FFFFFF 100%);
    border: 1px solid #EDE9FE;
    border-top: 3px solid var(--color-primary);
}

/* 4 列金额网格 */
#modal-refund-approve .card-amount .amount-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}
#modal-refund-approve .card-amount .amount-item {
    text-align: center;
    padding: 12px 8px;
    border-radius: var(--radius-sm);
    background: #FAFAFE;
    transition: background 0.2s;
}
#modal-refund-approve .card-amount .amount-item:hover {
    background: var(--color-primary-bg);
}
#modal-refund-approve .card-amount .amount-label {
    font-size: 11px;
    color: var(--color-text-muted);
    margin-bottom: 6px;
}
#modal-refund-approve .card-amount .amount-value {
    font-size: 16px;
    font-weight: 700;
    color: var(--color-text);
    font-variant-numeric: tabular-nums;
    font-family: 'SF Mono', 'Consolas', 'Menlo', monospace;
}

/* 实退金额 — 特殊高亮 */
#modal-refund-approve .card-amount .amount-item.amount-hero {
    background: linear-gradient(135deg, #FEF3C7 0%, #FFF7ED 100%);
    border: 1px solid #FCD34D;
    position: relative;
}
#modal-refund-approve .card-amount .amount-item.amount-hero::before {
    content: '';
    position: absolute;
    top: 0; left: 12px; right: 12px;
    height: 2px;
    background: linear-gradient(90deg, transparent, #F59E0B, transparent);
}
#modal-refund-approve .card-amount .amount-item.amount-hero .amount-value {
    color: #B45309;
    font-size: 22px;
    font-weight: 800;
}
#modal-refund-approve .card-amount .amount-item.amount-hero .amount-label {
    color: #92400E;
    font-weight: 600;
}
```

#### 1.4 信息卡片结构（三类）

**卡片 A — 学员信息** (grid-col: 1)
- 学员、校区、课程

**卡片 B — 订单信息** (grid-col: 2)
- 订单号、退费原因、退费方式（转账银行/银行卡号/开户人）

**卡片 C — 金额明细** (grid-col: 1/-1, 横跨)
- 报读课时 → 报读金额
- 消耗课时 → 消耗金额
- 剩余可退课时 → 剩余可退金额
- 自定义扣减 → **实退金额（Hero）**

---

### 区域 2：审批进度条（视觉升级）

**设计目标**：完成步骤有对勾，当前步骤有脉冲动画，连线渐变变色

```css
/* === 进度条容器 === */
#modal-refund-approve .approval-steps {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px 16px 16px;
    background: #FAFAFE;
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border-light);
}

/* === 步骤节点 === */
#modal-refund-approve .approval-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    position: relative;
    flex: 0 0 auto;
    min-width: 80px;
}

/* === 步骤圆点（加大到 24px） === */
#modal-refund-approve .approval-step-dot {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #E5E7EB;
    border: 2px solid #E5E7EB;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    position: relative;
    z-index: 2;
}

/* === 步骤文字 === */
#modal-refund-approve .approval-step span {
    font-size: 12px;
    color: #9CA3AF;
    white-space: nowrap;
    font-weight: 500;
    transition: color 0.3s;
}

/* === 连线（加粗到 3px） === */
#modal-refund-approve .approval-step-line {
    flex: 1;
    height: 3px;
    background: #E5E7EB;
    min-width: 44px;
    max-width: 80px;
    margin: 0 -4px;
    align-self: flex-start;
    margin-top: 12px;
    border-radius: 2px;
    transition: background 0.4s ease;
    position: relative;
    z-index: 1;
}

/* === 当前步骤 === */
#modal-refund-approve .approval-step-current .approval-step-dot {
    background: #fff;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 6px rgba(124,58,237,0.12);
    animation: approve-pulse 2s infinite;
}
#modal-refund-approve .approval-step-current span {
    color: var(--color-primary);
    font-weight: 700;
}

/* 脉冲动画 */
@keyframes approve-pulse {
    0%, 100% { box-shadow: 0 0 0 6px rgba(124,58,237,0.12); }
    50%      { box-shadow: 0 0 0 12px rgba(124,58,237,0.06); }
}

/* === 已完成步骤 === */
#modal-refund-approve .approval-step-done .approval-step-dot {
    background: var(--color-primary);
    border-color: var(--color-primary);
}
/* 已完成步骤显示白色对勾（CSS 绘制） */
#modal-refund-approve .approval-step-done .approval-step-dot::after {
    content: '';
    display: block;
    width: 6px;
    height: 10px;
    border: solid #fff;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
    margin-top: -2px;
}
#modal-refund-approve .approval-step-done span {
    color: var(--color-primary);
    font-weight: 600;
}
/* 已完成步骤之前的连线也变成紫色 */
#modal-refund-approve .approval-step-line.line-done {
    background: var(--color-primary-light);
}

/* === 驳回步骤 === */
#modal-refund-approve .approval-step-rejected .approval-step-dot {
    background: #fff;
    border-color: var(--color-danger);
    box-shadow: 0 0 0 4px rgba(229,62,62,0.12);
}
/* 驳回步骤显示 X */
#modal-refund-approve .approval-step-rejected .approval-step-dot::after {
    content: '✕';
    color: var(--color-danger);
    font-size: 12px;
    font-weight: 700;
    line-height: 1;
}
#modal-refund-approve .approval-step-rejected span {
    color: var(--color-danger);
    font-weight: 600;
}
```

---

### 区域 3：审批操作区

**设计目标**：审批人输入与按钮视觉分离，驳回原因 inline 展开更自然

```css
/* === 审批操作容器 === */
#modal-refund-approve .approve-action-area {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--color-border-light);
}

/* === 审批人输入行 === */
#modal-refund-approve .approve-action-area .approve-approver-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}
#modal-refund-approve .approve-action-area .approve-approver-row label {
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-secondary);
    white-space: nowrap;
    min-width: 48px;
}
#modal-refund-approve .approve-action-area .approve-approver-row input {
    flex: 1;
    max-width: 240px;
    padding: 8px 14px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
    background: #fff;
}
#modal-refund-approve .approve-action-area .approve-approver-row input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(124,58,237,0.1);
}

/* === 财务确认阶段：退款方式 radio（继承现有模式） === */
#modal-refund-approve .approve-refund-to-group {
    margin-bottom: 14px;
}
#modal-refund-approve .approve-refund-to-group label {
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-secondary);
    display: block;
    margin-bottom: 8px;
}
#modal-refund-approve .approve-refund-to-group .refund-to-options {
    display: flex;
    gap: 24px;
}

/* 自定义 radio（复用 refund-radio-design.md 模式） */
#modal-refund-approve .refund-to-radio-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
    color: #333;
    padding: 6px 0;
    user-select: none;
    transition: color 0.15s;
}
#modal-refund-approve .refund-to-radio-label:hover { color: var(--color-primary); }
#modal-refund-approve .refund-to-radio-custom {
    width: 18px; height: 18px; border-radius: 50%;
    border: 2px solid #C4C4C4; flex-shrink: 0;
    transition: border-color 0.2s, background 0.2s;
}
#modal-refund-approve .refund-to-radio-label:hover .refund-to-radio-custom {
    border-color: var(--color-primary-light);
}
#modal-refund-approve .refund-to-radio-label input:checked + .refund-to-radio-custom {
    border-color: var(--color-primary);
    background: var(--color-primary);
    box-shadow: inset 0 0 0 4px #fff;
}
#modal-refund-approve .refund-to-radio-label input[type="radio"] {
    position: absolute; opacity: 0; width: 0; height: 0;
}

/* === 驳回原因（inline 展开，text-sm） === */
#modal-refund-approve #approve-reject-reason-group {
    margin-bottom: 0;
}
#modal-refund-approve #approve-reject-reason-group label {
    font-size: 13px;
    font-weight: 600;
    color: #B91C1C;
    display: block;
    margin-bottom: 6px;
}
#modal-refund-approve #approve-reject-reason {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #FECACA;
    border-radius: var(--radius-sm);
    font-size: 13px;
    resize: vertical;
    transition: border-color 0.2s;
    background: #FFF5F5;
}
#modal-refund-approve #approve-reject-reason:focus {
    outline: none;
    border-color: var(--color-danger);
    box-shadow: 0 0 0 3px rgba(229,62,62,0.1);
}
```

---

### 区域 4：底部按钮（主次分明）

```css
/* === Footer 间距 === */
#modal-refund-approve .modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 28px 20px;
}

/* === 关闭按钮 — 中性弱化 === */
#modal-refund-approve .modal-footer .btn-default {
    padding: 9px 20px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    font-size: 14px;
    color: var(--color-text-secondary);
    background: #fff;
    cursor: pointer;
    transition: all 0.2s;
}
#modal-refund-approve .modal-footer .btn-default:hover {
    background: #F9F9FB;
    border-color: #C4C4C4;
}

/* === 驳回按钮 — 危险色，左侧 × 图标 === */
#modal-refund-approve #btn-refund-reject {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 22px;
    border: 1px solid #FECACA;
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-weight: 600;
    color: var(--color-danger);
    background: #fff;
    cursor: pointer;
    transition: all 0.2s;
}
#modal-refund-approve #btn-refund-reject:hover {
    background: var(--color-danger-bg);
    border-color: var(--color-danger);
    box-shadow: 0 2px 8px rgba(229,62,62,0.15);
    transform: translateY(-1px);
}
/* 驳回按钮图标 */
#modal-refund-approve #btn-refund-reject::before {
    content: '✕';
    font-size: 13px;
    font-weight: 700;
}

/* === 审批通过 — 紫色渐变，右箭头 → === */
#modal-refund-approve #btn-refund-approve {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 24px;
    border: none;
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-weight: 600;
    color: #fff;
    background: linear-gradient(135deg, #7C3AED, #6D28D9);
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(124,58,237,0.25);
    transition: all 0.2s;
}
#modal-refund-approve #btn-refund-approve:hover {
    background: linear-gradient(135deg, #8B5CF6, #7C3AED);
    box-shadow: 0 4px 14px rgba(124,58,237,0.35);
    transform: translateY(-1px);
}
/* 审批按钮图标 */
#modal-refund-approve #btn-refund-approve::after {
    content: '→';
    font-size: 15px;
}

/* === Footer 内按钮无额外 icon 时居中 === */
#modal-refund-approve .modal-footer .btn-default:only-child {
    margin: 0 auto;
}
```

---

### 区域 5：审批进度信息 + 已审批人

```css
/* === 进度标题（黑底白字带左侧紫色竖条） === */
#modal-refund-approve .approve-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 700;
    color: var(--color-text);
    margin-bottom: 4px;
}
#modal-refund-approve .approve-section-title::before {
    content: '';
    display: inline-block;
    width: 3px;
    height: 16px;
    background: var(--color-primary);
    border-radius: 2px;
}

/* === 已审批人标签 === */
#modal-refund-approve .approve-approver-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}
#modal-refund-approve .approve-approver-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 12px;
    background: var(--color-primary-bg);
    border-radius: 20px;
    font-size: 12px;
    color: var(--color-primary);
    font-weight: 500;
}
#modal-refund-approve .approve-approver-tag::before {
    content: '✓';
    font-size: 10px;
    font-weight: 700;
}
```

---

### 区域 6：绿色提示条优化

```css
/* 已有基础样式（style.css:5241-5250），追加一个图标 SVG 增强版 */
#modal-refund-approve .approve-account-hint {
    margin-top: 12px;
    padding: 10px 16px;
    background: #f0fdf4;
    border-left: 3px solid #16a34a;
    color: #15803d;
    font-size: 13px;
    border-radius: 6px;
    line-height: 1.6;
    display: flex;
    align-items: flex-start;
    gap: 8px;
}
#modal-refund-approve .approve-account-hint .hint-icon {
    flex-shrink: 0;
    margin-top: 1px;
}
```

---

### 区域 7：驳回提示条（已有 inline style → CSS 类化）

```css
/* 替换 showApproveModal 中的 inline style (background:#fff5f5;border-left:3px solid #e53e3e;color:#c53030;) */
#modal-refund-approve .approve-reject-banner {
    margin-top: 12px;
    padding: 10px 16px;
    background: #fff5f5;
    border-left: 3px solid var(--color-danger);
    color: #c53030;
    font-size: 13px;
    border-radius: 6px;
    line-height: 1.6;
    display: flex;
    align-items: flex-start;
    gap: 8px;
}
#modal-refund-approve .approve-reject-banner .banner-icon {
    flex-shrink: 0;
    font-size: 14px;
    margin-top: 1px;
}
```

---

## 三、HTML 模板改造（main.js showApproveModal 中）

当前 JS 使用模板字面量内联 HTML（line 7918-7960），需改造为：

### 3.1 信息区 → 三卡片布局

**旧代码（7906-7936 行）**：2 列 grid，flat 结构
**新代码**：

```javascript
// 学员+订单信息卡片
let infoCardsHtml = `
<div class="approve-info-cards">
    <!-- 卡片 A：学员信息 -->
    <div class="approve-info-card">
        <div class="card-title">📋 学员信息</div>
        <div class="info-row"><span class="info-label">学员</span><span class="info-value">${esc(rr.student_name || '')}</span></div>
        <div class="info-row"><span class="info-label">校区</span><span class="info-value">${esc(rr.campus || '-')}</span></div>
        <div class="info-row"><span class="info-label">课程</span><span class="info-value">${esc(rr.course_name || '')}</span></div>
    </div>

    <!-- 卡片 B：订单 & 退款去向 -->
    <div class="approve-info-card">
        <div class="card-title">📦 订单信息</div>
        <div class="info-row"><span class="info-label">订单号</span><span class="info-value mono">${esc(rr.order_no || '')}</span></div>
        <div class="info-row"><span class="info-label">退费类型</span><span class="info-value">${isAccount ? '账户余额退费' : '课程退费'}</span></div>
        <div class="info-row"><span class="info-label">退费原因</span><span class="info-value">${esc(rr.refund_reason || '-')}</span></div>`;

if (!isAccount && rrMethod !== '账户') {
    infoCardsHtml += `
        <div class="info-row"><span class="info-label">转账银行</span><span class="info-value">${esc(rr.bank_name || '-')}</span></div>
        <div class="info-row"><span class="info-label">银行卡号</span><span class="info-value mono">${esc(rr.bank_account || '-')}</span></div>
        <div class="info-row"><span class="info-label">开户人</span><span class="info-value">${esc(rr.account_holder || '-')}</span></div>`;
}
infoCardsHtml += `</div>

    <!-- 卡片 C：金额明细（横跨两列） -->
    <div class="approve-info-card card-amount card-full">
        <div class="card-title">💰 金额明细</div>
        <div class="amount-grid">
            <div class="amount-item">
                <div class="amount-label">报读课时</div>
                <div class="amount-value">${parseInt(rr.total_lessons) || 0}</div>
            </div>
            <div class="amount-item">
                <div class="amount-label">报读金额</div>
                <div class="amount-value">¥${Number(rr.total_amount || 0).toFixed(2)}</div>
            </div>
            <div class="amount-item">
                <div class="amount-label">消耗课时</div>
                <div class="amount-value">${parseInt(rr.consumed_lessons) || 0}</div>
            </div>
            <div class="amount-item">
                <div class="amount-label">消耗金额</div>
                <div class="amount-value">¥${Number(rr.consumed_amount || 0).toFixed(2)}</div>
            </div>
            <div class="amount-item">
                <div class="amount-label">剩余可退课时</div>
                <div class="amount-value">${parseInt(rr.remaining_lessons) || 0}</div>
            </div>
            <div class="amount-item">
                <div class="amount-label">剩余可退金额</div>
                <div class="amount-value">¥${Number(rr.remaining_amount || 0).toFixed(2)}</div>
            </div>
            <div class="amount-item">
                <div class="amount-label">自定义扣减</div>
                <div class="amount-value">¥${Number(rr.custom_deduction || 0).toFixed(2)}</div>
            </div>
            <div class="amount-item amount-hero">
                <div class="amount-label">实退金额</div>
                <div class="amount-value">¥${Number(rr.actual_refund || 0).toFixed(2)}</div>
            </div>
        </div>
    </div>
</div>`;
```

### 3.2 进度条 → 已完成连线变色

在 `showApproveModal` 构建 stepsHtml 时，已完成步骤之间的连线添加 `line-done` class：

```javascript
let stepsHtml = '<div class="approval-steps">';
steps.forEach((s, i) => {
    let cls = 'approval-step';
    if (status === '审批驳回') cls += ' approval-step-rejected';
    else if (i < currentStepIdx || status === '已退费') cls += ' approval-step-done';
    else if (i === currentStepIdx) cls += ' approval-step-current';
    stepsHtml += `<div class="${cls}"><div class="approval-step-dot"></div><span>${s}</span></div>`;
    if (i < 2) {
        // 已完成步骤之间的连线变色
        let lineCls = 'approval-step-line';
        if (status !== '审批驳回' && i < currentStepIdx) lineCls += ' line-done';
        stepsHtml += `<div class="${lineCls}"></div>`;
    }
});
stepsHtml += '</div>';
```

### 3.3 审批人区改为结构化

```javascript
// 原有 canApprove 分支的HTML改为：
${canApprove ? `<div class="approve-action-area">
    <div class="approve-approver-row">
        <label>审批人</label>
        <input type="text" id="approve-approver-name" class="form-input" placeholder="请输入审批人姓名">
    </div>
    ${rr.approval_stage === '财务确认' ? `<div class="approve-refund-to-group">
        <label>退款方式</label>
        <div class="refund-to-options">
            <label class="refund-to-radio-label">
                <input type="radio" name="approve-refund-to" value="cash" checked>
                <span class="refund-to-radio-custom"></span>退到银行卡
            </label>
            ${(isAccount || (rrProject==='课程' && rrMethod==='账户')) ? '' : `<label class="refund-to-radio-label">
                <input type="radio" name="approve-refund-to" value="balance">
                <span class="refund-to-radio-custom"></span>退到余额
            </label>`}
        </div>
    </div>` : ''}
    <div id="approve-reject-reason-group" style="display:none;">
        <label>驳回原因 <span style="color:red;">*</span></label>
        <textarea id="approve-reject-reason" rows="2" placeholder="请输入驳回原因"></textarea>
    </div>
</div>` : ''}
```

### 3.4 驳回提示条 → CSS 类化

```javascript
// 替换 inline style：
if (status === '审批驳回') {
    stepsHtml += `<div class="approve-reject-banner">
        <span class="banner-icon">⚠️</span>驳回原因：${esc(rr.reject_reason || '无')}
    </div>`;
}
```

### 3.5 已审批人 → 标签化

```javascript
// 替换三行 inline style div：
let approverTagsHtml = '';
if (rr.approver1) approverTagsHtml += `<span class="approve-approver-tag">${esc(rr.approver1)}</span>`;
if (rr.approver2) approverTagsHtml += `<span class="approve-approver-tag">${esc(rr.approver2)}</span>`;
if (rr.approver3) approverTagsHtml += `<span class="approve-approver-tag">${esc(rr.approver3)}</span>`;
if (approverTagsHtml) {
    approverTagsHtml = `<div class="approve-approver-tags">${approverTagsHtml}</div>`;
}
```

---

## 四、CSS 汇总（追加到 style.css 末尾）

```css
/* ==================== 退费审批弹窗 UI 优化 ==================== */

/* --- 区域 1：信息卡片 --- */
#modal-refund-approve .approve-info-cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}
#modal-refund-approve .approve-info-cards .card-full {
    grid-column: 1 / -1;
}

#modal-refund-approve .approve-info-card {
    background: #fff;
    border: 1px solid var(--color-border-light);
    border-radius: var(--radius-md);
    padding: 16px 20px;
}
#modal-refund-approve .approve-info-card .card-title {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--color-text-muted);
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--color-border-light);
}
#modal-refund-approve .approve-info-card .info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
    font-size: 13px;
    line-height: 1.6;
}
#modal-refund-approve .approve-info-card .info-label {
    color: var(--color-text-secondary);
    font-size: 12px;
    flex-shrink: 0;
    margin-right: 12px;
}
#modal-refund-approve .approve-info-card .info-value {
    color: var(--color-text);
    font-weight: 500;
    text-align: right;
    word-break: break-all;
}
#modal-refund-approve .approve-info-card .info-value.mono {
    font-family: 'SF Mono', 'Consolas', 'Menlo', monospace;
    font-size: 12px;
}

/* 金额卡片 */
#modal-refund-approve .card-amount {
    background: linear-gradient(180deg, #FAF8FF 0%, #FFFFFF 100%);
    border: 1px solid #EDE9FE;
    border-top: 3px solid var(--color-primary);
}
#modal-refund-approve .card-amount .amount-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}
#modal-refund-approve .card-amount .amount-item {
    text-align: center;
    padding: 12px 8px;
    border-radius: var(--radius-sm);
    background: #FAFAFE;
    transition: background 0.2s;
}
#modal-refund-approve .card-amount .amount-item:hover {
    background: var(--color-primary-bg);
}
#modal-refund-approve .card-amount .amount-label {
    font-size: 11px;
    color: var(--color-text-muted);
    margin-bottom: 6px;
}
#modal-refund-approve .card-amount .amount-value {
    font-size: 16px;
    font-weight: 700;
    color: var(--color-text);
    font-variant-numeric: tabular-nums;
    font-family: 'SF Mono', 'Consolas', 'Menlo', monospace;
}

/* Hero：实退金额 */
#modal-refund-approve .card-amount .amount-item.amount-hero {
    background: linear-gradient(135deg, #FEF3C7 0%, #FFF7ED 100%);
    border: 1px solid #FCD34D;
    position: relative;
}
#modal-refund-approve .card-amount .amount-item.amount-hero::before {
    content: '';
    position: absolute;
    top: 0; left: 12px; right: 12px;
    height: 2px;
    background: linear-gradient(90deg, transparent, #F59E0B, transparent);
}
#modal-refund-approve .card-amount .amount-item.amount-hero .amount-value {
    color: #B45309;
    font-size: 22px;
    font-weight: 800;
}
#modal-refund-approve .card-amount .amount-item.amount-hero .amount-label {
    color: #92400E;
    font-weight: 600;
}

/* --- 区域 2：进度条 --- */
#modal-refund-approve .approval-steps {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px 16px 16px;
    background: #FAFAFE;
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border-light);
}
#modal-refund-approve .approval-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    position: relative;
    flex: 0 0 auto;
    min-width: 80px;
}
#modal-refund-approve .approval-step-dot {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #E5E7EB;
    border: 2px solid #E5E7EB;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    position: relative;
    z-index: 2;
}
#modal-refund-approve .approval-step span {
    font-size: 12px;
    color: #9CA3AF;
    white-space: nowrap;
    font-weight: 500;
    transition: color 0.3s;
}
#modal-refund-approve .approval-step-line {
    flex: 1;
    height: 3px;
    background: #E5E7EB;
    min-width: 44px;
    max-width: 80px;
    margin: 0 -4px;
    align-self: flex-start;
    margin-top: 12px;
    border-radius: 2px;
    transition: background 0.4s ease;
    position: relative;
    z-index: 1;
}

/* 当前步骤 */
#modal-refund-approve .approval-step-current .approval-step-dot {
    background: #fff;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 6px rgba(124,58,237,0.12);
    animation: approve-pulse 2s infinite;
}
#modal-refund-approve .approval-step-current span {
    color: var(--color-primary);
    font-weight: 700;
}
@keyframes approve-pulse {
    0%, 100% { box-shadow: 0 0 0 6px rgba(124,58,237,0.12); }
    50%      { box-shadow: 0 0 0 12px rgba(124,58,237,0.06); }
}

/* 已完成步骤 */
#modal-refund-approve .approval-step-done .approval-step-dot {
    background: var(--color-primary);
    border-color: var(--color-primary);
}
#modal-refund-approve .approval-step-done .approval-step-dot::after {
    content: '';
    display: block;
    width: 6px;
    height: 10px;
    border: solid #fff;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
    margin-top: -2px;
}
#modal-refund-approve .approval-step-done span {
    color: var(--color-primary);
    font-weight: 600;
}
#modal-refund-approve .approval-step-line.line-done {
    background: var(--color-primary-light);
}

/* 驳回步骤 */
#modal-refund-approve .approval-step-rejected .approval-step-dot {
    background: #fff;
    border-color: var(--color-danger);
    box-shadow: 0 0 0 4px rgba(229,62,62,0.12);
}
#modal-refund-approve .approval-step-rejected .approval-step-dot::after {
    content: '✕';
    color: var(--color-danger);
    font-size: 12px;
    font-weight: 700;
    line-height: 1;
}
#modal-refund-approve .approval-step-rejected span {
    color: var(--color-danger);
    font-weight: 600;
}

/* --- 区域 3：审批操作区 --- */
#modal-refund-approve .approve-action-area {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--color-border-light);
}
#modal-refund-approve .approve-action-area .approve-approver-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}
#modal-refund-approve .approve-action-area .approve-approver-row label {
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-secondary);
    white-space: nowrap;
    min-width: 48px;
}
#modal-refund-approve .approve-action-area .approve-approver-row input {
    flex: 1;
    max-width: 240px;
    padding: 8px 14px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
    background: #fff;
}
#modal-refund-approve .approve-action-area .approve-approver-row input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(124,58,237,0.1);
}

/* 退款方式 radio */
#modal-refund-approve .approve-refund-to-group {
    margin-bottom: 14px;
}
#modal-refund-approve .approve-refund-to-group > label {
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-secondary);
    display: block;
    margin-bottom: 8px;
}
#modal-refund-approve .refund-to-options {
    display: flex;
    gap: 24px;
}
#modal-refund-approve .refund-to-radio-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
    color: #333;
    padding: 6px 0;
    user-select: none;
    transition: color 0.15s;
}
#modal-refund-approve .refund-to-radio-label:hover { color: var(--color-primary); }
#modal-refund-approve .refund-to-radio-custom {
    width: 18px; height: 18px; border-radius: 50%;
    border: 2px solid #C4C4C4; flex-shrink: 0;
    transition: border-color 0.2s, background 0.2s;
}
#modal-refund-approve .refund-to-radio-label:hover .refund-to-radio-custom {
    border-color: var(--color-primary-light);
}
#modal-refund-approve .refund-to-radio-label input:checked + .refund-to-radio-custom {
    border-color: var(--color-primary);
    background: var(--color-primary);
    box-shadow: inset 0 0 0 4px #fff;
}
#modal-refund-approve .refund-to-radio-label input[type="radio"] {
    position: absolute; opacity: 0; width: 0; height: 0;
}

/* 驳回原因 textarea */
#modal-refund-approve #approve-reject-reason-group {
    margin-bottom: 0;
}
#modal-refund-approve #approve-reject-reason-group label {
    font-size: 13px;
    font-weight: 600;
    color: #B91C1C;
    display: block;
    margin-bottom: 6px;
}
#modal-refund-approve #approve-reject-reason {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #FECACA;
    border-radius: var(--radius-sm);
    font-size: 13px;
    resize: vertical;
    transition: border-color 0.2s;
    background: #FFF5F5;
}
#modal-refund-approve #approve-reject-reason:focus {
    outline: none;
    border-color: var(--color-danger);
    box-shadow: 0 0 0 3px rgba(229,62,62,0.1);
}

/* --- 区域 4：底部按钮 --- */
#modal-refund-approve .modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 28px 20px;
}
#modal-refund-approve .modal-footer .btn-default {
    padding: 9px 20px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    font-size: 14px;
    color: var(--color-text-secondary);
    background: #fff;
    cursor: pointer;
    transition: all 0.2s;
}
#modal-refund-approve .modal-footer .btn-default:hover {
    background: #F9F9FB;
    border-color: #C4C4C4;
}
#modal-refund-approve #btn-refund-reject {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 22px;
    border: 1px solid #FECACA;
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-weight: 600;
    color: var(--color-danger);
    background: #fff;
    cursor: pointer;
    transition: all 0.2s;
}
#modal-refund-approve #btn-refund-reject:hover {
    background: var(--color-danger-bg);
    border-color: var(--color-danger);
    box-shadow: 0 2px 8px rgba(229,62,62,0.15);
    transform: translateY(-1px);
}
#modal-refund-approve #btn-refund-reject::before {
    content: '✕';
    font-size: 13px;
    font-weight: 700;
}
#modal-refund-approve #btn-refund-approve {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 24px;
    border: none;
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-weight: 600;
    color: #fff;
    background: linear-gradient(135deg, #7C3AED, #6D28D9);
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(124,58,237,0.25);
    transition: all 0.2s;
}
#modal-refund-approve #btn-refund-approve:hover {
    background: linear-gradient(135deg, #8B5CF6, #7C3AED);
    box-shadow: 0 4px 14px rgba(124,58,237,0.35);
    transform: translateY(-1px);
}
#modal-refund-approve #btn-refund-approve::after {
    content: '→';
    font-size: 15px;
}

/* --- 区域 5：辅助元素 --- */
#modal-refund-approve .approve-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 700;
    color: var(--color-text);
    margin-bottom: 4px;
}
#modal-refund-approve .approve-section-title::before {
    content: '';
    display: inline-block;
    width: 3px;
    height: 16px;
    background: var(--color-primary);
    border-radius: 2px;
}
#modal-refund-approve .approve-approver-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}
#modal-refund-approve .approve-approver-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 12px;
    background: var(--color-primary-bg);
    border-radius: 20px;
    font-size: 12px;
    color: var(--color-primary);
    font-weight: 500;
}
#modal-refund-approve .approve-approver-tag::before {
    content: '✓';
    font-size: 10px;
    font-weight: 700;
}

/* 驳回横幅 */
#modal-refund-approve .approve-reject-banner {
    margin-top: 12px;
    padding: 10px 16px;
    background: #fff5f5;
    border-left: 3px solid var(--color-danger);
    color: #c53030;
    font-size: 13px;
    border-radius: 6px;
    line-height: 1.6;
    display: flex;
    align-items: flex-start;
    gap: 8px;
}
```

---

## 五、JS 改动对照表

| 位置 | 改动项 | 描述 |
|------|--------|------|
| `showApproveModal` L7918-7936 | 信息区 HTML | 2 列 grid → 三卡片布局（infoCardsHtml 变量） |
| `showApproveModal` L7898-7907 | 进度条 HTML | `approval-step-line` 增加 `line-done` class |
| `showApproveModal` L7909-7911 | 驳回提示 | inline style → `approve-reject-banner` class |
| `showApproveModal` L7913-7916 | 已审批人 | 三行 div → `approve-approver-tags` pill |
| `showApproveModal` L7943-7959 | 审批操作区 | 重新结构化 HTML（`.approve-action-area`） |
| `showApproveModal` L7943 财务确认 radio | 退款方式 radio | 原生 input → 自定义 radio（refund-to-radio-label） |

---

## 六、实施阶段

| 阶段 | 内容 | 涉及文件 | 验证 |
|------|------|----------|------|
| Phase 1 | CSS 全部追加到 style.css 末尾 | `static/css/style.css` | `python -c "c=open('static/css/style.css').read();print('OK'if c.count('{')==c.count('}')else'UNBALANCED')"` |
| Phase 2 | JS HTML 模板改造 | `static/js/main.js` | `node --check static/js/main.js` |
| Phase 3 | 浏览器验证 | 启动 PHP 服务器 | 打开审批弹窗验证四类状态：待审批/一级通过/二级通过/驳回/已退费 |

---

## 七、注意事项

1. **CSS 作用域**：所有新规则全部带 `#modal-refund-approve` 前缀，不覆盖全局 `.approval-steps` 现有样式（原样式保留给其他弹窗可能的使用场景，审批弹窗用自己的升级版覆盖）
2. **JS 不破坏现有逻辑**：只改 HTML 模板字符串，不改 `submitApproval`、`closeModal` 等函数逻辑
3. **驳回原因 textarea**：`display:none` 初始隐藏，`submitApproval('reject')` 中 `group.style.display = 'block'` 逻辑保持不变
4. **财务确认 radio**：`submitApproval` 中 `document.querySelector('input[name="approve-refund-to"]:checked')` 选择器不变
5. **响应式**：1200px 以下 width 时金额卡片 `amount-grid` 改为 2 列，信息卡片改为单列
