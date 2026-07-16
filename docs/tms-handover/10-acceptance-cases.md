# 10-acceptance-cases.md — 验收案例

> 版本：v1.0
> 日期：2026-07-16
> 格式：Given/When/Then，每个案例引用对应的 API action 和 TMS-RULE-XXX
> 覆盖场景：多支付组合、余额不足、赠课、小课包限制、跨订单扣课、扣课优先级、考勤修改冲正、退费中的考勤锁定、课程退款、账户退款、教材退回、转校、活动混合报名、活动收费扣课混合、活动考勤删除归还课时、订单作废余额返还

---

## AC-001: 多支付方式报名（微信+余额+储值组合）

**引用**：API `pay_enroll` (index.php) | TMS-RULE-007

### Given
- 学员 S001（student_id=1）在课程"少儿美术"有一个报价方案，报价单元"48课时常规班"，actual_price=4800 元
- 学员账户 student_accounts 余额为 1000 元（即 100000 分）
- 订单 O-20260716-001 已创建，status='已报名'，paid_amount=0

### When
- 调用 `pay_enroll`，传入：
  - order_no = O-20260716-001
  - cash_amount = 2800（微信扫码）
  - meituan_amount = 0
  - account_amount = 2000（账户余额支付 2000 元）

### Then
| 验证点 | 预期结果 |
|--------|----------|
| 支付校验 | `2800 + 0 + 2000 = 4800` 等于 `actual_price=4800`，校验通过 |
| 订单状态 | orders.paid_amount = 4800，orders.cash_amount=2800，orders.account_amount=2000 |
| 账户扣减 | student_accounts.balance = 1000 - 2000 = **余额不足**，事务回滚 |
| 支付结果 | 返回错误"账户余额不足"，订单保持 `paid_amount=0` |

---

## AC-002: 余额不足时支付失败

**引用**：API `pay_enroll` (index.php) | TMS-RULE-020

### Given
- 同 AC-001

### When
- 调用 `pay_enroll`，account_amount=3000 > 实际余额 1000

### Then
| 验证点 | 预期结果 |
|--------|----------|
| 余额校验 | FOR UPDATE 锁定 student_accounts 行，检测到 1000 < 3000 |
| 事务行为 | ROLLBACK，balance 不变、paid_amount 不变 |
| 错误消息 | "账户余额不足，当前余额 1000 元" |
| 幂等性 | 重复调用同参数返回相同错误，不产生副作用 |

---

## AC-003: 赠送课时（报名时赠送）

**引用**：API `save_order` (index.php) | TMS-RULE-008

### Given
- 课程"少儿美术"报价方案"48课时送4课时"，报价单元 lesson_count=48，gifted_lessons=4，unit_price=4800
- 学员 S001 已是该课程常规学员

### When
- 调用 `save_order` 创建新报订单

### Then
| 验证点 | 预期结果 |
|--------|----------|
| 主订单 | orders[0].order_type='新报'，lesson_count=48，actual_price=4800，gifted_lessons=4 |
| 赠课订单 | orders[1].order_type='赠送'，lesson_count=4，actual_price=0，parent_order_no=主订单.order_no |
| 订单关联 | 两条 orders 的 student_id 和 course_id 相同 |
| 课时汇总 | SUM(lesson_count) = 52（48 付费 + 4 赠送） |

---

## AC-004: 续费时赠送课时

**引用**：API `save_order` (index.php) | TMS-RULE-008

### Given
- 学员 S001 在"少儿美术"已有 48 课时订单，已消耗 30 课时，剩余 18 课时
- 续费报价方案"24课时送2课时"

### When
- 调用 `save_order`，order_type='续费'

### Then
| 验证点 | 预期结果 |
|--------|----------|
| 续费订单 | order_type='续费'，lesson_count=24，actual_price 按续费方案定价 |
| 赠课订单 | order_type='赠送'，lesson_count=2，actual_price=0 |
| 原订单不变 | 旧订单 consumed_lessons=30，lesson_count=48，不受影响 |
| 总可用课时 | 学员该课程可用 = (48-30) + 24 + 2 = 44 课时 |

