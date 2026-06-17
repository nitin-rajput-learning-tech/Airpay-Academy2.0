#!/usr/bin/env python3
"""Brand Book 2026-06 teal sweep — non-SCSS surfaces (PHP layouts, mustache).

Same gradient->deep / accent->bright / dark-teal->dark-blue rules as the SCSS
pass, EXCEPT email body text -> primary #0066A7 (AA-safe; bright #1985DD is only
~3.4:1 on white and fails WCAG AA for normal text)."""
import io, sys, re
from pathlib import Path

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
T = Path(r"D:\Claude Local\airpay-ld-os\theme\sentientia")

# files that follow the standard rules
STD_RULES = [
    (r"#0066A7, #0f7a73", "#0066A7, #0d5da1", "grad ->deep"),
    (r"#0f7a73, #059669", "#1985DD, #059669", "teal->green start ->bright"),
    (r"#134e4a", "#0d3a5c", "dark teal ->dark blue"),
    (r"#0f7a73", "#1985DD", "bare accent ->bright"),
]
STD_FILES = [
    T / "layout" / "frontpage.php",
    T / "layout" / "dashboard.php",
    T / "templates" / "head.mustache",
    T / "templates" / "dashboard.mustache",
]
# email: conservative AA-safe primary for any teal (body text legibility)
EMAIL_RULES = [(r"#0f7a73", "#0066A7", "email teal ->primary (AA-safe)")]
EMAIL_FILES = [T / "templates" / "core" / "email_html.mustache"]

def apply(files, rules):
    tot = 0
    for f in files:
        text = f.read_text(encoding="utf-8"); orig = text; counts = []
        for pat, repl, label in rules:
            text, n = re.subn(pat, repl, text, flags=re.IGNORECASE)
            if n: counts.append(f"{n}x {label}")
        if text != orig:
            f.write_text(text, encoding="utf-8")
            nf = sum(int(c.split('x')[0]) for c in counts); tot += nf
            print(f"  {f.relative_to(T)}  ({nf}): " + "; ".join(counts))
    return tot

total = apply(STD_FILES, STD_RULES) + apply(EMAIL_FILES, EMAIL_RULES)
print(f"\nTOTAL non-SCSS teal replaced: {total}")

# residual check across whole theme (mustache/php/js/css), excluding revert comments
print("\n--- residual teal across theme (php/mustache/js/css) ---")
res = 0
for f in T.rglob("*"):
    if f.suffix.lower() not in (".php", ".mustache", ".js", ".css"): continue
    for i, line in enumerate(f.read_text(encoding="utf-8", errors="replace").splitlines(), 1):
        if re.search(r"#0f7a73|#134e4a|#0d6b65|#1fa69c", line, re.IGNORECASE):
            print(f"  RESIDUAL {f.relative_to(T)}:{i}: {line.strip()[:90]}"); res += 1
print(f"residual: {res}")
