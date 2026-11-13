# State Card — `local_airpay_proctoring`

**Component:** `local_airpay_proctoring`
**Version:** `2026111300` / `1.0.7`  (privacy metadata fields, 2026-11-13; before that `2026111200` / `1.0.6`, decision A5 audit events, 2026-11-12; the previous version was `2026110300` / `1.0.5`, the 2026-11-03 full-name user search, which has no dated section here; see the dated sections below)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Engine for `quizaccess_airpay_proctoring`.
**Last refreshed:** 2026-11-13 (privacy metadata names every table's fields)

---

## Mission

Online proctoring engine — identity verification, in-attempt event
capture (focus loss, multiple faces, audio anomalies), recording chunk
storage in S3, AI analysis post-attempt, and a human reviewer queue.

Wired to `mod_quiz` via the companion access-rule plugin
`quizaccess_airpay_proctoring`. Both must be installed together.

## DB tables (5)

| Table | Purpose |
|-------|---------|
| `local_airpay_proctor_sessions` | One row per proctored attempt (links `quiz_attempts.id`) |
| `local_airpay_proctor_identity` | Identity verification results (selfie + government-ID hash) |
| `local_airpay_proctor_events` | Append-only attempt events (focus blur, face count change, etc.) |
| `local_airpay_proctor_recordings` | Pointers to recording chunks stored in S3 |
| `local_airpay_proctor_reviews` | Human reviewer decisions (cleared / flagged / rejected) |

## Capabilities (5)

`local/airpay_proctoring:` `attempt`, `viewattempts`, `review`,
`manage`, `bypass`. The `:bypass` cap is for designated test
administrators (lets them ignore the proctoring gate).

## Feature flags

None registered.

## Key files

```
local/airpay_proctoring/
├── version.php                                   2026111200 / 1.0.6
├── README.md
├── lib.php
├── admin.php                                      Admin operations
├── attempt.php                                    Pre-attempt consent + identity flow
├── review.php                                     Reviewer queue
├── cli/                                            Operations
├── classes/
│   ├── session_manager.php                       Session lifecycle
│   ├── notifier.php                              Reviewer notification dispatcher
│   ├── identity/                                  Identity verification module
│   ├── analyzer/                                  AI / heuristic analyzers
│   ├── external/                                  WS endpoints (attempt + review)
│   ├── task/                                      Scheduled tasks (finalize + purge)
│   ├── event/                                     session_flagged + review_submitted (2026-11-12)
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                5 tables
│   ├── upgrade.php
│   ├── access.php                                 5 capabilities
│   └── services.php                               WS function registry
├── amd/                                           proctor.js + analyzer client
├── templates/
├── lang/
│   ├── en/local_airpay_proctoring.php
│   └── hi/local_airpay_proctoring.php
└── tests/
    ├── tenant_scope_test.php                      ADR-031 tenant isolation (2026-09-25)
    └── event/                                     session_flagged_test + review_submitted_test (2026-11-12)
```

## Tests

At the plugin level: `tests/tenant_scope_test.php` (`@group tenant_isolation`, ADR-031: queue badge, session
access, flag notification recipient) and, since 2026-11-12, `tests/event/session_flagged_test.php` and
`tests/event/review_submitted_test.php`, which drive `session_manager::finalize()`, `submit_review()` and the
`flag_session` web service. The mod_quiz integration is covered on `quizaccess_airpay_proctoring` (13 methods).

## Open items

- [ ] PHPUnit for `session_manager` + `analyzer/` - partly done: `finalize()`, `submit_review()`,
      `require_session_access()` and `flag_recipient()` are covered (tenant_scope_test + tests/event); the analyzer
      scoring, `start_session()` edge cases, `record_event()` / `register_chunk()` and the purge task are not
- [ ] S3 chunk-replay tool for reviewers
- [ ] Live face-detection feedback in `proctor.js` (today: post-attempt
      AI only)
- [ ] Per-tenant identity-check tier (light / heavy)
- [x] Audit trail of reviewer overrides (2026-11-12, decision A5: the `review_submitted` and `session_flagged`
      events reach the standard log; see the dated section below)
- [ ] Behat coverage of the consent flow
- [ ] Inline AI verdict on the reviewer screen (today: queue-row badge
      only — clicking opens analyzer detail)

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases. Created
now as part of the P1 state-card pass. Companion to
`quizaccess_airpay_proctoring` (which has its own card).


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-24 - Privacy provider fix (erasure audit)

(1) Recording rows were hard-deleted. They are the only pointer to the S3 chunks, and the purge task is the only code that deletes S3 objects, so the video stayed in S3 for good. Recordings are now expired (`retain_until` in the past) and the next daily purge deletes the objects. (2) New `anonymise_data_for_user()` for the DPDP flow: it deletes identity (face-match), events and recordings; it keeps the session and review (the exam-integrity verdict on a kept quiz attempt) and unlinks `identity_id` / `consent_given_at`.

Found by a read-only audit of all 38 Sentientia privacy providers, run because `local_sentientia_privacy\privacy_manager::process_deletion()` now calls every one of them. Class change only: no version bump. Covered by `local_sentientia_privacy\erasure_scope_test` / `privacy_manager_test`.

## 2026-09-24 - Erasure review follow-up

(1) `anonymise_data_for_user()` no longer nulls `consent_given_at` on kept sessions: it is the lawful-basis record for the kept exam-integrity verdict. (2) A REVIEWER's data was never found: `get_contexts_for_userid()` and `get_users_in_context()` now include `reviews.reviewer_userid`, and both erasure paths anonymise it to 0 while keeping the candidate's review.

## 2026-09-25 - ADR-031: badge scoped, no-tenant reviewers fail closed (1.0.4, 2026092500)

Sweep hit `local/sentientia_proctoring:review` (CONFIRMED, low). The "Review queue (N)" badge in `lib.php`
counted every tenant's flagged sessions. It now uses `tenant::sql_filter()`, so it equals the queue the
reviewer actually sees.

`session_manager::require_session_access()` now backs flag_session, submit_review, get_attempt and
`attempt.php`. A viewer who is not cross-tenant must have a tenant of their own. `tenant::require_access(0)`
used to match a no-tenant reviewer to every session stamped costcenterid 0.

The `:review` default grant is unchanged: it is a tenant-scoped reviewer capability by design.

Tests: `tests/tenant_scope_test.php` (`@group tenant_isolation`).

## 2026-09-25 - ADR-031 wave-1 review follow-up (no version change; stays 2026092500)

The flag notification (candidate user id, quiz id, risk score) went to the one site-wide `default_reviewer` whatever the session's tenant, so a reviewer in one tenant was told about another tenant's candidates, whose sessions they cannot open. `session_manager::flag_recipient()` now sends it only when the default reviewer passes `require_session_access()` for that session; otherwise nobody is notified. The shipped default (userid 2, the site admin) is unchanged. Per-tenant reviewer routing, so that other tenants' flags reach someone, is still open and needs a settings design. Test: `test_flag_notification_goes_only_to_a_reviewer_of_that_tenant`.

## 2026-11-12 - decision A5: the two audit events the platform names (1.0.6, 2026111200)

`\local_sentientia_platform\audit_log::SENSITIVE_EVENTS` has listed `\local_sentientia_proctoring\event\session_flagged`
and `\local_sentientia_proctoring\event\review_submitted` since the audit dashboard was built, but neither class
existed, so no flag and no verdict ever reached the audit trail. Nitin approved building them on 2026-11-12
(decision A5 in `docs/cutover/NITIN-DECISIONS-2026-11-12.md`). No schema, capability or flag change;
`local_sentientia_platform` is untouched.

- `classes/event/session_flagged.php` (crud u, LEVEL_OTHER, object `local_sentientia_proctor_sessions`, relateduserid
  the candidate). `other`: `quizid`, `source` (auto | manual). Triggered once, after the flag is stored:
  `session_manager::finalize()` when the analyzer's verdict is not clean (source auto), and `external\flag_session` when
  a reviewer flags a session that was not already flagged (source manual). A clean finish, a finalize() replay and a
  re-flag trigger nothing.
- `classes/event/review_submitted.php` (same shape; userid the reviewer). `other`: `reviewid`, `quizid`, `decision`
  (clean | warn | fail). Triggered once by `session_manager::submit_review()` after the review row and the reviewed
  status are stored; an unknown decision throws before anything is written. The reviewer's note is never carried.
- Context: `session_manager::event_context()` - the quiz's module context, or the system context when the quiz is gone
  (or quizid is 0). No risk score, name or free text in either event.
- Privacy: the provider now links the `logstore` plugin type (`add_plugintype_link('logstore', ...)`, string
  `privacy:metadata:logstore`, en + hi), as `tool_log` itself declares it. logstore is a plugin type (a `tool_log`
  subplugin), not a core subsystem: a first draft used `add_subsystem_link('core_logstore', ...)`, which core's
  `core_privacy` metadata test (`is_core_subsystem()`) would fail.
- Strings: `eventsessionflagged`, `eventreviewsubmitted`, `privacy:metadata:logstore` (en + hi).
- Tests (written, not run in this session): `tests/event/session_flagged_test.php`,
  `tests/event/review_submitted_test.php` - real code paths under `redirectEvents()`: fires once with the right
  objectid / relateduserid / userid / context; nothing on a clean finish, a replay, a re-flag, an unknown decision,
  a non-owner finalize or an out-of-tenant reviewer. The system-context fallback is tested both for a session with
  no quiz (quizid 0) and for a quiz deleted with `course_delete_module()` while the attempt was open.

Open: `flag_session` still writes `timemodified` when the session is already flagged (no event, no status change);
`submit_review()` does not check that the session is flagged before accepting a verdict (unchanged behaviour).

## 2026-11-13 - privacy metadata names every table's fields (1.0.7, 2026111300)

`get_metadata()` declared all five tables (`sessions`, `identity`, `events`, `recordings`, `reviews`) with an empty
field list. Under DEBUG_DEVELOPER, core's `database_table` constructor then calls `debugging()`, which:
- errored `local_sentientia_platform\privacy_coverage_test::test_declared_providers_cover_the_tables_they_own`
  ("Unexpected debugging() call");
- made core's `privacy/tests/privacy/provider_test.php::test_table_coverage` risky for EVERY component's dataset,
  because that test walks every provider's metadata.

Found while running PHPUnit for ADR-035 (`local_sentientia_privacy` 2026111300). The fix declares each table's
personal-data columns: candidate, quiz/attempt, status, consent time, risk score, AI and human verdicts, times;
identity provider, match score, pass, error; event type, severity, payload; recording kind, S3 key, size, duration,
retain-until; reviewer, decision, note. Ids, `costcenterid` and `identity_id` are not personal data and are left out.
28 strings, en + hi (121/121); `userid`, `sessionid` and `timecreated` share one key each. kn/mr/sw fall back to en
(they were already 3 strings behind). No behaviour change: export, delete and anonymise are untouched.

