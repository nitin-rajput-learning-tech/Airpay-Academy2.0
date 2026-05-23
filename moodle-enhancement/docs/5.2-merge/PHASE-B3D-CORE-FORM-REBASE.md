# Phase B.3.d — core_form widgets rebase against Moodle 5.2

**Date:** 2026-05-23
**Status:** Complete. The 18h ADR-011 estimate dropped to **~5 min**
because the actual upstream diff scope was far narrower than the
"52 templates need re-implementation" assumption.

---

## What the diff actually contained

Per `5.2-theme-boost-full.diff` and `5.2-lib-full.diff`:

- **5.2 ADDED 2 new core_form mustache templates:**
  - `theme/boost/templates/core_form/element-float.mustache`
  - `theme/boost/templates/core_form/element-float-inline.mustache`
- **5.2 MODIFIED 0 existing core_form mustache templates.**

Both new templates are one-liner partials that delegate to
`{{> core_form/element-text }}` — they exist so the new `MoodleQuickForm_float`
PHP element (introduced in 5.2's lib/form/) has a corresponding
template hook.

---

## What our airpayux fork already has

```
> ls theme/airpayux/templates/core_form/element-float*.mustache
element-float-inline.mustache
element-float.mustache

> cat theme/airpayux/templates/core_form/element-float.mustache
{{> core_form/element-text }}

> cat .../element-float-inline.mustache
{{> core_form/element-text-inline }}
```

Both new templates already exist in our fork with byte-identical
content to 5.2 boost's versions. **No code changes needed for B.3.d.**

How did this happen? When the engineering pass earlier shipped the
52 core_form template overrides, it pre-emptively included the new
float templates (anticipating 5.2 form-element migrations). The
inventory check today confirms they're in place.

---

## Why the 18h estimate was so far off

ADR-011's 18h B.3.d budget assumed each of the 52 core_form templates
might need re-implementation against a new 5.2 base — that estimate
was made BEFORE generating the diff. The actual `5.2-theme-boost-full.diff`
shows zero existing-template modifications, so the "re-implement on
new base" work that was budgeted simply doesn't exist.

This is a recurring pattern across Phase B.3:

| Leg | ADR estimate | Actual |
|-----|-------------:|------:|
| B.3.a core_renderer | 6h | 30 min |
| B.3.b layouts | 4h | 20 min |
| B.3.c top templates | 5h | 30 min (plan only) |
| B.3.d core_form widgets | 18h | 5 min |
| B.3.e SCSS rebase | 4h | 30 min |
| B.3.f AMD cleanup | 1h | 15 min |
| B.3.e+ BS5 + 5.2 vars | not in plan | 30 min |
| **B.3 total** | **38h** | **~2.5h** |

The estimates assumed worst-case scope. Actual scope was much smaller
because:

1. Boost 5.2's surface changes were narrowly bounded
2. Our trait-decomposed renderer already had 5.2-compatible signatures
3. We self-host Bootstrap 4 (no BS5 forced migration)
4. Our P0-borrow shims had zero callsites
5. Engineering passes during 5.1 work pre-emptively added 5.2-shape files

---

## Versions

```
theme_airpayux : 2026052329 → 2026052330 (1.0.29-beta → 1.0.30-beta)
```

Version bump for the leg-doc + Phase B.3 milestone marker. No
template content changed.

---

## Refs

- ADR-011 §"Phase B work breakdown" — B.3.d 18h estimate
- PHASE-A4B-CONFLICT-MAP.md §"templates/core_form/ (52 files)" — original
  RE-IMPLEMENT strategy
- 5.2-theme-boost-full.diff — the actual upstream diff (zero core_form
  template modifications)
- This file — Phase B.3.d leg

---

## Phase B.3 cumulative outcome

With B.3.d landing as a near-no-op, Phase B.3 is **substantially complete**:

```
B.3       hook migration            ✅ committed (21241c358)
B.3.a     core_renderer rebase      ✅ committed (bc8e44ab3)
B.3.b     layouts rebase            ✅ committed (2c8ad14cd)
B.3.c     top templates inventory   ✅ committed (2d23aebf1)
B.3.d     core_form widgets         ✅ this leg
B.3.e     SCSS variables inventory  ✅ committed (85e060760)
B.3.e+    BS5 + 5.2 vars adoption   ✅ committed (1479659b2)
B.3.f     AMD shim cleanup plan     ✅ committed (8823d8d08)
```

The cutover-day deferred items (line-tagged in code) are:
- `course.mustache`: tertiary-nav partial swap (B.3.c)
- 3 AMD shim files: delete + announcement.js review (B.3.f)
- Phase B.3.c per-template review of drawer/drawers/secure mustaches

Phase B.4-B.12 next.
