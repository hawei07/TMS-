# TMS 画具管理模块 PRD

> **版本**: v1.0  
> **日期**: 2026-07-08  
> **作者**: Product Manager (Hermes Agent)  
> **项目路径**: `D:\market-system-php\`  
> **技术栈**: PHP 8.4 + MySQL 8.4 (PDO) + Vanilla JS + CSS3

---

## 1. 需求概述

在 TMS 教务管理模块中新增「画具管理」子页面，支持画具的增删改查。画具是与学科绑定的教学用具，按校区配置可用范围，有售价和上架/下架状态。

### 功能清单

| 功能 | 描述 |
|------|------|
| 画具列表 | 表格展示所有画具，支持关键词搜索 |
| 新增画具 | 弹窗表单，包含名称、单位、学科、售价、校区、状态、备注 |
| 编辑画具 | 弹窗回填已有数据，允许修改全部字段 |
| 删除画具 | 二次确认后删除（CASCADE 清理关联表） |

---

## 2. 数据库设计

### 2.1 主表 `teaching_aids`

```sql
CREATE TABLE IF NOT EXISTS teaching_aids (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL DEFAULT '',
    unit VARCHAR(20) NOT NULL DEFAULT '个',
    subject_id INT NOT NULL DEFAULT 0,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(10) NOT NULL DEFAULT '上架',
    remark TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**字段说明**:

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | INT PK | 自动 | 主键 |
| name | VARCHAR(200) | ✅ | 画具名称 |
| unit | VARCHAR(20) | ✅ | 计量单位（个/件/套/支/盒/包/本） |
| subject_id | INT | ✅ | 关联 subjects 表的一级学科 ID（parent_id=0） |
| price | DECIMAL(10,2) | ✅ | 售价（元） |
| status | VARCHAR(10) | ✅ | 上架 / 下架（默认「上架」） |
| remark | TEXT | 否 | 备注 |
| created_at | DATETIME | 自动 | 创建时间 |
| updated_at | DATETIME | 自动 | 更新时间 |

### 2.2 关联表 `teaching_aid_campuses`

```sql
CREATE TABLE IF NOT EXISTS teaching_aid_campuses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teaching_aid_id INT NOT NULL,
    campus_id INT NOT NULL,
    INDEX idx_tac_aid (teaching_aid_id),
    INDEX idx_tac_campus (campus_id),
    FOREIGN KEY (teaching_aid_id) REFERENCES teaching_aids(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**设计说明**：
- 参照 `discount_plan_campuses` 模式（关联表 + CASCADE 外键）
- 不使用逗号分隔字符串，便于 JOIN 查询和按校区筛选
- `ON DELETE CASCADE`：删除画具时自动清理关联的校区记录

### 2.3 建表位置

在 `index.php` 建表区（约第 610 行，`coupon_records` 之后）追加以上两段 DDL。

---

## 3. API 设计

### 3.1 API 清单

| Action | Method | 功能 | 请求参数 | 返回格式 |
|--------|--------|------|----------|----------|
| `list_teaching_aids` | GET | 分页列表 | keyword, page, page_size | `{data, total, page, page_size}` |
| `get_teaching_aid` | GET | 查询单条 | id | `{data: {id, name, unit, subject_id, price, status, remark, campus_ids: [...]}}` |
| `add_teaching_aid` | POST | 新增 | name, unit, subject_id, price, status, remark, campus_ids | `{id, message}` |
| `update_teaching_aid` | POST | 编辑 | id, name, unit, subject_id, price, status, remark, campus_ids | `{message}` |
| `delete_teaching_aid` | POST | 删除 | id | `{message}` |

### 3.2 接口详细规格

#### 3.2.1 `list_teaching_aids` — 分页列表

```
GET /?action=list_teaching_aids&keyword=xxx&page=1&page_size=20
```

**请求参数**:

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| keyword | string | 否 | — | 模糊匹配 name |
| page | int | 否 | 1 | 页码 |
| page_size | int | 否 | 20 | 每页条数 |

**返回 JSON**:

```json
{
    "data": [
        {
            "id": 1,
            "name": "素描铅笔套装",
            "unit": "套",
            "subject_id": 5,
            "subject_name": "美术",
            "price": 128.00,
            "status": "上架",
            "remark": "包含2B-6B铅笔12支",
            "campus_ids": "7,14",
            "campus_names": "曲江校区, 高新校区",
            "created_at": "2026-07-01 10:00:00"
        }
    ],
    "total": 1,
    "page": 1,
    "page_size": 20
}
```

**SQL 实现要点**:
```sql
SELECT ta.*,
    s.name AS subject_name,
    (SELECT GROUP_CONCAT(DISTINCT tac2.campus_id ORDER BY tac2.campus_id SEPARATOR ',') 
     FROM teaching_aid_campuses tac2 WHERE tac2.teaching_aid_id = ta.id) AS campus_ids,
    (SELECT GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ') 
     FROM teaching_aid_campuses tac2 LEFT JOIN organizations o ON tac2.campus_id = o.id 
     WHERE tac2.teaching_aid_id = ta.id) AS campus_names
FROM teaching_aids ta
LEFT JOIN subjects s ON ta.subject_id = s.id
WHERE (keyword 为空 OR ta.name LIKE '%keyword%')
ORDER BY ta.id DESC
LIMIT offset, page_size
```

#### 3.2.2 `get_teaching_aid` — 查询单条

```
GET /?action=get_teaching_aid&id=1
```

**返回 JSON**:

```json
{
    "data": {
        "id": 1,
        "name": "素描铅笔套装",
        "unit": "套",
        "subject_id": 5,
        "subject_name": "美术",
        "price": 128.00,
        "status": "上架",
        "remark": "包含2B-6B铅笔12支",
        "campus_ids": [7, 14]
    }
}
```

> ⚠ `campus_ids` 返回整数数组，供前端 `campusCheckboxData` 回填选中状态。

#### 3.2.3 `add_teaching_aid` — 新增

```
POST /?action=add_teaching_aid
Content-Type: application/json

{
    "name": "素描铅笔套装",
    "unit": "套",
    "subject_id": 5,
    "price": 128.00,
    "status": "上架",
    "remark": "包含2B-6B铅笔12支",
    "campus_ids": [7, 14]
}
```

**校验规则**:
1. `name` 不能为空
2. `unit` 不能为空
3. `subject_id` 必须 > 0
4. `price` 必须 >= 0

**实现**: 事务包裹
```php
$db->beginTransaction();
try {
    // 1. INSERT teaching_aids
    $stmt = $db->prepare("INSERT INTO teaching_aids (name, unit, subject_id, price, status, remark, created_at) 
                          VALUES (:n, :u, :sid, :p, :st, :rm, :ct)");
    $stmt->execute([...]);
    $aid = $db->lastInsertId();

    // 2. INSERT teaching_aid_campuses（批量）
    if (!empty($campusIds)) {
        $vals = [];
        foreach ($campusIds as $cid) {
            if (intval($cid) > 0) $vals[] = "($aid, $cid)";
        }
        if (!empty($vals)) {
            $db->exec("INSERT INTO teaching_aid_campuses (teaching_aid_id, campus_id) VALUES " . implode(', ', $vals));
        }
    }

    $db->commit();
    json(['id' => $aid, 'message' => '画具添加成功']);
} catch (Exception $e) {
    $db->rollBack();
    json(['error' => '添加失败：' . $e->getMessage()]);
}
```

#### 3.2.4 `update_teaching_aid` — 编辑

```
POST /?action=update_teaching_aid
Content-Type: application/json

{
    "id": 1,
    "name": "素描铅笔套装",
    "unit": "套",
    "subject_id": 5,
    "price": 128.00,
    "status": "上架",
    "remark": "包含2B-6B铅笔12支",
    "campus_ids": [7, 14]
}
```

**实现**: 事务包裹 — UPDATE 主表 → DELETE 旧关联 → INSERT 新关联

```php
$db->beginTransaction();
try {
    // 1. UPDATE teaching_aids
    $db->exec("UPDATE teaching_aids SET name=..., unit=..., subject_id=..., price=..., status=..., remark=..., updated_at=NOW() WHERE id=$id");

    // 2. DELETE old campuses
    $db->exec("DELETE FROM teaching_aid_campuses WHERE teaching_aid_id=$id");

    // 3. INSERT new campuses
    if (!empty($campusIds)) { ... }

    $db->commit();
    json(['message' => '画具更新成功']);
} catch (Exception $e) {
    $db->rollBack();
    json(['error' => '更新失败']);
}
```

#### 3.2.5 `delete_teaching_aid` — 删除

```
POST /?action=delete_teaching_aid
Content-Type: application/json

{"id": 1}
```

**实现**: 直接 DELETE（CASCADE 自动清理关联表）

```php
$id = intval($input['id'] ?? 0);
if ($id <= 0) json(['error' => 'ID无效']);
$exists = $db->query("SELECT id FROM teaching_aids WHERE id=$id")->fetch();
if (!$exists) json(['error' => '画具不存在']);
$db->exec("DELETE FROM teaching_aids WHERE id=$id");
json(['message' => '画具已删除']);
```

### 3.3 API case 插入位置

在 `index.php` 的 switch-case 块中按字母顺序插入——`delete_teaching_aid` 在 `delete_schedule` 之后，`get_teaching_aid` 在 `get_trial_sessions` 之后，以此类推。建议插入到 `// ==================== 画具管理 API ====================` 注释区块。

---

## 4. 前端设计

### 4.1 导航位置

**位置**: 教务管理 → tree-children 内，建议放在「优惠管理」之后、「基础设置」之前。

```html
<!-- 画具管理（导航叶子节点） -->
<li class="tree-node">
    <div class="tree-leaf" data-panel="panel-teaching-aids">
        <span class="tree-icon-sub">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                <path d="M2 17l10 5 10-5"/>
                <path d="M2 12l10 5 10-5"/>
            </svg>
        </span>
        <span class="tree-label">画具管理</span>
    </div>
</li>
```

**SVG Icon**: 三层图层叠图标（layers），寓意画具/教具物资。

**导航层级**:

```
教务管理
├── 课程管理
├── 学员管理
├── 考勤
├── (班级管理 — 隐藏)
├── 交易订单
├── 工作记录
├── 优惠管理
├── 画具管理          ← 新增，独立叶子节点
├── 基础设置
│   ├── 学科设置
│   ├── 教室管理
│   └── 上课时段设置
```

> **设计决策**: 画具作为独立叶子节点而非放在「基础设置」子节点里——因为画具有独立的 CRUD 面板、关联表、售价字段，功能体量大于「学科设置」这类纯字典项，适合独立面板。

### 4.2 面板 HTML 结构

```html
<!-- 面板：画具管理 -->
<section class="content-panel" id="panel-teaching-aids">
    <div class="panel-header"><h3>画具管理</h3></div>
    <div class="section-tabs">
        <span class="sec-tab active" data-tab="tab-teaching-aids">画具列表</span>
    </div>
    <div id="tab-teaching-aids">
        <!-- 工具栏 -->
        <div class="toolbar">
            <div class="toolbar-left">
                <button class="btn btn-primary" onclick="showTeachingAidForm()">+ 新增画具</button>
                <span style="color:#888;font-size:13px;margin-left:12px;" id="ta-total-count"></span>
            </div>
            <div class="toolbar-right">
                <input type="text" id="ta-search" class="form-input" 
                       placeholder="搜索画具名称" 
                       onkeyup="if(event.key==='Enter'){teachingAidPage=1;loadTeachingAids();}"
                       style="width:220px;">
                <button class="btn btn-search" onclick="teachingAidPage=1;loadTeachingAids();">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    搜索
                </button>
            </div>
        </div>

        <!-- 表格 -->
        <div class="table-wrap">
            <table id="table-teaching-aids">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>画具名称</th>
                        <th style="width:80px;">计量单位</th>
                        <th>学科</th>
                        <th style="width:100px;text-align:right;">售价</th>
                        <th>适用校区</th>
                        <th style="width:80px;">状态</th>
                        <th>备注</th>
                        <th style="width:120px;">操作</th>
                    </tr>
                </thead>
                <tbody id="ta-tbody">
                    <tr><td colspan="9"><div class="empty-state">暂无画具数据</div></td></tr>
                </tbody>
            </table>
        </div>

        <!-- 分页 -->
        <div class="pagination" id="pagination-teaching-aids"></div>
    </div>
</section>
```

**列说明** (colspan=9):

| # | 列名 | 宽度 | 说明 |
|---|------|------|------|
| 1 | # | 50px | 序号 |
| 2 | 画具名称 | auto | `esc(row.name)` |
| 3 | 计量单位 | 80px | 个/件/套等 |
| 4 | 学科 | auto | 一级学科名称（JOIN subjects） |
| 5 | 售价 | 100px | `text-align:right; color:#DC2626; font-weight:600` |
| 6 | 适用校区 | auto | 逗号分隔校区名称，超过3个显示"等N个校区" |
| 7 | 状态 | 80px | 上架 🔵 / 下架 ⚪ 标签 |
| 8 | 备注 | auto | 截断显示，hover 展示全文 |
| 9 | 操作 | 120px | 编辑 / 删除 |

### 4.3 弹窗 HTML 结构

```html
<!-- 画具弹窗 -->
<div class="modal-overlay" id="modal-teaching-aid">
    <div class="modal modal-lg" style="max-width:680px;">
        <div class="modal-header">
            <h4 id="teaching-aid-modal-title">新增画具</h4>
            <button class="modal-close" onclick="closeModal('modal-teaching-aid')">&times;</button>
        </div>
        <div class="modal-body">
            <!-- ====== 卡片 1: 基本信息 ====== -->
            <div class="dp-card">
                <div class="dp-card-title">
                    <span class="dp-card-icon">📋</span> 基本信息
                </div>
                <div class="dp-card-body">
                    <div class="form-row">
                        <div class="form-group" style="flex:2;">
                            <label class="required">画具名称</label>
                            <input type="text" id="ta-name" class="form-input" 
                                   placeholder="请输入画具名称" maxlength="50">
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label class="required">计量单位</label>
                            <select id="ta-unit" class="form-input">
                                <option value="个">个</option>
                                <option value="件">件</option>
                                <option value="套">套</option>
                                <option value="支">支</option>
                                <option value="盒">盒</option>
                                <option value="包">包</option>
                                <option value="本">本</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="flex:1;">
                            <label class="required">学科</label>
                            <select id="ta-subject" class="form-input">
                                <option value="">请选择学科</option>
                                <!-- JS 动态填充：SELECT id, name FROM subjects WHERE parent_id=0 -->
                            </select>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label class="required">售价 (元)</label>
                            <input type="number" id="ta-price" class="form-input" 
                                   step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ====== 卡片 2: 适用范围 ====== -->
            <div class="dp-card">
                <div class="dp-card-title">
                    <span class="dp-card-icon">🏫</span> 适用范围
                </div>
                <div class="dp-card-body">
                    <div class="form-group">
                        <div class="dp-tree-header">
                            <label class="required">适用校区</label>
                            <span class="dp-badge" id="ta-campus-count">未选择</span>
                        </div>
                        <div class="dp-tree-wrap" id="teaching-aid-campus-tree"></div>
                    </div>
                </div>
            </div>

            <!-- ====== 卡片 3: 其他信息 ====== -->
            <div class="dp-card">
                <div class="dp-card-title">
                    <span class="dp-card-icon">⚙️</span> 其他信息
                </div>
                <div class="dp-card-body">
                    <div class="form-group">
                        <label>状态</label>
                        <div class="radio-group" style="display:flex;gap:24px;padding-top:6px;">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="radio" name="ta-status" value="上架" checked> 上架
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="radio" name="ta-status" value="下架"> 下架
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>备注</label>
                        <textarea id="ta-remark" class="form-input" rows="3" 
                                  placeholder="选填，补充说明信息" maxlength="500"></textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-default" onclick="closeModal('modal-teaching-aid')">取消</button>
            <button class="btn btn-primary" id="btn-ta-save" onclick="saveTeachingAid()">保存</button>
        </div>
    </div>
    <!-- 隐藏域：编辑时的画具 ID -->
    <input type="hidden" id="edit-ta-id">
</div>
```

> ⚠ **注意**: 隐藏域 `#edit-ta-id` 放在 `modal-overlay` 下而非 `modal-body` 下，避免 CSS nth-child 偏移（参见 TMS 陷阱 #CSS nth-child）。

### 4.4 JS 函数清单

所有以下函数添加到 `static/js/main.js` 末尾，放在 `// ==================== 画具管理 ====================` 注释区块下。

| 函数 | 类型 | 功能 |
|------|------|------|
| `loadTeachingAids(page?)` | async | 调用 `list_teaching_aids` API，渲染表格+分页 |
| `renderTeachingAidTable(rows)` | sync | 渲染 tbody 行 |
| `showTeachingAidForm(id?)` | async | 打开弹窗，无 id=新增，有 id=编辑 |
| `saveTeachingAid()` | async | 表单校验 → POST API → 关闭弹窗→刷新列表 |
| `deleteTeachingAid(id, name)` | sync | showCustomConfirm → POST API → 刷新列表 |
| `getTaSelectedCampuses()` | sync | 收集校区树选中 ID |
| `updateTaCampusCount()` | sync | 更新校区选择计数徽章 |
| `ensureTaCampusTreeListener()` | sync | 注册校区树 checkbox change 事件 |

**全局状态变量**:
```javascript
let teachingAidPage = 1;
let teachingAidEditingId = null;
```

**关键函数签名与逻辑**:

```javascript
// ===== 列表加载 =====
async function loadTeachingAids(page = 1) {
    teachingAidPage = page;
    const keyword = document.getElementById('ta-search')?.value || '';
    const data = await api('list_teaching_aids', { keyword, page, page_size: 20 }, 'GET');
    renderTeachingAidTable(data.data);
    renderPagination('pagination-teaching-aids', data.total, page, 20, 'loadTeachingAids');
    document.getElementById('ta-total-count').textContent = `共 ${data.total} 条`;
}

function renderTeachingAidTable(rows) {
    const tbody = document.getElementById('ta-tbody');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state">暂无画具数据</div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map((r, i) => {
        const campusNames = (r.campus_names || '').split(', ').filter(Boolean);
        const campusDisplay = campusNames.length > 3 
            ? campusNames.slice(0, 3).join(', ') + ` 等${campusNames.length}个校区`
            : (r.campus_names || '—');
        const statusClass = r.status === '上架' ? 'tag-green' : 'tag-gray';
        return `
        <tr>
            <td>${(teachingAidPage - 1) * 20 + i + 1}</td>
            <td><strong>${esc(r.name)}</strong></td>
            <td>${esc(r.unit)}</td>
            <td>${esc(r.subject_name || '—')}</td>
            <td style="text-align:right;color:#DC2626;font-weight:600;">¥${Number(r.price).toFixed(2)}</td>
            <td title="${esc(r.campus_names || '')}">${esc(campusDisplay)}</td>
            <td><span class="${statusClass}">${r.status}</span></td>
            <td title="${esc(r.remark || '')}">${esc((r.remark || '').substring(0, 20))}${(r.remark || '').length > 20 ? '...' : ''}</td>
            <td>
                <button class="btn btn-sm btn-outline" onclick="showTeachingAidForm(${r.id})">编辑</button>
                <button class="btn btn-sm btn-outline btn-danger" onclick="deleteTeachingAid(${r.id}, '${esc(r.name)}')">删除</button>
            </td>
        </tr>`;
    }).join('');
}

// ===== 弹窗 =====
async function showTeachingAidForm(id) {
    // 可用 via inline API 或 wrapper
    document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show'));
    
    document.getElementById('edit-ta-id').value = '';
    document.getElementById('ta-name').value = '';
    document.getElementById('ta-unit').value = '个';
    document.getElementById('ta-price').value = '';
    document.getElementById('ta-remark').value = '';
    document.querySelector('input[name="ta-status"][value="上架"]').checked = true;
    
    // 加载学科下拉（一级学科）
    await loadTeachingAidSubjects();
    
    // 加载校区树
    await loadCampusTree('teaching-aid-campus-tree');
    ensureTaCampusTreeListener();
    
    if (id) {
        // 编辑模式
        document.getElementById('teaching-aid-modal-title').textContent = '编辑画具';
        document.getElementById('edit-ta-id').value = id;
        const data = await api('get_teaching_aid', { id }, 'GET');
        const r = data.data;
        document.getElementById('ta-name').value = r.name;
        document.getElementById('ta-unit').value = r.unit;
        document.getElementById('ta-subject').value = r.subject_id;
        document.getElementById('ta-price').value = r.price;
        document.querySelector(`input[name="ta-status"][value="${r.status}"]`).checked = true;
        document.getElementById('ta-remark').value = r.remark || '';
        
        // 回填校区树选中
        if (r.campus_ids && r.campus_ids.length > 0) {
            setTimeout(() => {
                r.campus_ids.forEach(cid => {
                    const cb = document.querySelector(`#teaching-aid-campus-tree input[type="checkbox"][value="${cid}"]`);
                    if (cb) { cb.checked = true; cb.dispatchEvent(new Event('change', {bubbles: true})); }
                });
                updateTaCampusCount();
            }, 300); // 延迟确保树渲染完成
        }
    } else {
        document.getElementById('teaching-aid-modal-title').textContent = '新增画具';
        updateTaCampusCount();
    }
    
    openModal('modal-teaching-aid');
}

