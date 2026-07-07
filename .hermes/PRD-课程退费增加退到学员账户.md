# PRD：学员课程退费增加「退到学员账户」方式

> **版本**：v1.0  
> **日期**：2026-07-07  
> **状态**：待开发  
> **相关文档**：`PRD-退费记录增加项目内容字段+账户退费功能.md`、`references/refund-architecture.md`

---

## 1. 需求背景

### 1.1 当前退费流程

```
课程退费: 退费申请(弹窗) → 一级审批 → 二级审批 → 财务确认
                                                              ├─ 退到银行卡 (refund_to='cash')
                                                              └─ 退到余额 (refund_to='balance', 财务确认阶段选择)

账户退费: 退费申请(弹窗) → 一级审批 → 二级审批 → 财务确认 (仅退银行卡)
```

### 1.2 痛点

- 课程退费「退到余额」只能在财务确认阶段选择，前端用户感知弱
- 无「退到学员账户」的一站式路径——用户在申请时就想明确"这笔钱直接退回账户余额"
- 退到学员账户时不需要银行卡信息，审批流程也应简化（无需财务确认环节）

### 1.3 目标

课程退费申请时增加**第三种退费方式**：「退到学员账户」。该方式从申请到完成的完整流程如下：

```
退到学员账户: 申请(选择"退到学员账户") → 一级审批 → 二级审批通过 → 自动到账（跳过财务确认）
```

---

## 2. 功能规格

### 2.1 退费申请弹窗 — 新增退费方式选择

**位置**：`modal-refund-apply`（学员详情页 → 课程列表 → 退费按钮）

**新增内容**：在「费用调整」区块和「收款信息」区块之间，插入「退费方式」选择区：

```
┌─────────────────────────────────────────┐
│ 费用调整                                 │
│   自定义扣减金额 ______                   │
│   实退金额：¥xxx                         │
├─────────────────────────────────────────┤
│ 退费方式  ○ 退到银行卡   ○ 退到学员账户  │  ← 新增
├─────────────────────────────────────────┤
│ 收款信息（仅「退到银行卡」时显示）          │
│   转账银行 ______ 银行卡号 ______ 开户人 __│
│   退费原因 ____________                  │
└─────────────────────────────────────────┘
```

**交互规则**：

| 选择 | 收款信息区 | refund_method 值 | 审批流程 |
|------|-----------|-----------------|---------|
| 退到银行卡（默认） | 显示银行字段 | `'转账'` | 三级审批（含财务确认） |
| 退到学员账户 | **隐藏银行字段** | `'账户'` | 二级审批通过即完成 |

**UI 效果**：
- 使用 radio 按钮组，与审批弹窗中「退款方式」风格一致（`display:flex;gap:20px`）
- 选择「退到学员账户」时，收款信息区块整体隐藏（`display:none`）而非仅清空银行字段
- 提交时不要求银行信息必填

### 2.2 提交流程变更

**`submitRefundApply()` 新增逻辑**：
1. 读取 radio 选中值：`document.querySelector('input[name="refund-apply-method"]:checked').value`
2. 值为 `'cash'` → 保持原有逻辑（需要银行信息校验）
3. 值为 `'account'` → 跳过银行信息校验，`body.refund_to = 'account'`

**JSON 请求体变更**：
```json
// 退到银行卡（原有，不变）
{ "order_id": 123, "custom_deduction": 0, "bank_name": "...", "bank_account": "...",
  "account_holder": "...", "refund_reason": "...", "refund_type": "course" }

// 退到学员账户（新增）
{ "order_id": 123, "custom_deduction": 0, "refund_to": "account",
  "refund_reason": "...", "refund_type": "course" }
```

### 2.3 后端 `submit_refund` 课程分支变更

**文件**：`index.php` 第 3455-3510 行

