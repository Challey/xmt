#!/bin/bash
set -euo pipefail
# Must run as root (e.g. wsl.exe -u root -e bash /home/wwwroot/xmt/setup/install-nginx-as-root.sh)
cp -a /home/wwwroot/xmt/setup/nginx/*.conf /usr/local/nginx/conf/vhost/
cp -a /home/wwwroot/xmt/setup/apache/*.conf /usr/local/apache/conf/vhost/
HOSTS_LINE="127.0.0.1 xmt.wsl zhubao.wsl airobotor.wsl hmos.wsl kstudy.wsl drupalcn.wsl itra.wsl xmt.pub zhubao.pub airobotor.com hm-os.com hm-os.cn kstudy.com.cn drupal.org.cn itra.com.cn"
grep -q 'xmt.wsl' /etc/hosts || echo "$HOSTS_LINE" >> /etc/hosts
mkdir -p /home/wwwlogs
touch /home/wwwlogs/xmt-agent.log
chown www:www /home/wwwlogs/xmt-agent.log || true
/usr/local/nginx/sbin/nginx -t && /usr/local/nginx/sbin/nginx -s reload
/usr/local/apache/bin/httpd -k graceful
echo "Nginx+Apache XMT vhosts installed"