async function loadTeachingAidSubjects() {
    const select = document.getElementById('ta-subject');
    select.innerHTML = '<option value="">加载中...</option>';
    const result = await api('list_subjects', {}, 'GET');
    // 筛选 parent_id=0（一级学科）
    const level1 = result.flat.filter(s => parseInt(s.parent_id) === 0);
    select.innerHTML = '<option value="">请选择学科</option>' + 
        level1.map(s => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
}

async function saveTeachingAid() {
    const id = document.getElementById('edit-ta-id').value;
    const isEdit = !!id;
    const name = document.getElementById('ta-name').value.trim();
    const unit = document.getElementById('ta-unit').value.trim();
    const subjectId = parseInt(document.getElementById('ta-subject').value) || 0;
    const price = parseFloat(document.getElementById('ta-price').value);
    const status = document.querySelector('input[name="ta-status"]:checked')?.value || '上架';
    const remark = document.getElementById('ta-remark').value.trim();
    const campusIds = getTaSelectedCampuses();
    
    // 校验
    if (!name) { showToast('请输入画具名称', 'error'); return; }
    if (!unit) { showToast('请选择计量单位', 'error'); return; }
    if (subjectId <= 0) { showToast('请选择学科', 'error'); return; }
    if (isNaN(price) || price < 0) { showToast('请输入有效售价', 'error'); return; }
    if (campusIds.length === 0) { showToast('请选择适用校区', 'error'); return; }
    
    const action = isEdit ? 'update_teaching_aid' : 'add_teaching_aid';
    const body = { name, unit, subject_id: subjectId, price, status, remark, campus_ids: campusIds };
    if (isEdit) body.id = parseInt(id);
    
    const result = await api(action, body, 'POST');
    if (result.error) { showToast(result.error, 'error'); return; }
    
    showToast(isEdit ? '画具更新成功' : '画具添加成功', 'success');
    closeModal('modal-teaching-aid');
    loadTeachingAids(teachingAidPage);
}

function deleteTeachingAid(id, name) {
    showCustomConfirm(`确定删除画具「${name}」吗？此操作不可恢复。`, async () => {
        const result = await api('delete_teaching_aid', { id }, 'POST');
        if (result.error) { showToast(result.error, 'error'); return; }
        showToast('画具已删除', 'success');
        loadTeachingAids(teachingAidPage);
    });
}

// ===== 校区树辅助 =====
function getTaSelectedCampuses() {
    const checks = document.querySelectorAll('#teaching-aid-campus-tree input[type="checkbox"]:checked');
    return Array.from(checks).map(cb => parseInt(cb.value)).filter(v => v > 0);
}

function updateTaCampusCount() {
    const count = getTaSelectedCampuses().length;
    const badge = document.getElementById('ta-campus-count');
    if (badge) badge.textContent = count > 0 ? `已选 ${count} 个校区` : '未选择';
}

function ensureTaCampusTreeListener() {
    const tree = document.getElementById('teaching-aid-campus-tree');
    if (!tree) return;
    // 移除旧监听器，重新绑定（避免重复）
    tree.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        cb.removeEventListener('change', updateTaCampusCount);
        cb.addEventListener('change', updateTaCampusCount);
    });
    // 使用 MutationObserver 监听新节点（校区树异步加载）
    const observer = new MutationObserver(() => {
        tree.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            if (!cb._taBound) { 
                cb.addEventListener('change', updateTaCampusCount);
                cb._taBound = true;
            }
        });
    });
    observer.observe(tree, { childList: true, subtree: true });
}
```

### 4.5 JS 注册点

#### `refreshPanel` 增加 case：

```javascript
case 'panel-teaching-aids':
    loadTeachingAids();
    break;
