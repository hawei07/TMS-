# 后台管理系统设计规范（Admin Dashboard）


整体布局为「**顶部菜单（一级/父级）+ 左侧菜单（二级/子级）混合模式**」。

---

## 一、全局设计规范（Design Tokens）

用 CSS 变量统一管理，放在全局样式（`src/styles/tokens.css`）中，并与 Element Plus 主题变量对齐。

```css
:root {
  /* 主色 */
  --brand:        #3B6EF6;   /* 主色（蓝） */
  --brand-hover:  #2E5AD6;   /* 主色悬停 */
  --brand-soft:   #EAF1FF;   /* 主色浅底（选中背景） */

  /* 中性色 */
  --text-primary:   #1F2430; /* 主文字 */
  --text-regular:   #5A6072; /* 常规文字 */
  --text-secondary: #9AA0AE; /* 次要/占位文字 */
  --border:         #EBEEF3; /* 边框/分割线 */
  --bg-page:        #F5F7FA; /* 页面底色 */
  --bg-card:        #FFFFFF; /* 卡片底色 */

  /* 语义色 */
  --success: #34C759;
  --warning: #FF9F0A;
  --danger:  #FF3B30;
  --info:    #8E8E93;

  /* 圆角 */
  --radius-sm: 8px;
  --radius-md: 12px;
  --radius-lg: 16px;

  /* 阴影 */
  --shadow-card:  0 1px 3px rgba(20,30,60,0.06), 0 8px 24px rgba(20,30,60,0.04);
  --shadow-pop:   0 6px 24px rgba(20,30,60,0.12);

  /* 尺寸 */
  --header-h: 60px;   /* 顶栏高度 */
  --sider-w:  220px;  /* 侧栏展开宽度 */
  --sider-w-collapsed: 64px; /* 侧栏收起宽度 */
}
```

**字体**：`font-family: "Inter", "PingFang SC", "Microsoft YaHei", sans-serif;`，`-webkit-font-smoothing: antialiased;`（通过 `*` 选择器全局设置）。

**Element Plus 主题**：通过覆盖 CSS 变量把 `--el-color-primary` 设为 `--brand`，圆角、边框色与上表对齐。

**图标统一用 lucide-vue-next**（如 `LayoutDashboard, Users, FileText, Settings, Bell, Search, ChevronDown, LogOut, Menu` 等），不用 Element Plus 自带图标。

---

## 二、菜单数据结构（顶+侧混合的核心）

菜单是「两级树」。**顶栏渲染所有一级项**；选中某个一级项后，**左侧栏渲染该一级项的 children**。


**联动规则**：
- 顶栏点击一级项 → 设置 `activeTopMenu` → 左侧栏切换为对应 children → 自动跳转到该一级下第一个叶子路由。
- 页面路由变化时（含刷新 / 直接输入 URL）→ 反向推导出所属的一级 key，同步高亮顶栏与侧栏。

---

## 三、整体布局（AppLayout）

有顶栏+侧栏的页面共用 `AppLayout`（`<router-view>` 嵌在内容区）。登录页不使用该布局。

### 结构

```
┌───────────────────────────────────────────────────────────┐
│ 顶栏 Header（固定，高 60px）                                  │
│  Logo | 一级菜单(横向)                    搜索 铃铛 主题 头像   │
├──────────┬────────────────────────────────────────────────┤
│ 侧栏     │ 内容区 Content                                    │
│ Sider    │  ├ 面包屑 Breadcrumb                             │
│ (二级菜单)│  └ <router-view/>（各页面）                       │
│          │                                                  │
└──────────┴────────────────────────────────────────────────┘
```

### 顶栏 Header

- 高度 `--header-h`，`background: var(--bg-card)`，底部 `1px solid var(--border)`，`position: sticky; top: 0; z-index: 100`。
- **左侧**：Logo（图标方块 `28x28`，圆角 `--radius-sm`，主色底 + 白色 lucide 图标）+ 产品名「Nebula Admin」（`16px` 加粗，`--text-primary`）。
- **中部**：一级菜单横向排列。每项：`padding: 8px 14px; border-radius: var(--radius-sm)`，含 lucide 图标（16px）+ 文字（14px）。激活态：`background: var(--brand-soft); color: var(--brand)`；未激活：`color: var(--text-regular)`，hover 变 `--text-primary`。
- **右侧（flex，gap 12px）**：
  1. 搜索框：`el-input` 圆角胶囊，前缀 Search 图标，占位「搜索…」，宽 200px，`md` 以下隐藏。
  2. 铃铛按钮：`el-badge` 包裹 Bell 图标，红点显示未读。
  3. 主题切换按钮（Sun/Moon 图标，占位交互即可）。
  4. 用户头像：`el-dropdown`，头像用 `el-avatar`（占位图 `https://images.pexels.com/photos/1239291/pexels-photo-1239291.jpeg?auto=compress&cs=tinysrgb&w=80`）+ 用户名 + ChevronDown。下拉项：「个人中心」「退出登录」（LogOut 图标，点击调用 `logout()` 跳 `/login`）。
