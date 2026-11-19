# ADR-043 — Survey reach: external form links, public no-login links and file uploads in `local_sentientia_evaluation`

- **Status:** Proposed 2026-11-19. Nitin's answers D23, D24 and D25 (post-demo plan §6a) are final and frame this ADR. The choices below follow the recommended answers to SV1 to SV24 in the surveys scope, which still await his choice (SV20 asks that they be recorded here before session 11b). Becomes Accepted when Nitin approves or amends them.
- **Date:** 2026-11-19
- **Decision-makers:** Nitin Rajput (Head of L&D, Airpay Payment Services). The grievance contact (SV21) and the ZEEA legal review (SV22) need Airpay's DPO and legal team.
- **Implementer:** Claude
- **Amends:** nothing. No core file changes (ADR-033 decision 4): reCAPTCHA v2, the antivirus manager, the lock API, `\core\encryption`, `core_qrcode` and file redaction are stock Moodle 5.3.
- **Related:** ADR-002 (flags), ADR-030 (signature header reused in D3), ADR-031 (tenant scope), ADR-035 (privacy erasure), ADR-036 (reserved, not written: the form binding and Training Program stages this ADR builds on).
- **Evidence:** `docs/plans/2026-11-19-SURVEYS-SCOPE.md` (section numbers below refer to it unless marked "plan"); `docs/plans/2026-11-19-POST-DEMO-PLAN.md` §4F, §5.1, §5.3, §6a; code in `local/sentientia_evaluation/` (version 2026111901, working tree on 19 Nov).

---

## Context

**What Nitin decided (plan §6a, final).** D23: extend our evaluation plugin **and** allow linking external forms
(Microsoft Forms or other tools). D24: public no-login links and file uploads **in v1**, scoped before any build.
D25: trainers see aggregates for their own sessions only, minimum 5 responses; new-response notices go to the form
creator and tenant L&D admins.

**What the plugin is today.**
- Login everywhere: `respond.php` calls `require_login()` and requires `:respond` in the system context. Two
  capabilities only (`db/access.php`): `:manage` (manager archetype) and `:respond`. Nothing for trainers.
- `lib.php` is a three-line stub, so the plugin cannot serve a file (E5). Response notices go to every site admin
  (`evaluation_manager::notify_admins_of_response()` calls `get_admins()`, E3).
- Course, program and classroom ids on a response came from the browser unchecked (E1). Version 2026111901 adds
  `evaluation_manager::clean_response_context()`, which refuses an id the user is not tied to, and invitations carry
  their context (E2). Both were uncommitted when this ADR was written.
- Flags are read for the evaluation's tenant, not the viewer's (`evaluation_manager::flag_scope()`). A visitor with no
  tenant needs exactly that.
- `identity_protected()` treats a form as anonymous when the form or a question is anonymous **or any response has
  user 0**; the drilldown pages then show totals only.

**Facts outside the code (scope §3, §5, §8).** Microsoft Graph has no generally available endpoint for Forms
responses (Power Automate is the supported route); Forms cannot redirect after submit and allows file questions only
on organisation-only forms. Neither Moodle 5.3 nor `local_sentientia_platform` has a rate limiter (E6). The privacy
hub serves logged-in users only (E7). ClamAV on UAT and the new server: not found. reCAPTCHA keys on UAT: not verified.

**Why an ADR.** The scope adds to one plugin an outbound redirect to third-party tools, an inbound callback without
login, a public page without login that stores free text, personal data of people with no Moodle account, and
uploaded files. Each crosses a line that CLAUDE.md §12 (employee data leaving the platform), ADR-031 or ADR-035
governs.

---

## Decision

### D1 — One engine, three reaches, each behind its own flag

- Native forms in `local_sentientia_evaluation` stay the engine (plan D23), not Moodle's Feedback module or
  Questionnaire (neither is tenant-aware).
- Three additive reaches: external forms (D2, D3), public links (D4 to D6), a file question (D7). New columns default
  to today's behaviour; scope §6.1 lists the tables.
- Flags, all default OFF, read through `flag_scope()` for the **form's** tenant: `sentientia.evaluation.external_links`,
  `.external_callback`, `.public_links`, `.public_identified`, `.file_upload`, and `.public_file_upload` only if
  SV10 = b.
- Not built: reading Forms through Graph, a Forms-style quiz mode (SV14), public "share summary" links (SV18).

### D2 — External forms: we own the wrapper, the tool owns the answers

- A form gets `source` = `native` | `external`. An external form keeps binding (ADR-036), assignment, invitations,
  reminders, non-respondents and reports, and has no questions of its own.
