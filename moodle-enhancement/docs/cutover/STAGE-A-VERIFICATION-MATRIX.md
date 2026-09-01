# Stage A verification matrix — Sentientia 5.2 on UAT

**Purpose:** the pass/fail record executed immediately after
`tools/uat/stage-a-install.sh` completes on `UAT-Sentientia-LMS`. Together with
the install log (`~/stage-a-*.log`) this IS the Stage A evidence — the first
runtime validation of the 5.2 package (validates/falsifies the P4 static PASS).
**Executor:** Nitin + Claude (via public URL + SSH) · **Drafted:** 2026-08-28
**Evidence folder:** `docs/visual-evidence/<date>/uat-stage-a/` (screenshots) +
the install log archived alongside.

Fill Result with ✅ / ❌ / ⚠ and attach evidence per row. Any ❌ → §F triage.

---

## A. Install integrity (from the script + SSH)

| # | Check | How | Expected | Result |
|---|-------|-----|----------|--------|
| A1 | Install script exit | script output | exit 0, "finished" banner, log path printed | |
| A2 | Install log archived | copy `stage-a-*.log` off-box into evidence folder | log stored in repo evidence folder | |
| A3 | `config.php` in place | `ls -l /var/www/sentientia/config.php` | exists, `640`, not world-readable | |
| A4 | Install wizard CLOSED | `curl -s -o /dev/null -w '%{http_code}' https://academy2.airpay.ninja/install.php` | NOT an installation wizard (redirect/error page) | |
| A5 | DB schema created | script step 3–4 output | `sentientia_uat` utf8mb4; install_database completed without error | |
| A6 | Cron wired | `sudo -u www-data php .../admin/cli/cron.php` once (or confirm systemd/crontab entry) | runs clean; note the recurring schedule | |

## B. Public-URL smoke (no login)

| # | Check | How | Expected | Result |
|---|-------|-----|----------|--------|
| B1 | Login page | `https://academy2.airpay.ninja/login/index.php` | HTTP 200, Sentientia-branded login (not stock Moodle) | |
| B2 | HTTPS behaviour | click through from `http://` | 301 → https, no mixed-content warnings, no redirect loop (sslproxy proof) | |
| B3 | Landing/front page | root URL | renders without exception/debug output | |
| B4 | Hindi availability | language menu on login page | हिन्दी selectable, login strings switch | |

## C. Admin walk (browser, `admin` account)

| # | Check | How | Expected | Result |
|---|-------|-----|----------|--------|
| C1 | Admin login | login as `admin` | dashboard renders in app shell, zero console errors | |
| C2 | Environment page | Site admin → Server → Environment | all rows OK/green (PHP 8.3, MySQL 8.4, extensions) — screenshot | |
| C3 | Plugins overview | Site admin → Plugins → Plugins overview | all `local_sentientia_*` / theme / blocks present + up-to-date; count matches package manifest | |
| C4 | Scheduled tasks | Server → Scheduled tasks | no task in fail state after first cron | |
| C5 | Feature-flag audit | the flags admin page | every gap/AI flag OFF (default posture); record any exception | |
| C6 | Outbound mail OFF | Server → Email or config check | `noemailever` active — no mail can leave UAT (151-email rule) | |
| C7 | Theme + dark mode | toggle dark mode on dashboard | tokens resolve, no white-on-white; screenshot both modes | |

## D. Functional micro-pass (5–10 min)

| # | Check | How | Expected | Result |
|---|-------|-----|----------|--------|
| D1 | Create test course | admin → new course "UAT-SMOKE-01" (hidden) | creates + renders in course shell | |
| D2 | Add one activity | add a Page/quiz to the course | saves + displays in the in-course player | |
| D3 | EN ⇄ HI toggle | switch language on dashboard + course | full UI switches, no missing-string placeholders | |
| D4 | Mobile viewport | devtools 590px on dashboard + course | responsive shell, no horizontal scroll; screenshot | |
| D5 | Second account | create one manual test user, log in | learner shell renders (non-admin path proves capability defaults) | |

## E. Security posture quick pass (from outside)

| # | Check | How | Expected | Result |
|---|-------|-----|----------|--------|
| E1 | `config.php` not served | `curl https://academy2.airpay.ninja/config.php` | empty 200 (PHP executes to nothing) — never source text | |
| E2 | Directory listing | try `/theme/`, `/local/` | 403 or redirect — no index listing | |
| E3 | Headers | `curl -I` on login page | HSTS present (LB or Apache); no server version oversharing (note-only) | |
| E4 | Admin password | — | strong password vaulted; forced-change not pending | |
| E5 | Emailed credentials rotated | LMS + db_user passwords changed at first login | done + vault updated | |

## F. Triage protocol (any ❌)

1. Capture the failing output **verbatim** (screenshot + text) before touching anything.
2. Classify: **Blocker** (install incomplete / page fatal / data loss) vs **Note**
   (cosmetic, config, deferred item). Blockers stop the walk; notes are logged and continue.
3. Blockers → same-day triage session against the repo (the P4 static validation
   report lists the known-risk areas to check first).
4. Every fix applied on UAT lands in git the same day (UAT hygiene rule,
   `UAT-REMOTE-DEV-WORKFLOW.md` §4) — the package refreshes before Stage B.

## G. Result summary (fill at the end)

```
Date executed: ____________  Executed by: ____________
A: __/6   B: __/4   C: __/7   D: __/5   E: __/5
Blockers found: ____  (list)
Notes logged:  ____  (list)
VERDICT: STAGE A PASS / PASS-WITH-NOTES / FAIL
→ On PASS: schedule Stage B (live-backup rehearsal) — requires the LB IP-allowlist
  + moodledata/DB size answers first (checklist §1, §6).
```
