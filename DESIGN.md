# TMS 管理系统 — 设计系统 (DESIGN.md)

> 教育培训教务管理系统品牌设计规范。Open Design 将读取此文件，确保所有生成的 UI 原型、仪表盘、弹窗与现有系统视觉一致。

---

## 1. 品牌标识

- **产品名称**：TMS 管理系统
- **行业**：教育培训（K12 + 素质教育）
- **设计调性**：现代、专业、温暖，紫色为主色调传递信赖感与成长感

---

## 2. 色彩系统

### 主色（Purple Scale）
| Token | 色值 | 用途 |
|-------|------|------|
| `--color-primary` | `#7C3AED` | 主按钮、链接、选中态、图标 |
| `--color-primary-light` | `#A78BFA` | 悬停高亮、装饰元素、图标亮色 |
| `--color-primary-dark` | `#6D28D9` | 按钮 hover、渐变起点 |
| `--color-primary-bg` | `#F5F0FF` | 浅紫背景、卡片 hover、标签底色 |
| `--color-primary-hover` | `#EDE5FF` | 行 hover 背景 |

### 中性色
| Token | 色值 | 用途 |
|-------|------|------|
| `--color-bg` | `#F8F7FC` | 页面背景 |
| `--color-surface` | `#FFFFFF` | 卡片、表格、弹窗背景 |
| `--color-text` | `#1E1B2E` | 主文字 |
| `--color-text-secondary` | `#6B6880` | 辅助文字 |
| `--color-text-muted` | `#9895A8` | 占位符、禁用态 |
| `--color-border` | `#E2E0E7` | 边框 |
| `--color-border-light` | `#F0EFF4` | 细分隔线 |

### 侧边栏（Dark）
| Token | 色值 | 用途 |
|-------|------|------|
| 背景 | `#1a1825` | 侧边栏底色 |
| 选中态 | `rgba(124,58,237,0.25)` | 渐变激活背景 |
| 文字 | `rgba(255,255,255,0.50)` | 默认文字 → hover 至 `0.90` |

### 语义色
| 用途 | 色值 | 说明 |
|------|------|------|
| 成功 | `#38A169` | 操作成功、状态通过 |
| 警告 | `#DD6B20` | 注意提示 |
| 危险 | `#E53E3E` | 删除、驳回、错误 |
| 信息 | `#3182CE` | 一般提示 |
| 危险背景 | `#FFF5F5` | 危险按钮/标签背景 |

---

## 3. 字体

| 用途 | 字体栈 |
|------|--------|
| 全局 | `-apple-system, BlinkMacSystemFont, 'PingFang SC', 'Microsoft YaHei', 'Segoe UI', sans-serif` |
| 字号基准 | `14px`（body） |
| 标题 H3 | `18px / 700` |
| 弹窗标题 H3 | `17px / 700` |
| 标签 label | `12px / 500` |

> 使用系统原生字体栈，中文优先 `PingFang SC` / `Microsoft YaHei`。开启 `-webkit-font-smoothing: antialiased`。

---

## 4. 间距 & 圆角

| Token | 值 | 用途 |
|-------|-----|------|
| `--radius-sm` | `6px` | 小按钮、输入框内边距 |
| `--radius-md` | `8px` | 输入框、卡片、选项 chip |
| `--radius-lg` | `12px` | 面板头部、弹窗 |
| `--radius-xl` | `16px` | 弹窗外框、大卡片 |

---

## 5. 阴影

| Token | 值 | 用途 |
|-------|-----|------|
| `--shadow-sm` | `0 1px 3px rgba(0,0,0,0.04)` | 表格容器、轻量卡片 |
| `--shadow-md` | `0 4px 12px rgba(0,0,0,0.06)` | 下拉菜单 |
| `--shadow-lg` | `0 12px 32px rgba(0,0,0,0.10)` | 弹窗 |
| 弹窗阴影 | `0 20px 60px rgba(0,0,0,0.18)` | modal 专属 |

---

## 6. 组件规范

### 6.1 按钮

**主按钮 `.btn-primary`**
```css
background: #7C3AED;  color: #fff;
border-radius: 6px;  padding: 8px 20px;
```
- hover: 背景变深 `#6D28D9` + 紫色辉光 `box-shadow: 0 2px 8px rgba(124,58,237,0.25)`
- active: `transform: scale(0.98)`

**线框按钮 `.btn-outline`**
```css
background: #fff;  color: #7C3AED;  border: 1px solid #7C3AED;
```
- hover: 背景变 `#F5F0FF`

**灰色线框 `.btn-outline-gray`** — 次要操作，灰色边框

**表格操作链接 `.btn-link`**
```css
padding: 3px 10px;  border-radius: 12px;
border: 1px solid #d4d4f7;  background: #f5f5ff;
color: #5b5bd6;  font-size: 12px;
```

**危险链接 `.btn-link-danger`** — 红色系，用于删除操作

**小按钮 `.btn-sm`** — `padding: 5px 12px; font-size: 12px`

### 6.2 表格

- 容器 `.table-wrap`：白色背景、圆角底部 `0 0 12px 12px`、阴影 sm
- 表格使用默认 auto layout（**不要**设 `table-layout: fixed`）
- 行 hover：浅紫背景 `#F5F0FF`
- 表头：默认字体，sticky 可选
- 金额列：右对齐、颜色强调（红色 `#DC2626` 表退款/扣减）
- 空态：居中紫色 SVG 图标 + 主副文字

