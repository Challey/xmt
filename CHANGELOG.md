# Changelog

## v0.9.0-trust-seo — 2026-08-14

### Added
- `hook_robots_alter` on xmt.pub hub — appends `Sitemap: /trusted/sitemap.xml` to `robots.txt`
- Sitemap includes recent **L0 aggregate** articles by default (half of `limit`, max 50)
- Query params: `include_l0=0` to exclude L0; `l0_limit=` to override L0 cap
- Trust feed pages link to sitemap; verify script checks `robots.txt`

### Docs
- `RELEASE-NOTES-v0.9.0-trust-platform.md` — consolidated v0.4–v0.9 slice summary

## v0.8.0-trust-verify-sitemap — 2026-08-14

### Added
- Trust platform XML sitemap at `/trusted/sitemap.xml` (hub pages, publishers, recent L1/L2 articles)
- `TrustSitemapBuilder` service with optional `?limit=` (10–500 articles, default 100)
- Homepage trust block footer links: all trusted, publishers, apply, sitemap
- Ops script `setup/scripts/75-verify-trust.sh` — HTTP + Drush post-deploy checks

### Docs
- `docs/deploy-server-b.md`, `docs/ops.md` — verification script usage

## v0.7.0-publishers-directory — 2026-08-14

### Added
- Public publishers directory at `/publishers` (official L1 + enterprise L2 sections)
- Per-publisher RSS/JSON feeds: `/publisher/{id}/feed.rss` and `/publisher/{id}/feed.json`
- `TrustedFeedBuilder::itemsForPublisher()` and channel serializers for publisher feeds
- Publisher pages show RSS/JSON subscription links; trust feed nav links to `/publishers`

### Docs
- README, `docs/ops.md`, `docs/trust-model.md` — directory and per-publisher feeds

## v0.6.0-publisher-page — 2026-08-14

### Added
- Public publisher page (`/publisher/{id}`) shows trust badge, certification status, website, and recent articles
- `PublisherPageBuilder` service — meta block + article list for approved publishers
- CSS `publisher-page` library for publisher layout
- Ops script `setup/scripts/65-trust-vertical.sh` — batch enable trust modules/fields on vertical sites

### Docs
- README, `docs/ops.md`, `docs/trust-model.md` — publisher page and vertical bootstrap

## v0.5.0-trusted-feed-api — 2026-08-14

### Added
- Machine-readable trusted feeds: RSS 2.0 and JSON for `/trusted`, `/trusted/official`, `/trusted/enterprise`, `/trusted/aggregate`
- `TrustedFeedBuilder` service — shared item shape with trust level, publisher, provenance hash, source URL
- HTML trust pages link to RSS/JSON and expose `<link rel="alternate">` for feed discovery
- Optional `?limit=` query parameter (1–100, default 30) on feed endpoints

### Docs
- README and `docs/ops.md` — feed URLs and sample `curl`

## v0.4.0-provenance-export — 2026-08-14

### Added
- CSV export for provenance audit: `/admin/xmt/provenance/export` (button on audit page)
- `ProvenanceAuditExporter` service — shared by admin UI and Drush
- Drush `xmt:provenance-export` (`--limit`, `--trust-level`, `--output`)

### Docs
- `docs/trust-model.md` — audit export marked implemented
- `docs/ops.md` — export usage

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
