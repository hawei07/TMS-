# 画具管理「购买画具 + 销售记录」Tab 页功能 PRD

> **版本**: v1.0  
> **日期**: 2026-07-09  
> **作者**: Product Manager (Hermes Agent)  
> **项目路径**: `D:\market-system-php\`  
> **技术栈**: PHP 8.4 + MySQL 8.4.9 (PDO) + Vanilla JS + CSS3  
> **服务地址**: 127.0.0.1:5001  
> **依赖**: 已上线的画具管理模块（`panel-teaching-aids`）

---

## 目录

1. [需求概述](#1-需求概述)
2. [数据库设计](#2-数据库设计)
3. [API 设计](#3-api-设计)
4. [前端 UI 设计](#4-前端-ui-设计)
5. [购买流程交互](#5-购买流程交互)
6. [边界情况与约束](#6-边界情况与约束)
7. [实现步骤](#7-实现步骤)
8. [验证清单](#8-验证清单)

---

## 1. 需求概述

### 1.1 背景

当前画具管理模块（`panel-teaching-aids`）仅支持画具/教材包的 CRUD 管理（列表+增删改查），缺少「学员购买画具」和「销售记录查询」功能。需要在画具管理面板内新增两个 tab 页，实现完整的画具销售闭环。

### 1.2 功能清单

| # | 功能 | Tab | 描述 |
|---|------|-----|------|
| 1 | 购买画具 | `购买画具` | 展示所有上架画具，学员可选购商品，确认后生成销售记录 |
| 2 | 销售记录 | `销售记录` | 表格展示所有销售记录，支持筛选（学员、商品、时间范围） |

### 1.3 核心约束

- **只能卖给学员**（`students` 表记录），不能卖给资源（`resources`）/非学员
- **只能卖上架状态的商品**（`teaching_aids.status = '上架'`）
- 购买时用户可以指定购买数量
- 销售记录不可编辑/删除（财务审计要求）

### 1.4 用户流程

```
用户打开画具管理面板
    └─ 点击「购买画具」tab
        ├─ 查看所有上架画具列表（卡片/表格形式）
        ├─ 搜索/选择学员（弹窗搜索学员）
        ├─ 勾选商品 + 指定数量
        └─ 确认购买 → 生成销售记录
    └─ 点击「销售记录」tab
        ├─ 查看所有销售记录表格
        └─ 按学员/商品/时间范围筛选
```

---

## 2. 数据库设计

### 2.1 新增表 `teaching_aid_sales`

```sql
CREATE TABLE IF NOT EXISTS teaching_aid_sales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teaching_aid_id INT NOT NULL COMMENT '关联 teaching_aids.id',
    student_id INT NOT NULL COMMENT '关联 students.id（不能为空）',
    student_name VARCHAR(200) NOT NULL DEFAULT '' COMMENT '学员姓名（冗余）',
    teaching_aid_name VARCHAR(200) NOT NULL DEFAULT '' COMMENT '商品名称（冗余）',
    type VARCHAR(50) NOT NULL DEFAULT '画具' COMMENT '类型：画具/教材包（冗余）',
    quantity INT NOT NULL DEFAULT 1 COMMENT '购买数量',
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '销售时单价',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '数量 × 单价',
    campus VARCHAR(500) NOT NULL DEFAULT '' COMMENT '销售时校区（冗余）',
    sold_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '销售时间',
    remark VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
    INDEX idx_sales_aid (teaching_aid_id),
    INDEX idx_sales_student (student_id),
    INDEX idx_sales_sold_at (sold_at),
    FOREIGN KEY (teaching_aid_id) REFERENCES teaching_aids(id) ON DELETE RESTRICT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.2 字段说明

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | INT PK | 自动 | 主键 |
| teaching_aid_id | INT | ✅ | 关联 teaching_aids，外键 RESTRICT（有销售记录不可删画具） |
| student_id | INT | ✅ | 关联 students，外键 RESTRICT（有销售记录不可删学员） |
| student_name | VARCHAR(200) | ✅ | 冗余学员姓名，避免 JOIN 查询 |
| teaching_aid_name | VARCHAR(200) | ✅ | 冗余商品名称，避免 JOIN 查询 |
| type | VARCHAR(50) | ✅ | 冗余类型（画具/教材包），方便筛选 |
| quantity | INT | ✅ | 购买数量，默认 1 |
| unit_price | DECIMAL(10,2) | ✅ | 销售时的单价（快照，不受后续调价影响） |
| total_amount | DECIMAL(10,2) | ✅ | 数量 × 单价 |
| campus | VARCHAR(500) | ✅ | 销售时校区名称（逗号分隔，从 teaching_aid_campuses JOIN 获取，冗余快照） |
| sold_at | DATETIME | ✅ | 销售时间，默认当前时间 |
| remark | VARCHAR(500) | 否 | 备注（预留） |

### 2.3 设计决策

| 决策 | 原因 |
|------|------|
| **冗余 student_name / teaching_aid_name / type / campus** | 避免 JOIN 查询，提升列表性能；销售记录是历史快照，源数据可能被修改或删除 |
| **外键 ON DELETE RESTRICT** | 已有销售记录的商品不允许直接删除，防止数据孤儿；如需删除，需先处理关联销售记录 |
| **unit_price 快照** | 销售时锁定价格，后续调价不影响历史记录 |
| **sold_at 索引** | 时间范围筛选高频使用 |
| **不设编辑/删除 API** | 财务审计要求，销售记录只读 |

### 2.4 建表位置

在 `index.php` 建表区（`teaching_aid_campuses` 建表之后，约第 683 行）追加。

---

## 3. API 设计

### 3.1 API 清单