**变更点**：
1. 读取新参数 `$refundTo = trim($input['refund_to'] ?? 'cash');`（已有，第 3481 行）
2. 现有逻辑 `$refundMethod = ($refundTo === 'balance') ? '账户' : '转账';`（第 3482 行）
3. **修改**这一行：
   ```php
   // 原代码 (line 3482):
   $refundMethod = ($refundTo === 'balance') ? '账户' : '转账';
   
   // 改为:
   $refundMethod = (in_array($refundTo, ['balance', 'account'])) ? '账户' : '转账';
   ```
4. 退到学员账户时（`$refundTo === 'account'`），银行字段在 INSERT 时可为空字符串（不需要填），前端已保证不发送这些字段

**关键**：`refund_method='账户'` 是后续 `approve_refund` 跳过财务确认的判断依据。

### 2.4 后端 `approve_refund` 二级审批变更 ⭐ 核心变更

**文件**：`index.php` 第 3705-3776 行

**当前逻辑**（第 3709-3711 行）：
```php
} elseif ($currentStage === '二级审批') {
    $db->exec("UPDATE refund_records SET status='二级审批通过', approval_stage='财务确认', approver2=...");
    json(['message' => '二级审批通过，等待财务确认']);
```

**变更后逻辑**：
```php
} elseif ($currentStage === '二级审批') {
    // ⭐ 新增：课程退费 + 退到学员账户 → 直接完成，跳过财务确认
    $rrMethod = $rr['refund_method'] ?? '转账';
    $rrProject = $rr['project'] ?? '课程';
    
    if ($rrProject === '课程' && $rrMethod === '账户') {
        // 直接完成退费：余额到账 + 写流水
        $refundAmount = floatval($rr['actual_refund'] ?? 0);
        $orderId = intval($rr['order_id']);
        $studentId = intval($rr['student_id']);
        $order2 = $db->query("SELECT lesson_count FROM orders WHERE id=$orderId")->fetch(PDO::FETCH_ASSOC);
        $lc = $order2 ? intval($order2['lesson_count']) : 0;
        
        $db->beginTransaction();
        try {
            // 1. 更新退费记录：直接标记「已退费」（跳过财务确认）
            $db->exec("UPDATE refund_records SET status='已退费', approval_stage='已完成', approver2=" 
                . $db->quote($approver) . ", updated_at='$n' WHERE id=$id");
            
            // 2. 将订单消耗课时设置为总课时（剩余课时归零）
            $db->exec("UPDATE orders SET refund_status='已退费', consumed_lessons=$lc WHERE id=$orderId");
            
            // 3. 余额到账 + FOR UPDATE 防并发
            $acct = $db->prepare("SELECT balance FROM student_accounts WHERE student_id = :sid FOR UPDATE");
            $acct->bindValue(':sid', $studentId, PDO::PARAM_INT);
            $acct->execute();
            $acctRow = $acct->fetch(PDO::FETCH_ASSOC);
            $oldBalance = $acctRow ? floatval($acctRow['balance']) : 0.00;
            $newBalance = round($oldBalance + $refundAmount, 2);
            
            // 4. 更新余额
            $upd = $db->prepare("INSERT INTO student_accounts (student_id, balance, total_deposit, total_consume, total_refund) VALUES (:sid, :bal, 0, 0, :tr) ON DUPLICATE KEY UPDATE balance = balance + :bal2, total_refund = total_refund + :tr2");
            $upd->bindValue(':sid', $studentId, PDO::PARAM_INT);
            $upd->bindValue(':bal', $refundAmount);
            $upd->bindValue(':tr', $refundAmount);
            $upd->bindValue(':bal2', $refundAmount);
            $upd->bindValue(':tr2', $refundAmount);
            $upd->execute();
            
            // 5. 写账户流水
            $stmt2 = $db->prepare("INSERT INTO account_transactions (student_id, type, amount, balance_after, ref_type, ref_id, campus, note) VALUES (:sid, 'refund', :amt, :ba, 'refund', :rid, :campus, :note)");
            $stmt2->bindValue(':sid', $studentId, PDO::PARAM_INT);
            $stmt2->bindValue(':amt', $refundAmount);
            $stmt2->bindValue(':ba', $newBalance);
            $stmt2->bindValue(':rid', $id, PDO::PARAM_INT);
            $stmt2->bindValue(':campus', $rr['campus'] ?? '', PDO::PARAM_STR);
            $stmt2->bindValue(':note', '课程退费-退回学员账户: ' . ($rr['course_name'] ?? ''), PDO::PARAM_STR);
            $stmt2->execute();
            
            $db->commit();
            json(['message' => '二级审批通过，退费已自动到账学员账户']);
        } catch (Exception $e) {
            $db->rollBack();
            json(['error' => '退费到账户失败：' . $e->getMessage()]);
        }
    } else {
        // 原有逻辑：进入财务确认阶段
        $db->exec("UPDATE refund_records SET status='二级审批通过', approval_stage='财务确认', approver2=" 
            . $db->quote($approver) . ", updated_at='$n' WHERE id=$id");
        json(['message' => '二级审批通过，等待财务确认']);
    }
}
```

