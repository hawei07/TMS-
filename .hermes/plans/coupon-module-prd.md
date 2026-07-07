# TMS 优惠券+发放记录模块 PRD

> 版本：1.0 | 日期：2026-07-07 | 状态：设计中

## 1. 概述

在现有「优惠管理」面板（`panel-discounts`）中新增两个标签页：
- **标签页2：优惠券** — 优惠券的增删改查，支持课程券/商品券分类、校区/学科树状多选匹配
- **标签页3：优惠券发放记录** — 记录向家长发放优惠券的操作日志

## 2. 数据库设计

### 2.1 优惠券主表 `coupons`

```sql
CREATE TABLE IF NOT EXISTS coupons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL DEFAULT '',
    coupon_type VARCHAR(20) NOT NULL DEFAULT '课程券' COMMENT '课程券|商品券',
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '优惠金额',
    start_date VARCHAR(20) DEFAULT '' COMMENT '有效期开始',
    end_date VARCHAR(20) DEFAULT '' COMMENT '有效期结束',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.2 优惠券适用校区关联表 `coupon_campuses`

```sql
CREATE TABLE IF NOT EXISTS coupon_campuses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    coupon_id INT NOT NULL,
    campus_id INT NOT NULL,
    INDEX idx_cc_coupon (coupon_id),
    INDEX idx_cc_campus (campus_id),
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.3 优惠券适用学科关联表 `coupon_subjects`

```sql
CREATE TABLE IF NOT EXISTS coupon_subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    coupon_id INT NOT NULL,
    subject_id INT NOT NULL,
    INDEX idx_cs_coupon (coupon_id),
    INDEX idx_cs_subject (subject_id),
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.4 优惠券发放记录表 `coupon_records`

```sql
CREATE TABLE IF NOT EXISTS coupon_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    coupon_id INT NOT NULL COMMENT '优惠券ID',
    student_name VARCHAR(200) NOT NULL DEFAULT '' COMMENT '学员姓名',
    phone VARCHAR(20) NOT NULL DEFAULT '' COMMENT '手机号',
    distributor VARCHAR(100) NOT NULL DEFAULT '' COMMENT '发放人',
    distributed_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '发放时间',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cr_coupon (coupon_id),
    INDEX idx_cr_phone (phone),
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.5 数据关系图

```
coupons (主表)
  ├── coupon_campuses (N:M → organizations.id)
  ├── coupon_subjects (N:M → subjects.id)
  └── coupon_records (1:N 发放记录)
```

### 2.6 与现有 discount_plans 区别

| 维度 | discount_plans（优惠方案） | coupons（优惠券） |
|------|---------------------------|-------------------|
| 用途 | 报名/续费时关联计费 | 发放给家长、独立核销 |
| 分类 | 新报 / 续费 | 课程券 / 商品券 |
| 发放 | 方案级、无发放记录 | 每人次发放、有记录追踪 |
| 有效期 | 方案级 | 券级，可不同 |

## 3. API 设计

### 3.1 优惠券 CRUD

#### `list_coupons` (GET) — 列表查询

**参数：**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| page | int | 否 | 页码，默认1 |
| page_size | int | 否 | 每页条数，默认15，最大100 |
| keyword | string | 否 | 搜索优惠券名称 |
| coupon_type | string | 否 | 类型筛选：课程券/商品券 |
| campus_id | int | 否 | 按适用校区筛选 |

**返回：**
```json
{
  "data": [{
    "id": 1,
    "name": "新年课程券",
    "coupon_type": "课程券",
    "amount": 200.00,
    "start_date": "2026-01-01",
    "end_date": "2026-12-31",
    "campus_ids": "7,14",
    "campus_names": "曲江龙湖, 高新校区",
    "subject_ids": "3,5,8",
    "subject_names": "数学 > 小学, 英语 > 初中",
    "record_count": 5,
    "created_at": "2026-01-15 10:30:00"
  }],
  "total": 1,
  "page": 1,
  "page_size": 15
}
```

