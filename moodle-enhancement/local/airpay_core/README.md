# local_airpay_core

Shared infrastructure used by all `airpay_*` plugins. Created Phase 8.1
(2026-05-12) to systematise the tenant-equality check that 10/11
blocking security findings traced back to.

| Field | Value |
|---|---|
| Component | `local_airpay_core` |
| Version | `2026051200` (1.0.0) |
| Requires | Moodle 4.0+ (`2022041900`) |
| Maturity | `MATURITY_STABLE` |
| Depends on | _(nothing — this is the base)_ |

## What it provides

A single static helper class `\local_airpay_core\tenant` that every other
Airpay plugin uses for tenant-scoping. Centralising the check here means
adding a new external WS class is a one-liner, and we can't accidentally
omit the tenant boundary in a future plugin.

```php
use local_airpay_core\tenant;

// In an external::execute() after require_capability():
tenant::require_access((int) $resource->costcenterid);

// In a SQL query — get a WHERE-clause fragment:
[$tnsql, $tnargs] = tenant::sql_filter('h');
$rows = $DB->get_records_sql(
    "SELECT * FROM {local_airpay_cart_history} h WHERE $tnsql",
    $tnargs);
```

## Methods

| Method | Purpose |
|---|---|
| `tenant::root_for_user(\stdClass $u): int` | Derive tenant id from `$u->open_path` ("/1/2/3" → 1) |
| `tenant::root_for_current_user(): int` | Same for current `$USER` |
| `tenant::assert_valid(int $tenantid): void` | Reject anything outside `{1, 77, 177}` |
| `tenant::viewer_can_access(int $resource_tenant, ?int $viewerid = null): bool` | Site admin always true; otherwise tenant must match |
| `tenant::require_access(int $resource_tenant, ?int $viewerid = null): void` | Throws `error_outoftenant` if not allowed |
| `tenant::sql_filter(string $alias = ''): array` | Returns `[$sql_fragment, $named_args]` — admin → `1=1`, tenant user → `alias.costcenterid = :_tenantroot` |

## Tables

None. This plugin is pure code.

## Capabilities

None — this plugin doesn't have any user-facing surfaces.

## Settings

None.

## Scheduled tasks

None.

## Tests

`tests/tenant_test.php` — 9 PHPUnit cases.
- 6 pass on any Moodle install (vanilla schema is sufficient).
- 3 skip on PHPUnit fixtures without BizLMS's `user.open_path` column
  (production runs all 9).

```powershell
# Run from Moodle root:
./vendor/bin/phpunit --testdox public/local/airpay_core/tests/tenant_test.php
```

## How to verify after install

```php
php -r "require '/path/to/moodle/config.php'; var_dump(class_exists('\local_airpay_core\tenant'));"
# → bool(true)
```

If false, the plugin didn't install (check `mdl_config_plugins` for the
version row + `local/airpay_core/` directory exists in moodle docroot).

## Privacy / GDPR

No PII stored. No `privacy/provider.php` needed (no data collected).

## Production assumptions

The helper assumes `user.open_path` exists as a real column on the
`mdl_user` table. This column is added by BizLMS `local_costcenter`
in production. If `local_costcenter` is uninstalled, all `airpay_*`
plugins depending on this helper will fail to scope correctly —
treat as a hard dependency.
