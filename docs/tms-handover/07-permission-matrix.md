---
AIGC:
    Label: "1"
    ContentProducer: 001191440300708461136T1XGW3
    ProduceID: 3f11eb7fa23d664c4b1c1527387f20fd_13538fd980ff11f1a60e525400e6dd8f
    ReservedCode1: zoJJ4rQnpGfTLfqBZR1+7zRK7oG33EV4iTkdlWQpKDM36YUo4ivrnhxPFHuODVTFb3KTtkuhzf+7fqTXxGFKfSl6ga8XM3HQZq5uuyLrQ7G1XLlwSRv0dKOuttm00czIj9i8rgcA7Ouf4wjvPF4zcM+6KbK+4K65g8VrhERXgGEKdfyzuVF8s2MjAG0=
    ContentPropagator: 001191440300708461136T1XGW3
    PropagateID: 3f11eb7fa23d664c4b1c1527387f20fd_13538fd980ff11f1a60e525400e6dd8f
    ReservedCode2: zoJJ4rQnpGfTLfqBZR1+7zRK7oG33EV4iTkdlWQpKDM36YUo4ivrnhxPFHuODVTFb3KTtkuhzf+7fqTXxGFKfSl6ga8XM3HQZq5uuyLrQ7G1XLlwSRv0dKOuttm00czIj9i8rgcA7Ouf4wjvPF4zcM+6KbK+4K65g8VrhERXgGEKdfyzuVF8s2MjAG0=
---

# 07-permission-matrix.md — 权限矩阵

> 版本：v1.0
> 日期：2026-07-16
> 结论：当前系统**未实现任何登录鉴权和 RBAC 权限控制**。所有 ~173 个 API 接口为完全开放状态。

---

## 1. 当前实际状态

### 1.1 鉴权状态

通过全面扫描 index.php（10408 行）和 api/\*.php（6 个模块文件），**未发现任何 session_start、登录校验、token 验证、角色判断或权限过滤代码**。

| 检查项 | 扫描范围 | 结果 |
|--------|----------|------|
| session_start / $_SESSION | index.php + api/\*.php | 不存在（仅有 computeSessions 排课函数，无关联） |
| 登录/登出逻辑 | index.php + api/\*.php | 不存在 |
| Token/JWT 校验 | index.php + api/\*.php | 不存在 |
| 角色/权限检查 | index.php + api/\*.php | 不存在 |
| RBAC 中间件 | 全量代码 | 不存在 |

**证据**：
- index.php: 入口直接 switch($action)，无前置鉴权；代码审计中所有 action 直接执行业务逻辑
- api/router.php: buildExtractedApiRoutes() 仅做路由注册，不包含鉴权中间件
- 所有 api/\*.php 文件：函数体直接从 DB 操作开始，无权限检查

### 1.2 当前每个面板/API 的实际操作范围

由于无鉴权，任何能访问系统 URL 的用户（通常为局域网内部员工）可以：

| 模块 | 可执行操作 | 风险 |
|------|-----------|------|
| 市场管理 - 线索 | 查看全部线索、增删改、批量导入导出、分配、公海操作 | 高：可删除他人线索 |
| 市场管理 - 预约 | 查看全部预约、预约/取消试听 | 中：可取消他人预约 |
| 市场管理 - 学员 | 查看全部学员、建档、删除 | 高：可删除学员数据 |
| 教务管理 - 课程 | 查看全部课程、增删改 | 高：可删除课程 |
| 教务管理 - 报价 | 查看全部方案、增删改 | 高：可修改报价 |
| 教务管理 - 订单 | 查看全部订单、报名、支付、作废 | 高：可作废他人订单 |
| 教务管理 - 班级排课 | 查看全部班级、创建、分班、排课 | 高：可修改课表 |
| 教务管理 - 考勤 | 查看全部考勤、考勤、修改、冲正 | 高：可修改历史考勤 |
| 教务管理 - 活动 | 查看全部活动、增删改、报名 | 高：可删除活动 |
| 教务管理 - 画具 | 查看、销售、退回 | 中 |
| 教务管理 - 优惠 | 查看全部方案、优惠券、发放 | 中 |
| 财务 - 退费 | 查看全部退费、审批 | 高：可审批任意退费 |
| 财务 - 账户 | 查看全部账户、充值、退款 | 高：可操作任意账户余额 |
| 基础设置 | 税率、时段、组织 | 高：可修改税率影响财务 |

