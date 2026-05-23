# P5 Deprecation Sweep — Moodle 5.2 readiness

**Date:** 2026-05-23
**Owner:** Claude (mechanical grep)
**Per:** ADR-010 P5 (Moodle 5.2 borrow inventory — deprecation audit)
**Scope:** `moodle-enhancement/local/airpay_*` (30 plugins) +
`moodle-enhancement/theme/airpayux/` (514 files) — NOT
`moodle-enhancement/admin/tool/` core-mods (those track upstream).
**Mode:** Inventory only. No fixes in this pass.

---

## Findings — by deprecated API

### 1. `core/modal_factory` + `core/modal_registry` — 4 files

These two AMD modules are deprecated in Moodle 5.2. Use `core/modal`
directly (the class, not the factory pattern).

```
local/airpay_courses/amd/src/enrolledusers.js
local/airpay_request/amd/src/request_button.js
local/airpay_request/amd/src/decide.js
local/airpay_cart/amd/src/admin_orders.js
```

**Migration shape (per Moodle 5.2 upgrade notes):**

```javascript
// OLD (deprecated):
import ModalFactory from 'core/modal_factory';
import ModalRegistry from 'core/modal_registry';
const modal = await ModalFactory.create({
    type: ModalFactory.types.SAVE_CANCEL,
    title: '...', body: '...',
});

// NEW (Moodle 5.2):
import Modal from 'core/modal';
import ModalSaveCancel from 'core/modal_save_cancel';
const modal = await ModalSaveCancel.create({
    title: '...', body: '...',
});
```

**Migration effort:** 4 files × ~10 min each = 40 min, plus
re-build the AMD bundles. Defer until we upgrade to Moodle 5.2.

### 2. `moodle-core-notification` family — CLEAN

No usage found in our code. Only the ADR-010 mention.

### 3. `M.util.set_user_preference()` / `xmlize()` / `file_encode_url()` — 2 files

```
theme/airpayux/amd/src/drawer.js
theme/airpayux/amd/src/drawers.js
```

Both are theme files forked from upstream Boost. Need to verify
whether these calls are ones we wrote or upstream code we inherited.
The drawer JS handles open/close state persistence — almost
certainly `M.util.set_user_preference()` for the drawer-collapsed
preference.

**Migration shape:**
```javascript
// OLD: M.util.set_user_preference('drawer-open-nav', 0);
// NEW: import UserRepository from 'core_user/repository';
//      UserRepository.setUserPreference('drawer-open-nav', 0);
```

**Migration effort:** 2 files × ~15 min. Defer.

### 4. `course_delete_module()` / `get_moodlenet_info()` / `switch_question_bank` — CLEAN

No usage. We never adopted these APIs.

### 5. `master-button` / `yui-treeview` selectors — CLEAN

No usage in our SCSS, Mustache, or JS. The `core/checkbox-
toggleall` rename (master-button → toggler) doesn't affect us.

### 6. `cm_info` / `course_request` namespace usage — CLEAN

No direct PHP usage of these classes in our plugins. We don't
extend cm_info or course_request — we wrap modinfo via standard
Moodle APIs. Migration when 5.2 lands is mechanical: replace
`\cm_info` → `\course\cm_info` (BC layer in place).

### 7. PHP `serialize()` / `unserialize()` — CLEAN

No usage in `local/airpay_*`. We use `json_encode/json_decode`
throughout (intentional — see CLAUDE.md security rules). This
means we're already compliant with Moodle 5.2's "JSON instead of
PHP serialization" security tightening — no migration needed.

---

## Blast radius summary

| Deprecation | Files affected | Migration effort | Priority |
|-------------|----------------|------------------|----------|
| `core/modal_factory` + `core/modal_registry` | 4 AMD files | ~40 min + rebuild | When upgrading to 5.2 |
| `M.util.set_user_preference()` / `xmlize()` / `file_encode_url()` | 2 theme JS files | ~30 min + rebuild | When upgrading to 5.2 |
| Everything else | 0 | 0 | N/A |

**Total Moodle 5.2 migration blast radius for our code: 6 files,
~70 min of mechanical updates.**

This is **dramatically lower** than I expected. The reason: our
custom plugins were written 2025-2026, mostly post-modal-factory-
deprecation-announcement, so they happen to already use the
modern shapes. The 4 files that DO use the deprecated factory
are the oldest plugins (airpay_courses, airpay_request,
airpay_cart) — written before the deprecation hit our radar.

---

## Recommendations

### Now (this audit doc)
- **Land this inventory** so the migration is sized + visible
- **DO NOT** start fixing — the upstream API still works in 5.1.3+
  (we're not on 5.2 yet)
- File a follow-up task to migrate ALL 6 files in ONE session
  immediately after the PHP 8.3 + Moodle 5.2 upgrade lands

### Pre-upgrade audit checks (when 5.2 upgrade is scheduled)
1. Re-run this grep sweep on the actual upgraded codebase — new
   deprecation notices may surface
2. PHP 8.3 lint pass over all `local/airpay_*` + `theme/airpayux/`
3. Run the PHPUnit test suite to catch any class-not-found errors
4. Run the standalone `ws_contract_check.php` gate
5. Smoke-test the 10 Sentientia surfaces in browser
6. Run the new `tests/surfaces.spec.mjs` Playwright suite

### CI gate to add (ADR-009 P4 extension)
A new standalone PHP script `ci-deprecation-check.php` similar to
`ci-ws-contract-check.php` that greps the codebase for ANY
deprecated API mentioned in Moodle 5.2 UPGRADING.md. Block-on-find.
Would catch new deprecations introduced as we write new code
between now and the 5.2 upgrade.

**Effort:** ~2 hours. Same shape as the existing ws_contract
gate. Add as item to ADR-009 (P3 lift: 3 enforcement layers, not
just 2).

---

## Sentientia Live (`local_airpay_live`) — clean by construction

The Sentientia Live plugin shipped 2026-05-20 / 2026-05-21 (Stream
E.0 → E.7) using modern Moodle APIs throughout:
  - `core/modal` directly (no factory)
  - `core/notification` for toasts
  - `core_user/repository` for preferences
  - Native PHP 8.2 typed properties + enums

No P5 migrations needed for any Sentientia Live code. Confirmed
via dedicated grep:

```
grep -rE "modal_factory|set_user_preference|xmlize" local/airpay_live/
→ no results
```

This is the model the older 4 plugins should converge to.
