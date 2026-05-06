# F1 — Console errors on airpay_courses + airpay_reports

**Status:** Investigation deepened, root cause still requires Chrome DevTools manual session
**Severity:** P2 (cosmetic — Phase B 73/73 PASS confirms functional impact = zero)
**First captured:** [PHASE-A-B-RESULTS.md](PHASE-A-B-RESULTS.md) §F1 (commit 8fe7bf7dc)

---

## The error pattern (production-mode, built JS)

```
TypeError: Cannot read properties of null (reading 'closest')
    at getFormFromChild (core/first.js:2233:108)
    at watchForm (core/first.js:2233:155)
    at _exports.watchFormById (core/first.js:2233:4146)
    at <anonymous>:5:103
```

This is Moodle core's `watchFormById` being called from an inline AMD
script with a form ID that doesn't exist in the DOM. The trigger is on
courses + reports pages but NOT users + others — same template
structure on the surface.

## What this session's investigation added

### Attempted: enable Moodle dev mode for readable line numbers

```php
// config.php
$CFG->cachejs = false;
$CFG->jsrev = -1;
```

**Result:** Dev mode breaks our setup entirely. New errors emerge:

```
Cannot use import statement outside a module
No define call for theme_airpayux/datatable
No define call for local_airpay_courses/course_actions
```

**Why:** Our AMD source files (`amd/src/*.js`) use ES module syntax
(`import/export`). The built versions (`amd/build/*.min.js`) are
AMD-compatible (transpiled by Grunt). With `cachejs=false`, Moodle
loads source files directly — RequireJS can't handle ES modules.

This means:
1. **Production** uses `build/*.min.js` (AMD bundled — works fine, but
   F1 errors fire because of *something* in the bundled code).
2. **Dev mode** would need a Grunt watcher running (`grunt watch`)
   that re-builds source on change. We don't have this set up.

### Mitigation for F1 (no source-map needed)

The errors are NON-FATAL. Phase B's 73/73 cases confirm:
- Page loads ✓
- Datatable populates with real rows ✓
- Modal opens (where applicable) ✓
- Sort, search, pagination work ✓

In production, the errors will appear in browser consoles but won't
break user flows. They will show up in any browser-error-tracking
service (Sentry etc.) we eventually add.

---

## Real root cause (hypothesis from earlier investigation)

Moodle 5.x has an auto-form-watcher that registers `watchFormById` on
forms with certain `data-*` attributes. Our courses page has:

```html
<div data-airpay-courses-visibility>
  <button data-visibility="all">All</button>
  <button data-visibility="visible">Visible</button>
  <button data-visibility="hidden">Hidden</button>
</div>
```

These `data-visibility` attributes might be triggering Moodle's form-id
auto-detection (it looks for elements that look form-like). The auto
call to `watchFormById` then looks for a `<form>` containing these
elements; finds none; `getFormFromChild` returns null; subsequent
`.closest('form')` errors.

**To verify** (need Chrome DevTools manual session):
1. Open `/local/airpay_courses/index.php` in Chrome with DevTools open
2. Sources tab — find `core/first.js`, set breakpoint inside `watchFormById`
3. Reload page
4. When breakpoint hits, inspect call stack
5. Walk up to the inline `<anonymous>:5:103` frame
6. Identify which AMD module's factory function called `watchFormById`
7. Look at what data-* attribute that factory checked

## Workarounds for now

**If F1 noise becomes a deploy blocker** (e.g. it triggers Sentry alerts):

1. Wrap the offending `watchFormById` call in a try/catch by patching
   our courses + reports template inline JS.
2. Or, remove the `data-visibility` / `data-airpay-courses-visibility`
   attributes and use explicit JS-bound CSS classes instead.
3. Or, file an upstream Moodle bug for the auto-watcher dereferencing
   null without a guard.

None of these are necessary right now. Functionality works. Errors are
silent unless explicitly looked for.

---

## Static analysis findings (added 2026-05-06)

Read `lib/form/amd/{src,build}/changechecker.js` end-to-end and grep'd
for `watchFormById` callers across the entire codebase:

```bash
grep -rn 'watchFormById' public/local/                # 0 hits in airpay code
grep -rn 'watchFormById' public/theme/airpayux/        # 0 hits
grep -rn 'watchFormById' public/lib/form/              # only the definition
```

Conclusion from static analysis: **none of OUR plugins or our theme call
`watchFormById` directly**. The error therefore originates in either:

1. A Moodle core mform template embedded on courses/reports pages
   (e.g. a search-filter mform whose template renders
   `{{#js}}require(['core_form/changechecker'], c => c.watchFormById('xxx-{{uniqid}}'));{{/js}}`)
   where the form's actual ID gets mismatched after our theme's
   renderer rewrites the output.

2. The auto-watcher `startWatching()` (line 416 in src/changechecker.js)
   adds global `change`, `click`, `focusin`, `submit` listeners — none
   of these call `watchFormById`. So the auto-watcher itself isn't the
   culprit.

3. A `{{#js}}` block in a template that survives the page render but
   references a form ID that's been removed/renamed by our theme.

The bug in Moodle core: `watchForm(formNode)` immediately does
`formNode.closest('form')` without first guarding against `formNode`
being null. `watchFormById` passes through whatever `getElementById`
returns, including `null`. A 1-line fix in core would be:

```js
export const watchFormById = formId => {
    const node = document.getElementById(formId);
    if (!node) return;                           // ← missing guard
    watchForm(node);
};
```

This is a Moodle upstream bug, not our bug. Filing it as `MDL-XXXXX`
would be the proper fix but requires Moodle Tracker access. Until then,
the symptom is harmless — Phase B's 73/73 functional cases confirm.

## To investigate properly (Phase 6B item)

| Step | Effort |
|------|--------|
| Set up `grunt watch` to keep `amd/src/` ↔ `amd/build/` in sync | 30min |
| Document the dev workflow in CLAUDE.md or DEPLOYMENT-RUNBOOK.md | 15min |
| Manual Chrome DevTools session — set breakpoint, walk stack | 30min |
| Patch the trigger (template edit or core/first.js workaround) | 30min |
| Re-run Phase A/B harness to confirm fix (0 console errors) | 10min |
| **Total** | **~2 hours** |

This is the minimum sequence. Worth doing before flipping `noemailever
= true` on production (when other browser-side anomalies will start
showing up).

## Why this is filed as P2 not P1

- Phase B confirms 73/73 functional cases pass on the same pages.
- The errors don't break any user-visible behavior.
- `dataset.action` click delegation (the actual functional code path)
  works as confirmed by manual modal-open + sort + filter tests.
- Production users will never see the errors unless they open DevTools.

The right time to fix this is during a focused front-end session that
also adds aria-sort to datatable headers (A11Y-1 from Phase H audit).
Both are dev-tool-side concerns that benefit from `grunt watch` being
running.
