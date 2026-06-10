# SW-4 — Local Moodle 5.2 cutover execution log

**Date:** 2026-06-10 · **Operator:** Claude (autonomous loop, Nitin's "Now, upgrade to 5.2")
**Mode:** promote the parallel `moodle5.2` instance, fully NON-DESTRUCTIVE — `moodle5` (5.1.3+, port 8080)
stays untouched as instant fallback. Nothing here touches live airpay.academy (rollout gate).

## Facts established

| Item | Value |
|---|---|
| Current primary | `C:\xampp\htdocs\moodle5` — 5.1.3+ (Build 20260415), DB `moodle` (2.5 GB prod import), dataroot `C:\xampp\moodledata` (18 MB, DB-only import → file store thin) |
| 5.2 instance | `C:\xampp\htdocs\moodle5.2` — 5.2+ (Build 20260519); core already carries the Phase-B merge work |
| 5.2 staleness | Product layer is Phase-B.12-era: 35 pre-rename `local/airpay_*`, `theme/airpayux` — must be replaced by the current layer |
| Old rehearsal DB | `moodle5_2` (168 MB, May-era) — left intact, NOT reused |
| Apache | Running, **8080 only**; no 8081 vhost. Decision: no Apache config edits (would need Nitin's confirm) — upgrade is CLI; smoke via PHP built-in server `php -S localhost:8081 -t public`. Full-browser campaign on 5.2 may later want a real vhost (ask Nitin then). |

## Plan (state ticked as executed)

- [x] 1. `mysqldump moodle` → `D:\Claude Local\Moodle Backup\sw4-moodle-dump-2026-06-10.sql` — **DONE, 3.3 GB**
- [~] 2. DB `moodle52_cut1` created (utf8mb4); restore running in background (old `moodle5_2` untouched)
- [x] 3. Dataroot copied → `C:\xampp\moodledata52_cut1` — **DONE**
- [x] 4. Stale add-ons MOVED aside → `_stale-b12\` — **DONE**: theme/airpayux, 31× local/airpay_*, 4 stale blocks, airpay_proctoring, stale paygw
- [x] 5. Current product layer synced from `moodle5\public` — **DONE**: theme/sentientia, 40× local/sentientia_*, 6 blocks, FIXED paygw_airpay, enrol/sentientiasub, sentientia_proctoring (local/ now 42 entries, matching primary)
- [x] 6. `config.php` → dbname `moodle52_cut1`, dataroot `moodledata52_cut1`, wwwroot :8081 — **DONE** (`noemailever=true` already present — real-user-email guard kept)

**Known risk noted:** the 5.2 core tree is the Phase-B merge build (20260519); core-mods added to the
5.1 tree AFTER B.12 (if any) are not auto-carried — upgrade + smoke + the foolproof campaign (#401)
are the nets. PHP 8.3 ✓ (5.2 requires 8.2+).
- [~] 7. Upgrade CLI — **first attempt blocked at the environment gate: Moodle 5.2 requires PHP ≥ 8.3.0;
  XAMPP CLI is 8.2.12** (exit 1, DB untouched — clean abort). Resolution: native **WinGet PHP 8.4.21**
  (all 11 required extensions enabled via the project-local `.tools/php84/php.ini`, used with `-c`;
  WinGet package untouched; Docker route available but daemon was down). Bootstrap verified against
  `moodle52_cut1` (3,176 users). Re-run in progress with PHP 8.4, logged to `sw4-upgrade-52.log`.
  ⚠ **PRODUCTION PRECONDITION discovered:** the live/sandbox 5.2 window requires the server PHP to be
  ≥ 8.3 FIRST — added to the rollout packet as a hard pre-check for IT.
- [x] 8. Purge ✓; smoke via `php84 -S localhost:8081` ✓ — login **200** (29.5 KB, 12 sentientia markers),
  public storefront **200** (54.7 KB), zero fatals/exceptions (the only regex hits are the benign YUI
  module names `moodle-core-notification-exception` present on every healthy Moodle page)
- [x] 9. Committed + packet step 7 extended

## Rollback

`moodle5` was never modified. To abandon: stop using 8081, leave `moodle52_cut1` for inspection or drop it later (Nitin-confirmed). Stale add-ons restorable by moving `_stale-b12` contents back.

## Result — ✅ SUCCESS (2026-06-10)

**`Command line upgrade from 5.1.3+ (Build: 20260415) to 5.2+ (Build: 20260519) completed
successfully`** (attempt #3; 2,057-line log, zero errors). Data intact post-upgrade:
**3,176 users / 412 courses / 22,523 enrolments / 32,248 completions.** HTTP smoke green.
`moodle5` (5.1, :8080) remains the untouched fallback; the 5.2 instance is the new primary
candidate for the foolproof campaign (#401).

**Environment gates discovered (now hard pre-checks in the rollout packet):**
1. PHP ≥ 8.3 required (XAMPP CLI 8.2.12 refused; resolved with WinGet PHP 8.4.21 +
   `.tools/php84/php.ini` — invoke `php.exe -c <ini>`).
2. `max_input_vars ≥ 5000` required.

**Follow-ups noted:** (a) known scssphp `Compiler.php:927` Array-to-string warning in the 5.2 tree —
port the Wave-A2 fix (needs a docs/core-mods entry); (b) for full-browser workflows on 8081 either an
Apache vhost (Nitin-confirmed change) or continued `php -S`; (c) the 5.2 instance now carries the
current product layer — keep it in sync with future `production` commits via the deploy automation.
