# 02-business-processes.md — 业务流程

> 版本：v1.0  
> 日期：2026-07-16  
> 所有流程基于代码实现绘制，不可确认的步骤标记"待业务确认"

---

## 流程 1: 线索录入→分配→公海→领取→转化

### 流程图

```mermaid
flowchart TD
    A[录入线索] --> B{手机号去重?}
    B -->|已存在| C[拒绝录入]
    B -->|新号码| D[创建 resources 记录]
    D --> E{是否指定归属人?}
    E -->|是| F[pool_type=我的资源, assigned_to=归属人]
    E -->|否| G[pool_type=公海, assigned_to=空]
    
    F --> H[跟进: 添加沟通记录]
    H --> I[更新 follow_status]
    I --> J{意向判断}
    J -->|无意向| K[status=无意向]
    J -->|有意向| L[预约试听 → 流程2]
    J -->|直接报名| M[转化 → 流程5]
    
    G --> N[其他员工查看公海]
    N --> O[领取公海线索]
    O --> F
    
    M --> P[创建学员 students]
    P --> Q[resources.converted=已转化]
```

### 前置条件
- 渠道字典已配置

### 操作步骤
1. 选择渠道、输入姓名和电话
2. 系统查重 phone
3. 未重复则创建，填入意向等级
4. 分配给归属人或进入公海
5. 跟进过程添加 communication_records

### 状态变化
- resources.status: → 待跟进 → 已联系 / 无意向 / 试听预约 / 已报名
- resources.converted: 未转化 → 已转化

### 失败场景
- 手机号重复：拒绝创建
- 必填字段缺失：返回错误

### 权限
- 无细粒度权限控制（当前为遗留系统）

### 事务
- 单条 INSERT，无显式事务

---

## 流程 2: 试听预约→取消→到场→转化

### 流程图

```mermaid
flowchart TD
    A[创建试听预约] --> B[选择校区/课程/班级/课次]
    B --> C[appointments.status=已预约]
    C --> D{学员到场?}
    D -->|是| E[class_attendance.status=出勤]
    D -->|否| F[class_attendance.status=缺勤]
    D -->|取消| G[appointments.status=已取消]
    
    E --> H{是否当场转化?}
    H -->|是| I[创建学员 → 新报订单]
    I --> J[resources.converted=已转化]
    H -->|否| K[effective_status=已试听]
    
    F --> L[effective_status=缺勤]
    G --> M[结束]
```

### 前置条件
- 线索已存在（resource_id）
- 课程和班级已配置

### 操作步骤
1. 填写预约信息（学员名/电话/课程/班级/课次）
2. 创建 appointments 记录
3. 试听当日：class_attendance 记录到场情况
4. 系统根据 class_attendance 动态计算 effective_status
5. 试听后可一键转化为学员+新报订单

### 状态变化
- appointments.status: 已预约 → （通过 class_attendance 计算的）已试听/缺勤/已取消
- resources.converted: 未转化→已转化（转化后）

### 失败场景
- 班级/课次不存在
- 已超出班级 max_students（待确认是否有校验）

### 权限
- 无权限校验

### 事务
- 单表操作，预约和考勤可能跨多个请求

---

## 流程 3: 学员建档和家庭关系

### 流程图

```mermaid
flowchart TD
    A[录入学员] --> B{phone 去重?}
    B -->|已存在| C[提示存在/返回已有学员]
    B -->|新号码| D[创建 students]
    D --> E[student_no=自动生成]
    E --> F[student_type=小课包]
    F --> G{关联 resource_id?}
    G -->|有| H[关联线索, resources.converted=已转化]
    G -->|无| I[独立建档]
    
    H --> J{学员有非小课包订单?}
    I --> J
    J -->|有| K[student_type 升级为 常规]
    J -->|无| L[保持 小课包]
```

### 前置条件
- 手机号唯一

### 操作步骤
1. 录入姓名/电话
2. 自动生成学号
3. 默认 student_type=小课包
4. 关联来源线索（如有）
5. 后续有报名时自动升级类型

### 状态变化
- student_type: 小课包 → 常规（不可逆）

### 失败场景
- 手机号重复

### 家庭关系
- 代码中存在 family_member 相关逻辑，但具体实现待确认

---

## 流程 4: 课程报价方案配置

### 流程图

