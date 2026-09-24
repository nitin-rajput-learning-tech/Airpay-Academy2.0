# State Card — `local_airpay_recompletion`

**Component:** `local_airpay_recompletion`
**Version:** `2026052001` / `1.1.1`  (+P1 #53 Hindi pack)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Periodic course re-completion engine.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

For mandatory compliance courses that need to be re-completed every N
months (cyber security every 12 months, KYC/AML every 6 months, etc.),
this plugin owns:
- Rule definitions (course, period, audience filter, behaviour)
- A reset engine that wipes per-user completion data on the due date
- An append-only audit trail of every reset

Resets fire after a cron walker compares last-completion timestamps
against the per-rule period.

## DB tables (2)

| Table | Purpose |
|-------|---------|
| `local_airpay_recompletion_rules` | Rule definitions (course, period_days, audience filter, on-reset action) |
| `local_airpay_recompletion_history` | Append-only reset audit (user, course, reset reason, before/after state) |

## Capabilities (3)

`local/airpay_recompletion:` `view`, `manage`, `reset`. The dedicated
`:reset` cap lets compliance run an ad-hoc reset without granting full
rule-edit rights.

## Feature flags

None registered.

## Key files

```
local/airpay_recompletion/
├── version.php                                   2026052001 / 1.1.1
├── README.md
├── settings.php
├── index.php                                      Rule list
├── edit.php                                       Edit rule form
├── history.php                                    Reset audit log
├── cli/                                            Manual run + replay
├── classes/
│   ├── recompletion_engine.php                   Reset state machine + audit writer
│   ├── event/                                     Audit events
│   ├── task/                                      Scheduled cron walker
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                2 tables
│   ├── upgrade.php
│   └── access.php                                 3 capabilities
├── templates/
├── lang/
│   ├── en/local_airpay_recompletion.php
│   └── hi/local_airpay_recompletion.php           (100% parity post-P1 #53)
└── tests/                                         1 PHPUnit class / 7 methods
```

## Tests

1 PHPUnit class, 7 methods. Covers the reset state machine + audit
write.

## Open items

- [ ] Cohort-scoped rules (today: tenant + course only)
- [ ] Per-rule pre-reset notification (warning email N days before reset)
- [ ] Behat coverage of the rule editor
- [ ] PHPUnit extension covering the cron task itself (today: engine
      only)
- [ ] Inline rule status on the course-admin page (preview next reset
      date)

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.


## 2026-09-24 - DPDP erasure detached the reset audit log from the person

**Defect.** `local_sentientia_privacy\privacy_manager::process_deletion()` (the DPDP
right-to-erasure flow) calls a Sentientia provider's `anonymise_data_for_user()` when it has
one and `delete_data_for_user()` otherwise. With no anonymise hook here, every approved erasure
redacted the person's `local_sentientia_recompletion_history` rows to `userid = 0`. A reset
deletes the course completion, grades and quiz attempts, so that row (`previous_timecompleted`)
is the only surviving evidence of each earlier compliance cycle the person completed. The DPDP
flow promises to keep such records against the user row it anonymises in place; redacting them
pooled every erased person's history under 0.

**Fix.** `privacy\provider::anonymise_data_for_user()` added. Table by table:
- `local_sentientia_recompletion_history`, subject column `userid`: DPDP flow KEEPS it as-is.
  Core erasure (`delete_data_for_user()`) still redacts it to 0, as it always has.
- same table, actor column `reset_by_userid` (the admin who pressed reset; NULL = cron):
  anonymised to 0 by both erasures, never used to delete a row. It was previously undeclared
  and untouched, and an admin who had only reset other people's completions was not even
  reachable (`get_contexts_for_userid()` looked at `userid` only). Now declared in metadata,
  found by `get_contexts_for_userid()` / `get_users_in_context()`, and exported (course,
  reason, time only - never the other person's id).
- `local_sentientia_recompletion_rules`: no user column; untouched.
- `get_users_in_context()` no longer reports the redacted `userid = 0` as a user.
- Raw `UPDATE` SQL replaced with `$DB->set_field()` / `set_field_select()`.

New strings (en + hi): `privacy:metadata:..._history:reset_by_userid`,
`:previous_timecompleted`, `:timecreated`, `privacy:export:resets_performed`.

**Test.** `tests/privacy_anonymise_test.php`: history survives `anonymise_data_for_user()`
keyed to the subject, the actor is anonymised, cron rows stay NULL; `delete_data_for_user()`
still leaves nothing naming the subject while the audit row survives; an actor-only admin is
reachable and 0 is never reported as a user. Written, not yet run (shared test DB being
rebuilt). No version bump (class + lang change only). Both trees.
