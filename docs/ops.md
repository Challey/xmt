# 运维说明

## 本机

- Web root: `/home/wwwroot/xmt/web`
- DB: 见各站 `settings.php`（不入库）
- Drush: `vendor/bin/drush --uri=xmt.pub <cmd>`
- Agent: `python3 agent/run_agent.py`（cron 见 crontab）
- Hosts / 宿主访问: `setup/HOST-ACCESS.md`

## 管理员

各站约定：`admin`（密码仅存于部署环境，勿提交仓库）

## 缓存

```bash
vendor/bin/drush --uri=xmt.pub cr
```

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
- 垂直站文章默认 **L0 汇聚**（`xmt_trust_entity_presave`）；编辑表单上信任字段对非 hub 为**只读**（仅 `xmt.pub` 可改 L1/L2）。
- Agent / `xmt_syndicate` 同步到 hub 时会带上 `trust_level` 与 `publisher_id`（垂直站一般为 L0）。
