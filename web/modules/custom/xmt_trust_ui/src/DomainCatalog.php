<?php

namespace Drupal\xmt_trust_ui;

/**
 * 短闻 / 信源领域分类（热点行业目录）。
 *
 * 单一真源：标签、分组、别名。官媒频道见 OfficialMediaChannels。
 */
final class DomainCatalog {

  /**
   * 分组：硬科技热点 → 产业升级 → 消费互联 → 垂直站 → 综合/时政延伸.
   */
  public const GROUPS = [
    'hot_tech' => '硬科技热点',
    'industry' => '产业升级',
    'consumer' => '消费与互联',
    'vertical' => '垂直站',
    'civic' => '财经时政',
  ];

  /**
   * 规范领域 slug => [label, group, aliases[]].
   *
   * 别名用于兼容历史入库（如 ai_robot）；筛选规范 slug 时 OR 匹配别名。
   */
  public const DOMAINS = [
    // —— 硬科技热点 ——
    'ai' => [
      'label' => '人工智能',
      'group' => 'hot_tech',
      'aliases' => [],
      'hint' => '大模型、生成式 AI、Agent、算力应用',
    ],
    'chip' => [
      'label' => '芯片半导体',
      'group' => 'hot_tech',
      'aliases' => ['semiconductor'],
      'hint' => '晶圆、先进制程、存储、EDA、设备材料',
    ],
    'robot' => [
      'label' => '机器人',
      'group' => 'hot_tech',
      'aliases' => ['ai_robot'],
      'hint' => '工业机器人、具身智能、人形机器人',
    ],
    'cyber' => [
      'label' => '网络安全',
      'group' => 'hot_tech',
      'aliases' => [],
      'hint' => '安全运营、漏洞、零信任、数据安全',
    ],
    'cloud' => [
      'label' => '云计算',
      'group' => 'hot_tech',
      'aliases' => [],
      'hint' => '公有云、边缘、数据中心、开发者基础设施',
    ],
    'quantum' => [
      'label' => '量子科技',
      'group' => 'hot_tech',
      'aliases' => [],
      'hint' => '量子计算、量子通信',
    ],
    // —— 产业升级 ——
    'ev' => [
      'label' => '智能汽车',
      'group' => 'industry',
      'aliases' => [],
      'hint' => '新能源车、智驾、车规芯片、出行',
    ],
    'energy' => [
      'label' => '新能源',
      'group' => 'industry',
      'aliases' => [],
      'hint' => '光伏、储能、氢能、电网',
    ],
    'biotech' => [
      'label' => '生物医药',
      'group' => 'industry',
      'aliases' => ['bio'],
      'hint' => '创新药、器械、合成生物',
    ],
    'space' => [
      'label' => '商业航天',
      'group' => 'industry',
      'aliases' => [],
      'hint' => '火箭、卫星互联网、太空经济',
    ],
    'fintech' => [
      'label' => '金融科技',
      'group' => 'industry',
      'aliases' => [],
      'hint' => '支付、数字货币、监管科技',
    ],
    'enterprise' => [
      'label' => '企业软件',
      'group' => 'industry',
      'aliases' => [],
      'hint' => 'SaaS、低代码、数字化转型',
    ],
    // —— 消费与互联 ——
    'tech' => [
      'label' => '综合科技',
      'group' => 'consumer',
      'aliases' => ['general'],
      'hint' => '消费电子与综合科技资讯',
    ],
    'internet' => [
      'label' => '互联网',
      'group' => 'consumer',
      'aliases' => [],
      'hint' => '平台经济、内容、社交电商',
    ],
    'fashion' => [
      'label' => '时尚',
      'group' => 'consumer',
      'aliases' => [],
      'hint' => '时装、美妆、生活方式',
    ],
    // —— 垂直站（产品矩阵）——
    'harmonyos' => [
      'label' => '鸿蒙',
      'group' => 'vertical',
      'aliases' => [],
      'hint' => 'OpenHarmony / 鸿蒙生态',
    ],
    'ai_edu' => [
      'label' => 'AI 教育',
      'group' => 'vertical',
      'aliases' => [],
      'hint' => '智能教育、在线学习',
    ],
    'jewelry' => [
      'label' => '珠宝',
      'group' => 'vertical',
      'aliases' => [],
      'hint' => '珠宝钻石产业',
    ],
    'drupal' => [
      'label' => 'Drupal',
      'group' => 'vertical',
      'aliases' => [],
      'hint' => '开源 CMS / Drupal 生态',
    ],
    'itra' => [
      'label' => '越野跑',
      'group' => 'vertical',
      'aliases' => [],
      'hint' => 'ITRA / 越野耐力赛事',
    ],
    // —— 财经时政（官媒等延伸，筛选有内容才显示）——
    'finance' => [
      'label' => '财经',
      'group' => 'civic',
      'aliases' => [],
      'hint' => '物价就业、资本市场、监管政策与民生财经',
    ],
    'world' => [
      'label' => '国际',
      'group' => 'civic',
      'aliases' => ['intl', 'international'],
      'hint' => '国际重大事件与全球要闻',
    ],
    'society' => [
      'label' => '社会',
      'group' => 'civic',
      'aliases' => [],
      'hint' => '社会民生',
    ],
    'gov' => [
      'label' => '时政',
      'group' => 'civic',
      'aliases' => [],
      'hint' => '政务政策',
    ],
    'military' => [
      'label' => '军事',
      'group' => 'civic',
      'aliases' => [],
      'hint' => '国防军事',
    ],
    'sports' => [
      'label' => '体育',
      'group' => 'civic',
      'aliases' => [],
      'hint' => '竞技体育',
    ],
    'auto' => [
      'label' => '汽车',
      'group' => 'civic',
      'aliases' => [],
      'hint' => '汽车产业（官媒频道）',
    ],
    'property' => [
      'label' => '房产',
      'group' => 'civic',
      'aliases' => [],
      'hint' => '房地产',
    ],
    'entertainment' => [
      'label' => '文娱',
      'group' => 'civic',
      'aliases' => [],
      'hint' => '文化娱乐',
    ],
  ];

