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

每条可信文章可写 `field_provenance_hash`（来源 URL + 主体 ID + 时间的哈希）。

拥有 `administer xmt trust` 权限的管理员可在 `/admin/xmt/provenance` 查看最近 50 篇文章的信任等级、发布主体、溯源哈希、来源 URL 和更新时间，并导出：

- Web：页面按钮「Export CSV」`/admin/xmt/provenance/export.csv`、「Export JSON」`/admin/xmt/provenance/export.json`（导出全部可访问文章，非仅前 50 条）
- CLI：`vendor/bin/drush --uri=xmt.pub xmt:provenance-export --file=/tmp/audit.csv`（详见 `docs/ops.md`）

## 与 DrupalX

DrupalX `dx_developer` 认证通过后，可签发 HMAC claim；XMT `xmt_dx_bridge` 验签后创建/升级为已批准企业主体。内容自动双写为二期。