**关键设计决策**：
- `refund_method='账户'` + `project='课程'` 是触发「跳过财务确认」的判断条件
- 原有课程退费的「财务确认阶段退到余额」逻辑（第 3728-3765 行）**保持不变**，这是用户申请时选「退到银行卡」但财务确认时改为余额的场景
- 账户退费（`project='账户'`）的二级审批 **不受影响**，因为 `$rrMethod` 不满足 `'账户'` 条件（账户退费的 `refund_method` 是 `'转账'`）

### 2.5 审批弹窗前端变更

**文件**：`main.js` 第 7825-7921 行（`showApproveModal()`）+ 审批弹窗 HTML

**变更点**：

1. **财务确认阶段的退款方式 radio**（第 7896-7902 行）：
   - 当 `refund_method === '账户'` 时，隐藏「退到余额」选项（因为申请时已决定退账户，无需财务确认再选择）
   - 这其实已经被「跳过财务确认」所覆盖——因为二级审批直接完成了，财务确认阶段不再出现。但如果未来有边缘场景需要财务确认，加上防护：
   ```javascript
   const rrMethod = rr.refund_method || '转账';
   const isAccountMethod = (rrMethod === '账户');
   // 财务确认区域：退费方式 radio，账户退费只显示「退到银行卡」
   ${rr.approval_stage === '财务确认' ? `
       <div class="form-group" id="approve-refund-to-group">
           <label>退款方式</label>
           <div style="display:flex;gap:20px;">
               <label style="font-weight:normal;cursor:pointer;">
                   <input type="radio" name="approve-refund-to" value="cash" checked> 退到银行卡
               </label>
               ${isAccount || isAccountMethod ? '' : `
                   <label style="font-weight:normal;cursor:pointer;">
                       <input type="radio" name="approve-refund-to" value="balance"> 退到余额
                   </label>
               `}
           </div>
       </div>
   ` : ''}
   ```

2. **审批进度步骤**（第 7840-7856 行）：
   - 当 `refund_method === '账户'` 且当前阶段为 `'二级审批'` 时，在审批进度下方显示提示：
   ```javascript
   // 在 stepsHtml 渲染后、approverInfo 之前插入
   if (rr.approval_stage === '二级审批' && rrMethod === '账户') {
       stepsHtml += `<div style="margin-top:10px;padding:8px 12px;background:#f0fdf4;border-left:3px solid #16a34a;color:#15803d;font-size:13px;border-radius:4px;">
           💡 退费方式为「退到学员账户」，二级审批通过后自动到账，无需财务确认
       </div>`;
   }
   ```

3. **审批进度步骤数调整**：
   - 当 `refund_method === '账户'` 时，步骤从 3 步（一级审批/二级审批/财务确认）改为 2 步（一级审批/二级审批）
   - 或者保持 3 步但标记财务确认为「跳过」状态。**推荐方案**：保持 3 步显示，财务确认步骤在 status='已退费' 时自动标记为 done（与现有逻辑一致）

