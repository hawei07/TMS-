# TMS 活动管理模块 Bug 修复 PRD

**版本**: 1.0  
**日期**: 2026-07-10  
**类型**: Bug 修复（非新功能）  
**影响模块**: 活动管理（activities）  
**涉及文件**: `index.php`、`static/js/main.js`

---

## 一、问题背景

活动管理模块「扣费规则」和「适用校区」两个子表单无法正常保存和回显。根本原因是前后端数据结构不一致——前端发送嵌套对象/ID 值，后端期望扁平数组/名称字符串，两端字段名也不匹配。共诊断出 5 个 Bug。

---

## 二、当前数据流分析（As-Is）

### 2.1 DB 表结构（不可变更）

```sql
-- activity_campuses：无 campus_id 列，存校区名称字符串
CREATE TABLE activity_campuses (
  id INT PRIMARY KEY AUTO_INCREMENT,
  activity_id INT,
  campus_name VARCHAR(200),   -- 校区名称（自由文本）
  max_capacity INT
);

-- activity_subject_deductions：无 subject_id 列，存学科名称字符串
CREATE TABLE activity_subject_deductions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  activity_id INT,
  fee_type VARCHAR(10),        -- 'adult' | 'student'
  subject_level1 VARCHAR(200), -- 学科名称
  deduct_lessons INT
);

-- organizations：可通过 name + type='校区' 反查 campus_id
CREATE TABLE organizations (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(200),
  type VARCHAR(50)             -- '校区' 标识校区
);
```

### 2.2 当前前后端数据格式对比

| 维度 | 前端发送（saveActivity） | 后端期望（save_activity） | 后端返回（get_activity） | 前端读取（showActivityForm） |
|------|--------------------------|--------------------------|--------------------------|------------------------------|
| **deductions 结构** | `{adult: [...], student: [...]}` | `[{fee_type, subject_level1, ...}]` 扁平数组 | `{adult: [...], student: [...]}` | `data.deductions.adult.forEach(...)` |
| **deductions 字段** | `subject_id`（整数） | `subject_level1`（字符串） | `subject_level1`（字符串） | `d.subject_id` / `d.subject_name` ❌ |
| **campuses 字段** | `campus_id`（整数） | `campus_name`（字符串）→ fallback 查 organizations | `campus_name`（无 campus_id） | `cp.campus_id` ❌ |

---

## 三、Bug 清单与修复方案

### Bug 1 — 保存时 deductions 结构不匹配（P0）

**症状**: 扣费规则的学科永远是空字符串，扣课时数永远是默认值 1。

**根因**:
- JS `saveActivity` 发送：`{adult: [{subject_id, deduct_lessons}], student: [...]}`
- PHP `foreach ($deductions as $deduct)` 遍历对象的两个 key（`'adult'`、`'student'`）
  - 第 1 轮：`$deduct` = 空数组 `[]`（adult 可能为空）→ `fee_type` fallback `'student'`，`subject_level1` = `''`，`deduct_lessons` = `1` → INSERT 垃圾记录
  - 第 2 轮：`$deduct` = `[{subject_id:N, ...}, ...]`（整个 student 数组被当作一条记录）→ 所有字段读不到 → 又一条垃圾记录

**修复**: 前端 `saveActivity()` 改为发送后端期望的扁平数组格式。

**文件**: `static/js/main.js`，`saveActivity` 函数（~3343-3359 行）