---

## AC-005: 小课包购买和限制

**引用**：API `save_order` (index.php) | TMS-RULE-009

### Given
- 课程"少儿美术体验课"标记 small_package='是'
- 学员 S001 已购买过该课程的小课包（order_type='小课包', lesson_count=4, status='已报名'）

### When
- 再次调用 `save_order` 购买同课程小课包

### Then
| 验证点 | 预期结果 |
|--------|----------|
| 重复检查 | 查询到已有该学员+该课程的小课包订单 |
| 拒绝创建 | 返回错误"该学员已购买过此课程的小课包" |
| 已退费场景 | 若旧小课包已退费（status='已退费'），应释放额度，允许重新购买 |

---

## AC-006: 扣课跨多个订单（新报+续费）

**引用**：API `save_class_attendance` (index.php) | TMS-RULE-010

### Given
- 学员 S001，课程"少儿美术"，班级"少儿美术-A班"，排课 1 课次（2 课时）
- 订单 O1：新报 48 课时，已消耗 48 课时（已耗完）
- 订单 O2：续费 24 课时，已消耗 0 课时
- 订单 O3：赠送 4 课时，已消耗 0 课时

### When
- 调用 `save_class_attendance`，class_id=A班，考生 S001，status='出勤'，需扣 2 课时

### Then
| 验证点 | 预期结果 |
|--------|----------|
| 扣课顺序 | O1 耗完（0 课时可用）→ O2 扣 2 课时 |
| O2 更新 | O2.consumed_lessons = 2 |
| deduction_json | [{order_id:O2.id, deducted_lessons:2, is_paid:true}] |
| 剩余可用 | O2 剩余 22 课时，O3 剩余 4 课时 |

---

## AC-007: 先付费后赠课，扣课顺序

**引用**：API `save_class_attendance` (index.php) | TMS-RULE-010, TMS-RULE-011

### Given
- 学员 S001，课程"少儿美术"，出勤需扣 2 课时
- 订单 O1：小课包 4 课时（order_type='小课包'），已消耗 2 课时，有 2 课时剩余
- 订单 O2：新报 48 + 4 赠课（gifted_lessons=4），lesson_count=48，consumed_lessons=0

### When
- 调用 `save_class_attendance`，扣 2 课时

### Then
| 验证点 | 预期结果 |
|--------|----------|
| 优先级 1：小课包优先 | O1 先消耗剩余 2 课时 |
| O1 更新 | O1.consumed_lessons = 4（耗完） |
| deduction_json | [{order_id:O1.id, deducted_lessons:2, is_paid:true}] |
| O2 不变 | O2.consumed_lessons = 0 |

---

## AC-008: 付费和赠课混合时的消耗顺序

**引用**：API `save_class_attendance` (index.php) | TMS-RULE-011

### Given
- 学员 S001，出勤需扣 6 课时
- 订单 O1：新报 48 + 4 赠课，consumed_lessons=45（付费已耗 45/48，赠课未消耗 4/4）

### When
- 调用 `save_class_attendance`，扣 6 课时

### Then
| 验证点 | 预期结果 |
|--------|----------|
| 付费优先 | O1 先消耗剩余 3 个付费课时 |
| 赠课接力 | 付费耗完后消耗 3 个赠送课时 |
| O1 更新 | consumed_lessons = 48（付费全耗）+ 3（赠送）= 51，剩余 1 赠课 |
| deduction_json | [{order_id:O1.id, deducted_lessons:3, is_paid:true}, {order_id:O1.id, deducted_lessons:3, is_gifted:true}] |

---

## AC-009: 修改考勤后冲正重扣

**引用**：API `update_attendance` (index.php) | TMS-RULE-013

### Given
- 考勤记录 CA-001：已出勤，扣除 O1 订单 2 课时（deducted_lessons=2，deduction_json={O1: 2}）
- O1.consumed_lessons = 2，lesson_count = 48

### When
- 调用 `update_attendance`，将 status 从'出勤'改为'缺勤'

