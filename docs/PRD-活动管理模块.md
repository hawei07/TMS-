# PRD：活动管理模块

> 版本：v1.0 | 日期：2026-07-09 | 作者：Product Manager Agent
>
> 关联项目：TMS管理系统 (`D:/market-system-php/`)

---

## 一、需求概述

### 1.1 背景

当前 TMS 课程管理页面（`#panel-courses`）仅支持课程 CRUD。业务方需要新增"活动"概念——区别于常规课程，活动是一次性的报名事件，涉及成人和学员分别定价、按学科扣课时、按校区限制容量等复杂费用模型。

### 1.2 核心需求

| # | 需求 | 优先级 |
|---|------|--------|
| 1 | 课程管理页面改为两个标签页：**课程管理** \| **活动管理** | P0 |
| 2 | 左侧导航"课程管理"改名为"课程&活动" | P0 |
| 3 | 活动 CRUD（创建、列表、编辑、删除） | P0 |
| 4 | 活动费用结构：成人和学员分别定价，支持"仅收费/收费+扣课时/仅扣课时"三种模式 | P0 |
| 5 | 扣课时按一级学科分别设置扣课数 | P1 |
| 6 | 适用校区多选，每个校区可设报名人数上限 | P0 |

---

## 二、页面重构

### 2.1 导航变更

**现状**（`index.php` 第 7048–7055 行）：

```html
<span class="tree-label">教务管理</span>
...
<span class="tree-label">课程管理</span>
```

**改为**：

```html
<span class="tree-label">课程&活动</span>
```

`data-panel="panel-courses"` 不变，但面板内部后续改为双标签页。

### 2.2 面板标签页重构

**现状**：`#panel-courses` 直接渲染课程卡片（`index.php` 第 7584–7652 行）。

**改为**：在 `#panel-courses` 的 `.panel-header` 下插入标签页切换栏，原有课程内容作为第一个标签页，新增活动管理作为第二个标签页。

遵循项目已有的 **`section-tabs` + `section-tab-content` + `sec-panel`** 模式（参考画具管理 `#panel-teaching-aids`，行 8341–8347）：

```html
<!-- 面板：课程&活动 -->
<section class="content-panel" id="panel-courses">
    <div class="panel-header">
        <h3>课程&活动</h3>
        <div class="header-stats-inline">
            <span class="stat-badge stat-badge-courses">课程总数：<strong id="stat-courses-inline">0</strong></span>
            <span class="stat-badge stat-badge-activities">活动总数：<strong id="stat-activities-inline">0</strong></span>
        </div>
    </div>

    <!-- 标签页切换 -->
    <div class="section-tabs">
        <button class="sec-tab active" data-tab="tab-courses-panel">课程管理</button>
        <button class="sec-tab" data-tab="tab-activities-panel">活动管理</button>
    </div>
    <div class="section-tab-content">
        <!-- 课程管理标签页（原有内容） -->
        <div class="sec-panel active" id="tab-courses-panel">
            <!-- 原有的 action-button-group + filter-bar + course-cards-wrap + pagination -->
        </div>

        <!-- 活动管理标签页（新增） -->
        <div class="sec-panel" id="tab-activities-panel">
            <!-- 见下文 UI 布局 -->
        </div>
    </div>
</section>
```

### 2.3 标签切换 JS

复用项目已有的 Tab 切换逻辑（`:not(.sec-tab)` 除外，`.sec-tab` 已有事件委托），在 `activatePanel` 或 `switchToPanel` 中检测当前活跃标签页加载对应数据：

```javascript
// 切换到 panel-courses 时默认加载课程
if (panelId === 'panel-courses') {
    loadFilterSubjects();
    const activeTab = document.querySelector('#panel-courses .sec-tab.active');
    if (!activeTab || activeTab.dataset.tab === 'tab-courses-panel') {
        loadCourses();
    } else {
        loadActivities();
    }
}
```

**Tab 切换事件委托**（首页加载时绑定）：

