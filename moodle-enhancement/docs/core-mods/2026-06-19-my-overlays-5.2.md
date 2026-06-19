# Core-mod record — `my/` overlays on Moodle 5.2

**Date:** 2026-06-19 · **Context:** Moodle 5.2 reconciliation (see `docs/cutover/MOODLE-5.2-RECONCILIATION-PLAN.md`).

Moodle 5.2 ships its **own** `my/dashboard.php` and `my/switchrole.php` as real core files (4.5 did
not). The Sentientia/BizLMS deploy **overwrites** both. They use only stable 5.2 APIs (verified by the
2026-06-19 compat audit — no fatal), so they are not a 5.2 break, but they ARE undocumented core-file
overrides of now-existing core files. Recorded here per the project's core-mod discipline.

| File | What our overlay does | 5.2 risk | Action on each 5.2.x pull |
|---|---|---|---|
| `my/dashboard.php` | Redirect shim → `/my/index.php` (every BizLMS nav link targets `/my/dashboard.php`) | Upstream 5.2 ships its own dashboard.php; a point-release pull can clobber/relitigate this. Redirect builds `moodle_url` from raw `$_GET` (fragile, not a vuln) | Diff vs upstream `my/dashboard.php`; prefer pointing nav at `/my/` directly, else re-apply + sanitise params |
| `my/switchrole.php` | BizLMS role-switch endpoint; writes `$SESSION->airpay_switchrole` + `$USER->useraccess['currentroleinfo']` (NOT core `role_switch()`/`$USER->access['rsw']`) | Overlays 5.2's own switchrole.php. Relies on BizLMS reading `$USER->useraccess` (not a core field) — if the BizLMS costcenter layer is absent the switch silently no-ops. WF-025 history (force-pin/role-demotion). | Re-verify BizLMS still consumes `$USER->useraccess['currentroleinfo']` on the 5.2 cutover; diff vs upstream; consider routing through core `role_switch()` semantics |

**Cutover gate:** on the 5.2 migration, after deploying these two files, confirm `/my/dashboard.php`
resolves (no 404, no redirect loop) and role-switch works for an org-role user — both were validated on
5.1.3+ (F7) and must be re-checked on 5.2.
