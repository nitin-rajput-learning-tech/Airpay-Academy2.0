# State Card — `local_airpay_pages`

**Component:** `local_airpay_pages`
**Status:** Live on airpay.academy — collection of standalone PHP pages
**Maturity:** Mixed (per-page, no global version.php)
**Version:** 2026052900 / 1.1
**Last refreshed:** 2026-05-29 (dark-mode — `templates/certificate_celebration.mustache` "paper" card name/meta text re-pinned dark so the theme's global dark-mode token flip doesn't invert it to light-on-white)

---

## 2026-05-29 — C10 P1 / Gap 3: tenant-scoped certificate template browser

- New `certificate_templates.php` — tenant-aware browser over the
  vendored `tool_certificate` templates (READ-ONLY). Filters by a
  JSON map (`cert_template_tenant_map` admin setting): non-siteadmin
  tenant admins see only global + their tenant's templates; siteadmins
  see all with an assigned-tenant column.
- New `db/feature_flags.php` — `sentientia.certificate.tenant_scope.enabled`
  (default OFF = today's behaviour, all admins see all templates).
- New `settings.php` — the JSON map textarea + an admin_externalpage
  link to the browser. (First time this plugin has had settings.php.)
- 19 new lang strings (cert_* keys).
- Version bumped 2026040400 → 2026052900, release 1.0 → 1.1.
- Zero mutation of the vendored tool_certificate plugin.
- Audit ref: `docs/audits/C10-CERTIFICATE-STACK-INVESTIGATION-2026-05-28.md` Gap 3.

(Sibling Gap 4 — tool_certificate Hindi pack — is STAGED for review at
`docs/translations/tool_certificate-hi-DRAFT.php`, not part of this
plugin.)

---

## Mission

Catch-all bucket for one-off / single-page features that don't justify
their own plugin shell. Each `.php` file at the top level is a
self-contained entry point.

Today's surfaces:
- `homepage.php` — site landing page (Airpay-branded)
- `onboarding.php` — new-employee onboarding journey
- `qr_attendance.php` — QR-code attendance scan-in flow
- `certificates.php` — certificate gallery
- `index.php` — pages directory

## DB tables

None.

## Capabilities

None declared (no `db/access.php`). Surfaces gate on login + the
referenced upstream plugin caps.

## Feature flags

None registered.

## Key files

```
local/airpay_pages/
├── README.md
├── version.php                                   ✓ added (F-091 back-port 2026-05-28)
├── index.php                                     Pages directory
├── homepage.php                                  Site landing
├── onboarding.php                                New-employee journey (tenant-scoped F-008 2026-05-28)
├── qr_attendance.php                             QR attendance scan
├── qr_scan.php                                   QR redirect handler
├── certificates.php                              Certificate gallery
├── cli/                                          ✓ back-ported 11 setup/seed scripts (F-091 2026-05-28)
│   ├── setup_costcenters.php
│   ├── setup_bizlms_data.php
│   ├── setup_policies.php
│   ├── seed_users.php
│   ├── seed_testdata.php
│   ├── seed_production_data.php
│   ├── fix_bizlms_columns.php
│   ├── fix_all_bizlms_data.php
│   ├── fix_manager_role.php
│   ├── create_hrbp_role.php
│   └── enable_completion.php
├── pages/                                        Static HTML (privacy, terms, help, contact, dpdp)
├── templates/                                    Mustache templates
└── lang/                                         (en + hi + kn + mr + sw — 5 locales)
```

**Status update 2026-05-28:** the plugin DOES have `version.php` (back-ported
from xampp during F-091 fix). The "no version.php" claim above was stale.
It IS a formally installed Moodle local plugin.

## Tests

None — each page is exercised manually + the upstream plugin's
PHPUnit suite covers the underlying queries.

## Stabilization notes

- F-091 (workspace drift) — RESOLVED 2026-05-28 by back-porting 17 files
  (version.php, EN lang pack, 11 CLI scripts, 3 HTML pages, qr_scan.php)
  from deployed (commit `e32473e58`).
- F-074 (state-card stale) — RESOLVED 2026-05-28 by this refresh (B19).
- Tenant leak in onboarding.php — RESOLVED 2026-05-28 (commit `db5242c9a`).

## Open items

- [ ] Hindi/locale parity audit for the 11 CLI scripts (they emit
      cli_writeln messages — mostly admin-facing, but still)
- [ ] Mobile responsiveness per page (Phase 6B follow-on)
- [ ] Visual evidence snapshot for the 4 active pages
- [ ] `qr_attendance.php` integration with `local_airpay_classroom`
      attendance writer
- [ ] Add a `MATURITY_BETA` stamp in `version.php` (currently
      back-ported as-is from xampp)

## State card created — 2026-05-24

Initial state card. This plugin is unusual — no `version.php`, so it's
deployed-but-not-installed. Created now as part of the P1 state-card
pass to surface that ambiguity for future cleanup decisions.

## 2026-09-22 - Tenant path-boundary sweep (platform-wide)

A repo-wide scan for unbounded tenant/org path prefixes found this plugin among them. A materialised
path prefix must be `/`-terminated AND match the node itself; `'/1' . '%'` also matches `/177`, so an
Airpay-scoped query silently included the ZEEA tenant. The same defect had already shipped four times
(admin dashboard, compliance BU filter, department scorecard, org-children picker) and is invisible in
use: nothing errors, only the numbers come out wrong.

Public homepage hero stats were unbounded and would also have dropped courses at the Public root.

Fixed via the new `\local_sentientia_platform	enant::path_descendant_filter()` (exact-or-descendant
for an arbitrary path), locked by a DB-level boundary suite in `tenant_test.php`, and prevented from
returning by `tools/check-path-boundary.php` - pre-commit CHECK 18 and the `path-boundary-check` CI job.

## 2026-09-24 - Regression in 86bb0c26f, caught before it reached UAT

`homepage.php` replaced `$publicpath` with a bounded `$publicsql` for the course count but left the
learner count and the Featured Courses query still binding `$publicpath`. PHP read the undefined variable
as NULL, `LIKE NULL` matched nothing, and the public homepage showed **"0+ Learners"** with the **Featured
Courses section gone**. Each query now gets its own bounded fragment with a distinct parameter tag
(`pubu`, `pubc`).

Verified on local data with the page's exact queries: 672 public learners and 6 featured courses (the
broken query returns 0). A sweep of every file 86bb0c26f touched found no other variable whose assignment
was removed while a use remained.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.


