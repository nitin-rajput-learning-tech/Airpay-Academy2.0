# State Card — `local_airpay_privacy`

**Component:** `local_airpay_privacy`
**Version:** `2026052001` / `1.0.1`
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. GDPR / DPDP request hub.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Privacy request hub — receives data-export / data-deletion requests
from users and orchestrates the response across every other plugin's
privacy provider. Pairs with Moodle's built-in `tool_dataprivacy`
flow but adds an Airpay-specific consent-log table for marketing /
analytics opt-ins.

## DB tables (2)

| Table | Purpose |
|-------|---------|
| `local_privacy_requests` | Per-user data-export / data-deletion request rows |
| `local_privacy_consent_log` | Append-only consent ledger (marketing opt-in, analytics opt-in, etc.) |

## Capabilities (2)

`local/airpay_privacy:` `view`, `manage`.

## Feature flags

None registered.

## Key files

```
local/airpay_privacy/
├── version.php                                  2026052001 / 1.0.1
├── README.md
├── index.php                                     Privacy request dashboard
├── styles.css
├── classes/
│   └── privacy_manager.php                       Request orchestrator
├── db/
│   ├── install.xml                              2 tables
│   └── upgrade.php
├── templates/
└── lang/
    ├── en/local_airpay_privacy.php
    └── hi/local_airpay_privacy.php
```

## Tests

None at the plugin level. Per-plugin privacy providers are tested in
their own `tests/privacy/provider_test.php` files.

## Open items

- [x] PHPUnit for `privacy_manager::process_deletion()` - tests/privacy_manager_test.php (2026-09-24)
- [ ] Per-customer consent banner customisation
- [ ] Auto-expire stale data-export request files
- [ ] Behat coverage of the request dashboard
- [ ] Integration audit — verify every `local_airpay_*` and
      `local_sentientia_*` plugin has a privacy/provider.php class
- [ ] Reference card: how to extend Moodle's privacy provider
      pattern with Airpay-specific consent records

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-24 - Erasure asks every Sentientia provider; admin panel fixed (1.0.2, 2026092400)

**Defect.** `process_deletion()` erased a hand-kept list of eight tables and marked the request
`completed`. Every other table a Sentientia plugin keeps about a person survived: the WhatsApp send
log (each row holds the mobile number), cart credits, calendar feed tokens, the agent audit and more.
It is the 2026-09-22 defect again (a success the code did not achieve), just outside the list that
fix touched.

**Fix.** A new Step 0 calls `delete_data_for_user()` on every `local_sentientia_*` privacy provider
(`erasure_providers()`, not this plugin's own), passing exactly the contexts the provider reports.
Most use the system context; the calendar uses the user context. Core providers are deliberately
not called: this flow keeps anonymised learning records for audit. A provider that throws is added
to the same `$missing` list as an absent table, so the request reads `partial` and the note names
it. The Step 2 `$missing = []` reset, which would have wiped that evidence, is gone.

**Admin panel (index.php)**
- Approve called `process_deletion($reqid)` without the required `$adminid`, so every click died
  with an ArgumentCountError. It now passes `$USER->id`.
- Approve and Reject act only on a `pending` request. A replayed link can no longer re-run an
  erasure or overturn a decision.
- After Approve the page reads the stored status back. A partial erasure gets an error
  notification instead of "processed successfully".
- Each status is counted explicitly. "Rejected" was total minus pending minus completed, so every
  partial request was shown as rejected. There is a new "Incomplete" tile.
- Partial rows show an Incomplete badge, the note, and "Needs manual follow-up".
- A guard comment at the gate: the request list is not tenant-scoped, so `:manage` must not be
  granted to tenant admins until it is.

**Tests.** `tests/privacy_manager_test.php` seeds rows in five tables owned by four plugins and
asserts on the rows. It also checks that a substituted failing provider
(`tests/fixtures/failing_erasure_provider.php`) gives `partial` with the provider named in the note,
while everything else is still erased. Strings added in en and hi.

**Same day, after the 38-provider audit.** Step 0 calls a provider's `anonymise_data_for_user(approved_contextlist)`
when it has one, and `delete_data_for_user()` otherwise. The method is duck-typed (`method_exists`), so no plugin
depends on this one. Learning-path, classroom, compliance-report, xapi and proctoring implement it. They keep
completions, attendance, exemptions, cmi5 attempts and proctoring verdicts keyed to the anonymised user row, as this
flow has always promised, and still erase the personal data around them. Before this, Step 0 would have deleted those
records, a regression from the old eight-table behaviour. The audit also found scope bugs in providers that Step 0 now
reaches (xapi, evaluation, WhatsApp, manager, courses, aiquiz), each fixed in its own plugin.
`tests/erasure_scope_test.php` seeds another person's rows next to the subject's and asserts that they survive.

## 2026-09-24 - Erasure review follow-up

Review follow-ups. Approve on a request that is not an account deletion, or whose user row is gone, now says nothing changed (`erasurenotapplicable`, en + hi) instead of reporting an erasure. Only a stored status of 'partial' gives the 'some data could not be erased' message. The Step 0 comment no longer claims the coverage test sees tables created at runtime or only in upgrade.php. Test fixes: the partial-erasure test expects its `debugging()` call. `erasure_scope_test` adds a proctoring reviewer case and a courses decider case.