```

#### `debounceSearch` 不需要注册（搜索直接用 `onkeyup` + Enter 触发，无需防抖）。

但如果后续需要防抖，增加：
```javascript
else if (tab === 'teaching-aids') { teachingAidPage = 1; loadTeachingAids(); }
```

### 4.6 CSS 新增

在 `static/css/style.css` 末尾追加画具面板相关样式（约 50-80 行）。如果需要卡片布局复用，直接复用已有的 `.dp-card`、`.dp-card-title`、`.dp-card-body`、`.form-row`、`.form-group` 等 class——这些已在 `#modal-discount-plan` 的 CSS 区块中定义（style.css ~6002 行起），对 `#modal-teaching-aid` 自动生效因为是通用 class。

若有面板特有样式（如 `.tag-gray` 状态标签），追加以下：

```css
/* ==================== 画具管理面板 ==================== */

/* 表格 hover 效果 */
#table-teaching-aids tbody tr:hover {
    background: var(--color-primary-bg);
}

/* 状态下架标签 */
.tag-gray {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 500;
    background: #F3F4F6;
    color: #6B7280;
}
```

---

## 5. 校区树状多选实现方案

### 5.1 方案选型

**采用**: 复用现有 `loadCampusTree(containerId)` 函数（`main.js` ~2606 行）。

