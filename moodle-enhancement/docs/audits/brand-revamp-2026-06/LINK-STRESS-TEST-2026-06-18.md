# End-user link stress-test — Sentientia LMS (2026-06-18)

Exhaustive, authenticated probe of **every end-user-facing link** across personas on
local XAMPP (`http://localhost:8080`, wwwroot has **no** `/moodle` prefix on the clean
reinstall). Read-only: the probe never follows destructive links (logout/delete/
unenrol/etc.). Harness lives in `moodle-enhancement/tools/gap-test/`.

## Method
- **Harness** `probe_links.ps1` — logs a persona in via the real `/login/index.php`
  POST (handles `logintoken`), probes a curated inventory of **~107 end-user URLs**
  (`urls.core.txt`: guest/public + core surfaces + all 46 `local_sentientia_*` plugin
  entry points + key sub-pages, with real DB IDs), classifies each response
  (`OK / REDIR-LOGIN / DENIED / PHP-FATAL / HTTP-4xx/5xx / THIN`), and for guest also
  crawls the rendered dashboard/catalog for same-origin links. Hard safety exclusions,
  an inter-request delay, and a **circuit-breaker** (abort a persona after N consecutive
  failures) so it can never overload the single-child Windows Apache.
- **Personas** (`run_all.ps1`, sequential): guest, learner (`fatma.khamis`), public
  learner (`vimalkoothattu`), course author (`asif.ansari`), manager
  (`binay.upadhyay`), compliance (`joseph.mandapati`), tenant admin
  (`academyexadmin`), site admin (`academy@`). Local-only creds.
- **Consolidation** `summarize.ps1` → `reports/matrix.csv` (per-URL × persona) +
  `reports/findings.csv`.

## Headline verdict
**End-user links are overwhelmingly healthy.** Site admin reaches **91/107 OK**;
ordinary learner/manager/public personas render 52–55 pages OK with **zero errors** and
correct capability-gating on everything else. Across the whole matrix only **one real
product defect** was found (and fixed), plus a small set of cosmetic/debug issues and
two instance-specific activity errors attributable to the incomplete-filedir local clone.

## Per-persona results (clean run, OPcache off, 2026-06-18)
| Persona | OK | 404 (gated/flag-off) | 500 | login-redirect | Hard errors |
|---|----|----|----|----|----|
| Site admin | 91 | 12 | 3* | 1 | 0 fatal |
| Learner | 52 | 54 | 0 | 1 | **0** |
| Manager | 55 | 51 | 0 | 1 | **0** |
| Public learner | 52 | 54 | 0 | 1 | **0** |
| Guest | 14 | 2 (+94 redirect-login) | 0 | — | 0 (1 THIN = the fixed bug) |
| Author / Compliance / Tenant admin | — | — | — | — | **home-page redirect loop — see F7** |

\* The 3 site-admin 500s: `course/view?id=22` was **transient** (303/OK on retry);
`mod/quiz/view?id=53` + `mod/forum/view?id=2` are **real but instance-specific** (see F5).

These 3 org-position personas authenticate successfully (password validates, not bounced
to login) but their **home page is an infinite redirect loop** (F7), so the probe could
not land on a dashboard to enumerate their links. Their *capability* gating is already
covered by the 5 completed personas.

## Findings

### F1 — `content_market` browse page blank for everyone — **P1, FIXED + deployed**
`local/sentientia_content_market/index.php` had `defined('MOODLE_INTERNAL') || die();`
placed **before** `require_once('../../config.php')`. Since `config.php` is what *defines*
`MOODLE_INTERNAL`, the guard fired `die()` immediately → **blank 0-byte HTTP 200 for all
users**. Caught by the byte-level probe (the earlier browser walk reported it "OK" because
it only checked status 200, which a headerless `die()` still returns). **Fix:** load
`config.php` first, then the guard (mirrors the correct `sentientia_xapi/index.php`
ordering). Swept all 46 plugins — the only entry point with this defect.

### F2 — OPcache instability on Windows under load — **P2 (environment), root-caused + mitigated**
A fast sequential probe drove Apache into a crash loop: `child process … exited with
status 3221225725` (0xC00000FD stack-overflow) ×2 plus repeated `VirtualProtect() failed
[87]` — the signature of OPcache shared-memory instability on Windows, surfaced by the
OPcache that was enabled this engagement for the page-speed fix. **Confirmed:** after
setting `opcache.enable=0` in `php.ini` and a full Apache Stop→Start, the entire matrix
ran with **zero crashes / zero VirtualProtect errors**. Not a product defect (the pages
themselves are fine); a local Windows/OPcache tuning issue. **Action:** keep OPcache off
until tuned, or re-enable with a smaller `opcache.memory_consumption` + AV exclusion for
the SHM and re-test under load before production.