| # | Action | Method | 功能 | 说明 |
|---|--------|--------|------|------|
| 1 | `list_available_teaching_aids` | GET | 获取可购买的上架商品列表 | 只返回 status='上架' 的画具 |
| 2 | `search_students_for_sale` | GET | 搜索学员（购买场景） | 只返回 students 表记录（排除资源） |
| 3 | `create_teaching_aid_sale` | POST | 创建销售记录 | 支持一次购买多个商品 |
| 4 | `list_teaching_aid_sales` | GET | 销售记录列表（分页+筛选） | 支持学员/商品/时间范围筛选 |

### 3.2 接口详细规格

---

#### 3.2.1 `list_available_teaching_aids` — 可购买商品列表

```
GET /?action=list_available_teaching_aids&keyword=&page=1&page_size=50
```

**请求参数**:

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| keyword | string | 否 | — | 模糊匹配 name |
| page | int | 否 | 1 | 页码 |
| page_size | int | 否 | 50 | 每页条数（购买场景通常不需要分页，一次加载全部） |

**返回 JSON**:

```json
{
    "data": [
        {
            "id": 1,
            "name": "素描铅笔套装",
            "type": "画具",
            "unit": "套",
            "price": 128.00,
            "subject_name": "美术",
            "campus_names": "曲江校区, 高新校区",
            "status": "上架",
            "remark": "包含2B-6B铅笔12支"
        }
    ],
    "total": 5,
    "page": 1,
    "page_size": 50
}
```

**SQL 实现**:

```sql
SELECT ta.id, ta.name, ta.type, ta.unit, ta.price, ta.status, ta.remark,
    s.name AS subject_name,
    (SELECT GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ')
     FROM teaching_aid_campuses tac
     LEFT JOIN organizations o ON tac.campus_id = o.id
     WHERE tac.teaching_aid_id = ta.id) AS campus_names
FROM teaching_aids ta
LEFT JOIN subjects s ON ta.subject_id = s.id
WHERE ta.status = '上架'
  AND (keyword = '' OR ta.name LIKE '%keyword%')
ORDER BY ta.id DESC
LIMIT offset, page_size
```

> **只查询 `status='上架'` 的商品**，下架商品不出现。

---

#### 3.2.2 `search_students_for_sale` — 搜索学员

```
GET /?action=search_students_for_sale&keyword=张三
```

**请求参数**:

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| keyword | string | ✅ | — | 模糊匹配 name / phone / student_no |

**返回 JSON**:

```json
{
    "data": [
        {
            "id": 1,
            "student_no": "2024000001",
            "name": "张三",
            "phone": "13800138000",
            "student_type": "常规"
        }
    ]
}
```

**SQL 实现**:

```sql
SELECT id, student_no, name, phone, student_type
FROM students
WHERE (name LIKE '%keyword%' OR phone LIKE '%keyword%' OR student_no LIKE '%keyword%')
ORDER BY id DESC
LIMIT 50
```

> ⚠ **核心约束**: 只查 `students` 表，不查 `resources` 表。不使用 `showTempStudentModal` 的那个搜索（那个同时查资源+学员）。

---

#### 3.2.3 `create_teaching_aid_sale` — 创建销售记录

```
POST /?action=create_teaching_aid_sale
Content-Type: application/json

{
    "student_id": 1,
    "items": [
        {"teaching_aid_id": 1, "quantity": 2},
        {"teaching_aid_id": 3, "quantity": 1}
    ],
    "remark": ""
}
```

**请求参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| student_id | int | ✅ | 学员 ID（必须存在且为 students 表记录） |
| items | array | ✅ | 购买商品列表，每个元素含 teaching_aid_id + quantity |
| items[].teaching_aid_id | int | ✅ | 商品 ID |
| items[].quantity | int | ✅ | 购买数量（>= 1） |
| remark | string | 否 | 备注 |

**校验规则**:

1. `student_id` 必须存在于 `students` 表（查 `SELECT COUNT(*) FROM students WHERE id=?`）
2. `items` 不能为空
3. 每个 `teaching_aid_id` 必须存在于 `teaching_aids` 表，且 `status = '上架'`
4. 每个 `quantity` >= 1
5. 不允许重复的 `teaching_aid_id`

**返回 JSON**:

```json
{
    "message": "销售记录创建成功",
    "sale_ids": [1, 2],
    "total_amount": 384.00
}
```

**实现（事务）**:

```php
$db->beginTransaction();
try {
    // 1. 校验学员存在
    $student = $db->query("SELECT id, name, student_no FROM students WHERE id=$studentId")->fetch();
    if (!$student) throw new Exception('学员不存在');

    // 2. 逐个校验商品 + 插入销售记录
    $saleIds = [];
    $totalAmount = 0;
    foreach ($items as $item) {
        $ta = $db->query(
            "SELECT ta.*, 
                (SELECT GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ')
                 FROM teaching_aid_campuses tac
                 LEFT JOIN organizations o ON tac.campus_id = o.id
                 WHERE tac.teaching_aid_id = ta.id) AS campus_names
             FROM teaching_aids ta WHERE ta.id = {$item['teaching_aid_id']}"
        )->fetch();
        if (!$ta) throw new Exception("商品不存在: id={$item['teaching_aid_id']}");
        if ($ta['status'] !== '上架') throw new Exception("商品「{$ta['name']}」已下架，无法购买");

        $qty = intval($item['quantity']);
        if ($qty < 1) throw new Exception("数量无效");

        $unitPrice = floatval($ta['price']);
        $amount = round($unitPrice * $qty, 2);
        $totalAmount += $amount;

        $campus = $ta['campus_names'] ?? '';

        $db->exec("INSERT INTO teaching_aid_sales 
            (teaching_aid_id, student_id, student_name, teaching_aid_name, type,
             quantity, unit_price, total_amount, campus, sold_at, remark)
            VALUES ({$ta['id']}, $studentId, " . $db->quote($student['name']) . ", 
            " . $db->quote($ta['name']) . ", " . $db->quote($ta['type']) . ",
            $qty, $unitPrice, $amount, " . $db->quote($campus) . ", NOW(), 
            " . $db->quote($remark ?? '') . ")");

        $saleIds[] = $db->lastInsertId();
    }

    $db->commit();
    json(['message' => '销售记录创建成功', 'sale_ids' => $saleIds, 'total_amount' => round($totalAmount, 2)]);
} catch (Exception $e) {
    $db->rollBack();
    json(['error' => $e->getMessage()]);
}
```

