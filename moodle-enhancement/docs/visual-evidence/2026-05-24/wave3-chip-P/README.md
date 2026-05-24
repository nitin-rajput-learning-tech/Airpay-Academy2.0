# P2 #19 — prefers-reduced-motion stylelint enforcement (chip-P, 2026-05-24)

**Chip:** `claude/happy-carson-LxfFQ`
**Auditor:** Claude Opus 4.7 (1M context)
**Closes:** P2 #19 / §2.7 from
`moodle-enhancement/docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`
**Scope owner:** Theme-scoped stylelint config + doc note
**Touched files:** 5 (1 config, 1 version, 1 README, 1 doc, 1 PROJECT-STATE H2)
**Did NOT touch:** Any `.scss` rule, any `.mustache`, any `.php` other than `version.php`, any plugin source, the upstream `.stylelintrc` at repo root.

---

## Why this chip exists

`_tokens.scss:258` already collapses `--ap-duration-*` to `0ms` under
`@media (prefers-reduced-motion: reduce)`. That makes the token cascade
WCAG-2.3.3-correct for every animation that opts in via
`var(--ap-duration-*)` / `var(--ap-transition-*)`. But §2.7 of the
2026-05-24 platform visual audit flagged that several surface partials
declare timing inline — bypassing the cascade. With no lint rule
preventing it, future surfaces would regress.

This chip ships the lint rule. It does NOT fix existing violations —
that's a separate refactor chip (call it "chip-P+" for now). The lint
rule will fail loudly when run today, surfacing the 54+ violations
inventoried below.

---

## Commits

```
????????   feat(theme): stylelint rule for prefers-reduced-motion token enforcement (P2 #19)
????????   docs(rules): document the motion lint rule in .claude/rules/frontend.md
```

(commit hashes filled in by the merge.)

---

## What shipped

### 1. New config: `theme/airpayux/.stylelintrc.json`

Self-contained, theme-scoped JSON. Strict-JSON-parsable (verified via
`python -c "import json,sys; json.load(sys.stdin)"`). Does NOT extend
the upstream Moodle `.stylelintrc` at repo root — that file is JSON5
(has `#` comments + unquoted keys) and belongs to Moodle's grunt build.
Keeping the two configs separate avoids upstream-merge pain.

```json
{
    "customSyntax": "postcss-scss",
    "rules": {},
    "overrides": [
        {
            "files": ["scss/moodle/partials/_surface-*.scss"],
            "rules": {
                "declaration-property-value-disallowed-list": [
                    {
                        "transition-duration": ["/^(?!var\\().*$/"],
                        "transition": ["/[0-9]+(\\.[0-9]+)?(s|ms)/"]
                    },
                    {
                        "message": "Motion timing must reference an --ap-duration-* / --ap-transition-* token …",
                        "severity": "error"
                    }
                ]
            }
        }
    ]
}
```

#### What the two regex rules do

| Property | Pattern | Catches | Lets through |
|---|---|---|---|
| `transition-duration` | `/^(?!var\\().*$/` | `transition-duration: 0.2s` (literal value) | `transition-duration: var(--ap-duration-quick)` (var()) |
| `transition` (shorthand) | `/[0-9]+(\\.[0-9]+)?(s|ms)/` | `transition: all 0.2s ease` (any digit-followed-by-s/ms inside shorthand) | `transition: color var(--ap-transition-quick)` (zero digits before s/ms) |

The second pattern is intentionally broad — if ANY numeric+unit pair
appears in the shorthand, the rule fires, even if other parts of the
shorthand use `var()`. That keeps the shorthand from becoming an escape
hatch for inline timing.

### 2. `version.php` — bumped to `2026052404` / `1.0.34-beta`

`-bump` only. Body comment block updated with rationale (no code
changes). The bump invalidates the cached compiled CSS bundle so future
deploy → purge_caches.php → reload picks up the SCSS recompile, even
though no SCSS rules changed today (defensive; aligns the cache key
with the new on-disk config tree).

### 3. Doc update — `.claude/rules/frontend.md`

New "Motion & `prefers-reduced-motion`" section added under the design-
token block. Documents:

- The `_tokens.scss` cascade pattern (`--ap-duration-*` collapses to
  `0ms` under reduced motion).
- The named token API (`--ap-transition-quick|default|slow|emphatic`).
- The new lint rule + its scope (surface partials only).
- The per-line opt-out (`// stylelint-disable-next-line declaration-property-value-disallowed-list`).
- WCAG 2.3.3 reference + audit cross-link.

---

## Sample violation the rule catches

Pulled live from the codebase today. The rule fires on each of these
lines once it runs:

`moodle-enhancement/theme/airpayux/scss/moodle/partials/_surface-course.scss:213`:

