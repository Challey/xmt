# 生产服务器 B 同步清单

本机 / GitHub 为真源；B 只部署。

```bash
# 在服务器 B
cd /home/wwwroot/xmt
git remote -v   # 应为 Challey/xmt
git pull origin main
composer install --no-dev -o
vendor/bin/drush --uri=xmt.pub updatedb -y
vendor/bin/drush --uri=xmt.pub en xmt_trust xmt_publisher xmt_trust_ui xmt_dx_bridge -y
vendor/bin/drush --uri=xmt.pub cr
```

勿在 B 上直接改业务代码。密钥与 `settings.php` 留在服务器本地。