该函数本身支持任意 `containerId`，渲染 `organizations` 表中 `type='校区'` 的节点，以 checkbox 树形式展示。接受 `isSingle` 参数（通过 `containerId === 'class-campus-tree'` 判断）控制单选/多选模式。

### 5.2 实现步骤

1. **弹窗 HTML**: 在 `.dp-tree-wrap` 容器中放置 `<div id="teaching-aid-campus-tree"></div>`
2. **弹窗打开时**: `await loadCampusTree('teaching-aid-campus-tree')`
3. **事件绑定**: `ensureTaCampusTreeListener()` — 注册 checkbox change 事件更新计数徽章
4. **收集选中值**: `getTaSelectedCampuses()` — `querySelectorAll('#teaching-aid-campus-tree input[type="checkbox"]:checked')`
5. **编辑回填**: 在 `showTeachingAidForm(id)` 中，API 返回 `campus_ids` 数组后，`setTimeout` 300ms 确保树渲染完成，再逐 checkbox 设 `checked = true` 并派发 change 事件

### 5.3 与 `discount_plan_campuses` 对比

| 对比项 | discount_plan | teaching_aid | 说明 |
|--------|--------------|-------------|------|
| 校区树容器 | `#discount-campus-tree` | `#teaching-aid-campus-tree` | 不同容器 ID 隔离 |
| 校区收集函数 | `getSelectedDiscountCampuses()` | `getTaSelectedCampuses()` | 独立函数，改选择器 |
| 计数徽章 | `#dp-campus-count` | `#ta-campus-count` | 独立 badge |
| 事件监听器 | `ensureDiscountCampusTreeListener()` | `ensureTaCampusTreeListener()` | MutationObserver 模式相同 |
| 树渲染函数 | `loadCampusTree(...)` | `loadCampusTree(...)` | 直接复用 |

