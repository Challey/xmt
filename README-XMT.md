# XMT Drupal 11.4 多站点

路径：`/home/wwwroot/xmt`  
核心：**Drupal 11.4.4** + **Drush 13.7.6**  
数据库：`192.168.16.1` / `xmt_multi`（各站表前缀区分）

## 站点

| 域名 | 目录 | 前缀 | 主题 | 领域 |
|------|------|------|------|------|
| xmt.pub | sites/xmt.pub | xmt_ | gavias_sancy | 全行业聚合 |
| zhubao.pub | sites/zhubao.pub | zb_ | gavias_sancy | 珠宝 |
| airobotor.com | sites/airobotor.com | ar_ | gavias_sancy | AI 机器人 |
| hm-os.com / hm-os.cn | sites/hm-os.com | hm_ | gavias_sancy | 鸿蒙 |
| kstudy.com.cn | sites/kstudy.com.cn | ks_ | gavias_kiamo | AI 教育 |
| drupal.org.cn | sites/drupal.org.cn | do_ | gavias_kiamo | Drupal 中国 |
| itra.com.cn | sites/itra.com.cn | itra_ | gavias_kiamo | ITRA |

本地测试 Host：`xmt.wsl`、`zhubao.wsl`、`airobotor.wsl`、`hmos.wsl`、`kstudy.wsl`、`drupalcn.wsl`、`itra.wsl`

## 管理员

- 用户：`admin`
- 密码：`XmtAdmin@2026`

说明：Drupal 11.4 起数据库 `prefix` 仅支持字符串，无法再按表映射 `shared_` 用户表。各站账号密码相同，但用户表彼此独立。

## 共享用户限制（表前缀）

各站 `web/sites/*/settings.php` 使用**字符串前缀**（如 `xmt_`、`zb_`），不是按表映射的数组。Drupal 11.4 已移除 per-table prefix 数组，因此无法把 `users` 等表挂到统一的 `shared_` 前缀。

影响：

- 各站管理员账号彼此独立，但约定同一凭据：`admin` / `XmtAdmin@2026`
- 用户行在各站表中重复存在（密码哈希相同），不是真正的跨站单点登录
- 内容聚合靠 Agent：按领域写入垂直站，并同步发布到 `xmt.pub`


## 内容采集 Agent

- 配置：[`agent/sources.yaml`](agent/sources.yaml)
- 运行：`python3 /home/wwwroot/xmt/agent/run_agent.py`
- 日志：`/home/wwwlogs/xmt-agent-run.log`
- Cron：每 30 分钟（按领域 RSS → 垂直站 + xmt.pub）

## 常用命令

```bash
cd /home/wwwroot/xmt
vendor/bin/drush --uri=xmt.pub status
vendor/bin/drush --uri=zhubao.pub cr
bash setup/scripts/bootstrap-all.sh   # 幂等引导（已安装会跳过）
```

## 重新安装主题/模块

```bash
bash setup/scripts/10-extract-themes.sh
bash setup/scripts/40-themes-modules.sh
```

## 引导脚本

`scripts/` 为 `setup/scripts/` 的符号链接目录，例如：

```bash
bash /home/wwwroot/xmt/scripts/bootstrap-all.sh
```
