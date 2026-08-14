# XMT 路线图

产品愿景见 [vision.md](vision.md)；信任模型见 [trust-model.md](trust-model.md)。

## 已完成

| 版本 / 切片 | 内容 |
|-------------|------|
| v0.1.0-trust | L0/L1/L2、发布主体申请/批准、可信 feed、HMAC claim |
| v0.2.0 Phase 5 | `POST /api/xmt/v1/trusted-content` 可信内容推送 |
| v0.3.0-homepage | 首页三列 block、`/trusted/aggregate` |
| 后续补强 | 溯源审计列表、article body bootstrap、垂直站信任字段、HTTPS 文档 |
| v0.4.0（本分支） | 溯源 CSV/JSON 导出 + Drush；主体页文章列表；文章归因；垂直站 after_build 修复；HMAC nonce 防重放 |

## 下一步（优先序）

1. ~~审计列表筛选/分页~~ — **已完成（本分支）**
2. ~~机器可读可信 feed~~ — **已完成**：`/api/xmt/v1/trusted/{filter}` JSON + `/trusted/{filter}.xml` RSS
3. ~~引导脚本~~ — **已完成**：`setup/scripts/80-trust-stack.sh`（bootstrap-all 已串联）
4. **PHPUnit** — `xmt_*` Kernel/Functional（导出、nonce、claim/content）
5. **DrupalX 侧 auto-push** — 在 DrupalX 仓库实现（非本仓）；须带唯一 nonce

## 非目标（本仓短期）

- 跨站真正 SSO（D11.4 字符串表前缀限制）
- OAuth 替换 HMAC claim（首期保持 HMAC）
