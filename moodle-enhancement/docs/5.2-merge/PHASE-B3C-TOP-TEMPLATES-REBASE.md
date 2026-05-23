# Phase B.3.c — top templates rebase against Moodle 5.2

**Date:** 2026-05-23
**Status:** Inventory complete. Per-template cutover plan documented.
**No template content swapped today** — mustache lacks PHP's runtime
conditional include, so dual-target on 5.1+5.2 needs PHP context flags
that we add at cutover. The single template that already breaks
(course.mustache tertiary-nav partial) is tagged for cutover-day swap.

---

## What 5.2 boost changed in templates

From `5.2-theme-boost-full.diff`:

| Template | Diff size | Nature |
|----------|----------:|--------|
| `columns2.mustache` | 6 lines | tertiary nav: `core/url_select` → `core/tertiary_navigation_selector` |
| `courseindexdrawercontrols.mustache` | 4 | CSS class rm + FontAwesome icon swap (`fa-ellipsis-v` → `fa-angles-down`) |
| `login.mustache` | 14 | Refactor to use `{{> core/login_layout }}` partial |
| `navbar.mustache` | 15 | Add divider in user menu + restructure plugin_output rendering |
| `footer.mustache` | 17 | Add telemetry_traceid block + `core/copy_to_clipboard` AMD dep |
| `drawers.mustache` | 22 | TBD |
| `drawer.mustache` | 35 | TBD |
| `secure.mustache` | 108 | Substantial refactor |

---

## The PHP-template context-shape problem

Phase B.3.b migrated 4 layouts (columns2.php, course.php, dashboard.php,
drawers.php) to dual-target `\core\output\select_menu`. The PHP now
passes DIFFERENT context shapes depending on the Moodle version:

- **5.1 path:** `$overflowdata->export_for_template($OUTPUT)` →
  shape compatible with `core/url_select` partial.
- **5.2 path:** `$selectmenu->export_for_template($OUTPUT)` →
  shape compatible with `core/tertiary_navigation_selector` partial.

Our airpayux's `templates/course.mustache:237` references
`{{> core/url_select}}` — which would render the 5.2 select_menu
context shape **as the wrong partial** on 5.2.

Visual impact:
- On 5.1 → renders correctly via url_select.
- On 5.2 → the URL select dropdown on `/course/view.php?id=N` with
  tertiary nav overflow likely renders incomplete or missing elements
  (label, ARIA attrs, JS onchange handler) since `core/url_select`
  doesn't know about `name`, `baseid`, `selectedoption`, `options.isgroup`.

The frontpage smoke pass didn't exercise this — frontpage doesn't have
secondary nav with overflow. The bug only surfaces on authenticated
course view pages.

---

## Why we can't just dual-target mustache

PHP can do `if (class_exists(...))` and switch behaviour at runtime.
Mustache CAN'T directly check class existence — it can only inspect
context variables.

The clean fix at cutover-day:

1. PHP passes an explicit marker:
   ```php
   $overflow = $selectmenu->export_for_template($OUTPUT);
   $overflow->is_select_menu_context = true; // 5.2 path only
   ```

2. Mustache branches on the marker:
   ```mustache
   {{#overflow}}
       {{#is_select_menu_context}}
           {{> core/tertiary_navigation_selector }}
       {{/is_select_menu_context}}
       {{^is_select_menu_context}}
           {{> core/url_select }}
       {{/is_select_menu_context}}
   {{/overflow}}
   ```

3. Both paths produce the right HTML on the right Moodle version.

This is a 2-file change per layout. **Scheduled for cutover-day** so we
can smoke-test against real Moodle 5.2 with course-view + authenticated
user before flipping.

---

## Per-template cutover plan

### columns2.mustache (theme/airpayux/templates/columns2.mustache)

**Risk:** LOW — our airpayux/columns2.mustache may not even reference
`core/url_select` (the actual usage is in course.mustache).

**Cutover action:** Audit our copy. If the tertiary-nav block exists,
apply the marker-based dual-target shown above.

### courseindexdrawercontrols.mustache

