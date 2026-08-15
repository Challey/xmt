<?php

namespace Drupal\xmt_trust_ui\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads agent allowlist + pause list + per-source article stats.
 */
class SourceCatalog {

  public function __construct(
    protected Connection $database,
    protected StateInterface $state,
  ) {}

  /**
   * Absolute path to agent/sources.yaml.
   */
  public function sourcesPath(): string {
    $configured = Settings::get('xmt_agent_sources');
    if (is_string($configured) && $configured !== '') {
      return $configured;
    }
    return dirname(DRUPAL_ROOT) . '/agent/sources.yaml';
  }

  /**
   * Preferred agent pause file.
   */
  public function pausedPath(): string {
    $configured = Settings::get('xmt_agent_paused');
    if (is_string($configured) && $configured !== '') {
      return $configured;
    }
    return dirname(DRUPAL_ROOT) . '/agent/ops_paused.json';
  }

  /**
   * Fallback pause file under public files (usually writable by PHP).
   */
  public function pausedFilesPath(): string {
    return DRUPAL_ROOT . '/sites/default/files/xmt_ops_paused.json';
  }

  /**
   * Absolute path to agent/state.json.
   */
  public function statePath(): string {
    return dirname(DRUPAL_ROOT) . '/agent/state.json';
  }

  /**
   * Absolute path to agent/last_run.log.
   */
  public function lastRunLogPath(): string {
    return dirname(DRUPAL_ROOT) . '/agent/last_run.log';
  }

  /**
   * @return list<array<string, mixed>>
   */
  public function feeds(): array {
    $path = $this->sourcesPath();
    if (!is_readable($path)) {
      return [];
    }
    try {
      $data = Yaml::parseFile($path);
    }
    catch (\Throwable $e) {
      return [];
    }
    $paused = $this->pausedUrls();
    $stats = $this->sourceStats();
    $out = [];
    foreach (($data['sources'] ?? []) as $group => $block) {
      if (!is_array($block)) {
        continue;
      }
      $site = (string) ($block['site'] ?? '');
      $domain = (string) ($block['domain'] ?? $group);
      $trust = (string) ($block['trust_level'] ?? 'l0_aggregate');
      $publisher = (string) ($block['publisher'] ?? '');
      foreach (($block['feeds'] ?? []) as $feed) {
        if (!is_array($feed)) {
          continue;
        }
        $name = trim((string) ($feed['name'] ?? ''));
        $url = trim((string) ($feed['url'] ?? ''));
        if ($name === '' || $url === '') {
          continue;
        }
        $st = $stats[$name] ?? ['count' => 0, 'last' => 0];
        $out[] = [
          'group' => (string) $group,
          'name' => $name,
          'url' => $url,
          'site' => $site,
          'domain' => $domain,
          'trust_level' => $trust,
          'publisher' => $publisher,
          'paused' => isset($paused[$url]),
          'article_count' => (int) $st['count'],
          'last_created' => (int) $st['last'],
        ];
      }
    }
    return $out;
  }

  /**
   * @return array<string, true> URL => true
   */
  public function pausedUrls(): array {
    $map = [];
    foreach ($this->state->get('xmt_source_ops.paused_urls', []) as $u) {
      $u = trim((string) $u);
      if ($u !== '') {
        $map[$u] = TRUE;
      }
    }
    foreach ([$this->pausedPath(), $this->pausedFilesPath()] as $path) {
      if (!is_readable($path)) {
        continue;
      }
      try {
        $data = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
      }
      catch (\Throwable $e) {
        continue;
      }
      foreach (($data['paused_urls'] ?? []) as $u) {
        $u = trim((string) $u);
        if ($u !== '') {
          $map[$u] = TRUE;
        }
      }
    }
    return $map;
  }

  /**
   * Toggle pause for a feed URL. Returns new paused state.
   */
  public function togglePaused(string $url): bool {
    $url = trim($url);
    if ($url === '') {
      throw new \InvalidArgumentException('Empty URL');
    }
    $map = $this->pausedUrls();
    if (isset($map[$url])) {
      unset($map[$url]);
      $paused = FALSE;
    }
    else {
      $map[$url] = TRUE;
      $paused = TRUE;
    }
    $list = array_keys($map);
    $this->state->set('xmt_source_ops.paused_urls', $list);
    $this->state->set('xmt_source_ops.paused_updated', time());
    $this->writePausedFiles($list);
    return $paused;
  }