### 1.3 每个 API 的鉴权现状

所有 ~173 个 API action **均为完全开放状态**，具体情况：

| API 类别 | 数量 | 鉴权 | 风险 |
|----------|------|------|------|
| GET 查询类 | ~50 | 无 | 信息泄露（全校学员/订单/账户/线索可查） |
| POST 创建类 | ~45 | 无 | 任意创建线索/学员/订单/班级 |
| POST 修改类 | ~35 | 无 | 任意修改报价/课程/考勤/排课 |
| POST 删除类 | ~15 | 无 | 任意删除学员/线索/课程/活动 |
| POST 审批类 | ~10 | 无 | 任意审批退费/转校 |
| POST 资金操作 | ~8 | 无 | 任意充值/退款/作废订单 |
| 导出类 | ~5 | 无 | 全量数据可导出 |

### 1.4 无菜单级别的页面隔离

虽然前端 index.php 定义了导航菜单结构，但由于无服务端鉴权，实际上：
- 任何人可以直接调用任何 API（不经过菜单）
- 任何人可以通过修改 URL 参数访问任意功能
- 员工管理模块标记为"待开发"，但 `get_employees` 等 API 已实现且开放

---

## 2. 当前权限矩阵（基于逻辑角色推导）

> 虽然系统无权限控制，但从业务逻辑可反推出合理的角色-菜单-操作关系。

| 用户角色 | 菜单 | 查询 | 新增 | 修改 | 删除/作废 | 审批 | 数据范围 |
|----------|------|------|------|------|----------|------|----------|
| 市场专员 | 我的资源 | 自己名下线索 | 线索 | 自己名下 | 不可删 | — | 仅自己的线索 |
| 市场专员 | 公海 | 公海线索 | — | — | — | — | 仅公海 |
| 市场专员 | 预约试听 | 全部预约 | 预约 | 自己创建的 | — | — | 全校 |
| 市场专员 | 学员管理 | 全部 | 建档 | — | — | — | 全校 |
| 教务老师 | 课程管理 | 全部 | — | — | — | — | 全校 |
| 教务老师 | 报价方案 | 全部 | — | — | — | — | 全校 |
| 教务老师 | 交易订单 | 全部 | 报名 | — | — | — | 全校 |
| 教务老师 | 班级排课 | 全部 | 班级/分班/排课 | 自己管理的 | — | — | 自己校区 |
| 教务老师 | 考勤管理 | 全部 | 考勤 | 历史考勤 | — | — | 自己校区 |
| 教务老师 | 活动管理 | 全部 | 活动 | 自己校区 | — | — | 全校 |
| 教务老师 | 画具管理 | 全部 | — | — | — | — | 全校 |
| 教务老师 | 优惠管理 | 全部 | 方案/券 | — | — | — | 全校 |
| 财务 | 退费管理 | 全部 | — | — | — | 一级+二级审批 | 全校 |
| 财务 | 学员账户 | 全部 | — | 审批退款 | — | — | 全校 |
| 校区管理员 | 基础设置 | 自己校区 | 税率/时段/教室 | 自己校区 | — | — | 仅自己校区 |
| 校区管理员 | 组织管理 | 全部 | — | — | — | — | 全校 |
| 系统管理员 | 全部 | 全部 | 全部 | 全部 | — | — | 全局 |

---

## 3. 新平台 RBAC 权限方案建议

### 3.1 权限模型

建议采用 **RBAC（角色-权限）** 模型，带校区数据范围隔离：

