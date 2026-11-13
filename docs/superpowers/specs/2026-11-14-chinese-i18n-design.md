# Coolify 中文汉化方案（fork 自用）— 设计文档

日期：2026-11-14
状态：待批准
作者：橙先生 与 ZCode 协作产出

## 一、意图与背景

Coolify 官方不支持界面 i18n（`lang/*.json` 仅 48 个登录相关 key，上游明确无计划）。本 fork（`tktogether/coolify`）供个人自托管使用，目标：

1. **整站中文界面**——个人使用，不需要按用户切换语言。
2. **上游合并零负担**——官方发版频繁（当前已到 v4.7.5），fork 必须能持续、低成本地合并上游并让"官方自动升级"机制照常工作。

社区已有两种路线的实证，均不走：模板全量 `__()` 化的 fork（`Isllanrx/coolify_br`）已落后上游 474 个提交；linux.do 分享的汉化版已落后约 4600 个提交。**结论：绝不批量改动上游模板文件。**

## 二、目标 / 非目标 / 反目标

### 目标
- 站内所有可见 UI 文本为中文；词典未命中的条目自然回退显示英文原文（模板原文即英文，不会出现原始 key）。
- 对上游的改动面收敛为：**全部新增文件 + 一个单行注册点**（Kernel.php），合并冲突预期为零或单行。
- 官方一键安装与自动升级链路原样可用，只是镜像源指向 fork 构建的镜像。
- Livewire 局部刷新（morph）后的内容同样为中文，无英文闪现。
- 敏感内容不翻译：终端输出、日志、代码块、环境变量值、Git 地址、镜像名、命令等。

### 非目标
- 不做语言切换 UI、不做多语言框架（仅中文）。
- 不修改任何 Blade 模板、不迁移到 Laravel 翻译函数。
- 邮件模板（44 个）汉化不列入 v1，机制上预留（同一替换器可挂到邮件渲染钩子）。
- 不给上游提交 i18n 相关 PR。
- API JSON 数据不做翻译（仅 UI 呈现层）。

### 反目标（任何出现即方案失败）
- 批量修改上游 Blade/PHP 文案文件。
- 需要手工干预才能完成的"上游发版 → 实例升级"链路。
- 页面先闪现英文再变中文的渲染方式。

## 三、约束

- Laravel 12（Laravel 10 文件结构）：中间件注册在 `app/Http/Kernel.php`。
- Livewire 3：初始页面为服务端渲染 HTML；增量更新经 `/livewire/update` 返回 JSON，其中每个组件含 `snapshot`（数据）与 HTML 片段字段。**替换必须只作用于 HTML 片段，绝不触碰 snapshot。**
- 官方升级链路（已核实源码）：
  - `scripts/install.sh:283` 接受 `REGISTRY_URL` 环境变量（或读已有 `.env`）；
  - `scripts/upgrade.sh:9-13` 自动升级同样从 `.env` 读 `REGISTRY_URL` 并用其 `docker pull`；
  - `docker-compose.prod.yml:8` 镜像地址为 `${REGISTRY_URL}/coollabsio/coolify:${版本}`；
  - `scripts/upgrade.sh:296` helper 镜像也从同一 `${REGISTRY_URL}` 命名空间拉取（`${REGISTRY_URL}/coollabsio/coolify-helper`）——因此 fork 侧需镜像一份官方 helper。
  - `REGISTRY_URL` 的语义是 **registry/命名空间**（如 `ghcr.io/tktogether`），不是完整仓库路径。
- GHCR 推送凭据由 GitHub Actions 自动提供（fork 仓库自带 `GITHUB_TOKEN`），无需额外账号。

## 四、备选方案与取舍（已与使用者共同决定）

### 机制层
| 方案 | 说明 | 结论 |
|---|---|---|
| A. 服务端运行时替换 | 响应中间件在 HTML 发往浏览器前做词典替换；Livewire JSON 中的 HTML 片段同样处理 | **选定** |
| B. 客户端 JS 替换 | 词典 + MutationObserver | 否：Livewire 高频刷新导致英文闪现、属性需单独处理、大 DOM 性能开销 |
| C. 模板 `__()` 化 | 传统 i18n | 否：数百文件改动的冲突面；社区两个 fork 分别落后上游 474 / 约 4600 提交，实证不可持续 |

### 部署层
| 方案 | 说明 | 结论 |
|---|---|---|
| 甲. fork 镜像流水线 | fork CI 构建镜像 → 实例 `.env` 设 `REGISTRY_URL` 指向 fork → 官方自动升级照常工作 | **选定** |
| 乙. 官方镜像 + bind-mount 覆盖 | 把中间件/词典挂载进官方容器 | 否：升级会重写 `/data/coolify/source/` 下的 compose 文件，挂载易被冲掉 |
| 丙. 浏览器油猴脚本 | 纯客户端 | 仅作流水线建成前的过渡方案（社区已有 MIT 许可的现成脚本） |

## 五、设计

### 5.1 机制层：服务端运行时替换

**词典**