### Then
| 验证点 | 预期结果 |
|--------|----------|
| 退还步骤 | O1.consumed_lessons = 2 - 2 = 0 |
| 重新计算 | status='缺勤' → deducted_lessons=0，不扣课 |
| deduction_json 清空 | deduction_json = null 或 [] |
| 最终状态 | CA-001.status='缺勤'，deducted_lessons=0，O1.consumed_lessons=0 |

---

## AC-010: 退款中的订单禁止修改考勤

**引用**：API `update_attendance` (index.php) | TMS-RULE-013, TMS-RULE-014

### Given
- 学员 S001 的订单 O1 已提交退费申请，refund_status='退费申请中'
- 考勤记录 CA-001 中 deduction_json 包含 O1 的扣除记录

### When
- 调用 `update_attendance` 修改 CA-001 的考勤状态

### Then
| 验证点 | 预期结果 |
|--------|----------|
| 退费检查 | 检测到 deduction_json 中 O1 的 refund_status='退费申请中' |
| 拒绝修改 | 返回错误"订单 O-XXX 正在退费审批中，不可修改考勤" |
| O1 不变 | consumed_lessons 不变 |
| CA-001 不变 | status / deducted_lessons / deduction_json 均不变 |

---

## AC-011: 课程退款（退金额+退课时）

**引用**：API `create_refund` → `approve_refund` (index.php) | TMS-RULE-014, TMS-RULE-015

### Given
- 订单 O1：少儿美术 48 课时，actual_price=4800，lesson_count=48，consumed_lessons=12，gifted_lessons=4
- 赠课订单 O-GIFT：4 课时，已消耗 2（不计入退费）

### When
- 调用 `create_refund`，order_id=O1.id
- 课时单价 = 4800 / 48 = 100 元/课时
- 剩余付费课时 = 48 - 12 = 36
- 退款金额 = 100 × 36 = 3600 元

### Then
| 验证点 | 预期结果 |
|--------|----------|
| refund_records 创建 | total_amount=4800, total_lessons=48, consumed_lessons=12, remaining_lessons=36, actual_refund=3600, status='待审批' |
| orders 状态 | refund_status='退费申请中'，不立即改变 consumed_lessons |
| 赠课订单处理 | O-GIFT 不受退费影响（赠课不退不折现） |

---

## AC-012: 退费二级审批流程

**引用**：API `approve_refund` (index.php) | TMS-RULE-015

### Given
- 退费申请 R-001：actual_refund=3600，status='待审批'

### When (Step 1)
- 调用 `approve_refund`，approval_stage=1，审批人=教务主管，decision=APPROVED

### Then (Step 1)
| 验证点 | 预期结果 |
|--------|----------|
| status 变化 | '一级审批通过' |
| approver1 记录 | approver1=教务主管 ID |
| 下一阶段 | approval_stage=2 |

### When (Step 2)
- 调用 `approve_refund`，approval_stage=2，审批人=财务主管，decision=APPROVED

### Then (Step 2)
| 验证点 | 预期结果 |
|--------|----------|
| status 变化 | '已退费' |
| approver2 记录 | approver2=财务主管 ID |
| 订单状态 | orders.status='已退费' |
| O1 可扣课时清零 | consumed_lessons 标记或清零 |

### When (Rejection)
- 任意阶段 decision=REJECTED，reject_reason="资料不完整"

### Then (Rejection)
| 验证点 | 预期结果 |
|--------|----------|
| status | '审批驳回' |
| reject_reason | "资料不完整" |
| 订单状态 | refund_status 可能恢复为空或保持'退费审批中' |

---

## AC-013: 账户退款（储值退款）

**引用**：API `refund_account` (index.php) | TMS-RULE-020

### Given
- 学员 S001 的 student_accounts 余额为 500000 分（5000 元）
- 该余额来自储值充值，非支付退款

### When
- 调用 `refund_account`，student_id=S001.id，amount=200000（2000 元）

