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
        'Not checked': '未检查',
        'Not ready': '未就绪',
        'Check needed': '需要检查',
        'Running (healthy)': '运行中（健康）',
        'Running (unknown)': '运行中（未知）',
        'Running (unhealthy)': '运行中（不健康）',
        'Running (degraded)': '运行中（降级）',
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
        'Unknown': '未知'
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
            if (zh === undefined) {
                continue;
            }
            node.nodeValue = raw.replace(trimmed, zh);
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
