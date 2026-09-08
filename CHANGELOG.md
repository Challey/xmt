# Changelog

## Unreleased

### Added
- 短闻 **Apple 精修层** `css/short-read-apple.css`（`short_read` library **3.0**）：更清晰字号/行高、iOS 分段控件、卡片悬停层级、信流阅读面、详情与「今日」全 token 化、夜间一致、减少动效支持
- 垂直站 **hm-os.com / airobotor.com** 信源扩充（`agent/sources.yaml`：`harmonyos`、`ai_robot`）
- Agent：`XMT_AGENT_FILTER` 域过滤；payload 尊重 YAML `trust_level` / `publisher`
- 垂直站短闻入口文案按鸿蒙 / 机器人定制；`hm-os.cn` Host 映射
- `docs/vertical-sites.md` — 垂直站采集、UI、验收

### Docs
- `docs/BRANCHES.md`、`docs/roadmap.md`

## v0.11.0-short-news-theme — 2026-08-15（合入 main 2026-09-09）

### Added
- 短闻 **白天 / 黑夜** 主题（`xmt_duanwen_theme`，顶栏与首页一键切换；信流布局与配色解耦）
- 首页最热四栏：**财经 · 科技 · 国内 · 国际**；主行领域芯片同步置顶
- 领域 `world`（国际）与虚拟聚合 `domestic`（时政/社会/军事）；官媒国际 RSS 独立分组入库
- `docs/short-news.md`、`docs/domains.md`、`docs/sources-allowlist.md`

### Merged
- PR #1 `cursor/short-news-theme-hot-home-e92f` → main

### Fixed（此前已在 main）
- Trust field `NodeForm` 命名空间修复；`provenance_audit` 服务注册

## v0.10.0-provenance-verify — 2026-08-14

### Added
- write-once Provenance、`/trusted/verify/{nid}`、hash 查找、`xmt:provenance-verify`、单元测试

## v0.9.0-trust-seo — 2026-08-14

### Added
- robots.txt sitemap、L0 入 sitemap

## v0.8.0 — v0.1.0

见仓库历史：publishers、feeds、homepage、DrupalX bridge、信任模型初版。