### 2.6 审批进度状态机

```
退到银行卡:
  待审批 → [一级审批:approve] → 一级审批通过
        → [二级审批:approve] → 二级审批通过
        → [财务确认:approve] → 已退费

退到学员账户:  ← 新增
  待审批 → [一级审批:approve] → 一级审批通过
        → [二级审批:approve] → 已退费（自动到账，跳过财务确认）
        → [任意阶段:reject] → 审批驳回 → 恢复 orders.refund_status='正常'

退到余额（财务确认时选择，原有）:
  待审批 → [一级审批:approve] → 一级审批通过
        → [二级审批:approve] → 二级审批通过
        → [财务确认:approve + refund_to=balance] → 已退费 + 余额到账
```

---

## 3. 数据结构

### 3.1 无需新增字段

`refund_method` 列（`VARCHAR(20) DEFAULT '转账'`）已存在，值 `'账户'` 也已在课程退费「财务确认退到余额」场景中使用。本次需求复用该字段。

### 3.2 新参数

| 参数 | 来源 | 类型 | 值 | 说明 |
|------|------|------|-----|------|
| `refund_to` | `submitRefundApply()` → `submit_refund` | string | `'cash'` / `'account'` | 退费方式选择。已有 `'balance'` 专用于财务确认阶段，本次新增 `'account'` 用于申请阶段 |

### 3.3 `refund_method` 值语义总结

| refund_method | 含义 | 设置时机 | 审批流程 |
|---------------|------|----------|---------|
| `'转账'` | 退到银行卡 | 申请时（默认） | 三级审批（含财务确认） |
| `'账户'` | 退到学员账户余额 | 申请时（用户选择）/ 财务确认时（管理员选择） | 申请时选择 → 二级审批即完成；财务确认时选择 → 财务确认后完成 |

---

## 4. 文件变更清单

### 4.1 后端 — `index.php`

| 行号范围 | 变更类型 | 说明 |
|----------|---------|------|
| 3481-3482 | **修改** | `refund_to` 读取 + `refund_method` 映射：`'account'` 也映射为 `'账户'` |
| 3709-3711 | **插入** | 二级审批阶段，检查 `refund_method='账户'` → 直接完成退费 + 余额到账 |

### 4.2 前端 — `static/js/main.js`

| 行号范围 | 变更类型 | 说明 |
|----------|---------|------|
| 7500-7530 | **修改** | `showRefundApplyModal()`：恢复 radio 默认选中「退到银行卡」 |
| 7538-7569 | **修改** | `submitRefundApply()`：读取 radio 值，`'account'` 时跳过银行校验 + 发送 `refund_to` |
| 7825-7921 | **修改** | `showApproveModal()`：二级审批 + `refund_method='账户'` 时显示到账提示；隐藏财务确认 radio 中的「退到余额」 |

### 4.3 前端 — `index.php`（HTML）

| 行号范围 | 变更类型 | 说明 |
|----------|---------|------|
| 8171-8198 | **插入** | 在「费用调整」和「收款信息」之间插入「退费方式」radio 区块 |
| 8197-8217 | **修改** | 收款信息区块加 id（`refund-bank-info-section`），便于 JS 控制显隐 |

### 4.4 前端 — `static/css/style.css`

| 说明 |
|------|
| 退费方式 radio 组样式（如需要）：`.refund-method-radio-group` — 与审批弹窗 radio 风格一致 |

---

## 5. 测试用例

### 5.1 退费申请弹窗 UI

| # | 测试场景 | 预期结果 |
|---|---------|---------|
| T1 | 默认选中「退到银行卡」 | 银行信息区显示，可正常填写 |
| T2 | 选择「退到学员账户」 | 银行信息区隐藏，提交按钮可用 |
| T3 | 切回「退到银行卡」 | 银行信息区重新显示 |

### 5.2 提交退费申请

