# DrupalX ↔ XMT 认证桥

## 首期：HMAC claim（非 OAuth）

1. 双方共享密钥：环境变量 `XMT_DX_BRIDGE_SECRET`（两端一致）
2. DrupalX 执行：`drush dx:xmt-issue-claim --developer=ID`（或 XMT 侧用测试命令模拟）
3. Claim JSON：`{publisher_name, credit_code, website, dx_developer_id, exp, nonce}`
4. 签名：`hash_hmac('sha256', body, secret)`，头 `X-XMT-Signature`
5. `POST /api/xmt/v1/dx-claim` → 创建/更新 `xmt_publisher` 为 `approved`

## 二期（未实现）

`POST /api/xmt/v1/trusted-content` 推送媒体节点。
