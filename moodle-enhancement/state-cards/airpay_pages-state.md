# State Card — `local_airpay_pages`

**Component:** `local_airpay_pages`
**Status:** Live on airpay.academy — collection of standalone PHP pages
**Maturity:** Mixed (per-page, no global version.php)
**Version:** 2026052900 / 1.1
**Last refreshed:** 2026-05-29 (C10 P1 / Gap 3)

---

## 2026-05-29 — C10 P1 / Gap 3: tenant-scoped certificate template browser

- New `certificate_templates.php` — tenant-aware browser over the
  vendored `tool_certificate` templates (READ-ONLY). Filters by a
  JSON map (`cert_template_tenant_map` admin setting): non-siteadmin
  tenant admins see only global + their tenant's templates; siteadmins
  see all with an assigned-tenant column.
- New `db/feature_flags.php` — `sentientia.certificate.tenant_scope.enabled`
  (default OFF = today's behaviour, all admins see all templates).
- New `settings.php` — the JSON map textarea + an admin_externalpage
  link to the browser. (First time this plugin has had settings.php.)
- 19 new lang strings (cert_* keys).
- Version bumped 2026040400 → 2026052900, release 1.0 → 1.1.
- Zero mutation of the vendored tool_certificate plugin.
- Audit ref: `docs/audits/C10-CERTIFICATE-STACK-INVESTIGATION-2026-05-28.md` Gap 3.

(Sibling Gap 4 — tool_certificate Hindi pack — is STAGED for review at
`docs/translations/tool_certificate-hi-DRAFT.php`, not part of this
plugin.)

---

## Mission

Catch-all bucket for one-off / single-page features that don't justify
their own plugin shell. Each `.php` file at the top level is a
self-contained entry point.

Today's surfaces:
- `homepage.php` — site landing page (Airpay-branded)
- `onboarding.php` — new-employee onboarding journey
- `qr_attendance.php` — QR-code attendance scan-in flow
- `certificates.php` — certificate gallery
- `index.php` — pages directory

## DB tables

None.

## Capabilities

None declared (no `db/access.php`). Surfaces gate on login + the
referenced upstream plugin caps.

## Feature flags

None registered.

## Key files

```
local/airpay_pages/
├── README.md
├── version.php                                   ✓ added (F-091 back-port 2026-05-28)
├── index.php                                     Pages directory
├── homepage.php                                  Site landing
├── onboarding.php                                New-employee journey (tenant-scoped F-008 2026-05-28)
├── qr_attendance.php                             QR attendance scan
├── qr_scan.php                                   QR redirect handler
├── certificates.php                              Certificate gallery
├── cli/                                          ✓ back-ported 11 setup/seed scripts (F-091 2026-05-28)
│   ├── setup_costcenters.php
│   ├── setup_bizlms_data.php
│   ├── setup_policies.php
│   ├── seed_users.php
│   ├── seed_testdata.php
│   ├── seed_production_data.php
│   ├── fix_bizlms_columns.php
│   ├── fix_all_bizlms_data.php
│   ├── fix_manager_role.php
│   ├── create_hrbp_role.php
│   └── enable_completion.php
├── pages/                                        Static HTML (privacy, terms, help, contact, dpdp)
├── templates/                                    Mustache templates
└── lang/                                         (en + hi + kn + mr + sw — 5 locales)
```

**Status update 2026-05-28:** the plugin DOES have `version.php` (back-ported
from xampp during F-091 fix). The "no version.php" claim above was stale.
It IS a formally installed Moodle local plugin.

## Tests

None — each page is exercised manually + the upstream plugin's
PHPUnit suite covers the underlying queries.

## Stabilization notes

- F-091 (workspace drift) — RESOLVED 2026-05-28 by back-porting 17 files
  (version.php, EN lang pack, 11 CLI scripts, 3 HTML pages, qr_scan.php)
  from deployed (commit `e32473e58`).
- F-074 (state-card stale) — RESOLVED 2026-05-28 by this refresh (B19).
- Tenant leak in onboarding.php — RESOLVED 2026-05-28 (commit `db5242c9a`).

## Open items

- [ ] Hindi/locale parity audit for the 11 CLI scripts (they emit
      cli_writeln messages — mostly admin-facing, but still)
- [ ] Mobile responsiveness per page (Phase 6B follow-on)
- [ ] Visual evidence snapshot for the 4 active pages
- [ ] `qr_attendance.php` integration with `local_airpay_classroom`
      attendance writer
- [ ] Add a `MATURITY_BETA` stamp in `version.php` (currently
      back-ported as-is from xampp)

## State card created — 2026-05-24

Initial state card. This plugin is unusual — no `version.php`, so it's
deployed-but-not-installed. Created now as part of the P1 state-card
pass to surface that ambiguity for future cleanup decisions.
