# 运维说明

## 本机

- Web root: `/home/wwwroot/xmt/web`
- DB: 见各站 `settings.php`（不入库）
- Drush: `vendor/bin/drush --uri=xmt.pub <cmd>`
- Agent: `python3 agent/run_agent.py`（cron 见 crontab）
- Hosts / 宿主访问: `setup/HOST-ACCESS.md`

## 管理员

## HTTPS（Server B）

证书与 Nginx 443 仅在生产机维护，见 [https.md](https.md)。签发：`/home/challey/ops/projects/xmt/setup-ssl-b.sh`（需 DNS 指向 B）。代码部署不含 SSL。


各站约定：`admin`（密码仅存于部署环境，勿提交仓库）

## 缓存

```bash
vendor/bin/drush --uri=xmt.pub cr
```

## Trust 栈引导

幂等脚本（亦可由 `bootstrap-all.sh` 调用）：

```bash
bash setup/scripts/80-trust-stack.sh
# 或
bash scripts/80-trust-stack.sh
```

作用：hub 启用 `xmt_publisher` / `xmt_trust` / `xmt_trust_ui` / `xmt_dx_bridge`；垂直站启用前三者；`xmt_trust_ensure_fields()` / `xmt_trust_ensure_roles()`；在 `gavias_sancy` 放置首页可信分区 block。

## 溯源审计导出

- 列表：`/admin/xmt/provenance`（需 `administer xmt trust`）；可按 Trust level / Publisher 筛选并分页
- CSV：`/admin/xmt/provenance/export.csv` 或页面 **Export CSV**（带当前筛选）
- JSON：`/admin/xmt/provenance/export.json` 或页面 **Export JSON**
- CLI：
  ```bash
  vendor/bin/drush --uri=xmt.pub xmt:provenance-export --limit=500 --output=/tmp/audit.csv
  vendor/bin/drush --uri=xmt.pub xmt:provenance-export --format=json --trust-level=l2_enterprise --output=/tmp/audit.json
  vendor/bin/drush --uri=xmt.pub xmt:provenance-export --publisher=1 --output=/tmp/pub1.csv
  ```

## 发布主体页

- 公开：`/publisher/{id}`（approved）列出该主体最近最多 20 篇 L1/L2 文章
- 文章页 L1/L2 显示「Published by」归因链接

## 首页可信分区（xmt.pub）

- Block `xmt_trust_home_columns`（标签「信媒体 · 可信分区」）置于 `gavias_sancy` 主题 `content` 区，weight `-50`。
- 三列：官方可信（L1）、企业可信（L2）、领域汇聚（L0），各最多 5 条；「更多」链至 `/trusted/official`、`/trusted/enterprise`、`/trusted/aggregate`。
- 放置（本地或 B 机）：
  ```bash
  vendor/bin/drush --uri=xmt.pub php:eval "
  \$s = \Drupal::entityTypeManager()->getStorage('block');
  foreach (\$s->loadByProperties(['plugin' => 'xmt_trust_home_columns']) as \$b) { \$b->delete(); }
  \$s->create([
    'id' => 'gavias_sancy_xmt_trust_home',
    'plugin' => 'xmt_trust_home_columns',
    'region' => 'content',
    'theme' => 'gavias_sancy',
    'weight' => -50,
    'status' => TRUE,
    'settings' => ['label' => '信媒体 · 可信分区', 'label_display' => 'visible'],
  ])->save();
  echo 'OK';
  "
  vendor/bin/drush --uri=xmt.pub cr
  ```

## 机器可读可信 feed

- JSON：`/api/xmt/v1/trusted`（默认 L1+L2）、`/api/xmt/v1/trusted/l1_official`、`…/l2_enterprise`、`…/l0_aggregate`
- RSS：`/trusted.xml`、`/trusted/l1_official.xml`（亦支持 `official` / `enterprise` / `aggregate` 别名）
- 权限：`access content`；最多 30 条，含 title/url/trust/publisher/provenance/summary
- HTML 可信页导航含 JSON / RSS 链接

## Article 字段（含 body）

新装或 `site:install` 后，**每个 URI** 需确保 article 含 syndication 字段及标准 **`body`**（`text_with_summary`），否则可信内容推送可能 400：

```bash
vendor/bin/drush --uri=xmt.pub php:script setup/scripts/ensure_fields.php
# 或（含 trust 字段 + body）：
vendor/bin/drush --uri=xmt.pub php:eval 'xmt_trust_ensure_fields(); echo "ok\n";'
```

