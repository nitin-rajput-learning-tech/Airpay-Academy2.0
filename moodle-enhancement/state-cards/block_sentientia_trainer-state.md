# State Card — `block_airpay_trainer`

**Component:** `block_airpay_trainer`
**Version:** `2026041600` / `1.0.0`
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Replaces BizLMS `block_trainerdashboard`.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Dashboard block for ILT trainers. Lists the 10 most recently created
classroom sessions where the current user is the assigned trainer
(`local_airpay_classroom.trainerid = $USER->id` with `status = 1`).

A BizLMS fallback path (`local_classroom`) is retained so the block
continues to render during the BizLMS → airpay-classroom transition;
once that transition is complete (Phase 7) the fallback can be removed.

## DB tables

None — read-only consumer of `local_airpay_classroom` (or fallback
`local_classroom`).

## Capabilities (`db/access.php`)

| Capability | Purpose |
|------------|---------|
| `block/airpay_trainer:addinstance` | Add to a page (admin / teacher) |
| `block/airpay_trainer:myaddinstance` | Add to personal dashboard |

## Feature flags

None registered.

## Key files

```
blocks/airpay_trainer/
├── version.php                                 2026041600 / 1.0.0
├── block_airpay_trainer.php                    Block class — init + get_content
├── dashboard.php                                Stand-alone trainer dashboard page
├── db/access.php                                2 capabilities
└── lang/en/block_airpay_trainer.php             pluginname + notrainings + add-instance strings
```

## Applicable contexts

`my => true`, `site-index => true`. Block is hidden inside courses
(would render duplicate info).

## Tests

None. Coverage lives in `local_airpay_classroom` PHPUnit suite which
exercises the underlying queries.

## Open items

- [ ] Hindi `lang/hi/block_airpay_trainer.php` (parity drive currently
      sweeps every plugin; this block is on the list)
- [ ] Add filter for upcoming-vs-past sessions
- [ ] Add count + "view all" footer link → `/local/airpay_classroom/mysessions.php`
- [ ] Remove BizLMS `local_classroom` fallback once Phase 7 retires it
- [ ] PHPUnit smoke test (silent-hide on no-sessions, list rendering)

## State card created — 2026-05-24

Initial state card for the trainer dashboard block. Plugin has been
live since 2026-04-16; created now as part of the P1 state-card pass.
