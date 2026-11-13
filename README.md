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
cd /data/coolify/source && docker compose pull && docker compose up -d   # 立即以同版本 fork 镜像重建
# 或不执行重建，等上游发新版后由自动升级自然拉取 fork 镜像
```

写入后可立即以同版本 Fork 镜像重建容器（不依赖上游发版），此后所有升级均从 Fork 源拉取。

**回滚**：从 `/data/coolify/source/.env` 中删除 `REGISTRY_URL=ghcr.io/tktogether` 这一行，再执行 `docker compose up -d` 即恢复官方镜像。

## 自动跟版

- **定时合并**：[`sync-upstream`](./.github/workflows/sync-upstream.yml) 每小时检查上游 `coollabsio/coolify` main 分支，领先则自动合并并推送（预期零冲突；万一冲突会停下提醒人工处理，绝不静默跳过）。
- **自动构建**：合并/发版后 [`build-cn-image`](./.github/workflows/build-cn-image.yml) 自动构建并推送 `ghcr.io/tktogether/coollabsio/coolify:<版本>`。
- **自动升级**：已安装实例由官方自动升级机制拉取新版镜像。

全链路无人值守，本汉化版最多落后上游约 1~2 小时；窗口期内实例升级拉取可能短暂失败并自动重试，实例照常运行，无害。

## 上游说明

- 上游原始 README 见 [`README.upstream.md`](./README.upstream.md)。
- 官方文档：https://coolify.io/docs

## 许可

Coolify 上游项目以 [Apache License 2.0](./LICENSE) 开源，本 Fork 在同一许可下分发；上游原始说明与致谢见 [`README.upstream.md`](./README.upstream.md)。

## 设计文档

汉化方案、跟版链路与测试设计详见 [`docs/superpowers/specs/2026-11-14-chinese-i18n-design.md`](./docs/superpowers/specs/2026-11-14-chinese-i18n-design.md)。