**改动**:
```javascript
// BEFORE (Bug): 嵌套结构 {adult: [...], student: [...]}
const deductions = { adult: [], student: [] };
['adult', 'student'].forEach(type => {
    rows.forEach(row => {
        deductions[type].push({
            subject_id: subId,        // Bug 2: 错误字段名
            deduct_lessons: lessons
        });
    });
});

// AFTER (Fix): 扁平数组 [{fee_type, subject_level1, deduct_lessons}]
const deductions = [];
['adult', 'student'].forEach(type => {
    rows.forEach(row => {
        const select = row.querySelector('.deduct-subject');
        const lessonsInput = row.querySelector('.deduct-lessons');
        const selectedOption = select ? select.options[select.selectedIndex] : null;
        const subName = selectedOption ? selectedOption.text : '';
        const lessons = lessonsInput ? (parseInt(lessonsInput.value) || 0) : 0;
        if (subName && lessons > 0) {
            deductions.push({
                fee_type: type,
                subject_level1: subName,   // Bug 2 Fix: 传学科名称字符串
                deduct_lessons: lessons
            });
        }
    });
});
```

**验证**: 新建活动 → 添加扣费规则（选学科+填课时）→ 保存 → 重新编辑，扣费规则应正确回显。

---

### Bug 2 — 保存时 deductions 字段名不匹配（P0）

**症状**: 即使修复 Bug 1，`subject_level1` 仍然为空（因为前端传 `subject_id` 整数）。

**根因**:
- `activity_subject_deductions` 表的 `subject_level1` 列是 VARCHAR(200)，存学科名称字符串
- 前端 `<select>` 的 `value` 是学科 ID（整数），`textContent` 才是学科名称
- JS 发送 `subject_id: parseInt(select.value)` → 后端读 `$deduct['subject_level1']` → 读到整数 → `trim(int)` 可能非空但语义错误

**修复**: 与 Bug 1 合并修复 —— 发送 `select.options[select.selectedIndex].text`（学科名称字符串）作为 `subject_level1`。

**改动**: 见 Bug 1 的 AFTER 代码中 `subject_level1: subName` 部分。

---

### Bug 3 — 加载时校区 checkbox 不勾选（P1）

**症状**: 编辑活动时，之前保存的校区 checkboxes 全部未勾选。

**根因**:
- 前端 `renderActivityCampusRows` 第 3298 行：`const existing = (campuses || []).find(cp => cp.campus_id == c.id);`
- `get_activity` API 返回的 `campuses` 来自 `activity_campuses` 表，该表只有 `campus_name`，**没有 `campus_id` 列**
- `cp.campus_id` 永远是 `undefined` → 匹配永远失败 → checkbox 永远不勾选

**修复**: 后端 `get_activity` 的 campuses 查询 LEFT JOIN `organizations` 表，追加 `campus_id` 字段。

**文件**: `index.php`，`get_activity` case（~1796-1799 行）

**改动**:
```php
// BEFORE (Bug):
$campusStmt = $db->prepare("SELECT * FROM activity_campuses WHERE activity_id = :aid");

// AFTER (Fix):
$campusStmt = $db->prepare("
    SELECT ac.*, o.id AS campus_id
    FROM activity_campuses ac
    LEFT JOIN organizations o ON o.name = ac.campus_name AND o.type = '校区'
    WHERE ac.activity_id = :aid
");
```

**说明**: 使用 LEFT JOIN（非 INNER JOIN），即使 organizations 中找不到匹配（校区改名/删除），`campus_id` 为 NULL 也不会丢失记录。前端 `renderActivityCampusRows` 无需改动，`cp.campus_id == c.id` 在 campus_id 为 NULL 时自然不匹配，checkbox 不勾选是合理行为。

**同步检查**: `list_activities` API（~1776 行）的 campuses 查询也需要同样修改，确保列表页数据一致。
```php
// list_activities 中也需同步
$campusStmt = $db->prepare("
    SELECT ac.id, ac.campus_name, ac.max_capacity, o.id AS campus_id
    FROM activity_campuses ac
    LEFT JOIN organizations o ON o.name = ac.campus_name AND o.type = '校区'
    WHERE ac.activity_id = :aid
");
```

---

### Bug 4 — 加载时扣费规则 select 不选中（P1）

**症状**: 编辑活动时，之前保存的扣费规则行的学科下拉框未选中对应学科。

