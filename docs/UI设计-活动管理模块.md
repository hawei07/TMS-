# 活动管理模块 UI 设计方案

> 版本：v1.0 | 日期：2026-07-09 | 作者：UI Design Agent
> 关联文档：`docs/PRD-活动管理模块.md`

---

## 一、设计概览

### 1.1 设计目标

在保留现有 HTML 结构和 JS 逻辑的前提下，通过纯 CSS 增强 + 最小化 HTML/JS 改动，将活动管理模块的视觉风格从通用紫色系转向**少儿美术风格**。

### 1.2 配色体系

| 色名 | 色值 | 语义角色 |
|------|------|----------|
| 鹅黄 | `#FFE066` / 浅底 `#FFF5CC` | 仅收费 = 温暖/价格感 |
| 珊瑚 | `#FF7675` / 浅底 `#FFE8E5` | 收费+扣课 = 重要/醒目 |
| 天蓝 | `#74B9FF` / 浅底 `#DBF0FF` | 仅扣课时 = 免费/清爽 |
| 草绿 | `#55EFC4` / 浅底 `#F0FFF8` | 校区配置 = 积极/通过 |
| 深灰 | `#2D3436` | 主文字 |
| 中灰 | `#888` | 次要文字 |
| 浅灰 | `#999` / `#E0E0E0` | 禁用/边框 |

**原则**：纯色扁平、圆角优先、禁止渐变（仅 background 微渐变允许）、不使用 box-shadow 做 3D 立体效果。

---

## 二、页面布局（标签页架构）

### 2.1 整体结构

```
┌─────────────────────────────────────────────────────────┐
│  panel-header                                            │
│  课程&活动          课程总数:25  活动总数:5               │
├─────────────────────────────────────────────────────────┤
│  section-tabs                                            │
│  [课程管理]  [活动管理]          ← 珊瑚/天蓝active指示器  │
├─────────────────────────────────────────────────────────┤
│  section-tab-content                                     │
│  ┌── sec-panel #tab-courses-panel (原课程内容)           │
│  ├── sec-panel #tab-activities-panel                     │
│  │   [新增活动] (action-button-group)                     │
│  │   [筛选栏] (filter-bar)                               │
│  │   [活动表格] (table-wrap > #table-activities)          │
│  │   [分页] (pagination)                                 │
└─────────────────────────────────────────────────────────┘
```

### 2.2 标签页视觉规范

修改文件：`static/css/style.css`（`.section-tabs` / `.sec-tab` 区域）

| 状态 | 样式 |
|------|------|
| 默认 | `color: #999`, 透明底 |
| Hover | `color: #555`, `background: rgba(255,224,102,0.08)` |
| Active (课程管理) | `color: #2D3436`, 底边 `3px #FF7675`（珊瑚） + 微渐变底 |
| Active (活动管理) | `color: #2D3436`, 底边 `3px #74B9FF`（天蓝） + 微渐变底 |

---

## 三、活动列表表格

### 3.1 列定义

| 列 | 宽度 | 渲染方式 | 说明 |
|----|------|----------|------|
| 活动名称 | auto (max 200px) | `<strong>` 加粗，单行省略 | 主要标识 |
| 一级学科 | 100px | 纯文本 | |
| 报名日期 | 140px | `开始 ~ 结束` | |
| 成人费用模式 | 120px | `.fee-badge` | 见 3.2 |
| 成人价格 | 100px | ¥金额 或 `-` | `deduct_only` 时显示 `-` |
| 学员费用模式 | 120px | `.fee-badge` | 同上 |
| 学员价格 | 100px | ¥金额 或 `-` | 同上 |
| 适用校区 | 160px | 逗号分隔 + title tooltip | hover 显示容量详情 |
| 操作 | 120px | 编辑/删除按钮 | |

### 3.2 费用模式 Badge

CSS class: `.fee-badge` + 修饰符

| 模式 | CSS class | 背景色 | 文字色 | 示例 |
|------|-----------|--------|--------|------|
| 仅收费 | `fee-badge fee-paid` | `#FFF5CC` (鹅黄) | `#B8860B` | `仅收费 ¥199.00` |
| 收费+扣课时 | `fee-badge fee-mixed` | `#FFE8E5` (珊瑚) | `#D63031` | `收费+扣课 ¥199.00` |
| 仅扣课时 | `fee-badge fee-free` | `#DBF0FF` (天蓝) | `#3A8FD4` | `仅扣课时` |

### 3.3 活动状态 Badge

CSS class: `.activity-status-badge` + 修饰符

| 状态 | CSS class | 背景 | 文字 |
|------|-----------|------|------|
| 进行中 | `status-active` | `#E8FFE8` + 绿边框 | `#27AE60` |
| 已结束 | `status-ended` | `#F0F0F0` + 灰边框 | `#888` |
| 已取消 | `status-cancelled` | `#FFE8E5` + 红边框 | `#D63031` |

