#!/usr/bin/env python3
"""Compare Product JSON-LD from source and translated acceptance pages."""

import json
import sys
from html.parser import HTMLParser
from pathlib import Path


class JsonLdParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.in_json_ld = False
        self.blocks = []

    def handle_starttag(self, tag, attrs):
        if tag == "script" and dict(attrs).get("type") == "application/ld+json":
            self.in_json_ld = True

    def handle_endtag(self, tag):
        if tag == "script":
            self.in_json_ld = False

    def handle_data(self, data):
        if self.in_json_ld:
            self.blocks.append(json.loads(data))


def products(node):
    if isinstance(node, dict):
        if node.get("@type") == "Product":
            yield node
        for value in node.values():
            yield from products(value)
    elif isinstance(node, list):
        for value in node:
            yield from products(value)


def read_products(path):
    parser = JsonLdParser()
    parser.feed(Path(path).read_text(encoding="utf-8"))
    return [product for block in parser.blocks for product in products(block)]


def main():
    source_path, target_path, expected_name, expected_price, expected_sku, lang = sys.argv[1:]
    source = read_products(source_path)
    target = read_products(target_path)
    prefix = f"[{lang.upper()}] "
    matched = [
        (original, translated)
        for original in source
        for translated in target
        if expected_name in original.get("name", "")
        and translated.get("name") == prefix + original.get("name")
    ]
    ok = any(
        original.get("sku") == translated.get("sku") == expected_sku
        and original.get("offers", {}).get("price")
        == translated.get("offers", {}).get("price")
        == expected_price
        for original, translated in matched
    )
    print(
        f"source_products={len(source)} target_products={len(target)} "
        f"name_translated={bool(matched)} price_sku_intact={ok}"
    )
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