### 6.3 弹窗 (Modal)

- 背板：`rgba(0,0,0,0.40)` + `backdrop-filter: blur(4px)`
- 弹窗框：白色背景、圆角 16px、入场动画 `modalSlideIn`
- 默认宽度 `520px`，大号 `.modal-lg` 宽度 `680px`
- 头部：`padding: 20px 28px`，底部分隔线
- 关闭按钮：圆形 32px、hover 浅灰背景
- **标题 H3**：`17px / 700`

### 6.4 表单

- 输入框/下拉/文本域：`padding: 9px 14px`、圆角 8px、灰色边框
- focus: 紫色边框 + `box-shadow: 0 0 0 3px rgba(124,58,237,0.08)`
- label：`12px / 500`、灰色 `#6B6880`、字母间距 0.3px
- 必填 `*`：紫色 `#7C3AED`

### 6.5 搜索工具栏

- 搜索框：自带搜索图标、左边内边距 36px、背景 `#F8F7FC`
- 下拉筛选：灰色背景、自定义箭头
- focus: 紫色边框 + 辉光
- 按钮：紫色渐变搜索按钮、圆角 pill 8px

### 6.6 侧边栏导航

- 宽度：`248px`，暗色背景 `#1a1825`
- Logo 区：紫色渐变 `#7C3AED → #6D28D9 → #4C1D95`
- 父节点：`13.5px / 600`、半透明白色、hover 左竖条动画
- 叶子节点：`13px`、圆角 8px、hover 上浮 `translateX(2px)`
- 选中态：紫色渐变背景 + 左侧 3px 竖条 + 内阴影辉光

### 6.7 面板头部

- `padding: 20px 28px`、白色背景、底部边框
- 标题：`18px / 700`
- 统计徽章 `.stat-badge`：圆角 pill 20px、`12px` 字、浅灰背景、紫色数字加粗

### 6.8 Section Tabs

- 容器：flex、底部 2px 灰线
- Tab：`padding: 10px 20px`、底部透明 2px 线
- Active：`#5a6bcf` 色 + 底部 2px 实线 + 加粗 600

### 6.9 Toast 通知

- 固定顶部居中、`z-index: 99999`
- 下滑动画 `toastIn`：`translateY(-12px) → 0`
- 4 种语义色：
  - info: `#e6f7ff`（蓝底）
  - warn: `#fff7e6`（橙底）
  - error: `#fff2f0`（红底）
  - success: `#f6ffed`（绿底）
- 2秒自动消失、`pointer-events: none`

### 6.10 自定义确认弹窗

- 替代浏览器 `confirm()`，紫色主题
- 两按钮布局（确定 / 取消）

---

## 7. 动效

| 元素 | 动画 | 时长 | 缓动 |
|------|------|------|------|
| 弹窗入场 | `modalSlideIn`（上滑 + 淡入） | 0.25s | `cubic-bezier(0.4, 0, 0.2, 1)` |
| Toast 入场 | `toastIn`（下滑 + 淡入） | 0.25s | ease |
| 侧边栏展开 | `max-height` 过渡 | 0.4s | `cubic-bezier(0.4, 0, 0.2, 1)` |
| 按钮 active | `scale(0.98)` | — | — |
| 通用过渡 | `--transition: 0.2s ease` | 0.2s | ease |
| 行 hover 上浮 | `translateX(2px)` / `translateY(-1px)` | — | — |

---

## 8. 禁止事项 ⛔

- **禁止**使用浏览器原生 `alert()` / `confirm()` → 用 hermes-toast + 自定义确认
- **禁止**使用 `table-layout: fixed`（会导致列宽偏移）
- **禁止**在表格中混用 `position: relative` + `::before`（会导致列错位）
- **禁止**对金额列使用 `font-family: monospace`（列宽偏移）
- **禁止**使用黑色背景 — 主题是浅色系紫色

---

## 9. 典型页面结构

```
┌──────────────────────────────┐
│  侧边栏 (248px, 暗色)        │  内容区
│  ┌─ Logo 紫色渐变           │  ┌─ panel-header (20px 28px)
│  ├─ 工作台 ▼                │  │  标题 18px Bold + stat-badge
│  │  ├─ 今日待办             │  ├─ section-tabs
│  │  └─ ...                  │  │  ┌─ tab1  ┌─ tab2
│  ├─ 教务管理 ▼              │  ├─ toolbar (搜索+筛选+按钮)
│  │  ├─ 学员管理             │  ├─ table-wrap
│  │  ├─ 班级管理             │  │  ┌─ thead ────────────┐
│  │  └─ ...                  │  │  │  tr (hover 浅紫)   │
│  └─ ...                     │  │  └────────────────────┘
└──────────────────────────────┘  └─ 分页
```

---

## 10. 使用 Open Design 时的提示词模板

**生成新面板原型：**
> 为 TMS 教育培训管理系统设计一个「优惠券管理」面板。左侧 248px 暗色侧边栏，右侧内容区包含：panel-header（标题 + stat badge）、section-tabs（全部/已使用/未使用）、搜索筛选 toolbar、数据表格（表格使用 auto layout，行 hover 浅紫背景）、分页。使用 DESIGN.md 中的紫色主题。

**生成弹窗原型：**
> 为 TMS 设计一个「新建优惠券」弹窗，包含表单：名称、类型、金额、有效期、使用条件。使用 modal-overlay + blur 背板，弹窗圆角 16px，紫色主按钮。参考 DESIGN.md。