```scss
.course_extended_menu_itemlink {
    border: 1px solid var(--ap-color-border);
    background: var(--ap-color-bg-surface);
    color: var(--ap-color-text-secondary);
    transition: all 0.2s ease;     /* ← rule fires here */
    text-decoration: none !important;
}
```

Suggested fix (NOT applied in this chip — deferred to refactor chip-P+):

```scss
.course_extended_menu_itemlink {
    border: 1px solid var(--ap-color-border);
    background: var(--ap-color-bg-surface);
    color: var(--ap-color-text-secondary);
    transition: all var(--ap-transition-quick);
    text-decoration: none !important;
}
```

Or, if `all` was being used because multiple non-transformable
properties are animated, switch to per-property `transition` lines and
preserve `var(--ap-transition-quick)` per property.

---

## Inventory — existing violations (deferred)

Counts produced by:

```bash
grep -nE "transition: [a-z-]+ [0-9]+(\.[0-9]+)?(s|ms)|transition: [0-9]+(\.[0-9]+)?(s|ms)" \
    moodle-enhancement/theme/airpayux/scss/moodle/partials/_surface-*.scss | wc -l
```

| Surface partial | Direct-timing violations |
|---|---:|
| `_surface-course.scss` | 13 |
| `_surface-user.scss` | 11 |
| `_surface-login.scss` | 9 |
| `_surface-dashboard.scss` | 6 |
| `_surface-grade-report.scss` | 5 |
| `_surface-badges.scss` | 3 |
| `_surface-footer.scss` | 3 |
| `_surface-navbar.scss` | 2 |
| `_surface-calendar.scss` | 2 |
| **TOTAL** | **54** |

Token-compliant usages already present (kept as positive examples):
`_surface-course.scss:111–112` and `_surface-course.scss:132–134`
(5 references to `var(--ap-transition-quick)`).

The chip-P+ refactor will migrate the 54 violations to
`var(--ap-transition-quick|default|slow|emphatic)` and add a release
note. Estimated effort: ~2 hrs (search-replace + visual smoke at
`prefers-reduced-motion: reduce`).

---

## How to run the lint locally

Stylelint is already a root devDependency (`^15.11.0`) — no
`package.json` change shipped today. After `npm install` at repo root:

```powershell
# From repo root (D:\Claude Local\airpay-ld-os\):
npx stylelint --config moodle-enhancement/theme/airpayux/.stylelintrc.json `
              "moodle-enhancement/theme/airpayux/scss/moodle/partials/_surface-*.scss"
```

Or from the theme dir (auto-discovered config):

```powershell
Set-Location moodle-enhancement\theme\airpayux
npx stylelint "scss/moodle/partials/_surface-*.scss"
```

Expected output today (before chip-P+ refactor): 54 errors across 9
files, one per direct-timing line.

---

## Opt-out (use sparingly)

If a future surface must use inline timing for a reason the cascade
cannot satisfy (e.g. a non-CSS-animated polyfill duration), disable
the rule per-line:

```scss
// stylelint-disable-next-line declaration-property-value-disallowed-list
transition: all 0.2s ease;
```

Add a comment on the next line explaining why. Per-file disables
(`/* stylelint-disable declaration-property-value-disallowed-list */`)
are discouraged — keep the opt-out scoped to the violating declaration.

---

## Safety + parity

- ✅ `python -c "import json,sys; json.load(sys.stdin)"` parses
      `.stylelintrc.json` silently (valid strict JSON).
- ✅ `php -l theme/airpayux/version.php` clean.
- ✅ No SCSS rule changes (audit invariant: config-only chip).
- ✅ No `.mustache` / `.php` / `.lang.php` touched.
- ✅ Upstream `.stylelintrc` (root, Moodle's) untouched.
- ✅ `package.json` (root, Moodle's) untouched — stylelint already
      installed as `^15.11.0`.
- ✅ `node_modules/` gitignore stays as-is at repo root.
- ✅ Hindi / locale parity unaffected (no string changes).
- ✅ Version bumped once (covers config + doc commits as one
      compiled-CSS-cache invalidation).

---

## Refs

- Audit: `moodle-enhancement/docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`
  §2.7 + P2 #19 (line 852 of the audit).
- Token cascade source of truth:
  `moodle-enhancement/theme/airpayux/scss/moodle/_tokens.scss:195–264`.
- Frontend rules doc: `.claude/rules/frontend.md` → new
  "Motion & `prefers-reduced-motion`" section.
- WCAG 2.3.3 — Animation from Interactions
  (Level AAA, https://www.w3.org/WAI/WCAG21/Understanding/animation-from-interactions.html).
- Stylelint rule docs:
  `declaration-property-value-disallowed-list` (stylelint ^15.11.0).
- Deferred follow-up: chip-P+ — refactor 54 inline-timing declarations
  to token references. Tracked in PROJECT-STATE.md H2 below.