---

#### 3.2.4 `list_teaching_aid_sales` — 销售记录列表

```
GET /?action=list_teaching_aid_sales&student_name=&teaching_aid_name=&date_from=&date_to=&page=1&page_size=20
```

**请求参数**:

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| student_name | string | 否 | — | 模糊匹配学员姓名 |
| teaching_aid_name | string | 否 | — | 模糊匹配商品名称 |
| date_from | string | 否 | — | 销售时间起始（YYYY-MM-DD） |
| date_to | string | 否 | — | 销售时间截止（YYYY-MM-DD） |
| page | int | 否 | 1 | 页码 |
| page_size | int | 否 | 20 | 每页条数 |

**返回 JSON**:

```json
{
    "data": [
        {
            "id": 1,
            "teaching_aid_id": 1,
            "teaching_aid_name": "素描铅笔套装",
            "type": "画具",
            "student_id": 1,
            "student_name": "张三",
            "quantity": 2,
            "unit_price": 128.00,
            "total_amount": 256.00,
            "campus": "曲江校区, 高新校区",
            "sold_at": "2026-07-09 14:30:00",
            "remark": ""
        }
    ],
    "total": 15,
    "page": 1,
    "page_size": 20
}
```

**SQL 实现**:

```sql
SELECT * FROM teaching_aid_sales
WHERE 1=1
  AND (student_name = '' OR student_name LIKE '%student_name%')
  AND (teaching_aid_name = '' OR teaching_aid_name LIKE '%teaching_aid_name%')
  AND (date_from = '' OR sold_at >= 'date_from 00:00:00')
  AND (date_to = '' OR sold_at <= 'date_to 23:59:59')
ORDER BY sold_at DESC, id DESC
LIMIT offset, page_size
```

> ⚠ 所有筛选字段均为可选，不传或为空字符串时不启用该筛选条件。

---

### 3.3 API case 插入位置

在 `index.php` 的 switch-case 块中，画具管理 API 区块（`// ==================== 画具管理 API ====================`，约 3665 行附近）追加六个新 case：

```
case 'list_available_teaching_aids':
case 'search_students_for_sale':
case 'create_teaching_aid_sale':
case 'list_teaching_aid_sales':
```

---

## 4. 前端 UI 设计

### 4.1 面板 HTML 改动

现有 `panel-teaching-aids` 的 `section-tabs` 从 1 个 tab 扩展为 3 个 tab：

```html
<!-- 面板：画具管理 -->
<section class="content-panel" id="panel-teaching-aids">
    <div class="panel-header"><h3>画具管理</h3></div>
    <div class="section-tabs">
        <span class="sec-tab active" data-tab="tab-teaching-aids">画具列表</span>
        <span class="sec-tab" data-tab="tab-buy-aids">购买画具</span>
        <span class="sec-tab" data-tab="tab-sales-records">销售记录</span>
    </div>
    
    <!-- Tab 1: 画具列表（现有，不变） -->
    <div id="tab-teaching-aids">
        ...现有工具栏+表格...
    </div>

    <!-- Tab 2: 购买画具（新增） -->
    <div id="tab-buy-aids" style="display:none;">
        <!-- 见 4.2 -->
    </div>

    <!-- Tab 3: 销售记录（新增） -->
    <div id="tab-sales-records" style="display:none;">
        <!-- 见 4.4 -->
    </div>
</section>
```

**改动点**（`index.php` 约 8090-8141 行）:

1. 在 `section-tabs` 栏追加两个 `<span class="sec-tab">` 
2. 在 `tab-teaching-aids` 之后追加两个 tab 面板 div

### 4.2 Tab 2: 购买画具 — HTML 结构

```html
<div id="tab-buy-aids" style="display:none;">
    <!-- 顶部：学员选择区 -->
    <div class="toolbar" style="margin-bottom:16px;">
        <div class="toolbar-left" style="display:flex;align-items:center;gap:12px;">
            <label style="font-weight:600;white-space:nowrap;">选择学员：</label>
            <div id="buy-student-display" style="
                display:flex;align-items:center;gap:8px;padding:6px 12px;
                border:1px solid var(--border);border-radius:6px;
                background:var(--color-bg);min-width:200px;cursor:pointer;
            " onclick="openStudentPickerForBuy()">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="var(--color-text-muted)" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <span id="buy-student-name" style="color:var(--color-text-muted);">点击选择学员</span>
                <span id="buy-student-clear" style="display:none;color:var(--color-danger);cursor:pointer;margin-left:auto;" onclick="event.stopPropagation();clearBuyStudent()">✕</span>
            </div>
            <!-- 已选学员信息 -->
            <span id="buy-student-info" style="display:none;color:var(--color-text-secondary);font-size:13px;"></span>
        </div>
        <div class="toolbar-right">
            <input type="text" id="buy-aid-search" class="form-input" 
                   placeholder="搜索商品名称" 
                   onkeyup="if(event.key==='Enter')loadAvailableAids()"
                   style="width:200px;">
            <button class="btn btn-search" onclick="loadAvailableAids()">搜索</button>
        </div>
    </div>

    <!-- 商品卡片列表 -->
    <div id="buy-aids-list" class="buy-aids-grid" style="
        display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:12px;
        margin-bottom:16px;
    ">
        <div class="empty-state" style="grid-column:1/-1;">加载中...</div>
    </div>

    <!-- 底部：已选商品汇总 + 确认购买 -->
    <div id="buy-cart-bar" style="
        display:none;position:sticky;bottom:0;background:var(--color-surface);
        border-top:2px solid var(--color-primary);padding:12px 16px;
        border-radius:8px 8px 0 0;z-index:10;box-shadow:0 -4px 12px rgba(0,0,0,0.08);
    ">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <div>
                <span style="font-weight:600;">已选 <span id="buy-cart-count" style="color:var(--color-primary);">0</span> 件商品</span>
                <span style="margin-left:16px;color:var(--color-text-secondary);">合计：</span>
                <span id="buy-cart-total" style="font-size:18px;font-weight:700;color:#DC2626;">¥0.00</span>
            </div>
            <button class="btn btn-primary" onclick="confirmBuyAids()" id="btn-confirm-buy" disabled>
                确认购买
            </button>
        </div>
    </div>
</div>
```

