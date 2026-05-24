# State Card — `local_airpay_pages`

**Component:** `local_airpay_pages`
**Status:** Live on airpay.academy — collection of standalone PHP pages
**Maturity:** Mixed (per-page, no global version.php)
**Last refreshed:** 2026-05-24 (P1 state-card pass)

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
├── index.php                                     Pages directory
├── homepage.php                                  Site landing
├── onboarding.php                                New-employee journey
├── qr_attendance.php                             QR attendance scan
├── certificates.php                              Certificate gallery
├── pages/                                        Supporting partials
├── templates/                                    Mustache templates
└── lang/                                         (en + hi)
```

This plugin has **no `version.php`** at the top level — it's not a
standard Moodle local plugin in the usual sense. Pages are
deployed-but-not-installed; they reference data + helpers from the
plugins that own the underlying state.

## Tests

None — each page is exercised manually + the upstream plugin's
PHPUnit suite covers the underlying queries.

## Open items

- [ ] Decision: promote each page into its owning plugin or formalise
      this plugin with a `version.php` so it has a proper Moodle install
      cycle
- [ ] Hindi parity audit per page
- [ ] Mobile responsiveness per page (Phase 6B follow-on)
- [ ] Visual evidence snapshot for the 4 active pages
- [ ] `qr_attendance.php` integration with `local_airpay_classroom`
      attendance writer

## State card created — 2026-05-24

Initial state card. This plugin is unusual — no `version.php`, so it's
deployed-but-not-installed. Created now as part of the P1 state-card
pass to surface that ambiguity for future cleanup decisions.