---

## 6. 实施步骤优先级

| 阶段 | 步骤 | 产出 | 预估工期 |
|------|------|------|----------|
| **Phase 1** | 数据库建表 | `index.php` 追加 DDL | 5 min |
| **Phase 2** | 后端 API | `index.php` 追加 5 个 case | 30 min |
| **Phase 3** | 导航栏 | `index.php` 追加 tree-leaf 节点 | 5 min |
| **Phase 4** | 面板 HTML | `index.php` 追加 panel + modal HTML | 15 min |
| **Phase 5** | 前端 JS | `main.js` 追加 8 个函数 | 30 min |
| **Phase 6** | CSS 样式 | `style.css` 追加面板样式 | 10 min |
| **Phase 7** | 端到端验证 | 语法检查 + API 测试 + 浏览器操作 | 15 min |

**预计总工期**: ~110 分钟（含调试验证）

### 6.1 Phase 1: 数据库

在 `index.php` 建表区（`coupon_records` 建表区之后，约第 614 行之后）追加：

```php
// ==================== 画具管理建表 ====================
$db->exec("CREATE TABLE IF NOT EXISTS teaching_aids (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL DEFAULT '',
    unit VARCHAR(20) NOT NULL DEFAULT '个',
    subject_id INT NOT NULL DEFAULT 0,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(10) NOT NULL DEFAULT '上架',
    remark TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->exec("CREATE TABLE IF NOT EXISTS teaching_aid_campuses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teaching_aid_id INT NOT NULL,
    campus_id INT NOT NULL,
    INDEX idx_tac_aid (teaching_aid_id),
    INDEX idx_tac_campus (campus_id),
    FOREIGN KEY (teaching_aid_id) REFERENCES teaching_aids(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
```

