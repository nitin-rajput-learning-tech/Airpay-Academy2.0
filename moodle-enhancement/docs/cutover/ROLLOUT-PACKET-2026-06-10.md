# Production Rollout Packet — Nitin's 2026-06-10 switch decisions

**Owner:** Nitin Rajput · **Status:** engineering side of SW-1/2/3 done; SW-4 (5.2) pending local execution

> ## ⛔ ROLLOUT GATE (Nitin, 2026-06-10 — overrides any "deploy window" language below)
>
> **Production deploy on live airpay.academy happens ONLY on Nitin's explicit go.** The path there:
>
> 1. **Phase 1 — Foolproof:** test each and every workflow on local staging (full persona × workflow
>    matrix; automated where possible, scripted-manual otherwise; evidence recorded).
> 2. **Phase 2 — Ninja sandbox:** deploy Sentientia to the ninja sandbox server, then **rehearse the
>    migration of a LIVE airpay.academy backup onto it** — restore DB + moodledata, run upgrades,
>    verify user/enrolment/completion/certificate data intact (parity counts before vs after).
> 3. **Phase 3 — Replacement (Nitin-gated):** only after Phase 2 succeeds, replace live
>    airpay.academy with Sentientia LMS, existing Academy users' data intact.
>
> The "IT deploy-window steps" below therefore describe the **Phase-3 procedure** (and double as the
> Phase-2 ninja-sandbox procedure, pointed at the sandbox). They are NOT scheduled — they execute
> per phase, on Nitin's word.

---

## What was already executed (no IT action)

| Switch | Result |
|---|---|
| SW-2 merge QA branches | **No merge needed.** Analysis: `e01` + `paygw` branches are 0 commits ahead (already contained); `e02`/`p02`/`sa05` are superseded by the canonicalized `theme/sentientia` (skills nav link, cart_badge wiring on all layouts, "Sentientia UX" branding all present at HEAD). The one genuinely missing fix — **SA-04** capability-gated native course-management redirect — was **ported** to `theme/sentientia/classes/output/core_renderer.php` (helper `can_reach_native_course_admin()` + gates on the `/course/management.php` and `/course/index.php` redirects). The 5 `fix/qa-walk-*` branches are now obsolete — safe to delete on GitHub at leisure. |
| SW-3 paygw fix | **Ported into the canonical tree.** The fail-closed verifier lived only in the orphaned `moodle-enhancement/payment/gateway/airpay/` copy (v…0.10); the canonical `payment/gateway/airpay/` (webroot lineage, v…0.09) still had the bypass + md5 + file-scope require_login. Canonical now = fixed tree (process.php, airpay_helper.php, checksum.php, db/upgrade.php, version.php, tests/). Deployed + verified on local staging. |
| SW-1 flag | **Executed on local staging**: tenant 1 already ON, tenant 177 flipped ON, Public 77 untouched. New audited, idempotent CLI ships in the deploy: `local/sentientia_catalog/cli/enable_oneclick_enrol.php`. |

---

## IT deploy-window steps (live server, in order)

> Precondition: take the standard DB + moodledata backup first.

1. **File deploy** — sync the `production` branch working tree to the live webroot per the standard
   deploy procedure (`theme/sentientia/`, `local/`, `payment/gateway/airpay/`, `blocks/`, etc.).
   This single step carries the entire 2026-Q2 product layer **including the paygw security fix
   and the SA-04 admin redirect gate**.
2. **Upgrade** — `php admin/cli/upgrade.php --non-interactive` (applies `paygw_airpay` v…0.10 +
   any pending plugin bumps).
3. **Purge caches** — `php admin/cli/purge_caches.php`.
4. **Flag flip (SW-1)** — `php local/sentientia_catalog/cli/enable_oneclick_enrol.php`
   (idempotent; enables tenants 1 + 177; add `--tenants=` to change scope; `--dry-run` to preview).
5. **Smoke test** — run the cutover smoke checklist (login, /my/ dashboard, catalog, one course page,
   one admin page; zero console errors). As siteadmin, confirm `/course/management.php` now reaches
   the NATIVE management hub (SA-04) while a learner still gets the catalog redirect.
6. **Paygw sandbox transaction (Nitin-deferred)** — per Nitin's call, the deploy does NOT wait for
   this; run one sandbox payment at first convenience after deploy and confirm the fail-closed
   verifier accepts a good hash + rejects a tampered one. Reference:
   `moodle-enhancement/docs/security/2026-06-02-airpay-payment-verification-fix.md`.

## Step 7 — Moodle 5.2 cutover (SW-4, "now")

**✅ EXECUTED ON LOCAL STAGING 2026-06-10** (full log: `SW4-52-LOCAL-EXECUTION.md`): 5.1.3+ → 5.2+
(Build 20260519) completed successfully on a fresh clone of the production-shaped DB; data intact
(3,176 users / 412 courses / 22,523 enrolments / 32,248 completions); login + storefront smoke green.

**Proven 5.2 procedure (sandbox first, then live — each on Nitin's go):**
1. Pre-checks (HARD): server PHP ≥ 8.3 (CLI **and** web SAPI) with mysqli/intl/mbstring/curl/zip/gd/
   soap/openssl/sodium/exif/fileinfo + `max_input_vars ≥ 5000`. DB + moodledata backup taken.
2. Maintenance mode ON.
3. Swap core to the 5.2 tree (Build 20260519 lineage) + overlay the current product layer
   (theme/sentientia, local/sentientia_*, blocks, payment/gateway/airpay, enrol/sentientiasub,
   quiz accessrule) — the same compose verified locally.
4. `php admin/cli/upgrade.php --non-interactive` (ran 2,057 steps locally, zero errors).
5. Purge caches; maintenance OFF; smoke (login 200, dashboard, catalog, one admin page).
6. Rollback = restore backup + previous file tree (local fallback proven: the 5.1 instance was
   never modified).

**Sequencing note for IT:** product layer (steps 1–5 above in the main sequence) ships on Moodle 5.1
first; 5.2 follows as its own window — do not combine unless Nitin explicitly collapses them.

> ⚠ **HARD PRECONDITION (found in the local run, 2026-06-10):** Moodle 5.2's environment gate
> requires **PHP ≥ 8.3.0** — the upgrade CLI refuses to start below it (clean abort, exit 1).
> Before the sandbox/live 5.2 window, IT must verify/upgrade the server PHP (both CLI **and**
> the web SAPI) to 8.3+ with extensions: mysqli, intl, mbstring, curl, zip, gd, soap, openssl,
> sodium, exif, fileinfo, opcache. The local run used PHP 8.4.21.

---

*Generated from the SW-1..4 execution log, 2026-06-10. Update on every change to the above.*
