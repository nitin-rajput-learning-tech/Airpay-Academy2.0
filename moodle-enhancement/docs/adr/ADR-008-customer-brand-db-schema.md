# ADR-008 — Customer brand table design (Phase 2 multi-customer)

**Status:** Proposed (forward-looking — implementation deferred until Customer 2 is imminent)
**Date:** 2026-05-21
**Deciders:** Nitin Rajput, Claude
**Builds on:** ADR-001 (fork strategy), ADR-002 (customer-level feature flags), ADR-005 (PWA install)

---

## Context

Sentientia LMS is a multi-customer product. Phase 0/1 (today) has exactly one
paying customer — Airpay Payment Services, customer id = 1 — and
`local_airpay_core\customer::branding(?customer_id)` returns a hard-wired
Airpay bundle:

```php
return [
    'name'         => 'Airpay Academy',
    'short_name'   => 'Academy',
    'theme_color'  => '#0066A7',
    'bg_color'     => '#F2F4FB',
    'icon_192_url' => $CFG->wwwroot . '/local/airpay_core/pix/customer/1/icon-192.png',
    'icon_512_url' => $CFG->wwwroot . '/local/airpay_core/pix/customer/1/icon-512.png',
    'start_url'    => '/my/dashboard.php?utm_source=pwa_install',
    'lang'         => 'en',
];
```

Phase 2 unlocks when the second customer ships. The hard-wired switch must
become a DB lookup so adding "Acme LMS" is one row insert + one icon-pair
upload — not a code deploy. This ADR defines the schema, migration path,
and resolver wiring that gets us there.

---

## Decision

**Add `local_airpay_customer_brand` table + a write-through cache.**
Replace the body of `customer::branding()` with the lookup; keep the
return shape identical so callers (manifest.php, theme renderer,
login splash, audience navbar) need zero changes.

### Schema (XMLDB — db/install.xml under `local_airpay_core`)

```xml
<TABLE NAME="local_airpay_customer_brand"
       COMMENT="Per-customer branding bundle. One row per customer id.
                Phase 2+ replaces the hard-wired switch in
                local_airpay_core\customer::branding() with a SELECT
                from this table. See ADR-008.">
  <FIELDS>
    <FIELD NAME="id"               TYPE="int"   LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="customerid"       TYPE="int"   LENGTH="10" NOTNULL="true"
           COMMENT="Unique customer identifier (FK to a future customers table;
                    Phase 2 starts with 1=Airpay, 2..N=new customers)"/>
    <FIELD NAME="name"             TYPE="char"  LENGTH="120" NOTNULL="true"/>
    <FIELD NAME="short_name"       TYPE="char"  LENGTH="40"  NOTNULL="true"/>
    <FIELD NAME="theme_color"      TYPE="char"  LENGTH="7"   NOTNULL="true"
           COMMENT="Hex like #0066A7"/>
    <FIELD NAME="bg_color"         TYPE="char"  LENGTH="7"   NOTNULL="true"/>
    <FIELD NAME="icon_192_url"     TYPE="char"  LENGTH="500" NOTNULL="true"
           COMMENT="Absolute or relative URL to 192x192 PNG"/>
    <FIELD NAME="icon_512_url"     TYPE="char"  LENGTH="500" NOTNULL="true"/>
    <FIELD NAME="start_url"        TYPE="char"  LENGTH="500" NOTNULL="true"
           COMMENT="Path relative to wwwroot (e.g. /my/dashboard.php?utm=pwa)"/>
    <FIELD NAME="lang"             TYPE="char"  LENGTH="10"  NOTNULL="true"  DEFAULT="en"
           COMMENT="BCP-47 language code (en, hi, en-IN, etc)"/>
    <FIELD NAME="status_bar_style" TYPE="char"  LENGTH="20"  NOTNULL="false"
           COMMENT="iOS apple-mobile-web-app-status-bar-style — default|black|black-translucent"/>
    <FIELD NAME="categories"       TYPE="char"  LENGTH="200" NOTNULL="false"
           COMMENT="Comma-separated W3C PWA categories (education, productivity, ...)"/>
    <FIELD NAME="timecreated"      TYPE="int"   LENGTH="10"  NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="timemodified"     TYPE="int"   LENGTH="10"  NOTNULL="true" DEFAULT="0"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary"      TYPE="primary" FIELDS="id"/>
    <KEY NAME="uk_customer"  TYPE="unique"  FIELDS="customerid"/>
  </KEYS>
</TABLE>
```

