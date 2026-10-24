#!/usr/bin/env python3
"""Check that enabling Omnium adds no external origin on either front URL."""

import json
import sys
from pathlib import Path
from urllib.parse import urlsplit


def origins(record):
    return {
        f"{parsed.scheme}://{parsed.netloc}"
        for url in record["html"] + record["network"]
        if (parsed := urlsplit(url)).scheme in ("http", "https")
    }


def main(directory):
    results = {}
    for route in ("recette-v1-01_", "en_recette-v1-01_"):
        off = json.loads((directory / f"resources-off-{route}.json").read_text())
        on = json.loads((directory / f"resources-on-{route}.json").read_text())
        if off["status"] != 200 or on["status"] != 200:
            raise ValueError(f"Unexpected HTTP status for {route}: {off['status']}/{on['status']}")
        before, after = origins(off), origins(on)
        results[route] = {
            "off": sorted(before),
            "on": sorted(after),
            "added": sorted(after - before),
        }
    print(json.dumps(results, ensure_ascii=False))
    return 1 if any(result["added"] for result in results.values()) else 0


if __name__ == "__main__":
    sys.exit(main(Path(sys.argv[1])))
