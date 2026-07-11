---
AIGC:
    Label: "1"
    ContentProducer: 001191440300708461136T1XGW3
    ProduceID: 3f11eb7fa23d664c4b1c1527387f20fd_45a4a5516eeb11f195af5254002afed2
    ReservedCode1: EZq5UO9xBBf9NCrTl9lwm0r3A50Hu6KmPirYUnSbGlC1hS+/U4rY/asVYwanqVnSxUqCKyhFOWBowiYL/kiWFdCMJZp3BuPeFqvXLCjorcmWdpSSq79jNABObOq/gOFvwp0qahmeRbCR67fhMyMdJ1ponlou/rVfQHHMxyEqDQMNFTNFx+9lngQGlf4=
    ContentPropagator: 001191440300708461136T1XGW3
    PropagateID: 3f11eb7fa23d664c4b1c1527387f20fd_45a4a5516eeb11f195af5254002afed2
    ReservedCode2: EZq5UO9xBBf9NCrTl9lwm0r3A50Hu6KmPirYUnSbGlC1hS+/U4rY/asVYwanqVnSxUqCKyhFOWBowiYL/kiWFdCMJZp3BuPeFqvXLCjorcmWdpSSq79jNABObOq/gOFvwp0qahmeRbCR67fhMyMdJ1ponlou/rVfQHHMxyEqDQMNFTNFx+9lngQGlf4=
---

# TMS管理系统 — 业务逻辑与技术架构总结

> 项目绝对路径：`D:\market-system-php\`

---

## 服务信息

| 项目 | 值 |
|------|-----|
| PHP 路径 | Winget PHP 8.4（`php.exe` 在 PATH 中） |
| 配置文件 | `C:\Users\吴赛\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.ini` |
| 监听端口 | `0.0.0.0:5001` |
| 数据库 | **MySQL 8.4.9**（`tms_db`，127.0.0.1:3306，root/root） |
| MySQL 安装路径 | `D:\dvptool\mysql\` |
| 访问地址 | http://localhost:5001 |

### Git 版本管理

| 项目 | 值 |
|------|-----|
| Git 路径 | `D:\Git\bin\git.exe`（Git 2.54.0） |
| GitHub | https://github.com/hawei07/TMS-.git |
| 分支策略 | `feature/xxx` → `develop` → `master`（no-ff 合并） |
| 稳定分支 | `master` |
| 开发分支 | `develop` |
| 提交规范 | [Conventional Commits](https://www.conventionalcommits.org/)（feat/fix/docs/refactor/style/chore） |
| 回退方式 | `git log` 查历史 → `git revert` / `git reset` |

每次迭代流程：切 `feature/xxx` 分支 → 修改代码 → 提交 → 合并回 `develop`，稳定后 `no-ff` 合并到 `master`。每个版本可追溯、可回退。

### 启动命令

```powershell
cd "D:\market-system-php"
php -S 127.0.0.1:5001
```

### 重启命令

```powershell
Stop-Process -Name "php" -Force -ErrorAction SilentlyContinue
Start-Process -FilePath "php" -ArgumentList "-S", "127.0.0.1:5001" -WorkingDirectory "D:\market-system-php" -WindowStyle Hidden
```

### 环境注意事项

1. **MySQL 服务**：开发前需确保 MySQL 8.4.9 已启动（`D:\dvptool\mysql\bin\mysqld.exe`），端口 3306，root 密码 `root`。

2. **PHP 扩展**：需启用 `pdo_mysql` 和 `mbstring` 扩展。已在 php.ini 中配置 `extension=pdo_mysql` 和 `extension=mbstring`。

3. **数据库连接**：`index.php` 通过 PDO 连接 `mysql:host=127.0.0.1;port=3306;dbname=tms_db;charset=utf8mb4`，异常模式下自动抛出 PDOException。

4. **MySQL 启动命令**：
   ```powershell
   Start-Process "D:\dvptool\mysql\bin\mysqld.exe" -ArgumentList "--defaults-file=`"D:\dvptool\mysql\my.ini`"" -WindowStyle Hidden
   ```

---

## 一、项目概述

- **系统名称**：TMS管理系统
- **系统定位**：教育培训行业市场资源与教务管理工具，覆盖资源录入、跟进、预约试听、公海流转、课程管理、活动管理、学员管理、学科设置、交易订单、报价方案、员工管理、组织架构管理等完整业务闭环
- **技术栈**：PHP 8.4（内嵌 HTML）+ MySQL 8.4.9（PDO）+ Vanilla JS（约 13200 行）+ CSS3（约 9300 行）
- **架构模式**：单体 PHP 单文件应用（`index.php`，约 11000 行），前端内嵌于同一文件，API 通过 `?action=` 路由分发，所有 API 统一返回 JSON

---

## 二、技术架构

### 2.1 架构图

```
┌──────────────────────────────────────────────────┐
│                 index.php (~3200行)                │
│  ┌────────────┐  ┌─────────────────────────────┐ │
│  │  PHP 后端   │  │       HTML 内嵌前端          │ │
│  │  - 建表     │  │  - 左侧树状导航（三级模块）   │ │
│  │  - API路由  │  │  - 右侧多面板内容区           │ │
│  │  - CSV导出  │  │  - 模态弹窗                   │ │
│  │  - Excel导入│  │  - 组织树形结构               │ │
│  └──────┬─────┘  └──────────┬──────────────────┘ │
│         │                   │                     │
│    MySQL (PDO)          main.js / style.css       │
│   (tms_db, 3306)        (static/js/ & static/css/) │
└──────────────────────────────────────────────────┘
```

### 2.2 技术选型

| 技术 | 选型理由 |
|------|----------|
| PHP 内嵌 HTML | 单文件部署，`php -S` 零配置启动 |
| MySQL 8.4 (PDO) | 关系型数据库，支持并发读写，外键约束，UTF-8 字符集 |
| Vanilla JS | 无框架依赖，约 7550 行完成完整 SPA 交互 |
| CSS Variables | 统一设计令牌（`--color-primary`/`--shadow-md` 等），便于主题定制 |
| ZipArchive + XML | 纯 PHP 解析 .xlsx 文件，零第三方依赖 |

### 2.3 目录结构

```
market-system-php/
├── index.php              # 主程序（后端 API + 前端 HTML，约 6700 行）
├── .gitignore             # Git 忽略规则（php_errors.log / temp/）
├── static/
│   ├── js/
│   │   └── main.js        # 前端逻辑（约 7550 行）
│   └── css/
│       └── style.css      # 样式表（约 3280 行）
└── PROJECT_SUMMARY.md     # 本文档
```

---

## 三、数据库设计

### 3.1 表概览（26 张表）

| 表名 | 用途 | 关联 |
|------|------|------|
| `resources` | 核心：客户资源（含跟进状态） | — |
| `employees` | 员工信息 | — |
| `organizations` | 组织架构（部门/校区树形层级） | parent_id 自引用 |
| `positions` | 岗位字典 | — |
| `channels` | 来源渠道字典 | 值引用 resources.source |
| `intention_levels` | 意向等级字典 | 值引用 resources.intention_level |
| `basic_types` | 基础类型字典（课程类型/沟通方式等） | 值引用 appointments.course_type / communication_records.comm_type |
| `appointments` | 预约试听记录 | resource_id → resources.id |
| `communication_records` | 沟通记录 | resource_id → resources.id |
| `courses` | 课程信息（含小课包/低幼龄/校区权限） | — |
| `subjects` | 学科设置（两级树形） | parent_id 自引用 |
| `students` | 学员信息（含学号/学员类型） | resource_id → resources.id |
| `classes` | 班级信息（标准班/活动班） | course_id → courses.id |
| `schedules` | 排课信息（规则排课/日期排课） | class_id → classes.id |
| `classrooms` | 教室信息 | — |
| `activities` | 活动信息（含成人/学员收费框架） | — |
| `activity_campuses` | 活动适用校区及容量 | activity_id → activities.id |
| `activity_subject_deductions` | 活动扣课时规则（按学科设置） | activity_id → activities.id |
| `activity_enrollment_counts` | 活动报名人数统计缓存 | activity_id → activities.id |
| `price_plans` | 价格方案 | course_id → courses.id |
| `price_items` | 报价单明细 | plan_id → price_plans.id |
| `orders` | 交易订单（含活动订单） | student_id → students.id, course_id → courses.id, activity_id → activities.id |
| `class_attendance` | 班级/活动考勤记录 | student_id → students.id, activity_id → activities.id |
| `parent_orders` | 父订单（汇总同一录单的所有子订单） | parent_order_no → orders.parent_order_no |
| `refund_records` | 退费记录（申请→三级审批→财务确认） | order_id → orders.id, student_id → students.id |
| `student_subject_teacher` | 学员-校区-学科-授课老师关联 | student_id → students.id, campus_id → organizations.id, subject_id → subjects.id, teacher_id → employees.id |

### 3.2 resources（资源表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| name | VARCHAR(500) | '' | 客户姓名（必填） |
| phone | VARCHAR(500) | '' | 电话（唯一性校验） |
| source | VARCHAR(500) | '' | 来源渠道 |
| source_detail | VARCHAR(500) | '' | 来源详情 |
| intention_level | VARCHAR(500) | '' | 意向等级 |
| gender | VARCHAR(500) | '' | 性别（增量字段） |
| birth_date | VARCHAR(500) | '' | 出生日期（增量字段） |
| status | VARCHAR(500) | '待跟进' | 原状态字段（已保留但前端不再展示，被跟进状态替代） |
| follow_status | VARCHAR(500) | '' | **跟进状态**：未沟通/沟通中/已邀约未试听/已试听待转化/已转化—定金/已转化—全款/无效客户，共 7 个选项 |
| assigned_to | VARCHAR(500) | '' | 归属人 |
| pool_type | VARCHAR(500) | '我的资源' | 我的资源/资源公海 |
| created_at | VARCHAR(500) | '' | 创建时间 |
| updated_at | VARCHAR(500) | '' | 更新时间 |

### 3.3 employees（员工表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| name | VARCHAR(500) | '' | 姓名（必填，唯一性校验） |
| phone | VARCHAR(500) | '' | 手机号（唯一性校验） |
| department | VARCHAR(500) | '' | 归属部门 |
| position | VARCHAR(500) | '' | 职位 |
| entry_date | VARCHAR(500) | '' | 入职日期 |
| status | VARCHAR(500) | '在职' | 在职/离职 |
| created_at | VARCHAR(500) | '' | 创建时间 |
| updated_at | VARCHAR(500) | '' | 更新时间 |

### 3.4 organizations（组织表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| name | VARCHAR(500) | '' | 组织名称（必填，同级同类型下唯一） |
| type | VARCHAR(500) | '部门' | 部门/校区 |
| parent_id | INT | 0 | 上级组织 ID（0 表示根节点） |
| sort_order | INT | 0 | 排序号 |
| created_at | VARCHAR(500) | '' | 创建时间 |

### 3.5 positions（岗位表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| name | VARCHAR(500) | — | 岗位名称（唯一约束） |
| sort_order | INT | 0 | 排序号 |
| created_at | VARCHAR(500) | '' | 创建时间 |

### 3.6 channels（渠道表）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INTEGER PK | 主键 |
| name | VARCHAR(500) | 渠道名称 |
| created_at | VARCHAR(500) | 创建时间 |

### 3.7 intention_levels（意向等级表）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INTEGER PK | 主键 |
| name | VARCHAR(500) | 等级名称 |
| sort_order | INT | 排序号（默认 0） |
| created_at | VARCHAR(500) | 创建时间 |

默认数据：A-高意向(1) / B-中意向(2) / C-低意向(3) / D-无意向(4)

### 3.8 basic_types（基础类型表）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INTEGER PK | 主键 |
| category | VARCHAR(500) | 分类标识（course_type / comm_type） |
| name | VARCHAR(500) | 类型名称 |
| sort_order | INT | 排序号（默认 0） |
| created_at | VARCHAR(500) | 创建时间 |

默认数据：课程类型（试听课/正式课体验/测评课/其他），沟通方式（电话/微信/面谈/短信）

### 3.9 appointments（预约试听表）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INTEGER PK | 主键 |
| resource_id | INT | 关联资源 ID |
| resource_name | VARCHAR(500) | 关联资源名称（冗余） |
| student_name | VARCHAR(500) | 学员姓名 |
| phone | VARCHAR(500) | 电话 |
| course_type | VARCHAR(500) | 课程类型 |
| appointment_time | VARCHAR(500) | 预约时间 |
| status | VARCHAR(500) | 已预约/已试听/已取消 |
| notes | VARCHAR(500) | 备注 |
| created_at | VARCHAR(500) | 创建时间 |

### 3.10 communication_records（沟通记录表）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INTEGER PK | 主键 |
| resource_id | INT | 关联资源 ID |
| resource_name | VARCHAR(500) | 关联资源名称（冗余） |
| content | VARCHAR(500) | 沟通内容 |
| comm_type | VARCHAR(500) | 沟通方式（电话/微信/面谈/短信） |
| created_at | VARCHAR(500) | 创建时间 |

### 3.11 courses（课程表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| name | VARCHAR(500) | — | 课程名称（必填） |
| subject | VARCHAR(500) | '' | 所属学科 |
| grade | VARCHAR(500) | '' | 适用年级 |
| description | VARCHAR(500) | '' | 课程描述 |
| small_package | VARCHAR(500) | '' | 小课包标记（增量字段）。空字符串或 `'否'` 表示非小课包，`'是'`/`'1'`/`'小课包'` 表示是小课包。前端通过 `isSmallPackage()` 函数判断 |
| toddler | VARCHAR(500) | '' | 低幼龄标记（增量字段） |
| campus_permission | VARCHAR(500) | '' | 校区权限控制（增量字段） |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

### 3.12 subjects（学科表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| name | VARCHAR(500) | — | 学科名称（必填） |
| parent_id | INT | 0 | 上级学科 ID（0 表示一级学科） |
| sort_order | INT | 0 | 排序号 |

支持两级树形结构：一级学科 → 二级学科。

### 3.13 students（学员表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| student_no | VARCHAR(500) | '' | **学号**（10 位数字，唯一，自动生成） |
| resource_id | INT | — | 关联资源 ID（可为空） |
| name | VARCHAR(500) | — | 学员姓名（必填） |
| phone | VARCHAR(500) | — | 电话（唯一约束） |
| source | VARCHAR(500) | — | 来源（保留字段，前端不再展示） |
| follow_status | VARCHAR(500) | — | 跟进状态（保留字段，前端不再展示） |
| student_type | VARCHAR(500) | '小课包' | **学员类型**：小课包 / 常规。新增学员默认小课包，存在非小课包订单时自动升级为常规（不可逆） |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

### 3.14 classes（班级表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| course_id | INT | — | 关联课程 ID（必填） |
| name | VARCHAR(500) | — | 班级名称（必填） |
| class_type | VARCHAR(500) | '' | 班级类型：标准班 / 活动班 |
| max_students | INT | 0 | 最大招生人数 |
| lesson_hours | INT | 0 | 授课课时（必为偶数） |
| can_trial | VARCHAR(500) | '0' | 是否可试听（0/1） |
| campus | VARCHAR(500) | '' | 所属校区 |
| remark | VARCHAR(500) | '' | 备注 |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

### 3.15 schedules（排课表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| class_id | INT | — | 关联班级 ID（必填） |
| rule_type | VARCHAR(500) | '按规则排课' | 排课方式：按规则排课 / 按日期排课 |
| start_date | VARCHAR(500) | '' | 开始日期 |
| end_date | VARCHAR(500) | '' | 结束日期 |
| weekdays | VARCHAR(500) | '' | 星期几上课，逗号分隔（如 '1,3,5' 表示周一三五） |
| time_slots | VARCHAR(500) | '' | 每天时间段，JSON 格式存储 |
| holiday_enabled | INT | 0 | 节假日是否排课（0/1） |
| teacher | VARCHAR(500) | '' | 授课老师 |
| classroom | VARCHAR(500) | '' | 上课教室（关联 classroom 名称） |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

