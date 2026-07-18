---
AIGC:
    Label: "1"
    ContentProducer: 001191440300708461136T1XGW3
    ProduceID: 3f11eb7fa23d664c4b1c1527387f20fd_18a2e9db80ff11f182875254006c9bbf
    ReservedCode1: /xStley5sUlHwy+JHDmf3n4yCSvcFTTltPonZ20WREx6E8jnu0sFsAxSSkXs5fk/mmpYeUlNUOjyKWQYILe2QF7eHO3IJRqg1zM2E6CdYhKw93fQHTHWk+xtvQ2RVQmXlrm/Y1ehP8RLDdGW9X+99ypcmvvVk75CRFrvRoU/xH63q/pfNsCCkntHGjw=
    ContentPropagator: 001191440300708461136T1XGW3
    PropagateID: 3f11eb7fa23d664c4b1c1527387f20fd_18a2e9db80ff11f182875254006c9bbf
    ReservedCode2: /xStley5sUlHwy+JHDmf3n4yCSvcFTTltPonZ20WREx6E8jnu0sFsAxSSkXs5fk/mmpYeUlNUOjyKWQYILe2QF7eHO3IJRqg1zM2E6CdYhKw93fQHTHWk+xtvQ2RVQmXlrm/Y1ehP8RLDdGW9X+99ypcmvvVk75CRFrvRoU/xH63q/pfNsCCkntHGjw=
---

# TMS 系统业务逻辑交接文档 — 总览

> 本文档基于 D:\market-system-php 真实代码、数据库迁移脚本（migrations/）与已有 PRD 生成，供目标团队使用 DDD + Spring Boot + PostgreSQL 重构系统参考。
> 所有结论均标注代码/数据/文档证据。无法确认的规则标记「待业务确认」。

---

## 1. 系统定位与用户角色

### 1.1 系统定位
TMS（Training Management System）是面向**教育培训行业**的市场-教务-财务一体化管理系统，覆盖从市场线索到学员结业退费的全生命周期管理。

**证据**：`PROJECT_SUMMARY.md` 第一节「系统概述」；`index.php` 入口路由分发 `?action=` 模式。

### 1.2 用户角色
当前系统**未实现真正的 RBAC 权限控制**（详见 `07-permission-matrix.md`）。从业务功能可推导出以下逻辑角色：

| 角色 | 职责 | 功能入口 |
|------|------|----------|
| 市场专员 / 课程顾问 | 线索录入、分配、跟进、预约试听、转化报名 | 市场管理模块 |
| 教务老师 | 班级创建、分班、排课、考勤、扣课 | 教务管理模块 |
| 财务 | 收款确认、退费审批、账户退款 | 退费管理 / 账户 |
| 校区管理员 | 校区/学科/教师/教室/税率基础配置 | 基础设置 |
| 系统管理员 | 组织架构、数据看板 | 数据中心（待开发） |

**证据**：`PROJECT_SUMMARY.md` 6.26 节「校区-学科-授课老师关联」；`index.php` 导航栏「市场管理→教务管理→数据中心→员工管理」顺序（2026-07-07 提交 0758bdc）。

---

## 2. 系统全部菜单与功能模块

### 2.1 一级模块（左侧导航）
根据 `index.php` 导航定义与 `PROJECT_SUMMARY.md` 更新日志：

1. **市场管理**
   - 我的资源（线索/公海）
   - 预约试听
   - 学员管理
2. **教务管理**
   - 课程管理
   - 报价方案
   - 交易订单
   - 班级与排课
   - 考勤管理（含课表视图）
   - 活动管理
   - 画具管理
   - 优惠管理
   - 基础设置（税率、上课时段、组织架构）
3. **数据中心**（待开发）
4. **员工管理**（待开发）

### 2.2 功能模块清单