### 6.2 Phase 2: 后端 API

在 `index.php` switch-case 中，按字母顺序插入 5 个 case。建议在 `delete_schedule` 之后、`delete_subject` 之前（约 3000 行区域），创建 `// ==================== 画具管理 API ====================` 区块。

### 6.3 Phase 3: 导航栏

在 `index.php` 导航区（约 6358 行，`优惠管理` `</li>` 之后，`基础设置` `<li>` 之前）插入画具管理的 `tree-leaf` 节点。

### 6.4 Phase 4: 面板 + 弹窗 HTML

在 `index.php` 面板区（约 7483 行，`panel-discounts` 的 `</section>` 之后）插入 `panel-teaching-aids`。

在 `index.php` 模态区（约 9280 行，`modal-discount-plan` 的 `</div>` 之后）插入 `modal-teaching-aid`。

### 6.5 Phase 5: 前端 JS

在 `static/js/main.js` 末尾追加画具管理 JS 区块。

### 6.6 Phase 6: CSS

在 `static/css/style.css` 末尾追加画具面板样式。

### 6.7 Phase 7: 验证

```bash
# 语法检查
php -l D:/market-system-php/index.php

# JS 函数去重检查
grep -oP "function \w+" D:/market-system-php/static/js/main.js | sort | uniq -d

# API 列表测试（需先启动 PHP server）
curl "http://127.0.0.1:5001/?action=list_teaching_aids"
```