**实现要点：**
- 子查询聚合 campus_ids/names、subject_ids/names（复用 `list_discount_plans` 模式）
- 子查询 COUNT coupon_records 获取发放次数
- 支持 campus_id 筛选（IN 子查询匹配 coupon_campuses）

---

#### `add_coupon` (POST) — 新增优惠券

**请求体：**
```json
{
  "name": "新年课程券",
  "coupon_type": "课程券",
  "discount_amount": 200,
  "start_date": "2026-01-01",
  "end_date": "2026-12-31",
  "campus_ids": [7, 14],
  "subject_ids": [3, 5, 8]
}
```

**校验规则：**
- `name` 不为空
- `coupon_type` ∈ {课程券, 商品券}
- `discount_amount` > 0
- `start_date` ≤ `end_date`
- 同名称+同类型唯一性校验

**实现：** 事务包裹 → INSERT coupons → INSERT coupon_campuses → INSERT coupon_subjects → commit

---

#### `update_coupon` (POST) — 编辑优惠券

请求体同 `add_coupon`，增加 `id` 字段。ID 不存在返回 404。

单条存在性查询 + 唯一性校验（排除自身 ID）。

事务：UPDATE → DELETE 旧关联 → INSERT 新关联。

---

#### `delete_coupon` (POST) — 删除优惠券

```json
{ "id": 1 }
```

存在性校验后 DELETE（CASCADE 自动清理关联表和发放记录）。

---

#### `get_coupon` (GET) — 查询单条

**参数：** `?action=get_coupon&id=1`

**返回：** coupon 所有字段 + `campus_ids: [7, 14]` + `subject_ids: [3, 5, 8]`

---

### 3.2 发放记录

#### `list_coupon_records` (GET) — 发放记录列表

**参数：**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| page | int | 否 | 页码 |
| page_size | int | 否 | 每页条数 |
| coupon_id | int | 否 | 按优惠券筛选 |
| keyword | string | 否 | 搜索学员姓名/手机号 |
| date_from | string | 否 | 发放日期起始 |
| date_to | string | 否 | 发放日期截止 |

**返回：**
```json
{
  "data": [{
    "id": 1,
    "coupon_id": 1,
    "coupon_name": "新年课程券",
    "coupon_type": "课程券",
    "discount_amount": 200.00,
    "student_name": "张三",
    "phone": "13800138000",
    "distributor": "李老师",
    "distributed_at": "2026-06-15 14:30:00"
  }],
  "total": 1,
  "page": 1,
  "page_size": 15
}
```

**实现：** JOIN coupons 获取优惠券名称/类型/金额。

---

#### `add_coupon_record` (POST) — 新增发放记录

**请求体：**
```json
{
  "coupon_id": 1,
  "student_name": "张三",
  "phone": "13800138000",
  "distributor": "李老师",
  "distributed_at": "2026-06-15 14:30:00"
}
```

**校验：**
- `coupon_id` 有效（coupons 表中存在）
- `student_name` 不为空
- `phone` 不为空
- `distributor` 不为空

**注意：** 不需要校验优惠券有效期——发放动作不阻止过期券的录入（历史补录需求）。

---

#### `delete_coupon_record` (POST) — 删除发放记录

```json
{ "id": 1 }
```

## 4. 前端页面结构

### 4.1 面板整体结构（修改 `panel-discounts`）

```html
<section class="content-panel" id="panel-discounts">
    <div class="panel-header"><h3>优惠管理</h3></div>
    <div class="section-tabs">
        <span class="sec-tab active" data-tab="tab-discount-plans">优惠方案</span>
        <span class="sec-tab" data-tab="tab-coupons">优惠券</span>
        <span class="sec-tab" data-tab="tab-coupon-records">发放记录</span>
    </div>

    <!-- Tab 1: 优惠方案（已有，不变） -->
    <div id="tab-discount-plans">...</div>

    <!-- Tab 2: 优惠券（新增） -->
    <div id="tab-coupons" style="display:none;">
        ...
    </div>

    <!-- Tab 3: 发放记录（新增） -->
    <div id="tab-coupon-records" style="display:none;">
        ...
    </div>
</section>
```

