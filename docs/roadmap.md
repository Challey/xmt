# XMT 路线图

产品愿景见 [vision.md](vision.md)；信任模型见 [trust-model.md](trust-model.md)；短闻产品见 [short-news.md](short-news.md)；领域目录见 [domains.md](domains.md)。

## 分支状态（2026-09-09）

| 分支 | 状态 | 说明 |
|------|------|------|
| `main` | 当前主线 | 已合并全部历史 feature 分支与 PR #1 |
| `cursor/short-news-theme-hot-home-e92f` | 已合并（可删除） | 短闻白天/黑夜主题 + 最热四栏首页 |

历史上并发的 Cursor 分支（publisher-directory、provenance-verify、trust-robots-l0-sitemap 等）均已合入 main，冲突按「保留信任平台新功能 + 短闻新功能」原则解决。

## 已完成

| 版本 / 切片 | 内容 |
|-------------|------|
| v0.1.0-trust | L0/L1/L2、发布主体申请/批准、可信 feed、HMAC claim |
| v0.2.0 Phase 5 | `POST /api/xmt/v1/trusted-content` 可信内容推送 |
| v0.3.0-homepage | 首页三列 block、`/trusted/aggregate` |
| 后续补强 | 溯源审计列表、article body bootstrap、垂直站信任字段、HTTPS 文档 |
| v0.4.0 | 溯源 CSV/JSON 导出 + Drush；主体页文章列表；文章归因；垂直站 after_build 修复；HMAC nonce 防重放 |
| v0.5.0–v0.9.0 | 可信 RSS/JSON、发布主体目录与页、sitemap、robots.txt SEO、L0 入 sitemap |
| v0.10.0-provenance-verify | 哈希 write-once；`/trusted/verify/{nid}`（含 JSON）；`/trusted/verify?hash=`；`xmt:provenance-verify`；`Provenance`/`TrustLevel` |
| **短闻 v1.0（2026-08-15）** | 白天/黑夜主题（`xmt_duanwen_theme`）；首页最热四栏（财经·科技·国内·国际）；领域 `world` + 虚拟聚合 `domestic`；信流 sticky 顶栏；`docs/short-news.md` / `docs/domains.md` |

## 下一步（优先序）

1. ~~审计列表筛选/分页~~ — **已完成**
2. ~~机器可读可信 feed~~ — **已完成**
3. ~~引导脚本~~ — **已完成**：`setup/scripts/80-trust-stack.sh`
4. ~~公众溯源核验~~ — **已完成**
5. ~~短闻主题与最热首页~~ — **已完成**（PR #1 已合入 main）
6. **PHPUnit** — `Provenance` 单元测试已落地；Kernel/Functional（导出、nonce、claim/content、短闻筛选）仍待
7. **DrupalX 侧 auto-push** — 在 DrupalX 仓库实现（非本仓）；须带唯一 nonce
8. **短闻运营** — 跑 agent 入库 `official_media_world`，保证国际栏有内容；监控 `/admin/xmt/sources`
9. **清理** — 删除已合并远程分支 `cursor/short-news-theme-hot-home-e92f`

## 非目标（本仓短期）

- 跨站真正 SSO（D11.4 字符串表前缀限制）
- OAuth 替换 HMAC claim（首期保持 HMAC）
- HTML 全页抓取（仅官方 RSS/Atom）

## 合并原则（后续）

- 冲突时：**优先保留 main 上已有的信任平台与溯源逻辑**，再叠加短闻/产品 UI 变更。
- 不覆盖 write-once Provenance、NodeForm 命名空间修复、服务注册等关键修复。
- 文档与 CHANGELOG 做增量合并，不整文件覆盖旧版本说明。
