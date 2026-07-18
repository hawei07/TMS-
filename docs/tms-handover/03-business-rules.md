---
AIGC:
    Label: "1"
    ContentProducer: 001191440300708461136T1XGW3
    ProduceID: 3f11eb7fa23d664c4b1c1527387f20fd_1665649a80ff11f1a60e525400e6dd8f
    ReservedCode1: 0MBrLKeaiXyVw/DRxYEc2F1boJMchwTX8MV5t9jDzX0O/w2muLujGMlB6M7V6BWtYb0Z3EHzbmtvuDxwsOUgGiD43gnFfSCMU2eJkR9X56b3zasKKVixecx2kRlDbSCm5GcUgRmvRDnmnFuuCwXpQ1wqcgkUXjhQfqbbZdeHaVFEI80WuyMJ6j84aMU=
    ContentPropagator: 001191440300708461136T1XGW3
    PropagateID: 3f11eb7fa23d664c4b1c1527387f20fd_1665649a80ff11f1a60e525400e6dd8f
    ReservedCode2: 0MBrLKeaiXyVw/DRxYEc2F1boJMchwTX8MV5t9jDzX0O/w2muLujGMlB6M7V6BWtYb0Z3EHzbmtvuDxwsOUgGiD43gnFfSCMU2eJkR9X56b3zasKKVixecx2kRlDbSCm5GcUgRmvRDnmnFuuCwXpQ1wqcgkUXjhQfqbbZdeHaVFEI80WuyMJ6j84aMU=
---

# 03-business-rules.md — 业务规则

> 版本：v1.0  
> 日期：2026-07-16  
> 所有规则均附代码证据；无法从代码确认的标记"待业务确认"

---

## TMS-RULE-001: 线索去重规则

| 属性 | 内容 |
|------|------|
| **规则名称** | 线索电话去重 |
| **业务描述** | 新增线索时，按手机号去重。同一手机号不允许重复录入。 |
| **适用条件** | add_resource / import_resources |
| **状态影响** | 重复时拒绝创建，返回提示 |
| **异常处理** | 显示"该手机号已存在" |
| **代码证据** | index.php: 在 add_resource 分支中 `SELECT id FROM resources WHERE phone=:phone` 查重后拒绝 |
| **数据表证据** | resources.phone 有索引 idx_resources_phone |
| **待确认问题** | 去重是否区分校区？当前实现按全局 phone 去重 |

---

## TMS-RULE-002: 线索归属与公海规则

| 属性 | 内容 |
|------|------|
| **规则名称** | 线索归属与公海领取 |
| **业务描述** | 线索有归属人（assigned_to），未被分配的线索进入公海（pool_type='公海'），其他员工可从公海领取。 |
| **适用条件** | pool_type 字段判定 |
| **计算公式** | pool_type = assigned_to 为空 ？"公海" : "我的资源" |
| **状态影响** | 领取后 pool_type 变为"我的资源"，assigned_to 更新 |
| **异常处理** | — |
| **代码证据** | index.php: claim_resource 分支更新 assigned_to 和 pool_type |
| **数据表证据** | resources.pool_type, resources.assigned_to |
| **待确认问题** | 公海领取是否有限制（如每人最多领取数、领取频率）？是否有自动掉入公海规则（如N天未跟进）？ |

---

## TMS-RULE-003: 试听状态流转规则

| 属性 | 内容 |
|------|------|
| **规则名称** | 试听预约状态计算 |
| **业务描述** | 预约的 effective_status 根据 class_attendance 动态计算，而非预先存储。已预约 → 到场后变为"已试听"→ 缺勤则变为"缺勤"。 |
| **适用条件** | appointments.status = query fields |
| **计算公式** | effective_status = 查 class_attendance 中匹配记录的状态 |
| **状态影响** | 影响列表展示的"状态"列 |
| **异常处理** | 未匹配 class_attendance 时保持 appointments.status |
| **代码证据** | index.php: get_appointments 分支中 LEFT JOIN class_attendance 计算 effective_status |
| **数据表证据** | appointments.status, class_attendance.status |
| **待确认问题** | — |

---

## TMS-RULE-004: 学员类型判定规则

