#!/usr/bin/env python3
"""Static scan of every plugin install.xml for DDL definitions Moodle's XMLDB
rejects on MariaDB/utf8mb4. Two failure classes:
  A) CHAR field LENGTH > 1333  -> Moodle caps VARCHAR at 1333 chars.
  B) composite/single index whose char columns sum to > 999 bytes
     (utf8mb4 = 4 bytes/char, so > ~249 chars across the indexed char cols).
Prints every offender so they can be fixed in one batch instead of one-per-upgrade.
"""
import glob
import os
import re
import xml.etree.ElementTree as ET

ROOTS = [
    r"D:\Claude Local\airpay-ld-os\moodle-enhancement\local",
    r"D:\Claude Local\airpay-ld-os\local",
]
CHAR_MAX = 1333
INDEX_BYTE_LIMIT = 999  # MariaDB/InnoDB per-index prefix limit Moodle enforces

offenders_char = []
offenders_index = []

for root in ROOTS:
    for path in glob.glob(os.path.join(root, "*", "db", "install.xml")):
        try:
            tree = ET.parse(path)
        except ET.ParseError as e:
            print(f"PARSE ERROR {path}: {e}")
            continue
        rel = os.path.relpath(path, os.path.dirname(root))
        for table in tree.iter("TABLE"):
            tname = table.get("NAME")
            # Map field -> (type, length) for char fields.
            charlen = {}
            for fld in table.iter("FIELD"):
                ftype = (fld.get("TYPE") or "").lower()
                length = fld.get("LENGTH")
                if ftype == "char" and length and length.isdigit():
                    L = int(length)
                    charlen[fld.get("NAME")] = L
                    if L > CHAR_MAX:
                        offenders_char.append((rel, tname, fld.get("NAME"), L))
            # Indexes + unique keys over char columns.
            for idx in list(table.iter("INDEX")) + [
                k for k in table.iter("KEY") if (k.get("TYPE") or "") == "unique"
            ]:
                fields = [f.strip() for f in (idx.get("FIELDS") or "").split(",")]
                charcols = [f for f in fields if f in charlen]
                if not charcols:
                    continue
                total_chars = sum(charlen[f] for f in charcols)
                total_bytes = total_chars * 4  # utf8mb4 worst case
                if total_bytes > INDEX_BYTE_LIMIT:
                    offenders_index.append(
                        (rel, tname, idx.get("NAME"), ",".join(fields),
                         total_chars, total_bytes)
                    )

print("=== CLASS A: CHAR fields > 1333 ===")
for rel, t, f, L in offenders_char:
    print(f"  {rel} :: {t}.{f} = CHAR({L})")
print(f"  ({len(offenders_char)} found)")
print()
print("=== CLASS B: char index keys > 999 bytes (utf8mb4) ===")
for rel, t, idx, flds, c, b in offenders_index:
    print(f"  {rel} :: {t} index '{idx}' on ({flds}) = {c} chars / {b} bytes")
print(f"  ({len(offenders_index)} found)")
