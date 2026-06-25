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
| PHP 路径 | `C:\php-8.4\php.exe`（PHP 8.4.22） |
| 配置文件 | `C:\php-8.4\php.ini` |
| 监听端口 | `127.0.0.1:5001` |
| 数据库文件 | `market.db`（SQLite，项目根目录） |
| 访问地址 | http://127.0.0.1:5001 |

### 启动命令

```powershell
cd "D:\market-system-php"
C:\php-8.4\php.exe -S 127.0.0.1:5001
```

### 重启命令

```powershell
Stop-Process -Name "php" -Force -ErrorAction SilentlyContinue
Start-Process -FilePath "C:\php-8.4\php.exe" -ArgumentList "-S", "127.0.0.1:5001" -WorkingDirectory "D:\market-system-php" -WindowStyle Hidden
```

### 环境注意事项

1. **PHP 运行环境**：必须使用 `C:\php-8.4\php.exe`。不要使用 PATH 中的 Winget PHP（位于 `C:\Users\吴赛\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\`），其 SQLite3 扩展存在加载问题，会导致 `Class "SQLite3" not found` 致命错误。

2. **启动命令（后台运行）**：
   ```powershell
   Start-Process "C:\php-8.4\php.exe" -ArgumentList "-S 127.0.0.1:5001", "-t", "D:\market-system-php" -WindowStyle Hidden
   ```

3. **PHP 扩展**：PHP 8.4 需启用 `mbstring` 扩展，已修改 `C:\php-8.4\php.ini` 开启 `extension=mbstring`。

---

## 一、项目概述

- **系统名称**：TMS管理系统
- **系统定位**：教育培训行业市场资源与教务管理工具，覆盖资源录入、跟进、预约试听、公海流转、课程管理、学员管理、学科设置、交易订单、报价方案、员工管理、组织架构管理等完整业务闭环
- **技术栈**：PHP 8.4（内嵌 HTML）+ SQLite（WAL 模式）+ Vanilla JS（约 3033 行）+ CSS3（CSS Variables 设计令牌体系，约 1305 行）
- **架构模式**：单体 PHP 单文件应用（`index.php`，约 3200+ 行），前端内嵌于同一文件，API 通过 `?action=` 路由分发，所有 API 统一返回 JSON

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
│    SQLite               main.js / style.css       │
│   (market.db)           (static/js/ & static/css/) │
└──────────────────────────────────────────────────┘
```

### 2.2 技术选型

| 技术 | 选型理由 |
|------|----------|
| PHP 内嵌 HTML | 单文件部署，`php -S` 零配置启动 |
| SQLite | 零安装依赖，WAL 模式支持并发读写，外键约束开启 |
| Vanilla JS | 无框架依赖，约 3033 行完成完整 SPA 交互 |
| CSS Variables | 统一设计令牌（`--color-primary`/`--shadow-md` 等），便于主题定制 |
| ZipArchive + XML | 纯 PHP 解析 .xlsx 文件，零第三方依赖 |

### 2.3 目录结构

```
market-system-php/
├── index.php              # 主程序（后端 API + 前端 HTML，约 3200+ 行）
├── market.db              # SQLite 数据库（自动生成）
├── static/
│   ├── js/
│   │   └── main.js        # 前端逻辑（约 3033 行）
│   └── css/
│       └── style.css      # 样式表（约 1305 行）
└── PROJECT_SUMMARY.md     # 本文档
```

---

## 三、数据库设计

### 3.1 表概览（20 张表）

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
| `students` | 学员信息（含学号） | resource_id → resources.id |
| `classes` | 班级信息（标准班/活动班） | course_id → courses.id |
| `schedules` | 排课信息（规则排课/日期排课） | class_id → classes.id |
| `classrooms` | 教室信息 | — |
| `price_plans` | 价格方案 | course_id → courses.id |
| `price_items` | 报价单明细 | plan_id → price_plans.id |
| `orders` | 交易订单（子订单，含父订单号/现金/美团） | student_id → students.id, course_id → courses.id |
| `attendance_records` | 上课记录（考勤） | student_id → students.id, course_id → courses.id |
| `parent_orders` | 父订单（汇总同一录单的所有子订单） | parent_order_no → orders.parent_order_no |