```
角色(Role) → 权限组(Permission Group) → 具体权限(Permission)
                                              ↓
                                         数据范围(Data Scope)
```

### 3.2 建议角色定义

| 角色 | 角色标识 | 默认数据范围 |
|------|----------|------------|
| 超级管理员 | SUPER_ADMIN | 全局 |
| 校区主管 | CAMPUS_MANAGER | 所属校区及子校区 |
| 市场主管 | MARKETING_MANAGER | 所属校区线索 + 公海 |
| 市场专员/课程顾问 | MARKETING_STAFF | 自己线索 + 公海 |
| 教务主管 | ACADEMIC_MANAGER | 所属校区 |
| 教务老师 | ACADEMIC_STAFF | 所属校区班级 |
| 财务 | FINANCE | 全局（可配置校区限制） |
| 财务主管 | FINANCE_MANAGER | 全局 |

### 3.3 建议权限点清单（≥ 50 个）

#### 3.3.1 线索管理 (resources)

| 权限点 | 标识 | 说明 |
|--------|------|------|
| 线索查看 | resource:read | 查看线索列表和详情 |
| 线索新增 | resource:create | 录入新线索 |
| 线索编辑 | resource:update | 修改线索信息 |
| 线索删除 | resource:delete | 删除线索 |
| 线索分配 | resource:assign | 将线索分配给他人 |
| 批量导入 | resource:import | Excel 批量导入 |
| 批量导出 | resource:export | 导出全量线索 |
| 公海领取 | resource:claim | 从公海领取线索 |
| 公海回收 | resource:pool | 将线索放入公海 |

#### 3.3.2 试听预约 (appointment)

| 权限点 | 标识 | 说明 |
|--------|------|------|
| 预约查看 | appointment:read | 查看预约列表 |
| 预约创建 | appointment:create | 创建试听预约 |
| 预约取消 | appointment:cancel | 取消预约 |
| 到场确认 | appointment:attend | 确认学员到场 |

#### 3.3.3 学员管理 (student)

| 权限点 | 标识 | 说明 |
|--------|------|------|
| 学员查看 | student:read | 查看学员列表和详情 |
| 学员建档 | student:create | 新建学员 |
| 学员编辑 | student:update | 修改学员信息 |
| 学员删除 | student:delete | 删除学员（软删除） |
| 家庭关系管理 | student:family | 维护家庭关系 |

#### 3.3.4 课程管理 (course)

| 权限点 | 标识 | 说明 |
|--------|------|------|
| 课程查看 | course:read | 查看课程列表 |
| 课程创建 | course:create | 新增课程 |
| 课程编辑 | course:update | 修改课程 |
| 课程删除 | course:delete | 删除课程（软删除） |
| 报价方案查看 | pricing:read | 查看报价方案 |
| 报价方案编辑 | pricing:update | 创建/修改报价方案 |
| 报价方案删除 | pricing:delete | 删除报价方案 |

#### 3.3.5 订单管理 (order)

| 权限点 | 标识 | 说明 |
|--------|------|------|
| 订单查看 | order:read | 查看订单列表和详情 |
| 报名下单 | order:create | 创建报名/续费/扩科订单 |
| 订单支付 | order:pay | 确认支付 |
| 订单作废 | order:void | 作废订单 |
| 赠课操作 | order:gift | 赠送课时 |
| 小课包下单 | order:small_package | 小课包下单 |
| 订单导出 | order:export | 导出订单 |

#### 3.3.6 班级与排课 (class)

| 权限点 | 标识 | 说明 |
|--------|------|------|
| 班级查看 | class:read | 查看班级列表 |
| 班级创建 | class:create | 创建班级 |
| 班级编辑 | class:update | 修改班级信息 |
| 分班操作 | class:assign | 学员分班 |
| 排课操作 | schedule:manage | 创建/修改课表 |
| 课表查看 | schedule:read | 查看课表 |

#### 3.3.7 考勤管理 (attendance)