### 4.3 商品卡片模板

每张卡片显示：

- 商品名称（粗体）
- 类型标签（画具 🔵 / 教材包 🟠）
- 单价（红色 ¥xx.xx / 单位）
- 适用校区
- 数量选择器（步进器）
- 勾选复选框（或点击卡片选中）

```javascript
// JS 渲染模板
function renderBuyAidCard(aid, idx) {
    const isSelected = buyCartItems[aid.id] !== undefined;
    const qty = isSelected ? buyCartItems[aid.id].quantity : 1;
    const typeTag = aid.type === '画具' 
        ? '<span class="tag-blue" style="font-size:11px;">画具</span>' 
        : '<span class="tag-orange" style="font-size:11px;">教材包</span>';
    
    return `
    <div class="buy-aid-card ${isSelected ? 'selected' : ''}" 
         data-aid="${aid.id}"
         style="
            border:2px solid ${isSelected ? 'var(--color-primary)' : 'var(--border)'};
            border-radius:10px;padding:14px;cursor:pointer;
            background:${isSelected ? 'var(--color-primary-light, #f5f0ff)' : '#fff'};
            transition:all 0.2s;
         ">
        <!-- 商品头部 -->
        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px;">
            <div>
                <div style="font-weight:600;font-size:15px;margin-bottom:4px;">${esc(aid.name)}</div>
                <div style="display:flex;gap:6px;align-items:center;">
                    ${typeTag}
                    <span style="color:var(--color-text-muted);font-size:12px;">${esc(aid.unit)}</span>
                </div>
            </div>
            <input type="checkbox" class="buy-aid-check" 
                   data-aid="${aid.id}" 
                   ${isSelected ? 'checked' : ''}
                   onclick="event.stopPropagation();toggleBuyItem(${aid.id})"
                   style="width:18px;height:18px;cursor:pointer;">
        </div>
        
        <!-- 价格 -->
        <div style="margin-bottom:8px;">
            <span style="font-size:20px;font-weight:700;color:#DC2626;">¥${Number(aid.price).toFixed(2)}</span>
            <span style="color:var(--color-text-muted);font-size:12px;"> / ${esc(aid.unit)}</span>
        </div>
        
        <!-- 校区 -->
        <div style="font-size:12px;color:var(--color-text-secondary);margin-bottom:10px;" 
             title="${esc(aid.campus_names || '')}">
            🏫 ${esc((aid.campus_names || '—').substring(0, 30))}${(aid.campus_names || '').length > 30 ? '...' : ''}
        </div>
        
        <!-- 数量选择器 -->
        <div style="display:flex;align-items:center;gap:8px;"
             onclick="event.stopPropagation();">
            <span style="font-size:12px;color:var(--color-text-muted);">数量：</span>
            <button class="stepper-btn" 
                    onclick="changeBuyQty(${aid.id}, -1)"
                    ${!isSelected ? 'disabled' : ''}
                    style="width:28px;height:28px;border:1px solid var(--border);border-radius:4px;background:#fff;cursor:pointer;">−</button>
            <span class="buy-qty-display" data-aid="${aid.id}" 
                  style="min-width:24px;text-align:center;font-weight:600;">${qty}</span>
            <button class="stepper-btn" 
                    onclick="changeBuyQty(${aid.id}, 1)"
                    ${!isSelected ? 'disabled' : ''}
                    style="width:28px;height:28px;border:1px solid var(--border);border-radius:4px;background:#fff;cursor:pointer;">+</button>
            <span style="color:var(--color-text-muted);font-size:12px;">${esc(aid.unit)}</span>
        </div>
    </div>`;
}
```

**交互规则**:
- 点击复选框 → 选中/取消商品（默认数量 1）
- 点击卡片主体区域 → 同复选框
- 数量步进器：未选中时禁用（灰色），选中后可用
- 步进器下限 1，无上限
- 卡片的 `selected` 样式：紫色边框 + 浅紫背景

### 4.4 Tab 3: 销售记录 — HTML 结构

