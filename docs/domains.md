# 短闻 · 热点行业分类方案

真源代码：[`DomainCatalog.php`](../web/modules/custom/xmt_trust_ui/src/DomainCatalog.php)  
信源配置：[`agent/sources.yaml`](../agent/sources.yaml)  
官媒频道（并行）：[`OfficialMediaChannels.php`](../web/modules/custom/xmt_trust_ui/src/OfficialMediaChannels.php)

## 目标

短闻不是「机器人 / 鸿蒙」两个竖条，而是覆盖 **2020s 最热硬科技与产业赛道** 的可信阅读目录：用户用领域芯片连刷，运营用同一套 slug 入库与统计。

## 五层分组

| 分组 | 定位 | 规范 slug |
|------|------|-----------|
| **硬科技热点** | 算力与智能主战场 | `ai` `chip` `robot` `cyber` `cloud` `quantum` |
| **产业升级** | 实体经济数字化 | `ev` `energy` `biotech` `space` `fintech` `enterprise` |
| **消费与互联** | 大众科技与生活方式 | `tech` `internet` `fashion` |
| **垂直站** | XMT 产品矩阵 | `harmonyos` `ai_edu` `jewelry` `drupal` `itra` |
| **财经时政** | 官媒延伸（有内容才显示） | `finance` `world` `society` `gov` `military` `sports` `auto` `property` `entertainment` |

虚拟聚合（仅筛选，不入库）：`domestic` → `gov` + `society` + `military`（主行「国内」）。

## 规范领域一览

### 硬科技热点

| slug | 中文 | 覆盖 |
|------|------|------|
| `ai` | 人工智能 | 大模型、Agent、生成式应用、多模态 |
| `chip` | 芯片半导体 | 先进制程、存储、EDA、设备材料、封测 |
| `robot` | 机器人 | 工业 / 人形 / 具身智能（兼容历史 `ai_robot`） |
| `cyber` | 网络安全 | 攻防、零信任、数据安全 |
| `cloud` | 云计算 | 公有云、边缘、开发者基础设施 |
| `quantum` | 量子科技 | 量子计算与通信（有源再亮） |

### 产业升级

| slug | 中文 | 覆盖 |
|------|------|------|
| `ev` | 智能汽车 | 新能源车、智驾、车规电子 |
| `energy` | 新能源 | 光伏、储能、氢能、电网 |
| `biotech` | 生物医药 | 创新药、器械、合成生物 |
| `space` | 商业航天 | 火箭、卫星互联网 |
| `fintech` | 金融科技 | 支付、数字资产、监管科技 |
| `enterprise` | 企业软件 | SaaS、低代码、数字化 |

### 消费与互联 / 垂直站

| slug | 中文 | 备注 |
|------|------|------|
| `tech` | 综合科技 | 通用科技媒体（兼容 `general`） |
| `internet` | 互联网 | 平台与内容经济 |
| `fashion` | 时尚 | L0 时尚 + 官媒时尚频道 |
| `harmonyos` | 鸿蒙 | 垂直站 hm-os.com |
| `ai_edu` | AI 教育 | kstudy |
| `jewelry` | 珠宝 | zhubao |
| `drupal` | Drupal | drupal.org.cn |
| `itra` | 越野跑 | itra.com.cn（国际站） |

### 财经时政

| slug | 中文 | 备注 |
|------|------|------|
| `finance` | 财经 | 民生与资本市场 |
| `world` | 国际 | 新华/中新/China Daily 国际 RSS |
| `domestic` | 国内（筛选） | 虚拟聚合：`gov`+`society`+`military` |
| `society` / `gov` / `military` … | 社会 / 时政 / 军事 | 官媒频道入库 slug |

## 规则

1. **入库 slug**：新采集用规范 slug（如 `robot` 而非 `ai_robot`）。
2. **筛选兼容**：`?domain=robot` 同时命中 `robot` 与历史 `ai_robot`（`DomainCatalog::expandFilter`）。
3. **UI**：短闻顶栏默认只显示 `PINNED_FILTERS`（**财经 · 科技 · 国内 · 国际** + AI/芯片/机器人/安全）+「更多」折叠；空域 URL 仍会把当前域提到主行。首页四栏同步展示这四类最热条目。
4. **合规**：仅官方 RSS/Atom；探测失败的源由运营台暂停，不 HTML 抓取。**证监会官网无公开 RSS**，监管要闻以人民网/新华网/中新网财经·证券转载覆盖；宏观民生数据用国家统计局 RSS（`stats_nbs`）。
5. **官媒**：`OfficialMediaChannels` 仍管 L1 频道；与上表 `civic`/`fashion`/`tech` 等 slug 对齐。

## 运营建议

```bash
# 热点硬科技批量入库（示例）
XMT_AGENT_FILTER='hot_ai|hot_chip|hot_cyber|hot_cloud|hot_quantum|ai_robot' XMT_MAX_PER_FEED=3 python3 agent/run_agent.py

# 产业赛道
XMT_AGENT_FILTER='hot_ev|hot_energy|hot_biotech|hot_space|hot_fintech|hot_enterprise' XMT_MAX_PER_FEED=3 python3 agent/run_agent.py
```

探测失败的 feed 在 `/admin/xmt/sources` 暂停；稳定后再开。

## 演进

- 二级标签（如 `ai/llm`）暂不入库，避免筛选爆炸；摘要/搜索承担细粒度。
- `quantum`：已加 Nature Physics / Phys.org / MIT News RSS（`hot_quantum`）。
- 垂直站域名 → 默认 domain 映射仍见 `ShortNewsEntryBlock::HOST_DOMAIN`。
