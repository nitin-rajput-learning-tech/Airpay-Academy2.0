# Installing Sentientia LMS from scratch

This is the turnkey procedure for standing up a **self-contained** Sentientia
LMS on a clean Moodle 5.1 base - no external (eAbyas / OPEN-LMS) BizLMS
plugins required. It was validated on 2026-06-04 against an empty database.

> **Why this guide exists.** Sentientia's own plugins (`local_airpay_*` /
> `local_sentientia_*` / `theme_airpayux`) install cleanly on vanilla Moodle.
> The *only* thing a from-scratch install is missing is the BizLMS-compatible
> `open_*` columns on `mdl_user` / `mdl_course` (the multi-tenant substrate).
> Historically those columns arrived with a production data dump; they are now
> reproduced by a first-party CLI (`bootstrap_substrate.php`), so Sentientia
> stands up end-to-end from this repository alone.

---

## 0. Prerequisites

| Component | Version |
|-----------|---------|
| PHP       | 8.2+ (8.3 recommended) |
| MariaDB / MySQL | 10.11+ / 8.0+ |
| Moodle core | 5.1.3+ |
| Web server | Apache 2.4+ (or nginx) |

A standard `config.php` with DB credentials, `$CFG->wwwroot`, and
`$CFG->dataroot` set. (On the Moodle 5.1 "public/" split, `config.php` lives at
the moodle root **above** `public/`; `public/config.php` is only a loader.)

---

## 1. Install Moodle core + the Sentientia plugin suite

1. Deploy the full tree (Moodle core + the bundled `local/`, `blocks/`,
   `enrol/`, `theme/airpayux` plugins) to the web root.
2. Run the core installer (fresh DB) **or** copy onto an existing Moodle and
   upgrade:

   ```bash
   # Fresh database:
   php admin/cli/install_database.php \
       --lang=en --adminuser=admin --adminpass='<STRONG-PASSWORD>' \
       --adminemail='admin@example.com' \
       --fullname='Sentientia LMS' --shortname='sentientia' --agree-license

   # OR, onto an existing Moodle:
   php admin/cli/upgrade.php --non-interactive
   ```

   This installs Moodle core plus all ~40 first-party Sentientia plugins.

---

## 2. Bootstrap the tenant substrate  (the de-coupling step)

A vanilla `mdl_user` / `mdl_course` lacks the `open_*` columns Sentientia's
multi-tenant logic reads. Create them with the first-party, **idempotent**
bootstrap CLI:

```bash
# Preview (no changes):
php local/sentientia_core/cli/bootstrap_substrate.php --dry-run

# Apply (adds only missing columns; safe to re-run):
php local/sentientia_core/cli/bootstrap_substrate.php
```

This adds 37 `open_*` columns to `mdl_user` and 18 to `mdl_course` if absent,
then purges caches. It never drops or alters an existing column, so it is a
no-op on a database that already has the substrate (e.g. a restored production
dump).

> **Tenant detection is pure `open_path` string parsing** (roots:
> `1`=Airpay, `77`=Public, `177`=ZEEA). The eAbyas `local_costcenter` /
> `local_userdata` tables are **not** required at runtime, which is why this
> bootstrap only needs to guarantee the columns.

---

## 3. Seed tenants + users (optional, for a usable demo box)

On a brand-new DB every user has `open_path = NULL`, so assign at least the
admins to a tenant so the dashboards resolve:

```bash
# Minimal: point existing admins at the Airpay (id=1) tenant root.
php admin/cli/cfg.php   # (or set open_path='/1' for admin users via SQL/UI)

# Fuller demo data (if the airpay_pages seeders are present):
php local/airpay_pages/cli/seed_users.php           # test_* personas (Airpay@2026)
php local/airpay_pages/cli/fix_bizlms_columns.php    # assign costcenter/department
```

Sentientia-core also ships tenant/org seeders for its own (future) tables:
`local/sentientia_core/cli/seed_tenants.php`, `backfill_org.php`. These feed
the ADR-020/021 migration model and are dormant by default.

---

## 4. (Optional) Serve at the web root - drop `/moodle` from URLs

To serve Sentientia at `http://HOST/` instead of `http://HOST/moodle/`:

1. Point the web server's DocumentRoot at the Moodle `public/` directory and
   grant access to it. (Apache example - in `httpd.conf`:)

   ```apache
   DocumentRoot "/path/to/moodle/public"
   <Directory "/path/to/moodle/public">
       AllowOverride All
       Require all granted
   </Directory>
   # Remove/!comment any "Alias /moodle ..." line.
   ```

2. Set `$CFG->wwwroot = 'http://HOST';` (no `/moodle` suffix) in `config.php`.
3. Restart the web server, then `php admin/cli/purge_caches.php`.
4. Verify: `http://HOST/login/index.php` -> 200; the old `/moodle/...` path
   -> 404.

---

## 5. Verify

```bash
php admin/cli/purge_caches.php
```

| Check | Expect |
|-------|--------|
| `GET /` | 200 (front page renders, `theme/airpayux` active) |
| `GET /login/index.php` | 200 |
| `GET /my/dashboard.php` (unauth) | 303 -> login |
| `bootstrap_substrate.php --dry-run` | `0 column(s) would be added` |
| Admin login -> dashboard | renders, no `open_*` / tenant errors |

---

## Appendix - dependency map

| Layer | Source | Clean-install story |
|-------|--------|---------------------|
| Moodle core 5.1 | upstream | `install_database.php` |
| Sentientia plugins (~40) | this repo | install/upgrade (self-contained schema) |
| `open_*` tenant columns | `bootstrap_substrate.php` | **this guide, step 2** |
| Tenant/org model tables | `local_sentientia_core` install.xml | created on plugin install (dormant) |
| eAbyas `local_costcenter` UI pages | NOT in repo (proprietary) | not required at runtime |

The long-term plan (ADR-018 / ADR-020 / ADR-021) migrates the live read-path
off the `open_*` columns onto first-party `local_sentientia_tenant` /
`org_unit` / `org_member` tables, at which point step 2 is retired. That
cutover is feature-flagged and human-gated; until then, `bootstrap_substrate.php`
is the supported way to stand up the substrate.
