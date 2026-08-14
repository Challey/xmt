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
具备 `administer xmt trust` 权限的管理员可在 `/admin/xmt/provenance`
分页审计 L1/L2 内容，并导出 UTF-8 CSV。导出会按节点访问权限过滤，
且对电子表格公式前缀进行转义。

## 与 DrupalX

DrupalX `dx_developer` 认证通过后，可签发 HMAC claim；XMT `xmt_dx_bridge` 验签后创建/升级为已批准企业主体。内容自动双写为二期。