```html
<div id="tab-sales-records" style="display:none;">
    <!-- 筛选栏 -->
    <div class="toolbar" style="margin-bottom:12px;flex-wrap:wrap;gap:8px;">
        <div class="toolbar-left" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="text" id="sales-filter-student" class="form-input" 
                   placeholder="学员姓名" style="width:140px;"
                   onkeyup="if(event.key==='Enter'){salesPage=1;loadSalesRecords();}">
            <input type="text" id="sales-filter-aid" class="form-input" 
                   placeholder="商品名称" style="width:140px;"
                   onkeyup="if(event.key==='Enter'){salesPage=1;loadSalesRecords();}">
            <input type="date" id="sales-filter-from" class="form-input" 
                   style="width:140px;" title="起始日期"
                   onchange="salesPage=1;loadSalesRecords();">
            <span style="color:var(--color-text-muted);">至</span>
            <input type="date" id="sales-filter-to" class="form-input" 
                   style="width:140px;" title="截止日期"
                   onchange="salesPage=1;loadSalesRecords();">
            <button class="btn btn-search" onclick="salesPage=1;loadSalesRecords();">查询</button>
            <button class="btn btn-outline btn-sm" onclick="clearSalesFilters()">重置</button>
        </div>
        <div class="toolbar-right">
            <span style="color:#888;font-size:13px;" id="sales-total-count"></span>
        </div>
    </div>

    <!-- 表格 -->
    <div class="table-wrap">
        <table id="table-sales-records">
            <thead>
                <tr>
                    <th style="width:50px;">#</th>
                    <th style="width:160px;">销售时间</th>
                    <th style="width:100px;">学员姓名</th>
                    <th>商品名称</th>
                    <th style="width:70px;">类型</th>
                    <th style="width:60px;text-align:center;">数量</th>
                    <th style="width:90px;text-align:right;">单价</th>
                    <th style="width:100px;text-align:right;">总金额</th>
                    <th>校区</th>
                </tr>
            </thead>
            <tbody id="sales-tbody">
                <tr><td colspan="9"><div class="empty-state">暂无销售记录</div></td></tr>
            </tbody>
        </table>
    </div>

    <!-- 分页 -->
    <div class="pagination" id="pagination-sales"></div>
</div>
```

### 4.5 Tab 切换逻辑

参照现有 `initCouponTabs()` 模式（`main.js` ~10859），新增 `initTeachingAidTabs()`：

```javascript
function initTeachingAidTabs() {
    document.querySelectorAll('#panel-teaching-aids .sec-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            // 切换 active
            document.querySelectorAll('#panel-teaching-aids .sec-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // 切换 tab 面板可见性
            ['tab-teaching-aids', 'tab-buy-aids', 'tab-sales-records'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.display = id === this.dataset.tab ? 'block' : 'none';
            });
            
            // 按需加载数据
            const tabId = this.dataset.tab;
            if (tabId === 'tab-teaching-aids') { teachingAidPage = 1; loadTeachingAids(); }
            else if (tabId === 'tab-buy-aids') { loadAvailableAids(); }
            else if (tabId === 'tab-sales-records') { salesPage = 1; loadSalesRecords(); }
        });
    });
}
```

在 `DOMContentLoaded` 或已有的初始化位置调用 `initTeachingAidTabs()`。

---

## 5. 购买流程交互

### 5.1 完整交互流程

```
1. 用户打开画具管理 → 点击「购买画具」tab
2. 系统加载所有上架商品卡片列表
3. 用户点击「选择学员」→ 弹出学员搜索弹窗
   ├─ 输入关键词搜索（姓名/手机号/学号）
   ├─ 列表展示学员（学号、姓名、手机号、学员类型）
   └─ 点击学员行 → 选中（弹窗关闭，顶部显示已选学员）
4. 用户勾选商品 + 调整数量
   ├─ 点击卡片 / 复选框 → 选中/取消
   └─ 步进器调整数量（≥1）
5. 底部「购物车栏」显示已选商品数和合计金额
6. 用户点击「确认购买」
   ├─ 二次确认弹窗：列出所选商品清单 + 总金额
   └─ 确认 → POST create_teaching_aid_sale
7. 成功 → Toast 提示 → 自动切到「销售记录」tab 查看结果
8. 失败 → Toast 提示错误信息
```

### 5.2 学员搜索弹窗

**HTML**（新增弹窗，放在 `modal-temp-student` 附近，约 9065 行之后）:

```html
<!-- 弹窗：选择购买学员 -->
<div class="modal-overlay" id="modal-buy-student-picker">
    <div class="modal" style="max-width:600px;width:94vw;">
        <div class="modal-header">
            <h3>选择学员</h3>
            <button class="modal-close" onclick="closeModal('modal-buy-student-picker')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom:12px;">
                <input type="text" id="buy-student-search" 
                       placeholder="搜索：学号 / 姓名 / 手机号" 
                       style="width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:14px;"
                       oninput="onBuyStudentSearch()" autocomplete="off">
            </div>
            <div class="table-wrap" style="max-height:400px;overflow-y:auto;">
                <table>
                    <thead><tr><th>学号</th><th>姓名</th><th>手机号</th><th>类型</th><th>操作</th></tr></thead>
                    <tbody id="buy-student-tbody">
                        <tr><td colspan="5" style="text-align:center;color:#999;padding:20px;">
                            请输入关键词搜索学员
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
```

**JS 函数**:

```javascript
let buyStudentSearchTimer;
let buySelectedStudent = null; // { id, name, student_no, phone }

function openStudentPickerForBuy() {
    document.getElementById('buy-student-search').value = '';
    openModal('modal-buy-student-picker');
    onBuyStudentSearch(); // 初始加载（空关键词返回最近学员）
}

function onBuyStudentSearch() {
    clearTimeout(buyStudentSearchTimer);
    buyStudentSearchTimer = setTimeout(async () => {
        const keyword = document.getElementById('buy-student-search')?.value || '';
        const tbody = document.getElementById('buy-student-tbody');
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;">搜索中...</td></tr>';
        
        const data = await api('search_students_for_sale', { keyword }, 'GET');
        const rows = data.data || [];
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;padding:20px;">未找到匹配学员</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => `
            <tr style="cursor:pointer;" onclick="selectBuyStudent(${r.id}, '${esc(r.name).replace(/'/g, "\\'")}', '${esc(r.student_no || '')}', '${esc(r.phone || '')}')">
                <td style="font-family:monospace;font-size:12px;">${esc(r.student_no || '—')}</td>
                <td><strong>${esc(r.name)}</strong></td>
                <td>${esc(r.phone || '—')}</td>
                <td><span class="tag ${r.student_type === '常规' ? 'tag-green' : 'tag-gray'}">${esc(r.student_type)}</span></td>
                <td><button class="btn btn-sm btn-primary">选择</button></td>
            </tr>
        `).join('');
    }, 300);
}

function selectBuyStudent(id, name, studentNo, phone) {
    buySelectedStudent = { id, name, student_no: studentNo, phone };
    document.getElementById('buy-student-name').textContent = name;
    document.getElementById('buy-student-name').style.color = 'var(--color-text)';
    document.getElementById('buy-student-clear').style.display = 'inline';
    document.getElementById('buy-student-info').textContent = `${studentNo || '—'} | ${phone || '—'}`;
    document.getElementById('buy-student-info').style.display = 'inline';
    updateBuyCartBar();
    closeModal('modal-buy-student-picker');
}

function clearBuyStudent() {
    buySelectedStudent = null;
    document.getElementById('buy-student-name').textContent = '点击选择学员';
    document.getElementById('buy-student-name').style.color = 'var(--color-text-muted)';
    document.getElementById('buy-student-clear').style.display = 'none';
    document.getElementById('buy-student-info').style.display = 'none';
    updateBuyCartBar();
}
```

