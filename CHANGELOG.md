# Changelog

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