- `launch.php` (login required) checks the binding with the E1 rules, records an `_extlaunch` row with a fresh
  32-byte random token (stored only as a SHA-256 hash), shows the per-customer privacy notice on first launch (en +
  hi), then redirects to **the URL stored on the form** with `{token}` replaced. Never a URL from the request: no
  open redirect.
- URL rules, checked on save and on every launch: `https` only; host on a site-admin allowlist (SV1 default:
  `forms.office.com`, `forms.microsoft.com`, `docs.google.com/forms`, `forms.gle`, `*.typeform.com`). The token is
  the only value we add, never a name, e-mail or employee id.
- **Added here, not in the scope:** an external form marked anonymous gets no token, and its completion is
  self-attest only. Otherwise the tool's export and our launch table together put a name on every answer.
- New tab, no embedding in v1 (SV4): an organisation-only Form inside our page depends on the user's Microsoft 365
  sign-in and third-party cookies.

### D3 — How we learn that an external form was submitted

- Every external form: self-attest ("I have submitted it") plus launch tracking, labelled "self-reported".
- Optional per form, behind `external_callback`: a signed callback from Power Automate, a Typeform webhook or a Google
  automation. One secret per form, encrypted with `\core\encryption` (as `local_sentientia_m365\msal_client` stores
  tokens). HMAC-SHA256 in ADR-030's header format (`X-Sentientia-Signature: t=<unix>,v1=<hex>`) so one setup guide
  covers both directions; 5-minute window. The body carries the token, no personal data; the token is single-use
  and tied to its launch, which defeats replays. A valid call marks the assignment "verified".
- A return link with the token is accepted for Typeform only (Forms cannot redirect); it records "returned", below
  verified. No Graph polling, no unofficial `formapi`, no reading of the Forms Excel workbook.
- Completion (SV3): a self-attested form counts toward a feedback stage, labelled; a stage can require "verified
  only" (ADR-036).

### D4 — Public links: capability-held, expiring, capped tokens

- A 32-byte random token in the path (`/local/sentientia_evaluation/p.php/<token>`), stored as a SHA-256 hash plus a
  `\core\encryption` copy so an admin can copy the link or QR code (`core_qrcode`) again. Each link: one native form,
  optionally one binding, a mode (anonymous / identified), open and expiry dates (default 30 days, maximum 180), a
  response cap (default 500), a status (active / paused / revoked), creator and tenant path. External forms are
  shared with the tool's own link, not ours.
- Created only by holders of the new `:managepubliclinks` capability (SV5): manager archetype plus Airpay's real L&D
  admin role, back-filled in `db/upgrade.php` (the T-01 lesson). Never trainers. Managing a link goes through
  `can_manage_evaluation()` (ADR-031).
- ZEEA (tenant 177) stays OFF until Airpay legal has reviewed Tanzanian law (SV22). Needs F3 first (session 25):
  the page reuses the native renderer with the server-side branch check.
- The page: no login, login layout, `Cache-Control: no-store`. A signed form nonce (link id, issue time, random
  value, site secret) replaces sesskey: it stops cross-site posts and carries the start time.
- Responses go to the existing table with user 0, a new `publinkid` and a `status` (ok / quarantined / spam).
  Under today's `identity_protected()` any user-0 row protects the whole form. Right for anonymous links; session 29b
  decides whether identified-link rows are left out of that test.

### D5 — Abuse controls fail closed

- **reCAPTCHA v2** (core form element): a link cannot be activated while the site keys are empty (the check
  `local_sentientia_users\form\signup_form::recaptcha_configured()` makes). **Honeypot** plus at least 3 seconds
  from page load to submit.
- **Rate limiter (new, E6):** counters in an application cache (`ratelimit`, `db/caches.php`) with a DB fallback.
  Defaults 5 submissions per IP per link per hour, 300 per link per hour, 20 page views per IP per minute. The IP is
  used only as a salted hash (SV8). The class knows nothing about evaluations, so it can move to the platform later.
- **Response cap** under a per-link lock (`\core\lock\lock_config::get_lock_factory()`) around the count and the
  insert. A transaction alone (scope §4.2) does not stop two simultaneous submissions from both reading 499.
- **Repeats:** anonymous links show a soft "already answered on this device" notice; identified links take one
  response per e-mail per link. Open text is flagged for review, never blocked, by
  `local_sentientia_live\profanity_filter` when that plugin is installed.
- **Moderation** (new `:moderate` capability): pause or revoke a link, quarantine a response or a time window,
  delete quarantined responses. Quarantined responses never count in results, exports or completion. Each action
  raises an event added to the `local_sentientia_platform\audit_log` whitelist.