**根因**:
- 前端 `showActivityForm` 第 3191 行：`addActivityDeductRow('adult', d.subject_id, d.subject_name, d.deduct_lessons)`
- `get_activity` API 返回的 deductions 来自 `activity_subject_deductions` 表，该表只有 `subject_level1`（字符串），**没有 `subject_id` 或 `subject_name`**
- `d.subject_id` = `undefined`，`d.subject_name` = `undefined` → `addActivityDeductRow` 无法匹配学科

- 但 `addActivityDeductRow` 第 3273 行已有 fallback 匹配逻辑：`const sel = (s.id == subjectId || s.name === subjectName) ? ' selected' : '';`
- 如果传正确的 `subjectName`（= `subject_level1` 字符串），通过 `s.name === subjectName` 即可匹配

**修复**: 前端 `showActivityForm` 调用 `addActivityDeductRow` 时，将 `d.subject_level1` 作为 `subjectName` 传入。

**文件**: `static/js/main.js`，`showActivityForm` 函数（~3189-3198 行）

**改动**:
```javascript
// BEFORE (Bug):
data.deductions.adult.forEach(d => {
    addActivityDeductRow('adult', d.subject_id, d.subject_name, d.deduct_lessons);
});
data.deductions.student.forEach(d => {
    addActivityDeductRow('student', d.subject_id, d.subject_name, d.deduct_lessons);
});

// AFTER (Fix):
data.deductions.adult.forEach(d => {
    addActivityDeductRow('adult', null, d.subject_level1, d.deduct_lessons);
});
data.deductions.student.forEach(d => {
    addActivityDeductRow('student', null, d.subject_level1, d.deduct_lessons);
});
```

**说明**: 第一个参数 `subjectId` 传 `null`，依赖 `addActivityDeductRow` 内的 `s.name === subjectName` 名称匹配逻辑。

---

### Bug 5 — 保存 campus 时名称查找链路脆弱（P2）

**症状**: 当前基本能工作，但依赖 `organizations` 表反查，存在风险。

**根因**:
- 前端发送 `campus_id`（checkbox value = org ID）
- 后端先尝试读 `campus_name`（空），fallback 到 `organizations` 表查询
- 如果 organizations 表中该 ID 不存在或 type ≠ '校区' → `campusName` 为空 → 跳过 INSERT（静默丢数据）

**修复**: 在 Bug 3 修复后（`get_activity` 返回 `campus_id`），前端编辑回填时 `cp.campus_id` 有值，保存时传到后端，后端 fallback 查询同一张 `organizations` 表（来源一致），链路闭合。**无需额外代码改动**。

**但建议加固**：后端 `save_activity` 在 `$campusName` 为空时记录日志并跳过，至少让管理员知道数据丢失了。

**文件**: `index.php`，`save_activity` case（~1860-1863 行）

**加固（可选）**:
```php
// AFTER: 增加日志（可选）
if ($campusName) {
    $db->exec("INSERT INTO activity_campuses ...");
} else {
    error_log("Activity save: campus_id=$campusId not found in organizations, skipping");
}
```

---

## 四、改动汇总

| Bug | 文件 | 位置 | 改动类型 | 改动量 |
|-----|------|------|----------|--------|
| 1+2 | `static/js/main.js` | `saveActivity()` ~3343-3359 | 重写 deductions 收集逻辑 | ~15 行 |
| 3 | `index.php` | `get_activity` ~1796 | 修改 SQL（LEFT JOIN） | ~3 行 |
| 3 | `index.php` | `list_activities` ~1776 | 修改 SQL（LEFT JOIN） | ~3 行 |
| 4 | `static/js/main.js` | `showActivityForm()` ~3191-3196 | 改参数 `d.subject_name` → `d.subject_level1` | ~4 行 |
| 5 | — | — | 无需改动（Bug 3 修复自然解决） | 0 行 |

**总计**: 2 个文件，约 25 行改动。

---