### 3.16 classrooms（教室表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| name | VARCHAR(500) | — | 教室名称（唯一，必填） |
| capacity | INT | 0 | 容纳人数 |
| campus | VARCHAR(500) | '' | 所属校区 |
| remark | VARCHAR(500) | '' | 备注 |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

### 3.17 price_plans（价格方案表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| course_id | INT | — | 关联课程 ID（必填） |
| name | VARCHAR(500) | — | 方案名称（必填） |
| plan_type | VARCHAR(500) | '' | 方案类型（增量字段）：新报 / 续费 / 小课包。由课程 small_package 决定是否锁死 |
| sort_order | INT | 0 | 排序号 |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

### 3.18 price_items（报价单表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| plan_id | INT | — | 关联价格方案 ID（必填） |
| name | VARCHAR(500) | — | 报价项名称（必填） |
| lesson_count | INT | — | 课时数（必填） |
| unit_price | DECIMAL(10,2) | — | 单价（必填） |
| actual_price | DECIMAL(10,2) | — | 实际价格（必填） |
| sort_order | INT | 0 | 排序号 |

### 3.19 orders（交易订单表 / 子订单）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| order_no | VARCHAR(500) | '' | **订单号**（16 位数字，唯一，自动生成） |
| parent_order_no | VARCHAR(500) | '' | **父订单号**（16 位，同一录单的子订单共用） |
| student_id | INT | — | 关联学员 ID（必填） |
| course_id | INT | — | 关联课程 ID（必填） |
| plan_name | VARCHAR(500) | — | 价格方案名称 |
| item_name | VARCHAR(500) | — | 报价项名称 |
| order_type | VARCHAR(500) | '' | **订单类型**（增量字段）：新报 / 续费 / 小课包，从价格方案 plan_type 继承 |
| lesson_count | INT | — | 课时数 |
| actual_price | DECIMAL(10,2) | — | 实际成交价格（= cash_amount + meituan_amount） |
| cash_amount | DECIMAL(10,2) | 0 | **现金支付金额** |
| meituan_amount | DECIMAL(10,2) | 0 | **美团支付金额** |
| status | VARCHAR(500) | '已报名' | 订单状态 |
| pay_status | VARCHAR(20) | '待支付' | **支付状态**：已支付 / 待支付 / 已取消 |
| is_voided | VARCHAR(5) | '否' | **是否作废**：是 / 否 |
| refund_status | VARCHAR(10) | '正常' | **退费状态**：正常 / 退费申请中 / 已退费 |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |


### 3.20 orders（交易订单表）— 活动订单扩展

orders 表新增以下活动相关字段（2026-07-10）：

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| activity_id | INT | 0 | 关联活动 ID |
| activity_name | VARCHAR(200) | '' | 活动名称 |
| activity_campus | VARCHAR(200) | '' | 报名校区 |
| activity_adult_count | INT | 0 | 成人报名人数 |
| activity_student_count | INT | 0 | 学员报名人数 |
| adult_unit_price | DECIMAL(10,2) | 0 | 成人单价 |
| student_unit_price | DECIMAL(10,2) | 0 | 学员单价 |
| activity_fee_type | VARCHAR(20) | '' | 收费类型 |

活动订单规则：`order_type='活动'`、`course_id=0`、`parent_order_no=order_no`（自引用）。

### 3.21 activities（活动表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| name | VARCHAR(500) | — | 活动名称（必填） |
| subject_level1 | VARCHAR(200) | '' | 一级学科 |
| reg_start_date | DATE | NULL | 报名开始日期 |
| reg_end_date | DATE | NULL | 报名结束日期 |
| adult_fee_mode | VARCHAR(20) | 'fee_only' | 成人收费模式：fee_only / fee_and_deduct / deduct_only |
| student_fee_mode | VARCHAR(20) | 'fee_only' | 学员收费模式 |
| adult_price | DECIMAL(10,2) | 0 | 成人单价 |
| student_price | DECIMAL(10,2) | 0 | 学员单价 |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |
| updated_at | DATETIME | CURRENT_TIMESTAMP | 更新时间 |

### 3.22 activity_campuses（活动校区表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| activity_id | INT | — | 关联活动 ID |
| campus_name | VARCHAR(200) | '' | 校区名称 |
| max_capacity | INT | 0 | 最大容量（0=不限） |

### 3.23 activity_subject_deductions（活动扣课时规则表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| activity_id | INT | — | 关联活动 ID |
| fee_type | VARCHAR(10) | 'student' | 收费类型：adult / student |
| subject_level1 | VARCHAR(200) | '' | 学科名称 |
| deduct_lessons | INT | 1 | 每人每次扣课时数 |

### 3.24 activity_enrollment_counts（活动报名统计表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| activity_id | INT | — | 关联活动 ID |
| campus_name | VARCHAR(200) | '' | 校区名称 |
| adult_count | INT | 0 | 成人报名累计 |
| student_count | INT | 0 | 学员报名累计 |
| updated_at | DATETIME | CURRENT_TIMESTAMP | 更新时间 |

### 3.25 class_attendance（考勤表）— 活动考勤扩展

class_attendance 新增以下活动考勤字段（2026-07-10）：

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| activity_id | INT | 0 | 关联活动 ID（>0 表示活动考勤） |
| activity_order_id | INT | 0 | 关联活动订单 ID |
| adult_attended | INT | 0 | 成人实际出勤人数 |
| student_attended | INT | 0 | 学员实际出勤人数 |
| deduction_breakdown | TEXT | NULL | 扣课时明细 JSON |

### 3.20 attendance_records（上课记录表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| student_id | INT | — | 关联学员 ID（必填） |
| course_id | INT | — | 关联课程 ID（必填）。考勤写入时优先取扣课时订单对应的课程，未扣课时则取班级所属课程 |
| campus | VARCHAR(100) | NULL | 所属校区 |
| class_name | VARCHAR(500) | — | 班级名称 |
| subject_level1 | VARCHAR(500) | '' | 一级学科。考勤写入时优先取扣课时订单对应课程的学科 |
| subject_level2 | VARCHAR(500) | '' | 二级学科。同上 |
| teacher | VARCHAR(500) | '' | 授课教师 |
| lesson_date | VARCHAR(500) | — | 上课日期（必填） |
| class_time | VARCHAR(500) | '' | 上课时间 |
| status | VARCHAR(500) | '出勤' | 出勤状态：出勤 / 请假 / 缺勤 |
| notes | VARCHAR(500) | '' | 备注 |
| deducted_order_id | INT | 0 | 关联的扣课时订单 ID |
| deducted_lessons | INT | 0 | 本次扣除课时数 |
| consumed_amount | DECIMAL(10,2) | 0 | 本次课耗金额 |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

### 3.21 parent_orders（父订单表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| parent_order_no | VARCHAR(500) | — | 父订单号（16 位，唯一） |
| child_order_nos | VARCHAR(500) | — | 关联的子订单号，逗号分隔（如 "xxx,yyy,zzz"） |
| course_name | VARCHAR(500) | — | 报读课程名称 |
| total_lessons | INT | 0 | 报读总课时数（所有子订单课时之和） |
| student_name | VARCHAR(500) | — | 学员姓名 |
| phone | VARCHAR(500) | — | 手机号 |
| student_no | VARCHAR(500) | — | 学号 |
| enroll_time | VARCHAR(500) | — | 报名时间 |
| total_price | DECIMAL(10,2) | 0 | 总价格（所有子订单 actual_price 之和） |
| cash_amount | DECIMAL(10,2) | 0 | 现金总额 |
| meituan_amount | DECIMAL(10,2) | 0 | 美团总额 |
| created_at | VARCHAR(500) | '' | 创建时间 |

### 3.22 student_subject_teacher（学员-校区-学科-授课老师关联表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| student_id | INT | — | 关联学员 ID（必填） |
| campus_id | INT | — | 关联校区 ID（对应 organizations.id，必填） |
| subject_id | INT | — | 关联学科 ID（对应 subjects.id，必填） |
| teacher_id | INT | 0 | 关联授课老师 ID（对应 employees.id，0 表示未设置） |
| created_at | VARCHAR(500) | '' | 创建时间 |

唯一约束：`student_id + campus_id + subject_id` 三元组唯一（同一学员在同一校区的同一学科下只能关联一条记录）。

### 3.23 refund_records（退费记录表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INT PK | AUTO_INCREMENT | 主键 |
| order_id | INT | — | 关联子订单 ID（必填） |
| student_id | INT | — | 关联学员 ID（必填） |
| campus | VARCHAR(500) | '' | 报读校区 |
| course_name | VARCHAR(500) | '' | 课程名称 |
| total_lessons | INT | 0 | 报读课时 |
| total_amount | DECIMAL(10,2) | 0 | 报读金额 |
| consumed_lessons | INT | 0 | 消耗课时 |
| consumed_amount | DECIMAL(10,2) | 0 | 消耗金额 |
| remaining_lessons | INT | 0 | 剩余可退课时 |
| remaining_amount | DECIMAL(10,2) | 0 | 剩余可退金额 |
| custom_deduction | DECIMAL(10,2) | 0 | 自定义扣减金额 |
| actual_refund | DECIMAL(10,2) | 0 | **实退金额**（= remaining_amount - custom_deduction） |
| bank_name | VARCHAR(500) | '' | 转账银行 |
| bank_account | VARCHAR(500) | '' | 银行卡号 |
| account_holder | VARCHAR(500) | '' | 开户人 |
| refund_reason | TEXT | '' | 退费原因 |
| status | VARCHAR(20) | '待审批' | **审批状态**：待审批 / 一级审批通过 / 二级审批通过 / 已退费 / 审批驳回 |
| approval_stage | VARCHAR(10) | '一级审批' | **当前审批阶段**：一级审批 / 二级审批 / 财务确认 |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |
| updated_at | DATETIME | CURRENT_TIMESTAMP | 更新时间 |

### 3.24 表关系图

```
organizations                     employees
┌──────────────┐                 ┌──────────────┐
│ id (PK)      │                 │ id (PK)      │
│ name         │                 │ name         │
│ type         │                 │ phone        │
│ parent_id ◄──┼── 自引用         │ department   │
│ sort_order   │                 │ position     │
└──────────────┘                 └──────────────┘

positions            channels              resources              intention_levels
┌──────────┐        ┌──────────┐         ┌──────────────┐        ┌──────────────────┐
│ id (PK)  │        │ id (PK)  │         │ id (PK)      │        │ id (PK)          │
│ name     │        │ name     │◄────────│ source       │        │ name             │
│ sort_order│       └──────────┘  值引用  │ intention_level│──────►│ sort_order       │
└──────────┘                             │ follow_status │        └──────────────────┘
                                          │ gender        │
                                          │ birth_date    │
                                          └──────┬───────┘
                                                 │ resource_id
                              ┌──────────────────┼──────────────────┐
                              ▼                  ▼                  ▼
                    appointments      communication_records     students
                    ┌────────────┐   ┌──────────────────┐   ┌──────────────┐
                    │ course_type◄┼──► basic_types      │   │ id (PK)      │
                    │ ...        │   │ (category=       │   │ student_no   │
                    └────────────┘   │  course_type)    │   │ resource_id  │
                                     │ comm_type ◄──────┼───│ name         │
                                     │ (category=       │   │ phone        │
                                     │  comm_type)      │   └──────┬───────┘
                                     └──────────────────┘   ┌──────┼──────┐
                                                            │ student_id    │
                                                            ▼               ▼
                                                          orders     attendance_records

                    classes                schedules              classrooms
               ┌──────────────┐      ┌──────────────┐      ┌──────────────┐
               │ id (PK)      │      │ id (PK)      │      │ id (PK)      │
               │ course_id ───┼──►   │ class_id ────┼──►   │ name         │
               │ name         │      │ rule_type    │      │ capacity     │
               │ class_type   │      │ start_date   │      │ campus       │
               │ max_students │      │ end_date     │      │ remark       │
               │ lesson_hours │      │ weekdays     │      └──────────────┘
               │ can_trial    │      │ time_slots   │
               │ campus       │      │ holiday_enabled│
               │ remark       │      │ teacher      │
               └──────────────┘      │ classroom    │
                                     └──────────────┘

subjects                  courses              ┌──────────────────────┐  ┌──────────────────┐
┌──────────────┐        ┌──────────────┐      │ id (PK)              │  │ id (PK)          │
│ id (PK)      │        │ id (PK)      │      │ order_no             │  │ student_id       │
│ name         │        │ name         │      │ parent_order_no ─────┼──┤ course_id        │
│ parent_id ◄──┼ 自引用 │ subject      │◄──   │ student_id           │  │ lesson_date      │
│ sort_order   │        │ grade        │      │ course_id ───────────┼──┤ status           │
└──────────────┘        │ description  │      │ plan_name            │  │ notes            │
                        │ small_package│      │ item_name            │  └──────────────────┘
                        │ toddler      │      │ order_type           │
                        │ campus_perm  │      │ lesson_count         │
                        └──────┬───────┘      │ actual_price         │
                               │ course_id    │ cash_amount          │
                               ▼              │ meituan_amount       │
                        price_plans           │ status               │
                        ┌──────────────┐      └──────────┬───────────┘
                        │ id (PK)      │                 │ parent_order_no
                        │ course_id    │                 ▼
                        │ name         │           parent_orders
                        │ plan_type    │      ┌──────────────────────┐
                        │ sort_order   │      │ id (PK)              │
                        └──────┬───────┘      │ parent_order_no      │
                               │ plan_id      │ child_order_nos      │
                               ▼              │ course_name          │
                        price_items           │ total_lessons        │
                        ┌──────────────┐      │ student_name         │
                        │ id (PK)      │      │ phone                │
                        │ plan_id      │      │ student_no           │
                        │ name         │      │ enroll_time          │
                        │ lesson_count │      │ total_price          │
                        │ unit_price   │      │ cash_amount          │
                        │ actual_price │      │ meituan_amount       │
                        │ sort_order   │      └──────────────────────┘
                        └──────────────┘
```

---


## 四、活动报名全流程（2026-07-10 新增）

### 4.1 报名流程

```
选学员 → 选校区 → 选「报名课程」或「报名活动」
  ├─ 报名课程 → 现有流程（选课程→选方案→支付）
  └─ 报名活动 → 选活动→填人数→支付
```

### 4.2 活动订单

- order_type = '活动'，course_id = 0
- parent_order_no = order_no（自引用）
- 展示活动名称、成人/学员人数、支付方式
- 可作废（void_order 兼容活动订单）

### 4.3 活动考勤

- 从学员详情「报读活动」Tab 或考勤「活动考勤」Tab 进入
- 选择实际出勤成人数、学员数
- 根据活动扣课规则自动计算扣课时（出勤人数 × 每人每次扣课数）
- 选择扣课来源课包（仅显示有剩余课时的）
- 考勤状态同步到学员详情和活动考勤列表

### 4.4 活动课耗

- 考勤模块「活动课耗」Tab 展示所有活动考勤记录
- 含学员、活动名称、考勤日期、消耗课时、扣除课包、课耗金额

### 4.5 相关 API

| API | 方法 | 说明 |
|-----|------|------|
| pay_activity_enroll | POST | 活动报名支付 |
| list_activities_for_enroll | GET | 按校区筛选活动 |
| get_student_activities | GET | 学员报读活动列表 |
| save_activity_attendance | POST | 活动考勤保存 |
| delete_activity_attendance | POST | 删除活动考勤 |
| list_activity_consumptions | GET | 活动课耗列表 |
| get_activity_deduction_rules | GET | 活动扣课规则 |
| get_activity_enroll_detail | GET | 活动报名详情 |

