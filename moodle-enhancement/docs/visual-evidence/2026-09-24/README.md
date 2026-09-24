# 2026-09-24 — Wave 2 Window B (VPN off): UAT before-deploy baseline

**Instance:** https://academy2.airpay.ninja (Moodle 5.2, fresh install with seeded demo data).
**Code on UAT:** last deploy was `c74bd55be` (2026-09-22 07:05). **Nothing from the 09-22 audit onward
(`86bb0c26f..HEAD`) is deployed.** Everything below is therefore the *before* state; the Window A deploy
is what the after-state will be compared against.

**How it was captured.** Claude drove the user's Chrome over the Claude-in-Chrome extension, logged in
only through Chrome's saved-password autofill (no password was typed or read — the extension blocks
reads of the password field), and switched persona with Moodle's *Log in as*. Every page was a
read-only GET; nothing was submitted, enrolled, exported or flagged. UAT was left logged out.

**Screenshots.** This extension version does not return a saved file path, so image files could not be
written to this folder. Images were inspected in the session; the numbers and strings below are copied
verbatim from the rendered pages, which is the more precise record for W2-05.

## Personas

| Persona | User id | Role | Tenant |
|---|---|---|---|
| Meera Iyer (`uat_ldadmin_airpay`) | 4 | Administrator (not a site admin — lacks `moodle/site:config`) | `/1` Airpay |
| Priya Nair (`uat_learner_airpay`), via Log in as | 6 | Employee (learner) | `/1` Airpay |

Chrome holds a saved credential for Meera only, so no site-admin session was possible without typing a
password. That blocks the on-screen half of W2-06 and the privacy-registry capture (see below).

## Tenant 1 ground truth

The compliance matrix (already correctly tenant-scoped since 1.0.2) lists **9** Airpay people: the 8
named UAT personas plus *Admin User* (id 2). The other tenants are Public (`/77`: Deepa Menon, id 11) and
ZEEA (`/177`: Fatma Khamis id 12, Juma Mwakalinga id 13).

## W2-05 — do the tiles agree with the rows?

| Surface | What it shows | Correct? |
|---|---|---|
| Admin dashboard (`/my/`) | 8 active users, **9 total** | yes |
| Manage Users table (`/local/sentientia_users/index.php`) | **8** rows (excludes Admin User) | yes |
| Manage Users tiles | **10** total, 10 active, 0 suspended | **no** — 8 + ZEEA's 2, because `'/1%'` matches `/177` but not `/77` |
| Compliance → Department Scorecard | totals **3 / 6 / 6 = 15** | **yes** — *corrected after review:* Total counts user × mandatory-course rows, not people. 3 mandatory courses: `/1/2` Rahul + Arjun = 6, `/1/79` Vikram + Priya = 6, `/1/114` Sneha = 3. `'/1/2%'` cannot match `/1/79` or `/1/114`. My first reading called this a double-count; it is not. |
| Learner dashboard leaderboard, "Your department" (as Priya) | Priya 390, Vikram 270, Rahul 270, Joseph 270, **Fatma Khamis 270** | **no** — Fatma is ZEEA; an Airpay learner is shown another tenant's employee |

Both wrong surfaces are fixed in `86bb0c26f`, which is **not yet deployed**. After Window A: Manage
Users tiles should read 8 (matching the table), the scorecard should stay **3 / 6 / 6**, and Fatma
should leave Priya's leaderboard. The dashboard's 9 against Manage Users' 8 is expected and stays:
the dashboard counts Admin User (id 2, `/1`), the Manage Users table does not.

**Correction.** An earlier version of this file predicted the scorecard would "sum to at most 9".
That was wrong and would have produced a false failure; the checklist workflow's critic caught it.

Also captured, unchanged by the pending deploy: compliance BU filter reads "AIRPAY … (9)"; Defaulters
tab lists 9; the footer private-and-confidential notice is served; public login hero shows
"1+ learners / 4+ courses / 1+ certificates".

## W2-06 — plugin overview shows zero "Airpay" product names

**Predicted FAIL, not yet confirmed on screen** (needs a site admin). From code and the saved 09-03
capture (`2026-09-03/uat-stage-a/admin-plugins-overview.html`, matched row for row): **34** Sentientia
plugins display "Airpay …" names — 29 `local_sentientia_*`, 4 `block_sentientia_*`,
`quizaccess_sentientia_proctoring`. `paygw_airpay` ("Airpay") is correctly named after the payment
company. `local_sentientia_ratings` and `_request` are branded in Hindi only.