- 目录 `resources/localization/zh-cn/`（全新目录，刻意不放 `lang/` 下，避免与 Laravel 自带翻译系统互相干扰）。
- 按模块拆分为多个 PHP 数组文件：`common.php`（通用按钮/状态/时间词）、`nav.php`、`servers.php`、`applications.php`、`databases.php`、`services.php`、`settings.php`、`security.php`、`teams.php`、`notifications.php` 等，每文件返回 `'英文原文' => '中文'` 的扁平数组。
- 条目格式铁律：**英文原文全文作为键**（含精确大小写与标点），避免单词级键；缺失回退即英文原文，永无原始 key 风险。
- 由 `app/Services/LocalizeDictionary.php`（新文件）一次性合并加载为单数组，容器单例缓存；`strtr`（数组形态）内部自动最长键优先。OPcache 下每请求成本可忽略。

**中间件 `app/Http/Middleware/LocalizeResponse.php`（新文件）**

- 挂载：`app/Http/Kernel.php` 的 `web` 组**末尾追加一行**（本方案对上游跟踪文件的唯一改动；上游改动该数组时合并冲突为单行取双方，可接受）。
- 生效条件：`Content-Type` 为 `text/html` 的响应；`LOCALIZE_OFF=true` 时整体跳过（调试对照用）。
- **HTML 感知替换，不做整段裸 `strtr`**：
  - 跳过 `<script>`、`<style>`、`<pre>`、`<code>`、`<textarea>` 标签内的内容；
  - 仅替换文本节点与白名单属性：`title`、`placeholder`、`aria-label`；
  - 其余属性（含 `wire:snapshot`、`x-data` 等 JSON 属性）一律不动——保护用户数据（如应用名恰好叫 "Server"）不被误翻与 Livewire 状态污染。
- Livewire 增量更新响应（`application/json`）：解析 JSON，仅对每组件的 HTML 片段字段（`components[*].effects.html`，以真实响应抓包为准）内的 HTML 应用同一替换器，`snapshot` 字段原样。处理过程防御式：结构不符合预期则原样放行。
- 操作反馈（toast）路径（已拍板纳入 v1）：`effects.dispatches[*].params` 中的纯字符串元素按**完整串精确匹配**词典后替换，不做子串扫描。全项目约 168 处组件经 dispatch() 发送操作反馈（保存/部署等点击反馈由此覆盖）；词典键为长短语，与用户数据全串碰撞概率趋近于零。
- 实例 `.env` 中设 `LOCALIZE_OFF=true` 可整体关闭（`.env.production` 每次升级被 CDN 覆盖，勿用）。
- 资产请求（CSS/JS/图片）、下载、终端 WebSocket、API 均不在替换路径上。

**实现要点（给 builder 的硬性要求）**

- HTML 分段建议轻量正则分段而非 PHP DOM 解析（DOM 对大页面开销大；本 UI 有超大表格页）。
- 替换只做一遍 `strtr` 于文本片段，禁止逐词条正则（性能）。
- 中间件不得缓冲化修改响应头；流式响应（如有）直接放行不处理。

### 5.2 部署层：fork 镜像流水线

**CI workflows（均为新增文件，零冲突）**

- 构建 workflow `.github/workflows/build-cn-image.yml`：触发 = `release.json` 版本字段变化（push 到 main 检测）或 tag push（`v*`），取其一，builder 按仓库现有 workflow 风格决定。
- 定时合并 workflow（如 `.github/workflows/sync-upstream.yml`，已拍板为必做项，随 Phase 3 交付）：每小时运行，检测上游 `coollabsio/coolify` main 是否领先 fork → 领先则自动 `git merge` 并 push（预期零冲突）→ push 触发构建 workflow。合并冲突时（按设计预期几乎不会发生）workflow 终止并在 GitHub 提醒人工处理，绝不静默跳过。
- 构建：复用仓库现有 Dockerfile，构建并推送：
  - `ghcr.io/tktogether/coollabsio/coolify:<版本>`（fork 应用镜像）
  - `ghcr.io/tktogether/coollabsio/coolify-helper:<upstream.json 中的 helper 版本>`（从官方镜像 re-tag 推送一份；helper 版本很少变化，可在 workflow 内先 pull 官方再 push）
- **GHCR 包可见性（必办）**：Actions 推送的包默认私有，首次推送后须在 GitHub 包页面将两个包手动设为 Public，否则实例匿名拉取会持续失败（官方安装/升级脚本从不 docker login）。
- 架构与渠道（已拍板）：**仅 amd64**；实例更新渠道为 **stable**——fork CI 仅在上游发布稳定版本时构建一次，镜像 tag 与上游版本号完全一致，不处理 preview/nightly。

**实例侧**

- 新实例一键安装（官方脚本原生支持）：
  `curl -fsSL https://cdn.coollabs.io/coolify/install.sh | REGISTRY_URL=ghcr.io/tktogether bash`
- 已有实例：见下方「存量实例快速切换」——`.env` 写入后可立即以同版本 fork 镜像重建容器（不依赖上游发版），此后所有升级均从 fork 源拉取。

**存量实例快速切换（零重装）**

前提：fork CI 已发布与当前运行版本号一致的镜像。全程卷与数据不动，无迁移风险：

