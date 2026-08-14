# Changelog

## v0.4.0-provenance-export — 2026-08-14

### Added
- Provenance audit CSV/JSON export at `/admin/xmt/provenance/export.csv` and `export.json` (buttons on audit page)
- Drush `xmt:provenance-export` — CSV or JSON, up to 500 articles (trust level, publisher, provenance hash, source URL)
- `ProvenanceAuditService` — shared query/export logic for web UI and CLI
- Publisher public page (`/publisher/{id}`) lists recent L1/L2 articles for that subject
- Article pages show “Published by” attribution linking to the publisher

### Fixed
- Define missing `xmt_trust_vertical_readonly_after_build()` so vertical-site article forms do not fatally error

### Ops
- Export: `vendor/bin/drush --uri=xmt.pub xmt:provenance-export --format=csv --output=/tmp/audit.csv`
- JSON: `vendor/bin/drush --uri=xmt.pub xmt:provenance-export --format=json --output=/tmp/audit.json`

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
