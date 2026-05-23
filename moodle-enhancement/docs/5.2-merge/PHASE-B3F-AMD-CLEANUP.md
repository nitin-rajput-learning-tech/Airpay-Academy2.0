# Phase B.3.f — AMD borrow shim cleanup (5.2 cutover gated)

**Date:** 2026-05-23
**Status:** Audit complete. Per Nitin's "bundle with 5.2 cutover"
directive, the 3 P0-borrow AMD shims are now tagged `@deprecated since
Moodle 5.2 cutover` with explicit delete-on-cutover plans baked into
their docblocks. **Critical finding: zero callsites in our codebase,
so cutover-day cleanup is a single-file removal each — no rewrites.**

---

## The 3 shims under audit

Per ADR-010 we ported 3 AMD modules from Moodle 5.2 back into our
5.1 codebase as P0 borrows. Each was designed to be swap-out-able
once we upgraded:

| Shim | P0 # | Native 5.2 equivalent | Delta vs core |
|------|-----:|------------------------|---------------|
| `theme_airpayux/page_title.js`  | #7 | `core/page_title`  | Identical API — pure copy |
| `theme_airpayux/deprecated.js`  | #8 | `core/deprecated`  | Identical API — pure copy |
| `theme_airpayux/announcement.js`| #6 | `core/toast` w/ `{visuallyHidden}` | ~~Pure pass-through~~ has aria-live re-announce trick for NVDA <2024 bug |

---

## Callsite audit (2026-05-23)

```
grep -rn "theme_airpayux/page_title\|theme_airpayux/deprecated\|theme_airpayux/announcement" moodle-enhancement/
```

**Result: ZERO production import callsites.** Only matches are:
- The 3 shim source files themselves (declaring `@module`).
- The 3 compiled `.min.js` files in `amd/build/`.
- Documentation refs in this doc and in the shim source headers.

No call-site rewrites required at cutover. The shims were
pre-emptively shipped during the P0 borrow weeks but never adopted
by downstream callers.

---

## Cutover-day delete plan

Once `airpay.academy` production is on Moodle 5.2, this is a single
commit removing 9 files:

```
git rm  moodle-enhancement/theme/airpayux/amd/src/page_title.js
git rm  moodle-enhancement/theme/airpayux/amd/build/page_title.min.js
git rm  moodle-enhancement/theme/airpayux/amd/build/page_title.min.js.map
git rm  moodle-enhancement/theme/airpayux/amd/src/deprecated.js
git rm  moodle-enhancement/theme/airpayux/amd/build/deprecated.min.js
git rm  moodle-enhancement/theme/airpayux/amd/build/deprecated.min.js.map
git rm  moodle-enhancement/theme/airpayux/amd/src/announcement.js   # see "Exception" below
git rm  moodle-enhancement/theme/airpayux/amd/build/announcement.min.js
git rm  moodle-enhancement/theme/airpayux/amd/build/announcement.min.js.map
```

Bump `theme/airpayux/version.php` and ship.

### Exception: `announcement.js` MAY survive

`announcement.js` is NOT pure pass-through. It wraps `core/toast` PLUS
an aria-live region with a same-text re-announcement trick that
addresses an NVDA <2024 screen-reader bug ("same text suppressed"
behaviour where rapid-fire announce-success-twice gets collapsed
into one auditory event).

At cutover, the team should:

1. Spin up 5.2 + connect NVDA 2023 (or current minimum supported AT).
2. Call `core/toast.add('Saved', {visuallyHidden: true})` twice in quick
   succession.
3. Verify both announcements fire.
4. If yes → delete `announcement.js`.
5. If no → keep `announcement.js`, update its header to remove the
   `@deprecated` tag and document that core/toast doesn't handle this
   case.

---

## What this commit ships

Each of the 3 shim source files now carries a `@deprecated since
Moodle 5.2 cutover` docblock with:

- Pointer to this leg doc
- The 5.2 native equivalent
- Cutover action (delete vs review)
- ZERO-callsites finding so future Claude / engineer doesn't redo the audit

No code change. No version bump (the change is comment-only — Moodle
doesn't need to re-register anything).

---

## Why this is "Phase B.3.f complete" rather than "Phase B.3.f deferred"

The ADR-011 estimate of 1h for B.3.f assumed callsite rewrites would
be needed. The audit found ZERO callsites, so the actual work is:

1. Tag the files for delete-on-cutover (this commit). ✅
2. At cutover day, delete the files. (One future commit, < 5 min.)

Both steps fit within the original 1h budget; the rewrite work that
would have consumed most of that budget evaporated when the audit
returned zero callsites.

---

## Refs

- ADR-010 §"P0 borrows" — original justification for the 3 shims
- ADR-011 §"Phase B work breakdown" — B.3.f 1h estimate
- PHASE-A4B-CONFLICT-MAP.md §"amd/src/*.js (18)" — original
  "DELETE 4, KEEP 14" rough estimate
- PHASE-B3-HOOK-MIGRATION-2026-05-23.md — Phase B.3 baseline
- PHASE-B3E-SCSS-REBASE-INVENTORY.md — Phase B.3.e leg
- PHASE-B3E+ (this commit's sibling on the SCSS line) — BS5 + 5.2 vars
- PHASE-B3A-CORE-RENDERER-REBASE.md — Phase B.3.a leg
- This file — Phase B.3.f leg
