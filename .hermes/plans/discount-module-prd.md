# TMS 优惠管理模块 PRD（产品需求文档）

> 版本：v1.0 | 日期：2026-07-07 | 项目：TMS管理系统（market-system-php）

---

## 目录

1. [需求概述](#1-需求概述)
2. [数据库设计](#2-数据库设计)
3. [API 设计](#3-api-设计)
4. [前端页面结构](#4-前端页面结构)
5. [实施步骤](#5-实施步骤)
6. [附录：关键设计决策](#6-附录关键设计决策)

---

## 1. 需求概述

### 1.1 背景
在教育培训业务中，机构需要为新报名学员和续费学员提供优惠方案（如"新报立减200元""续费9折"等），以促进成交。当前 TMS 系统没有优惠管理能力，需要新增独立模块。

### 1.2 模块位置
- **一级导航**：教务管理
- **插入位置**：`工作记录` 之后（`panel-work-records` 导航项之后）
- **panel ID**：`panel-discounts`
- **标签**：优惠管理

### 1.3 核心功能：优惠方案（第一个标签页）
- 列表展示（分页）
- 新增方案（弹窗表单）
- 编辑方案（弹窗表单，回填已有数据）
- 删除方案（二次确认）
- 搜索/筛选

### 1.4 字段定义

| 字段 | 说明 | 类型 | 必填 | 备注 |
|------|------|------|------|------|
| 方案名称 | 优惠方案名称 | 文本 | 是 | 如"新报立减200"、"续费9折" |
| 类型 | 新报 / 续费 | 单选 | 是 | 枚举：`新报`、`续费` |
| 优惠金额 | 优惠金额（元） | 数字 | 是 | 正数，支持小数 |
| 开始日期 | 有效期开始 | 日期 | 是 | 格式 Y-m-d |
| 结束日期 | 有效期结束 | 日期 | 是 | 必须 ≥ 开始日期 |
| 适用校区 | 树状多选 | 多选 | 否 | 仅 type='校区' 节点；空=全部校区 |
| 适用学科 | 树状多选 | 多选 | 否 | 粒度到二级学科；空=全部学科 |

### 1.5 约束规则
1. 优惠金额必须 > 0
2. 结束日期 ≥ 开始日期
3. 方案名称在同一类型下唯一（同一类型下不能重名）
4. 适用校区为空 = 全校区适用
5. 适用学科为空 = 全学科适用
6. 删除方案前检查是否有关联订单引用（软约束：提示但允许删除）

---

## 2. 数据库设计

### 2.1 优惠方案主表 `discount_plans`

```sql
CREATE TABLE IF NOT EXISTS discount_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(500) NOT NULL COMMENT '方案名称',
    plan_type VARCHAR(20) NOT NULL DEFAULT '新报' COMMENT '类型：新报/续费',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '优惠金额',
    start_date DATE NOT NULL COMMENT '有效期开始',
    end_date DATE NOT NULL COMMENT '有效期结束',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.2 方案-校区关联表 `discount_plan_campuses`

```sql
CREATE TABLE IF NOT EXISTS discount_plan_campuses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plan_id INT NOT NULL COMMENT '关联 discount_plans.id',
    campus_id INT NOT NULL COMMENT '关联 organizations.id（仅校区类型）',
    UNIQUE KEY uk_plan_campus (plan_id, campus_id),
    INDEX idx_plan_id (plan_id),
    INDEX idx_campus_id (campus_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.3 方案-学科关联表 `discount_plan_subjects`

```sql
CREATE TABLE IF NOT EXISTS discount_plan_subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plan_id INT NOT NULL COMMENT '关联 discount_plans.id',
    subject_id INT NOT NULL COMMENT '关联 subjects.id',
    UNIQUE KEY uk_plan_subject (plan_id, subject_id),
    INDEX idx_plan_id (plan_id),
    INDEX idx_subject_id (subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.4 设计说明

| 决策 | 说明 |
|------|------|
| 关联表而非逗号分隔 | 满足数据库范式，支持 JOIN 查询和索引优化 |
| `DECIMAL(10,2)` 金额 | 精确存储，避免浮点精度问题 |
| 校区存 ID 非名称 | 与项目现有 convention 一致（`campus_permission` 存 ID） |
| `ON UPDATE CURRENT_TIMESTAMP` | MySQL 自动维护更新时间 |
| UNIQUE KEY 防止重复 | `plan_id + campus_id` / `plan_id + subject_id` 组合唯一，避免重复绑定 |

### 2.5 兼容块（ALTER TABLE）

由于 TMS 使用 `CREATE TABLE IF NOT EXISTS`，首次部署在已有数据库上表不存在会自动创建。后续如果需要在旧表上加字段，需追加 `SHOW COLUMNS` + `ALTER TABLE` 块。本次为全新表无需兼容块。

---

## 3. API 设计

### 3.1 通用约定

- **Base URL**: `http://127.0.0.1:5001/?action=xxx`
- **Method**: GET 用于查询，POST 用于写入
- **Content-Type**: `application/json`（POST）
- **返回格式**: `{"data": [...], "total": N, ...}` 或 `{"error": "msg"}` / `{"message": "success"}`

### 3.2 API 清单

| Action | Method | 说明 |
|--------|--------|------|
| `list_discount_plans` | GET | 分页列表 + 搜索筛选 |
| `add_discount_plan` | POST | 新增方案 |
| `update_discount_plan` | POST | 编辑方案 |
| `delete_discount_plan` | POST | 删除方案（含关联数据） |
| `get_discount_plan` | GET | 获取单个方案详情（含关联校区/学科） |

### 3.3 接口详细设计

#### 3.3.1 `list_discount_plans` — 分页列表

**请求**：GET
```
?action=list_discount_plans
&page=1
&page_size=15
&keyword=新报          （可选，搜索方案名称）
&plan_type=新报         （可选，筛选类型）
&campus_id=7           （可选，筛选适用校区）
```

**返回**：
```json
{
    "data": [
        {
            "id": 1,
            "name": "新报立减200",
            "plan_type": "新报",
            "amount": 200.00,
            "start_date": "2026-01-01",
            "end_date": "2026-12-31",
            "campus_names": "曲江校区, 高新校区",
            "subject_names": "数学 > 代数, 英语 > 口语",
            "created_at": "2026-07-01 10:00:00"
        }
    ],
    "total": 25,
    "page": 1,
    "page_size": 15
}
```

**后端实现要点**：
- `keyword` 对 `name` 做 LIKE 模糊搜索
- `plan_type` 精确匹配
- `campus_id` 通过 JOIN `discount_plan_campuses` 筛选
- `campus_names` 和 `subject_names` 通过子查询/LEFT JOIN 聚合拼接（类似 `GROUP_CONCAT`）
- 排序：`created_at DESC`

**PHP 代码位置**：`index.php` 的 switch-case 块中，约在 `case 'list_organizations':` 之前（按字母或逻辑分组插入）

#### 3.3.2 `add_discount_plan` — 新增方案

**请求**：POST JSON
```json
{
    "name": "新报立减200",
    "plan_type": "新报",
    "amount": 200.00,
    "start_date": "2026-01-01",
    "end_date": "2026-12-31",
    "campus_ids": [7, 14, 13],
    "subject_ids": [1, 5, 8]
}
```

**返回**：
```json
{"message": "优惠方案创建成功", "id": 1}
```

**校验逻辑**：
1. `name` 非空
2. `plan_type` 必须是 `新报` 或 `续费`
3. `amount` > 0
4. `start_date` 和 `end_date` 非空，且 `end_date >= start_date`
5. 同 `plan_type` 下 `name` 不重复
6. `campus_ids` / `subject_ids` 可选，空数组 = 不限制

**后端实现要点**：
- 使用事务：`$db->beginTransaction()` → INSERT `discount_plans` → 批量 INSERT `discount_plan_campuses` / `discount_plan_subjects` → `$db->commit()`
- 名称唯一性：`SELECT COUNT(*) FROM discount_plans WHERE name=:name AND plan_type=:type`

#### 3.3.3 `update_discount_plan` — 编辑方案

**请求**：POST JSON
```json
{
    "id": 1,
    "name": "新报立减300",
    "plan_type": "新报",
    "amount": 300.00,
    "start_date": "2026-03-01",
    "end_date": "2026-12-31",
    "campus_ids": [7, 14],
    "subject_ids": [1, 5]
}
```

**返回**：
```json
{"message": "优惠方案更新成功"}
```

**后端实现要点**：
- 事务包裹
- 先 UPDATE `discount_plans`
- 再 DELETE 旧的关联记录（`discount_plan_campuses` WHERE plan_id=:id）+ 重新批量 INSERT
- 同样处理 `discount_plan_subjects`
- 名称唯一性：排除自身 `AND id != :id`

#### 3.3.4 `delete_discount_plan` — 删除方案

**请求**：POST JSON
```json
{"id": 1}
```

**返回**：
```json
{"message": "优惠方案已删除"}
```

**后端实现要点**：
- 事务包裹
- 先 DELETE `discount_plan_campuses` WHERE plan_id=:id
- 再 DELETE `discount_plan_subjects` WHERE plan_id=:id
- 最后 DELETE `discount_plans` WHERE id=:id

#### 3.3.5 `get_discount_plan` — 获取单个方案详情

**请求**：GET
```
?action=get_discount_plan&id=1
```

**返回**：
```json
{
    "id": 1,
    "name": "新报立减200",
    "plan_type": "新报",
    "amount": 200.00,
    "start_date": "2026-01-01",
    "end_date": "2026-12-31",
    "campus_ids": [7, 14, 13],
    "subject_ids": [1, 5, 8],
    "created_at": "2026-07-01 10:00:00"
}
```

**用途**：编辑弹窗的数据回填

---

## 4. 前端页面结构

### 4.1 导航栏变更

**文件**：`index.php`，约 5673 行（`panel-work-records` 的 `</li>` 之后）

在「工作记录」和「基础设置」之间插入：

```html
<!-- 插入位置：工作记录 </li> 之后，基础设置 sub-parent 之前 -->
<li class="tree-node">
    <div class="tree-leaf" data-panel="panel-discounts">
        <span class="tree-icon-sub">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                <line x1="7" y1="7" x2="7.01" y2="7"/>
            </svg>
        </span>
        <span class="tree-label">优惠管理</span>
    </div>
</li>
```

### 4.2 JS 路由注册

**文件**：`static/js/main.js`

**`refreshPanel` switch 添加**（约 line 99 之后）：
```javascript
case 'panel-discounts': initDiscountTabs(); loadDiscountPlans(); break;
```

### 4.3 优惠管理面板 HTML

**文件**：`index.php`，在面板区（约 `panel-work-records` section 后面，如 6796 行后）插入：

```html
<!-- 面板：优惠管理 -->
<section class="content-panel" id="panel-discounts">
    <div class="panel-header">
        <h3>优惠管理</h3>
    </div>
    <div class="section-tabs">
        <button class="sec-tab active" data-tab="tab-discount-plans">优惠方案</button>
    </div>
    <div class="section-tab-content">
        <!-- 优惠方案 tab -->
        <div class="sec-panel active" id="tab-discount-plans">
            <!-- 工具栏 -->
            <div class="toolbar">
                <div class="toolbar-left" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <button class="btn btn-primary btn-sm" onclick="showDiscountPlanForm()">+ 新增方案</button>
                    <select id="filter-discount-type" onchange="loadDiscountPlans()" style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                        <option value="">全部类型</option>
                        <option value="新报">新报</option>
                        <option value="续费">续费</option>
                    </select>
                </div>
                <div class="toolbar-right" style="margin-left:auto;">
                    <input type="text" id="search-discount" placeholder="搜索方案名称..." onkeyup="debounceSearch('discount')">
                    <button class="btn btn-primary btn-sm" onclick="loadDiscountPlans()">搜索</button>
                </div>
            </div>
            <!-- 表格 -->
            <div class="table-wrap">
                <table id="table-discount-plans">
                    <thead><tr>
                        <th>方案名称</th>
                        <th width="80">类型</th>
                        <th width="100">优惠金额</th>
                        <th width="110">开始日期</th>
                        <th width="110">结束日期</th>
                        <th>适用校区</th>
                        <th>适用学科</th>
                        <th width="140">创建时间</th>
                        <th width="120">操作</th>
                    </tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="pagination" id="pagination-discount"></div>
        </div>
    </div>
</section>
```

### 4.4 新增/编辑弹窗 HTML

**文件**：`index.php`，模态区（如 `modal-refund-apply` 附近）插入：

```html
<!-- 优惠方案弹窗 -->
<div class="modal-overlay" id="modal-discount-plan">
    <div class="modal-container" style="max-width:720px;">
        <div class="modal-header">
            <h4 id="modal-discount-title">新增优惠方案</h4>
            <button class="modal-close" onclick="closeModal('modal-discount-plan')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="edit-plan-id" value="">
            <div class="form-group">
                <label>方案名称 <span style="color:red;">*</span></label>
                <input type="text" id="discount-name" placeholder="如：新报立减200">
            </div>
            <div class="form-group">
                <label>类型 <span style="color:red;">*</span></label>
                <select id="discount-plan-type">
                    <option value="新报">新报</option>
                    <option value="续费">续费</option>
                </select>
            </div>
            <div class="form-group">
                <label>优惠金额（元） <span style="color:red;">*</span></label>
                <input type="number" id="discount-amount" placeholder="0.00" min="0.01" step="0.01">
            </div>
            <div class="form-row" style="display:flex;gap:12px;">
                <div class="form-group" style="flex:1;">
                    <label>开始日期 <span style="color:red;">*</span></label>
                    <input type="date" id="discount-start-date">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>结束日期 <span style="color:red;">*</span></label>
                    <input type="date" id="discount-end-date">
                </div>
            </div>
            <div class="form-section">
                <h5 class="form-section-title">适用校区</h5>
                <div class="checkbox-tree-wrap" id="discount-campus-tree">
                    <span style="color:#999;font-size:13px;">加载中...</span>
                </div>
            </div>
            <div class="form-section">
                <h5 class="form-section-title">适用学科</h5>
                <div class="checkbox-tree-wrap" id="discount-subject-tree">
                    <span style="color:#999;font-size:13px;">加载中...</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-discount-plan')">取消</button>
            <button class="btn btn-primary" onclick="saveDiscountPlan()">保存</button>
        </div>
    </div>
</div>
```

### 4.5 前端 JS 函数设计

**文件**：`static/js/main.js`，新增约 300 行

#### 4.5.1 状态变量
```javascript
let discountPlanPage = 1;
let discountPlanEditingId = null;
```

#### 4.5.2 核心函数

| 函数 | 说明 |
|------|------|
| `initDiscountTabs()` | 绑定 section-tabs 点击事件 |
| `loadDiscountPlans(page)` | 调用 `list_discount_plans` API，渲染表格 |
| `renderDiscountPlanTable(rows)` | 渲染表格行 |
| `renderDiscountPagination(total, page)` | 渲染分页 |
| `showDiscountPlanForm(id)` | 打开新增/编辑弹窗（id=undefined 为新增） |
| `saveDiscountPlan()` | 提交新增/编辑表单 |
| `deleteDiscountPlan(id, name)` | 删除方案（showCustomConfirm） |
| `loadDiscountCampusTree()` | 加载校区树（复用 `loadCampusTree` 模式，但容器不同） |
| `loadDiscountSubjectTree()` | 加载学科树 |
| `getSelectedDiscountCampuses()` | 收集校区树已选 ID |
| `getSelectedDiscountSubjects()` | 收集学科树已选 ID |
| `debounceSearch('discount')` | 搜索防抖 |

#### 4.5.3 表格渲染模板

```javascript
function renderDiscountPlanTable(rows) {
    const tbody = document.querySelector('#table-discount-plans tbody');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#999;padding:30px;">暂无优惠方案</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td>${esc(r.name)}</td>
            <td><span class="tag tag-${r.plan_type === '新报' ? 'green' : 'blue'}">${esc(r.plan_type)}</span></td>
            <td style="text-align:right;font-weight:600;color:#DC2626;">¥${Number(r.amount).toFixed(2)}</td>
            <td>${r.start_date || '-'}</td>
            <td>${r.end_date || '-'}</td>
            <td title="${esc(r.campus_names || '')}">${esc((r.campus_names || '全部校区').length > 20 ? (r.campus_names || '全部校区').substring(0, 20) + '...' : (r.campus_names || '全部校区'))}</td>
            <td title="${esc(r.subject_names || '')}">${esc((r.subject_names || '全部学科').length > 20 ? (r.subject_names || '全部学科').substring(0, 20) + '...' : (r.subject_names || '全部学科'))}</td>
            <td>${(r.created_at || '').substring(0, 16)}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-link" onclick="showDiscountPlanForm(${r.id})">编辑</button>
                    <button class="btn-link-danger" onclick="deleteDiscountPlan(${r.id}, '${esc(r.name).replace(/'/g, "\\'")}')">删除</button>
                </div>
            </td>
        </tr>
    `).join('');
}
```

#### 4.5.4 学科树渲染（新函数，不可复用现有 `loadSubjects`）

现有 `loadSubjects` 是为「学科设置」面板渲染的（带编辑/删除按钮），优惠弹窗需要纯 checkbox 树。需要新建独立函数：

```javascript
let discountSubjectCheckboxData = [];

async function loadDiscountSubjectTree() {
    const container = document.getElementById('discount-subject-tree');
    if (!container) return;
    container.innerHTML = '<span style="color:#999;font-size:13px;">加载中...</span>';
    try {
        const result = await api('list_subjects', {}, 'GET');
        const tree = result.tree || [];
        discountSubjectCheckboxData = result.flat || [];
        container.innerHTML = tree.map(node => renderDiscountSubjectNode(node, 0)).join('');
    } catch (e) {
        container.innerHTML = '<span style="color:#e6a23c;">加载学科失败</span>';
    }
}

function renderDiscountSubjectNode(node, level) {
    const hasChildren = node.children && node.children.length > 0;
    let html = `<div class="campus-tree-node" data-id="${node.id}" data-has-children="${!!hasChildren}" data-expanded="${level === 0}">`;
    html += `<div class="campus-tree-row" style="padding-left:${level * 20 + 12}px">`;
    if (hasChildren) {
        html += `<span class="campus-tree-arrow" onclick="toggleCampusTreeExpand(this)">${level === 0 ? '▾' : '▸'}</span>`;
    } else {
        html += '<span class="campus-tree-arrow" style="visibility:hidden;">▸</span>';
    }
    html += `<input type="checkbox" class="campus-tree-check" onclick="onDiscountSubjectCheck(this)">`;
    html += `<span class="campus-tree-label">${esc(node.name)}</span>`;
    html += '</div>';
    if (hasChildren) {
        html += `<div class="campus-tree-children" style="display:${level === 0 ? 'block' : 'none'}">`;
        node.children.forEach(child => { html += renderDiscountSubjectNode(child, level + 1); });
        html += '</div>';
    }
    html += '</div>';
    return html;
}

function onDiscountSubjectCheck(el) {
    const node = el.closest('.campus-tree-node');
    if (!node) return;
    const checked = el.checked;
    node.querySelectorAll('.campus-tree-check').forEach(c => { c.checked = checked; c.indeterminate = false; });
    // 向上传播三态
    const parentNode = node.parentElement?.closest('.campus-tree-node');
    if (parentNode) updateDiscountSubjectParentState(parentNode);
}

function getSelectedDiscountSubjects() {
    const checks = document.querySelectorAll('#discount-subject-tree .campus-tree-check:checked');
    return Array.from(checks).map(c => c.closest('.campus-tree-node').dataset.id);
}

function getSelectedDiscountCampuses() {
    const checks = document.querySelectorAll('#discount-campus-tree .campus-tree-node[data-type="校区"] .campus-tree-check:checked');
    return Array.from(checks).map(c => c.closest('.campus-tree-node').dataset.id);
}
```

**校区树**可复用现有 `renderCampusTreeNode` 逻辑，但需要独立容器 `discount-campus-tree`，用 `loadCampusTree('discount-campus-tree')` 调用。

### 4.6 Editable Campus Tree Reuse Strategy

`loadCampusTree(containerId)` 现有实现支持任意容器 ID，且已实现多选 checkbox + 三态传播 + 剪枝（只显示校区节点）。优惠弹窗可直接调用：

```javascript
// 在 showDiscountPlanForm() 中：
await loadCampusTree('discount-campus-tree');
await loadDiscountSubjectTree();
// 编辑模式时，回填已选节点
if (id) {
    const detail = await api('get_discount_plan', { id }, 'GET');
    // 勾选对应校区
    (detail.campus_ids || []).forEach(cid => {
        const cb = document.querySelector(`#discount-campus-tree .campus-tree-node[data-id="${cid}"] .campus-tree-check`);
        if (cb) { cb.checked = true; onDiscountCampusCheck(cb); }
    });
    // 勾选对应学科
    (detail.subject_ids || []).forEach(sid => {
        const cb = document.querySelector(`#discount-subject-tree .campus-tree-node[data-id="${sid}"] .campus-tree-check`);
        if (cb) { cb.checked = true; onDiscountSubjectCheck(cb); }
    });
}
```

**⚠️ 注意**：`getSelectedCampuses()` 现有实现硬编码了 `#course-campus-tree` 选择器，优惠弹窗需要独立的 `getSelectedDiscountCampuses()` 函数。

---

## 5. 实施步骤

### Phase 1：数据库（后端，~10 分钟）

**文件**：`index.php`

1. **PHP 文件头建表区域**（约 30-200 行 CREATE TABLE 块后）
   - 添加 `discount_plans` 建表语句
   - 添加 `discount_plan_campuses` 建表语句
   - 添加 `discount_plan_subjects` 建表语句

2. **验证**：启动 PHP 服务器，观察控制台无 SQL 错误

### Phase 2：API 后端（后端，~30 分钟）

**文件**：`index.php`，switch-case 路由块中

1. 插入 5 个 case 分支（按字母顺序，`case 'add_discount_plan':` → `case 'delete_discount_plan':` → `case 'get_discount_plan':` → `case 'list_discount_plans':` → `case 'update_discount_plan':`）

2. **验证**：
```bash
# 启动 PHP 服务器后测试
curl "http://127.0.0.1:5001/?action=list_discount_plans"
# 预期返回 {"data":[],"total":0,"page":1,"page_size":15}

curl -X POST "http://127.0.0.1:5001/?action=add_discount_plan" \
  -H "Content-Type: application/json" \
  -d '{"name":"新报立减200","plan_type":"新报","amount":200,"start_date":"2026-01-01","end_date":"2026-12-31","campus_ids":[7],"subject_ids":[1]}'
```

### Phase 3：导航栏（前端，~5 分钟）

**文件**：`index.php`

1. 在「工作记录」`</li>`（约 5673 行）和 `<li class="tree-node">`「基础设置」之间插入导航项
2. 在面板区（`panel-work-records` section 后）插入优惠管理面板 HTML
3. 在模态区插入弹窗 HTML

### Phase 4：前端 JS（前端，~40 分钟）

**文件**：`static/js/main.js`

1. **注册路由**：`refreshPanel` 添加 `panel-discounts` case
2. **标签页初始化**：`initDiscountTabs()`
3. **列表功能**：`loadDiscountPlans`、`renderDiscountPlanTable`、分页
4. **弹窗功能**：`showDiscountPlanForm`、`saveDiscountPlan`
5. **学科树**：`loadDiscountSubjectTree`、`renderDiscountSubjectNode`、`getSelectedDiscountSubjects`
6. **校区树**：复用 `loadCampusTree('discount-campus-tree')`
7. **删除确认**：`deleteDiscountPlan`

### Phase 5：样式（CSS，~10 分钟）

**文件**：`static/css/style.css`

1. 类型标签：`.tag-green`（新报）、`.tag-blue`（续费）
2. 弹窗表单双列布局：`#modal-discount-plan .form-row`
3. 分区标题：`.form-section-title`

```css
/* 优惠方案类型标签 */
.tag-green { background: #ecfdf5; color: #059669; padding: 2px 10px; border-radius: 10px; font-size: 12px; font-weight: 600; }
.tag-blue  { background: #eff6ff; color: #2563eb; padding: 2px 10px; border-radius: 10px; font-size: 12px; font-weight: 600; }

/* 弹窗分区标题 */
.form-section-title {
    font-size: 14px; font-weight: 600; color: var(--color-primary);
    padding-bottom: 6px; border-bottom: 1px solid #eee; margin: 16px 0 8px;
}
```

### Phase 6：端到端测试（~15 分钟）

1. 启动 PHP 服务器 + MySQL
2. 浏览器打开 http://127.0.0.1:5001
3. 点击「优惠管理」→ 验证面板显示
4. 新增方案 → 验证表单校验 + 校区/学科树正常
5. 编辑方案 → 验证数据回填
6. 删除方案 → 验证二次确认
7. 搜索/筛选 → 验证联动

---

## 6. 附录：关键设计决策

### 6.1 为什么用关联表而非逗号分隔字符串？
项目中 `courses.campus_permission` 存的是逗号分隔 ID 字符串（`'7,14,13'`），那是历史原因。优惠方案需要 JOIN 查询（如按校区筛选、展示校区名称），关联表更适合。且 `discount_plan_campuses` 和 `discount_plan_subjects` 数据量可控（单个方案的绑定数不超过几十条），性能无问题。

### 6.2 为什么不在现有学科树/校区树基础上改？
现有 `loadCampusTree()` 和 `loadSubjects()` / `renderSubjectTree()` 是为课程表单和学科设置面板设计的，DOM 选择器硬编码（如 `getSelectedCampuses` 用 `#course-campus-tree`）。直接在优惠弹窗中共用会造成状态污染。最安全的方式是用新的容器 ID 调用（校区树可复用函数，学科树需新函数）。

### 6.3 面板为什么放在「工作记录」之后？
「工作记录」是教务管理最后一个叶子节点，优惠管理也是教务模块的一部分。后续优惠方案可能与交易订单联动（报名时自动匹配优惠），放在教务管理下逻辑通顺。

### 6.4 为什么只有一个标签页？
当前需求明确只开发「优惠方案」功能。`section-tabs` 结构预留了扩展空间——后续可增加「优惠活动」「优惠券」等标签页，只需加 `sec-tab` + `sec-panel` 即可，无需改动结构。

### 6.5 TMS 开发规范遵从清单

| 规范 | 遵从情况 |
|------|----------|
| 禁用 alert/confirm | ✅ 使用 `showToast` + `showCustomConfirm` |
| `$db->quote()` 不二次包引号 | ✅ 直接 `name=" . $db->quote($name)` |
| campus_permission 存 ID | ✅ `discount_plan_campuses` 存 `organizations.id` |
| JS 函数查重 | ✅ 新增前 `grep "function 函数名" main.js` |
| 并发安全 | ⚠️ 暂无库存扣减，不涉及 `FOR UPDATE` |
| API 返回格式 | ✅ `list_xxx` → `{data, total, page}`，增删改 → `{message}` |
| Flatpickr | ✅ 弹窗中的 `input[type="date"]` 会被全局 `initDatePickers()` 自动处理 |
| 新增表无兼容块 | ✅ 全新表，`CREATE TABLE IF NOT EXISTS` 覆盖 |
| colspan 同步 | ✅ 9 列，空状态 colspan="9" |
