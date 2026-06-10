# Production Rollout Packet — Nitin's 2026-06-10 switch decisions

**Owner:** Nitin Rajput · **Status:** steps 1–3 ready now; step 5 (Moodle 5.2) pending local execution
**Context:** Nitin approved all four queued switches on 2026-06-10: (1) one-click enrol ON for internal
tenants, (2) merge the QA-fix branches, (3) deploy the paygw security fix ("we test later"),
(4) Moodle 5.2 cutover = now. Everything executable from the engineering workstation is DONE and on
the `production` branch; this packet is the exact, ordered remainder that must run **on the live
server** (file access is manual/IT).

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

Being executed on local staging first (rehearsed runbook); this packet will be extended with the
exact live 5.2 steps (core file swap + upgrade + theme rebuild) once the local execution passes the
cutover smoke test. **Sequencing note for IT:** product layer (steps 1–5) ships on Moodle 5.1
first; 5.2 follows as its own window — do not combine unless Nitin explicitly collapses them.

---

*Generated from the SW-1..4 execution log, 2026-06-10. Update on every change to the above.*
