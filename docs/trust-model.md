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
- 订阅该主体文章：`/publisher/{id}/feed.rss`、`/publisher/{id}/feed.json`

## 主体目录

- `/publishers` — 全部已认证官方（L1）与企业（L2）主体，链至各主体页与申请入口 `/publishers/apply`

联系人姓名与邮箱仅在管理表单可见，不在公开页面输出。

## 溯源

每条文章在**首次落库**时写入 `field_provenance_hash`：

```
sha256(来源URL|主体ID|创建时间戳)
```

无主体时主体 ID 记为 `0`，无来源时来源为空串。哈希写入后不再覆盖，因此此后改动来源、主体或创建时间都会在核验时暴露为 `mismatch`，而不是被静默重写。

| 结论 | 含义 |
|------|------|
| `verified` | 当前字段重算结果与已记录哈希一致 |
| `mismatch` | 与首次落库的记录不一致，需人工核对修改记录 |
| `bridge` | 由 DrupalX 签发（`dx:` 前缀），本站无法重算 |
| `missing` | 尚未记录哈希 |

入口：

- 公开核验页 `/trusted/verify/{nid}`，机器可读 `/trusted/verify/{nid}/json`
- 管理端审计列表 `/admin/xmt/provenance`，CSV 导出 `/admin/xmt/provenance/export`
- 批量核验 `vendor/bin/drush --uri=xmt.pub xmt:provenance-verify`
- 运维导出：`vendor/bin/drush --uri=xmt.pub xmt:provenance-export --limit=1000 --output=/tmp/xmt-provenance.csv`

算法与字段口径对外公开（核验页同时展示），第三方可独立复算。

## 与 DrupalX

DrupalX `dx_developer` 认证通过后，可签发 HMAC claim；XMT `xmt_dx_bridge` 验签后创建/升级为已批准企业主体。内容自动双写为二期。
