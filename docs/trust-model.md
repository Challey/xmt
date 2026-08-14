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

## 公开主体页

已批准主体可通过 `/publisher/{id}` 公开访问：

- 官方主体显示 L1 徽章，企业主体显示 L2 徽章
- 展示官网链接；企业主体展示注册号/信用代码
- 列出该主体最近发布的文章（含信任徽章与日期）

## 溯源

每条可信文章可写 `field_provenance_hash`（来源 URL + 主体 ID + 时间的哈希），便于审计导出。

### 审计导出

- 后台 **Content → Provenance audit**（`/admin/xmt/provenance`）列出最近 50 条文章及溯源字段。
- 页面 **Export CSV** 或 `GET /admin/xmt/provenance/export` 下载 CSV（默认最多 500 条，可用 `?limit=`、`?trust_level=` 过滤）。
- 运维：`vendor/bin/drush --uri=xmt.pub xmt:provenance-export --limit=1000 --output=/tmp/xmt-provenance.csv`

## 与 DrupalX

DrupalX `dx_developer` 认证通过后，可签发 HMAC claim；XMT `xmt_dx_bridge` 验签后创建/升级为已批准企业主体。内容自动双写为二期。