```mermaid
flowchart TD
    A[选择课程] --> B[创建报价方案 price_plans]
    B --> C[设定 plan_type 和 sort_order]
    C --> D[添加报价单元 price_items]
    D --> E[配置 lesson_count/unit_price/actual_price]
    E --> F{是否关联优惠?}
    F -->|优惠方案| G[关联 discount_plan_id]
    F -->|优惠券| H[关联 coupon_id]
    F -->|画具| I[关联 teaching_aid_id]
    F -->|商品券| J[关联 product_coupon_id]
    F -->|无| K[完成]
    G --> L[配置赠课 gifted_lessons]
    H --> L
    I --> L
    J --> L
    L --> K
```

### 前置条件
- 课程已创建
- 优惠方案/券已配置（如关联）

### 操作步骤
1. 创建报价方案并绑定课程
2. 添加报价单元（name/lesson_count/unit_price/actual_price）
3. 可选关联优惠方案/优惠券/画具/赠课
4. actual_price 为面向客户的最终价格

### 状态变化
- 无状态字段，创建即生效

---

## 流程 5: 新报/续费/扩科/小课包/赠课下单

### 流程图

```mermaid
flowchart TD
    A[选择学员] --> B[选择课程+报价方案+报价单元]
    B --> C{订单类型判定}
    C -->|首次报名该课程| D[order_type=新报]
    C -->|已报过该课程| E[order_type=续费]
    C -->|跨学科报名| F[order_type=扩科]
    C -->|课程为小课包| G[order_type=小课包, 检查是否已购买]
    C -->|纯赠送| H[order_type=赠送, actual_price=0]
    
    D --> I[创建 orders 记录]
    E --> I
    F --> I
    G -->|未买过| I
    G -->|已买过| J[拒绝]
    H --> I
    
    I --> K{是否多子订单?}
    K -->|是| L[创建 parent_orders]
    K -->|否| M[单独订单]
    
    L --> N[pay_status=待支付]
    M --> N
```

### 前置条件
- 学员已建档
- 课程+报价方案已配置

### 操作步骤
1. 选择学员和课程
2. 选择报价方案和报价单元
3. 系统判定订单类型
4. 快照优惠信息到 orders 表
5. 如有赠课，创建独立赠送订单
6. 生成订单号

### 状态变化
- orders.status: 已报名
- orders.pay_status: 待支付

### 失败场景
- 小课包重复购买
- 优惠方案过期
- 课程未关联校区

---

## 流程 6: 父子订单创建和支付分摊

### 流程图

```mermaid
flowchart TD
    A[多子订单] --> B[创建 parent_orders]
    B --> C[parent_order_no=生成]
    C --> D[各子订单记录 parent_order_no]
    D --> E[parent_orders.child_order_nos=汇总]
    E --> F[parent_orders.total_price=SUM actual_price]
    F --> G[parent_orders.total_lessons=SUM lesson_count]
    G --> H[支付 parent_order]
    
    H --> I[指定 cash_amount/meituan_amount/account_amount]
    I --> J{sum == total_price?}
    J -->|否| K[拒绝]
    J -->|是| L[各子订单 pay_status=已支付]
    L --> M[如有 account_amount, 扣减账户]
```

### 前置条件
- 至少 2 个子订单
- 支付金额与总价匹配

### 操作步骤
1. 创建父订单汇总
2. 每个子订单关联 parent_order_no
3. 支付时验证 sum(各渠道) = total_price
4. 一次性更新所有子订单

### 事务
- 需显式事务保证一致性（代码证据：index.php: BEGIN/COMMIT）

---

## 流程 7: 储值充值→余额支付→账户退款

### 流程图

```mermaid
flowchart TD
    A[学员账户] --> B{账户存在?}
    B -->|否| C[自动初始化 balance=0]
    B -->|是| D[继续]
    C --> D
    
    D --> E[充值 deposit_account]
    E --> F[BEGIN + SELECT FOR UPDATE]
    F --> G[balance += amount]
    G --> H[account_transactions type=deposit]
    H --> I[COMMIT]
    
    I --> J[下单时使用余额]
    J --> K[account_amount 字段记录]
    K --> L{支付时}
    L --> M[BEGIN + SELECT FOR UPDATE]
    M --> N{balance >= account_amount?}
    N -->|否| O[余额不足, ROLLBACK]
    N -->|是| P[balance -= account_amount]
    P --> Q[account_transactions type=consume]
    Q --> R[COMMIT]
    
    R --> S[退款到账户]
    S --> T[BEGIN + SELECT FOR UPDATE]
    T --> U[balance += refund_amount]
    U --> V[account_transactions type=refund]
    V --> W[COMMIT]
```

### 前置条件
- 学员已存在

