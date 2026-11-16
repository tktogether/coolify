/**
 * Client-side translation rules for text that the server pass cannot reach.
 *
 * PHP composes some sentences at runtime — "3 resources on 2 servers were not
 * scanned, so Coolify cannot tell." — so no static dictionary entry can match
 * them, and enumerating the counts is not an option either. These rules rewrite
 * the fixed sentence shape and leave every number alone.
 *
 * Safety boundary: a rule may only match where the count starts the text or
 * follows whitespace or punctuation, and must not run into an identifier. Without
 * that, a name such as "my-3 servers-app" would turn into "my-3 台服务器-app".
 */

const COUNT_START = '(^|[\\s(\\[,:;>])';

export const sentenceRules = [
    {
        re: new RegExp(`${COUNT_START}(\\d[\\d,]*) resources? on (\\d[\\d,]*) servers? were not scanned, so Coolify cannot tell\\.`, 'g'),
        to: '$1$2 个资源，位于 $3 台服务器上未被扫描，Coolify 无法判断。',
    },
    {
        re: new RegExp(`${COUNT_START}1 resource on (\\d[\\d,]*) servers? was not scanned, so Coolify cannot tell\\.`, 'g'),
        to: '$11 个资源，位于 $2 台服务器上未被扫描，Coolify 无法判断。',
    },
    {
        re: new RegExp(`${COUNT_START}(\\d[\\d,]*) resources? on 1 server were not scanned, so Coolify cannot tell\\.`, 'g'),
        to: '$1$2 个资源，位于 1 台服务器上未被扫描，Coolify 无法判断。',
    },
    {
        re: new RegExp(`${COUNT_START}1 resource on 1 server was not scanned, so Coolify cannot tell\\.`, 'g'),
        to: '$11 个资源，位于 1 台服务器上未被扫描，Coolify 无法判断。',
    },
    {
        re: new RegExp(`${COUNT_START}(\\d[\\d,]*) resources? were not scanned, so Coolify cannot tell\\.`, 'g'),
        to: '$1$2 个资源未被扫描，Coolify 无法判断。',
    },
    {
        re: new RegExp(`${COUNT_START}1 resource was not scanned, so Coolify cannot tell\\.`, 'g'),
        to: '$11 个资源未被扫描，Coolify 无法判断。',
    },
    {
        re: new RegExp(`${COUNT_START}Metrics enabled on (\\d[\\d,]*) servers?\\.`, 'g'),
        to: '$1已在 $2 台服务器上启用指标。',
    },
];

/** Counting units that appear as a whole text node, e.g. a table footer. */
export const countUnits = {
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
    vulnerability: '个漏洞', vulnerabilities: '个漏洞',
};

const WHOLE_COUNT = /^(\d[\d,]*)(?:\s+)([a-zA-Z]+)$/;

/**
 * Rewrite the fixed sentences and, when the whole text is just a count and a
 * unit, the unit too. Anything else is returned unchanged.
 *
 * @param {string} text
 * @returns {string}
 */
export function applyClientRules(text) {
    let out = text;

    for (const rule of sentenceRules) {
        out = out.replace(rule.re, rule.to);
    }

    const trimmed = out.trim();
    const match = WHOLE_COUNT.exec(trimmed);

    if (match !== null) {
        const unit = countUnits[match[2].toLowerCase()];

        if (unit !== undefined) {
            return out.replace(trimmed, `${match[1]} ${unit}`);
        }
    }

    return out;
}
