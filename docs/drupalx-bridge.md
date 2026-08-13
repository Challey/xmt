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

### 本地测试（XMT）

```bash
export XMT_DX_BRIDGE_SECRET='your-shared-secret'
cd /home/wwwroot/xmt
vendor/bin/drush --uri=xmt.pub xmt:dx-claim-test --developer=dx-test-001 --name='Test Enterprise'
```

## 二期（未实现）

`POST /api/xmt/v1/trusted-content` 推送媒体节点。