### Resolver body (post-Phase-2 `customer::branding()`)

```php
public static function branding(?int $customer_id = null): array {
    global $DB, $CFG;
    if ($customer_id === null) {
        $customer_id = self::current();
    }
    $cache = \cache::make('local_airpay_core', 'customer_brand');
    $cache_key = 'brand_' . $customer_id;
    $bundle = $cache->get($cache_key);
    if ($bundle !== false) {
        return $bundle;
    }

    $row = $DB->get_record('local_airpay_customer_brand',
        ['customerid' => $customer_id]);
    if (!$row) {
        // Fallback: synthesise a generic Sentientia-default bundle so the
        // app doesn't 500 if a new customer is provisioned without a brand row.
        $bundle = self::default_brand();
    } else {
        $icon192 = self::resolve_icon_url($row->icon_192_url);
        $icon512 = self::resolve_icon_url($row->icon_512_url);
        $bundle = [
            'name'              => $row->name,
            'short_name'        => $row->short_name,
            'theme_color'       => $row->theme_color,
            'bg_color'          => $row->bg_color,
            'icon_192_url'      => $icon192,
            'icon_512_url'      => $icon512,
            'start_url'         => $row->start_url,
            'lang'              => $row->lang,
            'status_bar_style'  => $row->status_bar_style ?? 'default',
            'categories'        => self::parse_categories($row->categories),
        ];
    }
    $cache->set($cache_key, $bundle);
    return $bundle;
}
```

### Cache definition (db/caches.php)

```php
$definitions = [
    'customer_brand' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 3600,       // 1 hour
        'simplekeys' => true,
        'staticacceleration' => true,
        'invalidationevents' => ['customer_brand_updated'],
    ],
];
```

Cache is invalidated when:
- The admin updates a brand row via the future admin UI
- A migration backfills brand rows for legacy customers

### Migration plan (Phase 2 upgrade — db/upgrade.php)

```php
if ($oldversion < 2026XXXXNN) {
    // 1. Create the table.
    $table = new xmldb_table('local_airpay_customer_brand');
    // ... (build from XMLDB above) ...
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }

    // 2. Backfill the Airpay row from the hardcoded Phase 0/1 bundle.
    if (!$DB->record_exists('local_airpay_customer_brand', ['customerid' => 1])) {
        $now = time();
        $DB->insert_record('local_airpay_customer_brand', (object) [
            'customerid'       => 1,
            'name'             => 'Airpay Academy',
            'short_name'       => 'Academy',
            'theme_color'      => '#0066A7',
            'bg_color'         => '#F2F4FB',
            'icon_192_url'     => '/local/airpay_core/pix/customer/1/icon-192.png',
            'icon_512_url'     => '/local/airpay_core/pix/customer/1/icon-512.png',
            'start_url'        => '/my/dashboard.php?utm_source=pwa_install',
            'lang'             => 'en',
            'status_bar_style' => 'default',
            'categories'       => 'education,productivity',
            'timecreated'      => $now,
            'timemodified'     => $now,
        ]);
    }

    upgrade_plugin_savepoint(true, 2026XXXXNN, 'local', 'airpay_core');
}
```

After the migration runs once, the Phase 0/1 hardcoded bundle in
`customer.php` is removed and the body becomes the resolver above.

### Admin UI (out of scope for this ADR — covered by ADR-009 when written)

A simple Switchboard-style admin page at
`/local/airpay_core/admin/customer_brands.php` lists rows + lets a
sysadmin add / edit / upload icons. Phase 2 ships without this — admins
edit rows via `mdl_local_airpay_customer_brand` directly until volume
justifies the UI investment.

---

## Why this shape