| 属性 | 内容 |
|------|------|
| **规则名称** | 学员类型自动升级（小课包→常规） |
| **业务描述** | 新建学员默认 student_type='小课包'。当检测到该学员存在有效非小课包订单时，自动升级为'常规'。升级不可逆。 |
| **适用条件** | 订单 order_type 为非小课包的已支付订单 |
| **状态影响** | student_type: 小课包→常规 |
| **异常处理** | 无降级路径 |
| **代码证据** | index.php: get_students / add_student 分支中检测 student_type 并查询 orders 判断升级 |
| **数据表证据** | students.student_type, orders.order_type |
| **待确认问题** | 升级是否考虑订单状态（已作废/已退费不应触发）？当前实现为"只要有就升级" |

---

## TMS-RULE-005: 报名类型判定规则

| 属性 | 内容 |
|------|------|
| **规则名称** | 订单类型（order_type）判断 |
| **业务描述** | 订单创建时根据上下文自动设定 order_type。 |
| **适用条件** | 创建订单 |
| **状态影响** | order_type 字段赋值 |
| **枚举值** | 新报/续费/扩科/小课包/赠送/活动报名/储值/画具销售 |
| **代码证据** | index.php: save_order 分支中根据传入参数设定 order_type |
| **待确认问题** | 续费 vs 新报的判断标准：是否按该学员+该课程是否已有有效订单？ |

---

## TMS-RULE-006: 订单金额计算规则

| 属性 | 内容 |
|------|------|
| **规则名称** | 订单实付金额计算 |
| **业务描述** | actual_price = unit_price - discount_plan_amount - coupon_amount + teaching_aid_price - product_coupon_amount |
| **适用条件** | 报价单元 price_items |
| **状态影响** | orders.actual_price / paid_amount |
| **异常处理** | 优惠金额不能超过原价（前端校验） |
| **代码证据** | price_items 包含 discount_plan_id / coupon_id / teaching_aid_id / product_coupon_id 字段，订单快照 discount_plan_amount / coupon_amount / teaching_aid_price / product_coupon_amount |
| **数据表证据** | price_items.{discount_plan_id,coupon_id,teaching_aid_id,product_coupon_id}, orders.{discount_plan_amount,coupon_amount,teaching_aid_price,product_coupon_amount} |
| **待确认问题** | 多个优惠方案能否叠加？当前仅支持单个方案+单个券 |

---

## TMS-RULE-007: 多支付渠道分摊顺序

| 属性 | 内容 |
|------|------|
| **规则名称** | 支付金额分摊（现金→美团→账户余额） |
| **业务描述** | 订单支付时，用户指定 cash_amount / meituan_amount / account_amount 三者之和 = paid_amount。多支付渠道按指定金额分摊，不做自动优先级。 |
| **适用条件** | pay_order 动作 |
| **计算公式** | paid_amount = cash_amount + meituan_amount + account_amount |
| **状态影响** | 更新对应金额字段 + 扣减账户余额 |
| **异常处理** | 总和不等时拒绝支付 |
| **代码证据** | index.php: pay_order 分支验证 sum = paid_amount |
| **数据表证据** | orders.{cash_amount,meituan_amount,account_amount,paid_amount} |
| **待确认问题** | 未来是否可能新增微信/支付宝渠道？ |

---

## TMS-RULE-008: 赠课生成规则

| 属性 | 内容 |
|------|------|
| **规则名称** | 赠课跟随主订单 |
| **业务描述** | 可以在报价单元中配置 gifted_lessons（赠送课时数）。下单时赠课随主订单一起创建，order_type='赠送'，lesson_count=gifted_lessons，actual_price=0。 |
| **适用条件** | price_items.gifted_lessons > 0 |
| **状态影响** | 创建独立 orders 记录（order_type=赠送） |
| **异常处理** | 赠课订单不参与退费金额计算 |
| **代码证据** | price_items.gifted_lessons 字段 + save_order 中创建赠送订单的逻辑 |
| **数据表证据** | price_items.gifted_lessons, orders.gifted_lessons |
| **待确认问题** | 赠课课时消耗优先级是否低于付费课时？ |

---

## TMS-RULE-009: 小课包限制规则

| 属性 | 内容 |
|------|------|
| **规则名称** | 小课包购买限制 |
| **业务描述** | 小课包是低课时体验包，每个学员每种课程仅限购买一次。 |
| **适用条件** | 课程标记 small_package='是' |
| **状态影响** | 重复购买时拒绝或提示 |
| **代码证据** | index.php: save_order 中检查该学员+该课程是否已有小课包订单 |
| **数据表证据** | courses.small_package, orders.order_type='小课包' |
| **待确认问题** | 已退费/已作废的小课包是否释放额度？ |

---

## TMS-RULE-010: 扣课优先级规则