```javascript
document.querySelector('#panel-courses .section-tabs').addEventListener('click', function(e) {
    const tab = e.target.closest('.sec-tab');
    if (!tab) return;
    this.querySelectorAll('.sec-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    const panelId = tab.dataset.tab;
    document.querySelectorAll('#panel-courses .sec-panel').forEach(p => p.classList.remove('active'));
    document.getElementById(panelId).classList.add('active');
    if (panelId === 'tab-courses-panel') loadCourses();
    else loadActivities();
});
```

---

## 三、数据库设计

### 3.1 新建表

#### `activities` — 活动主表

```sql
CREATE TABLE IF NOT EXISTS activities (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    name            VARCHAR(500) NOT NULL,          -- 活动名称
    subject_level1  VARCHAR(500) DEFAULT '',         -- 一级学科（关联 subjects WHERE parent_id=0）
    reg_start_date  VARCHAR(20) DEFAULT '',           -- 报名开始日期 (YYYY-MM-DD)
    reg_end_date    VARCHAR(20) DEFAULT '',           -- 报名截止日期 (YYYY-MM-DD)
    adult_fee_mode  VARCHAR(50) DEFAULT '',           -- 成人收费模式: fee_only / fee_and_deduct / deduct_only
    student_fee_mode VARCHAR(50) DEFAULT '',          -- 学员收费模式: fee_only / fee_and_deduct / deduct_only
    adult_price     DECIMAL(10,2) DEFAULT 0.00,      -- 成人报名费（元）
    student_price   DECIMAL(10,2) DEFAULT 0.00,      -- 学员报名费（元）
    status          VARCHAR(50) DEFAULT '进行中',      -- 状态: 进行中 / 已结束 / 已取消
    created_at      VARCHAR(20) DEFAULT ''
);
```

#### `activity_campuses` — 活动校区配置

```sql
CREATE TABLE IF NOT EXISTS activity_campuses (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    activity_id     INT NOT NULL,                     -- 关联 activities.id
    campus_id       INT NOT NULL,                     -- 关联 organizations.id (type='校区')
    max_capacity    INT DEFAULT 0,                    -- 该校区报名人数上限 (0=不限)
    created_at      VARCHAR(20) DEFAULT ''
);
```

#### `activity_subject_deductions` — 按学科扣课配置

```sql
CREATE TABLE IF NOT EXISTS activity_subject_deductions (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    activity_id     INT NOT NULL,                     -- 关联 activities.id
    fee_type        VARCHAR(20) NOT NULL,             -- 'adult' 或 'student'
    subject_id      INT NOT NULL,                     -- 关联 subjects.id (一级学科, parent_id=0)
    subject_name    VARCHAR(500) DEFAULT '',           -- 冗余：学科名称，方便展示
    deduct_lessons  INT DEFAULT 0,                    -- 扣课时数
    created_at      VARCHAR(20) DEFAULT ''
);
```

### 3.2 索引建议

```sql
ALTER TABLE activity_campuses ADD INDEX idx_ac_activity (activity_id);
ALTER TABLE activity_campuses ADD INDEX idx_ac_campus (campus_id);
ALTER TABLE activity_subject_deductions ADD INDEX idx_asd_activity (activity_id);
ALTER TABLE activity_subject_deductions ADD INDEX idx_asd_subject (subject_id);
```

### 3.3 费用结构说明

| 模式 | 值 | 含义 | adult_price / student_price | 扣课配置 |
|------|-----|------|----------------------------|----------|
| 仅收费 | `fee_only` | 只收取报名费，不扣课时 | 必填 | 无 |
| 收费+扣课时 | `fee_and_deduct` | 收取报名费 + 按学科扣除课时 | 必填 | 必填 |
| 仅扣课时 | `deduct_only` | 只扣课时，不收报名费 | 忽略(0) | 必填 |

**模式约束**：
- `fee_only` → `adult_price` / `student_price` 必填，不展示扣课配置
- `fee_and_deduct` → 价格和扣课配置均必填
- `deduct_only` → 仅需扣课配置，价格显示为"免费"

### 3.4 与现有表的关联

| 现有表 | 关联方式 | 说明 |
|--------|----------|------|
| `subjects` | `subject_level1` 字段存储学科名称，`activity_subject_deductions.subject_id` 存 ID | 一级学科 parent_id=0 |
| `organizations` | `activity_campuses.campus_id` 存校区 ID（type='校区'） | 多选校区 |
| `courses` | 无直接关联 | 活动独立于课程体系 |

