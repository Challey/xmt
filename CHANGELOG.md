# Changelog

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