多站批量见 `setup/scripts/60-content-fields.sh`。

## 垂直站（zhubao 等）

- 启用 `xmt_trust`、`xmt_publisher`（可选 `xmt_trust_ui` 展示徽章）；`vendor/bin/drush --uri=zhubao.wsl en xmt_trust xmt_publisher xmt_trust_ui -y` 后执行 `php:eval` 调用 `xmt_trust_ensure_fields()` / `xmt_trust_ensure_roles()`。
- **批量引导**（非 hub 六站）：
  ```bash
  bash setup/scripts/65-trust-vertical.sh
  ```
- 垂直站文章默认 **L0 汇聚**（`xmt_trust_entity_presave`）；编辑表单上信任字段对非 hub 为**只读**（仅 `xmt.pub` 可改 L1/L2）。
- Agent / `xmt_syndicate` 同步到 hub 时会带上 `trust_level` 与 `publisher_id`（垂直站一般为 L0）。

## 溯源审计导出

- 后台：**Content → Provenance audit**（`/admin/xmt/provenance`），点击 **Export CSV**。
- 直接下载：`/admin/xmt/provenance/export`（需 `administer xmt trust`）；可选查询参数 `limit`（1–5000，默认 500）、`trust_level`（`l0_aggregate` / `l1_official` / `l2_enterprise`）。
- Drush：
  ```bash
  vendor/bin/drush --uri=xmt.pub xmt:provenance-export --limit=500 --output=/tmp/xmt-provenance.csv
  vendor/bin/drush --uri=xmt.pub xmt:provenance-export --trust-level=l2_enterprise
  ```

## 可信流 RSS / JSON

公开订阅（无需登录），与 HTML 页 `/trusted*` 同级过滤：

| 过滤 | RSS | JSON |
|------|-----|------|
| L1+L2 | `/trusted/feed.rss` | `/trusted/feed.json` |
| L1 官方 | `/trusted/official/feed.rss` | `/trusted/official/feed.json` |
| L2 企业 | `/trusted/enterprise/feed.rss` | `/trusted/enterprise/feed.json` |
| L0 汇聚 | `/trusted/aggregate/feed.rss` | `/trusted/aggregate/feed.json` |

每条含 `trust_level`、`trust_label`、`publisher`、`provenance_hash`、`source_url` 等。可选 `?limit=50`（1–100）。

验收：

```bash
curl -sH 'Host: xmt.pub' 'http://127.0.0.1/trusted/feed.json?limit=5' | head -c 500
curl -sH 'Host: xmt.pub' 'http://127.0.0.1/trusted/enterprise/feed.rss?limit=5' | head -20
```

## 发布主体公开页

- URL：`/publisher/{id}`（仅 `approved` 主体对公众可见）
- 展示信任徽章、认证状态、官网、企业注册号及该主体最近文章
- 验收：`curl -sH 'Host: xmt.pub' http://127.0.0.1/publisher/1 | grep -oE 'xmt-publisher|xmt-trust-badge|Published articles' | head`

## 认证主体目录

- URL：`/publishers`（官方 L1 + 企业 L2 分区，链至 `/publisher/{id}`）
- 单主体订阅：`/publisher/{id}/feed.rss`、`/publisher/{id}/feed.json`（`?limit=` 1–100）
- 验收：
  ```bash
  curl -sH 'Host: xmt.pub' http://127.0.0.1/publishers | grep -oE 'xmt-publishers-directory|Official|Enterprise' | head
  curl -sH 'Host: xmt.pub' 'http://127.0.0.1/publisher/1/feed.json?limit=3' | head -c 400
  ```

## 部署验收脚本

一键检查可信页、RSS/JSON、主体目录、sitemap 及 Drush 导出：

```bash
bash setup/scripts/75-verify-trust.sh
HOST=xmt.pub BASE=https://xmt.pub DRUSH_URI=xmt.pub bash setup/scripts/75-verify-trust.sh
```

## 可信站点地图

- URL：`/trusted/sitemap.xml`
- 含 `/trusted*`、`/publishers`、各已认证 `/publisher/{id}`、L1/L2 文章，**默认含 L0 汇聚**（约为 `limit` 一半，最多 50 条）
- 可选：`?limit=200`、`?include_l0=0` 排除 L0、`?l0_limit=30` 指定 L0 上限
- **robots.txt**（仅 xmt.pub hub）：自动追加 `Sitemap: https://xmt.pub/trusted/sitemap.xml`