## 2026-09-24 - Wave 2 N5: two refusals rendered as raw identifiers

- `qr_attendance.php` refused with `moodle_exception('nopermission')` - not a core key (core has
  only the plural `nopermissions`), so the user saw `error/nopermission`. Now core
  `nopermissiontoaccesspage` ("You don't have permission to access this page."). A plain core
  string rather than `required_capability_exception` because the capability the page checks is
  undeclared (below), and rather than a new plugin string because this plugin has no Hindi pack
  yet and core's string is already translated.
- `index.php` unknown `?page=` threw `moodle_exception('invalidpage', 'error')` - also not a core
  key, shown as `error/invalidpage`. Now core `invalidaccess`.

**Open - capability undeclared where BizLMS is absent.** `qr_attendance.php` gates on
`local/classroom:takesessionattendance`, the pre-ADR-025 BizLMS name. BizLMS `local_classroom`
declares it (`db/access.php`, `CONTEXT_COURSECAT`, no archetype defaults), so on a site that also
runs BizLMS, as the current airpay.academy stack does, it passes for whoever was explicitly granted
it at system context. Sentientia does not ship `local_classroom`, so on UAT and on a fresh
Sentientia install it is undeclared: `has_capability()` answers false with a debugging notice and
**only site admins can display the attendance QR** - trainers cannot. The likely successor is
`local/sentientia_classroom:attendance` (archetypes manager + editingteacher). Choosing it is an
access decision for its own change; recorded in the code at the check. (The first version of this
note said "no shipped plugin declares it"; corrected in the review pass, because the decision
should not be made on that premise.)

Version 2026092400. Guarded platform-wide by
`local_sentientia_platform/tests/exception_strings_test.php`.

## 2026-09-30 - ADR-032 Phase 0 source freezing

`cli/setup_costcenters.php`, `setup_bizlms_data.php` and `fix_all_bizlms_data.php` refuse to run (exit 3) on a
database that holds any known BizLMS table (`local_sentientia_platform\bizlms\legacy_tables::holds_bizlms()`),
because they insert into `local_costcenter` and rewrite user and course rows, and those tables are now the
read-only archive of the import. `seed_production_data.php` and `fix_bizlms_columns.php` also write BizLMS
tables but were not in the ADR's list and are unchanged. The QR pages (`qr_scan.php`, `qr_attendance.php`)
still use `local_classroom_attendance`; moving them off the legacy tables is on another branch. Version
unchanged; both trees identical.