---

## 四、API 清单

### 4.1 活动 CRUD

#### `list_activities` — 活动列表（支持分页+筛选）

```
GET /?action=list_activities&page=1&page_size=15&keyword=&subject_level1=&status=
```

**请求参数**：

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| page | int | 否 | 页码，默认 1 |
| page_size | int | 否 | 每页条数，默认 15 |
| keyword | string | 否 | 搜索活动名称 |
| subject_level1 | string | 否 | 一级学科筛选 |
| status | string | 否 | 状态筛选 |

**返回格式**：

```json
{
    "total": 10,
    "page": 1,
    "page_size": 15,
    "data": [
        {
            "id": 1,
            "name": "2026暑期绘画集训营",
            "subject_level1": "绘画",
            "reg_start_date": "2026-07-01",
            "reg_end_date": "2026-08-15",
            "adult_fee_mode": "fee_and_deduct",
            "student_fee_mode": "deduct_only",
            "adult_price": "199.00",
            "student_price": "0.00",
            "status": "进行中",
            "created_at": "2026-07-09 10:00",
            "campuses": [
                {"campus_id": 7, "campus_name": "曲江校区", "max_capacity": 30},
                {"campus_id": 14, "campus_name": "高新校区", "max_capacity": 20}
            ],
            "deductions": {
                "adult": [
                    {"subject_id": 5, "subject_name": "绘画", "deduct_lessons": 2},
                    {"subject_id": 8, "subject_name": "书法", "deduct_lessons": 4}
                ],
                "student": [
                    {"subject_id": 5, "subject_name": "绘画", "deduct_lessons": 3}
                ]
            }
        }
    ]
}
```

#### `get_activity` — 活动详情

```
GET /?action=get_activity&id=1
```

返回单个活动完整数据（含 campuses 和 deductions）。

#### `save_activity` — 新增/编辑活动

```
POST /?action=save_activity
Content-Type: application/json

{
    "id": 0,                           // 0=新增, >0=编辑
    "name": "暑期绘画集训营",
    "subject_level1": "绘画",
    "reg_start_date": "2026-07-01",
    "reg_end_date": "2026-08-15",
    "adult_fee_mode": "fee_and_deduct",
    "student_fee_mode": "deduct_only",
    "adult_price": 199.00,
    "student_price": 0,
    "campuses": [
        {"campus_id": 7, "max_capacity": 30},
        {"campus_id": 14, "max_capacity": 20}
    ],
    "deductions": {
        "adult": [
            {"subject_id": 5, "deduct_lessons": 2}
        ],
        "student": [
            {"subject_id": 5, "deduct_lessons": 3}
        ]
    }
}
```

**后端逻辑**：
- 使用事务：先 INSERT/UPDATE `activities`，再 DELETE + INSERT `activity_campuses` 和 `activity_subject_deductions`
- 验证 `subject_level1` 在 `subjects WHERE parent_id=0` 中存在
- 验证所有 `campus_id` 在 `organizations WHERE type='校区'` 中存在

**返回**：`{"id": 1, "message": "活动保存成功"}`

#### `delete_activity` — 删除活动

```
POST /?action=delete_activity
{"id": 1}
```

使用事务级联删除 campus 和 deduction 记录。

### 4.2 辅助 API

#### `get_level1_subjects` — 获取一级学科列表（复用 `list_subjects` 过滤 parent_id=0）

前端从现有的 `list_subjects` API 返回的 `tree` 中提取 `parent_id=0` 的节点即可，无需新增 API。或简化为直接 filter。

**说明**：现有 `list_subjects` 返回 `{tree: [...], flat: [...]}`。前端可遍历 `tree` 获取一级学科列表（含子节点的 children）。

---

## 五、前端 UI 布局

### 5.1 活动管理标签页整体布局