| 属性 | 内容 |
|------|------|
| **规则名称** | 扣课优先级（先小课包后常规，先付费后赠送，先早后晚） |
| **业务描述** | 考勤扣课时按优先级依次从订单中扣除：① 小课包订单优先消耗；② 付费课时优先于赠送课时；③ 创建时间早的订单优先。 |
| **适用条件** | class_attendance.status='出勤' 触发扣课 |
| **状态影响** | order.consumed_lessons 递增 |
| **异常处理** | 课时不足时：扣完所有可用课时，标记欠课 |
| **代码证据** | index.php: save_attendance / update_attendance 中的 deduction_json 扣课算法，按优先级排序后依次扣除 |
| **数据表证据** | class_attendance.deduction_json, orders.consumed_lessons |
| **待确认问题** | — |

---

## TMS-RULE-011: 付费/赠送课时消耗优先级

| 属性 | 内容 |
|------|------|
| **规则名称** | 付费课时优先消耗 |
| **业务描述** | 同一订单内，先消耗付费课时（lesson_count - gifted_lessons），再消耗赠送课时（gifted_lessons）。 |
| **适用条件** | 订单有 gifted_lessons > 0 |
| **状态影响** | consumed_lessons 变化 |
| **异常处理** | 退费时仅计算付费课时消耗 |
| **代码证据** | index.php: deduction_json 中区分付费与赠送课时 |
| **数据表证据** | orders.{lesson_count,gifted_lessons,consumed_lessons} |
| **待确认问题** | — |

---

## TMS-RULE-012: 跨课程/跨学科/跨校区限制

| 属性 | 内容 |
|------|------|
| **规则名称** | 课时不可跨课程使用 |
| **业务描述** | 学员在某课程购买的课时，只能在该课程对应的班级考勤中消耗。不可跨课程/跨学科/跨校区使用。 |
| **适用条件** | 扣课时匹配 |
| **计算公式** | 匹配优先级：课程匹配 > 学科匹配 > 校区匹配（扣课时仅匹配同一 course_id 的订单） |
| **状态影响** | — |
| **异常处理** | 无匹配可用课时时标记— |
| **代码证据** | index.php: save_attendance / deduction_json 中按 course_id 筛选可扣订单 |
| **数据表证据** | orders.course_id, class_attendance.class_id→classes.course_id |
| **待确认问题** | — |

---

## TMS-RULE-013: 考勤修改/冲正规则

| 属性 | 内容 |
|------|------|
| **规则名称** | 考勤修改先退还再重新扣 |
| **业务描述** | 修改已考勤记录时，先将被修改记录已扣课时退还到对应订单，再根据新状态重新扣课。 |
| **适用条件** | update_attendance / reverse_attendance |
| **状态影响** | class_attendance.status / deducted_lessons 更新，order.consumed_lessons 调整 |
| **异常处理** | 退还后课时数不应超过原订单 lesson_count+consumed_lessons |
| **代码证据** | index.php: update_attendance 中先 SELECT deducted_lessons → UPDATE orders consumed_lessons -= deducted → 重新扣课 |
| **数据表证据** | class_attendance.{deducted_lessons,deduction_json}, orders.consumed_lessons |
| **待确认问题** | 冲正与修改的区别？冲正是否将 status 置为"缺勤"并退还所有课时？ |

---

## TMS-RULE-014: 课程退款金额计算

| 属性 | 内容 |
|------|------|
| **规则名称** | 退费金额 = 剩余课时单价 × 剩余课时数 - 自定义扣除 |
| **业务描述** | 课时单价 = 订单实付 / 总课时；退款 = 单价 × (总课时 - 已消耗 - 转校课时) - custom_deduction |
| **适用条件** | create_refund（课程退款） |
| **计算公式** | 课时单价 = total_amount / total_lessons; actual_refund = 单价 × remaining_lessons - custom_deduction |
| **状态影响** | refund_records.status→待审批；订单 refund_status→退费申请中 |
| **异常处理** | 剩余课时=0 时拒绝退款；退款金额<0 时拒绝 |
| **代码证据** | index.php: create_refund 分支中计算逻辑 |
| **数据表证据** | refund_records.{total_amount,total_lessons,consumed_lessons,remaining_lessons,actual_refund,custom_deduction} |
| **待确认问题** | 赠课课时是否计入 total_lessons？当前实现中赠课单独订单，total_lessons 不含赠课 |

---

## TMS-RULE-015: 退费审批规则