浏览器验证：
1. 点击导航「画具管理」→ 面板显示
2. 点击「新增画具」→ 表单填写 → 保存 → 列表刷新
3. 编辑已有画具 → 数据回填正确 → 修改保存
4. 删除画具 → 二次确认 → 列表刷新
5. 搜索框输入关键词 → 回车 → 列表过滤

---

## 7. 风险与注意事项

### 7.1 已知风险

| 风险 | 影响 | 缓解措施 |
|------|------|----------|
| JS 函数重复定义 | 后定义者覆盖前者，功能异常 | 实施前 `grep "function 函数名" main.js` 查重 |
| `loadCampusTree` 是 async，编辑回填需等树渲染完成 | 校区 checkbox 未选中 | 使用 `setTimeout(300ms)` 延迟回填 |
| `getTaSelectedCampuses` 需独立实现 | `getSelectedCampuses()` 硬编码了 `#course-campus-tree` | 新写独立函数，选择器改为 `#teaching-aid-campus-tree` |
| `openModal` 不关旧弹窗 | 画具弹窗被其他弹窗覆盖 | TMS 陷阱 #35 — `openModal` 已自动关闭所有弹窗 |
| PHP 内置服务器 `php://input` 对 curl POST 可能为空 | curl 测试新增接口失败 | 用浏览器操作验证 POST API（TMS 陷阱 #46） |

