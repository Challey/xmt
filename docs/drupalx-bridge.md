# DrupalX ↔ XMT 认证桥

## 首期：HMAC claim（非 OAuth）

1. 双方共享密钥：环境变量 `XMT_DX_BRIDGE_SECRET`（两端一致）；XMT 在 `web/sites/xmt.pub/settings.php` 中可设置 `$settings['xmt_dx_bridge_secret']`。
2. **DrupalX** 签发 claim：
   ```bash
   export XMT_DX_BRIDGE_SECRET='your-shared-secret'
   cd /home/wwwroot/drupalX
   vendor/bin/drush dx:xmt-issue-claim \
     --name='鸿蒙产业联盟' \
     --developer=dx-hm-100 \
     --credit=91440300HMTEST \
     --website=https://hm.example.com
   ```
   输出第一行为 JSON body，下一行 `X-XMT-Signature: <hex>`。
3. Claim 字段：`publisher_name`, `credit_code`, `website`, `dx_developer_id`, `exp`, `nonce`。
4. 签名：`hash_hmac('sha256', body, secret)`，请求头 `X-XMT-Signature`。
   **注意：** HMAC 的 `body` 必须是签名的**精确字节**（与 Drush 打印的**单行 JSON 字符串**完全一致，无额外空格或换行）。
5. **XMT** 接收：`POST /api/xmt/v1/dx-claim`（Host: `xmt.wsl` 或生产域名）→ 创建/更新 `xmt_publisher` 为 `approved`。
6. **防重放**：签名有效但被截获的请求，在 `exp` 之前理论上可被重放。若请求带 `nonce`，XMT 会记录 `dx_developer_id + nonce` 组合，重复提交返回 `400`（`Nonce already used; possible replay.`）。记录窗口取 `exp - 当前时间`，无 `exp` 时默认 10 分钟。省略 `nonce` 时不做防重放检查（兼容旧调用方），但新集成应始终携带。

### 本地测试（XMT）

```bash
export XMT_DX_BRIDGE_SECRET='your-shared-secret'
cd /home/wwwroot/xmt
vendor/bin/drush --uri=xmt.pub xmt:dx-claim-test --developer=dx-test-001 --name='Test Enterprise'
```

## 二期：可信内容推送（已实现）

`POST /api/xmt/v1/trusted-content` — 与 claim 相同 HMAC 验签（`X-XMT-Signature` + 原始 JSON body）。

### 请求 JSON 字段

| 字段 | 必填 | 说明 |
|------|------|------|
| `title` | 是 | 文章标题 |
| `body` | 是 | 正文 HTML |
| `dx_developer_id` | 是 | 已在 XMT 完成 claim 且 `approved` 的发布者 ID |
| `external_id` | 否 | 外部内容 ID；幂等键，`field_provenance_hash` = `dx:{external_id}` |
| `source_url` | 否 | 原文链接；无 `external_id` 时用作幂等键 |
| `source_name` | 否 | 来源名称 |
| `domain` | 否 | 领域标签 |
| `format` | 否 | 正文 text format，默认 `full_html` |
| `exp` | 否 | Unix 过期时间，过期拒绝 |
| `nonce` | 否（**强烈建议**） | 防重放；提供后 XMT 会记录并拒绝同一 `dx_developer_id` + `nonce` 的重复请求，直到 `exp`（或默认 10 分钟）过期 |

### 行为

- 创建或更新 `article` 节点：`status=1`，`field_trust_level=l2_enterprise`，关联已批准 `xmt_publisher`。
- 设置 `xmt_skip_syndicate`，避免回环 syndicate。
- 成功响应：`{"status":"ok","nid":123}`；验签失败：`403`。

### 本地测试

**XMT（模拟推送）：**

```bash
export XMT_DX_BRIDGE_SECRET='your-shared-secret'
cd /home/wwwroot/xmt
vendor/bin/drush --uri=xmt.pub xmt:dx-content-test \
  --developer=dx-hm-100 \
  --title='DrupalX推送的可信稿' \
  --body='<p>来自 DrupalX 的企业可信内容</p>' \
  --external-id=dx-media-demo-001
```

**DrupalX（推送到 XMT）：**

```bash
export XMT_DX_BRIDGE_SECRET='your-shared-secret'
cd /home/wwwroot/drupalX
vendor/bin/drush dx:xmt-push-content \
  --developer=dx-hm-100 \
  --title='从DrupalX推送' \
  --body='<p>DX push</p>' \
  --external-id=dx-media-demo-002 \
  --host=xmt.wsl
```

企业可信 feed：`/trusted/enterprise`（L2 内容）。
领域汇聚 feed：`/trusted/aggregate`（L0 内容）。

## DrupalX 自动推送（auto-push）

在 DrupalX 站点 `settings.php`（或 `settings.local.php`，**勿提交密钥**）配置：

| 键 | 说明 |
|----|------|
| `xmt_auto_push` | `TRUE` 启用 `dx_media` 发布/更新时自动 POST |
| `xmt_dx_bridge_secret` | 与 XMT `$settings['xmt_dx_bridge_secret']` / `XMT_DX_BRIDGE_SECRET` 一致 |
| `xmt_developer_id` | 已在 XMT 完成 claim 且 `approved` 的 `dx_developer_id` |
| `xmt_endpoint` | 默认 `http://127.0.0.1/api/xmt/v1/trusted-content`；生产改为 HTTPS 网关 URL |
| `xmt_host` | 请求 `Host` 头；本地 `xmt.wsl`，生产 `xmt.pub` |

**生产示例（无密钥）：**

```php
$settings['xmt_auto_push'] = TRUE;
$settings['xmt_dx_bridge_secret'] = getenv('XMT_DX_BRIDGE_SECRET') ?: '';
$settings['xmt_developer_id'] = 'dx-prod-xxx';
$settings['xmt_endpoint'] = 'https://xmt.pub/api/xmt/v1/trusted-content';
$settings['xmt_host'] = 'xmt.pub';
```

本地启用前须在 XMT 执行 claim（`xmt:dx-claim-test` 或 `POST /api/xmt/v1/dx-claim`）。发布 `dx_media` 后 XMT 应出现 L2 文章（`field_provenance_hash` = `dx:dx-media-{nid}`）。