## 2026-09-30 - QR pages moved off the retired BizLMS tables

- `qr_scan.php` checked and inserted in `{local_classroom_attendance}`; `qr_attendance.php` read
  `{local_classroom_sessions}` / `{local_classroom}`. A fresh Sentientia install (UAT) has none of
  them, and after the BizLMS import (ADR-032, in design) the history lives in the Sentientia tables.
- `qr_scan.php` now calls `\local_sentientia_classroom\session_manager::record_qr_attendance()`
  (see the classroom state card for the checks and the row it writes). It keeps the login and the
  hourly rotating token check. New refusals it can show: "Session Not Found", "Not Enrolled", and
  a different-organisation message (ADR-031). A duplicate scan shows "Already Marked" and writes
  nothing. If `local_sentientia_classroom` is not installed the page says attendance is not
  available instead of fataling.
- `qr_attendance.php` gets the session and its classroom from
  `session_manager::require_session_access()` (Sentientia tables plus the ADR-031 tenant guard). An
  unknown session gives core `invalidaccess`, because a QR for it could never record anything. The
  heading now reads "classroom name - session title", and is no longer double-escaped
  (`s(format_string())`).
- **Still open, unchanged:** the capability `qr_attendance.php` checks is the pre-ADR-025
  `local/classroom:takesessionattendance` (see the 2026-09-24 note). Only site admins can display
  the QR on a Sentientia-only install.
- No version bump. Both trees. Test: `local_sentientia_classroom/tests/qr_attendance_test.php`.

## 2026-09-30 (review follow-up) - QR pages: "Classroom Cancelled" state, QR image on Moodle 5

- `qr_scan.php` shows a new refusal, "Classroom Cancelled", for `SCAN_CANCELLED` (see the classroom
  state card). The page's result states are now: Attendance Marked, Already Marked, Not Enrolled,
  Classroom Cancelled, Session Not Found, QR Code Expired, and the different-organisation error.
