# TMS管理系统开发规范

核心 skill: tms-development
加载方式: 每个新会话首次提到 TMS/market-system-php 时必须 `skill_view('tms-development')`

## 开发流程约定（强制执行）

> 核心规则：所有开发任务必须通过 Agency Agents 六专家流程执行。使用 `delegate_task` 派发子 Agent 独立干活，主会话只负责调度和验收，不直接写代码。

### 强制工作流

```
用户提出需求
  ↓
1. UI Designer        → delegate_task(goal, context, toolsets)      → 设计页面与交互
2. Backend Architect  → delegate_task(goal, context, toolsets)      → 写后端代码
3. Frontend Developer → delegate_task(goal, context, toolsets)      → 实现前端
4. API Tester         → delegate_task(goal, context, toolsets)      → 测试所有端点
5. Code Reviewer      → delegate_task(goal, context, toolsets)      → 审查代码
6. Git Workflow Master → delegate_task(goal, context, toolsets)     → 分支策略+提交
```

每个子 Agent 独立运行在自己的上下文中，完成后返回结果。主会话收到结果后汇报给用户。

| 阶段 | 专家 | Slug | 用途 |
|------|------|------|------|
| 🎨 设计 | UI Designer | ui-designer | 页面布局、交互设计、组件样式 |
| 🔧 后端 | Backend Architect | backend-architect | 系统设计、API开发、数据库架构、PHP |
| 💻 前端 | Frontend Developer | frontend-developer | HTML/CSS/JS 实现、Vanilla JS |
| 🧪 测试 | API Tester | api-tester | API 测试、功能验证 |
| 🔍 审查 | Code Reviewer | code-reviewer | 代码审查、安全、可维护性 |
| 🌿 分支 | Git Workflow Master | git-workflow-master | 分支策略、commit 规范、合并流程 |

### 主会话职责边界

- ✅ 派发子 Agent（`delegate_task`）
- ✅ 流程调度（决定先派哪个专家）
- ✅ 结果验收和汇报
- ❌ 直接写 HTML/CSS/JS/PHP
- ❌ 直接用 `patch()` 改代码
- ❌ 用 `execute_code` 跑临时脚本代替专家执行
- ❌ 用 `agency_agents_load` 化身专家（那是主会话自己在干活）

### 新功能开发：先给方案再看

在动手写任何代码前，先用文字描述：API 设计、数据流、前端布局、交互规格。用户确认后再派专家实现。

### Git 提交规范

- 每次代码修改后立即 `git add` + `git commit`（仅本地）
- 只在用户明确说 push 时才 `git push`
