# 生产服务器 B 同步清单

本机 `/home/wwwroot/xmt` 与 GitHub `main` 为真源；**Server B** 只部署、不在 B 上改业务代码。

## 运维入口（主机与 SSH）

生产主机、SSH 选项、Git 远程等写在 **`/home/challey/ops/projects/xmt/hosts.env`**（`chmod 600`，勿提交仓库）。示例见同目录 `hosts.env.example`。

```bash
/home/challey/ops/projects/xmt/deploy-prod.sh
/home/challey/ops/projects/xmt/deploy-prod.sh --dry-run
```

## 推荐流程

1. **本机验收**（bootstrap、xmt 模块、`/trusted`、可选 `xmt:dx-content-test`）通过后，再同步 B。
2. **B 上部署**  
   - 优先：`git pull`（B 需能访问 GitHub；SSH 密钥缺失时用 HTTPS 或本机 `rsync`）。  
   - `deploy-prod.sh` 在 Git 失败时会从本机 `rsync`（排除 `vendor`、各站 `settings.php`、`files`）。
3. **Composer / Drush**（在 B 的 `/home/wwwroot/xmt`）：

```bash
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev -o --no-interaction
vendor/bin/drush --uri=xmt.pub updatedb -y
vendor/bin/drush --uri=xmt.pub en xmt_publisher xmt_trust xmt_trust_ui xmt_dx_bridge xmt_syndicate -y
vendor/bin/drush --uri=xmt.pub cr
```

## Web 根目录

Composer 布局文档根为 **`/home/wwwroot/xmt/web`**。Nginx / Apache 的 `DocumentRoot` 与 `open_basedir` 须指向 `web`（模板见 `setup/nginx/xmt.pub.conf`）。改 vhost 后 reload。

## `settings.php` 与数据库

- **禁止**用本机 WSL 的 `settings.php` 覆盖 B 上的生产库账号。  
- 若 B 上尚无 `web/sites/xmt.pub/settings.php`，可从 B 上已有站点推断 **RDS 主机**（例如同机 `drupalX` 的 `web/sites/default/settings.php` 中 `$db_host`），再为 `xmt.pub` 单独建库/表前缀或专用库；密码仅保存在 B 本地 `settings.php`。  
- 旧版扁平目录备份在 `/home/wwwroot/xmt.bak.*` 时，可从 `sites/default/settings.php` 提取 `$databases['default']['default']`（勿匹配文件开头的 `$databases = []` 占位）。

## 密钥

`xmt_dx_bridge_secret` 等通过 B 上环境变量或 `settings.php` 配置，勿写入 Git。

## 常见阻塞

| 现象 | 说明 |
|------|------|
| GitHub clone 失败 | B 无 GitHub SSH；用 HTTPS 或本机 `rsync` + 远程 `composer install`。 |
| HTTP 500 / 缺字段插件 | B 仍指向旧站库（如 kstudy）时，库内 enabled 模块与当前 `composer.json` 不一致；需专用 XMT 库或补全旧 contrib 模块。 |
| DNS | 公网访问依赖 `xmt.pub` 解析到 B；本机 HTTP 测试可用 `curl -H 'Host: xmt.pub' http://127.0.0.1/...`（在 B 上执行）。 |