  /**
   * Persist pause list for the agent process.
   *
   * @param list<string> $urls
   */
  protected function writePausedFiles(array $urls): void {
    $payload = json_encode([
      'paused_urls' => array_values($urls),
      'updated' => time(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($payload === FALSE) {
      throw new \RuntimeException('JSON encode failed');
    }
    $payload .= "\n";
    $written = FALSE;
    foreach ([$this->pausedPath(), $this->pausedFilesPath()] as $path) {
      $dir = dirname($path);
      if (!is_dir($dir)) {
        continue;
      }
      if (@file_put_contents($path, $payload) !== FALSE) {
        $written = TRUE;
      }
    }
    if (!$written) {
      throw new \RuntimeException('Cannot write ops_paused.json (agent/ or files/)');
    }
  }

  /**
   * @return array<string, array{count: int, last: int}> keyed by source name
   */
  public function sourceStats(): array {
    if (!$this->database->schema()->tableExists('node__field_source_name')) {
      return [];
    }
    $q = $this->database->select('node__field_source_name', 's');
    $q->join('node_field_data', 'n', 'n.nid = s.entity_id AND n.status = 1 AND n.type = :type', [
      ':type' => 'article',
    ]);
    $q->addField('s', 'field_source_name_value', 'name');
    $q->addExpression('COUNT(*)', 'cnt');
    $q->addExpression('MAX(n.created)', 'last_created');
    $q->groupBy('s.field_source_name_value');
    $out = [];
    foreach ($q->execute() as $row) {
      $name = (string) $row->name;
      if ($name === '') {
        continue;
      }
      $out[$name] = [
        'count' => (int) $row->cnt,
        'last' => (int) $row->last_created,
      ];
    }
    return $out;
  }

  /**
   * Source names in DB but not in allowlist.
   *
   * @param list<array<string, mixed>> $feeds
   *
   * @return list<array{name: string, count: int, last: int}>
   */
  public function orphans(array $feeds): array {
    $known = [];
    foreach ($feeds as $f) {
      $known[$f['name']] = TRUE;
    }
    $orphans = [];
    foreach ($this->sourceStats() as $name => $st) {
      if (!isset($known[$name])) {
        $orphans[] = [
          'name' => $name,
          'count' => $st['count'],
          'last' => $st['last'],
        ];
      }
    }
    usort($orphans, static fn($a, $b) => $b['count'] <=> $a['count']);
    return $orphans;
  }

  /**
   * Agent runtime snapshot.
   *
   * @return array<string, mixed>
   */
  public function agentSnapshot(): array {
    $state_path = $this->statePath();
    $log_path = $this->lastRunLogPath();
    $seen = 0;
    $state_mtime = 0;
    if (is_readable($state_path)) {
      $state_mtime = (int) filemtime($state_path);
      try {
        $data = json_decode((string) file_get_contents($state_path), TRUE);
        $seen = is_array($data['seen'] ?? NULL) ? count($data['seen']) : 0;
      }
      catch (\Throwable $e) {
        $seen = 0;
      }
    }
    $log_tail = '';
    $log_mtime = 0;
    $last_published = NULL;
    $last_errors = NULL;
    $last_run_stamp = '';
    $last_run_mode = '';
    if (is_readable($log_path)) {
      $log_mtime = (int) filemtime($log_path);
      $raw = (string) file_get_contents($log_path);
      $lines = preg_split('/\R/', $raw) ?: [];
      $log_tail = implode("\n", array_slice($lines, -12));
      // New structured log: published=N errors=M (optional dry_run note).
      if (preg_match_all('/(?:^|\n)published=(\d+)\s+errors=(\d+)/', $raw, $all, PREG_SET_ORDER)) {
        $last = end($all);
        $last_published = (int) $last[1];
        $last_errors = (int) $last[2];
      }
      // Legacy: Done. published=N errors=M
      elseif (preg_match_all('/Done\.\s*published=(\d+)\s+errors=(\d+)/', $raw, $all, PREG_SET_ORDER)) {
        $last = end($all);
        $last_published = (int) $last[1];
        $last_errors = (int) $last[2];
      }
      if (preg_match('/===\s*([^\s]+)\s+agent run(?:\s*\(([^)]+)\))?/', $raw, $hm)) {
        $last_run_stamp = (string) $hm[1];
        $last_run_mode = isset($hm[2]) && $hm[2] !== '' ? (string) $hm[2] : 'ingest';
      }
    }
    $dry = $this->state->get('xmt_source_ops.last_dry_run', []);
    $ingest = $this->state->get('xmt_source_ops.last_ingest', []);
    return [
      'sources_readable' => is_readable($this->sourcesPath()),
      'paused_count' => count($this->pausedUrls()),
      'seen_count' => $seen,
      'state_mtime' => $state_mtime,
      'log_mtime' => $log_mtime,
      'log_tail' => $log_tail,
      'last_published' => $last_published,
      'last_errors' => $last_errors,
      'last_run_stamp' => $last_run_stamp,
      'last_run_mode' => $last_run_mode,
      'last_dry_run_at' => is_array($dry) ? (int) ($dry['at'] ?? 0) : 0,
      'last_dry_run_group' => is_array($dry) ? (string) ($dry['group'] ?? '') : '',
      'last_dry_run_ok' => is_array($dry) ? !empty($dry['ok']) : FALSE,
      'last_ingest_at' => is_array($ingest) ? (int) ($ingest['at'] ?? 0) : 0,
      'last_ingest_group' => is_array($ingest) ? (string) ($ingest['group'] ?? '') : '',
      'last_ingest_ok' => is_array($ingest) ? !empty($ingest['ok']) : FALSE,
    ];
  }

  /**
   * Pause or resume all feeds in a group.
   *
   * @return array{changed: int, paused: bool}
   */
  public function setGroupPaused(string $group, bool $pause): array {
    $group = trim($group);
    $map = $this->pausedUrls();
    $changed = 0;
    foreach ($this->feeds() as $f) {
      if ($f['group'] !== $group) {
        continue;
      }
      $url = $f['url'];
      if ($pause) {
        if (!isset($map[$url])) {
          $map[$url] = TRUE;
          $changed++;
        }
      }
      else {
        if (isset($map[$url])) {
          unset($map[$url]);
          $changed++;
        }
      }
    }
    $list = array_keys($map);
    $this->state->set('xmt_source_ops.paused_urls', $list);
    $this->state->set('xmt_source_ops.paused_updated', time());
    $this->writePausedFiles($list);
    return ['changed' => $changed, 'paused' => $pause];
  }

  /**
   * Pause feeds whose last probe failed (optionally scoped to a group).
   *
   * @return array{changed: int, candidates: int, names: list<string>}
   */
  public function pauseFailedProbes(?string $group = NULL): array {
    $group = $group !== NULL ? trim($group) : '';
    $probe = $this->probeCacheMap();
    $map = $this->pausedUrls();
    $changed = 0;
    $candidates = 0;
    $names = [];
    foreach ($this->feeds() as $f) {
      if ($group !== '' && $f['group'] !== $group) {
        continue;
      }
      $url = $f['url'];
      if (!isset($probe[$url]) || !empty($probe[$url]['ok'])) {
        continue;
      }
      $candidates++;
      if (isset($map[$url])) {
        continue;
      }
      $map[$url] = TRUE;
      $changed++;
      $names[] = $f['name'];
    }
    if ($changed > 0) {
      $list = array_keys($map);
      $this->state->set('xmt_source_ops.paused_urls', $list);
      $this->state->set('xmt_source_ops.paused_updated', time());
      $this->writePausedFiles($list);
    }
    return [
      'changed' => $changed,
      'candidates' => $candidates,
      'names' => array_slice($names, 0, 40),
    ];
  }

  /**
   * Pause feeds that have never been probed (optionally scoped to a group).
   *
   * @return array{changed: int, candidates: int, names: list<string>}
   */
  public function pauseNeverProbed(?string $group = NULL): array {
    $group = $group !== NULL ? trim($group) : '';
    $probe = $this->probeCacheMap();
    $map = $this->pausedUrls();
    $changed = 0;
    $candidates = 0;
    $names = [];
    foreach ($this->feeds() as $f) {
      if ($group !== '' && $f['group'] !== $group) {
        continue;
      }
      $url = $f['url'];
      if (isset($probe[$url])) {
        continue;
      }
      $candidates++;
      if (isset($map[$url])) {
        continue;
      }
      $map[$url] = TRUE;
      $changed++;
      $names[] = $f['name'];
    }
    if ($changed > 0) {
      $list = array_keys($map);
      $this->state->set('xmt_source_ops.paused_urls', $list);
      $this->state->set('xmt_source_ops.paused_updated', time());
      $this->writePausedFiles($list);
    }
    return [
      'changed' => $changed,
      'candidates' => $candidates,
      'names' => array_slice($names, 0, 40),
    ];
  }

  /**
   * Pause feeds with stale probe cache (≥7d).
   *
   * @return array{changed: int, candidates: int, names: list<string>}
   */
  public function pauseStaleFeeds(?string $group = NULL): array {
    $group = $group !== NULL ? trim($group) : '';
    $probe = $this->probeCacheMap();
    $map = $this->pausedUrls();
    $changed = 0;
    $candidates = 0;
    $names = [];
    foreach ($this->feeds() as $f) {
      if ($group !== '' && $f['group'] !== $group) {
        continue;
      }
      if (!$this->isStaleProbe($f, $probe)) {
        continue;
      }
      $candidates++;
      $url = $f['url'];
      if (isset($map[$url])) {
        continue;
      }
      $map[$url] = TRUE;
      $changed++;
      $names[] = $f['name'];
    }
    if ($changed > 0) {
      $list = array_keys($map);
      $this->state->set('xmt_source_ops.paused_urls', $list);
      $this->state->set('xmt_source_ops.paused_updated', time());
      $this->writePausedFiles($list);
    }
    return [
      'changed' => $changed,
      'candidates' => $candidates,
      'names' => array_slice($names, 0, 40),
    ];
  }

  /**
   * Pause empty-RSS feeds (probe-ok with item_count=0).
   *
   * @return array{changed: int, candidates: int, names: list<string>}
   */
  public function pauseEmptyFeeds(?string $group = NULL): array {
    $group = $group !== NULL ? trim($group) : '';
    $probe = $this->probeCacheMap();
    $map = $this->pausedUrls();
    $changed = 0;
    $candidates = 0;
    $names = [];
    foreach ($this->feeds() as $f) {
      if ($group !== '' && $f['group'] !== $group) {
        continue;
      }
      if (!$this->isEmptyRssFeed($f, $probe)) {
        continue;
      }
      $candidates++;
      $url = $f['url'];
      if (isset($map[$url])) {
        continue;
      }
      $map[$url] = TRUE;
      $changed++;
      $names[] = $f['name'];
    }
    if ($changed > 0) {
      $list = array_keys($map);
      $this->state->set('xmt_source_ops.paused_urls', $list);
      $this->state->set('xmt_source_ops.paused_updated', time());
      $this->writePausedFiles($list);
    }
    return [
      'changed' => $changed,
      'candidates' => $candidates,
      'names' => array_slice($names, 0, 40),
    ];
  }

  /**
   * Pause silent feeds (probe-ok but stale/zero ingest).
   *
   * @return array{changed: int, candidates: int, names: list<string>}
   */
  public function pauseSilentFeeds(?string $group = NULL): array {
    $group = $group !== NULL ? trim($group) : '';
    $probe = $this->probeCacheMap();
    $map = $this->pausedUrls();
    $changed = 0;
    $candidates = 0;
    $names = [];
    foreach ($this->feeds() as $f) {
      if ($group !== '' && $f['group'] !== $group) {
        continue;
      }
      if (!$this->isSilentFeed($f, $probe)) {
        continue;
      }
      $candidates++;
      $url = $f['url'];
      if (isset($map[$url])) {
        continue;
      }
      $map[$url] = TRUE;
      $changed++;
      $names[] = $f['name'];
    }
    if ($changed > 0) {
      $list = array_keys($map);
      $this->state->set('xmt_source_ops.paused_urls', $list);
      $this->state->set('xmt_source_ops.paused_updated', time());
      $this->writePausedFiles($list);
    }
    return [
      'changed' => $changed,
      'candidates' => $candidates,
      'names' => array_slice($names, 0, 40),
    ];
  }

  /**
   * Cached probe (TTL seconds).
   *
   * @return array{ok: bool, http: int, ctype: string, bytes: int, message: string, cached: bool, checked: int}
   */
  public function probeCached(string $url, int $ttl = 3600, bool $force = FALSE): array {
    $url = trim($url);
    $cache = $this->state->get('xmt_source_ops.probe_cache', []);
    if (!is_array($cache)) {
      $cache = [];
    }
    $hit = $cache[$url] ?? NULL;
    if (!$force && is_array($hit) && !empty($hit['checked']) && (time() - (int) $hit['checked']) < $ttl) {
      return [
        'ok' => !empty($hit['ok']),
        'http' => (int) ($hit['http'] ?? 0),
        'ctype' => (string) ($hit['ctype'] ?? ''),
        'bytes' => (int) ($hit['bytes'] ?? 0),
        'item_count' => array_key_exists('item_count', $hit) ? (int) $hit['item_count'] : NULL,
        'message' => (string) ($hit['message'] ?? ''),
        'cached' => TRUE,
        'checked' => (int) $hit['checked'],
      ];
    }
    $result = $this->probe($url);
    $cache[$url] = [
      'ok' => $result['ok'],
      'http' => $result['http'],
      'ctype' => $result['ctype'],
      'bytes' => $result['bytes'],
      'item_count' => (int) ($result['item_count'] ?? 0),
      'message' => $result['message'],
      'checked' => time(),
    ];
    // Cap cache size.
    if (count($cache) > 400) {
      uasort($cache, static fn($a, $b) => ($a['checked'] ?? 0) <=> ($b['checked'] ?? 0));
      $cache = array_slice($cache, -300, NULL, TRUE);
    }
    $this->state->set('xmt_source_ops.probe_cache', $cache);
    return $result + ['cached' => FALSE, 'checked' => time()];
  }

  /**
   * Probe status map for dashboard chips.
   *
   * @return array<string, array{ok: bool, checked: int, message: string}>
   */
  public function probeCacheMap(): array {
    $cache = $this->state->get('xmt_source_ops.probe_cache', []);
    $out = [];
    foreach (is_array($cache) ? $cache : [] as $url => $row) {
      if (!is_array($row)) {
        continue;
      }
      $out[(string) $url] = [
        'ok' => !empty($row['ok']),
        'checked' => (int) ($row['checked'] ?? 0),
        'message' => (string) ($row['message'] ?? ''),
        'item_count' => array_key_exists('item_count', $row) ? (int) $row['item_count'] : NULL,
      ];
    }
    return $out;
  }

  /**
   * Probe-ok feed whose last probe reported zero RSS/Atom items.
   */
  public function isEmptyRssFeed(array $feed, array $probe_map): bool {
    $url = (string) ($feed['url'] ?? '');
    if ($url === '' || !isset($probe_map[$url])) {
      return FALSE;
    }
    $p = $probe_map[$url];
    if (empty($p['ok'])) {
      return FALSE;
    }
    return isset($p['item_count']) && (int) $p['item_count'] === 0;
  }

  /**
   * Feed whose last probe is older than $stale_after (has been probed).
   */
  public function isStaleProbe(array $feed, array $probe_map, int $stale_after = 604800): bool {
    $url = (string) ($feed['url'] ?? '');
    if ($url === '' || !isset($probe_map[$url])) {
      return FALSE;
    }
    $checked = (int) ($probe_map[$url]['checked'] ?? 0);
    return $checked > 0 && $checked < (time() - $stale_after);
  }

  /**
   * Active + probe-ok but no articles in 7 days (or never ingested).
   */
  public function isSilentFeed(array $feed, array $probe_map, int $stale_after = 604800): bool {
    if (!empty($feed['paused'])) {
      return FALSE;
    }
    $url = (string) ($feed['url'] ?? '');
    if ($url === '' || !isset($probe_map[$url]) || empty($probe_map[$url]['ok'])) {
      return FALSE;
    }
    $last = (int) ($feed['last_created'] ?? 0);
    $count = (int) ($feed['article_count'] ?? 0);
    if ($count === 0 || $last === 0) {
      return TRUE;
    }
    return $last < (time() - $stale_after);
  }

  /**
   * Re-probe silent feeds (probe-ok but stale ingest). Cap to avoid long requests.
   *
   * @return array{ok: int, fail: int, skipped: int, results: list<array{name: string, ok: bool, message: string}>}
   */
  public function probeSilent(string $group = '', int $limit = 15): array {
    $group = trim($group);
    $probe = $this->probeCacheMap();
    $ok = $fail = $skipped = 0;
    $results = [];
    $n = 0;
    foreach ($this->feeds() as $f) {
      if ($group !== '' && $f['group'] !== $group) {
        continue;
      }
      if (!$this->isSilentFeed($f, $probe)) {
        continue;
      }
      if ($n >= $limit) {
        $skipped++;
        continue;
      }
      $n++;
      $r = $this->probeCached($f['url'], 3600, TRUE);
      if (!empty($r['ok'])) {
        $ok++;
      }
      else {
        $fail++;
      }
      $results[] = [
        'name' => $f['name'],
        'ok' => !empty($r['ok']),
        'message' => (string) ($r['message'] ?? ''),
      ];
    }
    return [
      'ok' => $ok,
      'fail' => $fail,
      'skipped' => $skipped,
      'results' => $results,
    ];
  }

  /**
   * Re-probe feeds whose last probe failed. Cap to avoid long requests.
   *
   * @return array{ok: int, fail: int, skipped: int, results: list<array{name: string, ok: bool, message: string}>}
   */
  public function probeFailed(string $group = '', int $limit = 15): array {
    $group = trim($group);
    $probe = $this->probeCacheMap();
    $ok = $fail = $skipped = 0;
    $results = [];
    $n = 0;
    foreach ($this->feeds() as $f) {
      if ($group !== '' && $f['group'] !== $group) {
        continue;
      }
      $url = (string) ($f['url'] ?? '');
      if ($url === '' || !isset($probe[$url]) || !empty($probe[$url]['ok'])) {
        continue;
      }
      if ($n >= $limit) {
        $skipped++;
        continue;
      }
      $n++;
      $r = $this->probeCached($url, 3600, TRUE);
      if (!empty($r['ok'])) {
        $ok++;
      }
      else {
        $fail++;
      }
      $results[] = [
        'name' => $f['name'],
        'ok' => !empty($r['ok']),
        'message' => (string) ($r['message'] ?? ''),
      ];
    }
    return [
      'ok' => $ok,
      'fail' => $fail,
      'skipped' => $skipped,
      'results' => $results,
    ];
  }

  /**
   * Resume currently paused feeds (optional group filter).
   *
   * @return array{changed: int, candidates: int, names: list<string>}
   */
  public function resumePausedFeeds(?string $group = NULL): array {
    $group = $group !== NULL ? trim($group) : '';
    $map = $this->pausedUrls();
    $changed = 0;
    $candidates = 0;
    $names = [];
    foreach ($this->feeds() as $f) {
      if ($group !== '' && $f['group'] !== $group) {
        continue;
      }
      $url = (string) ($f['url'] ?? '');
      if ($url === '' || !isset($map[$url])) {
        continue;
      }
      $candidates++;
      unset($map[$url]);
      $changed++;
      $names[] = $f['name'];
    }
    if ($changed > 0) {
      $list = array_keys($map);
      $this->state->set('xmt_source_ops.paused_urls', $list);
      $this->state->set('xmt_source_ops.paused_updated', time());
      $this->writePausedFiles($list);
    }
    return [
      'changed' => $changed,
      'candidates' => $candidates,
      'names' => array_slice($names, 0, 40),
    ];
  }

  /**
   * Re-probe feeds whose last probe reported 0 RSS items. Cap to avoid long requests.
   *
   * @return array{ok: int, fail: int, skipped: int, results: list<array{name: string, ok: bool, message: string}>}
   */
  public function probeEmpty(string $group = '', int $limit = 15): array {
    $group = trim($group);
    $probe = $this->probeCacheMap();
    $ok = $fail = $skipped = 0;
    $results = [];
    $n = 0;
    foreach ($this->feeds() as $f) {
      if ($group !== '' && $f['group'] !== $group) {
        continue;
      }
      if (!$this->isEmptyRssFeed($f, $probe)) {
        continue;
      }
      if ($n >= $limit) {
        $skipped++;
        continue;
      }
      $n++;
      $r = $this->probeCached($f['url'], 3600, TRUE);
      if (!empty($r['ok'])) {
        $ok++;
      }
      else {
        $fail++;
      }
      $results[] = [
        'name' => $f['name'],
        'ok' => !empty($r['ok']),
        'message' => (string) ($r['message'] ?? ''),
      ];
    }
    return [
      'ok' => $ok,
      'fail' => $fail,
      'skipped' => $skipped,
      'results' => $results,
    ];
  }

  /**
   * First-probe feeds with no probe cache. Cap to avoid long requests.
   *
   * @return array{ok: int, fail: int, skipped: int, results: list<array{name: string, ok: bool, message: string}>}
   */
  public function probeNever(string $group = '', int $limit = 20): array {
    $group = trim($group);
    $probe = $this->probeCacheMap();
    $ok = $fail = $skipped = 0;
    $results = [];
    $n = 0;
    foreach ($this->feeds() as $f) {
      if ($group !== '' && $f['group'] !== $group) {
        continue;
      }
      $url = (string) ($f['url'] ?? '');
      if ($url === '' || isset($probe[$url])) {
        continue;
      }
      if ($n >= $limit) {
        $skipped++;
        continue;
      }
      $n++;
      $r = $this->probeCached($url, 3600, TRUE);
      if (!empty($r['ok'])) {
        $ok++;
      }
      else {
        $fail++;
      }
      $results[] = [
        'name' => $f['name'],
        'ok' => !empty($r['ok']),
        'message' => (string) ($r['message'] ?? ''),
      ];
    }
    return [
      'ok' => $ok,
      'fail' => $fail,
      'skipped' => $skipped,
      'results' => $results,
    ];
  }

  /**
   * Re-probe feeds whose last probe is older than $stale_after seconds.
   *
   * Oldest-checked first. Cap to avoid long requests.
   *
   * @return array{ok: int, fail: int, skipped: int, results: list<array{name: string, ok: bool, message: string}>}
   */
  public function probeStale(string $group = '', int $limit = 20, int $stale_after = 604800): array {
    $group = trim($group);
    $probe = $this->probeCacheMap();
    $cutoff = time() - $stale_after;
    $candidates = [];
    foreach ($this->feeds() as $f) {
      if ($group !== '' && $f['group'] !== $group) {
        continue;
      }
      $url = (string) ($f['url'] ?? '');
      if ($url === '') {
        continue;
      }
      $p = $probe[$url] ?? NULL;
      if (!$p || empty($p['checked'])) {
        continue;
      }
      $checked = (int) $p['checked'];
      if ($checked >= $cutoff) {
        continue;
      }
      $candidates[] = [
        'checked' => $checked,
        'feed' => $f,
      ];
    }
    usort($candidates, static fn(array $a, array $b): int => ($a['checked'] <=> $b['checked']));
    $ok = $fail = $skipped = 0;
    $results = [];
    $n = 0;
    foreach ($candidates as $item) {
      if ($n >= $limit) {
        $skipped++;
        continue;
      }
      $n++;
      $f = $item['feed'];
      $r = $this->probeCached($f['url'], 3600, TRUE);
      if (!empty($r['ok'])) {
        $ok++;
      }
      else {
        $fail++;
      }
      $results[] = [
        'name' => $f['name'],
        'ok' => !empty($r['ok']),
        'message' => (string) ($r['message'] ?? ''),
      ];
    }
    return [
      'ok' => $ok,
      'fail' => $fail,
      'skipped' => $skipped,
      'results' => $results,
    ];
  }

  /**
   * Probe all feeds in a group (writes cache). Cap to avoid long requests.
   *
   * @return array{ok: int, fail: int, skipped: int, results: list<array{name: string, ok: bool, message: string}>}
   */
  public function probeGroup(string $group, int $limit = 20): array {
    $group = trim($group);
    $ok = $fail = $skipped = 0;
    $results = [];
    $n = 0;
    foreach ($this->feeds() as $f) {
      if ($f['group'] !== $group) {
        continue;
      }
      if ($n >= $limit) {
        $skipped++;
        continue;
      }
      $n++;
      $r = $this->probeCached($f['url'], 3600, TRUE);
      if (!empty($r['ok'])) {
        $ok++;
      }
      else {
        $fail++;
      }
      $results[] = [
        'name' => $f['name'],
        'ok' => !empty($r['ok']),
        'message' => (string) ($r['message'] ?? ''),
      ];
    }
    return [
      'ok' => $ok,
      'fail' => $fail,
      'skipped' => $skipped,
      'results' => $results,
    ];
  }

  /**
   * Dry-run a single feed URL (XMT_FEED_URL + group filter for speed).
   *
   * @return array{ok: bool, code: int, output: string}
   */
  public function dryRunFeed(string $url, string $group = ''): array {
    $url = trim($url);
    $group = trim($group);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
      return ['ok' => FALSE, 'code' => 1, 'output' => 'Invalid feed URL'];
    }
    $agent = dirname(DRUPAL_ROOT) . '/agent/run_agent.py';
    if (!is_readable($agent)) {
      return ['ok' => FALSE, 'code' => 1, 'output' => 'run_agent.py missing'];
    }
    $env = 'XMT_DRY_RUN=1 XMT_MAX_PER_FEED=5 XMT_FEED_URL=' . escapeshellarg($url);
    if ($group !== '') {
      $env .= ' XMT_AGENT_FILTER=' . escapeshellarg($group);
    }
    $cmd = $env . ' python3 ' . escapeshellarg($agent) . ' 2>&1';
    $output = [];
    $code = 0;
    exec($cmd, $output, $code);
    $text = implode("\n", array_slice($output, 0, 200));
    $result = [
      'ok' => $code === 0 || str_contains($text, 'Done.'),
      'code' => $code,
      'output' => $text !== '' ? $text : '(no output)',
    ];
    $this->state->set('xmt_source_ops.last_dry_run', [
      'group' => $group !== '' ? $group : '(feed)',
      'feed_url' => $url,
      'ok' => $result['ok'],
      'code' => $result['code'],
      'at' => time(),
      'tail' => mb_substr($result['output'], 0, 4000),
    ]);
    return $result;
  }

  /**
   * Run agent dry-run for one allowlist group (exact key match via filter).
   *
   * @return array{ok: bool, code: int, output: string}
   */
  public function dryRunGroup(string $group): array {
    $group = trim($group);
    $allowed = [];
    foreach ($this->feeds() as $f) {
      $allowed[$f['group']] = TRUE;
    }
    if ($group === '' || !isset($allowed[$group])) {
      return ['ok' => FALSE, 'code' => 1, 'output' => 'Unknown group'];
    }
    $agent = dirname(DRUPAL_ROOT) . '/agent/run_agent.py';
    if (!is_readable($agent)) {
      return ['ok' => FALSE, 'code' => 1, 'output' => 'run_agent.py missing'];
    }
    $cmd = 'XMT_DRY_RUN=1 XMT_MAX_PER_FEED=3 XMT_AGENT_FILTER=' . escapeshellarg($group)
      . ' python3 ' . escapeshellarg($agent) . ' 2>&1';
    $output = [];
    $code = 0;
    exec($cmd, $output, $code);
    $text = implode("\n", array_slice($output, 0, 200));
    $result = [
      'ok' => $code === 0 || str_contains($text, 'Done.'),
      'code' => $code,
      'output' => $text !== '' ? $text : '(no output)',
    ];
    $this->state->set('xmt_source_ops.last_dry_run', [
      'group' => $group,
      'ok' => $result['ok'],
      'code' => $result['code'],
      'at' => time(),
      'tail' => mb_substr($result['output'], 0, 4000),
    ]);
    $dry_file = DRUPAL_ROOT . '/sites/default/files/xmt_ops_last_dry_run.json';
    $agent_dry = dirname(DRUPAL_ROOT) . '/agent/last_dry_run.json';
    $payload = json_encode([
      'group' => $group,
      'ok' => $result['ok'],
      'code' => $result['code'],
      'at' => time(),
      'tail' => mb_substr($result['output'], 0, 4000),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    foreach ([$dry_file, $agent_dry] as $path) {
      $dir = dirname($path);
      if (is_dir($dir) && is_writable($dir)) {
        @file_put_contents($path, $payload . "\n");
      }
    }
    return $result;
  }

  /**
   * Lightweight feed probe (no HTML scrape of articles).
   *
   * @return array{ok: bool, http: int, ctype: string, bytes: int, message: string}
   */
  public function probe(string $url): array {
    $url = trim($url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
      return [
        'ok' => FALSE,
        'http' => 0,
        'ctype' => '',
        'bytes' => 0,
        'message' => 'Invalid URL',
      ];
    }
    $ctx = stream_context_create([
      'http' => [
        'method' => 'GET',
        'timeout' => 12,
        'follow_location' => 1,
        'max_redirects' => 3,
        'header' => "User-Agent: XMT-SourceOps/1.0 (+https://xmt.pub)\r\nAccept: application/rss+xml, application/atom+xml, application/xml, text/xml, */*\r\n",
        'ignore_errors' => TRUE,
      ],
      'ssl' => [
        'verify_peer' => TRUE,
        'verify_peer_name' => TRUE,
      ],
    ]);
    $body = @file_get_contents($url, FALSE, $ctx, 0, 65536);
    $http = 0;
    $ctype = '';
    if (isset($http_response_header) && is_array($http_response_header)) {
      foreach ($http_response_header as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
          $http = (int) $m[1];
        }
        if (stripos($h, 'Content-Type:') === 0) {
          $ctype = trim(substr($h, 13));
        }
      }
    }
    if ($body === FALSE) {
      return [
        'ok' => FALSE,
        'http' => $http,
        'ctype' => $ctype,
        'bytes' => 0,
        'message' => 'Fetch failed',
      ];
    }
    $bytes = strlen($body);
    // RSS 2.0 / Atom / RSS 1.0 RDF (Nature etc. may omit <?xml and use <rdf:RDF>).
    $looks_xml = (bool) preg_match('/<(?:\?xml|rss\b|feed\b|rdf:RDF\b)/i', $body);
    $ok = $http >= 200 && $http < 400 && $looks_xml;
    $item_count = 0;
    if ($looks_xml) {
      $item_count = preg_match_all('#<(?:[\w.-]+:)?(?:item|entry)(?:\s|/|>)#i', $body);
      if ($item_count === FALSE) {
        $item_count = 0;
      }
    }
    $message = $ok
      ? ('Looks like RSS/Atom · ' . $item_count . ' items')
      : ($looks_xml ? 'XML but HTTP ' . $http : 'Not RSS/Atom XML');
    return [
      'ok' => $ok,
      'http' => $http,
      'ctype' => $ctype,
      'bytes' => $bytes,
      'item_count' => (int) $item_count,
      'message' => $message,
    ];
  }

}
