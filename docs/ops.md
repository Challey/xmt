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

## 垂直站（zhubao 等）

- 启用 `xmt_trust`、`xmt_publisher`（可选 `xmt_trust_ui` 展示徽章）；`vendor/bin/drush --uri=zhubao.wsl en xmt_trust xmt_publisher xmt_trust_ui -y` 后执行 `php:eval` 调用 `xmt_trust_ensure_fields()` / `xmt_trust_ensure_roles()`。
- 垂直站文章默认 **L0 汇聚**（`xmt_trust_entity_presave`）；编辑表单上信任字段对非 hub 为**只读**（仅 `xmt.pub` 可改 L1/L2）。
- Agent / `xmt_syndicate` 同步到 hub 时会带上 `trust_level` 与 `publisher_id`（垂直站一般为 L0）。
