# local_sentientia_proctoring

Proctoring engine for Moodle quizzes: consent and an identity confirmation in the quiz's own
pre-start check, in-browser event logging on every page of the attempt, a rule-based risk score
computed on the server when the attempt ends, and a human reviewer queue. It pairs with the
`quizaccess_sentientia_proctoring` access rule, which attaches it to a quiz.

> **Status (B1, session 4 of the post-demo plan, 2026-11-20): behind the flag
> `sentientia.proctoring.enabled`, default OFF everywhere.** With the flag OFF every quiz is stock
> Moodle: "Require proctoring" is not on the quiz form, no pre-start check is added, nothing is loaded
> on the attempt pages and no session is opened. It is not live anywhere (Sentientia is not in
> production; live airpay.academy is still BizLMS), and switching the flag on UAT is Nitin's call. B1 is
> the foundation only (ADR-038 D2): no camera, no blocking, no automated identity match. Until B1 is
> deployed and the flag is ON, use the core quiz settings in
> `moodle-enhancement/docs/guides/ASSESSMENT-INTEGRITY-ADMIN-GUIDE.md`.
>
> **Before B1 nothing worked end to end.** Moodle never loaded the access rule (its class did not have
> the name core looks for), so "Require proctoring" never appeared on the quiz form. The consent step
> was loaded only on the attempt page, monitoring stopped after the first page, and nothing scored a
> session when an attempt ended. An earlier version of this README also described live webcam and
> screen recording uploaded to S3 through presigned URLs, and "AI behaviour flagging". Neither exists:
> see "What does not exist".

