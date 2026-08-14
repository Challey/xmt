# 信任模型

## 级别

| Code | 标签 | 谁可以发布 | UI |
|------|------|------------|-----|
| `l0_aggregate` | 汇聚·未认证 | 系统 Agent | 灰色 |
| `l1_official` | 官方可信 | `xmt_official_editor` | 蓝色徽章 |
| `l2_enterprise` | 企业可信 | 已批准的 `xmt_publisher` 绑定用户 | 金色徽章 |

## 主体 `xmt_publisher`

- `type`: `official` \| `enterprise`
- `status`: `pending` \| `approved` \| `rejected` \| `suspended`
- 企业字段：名称、信用代码/注册号、网站、联系人
- 批准后授予角色 `xmt_enterprise_publisher`，发文必须绑定本主体且等级为 L2

## 溯源

每条可信文章可写 `field_provenance_hash`（来源 URL + 主体 ID + 时间的哈希），便于审计导出。

- 管理后台：`/admin/xmt/provenance` — 可按信任等级 / 发布主体筛选，分页（每页 50）
- CSV：`/admin/xmt/provenance/export.csv`（继承当前筛选 query）或 Drush `xmt:provenance-export`
- JSON：`/admin/xmt/provenance/export.json` 或 `--format=json`
- Drush 筛选：`--trust-level=l2_enterprise`、`--publisher=<id>`

## 发布主体页

- 公开页 `/publisher/{id}`（已批准主体）展示该主体最近 L1/L2 可信文章
- 文章页对 L1/L2 显示「Published by」链至主体页

## 与 DrupalX

DrupalX `dx_developer` 认证通过后，可签发 HMAC claim；XMT `xmt_dx_bridge` 验签后创建/升级为已批准企业主体。可信内容推送见 [drupalx-bridge.md](drupalx-bridge.md)。