**Regression noted:** `b1ae4bd64` copied the ME analytics lang file over the top-level one, turning
"Sentientia Advanced Analytics" back into "Airpay Advanced Analytics" in the top tree.

## W2-07 — non-mutating gates against UAT

**Not run by Claude.** `render-smoke` and `a11y-smoke` log in by filling the real login form with
persona passwords, which Claude does not enter. Nitin to run (credentials from the vault, never
committed):

```powershell
cd "D:\Claude Local\airpay-ld-os\tests\playwright"
$env:PLAYWRIGHT_BASE_URL = "https://academy2.airpay.ninja"
# set PLAYWRIGHT_ADMIN_USER/_PASS and PLAYWRIGHT_LEARNER_USER/_PASS etc. from the vault
npx playwright test render-smoke.spec.ts a11y-smoke.spec.ts --project=chromium
```

## W2-08 — TLS posture (settled without a third-party scan)

Run locally with OpenSSL 3.5.5 at `SECLEVEL=0`, so the client *does* offer legacy protocols. The
2026-09-03 probe was inconclusive only because the client refused to offer TLS 1.0/1.1.

| Check | Result |
|---|---|
| TLS 1.0 / 1.1 | **refused by the server** (`tlsv1 alert protocol version`, received from the peer) |
| TLS 1.2 | accepted, ECDHE-RSA-AES128/256-GCM only — CBC, 3DES, RC4 and non-forward-secret suites all refused |
| TLS 1.3 | accepted (TLS_AES_128_GCM_SHA256) |
| Certificate | `*.airpay.ninja`, Amazon RSA 2048 M04, full chain, verify OK, **expires 2026-12-12** |
| Headers | HSTS 1 year `includeSubDomains`; X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy present; cookie `secure; HttpOnly; SameSite=Lax`; HTTP→HTTPS 301 |
| Gaps | no `Content-Security-Policy`; HSTS not `preload`; confirm with IT that the ACM certificate auto-renews |

Equivalent to an SSL Labs A. Nothing was submitted to Qualys.

## New defects found during this pass (not in the plan)

| # | Severity | Defect | Evidence | Fixed by pending deploy? |
|---|---|---|---|---|
| N1 | **High** | **Any logged-in user can read any user's full profile across tenants.** `local/sentientia_users/profile.php` checks only `require_login()`; core `/user/profile.php` redirects to it. | As **Priya (learner)**: Fatma (ZEEA), Juma (ZEEA) and Deepa (Public) profiles all render with email, job title, points, rank, badges, skills | **No** |
| N2 | High | **Four Sentientia capabilities are checked but never declared**, and Moodle's `has_capability()` returns false for an undeclared capability *before* the site-admin check (`lib/accesslib.php:457`). Bare checks therefore refuse everyone, admins included. | Evaluation response list refuses Meera: "…permissions to do that ([[sentientia_evaluation:view]])". Also `sentientia_classroom:enrol` (bulk enrol by audience, 10 sites), `sentientia_exams:update` (edit controls never shown), `sentientia_live:manage` (should be `:manage_all`) | **No** |
| N3 | Medium | Ten more capabilities are **retired BizLMS names** (`local/courses:manage` ×12 sites, `local/courses:enrol`, `local/classroom:*`, `local/costcenter:*`). They exist on the old production stack and vanish at the 5.2 cutover. | capability sweep, 967 declarations vs 155 checks | Analytics only |
| N4 | Medium | Analytics drill-downs refuse the admin the dashboard admits (only `index.php` had the role-id-9 fallback). | Meera opens the dashboard; `drilldown.php?type=course&courseid=3` → refused | Yes (`b1ae4bd64`) |
| N5 | Medium | Refusals render the raw identifier **`error/nopermission`** — the key does not exist (core has only plural `nopermissions`). 11 throw sites in 7 files, including the new analytics code. | drill-down refusal page | **No** — the analytics commit reuses the broken key |
| N6 | Low | Analytics At-Risk table header prints the raw placeholder **`{$a->firstname} {$a->lastname}`**. | analytics dashboard | No |
| N7 | Low | Compliance Manager Report mixes units: "Team Items 3, Completed 2, Overdue 3, Rate 22%" (22% = 2 of 9 assignments; "3" is people). | Manager Report tab | Fixed in code, verified on local data: 23 managers, 0 invariant violations. On-screen check in the after-pass |