### 操作步骤
1. 首次访问自动初始化账户
2. 充值使用 FOR UPDATE 行锁
3. 消费校验余额
4. 退款恢复余额
5. 所有操作记录流水

### 状态变化
- balance 实时变化
- account_transactions 追加

### 事务
- 所有资金操作使用显式事务 + FOR UPDATE

---

## 流程 8: 班级→分班→排课→临时学员

### 流程图

```mermaid
flowchart TD
    A[创建班级] --> B[设定 course_id/campus/max_students/can_trial]
    B --> C[排课 schedules]
    C --> D[设定 rule_type/weekdays/time_slots/teacher/classroom]
    D --> E[根据规则生成课次]
    
    E --> F[分班: add_class_student]
    F --> G[class_students UNIQUE class_id+student_id]
    G --> H{是否临时学员?}
    H -->|是| I[class_attendance.is_temporary=1, 不写入 class_students]
    H -->|否| J[正常在班]
    
    J --> K[考勤: 流程9]
    
    L[学员出班] --> M[class_students.left_at=当前时间]
```

### 前置条件
- 课程已创建
- 教师已在 employees 中标记 is_teacher

### 操作步骤
1. 创建班级（绑定课程/校区）
2. 设置排课规则（按周几+时段自动生成）
3. 手动将学员分入班级
4. 临时学员不占班级名额，仅考勤时标记

### 失败场景
- 班级已满 max_students
- 学员已在同一班级

---

## 流程 9: 常规课程考勤和扣课

### 流程图

```mermaid
flowchart TD
    A[课次日期到达] --> B[教师/教务录入考勤]
    B --> C{考勤状态}
    C -->|出勤| D[计算扣课]
    C -->|缺勤| E[不扣课, status=缺勤]
    C -->|请假| F[不扣课, status=请假]
    
    D --> G[按优先级收集可扣订单]
    G --> H[排序: 小课包>常规, 付费>赠送, 早>晚]
    H --> I[逐订单扣除 1 课时]
    I --> J{剩余需扣 > 0?}
    J -->|是| K[下一个订单]
    K --> I
    J -->|否| L[写入 deduction_json]
    L --> M[更新 order.consumed_lessons]
    M --> N[class_attendance.deducted_lessons=N]
    N --> O[attendance_records 追加记录]
```

### 前置条件
- 学员在班
- 存在有效订单

### 操作步骤
1. 选择课次
2. 标记学员出勤状态
3. 出勤触发自动扣课
4. 按优先级从订单消耗
5. 扣课明细写入 deduction_json

### 状态变化
- class_attendance.status: 出勤/缺勤/请假
- orders.consumed_lessons: 递增

### 失败场景
- 可用课时不足：扣完所有可用课时，标记欠课

### 审计
- 修改/冲正记录在 deduction_json 变化中可追溯

---

## 流程 10: 活动报名→收费→扣课→考勤

### 流程图

```mermaid
flowchart TD
    A[创建活动] --> B[设置 fee_mode/per campus capacity]
    B --> C[配置扣课规则 activity_subject_deductions]
    C --> D[活动报名期]
    
    D --> E[学员报名 enroll_activity]
    E --> F{容量检查}
    F -->|已满| G[拒绝]
    F -->|有余量| H[计算费用: adult_price×n + student_price×n]
    H --> I[创建订单 activity字段填充]
    I --> J{扣课模式?}
    J -->|fee_only| K[仅收费, 标记 activity_fee_type]
    J -->|fee_deduct| L[收费 + 扣课时]
    J -->|deduct_only| M[仅扣课时]
    
    K --> N[支付 → pay_status=已支付]
    L --> N
    M --> O[不创建支付]
    
    N --> P[活动考勤]
    O --> P
    P --> Q[class_attendance activity字段]
    Q --> R{depends on fee_mode}
    R -->|含扣课| S[按 subject_level1 匹配扣课规则]
    S --> T[扣除对应课时]
```

### 前置条件
- 活动在报名期内
- 校区容量未满

### 操作步骤
1. 创建活动配置 fee_mode 和扣课规则
2. 学员报名 → 按模式创建订单
3. 付费模式生成支付
4. 活动考勤执行扣课

---

## 流程 11: 课程退款→账户退款→转校退款

### 流程图