| 属性 | 内容 |
|------|------|
| **规则名称** | 退费二级审批 |
| **业务描述** | 退费申请进入一级审批，通过后进入二级审批，二级通过后实际执行退款。任意级驳回即终止。 |
| **适用条件** | refund_records |
| **状态影响** | 待审批 → 一级审批通过 → 二级审批通过 → 已退费（或 → 审批驳回） |
| **异常处理** | 驳回需填写 reject_reason |
| **代码证据** | index.php: approve_refund 分支中的状态流转 |
| **数据表证据** | refund_records.{status,approval_stage,reject_reason,approver1,approver2} |
| **待确认问题** | 一级审批人和二级审批人的选择规则？是否存在审批金额阈值（如 < N 元可跳过二级）？ |

---

## TMS-RULE-016: 教材/画具退回规则

| 属性 | 内容 |
|------|------|
| **规则名称** | 画具销售退回处理 |
| **业务描述** | 已售出的教材/画具可以退回。退回后更新退款金额计算。 |
| **适用条件** | return_teaching_aid |
| **状态影响** | 教材销售记录状态变更 |
| **异常处理** | — |
| **代码证据** | 迁移版本 20260713_002_return_teaching_aid |
| **数据表证据** | teaching_aid_sales |
| **待确认问题** | 退回后的退款方式（现金/账户）？退回后商品是否重新上架？ |

---

## TMS-RULE-017: 转校金额和课时计算

| 属性 | 内容 |
|------|------|
| **规则名称** | 转校转移课时与金额 |
| **业务描述** | 转校时将剩余课时（含未消耗付费课时）按原订单单价折算金额，创建新订单标记 transferred_lessons。 |
| **适用条件** | transfer_campus_order |
| **计算公式** | transfer_lessons = remaining_lessons（付费课时）, transfer_amount = 课时单价 × transfer_lessons |
| **状态影响** | 原订单 transferred_lessons += transfer_lessons; 新订单 lesson_count=transfer_lessons, order_type=转校 |
| **异常处理** | 剩余课时=0 时拒绝转校 |
| **代码证据** | index.php: transfer_campus_order 分支 + transfer_records 表 |
| **数据表证据** | transfer_records, orders.transferred_lessons |
| **待确认问题** | 转校是否也需审批？当前实现是否有审批流程？新订单的 campus 如何确定？ |

---

## TMS-RULE-018: 活动成人/学员收费及扣课规则

| 属性 | 内容 |
|------|------|
| **规则名称** | 活动费用模式（仅收费 / 收费+扣课时 / 仅扣课时） |
| **业务描述** | 活动支持三种收费模式：fee_only（仅收费）、fee_deduct（收费+扣课时）、deduct_only（仅扣课时）。成人与学员可独立设置不同模式。 |
| **适用条件** | activities.{adult_fee_mode,student_fee_mode} |
| **计算公式** | 总费用 = adult_price × count + student_price × count; 总扣课 = sum(deduct_lessons) per subject |
| **状态影响** | 活动订单创建 + 考勤扣课 |
| **异常处理** | 仅扣课时模式：不创建支付流水 |
| **代码证据** | index.php: enroll_activity 分支中根据 fee_mode 处理支付和扣课 |
| **数据表证据** | activities.{adult_fee_mode,student_fee_mode}, activity_subject_deductions |
| **待确认问题** | 成人扣课是否使用学员账户余额？成人无学员账号如何处理？ |

---

## TMS-RULE-019: 税率和税后课耗计算

| 属性 | 内容 |
|------|------|
| **规则名称** | 税后课耗金额计算 |
| **业务描述** | 按校区设定的税率，计算税后课耗金额用于财务统计。 |
| **适用条件** | 有税率配置的校区 |
| **计算公式** | consumed_amount_post_tax = consumed_amount / (1 + tax_rate) |
| **状态影响** | 影响财务统计报表 |
| **异常处理** | 未配置税率的校区默认税率=0 |
| **代码证据** | api/settings.php: 税率设置接口 + index.php: 现金流统计中的税后金额计算 |
| **数据表证据** | tax_rates.{campus_id,course_tax_rate,product_tax_rate} |
| **待确认问题** | 退费时是否也按税后金额计算退款？ |

---

## TMS-RULE-020: 账户余额并发安全

| 属性 | 内容 |
|------|------|
| **规则名称** | 账户操作 FOR UPDATE 行锁 |
| **业务描述** | 所有涉及 student_accounts 的读写操作（充值/消费/退款）必须使用 FOR UPDATE 锁定该行，防止并发超扣。 |
| **适用条件** | 任何更新 balance 的操作 |
| **计算公式** | BEGIN → SELECT balance FOR UPDATE → 计算 → UPDATE balance → COMMIT |
| **状态影响** | — |
| **异常处理** | 余额不足时 ROLLBACK + 返回错误 |
| **代码证据** | index.php: deposit_account / pay_order(account) / refund_account 中各分支 |
| **数据表证据** | student_accounts.balance |
| **待确认问题** | — |

