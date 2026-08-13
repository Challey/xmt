# HTTPS（生产 Server B）

TLS 证书与 Nginx SSL **只在 B 上**维护，不随 `pack-deploy` / Git 同步私钥或 `.cer` 文件。

- **B 公网 IP**：`47.113.217.2`（在 B 上 `curl -s ifconfig.me` 确认）
- **Web 根**：`/home/wwwroot/xmt/web`
- **证书目录**（与 topstar / drupalX 一致）：`/usr/local/nginx/conf/ssl/www.xmt.pub/`
  - `fullchain.cer`
  - `www.xmt.pub.key`（权限 `600`，勿提交 Git）
- **Nginx vhost（线上）**：`/usr/local/nginx/conf/vhost/www.xmt.pub.conf`
- **签发工具**：`/usr/local/acme.sh/acme.sh`（cron：`25 0 * * *`，自动续期）

## DNS 前置条件（Let's Encrypt HTTP-01）

| 域名 | 须指向 B |
|------|-----------|
| `xmt.pub` | A → `47.113.217.2`（无公网 A 记录则无法签发） |
| `www.xmt.pub` | A → `47.113.217.2` |

垂直域是否在本机签证书，以 B 上解析为准：

```bash
/home/challey/ops/projects/xmt/setup-ssl-b.sh --check-dns
```

未指向 B 的域名需在阿里云 HiChina 改 A 记录后再签发。

## 一键签发 + 启用 HTTPS

```bash
/home/challey/ops/projects/xmt/setup-ssl-b.sh
/home/challey/ops/projects/xmt/setup-ssl-b.sh --check-dns
/home/challey/ops/projects/xmt/setup-ssl-b.sh --issue-only
```

脚本会：部署带 `/.well-known` 的 HTTP vhost → `acme.sh --issue`（Let's Encrypt）→ 443 vhost → `nginx -t && reload`。

仓库 Nginx **参考模板**：`setup/nginx/xmt.pub.conf`（不自动覆盖线上）。

## 手动续期 / 排查

```bash
ssh root@47.113.217.2
/usr/local/acme.sh/acme.sh --renew -d www.xmt.pub --force
/usr/local/acme.sh/acme.sh --list | grep xmt
/usr/local/nginx/sbin/nginx -t && /usr/local/nginx/sbin/nginx -s reload
tail -50 /usr/local/acme.sh/acme.sh.log
```

## 与代码部署

- `deploy-prod.sh` / `pack-deploy.sh`：Drupal 代码，**不含** SSL。
- 换证后 reload Nginx 即可。

## 验收（B 上）

```bash
curl -sIk https://127.0.0.1/ -H 'Host: xmt.pub' --resolve xmt.pub:443:127.0.0.1 | head -15
curl -sk https://xmt.pub/ --resolve xmt.pub:443:127.0.0.1 | grep -oE '可信|官方|xmt-trust-home|芯媒体' | head
curl -sI http://127.0.0.1/ -H 'Host: xmt.pub' | head -10
```

公网：`curl -sIk https://xmt.pub/ | head -10`
