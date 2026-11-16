<?php

/* 上游同步批次：随上游合并进入界面的新文案（注册页根账户警告、GitHub App
   安装账号、备份保留期、镜像扫描超时、根用户命令等），以及登录页的注册词条。
   插值碎片与代码示例不收录。 */

return [
    'An invitation created this account, and it is already in use. Sign in with its password, or reset the password with the email link, then sign in with this provider. If email is not set up, ask your administrator.' => '此账户由邀请创建，且已在使用中。请用它的密码登录，或通过邮件链接重置密码后再用此提供商登录。若未配置邮件，请联系管理员。',
    'Also remove the containers, volumes, and files of these resources from the server' => '同时从服务器上删除这些资源的容器、卷和文件',
    'By default a compose resource only gets its own internal network. Connecting to a Coolify predefined network may require different internal DNS names.' => '默认情况下，Compose 资源只会获得自己的内部网络。连接到 Coolify 预定义网络可能需要不同的内部 DNS 名称。',
    'Connected' => '已连接',
    'Coolify creates the root account from the ROOT_USER_* values in /data/coolify/source/.env, but it rejected them. Read the reason in the logs, then run the installer again with corrected values:' => 'Coolify 会使用 /data/coolify/source/.env 中的 ROOT_USER_* 值创建根账户，但这些值未被接受。请在日志中查看原因，然后用修正后的值重新运行安装脚本：',
    'Coolify has not finished starting. Wait a few seconds, then try again.' => 'Coolify 尚未完成启动。请稍等几秒后重试。',
    'Create the root account for this instance.' => '为此实例创建根账户。',
    'Create your account to get started.' => '创建你的账户以开始使用。',
    'Maximum number of recent local backups of each database.' => '每个数据库保留的最近本地备份数量上限。',
    'Maximum number of recent S3 backups of each database.' => '每个数据库保留的最近 S3 备份数量上限。',
    'No public endpoint' => '无公网端点',
    'Not usable' => '不可用',
    'Permanently delete all database and storage backups of this resource, on the server and on S3.' => '永久删除此资源在服务器和 S3 上的所有数据库与存储备份。',
    'Production and its preview deployments share this network: a service name such as' => '生产环境及其预览部署共享此网络：诸如',
    'Provide the full Fluent Bit configuration and an optional parser configuration. The configuration must have an [INPUT] section with Name forward: containers send their logs to this input on port 24224. To use credential or certificate files, copy them to /data/coolify/log-drains/files on the server. Fluent Bit reads them at /files, for example: google_service_credentials /files/gcp.json' => '填写完整的 Fluent Bit 配置和可选的解析器配置。配置必须包含 [INPUT] 段且 Name 为 forward：容器会将其日志发送到此输入（端口 24224）。如需使用凭据或证书文件，请将它们复制到服务器上的 /data/coolify/log-drains/files，Fluent Bit 会在 /files 读取它们，例如：google_service_credentials /files/gcp.json',
    'Remove local backups older than this many days, counted from the newest backup of the same database.' => '删除早于该天数的本地备份，从同一数据库的最新备份算起。',
    'Remove oldest local backups after the backups of all databases reach this total size. The newest backup of each database stays.' => '当所有数据库的备份达到此总大小后，删除最旧的本地备份。每个数据库的最新备份会保留。',
    'Remove oldest S3 backups after the backups of all databases reach this total size. The newest backup of each database stays.' => '当所有数据库的备份达到此总大小后，删除最旧的 S3 备份。每个数据库的最新备份会保留。',
    'Remove S3 backups older than this many days, counted from the newest backup of the same database.' => '删除早于该天数的 S3 备份，从同一数据库的最新备份算起。',
    'Root user not created' => '未创建根用户',
    'Search sources' => '搜索源',
    'Search storages' => '搜索存储',
    'Setup incomplete' => '设置未完成',
    'Only OAuth registration is enabled.' => '当前仅启用 OAuth 注册。',
    'Register' => '注册',
    'The database and storage backups of this resource stay on the server and on S3. Coolify no longer manages them, so you must delete them yourself.' => '此资源的数据库与存储备份会保留在服务器和 S3 上。Coolify 不再管理它们，你需要自行删除。',
    'The value starts or ends with a space. Coolify keeps the space.' => '该值的开头或结尾有空格。Coolify 会保留这些空格。',
    'Time to check the vulnerability database, and time to match the packages of one image with it. Slow disks need more time.' => '检查漏洞数据库以及将镜像中的软件包与之匹配的时间。慢速磁盘需要更多时间。',
    'Time to export one local image from Docker, and time to list its packages. Large images and slow disks need more time.' => '从 Docker 导出一个本地镜像以及列出其软件包的时间。大镜像和慢速磁盘需要更多时间。',
    'To use this GitHub App with another account or organization, click Repositories and install it there. Coolify adds a new source for each installation.' => '要将此 GitHub App 用于另一个账户或组织，请点击 Repositories 并在那里安装。Coolify 会为每次安装添加一个新的源。',
    'Used only on the first start, when PostgreSQL creates the data directory. To apply a change later, delete the data volume or edit the files in the database.' => '仅在首次启动、PostgreSQL 创建数据目录时使用。之后如需更改，请删除数据卷或直接编辑数据库中的文件。',
    'as the hostname to reach the service of the same deployment. More info' => '作为主机名，以访问同一部署中的服务。更多信息',
    'resolves to the production container, also from a preview. Use' => '这样的服务名会解析到生产容器，预览环境下也是如此。请使用',
];
