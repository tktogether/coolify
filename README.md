<div align="center">

# Coolify 中文版

**[Coolify](https://github.com/coollabsio/coolify) 的个人中文汉化 Fork。**

</div>

## 关于本 Fork

这是 Coolify 的个人汉化版本：在上游代码之上叠加一层中文界面词典与镜像构建流水线，不改动上游核心功能。Fork 每小时自动合并上游更新并自动构建对应版本的 Docker 镜像，保持与上游功能同步。

## 一键安装（新实例）

在服务器上执行官方安装脚本，仅把镜像源切到本 Fork：

```bash
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | REGISTRY_URL=ghcr.io/tktogether bash
```

## 已有实例切换与回滚

前提：本 Fork 已发布与你当前运行版本号一致的镜像。全程数据卷不动，无迁移风险：

```bash
echo "REGISTRY_URL=ghcr.io/tktogether" >> /data/coolify/source/.env
# 立即以同版本 fork 镜像重建（compose 必须叠加 prod 文件，见下）：
cd /data/coolify/source && REGISTRY_URL=ghcr.io/tktogether LATEST_IMAGE=4.7.5 \
  docker compose -f docker-compose.yml -f docker-compose.prod.yml pull && \
  REGISTRY_URL=ghcr.io/tktogether LATEST_IMAGE=4.7.5 \
  docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
# 或不执行重建，等自动升级（见下）自然拉取 fork 镜像
```

> 注意：`coolify` 服务的镜像定义在 `docker-compose.prod.yml`（`docker-compose.yml` 只是基础文件），裸调 `docker compose pull` 会报 "neither an image nor a build context"。`LATEST_IMAGE` 按你的目标版本号填写。

写入后可立即以同版本 Fork 镜像重建容器（不依赖上游发版），此后所有升级均从 Fork 源拉取。

**回滚**：从 `/data/coolify/source/.env` 中删除 `REGISTRY_URL=ghcr.io/tktogether` 这一行，再执行 `bash /data/coolify/source/upgrade.sh <目标版本> <helper版本> docker.io`（或同上带 `-f` 两个文件的 compose 命令，`LATEST_IMAGE` 用目标官方版本号）即恢复官方镜像。

## 自动跟版

- **定时合并**：[`sync-upstream`](./.github/workflows/sync-upstream.yml) 每小时检查上游 `coollabsio/coolify` main 分支，领先则自动合并并推送（其余文件预期零冲突；上游改动其 `README.md` 时会冲突停下并告警，属已知例外，处理见下节）。
- **自动构建**：合并/发版后 [`build-cn-image`](./.github/workflows/build-cn-image.yml) 自动构建并推送 `ghcr.io/tktogether/coollabsio/coolify:<版本>`。
- **自动升级**：已安装实例由官方自动升级机制拉取新版镜像。

全链路无人值守，本汉化版最多落后上游约 1~2 小时；窗口期内实例升级拉取可能短暂失败并自动重试，实例照常运行，无害。

## 上游 README 更新冲突处理

上游修改其 `README.md` 时，`sync-upstream` 合并会在该文件冲突停下并告警；CI 侧不会推送任何内容，在本地 fork `main` 分支重放合并后机械解决——中文 `README.md` 原样保留，上游最新英文覆盖进 `README.upstream.md`：

```bash
git fetch upstream && git merge upstream/main   # 重放合并；README.md 冲突属预期，报错可忽略（其余文件冲突需人工判断）
git checkout --ours -- README.md                # 保留中文版并清除冲突标记（必须在 add 之前）
git show upstream/main:README.md > README.upstream.md && git add README.md README.upstream.md
git commit --no-edit && git push                # 完成合并并推送，CI 随后自动构建新镜像
```

## 上游说明

- 上游原始 README 见 [`README.upstream.md`](./README.upstream.md)。
- 官方文档：https://coolify.io/docs

## 许可

Coolify 上游项目以 [Apache License 2.0](./LICENSE) 开源，本 Fork 在同一许可下分发；上游原始说明与致谢见 [`README.upstream.md`](./README.upstream.md)。

## 设计文档

汉化方案、跟版链路与测试设计详见 [`docs/superpowers/specs/2026-11-14-chinese-i18n-design.md`](./docs/superpowers/specs/2026-11-14-chinese-i18n-design.md)。
