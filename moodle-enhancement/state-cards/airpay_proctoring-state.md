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