| 权限点 | 标识 | 说明 |
|--------|------|------|
| 考勤查看 | attendance:read | 查看考勤记录 |
| 考勤录入 | attendance:create | 录入考勤（含扣课） |
| 考勤修改 | attendance:update | 修改已录入考勤 |
| 考勤冲正 | attendance:reverse | 冲正历史考勤 |

#### 3.3.8 活动管理 (activity)

| 权限点 | 标识 | 说明 |
|--------|------|------|
| 活动查看 | activity:read | 查看活动列表 |
| 活动创建 | activity:create | 创建活动 |
| 活动编辑 | activity:update | 修改活动 |
| 活动报名 | activity:enroll | 为学员报名活动 |
| 活动考勤 | activity:attendance | 活动考勤操作 |

#### 3.3.9 账户与退费 (wallet)

| 权限点 | 标识 | 说明 |
|--------|------|------|
| 账户查看 | wallet:read | 查看学员账户余额与流水 |
| 储值充值 | wallet:topup | 账户充值 |
| 退费申请 | refund:create | 发起退费申请 |
| 退费一级审批 | refund:approve1 | 一级审批 |
| 退费二级审批 | refund:approve2 | 二级审批 |
| 账户退款 | wallet:refund | 从账户余额退款 |

#### 3.3.10 基础设置 (settings)

| 权限点 | 标识 | 说明 |
|--------|------|------|
| 组织管理 | org:manage | 管理组织架构 |
| 税率设置 | settings:tax | 校区税率配置 |
| 时段设置 | settings:period | 上课时段配置 |
| 字典管理 | settings:dict | 渠道/意向等级等字典 |

### 3.4 数据范围策略

| 数据范围类型 | 说明 | 适用场景 |
|------------|------|----------|
| ALL (全局) | 查看所有数据 | 超级管理员、财务 |
| CAMPUS (校区) | 仅查看所属校区数据 | 校区主管、校区管理员 |
| CAMPUS_AND_CHILDREN | 查看所属校区及子校区 | 大区主管 |
| SELF (本人) | 仅查看自己创建/负责的数据 | 市场专员（线索） |

**实施建议**：
- 线索默认 SELF 范围，主管可查看 CAMPUS 范围
- 订单/学员/班级默认 CAMPUS 范围
- 财务默认 ALL 范围
- 数据范围可通过 Role 级别的 `data_scope` 属性灵活配置，支持特例覆盖

### 3.5 权限与 API 映射示例

| API (新平台 REST) | 方法 | 权限点 | 数据范围 |
|--------------------|------|--------|----------|
| POST /api/v1/resources | POST | resource:create | CAMPUS (创建时自动绑定校区) |
| GET /api/v1/resources | GET | resource:read | SELF or CAMPUS_AND_CHILDREN |
| PATCH /api/v1/resources/{id} | PATCH | resource:update | 仅自己的或 CAMPUS 范围 |
| POST /api/v1/resources/{id}/assign | POST | resource:assign | 仅自己名下的或 CAMPUS 范围 |
| POST /api/v1/orders/enroll | POST | order:create | CAMPUS (按学员校区) |
| POST /api/v1/orders/{id}/pay | POST | order:pay | CAMPUS |
| POST /api/v1/orders/{id}/void | POST | order:void | —（需额外确认） |
| POST /api/v1/refunds/{id}/approve | POST | refund:approve1 | ALL |
| POST /api/v1/wallet/{account_id}/topup | POST | wallet:topup | ALL |

---

## 4. 迁移注意事项

1. **默认角色**：旧系统所有员工数据需导入时赋予默认角色（建议 CAMPUS_STAFF），再由管理员调整。
2. **历史权限记录**：旧系统无权限日志，新系统必须记录所有敏感操作的审计日志（操作人/时间/IP/操作内容）。
3. **过渡期方案**：可设置"管理员"角色覆盖所有权限，待业务稳定后再精细化分配。
4. **校区归属**：员工必须有明确的默认校区（从 organizations 表推断或要求录入）。
*（内容由AI生成，仅供参考）*
