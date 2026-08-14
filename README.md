# XMT — 信媒体 / 新媒体

**可信的媒体信源，服务 AI 时代。**

XMT（xmt.pub）是在 Drupal 11 上构建的**企业级认证新媒体平台**：官方可信发布、企业身份认证后的企业可信发布，并与 [DrupalX](https://github.com/Challey) 企业门户 / App Store 在认证层互通。

| | |
|--|--|
| 域名 | https://xmt.pub |
| 栈 | Drupal 11.4 · Drush 13 · LNMPA |
| 开源仓库 | [Challey/xmt](https://github.com/Challey/xmt) |

## 信任分级

| 级别 | 含义 |
|------|------|
| **L0 汇聚** | RSS/公开源自动采集，标注「汇聚·未认证」 |
| **L1 官方可信** | XMT 官方栏目签发 |
| **L2 企业可信** | 完成企业身份认证的主体发布 |

详见 [docs/trust-model.md](docs/trust-model.md)。

垂直站（如 zhubao）仅展示/汇聚：**L0** 由 Agent 或 presave 默认；同步至 xmt.pub 时携带 `field_trust_level` / `field_publisher`。垂直站后台信任字段只读，L1/L2 仅在 hub 签发。

公开可信流：`/trusted`（全部 L1+L2）、`/trusted/official`（L1 官方）、`/trusted/enterprise`（L2 企业）、`/trusted/aggregate`（L0 汇聚）。

机器可读订阅（RSS / JSON，含信任等级、主体、溯源哈希）：

| 页面 | RSS | JSON |
|------|-----|------|
| 全部可信 | `/trusted/feed.rss` | `/trusted/feed.json` |
| 官方 L1 | `/trusted/official/feed.rss` | `/trusted/official/feed.json` |
| 企业 L2 | `/trusted/enterprise/feed.rss` | `/trusted/enterprise/feed.json` |
| 汇聚 L0 | `/trusted/aggregate/feed.rss` | `/trusted/aggregate/feed.json` |

可选 `?limit=50`（1–100，默认 30）。

## 快速开始

```bash
cd /home/wwwroot/xmt   # 或 git clone git@github.com:Challey/xmt.git
composer install
cp web/sites/example.settings.php web/sites/xmt.pub/settings.php
# 编辑数据库与 hash_salt 后：
vendor/bin/drush --uri=xmt.pub cr
```

启用信任模块：

```bash
vendor/bin/drush --uri=xmt.pub en xmt_trust xmt_publisher xmt_trust_ui xmt_dx_bridge xmt_syndicate -y
vendor/bin/drush --uri=xmt.pub cr
```

默认运维说明见 [docs/ops.md](docs/ops.md)；愿景见 [docs/vision.md](docs/vision.md)。

## 模块

| 模块 | 说明 |
|------|------|
| `xmt_trust` | 信任等级、溯源字段、权限 |
| `xmt_publisher` | 发布主体（官方/企业）与认证状态机 |
| `xmt_trust_ui` | 可信流、主体页、徽章 |
| `xmt_dx_bridge` | DrupalX 认证 claim 验签 |
| `xmt_syndicate` | 垂直站 → xmt.pub 聚合 |
| `agent/` | 按领域 RSS 采集（写入 L0） |

已认证发布主体公开页：`/publisher/{id}`（展示信任徽章、官网链接、已发布文章列表）。

## 许可证

见 [LICENSE.txt](LICENSE.txt)。运营密钥与生产 `settings.php` 不进入本仓库。
