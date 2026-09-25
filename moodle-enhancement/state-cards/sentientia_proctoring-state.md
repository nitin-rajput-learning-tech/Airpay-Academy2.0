# State Card — `local_airpay_proctoring`

**Component:** `local_airpay_proctoring`
**Version:** `2026052201` / `1.0.3`  (+Goal A Bug #10 WS-contract alignment)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Engine for `quizaccess_airpay_proctoring`.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

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
├── version.php                                   2026052201 / 1.0.3
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
└── (no tests/ directory yet)
```

## Tests

None at the plugin level. Coverage is on
`quizaccess_airpay_proctoring` (13 methods covering the mod_quiz
integration). PHPUnit for the engine layer is on the P1 backlog.

## Open items

- [ ] PHPUnit for `session_manager` + `analyzer/` (priority)
- [ ] S3 chunk-replay tool for reviewers
- [ ] Live face-detection feedback in `proctor.js` (today: post-attempt
      AI only)
- [ ] Per-tenant identity-check tier (light / heavy)
- [ ] Audit trail of reviewer overrides
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