### 3.2 resources（资源表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INTEGER PK | AUTO | 主键 |
| name | TEXT | '' | 客户姓名（必填） |
| phone | TEXT | '' | 电话（唯一性校验） |
| source | TEXT | '' | 来源渠道 |
| source_detail | TEXT | '' | 来源详情 |
| intention_level | TEXT | '' | 意向等级 |
| gender | TEXT | '' | 性别（增量字段） |
| birth_date | TEXT | '' | 出生日期（增量字段） |
| status | TEXT | '待跟进' | 原状态字段（已保留但前端不再展示，被跟进状态替代） |
| follow_status | TEXT | '' | **跟进状态**：未沟通/沟通中/已邀约未试听/已试听待转化/已转化—定金/已转化—全款/无效客户，共 7 个选项 |
| assigned_to | TEXT | '' | 归属人 |
| pool_type | TEXT | '我的资源' | 我的资源/资源公海 |
| created_at | TEXT | '' | 创建时间 |
| updated_at | TEXT | '' | 更新时间 |

### 3.3 employees（员工表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INTEGER PK | AUTO | 主键 |
| name | TEXT | '' | 姓名（必填，唯一性校验） |
| phone | TEXT | '' | 手机号（唯一性校验） |
| department | TEXT | '' | 归属部门 |
| position | TEXT | '' | 职位 |
| entry_date | TEXT | '' | 入职日期 |
| status | TEXT | '在职' | 在职/离职 |
| created_at | TEXT | '' | 创建时间 |
| updated_at | TEXT | '' | 更新时间 |

### 3.4 organizations（组织表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INTEGER PK | AUTO | 主键 |
| name | TEXT | '' | 组织名称（必填，同级同类型下唯一） |
| type | TEXT | '部门' | 部门/校区 |
| parent_id | INTEGER | 0 | 上级组织 ID（0 表示根节点） |
| sort_order | INTEGER | 0 | 排序号 |
| created_at | TEXT | '' | 创建时间 |

### 3.5 positions（岗位表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INTEGER PK | AUTO | 主键 |
| name | TEXT | — | 岗位名称（唯一约束） |
| sort_order | INTEGER | 0 | 排序号 |
| created_at | TEXT | '' | 创建时间 |

### 3.6 channels（渠道表）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INTEGER PK | 主键 |
| name | TEXT | 渠道名称 |
| created_at | TEXT | 创建时间 |

### 3.7 intention_levels（意向等级表）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INTEGER PK | 主键 |
| name | TEXT | 等级名称 |
| sort_order | INTEGER | 排序号（默认 0） |
| created_at | TEXT | 创建时间 |

默认数据：A-高意向(1) / B-中意向(2) / C-低意向(3) / D-无意向(4)

### 3.8 basic_types（基础类型表）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INTEGER PK | 主键 |
| category | TEXT | 分类标识（course_type / comm_type） |
| name | TEXT | 类型名称 |
| sort_order | INTEGER | 排序号（默认 0） |
| created_at | TEXT | 创建时间 |

默认数据：课程类型（试听课/正式课体验/测评课/其他），沟通方式（电话/微信/面谈/短信）

### 3.9 appointments（预约试听表）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INTEGER PK | 主键 |
| resource_id | INTEGER | 关联资源 ID |
| resource_name | TEXT | 关联资源名称（冗余） |
| student_name | TEXT | 学员姓名 |
| phone | TEXT | 电话 |
| course_type | TEXT | 课程类型 |
| appointment_time | TEXT | 预约时间 |
| status | TEXT | 已预约/已试听/已取消 |
| notes | TEXT | 备注 |
| created_at | TEXT | 创建时间 |

### 3.10 communication_records（沟通记录表）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INTEGER PK | 主键 |
| resource_id | INTEGER | 关联资源 ID |
| resource_name | TEXT | 关联资源名称（冗余） |
| content | TEXT | 沟通内容 |
| comm_type | TEXT | 沟通方式（电话/微信/面谈/短信） |
| created_at | TEXT | 创建时间 |

### 3.11 courses（课程表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INTEGER PK | AUTO | 主键 |
| name | TEXT | — | 课程名称（必填） |
| subject | TEXT | '' | 所属学科 |
| grade | TEXT | '' | 适用年级 |
| description | TEXT | '' | 课程描述 |
| small_package | TEXT | '' | 小课包标记（增量字段）。空字符串或 `'否'` 表示非小课包，`'是'`/`'1'`/`'小课包'` 表示是小课包。前端通过 `isSmallPackage()` 函数判断 |
| toddler | TEXT | '' | 低幼龄标记（增量字段） |
| campus_permission | TEXT | '' | 校区权限控制（增量字段） |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

### 3.12 subjects（学科表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INTEGER PK | AUTO | 主键 |
| name | TEXT | — | 学科名称（必填） |
| parent_id | INTEGER | 0 | 上级学科 ID（0 表示一级学科） |
| sort_order | INTEGER | 0 | 排序号 |

