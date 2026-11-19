# Surveys scope: Microsoft Forms parity, external form links, public links, file uploads (D23, D24)

**For:** Nitin (Head of L&D, product owner) and the Airpay CTO | **Date:** 2026-11-19 | **Status:** scope complete,
awaiting decisions SV1 to SV24 | **Plugin:** `local_sentientia_evaluation` (version 2026111800, release 1.17.5; both
plugin trees identical)

Your answers: **D23** extend our evaluation plugin **and** allow linking external forms (Microsoft Forms or other tools);
**D24** public no-login links and file uploads **in v1**, scoped before any build. This document is that scope. Nothing
was built. Effort figures are estimates.

---

## 0. Summary

- **Microsoft Forms responses cannot be read through Microsoft Graph today.** No generally available Graph endpoint was
  found. The supported route is Power Automate ("When a new response is submitted" + "Get response details"). So for an
  external form we either take the learner's word ("I have submitted it") or confirm it with a signed callback from a
  Power Automate flow, Typeform webhook or Google Forms automation.
- **Public no-login links are safe to build** with a random link token, reCAPTCHA v2, a honeypot, a signed form nonce, our
  own rate limiter, a response cap and an expiry. We must build the rate limiter (neither Moodle 5.3 nor our platform
  plugin has one). People who answer a public link are not Moodle users, so this plugin needs its own "find by email"
  erase screen and a retention purge.
- **File uploads by logged-in users** reuse core Moodle (draft area + antivirus scan). **Public uploads** need our own
  endpoint, and Microsoft Forms itself only allows uploads on organisation-only forms, so we recommend logged-in uploads
  only in the first release. **ClamAV on UAT and the new server: not found** (IT ask).
- **Existing defect found:** a learner can file feedback against any course, program or classroom, because the ids sent
  with a response are never checked on the server (E1).
- **Effort:** about **11.5 to 13.5 days** of new work in 5 new sessions, on top of the planned F0 to F4. Surveys overall
  are now about **21.5 to 27 days** (were 15 to 19).

---

## 1. What the plugin has today

| Area | Today |
|---|---|
| Forms | Name, description, Kirkpatrick level, trigger (manual, course / program completion, classroom end) with delay, tenant scope, draft / active / archived, anonymous, self or supervisor mode, open and close dates, multiple submissions, notify on response |
| Questions | Rating (fixed 1-5), NPS, yes/no, single and multiple choice, integer number, long text; required; per-question anonymity; show/hide on one earlier answer. No sections, no per-question settings |
| Responses | One row per answer set: user (0 when anonymous), subject user, course / program / classroom ids, answers as JSON |
| Distribution | Trigger queue, assignments (assigned / responded / expired, due date), bulk assign by audience, non-respondents page |
| Other | 8 web services, 2 message providers, 2 scheduled tasks, 2 flags (OFF), 247 strings in each of en, hi, kn, mr, sw, 16 test files, CSV export, Kirkpatrick analysis, template library with JSON import/export, privacy provider |

**Problems found that affect this scope** (each fixed in the session named):

| # | What | Where | Fix in |
|---|---|---|---|
| E1 | Course, program and classroom ids attached to a response come from the browser and are never checked, so a learner can file feedback against any classroom | `classes/external/submit_response.php` → `evaluation_manager::submit_response()`; `respond.php` | 11 (interim guard in 2 if SV24 = yes) |
| E2 | Invitations carry only the form id, so responses are stored without context | `evaluation_engine::send_invite_notification()` | 2 (F0) |
| E3 | Response notices go to all **site admins**, not the form owner and tenant L&D admins (D25) | `notify_admins_of_response()` | 11 |
| E4 | Invitation subject and body are hard-coded English | `send_invite_notification()` | 2 (F0) |
| E5 | `lib.php` is empty: no file serving yet | `lib.php` | 28 |
| E6 | No rate limiter anywhere (plugin, platform, Moodle 5.3 core) | searched each | 29a |
| E7 | The privacy hub only serves logged-in users | `local_sentientia_privacy/index.php` | 29b |

**Patterns to reuse:** the no-login join page of `local_sentientia_live` (`audience/join.php`: no login, login layout,
anonymous bearer token, gated by a flag) and the sign-up form of `local_sentientia_users` (honeypot first, then core
reCAPTCHA v2 when site keys exist).

