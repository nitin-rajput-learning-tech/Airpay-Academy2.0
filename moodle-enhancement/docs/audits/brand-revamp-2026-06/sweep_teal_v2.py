#!/usr/bin/env python3
"""Brand Book 2026-06 teal sweep v2 — alternate teal representations.

The hex sweeps (v1) missed teal expressed as rgba() and two dark-teal hexes:
  rgba(15,122,115,A)  == #0f7a73 in rgba form  -> rgba(25,133,221,A) (brand bright-blue)
  #0a4a42 / #0a5c50   == dark teal gradient ends -> #0d5da1 (brand deep-blue)
  #006699             == pre-brand blue default  -> #0066A7 (brand primary)

Roots: deployed XAMPP tree + both source local/ trees + theme + docs-site."""
import io, sys, re
from pathlib import Path
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

ROOTS = [
    Path(r"C:\xampp\htdocs\moodle5\public\theme\sentientia"),
    Path(r"C:\xampp\htdocs\moodle5\public\local"),
    Path(r"D:\Claude Local\airpay-ld-os\theme\sentientia"),
    Path(r"D:\Claude Local\airpay-ld-os\local"),
    Path(r"D:\Claude Local\airpay-ld-os\moodle-enhancement\local"),
    Path(r"D:\Claude Local\airpay-ld-os\docs-site"),
]
EXTS = {".css", ".scss", ".mustache", ".php", ".js"}
RULES = [
    # rgba teal (any spacing, any alpha) -> brand bright-blue rgba (preserve alpha)
    (r"rgba\(\s*15\s*,\s*122\s*,\s*115\s*,", "rgba(25, 133, 221,", "rgba teal -> bright-blue"),
    (r"#0a4a42", "#0d5da1", "dark teal -> deep-blue"),
    (r"#0a5c50", "#0d5da1", "dark teal -> deep-blue"),
    (r"#006699", "#0066A7", "old-blue default -> brand primary"),
]
total = {}
seen = set()
files = []
for root in ROOTS:
    if root.exists():
        for f in root.rglob("*"):
            if f.suffix.lower() in EXTS and f.resolve() not in seen:
                seen.add(f.resolve()); files.append(f)
for f in files:
    try: text = f.read_text(encoding="utf-8")
    except Exception: continue
    orig = text; n = 0
    for pat, repl, _ in RULES:
        text, c = re.subn(pat, repl, text, flags=re.IGNORECASE); n += c
    if text != orig:
        f.write_text(text, encoding="utf-8"); total[str(f)] = n
for k in sorted(total): print(f"  {total[k]:>2}  {k}")
print(f"\nfiles changed: {len(total)}   replacements: {sum(total.values())}")
# residual
res = 0
for f in files:
    try: t = f.read_text(encoding='utf-8', errors='replace')
    except Exception: continue
    for m in re.finditer(r"rgba\(\s*15\s*,\s*122\s*,\s*115|#0a4a42|#0a5c50|#006699", t, re.I):
        res += 1
print(f"residual teal-v2 forms: {res}")