### Single row per customer (not key-value EAV)
EAV (`brand_key`, `brand_value` rows) would be more flexible — add a new
brand attribute without a schema migration. But every fetch becomes
N row reads + a PHP `foreach` to build the bundle. The branding bundle
is hit on EVERY page (manifest, theme renderer, navbar logo), so we
optimise for read latency. Flat row + cache wins.

### Indexed on `customerid` only
`get_record('local_airpay_customer_brand', ['customerid' => N])` is the
ONLY query pattern. We don't search by name, theme_color, etc. Adding
indexes we don't use just wastes pages in the buffer pool.

### `icon_*_url` stored as VARCHAR(500), not a Moodle file area
Three options were considered:
- **(a) Plain URL string** (chosen) — admin uploads the icon to
  `local/airpay_core/pix/customer/N/icon-192.png` via the file system
  or a future admin upload form, then stores the relative URL
- (b) Moodle file area (`get_file_storage()->create_file()`) — more
  flexible but adds a pluginfile.php round trip on every manifest fetch
- (c) BLOB the PNG bytes in the DB — terrible for backups + replication

(a) wins because it integrates with the static-asset caching the SW
already does (Phase D.1.d cache-first for `.png`). The pluginfile route
loses that win and adds 80ms per icon fetch on a cold cache.

### `status_bar_style` + `categories` nullable
These are PWA-spec-optional. Phase 0/1 doesn't use them; Phase 2+ may.
Nullable is cleaner than per-customer defaults littering install code.

### `lang` as BCP-47 (not Moodle's `en_us` form)
W3C Web App Manifest spec uses BCP-47 (`en`, `hi`, `en-IN`). We store
that form and let `customer::branding()` translate to Moodle's
`get_string('lang')` form only when needed by the theme renderer.

---

## What stays out

1. **Multi-tenancy within a customer.** A customer (Airpay) has multiple
   tenants (costcenterids 1/77/177). Branding is at the customer level,
   not the tenant level — all Airpay tenants share the same logo + colors.
   If a customer ever wants per-tenant branding, that's a future ADR.
2. **Per-customer feature-flag scope.** ADR-002 already covers this. The
   brand table doesn't touch flags.
3. **Per-customer DB user / connection.** Phase 2 stays in a single
   Moodle DB. True multi-DB (one DB per customer) would be ADR-010 if
   we ever need to scale that far.

---

## Consequences

**Positive:**
- Adding a new customer = 1 row insert + 2 icon files. No code deploy.
- Read latency is fast (application cache + simple-keys + 1h TTL).
- Backwards-compatible: callers see no API change; Phase 2 swap is internal.
- Admin UI can come later; rows can be edited directly until volume justifies UI.

**Negative:**
- A migration is mandatory at Phase 2 cutover. Forgetting to run upgrade.php
  would leave new customers without branding (caller falls back to
  generic Sentientia default — visible but not fatal).
- Cache invalidation discipline: every brand edit must fire the
  `customer_brand_updated` event. A future bug here could serve stale
  branding for up to 1 hour.

**Neutral:**
- No new dependencies. Pure Moodle XMLDB + standard cache API.
- Phase 0/1 callers are unchanged.

---

## Open questions (parked)

1. **Brand-asset CDN.** Phase 2 might want to push icons to a CDN like
   Cloudflare R2 instead of serving from Moodle's pluginfile.php. Out
   of scope here; ADR-011 if it becomes a problem.
2. **Per-customer theme override.** Today airpayux is the only theme;
   tomorrow we might let customers pick from a curated set. Out of scope
   — would be ADR-012.
3. **Brand rotation / experiments.** A/B testing two logos for the same
   customer would require a second `brand_variant` column + selector.
   Not requested by anyone today. Park.

---

## References

- ADR-001 — Fork strategy + product pivot
- ADR-002 — Customer-level feature flags
- ADR-005 — PWA install + native-wrapper decision
- W3C Web App Manifest spec: https://www.w3.org/TR/appmanifest/
- BCP-47 language tags: https://www.rfc-editor.org/info/bcp47
