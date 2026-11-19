# ADR-038 — Assessment integrity levels: Standard, Secure and Proctored per assessment type, Safe Exam Browser, in-house record-and-review, candidate accounts

- **Status:** Proposed 2026-11-19. Nitin's answers D8 to D11 (post-demo plan §6a) are final and frame this ADR. Choices beyond them are marked "proposed" and await his approval; the personal-data reading (D8 below) needs Airpay's DPO and Legal. Becomes Accepted when Nitin approves or amends it. Needed before session 4 (B1).
- **Date:** 2026-11-19
- **Decision-makers:** Nitin Rajput (Head of L&D, Airpay Payment Services); HR for the candidate flow; the DPO and Legal for lawful basis, notice and retention.
- **Implementer:** Claude
- **Amends:** ADR-017 (adds a fifth user type, `candidate`, as ADR-036 adds the external trainer). No core file changes (ADR-033 decision 4) and no vendor patch (ADR-041): the quiz access-rule hooks, `quizaccess_seb` templates and the quiz attempt API are stock Moodle 5.3.
- **Related:** ADR-002, ADR-017, ADR-031, ADR-034 (D6), ADR-035, ADR-036 and ADR-040 (reserved, not written), ADR-041.
- **Evidence:** `docs/plans/2026-11-19-POST-DEMO-PLAN.md` §4B, §5.1, §6a; `docs/guides/ASSESSMENT-INTEGRITY-ADMIN-GUIDE.md`; `local/sentientia_proctoring/` (2026111200, 1.0.6) and `mod/quiz/accessrule/sentientia_proctoring/` (2026080400, 1.1.1), both trees (the access rule's copies differ in line endings only); core 5.1.3+ at `C:/xampp/htdocs/moodle5/public`, read only.

Plan decisions are written "plan D8". D1 to D9 below are this ADR's own.

---

## Context

**Nitin's decisions (plan §6a, final).**
- **D8:** hiring and skill certification Proctored; skill assessments Secure; compliance and POSH Standard.
- **D9:** hiring both remote and walk-in; walk-in on office laptops with Safe Exam Browser (SEB), an IP subnet and a quiz password.
- **D10:** build record-and-review in-house; quote live proctoring only (B6); store in an India region or on Airpay's own server; keep 30 days for candidates, 90 for employees; no automated ID-to-face matching in v1.
- **D11:** employees warn and flag only; hiring auto-submits after 3 fullscreen or tab violations (configurable); "review immediately after" off in the site quiz defaults.

**The code today is not usable** (README corrected 19 Nov; 0 proctored quizzes and 0 sessions on local).
- **Lockout:** `rule.php:101-118` refuses an attempt unless a session is `recording`, but the consent flow loads only from `setup_attempt_page()` (`:125`), on the attempt page the learner never reaches.
- **Logs, never blocks:** `amd/src/proctor.js` logs tab switches, blur, fullscreen exits and paste, all reported by the browser, so forgeable. It starts once (`consent.js:133`): first page only.
- **No recording, no face detection:** webcam and screen streams are opened (`proctor.js:66, 78`) but never recorded; `upload_chunk` has no caller. `sampleFace()` (`:170`) only checks for a picture; `multiple_faces` has a weight (`risk_analyzer.php:36`) and no producer.
- **No end-of-attempt step:** `finalize` runs only from `proctor.stop()`, which nothing calls.
- **Identity** sends an ID photo and a selfie to AWS Rekognition when `provider` = `aws` (`classes/identity/aws_verifier.php`; default `mock`), which plan D10 rules out.
- **Retention** is one setting (90 days) stamped per chunk at upload (`session_manager.php:288-296`). The purge reads `aws_access_key` / `aws_secret_key` (`classes/task/purge_old_recordings.php:64-65`), but `settings.php:23, 27` stores `aws_key` / `aws_secret`: every purge would skip as "config incomplete" and nothing would leave the bucket.
- The consent text is English in JS and promises what does not exist ("scanned by AI", `consent.js:48-50`). No flag registry. The Exams form (`local/sentientia_exams/classes/form/edit_exam.php`) has no integrity field.
- **Data** (April production backup): of 235 BizLMS quizzes, 7 use browser security; none a password, IP restriction, SEB or proctoring; 28 draw random questions; 120 show the questions straight after submitting.

**Stock Moodle already has** access-rule pre-flight hooks (`mod/quiz/classes/local/access_rule_base.php:111-152`); `quizaccess_seb` templates (`settings_provider.php:61`), camera permission (`seb_quiz_settings.php:137`) and server-side Config Key and Browser Exam Key checks (`seb_access_manager.php:82, 114`); password and network rules; server-side submission (`quiz_attempt::process_submit()`, `quiz_attempt.php:1864`); and the `attempt_submitted`, `attempt_abandoned` and `attempt_becameoverdue` events.

**Nothing in a browser can see** a phone running an AI chatbot, or a helper off camera. Only a camera, a live proctor or question design reduces that risk.

---

## Decision

### D1 — Three levels, set per quiz, defaulted from the assessment's purpose

| Level | Default for (plan D8) | Adds | Runs on |
|---|---|---|---|
| Standard | Compliance, POSH, practice | Question-design preset (B0 guide) + browser hardening (D3) | Any desktop browser; mobile app per ADR-040 |
| Secure | Skill assessments | Standard + SEB kiosk preset (D4), camera off | Windows or macOS with SEB (iPad possible) |
| Proctored | Hiring, skill certification | Secure + webcam record-and-review (D5), camera on | as Secure |

- **Stored on the quiz:** new columns `level` and `purpose` in the access rule's `quizaccess_sentientia_proctor` (proposed); the old `enabled` maps to Proctored (0 rows today). Set in the quiz settings and the Exams create form (B3).
- **Purpose picks the default.** Raising is free; lowering below it needs `local/sentientia_proctoring:manage` and a logged reason.
- **No level, no change.** A quiz without a level behaves as today. The 235 imported quizzes are not bulk-assigned.
- **Fail closed.** A Secure or Proctored quiz whose flag is OFF, or whose SEB template or recording store is missing, refuses to start with a message (`prevent_access()`); it never runs silently at a lower level. Standard falls back to the core-settings preset while B2's flag is OFF (no weaker than today).
- **Accommodations** (assistive technology, fullscreen exemption) are per user and quiz, with a reason, by `:manage`, stored in `local_sentientia_proctoring`. Extra time stays a core user override. The access rule keeps per-quiz data only, so its `null_provider` stays true.

### D2 — B1 rebuilds the foundation inside the two existing components

- Names, the 5 tables and the 12 services stay; schema changes are additive.
- Consent and identity move into the quiz's pre-flight check; the `prevent_new_attempt()` lockout goes.
- `setup_attempt_page()` loads the monitor on every attempt page. The server finds the session from the attempt; the browser never names it.
- The server closes the session: observers on the three attempt events run `finalize()`. The browser's `stop()` only releases the camera.
- **Identity v1 (plan D10):** at Proctored, a live webcam still at pre-flight, kept with the recording for the reviewer. B1 removes the ID-photo step, the `aws` provider and its settings, and the `aws_rekognition` metadata link (`classes/privacy/provider.php:27`); the verifier interface stays for a possible v2 with its own ADR. At Standard and Secure identity is the login; at walk-in sittings the invigilator also checks a physical ID.
- All learner strings in lang (en + hi); the notice says only what the code does. B1 also fixes the purge config keys.

### D3 — Browser hardening (B2): block what a page can, log the rest with server time

- **Blocked** in the quiz page: copy, cut, paste, right-click, print, drag, selection and their shortcuts. On desktop, questions are hidden outside fullscreen. A watermark shows the candidate's name and attempt id.
- **Logged with server time:** focus and visibility loss, fullscreen exit, reloads, an extended display where the browser exposes it (Chromium only, best effort), an IP change mid-attempt, time per question.
- **Heartbeat** every 15 seconds (proposed): a gap while the attempt is open is itself an event, so blocking our script shows up.

**What each layer can and cannot detect** (SEB rows to be confirmed in the B3 Windows test):

| Threat | Browser hardening | + SEB | + recording |
|---|---|---|---|
| Copying question text | Blocked in the page; OS screenshots not | Screen capture blocked | as SEB |
| Another tab or app (AI on the same computer) | Detected, not prevented | Prevented (kiosk) | as SEB |
| Second monitor | Best effort, Chromium only | One display enforced | as SEB |
| Remote control or screen sharing | Not detected | Listed processes blocked (Windows) | as SEB |
| Virtual machine | Not detected | Refused when configured | as SEB |
| Second device (phone) | Not detected | Not detected | Only if in frame |
| Helper or stand-in | Not detected | Not detected | If in frame; same face throughout |
| Tampered client | Possible; a heartbeat gap is the only trace | Keys checked on the server; a skilled candidate can forge the Config Key, the office laptops' Browser Exam Keys close most of that | The video is the evidence; stopping it leaves a gap |

### D4 — Safe Exam Browser preset (B3)

- Two seeded `quizaccess_seb` templates, **Sentientia Secure** (camera and microphone off) and **Sentientia Proctored** (camera on, microphone off). Both: one display, no screen capture, no virtual machine, remote-control apps prohibited, reload allowed (a crash must not lose the attempt), quit password per quiz. Secure and Proctored select them through `quizaccess_seb`'s own settings ("use an existing template").
- A candidate pre-check page tests SEB, camera and upload speed before the sitting.
- **Walk-in (plan D9):** SEB installed by IT on office laptops, a new quiz password per sitting, the office egress range as the network address, and those laptops' Browser Exam Keys listed on the quiz.
- SEB runs on Windows, macOS and iPad, not Android, Linux or ChromeOS; those candidates come in. The mobile app shows "desktop required" for Secure and Proctored (plan D29).

### D5 — Record-and-review, built in-house (B4)

- **Webcam video only:** 320x240, low frame rate, 30-second chunks, recorded inside SEB. No screen recording (SEB already locks the screen; screen video catches personal notifications). No audio in v1 (open question 4).
- **Stills:** the pre-flight selfie, then one every 5 minutes (proposed).
- **Face detection in the browser:** MediaPipe face detector (Apache-2.0), shipped inside the plugin, never fetched at exam time. It emits `no_face` (over 10 seconds, proposed) and `multiple_faces`; no face template or identity is computed or stored.
- **Upload through our server:** the session owner POSTs each chunk within the bounds `register_chunk()` already checks; the server records a SHA-256 and writes it to the store (D8). The browser holds no storage credentials. Gaps show in the timeline.
- **Review:** chunks, stills and events on one server-time timeline; clean / warn / fail as today. The risk score only orders the queue; a "fail" needs a human.
- Estimate, to measure in B4: 60 to 120 MB per one-hour sitting.

### D6 — Violations (plan D11)

- The server counts; the page only shows the count.
- A fullscreen exit or focus loss is one violation; events within 2 seconds count once, and the first 10 seconds after a page load do not count (permission prompts cause blur) (both proposed). Heartbeat gaps, face signals and SEB anomalies are flagged, never counted.
- **Employees:** warning with the count, and a flag. Never auto-submitted.
- **Candidates:** warnings at 1 and 2; at 3 the server submits the attempt (`process_submit()`, then grading) and logs `integrity_autosubmitted`. Per-quiz threshold, default 3, 0 = off, stated in the notice.
- Auto-submit ends an attempt; it never fails a candidate. HR can grant a new attempt with a core override.

### D7 — Candidate accounts (B5)

- **User type `candidate`** (amends ADR-017; `local/sentientia_platform/classes/user_type_factory.php:85` lists four today). A hired candidate gets a new employee account; no data crosses (ADR-017 Q1).
- **Invite-only:** created by HR or the hiring tenant's L&D admins (ADR-031 scope); no self-signup; the invitation link sets a password once. Walk-in candidates get the sitting password from the invigilator.
- **Restricted role:** only the assessments in a hidden hiring course; no catalogue, messaging or forums; profile limited to name, e-mail and phone (proposed).
- **Expiry:** suspended when the invitation window closes (7 days after the last sitting, proposed); video purged at 30 days; account and attempt anonymised at the candidate retention end (open question 3).
- **Results to HR:** score and integrity verdict in an HR-only report. Video never leaves the platform. Candidates and employees never share accounts.

### D8 — Webcam data under DPDP and the SPDI Rules

Our reading; nobody has checked it against the legal text. Legal and the DPO confirm it before any recording flag is ON.

- **No biometric processing in v1.** The SPDI Rules 2011 treat biometric information (facial patterns measured for authentication) as sensitive personal data. v1 does not authenticate by face: no ID upload, no matching, detection yields counts only. Recordings are still handled as sensitive.
- **Notice and basis.** Candidates: consent after a DPDP notice (en + hi) listing the data, purpose, retention, who sees it, and how to withdraw and complain; consent and notice version recorded. A candidate who refuses the camera is offered walk-in, so consent is not a condition of the candidacy. Employees: notice and consent at pre-flight; whether the DPDP employment legitimate use (s.7(i)) applies is for Legal.
- **India only.** One interface, two stores: the Moodle file store on an India-hosted server, or S3 in `ap-south-1` (Mumbai) or `ap-south-2` (Hyderabad), private and encrypted at rest. The S3 store refuses any other region; recording cannot be enabled until a store passes that check.
- **Retention (plan D10).** Fixed at session start from the user type: candidates 30 days, everyone else 90, from the end of the attempt. A per-quiz override can only shorten. A reviewer can hold a disputed session with a logged reason, up to 180 days, renewable by `:manage` (proposed).
- **Purging is a duty, not a feature.** The purge task and the privacy provider read no flag. Three days of purge failures (proposed) notify the DPO; on S3 a lifecycle rule per class is the backstop.
- **Access.** Video plays only through a capability-checked endpoint (`:review` in the session's tenant, `session_manager::require_session_access()`), streamed, not downloadable; every view logged. HR sees verdicts, not video.
- **Rights.** Export gives events and verdicts and lists the recordings (count, length, deletion date). Erasure keeps today's `expire_recordings()` (`classes/privacy/provider.php:175`), which hands the objects to the purge.
- If S3 is chosen, AWS joins the sub-processor list and ADR-034 D6's CERT-In reporting path covers the store.

### D9 — Flags, tenancy and order

| Flag (default OFF) | Item | Session |
|---|---|---|
| `sentientia.proctoring.enabled` | B1 foundation | 4 |
| `sentientia.assessment.secure_mode.enabled` | B2 hardening and violations | 7 |
| `sentientia.assessment.seb_preset.enabled` | B3 SEB preset | 9 |
| `sentientia.proctoring.recording.enabled` | B4 record-and-review | 15 |
| `sentientia.assessment.candidates.enabled` | B5 candidate accounts | 17 |

- Registered in a new `local/sentientia_proctoring/db/feature_flags.php` (both trees); resolved for the attempting user's customer and tenant, so one tenant can run proctored hiring alone.
- B1 first; B2 and B3 in parallel; B4 after B1, the budget and open questions 1 and 2; B5 after B3. Live proctoring is not built: if B6 buys it, it arrives through the vendor's own access rule under its own ADR.

---

## Consequences

**Positive**
- Proportionate: compliance stays light; video only for hiring and certification (data minimisation).
- SEB is free, maintained and built into Moodle; no core edit, no vendor patch.
- No third party sees a candidate's face; India residency is enforced in code.
- A tampered browser cannot rewrite the recording; stopping it leaves a gap.

**Negative / accepted**
- Weak remote identity: the stills prove one person sat the whole test, but a stand-in is caught only when HR compares them with the person at interview.
- SEB must be installed. Remote candidates without Windows, macOS or an iPad come in; employee laptops need an IT rollout before Secure works.
- No layer sees a second device; question design (B0) stays the main control.
- Reviewer time, storage and bandwidth need the pending budget (session 15).
- Auto-submit can end an honest candidate's attempt on a faulty machine; the grace window, warnings and HR's re-attempt soften it.

**Neutral**
- Quizzes without a level behave as today; changes to tables and services are additive.

---

## Alternatives considered

- **A vendor for every level:** per-sitting cost, candidate video at a vendor, too heavy for employees. Rejected (plan D10); the live-proctoring quote stays (B6).
- **Keep automated ID-to-face matching** (the Rekognition code): biometric processing, ID images at a third party, false rejections. Rejected (plan D10).
- **One level for everything:** webcam on every compliance quiz is disproportionate and costly. Rejected (plan D8).
- **Browser hardening only for hiring:** a page cannot see other apps or screens, and its signals can be forged. Rejected.
- **Our own lockdown browser:** SEB already does it, free, with Moodle support. Rejected.
- **Screen recording** (today's `getDisplayMedia`): redundant inside SEB, large, full of unrelated personal data. Rejected for v1.
- **Presigned direct-to-S3 uploads** (the original design): a signing service, CORS and browser upload rights, and no Moodle file store; our server can carry this volume. Rejected for v1.
- **Automatic fail on a high risk score:** an adverse decision from forgeable signals. Rejected; a human decides.

---

## Implementation actions

1. B0 (session 1): admin guide written 19 Nov. The site quiz default (plan D11) changes on UAT or live on Nitin's go.
2. Session 4 (B1): D1 columns, D2, the flag registry, the purge config fix, en + hi strings; tests for pre-flight, server finalize and tenant isolation.
3. Session 7 (B2): D3 and D6. Session 9 (B3): D4 with the Windows test.
4. Session 15 (B4): D5 and the D8 store, after open questions 1 and 2. Session 17 (B5): D7.
5. Before any of these flags is ON on UAT: Legal and DPO sign-off on D8, and Nitin's go.

---

## Open questions for future ADRs

1. **Store for Airpay:** the Moodle file store on the new server (hosted in India?) or S3 `ap-south-1`; who holds the keys (a server role, preferably).
2. **Legal and DPO:** the SPDI reading; consent or employment legitimate use for employees; whether the DPDP Rules 2025 one-year log duty and CERT-In's 180 days cover only the access and processing logs (kept that long) or also the recordings (30 and 90 days).
3. **Candidate data after the video goes:** keep account, attempt and verdict 180 days, then anonymise (proposed); HR's hand-off format.
4. **Audio** in Proctored hiring sittings (off in v1).
5. **Reviewers:** HR or L&D for hiring, turnaround, a second reviewer before "fail".
6. **IT:** SEB on employee laptops; office egress ranges and Browser Exam Keys.
7. **Mobile (ADR-040):** Standard quizzes in the app with app-side hardening, or web only.
8. **B6:** if a live-proctoring vendor is bought: data terms, India residency, and its place among the levels.

---

## Related

- Plan: `docs/plans/2026-11-19-POST-DEMO-PLAN.md` §4B (B0 to B6), §5.1 (sessions 4, 7, 9, 15, 17), §6a (D8 to D11, D29).
- ADR-002 (flags), ADR-017 (user types, amended), ADR-031 (tenant scope), ADR-033 (no core edits), ADR-034 D6 (DPDP posture, sub-processors, CERT-In), ADR-035 (erasure), ADR-036 (external-trainer type, same pattern), ADR-040 (mobile), ADR-041 (vendor patches; none needed).
- Code: `local/sentientia_proctoring/` (`README.md`, `classes/session_manager.php`, `classes/privacy/provider.php`, `classes/task/purge_old_recordings.php`, `amd/src/`); `mod/quiz/accessrule/sentientia_proctoring/rule.php`; core `mod/quiz/classes/local/access_rule_base.php`, `mod/quiz/classes/quiz_attempt.php`, `mod/quiz/accessrule/seb/classes/`.
- External (plan §7): Moodle Safe Exam Browser docs, safeexambrowser.org, MediaPipe face detector (web), Window Management API, DPDP Rules 2025, SPDI vs DPDP.