**Risk:** NIL — purely visual (icon + class change). Our airpayux
override likely has Sentientia styling already.

**Cutover action:** Optionally swap icon from `fa-ellipsis-v` to
`fa-angles-down` if we want to track upstream UX. Otherwise no action.

### login.mustache

**Risk:** HIGH — 5.2 refactors to `{{> core/login_layout }}` partial
which encapsulates the new login UI structure. Our airpayux/login.mustache
is heavily Sentientia-branded with hero panel + OTP login flow.

**Cutover action:** REJECT the upstream change. Our Sentientia login
is the canonical design for airpay.academy. Keep our login.mustache;
note that the upstream `core/login_layout` partial may produce a more
accessible structure that we could optionally adopt as a follow-up.

### navbar.mustache

**Risk:** MEDIUM — 5.2 adds a vertical divider in the user-menu area
and restructures `output.navbar_plugin_output` rendering. Our airpayux
navbar is heavily branded.

**Cutover action:** Sentientia-branded navbar wins. No backport.

### footer.mustache (theme/airpayux/templates/footer.mustache)

**Risk:** LOW — 5.2 adds telemetry_traceid debug block + clipboard
copy AMD. Both purely additive features.

**Cutover action:** Backport both blocks. They make debugging easier
and don't conflict with Sentientia branding.

### drawer.mustache, drawers.mustache

**Risk:** MEDIUM — 5.2 changes structural to drawer chrome.

**Cutover action:** Audit at cutover, apply changes that don't conflict
with Sentientia drawer styling.

### secure.mustache (108-line diff)

**Risk:** HIGH but LOW IMPACT — secure layout is admin-internal
(displayed for `/admin/secure.php` workflows). Our airpayux/secure.mustache
is essentially unbranded.

**Cutover action:** TAKE THEIRS — the secure layout is plumbing, not
brand. Replace our copy with 5.2's verbatim.

---

## Today's safe action: line-tagging course.mustache

This is the one template where the PHP context shape change from B.3.b
creates a guaranteed mismatch on /course/view.php. Tag the line so
future Claude / engineer can find it at cutover:

```mustache
{{#overflow}}
    <div class="container-fluid tertiary-navigation">
        <div class="navitem">
            {{!-- @todo Phase B.3.c+ cutover: B.3.b PHP layouts now pass
                  select_menu context on 5.2 but url_select context on 5.1.
                  At cutover, swap this to:
                    {{#is_select_menu_context}}{{> core/tertiary_navigation_selector }}{{/is_select_menu_context}}
                    {{^is_select_menu_context}}{{> core/url_select }}{{/is_select_menu_context}}
                  And have PHP set $overflow->is_select_menu_context = true on the 5.2 branch.
                  See docs/5.2-merge/PHASE-B3C-TOP-TEMPLATES-REBASE.md. --}}
            {{> core/url_select}}
        </div>
    </div>
{{/overflow}}
```

The {{!-- ... --}} mustache comment is invisible at render time but
makes the cutover task discoverable via grep.

---

## What NOT to do today

- **Don't swap to `core/tertiary_navigation_selector` unconditionally**
  → breaks 5.1 production.
- **Don't adopt 5.2 `core/login_layout` partial** → losses Sentientia
  login branding for no operational gain.
- **Don't backport the full secure.mustache** → fine for 5.2 cutover
  day, but 5.1 production needs the current shape.

---

## Versions

```
theme_airpayux : 2026052328 → 2026052329 (1.0.28-beta → 1.0.29-beta)
```

The version bump is for the tagged TODO line in course.mustache —
SCSS bundle doesn't change, but the template hash will. Bumping
ensures Moodle's mustache cache picks up the new content.

---

## Refs

- ADR-011 §"Phase B work breakdown" — B.3.c 5h estimate
- PHASE-A4B-CONFLICT-MAP.md §"F. boost/templates/*" — per-template
  strategy table
- PHASE-B3B-LAYOUTS-REBASE.md — the PHP side of this leg's pairing
- 5.2-theme-boost-full.diff — upstream template deltas
- This file — Phase B.3.c leg