---

## 2. Microsoft Forms compared with our plugin

Facts come from Microsoft support pages unless marked "not verified".

| Forms feature | Ours today | Gap | Session |
|---|---|---|---|
| Choice (one or many) | Yes | "Other" with free text, dropdown, shuffle, weighted choices (BizLMS had them) | 24 (F2) |
| Text, number | Long text; integer with min/max | Short text, decimals, length limits | 24 |
| Rating | Fixed 1-5 | 2-10 levels, stars or numbers, own end labels | 24 |
| Date, ranking, Likert | None | All three (Likert stored one answer per row; ranking with a keyboard alternative) | 24 |
| Net Promoter Score | Yes (0-10) | Promoter / passive / detractor chart | 26 (F4) |
| File upload (up to 10 files; 10 MB / 100 MB / 1 GB per file; organisation-only forms; files go to the owner's OneDrive) | None | New question type | 28 (logged-in), 29b (public, if SV10 = b) |
| Sections, progress bar | None (BizLMS had page breaks) | Sections, pages, progress bar | 25 (F3) |
| Branching to a question, section or the end | Show/hide on one earlier answer | Branch to a section or the end, path re-checked on the server | 25 |
| Themes (image, colour) | None | Header image + accent colour from the brand tokens | 30 |
| Quiz mode (points, auto-marking, time limit) | None | **Do not build** (Moodle Quiz covers it; SV14) | none |
| Who can respond: anyone / my organisation / specific people | Login + tenant + assignment | Anyone (public link) | 29a, 29b |
| Accept responses, start and end dates | Yes | none | done |
| Custom thank-you message | None | Per-form text | 11 (F1) |
| Response receipts, reminders | Admin notice to site admins; no reminders | Reminders at 3 and 7 days; notices to owner + tenant L&D; receipt = learner history page | 11; SV19 |
| Limits (200 questions, 5,000,000 responses, 4,000 characters) | None | Per-form response cap; answer length limit | 30, 24 |
| Results (charts, individual responses, live) | Per-question stats, Kirkpatrick, drilldown (flag) | Charts, stacked Likert, 30-second refresh, completion time | 26 |
| Open in Excel | CSV | Excel with a summary sheet (`MoodleExcelWorkbook` supports several sheets) | 26 |
| Share: link, QR, embed, e-mail, pre-filled link | Invitations only | Copy link, QR (`core_qrcode`), public link | 11, 29a |
| Share a public summary link | None | **No** (results stay behind login; SV18) | none |

**What we have that Forms does not:** tenant scoping, Kirkpatrick levels, automatic sending on course, program or
classroom completion, audience assignment with non-respondents and expiry, per-question anonymity, supervisor
evaluations, binding to learning context.

---

## 3. External form links (D23)

### 3.1 Design

A form gets a **source**: `native` (today) or `external`. An external form keeps everything around the questions
(binding to a course, classroom, program stage or trainer; assignment; invitations; reminders; non-respondents;
reports) but has no questions of its own.

**Launch flow:**
1. The learner opens our `launch.php` (login required). It checks the binding and records a launch with a fresh random token.
2. First time only: a privacy notice (3.4).
3. Redirect to the **URL stored on the form**, with `{token}` replaced by the token. Never a URL from the request, so it
   cannot be used as an open redirect.

The admin pastes the tool's own pre-filled link with `{token}` where the sample answer was (Microsoft Forms "Get
pre-filled link", Google Forms pre-filled link, Typeform hidden fields). Exact parameter formats were not verified; the
paste-a-template approach does not depend on them.

**URL rules:** `https` only; host on a site-admin allowlist (SV1); **only the token goes out**, never a name, e-mail or
employee id (sending employee data to an outside service is a CLAUDE.md escalation item).

### 3.2 How we know the form was submitted

| Option | How | Microsoft Forms | Google Forms | Typeform | Trust |
|---|---|---|---|---|---|
| A. Self-attest | Learner clicks "I have submitted it" | Yes | Yes | Yes | Low, labelled "self-reported" |
| B. Launch tracking | We record that the link was opened | Yes | Yes | Yes | Shows opening only |
| C. Return link with token | Tool sends the respondent back to our page | **No** (thank-you text is static) | Not found | **Yes** (redirect with hidden fields) | Medium |
| D. Signed callback | Tool or automation posts the token with an HMAC signature to our callback | Yes, via **Power Automate** (HTTP action said to be premium; licence check needed) | Forms API / watches or Apps Script (not verified) | Webhooks with HMAC-SHA256 (check Typeform docs) | High |
| E. Pull from Microsoft Graph | Poll for responses | **Not available** (no GA endpoint; unofficial `formapi` must not be used) | n/a | n/a | n/a |
| F. Excel workbook via Graph | Read "Open in Excel" | Live workbook refreshes only when an owner or editor refreshes it | n/a | n/a | Low |

**Recommendation:** A + B for every external form; D opt-in per form behind its own flag (it opens an endpoint without
login); C for Typeform only; no Graph work until Microsoft ships a GA Forms API.

**Callback rules:** one secret per form, encrypted with `\core\encryption` (as `local_sentientia_m365` stores tokens);
HMAC-SHA256 over the raw body with a timestamp header, a 5-minute window and replay protection; single-use token tied to
its launch; no personal data in the body; marks the assignment "responded" with source "callback".

### 3.3 What counts toward completion

Training completion (C4) needs "both feedback forms submitted". Recommendation (SV3): a self-attested external form
counts for feedback stages, reports label it "self-reported", and a stage can be set to "verified only".

### 3.4 Privacy notice on first launch (per customer, en + hi)

Which provider the learner is going to and that the provider holds what they type there; that we send only a reference
number; for Microsoft Forms in the customer's own Microsoft 365 tenant, answers stay in that tenant (Google Forms and
Typeform are outside parties); whether the external form records their name (declared by the admin). DPDP notice duties
start about 13 May 2027; building the notice now meets good practice and the cutover timeline.