## 四、后端 API 完整列表

所有 API 通过 `?action=<name>` 路由，统一返回 JSON。共 **88 个** action。

### 4.1 资源管理（9 个）

| action | 方法 | 说明 |
|--------|------|------|
| `get_resources` | GET | 分页查询，支持 pool_type / keyword / follow_status / name / phone / source / created_start / created_end / resource_id 多条件筛选 |
| `add_resource` | POST | 新增资源（含 gender / birth_date / follow_status，手机号唯一性校验） |
| `update_resource` | POST | **动态字段更新**（仅更新 `$input` 中传入的字段，避免覆盖未传入字段）；手机号唯一性校验（排除自身） |
| `delete_resource` | POST | 删除资源（级联删除关联预约和沟通记录） |
| `batch_import` | POST | 批量导入（支持 JSON 模式和 Excel 上传模式，含 follow_status 列，手机号唯一性校验） |
| `batch_assign` | POST | 批量分配归属人（左树右表 UI：左侧组织树选择部门 → 右侧员工列表 → 支持平均分配） |
| `batch_pool` | POST | 批量移入公海 / 领取 |
| `export_resources` | GET | 导出 CSV（UTF-8 BOM，含跟进状态列，共 11 列） |
| `download_template` | GET | 下载资源导入模板 .xlsx（纯表头，无提示文字：姓名/电话/来源/来源详情/意向等级/归属人/性别/出生日期/跟进状态） |

### 4.2 员工管理（6 个）

| action | 方法 | 说明 |
|--------|------|------|
| `get_employees` | GET | 分页查询，支持 keyword / department / status 筛选 |
| `add_employee` | POST | 新增员工（姓名必填，姓名+手机号唯一性校验，两者重复同时提示） |
| `update_employee` | POST | **动态字段更新**（仅更新传入字段）；姓名+手机号唯一性校验（排除自身） |
| `delete_employee` | POST | 删除员工 |
| `batch_import_employees` | POST | 批量导入（JSON 模式 + Excel 上传模式，表头：姓名/手机号/部门/职位/入职日期/状态） |
| `export_employees` | GET | 导出 CSV（UTF-8 BOM，8 列：姓名/手机号/部门/职位/入职日期/状态/创建时间/更新时间） |

### 4.3 岗位管理（4 个）

| action | 方法 | 说明 |
|--------|------|------|
| `list_positions` | GET | 岗位列表（按 sort_order 升序） |
| `add_position` | POST | 添加岗位（名称唯一约束） |
| `update_position` | POST | 编辑岗位（名称+排序） |
| `delete_position` | POST | 删除岗位 |

### 4.4 组织管理（4 个）

| action | 方法 | 说明 |
|--------|------|------|
| `list_organizations` | GET | 返回 `{tree, flat}` 树形结构 + 扁平列表（按 type 分组） |
| `add_organization` | POST | 新增组织（name 必填，type 必填为部门/校区，同级同 type 下 name 不重复） |
| `update_organization` | POST | 编辑组织（动态字段更新，空值不覆盖；校验不能将自身设为上级） |
| `delete_organization` | POST | 删除组织（有子节点时拒绝，提示先删除子节点） |

### 4.5 预约试听 & 沟通记录（6 个）

| action | 方法 | 说明 |
|--------|------|------|
| `get_appointments` | GET | 分页查询预约（支持 keyword / status 筛选） |
| `add_appointment` | POST | 新增预约 |
| `update_appointment` | POST | 编辑预约 |
| `delete_appointment` | POST | 删除预约 |
| `get_communications` | GET | 查询某资源的沟通记录（按 resource_id） |
| `add_communication` | POST | 添加沟通记录（**同步更新资源 follow_status**） |

### 4.6 渠道设置（4 个）

| action | 方法 | 说明 |
|--------|------|------|
| `list_channels` | GET | 渠道列表 |
| `add_channel` | POST | 添加渠道（重名校验） |
| `update_channel` | POST | 改名（事务同步 resources.source，返回受影响的资源数） |
| `delete_channel` | POST | 删除渠道（资源保留旧值，前端显示"已删除"标注） |

### 4.7 意向等级设置（4 个）

| action | 方法 | 说明 |
|--------|------|------|
| `list_intention_levels` | GET | 意向等级列表（按 sort_order 升序） |
| `add_intention_level` | POST | 添加意向等级（含 sort_order） |
| `update_intention_level` | POST | 改名/改排序（事务同步 resources.intention_level） |
| `delete_intention_level` | POST | 删除等级（资源保留旧值） |

### 4.8 基础类型设置（4 个）

| action | 方法 | 说明 |
|--------|------|------|
| `list_basic_types` | GET | 按 category 查询类型列表（?category=course_type 或 comm_type） |
| `add_basic_type` | POST | 添加类型（category + name + sort_order，同类别重名校验） |
| `update_basic_type` | POST | 改名/改排序（事务同步 appointments.course_type 或 communication_records.comm_type） |
| `delete_basic_type` | POST | 删除类型（关联数据保留旧值） |

### 4.9 课程管理（5 个）

| action | 方法 | 说明 |
|--------|------|------|
| `list_courses` | GET | 课程列表（含小课包/低幼龄/校区权限字段） |
| `add_course` | POST | 新增课程（name 必填） |
| `update_course` | POST | 编辑课程（支持更新 small_package / toddler / campus_permission） |
| `delete_course` | POST | 删除课程 |
| `export_courses` | GET | 导出课程 CSV |

### 4.10 价格方案（3 个）

| action | 方法 | 说明 |
|--------|------|------|
| `list_price_plans` | GET | 按课程查询价格方案列表（含关联报价单 price_items 和 plan_type） |
| `save_price_plan` | POST | 新增/编辑价格方案（含批量保存报价单明细，接收并写入 plan_type） |
| `delete_price_plan` | POST | 删除价格方案（级联删除关联报价单） |

### 4.11 学科设置（5 个）

| action | 方法 | 说明 |
|--------|------|------|
| `list_subjects` | GET | 学科列表（两级树形，按 sort_order 排序） |
| `add_subject` | POST | 添加学科（支持 parent_id 指定上级学科） |
| `update_subject` | POST | 编辑学科 |
| `delete_subject` | POST | 删除单条学科 |
| `batch_delete_subjects` | POST | 批量删除学科 |

### 4.12 学员管理（7 个）

| action | 方法 | 说明 |
|--------|------|------|
| `list_students` | GET | 学员列表（分页，支持 keyword / campus / student_filter / subject_level1 筛选；含校区、所在班级、学科剩余课时、授课老师列；在册学员筛选规则：student_type=常规 + 指定校区下剩余课时>0，可选限定学科） |
| `get_student` | GET | 查询单个学员详情（含订单/汇总/sst_records 校区-学科-老师关联记录） |
| `add_student` | POST | 新增学员（自动生成 10 位学号，手机号唯一约束，默认 student_type='小课包'，支持 sst_items 保存校区-学科-老师关联） |
| `update_student` | POST | 编辑学员信息（仅姓名+手机号可编辑，自动重算 student_type，支持 sst_items 全量替换关联） |
| `delete_student` | POST | 删除学员（级联删除 orders 和 student_subject_teacher 关联） |
| `create_student_from_resource` | POST | 从资源创建学员（按手机号查重，存在则复用，不存在则新建，返回 student_id） |
| `get_student_courses` | GET | 获取学员已报读课程列表（基于 orders 表关联查询，过滤 is_voided='否' 的订单，含子订单号 order_no 和退款状态 refund_status） |

### 4.13 班级管理（4 个）

| action | 方法 | 说明 |
|--------|------|------|
| `list_classes` | GET | 班级列表（支持 keyword 搜索） |
| `add_class` | POST | 新增班级（授课课时必须为偶数，前后端双重校验） |
| `update_class` | POST | 编辑班级（动态字段更新，授课课时偶数校验） |
| `delete_class` | POST | 删除班级 |

### 4.13a 校区-学科-老师关联（2 个）

| action | 方法 | 说明 |
|--------|------|------|
| `get_campus_subjects` | GET | 根据 campus_id 获取该校区下所有一级学科（从 courses 表 campus_permission 匹配） |
| `get_teachers` | GET | 获取所有在职教师列表（is_teacher='是' 且 status!='离职'，按部门/姓名排序） |

### 4.14 排课管理（5 个）

| action | 方法 | 说明 |
|--------|------|------|
| `list_schedules` | GET | 排课列表（按 class_id 查询） |
| `get_schedule` | GET | 查询单个排课详情 |
| `add_schedule` | POST | 新增排课（按规则排课/按日期排课） |
| `update_schedule` | POST | 编辑排课 |
| `delete_schedule` | POST | 删除排课 |

### 4.15 教室管理（4 个）

| action | 方法 | 说明 |
|--------|------|------|
| `list_classrooms` | GET | 教室列表（支持 keyword 搜索） |
| `add_classroom` | POST | 新增教室（名称唯一校验） |
| `update_classroom` | POST | 编辑教室（含自名排除重复校验） |
| `delete_classroom` | POST | 删除教室 |

### 4.16 报名 & 订单（6 个）

| action | 方法 | 说明 |
|--------|------|------|
| `get_course_plans` | GET | 按 course_id 查询课程的所有价格方案及其报价单明细（含 plan_type） |
| `pay_enroll` | POST | **核心报名支付**：按方案下所有报价单逐条生成子订单（自动生成 order_no 和 parent_order_no，含 pay_status='待支付'、is_voided='否'、refund_status='正常'），支持现金+美团双支付方式，服务端校验金额合计=总额，采用"逐个填满"分配策略。从价格方案继承 plan_type 写入所有子订单的 order_type，若课程 small_package 非空则强制为"小课包" |
| `enroll_course` | POST | 学员直接报名课程（单条订单，含 pay_status='待支付'、is_voided='否'、refund_status='正常'） |
| `enroll_from_resource` | POST | 从资源入口报名：将资源转为学员并生成订单（保留兼容） |
| `create_student_from_resource` | POST | 从资源创建学员记录（供 panel-enroll 前端调用） |
| `list_orders` | GET | 订单列表（17 列：订单号/父订单号/学号/编号/学员姓名/课程名称/价格方案/报价单名称/订单类型/课时数量/订单金额/现金/美团/支付状态/是否作废/状态/报名时间），含 `payment_summary` 汇总和 order_type 字段，支持 keyword 搜索及 pay_status/is_voided 筛选 |
| `void_order` | POST | **作废订单**：校验 consumed_lessons==0（无课耗），将 is_voided 设为'是'，作废后该订单对应报读课程从学员详情中消失 |

### 4.17 退费管理（5 个）

| action | 方法 | 说明 |
|--------|------|------|
| `submit_refund` | POST | **提交退费申请**：校验 refund_status='正常' 且 consumed_lessons < lesson_count，自动计算剩余可退课时/金额（remaining_amount = actual_price × (lesson_count - consumed_lessons) / lesson_count），实退金额 = remaining_amount - custom_deduction（≥0），写入 refund_records（status='待审批', approval_stage='一级审批'），更新 orders.refund_status='退费申请中' |
| `list_refund_records` | GET | 退费记录列表（分页，支持 keyword 搜索、status 筛选、日期范围筛选，LEFT JOIN orders 联查订单号/学员/课程信息） |
| `approve_refund` | POST | **退费审批**：入参 id + action(approve/reject) + approver + reject_reason。已退费/驳回状态拒绝继续审批。一级审批通过→approval_stage='二级审批',status='一级审批通过'；二级审批通过→approval_stage='财务确认',status='二级审批通过'；财务确认通过→status='已退费'，同步更新 orders.refund_status='已退费'、consumed_lessons=lesson_count（剩余课时归零）；任意阶段驳回→status='审批驳回' |
| `cancel_refund` | POST | **撤销退费申请**：校验状态非已退费/审批驳回，恢复订单 refund_status='正常'，删除 refund_records 记录 |
| `get_refund_record` | GET | 查询单条退费记录详情 |

### 4.18 考勤 / 上课记录（4 个）

| action | 方法 | 说明 |
|--------|------|------|
| `list_attendance` | GET | 按 student_id 查询上课记录列表 |
| `add_attendance` | POST | 新增上课记录（student_id / course_id / lesson_date / status / notes） |
| `update_attendance` | POST | 编辑上课记录（支持任意字段动态更新） |
| `delete_attendance` | POST | 删除上课记录 |

### 4.19 父订单（1 个）

| action | 方法 | 说明 |
|--------|------|------|
| `list_parent_orders` | GET | 父订单列表（分页，支持 keyword 搜索学员名/课程名/父订单号/学号） |

### 4.20 统计（1 个）

| action | 方法 | 说明 |
|--------|------|------|
| `get_stats` | GET | 返回 {my_resources, sea_resources, appointments, employees, courses} 五个计数 |

---

## 五、前端设计

### 5.1 页面布局

```
┌──────────┬──────────────────────────────────────┐
│ 左侧边栏  │              右侧内容区                │
│ 240px    │                                      │
│          │  ┌──────────────────────────────────┐│
│ 系统Logo │  │ 面板头部（标题 + 统计徽章）        ││
│          │  ├──────────────────────────────────┤│
│▼ 市场管理│  │ 功能按钮组（卡片网格布局）          ││
│ ├ 我的   │  ├──────────────────────────────────┤│
│ │ 资源   │  │ 工具栏（筛选 + 搜索）              ││
│ ├ 预约   │  ├──────────────────────────────────┤│
│ ├ 资源   │  │ 数据表格（无边设计）               ││
│ │ 公海   │  ├──────────────────────────────────┤│
│ └▼基础   │  │ 分页控件                          ││
│    设置   │  └──────────────────────────────────┘│
│   ├ 渠道 │                                      │
│   ├ 意向 │                                      │
│   └ 基础 │                                      │
│▼ 教务管理│                                      │
│ ├ 课程   │                                      │
│ │ 管理   │                                      │
│ ├ 学员   │                                      │
│ │ 管理   │                                      │
│ ├ 班级   │                                      │
│ │ 管理   │                                      │
│ ├ 交易   │                                      │
│ │ 订单   │                                      │
│ ├ 报名   │                                      │
│ │ 详情   │  (panel-enroll，隐藏面板)              │
│ └▼基础   │                                      │
│    设置   │                                      │
│   ├ 学科 │                                      │
│   └ 教室 │                                      │
│▼ 员工管理│                                      │
│ ├ 员工   │                                      │
│ │ 名册   │                                      │
│ ├ 岗位   │                                      │
│ │ 管理   │                                      │
│ └ 组织   │                                      │
│    管理   │                                      │
│ 统计数字 │                                      │
└──────────┴──────────────────────────────────────┘
```

### 5.2 导航结构（三大一级模块）

| 一级模块 | 二级菜单 | 三级菜单 | 面板 ID |
|----------|----------|----------|---------|
| **市场管理** | 我的资源 | — | `panel-my-resources` |
| | 预约试听名单 | — | `panel-appointments` |
| | 资源公海 | — | `panel-sea-pool` |
| | 基础设置 | 渠道设置 | `panel-channel-settings` |
| | | 意向等级设置 | `panel-intention-level-settings` |
| | | 基础类型设置 | `panel-basic-type-settings` |
| **教务管理** | 课程管理 | — | `panel-courses` |
| | 学员管理 | — | `panel-students` |
| | 班级管理 | — | `panel-classes` |
| | 交易订单 | — | `panel-orders` |
| | 报名详情 | — | `panel-enroll`（隐藏面板，通过按钮跳转） |
| | 工作记录 | 退费记录 | `panel-work-log` |
| | | 课程记录 | `panel-work-log`（预留） |
| | 基础设置 | 学科设置 | `panel-subjects` |
| | | 教室管理 | `panel-classrooms` |
| **员工管理** | 员工名册 | — | `panel-employees` |
| | 岗位管理 | — | `panel-position-settings` |
| | 组织管理 | — | `panel-org` |

