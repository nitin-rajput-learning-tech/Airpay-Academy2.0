#!/usr/bin/env python3
"""Brand Book 2026-06 teal sweep — plugin stylesheets + templates + JS + email PHP.

Teal is distributed across airpay/sentientia plugin styles.css, mustache inline
styles, AMD JS, and email-config PHP — Moodle aggregates plugin styles.css into
the served theme CSS, so these must be swept for true 100% no-teal.

Roots:
  1. DEPLOYED target  C:\\xampp\\htdocs\\moodle5\\public\\local   (fixes runtime/compiled CSS)
  2. source tree A    <repo>\\local
  3. source tree B    <repo>\\moodle-enhancement\\local
  4. docs-site brand css

Rules: gradient pairs -> deep #0d5da1; teal->green start -> bright #1985DD;
dark teal #134e4a -> #0d3a5c; teal-light bg -> #e8f4fd; bare teal -> bright
#1985DD; EXCEPT files whose path contains 'email'/'notification' -> bare teal
to primary #0066A7 (AA-safe for body text)."""
import io, sys, re
from pathlib import Path

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

ROOTS = [
    Path(r"C:\xampp\htdocs\moodle5\public\local"),
    Path(r"D:\Claude Local\airpay-ld-os\local"),
    Path(r"D:\Claude Local\airpay-ld-os\moodle-enhancement\local"),
]
EXTRA_FILES = [Path(r"D:\Claude Local\airpay-ld-os\docs-site\docs\assets\airpay-brand.css")]
EXTS = {".css", ".scss", ".mustache", ".js", ".php"}

# shared (run BEFORE the bare catch-all)
PRE = [
    (r"#0066A7 0%, #0f7a73 100%", "#0066A7 0%, #0d5da1 100%"),
    (r"#0066A7, #0f7a73",          "#0066A7, #0d5da1"),
    (r"#0066a7,#0f7a73",           "#0066a7,#0d5da1"),
    (r"#0066A7 0%,#0f7a73",        "#0066A7 0%,#0d5da1"),
    (r"#0f7a73, #059669",          "#1985DD, #059669"),
    (r"#0f7a73,#059669",           "#1985DD,#059669"),
    (r"#1f6feb, ?#0f7a73",         "#1f6feb, #0d5da1"),
    (r"#134e4a",                   "#0d3a5c"),
    (r"#e5f4f3",                   "#e8f4fd"),
    (r"#e6f5f3",                   "#e8f4fd"),
]
BARE_WEB   = (r"#0f7a73", "#1985DD")   # accent on web surfaces
BARE_EMAIL = (r"#0f7a73", "#0066A7")   # AA-safe primary for email body text
# other teal-ramp hexes that may appear standalone
RAMP = [(r"#0d6b65","#0d5da1"),(r"#1fa69c","#1985DD"),(r"#0a5c56","#0d5da1"),
        (r"#074d48","#005590"),(r"#043e3a","#003050")]

def is_email(p):
    s = str(p).lower()
    return "email" in s or "notification" in s

def process(f):
    try: text = f.read_text(encoding="utf-8")
    except Exception: return 0
    orig = text
    for pat, repl in PRE:
        text = re.sub(pat, repl, text, flags=re.IGNORECASE)
    bare = BARE_EMAIL if is_email(f) else BARE_WEB
    text = re.sub(bare[0], bare[1], text, flags=re.IGNORECASE)
    for pat, repl in RAMP:
        text = re.sub(pat, repl, text, flags=re.IGNORECASE)
    if text != orig:
        # count teal removed
        n = len(re.findall(r"#0f7a73|#0d6b65|#1fa69c|#0a5c56|#074d48|#043e3a|#134e4a|#e5f4f3|#e6f5f3", orig, re.I))
        f.write_text(text, encoding="utf-8")
        return n
    return 0

total = {}
files = []
for root in ROOTS:
    if root.exists():
        files += [f for f in root.rglob("*") if f.suffix.lower() in EXTS]
files += [f for f in EXTRA_FILES if f.exists()]

for f in files:
    n = process(f)
    if n:
        key = str(f)
        total[key] = n

for k in sorted(total): print(f"  {total[k]:>2}  {k}")
print(f"\nfiles changed: {len(total)}   teal literals replaced: {sum(total.values())}")

# residual across all roots (UI exts only)
print("\n--- residual teal (UI files, all roots) ---")
res = 0
for f in files:
    try: lines = f.read_text(encoding='utf-8', errors='replace').splitlines()
    except Exception: continue
    for i,l in enumerate(lines,1):
        if re.search(r"#0f7a73|#0d6b65|#1fa69c|#0a5c56|#074d48|#043e3a|#134e4a", l, re.I):
            print(f"  {f}:{i}: {l.strip()[:80]}"); res+=1
print(f"residual: {res}")
