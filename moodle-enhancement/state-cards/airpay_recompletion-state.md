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
