# local_sentientia_core

The **Sentientia Core** layer — the first plugin of the ADR-018 independence
program (Wave 2). It owns Sentientia-level abstractions that sit *above* the
BizLMS heritage, so the rest of the product can stop touching BizLMS internals
directly.

**Status:** `MATURITY_ALPHA`, `0.2.0-alpha`. The `tenant_identity` seam now
exposes the **full open_path surface** (root + department/sub-dept + path-access
+ query filters); the ~20 existing `$USER->open_path` call sites migrate onto it
in staged, reviewed batches (ADR-018 Wave 2, PR-2+). Design:
`docs/adr/ADR-019-sentientia-core-tenant-identity.md`.

## What's here

### `tenant_identity` — the tenant-resolution + tenant-path-access seam
The single place the rest of Sentientia resolves a user's tenant and enforces
tenant-path access, instead of reading `$USER->open_path` directly (the hard
coupling in `docs/DEPRECATION-SCHEDULE.md` row 7).

```php
use local_sentientia_core\tenant_identity;

// Decompose a user's open_path (replaces hand-rolled explode('/', trim(...))):
$root  = tenant_identity::root_for_user($user);            // e.g. 77
$root  = tenant_identity::root_for_current_user();         // 0 if logged out
$dept  = tenant_identity::department_for_user($user);      // 2nd segment, 0 if none
$sub   = tenant_identity::subdepartment_for_user($user);   // 3rd segment, 0 if none
$parts = tenant_identity::segments_for_user($user);        // [root, dept, sub, …]
$path  = tenant_identity::path_for_user($user);            // raw "/1/2/3"

// Entity open_path (e.g. mdl_course.open_path) rather than a user record:
$root  = tenant_identity::path_root($course->open_path);   // root of any path string
$ok    = tenant_identity::can_access_path($course->open_path);  // boolean
tenant_identity::require_path_access($course->open_path);  // throws if out-of-tenant

// Tenant-scoping WHERE fragments for queries:
[$sql, $params] = tenant_identity::sql_filter('h');        // h.costcenterid = :…
[$sql, $params] = tenant_identity::path_filter('c');       // c.open_path =/LIKE …
```

Behind the default-ON setting **`tenant_identity_legacy`** the tenant resolver
delegates to the legacy BizLMS parser (`local_sentientia_platform\tenant`); the
access/filter helpers likewise delegate to the canonical legacy implementation —
so behaviour is byte-identical to current production. When a future wave builds
the Sentientia tenant registry, flipping the setting OFF switches the source, and
until then the OFF path falls back to legacy so it can never break authentication.
Every delegation is `class_exists()`-guarded with an inline fallback, so the seam
carries **no hard dependency** on `local_sentientia_platform` (it can ship standalone for
Enterprise N).

### `org` — the manager/org seam (ADR-020 Wave 3.1)
The sanctioned way to read a user's manager, instead of touching the BizLMS
`$user->open_supervisorid` column directly (DEPRECATION-SCHEDULE row 8).

```php
use local_sentientia_core\org;

$mgrid = org::manager_id_of($user);              // open_supervisorid, 0 if none
$mgrid = org::manager_id_for_current_user();     // 0 if logged out
```

Behind the default-ON **`org_legacy`** setting it reads `open_supervisorid`, so
behaviour matches production. The OFF path (the future `local_sentientia_org_*`
model, gated on ADR-020 §2) safely falls back to legacy until that schema lands.
Scope: only the manager-id accessor ships in 3.1 (property-based, so vanilla-Moodle
testable); reverse lookups + unit-tree walks arrive in Wave 3.2 with the schema.

## Why a default-ON flag (not a hard cutover)
ADR-018's governing rule: **customer-zero (Airpay) never sees a regression.**
The seam ships additive + reversible; the legacy resolver is the default until a
migration deliberately flips it, rehearsed on a clone DB first.

## Settings
Site administration → Plugins → Local plugins → **Sentientia Core** →
*Resolve tenant from BizLMS open_path (legacy)* (default ON).

## Not in this layer yet (later waves, human-gated)
- **Wave 2 PR-2+**: the remaining `open_path` call-site migrations onto
  `tenant_identity` (reviewed batches; `sentientia_compliance_report` excluded while
  it is active WIP, `_PATCHED` vendor files deferred to Wave 5).
- The Sentientia tenant **registry** table + admin UI (Wave 4 — replaces the
  hardcoded `local_sentientia_platform\tenant::VALID_TENANTS`).
- `local_costcenter` → `local_sentientia_org` org-hierarchy migration (Wave 3.2+).