```
┌─────────────────────────────────────────────────────┐
│  课程&活动                                            │
│  [课程管理] [活动管理]    课程总数:25  活动总数:5       │
├─────────────────────────────────────────────────────┤
│  [+ 新增活动]                                         │
│                                                       │
│  [🔍 搜索活动...]  [全部学科 ▾]  [全部状态 ▾]  [重置]  │
│                                                       │
│  ┌─────────────────────────────────────────────────┐ │
│  │ 活动名称          │ 学科  │ 报名日期     │ 状态  │…│ │
│  │ 暑期绘画集训营    │ 绘画  │ 07/01-08/15 │ 进行中│  │ │
│  │ 成人: ¥199+扣课  │       │             │       │  │ │
│  │ 学员: 仅扣课      │       │             │       │  │ │
│  ├─────────────────────────────────────────────────┤ │
│  │ 成人书法体验日    │ 书法  │ 07/15-07/20 │ 已结束│  │ │
│  │ 成人: 仅收费 ¥50 │       │             │       │  │ │
│  │ 学员: ¥30+扣课   │       │             │       │  │ │
│  └─────────────────────────────────────────────────┘ │
│                                                       │
│  < 1  2  3 >  共 5 条                                 │
└─────────────────────────────────────────────────────┘
```

### 5.2 活动列表表格列定义

| 列 | 宽度 | 说明 |
|----|------|------|
| 活动名称 | auto | 加粗显示，下方小字显示收费模式摘要 |
| 一级学科 | 100px | |
| 报名日期 | 140px | 07/01 ~ 08/15 |
| 适用校区 | 160px | 校区名称列表，hover 显示容量详情 |
| 成人收费 | 120px | badge: "¥199+扣课" / "仅收费¥50" / "仅扣课" |
| 学员收费 | 120px | 同上 |
| 状态 | 80px | badge: 进行中(绿) / 已结束(灰) / 已取消(红) |
| 操作 | 120px | 编辑 / 删除 |

### 5.3 新增/编辑活动弹窗（`modal-activity`）

弹窗使用项目已有的 `.modal-overlay` + `.modal` 体系，宽度 680px（`.modal-lg`）。

```
┌──────────────────────────────────────────────────┐
│  新增活动                                   [×]   │
├──────────────────────────────────────────────────┤
│                                                    │
│  活动名称:  [___________________________]          │
│                                                    │
│  一级学科:  [绘画 ▾]                               │
│                                                    │
│  报名日期:  [2026-07-01] 至 [2026-08-15]           │
│                                                    │
│  ──────── 适用校区 ────────                        │
│  ☑ 曲江校区  上限: [30] 人                         │
│  ☑ 高新校区  上限: [20] 人                         │
│  ☐ 长安校区  上限: [__] 人                         │
│  [+ 添加校区]                                       │
│                                                    │
│  ──────── 成人收费 ────────                        │
│  模式:  [仅收费 ▾]  [收费+扣课时]  [仅扣课时]       │
│  费用:  ¥[____]（仅收费/收费+扣课时时显示）          │
│  扣课:  ┌ 绘画: [2] 课时    [删除]                 │
│         └ 书法: [4] 课时    [删除]                  │
│  [+ 添加学科扣课]（仅"收费+扣课时"/"仅扣课时"显示）  │
│                                                    │
│  ──────── 学员收费 ────────                        │
│  模式:  [仅收费 ▾]  [收费+扣课时]  [仅扣课时]       │
│  费用:  ¥[____]                                    │
│  扣课:  ┌ 绘画: [3] 课时    [删除]                 │
│  [+ 添加学科扣课]                                   │
│                                                    │
│              [取消]  [保存]                         │
└──────────────────────────────────────────────────┘
```

### 5.4 收费模式切换逻辑（JS 交互）

```javascript
function onFeeModeChange(type) {
    // type = 'adult' | 'student'
    const mode = document.getElementById(`fee-mode-${type}`).value;
    const priceRow = document.getElementById(`fee-price-row-${type}`);
    const deductRow = document.getElementById(`fee-deduct-row-${type}`);

    if (mode === 'fee_only') {
        priceRow.style.display = '';
        deductRow.style.display = 'none';
    } else if (mode === 'fee_and_deduct') {
        priceRow.style.display = '';
        deductRow.style.display = '';
    } else if (mode === 'deduct_only') {
        priceRow.style.display = 'none';
        deductRow.style.display = '';
    }
}
```

### 5.5 活动卡片模式（可选方案 B）

