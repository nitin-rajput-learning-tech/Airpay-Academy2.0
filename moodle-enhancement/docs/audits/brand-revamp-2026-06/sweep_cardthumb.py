#!/usr/bin/env python3
"""Brand Book 2026-06 — remap card-thumb variety palette to brand secondaries.

The catalog/featured course-card thumbnails used a multi-hue variety set
(cyan/magenta/pink/gold) that is off-brand. Remap to a blue-DOMINANT set with
ONE sparing purple + ONE sparing orange variant, per the book's philosophy.
Full-gradient-string replacement (these exact strings are the variety palette
only — safe). Applied across deployed + both source local/ trees."""
import io, sys
from pathlib import Path
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

ROOTS = [
    Path(r"C:\xampp\htdocs\moodle5\public\local"),
    Path(r"D:\Claude Local\airpay-ld-os\local"),
    Path(r"D:\Claude Local\airpay-ld-os\moodle-enhancement\local"),
]
FILES = ["sentientia_catalog/styles.css", "sentientia_courses/styles.css"]

REPLACEMENTS = [
    # v1 bright→CYAN  ->  bright→sky (airy blue)
    ("linear-gradient(135deg, #1985DD, #0aa3a3)", "linear-gradient(135deg, #1985DD, #9cdbf4)"),
    # v2 purple→MAGENTA  ->  brand purple #6d58a5 family (sparing)
    ("linear-gradient(135deg, #6a3fb5, #b34db8)", "linear-gradient(135deg, #6d58a5, #9b86c9)"),
    # v3 PINK→orange  ->  brand orange #ed692b family (sparing)
    ("linear-gradient(135deg, #c2407e, #f0883e)", "linear-gradient(135deg, #ed692b, #f7a072)"),
    # v4 github-blue→deep  ->  navy→primary (brand blue)
    ("linear-gradient(135deg, #1f6feb, #0d5da1)", "linear-gradient(135deg, #003d66, #0066A7)"),
    # v5 amber→GOLD  ->  deep→bright (brand blue)
    ("linear-gradient(135deg, #d97706, #e0b400)", "linear-gradient(135deg, #0d5da1, #1985DD)"),
]

total = 0
for root in ROOTS:
    for rel in FILES:
        f = root / rel
        if not f.exists():
            continue
        text = f.read_text(encoding="utf-8"); orig = text; n = 0
        for old, new in REPLACEMENTS:
            c = text.count(old); text = text.replace(old, new); n += c
        if text != orig:
            f.write_text(text, encoding="utf-8"); total += n
            print(f"  {n:>2}  {f}")
print(f"\nTOTAL card-thumb gradients remapped: {total}")

# residual off-brand variety hexes across the 6 files
print("\n--- residual off-brand card hexes ---")
res = 0
bad = ["#0aa3a3", "#6a3fb5", "#b34db8", "#c2407e", "#f0883e", "#d97706", "#e0b400", "#1f6feb"]
for root in ROOTS:
    for rel in FILES:
        f = root / rel
        if not f.exists(): continue
        t = f.read_text(encoding="utf-8")
        for b in bad:
            if b in t:
                print(f"  RESIDUAL {b} in {f}"); res += 1
print(f"residual: {res}")