### 3.5 Embed or new tab

Recommendation (SV4): **new tab** in v1. An embedded organisation-only Microsoft Form depends on the user's Microsoft
365 sign-in and third-party cookies inside our page (my reasoning, to be tested). No `frame-src` policy was found in the
theme or platform plugin, so embedding is not blocked technically.

---

## 4. Public no-login links (D24)

### 4.1 Model

- **Link:** random 32-byte token in the URL path (`/local/sentientia_evaluation/p.php/<token>`). Stored as a SHA-256
  hash for lookup plus an encrypted copy so an admin can copy the link or QR again. Each link belongs to one form (and
  optionally one binding) and has a label, mode (anonymous / identified), open and expiry dates (default 30 days, maximum
  180), response cap (default 500), status (active / paused / revoked), creator and tenant.
- **Who creates links:** new capability `:managepubliclinks` for tenant L&D admins only, never trainers. Granted to the
  real Airpay L&D admin role with an upgrade back-fill (the T-01 lesson: caps granted only to standard archetypes never
  reached Airpay's roles).
- **Flags:** a visitor has no tenant, so the page reads every flag **for the form's tenant**.
- **Public page:** no login, login layout (works on phones), `Cache-Control: no-store`, sections and branching (needs
  F3). No Moodle session; a signed form nonce (link id, issue time, random value, site secret) stops cross-site posts
  and enforces a minimum fill time.
- **Storage:** existing responses table with user 0 and a new link id; identified respondents get a row in a new
  respondent table. Quarantined responses never count in results.

### 4.2 Abuse controls

| Control | Detail |
|---|---|
| reCAPTCHA v2 | Core element. A link **cannot be activated** without site keys (fail closed). Whether keys are set on UAT: not verified. reCAPTCHA sends IP and browser data to Google, so the notice says so. Its audio challenge is weak for accessibility |
| Honeypot + minimum time | Hidden field + at least 3 seconds from page load to submit |
| Rate limits (new) | Application cache with a DB fallback. Defaults: 5 submissions per IP per link per hour; 300 per link per hour; 20 page views per IP per minute. IP used only as a salted hash |
| Response cap | Enforced inside a transaction, so simultaneous submissions cannot exceed it |
| Repeat answers | Anonymous: soft cookie ("you already answered on this device"). Identified: one response per e-mail per link |
| Content | Reuse `local_sentientia_live`'s profanity filter to **flag** open text for review |
| Moderation | Pause or revoke a link; quarantine one response or a time window; delete quarantined responses; every action in the platform audit log |

### 4.3 Anonymous or identified

- **Anonymous (default):** no name or e-mail; only the IP hash, cleared at retention.
- **Identified:** name and e-mail with an unticked consent box (text version and time stored); e-mail format checked,
  not verified, and nothing is e-mailed to respondents (so a fake address cannot be used to spam someone); optional
  "I am 18 or older" question (DPDP requires parental consent for children; out of scope for v1).

### 4.4 Notice and personal data

- Notice before the questions (per customer, en + hi): who collects, why, what (answers; name and e-mail if identified;
  IP hash; reCAPTCHA data to Google), how long it is kept, how to ask for erasure (grievance contact, SV21).
- Admin page "Find respondent by e-mail" to export or erase; daily retention task clears e-mail, name and IP hash after
  N days and keeps the answers anonymised (SV9). The privacy provider declares the new tables.
- **ZEEA (Tanzania):** data protection law not researched; public links off for ZEEA until legal review (SV22).

---

## 5. File uploads

### 5.1 Logged-in respondents (session 28)

- New question type `fileupload`: maximum files (1-10), maximum size per file (capped by site limits), allowed types
  from core file-type groups (documents, spreadsheets, presentations, PDF, images; no audio, video, archives or
  executables in v1).
- Core file manager widget; core already scans these uploads with the antivirus manager.
- Stored in the plugin's `response_file` area (item = response id); the answer JSON keeps only a count.
- New `pluginfile` callback. Who can open files: `:viewfiles` holders in the form's tenant and the respondent (own files
  only). **Never trainers.** Admins can download all as ZIP.
