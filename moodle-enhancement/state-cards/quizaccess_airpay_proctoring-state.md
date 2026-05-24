# State Card — `quizaccess_airpay_proctoring`

**Component:** `quizaccess_airpay_proctoring`
**Version:** `2026052401` / `1.1.1`  — Phase B.12 hotfix (defensive `table_exists()` in upgrade.php)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Companion to `local_airpay_proctoring`.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Standard `mod_quiz` access-rule plugin that wires `mod_quiz` into the
`local_airpay_proctoring` engine. Mirrors the upstream `quizaccess_seb`
(Safe Exam Browser) integration pattern.

When a teacher ticks "Require proctoring" on the quiz settings form:
- Pre-attempt: a "Start proctored attempt" button replaces the default
  attempt button. It launches the consent + identity-capture flow in
  `local_airpay_proctoring`.
- During attempt: `proctor.js` runs in-page and emits events (focus
  loss, multiple faces, etc.) to the proctoring engine.
- Post-attempt: server-side finalize runs AI analysis + writes a
  reviewer queue row.

## DB tables (1)

| Table | Purpose |
|-------|---------|
| `quizaccess_airpay_proctor` | One row per quiz × proctoring-enabled toggle. Stores per-quiz proctoring settings (consent text, identity check level, event budgets). |

## Capabilities

None declared here. The plugin defers all access decisions to
`local_airpay_proctoring` capabilities.

## Feature flags

None registered.

## Key files

```
mod/quiz/accessrule/airpay_proctoring/
├── version.php                                     2026052401 / 1.1.1
├── README.md
├── rule.php                                        access_rule_base impl
├── db/
│   ├── install.xml                                 1 table
│   └── upgrade.php                                 (Phase B.12 hotfix: defensive table_exists())
├── lang/en/quizaccess_airpay_proctoring.php
└── tests/
    ├── rule_test.php                               9 methods
    └── upgrade_test.php                            4 methods (13 total)
```

## Tests

2 PHPUnit classes, 13 methods. `upgrade_test.php` was added with the
Phase B.12 hotfix to lock in the regression that motivated it.

## Open items

- [ ] Hindi `lang/hi/quizaccess_airpay_proctoring.php` (parity drive
      sweeps every plugin)
- [ ] Behat coverage of the quiz-edit form toggle
- [ ] Settings-form preview of the consent text (currently shown only
      at attempt time)
- [ ] Per-tenant "proctoring tier" (light vs. heavy event capture)
- [ ] Surface the AI verdict directly on the attempt-review screen
      (today: only on the proctor reviewer queue)

## State card created — 2026-05-24

Initial state card. Plugin shipped pre-2026-05; Phase B.12 hotfix is
the most recent touch. Created now as part of the P1 state-card pass.
The full proctoring engine lives in `local_airpay_proctoring` — this
plugin is intentionally thin (mod_quiz integration layer only).