---

## TMS-RULE-021: 订单作废/课时归还

| 属性 | 内容 |
|------|------|
| **规则名称** | 订单作废时退还已扣课时和已扣金额 |
| **业务描述** | void_order 将订单 is_voided='是'，同时还原所有已扣课时到其他有效订单。如有账户支付则退款到账户。 |
| **适用条件** | is_voided='否' 的非活动订单 |
| **状态影响** | is_voided→'是', consumed_lessons→0, 关联考勤的 deducted_order_id 清除, 账户余额+原有已扣金额 |
| **异常处理** | 已退费的订单不能作废 |
| **代码证据** | index.php: void_order 分支 |
| **数据表证据** | orders.{is_voided,consumed_lessons}, class_attendance.deducted_order_id |
| **待确认问题** | 赠送课时是否一并作废？子订单作废是否影响父订单 total_lessons？ |

---

## TMS-RULE-022: 活动报名容量限制

| 属性 | 内容 |
|------|------|
| **规则名称** | 活动校区容量限制 |
| **业务描述** | 活动报名时校区的 max_capacity > 0 时，已报名人数不能超过容量。 |
| **适用条件** | activity_campuses.max_capacity > 0 |
| **状态影响** | 超额时拒绝报名 |
| **异常处理** | 返回"该校区报名已满" |
| **代码证据** | activity_enrollment_counts 缓存表 + index.php: enroll_activity 分支检查容量 |
| **数据表证据** | activity_campuses.max_capacity, activity_enrollment_counts |
| **待确认问题** | 取消报名是否释放容量？并发报名是否有锁？ |

---

## TMS-RULE-023: 优惠券有效期校验

| 属性 | 内容 |
|------|------|
| **规则名称** | 优惠券有效期校验 |
| **业务描述** | 下单时校验优惠券的 start_date / end_date 是否在有效期内。过期的券不允许使用。 |
| **适用条件** | coupon 关联下单 |
| **状态影响** | usage_status→已过期（定时/扫描） |
| **代码证据** | index.php: save_order 分支中检查券有效期 |
| **数据表证据** | coupons.{start_date,end_date}, coupon_records.usage_status |
| **待确认问题** | usage_status 的"已过期"标记是定时任务还是查询时实时判断？ |

---

## 规则编号索引

| 编号 | 名称 | 领域 |
|------|------|------|
| TMS-RULE-001 | 线索电话去重 | crm-enrollment |
| TMS-RULE-002 | 线索归属与公海规则 | crm-enrollment |
| TMS-RULE-003 | 试听状态流转 | crm-enrollment |
| TMS-RULE-004 | 学员类型判定 | identity-access |
| TMS-RULE-005 | 报名类型判定 | order-entitlement |
| TMS-RULE-006 | 订单金额计算 | catalog-pricing / order-entitlement |
| TMS-RULE-007 | 多支付渠道分摊 | wallet-ledger |
| TMS-RULE-008 | 赠课生成 | order-entitlement |
| TMS-RULE-009 | 小课包限制 | order-entitlement |
| TMS-RULE-010 | 扣课优先级 | education-teaching |
| TMS-RULE-011 | 付费/赠送课时消耗优先级 | education-teaching |
| TMS-RULE-012 | 跨课程限制 | education-teaching |
| TMS-RULE-013 | 考勤修改/冲正 | education-teaching |
| TMS-RULE-014 | 课程退款金额计算 | wallet-ledger |
| TMS-RULE-015 | 退费审批规则 | notification-workflow |
| TMS-RULE-016 | 教材退回 | order-entitlement |
| TMS-RULE-017 | 转校金额和课时计算 | order-entitlement |
| TMS-RULE-018 | 活动收费及扣课规则 | study-activity |
| TMS-RULE-019 | 税率和税后课耗计算 | data-analytics |
| TMS-RULE-020 | 账户余额并发安全 | wallet-ledger |
| TMS-RULE-021 | 订单作废课时归还 | order-entitlement |
| TMS-RULE-022 | 活动报名容量限制 | study-activity |
| TMS-RULE-023 | 优惠券有效期校验 | marketing-growth |
*（内容由AI生成，仅供参考）*
