# local_sentientia_core

The **Sentientia Core** layer — the first plugin of the ADR-018 independence
program (Wave 2). It owns Sentientia-level abstractions that sit *above* the
BizLMS heritage, so the rest of the product can stop touching BizLMS internals
directly.

**Status:** `MATURITY_ALPHA`, `0.1.0-alpha`. Scaffold only — the seam exists;
the ~24 existing `open_path` call sites are **not** migrated onto it yet (that's
a separate, staged step). Design: `docs/adr/ADR-019-sentientia-core-tenant-identity.md`.

## What's here

### `tenant_identity` — the tenant-resolution seam
The single place the rest of Sentientia should resolve a user's tenant, instead
of reading `$USER->open_path` directly (the hard coupling in
`docs/DEPRECATION-SCHEDULE.md` row 7).

```php
use local_sentientia_core\tenant_identity;

$root = tenant_identity::root_for_user($user);        // e.g. 77
$root = tenant_identity::root_for_current_user();     // 0 if logged out
```

Behind the default-ON setting **`tenant_identity_legacy`** it delegates to the
legacy BizLMS parser (`local_airpay_core\tenant`), so behaviour is identical to
current production. When a future wave builds the Sentientia tenant registry,
flipping the setting OFF switches the source — and until then the OFF path
safely falls back to legacy, so it can never break authentication.

## Why a default-ON flag (not a hard cutover)
ADR-018's governing rule: **customer-zero (Airpay) never sees a regression.**
The seam ships additive + reversible; the legacy resolver is the default until a
migration deliberately flips it, rehearsed on a clone DB first.

## Settings
Site administration → Plugins → Local plugins → **Sentientia Core** →
*Resolve tenant from BizLMS open_path (legacy)* (default ON).

## Not in this scaffold (later waves, human-gated)
- Migrating the existing `open_path` callers onto `tenant_identity` (staged).
- The Sentientia tenant **registry** table + admin UI (Wave 4 — replaces the
  hardcoded `local_airpay_core\tenant::VALID_TENANTS`).
- `local_costcenter` → `local_sentientia_org` org-hierarchy migration (Wave 3).