### 5.3 购物车管理

```javascript
// 购物车状态：{ [aid]: { id, name, type, price, unit, campus_names, quantity } }
let buyCartItems = {};

function toggleBuyItem(aid) {
    if (buyCartItems[aid]) {
        delete buyCartItems[aid];
    } else {
        const card = document.querySelector(`.buy-aid-card[data-aid="${aid}"]`);
        buyCartItems[aid] = {
            id: aid,
            name: card.dataset.name,
            type: card.dataset.type,
            price: parseFloat(card.dataset.price),
            unit: card.dataset.unit,
            campus_names: card.dataset.campus || '',
            quantity: 1
        };
    }
    refreshBuyCards();
    updateBuyCartBar();
}

function changeBuyQty(aid, delta) {
    if (!buyCartItems[aid]) return;
    const newQty = buyCartItems[aid].quantity + delta;
    if (newQty < 1) return;
    buyCartItems[aid].quantity = newQty;
    document.querySelector(`.buy-qty-display[data-aid="${aid}"]`).textContent = newQty;
    updateBuyCartBar();
}

function updateBuyCartBar() {
    const bar = document.getElementById('buy-cart-bar');
    const btn = document.getElementById('btn-confirm-buy');
    const items = Object.values(buyCartItems);
    
    if (items.length === 0 || !buySelectedStudent) {
        bar.style.display = 'none';
        btn.disabled = true;
        return;
    }
    
    bar.style.display = 'block';
    const count = items.reduce((sum, it) => sum + it.quantity, 0);
    const total = items.reduce((sum, it) => sum + it.price * it.quantity, 0);
    document.getElementById('buy-cart-count').textContent = count;
    document.getElementById('buy-cart-total').textContent = '¥' + total.toFixed(2);
    btn.disabled = false;
}
```

### 5.4 确认购买

```javascript
async function confirmBuyAids() {
    const items = Object.values(buyCartItems);
    if (items.length === 0) { showToast('请先选择商品', 'error'); return; }
    if (!buySelectedStudent) { showToast('请先选择学员', 'error'); return; }

    // 构建确认文案
    let confirmHtml = `<div style="text-align:left;">
        <p><strong>学员：</strong>${esc(buySelectedStudent.name)} (${esc(buySelectedStudent.student_no || '—')})</p>
        <table style="width:100%;margin:8px 0;border-collapse:collapse;">
            <tr style="border-bottom:1px solid var(--border);"><th style="text-align:left;padding:6px;">商品</th><th style="text-align:center;padding:6px;">数量</th><th style="text-align:right;padding:6px;">金额</th></tr>`;
    
    let total = 0;
    items.forEach(it => {
        const amt = it.price * it.quantity;
        total += amt;
        confirmHtml += `<tr style="border-bottom:1px solid #f5f5f5;">
            <td style="padding:6px;">${esc(it.name)}</td>
            <td style="text-align:center;padding:6px;">${it.quantity} ${esc(it.unit)}</td>
            <td style="text-align:right;padding:6px;color:#DC2626;">¥${amt.toFixed(2)}</td>
        </tr>`;
    });
    
    confirmHtml += `<tr><td colspan="3" style="text-align:right;padding:8px;font-weight:700;">
        合计：<span style="color:#DC2626;font-size:16px;">¥${total.toFixed(2)}</span>
    </td></tr></table></div>`;

    // 直接用 showCustomConfirm（纯文本版本，因为 HTML 会被转义）
    const textSummary = items.map(it => `${it.name} ×${it.quantity}`).join('、');
    const confirmMsg = `确认购买？\n\n学员：${buySelectedStudent.name}\n商品：${textSummary}\n合计：¥${total.toFixed(2)}`;

    showCustomConfirm(confirmMsg, async () => {
        const payload = {
            student_id: buySelectedStudent.id,
            items: items.map(it => ({ teaching_aid_id: it.id, quantity: it.quantity })),
            remark: ''
        };
        
        const result = await api('create_teaching_aid_sale', payload);
        if (result.error) { showToast(result.error, 'error'); return; }
        
        showToast(`购买成功！合计 ¥${parseFloat(result.total_amount).toFixed(2)}`, 'success');
        
        // 清空购物车
        buyCartItems = {};
        buySelectedStudent = null;
        clearBuyStudent();
        refreshBuyCards();
        updateBuyCartBar();
        
        // 自动切换到销售记录 tab
        document.querySelectorAll('#panel-teaching-aids .sec-tab').forEach(t => t.classList.remove('active'));
        const salesTab = document.querySelector('#panel-teaching-aids .sec-tab[data-tab="tab-sales-records"]');
        if (salesTab) {
            salesTab.classList.add('active');
            document.getElementById('tab-buy-aids').style.display = 'none';
            document.getElementById('tab-sales-records').style.display = 'block';
            document.getElementById('tab-teaching-aids').style.display = 'none';
        }
        salesPage = 1;
        loadSalesRecords();
    });
}
```