| # | 测试场景 | 预期结果 |
|---|---------|---------|
| T4 | 退到银行卡，填全信息后提交 | `refund_method='转账'`，INSERT 成功，订单状态变为「退费申请中」|
| T5 | 退到学员账户，不填银行信息提交 | `refund_method='账户'`，`bank_name/bank_account/account_holder` 为空字符串，INSERT 成功 |
| T6 | 退到学员账户，退款记录表格显示「退费方式=账户」（绿色 badge） | badge 正确渲染 |

### 5.3 审批流程

| # | 测试场景 | 预期结果 |
|---|---------|---------|
| T7 | 退到账户的记录，一级审批通过 | status='一级审批通过'，approval_stage='二级审批' |
| T8 | 退到账户的记录，二级审批通过 | status='已退费'，学员账户余额 +actual_refund，account_transactions 写入流水，orders.refund_status='已退费' |
| T9 | 退到银行卡的记录，二级审批通过 | status='二级审批通过'，approval_stage='财务确认'（不变） |
| T10 | 退到账户的记录，一级审批驳回 | status='审批驳回'，orders.refund_status 恢复为「正常」|
| T11 | 退到账户的记录，二级审批驳回 | status='审批驳回'，orders.refund_status 恢复为「正常」|
| T12 | 退到账户，审批弹窗显示「退费方式为退到学员账户，二级审批通过后自动到账」提示 | 绿色提示条显示在审批进度下方 |

### 5.4 边界情况

| # | 测试场景 | 预期结果 |
|---|---------|---------|
| T13 | 退到账户 + 余额不足（学员无 student_accounts 记录） | INSERT ON DUPLICATE KEY UPDATE 自动创建记录并增加余额 |
| T14 | 退到账户 + 并发审批（两个审批人同时点通过） | FOR UPDATE 锁保证余额一致性 |
| T15 | 退到账户的记录，撤销退费 | 原有课程退费撤销逻辑（删除 refund_record + 恢复 orders.refund_status），不需要恢复余额（因为钱还没到账） |
| T16 | 退到银行卡的旧记录，approval_stage='财务确认'，refund_method 为空或'转账' | 不受影响，走原有财务确认流程 |

---

## 6. 风险与注意事项

### 6.1 关键风险

| 风险 | 缓解措施 |
|------|---------|
| `refund_method='账户'` 与已有「财务确认退余额」的 `refund_method='账户'` 冲突 | 两者语义一致（都是退到账户余额），只是触发时机不同。`approve_refund` 中通过 `approval_stage` 区分：二级审批阶段检查 `refund_method='账户'` → 跳过财务确认；财务确认阶段检查 `refund_to='balance'` → 余额到账 |
| 撤销退费（`cancel_refund`）时，退到账户的记录余额尚未到账 | 课程退费的 `cancel_refund` 逻辑是 `DELETE FROM refund_records` + `UPDATE orders SET refund_status='正常'`——余额从未被扣减，不需要恢复。仅账户退费（`project='账户'`）才需要恢复余额。**无影响** |
| `refund_to` 参数命名冲突 | 已有 `refund_to='balance'`（在 `approve_refund` 财务确认阶段使用），新增 `refund_to='account'`（在 `submit_refund` 申请阶段使用）。两个参数在不同 API 中，不会冲突 |

### 6.2 实现注意事项

1. **事务包裹**：二级审批直接完成退费的余额操作必须在 `beginTransaction()/commit()/rollBack()` 中（参考现有代码第 3730 行模式）
2. **FOR UPDATE 锁**：余额操作前必须锁行防并发（参考第 3737 行）
3. **`approval_stage` 值**：二级审批完成后 `approval_stage` 设为 `'已完成'`（新值）而非 `'财务确认'`，便于前端区分
4. **colspan 同步**：如果表格列数变更，检查 `renderRefundRecordTable()` 中的 colspan 值
5. **JS 无重复函数**：新增/修改函数前确认无名称冲突
6. **`esc()` 转义**：所有用户数据渲染到 HTML 前必须转义