- **`qr_attendance.php` could not show a QR on Moodle 5.** It did
  `require_once($CFG->libdir . '/phpqrcode/qrlib.php')`; Moodle 5.x does not ship `lib/phpqrcode`
  (checked on the local 5.1.3 tree), so on a Sentientia install the page stopped with a missing-file
  error before it showed anything. Found while taking the screenshots for this change. It now uses
  `core_qrcode` (TCPDF's 2D barcode, in Moodle core since 3.9, so also on the 4.1 BizLMS box), at
  error-correction level L and 8 px per module. When PHP has neither GD nor Imagick the page says the
  QR could not be generated instead of showing a broken image.
- The new wording on these pages is still hard-coded English, like the rest of both pages. It moves to
  lang strings, with the Hindi pack, when this plugin gets one.
- Visual evidence: `docs/visual-evidence/2026-09-30/qr-and-loginas/` (README there).
- **Still open, unchanged:** the capability `qr_attendance.php` checks is the pre-ADR-025
  `local/classroom:takesessionattendance`, so only site admins can display the QR on a Sentientia-only
  install (2026-09-24 note). Take that access decision before the trainer persona is tested, or the
  trainer persona cannot use the feature. (Taken in the second review, below.)
- No version bump. Both trees.

## 2026-09-30 (second review) - QR pages: signed token, new refusals, lang/en + lang/hi, real capability

Owner decisions taken as recommended (Nitin, 2026-09-30). No version bump. Both trees. The
token, window and "trainer's mark wins" rules live in `local_sentientia_classroom`
(`session_manager`, see its state card); this card is about what the two pages do with them.

- **Token.** Neither page reads `$CFG->passwordsaltmain` any more. `qr_attendance.php` shows
  `session_manager::qr_token($sessionid, time())`; `qr_scan.php` checks
  `session_manager::qr_token_is_valid()` (HMAC with a per-site secret, current and previous hour).
  The old salt-free sha256 token is refused as "QR Code Expired".
- **Capability.** `qr_attendance.php` now requires `local/sentientia_classroom:attendance` (the
  capability `attendance.php` and the bulk-mark web service use; manager, editingteacher and the
  `administrator` role hold it; site admins always pass), then applies the ADR-031 tenant check in
  `require_session_access()` as before. The undeclared BizLMS `local/classroom:takesessionattendance`
  check is gone, together with the code comment that explained why the refusal was a plain string:
  a declared capability gives the normal "no permission" page. The classroom plugin is checked
  first, because it declares the capability. **Still open:** the `trainer` role (archetype teacher)
  holds only `local/sentientia_classroom:manage`, so a user with only that role still cannot open
  this page; see the classroom state card. Nothing in the Sentientia classroom UI links to
  `qr_attendance.php` either, so trainers need the URL.
- **New result states on `qr_scan.php`.** "Attendance Not Open Yet" (before the window, shows when it
  opens), "Attendance Closed" (after it, shows when it closed; or, for a session with no time at all,
  that it has no start time), and "Already Marked" now says the attendance was already *marked* (not
  recorded) and that the scan changed nothing. Full list: Attendance Marked, Already Marked, Not
  Enrolled, Classroom Cancelled, Attendance Not Open Yet, Attendance Closed, Session Not Found, QR
  Code Expired, and the different-organisation and generic errors.
- **Lang strings.** Every visible text on both pages is now a string of this plugin, `qr_*` keys in
  `lang/en/local_sentientia_pages.php`. This plugin gets its **Hindi pack**
  (`lang/hi/local_sentientia_pages.php`, 57 keys, all the existing ones translated too, so the
  parity gate has 0 failures and this plugin no longer warns "no-hi-pack"). The kn/mr/sw packs are
  still the small footer-only stubs.
- **Not changed:** the footer and certificate-template strings' English, `version.php`.
- Visual evidence: `docs/visual-evidence/2026-09-30/qr-and-loginas/` (README there: 20 automatic
  checks, all pass, 40 screenshots; checks 01-08 re-captured, 12-20 new).


## 2026-09-30 (final review) - Hindi "already marked" wording; QR entry point lives in the classroom plugin

- **Hindi.** `lang/hi` `qr_already_title` / `qr_already_body` said the attendance was *darj* (recorded),
  the same verb the success page and every refusal use, so a Hindi-UI learner whom the trainer had
  marked Absent was told "already recorded" in the words of the success page. They now say *chihnit*
  (marked): title "पहले से चिह्नित है", body "... उपस्थिति पहले ही चिह्नित की जा चुकी है ...", which keeps the English
  distinction ("Attendance Marked!" / "recorded at" for the success, "already been marked" for the
  repeat). Both trees identical; screenshot 18 re-captured with a throwaway `vpqr_*` account.
- **No PHP change** in this plugin (the version is untouched). The link to `qr_attendance.php` is on the classroom attendance
  page, behind the default-OFF flag `sentientia.classroom.qr_attendance` registered in
  `local_sentientia_classroom` (see that state card). The QR page and the scan page are unchanged and
  are reachable by URL exactly as before whether the flag is on or off. The `trainer` role gets
  `local/sentientia_classroom:attendance` from the classroom plugin's upgrade step 2026093001, which is
  what lets a trainer open `qr_attendance.php`.

## 2026-09-30 (round 4 review) - "already marked" icon; QR page needs the assigned trainer

Owner decisions taken as recommended (Nitin, 2026-09-30). No version bump. Both trees.

- **`qr_scan.php`:** the SCAN_ALREADY box ("Already Marked") uses the `info-circle` icon instead of the
  success page's `check-circle`. A learner whom the trainer marked Absent no longer sees a tick-like cue
  at a glance (screenshot 18 re-captured; the check asserts the icon class). Title, body and Hindi wording
  are unchanged.
- **`qr_attendance.php`:** calls `session_manager::require_attendance_access()` instead of
  `require_session_access()`. After the ADR-031 tenant guard, a user WITHOUT
  `local/sentientia_classroom:manage` may show the QR only for a session they are the assigned trainer of
  (`local_sentientia_classroom_sessions.trainerid` or the classroom's `trainerid`); managers (which the
  tenant `administrator` role is) and site admins are unchanged; otherwise `error_nottrainer` (string in
  `local_sentientia_classroom`, en + hi). See the classroom state card for the rule, the data finding
  (the local `trainer` role holds `:manage`) and the tests. The scan page (the learner's side) is
  unaffected.
- Visual evidence: `docs/visual-evidence/2026-09-30/qr-and-loginas/` (README there: 28 automatic checks,
  all pass; new screens 25 to 28, 18 re-captured).