---

## 四、费用结构弹窗表单

### 4.1 弹窗整体规格

- 容器：`.modal-overlay > .modal.modal-lg`
- 宽度：`750px`，最大 `95vw`
- 弹窗体：`max-height: 70vh; overflow-y: auto`
- 使用扁平圆角、无阴影的模态框风格

### 4.2 弹窗内分区标题（`.activity-section-title`）

使用彩色左竖线 + 圆点装饰 + 浅色背景的卡片式标题：

```
┌── 成人收费 ────────────────────────────┐
│ ● 收费模式  [仅收费] [收费+扣课时] [仅扣课时] │
│   费用      ¥[____]                      │
│   扣课学科  ┌ 绘画: [2] 课时  [删除]     │
│             └ 书法: [4] 课时  [删除]     │
│   + 添加学科扣课                          │
└──────────────────────────────────────────┘
```

三个分区的颜色编码：

| 分区 | 左边框 | 背景 | 圆点色 |
|------|--------|------|--------|
| 成人收费 | `#FF7675` 珊瑚 | `#FFF0F0` | `#FF7675` |
| 学员收费 | `#74B9FF` 天蓝 | `#F0F7FF` | `#74B9FF` |
| 适用校区 | `#55EFC4` 草绿 | `#F0FFF8` | `#55EFC4` |

### 4.3 收费模式分段控件（可选增强方案）

当前使用 `<select>` 下拉框。建议未来升级为**分段按钮控件**（segmented control），CSS 已预置：

```html
<div class="fee-mode-segment">
    <button class="fee-mode-option active" data-mode="fee_only">仅收费</button>
    <button class="fee-mode-option" data-mode="fee_and_deduct">收费+扣课时</button>
    <button class="fee-mode-option" data-mode="deduct_only">仅扣课时</button>
</div>
```

对应 CSS class：`.fee-mode-segment` / `.fee-mode-option`（已在 `style.css` 中定义）。

### 4.4 扣课学科行（`.deduct-row`）

卡片式设计，每行都有独立的边框和背景：

```
┌─────────────────────────────────────────────────┐
│ [绘画 ▾]  扣课数：[2] 课时  [删除]              │
└─────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────┐
│ [书法 ▾]  扣课数：[4] 课时  [删除]              │
└─────────────────────────────────────────────────┘
```

交互细节：
- 默认：浅蓝灰背景 `#F8FAFF`，边框 `#E8EDF5`
- Hover：边框变天蓝 `#74B9FF`，背景变 `#F0F7FF`，微量阴影
- 删除按钮：珊瑚色文字/背景，hover 反转
- 添加按钮：天蓝色虚线边框，hover 变实色填充

### 4.5 校区容量行（`.campus-capacity-row`）

```
☑ 曲江校区    上限人数：[30] (0=不限)
☐ 高新校区    上限人数：[__] (0=不限)
☑ 长安校区    上限人数：[20] (0=不限)
```

交互细节：
- 隔行变色（odd行 `#FAFAFA`）
- Hover：鹅黄色 `#FFF9E6`
- Checkbox：珊瑚色 `accent-color: #FF7675`
- 输入框聚焦：天蓝边框 + `box-shadow` 光环
- 提示文字：`campus-capacity-hint` 浅灰色

---

## 五、已修改文件清单

### 5.1 `static/css/style.css`

| 区域 | 行号（约） | 变更内容 |
|------|-----------|----------|
| `.section-tabs` / `.sec-tab` | 3406-3443 | 少儿美术风格标签页：珊瑚/天蓝active指示器 |
| `.fee-badge` + 修饰符 | 7966-7995 | 鹅黄/珊瑚/天蓝配色替换原紫色/琥珀/绿色 |
| `.activity-section-title` | 7997-8047 | 分区标题彩色左边框 + 圆点装饰 |
| `.campus-capacity-row` | 8049-8104 | 隔行变色、鹅黄hover、珊瑚checkbox |
| `.deduct-row` | 8106-8206 | 卡片式设计、天蓝hover、专用删除按钮 |
| `.deduct-add-btn` | 8208-8223 | 天蓝虚线添加按钮 |
| `.fee-mode-segment` | 8225-8273 | 分段控件（预置，暂未使用） |
| `.fee-group-card` | 8275-8310 | 费用分组卡片（预置） |
| `.price-input-wrap` | 8312-8326 | ¥前缀价格输入增强（预置） |
| `.deduct-empty-hint` | 8328-8335 | 空状态提示样式 |
| `.activity-status-badge` | 8337-8355 | 活动状态徽章 |
| `#table-activities` | 8357-发布 | 表格增强样式 |