### 5.3 十八个面板

| 面板 | 功能 | 关键特性 |
|------|------|----------|
| 我的资源 | CRUD、批量导入/分配/移入公海、预约、沟通、导出 | 跟进状态行内编辑（点击标签下拉选择 7 种状态）、姓名/手机号/渠道/创建时间/跟进状态筛选 + 搜索按钮手动触发 + 空状态提示；**归属人**列显示员工姓名；提供**资源报名入口**按钮（跳转 panel-enroll） |
| 预约试听名单 | 预约记录增删改查、状态筛选 | 课程类型下拉来自 basic_types |
| 资源公海 | 查看、领取（单个/批量）、导出 | 搜索框实时筛选 |
| 渠道设置 | 渠道字典增删改，双击编辑 | 改名事务同步 resources，重名校验 |
| 意向等级设置 | 意向等级增删改，支持排序号 | 改名/改排序事务同步 resources |
| 基础类型设置 | 课程类型 + 沟通方式 Tab 切换，增删改排序 | 改名事务同步 appointments/communication_records |
| **课程管理** | 课程 CRUD、价格方案、报价单、导出 | **价格方案**：每门课程可配置多个价格方案，每个方案包含多条报价单（课时数、单价、实际价格），支持设置方案类型（新报/续费/小课包）；**小课包**标记；**低幼龄**标记；**校区权限**控制字段 |
| **学员管理** | 学员 CRUD、搜索、详情页（3 标签页） | 列表列：学号/姓名/手机号/学员类型/校区/所在班级/学科剩余课时/授课老师/操作。学员类型自动计算（小课包/常规）。校区取自该学员报过课程的订单去重拼接。授课老师列展示校区-学科-老师关联，按校区筛选时仅展示当前校区记录。学员详情页：标签页布局（报读课程 / 交易订单 / 上课记录），报读课程表格含状态列（正常/退费申请中/已退费）和退费操作按钮，列表页提供**报名**按钮跳转 panel-enroll |
| **班级管理** | 班级 CRUD、排课入口 | 班级列表表格（ID/名称/关联课程/班级类型/招生人数/授课课时/是否可试听/校区/备注/创建时间/操作-排课/编辑/删除）+ 搜索框 + 新增班级按钮；新增/编辑时授课课时必须为偶数（前端+后端双重校验） |
| **交易订单** | 订单列表查看（17 列） | 列：订单号、父订单号、学号、编号、学员姓名、课程名称、价格方案、报价单名称、订单类型、课时数量、订单金额、现金、美团、支付状态、是否作废、状态、报名时间；支付状态列以三色标签展示（已支付=绿/待支付=橙/已取消=灰），是否作废列（是=红/否=-）；订单类型列以三色标签展示（新报=蓝/续费=绿/小课包=橙）；列表顶部筛选栏含支付状态和是否作废下拉筛选；列表顶部**支付方式汇总卡片**（现金/美团/总计）；支持 keyword 搜索 |
| **报名详情** | 独立报名流程页面（panel-enroll） | 展示学员/资源姓名+手机号（只读）→ 选择课程 → 展示价格方案卡片（含类型标签：新报=蓝/续费=绿/小课包=橙）→ 选中方案展示报价单明细表格 + 合计金额 → **支付方式区域**（现金+美团输入框，实时校验金额匹配）→ 确认支付 → 逐条生成子订单（逐个填满分配策略，所有子订单继承方案 plan_type）+ 父订单 → 返回来源页 |
| **工作记录** | 双标签页（退费记录 / 课程记录） | **退费记录**标签：表格（订单号/学员/课程/报读课时/消耗课时/剩余课时/报读金额/实退金额/状态/申请时间/操作），状态颜色标签（待审批=橙/一级审批通过=蓝/二级审批通过=蓝/已退费=绿/审批驳回=红），支持 status 和日期筛选，操作列含审批按钮和查看详情；**审批弹窗**：三步审批进度条（当前步骤高亮），审批人输入框，通过/驳回（需填写驳回原因）；**课程记录**标签：预留空 |
| **学科设置** | 两级学科树增删改、批量删除 | 一级学科 → 二级学科，支持拖拽排序 |
| **教室管理** | 教室 CRUD | 教室列表表格（名称/容纳人数/所属校区/备注/创建时间/操作-编辑/删除）+ 搜索框 + 新增教室按钮；名称唯一校验，编辑时排除自身重复 |
| **员工名册** | 员工 CRUD、批量导入/导出、部门/状态筛选 | 姓名+手机号双重唯一性校验，重复字段输入框红色高亮；**员工名册**列显示归属部门 |
| **岗位管理** | 岗位字典增删改 | 名称唯一约束，排序号 |
| **组织管理** | 树形组织架构（部门+校区两组独立树），节点新增/编辑/删除 | 左侧树视图（展开折叠 + 类型标签蓝/橙）+ 右侧详情卡片，有子节点禁止删除 |

### 5.4 筛选功能（我的资源）

"我的资源"面板工具栏左侧新增精细化筛选控件：

| 控件 | 类型 | ID | 说明 |
|------|------|-----|------|
| 姓名 | `<input>` | `filter-name-my` | 模糊匹配 |
| 手机号 | `<input>` | `filter-phone-my` | 模糊匹配 |
| 渠道 | `<select>` | `filter-source-my` | 精确匹配，选项来自 channels 表 |
| 创建时间起 | `<input type="date">` | `filter-created-start-my` | 范围筛选起始 |
| 创建时间止 | `<input type="date">` | `filter-created-end-my` | 范围筛选截止 |
| 跟进状态 | `<select>` | `filter-follow-status-my` | 7 种状态精确匹配 |

工具栏右侧保留关键字搜索输入框 + **搜索按钮**（`btn btn-primary btn-sm`），点击搜索按钮手动触发查询。

### 5.5 批量分配（左树右表 + 平均分配）

批量分配归属人采用"左树右表"布局：
- **左侧**：组织树视图，支持展开/折叠，点击选中部门后右侧加载该部门及子部门下的员工列表
- **右侧**：员工列表，支持勾选多个员工 → 点击"平均分配"按钮 → 系统按员工数量将选中的资源平均分配，余数依次顺延
- 也支持直接指定单个归属人

### 5.6 跟进状态行内编辑

表格中"跟进状态"列显示为彩色标签（`follow-status-tag`），7 种状态各有独立 CSS 类颜色标记。**点击标签**弹出下拉菜单，选择后即时通过 `update_resource`（仅传 `follow_status` 字段）更新，不刷新页面。

跟进状态选项（7 个）：`未沟通` / `沟通中` / `已邀约未试听` / `已试听待转化` / `已转化—定金` / `已转化—全款` / `无效客户`

### 5.7 员工管理筛选

| 控件 | 类型 | ID | 说明 |
|------|------|-----|------|
| 姓名 | `<input>` | `filter-emp-name` | 模糊匹配（合并到 keyword 参数） |
| 部门 | `<select>` | `filter-emp-dept` | 精确匹配，选项从当前结果动态提取 |
| 状态 | `<select>` | `filter-emp-status` | 在职/离职 |

### 5.8 空状态设计

数据表格无结果时显示统一空状态：
- 紫色 SVG 搜索图标
- 主标题："无查询结果"
- 副标题："调整筛选条件后重新搜索"
- 各面板各自的空状态提示

---

## 六、关键业务规则

### 6.1 数据一致性

| 场景 | 规则 |
|------|------|
| 渠道改名 | 事务内同步更新 resources.source，返回 `updated_resources` 计数 |
| 意向等级改名 | 事务内同步更新 resources.intention_level |
| 基础类型改名 | 事务内同步更新 appointments.course_type 或 communication_records.comm_type |
| 渠道删除 | 仅删配置，资源保留旧值（前端下拉显示"（已删除）"标注） |
| 意向等级删除 | 仅删配置，资源保留旧值（前端下拉显示"（已删除）"标注） |
| 基础类型删除 | 仅删配置，关联数据保留旧值 |
| 资源删除 | 级联删除关联的预约和沟通记录 |
| 价格方案删除 | 级联删除关联的报价单 |

### 6.2 动态字段更新

`update_resource` 和 `update_employee` 均采用动态字段更新策略：
- 仅更新 `$input` 中实际传入的字段（从 `$allowedFields` 白名单中过滤）
- 未传入的字段保持数据库原值不变
- 避免前端只传部分字段时意外覆盖其他字段为空

### 6.3 唯一性校验

| 表 | 校验字段 | 触发场景 | 行为 |
|------|----------|----------|------|
| resources | phone（非空时） | add_resource / update_resource / batch_import | 返回错误"手机号已存在，请勿重复录入"，前端输入框红色高亮 |
| employees | name | add_employee / update_employee / batch_import_employees | 返回错误"姓名已存在，请勿重复录入" |
| employees | phone（非空时） | add_employee / update_employee / batch_import_employees | 返回错误"手机号已存在，请勿重复录入" |
| positions | name | add_position / update_position | 返回错误，唯一约束 |
| students | phone | add_student / update_student | 返回错误，唯一约束 |

- 姓名和手机号同时重复时，两个错误合并提示（分号分隔）
- 编辑时唯一性校验自动排除自身 ID

### 6.4 批量导入

**资源导入**：
- 支持 .xlsx / .xls Excel 上传和 JSON 模式
- Excel 模式：根据中文表头自动匹配列（姓名/电话/来源/来源详情/意向等级/归属人/性别/出生日期/跟进状态）
- 姓名和手机号必填，空行自动跳过
- 来源渠道和意向等级必须在已配置列表中
- 模板 .xlsx 纯表头，无提示文字
- 导入后状态统一设为"待跟进"

**员工导入**：
- 支持 .xlsx / .xls Excel 上传和 JSON 模式
- 表头：姓名/手机号（或电话）/部门/职位/入职日期/状态
- 姓名必填
- 姓名+手机号双重唯一性校验
- 模板为前端动态生成的 CSV（含示例数据行）

### 6.6 订单作废规则

| 场景 | 规则 |
|------|------|
| 作废条件 | 仅当订单 consumed_lessons==0（未产生课耗）时可作废 |
| 作废效果 | 将 orders.is_voided 设为'是'，该订单对应报读课程从学员详情中消失 |
| 历史数据处理 | 历史美团/现金订单自动标记为 pay_status='已支付' |
| 支付状态流转 | 新增订单默认 pay_status='待支付'，支付后更新为'已支付'，取消后更新为'已取消' |
| 前端筛选 | 订单列表支持 pay_status（已支付/待支付/已取消）和 is_voided（是/否）筛选，学员详情报读课程仅显示 is_voided='否' 的有效订单 |
| 考勤排除 | 考勤重算课时消耗时自动跳过 refund_status='已退费' 的订单，防止 consumed_lessons 被覆盖归零 |

### 6.7 退费管理规则

| 场景 | 规则 |
|------|------|
| 退费条件 | 仅 refund_status='正常' 且 consumed_lessons < lesson_count（有剩余课时）的订单可发起退费 |
| 金额计算 | remaining_amount = actual_price × (lesson_count - consumed_lessons) / lesson_count；actual_refund = remaining_amount - custom_deduction，不能为负 |
| 审批流程 | 三步逐级审批：一级审批 → 二级审批 → 财务确认，必须按序完成不可跳过；任意阶段可驳回（需填写驳回原因），驳回后 status='审批驳回' |
| 退费生效 | 财务确认通过后：orders.refund_status='已退费'，orders.consumed_lessons=lesson_count（剩余课时归零），refund_records.status='已退费' |
| 状态流转 | 正常 → 退费申请中（提交申请）→ 已退费（审批通过）/ 正常（驳回后可重新申请） |
### 6.5 导出

**资源导出**：
- 根据当前 `pool_type` 和筛选条件导出

**员工导出**：
- 文件名：`员工导出_YYYYmmdd_HHMMSS.csv`
- 表头：姓名、手机号、部门、职位、入职日期、状态、创建时间、更新时间（共 8 列）

**课程导出**：
- 文件名：`课程导出_YYYYmmdd_HHMMSS.csv`
- 表头含小课包/低幼龄/校区权限等字段

### 6.6 跟进状态流转

```
未沟通 → 沟通中 → 已邀约未试听 → 已试听待转化 → 已转化—定金 → 已转化—全款
  ↓        ↓          ↓              ↓
  └────────┴──────────┴──────────────┴── 无效客户
```

添加沟通记录时，在弹窗中可选择同步更新资源的 `follow_status`。原 `status` 字段保留在数据库但前端不再展示。

### 6.7 资源池流转

```
我的资源 ──移入公海──► 资源公海 ──领取──► 我的资源
```

`batch_pool` action 通过 `pool_type` 参数同时支持"移入公海"和"领取"两个方向。

### 6.8 报名支付流程（panel-enroll）

报名流程已从弹窗改造为独立的报名详情页面 `panel-enroll`，支持学员报名和资源报名两种入口：

**学员报名入口**：学员列表/学员详情页点击"报名" → 跳转 panel-enroll（mode='student'）
**资源报名入口**：我的资源列表点击"报名" → 跳转 panel-enroll（mode='resource'）

流程：
1. 展示学员/资源姓名和手机号（只读），加载课程下拉列表
2. 选择课程 → 自动展示该课程的所有价格方案卡片（含类型标签：新报=蓝/续费=绿/小课包=橙）
3. 点击方案卡片 → 展示报价单明细表格（报价项名称/课时数/单价/实际价格）+ 底部合计金额
4. 支付方式区域：现金输入框 + 美团输入框，默认现金=总金额/美团=0，实时校验金额合计是否匹配
5. 点击"确认支付"：
   - 资源模式：先调用 `create_student_from_resource` 创建学员记录
   - 调用 `pay_enroll`：按方案下所有报价单逐条生成子订单（每个报价单项 → 1 条订单）
   - 从选中价格方案读取 `plan_type`，所有子订单的 `order_type` 继承该类型
   - 若课程 `small_package` 非空且为有效小课包标识值，则强制 `plan_type` 为 `'小课包'`
   - 自动生成 16 位 `order_no`（子订单号）和 `parent_order_no`（父订单号，同一录单共用）
   - 同时插入一条 `parent_orders` 汇总记录
   - 支付成功 → 返回来源页面
   - 支付完成后自动重算学员类型：存在非小课包订单则升级为"常规"

### 6.9 支付方式与分配策略

- 支持两种支付方式：**现金** + **美团**，可合并支付
- 前后端双重校验：两个输入框金额之和必须等于合计金额
- **"逐个填满"分配策略**：
  - 依次处理每个报价单项，先用现金余额填充，不足再用美团余额补充
  - 示例：报价单 A=5000, B=3000，现金=4000，美团=4000
    - A 订单：现金 4000 + 美团 1000（现金耗尽，美团余 3000）
    - B 订单：现金 0 + 美团 3000（美团耗尽）
  - 每个报价单项只生成一笔订单，订单数 = 报价单项数
  - 每笔订单同时记录 `cash_amount` 和 `meituan_amount`

### 6.10 父订单体系

- 同一录单操作生成的所有子订单归属同一个父订单
- `orders.parent_order_no`：16 位父订单号，同批次子订单共用
- `parent_orders` 表：汇总父订单信息（课程名称、总课时、总价格、学员信息、现金/美团总额、子订单号列表逗号拼接）
- 交易订单列表展示子订单（15 列），含父订单号列

### 6.11 学员详情页标签页

学员详情页（panel-students 详情视图）采用标签页布局，三个标签页：

