#!/usr/bin/env python3
"""Brand Book 2026-06 — off-brand residue closure (post brand-verify workflow).

The adversarial audit (wf_03790a60) found four off-brand families the teal-only
sweeps missed. This closes the UNAMBIGUOUS ones (no semantic collisions) + the
a11y accent-as-text failures. Semantic-aware cases (#34d399 success vs accent,
#0a2e1a badge vs onboarding, #d97706 warning vs decorative, Bootstrap $info,
hero consistency) are handled by targeted edits, NOT here.

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
    Path(r"D:\Claude Local\airpay-ld-os\docs-site\docs"),
]
EXTS = {".css", ".scss", ".mustache", ".php", ".js"}
SKIP_SUBSTR = ["MONOLITH", "_archive"]

# UNAMBIGUOUS off-brand hex remaps (none are semantic colours in this palette)
HEX = [
    ("#7c3aed", "#6d58a5"),  # Tailwind violet -> brand purple
    ("#4f46e5", "#6d58a5"),  # indigo -> brand purple
    ("#5eead4", "#4da8e4"),  # teal-mint -> brand blue-300
    ("#efce2e", "#ed692b"),  # gold star -> brand orange
    ("#fbbf24", "#ed692b"),  # gold star -> brand orange
    ("#ea580c", "#ed692b"),  # orange-600 near-miss -> brand orange
    ("#059669", "#0d5da1"),  # emerald decorative -> brand deep-blue
    ("#0a4a44", "#0d5da1"),  # dark teal-green gradient -> brand deep-blue
    ("#f5f3ff", "#f0edf7"),  # violet tint -> purple-derived tint
    ("#ede9fe", "#e8e2f2"),  # violet tint -> purple-derived tint
    ("#d8b4fe", "#b9acd5"),  # violet mid -> purple-derived mid
    ("#5b21b6", "#4a3d73"),  # violet dark -> purple-derived dark
]

# a11y: brand bright-blue accent used as FOREGROUND text -> brand primary (AA-safe).
# Lookbehind (?<![-\w]) excludes background-color/border-color and word chars.
A11Y = [
    (re.compile(r"(?<![-\w])color(\s*:\s*)#1985dd", re.I), r"color\g<1>#0066A7"),
    (re.compile(r"(?<![-\w])color(\s*:\s*)var\(--ap-color-accent\)", re.I), r"color\g<1>var(--ap-color-primary)"),
    (re.compile(r"(?<![-\w])color(\s*:\s*)var\(--ap-accent\s*,\s*#1985dd\s*\)", re.I), r"color\g<1>var(--ap-primary, #0066A7)"),
    (re.compile(r"(?<![-\w])color(\s*:\s*)var\(--ap-accent\)", re.I), r"color\g<1>var(--ap-primary)"),
]

def skip(p):
    s = str(p)
    return any(x in s for x in SKIP_SUBSTR)

seen, files = set(), []
for root in ROOTS:
    if root.exists():
        for f in root.rglob("*"):
            if f.suffix.lower() in EXTS and not skip(f) and f.resolve() not in seen:
                seen.add(f.resolve()); files.append(f)

hex_total, a11y_total, changed = 0, 0, 0
for f in files:
    try: text = f.read_text(encoding="utf-8")
    except Exception: continue
    orig = text; h = 0; a = 0
    for old, new in HEX:
        cnt = len(re.findall(re.escape(old), text, re.I))
        if cnt: text = re.sub(re.escape(old), new, text, flags=re.I); h += cnt
    for pat, repl in A11Y:
        text, c = pat.subn(repl, text); a += c
    if text != orig:
        f.write_text(text, encoding="utf-8"); changed += 1; hex_total += h; a11y_total += a
print(f"files changed: {changed}   hex remaps: {hex_total}   a11y color->primary: {a11y_total}")

print("\n--- residual unambiguous off-brand hexes ---")
res = 0
bad = [h[0] for h in HEX]
for f in files:
    try: t = f.read_text(encoding="utf-8")
    except Exception: continue
    for b in bad:
        for m in re.finditer(re.escape(b), t, re.I):
            res += 1; break
print(f"residual files with off-brand hex: {res}")
