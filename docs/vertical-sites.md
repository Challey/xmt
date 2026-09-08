# 垂直站：hm-os.com · airobotor.com

二者均为 **xmt.pub 垂直领域站**：独立域名与表前缀，内容由 Agent 按领域写入垂直站并同步 hub。

| 站点 | 领域 slug | 定位 |
|------|-----------|------|
| [hm-os.com](https://hm-os.com) | `harmonyos` | 鸿蒙 / OpenHarmony 开放信息、动态、开发与应用生态 |
| [airobotor.com](https://airobotor.com) | `robot`（兼容历史 `ai_robot`） | AI + 机器人前沿技术与行业资讯 |

主题：`gavias_sancy`。短闻入口：模块 block `xmt_short_news_entry`（`ShortNewsEntryBlock` 按 Host 注入默认 `?domain=`）。

## 自动采集

配置：[`agent/sources.yaml`](../agent/sources.yaml)

| key | site | domain | 信源规模（约） |
|-----|------|--------|----------------|
| `harmonyos` | hm-os.com | harmonyos | OpenHarmony、华为开发者、CNX、Android Authority、9to5Google、XDA、Solidot、爱范儿、少数派、雷锋网、InfoQ 等 |
| `ai_robot` | airobotor.com | robot | IEEE Spectrum Robotics/AI、Robot Report、Robohub、MIT News Robotics、Science Robotics、TechCrunch Robotics、雷锋网、极客公园、InfoQ 等 |

仅 **官方/成熟 RSS·Atom**，不做 HTML 全页抓取。探测失败的源在 `/admin/xmt/sources` 暂停。

### 运行示例

```bash
cd /home/wwwroot/xmt

# 仅鸿蒙垂直站
XMT_AGENT_FILTER=harmonyos XMT_MAX_PER_FEED=4 python3 agent/run_agent.py

# 仅机器人垂直站
XMT_AGENT_FILTER=ai_robot XMT_MAX_PER_FEED=4 python3 agent/run_agent.py

# 两者一起
XMT_AGENT_FILTER='harmonyos|ai_robot' XMT_MAX_PER_FEED=3 python3 agent/run_agent.py
```

Agent 会：

1. 写入对应垂直站（L0 汇聚）
2. 同步一份到 `xmt.pub`（同 domain / trust_level）
3. 用 `state.json` 去重，避免重复入库

建议 cron 每 30–60 分钟跑垂直过滤任务，与全量任务错峰。

## UI 与阅读体验

- **短闻**：垂直站首页放置「XMT Short News Entry」block → 默认 `/read?domain=harmonyos` 或 `robot`，进入信流。
- **样式**：与 hub 共用 `xmt_trust_ui/short_read`（含苹果风精修层 `short-read-apple.css` v3）。
- **信任字段**：垂直站后台信任级只读；L1/L2 仅在 xmt.pub hub 签发。

### 推荐首页结构（运营）

1. Hero / 品牌一句定位  
2. 短闻入口（信流主 CTA）  
3. 本站最新文章列表（Views 或主题区块）  
4. 外链：开发文档 / 官方门户（鸿蒙：OpenHarmony、华为开发者；机器人：IEEE、Robot Report）  
5. 页脚备案与 XMT 信任说明  

部署后：

```bash
vendor/bin/drush --uri=hm-os.com cr
vendor/bin/drush --uri=airobotor.com cr
# 确认短闻 block 在 content / 首页区域
```

## 筛选与兼容

- `?domain=robot` 同时命中历史 `ai_robot`（`DomainCatalog::expandFilter`）
- hub 短闻「更多」中可进入鸿蒙 / 机器人领域

## 验收清单

1. Agent 过滤跑通，垂直站与 xmt.pub 均有新 L0 文章  
2. 垂直站 `/read` 默认落在本领域，信流可上滑连读  
3. 白天/黑夜主题可用，卡片与详情阅读舒适（苹果风层级）  
4. 失败源可在运营台暂停，不阻塞整批任务  
