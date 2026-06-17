#!/usr/bin/env python3
"""Brand Book 2026-06 — targeted semantic-aware closure edits (production source).

The v3 sweep handles unambiguous off-brand hexes; these are the context-sensitive
fixes it deliberately leaves (semantic success-green / warning-amber / accent-fill,
Bootstrap $info, hero). Exact unique-string replacements; prints match counts."""
import io, sys
from pathlib import Path
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

T = Path(r"D:\Claude Local\airpay-ld-os\theme\sentientia")
LOCALS = [Path(r"D:\Claude Local\airpay-ld-os\local"),
          Path(r"D:\Claude Local\airpay-ld-os\moodle-enhancement\local")]

# (file, old, new)
EDITS = [
    # Bootstrap $info cyan -> brand bright-blue (primitive + active preset)
    (T/"scss/bootstrap/_variables.scss",
     "$teal:    #20c997 !default;\n$cyan:    #17a2b8 !default;",
     "// Brand Book 2026-06: alias teal/cyan to brand bright-blue so core $info\n// (alert-info/badge-info/text-info) + the $colors map render brand blue, not cyan.\n$teal:    #1985DD !default;\n$cyan:    #1985DD !default;"),
    (T/"scss/preset/default.scss",
     "$teal:    #20c997 !default;\n$cyan:    #008196 !default;",
     "// Brand Book 2026-06: alias teal/cyan to brand bright-blue (active preset $info -> blue).\n$teal:    #1985DD !default;\n$cyan:    #1985DD !default;"),
    (T/"scss/preset/default.scss",
     "$secondarycolor: #1985DD !default; // Teal accent (was #006699)",
     "$secondarycolor: #1985DD !default; // Brand bright-blue accent — Brand Book 2026-06 (label was mislabelled 'teal')"),
    # Dark-mode accent (emerald -> blue); success row left emerald (semantic)
    (T/"scss/moodle/dark_mode.scss",
     ".airpay-dash__stat--accent .airpay-dash__stat-icon { background: rgba(16,185,129,0.15); color: #34d399; }",
     ".airpay-dash__stat--accent .airpay-dash__stat-icon { background: rgba(25,133,221,0.15); color: #1985DD; } /* Brand Book 2026-06: accent is brand blue, not emerald */"),
    (T/"scss/moodle/partials/_dark-mode-global.scss",
     "body.dark-mode .ap-badge--accent { background: #0a2e1a !important; color: #34d399 !important; }",
     "body.dark-mode .ap-badge--accent { background: #0d2540 !important; color: #60a5fa !important; } /* Brand Book 2026-06: accent badge brand blue, not emerald */"),
    (T/"scss/moodle/partials/_dark-mode-global.scss",
     "body.dark-mode .ap-onboard { background: linear-gradient(135deg, #0d2540, #0a2e1a, #0d1b36) !important; }",
     "body.dark-mode .ap-onboard { background: linear-gradient(135deg, #0d2540, #0a3d6b, #0d1b36) !important; } /* Brand Book 2026-06: blue-navy mid-stop (was green) */"),
    # Frontpage decorative gradients
    (T/"layout/frontpage.php",
     "'linear-gradient(135deg, #6d58a5, #6d58a5)',",
     "'linear-gradient(135deg, #6d58a5, #0d5da1)',"),
    (T/"layout/frontpage.php",
     "'linear-gradient(135deg, #d97706, #ed692b)',",
     "'linear-gradient(135deg, #ed692b, #0066A7)',"),
    (T/"layout/frontpage.php",
     '<div class="ap-why__card-icon" style="background: #fef3c7; color: #d97706;"><i class="fa fa-mobile"></i></div>',
     '<div class="ap-why__card-icon" style="background: #fdeee6; color: #ed692b;"><i class="fa fa-mobile"></i></div>'),
    # Legacy-admin decorative course-card gradients (drop red/amber decorative)
    (T/"scss/moodle/partials/_legacy-admin.scss",
     ".airpay-homepage__course-card:nth-child(4) .airpay-homepage__course-header { background: linear-gradient(135deg, #d97706 0%, #dc2626 100%); }",
     ".airpay-homepage__course-card:nth-child(4) .airpay-homepage__course-header { background: linear-gradient(135deg, #ed692b 0%, #0066A7 100%); }"),
    (T/"scss/moodle/partials/_legacy-admin.scss",
     ".airpay-homepage__course-card:nth-child(6) .airpay-homepage__course-header { background: linear-gradient(135deg, #6d58a5 0%, #dc2626 100%); }",
     ".airpay-homepage__course-card:nth-child(6) .airpay-homepage__course-header { background: linear-gradient(135deg, #6d58a5 0%, #0d5da1 100%); }"),
    # version bump
    (T/"version.php",
     "$plugin->version   = 2026061602;",
     "// 2026061603 — brand-verify closure: Bootstrap $info cyan->blue, dark-mode emerald\n// accent->blue, decorative emerald/amber/red->brand, violet->purple, gold->orange, a11y.\n$plugin->version   = 2026061603;"),
]

# gamification 'today' (emerald -> blue) across both source local/ trees
GAM_OLD = "body.dark-mode .airpay-gamification__today {\n    background: #0a2e1a !important;\n    color: #34d399 !important;\n}"
GAM_NEW = "body.dark-mode .airpay-gamification__today {\n    background: #0d2540 !important;\n    color: #60a5fa !important;\n}"

def apply(path, old, new):
    if not path.exists(): return f"MISS-FILE {path}"
    s = path.read_text(encoding="utf-8"); n = s.count(old)
    if n: path.write_text(s.replace(old, new), encoding="utf-8")
    return f"{n}x  {path.name} :: {old[:48].strip()}"

for f, old, new in EDITS:
    print(" ", apply(f, old, new))
for root in LOCALS:
    print(" ", apply(root/"sentientia_gamification/styles.css", GAM_OLD, GAM_NEW))