### Then
| 验证点 | 预期结果 |
|--------|----------|
| 行锁 | FOR UPDATE 锁定 student_accounts 行 |
| 余额校验 | 500000 >= 200000 → 通过 |
| balance 更新 | balance = 500000 - 200000 = 300000 |
| account_transactions | 新增流水：txn_type='退款'，amount=200000，balance_after=300000 |
| 事务提交 | COMMIT，返回成功 |

---

## AC-014: 退回教材包

**引用**：API `return_teaching_aid` | TMS-RULE-016

### Given
- 订单 O1 关联了教材/画具 TA-001（teaching_aid_id 关联），teaching_aid_price=200
- 画具销售记录 teaching_aid_sales 存在对应记录

### When
- 调用 `return_teaching_aid`，order_id=O1.id，teaching_aid_id=TA-001.id

### Then
| 验证点 | 预期结果 |
|--------|----------|
| 销售记录状态 | teaching_aid_sales.status 标记为已退回 |
| 退款金额计算 | 若需退款，按教材价格退回（200 元） |
| 订单更新 | orders.teaching_aid_price 可能调整或退款时纳入 actual_refund |
| 商品库存 | 教材退回后重新入库（待确认） |

---

## AC-015: 转校（跨校区转移课时和金额）

**引用**：API `submit_transfer` (index.php) | TMS-RULE-017

### Given
- 学员 S001 在"少儿美术"课程，原校区"朝阳校区"的订单 O1：48 课时，consumed_lessons=20，lesson_count=48，actual_price=4800
- 目标校区"海淀校区"

### When
- 调用 `submit_transfer`，order_id=O1.id，student_id=S001.id，from_campus="朝阳校区"，to_campus="海淀校区"

### Then
| 验证点 | 预期结果 |
|--------|----------|
| transfer_records 创建 | from_campus="朝阳校区"，to_campus="海淀校区"，transfer_lessons=28（48-20），transfer_amount=100×28=2800 |
| 原订单更新 | O1.transferred_lessons = 28 |
| 新订单创建 | new_order_id 指向新订单：order_type='转校'，lesson_count=28，campus="海淀校区" |
| 审批状态 | transfer_records.status 可能为'待审批'（如需要审批） |

---

## AC-016: 活动成人和学员混合报名

**引用**：API `enroll_activity` (index.php) | TMS-RULE-018

### Given
- 活动"亲子绘画"：adult_fee_mode='fee_only'（成人 100 元/人），student_fee_mode='fee_deduct'（学员 50 元/人 + 扣 2 课时）
- activity_campuses 容量 max_capacity=20

### When
- 调用 `enroll_activity`：学员 S001（2 成人 + 1 学员）报名

### Then
| 验证点 | 预期结果 |
|--------|----------|
| 成人费用 | 2 × 100 = 200 元 |
| 学员费用 | 1 × 50 = 50 元 |
| 学员扣课 | 2 课时，走 deduction 逻辑 |
| 总费用 | 250 元 |
| 容量检查 | 当前已报名人数+3 ≤ 20 → 允许 |
| 活动订单 | order_type='活动报名'，actual_price=250 |

---

## AC-017: 活动收费+扣课混合

**引用**：API `enroll_activity` (index.php) | TMS-RULE-018

### Given
- 活动"户外写生"：student_fee_mode='fee_deduct'（80 元/人 + 扣 1 课时/学科）
- activity_subject_deductions：学科"美术"扣 1 课时

### When
- 学员 S001 报名，仅 1 个学员，无成人

### Then
| 验证点 | 预期结果 |
|--------|----------|
| 订单金额 | actual_price=80 |
| 扣课预留 | 报名时不立即扣课，在活动考勤时扣 |
| 订单类型 | order_type='活动报名' |
| 关联学科扣课规则 | 关联 activity_subject_deductions 记录 |

---

## AC-018: 活动考勤删除后归还课时

**引用**：API 活动考勤相关 (index.php) | TMS-RULE-018

### Given
- 活动"户外写生"报名学员 S001，活动考勤已出勤，已扣 O1 订单 1 课时
- O1.consumed_lessons = 12（包含本次扣的 1 课时）