- **移动端（<768px）**：一级菜单收进汉堡按钮（Menu 图标）弹出的 `el-drawer`；侧栏也改为抽屉。

### 侧栏 Sider

- 宽 `--sider-w`，可收起为 `--sider-w-collapsed`（顶栏放一个折叠按钮 PanelLeft 图标控制 `sidebarCollapsed`）。
- `background: var(--bg-card)`，右侧 `1px solid var(--border)`。
- 用 **`el-menu`**（`:collapse="sidebarCollapsed"`，`router` 模式，`:default-active` 绑当前路由）渲染当前一级下的 children。
- 菜单项：lucide 图标 + 文字；选中项左侧有 3px 主色高亮条，选中背景 `--brand-soft`，文字 `--brand`。
- 收起态只显示图标，hover 出 tooltip。

### 内容区 Content

- `background: var(--bg-page)`，`padding: 20px`，`overflow-y: auto`，占满剩余高度。
- 顶部面包屑：`el-breadcrumb`，格式「一级标题 / 二级标题」，分隔符用 lucide `ChevronRight`。
- 面包屑下方即各页面内容。

---

## 四、页面 1：登录页（/login）

独立全屏布局，**不使用 AppLayout**。左右分栏（`md` 以下堆叠为单列，隐藏左侧品牌图）。

### 左侧品牌区（flex-[1.2]，md 以下隐藏）
- 背景：主色渐变 `linear-gradient(135deg, var(--brand) 0%, #6A8DFF 100%)`，可叠加一层低透明度的几何 SVG 光斑。
- 内容（白色文字，垂直居中，padding 48px）：Logo + 「Nebula Admin」大标题（`32px`）、一句副标语「高效、清晰的一站式后台管理平台」、底部 3 条卖点小字（配 lucide 图标：`Zap` 极速、`ShieldCheck` 安全、`BarChart3` 可视化）。

### 右侧表单区（flex-[1]，居中）
- 白底卡片，`max-width: 380px`，`padding: 40px 32px`，`border-radius: var(--radius-lg)`，`box-shadow: var(--shadow-card)`（md 以下无阴影、全宽）。
- 标题「欢迎回来 👋」（`24px`，`--text-primary`）+ 副标题「请登录你的账号」（`14px`，`--text-secondary`）。
- **表单（`el-form`，带校验）**：
  - 用户名：`el-input`，前缀 lucide `User` 图标，`placeholder="用户名 / 邮箱"`，规则：必填。
  - 密码：`el-input type="password" show-password`，前缀 `Lock` 图标，规则：必填、≥6 位。
  - 一行：`el-checkbox`「记住我」 + 右侧「忘记密码？」文字链接（主色）。
  - 登录按钮：`el-button type="primary"` 全宽、圆角 `--radius-sm`、高 44px，`loading` 状态；点击校验通过后模拟请求（`setTimeout` 800ms），写入 Pinia token 并跳 `/dashboard`。
  - 分割线「或」+ 两个第三方登录圆形按钮（占位，lucide 图标即可）。

---

## 五、列表页

标准 CRUD 列表页，结构：**搜索筛选区 → 工具栏 → 表格 → 分页**。全部包在白色卡片里（`--bg-card`，`--radius-lg`，`--shadow-card`，`padding: 20px`）。

---

## 六、响应式断点

统一断点（scoped CSS 媒体查询）：
- `>=1200px` 桌面：顶栏一级菜单全展开、侧栏默认展开、图表多列。
- `768~1199px` 平板：侧栏默认收起为图标、KPI 2 列。
- `<768px` 移动：顶栏一级菜单收进汉堡抽屉，侧栏用 `el-drawer`，所有网格单列，登录页单列。

---