  /**
   * 短闻顶栏优先展示的分组顺序.
   */
  public const FILTER_GROUP_ORDER = [
    'hot_tech',
    'industry',
    'consumer',
    'vertical',
    'civic',
  ];

  /**
   * 短闻默认露出的最热门领域（其余收入「更多」）.
   *
   * 顺序即主行展示顺序；无内容的 slug 自动跳过。
   * 国内为虚拟聚合（见 BUNDLES），国际为入库 domain `world`。
   */
  public const PINNED_FILTERS = [
    'finance',
    'tech',
    'domestic',
    'world',
    'ai',
    'chip',
    'robot',
    'cyber',
  ];

  /**
   * 虚拟筛选聚合：URL `?domain=domestic` 等，本身不入库。
   *
   * @var array<string, array{label: string, group: string, members: list<string>, hint: string}>
   */
  public const BUNDLES = [
    'domestic' => [
      'label' => '国内',
      'group' => 'civic',
      'members' => ['gov', 'society', 'military'],
      'hint' => '时政、社会与国内重大事件',
    ],
  ];

  /**
   * @return array<string, string>
   *   slug => label（含别名指向规范 label）.
   */
  public static function labels(): array {
    $out = [];
    foreach (self::DOMAINS as $slug => $meta) {
      $out[$slug] = $meta['label'];
      foreach ($meta['aliases'] as $alias) {
        $out[$alias] = $meta['label'];
      }
    }
    foreach (self::BUNDLES as $slug => $meta) {
      $out[$slug] = $meta['label'];
    }
    // Official media channel labels fill gaps.
    foreach (OfficialMediaChannels::CHANNELS as $code => $label) {
      $out[$code] = $out[$code] ?? $label;
    }
    return $out;
  }

  /**
   * Human label for a stored domain slug.
   */
  public static function label(string $slug): string {
    if ($slug === '') {
      return '';
    }
    $labels = self::labels();
    return $labels[$slug] ?? $slug;
  }

  /**
   * Whether slug is known (canonical, alias, bundle, or official media).
   */
  public static function isValid(?string $slug): bool {
    if ($slug === NULL || $slug === '') {
      return FALSE;
    }
    if (isset(self::DOMAINS[$slug]) || isset(self::BUNDLES[$slug])) {
      return TRUE;
    }
    foreach (self::DOMAINS as $meta) {
      if (in_array($slug, $meta['aliases'], TRUE)) {
        return TRUE;
      }
    }
    return OfficialMediaChannels::isValid($slug);
  }

  /**
   * Expand a filter slug to all DB values that should match (canonical + aliases).
   *
   * Bundles expand to member domains (recursively for aliases).
   *
   * @return list<string>
   */
  public static function expandFilter(string $slug): array {
    if ($slug === '') {
      return [];
    }
    if (isset(self::BUNDLES[$slug])) {
      $out = [];
      foreach (self::BUNDLES[$slug]['members'] as $member) {
        foreach (self::expandFilter($member) as $value) {
          $out[] = $value;
        }
      }
      return array_values(array_unique($out));
    }
    if (isset(self::DOMAINS[$slug])) {
      return array_values(array_unique(array_merge([$slug], self::DOMAINS[$slug]['aliases'])));
    }
    foreach (self::DOMAINS as $canonical => $meta) {
      if (in_array($slug, $meta['aliases'], TRUE)) {
        return array_values(array_unique(array_merge([$canonical], $meta['aliases'])));
      }
    }
    return [$slug];
  }

  /**
   * Canonical slug for storage preference (aliases map to canonical).
   *
   * Bundles keep their own slug (not stored on nodes).
   */
  public static function canonicalize(string $slug): string {
    if (isset(self::BUNDLES[$slug])) {
      return $slug;
    }
    if (isset(self::DOMAINS[$slug])) {
      return $slug;
    }
    foreach (self::DOMAINS as $canonical => $meta) {
      if (in_array($slug, $meta['aliases'], TRUE)) {
        return $canonical;
      }
    }
    return $slug;
  }

  /**
   * Whether slug is a virtual bundle (not a stored field_domain value).
   */
  public static function isBundle(string $slug): bool {
    return isset(self::BUNDLES[$slug]);
  }

  /**
   * Grouped domain list for UI (domains + virtual bundles).
   *
   * @return array<string, list<array{slug: string, label: string, hint: string}>>
   */
  public static function groupedForFilters(): array {
    $grouped = [];
    foreach (self::FILTER_GROUP_ORDER as $gid) {
      $grouped[$gid] = [];
    }
    // Bundles first within their group so 国内 sits with civic hot chips.
    foreach (self::BUNDLES as $slug => $meta) {
      $gid = $meta['group'];
      if (!isset($grouped[$gid])) {
        $grouped[$gid] = [];
      }
      $grouped[$gid][] = [
        'slug' => $slug,
        'label' => $meta['label'],
        'hint' => $meta['hint'] ?? '',
      ];
    }
    foreach (self::DOMAINS as $slug => $meta) {
      $gid = $meta['group'];
      if (!isset($grouped[$gid])) {
        $grouped[$gid] = [];
      }
      $grouped[$gid][] = [
        'slug' => $slug,
        'label' => $meta['label'],
        'hint' => $meta['hint'] ?? '',
      ];
    }
    return $grouped;
  }

}