如果活动数量较多，可使用课程卡片同款的 `.course-cards-wrap` 网格布局，每个卡片展示：

```
┌────────────────────────┐
│  暑期绘画集训营          │
│  绘画                   │
│  📅 07/01 - 08/15      │
│  🏫 曲江(30人) 高新(20)  │
│  👤 成人: ¥199+扣课     │
│  🎓 学员: 仅扣课        │
│  [编辑] [删除]          │
└────────────────────────┘
```

**推荐**：表格模式（方案 A），因为活动字段较多，卡片不易对齐且信息密度低。

---

## 六、CSS 样式要点

### 6.1 新增 CSS class

```css
/* 活动管理表格 */
#table-activities { width: 100%; border-collapse: collapse; }
#table-activities th,
#table-activities td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }

/* 收费模式 badge */
.fee-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    background: #F3E8FF;
    color: #7C3AED;
    white-space: nowrap;
}
.fee-badge.fee-free { background: #ECFDF5; color: #059669; }      /* 仅扣课/免费 */
.fee-badge.fee-paid { background: #FEF3C7; color: #D97706; }      /* 仅收费 */
.fee-badge.fee-mixed { background: #F3E8FF; color: #7C3AED; }     /* 收费+扣课 */

/* 活动弹窗：校区行 */
.campus-capacity-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
}
.campus-capacity-row input[type="number"] {
    width: 70px;
    padding: 4px 8px;
    border: 1px solid var(--border);
    border-radius: 4px;
}

/* 扣课学科行 */
.deduct-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 0;
}
.deduct-row select { min-width: 130px; }
.deduct-row input[type="number"] { width: 60px; }

/* 弹窗分区标题 */
.activity-section-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--color-primary);
    margin: 16px 0 8px;
    padding-bottom: 4px;
    border-bottom: 1px solid var(--border);
}
```

### 6.2 复用现有样式

- `.section-tabs` / `.sec-tab` / `.section-tab-content` / `.sec-panel` — 标签页切换
- `.filter-bar` / `.filter-item` / `.filter-label` — 筛选栏
- `.action-button-group` / `.action-btn` — 操作按钮组
- `.toolbar` / `.toolbar-left` / `.toolbar-right` — 工具栏
- `.table-wrap` — 表格容器
- `.pagination` — 分页

---

## 七、实现步骤

### 第 1 步：数据库建表（DDL）

在 `index.php` 初始化区（`$db->exec(...)` 序列）追加三张新表的 `CREATE TABLE IF NOT EXISTS` 语句。

**位置**：约第 190 行 `courses` 表定义之后。

### 第 2 步：导航改名

修改 `index.php` 约第 7055 行：

```php
// 旧
<span class="tree-label">课程管理</span>

// 新
<span class="tree-label">课程&活动</span>
```

### 第 3 步：面板 HTML 重构

将 `#panel-courses` 内部改为标签页结构。保留原有课程列表 HTML 作为 `#tab-courses-panel`；新增 `#tab-activities-panel` 含活动筛选栏 + 表格 + 分页的 HTML 骨架。

### 第 4 步：后端 API

在 `index.php` 的 API 路由 `switch($action)` 中，`// ==================== 课程管理 API ====================` 之后追加活动 API：

1. `list_activities` — 列表查询（JOIN campuses 和 deductions）
2. `get_activity` — 详情查询
3. `save_activity` — 新增/编辑（事务）
4. `delete_activity` — 删除（事务级联）

### 第 5 步：前端 JS

在 `main.js` 中 `// ==================== 课程管理 ====================` 区域之后追加活动管理函数：

- `loadActivities()` — 列表加载
- `renderActivityTable(data)` — 表格渲染
- `showActivityModal(id?)` — 弹窗（新增/编辑）
- `saveActivity()` — 表单提交
- `deleteActivity(id)` — 删除确认
- `onActivityFeeModeChange(type)` — 收费模式切换 UI
- `addDeductRow(type)` / `removeDeductRow(btn)` — 动态增减扣课学科行
- `addCampusRow()` / `removeCampusRow(btn)` — 动态增减校区行

### 第 6 步：CSS 样式

在 `style.css` 末尾追加活动管理相关样式。

