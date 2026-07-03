# 班级管理+考勤+课表合并计划

> **For Hermes:** One agent, surgical edits, commit per step.

**Goal:** 将班级管理移入考勤页面作为首个标签页，统一为：班级管理 | 课表 | 操作考勤 | 学员课耗 | 缺勤记录

**Architecture:** panel-attendance 新增第一个 tab "班级管理"，嵌入 panel-classes 的表格和逻辑。移除导航中独立的"班级管理"菜单项。

**Tech Stack:** PHP 8.4 + Vanilla JS + CSS3

---

## Task 1: 在 panel-attendance 新增"班级管理"标签页

**Files:** `D:\market-system-php\index.php`

**Step 1: 插入 tab 按钮**
在 `.attendance-tabs` 最前面插入 `<button class="att-tab active" data-tab="tab-classes">班级管理</button>`，并将原来的 `active` 从"操作考勤"移除。

**Step 2: 插入 tab 内容容器**
在 `.attendance-tab-content` 最前面插入：
```html
<div class="att-panel active" id="tab-classes"></div>
```
将原 `active` 从 `tab-attendance-operations` 移除。

**Step 3: 调整 tab 顺序**
最终 tabs: 班级管理(active) | 课表 | 操作考勤 | 学员课耗 | 缺勤记录

**验证:** 刷新页面，考勤面板默认显示"班级管理"标签页，内容区为空（下一步填充）。

---

## Task 2: 将 panel-classes 的功能注入 tab-classes

**Files:** `D:\market-system-php/index.php` `D:\market-system-php/static/js/main.js`

**Step 1: 读取 panel-classes HTML**
定位 panel-classes 的 HTML（toolbar + 表格区域），复制其结构，将其插入到 `tab-classes` 容器内。ID 需要加前缀避免冲突，如 `class-search` → `att-class-search`。

**Step 2: JS 切换处理**
修改 `switchPanel` 函数使得进入 `panel-attendance` 时自动加载班级列表。修改 `switchAttendanceTab` 函数处理 `tab-classes` 标签切换。

**Step 3: 删除独立 panel-classes 的 HTML**
或者保留但隐藏（更安全）。建议保留 HTML 但隐藏，通过 CSS `display:none` 或注释掉。导航中也隐藏该项。

**验证:** 点击考勤 → 班级管理 tab → 显示班级列表和搜索功能。

---

## Task 3: 调整导航菜单

**Files:** `D:\market-system-php/index.php`

**Step 1: 隐藏独立的"班级管理"菜单项**
用 HTML 注释包裹或添加 `style="display:none"`。

**Step 2: 确保"考勤"菜单项突出**
可考虑把"考勤"改为"班级/考勤"或"教学管理"，提示用户这是统一入口。

**验证:** 左侧导航无"班级管理"，点击"考勤"进入统一面板。

---

## Task 4: Git 提交 + 合并

```bash
git checkout -b feature/attendance-merge-classes
# ... 修改 ...
git add index.php static/js/main.js
git commit -m "feat: 班级管理移入考勤面板作为首个标签页——统一入口：班级管理|课表|操作考勤|学员课耗|缺勤记录"
git checkout develop && git merge --no-ff feature/attendance-merge-classes
```
