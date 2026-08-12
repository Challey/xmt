# 架构

```
Agent(RSS) ──L0──► 垂直站 / xmt.pub
编辑 CMS ──L1──► xmt.pub（官方主体）
企业用户 ──L2──► xmt.pub（已认证主体）
DrupalX claim ──► xmt_publisher(approved)
```

- 代码真源：本机 `/home/wwwroot/xmt` → GitHub `Challey/xmt`
- 生产服务器 B：`git pull` + composer + drush（见 `docs/deploy-server-b.md`）
- 多站点表前缀：字符串前缀（D11.4）；用户表各站独立
- 信任模块仅需在 **xmt.pub** 完整启用；垂直站可只读展示信任字段