| Field | Value |
|---|---|
| Component | `local_sentientia_proctoring` |
| Version | `2026112000` (1.1.0, B1) |
| Requires | `2024042200` |
| Maturity | `MATURITY_STABLE` (the code label; the feature is OFF by default, see the status above) |
| Depends on | `local_sentientia_org`, `local_sentientia_privacy`, `local_sentientia_platform` |
| Feature flag | `sentientia.proctoring.enabled` (B1, default OFF, read for the learner's customer and tenant). `db/feature_flags.php` also registers the reserved flags of B2 to B5 (`sentientia.assessment.secure_mode.enabled`, `sentientia.assessment.seb_preset.enabled`, `sentientia.proctoring.recording.enabled`, `sentientia.assessment.candidates.enabled`), all default OFF; no code reads them yet |

## What the code does (flag ON, quiz with "Require proctoring" = Yes)

Session states on the B1 path:
```
(pre-start check passed) -> recording -> finished            (clean)
                                      -> flagged -> reviewed  (warn or fail)
```
The states `new`, `consenting` and `verifying` belong to the pre-B1 browser flow (`amd/src/consent.js`),
which nothing loads any more.

1. **Pre-start check.** When the learner presses Start, or returns to an open attempt that has no
   active session, the quiz's own pre-start form shows a notice of what is recorded and two required
   tick boxes: agreement to the monitoring, and "I am (name) and I will take this attempt myself".
   Cancel means the attempt does not start. The Start button is never hidden, and teachers' previews
   are never proctored. `quiz_integration::pass_preflight()` stores the consent time on a session
   (status `recording`) and logs `consent_given` (with the notice version) and `identity_confirmed`
   (method `attestation`).
2. **Attempt binding.** The new attempt claims the learner's waiting session: the `attempt_started`
   observer does it, and the access rule does it again on the attempt page if the observer did not
   run. A consent older than one hour is not claimed; the learner then sees the pre-start check again.
3. **Monitor on every page.** The access rule loads `amd/src/proctor.js` on each page of the open
   attempt (attempt and summary pages) with the session the server found for that attempt, and the
   page shows a banner. The monitor logs `page_shown`, `tab_switch`, `window_blur` and `window_focus`,
   `fullscreen_exit` and `clipboard_paste`. It only **logs**: nothing is blocked, nothing forces
   fullscreen, and a learner who blocks the script or uses a second device is not seen (gaps in
   `page_shown` show pages where the monitor did not run). The server (`report_event`) accepts only
   these event types, sets the severity and the time itself, keeps only the page number from the
   payload, refuses a closed session or another user's, and stores at most 2000 events per session.
4. **Finalize on the server.** The `attempt_submitted` and `attempt_abandoned` observers
   (`db/events.php`, `classes/observer.php`) close the session and score it
   (`session_manager::close_session()`). `attempt_becameoverdue` only notes the event on the session,
   because the learner may still submit in the grace period. The observers run after the quiz's own
   transaction commits, so a failure never blocks a submission (it is reported with `debugging()` and
   the session stays open). A browser call to `finalize` changes nothing while the session's attempt is
   open. Scoring uses fixed weights (`classes/analyzer/risk_analyzer.php`: 0-30 clean, 31-60 warn, 61+
   fail). A clean session ends `finished`; any other is `flagged` and the default reviewer is told, in
   the reviewer's own language.
5. **Review.** Flagged sessions appear in a tenant-scoped reviewer queue (`review.php`,
   `attempt.php`); a reviewer records clean / warn / fail and the learner is told. The
   `session_flagged` and `review_submitted` events reach the audit log (decision A5, 2026-11-12).

## What does not exist

- **No recording.** B1 does not open the camera, the microphone or the screen. Nothing is recorded,
  no presigned URL is generated and nothing is uploaded to S3. The `upload_chunk` service and the
  `local_sentientia_proctor_recordings` table are unused plumbing for B4
  (`sentientia.proctoring.recording.enabled`, reserved).
- **No identity verification.** The learner confirms their identity with a tick box. There is no
  photo, no ID document and no face match (decision D10: no automated ID-to-face matching in v1). B1
  removed the `aws` (AWS Rekognition) provider: a site still set to `aws` gets an error from
  `submit_identity`, and no photo leaves the server. The pre-B1 photo service (`submit_identity`,
  `mock` provider only) has no caller.
- **No AI analysis.** The risk score is a fixed weighted count of browser events.
- **No face detection.** `face_lost`, `multiple_faces` and `mic_noise` have weights but nothing
  produces them (the server refuses them from the browser).
- **No blocking.** Copy, paste, tab switches and fullscreen exits are logged, not prevented (B2,
  session 7).
- **No AWS connection.** The local site has no AWS keys (checked 2026-11-19, along with 0 proctored
  quizzes and 0 sessions); no AWS set-up is recorded for UAT. The S3 bucket name in settings is a
  default, not a provisioned bucket.

## Capabilities

| Capability | Default archetypes | Purpose |
|---|---|---|
| `local/sentientia_proctoring:attempt` | user, student | take a proctored quiz |
| `local/sentientia_proctoring:viewattempts` | manager, editingteacher | list and open attempts |
| `local/sentientia_proctoring:review` | manager | review flagged sessions |
| `local/sentientia_proctoring:manage` | none (site admin) | configure the plugin |
| `local/sentientia_proctoring:bypass` | none (site admin) | for test administrators |

## Tables (5)

No schema change in B1: the session's existing `attemptid` and `consent_given_at` columns carry the
attempt binding and the consent time.

| Table | Purpose |
|---|---|
| `local_sentientia_proctor_sessions` | One row per proctored attempt |
| `local_sentientia_proctor_identity` | Identity match score and pass/fail (photos are never stored). Written only by the pre-B1 photo service, which has no caller |
| `local_sentientia_proctor_events` | Per-session event log: the monitor's events and the server's own (`consent_given`, `identity_confirmed`, `attempt_linked`, `attempt_submitted`, `attempt_abandoned`, `attempt_overdue`, `session_end`) |
| `local_sentientia_proctor_recordings` | Pointers to recording chunks (unused: nothing records, see above) |
| `local_sentientia_proctor_reviews` | Human reviewer decisions |

## Web services (12, names as registered in `db/services.php`)

| Function | Purpose |
|---|---|
| `local_sentientia_proctoring_start_session` | Create a session row (pre-B1 browser flow; no caller since B1) |
| `local_sentientia_proctoring_give_consent` | Candidate accepts the terms (pre-B1 browser flow; no caller since B1) |
| `local_sentientia_proctoring_submit_identity` | ID photo + selfie to the `mock` verifier (rate-limited, MIME-sniffed; no caller since B1) |
| `local_sentientia_proctoring_report_event` | The monitor's events on the caller's own active session (types, severity and time set by the server; at most 2000 per session) |
| `local_sentientia_proctoring_upload_chunk` | Register a recording chunk (no caller; for B4) |
| `local_sentientia_proctoring_finalize` | Close and score a session (no caller; changes nothing while the session's attempt is open, the server closes it) |
| `local_sentientia_proctoring_list_attempts` | Attempt list (tenant-scoped) |
| `local_sentientia_proctoring_get_attempt` | One attempt (tenant-scoped) |
| `local_sentientia_proctoring_list_review_queue` | Reviewer queue (tenant-scoped) |
| `local_sentientia_proctoring_flag` | Manual flag for review |
| `local_sentientia_proctoring_submit_review` | Reviewer decision (tenant-scoped) |
| `local_sentientia_proctoring_compliance_report` | Aggregate report (tenant-scoped) |

## Settings (Site administration > Plugins > Local plugins > Sentientia Proctoring)

| Setting | Default | Purpose |
|---|---|---|
| `aws_region` | `ap-south-1` | AWS region of the recording store |
| `aws_key`, `aws_secret` | empty | Credentials of the recording store, read by the purge task (never set on any environment today) |
| `aws_s3_bucket` | a placeholder name | bucket the purge task deletes from |
| `retention_days` | `90` | recording retention |
| `chunk_secs` | `30` | recording chunk length (unused) |
| `default_reviewer` | `2` | user id told about flagged sessions, only when that user may open the session's tenant |

B1 removed the identity provider choice (`provider`) and the face-match pass mark
(`match_threshold`) from the page (decision D10). A value stored before B1 stays in the config table;
`provider` = `aws` now makes `submit_identity` fail.

## Scheduled task

| Task | Schedule | Purpose |
|---|---|---|
| `\local_sentientia_proctoring\task\purge_old_recordings` ("Proctoring: delete recordings past their retention date") | 02:23 daily | Deletes expired recording objects from S3 (SigV4 DELETE) and marks the rows; skips when the AWS settings are incomplete. Since B1 it reads the `aws_key` and `aws_secret` the settings page stores (before B1 it read other names and always skipped). Has nothing to delete today. |

## Security hardening already in the code

- Tenant scope on every read path (ADR-031): `list_review_queue`, `list_attempts`,
  `compliance_report`, `get_attempt`, `submit_review`, `flag`, `attempt.php`, and the review-queue
  badge. A viewer with no tenant of their own sees nothing.
- The browser cannot choose or end a session: the attempt page is given the session the server found
  for the attempt, and `finalize` cannot close a session whose attempt is still open (B1).
- `report_event` takes only the monitor's event types on the caller's own active session with an open
  attempt; the server sets the severity and the time, keeps only the page number and caps a session at
  2000 events (B1).
- `register_chunk`, `record_event` and `finalize` check that the caller owns the session; strict
  `s3_key` pattern; size and duration bounds.
- `submit_identity`: rate limit, size cap, strict base64, magic-byte sniff.

## Tests

`tests/quiz_integration_test.php` (B1: flags registered default OFF, server-side close on submit and
abandon, overdue keeps the session open, `report_event` limits, a waiting session is never claimed by
another quiz or learner, purge settings names, no `aws` provider, privacy export, notification
language), `tests/tenant_scope_test.php` (`@group tenant_isolation`),
`tests/event/session_flagged_test.php`, `tests/event/review_submitted_test.php`. The access rule's
`tests/preflight_test.php` covers the pre-start check and the per-page monitor.
`cli/smoke_proctoring.php` exercises the pre-B1 service layer against a named test account; it does not
cover the B1 pre-start flow.

## Privacy

`classes/privacy/provider.php`: identity photos are never stored, only the score. B1 names the
personal fields of the sessions and events tables, adds the attempt, the consent time and every event
(type, severity, time, details) to the learner's export, and drops the AWS Rekognition external
location (no code path sends a photo there). Erasure expires recording rows so the purge task deletes
their objects, and deletes the user's sessions, reviews, identity and event rows (a reviewer's own
decisions are kept with the reviewer anonymised); the DPDP anonymise path deletes identity and event
rows but keeps the session and review (the integrity verdict on a kept quiz attempt) and keeps
`consent_given_at` as the lawful-basis record. Webcam recording, when B4 builds it, needs the DPDP
decisions in D10 (India region, 30 days for candidates, 90 for employees).

## What comes next

Post-demo plan section B (`moodle-enhancement/docs/plans/2026-11-19-POST-DEMO-PLAN.md`):
B1 foundation (session 4, this version), B2 secure assessment mode (session 7), B3 Safe Exam Browser
preset (session 9), B4 webcam record and review (session 15), B5 hiring candidate flow (session 17),
each behind its own default-OFF flag, with ADR-038 recording the three security levels.
