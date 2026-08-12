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
