#!/usr/bin/env python3
"""XMT content agent: fetch mature RSS/Atom feeds and publish into Drupal multisite."""

from __future__ import annotations

import hashlib
import json
import os
import re
import subprocess
import sys
import time
import urllib.request
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(os.environ.get("XMT_ROOT", "/home/wwwroot/xmt"))
AGENT_DIR = Path(__file__).resolve().parent
SOURCES = AGENT_DIR / "sources.yaml"
STATE = AGENT_DIR / "state.json"
DRUSH = ROOT / "vendor" / "bin" / "drush"
MAX_PER_FEED = int(os.environ.get("XMT_MAX_PER_FEED", "5"))
USER_AGENT = "XMT-Agent/1.0 (+https://xmt.pub)"


def load_yaml(path: Path) -> dict:
    try:
        import yaml  # type: ignore
        return yaml.safe_load(path.read_text(encoding="utf-8"))
    except Exception:
        # Minimal YAML subset parser for our simple structure
        return json.loads(subprocess.check_output(
            ["php", "-r", 'echo json_encode(yaml_parse(file_get_contents($argv[1])));', str(path)],
            text=True,
        )) if False else _parse_simple_yaml(path.read_text(encoding="utf-8"))


def _parse_simple_yaml(text: str) -> dict:
    """Very small YAML reader sufficient for sources.yaml structure."""
    # Prefer PyYAML; if missing, shell out to python with ruamel or install.
    try:
        import yaml
        return yaml.safe_load(text)
    except ImportError:
        pass
    # Fallback: use ruby or php
    for cmd in (
        ["ruby", "-ryaml", "-rjson", "-e", "puts JSON.generate(YAML.load(STDIN.read))"],
        ["php", "-r", "echo json_encode(yaml_parse(stream_get_contents(STDIN)));"],
    ):
        try:
            out = subprocess.check_output(cmd, input=text, text=True)
            return json.loads(out)
        except Exception:
            continue
    raise SystemExit("Need PyYAML: pip install pyyaml")


def load_state() -> dict:
    if STATE.exists():
        return json.loads(STATE.read_text(encoding="utf-8"))
    return {"seen": {}}


def save_state(state: dict) -> None:
    STATE.write_text(json.dumps(state, ensure_ascii=False, indent=2), encoding="utf-8")


def fetch(url: str, timeout: int = 30) -> bytes:
    req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return resp.read()


def local(tag: str) -> str:
    if "}" in tag:
        return tag.rsplit("}", 1)[-1]
    return tag


def parse_feed(content: bytes) -> list[dict]:
    root = ET.fromstring(content)
    items: list[dict] = []
    # RSS
    for item in root.iter():
        if local(item.tag) != "item":
            continue
        title = link = desc = ""
        for child in list(item):
            t = local(child.tag)
            if t == "title":
                title = (child.text or "").strip()
            elif t == "link":
                link = (child.text or "").strip()
            elif t in ("description", "encoded"):
                desc = (child.text or "").strip()
        if title and link:
            items.append({"title": title, "link": link, "summary": desc})
    if items:
        return items
    # Atom
    for entry in root.iter():
        if local(entry.tag) != "entry":
            continue
        title = link = summary = ""
        for child in list(entry):
            t = local(child.tag)
            if t == "title":
                title = (child.text or "").strip()
            elif t == "link":
                href = child.attrib.get("href", "")
                rel = child.attrib.get("rel", "alternate")
                if href and rel in ("alternate", ""):
                    link = href
            elif t in ("summary", "content"):
                summary = (child.text or "").strip()
        if title and link:
            items.append({"title": title, "link": link, "summary": summary})
    return items


def strip_html(html: str) -> str:
    text = re.sub(r"<script[\s\S]*?</script>", "", html, flags=re.I)
    text = re.sub(r"<style[\s\S]*?</style>", "", text, flags=re.I)
    text = re.sub(r"<[^>]+>", "", text)
    return re.sub(r"\s+", " ", text).strip()


def publish(site: str, payload: dict) -> bool:
    # Use /tmp so PHP/drush can always read the payload regardless of agent dir perms.
    tmp = Path("/tmp") / f"xmt_payload_{hashlib.md5(payload['source_url'].encode()).hexdigest()}.json"
    tmp.write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")
    try:
        tmp.chmod(0o644)
    except OSError:
        pass
    cmd = [str(DRUSH), f"--uri={site}", "xmt:import-article", str(tmp)]
    try:
        res = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
        tmp.unlink(missing_ok=True)
        if res.returncode != 0:
            print(f"  FAIL {site}: {res.stderr[-400:] or res.stdout[-400:]}", file=sys.stderr)
            return False
        print(f"  OK {site} nid={res.stdout.strip().splitlines()[-1] if res.stdout.strip() else '?'}")
        return True
    except Exception as e:
        tmp.unlink(missing_ok=True)
        print(f"  ERR {site}: {e}", file=sys.stderr)
        return False


def main() -> int:
    if not SOURCES.exists():
        print("missing sources.yaml", file=sys.stderr)
        return 1
    # Ensure pyyaml
    try:
        import yaml  # noqa: F401
    except ImportError:
        subprocess.check_call([sys.executable, "-m", "pip", "install", "--user", "pyyaml", "-q"])
        import yaml  # noqa: F401

    data = yaml.safe_load(SOURCES.read_text(encoding="utf-8"))
    state = load_state()
    seen = state.setdefault("seen", {})
    published = 0
    errors = 0

    for key, block in (data.get("sources") or {}).items():
        site = block["site"]
        domain = block.get("domain", key)
        print(f"== Domain {key} -> {site}")
        for feed in block.get("feeds") or []:
            name, url = feed.get("name"), feed.get("url")
            print(f"  Feed: {name} ({url})")
            try:
                raw = fetch(url)
                items = parse_feed(raw)[:MAX_PER_FEED]
            except Exception as e:
                print(f"  skip fetch: {e}", file=sys.stderr)
                errors += 1
                continue
            for it in items:
                link = it["link"]
                h = hashlib.sha256(link.encode()).hexdigest()
                if h in seen:
                    continue
                body = it.get("summary") or ""
                if body and "<" in body:
                    # keep basic html
                    pass
                else:
                    body = f"<p>{strip_html(body)}</p><p>来源：<a href=\"{link}\">{link}</a></p>"
                payload = {
                    "title": it["title"][:200],
                    "body": body,
                    "format": "full_html",
                    "source_url": link,
                    "source_name": name,
                    "domain": domain,
                }
                if publish(site, payload):
                    seen[h] = {"url": link, "site": site, "ts": int(time.time())}
                    published += 1
                    # Also ensure xmt gets a copy if vertical site (module hooks may handle)
                    if site != "xmt.pub":
                        publish("xmt.pub", payload)
                else:
                    errors += 1
                time.sleep(0.5)

    save_state(state)
    print(f"Done. published={published} errors={errors}")
    return 0 if errors == 0 or published > 0 else 2


if __name__ == "__main__":
    raise SystemExit(main())