支持两级树形结构：一级学科 → 二级学科。

### 3.13 students（学员表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INTEGER PK | AUTO | 主键 |
| student_no | TEXT | '' | **学号**（10 位数字，唯一，自动生成） |
| resource_id | INTEGER | — | 关联资源 ID（可为空） |
| name | TEXT | — | 学员姓名（必填） |
| phone | TEXT | — | 电话（唯一约束） |
| source | TEXT | — | 来源 |
| follow_status | TEXT | — | 跟进状态 |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

### 3.14 classes（班级表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INTEGER PK | AUTO | 主键 |
| course_id | INTEGER | — | 关联课程 ID（必填） |
| name | TEXT | — | 班级名称（必填） |
| class_type | TEXT | '' | 班级类型：标准班 / 活动班 |
| max_students | INTEGER | 0 | 最大招生人数 |
| lesson_hours | INTEGER | 0 | 授课课时（必为偶数） |
| can_trial | TEXT | '0' | 是否可试听（0/1） |
| campus | TEXT | '' | 所属校区 |
| remark | TEXT | '' | 备注 |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

### 3.15 schedules（排课表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INTEGER PK | AUTO | 主键 |
| class_id | INTEGER | — | 关联班级 ID（必填） |
| rule_type | TEXT | '按规则排课' | 排课方式：按规则排课 / 按日期排课 |
| start_date | TEXT | '' | 开始日期 |
| end_date | TEXT | '' | 结束日期 |
| weekdays | TEXT | '' | 星期几上课，逗号分隔（如 '1,3,5' 表示周一三五） |
| time_slots | TEXT | '' | 每天时间段，JSON 格式存储 |
| holiday_enabled | INTEGER | 0 | 节假日是否排课（0/1） |
| teacher | TEXT | '' | 授课老师 |
| classroom | TEXT | '' | 上课教室（关联 classroom 名称） |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

### 3.16 classrooms（教室表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INTEGER PK | AUTO | 主键 |
| name | TEXT | — | 教室名称（唯一，必填） |
| capacity | INTEGER | 0 | 容纳人数 |
| campus | TEXT | '' | 所属校区 |
| remark | TEXT | '' | 备注 |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

### 3.17 price_plans（价格方案表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INTEGER PK | AUTO | 主键 |
| course_id | INTEGER | — | 关联课程 ID（必填） |
| name | TEXT | — | 方案名称（必填） |
| plan_type | TEXT | '' | 方案类型（增量字段）：新报 / 续费 / 小课包。由课程 small_package 决定是否锁死 |
| sort_order | INTEGER | 0 | 排序号 |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

### 3.18 price_items（报价单表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INTEGER PK | AUTO | 主键 |
| plan_id | INTEGER | — | 关联价格方案 ID（必填） |
| name | TEXT | — | 报价项名称（必填） |
| lesson_count | INTEGER | — | 课时数（必填） |
| unit_price | REAL | — | 单价（必填） |
| actual_price | REAL | — | 实际价格（必填） |
| sort_order | INTEGER | 0 | 排序号 |

### 3.19 orders（交易订单表 / 子订单）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INTEGER PK | AUTO | 主键 |
| order_no | TEXT | '' | **订单号**（16 位数字，唯一，自动生成） |
| parent_order_no | TEXT | '' | **父订单号**（16 位，同一录单的子订单共用） |
| student_id | INTEGER | — | 关联学员 ID（必填） |
| course_id | INTEGER | — | 关联课程 ID（必填） |
| plan_name | TEXT | — | 价格方案名称 |
| item_name | TEXT | — | 报价项名称 |
| order_type | TEXT | '' | **订单类型**（增量字段）：新报 / 续费 / 小课包，从价格方案 plan_type 继承 |
| lesson_count | INTEGER | — | 课时数 |
| actual_price | REAL | — | 实际成交价格（= cash_amount + meituan_amount） |
| cash_amount | REAL | 0 | **现金支付金额** |
| meituan_amount | REAL | 0 | **美团支付金额** |
| status | TEXT | '已报名' | 订单状态 |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

### 3.20 attendance_records（上课记录表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INTEGER PK | AUTO | 主键 |
| student_id | INTEGER | — | 关联学员 ID（必填） |
| course_id | INTEGER | — | 关联课程 ID（必填） |
| lesson_date | TEXT | — | 上课日期（必填） |
| status | TEXT | '出勤' | 出勤状态：出勤 / 请假 / 缺勤 |
| notes | TEXT | '' | 备注 |
| created_at | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