- **No file questions on anonymous forms** (SV13): files can identify a person and could not be erased on request.
- Turn on Moodle 5.3's photo-metadata remover (Site admin > Server > File redaction); the session checks it fires for
  our files.
- Deleting a response, a form or a user deletes the files; the privacy provider exports and deletes them.

### 5.2 Public respondents (only if SV10 = b, session 29b)

Our own upload endpoint (core's uploader requires login): gated by the signed nonce and rate limit, runs the antivirus
scan itself, checks content type and extension, holds files in a pending area for up to 24 hours until the response is
submitted. Does nothing unless an antivirus plugin is enabled and healthy.

### 5.3 Antivirus and retention

- Moodle 5.3 ships ClamAV support (command line or socket) with a "what if the scanner fails" setting. **ClamAV on UAT
  or the new server: not found.** IT ask: install clamd, enable the plugin over the Unix socket, treat scanner failure as
  a virus, test with the EICAR file.
- File questions cannot be added unless the antivirus check passes (SV12); a site admin may override for local
  development, audited.
- Files kept 365 days by default, adjustable 30-730 per form (SV9), then deleted, with "file removed (retention)" left in
  the answer. Uploads grow the file store, which Stage B must carry.

---

## 6. Build plan

### 6.1 Data model (additive; columns default to today's behaviour)

| Change | Fields | Session |
|---|---|---|
| Form table, add | `source` (native / external), `external_url`, `external_provider`, `external_records_identity`, `completion_mode` (submit / selfattest / callback), `callback_secret` (encrypted), `max_responses`, `thankyou_message`, `accent_color`, `retention_days` | 11, 11b, 28, 30 |
| Questions, add | `settings` (JSON: scale, labels, file limits, date range), `sectionid` | 24, 25, 28 |
| New `_section`, `_branch` | as planned in F3 | 25 |
| New `_binding` | form, item type (course, classroom, program, stage, trainer), item id, subject user, dates, tenant, path | 11 (ADR-036) |
| Responses, add | `bindingid`, `publinkid`, `status` (ok / quarantined / spam), `timestarted` | 11, 29a, 26 |
| New `_extlaunch` | form, binding, user, assignment, token hash (unique), launched / attested / verified times, verify source, external response id | 11b |
| New `_publink` | form, binding, token hash (unique), encrypted token, label, mode, captcha, cap, count, dates, status, creator, tenant, path | 29a |
| New `_pubresp` | response, link, e-mail, name, consent version and time, IP hash, status, purge time | 29a, 29b |
| File areas | `response_file`, `pending_upload`, `header` | 28, 29b, 30 |
| New `db/caches.php` | `ratelimit` | 29a |

XMLDB reminders: index names under the length limit, VARCHAR maximum 1333, no raw `<` in COMMENT attributes.

### 6.2 Capabilities (Airpay roles back-filled in upgrade.php)

| Capability | Who | Why separate |
|---|---|---|
| `local/sentientia_evaluation:managepubliclinks` | Manager + tenant L&D admin | Creates entry points without login |
| `local/sentientia_evaluation:viewfiles` | Manager + tenant L&D admin | Files are more sensitive than totals |
| `local/sentientia_evaluation:moderate` | Manager + tenant L&D admin | Quarantine and delete public responses |
| `local/sentientia_evaluation:viewownresults` | Trainer (D25, minimum 5 responses) | Planned in F1 / F4 |

### 6.3 Feature flags (all default OFF)

`sentientia.evaluation.external_links`, `sentientia.evaluation.external_callback`, `sentientia.evaluation.public_links`
(read for the form's tenant), `sentientia.evaluation.public_identified`, `sentientia.evaluation.file_upload`,
`sentientia.evaluation.public_file_upload` (only if SV10 = b), `sentientia.evaluation.themes`; plus the planned
`admin_shortcuts`, `distribution`, `qtypes_v2`, `sections`, `results_v2`.

### 6.4 Strings and tests

- About 170 new strings; en and hi in each session (pre-commit CHECK 17); kn, mr and sw in one later batch (SV17).
  Customer legal text (entity, grievance contact) goes in per-customer settings, not language files.
- Tests per session, including: token stored only as a hash and single-use; URL allowlist (https only, no
  `javascript:` or `data:`); no personal data in outbound URLs; callback signature valid / wrong / expired / replayed;
  cross-tenant refusal; flag OFF means unavailable; expired / paused / revoked links; response cap under simultaneous
  submissions; rate-limit windows; reCAPTCHA required and link activation refused without keys; honeypot and minimum
  time; consent required in identified mode; IP stored only as a hash; quarantined responses excluded; retention purge
  and erase by e-mail; antivirus called and fail-closed; file permission matrix (same-tenant manager yes, other tenant
  no, trainer no, owner yes); file question refused on anonymous forms; deletes cascade; **E1: a response bound to an
  item the user was not invited to is refused**.

### 6.5 Sessions and effort

| Session | Delivers | Days | Needs first |
|---|---|---|---|
| 2 (planned) | F0 quick wins, plus E2 and E4 (and an interim E1 guard if SV24 = yes) | 0.5 (+0.25) | none |
| 11 (planned, grows) | F1 + C2 binding and distribution, **plus E1 server-side binding check and E3 notices** | 3.5-5 | ADR-036, D23, D25 |
| **11b (new)** | External form links: source, admin form, launch + token, notice, self-attest, signed callback, setup guide (Power Automate, Typeform, Google), reports | 3-3.5 | 11, ADR-043, SV1-SV4 |
| 24 (planned) | F2 question types v2 + `settings` column | 2-3 | 11 |
| 25 (planned) | F3 sections and branching | 2-3 | 24 |
| 26 (planned) | F4 results v2 (charts, Excel with several sheets, 30-second refresh) | 2 | 11 |
| **28 (new)** | File-upload question for logged-in users, file serving, permissions, retention task, privacy | 2-2.5 | 24, SV10-SV13, IT: ClamAV |
| **29a (new)** | Public links, anonymous: link admin + QR, public page, nonce, reCAPTCHA, honeypot, rate limiter, cap, moderation, audit | 3 | 25, 11, ADR-043, SV5, SV7, SV8, SV22, reCAPTCHA keys |
| **29b (new)** | Identified mode + consent + DPDP notice, respondent table, erase by e-mail, retention purge; public uploads only if SV10 = b | 2-2.5 | 29a, 28, SV6, SV9, SV21 |
| **30 (new)** | Header image + accent colour, per-form response cap, shuffle and numbering toggles | 1-1.5 | 25, SV15, SV16 |

**Totals:** new sessions 11b, 28, 29a, 29b, 30 = **11.5 to 13.5 days**. Planned F0 to F4 with session 11's growth =
10 to 13.5 days. **Surveys overall about 21.5 to 27 days** (was 15 to 19 including the old "later" F5).
**Order:** 2 → 11 → 11b → 24 → 25 → 26 → 28 → 29a → 29b → 30. 11b can run beside 24; 26 beside 25.
**ADR-043 "Survey reach"** (external links, public links, non-user personal data, files and retention) is written before
11b. (ADR-042 is taken by ROI model v2 in the revised schedule.)

---

## 7. Decisions (choose and approve)

| # | Question | Options | Recommended | Before |
|---|---|---|---|---|
| SV1 | Which external form tools may be linked? | a) Microsoft Forms only; b) Forms + Google Forms + Typeform; c) any https host on a site-admin allowlist | **c**, default allowlist `forms.office.com`, `forms.microsoft.com`, `docs.google.com/forms`, `forms.gle`, `*.typeform.com`; customers ask to add more | 11b |
| SV2 | How do we confirm an external form was submitted? | a) self-attest only; b) self-attest + optional signed callback; c) wait for a Graph API | **b** | 11b |
| SV3 | Does a self-attested external form count toward training completion? | a) yes; b) only verified; c) yes, labelled, and a stage can require verified | **c** | 11b |
| SV4 | Embed external forms or open in a new tab? | a) new tab; b) embed | **a** for v1 | 11b |
| SV5 | Who may create public links? | a) tenant L&D admins; b) plus trainers; c) site admins only | **a**, with a new capability; trainers never | 29a |
| SV6 | Default mode for public links; is e-mail verified? | a) anonymous by default, identified optional, e-mail not verified, nothing e-mailed to respondents; b) identified with e-mail verification (needs SMTP) | **a** | 29b |
| SV7 | Anti-abuse defaults | as in 4.2 | **Yes**: reCAPTCHA required (no keys = link cannot be activated), honeypot + 3 s, 5 per IP per hour, 300 per link per hour, cap 500, expiry 30 days (max 180) | 29a |
| SV8 | Respondent IP addresses | a) never kept; b) salted hash only, purged at retention; c) clear IP for admins | **b** | 29a |
| SV9 | Retention | set days | **E-mail, name, IP hash: 180 days then cleared (answers kept anonymised). Files: 365 days, adjustable 30-730 per form** | 28, 29b |
| SV10 | File uploads on public links in v1? | a) logged-in respondents only (Microsoft's own rule); b) public too, identified mode only, ClamAV required | **a** for the first release; b behind its own flag in 29b later | 28 |
| SV11 | File limits | choose | **Default 3 files, 10 MB each (max 25 MB); documents, PDF, images; no video, audio, archives or executables** | 28 |
| SV12 | Antivirus | a) required, fail closed, audited override; b) optional | **a**; IT installs ClamAV on UAT and the new server | 28 |
| SV13 | Who sees uploaded files; uploads on anonymous forms? | — | **`:viewfiles` holders in the form's tenant + the respondent's own files; never trainers; no file questions on anonymous forms** | 28 |
| SV14 | Forms-style quiz mode (points, auto-marking)? | a) no, keep it in Moodle Quiz; b) light scoring later | **a** | 24 |
| SV15 | Theme freedom per form | a) header image + accent colour from brand tokens; b) any colour or font | **a** (keeps contrast and brand) | 30 |
| SV16 | Response limits | a) per-form and per-link cap; b) also a timed form | **a** only | 30 |
| SV17 | Languages for the ~170 new strings | a) en + hi each session, kn / mr / sw in one later batch; b) all 5 each session | **a** | all |
| SV18 | Public "share summary" links like Forms? | yes / no | **No**; results stay behind login (D25) | 26 |
| SV19 | Response receipts | a) learner history page for logged-in users, none for public; b) e-mailed PDF | **a** | 11 |
| SV20 | Record all of this in ADR-043 before session 11b? | yes / no | **Yes** | 11b |
| SV21 | Grievance and erasure contact named in the public notice | name per customer | **Nitin names Airpay's DPO or grievance contact**; erasure through the admin "find by e-mail" screen | 29b |
| SV22 | Public links for ZEEA (Tanzania) | on / off until legal review | **Off for ZEEA** until legal reviews Tanzanian law | 29a |
| SV23 | Session order | as in 6.5 / other | **As in 6.5** | now |
| SV24 | Interim guard for defect E1 before session 11 (accept a course or classroom id only if the respondent is enrolled or on the roster) | yes, in session 2 (about 2 hours) / wait for session 11 | **Yes, in session 2** (my addition: session 11 may be weeks away) | 2 |