```bash
echo "REGISTRY_URL=ghcr.io/tktogether" >> /data/coolify/source/.env
cd /data/coolify/source && docker compose pull && docker compose up -d   # 立即以同版本 fork 镜像重建
# 或不执行重建，等上游发新版后由自动升级自然拉取 fork 镜像
```

依据（均已核实）：`docker-compose.yml` 镜像地址由 `${REGISTRY_URL}` 插值；`upgrade.sh:255` 每次升级将 `REGISTRY_URL` 持久化回 `.env`。回滚 = 删除该行后 `docker compose up -d`。不修改官方 `upgrade.sh`（其头部禁止改动），`.env` 即其正规配置通道。

**README（使用者明确要求：新 md 作为仓库首页）**

GitHub 无「钉选任意 md 为首页」功能（首页恒渲染根目录 `README.md`）。采用**改名互换**：

- `git mv README.md README.upstream.md`——上游原文保留；上游后续对 README.md 的修改经 git rename 跟踪自动落在 `README.upstream.md`，预期零冲突；上游自行删除/重命名 README.md 时（极罕见）机械解决。
- 新建 `README.md` 为 fork 首页说明（中文）：个人汉化 fork 定位、一键安装命令（含 `REGISTRY_URL`）、存量实例切换与回滚、更新渠道说明、指向设计文档。
- 仓库 About 描述改中文，Website 链接指向 `README.upstream.md` 便于回溯上游说明。

**跟版时序**

- 上游发版 → 定时合并 workflow 自动合并（每小时检查）→ CI 自动出镜像 → 实例自动升级拉到新版。全链路无人值守，汉化版最多落后上游约 1~2 小时。
- 窗口期内实例升级任务会 pull 失败并自动重试（官方行为，实例照常运行），无害。

### 5.3 词典生产流程

1. **种子**：Greasy Fork 油猴脚本（MIT 许可，[脚本 580682](https://greasyfork.org/en/scripts/580682)，84.5 KB 词典，覆盖 v4 主要模块）转换导入；转换产物文件头部保留原脚本版权与 MIT 许可声明；全量导入作为种子，后续用抽取工具对照清理；其排除区域清单（终端/日志/代码块/环境变量值/Git 地址/镜像名）已吸收进 5.1 设计。
2. **抽取工具**（一次性工具，`scripts/` 下新文件）：扫描 Blade 模板的静态文本节点与白名单属性，输出候选英文短语清单，供翻译与覆盖率对照。工具不进构建流程。
3. **翻译**：LLM 批量翻译 + 人工校对；日常使用中发现漏网之鱼随手补词典文件。
4. **覆盖推进**：Phase 2 先覆盖高频运维页面（导航、仪表盘、服务器、应用、数据库、服务、设置），其余随使用逐步补全。

## 六、测试

- **单元/特性测试**（新增 `tests/Feature/Localize*` 系列）：
  - HTML 文本节点替换命中、词典 miss 回退英文原文；
  - `<script>`/`<pre>`/`<code>`/`<textarea>` 内容不替换；
  - 白名单属性替换，非白名单属性（含 `wire:snapshot`）不替换；
  - Livewire JSON：`html` 片段替换、`snapshot` 字段逐字节不变、结构异常时原样放行；
  - dispatches（toast）：纯字符串消息参数精确命中替换，非字符串参数与未命中串一律不动；
  - `LOCALIZE_OFF=true` 时响应与上游行为一致。
- **部署冒烟**：CI workflow 语法校验 + 本地 `docker build` 一次成功；真实实例执行一次"检查更新 → 升级"确认从 fork 源拉取。

## 七、风险与缓解

| 风险 | 缓解 |
|---|---|
| 用户数据恰含词典键（如应用名 "Deploy"） | snapshot 永不处理；白名单属性只含 title/placeholder/aria-label；键为长短语。残余风险为外观级 |
| Livewire 升级改变响应结构 | JSON 处理防御式；合并后跑本地化测试组 |
| 上游重排 Kernel.php | 单行冲突，合并取双方即可 |
| 词典条目过期（上游改文案） | 无害（miss 回退英文），抽取工具可对照清理 |
| 性能 | 单遍 strtr + OPcache；个人实例规模可忽略 |

## 八、分期

1. **Phase 1 机制**：词典服务 + 中间件 + Kernel 注册 + 全部测试。
2. **Phase 2 词典**：油猴词典转换导入 + Blade 抽取工具 + 高频页面覆盖。
3. **Phase 3 部署**：构建 workflow + 定时合并 workflow + README 改名互换与首页说明 + 实例 `.env` 与存量切换 + 一次真实自动升级验证。
4. **Phase 4（可选）**：邮件渲染钩子复用替换器。

## 九、留给 builder 的决定项

文档已明确意图、边界与硬性要求；以下属实现自由度：
- HTML 分段器的具体实现（建议正则分段）与属性白名单的最终微调；
- 词典文件拆分粒度的最终命名；
- CI 构建的触发方式（release.json 检测 vs tag push）；定时合并 workflow 的检查频率（默认每小时）。
