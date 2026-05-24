# P2 #19 follow-up — inline-timing → tokens (chip-D, 2026-05-24)

**Chip:** `claude/clever-dijkstra-8Iczy`
**Auditor:** Claude Opus 4.7 (1M context)
**Closes:** the inline-timing violation backlog left open by chip-P
(P2 #19 / §2.7 from
`moodle-enhancement/docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`).
**Scope owner:** Refactor of `transition` shorthand + `transition-duration`
declarations in `theme/airpayux/scss/moodle/partials/_surface-*.scss`.
**Touched files:** 11 (9 surface partials + 1 version + 1 README + 1 PROJECT-STATE H2).
**Did NOT touch:** Any `.mustache`, any `.php` other than `version.php`,
any `.lang.php`, any non-`_surface-*.scss` partial, `_tokens.scss` (token
definitions — out of scope), `.stylelintrc.json` (chip-P's config — done).

---

## Why this chip exists

Chip-P (commit `314c2948`) added a stylelint rule to `.stylelintrc.json`
that bans inline `s`/`ms` timing in `transition` shorthand and
`transition-duration` declarations inside `_surface-*.scss`. That rule
fired against 54 existing declarations that pre-dated the lint pattern.
Chip-P deferred the migration to a follow-up; this is that follow-up.

After this chip:

- Every `transition` declaration in a surface partial resolves through
  `var(--ap-transition-quick|default|slow)`.
- The token cascade in `_tokens.scss:258-264` collapses
  `--ap-duration-*` → `0ms` under `@media (prefers-reduced-motion: reduce)`.
- WCAG 2.3.3 (Animation from Interactions) is now enforced cascade-wide,
  not just on the two files (`_ui-polish.scss`, `_tokens.scss`) chip-P
  inventoried.

---

## Commits (one per partial, lint-rule scope)

| Commit | Partial | Violations closed |
|---|---|---:|
| `76d00900` | `_surface-badges.scss` | 3 |
| `d0c47f34` | `_surface-calendar.scss` | 2 |
| `29dc81f6` | `_surface-navbar.scss` | 2 |
| `4a76a7b4` | `_surface-footer.scss` | 3 |
| `feb46c31` | `_surface-grade-report.scss` | 5 |
| `f4b7a5eb` | `_surface-dashboard.scss` | 6 |
| `030fb926` | `_surface-login.scss` | 9 |
| `b072fab5` | `_surface-user.scss` | 11 |
| `4f13d582` | `_surface-course.scss` | 13 |
| (next)     | `version.php` + this README + PROJECT-STATE H2 | bump only |
| **TOTAL**  | **9 partials** | **54** |

Sum reconciles with the chip-P inventory (`wave3-chip-P/README.md` line 164).
Per-line declaration count is occasionally higher than chip-P's per-file
count because a single `transition:` shorthand can carry two or three
inline-timing pairs (e.g. `border-color 0.15s ease, box-shadow 0.15s ease`
is one declaration but two timing values); both are resolved.

---

## Before / after counts per partial

Counts taken with:

```bash
grep -nE 'transition\b' moodle-enhancement/theme/airpayux/scss/moodle/partials/_surface-*.scss \
    | grep -v 'var(' | grep -vE '^[0-9]+:\s*//'
```

| Partial | Before | After | Δ |
|---|---:|---:|---:|
| `_surface-badges.scss` | 3 | 0 | -3 |
| `_surface-calendar.scss` | 2 | 0 | -2 |
| `_surface-navbar.scss` | 2 | 0 | -2 |
| `_surface-footer.scss` | 3 | 0 | -3 |
| `_surface-grade-report.scss` | 5 | 0 | -5 |
| `_surface-dashboard.scss` | 6 | 0 | -6 |
| `_surface-login.scss` | 9 | 0 | -9 |
| `_surface-user.scss` | 11 | 0 | -11 |
| `_surface-course.scss` | 13 | 0 | -13 |
| **TOTAL** | **54** | **0** | **-54** |

---

## Token mapping (per `_tokens.scss:195-220`)

| Manifesto duration | Composite shortcut | Used for |
|---|---|---|
| `--ap-duration-instant` (0ms) | n/a | button-press, checkbox-tick (out of scope — no surface use) |
| `--ap-duration-quick` (150ms) | `--ap-transition-quick` (150ms ease-out) | hover state, focus glow, brand-colour shift |
| `--ap-duration-default` (250ms) | `--ap-transition-default` (250ms ease-out) | gradient submit CTA on login |
| `--ap-duration-slow` (400ms) | `--ap-transition-slow` (400ms ease-in-out) | dashboard courseimg scale-up on hover |

Decision per inline value:

| Inline value | Resolved token | Notes |
|---|---|---|
| 0.05s | `--ap-transition-quick` | Tactile press feedback on `_surface-user.scss:708` (mform submit button) and `_surface-user.scss:850` (list-link hover). 50ms rounds UP to 150ms — the closest manifesto-locked token. Inline `//` comment marks the rounding so future audits can revisit if a sub-150ms token is added to `_tokens.scss`. |
| 0.12s | `--ap-transition-quick` | Initialbar pagination on `_surface-grade-report.scss:216`. Rounded UP to 150ms; inline `//` comment notes. |
| 0.15s | `--ap-transition-quick` | Literal match (150ms = 0.15s). The bulk of the migration. |
| 0.2s | `--ap-transition-quick` | Rounded DOWN from 200ms to the manifesto-locked 150ms. Most card hover-lift + nav-link + button transitions. Difference is sub-perceptual. |
| 0.25s | `--ap-transition-default` | Literal match (250ms = 0.25s). Used on the gradient submit CTA in `_surface-login.scss:257`. |
| 0.3s | `--ap-transition-slow` | Courseimg zoom-on-hover in `_surface-dashboard.scss:524`. Layout-affecting motion benefits from the `ease-in-out` curve baked into `--ap-transition-slow`. |

---

## Behaviour deltas — what users will feel

For the vast majority of changed declarations: nothing. 200ms → 150ms is
sub-perceptual and matches the manifesto-locked timing of every other
Sentientia surface (`_layout-shell.scss`, `_components-course-card.scss`,
`_components-section-header.scss` already use `--ap-transition-quick`).

The two notable shifts:

1. **Button-press feedback on mform submit** (`_surface-user.scss:708`):
   `transform 0.05s ease` → `transform var(--ap-transition-quick)` (150ms).
   The press-bounce is 3× slower. This matches the established Sentientia
   pattern (no `--ap-duration-50ms` token exists). If a tactile-press
   token is added in a future `_tokens.scss` revision, this declaration
   can be re-targeted without re-introducing inline timing.

2. **List-link transform shift** (`_surface-user.scss:850`):
   Same story — `transform 0.05s` → `var(--ap-transition-quick)` (150ms).

Both sites carry an inline `//` comment at the violating declaration so
the rounding decision is recoverable from `git blame` without needing to
open this README.

---

## Safety + parity

- ✅ All 9 commits pass independent SCSS brace-balance check (open `{` = close `}` in every file).
- ✅ No new `!important` introduced; the one existing `!important` on
      `_surface-user.scss:71` is preserved as-is per chip prompt.
- ✅ Each commit is independent — reverting any one commit leaves the
      remaining 8 commits clean and compilable.
- ✅ No `.mustache` / `.php` (besides `version.php`) / `.lang.php` touched.
- ✅ No `_tokens.scss` change (token definitions out of scope).
- ✅ No `.stylelintrc.json` change (chip-P's config stays).
- ✅ No `_bizlms-*.scss` / `_moodle-overrides.scss` / `dark_mode.scss`
      touched (out of stylelint scope per chip prompt).
- ✅ `version.php` bumped `1.0.35-beta` → `1.0.36-beta` and
      `2026052404` → `2026052405`. `php -l version.php` clean.
- ✅ Hindi / locale parity unaffected (no string changes).
- ✅ Pre-commit hook (`.claude/hooks/pre-commit.sh`) is not installed
      in this remote environment — commits pass git's default checks.

---

## How to verify the migration locally

```powershell
# 1. Lint should now report zero errors against the surface partials:
Set-Location moodle-enhancement\theme\airpayux
npx stylelint "scss/moodle/partials/_surface-*.scss"
# Expected: clean — every violation chip-P inventoried is gone.

# 2. Smoke under prefers-reduced-motion (Chrome devtools):
#    Rendering tab → Emulate CSS media feature prefers-reduced-motion:reduce
#    → every animation on /my/dashboard.php, /login/, /badges/mybadges.php,
#      /grade/report/overview, /course/view.php should be instant (0ms).
#    Without the flag, the same surfaces should animate at 150ms / 250ms / 400ms
#    per the token mapping above.

# 3. Visual smoke without reduced-motion — confirm no regression:
#    Hover the dashboard course-card → it lifts at 150ms (was 200ms).
#    Click the login submit CTA → gradient transitions at 250ms (unchanged).
#    Hover a course catalog card image → it scales at 400ms with ease-in-out
#      (was 300ms with linear-ish ease).
```

---

## Refs

- Audit: `moodle-enhancement/docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`
  §2.7 / P2 #19 (line 852 of the audit).
- Chip-P prerequisite: `docs/visual-evidence/2026-05-24/wave3-chip-P/README.md`
  (stylelint rule + 54-violation inventory).
- Token cascade source of truth:
  `moodle-enhancement/theme/airpayux/scss/moodle/_tokens.scss:194-220` (durations
  + composite shortcuts) and `_tokens.scss:258-265` (reduced-motion override).
- Frontend rules doc: `.claude/rules/frontend.md` → "Motion &
  `prefers-reduced-motion`" section (chip-P added).
- WCAG 2.3.3 — Animation from Interactions
  (Level AAA, https://www.w3.org/WAI/WCAG21/Understanding/animation-from-interactions.html).
- Stylelint rule: `declaration-property-value-disallowed-list`.

---

## Out of scope (deferred backlog)

The chip prompt explicitly excludes:

- `_tokens.scss` itself — adding a `--ap-duration-instant` (or
  `--ap-duration-press`) token at sub-150ms would let the two 0.05s
  press-feedback sites stay tactile; that's a `_tokens.scss` revision,
  not a `_surface-*.scss` migration.
- `_bizlms-*.scss`, `_moodle-overrides.scss`, `dark_mode.scss` — these
  partials are outside the stylelint glob in `.stylelintrc.json`. If
  inline timing appears there, it'll be caught by a separate audit
  pass.
- Non-`.scss` files — no `.mustache`/`.php`/`.lang.php` motion code
  shipped today.

If a future audit widens the lint scope to `_bizlms-*.scss` or
`_moodle-overrides.scss`, a chip-D+ follow-up can re-run this same
playbook against those partials.