### 3.21 parent_orders（父订单表）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | INTEGER PK | AUTO | 主键 |
| parent_order_no | TEXT | — | 父订单号（16 位，唯一） |
| child_order_nos | TEXT | — | 关联的子订单号，逗号分隔（如 "xxx,yyy,zzz"） |
| course_name | TEXT | — | 报读课程名称 |
| total_lessons | INTEGER | 0 | 报读总课时数（所有子订单课时之和） |
| student_name | TEXT | — | 学员姓名 |
| phone | TEXT | — | 手机号 |
| student_no | TEXT | — | 学号 |
| enroll_time | TEXT | — | 报名时间 |
| total_price | REAL | 0 | 总价格（所有子订单 actual_price 之和） |
| cash_amount | REAL | 0 | 现金总额 |
| meituan_amount | REAL | 0 | 美团总额 |
| created_at | TEXT | '' | 创建时间 |

### 3.22 表关系图

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

## 四、后端 API 完整列表

所有 API 通过 `?action=<name>` 路由，统一返回 JSON。共 **85 个** action。

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
| `list_students` | GET | 学员列表（分页，支持 keyword 筛选，含学号字段） |
| `get_student` | GET | 查询单个学员详情 |
| `add_student` | POST | 新增学员（自动生成 10 位学号，手机号唯一约束） |
| `update_student` | POST | 编辑学员信息 |
| `delete_student` | POST | 删除学员 |
| `create_student_from_resource` | POST | 从资源创建学员（按手机号查重，存在则复用，不存在则新建，返回 student_id） |
| `get_student_courses` | GET | 获取学员已报读课程列表（基于 orders 表关联查询） |

### 4.13 班级管理（4 个）

| action | 方法 | 说明 |
|--------|------|------|
| `list_classes` | GET | 班级列表（支持 keyword 搜索） |
| `add_class` | POST | 新增班级（授课课时必须为偶数，前后端双重校验） |
| `update_class` | POST | 编辑班级（动态字段更新，授课课时偶数校验） |
| `delete_class` | POST | 删除班级 |

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
| `pay_enroll` | POST | **核心报名支付**：按方案下所有报价单逐条生成子订单（自动生成 order_no 和 parent_order_no），支持现金+美团双支付方式，服务端校验金额合计=总额，采用"逐个填满"分配策略。从价格方案继承 plan_type 写入所有子订单的 order_type，若课程 small_package 非空则强制为"小课包" |
| `enroll_course` | POST | 学员直接报名课程（单条订单） |
| `enroll_from_resource` | POST | 从资源入口报名：将资源转为学员并生成订单（保留兼容） |
| `create_student_from_resource` | POST | 从资源创建学员记录（供 panel-enroll 前端调用） |
| `list_orders` | GET | 订单列表（15 列：订单号/父订单号/学号/编号/学员姓名/课程名称/价格方案/报价单名称/订单类型/课时数量/订单金额/现金/美团/状态/报名时间），含 `payment_summary` 汇总和 order_type 字段，支持 keyword 搜索 |

### 4.17 考勤 / 上课记录（4 个）

| action | 方法 | 说明 |
|--------|------|------|
| `list_attendance` | GET | 按 student_id 查询上课记录列表 |
| `add_attendance` | POST | 新增上课记录（student_id / course_id / lesson_date / status / notes） |
| `update_attendance` | POST | 编辑上课记录（支持任意字段动态更新） |
| `delete_attendance` | POST | 删除上课记录 |

### 4.18 父订单（1 个）

| action | 方法 | 说明 |
|--------|------|------|
| `list_parent_orders` | GET | 父订单列表（分页，支持 keyword 搜索学员名/课程名/父订单号/学号） |

### 4.19 统计（1 个）

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
| | 基础设置 | 学科设置 | `panel-subjects` |
| | | 教室管理 | `panel-classrooms` |
| **员工管理** | 员工名册 | — | `panel-employees` |
| | 岗位管理 | — | `panel-position-settings` |
| | 组织管理 | — | `panel-org` |

### 5.3 十七个面板