> 注意：非 active 的 sec-panel 需要 `display:none`（HTML 内联），切换时由 JS 控制 `display:block/none`。

### 4.2 标签页2：优惠券 (`tab-coupons`)

#### 工具栏
- 左侧：搜索框（名称）、类型下拉（全部/课程券/商品券）、校区下拉
- 右侧：「+ 新增优惠券」按钮

#### 表格（8列）
| 列名 | 宽度 | 渲染方式 |
|------|------|----------|
| 优惠券名称 | auto | `esc(r.name)` |
| 类型 | 80px | `.tag-green`(课程券) / `.tag-orange`(商品券) |
| 优惠金额 | 100px | 右对齐、红色、`¥Number(r.amount).toFixed(2)` |
| 有效期 | 180px | `r.start_date ~ r.end_date` |
| 适用校区 | 140px | `r.campus_names` 截断+title |
| 适用学科 | 140px | `r.subject_names` 截断+title |
| 已发放 | 80px | `r.record_count` 次 |
| 操作 | 120px | 编辑 / 删除 |

> ⚠ colspan 检查：空状态 `<td colspan="8">`，表头 8 列。

#### 新增/编辑弹窗 (`modal-coupon`)

**字段：**
| 字段 | 控件 | 必填 | 校验 |
|------|------|------|------|
| 优惠券名称 | `<input>` | ✅ | 非空 |
| 优惠券类型 | `<select>` 课程券/商品券 | ✅ | 枚举 |
| 优惠金额(元) | `<input type="number">` | ✅ | >0 |
| 有效期开始 | `<input type="date">` | ✅ | Flatpickr 自动接管 |
| 有效期结束 | `<input type="date">` | ✅ | ≥开始日期 |
| 适用校区 | 树状多选 checkbox | 否 | 仅校区节点（type='校区'） |
| 适用学科 | 树状多选 checkbox | 否 | 粒度到二级学科 |

**关键注意事项：**

1. **校区树**：复用 `loadCampusTree('coupon-campus-tree')`，但独立写 `getCouponSelectedCampuses()` 收集函数（因为 `getSelectedCampuses()` 硬编码了 `#course-campus-tree`）。

2. **学科树**：使用已有的 `loadDiscountSubjectTree()` 并改容器 ID → `coupon-subject-tree`。注意函数内的 `syncDiscountSubjectBadge()` 等也需要复制一份适配 coupon 前缀。或者更干净的做法：写一个通用 `loadSubjectPickerTree(containerId, onCheckChange)` 函数。

3. **弹窗容器**：⚠️ 必须用 `class="modal modal-lg"`，不是 `modal-container`（TMS CSS 无该 class）。

4. **编辑回填**：树渲染完成后用 `setTimeout`（200ms）勾选 checkbox，并更新 badge 计数。

### 4.3 标签页3：发放记录 (`tab-coupon-records`)

#### 工具栏
- 左侧：优惠券下拉筛选、搜索框（学员姓名/手机号）、日期范围（发放日期起止）
- 右侧：「+ 新增发放记录」按钮

#### 表格（6列）
| 列名 | 宽度 | 渲染方式 |
|------|------|----------|
| 发放时间 | 150px | `r.distributed_at.substring(0,16)` |
| 优惠券 | 140px | `esc(r.coupon_name)` |
| 优惠金额 | 100px | 右对齐、红色、`¥Number(r.discount_amount).toFixed(2)` |
| 学员姓名 | auto | `esc(r.student_name)` |
| 手机号 | 120px | `esc(r.phone)` |
| 发放人 | 80px | `esc(r.distributor)` |
| 操作 | 80px | 删除 |