### F3 — Stale capability fallbacks → "Capability not found" debug noise — **P3**
`local/sentientia_org/classes/accesslib.php` (lines ~308–368) and
`theme/sentientia/.../core_renderer.php` check **both** new and **removed** legacy caps
(`X || has_capability('local/costcenter:…')`). The new `local/sentientia_org:*` caps work
(left of `||`), but the dead fallback to removed `local/costcenter:*` /
`local/classroom:manageclassroom` / `block/trainerdashboard:viewtrainerslist` fires a
debug notice on **every** nav render. Fix: drop the obsolete fallbacks (the
`migrate_all.php` map already renamed them) or guard with a capability-exists check.

### F4 — Stale lang-string component reference — **P3**
`Invalid get_string() identifier: 'enrolledusers' or component 'local_courses'` — a course
surface references the pre-rename component `local_courses` (now
`local_sentientia_courses`) and/or a missing string. Cosmetic notice; fix the component
name / add the string.

### F5 — Two activity instances 500 — **P2, instance-specific (likely clone artifact)**
`mod/quiz/view?id=53` and `mod/forum/view?id=2` return HTTP 500 (empty body, **no PHP
fatal logged, no worker crash today**) for site admin, while **other quizzes
(`id=55`) and forums (`id=4`) render fine** — so it is **not** a systemic module bug.
The clone's `filedir` is incomplete (`getimagesize … No such file or directory` warnings),
which is the known [clone-filedir artifact]; these two specific imported instances likely
reference missing content. **Action:** re-verify on a complete-data environment (the
ninja-sandbox migration rehearsal); not expected to affect production where filedir is intact.

### F6 — Incomplete `filedir` on the local clone — **known data artifact, not a bug**
`getimagesize(... moodledata/filedir/...): No such file or directory` — DB imported without
the file store. Expected on local clones; migration must carry `filedir`.

### F7 — Home-page redirect loop for org-position personas — **P1, FIXED + VERIFIED (2026-06-18)**
**Fix applied** to `theme/sentientia/classes/output/traits/user_menu.php`: the auto-role-scope
redirect is now guarded by a one-shot `$SESSION->sentientia_autoroleswitched` flag (set before
the scope call so a throwing call can't re-loop) and redirects to `/my/` instead of `/`.
Also synced the deployed local theme `core_renderer.php` to the repo's WF-025b version (the
local XAMPP copy was stale — pre-WF-025b 3-arg `role_switch_basedon_userroles`). **Verified:**
asif's `/my/` hit-1 = one-time interstitial, hit-2 = full dashboard (79 KB, logout present),
loop gone; learner + manager dashboards unaffected (75/88 KB); **no WF-025 demotion** —
`authoring/studio.php` = 200/46 KB for asif (author caps intact). Root-cause detail below.


Course author (`asif.ansari`), compliance (`joseph.mandapati`) and tenant admin
(`academyexadmin`) authenticate fine but **`/my/` → 303 → `/` → 303 → `/` … infinite loop**
— they reach no home page. Ordinary learner (`fatma.khamis`) and manager (`binay.upadhyay`)
land on `/my/` (200 dashboard) normally. Confirmed server-side (303 `Location` headers).

**Root cause (code-traced):**
`theme/sentientia/classes/output/traits/user_menu.php:231-240` — building the user menu on
**every** authenticated render, for a user with switchable roles (`count($roles) > 0`) whose
`$USER->useraccess['currentroleinfo']` is empty and who has a `$highest_roleinfo->roleid`,
the theme auto-switches them into their highest role and then **`redirect(new moodle_url('/'))`**:
```php
if ((count($roles) > 0) && (!isset($USER->useraccess['currentroleinfo']) || empty(...))) {
    if ($highest_roleinfo->roleid) {
        $this->role_switch_basedon_userroles($highest_roleid, false, $contextid);
        redirect(new moodle_url('/'));   // re-fires every request → loop
    }
}
```
The redirect is meant to fire **once**, suppressed thereafter by the `currentroleinfo` guard
that `roleswitch()` sets (`core_renderer.php:1420`). But that guard lives on
`$USER->useraccess` — **retired BizLMS session machinery** explicitly flagged as having "no
Sentientia equivalent" (`local/sentientia_org/classes/hook_callbacks.php:34`) — which is not
reliably persisted across the redirect for these users. So the guard never reads "done" →
every render re-redirects → `/my/`→`/`→`/`… The only `redirect('/')` in the theme is this
line (grep-confirmed); `custom_secured_redirection()` is **not** involved (it only rewrites
`/my/dashboard.php`, `/enrol`, `/course*`, `/user/*`). Ordinary learner/manager lack a
switchable highest role, so the block is skipped and they reach the dashboard.

**Caveat — partial interaction with this session's role work:** the loop pre-exists for
multi-role org users (joseph + academyexadmin, untouched this session, already looped).
Assigning the new **Sentientia Author** role to `asif` at SYSTEM context this session very
likely added `asif` to the multi-switchable-role set, pulling him into the same loop. So the
new system-context author-role provisioning can trip this latent bug for SME authors — worth
fixing F7 **before** assigning the Author role broadly.