---

## 7. 工时估算

| 模块 | 工作内容 | 预估工时 |
|------|---------|---------|
| 后端 | `submit_refund` 参数映射修改 | 0.2h |
| 后端 | `approve_refund` 二级审批直接完成逻辑 | 1h |
| 前端 HTML | 退费申请弹窗增加 radio 组 + 银行区 id | 0.3h |
| 前端 JS | `showRefundApplyModal` + `submitRefundApply` + `showApproveModal` 变更 | 0.5h |
| 前后端联调 | 端到端测试（curl + 浏览器） | 0.5h |
| Code Review | 按 `refund-review-checklist.md` 逐项检查 | 0.5h |
| **合计** | | **~3h** |

---

## 8. 附录：完整数据流

```
┌─────────────────────────────────────────────────────────────────┐
│                    课程退费「退到学员账户」                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  申请阶段 (submit_refund)                                        │
│  ┌──────────────────────────┐                                   │
│  │ refund_to = 'account'    │                                   │
│  │ refund_method = '账户'   │                                   │
│  │ bank_* = '' (空)         │                                   │
│  │ status = '待审批'         │                                   │
│  │ orders.refund_status     │                                   │
│  │   = '退费申请中'          │                                   │
│  └────────┬─────────────────┘                                   │
│           │                                                     │
│           ▼                                                     │
│  一级审批 (approve_refund, action=approve)                       │
│  ┌──────────────────────────┐                                   │
│  │ status = '一级审批通过'    │                                   │
│  │ approval_stage = '二级审批'│                                   │
│  └────────┬─────────────────┘                                   │
│           │                                                     │
│           ▼                                                     │
│  二级审批 (approve_refund, action=approve)  ⭐ 关键步骤            │
│  ┌──────────────────────────┐                                   │
│  │ 检测: refund_method='账户' │                                  │
│  │ ↓                        │                                   │
│  │ status = '已退费'          │  ← 跳过财务确认                    │
│  │ approval_stage = '已完成'  │                                   │
│  │                          │                                   │
│  │ BEGIN TRANSACTION        │                                   │
│  │   FOR UPDATE 锁行        │                                   │
│  │   student_accounts       │                                   │
│  │   .balance += refundAmt  │                                   │
│  │   .total_refund += ...   │                                   │
│  │   INSERT account_        │                                   │
│  │     transactions 流水     │                                   │
│  │   orders.refund_status   │                                   │
│  │     = '已退费'            │                                   │
│  │   orders.consumed_       │                                   │
│  │     lessons = 总课时      │                                   │
│  │ COMMIT                   │                                   │
│  └──────────────────────────┘                                   │
│                                                                 │
│  任意阶段驳回 (action=reject)                                     │
│  ┌──────────────────────────┐                                   │
│  │ status = '审批驳回'        │                                   │
│  │ orders.refund_status     │                                   │
│  │   = '正常'               │                                   │
│  │ (无需恢复余额，钱未动)      │                                   │
│  └──────────────────────────┘                                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## 9. 与现有退款方式的对比总览

| 维度 | 退到银行卡 (现有) | 退到余额 (现有) | 退到学员账户 (新增) |
|------|-----------------|----------------|-------------------|
| 选择时机 | 申请时默认 | 财务确认阶段选择 | **申请时选择** |
| refund_method | `'转账'` | `'账户'` (财务确认时改写) | **`'账户'` (申请时写入)** |
| 审批流程 | 三级审批 | 三级审批 | **二级审批即完成** |
| 银行信息 | 必填 | 必填 | **不需要（隐藏）** |
| 余额到账时机 | N/A | 财务确认通过后 | **二级审批通过后** |
| account_transactions | N/A | type='refund', ref_type='refund' | **type='refund', ref_type='refund'** |
| 前端感知 | 退款方式 radio 在审批弹窗(财务确认) | 同左 | **退款方式 radio 在申请弹窗** |