### 7.2 设计决策记录

1. **学科下拉用一级学科**: 用户需求明确「数据源从 subjects 表的一级学科」（`parent_id=0`），使用 `list_subjects` API 获取全部学科后前端过滤
2. **校区用关联表而非逗号分隔**: 遵循 TMS 最佳实践，新功能统一用关联表（TMS 陷阱 #44）
3. **画具放在教务管理独立叶子节点**: 功能体量 > 基础设置子项，后续可能扩展（如画具库存、画具领用记录），独立面板扩展性更好
4. **弹窗复用 dp-card 卡片布局**: 与优惠管理弹窗视觉一致，降低 CSS 维护成本

---

## 附录 A: 完整文件改动范围

| 文件 | 改动类型 | 行数估计 |
|------|----------|----------|
| `index.php` | 建表 DDL | +15 行 |
| `index.php` | API cases (5 个) | +120 行 |
| `index.php` | 导航 tree-leaf | +12 行 |
| `index.php` | 面板 HTML | +50 行 |
| `index.php` | 弹窗 HTML | +85 行 |
| `static/js/main.js` | JS 函数 (8 个) | +250 行 |
| `static/css/style.css` | 面板样式 | +50 行 |
| **合计** | | **~582 行** |

## 附录 B: API 契约（JSON Schema）

### `list_teaching_aids` 响应

```json
{
    "data": [
        {
            "id": "integer",
            "name": "string",
            "unit": "string (个|件|套|支|盒|包|本)",
            "subject_id": "integer",
            "subject_name": "string",
            "price": "number (DECIMAL)",
            "status": "string (上架|下架)",
            "remark": "string | null",
            "campus_ids": "string (逗号分隔的ID)",
            "campus_names": "string (逗号分隔的名称)",
            "created_at": "datetime"
        }
    ],
    "total": "integer",
    "page": "integer",
    "page_size": "integer"
}
```

### `get_teaching_aid` 响应

```json
{
    "data": {
        "id": "integer",
        "name": "string",
        "unit": "string",
        "subject_id": "integer",
        "subject_name": "string",
        "price": "number",
        "status": "string",
        "remark": "string | null",
        "campus_ids": ["integer[]"]
    }
}
```

> ⚠ `campus_ids` 在 `get` 中是整数数组，在 `list` 中是逗号分隔字符串——这是与 `discount_plans` 模块一致的模式。前端 `showTeachingAidForm` 读取 `get` 的 `campus_ids` 数组回填校区树。

---

**PRD 状态**: ✅ 待评审，等用户确认后进入实施阶段