---

## 8. Open questions for IT or the business

- reCAPTCHA v2 site keys on UAT and production: not verified.
- ClamAV on UAT and the new server: not found.
- Power Automate licence for the HTTP action (community sources say premium), only if Forms callbacks are wanted.
- Typeform plan with webhooks and a Google Cloud project, only if those tools are used.
- Airpay's Microsoft Forms tenant settings (for example external sharing): not checked.

---

## 9. Sources

**Microsoft Forms:** limits https://support.microsoft.com/en-US/Forms/form-question-response-and-character-limits-in-microsoft-Forms ·
settings https://support.microsoft.com/en-us/forms/adjust-your-form-or-quiz-settings-in-microsoft-forms ·
create a form https://support.microsoft.com/office/4ffb64cc-7d5d-402f-b82e-b1d49418fd9d ·
file uploads https://support.microsoft.com/en-us/forms/add-questions-that-allow-for-file-uploads-in-microsoft-forms ·
results and Excel https://support.microsoft.com/office/check-and-share-your-form-results-02859424-341d-406f-b32a-9a0fbaf357af ·
send a form https://support.microsoft.com/Forms/send-a-form-and-collect-responses ·
themes https://support.microsoft.com/forms/change-a-form-theme ·
branching https://support.microsoft.com/en-us/office/use-branching-logic-in-microsoft-forms-16634fda-eddb-44da-856d-6a8213f0d8bb ·
sections https://support.microsoft.com/en-US/Forms/add-sections-to-your-survey-or-questionnaire-1 ·
pre-filled links https://techcommunity.microsoft.com/discussions/microsoftforms/pre-populate-fields-in-microsoft-forms-via-url-parameter/113803 ·
no redirect after submit https://techcommunity.microsoft.com/discussions/microsoftforms/take-user-to-a-specific-url-after-they-click-submit-on-a-microsoft-form/3870062