```mermaid
flowchart TD
    A[发起退款] --> B{退款类型}
    B -->|课程退款| C[计算剩余课时和金额]
    B -->|账户退款| D[refund_account 直接退余额]
    
    C --> E[创建 refund_records project=课程]
    E --> F[status=待审批, approval_stage=一级审批]
    F --> G[一级审批]
    G -->|通过| H[approval_stage=二级审批]
    G -->|驳回| I[status=审批驳回, 记录 reject_reason]
    H -->|通过| J[status=已退费]
    H -->|驳回| I
    
    J --> K{refund_method}
    K -->|转账| L[记录银行信息, 线下打款]
    K -->|账户| M[student_accounts balance += actual_refund]
    
    L --> N[order.refund_status=已退费]
    M --> N
    
    D --> O[直接更新账户余额 + 流水记录]
```

### 前置条件
- 订单存在且 refund_status=正常
- 剩余课时 > 0（课程退款）

### 操作步骤
1. 选择订单发起退款
2. 系统计算剩余课时/金额和 custom_deduction
3. 提交审批（二级）
4. 审批通过后按 refund_method 执行退款

### 审计
- refund_records 完整记录审批链

---

## 流程 12: 转校→订单作废→课时归还

### 流程图

```mermaid
flowchart TD
    A[发起转校] --> B[计算剩余付费课时]
    B --> C{剩余 > 0?}
    C -->|否| D[拒绝]
    C -->|是| E[创建 transfer_records, status=待审批]
    E --> F[审批]
    F -->|通过| G[原订单 transferred_lessons += N]
    F -->|驳回| H[status=已驳回]
    
    G --> I[创建新订单: order_type=转校, campus=to_campus, lesson_count=N]
    I --> J[transfer_records.new_order_id=新订单ID]
    J --> K[transfer_records.status=已通过]
    
    L[订单作废 void_order] --> M{is_voided?}
    M -->|已作废| N[拒绝]
    M -->|否| O[还原 consumed_lessons 到其他订单]
    O --> P[is_voided=是]
```

### 前置条件
- 订单未退费
- 有剩余课时

### 操作步骤
1. 选择订单和目标校区
2. 系统计算可转移课时
3. 审批通过后创建新订单
4. 原订单标记 transferred_lessons

---

## 流程 13: 教材/画具销售和退回

### 流程图

```mermaid
flowchart TD
    A[画具上架] --> B[配置 teaching_aids name/price/type/campus]
    B --> C[售卖 sell_teaching_aid]
    C --> D[创建 teaching_aid_sales 记录]
    D --> E[选择支付方式: cash/meituan/account]
    E --> F[total_amount = unit_price × quantity]
    F --> G[记录 sold_at/sold_by]
    
    G --> H{退回?}
    H -->|是| I[return_teaching_aid]
    I --> J[更新销售记录状态/退款]
    H -->|否| K[完成]
```

### 前置条件
- 画具已上架（status=上架）
- 学员已建档

### 操作步骤
1. 创建画具配置
2. 选择学员和画具
3. 支付（支持多渠道分摊）
4. 退回时更新记录

### 状态变化
- teaching_aid_sales 创建/退回标记

---

## 流程 14: 优惠方案→优惠券→发放→核销

### 流程图

```mermaid
flowchart TD
    A[创建优惠方案] --> B[设定 plan_type=新报/续费/扩科]
    B --> C[设定 discount_amount]
    C --> D[关联校区 discount_plan_campuses]
    D --> E[关联学科 discount_plan_subjects]
    E --> F[设定有效期 start_date~end_date]
    
    G[创建优惠券] --> H[设定 coupon_type=课程券/商品券]
    H --> I[设定 discount_amount]
    I --> J[关联校区 coupon_campuses]
    J --> K[关联学科 coupon_subjects]
    K --> L[设定有效期]
    
    L --> M[发放优惠券 issue_coupon]
    M --> N[coupon_records usage_status=未使用]
    
    N --> O{下单时核销}
    O --> P{在有效期内?}
    P -->|否| Q[拒绝使用]
    P -->|是| R{适用范围匹配?}
    R -->|否| Q
    R -->|是| S[核销: usage_status=已使用]
    S --> T[price_items 关联 coupon_id]
    T --> U[订单快照 coupon_name/coupon_amount]
```

### 前置条件
- 校区和学科已配置

### 操作步骤
1. 创建优惠方案/券模板
2. 设定适用范围和有效期
3. 向学员发放优惠券
4. 下单时选择券 → 校验有效期和适用范围
5. 核销后写入订单快照

### 状态变化
- coupon_records.usage_status: 未使用 → 已使用 / 已过期

### 失败场景
- 过期
- 不适用当前校区/学科
- 优惠金额超过订单金额