---

## 6. 边界情况与约束

### 6.1 业务约束

| 约束 | 实现方式 |
|------|----------|
| 只能卖给学员 | `search_students_for_sale` 只查 `students` 表；`create_teaching_aid_sale` 写前校验 student_id 存在于 students |
| 只能卖上架商品 | `list_available_teaching_aids` 只返回 `status='上架'`；`create_teaching_aid_sale` 写前二次校验 |
| 不能卖给资源（非学员） | 学员搜索弹窗不查 `resources` 表 |
| 销售记录不可编辑/删除 | 不提供 edit/delete API |
| 数量 ≥ 1 | 前端步进器下限 + 后端校验 |
| 同商品不可重复勾选 | `buyCartItems[aid]` 唯一 key |

### 6.2 数据一致性

| 场景 | 处理 |
|------|------|
| 购买时商品被下架 | `create_teaching_aid_sale` 事务内二次校验 status，失败则整个事务回滚 |
| 购买时学员被删除 | 校验 student_id 不存在则拒单 |
| 购买后画具被删除 | 外键 RESTRICT，有销售记录则不可删除画具 |
| 购买后画具调价 | 不影响已销售记录（unit_price 是快照） |
| 购买后校区变更 | 不影响已销售记录（campus 是快照） |

### 6.3 前端边界

| 场景 | 处理 |
|------|------|
| 未选学员就勾商品 | 购物车栏不显示，「确认购买」按钮禁用 |
| 选了学员但未勾商品 | 购物车栏不显示 |
| 重复点击确认购买 | `showCustomConfirm` 后回调中重置购物车，二次点击无效 |
| 搜索学员无结果 | 显示「未找到匹配学员」空状态 |
| 无上架商品 | 卡片区显示「暂无可购买的商品」空状态 |
| 销售记录为空 | 表格显示「暂无销售记录」空状态 |

---

## 7. 实现步骤

按照 TMS 项目 7 步专家流程，分阶段实施：

### 7.1 Phase 1: 数据库迁移

| # | 步骤 | 位置 | 验证 |
|---|------|------|------|
| 1 | 在 `index.php` 建表区新增 `teaching_aid_sales` DDL | ~683 行之后 | 刷新页面，检查 MySQL 表已创建 |

**代码**（兼容块模式）:
```php
// 画具销售记录表
$db->exec("CREATE TABLE IF NOT EXISTS teaching_aid_sales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teaching_aid_id INT NOT NULL,
    student_id INT NOT NULL,
    student_name VARCHAR(200) NOT NULL DEFAULT '',
    teaching_aid_name VARCHAR(200) NOT NULL DEFAULT '',
    type VARCHAR(50) NOT NULL DEFAULT '画具',
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    campus VARCHAR(500) NOT NULL DEFAULT '',
    sold_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    remark VARCHAR(500) NOT NULL DEFAULT '',
    INDEX idx_sales_aid (teaching_aid_id),
    INDEX idx_sales_student (student_id),
    INDEX idx_sales_sold_at (sold_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 外键（分步添加，避免表已存在时报错）
try { $db->exec("ALTER TABLE teaching_aid_sales ADD FOREIGN KEY (teaching_aid_id) REFERENCES teaching_aids(id) ON DELETE RESTRICT"); } catch (PDOException $e) {}
try { $db->exec("ALTER TABLE teaching_aid_sales ADD FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT"); } catch (PDOException $e) {}
```

### 7.2 Phase 2: 后端 API

| # | 步骤 | 位置 | 验证 |
|---|------|------|------|
| 2 | 实现 `list_available_teaching_aids` case | `index.php` switch-case | `curl` 测试返回上架商品列表 |
| 3 | 实现 `search_students_for_sale` case | `index.php` switch-case | `curl` 测试搜索学员 |
| 4 | 实现 `create_teaching_aid_sale` case | `index.php` switch-case | `curl` 测试创建销售记录 |
| 5 | 实现 `list_teaching_aid_sales` case | `index.php` switch-case | `curl` 测试列表+筛选 |

### 7.3 Phase 3: 前端 HTML（面板结构）

| # | 步骤 | 位置 | 验证 |
|---|------|------|------|
| 6 | 修改 `section-tabs`，新增两个 tab 标签 | `index.php` ~8093 行 | 页面显示 3 个 tab |
| 7 | 新增 `tab-buy-aids` 面板 HTML | `index.php` ~8141 行之后 | DOM 存在 |
| 8 | 新增 `tab-sales-records` 面板 HTML | `index.php` ~8141 行之后 | DOM 存在 |
| 9 | 新增 `modal-buy-student-picker` 弹窗 HTML | `index.php` ~9065 行之后 | DOM 存在 |

### 7.4 Phase 4: 前端 JS（交互逻辑）

| # | 步骤 | 位置 | 验证 |
|---|------|------|------|
| 10 | 实现 `initTeachingAidTabs()` tab 切换 | `main.js` | 点击 tab 切换面板 |
| 11 | 实现 `loadAvailableAids()` + `renderBuyAidCard()` | `main.js` | 购买 tab 显示卡片 |
| 12 | 实现学员搜索弹窗逻辑（open/select/search） | `main.js` | 弹窗搜索+选择学员 |
| 13 | 实现购物车逻辑（toggle/qty/cart bar） | `main.js` | 勾选商品，底部栏更新 |
| 14 | 实现 `confirmBuyAids()` 确认购买 | `main.js` | 确认→API→成功切换 tab |
| 15 | 实现 `loadSalesRecords()` + `renderSalesTable()` | `main.js` | 销售记录表格+筛选+分页 |

### 7.5 Phase 5: CSS（卡片样式）