### When
- 删除活动考勤记录（或标记为缺勤）

### Then
| 验证点 | 预期结果 |
|--------|----------|
| 课时退还 | O1.consumed_lessons = 12 - 1 = 11 |
| 考勤状态 | status='缺勤' 或删除 |
| deduction_json | 清除或更新为无扣除 |

---

## AC-019: 订单作废和余额返还

**引用**：API `void_order` (index.php) | TMS-RULE-021

### Given
- 订单 O1：48 课时，consumed_lessons=10，actual_price=4800，account_amount=1000（1000 元来自账户余额支付）
- 考勤记录 CA-001（deducted_order_id=O1.id）和 CA-002（deducted_order_id=O1.id）各扣 5 课时

### When
- 调用 `void_order`，order_id=O1.id

### Then
| 验证点 | 预期结果 |
|--------|----------|
| 作废标记 | O1.is_voided='是' |
| 课时归还 | O1.consumed_lessons = 0 |
| 考勤清理 | CA-001.deducted_order_id 清除，CA-002.deducted_order_id 清除 |
| 余额返还 | student_accounts.balance 增加 100000 分（1000 元），新增流水 txn_type='订单作废退款' |
| 不可对已退费订单作废 | 若 O1.status='已退费'，拒绝作废 |

---

## AC-020: 跨课程扣课隔离

**引用**：API `save_class_attendance` (index.php) | TMS-RULE-012

### Given
- 学员 S001 有：
  - 课程"少儿美术"订单 O1：48 课时，consumed_lessons=0
  - 课程"书法"订单 O2：24 课时，consumed_lessons=0
- 当前在"书法"班级考勤，需扣 2 课时

### When
- 调用 `save_class_attendance`，class_id 属于"书法"课程

### Then
| 验证点 | 预期结果 |
|--------|----------|
| 扣课匹配 | 仅匹配 course_id="书法"的订单 O2 |
| O1 不受影响 | O1.consumed_lessons=0（属于少儿美术，不参与扣课） |
| O2 扣课 | O2.consumed_lessons=2 |

---

## 案例索引

| 编号 | 场景 | 核心规则 | API action |
|------|------|----------|-----------|
| AC-001 | 多支付方式报名 | TMS-RULE-007 | pay_enroll |
| AC-002 | 余额不足支付失败 | TMS-RULE-020 | pay_enroll |
| AC-003 | 报名时赠送课时 | TMS-RULE-008 | save_order |
| AC-004 | 续费时赠送课时 | TMS-RULE-008 | save_order |
| AC-005 | 小课包购买限制 | TMS-RULE-009 | save_order |
| AC-006 | 跨订单扣课 | TMS-RULE-010 | save_class_attendance |
| AC-007 | 小课包优先扣课 | TMS-RULE-010 | save_class_attendance |
| AC-008 | 付费优先于赠课 | TMS-RULE-011 | save_class_attendance |
| AC-009 | 考勤修改冲正 | TMS-RULE-013 | update_attendance |
| AC-010 | 退款中禁止改考勤 | TMS-RULE-013/014 | update_attendance |
| AC-011 | 课程退款 | TMS-RULE-014 | create_refund |
| AC-012 | 退费二级审批 | TMS-RULE-015 | approve_refund |
| AC-013 | 账户储值退款 | TMS-RULE-020 | refund_account |
| AC-014 | 退回教材包 | TMS-RULE-016 | return_teaching_aid |
| AC-015 | 转校转移课时 | TMS-RULE-017 | submit_transfer |
| AC-016 | 活动混合报名 | TMS-RULE-018 | enroll_activity |
| AC-017 | 活动收费+扣课 | TMS-RULE-018 | enroll_activity |
| AC-018 | 活动考勤删除归还 | TMS-RULE-018 | 活动考勤相关 |
| AC-019 | 订单作废余额返还 | TMS-RULE-021 | void_order |
| AC-020 | 跨课程扣课隔离 | TMS-RULE-012 | save_class_attendance |
