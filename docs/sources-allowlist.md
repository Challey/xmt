# 信源调研与 allowlist（合规：仅官方 RSS/Atom）

原则：**不 HTML 抓取**；仅接入站点公开发布的 RSS/Atom。L1 仅官媒 allowlist；十年以上专业媒体进 **L0 汇聚**。

探测环境：生产 Server B（阿里云，`47.113.217.2`），2026-08-13。

---

## 1. 国内 / 香港权威官网

| 机构 | 官网 | 官方订阅入口 | B 可达 | 入库策略 |
|------|------|--------------|--------|----------|
| 人民网 | people.com.cn | `/rss/*.xml` | ✅ | **L1** |
| 新华网 | news.cn / xinhuanet.com | `/…/news_*.xml` | ✅ | **L1** |
| China Daily | chinadaily.com.cn | `/rss/*_rss.xml` | ✅ | **L1** |
| 中国新闻网 | chinanews.com.cn | [RSS 中心](https://www.chinanews.com.cn/rss/) | ✅ | **L1**（本轮新增） |
| 中国网 | china.com.cn | 宣称 RSS | ❌ 返回 HTML | 未接入 |
| 光明网 / 经济日报 / 央广网 | — | 公开 XML 失效或非 feed | ❌ | 未接入 |
| **凤凰网**（港资新媒体） | ifeng.com | 见下方「凤凰网分频道实测」 | 订阅页 ✅ / XML ❌ | **暂未接入**；`agent/probe_ifeng.py` 可复测 |
| 香港电台 RTHK | rthk.hk | 有 RSS 路径 | ❌ Network unreachable | 待出网 |
| 香港政府新闻网 / info.gov.hk | news.gov.hk | 旧 RSS 404 | ❌ | 待确认新入口 |
| SCMP | scmp.com | 有 feed | ❌ Network unreachable | 待出网 |

### 已接入中新网频道（示例）

`china.xml` · `finance.xml` · `world.xml` · `society.xml` · `sports.xml` · `ent.xml` · `culture.xml` · `life.xml` · `scroll-news.xml` · `importnews.xml` · `edu.xml` · `fz.xml` · `dwq.xml`

---

## 2. 十年以上可信科技 / 时尚媒体 → L0

均选 **创立逾十年**、仍提供官方 RSS、且 B 探测为有效 XML 的源（摘录）：

### 科技（`general_tech` → domain=`tech`，L0）

| 媒体 | 创立约 | Feed | B |
|------|--------|------|---|
| TechCrunch | 2005 | techcrunch.com/feed/ | ✅ |
| Wired | 1993 | wired.com/feed/rss | ✅ |
| Ars Technica | 1998 | feeds.arstechnica.com/... | ✅ |
| The Verge | 2011 | theverge.com/rss/index.xml | ✅ |
| MIT Technology Review | 1899 | technologyreview.com/feed/ | ✅ |
| IEEE Spectrum | 1964 | spectrum.ieee.org/feeds/feed.rss | ✅ |
| CNET | 1994 | cnet.com/rss/news/ | ✅ |
| Engadget | 2004 | engadget.com/rss.xml | ✅ |
| 爱范儿 | 2008 | ifanr.com/feed | ✅ |
| 少数派 | 2012 | sspai.com/feed | ✅ |
| Solidot | 2005 | solidot.org/index.rss | ✅ |
| InfoQ 中文 | 2007 | infoq.cn/feed | ✅ |
| 雷锋网 | 2011 | leiphone.com/feed | ✅ |
| 极客公园 | 2010 | geekpark.net/rss | ✅ |

未接入：虎嗅（超时）、36氪（疑似非标准 feed）、Business of Fashion（403）。

### 时尚（`trusted_fashion` → domain=`fashion`，L0）

| 媒体 | 创立约 | Feed | B |
|------|--------|------|---|
| Vogue | 1892 | vogue.com/feed/rss | ✅ |
| WWD | 1910 | wwd.com/feed/ | ✅ |
| Elle | 1945 | elle.com/rss/all.xml/ | ✅ |
| Harper's Bazaar | 1867 | harpersbazaar.com/rss/all.xml/ | ✅ |
| GQ | 1931 | gq.com/feed/rss | ✅ |
| Hypebeast | 2005 | hypebeast.com/feed | ✅ |
| 数英网 | 2009+ | digitaling.com/rss | ✅ |

未接入：BoF（403）、国内 Vogue/Elle/GQ 中文站无可用官方 XML。

---

## 3. 运行

```bash
# 仅官媒 L1
XMT_AGENT_FILTER=official_media XMT_MAX_PER_FEED=3 python3 agent/run_agent.py

# 仅 L0 科技+时尚
XMT_AGENT_FILTER='general_tech|trusted_fashion' XMT_MAX_PER_FEED=3 python3 agent/run_agent.py
```

前台：L1 → `/official-media`；L0 → `/trusted/aggregate`。
