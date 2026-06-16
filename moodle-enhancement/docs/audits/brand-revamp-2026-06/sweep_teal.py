#!/usr/bin/env python3
"""
Brand Book 2026-06 (BB-revamp-noweb.pdf) — teal-straggler sweep.

Replaces every HARDCODED teal hex literal in the Sentientia theme SCSS with the
on-brand blue equivalent. The `var(--ap-teal-*)` token consumers are already
handled by the ramp alias in _tokens.scss, so this only targets raw hex.

Rules (order matters — gradient pairs consumed before the bare catch-all):
  gradient-end paired with primary  -> deep blue   #0d5da1   (classy primary->deep)
  teal start of a teal->green pair   -> bright blue #1985DD
  shimmer mid stop                   -> bright blue #1985DD
  teal-light backgrounds             -> blue-50     #e8f4fd
  dark teal                          -> dark blue   #0d3a5c
  any remaining bare teal accent     -> bright blue #1985DD  (strokes/text/fallbacks)
  #059669 (green variety)            -> LEFT ALONE  (Phase-2 design call)

Skips _tokens.scss / _tokens-dark.scss (their #0f7a73 now lives only in
revert-documentation comments).
"""
import io, sys, re
from pathlib import Path

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

ROOT = Path(r"D:\Claude Local\airpay-ld-os\theme\sentientia\scss")
SKIP = {"_tokens.scss", "_tokens-dark.scss"}

# Ordered, case-insensitive replacements. (pattern, replacement, label)
RULES = [
    # 1. two-stop primary->teal gradients  -> primary->deep
    (r"#0066A7 0%, #0f7a73 100%", "#0066A7 0%, #0d5da1 100%", "grad 2-stop ->deep"),
    # 2. login 3-stop  ...#0066A7 40%, #0f7a73 100%  -> deep
    (r"#0066A7 40%, #0f7a73 100%", "#0066A7 40%, #0d5da1 100%", "login 3-stop ->deep"),
    # 3. dark-mode  #0a3d6b 0%, #0f7a73 100%  -> deep
    (r"#0a3d6b 0%, #0f7a73 100%", "#0a3d6b 0%, #0d5da1 100%", "darkmode grad ->deep"),
    # 4. teal->green variety  #0f7a73 0%, #059669 100%  -> bright->green (teal start gone)
    (r"#0f7a73 0%, #059669 100%", "#1985DD 0%, #059669 100%", "teal->green start ->bright"),
    # 5. no-stop primary,teal gradients  -> primary,deep
    (r"#0066A7, #0f7a73", "#0066A7, #0d5da1", "grad no-stop ->deep"),
    # 6. shimmer mid stop  #0f7a73 50%  -> bright
    (r"#0f7a73 50%", "#1985DD 50%", "shimmer mid ->bright"),
    # 7. teal-light backgrounds -> blue-50
    (r"#e5f4f3", "#e8f4fd", "teal-light bg ->blue-50"),
    (r"#e6f5f3", "#e8f4fd", "teal-light bg ->blue-50"),
    # 8. dark teal -> dark blue
    (r"#134e4a", "#0d3a5c", "dark teal ->dark blue"),
    # 9. catch-all bare teal accent (strokes/text/fallbacks/$secondarycolor) -> bright
    (r"#0f7a73", "#1985DD", "bare accent ->bright"),
]

total = 0
for f in sorted(ROOT.rglob("*.scss")):
    if f.name in SKIP:
        continue
    text = f.read_text(encoding="utf-8")
    orig = text
    counts = []
    for pat, repl, label in RULES:
        new, n = re.subn(pat, repl, text, flags=re.IGNORECASE)
        if n:
            counts.append(f"{n}x {label}")
        text = new
    if text != orig:
        f.write_text(text, encoding="utf-8")
        n_file = sum(int(c.split("x")[0]) for c in counts)
        total += n_file
        rel = f.relative_to(ROOT.parent.parent)
        print(f"  {rel}  ({n_file}): " + "; ".join(counts))

print(f"\nTOTAL hardcoded teal hex replaced: {total}")

# Verify nothing teal remains outside the two skipped token files.
print("\n--- residual teal check (excluding token-file revert comments) ---")
residual = 0
for f in sorted(ROOT.rglob("*.scss")):
    if f.name in SKIP:
        continue
    for i, line in enumerate(f.read_text(encoding="utf-8").splitlines(), 1):
        if re.search(r"#0f7a73|#0d6b65|#1fa69c|#48b8af|#80cec7|#b8e3df|#e5f4f3|#e6f5f3|#0a5c56|#074d48|#043e3a|#134e4a", line, re.IGNORECASE):
            print(f"  RESIDUAL {f.name}:{i}: {line.strip()}")
            residual += 1
print(f"residual teal literals remaining: {residual}")