| 标签页 | 内容 | 数据来源 |
|--------|------|----------|
| 报读课程 | 该学员已报读的课程列表 | `get_student_courses`（基于 orders 表） |
| 交易订单 | 该学员的交易订单列表（15 列，与 panel-orders 一致） | `list_orders`（按 student_id 筛选） |
| 上课记录 | 该学员的出勤记录，支持新增/编辑/删除。列顺序：校区 → 课程 → 一级学科 → 二级学科 → 班级 → 授课教师 → 上课日期 → 上课时间 → 考勤时间 → 出勤状态 → 消耗课时 → 课耗金额。出勤状态三色标签：出勤（绿）/ 请假（橙）/ 缺勤（红） | `attendance_records` 表 + 对应 CRUD API |

标签切换纯 JS 实现，不刷新页面。

### 6.12 编号生成规则

- **学号（student_no）**：10 位数字 = `time()` 末 8 位 + 2 位随机数，带唯一性冲突重试。新建学员时自动生成。
- **订单号（order_no）**：16 位数字 = `time()` 末 10 位 + 6 位随机数，带唯一性冲突重试。新建订单时自动生成。
- **父订单号（parent_order_no）**：16 位数字，生成规则同订单号。一次录单生成一个，所有子订单共用。
- 已有数据的行默认为空，后续新建自动填充。

### 6.13 课程价格体系

```
课程 (courses)
  └── 价格方案 (price_plans)：每门课程可有多个方案（如"标准方案"、"暑期特惠"），每个方案有 plan_type（新报/续费/小课包）
        └── 报价单 (price_items)：每个方案含多条报价项（课时数 + 单价 + 实际价格）
```

报价单中 `actual_price` 为实际成交价，可按低于或等于 `unit_price × lesson_count` 灵活定价。

小课包课程（`small_package` 非空且为有效标识值）的 price_plans 中 `plan_type` 锁死为"小课包"，不可选择其他类型。

### 6.14 学科两级体系

学科支持两级树形结构：一级学科（如"语言类"、"艺术类"）下可挂二级学科（如"英语"、"美术"），通过 `parent_id` 自引用实现。

### 6.15 校区权限

课程表 `campus_permission` 字段用于控制课程在哪些校区可见/可选，支持多校区逗号分隔存储。

### 6.16 组织架构规则

- 部门和校区为两类独立树，同级同类型下名称不可重复
- 有子节点的组织不可删除（需先删除子节点）
- 编辑时不能将自身设为上级
- 树节点支持展开/折叠、新增子节点、编辑、删除，hover 显示操作按钮

### 6.17 默认数据初始化

首次运行时自动插入：
- 意向等级：A-高意向 / B-中意向 / C-低意向 / D-无意向
- 课程类型：试听课 / 正式课体验 / 测评课 / 其他
- 沟通方式：电话 / 微信 / 面谈 / 短信

### 6.18 授课课时偶数校验

班级（`classes`）新增/编辑时，`lesson_hours`（授课课时）必须为偶数。前端提交前校验 + 后端 `add_class` / `update_class` 双重校验，不通过则返回错误提示。

### 6.19 教室名称唯一

教室（`classrooms`）表 `name` 字段有唯一约束。新增时校验名称是否重复；编辑时校验排除自身（允许保持原名不变），若改为已存在的其他名称则返回错误。

### 6.20 兼容性处理

- 本次已完成 SQLite → MySQL 8.4.9 完整迁移（20 张表，128 条记录）
- MySQL TEXT 列不支持默认值，建表时 `DEFAULT ''` 字段改为 `VARCHAR(500)`（27 处）
- PDO `execute()` 返回 `boolean`，不能链式调用 `->fetch()`，已修复 `->execute()->fetch()` 模式（7 处）
- PDO `execute()` 返回值不能赋值给变量后调用 `->fetch()`，`$res = $stmt->execute(); $res->fetch()` 改为 `$stmt->execute(); $stmt->fetch()`（13 处）
- `SHOW COLUMNS` 返回列名 `Field`（非 SQLite 的 `name`），已修正
- SQLite `SQLITE3_INTEGER`/`TEXT` 绑定改为 `PDO::PARAM_INT`/`PARAM_STR`
- `PRAGMA table_info` 替换为 `SHOW COLUMNS`
- `AUTOINCREMENT` 替换为 `AUTO_INCREMENT`
- SQLite `fetchArray` → PDO `fetch`，`querySingle` → `query`+`fetchColumn`，`escapeString` → `quote`
- SQLite 原始版本保留在 Git 历史中（master 分支），可随时回溯
- 前端对已删除的字典值显示"（已删除）"标注并保留选项

### 6.21 订单类型体系

系统引入三种订单类型：**新报**、**续费**、**小课包**，贯穿价格方案 → 报名 → 交易订单全流程。

**类型定义**：

| 类型 | 含义 | 标签颜色（CSS） |
|------|------|-----------------|
| 新报 | 新学员首次报读 | 蓝色 `#1890ff`（`.tag-new-enroll`） |
| 续费 | 老学员续费报读 | 绿色 `#52c41a`（`.tag-renewal`） |
| 小课包 | 短期体验课包 | 橙色 `#fa8c16`（`.tag-small-pack`） |

**类型流转**：

```
课程编辑（设置 small_package）
        │
        ▼
设置价格方案（plan_type 下拉）
  ├─ small_package 非空且为有效标识值 → 锁死为"小课包"
  └─ small_package 为空/"否" → 可选"新报"或"续费"，默认"新报"
        │
        ▼
报名支付（从方案继承 → 写入子订单 order_type）
        │
        ▼
交易订单列表（展示"订单类型"列，三色标签）
```

**isSmallPackage() 判断函数**（`static/js/main.js`）：

```javascript
function isSmallPackage(val) {
    return val === '是' || val === '1' || val === '小课包';
}
```

仅当课程 `small_package` 字段为 `'是'` / `'1'` / `'小课包'` 时判定为小课包课程；空字符串、`'否'`、`'0'` 等均视为非小课包。

**涉及文件**：
- `index.php`：price_plans 表 + plan_type、orders 表 + order_type、MySQL 建表 API、save_price_plan / get_course_plans / pay_enroll / list_orders API
- `static/js/main.js`：isSmallPackage()、showPriceModal 锁死逻辑、addPlan/editPlan/savePlan、renderPlanList/renderItemList 类型标签、selectEnrollPlan/confirmPayEnroll 传递 plan_type、renderOrderTable/loadStudentOrders 订单类型列
- `static/css/style.css`：`.tag-new-enroll`（蓝）、`.tag-renewal`（绿）、`.tag-small-pack`（橙）

### 6.22 学员课耗与上课记录扩展

**学员课耗面板**（panel-students → 学员课耗标签页）：展示全量考勤消费记录，列顺序：校区 → 学号 → 学员姓名 → 手机号 → 课程 → 一级学科 → 二级学科 → 班级 → 授课教师 → 上课日期 → 上课时间 → 考勤时间 → 出勤状态 → 消耗课时 → 课耗金额。支持日期范围筛选，底部分页。

**上课记录列顺序**（学员详情 → 上课记录标签页）：校区 → 课程 → 一级学科 → 二级学科 → 班级 → 授课教师 → 上课日期 → 上课时间 → 考勤时间 → 出勤状态 → 消耗课时 → 课耗金额。校区列置于最前方便按校区分组查看。

**考勤写入逻辑**（`save_class_attendance`）：
- `class_attendance` 是班级考勤主记录；`class_attendance.deduction_json` 是跨订单扣课时的明细来源，格式为 `[{order_id, amount}]`
- `attendance_records` 是上课记录/课耗展示用明细；当一次考勤跨多个订单扣课时，查询详情时必须按 `deduction_json` 拆成多条订单级记录展示，不能只看 `attendance_records.order_id`
- 上课记录中的课程/一级学科/二级学科取自实际扣课时订单对应的课程信息，而非简单取班级所属课程
- 若本次未扣课时（如缺勤，`$deductedOrderId = 0`），则回退使用班级所属课程的学科信息
- 校区字段从班级表查询后写入 `attendance_records.campus`
- 班级名称（`class_name`）始终保持班级原名不变

**扣课时候选订单条件**：
- 只扣该学员已报读课程中的订单
- 只扣与本次班级相同校区的订单
- 只扣未作废订单：`is_voided='否'`
- 只扣未完成退费订单：`refund_status` 不能为 `已退费`
- 只扣剩余课时大于 0 的订单：`lesson_count - consumed_lessons > 0`
- 退费申请中的订单默认冻结，不参与新的扣课；唯一例外见下方“与退费的关系”

**扣课时优先级规则**（三级优先级，跨订单连续扣，限定同校区）：
1. 优先扣同一 `course_id` 的订单，多个时按报名时间 `created_at ASC, id ASC`，先报名优先
2. 同课程未扣满时，继续扣同一二级学科的订单，多个时按报名时间 `created_at ASC, id ASC`，先报名优先
3. 同二级学科仍未扣满时，继续扣同一级学科的订单，多个时按报名时间 `created_at ASC, id ASC`，先报名优先

每级内部独立查询，逐级递减 `$remainingToDeduct`，扣完即止。已处理订单通过 `$processedOrderIds` 数组在后续级别查询中排除，避免同一个订单在不同优先级层级重复扣课，也避免低优先级层级的早期订单插队到高优先级层级的后期订单前面。

**保存与重算规则**：
- 每次保存考勤时，先读取该条旧 `class_attendance.deduction_json`
- 先按旧明细逐笔归还课时：`orders.consumed_lessons = GREATEST(0, consumed_lessons - amount)`
- 再按当前状态、当前扣课时数、当前优先级重新计算并写入新的 `deduction_json`
- 如果当前可用课时不足以扣完本次出勤课时，整次保存失败并回滚事务
- 不再使用全局 `attendance_records` 汇总去重算 `orders.consumed_lessons`；跨订单扣课以后，订单课耗应以 `class_attendance.deduction_json` 的逐笔扣还为准

**与退费的关系**：
- 提交课程退费后，订单进入 `refund_status='退费申请中'`，并写入待审批/审批中的 `refund_records`，该订单剩余课时在考勤中视为冻结
- 退费申请中的订单不参与新的考勤扣课，防止退费金额和可退课时被后续考勤改变
- 例外：如果某订单已经存在于当前这条考勤的旧 `deduction_json` 中，编辑/重算同一条考勤时允许临时把这部分旧扣课时纳入可用课时，以便“先还旧扣课，再按优先级重扣”不会误报课时不足
- 当旧 `deduction_json` 中涉及退费申请中或已退费订单时，不允许通过修改状态/扣课时数改变这些订单已参与的课耗，避免退费审批期间课时和金额口径漂移
- 撤销退费申请（`cancel_refund`）会恢复 `orders.refund_status='正常'` 并删除对应退费记录；前端会刷新打开中的考勤弹窗和考勤列表，撤销前被冻结的剩余课时重新变成可扣课时
- 退费审批完成后，订单进入 `refund_status='已退费'`，并将 `orders.consumed_lessons=lesson_count`，该订单剩余课时归零，不再参与考勤扣课

**还课时逻辑**：
- 出勤改为缺勤/请假、降低扣课时数、删除旧扣课分配、或重新保存同一条考勤时，都先按旧 `deduction_json` 把课时还回原订单
- 归还只还到旧明细中的原 `order_id`，不按当前优先级重新寻找订单
- 还课时发生在重新计算可用课时之前，因此同一条考勤原本占用的课时可以被本次重算继续使用
- 缺勤记录不扣课时；对应步进器值为 0，保存时不会生成新的扣课 `deduction_json`

**前端交互规则**：
- 考勤状态为缺勤或未选择时，扣课时步进器固定为 0，置灰不可点击
- 从缺勤切换回出勤时，扣课时步进器默认恢复为该班级的授课课时（`classes.lesson_hours`），并受当前可扣课时上限限制
- 点击已消耗课时数字查看明细时，应按 `deduction_json` 展示完整订单级扣课明细，不能只展示第一条订单

**涉及文件**：
- `index.php`：attendance_records 表 campus/class_name/subject_level1/subject_level2/teacher/class_time/deducted_order_id/deducted_lessons/consumed_amount 字段、`save_class_attendance` / `add_attendance` / `update_attendance` / `list_attendance` / `list_all_attendance` API
- `static/js/main.js`：`loadAttendance` / `loadStudentConsumption` / `saveAttendance` / `editAttendance` / `showAttendanceModal` / `loadAttendanceClassSelect` / `onClassChangeInAttendance`

### 6.23 出班机制（left_at 字段）

学员从班级出班时，`class_students.left_at` 字段被设置为出班时间戳（如 `'2026-06-27'`），在班状态则为空串 `''`。系统多个查询点需同步过滤 `left_at`：

| 查询点 | API / 函数 | 过滤规则 |
|--------|-----------|----------|
| 班级详情学员列表 | `list_class_students` | `WHERE cs.left_at = ''`（仅展示在班学员） |
| 添加学员弹窗 | `get_available_students` | 子查询 `NOT IN (SELECT student_id FROM class_students WHERE class_id=$classId AND left_at = '')`（仅排除在班学员，已出班的可重新添加） |

### 6.24 学员类型自动计算

| 场景 | 规则 |
|------|------|
| 默认值 | 新增学员 `student_type` 默认为 `'小课包'` |
| 自动升级 | 报名支付（`pay_enroll` / `enroll_course`）或编辑学员（`update_student`）后，查询该学员是否存在有效非小课包订单（`is_voided='否'` 且 `order_type != '小课包'` 且 `order_type != ''`） |
| 升级条件 | 只要存在至少一条符合条件的订单，立即将 `student_type` 更新为 `'常规'` |
| 不可逆 | 一旦升级为 `'常规'` 后永久保持，不会回退为 `'小课包'` |

### 6.25 在册学员筛选规则

在册学员（`student_filter='active'`）的筛选逻辑包含两个条件：

1. **学员类型**：必须为 `'常规'` 类型（`student_type = '常规'`）
2. **剩余课时 > 0**：当指定校区时，通过 EXISTS 子查询检查该学员在指定校区下是否存在有效订单（`is_voided='否'`、非已退费）且剩余课时 > 0；剩余课时 = `lesson_count - COALESCE(consumed_lessons, 0)`，退费申请中订单课时冻结为 0

campus 筛选同步增加 `is_voided='否'` 和 `(refund_status IS NULL OR refund_status != '已退费')` 过滤，防止作废/已退费订单干扰校区筛选。

### 6.26 校区-学科-授课老师关联

| 场景 | 规则 |
|------|------|
| 数据载体 | `student_subject_teacher` 表，三元组唯一约束（student_id + campus_id + subject_id） |
| 新增/编辑 | 学员新增/编辑弹窗支持配置多条关联（选择校区 → 自动加载该校区下学科 → 选择老师），通过 `sst_items` 数组提交。编辑时全量替换旧关联（先删后插） |
| 删除学员 | 级联删除 `student_subject_teacher` 中该学员所有关联记录 |
| 列表展示 | 学员列表"授课老师"列展示格式：`校区:学科-老师`，多个用逗号分隔；按校区筛选时仅展示该校区下的关联记录 |
| 详情展示 | `get_student` API 返回 `sst_records` 数组，含校区名、学科名（一级+二级）、老师名及部门 |

---

## 七、代码规范

> 以下规范源自 Andrej Karpathy 提出的 LLM 编码行为准则，作为本项目后续开发的强制性约束。

### 7.1 先思考再编码

**不假设，不隐藏困惑，主动揭示权衡。**

实施前：
- 明确陈述假设。不确定时发问。
- 存在多种解释时，列出选项而非沉默取舍。
- 有更简单的方案应当指出，必要时提出反对意见。
- 遇到不明确的地方，停下来，指出困惑点，发问。