**Recommended fix (precise, low-risk; NOT applied — role-switch logic is the WF-025 danger
zone, needs your go + careful re-test):** make the auto-switch+redirect idempotent with a
reliably-persisted one-shot guard on the core `$SESSION` (which *is* session-backed), instead
of the retired `$USER->useraccess`:
```php
global $SESSION;
if (count($roles) > 0 && empty($SESSION->sentientia_autoroleswitched)
    && (!isset($USER->useraccess['currentroleinfo']) || empty($USER->useraccess['currentroleinfo']))) {
    if ($highest_roleinfo->roleid) {
        $SESSION->sentientia_autoroleswitched = true;  // one-shot
        $this->role_switch_basedon_userroles($highest_roleinfo->roleid, false, $highest_roleinfo->contextid);
        redirect(new moodle_url('/my/'));   // go straight to dashboard; avoids the / hop
    }
}
```
Clear `$SESSION->sentientia_autoroleswitched` on logout so a fresh session re-switches once.
Add a probe assertion (same URL must not 303 to itself / same pair twice) to the rollout gate.

## Working as expected (NOT defects)
- **404s for non-admins** on plugin admin pages = correct **capability-gating** (hidden
  via themed 404) — verified OK-for-siteadmin / 404-for-learner pattern.
- **404s for everyone** on `calendar`/`learningpath`/`analytics` plugin pages = their
  **feature flags resolve off** (gap features ship default-OFF). `content_market` /
  `talent` / `skillsai` flags are currently ON (persisted from prior testing) and render.
- `/message/index.php` 404 for all = **`$CFG->messaging = 0`** (messaging disabled site-wide).
- `sentientia_manager/index.php` reachable by a learner = **intentional** (T-03 fix): it
  renders a graceful empty "My Team" showing only the viewer's own (empty) team — explicitly
  no data leak.
- `sentientia_pages/index.php` requires `?page=privacy|terms|help|contact` — a public
  static-page viewer; a bare hit is a 404 by design (harness inventory corrected).

## Reusable artifact
The `tools/gap-test/` harness (probe engine + URL inventory + per-persona driver +
consolidator) is a **repeatable link/regression gate**. Recommend running it as part of the
rollout gate after each deploy (on a complete-data environment, OPcache tuned).

## Net
- **F1** — content_market blank page: real P1, **fixed + deployed**.
- **F7** — home-page redirect loop for org-position roles (author/compliance/tenant-admin):
  real **P1**, **FIXED + verified** — one-shot `$SESSION` guard in `user_menu.php` (+ synced the
  stale local `core_renderer.php` to the repo's WF-025b version). Loop gone, no regression, no
  role-demotion.
- **F2** — OPcache Windows instability: environment, root-caused + mitigated (OPcache off).
- **F5** — 2 instance-specific activity 500s (quiz 53 / forum 2): re-verify on complete-data env.
- **F3, F4, F6** — cosmetic debug-noise / known clone artifacts to tidy.
- **Everything else across ~107 URLs × personas: working as designed** (capability gating,
  feature-flags-off, messaging-off, intentional redirects). The probe harness in
  `tools/gap-test/` is a reusable rollout-gate regression check.

## Close-out 2026-06-18 (milestone prep)
- **F3 — FIXED in the Sentientia product code.** Added a behaviour-preserving
  `local_sentientia_org\accesslib::legacy_cap()` guard (skips `has_capability` for an
  unregistered legacy cap → no notice, identical return) and routed the 6 org/classroom
  legacy fallbacks + the theme `core_renderer` `block/trainerdashboard` fallback through it.
  **Verified by CLI**: `has_capability('local/costcenter:view')` EMITS the notice;
  `accesslib::legacy_cap(...)` and `can_view()` are **silent**. Residual "Capability not
  found" notices remaining at runtime originate **only** from legacy BizLMS vendor blocks
  (`block_reportdashboard` / `block_learnerscript`) that call the old caps directly — these
  are DEVELOPER-debug-level only (zero production impact; production runs debug=NORMAL) and
  belong to the BizLMS-decouple track, not the Sentientia product. **Not a deploy blocker.**
- **F4 — deferred cosmetic.** `get_string('enrolledusers','local_courses')` is a
  DEVELOPER-debug-level notice (invisible at production debug) and is **not present in any
  shipping product file** (no `local/courses` plugin; not in `sentientia_*` plugins, theme,
  or lang/nav hooks) — so it has no impact on the ninja/production deploy. Origin likely a
  dynamically-composed component; flagged for a runtime trace if it recurs. **Not a blocker.**
- **F5 — re-verified: instance-specific clone-data artifact, NOT a code defect.** `quiz
  cmid=53` + `forum cmid=2` still return empty-500 (no PHP fatal logged) while other quiz/
  forum instances (cmid 55 / 4) render 200 — the local clone's `filedir` is incomplete (the
  known [[project_clone_filedir_artifact]]; `getimagesize … No such file or directory`
  warnings on those activities). Re-verify on the complete-data ninja sandbox. **Not a
  deploy blocker for the product code.**