| 模块 | 核心功能 | 关键 API（action） |
|------|----------|-------------------|
| 线索管理 | 录入、分配、公海、领取、转化 | `save_resource`, `assign_resource`, `claim_resource`, `list_resources` |
| 预约试听 | 级联预约、状态流转、取消 | `book_trial`, `cancel_trial`, `search_trial_sessions`, `get_appointments` |
| 学员管理 | 建档、家庭关系、类型判定、授课老师关联 | `save_student`, `get_student`, `list_students`, `delete_student` |
| 课程管理 | 课程 CRUD、校区权限 | `save_course`, `list_courses`, `delete_course` |
| 报价方案 | 方案配置、报价单、赠课、优惠关联 | `save_price_plan`, `save_price_item`, `get_price_plan` |
| 交易订单 | 报名、支付、父子订单、作废 | `enroll_course`, `pay_enroll`, `void_order`, `list_orders` |
| 学员账户 | 储值、余额支付、退款 | `top_up_account`, `get_student_account`, `approve_refund` |
| 班级排课 | 班级、分班、排课、课表 | `save_class`, `add_student_to_class`, `create_schedule_from_grid` |
| 考勤扣课 | 考勤、三级扣课、冲正 | `save_class_attendance`, `update_attendance`, `list_attendance` |
| 退费转校 | 退费审批、转校 | `submit_refund`, `approve_refund`, `submit_transfer` |
| 活动管理 | 活动报名、收费、扣课、考勤 | `save_activity`, `enroll_activity`, `list_activity_attendance` |
| 画具管理 | 教材/画具销售退回 | `save_teaching_aid`, `sell_teaching_aid`, `return_teaching_aid` |
| 优惠管理 | 优惠方案、优惠券、发放 | `save_discount_plan`, `save_coupon`, `grant_coupon` |
| 基础设置 | 税率、时段、组织 | `save_tax_rate`, `save_class_period`, `save_organization` |

**证据**：`PROJECT_SUMMARY.md` 第五节「API 路由归属」；`api/router.php` 模块化 57 个 action；`index.php` switch 中 105 个 action。

---

## 3. 功能完成度

### 3.1 已完成
- 线索全生命周期（录入/分配/公海/领取/转化）
- 预约试听四态流转
- 学员建档 + 家庭关系 + 类型自动判定
- 课程报价方案 + 报价单 + 赠课
- 交易订单（新报/续费/扩科/小课包/赠课）+ 父子订单 + 多支付渠道
- 学员储值账户 + 余额支付 + 账户退款
- 班级/分班/排课/课表
- 考勤三级扣课 + 退费冻结 + 冲正重扣
- 退费三级审批 + 转校审批
- 活动报名/收费/扣课/考勤
- 画具销售/退回
- 优惠方案/优惠券/发放
- 税率设置 + 税后课耗

### 3.2 部分完成
| 功能 | 完成度 | 说明 |
|------|--------|------|
| 转校 | 核心流程可用 | 课包子订单号继承已实现，但跨校区课时/金额计算边界待确认 |
| 活动报名 | 核心流程可用 | 成人/学员混合报名已实现，但扣课规则细节待确认 |
| 税后课耗 | 已实现 | 历史数据 NULL 降级处理，迁移时需重算 |

### 3.3 待开发（来自 TODO.md）
- 转卖逻辑
- 录单信息优化（部分字段已加）
- 账户充值优化
- 次月录单
- 迁入订单
- 人事花名册
- 权限系统（RBAC）
- 数据中心看板
- 扩科独立流程（当前扩科复用报名流程）

**证据**：`TODO.md` 14 个功能优化项；`PROJECT_SUMMARY.md` 更新日志 2026-07-07「导航顺序调整：市场管理→教务管理→数据中心→员工管理」。

---

## 4. 主要业务闭环

```
市场线索 ──分配/公海/领取──→ 预约试听 ──到场转化──→ 学员建档
                                                          │
                                                          ↓
课程报价方案 ──报价单──→ 交易订单（父子）──支付分摊──→ 学员账户/收款
                                                          │
                              ┌───────────────────────────┤
                              ↓                           ↓
                        班级分班排课 ──→ 考勤扣课 ──→ 课耗/收入确认
                                                          │
                              ┌───────────────────────────┤
                              ↓                           ↓
                        退费审批 ──→ 课时归还/余额返还   转校审批 ──→ 跨校区转移
                                                          │
                              ┌───────────────────────────┤
                              ↓
                        活动报名 ──收费/扣课/考勤
```

**证据**：`PROJECT_SUMMARY.md` 第六节「关键业务规则」；`index.php` 各 action 调用链。

---

## 5. 技术架构与代码入口

### 5.1 技术栈
- **后端**：PHP 8.4 + MySQL 8.4.9（PDO 原生 SQL，无 ORM）
- **前端**：Vanilla JS + 原生 CSS（Soft Industrial 风格），单页应用
- **入口**：`index.php` 单入口，`?action=` 路由分发
- **API 模块化**：`api/router.php` 分发 57 个已拆分 action；其余 105 个在 `index.php` switch

