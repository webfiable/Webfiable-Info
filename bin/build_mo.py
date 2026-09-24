#!/usr/bin/env python3
"""Compile a .po file into a GNU .mo file (standard library only).

There is no GNU gettext on the build machine, so the committed .mo is written by
this script. CI proves it equal to what `msgfmt` produces: `release_checks.py
i18n` compares `msgunfmt` of both. Never shipped: `bin/` is in `.distignore`.

Usage: build_mo.py <input.po> <output.mo>
"""

import os
import struct
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from release_checks import parse_po, read  # noqa: E402


def compile_mo(po_text):
    """The bytes of a little-endian GNU .mo holding every translated, non-fuzzy entry."""
    catalog = {}
    for e in parse_po(po_text):
        if e["fuzzy"] and e["msgid"]:
            continue
        if e["msgid"] and not e["msgstr"]:
            continue
        catalog[e["msgid"].encode("utf-8")] = e["msgstr"].encode("utf-8")
    ids = sorted(catalog)
    n = len(ids)
    orig_table = 7 * 4
    trans_table = orig_table + n * 8
    data_start = trans_table + n * 8
    offsets = []
    blob = b""
    for key in ids:
        offsets.append((len(key), data_start + len(blob)))
        blob += key + b"\0"
    for key in ids:
        value = catalog[key]
        offsets.append((len(value), data_start + len(blob)))
        blob += value + b"\0"
    header = struct.pack("<7I", 0x950412DE, 0, n, orig_table, trans_table, 0, data_start)
    tables = b"".join(struct.pack("<2I", length, offset) for length, offset in offsets)
    return header + tables + blob


def main(argv):
    if len(argv) != 2:
        sys.stderr.write(__doc__)
        return 2
    mo = compile_mo(read(argv[0]))
    with open(argv[1], "wb") as fh:
        fh.write(mo)
    print(f"build_mo: {argv[1]} written ({len(mo)} bytes)")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
