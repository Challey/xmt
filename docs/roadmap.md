# XMT 路线图

以「可核验来源、可追责主体、可审计流转」为主线（见 [vision.md](vision.md)），按阶段推进。
每阶段以 `CHANGELOG.md` 的一个版本收口；**状态**只有三种：`已完成` / `进行中` / `待开始`。

自动化开发约定：接手时先读本文件，取第一个非 `已完成` 阶段作为当期目标；完成后更新本文件状态与 `CHANGELOG.md`。

| 阶段 | 主题 | 状态 | 版本 |
|------|------|------|------|
| P1 | 信任底座（L0/L1/L2 + 主体实体 + 可信列表） | 已完成 | `v0.1.0-trust` |
| P2 | 企业认证与 DrupalX claim 桥（HMAC） | 已完成 | `v0.1.0-trust` |
| P3 | DrupalX → XMT 可信内容推送 | 已完成 | `v0.2.0-trust-phase5` |
| P4 | 垂直站信任字段传播 + Server B 部署/HTTPS | 已完成 | `v0.2.0-trust-phase5` |
| P5 | 首页可信分区 + L0 聚合页 | 已完成 | `v0.3.0-homepage` |
| P6 | 溯源可核验与审计导出 | 已完成 | `v0.4.0-provenance` |
| P7 | 主体主页与信任目录 | 已完成 | `v0.5.0-publishers` |
| P8 | 机器可读信任分发 | 待开始 | — |
| P9 | 信任运营与风控 | 待开始 | — |
| P10 | 观测与自动化 | 待开始 | — |

## P6 溯源可核验与审计导出（已完成）

`trust-model.md` 早前把「审计导出」标为后续项，本阶段补齐，并把溯源哈希从「每次保存重算」改为「首次落库固定」，使核验真正能发现漂移。

- `Drupal\xmt_trust\Provenance`：哈希载荷、期望哈希、核验结论（`verified` / `mismatch` / `bridge` / `missing`）集中一处
- 公开核验页 `/trusted/verify/{nid}` 与机器可读 `/trusted/verify/{nid}/json`
- 管理端审计 CSV 导出 `/admin/xmt/provenance/export`
- Drush `xmt:provenance-verify` 批量核验（有 `mismatch` 时退出码 1）
- 文章页展示「核验溯源」入口
- 单元测试 `Drupal\Tests\xmt_trust\Unit\ProvenanceTest`

部署校验：B 机 `git pull` 后执行 `drush --uri=xmt.pub cr`，再访问 `/trusted/verify/{nid}` 与 `/admin/xmt/provenance`。

## P7 主体主页与信任目录（已完成）

「可追责主体」此前只有实体与审批流，读者无法从文章反查主体、也无法浏览已认证主体，本阶段补上这条闭环。

- `/publishers` 认证主体目录：仅列出 `approved`，`?type=official|enterprise` 筛选，分页，含认证时间与可信发文数
- 主体主页补充认证时间、可信发文数、最近 10 篇可信内容
- 文章完整视图新增「发布主体」署名链接
- `Drupal\xmt_trust\TrustLevel` 收敛散落的等级字符串
- 修复主体主页整体套用 `xmt-trust-badge` 徽章样式导致的排版问题

主体联系人与邮箱仍只在表单显示，不进入公开页面。

## P8 机器可读信任分发（待开始）

- `/trusted/feed.json`（JSON Feed 1.1）与 `/trusted/feed.xml`，携带 `trust_level`、主体、溯源哈希
- 文章页结构化数据（`schema.org/NewsArticle` + `publisher` + 核验页 `sameAs`）
- 供第三方校验的公开说明文档（哈希算法与字段口径）

## P9 信任运营与风控（待开始）

- 主体 `suspended` 生效链路：撤下 L2 文章或降级为 L0，并留痕
- 更正/撤稿声明：文章级更正记录与展示
- 信任操作审计日志（谁在何时改了等级/主体/状态）

## P10 观测与自动化（待开始）

- Agent 采集质量指标（成功率、去重率、来源分布）与 cron 健康检查
- CI：`php -l`、`phpcs --standard=Drupal`、`phpunit` 单元测试门禁
- Server B 部署校验脚本（字段/模块/区块就位检查）
