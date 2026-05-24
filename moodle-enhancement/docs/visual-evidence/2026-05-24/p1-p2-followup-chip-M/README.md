# P1 #15 + P2 #22 follow-up — chip M (2026-05-24)

**Branch:** `claude/nice-gauss-Jeyou` on `nitin-rajput-learning-tech/Airpay-Academy2.0`
**Scope:** sentientia_live tokens + table a11y
**Plugin version:** `2026052401` (single bump for both items)

---

## What changed

Two surgical fixes shipped from Platform Visual Audit 2026-05-24 follow-up:

| # | Audit finding | Scope | Type |
|---|---------------|-------|------|
| 1 | **F-24 (P1 #15)** | `local_sentientia_live` templates use Bootstrap utility classes only | BEM tokens layered over Bootstrap |
| 2 | **F-25 (P2 #22)** | `trainer_dashboard.mustache` table has no `<caption>` or `scope` attributes | a11y attributes + new lang string |

Both items ship together under one plugin version bump.

---

## Item 1 — BEM tokens (P1 #15 / F-24)

### Bootstrap → Sentientia mapping

The audit recommended adding `airpay-*` BEM equivalents for the Bootstrap
utility classes used in `local_sentientia_live` templates, scoped to the
plugin's body path. Templates carry **both** classes so vanilla-Bootstrap
deployments fall back gracefully.

| Bootstrap class | Sentientia BEM class | Underlying token | Where used |
|-----------------|----------------------|------------------|------------|
| `.badge.bg-primary` | `.airpay-badge.airpay-badge--primary` | `var(--ap-primary)` (#0066A7) | trainer_dashboard audience-count badge |
| `.badge.bg-success` | `.airpay-badge.airpay-badge--success` | `var(--ap-success)` (#16a34a) | trainer_dashboard live-state, result_bar_chart correct |
| `.badge.bg-secondary` | `.airpay-badge.airpay-badge--secondary` | `var(--ap-text-secondary)` (#5a6070) | trainer_dashboard draft-state, result_panel total |
| `.badge.bg-light` | `.airpay-badge.airpay-badge--light` | `var(--ap-surface-alt)` (#f8f9fc) + `--ap-border` | trainer_dashboard ended-state |
| `.btn.btn-primary` | `.airpay-btn.airpay-btn--primary` | `var(--ap-primary)` | "Create new session" CTAs (×2) |
| `.btn.btn-success` | `.airpay-btn.airpay-btn--success` | `var(--ap-success)` | "Run" action button |
| `.btn.btn-outline-primary` | `.airpay-btn.airpay-btn--outline-primary` | `var(--ap-primary)` border | "Edit" action button (draft state) |
| `.btn.btn-outline-secondary` | `.airpay-btn.airpay-btn--outline-secondary` | `var(--ap-text-secondary)` border | "View" action button (ended state) |
| `.btn.btn-outline-warning` | `.airpay-btn.airpay-btn--outline-warning` | `var(--ap-warning)` (#d97706) border | "End" action button (live state) |
| `.btn.btn-outline-info` | `.airpay-btn.airpay-btn--outline-info` | `var(--ap-primary)` border | "Export CSV" action button |
| `.btn.btn-outline-danger` | `.airpay-btn.airpay-btn--outline-danger` | `var(--ap-danger)` (#dc2626) border | "Delete session" action button |

All tokens are CSS custom properties already defined in
`theme/airpayux/scss/moodle/partials/_components.scss` `:root`.

### Markup pattern

Every badge / button carries **two** sets of classes — Sentientia first,
Bootstrap second:

```html
<!-- Before (audit finding) -->
<span class="badge bg-success">Live</span>

<!-- After (this commit) -->
<span class="airpay-badge airpay-badge--success badge bg-success">Live</span>
```

When the airpayux theme is active the body has class
`path-local-sentientia_live` and the Sentientia overrides win (they use
`!important` for the colour rules). On vanilla Moodle deployments the
override doesn't fire and Bootstrap renders as before — markup is
backwards-compatible by design.

### Files touched

```
moodle-enhancement/
├── theme/airpayux/scss/moodle/partials/_bizlms-modern.scss     (+154 lines)
└── local/sentientia_live/templates/
    ├── trainer_dashboard.mustache    (badges ×4, buttons ×7 — fallback-mode markup)
    ├── result_panel.mustache         (1 badge — total-responses counter)
    └── result_bar_chart.mustache     (1 badge — is_correct label on quiz options)
```

### Scope rationale

The body class Moodle generates for `/local/sentientia_live/...` paths is
`path-local-sentientia_live` — `pagelib.php::initialise_default_pagetype`
keeps underscores; only `/` becomes `-`. The audit doc suggested the
hyphenated form (`path-local-sentientia-live`) but that body class never
gets emitted by Moodle, so the underscore form is what landed in the SCSS.

### Dark mode

`.airpay-badge--light` flips to a darker surface (`#2d3140`) on
`body.dark-mode.path-local-sentientia_live` so the "Ended" state badge
keeps contrast. Other variants already use brand colours that work on
both palettes.

---

## Item 2 — Table a11y (P2 #22 / F-25)

### Caption

Added to the trainer-sessions table in `trainer_dashboard.mustache:40`:

```html
<table class="table table-hover align-middle">
  <caption class="sr-only">{{#str}}trainer_sessions_table_caption, local_sentientia_live{{/str}}</caption>
  <thead class="table-light">
    ...
```

The `.sr-only` class hides the caption visually but exposes it to
assistive tech (NVDA / JAWS / VoiceOver / Narrator). The text:

- **EN:** "List of your live sessions with state, join code, slide count, audience size, creation date, and available actions."
- **HI:** "आपके लाइव सेशन की सूची — स्थिति, join कोड, स्लाइड संख्या, ऑडियंस आकार, बनाने की तारीख और उपलब्ध क्रियाएं।"

### Scope attributes

Every `<th>` in `<thead>` now carries `scope="col"`:

```html
<tr>
  <th scope="col">Title</th>
  <th scope="col" class="text-center">State</th>
  <th scope="col" class="text-center">Join code</th>
  <th scope="col" class="text-center">Slides</th>
  <th scope="col" class="text-center">Audience</th>
  <th scope="col">Created</th>
  <th scope="col" class="text-end">Actions</th>
</tr>
```

The `<tbody>` rows weren't given `scope="row"` on a leading `<th>`
because the title cell is wrapped in an `<a>` and conceptually it's a
data row rather than a header row — adding a row-header would conflict
with existing semantics.

### WCAG mapping

- **WCAG 2.1 SC 1.3.1 Info and Relationships (Level A)** — table caption
  + header `scope` is the standard way to convey table structure to
  assistive tech.
- **WCAG 2.1 SC 4.1.2 Name, Role, Value (Level A)** — the caption
  doubles as the table's accessible name.

### Screen-reader verification protocol (NVDA / JAWS)

Local-XAMPP capture deferred — this session ran in a remote container
without a working Moodle install. Reproduction steps for the local
verifier:

1. **Build airpayux theme** (`npx grunt scss` in `theme/airpayux/`).
2. **Bump version cache** (`php admin/cli/upgrade.php` then
   `purge_caches.php`).
3. **Log in as a trainer**, navigate to `/local/sentientia_live/trainer/index.php`.
4. **Turn on NVDA**, browse to the table:
   - Expected announcement: *"Table: List of your live sessions with
     state, join code, slide count, audience size, creation date, and
     available actions. 7 columns, N rows."*
   - Navigate columns with `Ctrl+Alt+→` — each cell's column header is
     announced ("State", "Join code", etc.).
5. **Repeat with Hindi locale** active (settings/site
   admin/Language/Default language → हिन्दी) — caption should announce
   in Hindi.

### Files touched

```
moodle-enhancement/local/sentientia_live/
├── templates/trainer_dashboard.mustache   (+1 caption, +7 scope="col")
├── lang/en/local_sentientia_live.php      (+1 key trainer_sessions_table_caption)
├── lang/hi/local_sentientia_live.php      (+1 key trainer_sessions_table_caption)
└── version.php                            (2026052103 → 2026052401, covers both items)
```

---

## Hindi parity verification

```
$ grep -cE "^\\\$string\[" lang/en/local_sentientia_live.php
256
$ grep -cE "^\\\$string\[" lang/hi/local_sentientia_live.php
256
$ diff <(grep -oE "^\\\$string\['[^']+'\]" lang/en/local_sentientia_live.php | sort) \
       <(grep -oE "^\\\$string\['[^']+'\]" lang/hi/local_sentientia_live.php | sort)
# (zero diff)
```

100% parity preserved.

---

## Out of scope (Chip E conflict note)

A parallel chip (Chip E) is adding `aria-live` regions to
`audience/play.mustache` and `trainer/run.mustache` (possibly
`trainer_dashboard.mustache`). If Chip E's branch lands first, the
BEM markup added here layers cleanly onto its `aria-live`
attributes — they target different attribute namespaces (class= vs
aria-live=) and won't collide. If Chip M lands first, Chip E's
edits need only add `aria-live="polite"` etc. attributes alongside
the airpay-* classes already in place.

---

## Safety summary

- ✅ `php -l` clean on both lang files + version.php
- ✅ Mustache lint — no triple-brace `{{{ }}}` introduced on user input
  (only pre-existing `{{{tally_json}}}` in result_panel.mustache which
  wraps a server-generated JSON tally in a `<script type="application/json">`)
- ✅ Hindi parity 100% (256/256 keys, zero diff)
- ✅ Bootstrap fallback markup preserved on every badge / button
- ✅ Scope and caption are additive — no semantic changes to existing markup
- ✅ Single plugin version bump (2026052103 → 2026052401)
- ✅ Body-class scope (`body.path-local-sentientia_live`) prevents any
  bleed into other plugin surfaces

---

## Refs

- Audit report: `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` (F-24, F-25)
- Frontend rules: `.claude/rules/frontend.md` §BEM
- State card: `state-cards/sentientia_live-state.md`
- PROJECT-STATE entry: see "## 🎨 P1 #15 + P2 #22 — sentientia_live tokens + table a11y (2026-05-24)"