**Graph and Power Automate:** Forms API status (MVP answer, Dec 2025) https://techcommunity.microsoft.com/discussions/microsoftforms/microsoft-forms-api/4480628 ·
(search summaries only) https://learn.microsoft.com/en-us/answers/questions/1650918/using-microsoft-graph-to-access-microsoft-forms-re ·
https://learn.microsoft.com/en-us/connectors/microsoftforms/ ·
https://learn.microsoft.com/en-us/answers/questions/687015/which-license-do-i-need-to-use-an-http-action-and?page=1

**Google Forms and Typeform:** https://developers.google.com/workspace/forms/api/reference/rest/v1/forms.responses/list ·
https://developers.google.com/workspace/forms/api/reference/rest/v1/forms.watches · https://help.typeform.com/hc/en-us/articles/360050455852 ·
https://www.typeform.com/developers/webhooks/secure-your-webhooks/ (third-party summary: https://hookdeck.com/webhooks/platforms/guide-to-typeform-webhooks-features-and-best-practices)

**DPDP:** https://static.pib.gov.in/WriteReadData/specificdocs/documents/2025/nov/doc20251117695301.pdf ·
https://yourstory.com/2025/11/digital-personal-data-protection-dpdp-rules-2025-rollout-over-18-months

**Code read:** `local/sentientia_evaluation/` and `moodle-enhancement/local/sentientia_evaluation/` (`db/install.xml`,
`db/access.php`, `db/feature_flags.php`, `classes/evaluation_manager.php`, `classes/evaluation_engine.php`,
`classes/external/submit_response.php`, `classes/privacy/provider.php`, `respond.php`, `exportcsv.php`) ·
patterns in `local/sentientia_live/audience/join.php`, `local/sentientia_users/classes/form/signup_form.php`,
`local/sentientia_privacy/`, `local/sentientia_m365/classes/` · Moodle 5.3 core in `D:\Claude Local\moodle53\moodle\public\`
(`lib/antivirus/clamav/settings.php`, `lib/classes/antivirus/manager.php`, `repository/upload/lib.php`,
`repository/repository_ajax.php`, `lib/recaptchalib_v2.php`, `lib/classes/qrcode.php`, `lib/excellib.class.php`,
`files/classes/hook/before_file_created.php`) · `moodle-enhancement/docs/plans/2026-11-19-POST-DEMO-PLAN.md` (4C, 4F, 5, 6, 6a, 6b)
