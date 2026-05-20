# ADR-002 — Customer-Level Feature Flags

> **Status:** Accepted
> **Date:** 2026-05-20 (Session 2)
> **Decision-makers:** Nitin Rajput
> **Implementer:** Claude
> **Supersedes:** Nothing
> **Related:** [ADR-001](ADR-001-fork-strategy-and-product-pivot.md) (multi-customer architecture commitment)

---

## Context

ADR-001 committed to multi-customer architecture from Day 0: Sentientia LMS
will host multiple paying customers, each containing 1+ tenants. Today
Airpay is the only customer (with 3 tenants: Airpay, Public, ZEEA).

The current `local_airpay_core` Switchboard supports two scopes for feature
flags:

1. **Global** (`tenant_id = 0`) — applies to every tenant
2. **Tenant** (`tenant_id = 1, 77, 177`) — applies to one specific tenant

We need a third scope: **Customer** — applies to all tenants owned by one
customer, but not to tenants owned by a different customer.

For Airpay-as-only-customer this is functionally equivalent to "global", but
the moment a second customer joins, the difference matters: enabling
`commerce.crossTenantShare.enabled` globally would expose Customer 2's
content to Customer 1's tenants, which is unacceptable cross-customer leak.

---

## Decision

### 1. Schema: extend the existing table (single-table approach)

Add `customer_id INT(10) NOTNULL DEFAULT 0` to both:
- `local_airpay_feature_flags` (overrides)
- `local_airpay_feature_flag_audit` (history)

`customer_id = 0` means "applies to all customers" — semantically identical
to the existing `tenant_id = 0` "applies to all tenants" idiom. Existing
rows survive the migration unchanged because their implicit `customer_id`
defaults to 0.

The unique key changes from `(flag_key, tenant_id)` to
`(flag_key, customer_id, tenant_id)` — same row identity, one more dimension.

### 2. Resolution precedence (5 levels, most-specific wins)

For a user in customer `C` and tenant `T`:

```
1. (flag_key, customer_id=C, tenant_id=T)   ← MOST SPECIFIC: this tenant in this customer
2. (flag_key, customer_id=C, tenant_id=0)   ← customer-wide override
3. (flag_key, customer_id=0, tenant_id=T)   ← legacy tenant override (cross-customer)
4. (flag_key, customer_id=0, tenant_id=0)   ← global override
5. registered default (db/feature_flags.php)
6. false                                     ← FAIL-SAFE
```

Why this order:

- **(1) before (2):** a tenant-specific override within a customer is more
  specific than a customer-wide override — admin's explicit intent wins
- **(2) before (3):** customer scope is more meaningful than legacy
  cross-customer tenant scope in the multi-customer world; we honour the
  legacy rows but prefer the customer-aware ones
- **(3) preserved:** existing Airpay tenant-overrides keep working without
  any migration (rows stay at `customer_id=0`, lookups match at step 3)
- **(4) preserved:** existing global overrides keep working
- **(5)+(6):** unchanged from the existing resolver

### 3. Customer identity in Phase 0/1 — hardcoded constant

We do NOT yet build a full customers table or admin UI for managing
customers. Instead:

```php
namespace local_airpay_core;

class customer {
    public const AIRPAY = 1;  // customer-zero
    public const DEFAULT = 0; // "no customer scope" — used by legacy rows

    public static function current(): int {
        // Phase 0/1: every authenticated user is in customer 1 (Airpay).
        // Phase 2+: this method consults a customer-mapping table or
        //           derives from user's tenant ancestry.
        return self::AIRPAY;
    }
}
```

Rationale: the customer-mapping table is real work and unnecessary while
there is only one customer. The constant + helper preserves the API
contract so when we add Customer 2, only `customer::current()` changes —
no other code touched.

### 4. The new layer is feature-flag-gated

Per CLAUDE.md v5.0's "feature-flag-everything" mandate, this very feature
ships behind a flag:

```php
'sentientia.customer_level_flags.enabled' => [
    'default'     => false,   // off until verified
    'description' => 'Customer-level feature-flag layer in the Switchboard. ...',
],
```

When the flag is OFF:
- Resolver short-circuits steps (1) and (2) — only steps (3-6) run
- Switchboard hides the "Customer" section in the UI
- `feature_flags::set()` rejects writes where `customer_id > 0` with a clear error

When ON:
- Full 5-level resolution
- Switchboard exposes customer-scope tabs alongside tenant-scope tabs
- `set()` accepts customer-scoped writes

### 5. Switchboard UI

Add a "Customer scope" tab strip ABOVE the existing tenant tab strip:

```
┌──────────────────────────────────────────────────────────┐
│ Customer:  [ All customers ] [ Airpay (id=1) ]           │
├──────────────────────────────────────────────────────────┤
│ Tenant within customer:                                  │
│   [ Customer default ] [ Airpay ] [ Public ] [ ZEEA ]   │
├──────────────────────────────────────────────────────────┤
│ [ flag rows for the selected (customer, tenant) pair ]  │
└──────────────────────────────────────────────────────────┘
```

When `sentientia.customer_level_flags.enabled` = OFF, the top tab strip is
hidden and the Switchboard looks exactly as it does today.

### 6. Audit log extension

Audit table gains `customer_id` mirroring the overrides table. Every audit
row records which (customer, tenant) pair the change applied to. This
matters for SOC2 attestation and future per-customer audit-feed APIs.

---

## Consequences

### Positive

1. **Multi-customer ready** — adding Customer 2 is purely a data
   operation (insert rows with `customer_id=2`), no schema or resolver
   change required