| # | 步骤 | 位置 | 验证 |
|---|------|------|------|
| 16 | 新增 `.buy-aid-card` 卡片样式 | `style.css` | 卡片网格布局、选中态、hover 效果 |
| 17 | 新增 `.buy-aids-grid` 网格布局 | `style.css` | 响应式网格 |
| 18 | 新增 `#buy-cart-bar` 购物车栏样式 | `style.css` | 粘性底栏样式 |

### 7.6 Phase 6: 测试验证

| # | 测试用例 | 验证方法 |
|---|----------|----------|
| 19 | 搜索学员（姓名/手机号/学号） | 浏览器 UI 测试 |
| 20 | 选择学员 → 显示在顶部 | 浏览器 UI 测试 |
| 21 | 勾选商品 → 底部栏显示 | 浏览器 UI 测试 |
| 22 | 调整数量 → 合计金额正确 | 浏览器 UI 测试 |
| 23 | 确认购买 → 销售记录生成 | 浏览器 + DB 查询 |
| 24 | 销售记录筛选（学员/商品/时间） | 浏览器 UI 测试 |
| 25 | 购买后自动切换到销售记录 tab | 浏览器 UI 测试 |
| 26 | 下架商品不出现在购买列表 | 浏览器 UI 测试 |
| 27 | 未选学员 → 确认按钮禁用 | 浏览器 UI 测试 |
| 28 | 取消学员 → 购物车清空 | 浏览器 UI 测试 |

---

## 8. 验证清单

### 8.1 API 验证（curl）

```bash
# 1. 可购买商品列表
curl -s "http://127.0.0.1:5001/?action=list_available_teaching_aids" | python -c "import sys,json; d=json.loads(sys.stdin.buffer.read().decode('utf-8-sig')); print(f'Total: {d[\"total\"]}, First: {d[\"data\"][0][\"name\"] if d[\"data\"] else \"none\"}')"

# 2. 搜索学员
curl -s "http://127.0.0.1:5001/?action=search_students_for_sale&keyword=张" | python -c "import sys,json; d=json.loads(sys.stdin.buffer.read().decode('utf-8-sig')); print(f'Found: {len(d[\"data\"])}')"

# 3. 创建销售记录（需有真实 teaching_aid_id + student_id）
echo '{"student_id":1,"items":[{"teaching_aid_id":1,"quantity":2}]}' > /tmp/buy.json
curl -s -X POST "http://127.0.0.1:5001/?action=create_teaching_aid_sale" -H "Content-Type: application/json" -d @/tmp/buy.json

# 4. 销售记录列表
curl -s "http://127.0.0.1:5001/?action=list_teaching_aid_sales" | python -c "import sys,json; d=json.loads(sys.stdin.buffer.read().decode('utf-8-sig')); print(f'Total: {d[\"total\"]}')"

# 5. 销售记录筛选
curl -s "http://127.0.0.1:5001/?action=list_teaching_aid_sales&date_from=2026-07-01&date_to=2026-07-31"
```

### 8.2 前端验证（浏览器）

1. 打开 http://127.0.0.1:5001 → 左侧导航「画具管理」
2. 确认 3 个 tab 可见：画具列表 | 购买画具 | 销售记录
3. 切换到「购买画具」tab → 显示上架商品卡片
4. 点击「选择学员」→ 弹窗搜索学员 → 选择学员
5. 勾选 2-3 个商品 → 调整数量 → 底部栏显示正确合计
6. 点击「确认购买」→ 二次确认 → 成功后自动切换到销售记录 tab
7. 在销售记录 tab 验证新记录出现
8. 测试筛选功能（学员姓名/商品名称/日期范围）

### 8.3 回归验证

- 画具列表 tab 功能不受影响（CRUD 正常）
- 现有画具管理 API 不受影响
- 删除有销售记录的画具时应被外键 RESTRICT 拦截（MySQL 报错，PHP 捕获并返回友好提示）

---

## 附录 A: 关键代码位置索引

| 组件 | 文件 | 行号（约） | 说明 |
|------|------|-----------|------|
| `panel-teaching-aids` HTML | `index.php` | 8090-8141 | 画具管理面板 |
| `section-tabs` | `index.php` | 8092-8094 | 现有 tab 栏（1 个 tab） |
| `teaching_aids` DDL | `index.php` | 659-669 | 画具主表建表 |
| `teaching_aid_campuses` DDL | `index.php` | 675-682 | 校区关联表建表 |
| `list_teaching_aids` API | `index.php` | 3681-3712 | 画具列表 API |
| `get_teaching_aid` API | `index.php` | 3665-3679 | 画具详情 API |
| `loadTeachingAids()` | `main.js` | 11635 | 画具列表加载 |
| `renderTeachingAidTable()` | `main.js` | 11644 | 画具表格渲染 |
| `initCouponTabs()` | `main.js` | 10859-10876 | Tab 切换参考实现 |
| `searchAvailableStudents()` | `main.js` | 7580-7606 | 学员搜索参考实现 |
| `onTempStudentSearch()` | `main.js` | 7924-7928 | 学员搜索 debounce |
| `students` 表结构 | `PROJECT_SUMMARY.md` | 292-304 | 学员表字段 |

## 附录 B: 风险与注意事项

| 风险 | 缓解措施 |
|------|----------|
| 购买时并发下架 | 事务内写前二次校验 `status='上架'` |
| `showCustomConfirm` 会转义 HTML | 确认文案用纯文本，商品列表用顿号分隔 |
| JS 全局命名冲突 | 新函数加 `buy`/`sales` 前缀，用 `grep -c "function 函数名"` 检查 |
| `patch` 工具对大文件静默失败 | `index.php` 编辑用 `sed -i` 或 Python 脚本 |
| PHP 内置服务器 curl POST JSON 问题 | 测试时用 `-d @file.json` 方式传参 |
| 外键 RESTRICT 错误提示不友好 | PHP 捕获 PDOException，返回中文提示 |