| 面板 | 功能 | 关键特性 |
|------|------|----------|
| 我的资源 | CRUD、批量导入/分配/移入公海、预约、沟通、导出 | 跟进状态行内编辑（点击标签下拉选择 7 种状态）、姓名/手机号/渠道/创建时间/跟进状态筛选 + 搜索按钮手动触发 + 空状态提示；**归属人**列显示员工姓名；提供**资源报名入口**按钮（跳转 panel-enroll） |
| 预约试听名单 | 预约记录增删改查、状态筛选 | 课程类型下拉来自 basic_types |
| 资源公海 | 查看、领取（单个/批量）、导出 | 搜索框实时筛选 |
| 渠道设置 | 渠道字典增删改，双击编辑 | 改名事务同步 resources，重名校验 |
| 意向等级设置 | 意向等级增删改，支持排序号 | 改名/改排序事务同步 resources |
| 基础类型设置 | 课程类型 + 沟通方式 Tab 切换，增删改排序 | 改名事务同步 appointments/communication_records |
| **课程管理** | 课程 CRUD、价格方案、报价单、导出 | **价格方案**：每门课程可配置多个价格方案，每个方案包含多条报价单（课时数、单价、实际价格），支持设置方案类型（新报/续费/小课包）；**小课包**标记；**低幼龄**标记；**校区权限**控制字段 |
| **学员管理** | 学员 CRUD、搜索、详情页（3 标签页） | 学员可关联资源（resource_id）；**学员详情页**：标签页布局（报读课程 / 交易订单 / 上课记录），含学号字段；列表页提供**报名**按钮跳转 panel-enroll |
| **班级管理** | 班级 CRUD、排课入口 | 班级列表表格（ID/名称/关联课程/班级类型/招生人数/授课课时/是否可试听/校区/备注/创建时间/操作-排课/编辑/删除）+ 搜索框 + 新增班级按钮；新增/编辑时授课课时必须为偶数（前端+后端双重校验） |
| **交易订单** | 订单列表查看（15 列） | 列：订单号、父订单号、学号、编号、学员姓名、课程名称、价格方案、报价单名称、订单类型、课时数量、订单金额、现金、美团、状态、报名时间；订单类型列以三色标签展示（新报=蓝/续费=绿/小课包=橙）；列表顶部**支付方式汇总卡片**（现金/美团/总计）；支持 keyword 搜索 |
| **报名详情** | 独立报名流程页面（panel-enroll） | 展示学员/资源姓名+手机号（只读）→ 选择课程 → 展示价格方案卡片（含类型标签：新报=蓝/续费=绿/小课包=橙）→ 选中方案展示报价单明细表格 + 合计金额 → **支付方式区域**（现金+美团输入框，实时校验金额匹配）→ 确认支付 → 逐条生成子订单（逐个填满分配策略，所有子订单继承方案 plan_type）+ 父订单 → 返回来源页 |
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

### 6.5 导出

**资源导出**：
- 文件名：`资源导出_YYYYmmdd_HHMMSS.csv`
- UTF-8 BOM 编码，Excel 直接打开不乱码
- 表头：姓名、电话、来源渠道、来源详情、意向等级、性别、出生日期、跟进状态、归属人、创建时间、更新时间（共 11 列）
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
| 上课记录 | 该学员的出勤记录，支持新增/编辑/删除 | `attendance_records` 表 + 对应 CRUD API |

标签切换纯 JS 实现，不刷新页面。出勤状态三色标签：出勤（绿）/ 请假（橙）/ 缺勤（红）。

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

- 使用 `ALTER TABLE ADD COLUMN` 增量添加 `gender` / `birth_date` / `follow_status` 字段，`@` 抑制报错以兼容已存在的旧库
- 使用 `ALTER TABLE ADD COLUMN` 增量添加 `small_package` / `toddler` / `campus_permission` 字段
- 使用 `ALTER TABLE ADD COLUMN` 增量添加 `plan_type` 字段（price_plans 表）
- 使用 `ALTER TABLE ADD COLUMN` 增量添加 `order_type` 字段（orders 表）
- 使用 `ALTER TABLE ADD COLUMN` 增量添加 `order_no` / `parent_order_no` / `cash_amount` / `meituan_amount` / `paid_amount` 字段（orders 表）
- 使用 `ALTER TABLE ADD COLUMN` 增量添加 `student_no` 字段（students 表）
- `employees` / `organizations` / `subjects` / `courses` / `students` / `classes` / `schedules` / `classrooms` / `attendance_records` / `parent_orders` 等表使用 `CREATE TABLE IF NOT EXISTS`
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
- `index.php`：price_plans 表 + plan_type、orders 表 + order_type、ALTER 迁移、save_price_plan / get_course_plans / pay_enroll / list_orders API
- `static/js/main.js`：isSmallPackage()、showPriceModal 锁死逻辑、addPlan/editPlan/savePlan、renderPlanList/renderItemList 类型标签、selectEnrollPlan/confirmPayEnroll 传递 plan_type、renderOrderTable/loadStudentOrders 订单类型列
- `static/css/style.css`：`.tag-new-enroll`（蓝）、`.tag-renewal`（绿）、`.tag-small-pack`（橙）
*（内容由AI生成，仅供参考）*
