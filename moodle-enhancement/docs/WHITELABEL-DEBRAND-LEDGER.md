# White-label De-brand Ledger — "Airpay Academy" customer-name sweep

**Owner:** Nitin Rajput · **Updated:** 2026-06-10 · **Status:** Rendered de-brand COMPLETE; residual classified.

---

## What "100%" means here

Sentientia LMS is a white-label product; **Airpay Academy is customer-zero**.
A white-label product needs a *default brand value* — and that default is, by
design, "Airpay Academy". So **literal-zero "Airpay Academy" in the codebase is
NOT the goal and would be wrong** (it would delete the customer-zero default,
break PHPUnit fixtures that assert the default, and strip legal copyright).

**The goal — and what is now achieved:** *zero rendered, customer-facing
customer-NAME strings that are not config-driven.* Every surface a real user
sees now resolves the platform name from `$SITE->fullname` /
`get_site()->fullname` / `{{sitename}}` / `{{#str}}customername{{/str}}` /
`{$a->sitename}`, so flipping the site name (or a future customer's brand)
re-skins the whole product with no code change.

The literal "Airpay Academy" strings that remain are enumerated below and are
each a deliberate keep (default value, copyright, comment, test fixture,
regulatory seed) or a documented deferred deeper-work item.

---

## DONE — rendered surfaces retargeted to the config-driven name

| Wave | Surface(s) | Mechanism |
|------|-----------|-----------|
| W-A | `theme/sentientia` loginform (hero + logo alts) | `{{sitename}}` |
| W-A | `theme/sentientia` email_html wrapper logo alt | `{{sitefullname}}` |
| W-A | `theme/sentientia` maintenance / DB-down page | `{{#str}}customername, theme_sentientia{{/str}}` |
| W-A | `theme_sentientia` lang `customername` (overridable default) | added en + hi |
| W-A | `sentientia_users` signup / privacypolicy / termscondition (titles, intros, legal body) | `{$a}` + caller `format_string($SITE->fullname)` |
| W-A | `sentientia_whatsapp` preferences heading / dlt / intro (en + hi) | `{$a}` + caller; intro brand-dropped |
| Batch 1 | 6 rendered templates: onboarding, certificate_celebration, notifications/prefs, courses/browse_airpay, courses/share_page, emails/welcome_new_user | `{{#str}}customername, theme_sentientia{{/str}}` |
| Batch 2 | 4 cron email signatures: course_reminder, course_overdue, exam_reminder, exam_overdue | `$a->sitename = format_string(get_site()->fullname)`; en signature strings → `{$a->sitename}` |
| Batch 2 | `sentientia_integrations` settings_desc | reworded ("for this platform") |
| Batch 3 | `sentientia_users/welcome_mailer` DEFAULT_BODY | dropped hardcoded brand that contradicted the `[employee_organization]` token already in the body |
| Batch 3 | `sentientia_classroom/ics_builder` ORGANIZER;CN | `get_site()->fullname` (RFC 5545-quoted; user-visible in calendar clients) |
| Batch 3 | `sentientia_classroom/ics_builder` PRODID | product name "Sentientia LMS" (.ics metadata) |
| Batch 3 | `sentientia_whatsapp` lang sw / mr / kn heading + dlt + intro | `{$a}` + caller; intro brand-dropped → **all 5 locales (en/hi/mr/kn/sw) at `{$a}` parity** |
| Batch 3 | `sentientia_pages/onboarding` redirect success msg + page title | `'Welcome to ' . format_string(get_site()->fullname)` |
| Batch 3 | `sentientia_notifications/rule_engine` inactive-user re-engagement subject | `'We miss you on ' . format_string(get_site()->fullname)` |
| Batch 4 | `theme_sentientia` product labels ×5 locales: `pluginname` "Airpay Academy UX (Sentientia)" → **"Sentientia UX"**, `privacy:metadata`, `choosereadme` (incl. lowercase "airpay academy" the case-sensitive greps missed) | product name, per the P2 rule (product labels → Sentientia) |
| Batch 4 | `theme_sentientia` `customername` added to kn/mr/sw (was en+hi only) | 5-locale parity for the white-label default key |
| Batch 4 | `footer.mustache` logo alt | was `{{#str}}footer_logo_alt{{/str}}` (hardcoded brand ×5 locales incl. transliterations) → `{{#str}}customername, theme_sentientia{{/str}}`; the 5 orphaned `footer_logo_alt` keys removed |
| Batch 4 | **hi** email signatures ×8: courses + exams reminder/overdue `_body_plain`+`_body_html` ("— एयरपे एकेडमी") | `— {$a->sitename}` (the batch-2 cron callers already pass it); kn/mr/sw have no signature strings |

---

## KEEP — literal "Airpay Academy" remains by design

| # | Category | Examples | Why keep |
|---|----------|----------|----------|
| K1 | **Copyright** | `© Airpay Payment Services` (file headers) | Legal entity name, not the LMS customer-name. Different string. |
| K2 | **Brand default / fallback** | `customer.php:234 'name' => 'Airpay Academy'`; `welcome_mailer::derive_org_name()` 3 returns; `tenant_config` defaults; `sidebar_navigation.php:88` `get_config(...) ?: 'airpay academy'` | This IS the white-label default value. A new customer overrides it; the fallback must exist. |
| K3 | **Comments / headers** | `{{! }}` mustache (incl. `Example context (json)` doc blocks with `"sitename": "airpay academy"`), `//`/`*` PHP, `/* */` SCSS block interiors, `*.README.md` | Not rendered to users. |
| K4 | **Build artifacts** | `theme/sentientia/amd/build/*.min.js.map` | Generated; embed original source comments. Regenerated by `grunt`. |
| K5 | **Lang default** | `theme_sentientia 'customername' = 'Airpay Academy'` (all 5 locales, brand kept untranslated) | The overridable default the white-label resolver returns. |
| K6 | **Test / seed / verify fixtures** | `verify_brand_resolver.php`, `customer_brand_test.php`, `seed_badges.php`, `seed_demo_translations.php`, `run_whatsapp_e2e.php`, `aiquiz/live_smoke.php` | Must assert/seed the default brand value to test the fallback path. |
| K7 | **AI prompt defaults** | `assistant/ai_client.php`, `assistant/core_ai_bridge.php` system prompts | Per-customer configurable (Wave C3). "Airpay Academy" is the customer-zero default prompt. |
| K8 | **DLT-approved template seed** | `whatsapp/db/install.php` DLT body | Regulatory-fixed text — must match the DLT-registered template verbatim. A new customer re-registers their own. |
| K9 | **Admin / dev internal** | `platform/admin/styleguide.php` | Internal design-token reference page, admin-only, not customer product chrome. |
| K10 | **XMLDB COMMENT attrs** | `*/db/install.xml COMMENT="Airpay Academy …"` | Schema metadata, never rendered. |
| K11 | **Gateway / proctoring component names** | `paygw_airpay`, `quizaccess_airpay_proctoring` | Payment-gateway / proctoring *product* component names, not the LMS customer-name. |

---

## DEFER — genuine deeper white-label work (documented, not yet done)

| # | Item | Surface | Note |
|---|------|---------|------|
| D1 | Static legal content | `sentientia_pages/pages/dpdp.html` names "Airpay Academy" as Data Fiduciary | Legal/compliance *content*. A new customer replaces the page. Future: per-customer legal-page override. |
| D2 | ~~Brand transliterations~~ **RESOLVED in batch 4** | whatsapp sw/mr/kn, hi email signatures, footer_logo_alt transliterations — all retargeted or removed | A repo-wide transliteration grep (एयरपे एकेडमी / एअरपे अकॅडमी / ಏರ್‌ಪೇ ಅಕಾಡೆಮಿ) now returns 0 in rendered strings. |
| D3 | ~~Support-email token~~ **RESOLVED 2026-06-10 overnight** — `[support_email]` token, config `local_sentientia_users/support_email`, customer-zero default kept | `welcome_mailer` DEFAULT_BODY `academy@airpay.co.in` | Functional contact address, not customer-name. Needs a `[support_email]` token + config for full white-label. |
| D4 | AI-prompt tokenization | `ai_client` / `core_ai_bridge` default prompts | Optionally inject `get_site()->fullname` into the default prompt instead of the hardcoded default. Currently per-customer config (K7). |
| D5 | PWA manifest generation | `theme/*/pix/brand/manifest.json` static default | The `customer_brand` resolver already generates per-customer manifests at runtime; the static file is customer-zero's default (K2). |
| D6 | Email-preview sample data | `sentientia_emails/email_context.php` admin preview fixtures | Admin template-preview uses "Airpay Academy" beside fake names (Priya Singh, etc.). Cosmetic, admin-only — could use the live site name in preview. |
| D7 | Marketing front page | `theme/sentientia/layout/frontpage.php` (title, hero, FAQ, testimonials — ~15 lowercase "airpay academy") | Whole-page customer-zero marketing **content** (like D1's legal page), not chrome. A new customer replaces the landing page; future: per-customer frontpage content via branding/pages. |
| D8 | Footer/frontpage logo asset | `footer.mustache` + `frontpage.php` hardcode `pix/brand/academy-logo-350.png` | The logo *image* is customer-zero's asset; per-customer logo override belongs to branding_manager (ADR-008) plumbing, not a string fix. Alt text is already white-labeled (batch 4). |

---

## Verification

- Every batch: `php -l` clean on all touched PHP, deployed to local XAMPP
  (`C:\xampp\htdocs\moodle5\public`), caches purged.
- Latin "Airpay Academy" in `sentientia_whatsapp/lang/` → **0** after batch 3.
- All 5 whatsapp locales' `preferences_heading` → `{$a}`; all 5 theme locales'
  `pluginname` → "Sentientia UX" + `customername` present (verified matrices).
- Signup runtime-verified earlier: renders "Create your airpay academy account"
  (config-driven; `$SITE->fullname` is lowercase on this deployment), **0**
  `{$a}` / `[[a]]` leak.
- Batch 4 also swept **case-insensitively** (caught the lowercase
  "airpay academy" in en `choosereadme` that case-sensitive greps missed) and
  swept the **transliterations** (एयरपे एकेडमी / एअरपे अकॅडमी / ಏರ್‌ಪೇ ಅಕಾಡೆಮಿ)
  → **0** remaining in rendered strings; `footer_logo_alt` → **0** refs anywhere.
- Residual literal "Airpay Academy" after batch 4: **77** occurrences
  (case-sensitive, `local/` + `theme/` incl. the legacy `theme/airpayux` build
  tree), each mapped to a K-category or D-item above — **0 unclassified
  rendered customer-facing leaks.**

---

## How to re-audit (one command)

```bash
# From repo root — case-INSENSITIVE (batch 4 proved case-sensitive greps miss
# lowercase variants) + the transliterations. Every hit should fall into a
# KEEP (K1–K11) or DEFER (D1–D8) bucket above:
grep -rni "airpay academy" local/ theme/ moodle-enhancement/
grep -rn  "एयरपे एकेडमी\|एअरपे अकॅडमी\|ಏರ್‌ಪೇ ಅಕಾಡೆಮಿ\|एयरपे अकैडमी" local/ theme/
```

If a NEW match appears that is a rendered, customer-facing, non-config-driven
string, it is a regression — retarget it to `get_site()->fullname` /
`{{sitename}}` / `{{#str}}customername{{/str}}` per the patterns in the DONE
table and add a row here.