### 第 7 步：验证

```bash
# 1. PHP 语法
php -l index.php

# 2. JS 函数去重
grep -oP "function \w+" static/js/main.js | sort | uniq -d

# 3. 重启 PHP 服务
taskkill //f //im php.exe && php -S 0.0.0.0:5001 -t D:/market-system-php

# 4. API 测试
curl -s "http://127.0.0.1:5001/?action=list_activities" | head -c 200
```

---

## 八、风险与注意事项

### 8.1 引用现有陷阱

| 陷阱 # | 标题 | 本模块相关度 |
|--------|------|-------------|
| #83 | price_items 加字段全链路 | ⚠ 活动不涉及 price_items，但新增 activity 表三张，`save_activity` 的事务逻辑类似 `save_price_plan` |
| #88 | PDO 命名参数重复 | 扣课 INSERT 循环中注意参数名唯一 |
| #129 | XSS：esc() 在 onclick 中无效 | 活动列表的操作按钮用 `data-*` + 事件委托 |
| #130 | `mb_strlen()` 未启用导致 PHP Fatal | 避免使用 mb_* 函数 |
| #131 | Tab 面板必须有 `section-tab-content` 包裹 | 活动管理标签页结构必须遵守 |
| #132 | `showCustomConfirm` 转义 HTML | 删除确认弹窗用纯文本 + `\n` |

### 8.2 项目特有注意点

1. **`$db->quote()` 双引号陷阱**（陷阱 #5）：INSERT 时不要包两层引号
2. **PHP 内置服务器缓存**：修改 index.php 后必须重启 `php -S`
3. **校区 ID vs 名称**：`activity_campuses.campus_id` 存的是 organizations.id，不是名称
4. **科目名称 vs ID**：`activities.subject_level1` 存名称（与 courses 表一致），`activity_subject_deductions.subject_id` 存 ID + 冗余 `subject_name`
5. **Tab 切换时数据加载**：首次切换到活动标签页时调用 `loadActivities()`，课程标签页保持原有 `loadCourses()`

---

## 九、附录：完整 SQL Schema

```sql
-- ============ 活动管理模块 DDL ============

CREATE TABLE IF NOT EXISTS activities (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    name            VARCHAR(500) NOT NULL,
    subject_level1  VARCHAR(500) DEFAULT '',
    reg_start_date  VARCHAR(20) DEFAULT '',
    reg_end_date    VARCHAR(20) DEFAULT '',
    adult_fee_mode  VARCHAR(50) DEFAULT '',
    student_fee_mode VARCHAR(50) DEFAULT '',
    adult_price     DECIMAL(10,2) DEFAULT 0.00,
    student_price   DECIMAL(10,2) DEFAULT 0.00,
    status          VARCHAR(50) DEFAULT '进行中',
    created_at      VARCHAR(20) DEFAULT ''
);

CREATE TABLE IF NOT EXISTS activity_campuses (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    activity_id     INT NOT NULL,
    campus_id       INT NOT NULL,
    max_capacity    INT DEFAULT 0,
    created_at      VARCHAR(20) DEFAULT '',
    INDEX idx_ac_activity (activity_id),
    INDEX idx_ac_campus (campus_id)
);

CREATE TABLE IF NOT EXISTS activity_subject_deductions (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    activity_id     INT NOT NULL,
    fee_type        VARCHAR(20) NOT NULL,
    subject_id      INT NOT NULL,
    subject_name    VARCHAR(500) DEFAULT '',
    deduct_lessons  INT DEFAULT 0,
    created_at      VARCHAR(20) DEFAULT '',
    INDEX idx_asd_activity (activity_id),
    INDEX idx_asd_subject (subject_id)
);
```

---

## 十、后续扩展（roadmap）

- [ ] 活动报名记录表（`activity_registrations`）：记录学员/成人报名信息，关联 `students` 表
- [ ] 活动报名前端页面：面向家长的报名入口
- [ ] 扣课执行：报名成功后自动从对应订单扣除课时
- [ ] 校区容量实时统计：`当前报名人数 / 上限`
- [ ] 活动状态自动流转：根据 `reg_end_date` 自动将"进行中"改为"已结束"
