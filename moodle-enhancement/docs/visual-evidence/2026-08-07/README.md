# Visual evidence — 2026-08-07 (KeKa JML hardening, ADR-029)

**No screenshots this session — deliberate scope call, documented here so
the evidence trail stays unbroken.**

## Why

This session was backend/security hardening
(`local_sentientia_integrations` 1.2.0-beta, `local_sentientia_lifecycle`
1.1.0-beta). Zero theme, template, SCSS or learner-facing changes. The
only UI delta is Moodle's **auto-generated admin settings forms**:

1. *Site admin → Plugins → Local plugins → Airpay Integrations Hub* —
   KeKa section labels now come from lang strings (en+hi, previously
   hardcoded English), plus one new text setting **"Default org path for
   new users"** (`keka_default_orgpath`, default `/1`) and an updated
   webhook-secret description (X-Webhook-Secret header only).
2. *Site admin → Plugins → Local plugins → Airpay Employee Lifecycle* —
   NEW settings page with one text setting **"Mandatory-course tag"**
   (`mandatory_tag`, default `mandatory`).
3. Switchboard — three new flags appear (all default OFF):
   `sentientia.hrms.webhook.enabled`, `sentientia.hrms.reconcile.enabled`,
   `sentientia.lifecycle.autoenrol.enabled`.

These render through the stock `admin_settingpage` pipeline — no custom
markup to regress. The local siteadmin password is (correctly) not
committed anywhere, and this autonomous session did not reset it just to
screenshot a stock form.

## How to eyeball in 60 seconds (Nitin)

1. `http://localhost:8080/moodle/admin/settings.php?section=local_sentientia_integrations`
   → KeKa section shows the new default-org-path setting; switch language
   to hi and confirm the KeKa labels are translated.
2. `http://localhost:8080/moodle/admin/settings.php?section=local_sentientia_lifecycle`
   → new Mandatory-course tag setting.
3. Switchboard → the three `sentientia.hrms.*` / `sentientia.lifecycle.*`
   flags listed, resolved OFF.