### 7.2 简洁优先

**最少代码解决问题，不做任何推测性编码。**

- 不添加超出需求的任何功能。
- 不为单次使用的代码创建抽象。
- 不做未被请求的"灵活性"或"可配置性"。
- 不处理不可能发生的错误场景。
- 若写了200行但50行能解决，重写。

自问："高级工程师会觉得这是过度设计吗？"如果是，简化。

### 7.3 外科手术式修改

**只动必须动的，只清理自己制造的混乱。**

编辑已有代码时：
- 不"顺手优化"相邻的代码、注释或格式。
- 不重构没坏的东西。
- 匹配已有代码风格，即使你更偏好另一种写法。
- 注意到无关的死代码时，提及但不删除。

当你的改动制造了孤儿代码：
- 移除因你的改动而不再使用的导入/变量/函数。
- 不删除改动前就存在的死代码，除非明确要求。
  
测试标准：每条改动的行都应当能追溯到用户的具体需求。

### 7.4 目标驱动执行

**定义成功标准，循环验证直到达标。**

将任务转化为可验证的目标：
- "加校验" → "为无效输入编写测试，再使其通过"
- "修复 bug" → "编写复现用例，再使其通过"
- "重构 X" → "确保前后测试均通过"

多步骤任务先陈述简要计划：
```
1. [步骤] → 验证: [检查项]
2. [步骤] → 验证: [检查项]
3. [步骤] → 验证: [检查项]
```

强成功标准让你可以独立循环。弱标准（"让它工作"）需要不断索要澄清。

---

**这些规范生效的标志：** diff 中不必要的改动减少、因过度复杂导致的重写减少、澄清性问题出现在实施之前而非错误之后。

---

## 八、开发流程规范

> 遵循 **Agency Agents 七专家流程**，强制产品经理前置分析 + 用户确认门禁。

### 流程

```
用户需求
  ↓
0️⃣ Product Manager (product-manager)     → 需求分析 + 产品设计
  ↓  ⛔ 用户必须确认后才能继续
1️⃣ UI Designer (ui-designer)             → 页面设计
2️⃣ Backend Architect (backend-architect) → 后端/API/数据库（PHP）
3️⃣ Frontend Developer (frontend-developer)→ 前端实现
4️⃣ API Tester (api-tester)               → 接口测试
5️⃣ Code Reviewer (code-reviewer)         → 代码审查
6️⃣ Git Workflow Master (git-workflow-master) → 提交规范
```

### 规则

| 规则 | 说明 |
|------|------|
| 派发方式 | `delegate_task` 派发子 Agent，禁止 `agency_agents_load` 化身 |
| 第 0 步强制 | 任何需求必须先经产品经理分析，输出需求文档 |
| 用户确认门禁 | 第 0 步后必须等待用户明确确认（"可以"/"开始"），才进入开发 |
| 禁止跳过 | 绝不允许跳过产品经理步骤直接写代码 |

---

## 九、更新日志

### 2026-07-01

| 类型 | 描述 | 涉及文件 | 提交 |
|------|------|----------|------|
| feat | **新增 student_subject_teacher 关联表**：学员-校区-学科-授课老师四元关联，三元组唯一约束；新增/编辑学员弹窗支持配置校区-学科-老师关联，编辑时全量替换；列表新增"授课老师"列（按校区筛选时仅展示当前校区记录）；get_student 返回 sst_records | `index.php`、`static/js/main.js`、`static/css/style.css` | 3a2f740 |
| feat | **新增学员类型字段 student_type**：students 表新增 student_type（默认'小课包'）；报名支付/编辑学员后自动计算——存在有效非小课包订单即升级为'常规'（不可逆）；列表新增"学员类型"列 | `index.php`、`static/js/main.js` | 3a2f740 |
| feat | **新增 get_campus_subjects / get_teachers API**：get_campus_subjects 根据校区ID返回该校区下所有一级学科（从 courses.campus_permission 匹配）；get_teachers 返回所有在职教师（is_teacher='是'） | `index.php` | 3a2f740 |
| fix | **修复在册学员筛选 Bug**：campus 筛选 EXISTS 增加 `is_voided='否'` 和 `refund_status!='已退费'` 过滤；在册筛选条件从 `if ($campus && $subjectLevel1)` 改为 `if ($campus)`，确保选校区就检查剩余课时 > 0；消除作废订单通过校区筛选、退化为仅按 student_type 判断的缺陷 | `index.php` | 3a2f740 |
| refactor | **学员编辑弹窗精简**：移除来源和跟进状态下拉框（字段保留在数据库但前端不再展示），新增只读学员类型展示 | `index.php` | 3a2f740 |
| style | **新增授课老师标签样式**：`.teacher-tag` 暖木配色标签 | `static/css/style.css` | 3a2f740 |
| feat | **现金流统计收入口径调整**：`get_cashflow_stats` 收入统计去掉 `refund_status` 过滤条件，仅保留 `pay_status='已支付' AND is_voided='否'`。已退费订单的收入如实计入收入端，退费金额在支出端体现 | `index.php` | — |
| feat | **现金流统计图表优化（全部校区模式）**：`renderCashflowCharts` 新增 `campusFilter` 参数，全部校区模式下按日期对各校区数据求和，堆叠柱状图改为普通柱状图（单色"全部校区"柱），折线图仅显示一条汇总折线；单校区模式保持原有堆叠+多折线行为 | `static/js/main.js` | — |
| feat | **净现金流图表类型改为柱状图**：第二个图表从 `type: 'line'` 改为 `type: 'bar'`，标题从"各校区净现金流趋势"改为"各校区净现金流" | `static/js/main.js`、`index.php` | — |
| style | **图表标题重命名**：第一个图表标题从"各订单类型收入堆叠柱状图"改为"总收入"，净现金流图表标题从"各校区净现金流"改为"净现金流" | `index.php` | — |


### 2026-07-02