> ⚠ colspan 检查：空状态 `<td colspan="7">`。

#### 新增发放记录弹窗 (`modal-coupon-record`)

**字段：**
| 字段 | 控件 | 必填 | 说明 |
|------|------|------|------|
| 优惠券 | `<select>` | ✅ | 从 coupons 表加载（仅加载有效期内或全部） |
| 学员姓名 | `<input>` | ✅ | |
| 手机号 | `<input>` | ✅ | |
| 发放人 | `<input>` | ✅ | |
| 发放时间 | `<input type="date">` | 否 | 默认当天，Flatpickr 自动接管 |

## 5. 前端 JS 函数清单

### 5.1 优惠券

| 函数 | 功能 |
|------|------|
| `initCouponTabs()` | 注册 section-tabs 点击切换事件（扩展自现有的 `initDiscountTabs`） |
| `loadCoupons(page)` | 调用 `list_coupons` API，渲染表格+分页 |
| `renderCouponTable(rows)` | 渲染优惠券表格（8列） |
| `renderCouponPagination(total, page)` | 分页渲染（复用 discount 分页模式） |
| `showCouponForm(id?)` | 打开新增/编辑弹窗，加载树，编辑模式回填 |
| `saveCoupon()` | 校验 → `add_coupon` / `update_coupon` → 关弹窗 → 刷新 |
| `deleteCoupon(id, name)` | `showCustomConfirm` → `delete_coupon` → 刷新 |
| `getCouponSelectedCampuses()` | 收集 `#coupon-campus-tree` 选中校区 ID |
| `getCouponSelectedSubjects()` | 收集 `#coupon-subject-tree` 选中学科 ID |
| `loadCouponSubjectTree()` | 加载学科 checkbox 树（带 badge 更新，适配 coupon 前缀） |

### 5.2 发放记录

| 函数 | 功能 |
|------|------|
| `loadCouponRecords(page)` | 调用 `list_coupon_records` API |
| `renderCouponRecordTable(rows)` | 渲染发放记录表格（7列） |
| `renderCouponRecordPagination(total, page)` | 分页 |
| `showCouponRecordForm()` | 打开新增发放记录弹窗，加载优惠券下拉 |
| `saveCouponRecord()` | 校验 → `add_coupon_record` → 关弹窗 → 刷新 |
| `deleteCouponRecord(id)` | `showCustomConfirm` → `delete_coupon_record` → 刷新 |

### 5.3 标签页切换逻辑

```javascript
function initCouponTabs() {
    document.querySelectorAll('#panel-discounts .sec-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('#panel-discounts .sec-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            // 切换标签页可见性
            ['tab-discount-plans', 'tab-coupons', 'tab-coupon-records'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.display = id === this.dataset.tab ? 'block' : 'none';
            });
            // 按需加载数据
            const tab = this.dataset.tab;
            if (tab === 'tab-discount-plans') { discountPlanPage = 1; loadDiscountPlans(); }
            else if (tab === 'tab-coupons') { couponPage = 1; loadCoupons(); }
            else if (tab === 'tab-coupon-records') { couponRecordPage = 1; loadCouponRecords(); }
        });
    });
}
```

## 6. 文件变更清单

| 文件 | 变更内容 |
|------|----------|
| `index.php` | ① 建表区追加 4 张表；② API 区追加 8 个 case；③ panel-discounts 内追加 tab-coupons + tab-coupon-records HTML；④ 追加 modal-coupon + modal-coupon-record 弹窗 HTML |
| `static/js/main.js` | ① 追加 ~300 行 JS（15个函数）；② 修改 `initDiscountTabs` → `initCouponTabs` 支持 3 tab；③ 在 `refreshPanel` 或 `DOMContentLoaded` 中注册 |
| `static/css/style.css` | 追加 coupon 面板相关样式（如有需要——tag-orange、table 微调等） |

