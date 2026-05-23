# Phase B.3.a — core_renderer rebase against Moodle 5.2

**Date:** 2026-05-23
**Status:** Completed. The 6h ADR-011 estimate dropped to **~30 min**
because airpayux's trait-decomposed core_renderer architecture already
ships 5.2-compatible method signatures.

---

## Scope

ADR-011 §"Phase B work breakdown" allocated 6h for B.3.a. The work
plan was:

1. Diff boost 5.1 → boost 5.2 to identify new methods + changed
   signatures.
2. Re-apply our extension methods on top of the new boost 5.2 base.
3. Confirm the 7 traits in `classes/output/traits/` are reusable as-is.

---

## What actually changed in 5.2 boost's core_renderer

From `D:\Claude Local\moodle-5.2-diffs\5.2-theme-boost-full.diff`:

```diff
--- /c/xampp/htdocs/moodle5/public/theme/boost/classes/output/core_renderer.php
+++ /d/Claude Local/moodle-5.2-source/.../theme/boost/classes/output/core_renderer.php
18a19
> use context_course;
253a255,279
+    /**
+     * Renders the Boost login form context.
+     */
+    public function render_login(\core_auth\output\login $form) {
+        ...
+        $context->logourl = $url;
+        $context->sitename = format_string(...);
+        $context->hasauthinstructions = !empty($CFG->auth_instructions);
+        return $this->render_from_template('core/loginform', $context);
+    }
```

**That's the entire delta.** 5.2 added ONE method (`render_login`) that
5.1 didn't have. All 4 pre-existing methods (`edit_button`, `navbar`,
`context_header`, `firstview_fakeblocks`) kept identical signatures.

---

## Signature-compatibility audit

All 5 methods 5.2 boost defines, vs our airpayux equivalents:

| Method | 5.2 boost signature | airpayux signature | Compat |
|--------|---------------------|---------------------|--------|
| `edit_button(moodle_url $url, string $method = 'post')` | typed | typed (in `page_helpers` trait) | ✅ |
| `navbar(): string` | : string | : string (in `page_helpers`) | ✅ |
| `context_header($headerinfo = null, $headinglevel = 1): string` | : string | : string (in `context_header`) | ✅ |
| `firstview_fakeblocks(): bool` | : bool | : bool (in main core_renderer.php) | ✅ |
| `render_login(\core_auth\output\login $form)` | typed | typed (in `login_render`) | ✅ |

Zero PHP "Declaration must be compatible with parent" risks. The
Phase B.3 web smoke pass (HTTP 200 / byte parity with 5.1) already
demonstrated this empirically — but it's worth recording the
contract-level audit too.

---

## One real finding: `hasauthinstructions` context key

5.2's `render_login()` sets:

```php
$context->hasauthinstructions = !empty($CFG->auth_instructions);
```

Our `traits/login_render.php::render_login()` does NOT set this. On 5.2,
any Mustache template that conditionally renders an "Authentication
instructions" block via `{{# hasauthinstructions }} ... {{/
hasauthinstructions }}` would silently never render even when
`$CFG->auth_instructions` was set.

**Fix shipped in this commit:** added the line to our trait:

```php
// Mirror 5.2 boost's render_login() context key.
$context->hasauthinstructions = !empty($CFG->auth_instructions);
```

Our custom `templates/core/loginform.mustache` doesn't currently use
this key (the P0 #5 OAuth2 i18n template handles its own conditional
display), so user-visible impact is nil today. The fix is purely
defensive — if a future template (or a template-cache miss falling
back to upstream) references this key, it now resolves correctly.

---

## Why "6 hours" became "30 minutes"

The A4B estimate assumed our 1,631-line core_renderer was a monolithic
file that needed substantial re-organisation against 5.2's new base.
Reality:

1. **Trait decomposition already done** — `classes/output/traits/`
   contains 8 traits (`branding_assets`, `branding_buttons`,
   `context_header`, `course_view`, `login_render`, `login_ui`,
   `page_helpers`, `user_menu`). Each trait owns a coherent
   responsibility. The main `core_renderer.php` is mostly `use Trait;`
   declarations.

2. **5.2 boost's surface barely changed** — only `render_login` is
   new. Our existing `render_login` already does everything 5.2's does
   PLUS Sentientia-specific enrichment (signup URL, cookie help icon,
   tenant logo, error formatting, theme-setting bridges).

3. **Signature audit was zero-defect** — all 5 5.2 methods have
   matching signatures in our overrides.

The "rebase" reduced to a single context-key addition.

---

## What's NOT in B.3.a (deferred to other legs)

Per the A4B conflict map, B.3.a covers ONLY `core_renderer.php` +
traits. Out of scope for this leg but in scope for later phases:

- `layout/columns2.php` — 5.2 introduces `\core\output\select_menu`
  for the tertiary navigation dropdown. Goes into **B.3.b**.
- `layout/drawers.php` — 5.2 drawer chrome changes. Goes into **B.3.b**.
- `layout/login.php` — Sentientia login redesign vs 5.2 base. Goes
  into **B.3.b**.
- `templates/columns2.mustache`, `drawer.mustache`, `drawers.mustache`
  — Goes into **B.3.c**.
- `templates/core_form/` (52 files) — biggest chunk. Goes into **B.3.d**.
- `templates/navbar.mustache` — Sentientia navbar branding vs 5.2 base.
  Goes into **B.3.c**.

---

## Versions

```
theme_airpayux : 2026052326 → 2026052327 (1.0.26-beta → 1.0.27-beta)
```

---

## Refs

- ADR-011 §"Phase B work breakdown" — original 6h estimate
- PHASE-A4B-CONFLICT-MAP.md §"A. boost/classes/output/core_renderer.php"
  — original "RE-IMPLEMENT, 4-6h" estimate
- 5.2-theme-boost-full.diff — the actual upstream diff
- PHASE-B3-WEB-SMOKE-PASS.md — empirical confirmation our renderer works
- PHASE-B3-HOOK-MIGRATION-2026-05-23.md — Phase B.3 hook migration leg
- PHASE-B3E-SCSS-REBASE-INVENTORY.md — Phase B.3.e SCSS leg
- This file — Phase B.3.a leg