### 5.2 `static/js/main.js`

| 函数 | 变更 |
|------|------|
| `addActivityDeductRow()` | 删除按钮改用 `btn-remove-deduct` class，标签用 `deduct-label` class，移除内联样式 |
| `renderActivityCampusRows()` | 标签改用 `deduct-label` class，提示文字用 `campus-capacity-hint` class，移除内联样式 |

### 5.3 `index.php`

| 位置 | 变更 |
|------|------|
| 成人扣课添加按钮 | `btn btn-sm btn-outline` → `deduct-add-btn` |
| 学员扣课添加按钮 | `btn btn-sm btn-outline` → `deduct-add-btn` |
| 校区添加按钮 | `btn btn-sm btn-outline` → `deduct-add-btn` |

---

## 六、HTML 结构改进建议（未来增强）

### 6.1 收费模式改用分段控件

**当前**：`<select>` 下拉框  
**建议**：分段按钮（更直观，一次点击即可切换）

```html
<!-- 替换原来的 <select id="activity-adult-fee-mode"> -->
<div class="fee-mode-segment" id="fee-mode-adult">
    <button class="fee-mode-option active" data-mode="fee_only" 
            onclick="selectFeeMode('adult', 'fee_only')">仅收费</button>
    <button class="fee-mode-option" data-mode="fee_and_deduct" 
            onclick="selectFeeMode('adult', 'fee_and_deduct')">收费+扣课时</button>
    <button class="fee-mode-option" data-mode="deduct_only" 
            onclick="selectFeeMode('adult', 'deduct_only')">仅扣课时</button>
</div>
<input type="hidden" id="activity-adult-fee-mode" value="">
```

配套 JS：
```javascript
function selectFeeMode(type, mode) {
    document.getElementById('activity-' + type + '-fee-mode').value = mode;
    const seg = document.getElementById('fee-mode-' + type);
    seg.querySelectorAll('.fee-mode-option').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.mode === mode);
    });
    onActivityFeeModeChange(type);
}
```

### 6.2 费用分组使用卡片包裹

**当前**：活动分区标题 + 表单控件平铺  
**建议**：用 `.fee-group-card` 包裹成卡片，视觉更清晰

```html
<!-- 把成人收费区域包裹在卡片中 -->
<div class="fee-group-card fee-group-adult">
    <div class="fee-group-header">
        <span class="fee-group-icon">成</span>
        成人收费设置
    </div>
    <!-- 原有的 form-group 内容 -->
</div>
```

### 6.3 表格增加活动状态列

**当前**：表格无状态列  
**建议**：在「学员价格」和「适用校区」之间插入状态列

```html
<th width="80">状态</th>
<!-- 渲染时 -->
<td><span class="activity-status-badge status-active">进行中</span></td>
```

### 6.4 扣课区域空状态优化

当用户选择了「收费+扣课时」或「仅扣课时」但尚未添加学科时，显示友好提示：

```javascript
// 在 onActivityFeeModeChange() 中：
if (mode === 'fee_and_deduct' || mode === 'deduct_only') {
    const rowsEl = document.getElementById('activity-' + type + '-deduct-rows');
    if (rowsEl.children.length === 0) {
        rowsEl.innerHTML = '<div class="deduct-empty-hint">暂无扣课配置，请点击下方按钮添加</div>';
    }
}
```

---

## 七、验收标准

| # | 检查项 | 预期 |
|---|--------|------|
| 1 | 标签页切换 | "课程管理"珊瑚底边 / "活动管理"天蓝底边 |
| 2 | Fee badge 颜色 | 仅收费=鹅黄 / 收费+扣课=珊瑚 / 仅扣课=天蓝 |
| 3 | 弹窗分区标题 | 成人=珊瑚左边框 / 学员=天蓝左边框 / 校区=草绿左边框 |
| 4 | 扣课学科行 | 卡片式、hover天蓝、删除按钮珊瑚色 |
| 5 | 校区容量行 | 隔行底色、hover鹅黄、checkbox珊瑚色 |
| 6 | 添加按钮 | 天蓝虚线边框、hover实色填充 |
| 7 | 模式切换联动 | 选择模式后正确显示/隐藏价格输入和扣课区域 |
| 8 | 扁平纯色 | 无渐变（除微背景渐变）、无立体阴影 |

---

## 八、色值速查表

```
鹅黄系：  #FFE066 (主)  #FFF5CC (浅底)  #B8860B (深文字)
珊瑚系：  #FF7675 (主)  #FFE8E5 (浅底)  #D63031 (深文字)
天蓝系：  #74B9FF (主)  #DBF0FF (浅底)  #3A8FD4 (深文字)
草绿系：  #55EFC4 (主)  #F0FFF8 (浅底)  #27AE60 (深文字) — 仅用于校区分区
```