### D6 — People without accounts: anonymous by default, erasable by e-mail, purged on a timer

- **Anonymous** is the default (SV6): no name, no e-mail, only the IP hash.
- **Identified** (`public_identified`): name and e-mail with an unticked consent box; consent text version and time go
  into `_pubresp`. The e-mail format is checked, not verified, and nothing is ever e-mailed to a respondent, so the
  form cannot send mail to someone else's address. Parental consent for children is out of scope for v1.
- **Notice** before the questions, per customer, en + hi: who collects, why, what (including the IP hash and the
  reCAPTCHA data sent to Google), how long, how to ask for erasure. Legal entity and grievance contact are
  per-customer settings, not language strings (SV21). DPDP notice duties start about 13 May 2027 (scope §3.4).
- **Erasure:** a person with no account cannot use `tool_dataprivacy` or the privacy hub, so the plugin gets an admin
  screen "Find respondent by e-mail" (export or erase), for `:moderate` holders in the form's tenant, audited.
- **Retention:** a daily task clears e-mail, name and IP hash after 180 days (SV9); answers stay, anonymised.
- **Privacy provider:** declares the new tables and two external locations, the external form tool (receives the
  token, holds what the learner types there) and Google reCAPTCHA. Logged-in users' launches and files stay on the
  stock export and delete path that ADR-035 completes.

### D7 — Files: logged-in respondents only in v1, antivirus required

- New question type `fileupload` (in the `settings` JSON column from session 24). Defaults (SV11): 3 files, 10 MB each
  (maximum 25 MB); documents, PDF and images only; no audio, video, archives or executables.
- Core file manager and draft area; core scans the upload (`repository/upload/lib.php` calls
  `\core\antivirus\manager::scan_file()`). Files live in the plugin's `response_file` area, item id = response id.
- **Fail closed** (SV12): a file question cannot be added unless an antivirus plugin is enabled and set to treat a
  scanner failure as a virus; an audited site-admin override exists for local development. IT ask: ClamAV over the
  Unix socket on UAT and the new server.
- New `pluginfile` callback (E5): `:viewfiles` holders in the form's tenant, and respondents for their own files.
  Never trainers. No file questions on anonymous forms (SV13): a file can identify its author.
- Moodle 5.3 file redaction (photo metadata) on; session 28 checks it fires for this area. Retention 365 days by
  default, 30 to 730 per form (SV9); deletes cascade from response, form and user.
- Public uploads (SV10 = b) are not in the first release: Forms itself allows them only on organisation-only forms,
  and no scanner is installed. If chosen later: identified mode only, own endpoint (nonce, rate limit, scan, 24-hour
  pending area), behind `public_file_upload`.

### D8 — Results: aggregates for trainers, the threshold applies to every slice

- New capability `:viewownresults` for Airpay's real trainer role, back-filled (sessions 11 and 26).
- A trainer sees aggregates (counts, averages, distributions, NPS) only for forms bound to them as subject or to
  their own classrooms and sessions, and only when the slice on screen holds at least 5 responses; each filter
  (course, classroom, dates) is checked again. Below 5: "not enough responses yet".
- Trainers never see individual responses, open-text answers, files or public respondents' details.
- New-response notices go to the form's creator and the tenant L&D admins of the form's tenant (E3, session 11).
  Results stay behind login (SV18).

### D9 — Build order and hard gates

Order as plan §5.1: 2 → 11 → 11b → 24 → 25 → 26 → 28 → 29a → 29b → 30 (11b may run beside 24, 26 beside 25).

| Session | Cannot start before |
|---|---|
| 11b External links | Session 11 (binding), this ADR accepted, SV1 to SV4 |
| 28 File question | Session 24, SV10 to SV13, ClamAV on UAT (IT) |
| 29a Public links, anonymous | Sessions 11 and 25, SV5, SV7, SV8, SV22, reCAPTCHA keys |
| 29b Public links, identified | 29a, 28, SV6, SV9, SV21 |

Each session keeps both plugin trees byte-identical, ships en + hi strings (SV17) and the tests in scope §6.4.

---

## Consequences

**Positive**
- Teams on Microsoft Forms keep it, and L&D still sees who was asked, who answered (self-reported or verified) and
  who did not, in the same reports.
- Feedback from people without accounts (walk-in candidates, external participants) without creating Moodle users.
- One engine; every reach switches per customer and tenant.

**Negative / accepted**
- Self-attest is low trust. Verified completion for Forms needs a Power Automate flow per form, possibly on a
  premium licence.
- The plugin takes on a write path without login, its abuse controls, a rate limiter, and an e-mail-keyed personal
  data store with its own erasure and retention: about 11.5 to 13.5 days on top of F0 to F4.