| 类型 | 内容 | 涉及文件 | 版本 |
|------|------|----------|------|
| feat | **现金流新增总支出图表**：在总收入与净现金流图表之间插入总支出柱状图，三图表宽度与三个色块对齐 | index.php static/js/main.js | 3b27c40 |
| fix | **校区排名图表去重**：rankings SQL 从 GROUP BY campus, order_type 改为 GROUP BY campus，同时补充支出数据，每个校区只出现一次 | index.php | 7064e4a |
| feat | **校区筛选改为树形多选**：替换下拉为自定义树形面板，支持按大区全选/单选校区/多选组合，后端 campus 参数改为逗号分隔多值 | index.php main.js style.css | 788683a |
| style | **三图表颜色对齐色块**：总收入→绿系(#38A169)、总支出→红系(#E53E3E)、净现金流→紫系(#7C3AED)，堆积用同色深浅区分 | main.js | 19d57b7 |
| style | **排名图表颜色统一**：校区排名图的总收入/总支出/净现金流指标颜色与上方色块对齐 | main.js | 85d06fb |
| style | **我的资源去按钮+日期预设**：去掉编辑和预约试听大按钮(8→6,4列→3列)；日期筛选改为预设下拉(今天/昨天/近7天/近30天/本月/上月/自定义) | index.php main.js | ab271c9 |
| style | **操作按钮改为药丸风格**：btn-link 从裸文字链接改为圆角药丸按钮，浅紫底+边框+hover 加深，删除为浅红底 | style.css | 752cc13 |
| revert | **撤销操作弹窗 tab 导航**：去除资源操作单按钮→弹窗融合 5 操作功能的修改，恢复 5 个独立药丸按钮 | index.php main.js style.css | 9a41ad4 |
| feat | **新增课表页面（panel-schedule-view）**：周视图展示排课数据，支持校区筛选和前后周切换；后端新增 get_schedule_view API（展开 weekdays + time_slots 按天按时段分组，JOIN classes/courses）；左侧导航"教务管理"模块新增"课表"菜单项（工作记录之后、基础设置之前）；前端新增周视图课表渲染、校区筛选、课程卡片5色循环、排课详情弹窗；课表表格样式、响应式横向滚动 | index.php static/js/main.js static/css/style.css | — |

### 2026-07-02 (第三阶段 — 课表整合到考勤页面)

| 类型 | 内容 | 涉及文件 | 版本 |
|------|------|----------|------|
| refactor | **课表整合到考勤页面作为标签页**：将独立的课表页面（panel-schedule-view）整合到考勤管理页面（panel-attendance）作为第二个标签页，用户可在"上课记录"和"课表视图"两个标签页间切换，减少页面跳转，提升用户体验 | index.php static/js/main.js static/css/style.css | — |
| feat | **考勤页面标签页导航**：在考勤管理页面顶部添加标签页导航栏，包含"上课记录"和"课表视图"两个标签页，当前选中标签高亮显示，点击切换内容区域 | static/js/main.js static/css/style.css | — |
| feat | **课表视图保持原有功能**：整合后的课表视图保持原有所有功能，包括周视图展示、校区筛选、前后周切换、课程卡片展示、详情弹窗等 | index.php static/js/main.js | — |
| style | **标签页样式优化**：新增标签页导航样式，当前选中标签底部边框高亮，hover效果，响应式适配 | static/css/style.css | — |

### 2026-06-30

| 类型 | 描述 | 涉及文件 | 提交 |
|------|------|----------|------|
| fix | **考勤扣课时三级优先级排除退费订单**：三级 SELECT（3471/3497/3525 行）和三级 UPDATE 新增 `AND id NOT IN (SELECT order_id FROM refund_records WHERE status NOT IN ('已退费', '审批驳回'))` 子查询，防止退费申请中订单被继续扣课时；退还阶段增加详细诊断日志（revert/deduct 前后快照、rowCount 验证）和二次保护子句 | `index.php` | — |
| fix | **历史实现：全局 consumed_lessons 重算排除退费订单**：263 行初始化重算逻辑曾新增 `AND o.id NOT IN (SELECT order_id FROM refund_records WHERE ...)` 子查询，防止退费申请中订单的 consumed_lessons 被重算归零；现行考勤逻辑已改为以 `class_attendance.deduction_json` 逐笔扣还为准，不再依赖全局 `attendance_records` 汇总重算订单课耗 | `index.php` | — |
| fix | **attendance_records 写入补全 order_id**：3617 行考勤 INSERT 新增 `order_id` 列 + `:oid` 绑定（`$deductedOrderId`），修复页面加载时 JOIN 回填随机匹配错误订单的 Bug | `index.php` | — |
| fix | **退费申请中学员剩余课时显示为 0**：`get_student_courses` API 新增 pendingRefundIds 收集逻辑，退费申请中订单课时冻结显示为 0，防止继续扣课 | `index.php` | — |
| fix | **多处剩余课时查询排除退费/作废订单**：`add_student_to_class` 分班校验、考勤编辑剩余课时上限、考勤后自动移班判断，三处 `SUM(lesson_count - consumed_lessons)` 查询统一添加 `AND is_voided='否' AND id NOT IN (...)` 过滤 | `index.php` | — |
| fix | **已终止退费不可继续审批**：`approve_refund` 新增状态校验，已退费/审批驳回的记录拒绝继续审批 | `index.php` | — |
| feat | **退费申请撤销功能**：新增 `cancel_refund` API（恢复订单 refund_status='正常'、删除 refund_records 记录）；前端退费记录表格增加「撤销」按钮（已退费/驳回时隐藏），`loadRefundRecords` 增加 try/catch 错误提示 | `index.php`、`static/js/main.js` | — |
| fix | **学员报读课程筛选下拉框 ID 冲突修复**：`filter-subject1`/`filter-subject2` 重命名为 `student-filter-subject1`/`student-filter-subject2`，对应函数 `onSubject1Change` → `onStudentSubject1Change`，避免与工作记录面板同类元素 ID 冲突 | `static/js/main.js` | — |
| data | **修正脏数据**：attendance_records id=105 的 order_id 从 52 修正为 67（对应正确扣课时订单） | — | — |
| chore | 新增 debug_save.log 记录考勤保存诊断日志（退还/扣课时/执行前后快照） | `debug_save.log` | — |
| style | **学员列表标签暖木自然配色**：方案5配色——班级标签底色 #F2E8D5；学科标签按学科分色（绘画/书法/语文/数学/英语）；标签改为纵向堆叠（每标签独占一行）；底色宽度适配文字（width: fit-content）；课时为0的学科不渲染，全为0时该格留空 | `index.php`、`static/css/style.css`、`static/js/main.js` | — |
| feat | **新增校区筛选功能**：学员列表筛选栏新增「校区」下拉框（数据源从组织树 API `type='校区'` 获取）；后端 `list_students` API 新增 `campus` 参数，WHERE 通过 `EXISTS (SELECT 1 FROM orders o WHERE o.student_id=s.id AND o.campus=:campus)` 过滤学员；`class_names` 子查询 JOIN 增加 `AND c.campus=:campus_cls`；`subject_remaining` 子查询内部增加 `AND o.campus=<校区名>` 条件 | `index.php`、`static/js/main.js` | — |
| fix | **编辑学员来源下拉框为空**：`list_channels` API 返回格式修复 —— `json($rows)` → `json(['data' => $rows])`，前端 `populateStudentSourceSelect` 原取 `result.data` 拿到 undefined 导致渠道列表为空 | `index.php` | — |
| fix | **router.php 静态文件 404**：`return false` 不可靠，改为直接 `readfile($file)` + 根据扩展名设置正确 `Content-Type`（css/js/png/jpg/svg/woff2 等）并 `exit`，确保 CSS/JS 正常加载 | `router.php` | — |

### 2026-06-29

| 类型 | 描述 | 涉及文件 | 提交 |
|------|------|----------|------|
| feat | **退费管理完整功能**：orders 表新增 refund_status 字段（正常/退费申请中/已退费）；新建 refund_records 表；新增 4 个 API（submit_refund/list_refund_records/approve_refund/get_refund_record）；学员详情报读课程表格增加状态列和退费按钮；新增工作记录页面（退费记录/课程记录双标签）；审批弹窗三步进度条（一级审批→二级审批→财务确认）；财务确认通过后自动将剩余课时置零；考勤重算排除已退费订单 | `index.php`、`static/css/style.css`、`static/js/main.js` | 20ccba2 |
| feat | **交易订单增加支付状态/作废字段**：orders 表新增 pay_status（已支付/待支付/已取消）和 is_voided（是/否）；前端表格新增两列及筛选下拉；JS 新增 renderPayStatus/renderVoidedStatus 渲染函数；enroll_course/pay_enroll 写入默认值；void_order API 完善（校验 consumed_lessons==0，作废后订单对应报读课程从学员详情移除）；get_student_courses 过滤 is_voided='否' 的订单 | `index.php`、`static/css/style.css`、`static/js/main.js` | 710d916 |
| feat | **课程搜索并入筛选栏**：课程名称搜索输入框从独立工具栏移到筛选栏同行首个 filter-item，删除 toolbar-course 区域，新增 .filter-item-search 样式 | `index.php`、`static/css/style.css` | 710d916 |
| feat | **学员报读课程增加子订单号列**：get_student_courses SQL 新增 o.order_no 字段，前端表头和每行增加"子订单号"列（等宽字体） | `index.php`、`static/js/main.js` | 710d916 |
| data | **历史美团/现金订单标记已支付**：UPDATE orders SET pay_status='已支付' WHERE payment_method IN ('美团','现金')，10 行受影响 | — | 710d916 |

### 2026-06-27

| 类型 | 描述 | 涉及文件 | 提交 |
|------|------|----------|------|
| feat | **考勤列表增加班级名称搜索**：`list_attendance_sessions` API 新增 `class_name` 模糊搜索参数（`array_filter` + `stripos`）；前端工具栏增加 `#attendance-class-name` 输入框，支持回车搜索；JS `loadAttendanceSessions` 读取输入值拼入 URL | `index.php`、`index.html`、`static/js/main.js` | b97d763、666bdb5 |
| fix | **班级详情学员列表过滤出班学员**：`list_class_students` 查询未过滤 `left_at`，导致已出班学员仍显示。修复：WHERE 增加 `AND cs.left_at = ''`，SELECT 增加 `cs.left_at` 字段 | `index.php` | 9b7ee22 |
| fix | **添加学员弹窗过滤出班学员**：`get_available_students` 的 `NOT IN` 子查询仅按 `class_id` 过滤，导致已出班学员无法被重新添加。修复：子查询改为 `WHERE class_id=$classId AND left_at = ''`，使仅当前在班的学员被排除 | `index.php` | b32982f |
| fix | **临时学员考勤状态默认改为未选择**：`save_temp_attendance` 后端 `status` 改为空字符串 `''`，前端 `main.js` 新增和加载临时学员模板均改为未选择默认状态，避免误操作 | `index.php`、`static/js/main.js` | 38bdd91 |
| fix | **新考勤弹窗临时学员识别与保护**：`showAttendanceSession` 和 `reloadAttendanceSession` 根据 `is_temporary` 字段渲染「临时」标签，并隐藏移除按钮防止误删 | `static/js/main.js` | b7579c7 |
| feat | **考勤弹窗新增添加学员功能**：去掉底部重复的临时学员按钮，新增 `showAddStudentToAttendanceModal` 弹窗（复用班级详情页添加逻辑），`reloadAttendanceSession` 支持刷新后渲染 | `static/js/main.js` | 2c26d5a |
| style | **缺勤开关红色样式**：考勤状态弹窗中缺勤 switch 激活时背景色改为 `#e74c3c` 红色，与到场/请假/未到形成视觉区分 | `static/css/style.css` | a4883ce |
| docs | **纳入 LLM 编码规范**：将 Andrej Karpathy 的 LLM 编码四原则（先思考再编码、简洁优先、外科手术式修改、目标驱动执行）纳入 `PROJECT_SUMMARY.md` 作为项目强制性代码约束 | `PROJECT_SUMMARY.md` | 2616bf5 |

*（内容由AI生成，仅供参考）*

### 2026-07-02 (第二阶段 — 课表模块优化与拖拽排课)

| 类型 | 内容 | 涉及文件 | 版本 |
|------|------|----------|------|
| feat | **课表模块全面优化**：今天列高亮（蓝色标记）；卡片显示在班学员数（JOIN class_students）；教室冲突检测（同时段同教室红色边框+⚠️）；教师/教室下拉筛选；月视图切换（日历网格）；详情弹窗跳转班级列表；键盘导航（←→翻周，T回今天）；@media print 打印样式 | index.php static/js/main.js static/css/style.css | a01b6b8 |
| feat | **拖拽排课**：左侧资源面板（课程/教师/教室三组可拖拽列表）→ 拖拽至右侧课表单元格 → 三元素齐备后 ✓创建按钮 → 弹窗确认班级名/日期/人数 → 事务创建班级+排课记录。新增 create_schedule_from_grid API | index.php static/js/main.js static/css/style.css | 1c1ee87 |
| fix | **拖拽排课教师列表空白**：get_teachers API 返回 `{teachers:[...]}`，JS 取 `res.data` 改为 `res.teachers` | static/js/main.js | 9e9ea9c |
| fix | **排课模式校区前置校验**：未选校区点击排课模式时 toast 提示，必须先选校区 | static/js/main.js | 9e9ea9c |
| feat | **自定义 toast 通知**：替换浏览器 alert()，顶部居中圆角卡片 + 滑入动画，支持 success/error/warn/info 四种类型。统一旧版 `.toast` CSS 为 `.hermes-toast` | static/js/main.js static/css/style.css | 05d19ab |
| fix | **排课模式课程不展示**：courses.campus_permission 存的是校区 ID（如 `'7,14,13'`），下拉选的是名称，indexOf 匹配失败。改为构建 name→id 映射后用 `split(',').includes(campusId)` 精确匹配 | static/js/main.js | b8dd0ee |
| fix | **showToast 重复定义**：新增的 showToast（hermes-toast）与旧版 showToast（.toast）冲突，导致教室编辑保存异常。删除重复版，升级旧版支持 4 种类型，统一 CSS | static/js/main.js static/css/style.css | e122778 |
| fix | **getSelectedCampuses 重复定义**：课程表单版（返回逗号 ID）被现金流版（返回名称数组）覆盖，导致课程保存时 campus_permission 传入数组，PHP trim() 报 TypeError。现金流版重命名为 getSelectedCashflowCampuses | static/js/main.js index.php | 5cb394d |
| fix | **消除所有 JS 函数重复定义**：toggleAllCampuses（课程表单/现金流两版不同实现）、formatDate（两版相同删除一个）、escHtml（DOM 版/正则版，保留正则版）全部去重 | static/js/main.js index.php | 5f6e5f0 |
| fix | **课表 time_slots 重复展示**：同一 schedule 在同一天同时段重复 3 次。根因：time_slots JSON 的 key 是星期编号（如 `{"5":...,"6":...,"7":...}`），展开时 weekdays×timeSlots 产生 3×3 条目。周视图+月视图加 schedule_id+start+end 去重 | index.php | 72980bc |
| fix | **课表不同时段混入同一天**：schedule id=4 的 time_slots 每个星期有不同时段（key=4→11:48, key=5→11:49, key=6→11:49），但代码把全部 3 个时段都塞到每一天。修复：time_slots key 为数字（1-7）时只匹配对应星期 | index.php | 92c0b5e |
| fix | **排课模式教师按校区筛选**：教师列表按 department 字段匹配校区名，只展示本校区教师 | static/js/main.js | 7d28cb3 |
| style | **课表卡片重新设计**：去掉课程名，班级名粗体置顶 → 教师名白色底粗体突出 → 教室+人数为副信息 | static/js/main.js static/css/style.css | 191aa03 / 4437d60 |
| feat | **课表弹窗增加「考勤」和「删除课次」按钮**：考勤 → 跳转班级管理面板；删除课次 → 调用 delete_schedule API | static/js/main.js | 7280983 |

### 2026-07-03 — 预约试听重构 + UI 优化

| 类型 | 变更说明 | 涉及文件 | Commit |
|------|---------|---------|--------|
| feat | **HTML 表单 UI 重设计**：班级新增/编辑表单改为分段控件（pill 按钮）、圆角输入框、招生人数+授课课时并排 | index.php style.css main.js | 88b6a8b |
| fix | **分段控件文字居中**：seg-item 加 `display:inline-flex;align-items:center;line-height:1` | style.css | d4fd4f0 |
| fix | **按钮文字居中**：.btn 加 `justify-content:center;text-align:center` | style.css | c8dacca |
| fix | **排课确认改用 toast 提示** + campus 兜底校验 | main.js | 929f936 |
| fix | **课表删除课次 confirm 改自定义弹窗** + scheduleView alert 改 toast | main.js | 0468b7a |
| feat | **授课课时默认 2 最小 2 步进 2** + 排课起始日期根据单元格日期智能计算 | index.php main.js | f76e28f |
| refactor | **排课弹窗去按日期排课** + UI 重设计（时间安排 + 资源配置两段式） | index.php main.js | acc9738 |
| feat | **排课弹窗教师按校区分组** + 搜索所有教师（input+datalist→自定义下拉）、教室按校区过滤 | index.php main.js style.css | f5e9289 |
| fix | **教师搜索改自定义下拉**：白底浅色 hover，本校教师绿色标识 | index.php main.js style.css | 495a689 |
| feat | **新增资源编辑 try/catch** + 错误详细提示 | main.js | 198fd9b |
| fix | **loadChannels 返回 res.data**：修复资源编辑 channels.map 报 TypeError | main.js | d3f0a67 |
| fix | **班级校区编辑回填**：loadCampusTree 加 await 确保树渲染后再回填 + esc→escAttr | main.js | 7fb5047 |
| fix | **班级校区从树状改回下拉 select** | index.php main.js | 68ffd9d |
| fix | **全量消除 \$db->quote 双引号包装**：update_class/update_classroom/update_attendance 共 5 处 SQL 语法错误 | index.php | b466e7c / 10e4ad3 |
| feat | **编辑班级隐藏班级类型和校区**（仅新增时显示和发送） | index.php main.js | 2dce6e2 / 0ccd237 |
| style | **编辑班级弹窗去备注字段** | index.php | 9073c02 |
| fix | **新增/删除班级后刷新正确 att-前缀表格** | main.js | fa82ec3 |
| fix | **授课课时强制偶数**：单数自动 +1，最小保持 2 | index.php | f75c8d4 |

### 2026-07-03 — 预约试听重构（核心功能）

| 类型 | 变更说明 | 涉及文件 | Commit |
|------|---------|---------|--------|
| feat | **appointments 表扩展**：新增 campus/subject_level1/course_id/class_id/schedule_id 字段 | index.php | 5bd6af9 |
| feat | **5 个级联查询 API**：get_trial_campuses/subjects/courses/classes/sessions | index.php | 21b346a |
| feat | **book_trial API**：级联创建预约记录，state=已预约待试听 | index.php | a8747be |
| feat | **预约试听弹窗重构**：校区→学科→课程→班级→课次 5 级级联 | index.php | 2ea52e2 |
| feat | **search_trial_sessions API**：独立筛选查询，展开为具体日期+时段 | index.php | 9cab794 |
| feat | **预约试听改为筛选+表格模式**：横向独立筛选器 + 查询按钮 + 结果表格每行「预约试听」链接 | index.php main.js | 9cab794 |
| fix | **预约试听独立筛选**：各筛选器不限顺序，查询按钮一次性搜索 | main.js | — |
| feat | **教师筛选**：选校区后教师列表缩为该校区的 | main.js | — |
| fix | **get_trial_subjects/courses 空筛选时返回全部** | index.php | 5d61eb0 |
| fix | **search_trial_sessions 日期计算修复**：next 跳过当天 → today+N days | index.php | adfaa01 |
| fix | **search_trial_sessions 按 weekday 过滤 time_slot**：避免跨天时段混入 | index.php | f45d089 |
| fix | **book_trial 不强制 course_id**：空时从班级反查 | index.php | 66a7c6c |
| fix | **attendance_records 无 notes 列**：移除，改用 class_attendance 表 | index.php | a9bd52e |
| fix | **class_attendance 加 student_name 列** + 零 ID 试听学员考勤可见 | index.php | c9edd70 |
| fix | **get_class_attendance student_id=0**：不从 students 表查，直接用 class_attendance.student_name | index.php | c9edd70 |
| fix | **预约名单去学员列**：改展示资源+电话，去掉 student_name | index.php main.js | 5d5732f |
| fix | **预约成功后刷新记录列表**：bookTrialInline 补 loadAppointments() | main.js | 2c99a01 |
| fix | **get_appointments 无筛选时 \$result→\$stmt**：修复预约列表始终为空 | index.php | 9e265f7 |
| style | **弹窗宽度放宽 + 去滚动条**：820→960→1100px + 95vw，white-space:nowrap | index.php | 3260c31 / 3582ab5 |
| style | **校区/学科联动 filter**：选校区缩课/师，选学科缩课 | index.php main.js | 64b9b34 |

### 2026-07-04 — 预约状态系统 + 报名修复 + 多角色工作流

| 类型 | 变更说明 | 涉及文件 | Commit |
|------|---------|---------|--------|
| feat | **预约状态四态系统**：已预约待试听/已试听/缺勤/已取消，由 CASE+class_attendance JOIN 联合计算 | index.php | 301cc55 / 702ef82 |
| fix | **collation mismatch**：apointments vs class_attendance JOIN 加 COLLATE utf8mb4_unicode_ci | index.php | 702ef82 |
| fix | **$offset 提前计算**：子查询前必须计算 offset | index.php | 773886d |
| fix | **count 子查询加入 effective_status**：修复状态筛选 Unknown column | index.php | 702ef82 |
| fix | **cancel_trial SQL 注入修复**：$trialDate 改用 prepared statement + 事务包裹 | index.php | 590d582 |
| feat | **预约名单扩展 7 列**：班级/老师/一级学科/二级学科/转化/渠道/归属人 | index.php main.js | 25970dd |
| fix | **r.channel→r.source**：resources 表无 channel 列 | index.php | 8fad8cf |
| feat | **预约名单去编辑+删除改取消试听**：cancel_trial 更新状态+清考勤 | index.php main.js | 1230ab6 |
| fix | **save_class_attendance 试听资源状态更新**：student_id=0 跳过扣课但更新 status | index.php | 2a0734b |
| fix | **报名选课方案展示修复**：enrollTransitionShow 移到数据渲染后 | main.js | 3f7506d |
| fix | **去 course/campus change 的 plans-section hide**：消除延迟 display:none 竞态 | main.js | a127470 |
| fix | **enrollTransitionShow 取消 pending hide**：clearTimeout + removeEventListener | main.js | 8307a10 |
| chore | **多角色开发工作流**：tms-development skill v2.0 + .hermes/project.md + memory | skill | — |
|| docs | **PROJECT_SUMMARY 更新**：07-03 + 07-04 全部变更 | .md | 242b157 / 9a14c78 |

### 2026-07-05 — 学员账户系统

| 类型 | 变更说明 | 涉及文件 | Commit |
|------|---------|---------|--------|
| feat | **学员账户基础功能**：新增 student_accounts 表（余额/累计充值/消费/退款）+ account_transactions 表（流水审计，type=deposit/consume/refund）；新增 get_student_account API（余额汇总+流水筛选+分页）；新增 top_up_account API（充值，事务保护，FOR UPDATE 锁行）；改造 enroll_course 支持 use_balance+balance_amount 余额支付；改造 approve_refund 支持 refund_to=balance 退款到余额 | index.php | 47a667a |
| feat | **学员详情页账户标签页**：新增「账户」tab（余额卡片+流水表格+充值弹窗+移动端适配）；前端客户端分页+类型/日期筛选；充值弹窗支持金额/支付方式/备注 | index.php main.js style.css | 47a667a |
| feat | **账户优化**：充值生成交易订单（order_type=账户充值，课程字段留空）；流水表+支付方式列；订单列表+一级/二级学科列；充值弹窗+校区/学科下拉 | index.php main.js style.css | 5704370 |
| fix | **账户流水关联单号**：get_student_account LEFT JOIN orders 返回子订单号 order_no | index.php main.js | fb27b4c |
| feat | **报名支持账户余额支付**：支付区新增账户余额卡片（蓝色主题）；pay_enroll API 支持 use_balance+balance_amount 扣款+流水 | index.php main.js style.css | ddbc26b |
| feat | **录单页面展示账户余额**：学员信息卡片增加余额显示 | index.php main.js | 5c3d15e |
| feat | **交易订单列表新增账户支付方式列**：现金/美团/账户三列+支付汇总含账户合计 | index.php main.js | d2ecae9 |
| fix | **余额加载错误提示**：goEnroll 中余额加载失败时显示错误信息替代静默忽略 | main.js | 387ee88 |

### 2026-07-06 — 上课时段设置 + 课表UI重设计

| 类型 | 变更说明 | 涉及文件 | Commit |
|------|---------|---------|--------|
| feat | **上课时段设置**：教务管理基础设置新增 class_periods 表+5个API(校区列表/时段CRUD)，支持按校区独立管理时段；排课表单时间输入改为下拉选择预设时段 | index.php main.js style.css | b61bf38 |
| feat | **时段校区归属**：class_periods 加 campus 字段，唯一约束改为(name,campus)；list_class_periods 支持 ?campus= 过滤；排课时按班级校区过滤时段 | index.php main.js style.css | b61bf38 |
| fix | **排课时段按时间排序**：下拉框选项按 start_time 升序排列 | main.js | b763b7c |
| style | **课表UI重设计**：暖色7色课程卡片（暖橙/薰衣草紫/薄荷绿/暖金/粉红/天空蓝/嫩绿）→ 现代简约纯色风格（Indigo/Teal/Red/Orange/Green/Purple/Blue），去掉全部渐变改用纯色+细阴影 | style.css main.js | 62bb40d / b9e7108 |
| fix | **排课拖拽效果优化**：投放区虚线→实线蓝框+内阴影 min-height 56px；拖拽项虚线+缩小+确认按钮改主蓝色 | style.css | b3ca73b |
| fix | **考勤保存失败修复**：class_attendance 表补全3个缺失字段（deducted_order_id/deduction_json/is_temporary），通过 ALTER TABLE 自动迁移 | index.php | f900642 |
| fix | **课表默认校区+教师色块**：initScheduleCampusFilter 默认选第一个校区；色块区分逻辑从按课程改为按老师 | main.js | 87ef01e |
| refactor | **考勤页班级管理移到最后**：tab 顺序改为课表→操作考勤→学员课耗→缺勤记录→班级管理；默认进入课表 | index.php main.js | 4bd279d / b793440 |
| fix | **课表首次加载异步竞态**：initScheduleCampusFilter 改为 async/await，等校区列表加载完再请求排课数据 | main.js | 9d85ac5 |
| feat | **月视图色块优化**：显示老师名字+上课时间+班级名称（单行）；同一天多排课按开始时间排序 | main.js style.css | 053f34c / 1c77c7d / 2e46bfb |

### 2026-07-07 — 退费系统重构 + 账户退费 + UI 优化

| 类型 | 变更说明 | 涉及文件 | Commit |
|------|---------|---------|--------|
| feat | **退费记录增加项目/内容字段**：refund_records 新增 project（课程/账户）+ content 字段；退费记录表格新增项目/内容列+筛选；历史数据自动回填 | index.php main.js | 0758bdc |
| feat | **账户退费功能**：学员账户余额支持发起退费（退到银行卡）；submit_refund 支持 refund_type=account；申请时立即扣减余额（事务+FOR UPDATE）；approve_refund/cancel_refund 支持账户退费的驳回/撤销余额恢复 | index.php main.js | 0758bdc / 1fc559b |
| feat | **退费记录增加学科/退费方式字段**：refund_records 新增 subject_level1 + refund_method；表格精简12列（去报读课时/消耗课时/报读金额/剩余课时/剩余金额）；账户退费扣减默认0、需选学科；退费方式 badge（转账蓝/账户绿） | index.php main.js | 9b41ce3 |
| feat | **课程退费增加「退到学员账户」方式**：退费申请弹窗新增退费方式 radio；选账户时免财务审批，二级审批直接到账+余额入账；审批弹窗适配提示 | index.php main.js style.css | 2f1f6a5 |
| fix | **Code Review 修复**：事务保护（beginTransaction/rollBack × 3）、兼容迁移、colspan 修正、confirm→showCustomConfirm、parseFloat→Number | index.php main.js | 1fc559b / 84bfe5a |
| fix | **审批弹窗嵌套修复**：modal-body 缺闭合标签导致审批弹窗嵌套在退费申请弹窗内 | index.php | 32adab0 |
| fix | **openModal 关闭旧弹窗**：打开新弹窗前自动关闭所有已打开弹窗，避免重叠 | main.js | fafcc02 |
| fix | **扣减金额字段映射错误**：consumed_amount→custom_deduction | main.js | 104ccb6 |
| style | **退费记录页面 UI 优化**：Badge 系统 CSS class 化；表格 hover/对齐/空态/骨架屏/响应式/入场动画 | style.css main.js | df29128 |
| style | **退费记录表格对齐修复**：table-layout:fixed→移除、border-left→伪元素→移除，最终精简为纯默认样式 | style.css | 4099d93 / eaf8999 / c9df54b |
| style | **退费审批弹窗 UI 优化**：三卡片布局、实退金额 Hero 样式、进度条增强（24px 圆点/pulse/✓）、审批人 pill、按钮主次分明 | style.css main.js | 14cb865 |
| chore | **导航顺序调整**：市场管理→教务管理→数据中心→员工管理 | index.php | 0758bdc |
| chore | **Product Manager 加入开发工作流** | .hermes/project.md | 0758bdc |
| chore | **账户充值标签颜色区分**：蓝色 #2563EB | style.css | 0758bdc |

### 2026-07-07（续）— 优惠管理模块 + 报价单优惠关联 + inline 编辑

| 类型 | 变更说明 | 涉及文件 | Commit |
|------|---------|---------|--------|
| feat | **优惠管理模块**：侧边栏新增「优惠管理」导航；优惠方案 CRUD（discount_plans 表+校区/学科关联表）；5 个 API + 16 个 JS 函数；表格/分页/搜索/类型筛选 | index.php main.js style.css | f51df13 / 59fbfd2 / 575fb22 / 003d478 |
| style | **优惠方案弹窗 UI 优化**：三卡片布局（基本信息/有效期/适用范围）；树选择强化（选中态+计数 badge）；输入控件统一 8px 圆角+focus 紫光晕 | index.php style.css main.js | e59d914 / f135c4a / 4d9680f |
| feat | **优惠券+发放记录模块**：coupons 表+coupon_records 表；优惠券 CRUD 5 API + 发放记录 3 API；标签页2「优惠券」+标签页3「发放记录」 | index.php main.js | dded0fa / 8d05c02 |
| feat | **报价单关联优惠方案+优惠券**：price_items 新增 discount_plan_id + coupon_id；报价单弹窗新增优惠方案下拉（按 plan_type 筛选）+优惠券下拉（仅课程券）；save/get API LEFT JOIN 回显名称 | index.php main.js | 7c5bdf3 / a6169d3 |
| fix | **优惠下拉字段映射修复**：api() 双问号→对象传参；discount_amount→amount；列表金额字段统一 | main.js | b60f2bd / a1b05dc / 2254ad8 |
| feat | **报价单实际支付价格自动扣减**：recalcItemActualPrice() 联动计算；改课时价格/优惠方案/优惠券任一实时更新实付 | main.js | a6169d3 / 5764262 |
| feat | **报价单列表 inline 编辑**：单击任意可编辑列→整行进入编辑态；text→input、优惠→select、实付只读联动；Enter 保存/Esc 取消/点击行外保存；去掉编辑按钮，新增保留弹窗 | main.js style.css | 12b0152 / 8d87f9e / 118cde1 / 2d7f338 |
| style | **价格方案弹窗 UI 重设计**：左右面板现代化（950px/500px）；报价单列表 sticky thead+hover；弹窗卡片式布局+紫色主题 | index.php style.css main.js | 7c5bdf3 |
| refactor | **审批弹窗退款方式不可变更**：去掉财务确认 radio 组+退余额分支；展示退费方式（退银行卡/退学员账户） | index.php main.js | 26b1c96 / c09542d |
| style | **退费申请弹窗 UI 优化**：5 张卡片式分组；计算流视觉（剩余→−扣减→=实退紫色渐变）；银行区展开动画；提交按钮 loading spinner | index.php style.css main.js | 46f0c74 |
| docs | **更新 PROJECT_SUMMARY** | PROJECT_SUMMARY.md | c45cc4b |

### 2026-07-09 — 考勤扣课时 / 退费 / 还课时规则文档化

| 类型 | 变更说明 | 涉及文件 | Commit |
|------|---------|---------|--------|
| fix | **本地启动兼容 MySQL 密码**：数据库连接改为优先尝试 `root/root`，失败后回退空密码；保留统一 PDO 选项和 `utf8mb4`，便于在 `C:\php8\` + `E:\MySQL` 本机环境启动 | index.php | - |
| fix | **订单优惠快照回填增加表存在保护**：启动时回填 discount/coupon/teaching aid 快照前先检查 `price_items`、`discount_plans`、`coupons`、`teaching_aids` 表是否存在，避免空库/迁移中因缺表 fatal | index.php | - |
| fix | **报价单设置价格弹窗可编辑性修复**：行内编辑优惠数据增加 loaded/loading 状态和 plan_type 缓存；当优惠/券列表为空但接口已加载完成时，仍允许点击报价单进入编辑态 | static/js/main.js | - |
| style | **设置价格弹窗删除按钮可见性修复**：价格明细表最后一列设为 sticky 操作列，并补齐 hover/偶数行/合计行背景，避免横向滚动时删除按钮被遮住 | static/css/style.css | - |
| fix | **操作考勤按钮事件修复**：课表/考勤操作按钮从内联 `onclick` 改为 `.js-attendance-session-btn` 委托事件，并通过 `data-*` 传递班级、排课、日期、教师、教室、时间等参数 | static/js/main.js | - |
| fix | **考勤扣课时优先级重写**：保存考勤时不再保留旧出勤分配，而是先按旧 `deduction_json` 还课时，再按同校区、剩余课时 > 0、同课程→同二级学科→同一级学科、先报名优先重新扣课；课时不足时事务回滚并提示学员姓名 | index.php | - |
| fix | **退费中旧扣课订单可在同一考勤内复用**：退费申请中订单默认冻结不可新增扣课；若订单已存在于当前考勤旧 `deduction_json`，本次重算可先还后扣，避免撤销/编辑同一考勤时误报可扣课时不足 | index.php | - |
| fix | **取消全局 consumed_lessons 重算**：移除启动时按 `attendance_records` 汇总覆盖订单 `consumed_lessons` 的逻辑，避免跨订单扣课被单订单明细覆盖；订单课耗以 `class_attendance.deduction_json` 逐笔扣还为准 | index.php | - |
| fix | **学员报读课程已消耗课时口径修复**：`get_student_courses` 优先汇总 `class_attendance.deduction_json`，并跳过同一班级/排课/日期下的聚合 `attendance_records`，避免跨订单扣课重复或漏算 | index.php | - |
| fix | **已消耗课时明细完整拆分**：`list_attendance` 对班级考勤的 `deduction_json` 按 `order_id` 拆成多条上课记录，并按对应订单课程、课时单价、扣课金额展示；跳过匹配的聚合 `attendance_records` 行 | index.php | - |
| fix | **考勤候选学员与自动移班剩余课时口径调整**：分班/考勤可选学员、考勤后自动移班判断改为按班级同校区 + 同一级学科统计有效订单剩余课时，排除作废、已退费和退费申请中的冻结订单 | index.php | - |
| fix | **缺勤步进器交互修复**：考勤状态为缺勤或未选择时，扣课时步进器固定为 0 且按钮禁用；从缺勤切回出勤时默认恢复班级授课课时，并受当前可扣课时上限限制 | static/js/main.js | - |
| fix | **撤销退费后刷新考勤可扣课时**：课程退费撤销成功后，若考勤弹窗正在打开则重新加载当前考勤；若考勤列表页处于激活状态则刷新列表，使刚解除冻结的剩余课时立即可见 | static/js/main.js | - |
| docs | **补充考勤扣课时 / 退费 / 还课时完整规则**：明确 `deduction_json` 来源、候选订单条件、三级优先级、保存重算、退费冻结例外、撤销退费恢复、还课时和前端步进器规则 | PROJECT_SUMMARY.md | - |

### 2026-07-08 — 画具管理 + 报价单优惠扩展 + 退费/考勤统一修复

| 类型 | 变更说明 | 涉及文件 | Commit |
|------|---------|---------|--------|
| feat | **画具管理模块**：teaching_aids 表+teaching_aid_campuses 关联表；5 个 API；导航「画具管理」；校区树多选；类型字段（教材包/画具） | index.php main.js style.css | - |
| feat | **订单详情弹窗→独立页面**：新增 panel-order-detail + 面包屑导航；get_order_detail 支持 order_no 参数 | index.php main.js | - |
| feat | **报价单/录单新增教材包+商品券列**：price_items 加 teaching_aid_id+product_coupon_id；优惠关联卡片4列（优惠方案/教材包/课时优惠券/商品券） | index.php main.js | - |
| feat | **录单自动发券+查重复用**：pay_enroll 写优惠快照到 orders 表（8字段）；get_order_detail 直接读 orders 不再 JOIN | index.php | - |
| fix | **实际价格公式统一**：max(0, 课时价格 - 优惠方案 - 课时优惠券 + 教材包原价 - 商品券)；4个保存函数全量重算 | main.js | - |
| fix | **课耗/退费金额排除教材包+商品券**：classPrice=actualPrice-teachingAidPrice+productCouponAmount；student_summary+order_detail 全量统一 | index.php | - |
| fix | **考勤退费拦截统一**：旧考勤关联订单有退费（待审批/审批中/已退费）→禁止修改；仅 delta≠0 或状态变化时触发 | index.php | - |
| fix | **扣课时上限保护**：所有 UPDATE 加 AND consumed_lessons+delta≤lesson_count；历史超扣数据归位 | index.php | - |
| fix | **追加扣课三级优先级**：同课程→同二级学科→同一级学科；排除退费中/已退费订单 | index.php | - |
| feat | **退费审批弹窗加手机号+学号** | index.php main.js | - |
| feat | **学员列表加分班按钮** | main.js | - |
| fix | **课表考勤按钮直接弹出考勤弹窗**：月视图补 class_id | index.php main.js | - |
| fix | **出勤→缺勤检查优化**：用旧 deduction_json 的订单ID检查退费状态 | index.php | - |
| fix | **退费金额前端展示与存储一致性**：排除教材包和商品券 | index.php | - |
| fix | **pay_enroll 命名参数重复** :ca→:coa | index.php | - |
| fix | **扣课时排除已退费订单**：退款/退款中订单过滤 | index.php | - |

### 2026-07-09 — 赠课功能（报价单 + 录单 + 学员展示 + 扣课优化）

| 类型 | 变更说明 | 涉及文件 | Commit |
|------|---------|---------|--------|
| feat | **报价单赠课功能**：price_items + orders 新增 gifted_lessons 字段；报价单表单新增赠送课时步进器（偶数步进，小课包隐藏）；save_price_plan INSERT 支持赠课；pay_enroll 录单带入赠课；get_student_courses 虚拟拆分赠课包（付费+赠课两条，同 order_no，赠课实付¥0） | index.php static/js/main.js static/css/style.css | 41b5c79 |
| feat | **报价单列表 inline 编辑赠课**：renderItemList 赠课列加 pi-editable；enterInlineEdit 新增 −/+ 步进按钮分支；saveInlineEdit 读取 editedValues | main.js | c5fccb2 |
| style | **设置价格弹窗加宽至1500px**：去横向滚动条，减 td/th padding 至 8px/10px | style.css | fc231a2 |
| fix | **扣课改为两轮机制**：先扣付费课时（lesson_count - consumed_lessons），三轮优先级走完后第二轮才扣赠课，修复同父订单赠课被优先消耗的 bug | index.php | 6ccb307 |
| fix | **课耗明细弹窗分离**：付费课包和赠课课包分开展示；赠课金额恒为 ¥0.00；付费弹窗过滤赠课消耗部分 | main.js | 7f5c1a6 |
| fix | **课耗金额精度修复**：去掉中间 round(classPrice/lc, 2)，改为一步 round(classPrice/lc * consumed, 2)，消除 0.10 误差 | index.php | 51ae286 |
| fix | **Code Review 修复**：selectEnrollPlan 列对齐、退费 remaining_lessons 负数保护（min(consumed, lessonCount)）、renderItemList 总计行多余 td | index.php main.js | 99d10be |
| fix | **保存报价单后回到设置价格主弹窗**：saveItem 改用 openModal('modal-price') 而非 closeModal('modal-price-item') | main.js | 94a2ef4 |

*（内容由AI生成，仅供参考）*
