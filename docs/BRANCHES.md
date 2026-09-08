# XMT 分支梳理与合并记录

更新时间：2026-09-09

## 当前分支

| 分支 | HEAD | 状态 | 说明 |
|------|------|------|------|
| **main** | 最新 | 主线 | 默认分支；已包含信任平台全栈 + 短闻主题 |
| `cursor/short-news-theme-hot-home-e92f` | f9fbad3 | **已合并** | 可安全删除 |

## 历史已合并分支（均已合入 main）

并发开发期间由 Cursor Agent 创建的 feature 分支，经多次 merge commit 解决冲突后进入 main：

- `cursor/publisher-directory-*` — 发布主体目录与公开页
- `cursor/provenance-audit-export-*` — 溯源审计导出、NodeForm 修复
- `cursor/trust-robots-l0-sitemap-*` — sitemap、robots.txt、L0 收录
- `cursor/trust-audit-verify-*` — 公众核验页
- `cursor/short-news-theme-hot-home-e92f` — 短闻白天/黑夜 + 最热四栏首页（PR #1）

## 冲突处理原则（已执行）

1. **信任与溯源逻辑优先**：write-once `Provenance`、正确 `Drupal\node\Form\NodeForm`、服务注册、nonce 防重放等关键修复一律保留。
2. **短闻产品功能叠加**：白天/黑夜主题、PINNED_FILTERS、四栏最热首页、`world`/`domestic` 领域不覆盖信任字段与 API。
3. **文档增量合并**：CHANGELOG、ops.md、roadmap 做段落级合并，不整文件覆盖。
4. **路由命名对齐 main**：`publishers_directory`、`trust_sitemap`、`provenance_audit_export` 等与主线一致。

## 清理建议

```bash
# 本地
git checkout main
git pull origin main
git branch -d cursor/short-news-theme-hot-home-e92f   # 若本地仍存在

# 远程（维护者）
git push origin --delete cursor/short-news-theme-hot-home-e92f
```

## 相关文档

- [roadmap.md](roadmap.md) — 产品与技术路线图
- [short-news.md](short-news.md) — 短闻产品说明
- [domains.md](domains.md) — 领域与筛选规范
- [trust-model.md](trust-model.md) — 信任分级
- [ops.md](ops.md) — 运维与部署