- reCAPTCHA sends visitor IP and browser data to Google; its audio challenge is weak for accessibility.
- Uploads grow the file store that Stage B and cutover must carry.
- Callback secrets and stored link tokens depend on the site encryption key (under `dataroot/secret` unless
  `$CFG->secretdataroot` is set); a server move without it means re-issuing them. `local_sentientia_m365` already
  has this dependency.

**Neutral**
- All flags default OFF: until one is switched on, the plugin behaves as today.

---

## Alternatives considered

- **Moodle Feedback or Questionnaire:** not tenant-aware. Rejected (plan §4F).
- **Reading Forms responses** through Graph (no GA endpoint), the unofficial `formapi` (can change without notice)
  or the Excel workbook (refreshes only when an owner opens it). Rejected until Microsoft ships a GA Forms API.
- **Sending name, e-mail or employee id in a pre-filled link** so the tool records who answered: employee data to an
  outside service (CLAUDE.md §12). Rejected; the admin declares whether the external form asks for identity itself
  (`external_records_identity`) and the notice says so.
- **Public links via the guest account or auto-created accounts:** guest is one shared user with no tenant, so flags
  and scope resolve for nobody; auto-created accounts inflate user counts and need verified e-mail and SMTP. Rejected.
- **E-mail verification for identified respondents** (SV6 b): needs SMTP and sends mail to any address a visitor
  types. Rejected for v1.
- **Clear IP addresses for admins** (SV8 c): rejected; salted hash only.
- **The rate limiter in `local_sentientia_platform` now:** one consumer today, and a platform change raises every
  plugin's dependency floor. Deferred (open question 9).

---

## Implementation actions

1. E1 and E2: in version 2026111901 (working tree, 19 Nov); commit with their tests.
2. Session 11: binding (ADR-036), E3 notices, `:viewownresults` for the F1 part. Then 11b, 28, 29a, 29b and 30 as
   in D9, each with its state card, screenshots and PROJECT-STATE entry.
3. IT asks this week (plan §5.4): ClamAV on UAT and the new server; reCAPTCHA v2 keys on UAT and production.

---

## References

- `docs/plans/2026-11-19-SURVEYS-SCOPE.md` (§1 E1 to E7, §3 to §6, §7 SV1 to SV24, §9 sources);
  `docs/plans/2026-11-19-POST-DEMO-PLAN.md` (§4F, §5.1, §5.3, §6a).
- `local/sentientia_evaluation/` (`respond.php`, `lib.php`, `db/access.php`, `db/feature_flags.php`,
  `classes/evaluation_manager.php`, `classes/privacy/provider.php`); `local/sentientia_live/audience/join.php`,
  `local/sentientia_live/classes/profanity_filter.php`, `local/sentientia_users/classes/form/signup_form.php`,
  `local/sentientia_m365/classes/msal_client.php`, `local/sentientia_platform/classes/audit_log.php`.
- Moodle 5.3 (`D:\Claude Local\moodle53\moodle\public\`): `repository/upload/lib.php`, `lib/classes/lock/`,
  `lib/classes/encryption.php`, `lib/moodlelib.php` (`getremoteaddr()`), `files/classes/redactor/`.
- ADR-002, ADR-030, ADR-031, ADR-033, ADR-035; ADR-036 (reserved).

---

## Open questions for future ADRs

1. SV1 to SV24: Nitin's approval or changes.
2. IT and business: reCAPTCHA keys; ClamAV; the Power Automate licence for the HTTP action; Airpay's Forms tenant
   settings (external sharing); a Typeform plan or Google Cloud project only if those tools are used.
3. Client IP behind a proxy. UAT is served over https while its Apache listens on port 80 only, so something in
   front may terminate TLS. If `getremoteaddr()` then returns the proxy's address, per-IP limits throttle every
   visitor together. Check `$CFG->getremoteaddrconf` and `$CFG->reverseproxyignore` on UAT and the new server
   before 29a.
4. Does `p.php` run with `NO_MOODLE_COOKIES` (no session row per visitor)? Then moodleform's sesskey cannot be used
   and the form is built around the nonce and the core reCAPTCHA check. Decide in 29a with a test.
5. Trainers and open-text answers (D8): hidden in v1; show them later, after review?
6. Anonymous external forms without a token (D2): confirm.
7. ZEEA (SV22): is the default-OFF flag enough, or is a code guard wanted until the legal review?
8. Under-18 respondents and parental consent under the DPDP Act, after v1.
9. Move the rate limiter to `local_sentientia_platform` when a second consumer appears (candidates: the anonymous
   join in `local_sentientia_live`, the public certificate verification page).
