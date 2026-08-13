# Release notes — v0.1.0-trust

Tag `v0.1.0-trust` may already point at an earlier commit; this file summarizes the trust-platform slice intended for the next commit to `main` (do **not** force-move the tag).

## Summary

- Trust levels L0/L1/L2 on articles; official publisher seed; enterprise apply/approve.
- Trusted UI routes: `/trusted`, `/trusted/official`, `/trusted/enterprise`.
- DrupalX ↔ XMT HMAC claim bridge (`XMT_DX_BRIDGE_SECRET`, `POST /api/xmt/v1/dx-claim`).
- Agent syndication sets `trust_level=l0_aggregate` on imported articles.
- Documentation: vision, trust-model, architecture, drupalx-bridge, deploy-server-b; deploy script `scripts/deploy-to-b.md`.

## Suggested commit scope (exclude noise)

**Include:** `agent/`, `docs/`, `scripts/`, `CHANGELOG.md`, `README.md`, `RELEASE-NOTES-v0.1.0-trust.md`, `web/modules/custom/xmt_*`, `setup/modules/` mirror changes if kept in sync.

**Exclude:** `web/sites/xmt.pub/files/php/twig/**`, `agent/state.json` (runtime), local-only `settings.php` secrets.

## Post-commit (optional)

```bash
git tag -a v0.1.0-trust -m "Trust platform MVP"   # only if tag should move to new commit; never force-push
git push origin main
git push origin v0.1.0-trust   # if tag updated
```

## Verify on xmt.pub

```bash
vendor/bin/drush --uri=xmt.pub xmt:dx-claim-test
curl -s -o /dev/null -w '%{http_code}' http://xmt.wsl/trusted
```
