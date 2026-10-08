# Core-mod record: `my/` overlays (5.2 override, rewritten 2026-10-08 as an additive overlay on 5.3)

**Original date:** 2026-06-19 (Moodle 5.2 reconciliation, see `docs/cutover/MOODLE-5.2-RECONCILIATION-PLAN.md`).
**Rewritten:** 2026-10-08 (Moodle 5.3 compatibility pass, finding C1 / fix FX-16, ADR-033).

## Status on 5.3: two pure additions, no core file is overridden

Vanilla Moodle 5.3 `public/my/` holds only `classes`, `tests`, `courses.php`, `index.php`, `indexsys.php`, `lib.php` and
`upgrade.txt`. It has **no `dashboard.php` and no `switchrole.php`**. The two Sentientia files therefore do not replace
anything on 5.3: they are added files, and **both are still required**.

| File | What it does | Why it is still required on 5.3 |
|---|---|---|
| `my/dashboard.php` | Redirect shim to `/my/index.php` (the query string is carried over) | Production BizLMS exposed the dashboard at `/my/dashboard.php`, so bookmarks, the PWA start URL of an installed app, saved e-mail links and the theme navigation (`core_renderer`, `user_menu`, the mobile bottom nav) still point there |
| `my/switchrole.php` | The BizLMS role-switch endpoint (writes `$SESSION->airpay_switchrole`, then `local_sentientia_org\accesslib::set_user_role_switch()`; NOT core `role_switch()`) | The theme's role-switch menu links to it. WF-025 history (force-pin / role demotion) |

`my/templates/dropdown.mustache` (shipped by the 5.2 overlay) is referenced by nothing and is **no longer shipped**
(`tools/packaging/build-standalone.sh` and `moodle-enhancement/tools/overlay-airpay-customs.ps1` skip it).
The stale `moodle-enhancement/my_dashboard_redirect.php` (a 5.1 note that says the dashboard "no longer exists") is
unreferenced; it is slated for removal and needs a one-line `git rm` once the owner agrees (project rule: no deletion
without a confirm).

## What changed in the plugins (FX-16)

Plugin links no longer depend on the shim: they point at `/my/`, which is exactly where the shim redirected to.

- Redirects and links: `local_sentientia_cart` `index.php`, `local_sentientia_users` `signup.php`,
  `local_sentientia_emails` `email_context.php` (3) and `parity_senders.php`, `local_sentientia_notifications`
  `rule_engine.php`, `local_sentientia_platform` `hook_callbacks.php`, `local_sentientia_pwa` `manifest.php`
  (both trees).
- PWA start URL: the default brand bundle in `local_sentientia_platform` `customer::branding()` is now
  `/my/?utm_source=pwa_install`. A NEW upgrade step (`2026100802`) rewrites every stored customer brand row whose
  `start_url` is exactly the old shim path (optionally with a query or fragment) to the same URL on `/my/`; any other
  path an admin chose is left alone. The executed step that first stored the old value (`2026052201`) is not edited.
  Tests and the brand-resolver CLI expect the new default.
- Left on the shim on purpose: the theme navigation (`theme/sentientia` `core_renderer.php`, `user_menu.php`,
  `mobile_bottom_nav.mustache`) and dev CLIs that only call `$PAGE->set_url('/my/dashboard.php')`. The shim stays shipped,
  so those keep working; moving the theme to `/my/` is a separate, visual change.

## Hardening note (unchanged behaviour)

`my/dashboard.php` builds its redirect from the raw `$_GET`. Moodle's `moodle_url` takes parameter names and values as data
(not markup) and `redirect()` validates the target, so this is fragile rather than a vulnerability. Not changed here.

## Cutover gate

After deploying the package on a 5.3 instance, confirm `/my/dashboard.php` resolves (no 404, no redirect loop) and lands
on the dashboard, `/my/` renders the dashboard, and role switch works for an org-role user. Validated on 5.1.3+ (F7) and the
5.2 UAT; to be repeated on the first 5.3 instance (ADR-033 gate 8).

## History (the 5.2 version of this record)

On 5.2 the premise was different: Moodle 5.2 shipped its **own** `my/dashboard.php` and `my/switchrole.php`, and the deploy
overwrote both (undocumented overrides of existing core files, recorded here per the core-mod discipline). 5.2 core files
were checked against stable 5.2 APIs only (compat audit, no fatal). That premise no longer holds on 5.3, hence the rewrite
above; the 5.2 UAT instance keeps working unchanged because the files are the same.

## Upgrade-merge notes

Nothing to merge on 5.3 (new files). If a later Moodle release adds its own `my/dashboard.php` or `my/switchrole.php`,
diff against upstream before shipping ours and prefer pointing links at `/my/` so the shim can be dropped.
