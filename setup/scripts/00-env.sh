#!/bin/bash
# Shared env for XMT Drupal multisite
export XMT_ROOT=/home/wwwroot/xmt
export XMT_WEB="$XMT_ROOT/web"
export MYSQL_HOST="${MYSQL_HOST:-192.168.16.1}"
export MYSQL_PORT="${MYSQL_PORT:-3306}"
export MYSQL_USER="${MYSQL_USER:-root}"
export MYSQL_PASSWORD="${MYSQL_PASSWORD:-Pmg@123789}"
export MYSQL_DATABASE="${MYSQL_DATABASE:-xmt_multi}"
export DRUSH="$XMT_ROOT/vendor/bin/drush"
export SITE_ADMIN_USER="${SITE_ADMIN_USER:-admin}"
export SITE_ADMIN_PASS="${SITE_ADMIN_PASS:-XmtAdmin@2026}"
export SITE_ADMIN_MAIL="${SITE_ADMIN_MAIL:-admin@xmt.pub}"

# site_key|domain|prefix|theme|site_name|profile_note
SITES_DEF=(
  "xmt|xmt.pub|xmt_|gavias_sancy|芯媒体 XMT|aggregate"
  "zhubao|zhubao.pub|zb_|gavias_sancy|珠宝媒体|jewelry"
  "airobotor|airobotor.com|ar_|gavias_sancy|AI机器人|ai_robot"
  "hmos|hm-os.com|hm_|gavias_sancy|鸿蒙OS|harmonyos"
  "kstudy|kstudy.com.cn|ks_|gavias_kiamo|AI教育 KStudy|ai_edu"
  "drupalcn|drupal.org.cn|do_|gavias_kiamo|Drupal中国|drupal"
  "itra|itra.com.cn|itra_|gavias_kiamo|ITRA中国|itra"
)

# Extra domains -> same site folder
# hm-os.cn -> hm-os.com