**证据**：`PROJECT_SUMMARY.md` 第二节「技术架构」；`api/router.php` 文件头注释。

### 5.2 代码入口
| 入口 | 职责 |
|------|------|
| `index.php` | 主入口，105 个 action + 前端页面渲染 |
| `api/router.php` | 模块化 API 分发（dictionaries/discounts/orders/organizations/settings/teaching_aids） |
| `api/*.php` | 各模块 action 实现 |
| `app/bootstrap.php` | 启动层（DB 配置、PDO、helper） |
| `migrations/bootstrap_schema.php` | 基线建表（44 张表） |
| `migrations/versions/*.php` | 增量迁移 |

**证据**：`PROJECT_SUMMARY.md` 更新日志 2026-07-13「数据库配置与启动层拆分」「迁移系统正规化」。

### 5.3 数据库
- 44 张表，MySQL 8.4.9
- 迁移系统：`schema_migrations` 表 + 版本运行器 + 并发锁
- 字符集：utf8mb4

**证据**：`migrations/bootstrap_schema.php`（843 行，44 张表）；`PROJECT_SUMMARY.md` 第三节「数据库设计」。

---

## 6. 关键技术债和已知 BUG

### 6.1 已知 BUG（来自 TODO.md）
| BUG | 状态 | 说明 |
|-----|------|------|
| 退费金额计算有误 | **未修复** | TODO.md 记录，需复核 `submit_refund` 金额公式 |
| 退费中订单考勤拦截 | 已修复 | 2026-06-30 提交，增加 `refund_status` 过滤 |
| 扣课优先级 BUG | 已修复 | 2026-07-09 提交，改为两轮机制（先付费后赠课） |

### 6.2 技术债
1. **无权限控制**：所有 action 无鉴权，任何人可调用任意接口（详见 07 文档）
2. **无分页接口**：`list_*` 系列接口无分页参数，大数据量性能风险
3. **SQL 注入风险**：历史代码存在 `$db->quote` 双引号包装问题（部分已修复）
4. **全局状态重算**：早期 `consumed_lessons` 全局重算逻辑已废弃，但代码残留
5. **冗余快照字段**：orders 表 8 个优惠快照字段（冗余设计，迁移时需评估）
6. **deduction_json**：考勤扣课分配以 JSON 存储于 `class_attendance`，非关系型
7. **无事务一致性保障**：部分写操作无 `beginTransaction` 包裹
8. **前端 ID 冲突**：历史存在 `filter-subject1` 等 ID 冲突（已修复但需审查）

**证据**：`TODO.md`；`PROJECT_SUMMARY.md` 更新日志中 fix 记录；`index.php` 代码审查。

---

## 7. 其他文档索引

| 文档 | 内容 |
|------|------|
| [01-domain-map.md](./01-domain-map.md) | 现有功能到目标领域映射 |
| [02-business-processes.md](./02-business-processes.md) | 14 个核心业务流程（Mermaid） |
| [03-business-rules.md](./03-business-rules.md) | 业务规则提取（TMS-RULE-XXX） |
| [04-state-machines.md](./04-state-machines.md) | 状态机定义 |
| [05-api-catalog.md](./05-api-catalog.md) | API 全量清单 |
| [06-data-dictionary.md](./06-data-dictionary.md) | 数据字典 |
| [07-permission-matrix.md](./07-permission-matrix.md) | 权限矩阵 |
| [08-migration-guide.md](./08-migration-guide.md) | 迁移指南 |
| [09-open-questions.md](./09-open-questions.md) | 待确认问题（P0/P1/P2） |
| [10-acceptance-cases.md](./10-acceptance-cases.md) | 验收案例 |

---

## 8. 重要说明

1. **本文档不修改任何业务代码、不重构、不新增接口、不修改数据库。**
2. **不输出任何敏感信息**（密码、密钥、真实手机号、身份证、银行卡、生产数据）。
3. **现有代码结构 ≠ 未来领域结构**：文档仅描述现状，目标领域划分见 `01-domain-map.md`。
4. **代码行为 ≠ 正确业务规则**：所有规则标注「待业务确认」的，需业务方最终拍板。
5. **证据格式**：`文件路径:行号, action名称` / `migrations/xxx.php, 表名` / `docs/PRD-xxx.md`。

---

*生成日期：2026-07-16 | 基于代码版本：2026-07-15 最新提交*
*（内容由AI生成，仅供参考）*
