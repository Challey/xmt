# Release notes — trust platform v0.4–v0.9

Consolidated summary for merging the autonomous roadmap slice (`cursor/trust-robots-l0-sitemap-2191` and stacked branches) into `main`.

## Versions included

| Version | Highlights |
|---------|------------|
| v0.4.0 | Provenance audit CSV export + Drush `xmt:provenance-export` |
| v0.5.0 | Trusted RSS/JSON feeds (`/trusted/*/feed.rss`, `.json`) |
| v0.6.0 | Publisher public pages + `65-trust-vertical.sh` |
| v0.7.0 | `/publishers` directory + per-publisher feeds |
| v0.8.0 | `/trusted/sitemap.xml` + `75-verify-trust.sh` + homepage footer |
| v0.9.0 | `robots.txt` sitemap line + L0 articles in sitemap |

## Post-merge verify (xmt.pub)

```bash
cd /home/wwwroot/xmt
composer install --no-dev -o
vendor/bin/drush --uri=xmt.pub cr
bash setup/scripts/75-verify-trust.sh
curl -s https://xmt.pub/robots.txt | grep trusted/sitemap
```

## Suggested tag

`v0.9.0-trust-platform` (do not force-move existing tags).

## Deploy Server B

See `docs/deploy-server-b.md` — run `75-verify-trust.sh` with `HOST=xmt.pub BASE=https://xmt.pub` after pull + composer + drush.