## Deploy risk carried into Window A

Meera reaches analytics today **only** through the hardcoded role-id-9 fallback, which `b1ae4bd64`
removes. If UAT's *Administrator* role does not carry the `manager` archetype (and does not hold
`local/sentientia_courses:manage`), the deploy would lock the demo admin out of analytics. Check the
role's archetype and the new capability grants on the box, before and after `upgrade.php`.

## Caught before the deploy

The Wave 2 checklist workflow (six read-only derivers plus a completeness critic, run over
`c74bd55be..HEAD`) found four defects **in the commits waiting to be deployed**. Each was fixed and
verified on local data before Window A:

| Defect | Visible effect if shipped | Source |
|---|---|---|
| `leaderboard::get_rank()` overwrote `$params` | whole gamification row gone from every learner dashboard and profile | `86bb0c26f` |
| `homepage.php` still bound the removed `$publicpath` | public homepage "0+ Learners", Featured Courses gone | `86bb0c26f` |
| assistant privacy provider replaced by a generated one | Anthropic external-location declaration dropped | `951b20982` |
| `:viewallorgs` defaulted to the manager archetype | every tenant admin reads every tenant's analytics | `b1ae4bd64` |

All four are mine. None was caught by a test: each failure was swallowed by a `catch`, or was a
policy error rather than a code error.

## Found offline while the VPN was down (2026-09-24, afternoon)

While finishing W2-02 I found more erasure defects, and the pre-deploy adversarial review (six
reviewers) found one blocker. The blocker was fixed before any deploy.

| # | Severity | Defect | Status |
|---|---|---|---|
| N8 | **High** | The right-to-erasure flow erased a hand-kept list of 8 tables and reported `completed`. Every other Sentientia table about the person survived: the WhatsApp send log (holding mobile numbers), cart credits, calendar tokens, the agent audit, evaluation assignments. | Fixed: Step 0 calls every `local_sentientia_*` privacy provider, and a provider that fails gives `partial` |
| N9 | **High** | **Approve on the DPDP admin panel always fataled.** `process_deletion($reqid)` omitted the required `$adminid`. The signature has required it since `e02af7b2d` (2026-04-10) and the one-argument call dates from `9eef91b10` (2026-04-13), so every deployment of this code since then, **production included if it runs it**, cannot approve an erasure from the UI. | Fixed: passes `$USER->id`; only pending requests can be acted on; partial outcomes are shown |
| N10 | Medium | Panel counted "Rejected" as total − pending − completed, so every incomplete erasure was shown to the DPO as rejected. | Fixed: explicit counts plus an "Incomplete" tile |
| N11 | Medium | WhatsApp provider reported only users with a saved preference. A user with send-log rows alone was never erased, by core's flow or ours. | Fixed |
| N12 | **High (blocker)** | **Evaluation provider `delete_data_for_user()` never set `$userid`**, so it deleted `WHERE userid IS NULL`. Because `assigned_by_userid` is nullable, the anonymise step would have rewritten other people's system-assigned rows, and Step 0 would have triggered that on UAT. `get_contexts_for_userid()` also ignored assign-only users. Introduced by my `951b20982`. | Fixed in both trees; `tests/privacy_provider_test.php` asserts that the other user's rows survive untouched |

The audit of all 38 providers finished: 15 findings confirmed, all fixed in `b2a8dee40`.

| # | Severity | Defect | Status |
|---|---|---|---|
| N13 | **High** | **Compliance report configuration was open to every viewer.** A line manager with one direct report could POST `action=exclude` for any user in any tenant, deactivate a mandatory course site-wide, or open `?tab=config` to see every tenant's excluded users with their emails. The UI only ever showed the tab to site admins. Found by the N5 review. | Fixed: site-admin only on the server as well |
| N14 | **High** | **Line managers saw their whole tenant's compliance**, not their team. | Fixed per the product rule: direct reports plus extended teams (`viewer_scope`) |
| N15 | Medium | A non-admin whose `open_path` is empty resolved to `''`, which the report and the export read as the whole site. | Fixed: refused |
