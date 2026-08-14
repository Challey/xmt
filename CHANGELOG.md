# Changelog

## v0.4.0-provenance — 2026-08-14

### Added
- `Drupal\xmt_trust\Provenance` — single source of truth for provenance payload, hashing, and verification (`verified` / `mismatch` / `bridge` / `missing`)
- Public verification page `/trusted/verify/{nid}` and machine-readable `/trusted/verify/{nid}/json`
- Provenance audit CSV export `/admin/xmt/provenance/export`, plus a verification column on `/admin/xmt/provenance`
- Drush `xmt:provenance-verify` (`--limit`, `--status`); exits 1 when mismatches exist, for cron checks
- 「核验溯源」link on full article displays
- Unit tests `Drupal\Tests\xmt_trust\Unit\ProvenanceTest`

### Changed
- Provenance hashes are now **write-once**: previously every save recomputed the hash, which hid later edits to source URL, publisher, or creation time
- `TrustFeedController` takes `date.formatter` by injection instead of `\Drupal::service()`

### Fixed
- `/admin/xmt/provenance` fataled on an unqualified `Drupal::service('date.formatter')` call

### Ops
- Run `drush --uri=xmt.pub cr` after deploy so the new routes register (see `docs/ops.md`)

## v0.3.0-homepage — 2026-08-13

### Added
- Homepage block `xmt_trust_home_columns` — three columns (官方可信 / 企业可信 / 领域汇聚)
- Route `/trusted/aggregate` for L0 aggregate feed
- CSS grid `.xmt-trust-home` (3 columns desktop, stacked mobile)

### Ops
- Place block on xmt.pub front page `content` region via Drush (see `docs/ops.md`)

## v0.2.0-trust-phase5 — 2026-08-13

### Added
- DrupalX → XMT trusted content API `POST /api/xmt/v1/trusted-content` (HMAC)
- `DxContentHandler` — L2 article upsert by `external_id` or `source_url`
- Drush `xmt:dx-content-test` and DrupalX `dx:xmt-push-content`
- Preserve `dx:` provenance hash on bridge-created articles

### Notes
- Requires approved publisher from phase 3 `dx-claim` flow

## v0.1.0-trust — 2026-08-12

### Added
- Branding/docs: vision, trust model, architecture, DrupalX bridge, deploy-to-B
- Modules: `xmt_publisher`, `xmt_trust`, `xmt_trust_ui`, `xmt_dx_bridge`
- Trust levels L0/L1/L2 on articles; official publisher seed「XMT官方」
- Enterprise apply `/publishers/apply` + admin approve flow
- Trusted feeds `/trusted`, `/trusted/official`, `/trusted/enterprise`
- DrupalX `dx_xmt_bridge` Drush `dx:xmt-issue-claim`
- Agent payloads set `trust_level=l0_aggregate`

### Notes
- Shared user tables not used (Drupal 11.4 string prefixes)
- Content push from DrupalX deferred to phase 2