## 五、验证步骤

### 5.1 验证 Bug 1+2+4（扣费规则保存+回显）

1. 新建活动 → 学员收费模式选「收费+扣课时」
2. 点击「+ 添加扣课规则」→ 选择学科（如「绘画」）→ 填扣课时数（如 3）
3. 再添加一条规则 → 选择另一学科（如「书法」）→ 填扣课时数（如 2）
4. 填写其他必填项 → 保存
5. 在列表页找到该活动 → 点击「编辑」
6. **验证**: 扣费规则区域应显示两条规则，学科下拉框应分别选中「绘画」和「书法」，扣课时数应分别为 3 和 2

### 5.2 验证 Bug 3（校区 checkbox 回显）

1. 编辑上述活动
2. **验证**: 「适用校区」区域的 checkbox 应勾选上次保存时选中的校区
3. 修改校区勾选 → 保存 → 再次编辑
4. **验证**: checkbox 勾选状态与上次保存一致

### 5.3 验证 Bug 5（campus 保存链路完整）

1. 选择校区时确保至少选一个在 `organizations` 表中 `type='校区'` 的记录
2. 保存 → 编辑 → 确认校区回显正确
3. **边界测试**: 如果选中了一个已被删除的校区（organizations 中不存在），保存后编辑应不勾选（因为 campus_id 为 NULL，不匹配任何 checkbox）

### 5.4 curl 验证

```bash
# 1. 语法检查
php -l index.php

# 2. JS 函数去重检查
grep -oP "function \w+" static/js/main.js | sort | uniq -d

# 3. 创建活动（含扣费规则）
curl -s -X POST "http://127.0.0.1:5001/index.php?action=save_activity" \
  -H "Content-Type: application/json" \
  -d '{"id":0,"name":"PRD测试活动","subject_level1":"绘画","reg_start_date":"2026-07-10","reg_end_date":"2026-08-10","adult_fee_mode":"fee_only","adult_price":0,"student_fee_mode":"fee_and_deduct","student_price":100,"campuses":[{"campus_id":7,"max_capacity":30}],"deductions":[{"fee_type":"student","subject_level1":"绘画","deduct_lessons":3},{"fee_type":"student","subject_level1":"书法","deduct_lessons":2}]}'

# 4. 获取活动详情（验证 campus_id 和 subject_level1 返回）
curl -s "http://127.0.0.1:5001/index.php?action=get_activity&id=<返回的ID>" | python -c "import sys,json; d=json.loads(sys.stdin.buffer.read().decode('utf-8-sig')); print('campuses:', d.get('campuses')); print('deductions:', d.get('deductions'))"
```

---

## 六、风险与边界

| 风险 | 影响 | 缓解措施 |
|------|------|----------|
| `organizations` 表中校区被删除或改名 | LEFT JOIN 返回 `campus_id=NULL`，checkbox 不勾选 | 可接受——校区已不存在，不应勾选 |
| 学科名称变更（如「绘画」改「美术」） | `subject_level1` 存的是旧名称，编辑回显时 `s.name === subjectName` 匹配失败 | 可接受——如需同步，管理员手动重新选择 |
| `addActivityDeductRow` 名称匹配依赖 `loadActivitySubjectOptionsForSelect` 的学科列表 | 如果学科被删除，匹配失败 | 可接受——select 保持未选中状态，管理员可手动修正 |
| `list_activities` 的 N+1 查询增加 LEFT JOIN | 性能轻微下降（多一次 JOIN） | 可接受——organizations 表数据量小，JOIN 走索引 |

---

## 七、不改动的范围

- ❌ 不新增 DB 列（`activity_campuses` 不加 `campus_id`，`activity_subject_deductions` 不加 `subject_id`）
- ❌ 不新增 API 端点
- ❌ 不改动表结构
- ❌ 不改动 UI 布局
- ❌ 不新增功能（仅修复已有功能的保存/回显）