2. **Zero migration risk** — existing rows keep `customer_id=0` and resolve
   identically to before. All 10 existing feature_flags_test.php tests
   continue to pass without modification.
3. **Feature-flagged rollout** — Nitin can ship this code dark, verify
   resolver semantics in PHPUnit + smoke tests, then flip the flag when
   confident
4. **Single table** = simpler queries, no JOINs, smaller code footprint
5. **Audit trail extends cleanly** — one new column, one new index

### Negative

1. **5-level resolution is more complex than 3-level** — mitigated by the
   precedence diagram + new tests covering every combination
2. **The `customer::current()` helper is a temporary hardcode** — when
   Customer 2 joins we MUST replace this with a real lookup (tracked as
   future work). A future ADR-008 will describe the customer table.
3. **The legacy "tenant override across all customers" semantic at step 3
   is potentially confusing** — but preserving it is the only way to keep
   Airpay's existing Switchboard rows working without data migration
4. **Switchboard URL complexity** — was `?tenant=N`, now `?customer=C&tenant=N`.
   We default `customer=0` for backwards compatibility with existing
   bookmarks.

### Neutral

1. **Per-row size grows by 4 bytes** (one INT column). Negligible.
2. **`local_airpay_feature_flag_audit` retention target (7 years)** is
   unchanged. Customer column survives every retention period.

---

## Alternatives considered

### Alt 1: Separate `local_airpay_feature_flags_customer` table
**Rejected** — requires two queries (or a JOIN) at every resolver call.
Doubles the maintenance surface. No real isolation benefit since both
tables hold the same kind of data.

### Alt 2: Encode customer into `tenant_id`
**Rejected** — would break the existing tenant id space (1, 77, 177 are
real costcenter IDs; we can't repurpose them for customers without a
migration). Also makes the SQL filters harder to read.

### Alt 3: Customer flags live in a config file, not the DB
**Rejected** — config files don't support audit logs, runtime toggling, or
per-environment overrides. We'd lose every benefit of the Switchboard.

### Alt 4: Skip customer scope until Customer 2 actually joins
**Rejected** — the whole point of Day 0 architectural commitment (ADR-001)
is to not retrofit multi-customer awareness. Adding the column now (when
all rows are customer_id=0) is cheap; adding it later requires a data
migration.

### Alt 5: Build a full customer-management UI (customers CRUD + tenant assignment)
**Rejected for THIS session** — out of scope. Sessions 2 just lays the
schema + resolver foundation. The CRUD UI lands in a future session when
Customer 2 is imminent.

---

## Implementation actions (this session)

- [ ] `classes/customer.php` — new helper class with `AIRPAY` constant + `current()` method
- [ ] `db/install.xml` — add `customer_id` field to both tables + composite unique key
- [ ] `db/upgrade.php` — savepoint adding `customer_id` columns and updating indexes
- [ ] `classes/feature_flags.php` — extend resolver with 5-level precedence, gated by feature flag
- [ ] `db/feature_flags.php` — register `sentientia.customer_level_flags.enabled` (default OFF)
- [ ] `admin/switchboard.php` — add customer tab strip (gated on the flag)
- [ ] `templates/switchboard.mustache` — render customer tabs above tenant tabs (gated)
- [ ] `lang/en/local_airpay_core.php` — new strings (customer labels, customer-disabled banner)
- [ ] `lang/hi/local_airpay_core.php` — Hindi parity (100% rule)
- [ ] `tests/feature_flags_test.php` — new tests:
  - customer-level override wins over tenant-only legacy override
  - tenant-within-customer override wins over customer-level override
  - flag OFF: customer-scoped writes rejected
  - flag OFF: customer-scoped reads return legacy resolution result
  - flag ON: full 5-level precedence verified
- [ ] `version.php` — bump to 2026052101 / 1.4.0
- [ ] `docs/customer-config/airpay.md` — customer-zero reference config
- [ ] `docs/customer-config/TEMPLATE.md` — skeleton for future customers
- [ ] Visual evidence — screenshots of Switchboard with flag OFF (identical to current) + with flag ON (new customer tab)
- [ ] PROJECT-STATE.md — Session 2 entry
- [ ] Commit + push

## Implementation actions (future)

- **ADR-008** (when Customer 2 imminent): customer table schema + tenant-to-customer mapping + admin UI
- **Session 3+:** "Moodle" → "Sentientia" rename pass — touches lang strings + footer + login page
- **Session N:** Stream A first feature (PWA + push notifications) or Stream B (WhatsApp deepening) per Nitin's priority order

---

## Verification gates (this session must pass before commit)

1. ✅ All 10 existing `feature_flags_test.php` tests pass unchanged
2. ✅ 5+ new tests cover customer-level scope semantics
3. ✅ `php admin/cli/upgrade.php --non-interactive` completes clean on local XAMPP
4. ✅ Visit `/local/airpay_core/admin/switchboard.php` — UI renders identically to before (flag is OFF by default)
5. ✅ Toggle flag ON via PHPUnit fixture or manual DB insert — verify customer tab strip appears
6. ✅ Toggle flag OFF again — verify customer tab strip disappears, customer-scoped DB rows still present but inert
7. ✅ Screenshot both states to `docs/visual-evidence/2026-05-20/`

---

## References

- ADR-001 — multi-customer architecture commitment
- `local_airpay_core/db/install.xml` — current schema
- `local_airpay_core/classes/feature_flags.php` — current resolver
- `local_airpay_core/admin/switchboard.php` — current Switchboard
- Original Phase A0 config doc: `docs/platform-review-2026-05-14/CONFIGURABILITY-ARCHITECTURE.md` (referenced by existing feature_flags class)