## 7. 实施步骤

### Phase 1: 数据库

1. 在 `index.php` 建表区（554-578行 `discount_plans` 附近）追加 4 张 `CREATE TABLE IF NOT EXISTS`
2. 重启 PHP 服务器使建表生效

### Phase 2: API 后端

1. 在 `index.php` `// ==================== 优惠管理 API ====================` 区域内（2712 行前）插入 8 个 API case
2. 按 coupon CRUD（5个）→ coupon_records（3个）顺序
3. 每个 case 写完用 `php -l index.php` 语法检查

### Phase 3: 前端面板 HTML

1. `section-tabs` 追加两行 `<span class="sec-tab">`
2. `tab-discount-plans` 后面追加两个 `<div id="tab-coupons">` 和 `<div id="tab-coupon-records">`
3. 弹窗区追加两个 modal（在现有 `modal-discount-plan` 附近）
4. 注意：非 active tab 需要内联 `style="display:none;"`

### Phase 4: 前端 JS

1. 在 `main.js` 末尾追加所有 coupon 函数
2. 修改 `initDiscountTabs()` 为支持 3 个 tab
3. 检查 JS 函数名无重复（`grep` 查重）
4. 检查 colspan 与表头列数一致
5. 确保所有 DOM ID 与 HTML 一致（无 `coupon-name` vs `coupon-plan-name` 之类不匹配）

### Phase 5: 验证

```bash
# 语法
php -l index.php

# 函数重复检查
grep -oP "function \w+" main.js | sort | uniq -d

# API 测试
curl "http://127.0.0.1:5001/?action=list_coupons"
curl -X POST "http://127.0.0.1:5001/?action=add_coupon" -H "Content-Type: application/json" -d '{...}'

# 浏览器
# 1. 点击"优惠券"标签 → 表格加载
# 2. 新增优惠券 → 校验 → 成功 → 列表刷新
# 3. 编辑 → 树回填正确
# 4. 删除 → 确认 → 消失
# 5. 点击"发放记录"标签 → 表格加载
# 6. 新增发放记录 → 成功
```

## 8. 风险与注意事项

| 风险 | 缓解措施 |
|------|----------|
| 学科树函数重复 | 已有 `loadDiscountSubjectTree()` 用于 discount plan 弹窗，优惠券弹窗需独立版本或通用化。如复制，函数名加 `Coupon` 前缀以避免冲突 |
| 校区树 getter 冲突 | `getSelectedCampuses()` 硬编码 `#course-campus-tree`。优惠券必须独立写 `getCouponSelectedCampuses()` |
| 弹窗 class 错误 | 必须用 `modal modal-lg`，不用 `modal-container` |
| JS 函数重复定义 | 所有新函数先 grep 查重 |
| colspan 不匹配 | 每张表格增加列后用手动计数确保 colspan 值正确 |
| debounceSearch | 现有 `debounceSearch('discount')` 只处理 discount plans，coupon 搜索如需要防抖需扩展该函数 |
| 并行委托覆盖 | 后端和前端 Agent 都改 `index.php` 时可能互相覆盖（陷阱 #48），建议顺序委托或后端只改 PHP case 区、前端只改 panel+弹窗 HTML |

## 9. 完成标准

- [ ] 4 张新表创建成功
- [ ] 8 个 API 全部可用并通过 curl 测试
- [ ] 3 个标签页可切换，每个切换时正确加载对应数据
- [ ] 优惠券 CRUD：新增/编辑/删除功能正常
- [ ] 校区树 + 学科树在优惠券弹窗中正常多选
- [ ] 发放记录 CRUD：新增/删除功能正常
- [ ] JS 无重复函数定义
- [ ] PHP 语法检查通过
- [ ] 所有 colspan 与表头列数一致
