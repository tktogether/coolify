/*
 * Coolify 中文汉化客户端配套脚本（个人 fork）。
 *
 * 服务端替换器无法触及 Alpine x-text 从 Livewire 快照数据渲染的文本；
 * 本脚本对这类节点做「整段精确匹配」翻译。安全边界：
 *  - 白名单只含固定 UI 枚举词（状态、设置页开关标题等），不含泛词；
 *  - 只匹配整段（trim 后全等）文本节点，零子串误伤；
 *  - 只写 textContent，不使用 innerHTML，无注入面；
 *  - 服务端已翻译的中文文本不会命中英文白名单。
 * 由 LocalizeResponse 中间件在启用汉化时注入；LOCALIZE_OFF=true 时不注入。
 */
(function () {
    'use strict';

    if (document.documentElement.dataset.localizeClient === '1') {
        return;
    }
    document.documentElement.dataset.localizeClient = '1';

    var map = {
        // 状态枚举
        'Running': '运行中',
        'Stopped': '已停止',
        'Starting': '启动中',
        'Stopping': '停止中',
        'Restarting': '重启中',
        'Deploying': '部署中',
        'Queued': '排队中',
        'Failed': '失败',
        'Success': '成功',
        'Pending': '等待中',
        'Exited': '已退出',
        'Created': '已创建',
        'Healthy': '健康',
        'Unhealthy': '不健康',
        'Degraded': '降级',
        'Paused': '已暂停',
        'Cancelled': '已取消',
        'Finished': '已完成',
        'Completed': '已完成',
        'In progress': '进行中',
        'Ready': '就绪',
        'Unreachable': '不可达',
        'Offline': '离线',
        'Online': '在线',
        'Unavailable': '不可用',
        'Expired': '已过期',
        'Ignored': '已忽略',
        'Reachable': '可达',
        'In sync': '已同步',
        'Not configured': '未配置',
        'Not used': '未使用',
        'Not usable': '不可用',
        'Not checked': '未检查',
        'Not ready': '未就绪',
        'Connected': '已连接',
        'Setup incomplete': '设置未完成',
        'Check needed': '需要检查',
        'Running (healthy)': '运行中（健康）',
        'Running (unknown)': '运行中（未知）',
        'Running (unhealthy)': '运行中（不健康）',
        'Running (degraded)': '运行中（降级）',
        // 部署画布（Svelte 客户端渲染，服务端替换触达不到）
        'Add resource': '添加资源',
        'Add the next step': '添加下一步',
        'Click to remove this connection': '点击移除此连接',
        'Connect it after a source step.': '请将其接在源步骤之后。',
        'Could not load the canvas': '无法加载画布',
        'Drag and drop to add a step': '拖放以添加步骤',
        'Drag one onto the canvas': '将其中一个拖到画布上',
        'Every resource you connect to a Server shows here.': '你连接到服务器的每个资源都会显示在这里。',
        'New resource page': '新建资源页面',
        'Open in Coolify': '在 Coolify 中打开',
        'Remove link': '移除连接',
        'Try again': '重试',
        // 设置页 x-text 开关与显示值
        'Registration disabled': '注册已禁用',
        'Anyone can register': '任何人可注册',
        'Allow password registration': '允许密码注册',
        'Disable when OAuth is enabled': '启用 OAuth 时关闭',
        'Require two-step confirmation': '需要两步确认',
        'Skip two-step confirmation': '跳过两步确认',
        'SPA navigation': 'SPA 导航',
        'Full page navigation': '整页导航',
        'Local storage': '本地存储',
        'Stream through Coolify': '经 Coolify 流式传输',
        'Pull request previews': '拉取请求预览',
        'Destructive action confirmation': '危险操作确认',
        'Validate Connection & Continue': '验证连接并继续',
        'Required Cloudflare permissions': '所需的 Cloudflare 权限',
        'Deployment server': '部署服务器',
        'Managed by Coolify (auto-generated)': '由 Coolify 托管（自动生成）',
        'Escape special characters ($ becomes $$)': '转义特殊字符（$ 变为 $$）',
        'None': '无',
        'Validate and add': '验证并添加',
        'Token name': '令牌名称',
        'Script name': '脚本名称',
        'Script content': '脚本内容',
        'Bot API token': 'Bot API 令牌',
        'Chat ID': '聊天 ID',
        'Topic ID': '话题 ID',
        'User key': '用户密钥',
        'API token': 'API 令牌',
        'Email service': '邮件服务',
        'Use team email settings': '使用团队邮件设置',
        'Use system-wide settings': '使用实例级设置',
        'Server disk usage': '服务器磁盘使用',
        'INFRASTRUCTURE': '基础设施',
        'MANAGE': '管理',
        'Recent deployments': '最近部署',
        'Usage': '用量',
        'Last 24 hours': '最近 24 小时',
        'Last 5 minutes': '最近 5 分钟',
        'Last 7 days': '最近 7 天',
        'Last 30 days': '最近 30 天',
        'All resources': '所有资源',
        'Table view': '表格视图',
        'Developer View': '开发者视图',
        'Developer view': '开发者视图',
        'Admin View': '管理员视图',
        'More information': '更多信息',
        'Unique visitors': '独立访客',
        'p95 latency': 'p95 延迟',
        'Referrers': '来源站点',
        'Browsers': '浏览器',
        'Operating systems': '操作系统',
        'Top IPs': '热门 IP',
        'Top user agents': '热门 User-Agent',
        'Top cache statuses': '热门缓存状态',
        'Top status codes': '热门状态码',
        'Time range': '时间范围',
        'Build cache': '构建缓存',
        'Build arguments': '构建参数',
        'Auto deploy': '自动部署',
        'Clone submodules': '克隆子模块',
        'Git LFS': 'Git LFS',
        'Clone depth': '克隆深度',
        'Container naming': '容器命名',
        'Log drain': '日志外发',
        'GPU access': 'GPU 访问',
        'Gzip compression': 'Gzip 压缩',
        'Path prefixes': '路径前缀',
        'Strip prefixes': '剥离前缀',
        'Certificate resolver': '证书解析器',
        'Build secrets': '构建机密',
        'Standard build arguments': '标准构建参数',
        'Include Coolify default variables': '包含 Coolify 默认变量',
        'Environment values in logs': '日志中的环境变量值',
        'Hide all values': '隐藏全部值',
        'Value type': '值类型',
        'Single line': '单行',
        'Interpolation': '插值',
        'Build time': '构建期',
        'Runtime': '运行时',
        'All environments': '所有环境',
        'Default order': '默认顺序',
        'Comment': '注释',
        'Lock': '锁定',
        'Octal mode': '八进制权限',
        'File name': '文件名',
        'Folder name': '文件夹名',
        'File actions': '文件操作',
        'Command': '命令',
        'Retries': '重试次数',
        'Scheme': '协议',
        'Time': '时间',
        'Text view': '文本视图',
        'Stream Logs': '实时日志',
        'Content': '内容',
        'Live': '实时',
        'Steps': '步骤',
        'Choose project': '选择项目',
        'Save only': '仅保存',
        'View only': '只读视图',
        'Show IP': '显示 IP',
        'Show IP address': '显示 IP 地址',
        'Other resources': '其他资源',
        'Operations': '操作',
        'Details': '详情',
        'Manage': '管理',
        'Include': '包含',
        'Star': '收藏',
        'Mem': '内存',
        'API': 'API',
        'Manual': '手动',
        'Close': '关闭',
        'Dismiss': '忽略',
        'Refresh': '刷新',
        'Reload': '重新加载',
        'Back': '返回',
        'Continue': '继续',
        'Save': '保存',
        'Delete': '删除',
        'Cancel': '取消',
        'Edit': '编辑',
        'Update': '更新',
        'Remove': '移除',
        'Add': '添加',
        'Test': '测试',
        'Open': '打开',
        'Visit': '访问',
        'Copy': '复制',
        'Download': '下载',
        'Upload': '上传',
        'Restore': '恢复',
        'Deploy': '部署',
        'Restart': '重启',
        'Stop': '停止',
        'Start': '启动',
        'Rebuild': '重建',
        'Rollback': '回滚',
        'Redeploy': '重新部署',
        'Enable': '启用',
        'Disable': '停用',
        'Yes': '是',
        'No': '否',
        'All': '全部',
        'Default': '默认',
        'Temporary': '临时',
        'Permanent': '永久',
        'Stable': '稳定版',
        'Preview': '预览版',
        'Nightly': '每日构建',
        'High': '高',
        'Medium': '中',
        'Low': '低',
        'Unknown': '未知',

        // 下拉选项（listbox 用 x-text 渲染，服务端触达不到）
        'AI agents & bots': 'AI 代理与机器人',
        'API Tokens': 'API 令牌',
        'Access': '访问',
        'Actions': '操作',
        'Add network containers as hosts': '把网络容器加入 hosts',
        'Add suffix': '添加后缀',
        'Admin': '管理员',
        'Advanced': '高级',
        'All databases': '所有数据库',
        'All resources in the team': '团队内全部资源',
        'All roles': '所有角色',
        'All servers': '全部服务器',
        'All sources': '所有源',
        'All tags': '所有标签',
        'All targets': '全部目标',
        'All team resources': '所有团队资源',
        'All users': '所有用户',
        'Allowed': '已允许',
        'Alphabetical': '按字母',
        'Also build applications': '也构建应用',
        'Analytics': '分析',
        'Anonymized (/24 IPv4, /48 IPv6)': '匿名化（/24 IPv4、/48 IPv6）',
        'Appearance': '外观',
        'Application / service': '应用 / 服务',
        'Application details': '应用详情',
        'Audit log': '审计日志',
        'Authentication': '身份验证',
        'Available build server (auto-select)': '可用构建服务器（自动选择）',
        'Available during build': '构建期可用',
        'Available in the container': '容器内可用',
        'Backup': '备份',
        'Backup contains all databases': '备份包含所有数据库',
        'Backup contains one database': '备份包含单个数据库',
        'Backup failure': '备份失败',
        'Backup success': '备份成功',
        'Backups': '备份',
        'Balanced (50%)': '均衡（50%）',
        'Bandwidth': '带宽',
        'Blocked': '已阻止',
        'Build & deploy': '构建与部署',
        'Build on the deployment server': '在部署服务器上构建',
        'Builds only': '仅构建',
        'CA Certificate': 'CA 证书',
        'Cancel when a newer push arrives': '新推送到达时取消',
        'Centered': '居中',
        'Clone resource': '克隆资源',
        'Cloud Token': '云令牌',
        'Cloud Tokens': '云服务令牌',
        'Cloud-Init Scripts': 'Cloud-Init 脚本',
        'Cloudflare': 'Cloudflare',
        'Cloudflare Tunnel': 'Cloudflare Tunnel',
        'Codeberg (Forgejo)': 'Codeberg（Forgejo）',
        'Compose': 'Compose',
        'Configuration': '配置',
        'Connect to predefined network': '连接到预定义网络',
        'Connect to the predefined Coolify network': '连接到 Coolify 预定义网络',
        'Consistent name (no rolling updates)': '固定名称（无滚动更新）',
        'Container': '容器',
        'Container command': '容器命令',
        'Containers': '容器',
        'Copy name': '复制名称',
        'Copy to clipboard': '复制到剪贴板',
        'Copy value': '复制值',
        'Create GitHub deployments': '创建 GitHub 部署',
        'Creation order': '按创建顺序',
        'Current team': '当前团队',
        'Custom': '自定义',
        'Custom image': '自定义镜像',
        'Custom resolver': '自定义解析器',
        'Danger Zone': '危险操作区',
        'Dark': '深色',
        'Dashboard': '仪表盘',
        'Database': '数据库',
        'Database details': '数据库详情',
        'Dedicated to runners': '专供运行器',
        'Delete after S3 upload': 'S3 上传后删除',
        'Delete all old images': '删除所有旧镜像',
        'Delete unused networks': '删除未使用的网络',
        'Delete unused volumes': '删除未使用的卷',
        'Deploy every push': '每次推送都部署',
        'Deploy on push (webhooks)': '推送时部署（Webhook）',
        'Deploy webhook': '部署 Webhook',
        'Deployment Logs': '部署日志',
        'Deployment failure': '部署失败',
        'Deployment success': '部署成功',
        'Deployments': '部署',
        'Deployments and builds': '部署与构建',
        'Deployments only': '仅部署',
        'Destinations': '目标',
        'Detect automatically': '自动检测',
        'Developer': '开发者',
        'DigitalOcean': 'DigitalOcean',
        'Disabled': '已停用',
        'Disabled for this team': '此团队已禁用',
        'Discord': 'Discord',
        'Disk usage warning': '磁盘占用告警',
        'Do not add (preserves cache)': '不添加（保留缓存）',
        'Do not create GitHub deployments': '不创建 GitHub 部署',
        'Do not drain logs': '不外发日志',
        'Do not forward logs': '不转发日志',
        'Do not mention': '不提及',
        'Do not run workflow jobs': '不运行工作流任务',
        'Do not update pull requests': '不更新拉取请求',
        'Docker (privileged, full host access)': 'Docker（特权，完全宿主访问）',
        'Docker BuildKit secrets': 'Docker BuildKit 机密',
        'Docker Cleanup': 'Docker 清理',
        'Docker Compose': 'Docker Compose',
        'Docker Hub': 'Docker Hub',
        'Docker cleanup failure': 'Docker 清理失败',
        'Docker cleanup success': 'Docker 清理成功',
        'Dockerfile': 'Dockerfile',
        'Domains': '域名',
        'Dynamic Configurations': '动态配置',
        'Email': '邮箱',
        'Email A–Z': '邮件 A–Z',
        'Email Z–A': '邮件 Z–A',
        'Enable debug logging': '启用调试日志',
        'Enabled': '已启用',
        'Enabled for this team': '此团队已启用',
        'Environment Variables': '环境变量',
        'Environments': '环境',
        'Every team on this instance': '此实例上的所有团队',
        'Exclude from status': '从状态中排除',
        'Executions': '执行记录',
        'Fail the deployment': '使部署失败',
        'Files': '文件',
        'Flat': '扁平',
        'Floating': '悬浮',
        'Forward logs to the server drain': '将日志转发到服务器的外发端点',
        'Full': '完整',
        'Full access': '完全访问权限',
        'Full history': '完整历史',
        'Full width': '全宽',
        'GET': 'GET',
        'General': '常规',
        'Generated name (rolling updates)': '生成名称（滚动更新）',
        'Git Source': 'Git 代码源',
        'Git source': 'Git 来源',
        'GitHub Container Registry': 'GitHub 镜像仓库',
        'GitHub Runners': 'GitHub 运行器',
        'HEAD': 'HEAD',
        'HTTP Basic Authentication': 'HTTP 基础认证',
        'HTTP request': 'HTTP 请求',
        'HashiCorp Vault': 'HashiCorp Vault',
        'Healthcheck': '健康检查',
        'Hide locked values only': '仅隐藏已锁定的值',
        'High (75%)': '高（75%）',
        'Host': '主机',
        'Hostinger': 'Hostinger',
        'Image Scanning': '镜像扫描',
        'Image vulnerabilities': '镜像漏洞',
        'Images': '镜像',
        'Include in status': '包含在状态中',
        'Indexable': '可索引',
        'Initialization scripts': '初始化脚本',
        'Inject build args automatically': '自动注入构建参数',
        'Integration Tokens': '集成令牌',
        'Integrations': '集成',
        'Interpolate $VARIABLES': '插值 $VARIABLES',
        'Isolated Docker (Sysbox)': '隔离 Docker（Sysbox）',
        'Isolated network only': '仅隔离网络',
        'Keep containers running': '保持容器运行',
        'Keep current': '保持当前',
        'Keep local backup': '保留本地备份',
        'Keep paths as-is': '保持路径原样',
        'Keep prefixes': '保留前缀',
        'Keep retained images': '保留已建镜像',
        'Keep unescaped (allow env variables)': '保持不转义（允许环境变量）',
        'Keep unused networks': '保留未使用的网络',
        'Keep unused volumes': '保留未使用的卷',
        'Keys & Tokens': '密钥与令牌',
        'Labels for all supported proxies': '为所有受支持代理生成标签',
        'Labels for the active proxy only': '仅为活动代理生成标签',
        'Last 10 minutes': '最近 10 分钟',
        'Last 12 hours': '最近 12 小时',
        'Last 30 minutes': '最近 30 分钟',
        'Last hour': '最近 1 小时',
        'Last week': '最近一周',
        'Let the build finish': '等构建完成',
        'Light': '浅色',
        'Literal (keep $ characters as-is)': '字面量（保留 $ 原样）',
        'Loading': '加载中',
        'Log Drains': '日志外发',
        'Logs': '日志',
        'Low (25%)': '低（25%）',
        'Maintenance Mode': '维护模式',
        'Managed by Coolify': '由 Coolify 托管',
        'Managed manually (edit labels yourself)': '手动管理（自行编辑标签）',
        'Managed manually in Dockerfile': '由 Dockerfile 手动管理',
        'Manager and worker nodes': '管理节点与工作节点',
        'Manual Git webhooks': '手动 Git Webhook',
        'Manual deployments only': '仅手动部署',
        'Manual preview': '手动预览',
        'Maximum (100%)': '最大（100%）',
        'Member': '成员',
        'Members': '成员',
        'Memory used': '已用内存',
        'Mention @here': '@此处',
        'Metrics': '指标',
        'Monitoring': '监控',
        'Move resource': '移动资源',
        'Multiline': '多行',
        'My team': '我的团队',
        'Name': '名称',
        'Name A–Z': '名称 A–Z',
        'Name Z–A': '名称 Z–A',
        'Network': '网络',
        'Never': '从未',
        'Nixpacks (deprecated)': 'Nixpacks（已弃用）',
        'No build servers connected': '未连接构建服务器',
        'No redirect': '不重定向',
        'Noindex': '禁止索引',
        'None (use your own certificates)': '无（使用你自己的证书）',
        'Not a Swarm manager': '不是 Swarm 管理节点',
        'Not a Swarm worker': '不是 Swarm 工作节点',
        'Not available during build': '构建期不可用',
        'Not available in the container': '容器内不可用',
        'Notifications': '通知',
        'OPTIONS': 'OPTIONS',
        'Only above disk threshold': '仅在超过磁盘阈值时',
        'Only this team': '仅此团队',
        'Operator': '运维',
        'Optional for members': '成员可选',
        'Outside team': '团队之外',
        'Overview': '概览',
        'Owner': '所有者',
        'POST': 'POST',
        'Password': '密码',
        'Patch check failed': '补丁检查失败',
        'Patching': '补丁更新',
        'Path': '路径',
        'Permissions': '权限',
        'Persistent Storage': '持久化存储',
        'Preview Deployments': '预览部署',
        'Pricing': '价格',
        'Private': '私有',
        'Private Key': '私钥',
        'Private Keys': '私钥',
        'Private key': '私钥',
        'Projects': '项目',
        'Proxy': '代理',
        'Public (fork PRs allowed)': '公开（允许 fork PR）',
        'Pushover': 'Pushover',
        'Railpack': 'Railpack',
        'Raw (deploy file as-is)': '原始（按原样部署文件）',
        'Read and update pull requests': '读取并更新拉取请求',
        'Reboot required': '需要重启',
        'Rebuild from scratch every time': '每次都从头构建',
        'Redirect to non-www': '重定向到非 www',
        'Redirect to www': '重定向到 www',
        'Refuse pull request jobs': '拒绝拉取请求任务',
        'Refuse to connect (enforce)': '拒绝连接（强制）',
        'Registries': '镜像仓库',
        'Repository members only': '仅仓库成员',
        'Requests': '请求数',
        'Required for all members': '所有成员必须',
        'Resource Limits': '资源限制',
        'Resource Operations': '资源操作',
        'Resource status changes': '资源状态变化',
        'Resources': '资源',
        'Restart limit reached': '达到重启上限',
        'Return the default 503 response': '返回默认的 503 响应',
        'Role': '角色',
        'Run on every schedule': '在每次计划时运行',
        'Run pull request jobs': '运行拉取请求任务',
        'Run workflow jobs on your servers': '在你的服务器上运行工作流任务',
        'Runtime Logs': '运行日志',
        'Runtime only (preserves cache)': '仅运行时（保留缓存）',
        'S3 storages': 'S3 存储',
        'SPA (single-page application)': 'SPA（单页应用）',
        'Scheduled Tasks': '定时任务',
        'Scheduled task failure': '计划任务失败',
        'Scheduled task success': '计划任务成功',
        'Security': '安全',
        'Security: security update': '安全：安全更新',
        'Security: update now': '安全：立即更新',
        'Select a private key': '选择私钥',
        'Self-hosted Gitea or Forgejo': '自托管 Gitea 或 Forgejo',
        'Send logs to drain': '将日志发送到外发端点',
        'Send logs to the log drain endpoint': '把日志发送到日志外发端点',
        'Sentinel': 'Sentinel',
        'Server': '服务器',
        'Server patching': '服务器补丁',
        'Server reachable': '服务器可达',
        'Server setting (': '服务器设置（',
        'Server unreachable': '服务器不可达',
        'Servers': '服务器',
        'Settings': '设置',
        'Shallow clone (latest commit only)': '浅克隆（仅最新提交）',
        'Share path': '共享路径',
        'Share volume': '共享卷',
        'Shared Variables': '共享变量',
        'Sign in with a passkey': '使用通行密钥登录',
        'Skip submodules': '跳过子模块',
        'Slack': 'Slack',
        'Someone else': '其他人',
        'Sources': '来源',
        'Specific databases': '指定数据库',
        'Standard logging': '标准日志',
        'Start with an empty script': '从空脚本开始',
        'StartTLS': 'StartTLS',
        'Static': '静态',
        'Static site': '静态站点',
        'Stop containers during archive': '归档期间停止容器',
        'Stop the build when a newer push arrives': '新推送到达时停止构建',
        'Storage': '存储',
        'Subscription': '订阅',
        'Swarm manager': 'Swarm 管理节点',
        'Swarm worker': 'Swarm 工作节点',
        'System': '系统',
        'TLS / SSL': 'TLS / SSL',
        'TLS Certificates': 'TLS 证书',
        'Tags': '标签',
        'Team': '团队',
        'Telegram': 'Telegram',
        'Terminal': '终端',
        'This resource': '此资源',
        'Top HTTP versions': '热门 HTTP 版本',
        'Traefik proxy outdated': 'Traefik 代理过时',
        'URL template': 'URL 模板',
        'Up to date': '已是最新',
        'Updates': '更新',
        'Use': '使用',
        'Use Docker build cache': '使用 Docker 构建缓存',
        'Use a custom endpoint': '使用自定义端点',
        'Use an instance endpoint': '使用实例端点',
        'Use custom request handling': '使用自定义请求处理',
        'Use hosted email service': '使用托管邮件服务',
        'Use the stack network only': '仅使用栈网络',
        'Viewer': '查看者',
        'Vulnerabilities': '漏洞',
        'Warn and connect': '警告并连接',
        'Web UI': 'Web 界面',
        'Web application': 'Web 应用',
        'Webhook': 'Webhook',
        'Webhooks': 'Webhook',
        'Worker nodes only': '仅工作节点',
        'Your time': '你的时区时间',
        'Your time and UTC': '你的时区时间与 UTC',
        'No Docker (isolated, no container actions)': '无 Docker（隔离，无容器动作）',
        'Plan': '方案',
        'Transfer': '迁移',
        'Off': '关闭',
        'Dynamic': '动态',
        'Error %': '错误率 %',
        '30 days': '30 天',
        '90 days': '90 天',
        'Selected projects, environments, or resources': '所选项目、环境或资源',
    };

    // 紧凑相对时间：2h ago / 3d ago / 5m ago …
    [['m', '分钟前', 1, 59], ['h', '小时前', 1, 23], ['d', '天前', 1, 30],
     ['w', '周前', 1, 8], ['mo', '个月前', 1, 11], ['y', '年前', 1, 5]]
        .forEach(function (u) {
            for (var n = u[2]; n <= u[3]; n++) {
                map[n + u[0] + ' ago'] = n + ' ' + u[1];
            }
        });
    map['just now'] = '刚刚';

    /*
     * 规则层：PHP 在运行时拼接的句子（"3 resources on 2 servers were not
     * scanned, so Coolify cannot tell."）没有任何静态整串可匹配，枚举数字
     * 也不可行。这里用正则一次覆盖任意计数。
     *
     * 安全边界（踩过的坑）：
     *  - 数字前必须是行首、空白或标点，后面必须是词边界；否则
     *    "my-3 servers-app" 会被改成 "my-3 台服务器-app"。
     *  - 只替换正则命中的片段，其余文本原样保留。
     *  - 不使用 innerHTML，只写 textContent / nodeValue。
     */
    var N = '(^|[\\s(\\[,:;>])';   // 计数起点：行首、空白或标点

    var rules = [
        // N resources on M servers were not scanned, so Coolify cannot tell.
        {
            re: new RegExp(N + '(\\d[\\d,]*) resources? on (\\d[\\d,]*) servers? were not scanned, so Coolify cannot tell\\.', 'g'),
            to: '$1$2 个资源，位于 $3 台服务器上未被扫描，Coolify 无法判断。'
        },
        // 1 resource on M servers was not scanned, so Coolify cannot tell.
        {
            re: new RegExp(N + '1 resource on (\\d[\\d,]*) servers? was not scanned, so Coolify cannot tell\\.', 'g'),
            to: '$1' + '1 个资源，位于 $2 台服务器上未被扫描，Coolify 无法判断。'
        },
        // N resources on 1 server were not scanned, so Coolify cannot tell.
        {
            re: new RegExp(N + '(\\d[\\d,]*) resources? on 1 server were not scanned, so Coolify cannot tell\\.', 'g'),
            to: '$1$2 个资源，位于 1 台服务器上未被扫描，Coolify 无法判断。'
        },
        // 1 resource on 1 server was not scanned, so Coolify cannot tell.
        {
            re: new RegExp(N + '1 resource on 1 server was not scanned, so Coolify cannot tell\\.', 'g'),
            to: '$1' + '1 个资源，位于 1 台服务器上未被扫描，Coolify 无法判断。'
        },
        // N resources were not scanned, so Coolify cannot tell.
        {
            re: new RegExp(N + '(\\d[\\d,]*) resources? were not scanned, so Coolify cannot tell\\.', 'g'),
            to: '$1$2 个资源未被扫描，Coolify 无法判断。'
        },
        // 1 resource was not scanned, so Coolify cannot tell.
        {
            re: new RegExp(N + '1 resource was not scanned, so Coolify cannot tell\\.', 'g'),
            to: '$1' + '1 个资源未被扫描，Coolify 无法判断。'
        },
        // Metrics enabled on N servers.
        {
            re: new RegExp(N + 'Metrics enabled on (\\d[\\d,]*) servers?\\.', 'g'),
            to: '$1已在 $2 台服务器上启用指标。'
        }
    ];

    /*
     * 计数 + 单位：只在整段节点是「N word」时替换，避免动到用户数据。
     * 词表刻意收窄到页面里真实出现的计数单位。
     */
    var units = {
        container: '个容器', containers: '个容器',
        service: '个服务', services: '个服务',
        application: '个应用', applications: '个应用',
        database: '个数据库', databases: '个数据库',
        deployment: '次部署', deployments: '次部署',
        server: '台服务器', servers: '台服务器',
        resource: '个资源', resources: '个资源',
        project: '个项目', projects: '个项目',
        environment: '个环境', environments: '个环境',
        network: '个网络', networks: '个网络',
        volume: '个卷', volumes: '个卷',
        backup: '个备份', backups: '个备份',
        domain: '个域名', domains: '个域名',
        member: '位成员', members: '位成员',
        user: '个用户', users: '个用户',
        key: '个密钥', keys: '个密钥',
        image: '个镜像', images: '个镜像',
        package: '个软件包', packages: '个软件包',
        update: '个更新', updates: '个更新',
        event: '个事件', events: '个事件',
        request: '次请求', requests: '次请求',
        finding: '条发现', findings: '条发现',
        vulnerability: '个漏洞', vulnerabilities: '个漏洞'
    };

    var countRe = /^(\d[\d,]*)(?:\s+)([a-zA-Z]+)$/;

    /*
     * 只在「整段节点恰好是 N + 单位」时替换。节点里有别的词（用户名、
     * 镜像名、路径）一律不动，因此不会从用户数据里切出片段。
     */
    var applyRules = function (text) {
        var out = text;
        for (var i = 0; i < rules.length; i++) {
            out = out.replace(rules[i].re, rules[i].to);
        }

        var trimmed = out.trim();
        var m = countRe.exec(trimmed);
        if (m && units[m[2].toLowerCase()]) {
            return out.replace(trimmed, m[1] + ' ' + units[m[2].toLowerCase()]);
        }

        return out;
    };

    var applyTo = function (root) {
        if (!root) {
            return;
        }
        if (root.nodeType === 3) {
            root = root.parentElement;
        }
        if (!root || root.nodeType !== 1) {
            return;
        }
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        var node;
        while ((node = walker.nextNode())) {
            var raw = node.nodeValue || '';
            var trimmed = raw.trim();
            var zh = map[trimmed];
            if (zh !== undefined) {
                node.nodeValue = raw.replace(trimmed, zh);
                continue;
            }
            // 规则层：只在节点确实是英文句子时处理，且要求替换后确实改变了文本。
            if (rules.length === 0 || !/[A-Za-z]{3,}/.test(trimmed)) {
                continue;
            }
            var converted = applyRules(raw);
            if (converted !== raw) {
                node.nodeValue = converted;
            }
        }
    };

    var pending = new Set();
    var timer = null;
    var flush = function () {
        timer = null;
        pending.forEach(function (root) {
            applyTo(root);
        });
        pending.clear();
    };
    var schedule = function () {
        if (timer === null) {
            timer = setTimeout(flush, 120);
        }
    };

    var observer = new MutationObserver(function (records) {
        records.forEach(function (record) {
            if (record.target) {
                pending.add(record.target);
            }
            record.addedNodes.forEach(function (added) {
                pending.add(added);
            });
        });
        schedule();
    });

    var boot = function () {
        applyTo(document.body);
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
