# Fact base 70: user-facing surfaces, public surfaces, design system, languages, accessibility, claims

Confidential: Airpay Payment Services internal. Not for external distribution.

Input for the UI/UX review pack and the marketing pack. Facts only; every row carries a source path. Paths are relative to the repository root (`D:/Claude Local/airpay-ld-os/`). `ME` means `moodle-enhancement/`, the canonical plugin tree. Plugins exist twice (`ME/local/<p>/` canonical, `local/<p>/` twin); each plugin is counted once.

## 0. Method, status mapping, limits

- Surfaces were enumerated from the top-level PHP entry points of each plugin (`ME/local/*/*.php`, excluding `lib.php`, `version.php`, `settings.php`, `externallib.php`), the theme layouts and templates, and the vendored `admin/tool/certificate`.
- Persona and purpose are inferred from the page name, the capability the page requires (`require_capability`, `has_capability`) and the lang strings. Where the page was not read in full, the purpose column is marked "(inferred)".
- Status labels (master prompt section 3.4):
  - **Available (UAT)**: unchanged since commit `af6e65c0e` (the commit on UAT). Verified by `git diff af6e65c0e --name-only` for `theme/sentientia`, `ME/local/sentientia_pages`, `sentientia_catalog`, `sentientia_pwa`, `sentientia_users`: the only changes under those paths are listed in section 0.1.
  - **Built (verified on development)**: changed after `af6e65c0e` (commits `76e8ec572`, `4a78b8f82`, and the uncommitted working tree of 20 Nov).
  - Whether a given flag is ON or OFF on UAT is not recorded in the repository for every flag. Where this matters it is marked "to be confirmed (owner: Head of Learning & Development)".
- Limits: no browser, no UAT access, no database read was used for this file. Counts of screenshots and strings are file counts; string counts are line counts of `$string[` and may include a few commented lines.

### 0.1 Changes after the UAT commit that touch UI surfaces

| Change | Files | Status |
|---|---|---|
| Programs link in the L&D admin sidebar (theme 2026111902, release 1.0.73-beta; UAT has 2026111901, 1.0.72-beta) | `theme/sentientia/classes/sidebar_navigation.php`, `theme/sentientia/version.php`, `theme/sentientia/tests/nav_chrome_test.php` | Built (verified on development) |
| Certificate gallery names the course for course certificates and imported certificates | `ME/local/sentientia_pages/certificates.php` | Built (verified on development) |
| `tool_certificate` re-vendored to 5.0.12 in the repository (the repository at the UAT commit carries 4.5.7; the UAT server runs upstream 5.0.12 with two 4.5.7-based patched files from the 19 Nov deploys, per the re-vendor record) | `admin/tool/certificate/**`, `ME/docs/core-mods/2026-11-20-tool-certificate-5.0.12-revendor.md` | Built (verified on development) |
| External-trainer accounts, behind a flag | `ME/local/sentientia_users/**` (flag `sentientia.users.external_trainer.enabled`, default OFF) | Built (behind a feature flag, off by default) |
| Proctoring B1 foundation and B2 secure assessment mode | `ME/local/sentientia_proctoring/**` (release 1.2.1), `ME/mod/quiz/accessrule/sentientia_proctoring` (release 1.3.1), commit `76e8ec572` | Built (behind a feature flag, off by default) |

## 1. Key numbers

| Item | Value | Source |
|---|---|---|
| Local plugins (canonical tree) | 47 | `ls ME/local` |
| Twin tree plugins | 44 plugin folders (46 entries including `readme.txt` and `upgrade.txt`); `sentientia_api`, `sentientia_content_market`, `sentientia_talent` exist only in the canonical tree | `ls local`, `ls ME/local` |
| Other Sentientia plugins | 6 blocks (`sentientia_cert_health`, `_compliance`, `_cron_health`, `_leaderboard`, `_recommendations`, `_trainer`), 1 enrol (`sentientiasub`), 1 payment gateway (`paygw_airpay`), 1 quiz access rule (`sentientia_proctoring`) | `ME/blocks`, `ME/enrol`, `ME/payment/gateway`, `ME/mod/quiz/accessrule` |
| Top-level PHP entry points in local plugins | 179 | `find ME/local -mindepth 2 -maxdepth 2 -name '*.php'` less lib, version, settings, externallib |
| Theme | `theme_sentientia` release 1.0.73-beta (version 2026111902); UAT 1.0.72-beta | `theme/sentientia/version.php`; `git show af6e65c0e:theme/sentientia/version.php` |
| Feature flags in plugin registries | 142 flags in 43 registries (41 local plugins, `enrol_sentientiasub` and `theme_sentientia`); 129 default OFF, 13 default ON | `ME/local/*/db/feature_flags.php`, `ME/enrol/sentientiasub/db/feature_flags.php` |
| Guest-reachable surfaces | 11 (section 3) | section 3 |
| Languages | 5 packs: en, hi, kn, mr, sw | `theme/sentientia/lang/`, `ME/local/*/lang/` |
| Plugin string count (local plugins and blocks) | en 5,871; hi 5,866; kn, mr, sw 4,736 each | `grep -c 'string\['` over `ME/local/*/lang/<l>/*.php`, `ME/blocks/*/lang/<l>/*.php` |
| Theme string count | en 343; hi 343; kn, mr, sw 189 each | `theme/sentientia/lang/<l>/theme_sentientia.php` |
| Screenshots in `ME/docs/visual-evidence/` | 1,528 images (1,489 PNG) in 47 dated folders, 28 of which hold images (2026-05-20 to 2026-11-20) | section 9 |
| Design tokens | brand blue `#0066A7`, bright blue `#1985DD`, background `#F2F4FB`, Montserrat, 8px-based spacing, 6 breakpoints | `theme/sentientia/scss/moodle/_tokens.scss` |

## 2. Surface inventory by plugin

Route pattern: `/local/<plugin>/<file>.php` unless stated. All pages call `require_login()` except those listed in section 3. "Cap" is the capability family the page requires. Personas: Learner, Manager (team lead), Trainer (course author or classroom trainer), L&D admin / tenant admin, Site admin. Source for every row is the page files named in the Routes column under `ME/local/<plugin>/`, plus `lang/en/local_<plugin>.php` for titles.

| Plugin (release) | Routes (file names) | Persona | Purpose (inferred where marked) |
|---|---|---|---|
| `sentientia_catalog` (1.0.11-beta) | `index`, `course`, `public`, `cart`, `mycourses` | Learner; Guest for `public` and `course` | Tenant catalogue, course detail, guest storefront, cart, "My courses" |
| `sentientia_pages` (1.2.2) | `homepage`, `index?page=privacy\|terms\|help\|contact\|dpdp`, `certificates`, `certificate_templates`, `onboarding`, `qr_scan`, `qr_attendance` | Guest (`homepage`, `index`); Learner; L&D admin (`certificate_templates`); Trainer (`qr_attendance`, cap `local/sentientia_classroom`) | Static public pages, certificate gallery, certificate template browser, onboarding wizard, QR attendance scan and projector page |
| `sentientia_users` (2.10.0) | `signup`, `privacypolicy`, `termscondition`, `profile`, `photo`, `skillprofile`, `help`, `index`, `bulk_csv`, `bulk_import`, `bulk_hrms`, `sample`, `exportcsv`, `external_trainers`, `sync_runs`, `sync_run_detail` | Guest (`signup`, `privacypolicy`, `termscondition`); Learner (profile); L&D admin (the rest) | Self-registration, legal pages, profile, user management, HR-system import and sync history |
| `sentientia_courses` (1.16.0) | `index`, `featured`, `enrolledusers`, `enrol_csv`, `bulk_unenrol`, `exportcsv`, `browse_airpay`, `share`, `my_requests`, `manage_requests` | L&D admin; Trainer; Learner (`my_requests`) | Manage courses, featured courses, enrolment, cross-tenant library browse and share requests |
| `sentientia_request` (1.6.4) | `index`, `approvals`, `all` | Learner; Manager (`approvals`); L&D admin (`all`) | Enrolment requests and approvals |
| `sentientia_manager` (1.3.10) | `index`, `allocations`, `member`, `performance`, `requests`, `exportcsv` | Manager | My Team dashboard, allocations, member view, performance, requests |
| `sentientia_classroom` (1.12.0) | `index`, `view`, `attendance`, `my`, `ics` | Trainer; L&D admin; Learner (`my`) | Instructor-led classrooms, attendance, calendar file |
| `sentientia_exams` (1.7.1) | `index`, `view` | L&D admin; Learner (`view`) | Online exams |
| `sentientia_proctoring` (1.2.1) | `index`, `admin`, `attempt`, `review` | L&D admin; Learner (`attempt`) | Secure assessment mode, violation review. Flagged OFF |
| `sentientia_learningpath` (1.9.4) | `index`, `view`, `mypaths`, `exportcsv` | L&D admin; Learner (`mypaths`) | Learning paths |
| `sentientia_programs` (1.9.2) | `index`, `view`, `levelcourses`, `myprograms` | L&D admin; Learner (`myprograms`) | Multi-level programs |
| `sentientia_evaluation` (1.18.0) | `index`, `questions`, `respond`, `my_evaluations`, `responses`, `response_list`, `response_detail`, `analysis`, `non_respondents`, `import_template`, `export_template`, `exportcsv` | L&D admin; Learner (`respond`, `my_evaluations`) | Evaluations and surveys |
| `sentientia_skills` (1.7.2) | `index`, `view`, `admin`, `course_mapping`, `designation_matrix`, `level_definitions` | Learner (`index`, `view`); L&D admin | Skills framework, self-rating, matrix |
| `sentientia_skillsai` (0.1.5-alpha) | `index`, `extract`, `gaps`, `review`, `taxonomy` | L&D admin | AI skill extraction (mock mode) |
| `sentientia_analytics` (1.2.8-beta) | `index`, `drilldown`, `export` | L&D admin; Manager | Analytics dashboards |
| `sentientia_reports` (1.3.0) | `index`, `run`, `export` | L&D admin | Report builder |
| `sentientia_compliance_report` (1.0.12) | `index`, `export` | Manager; L&D admin; Learner (own scope, nav item "Compliance") | Compliance RAG report |
| `sentientia_recompletion` (1.2.2) | `index`, `edit`, `history`, `history_detail` | L&D admin | Course re-completion rules |
| `sentientia_org` (1.6.2 in `version.php`; a second `1.5.0` line is present) | `admin`, `tenant_settings`, `index`, `data_migration` | L&D admin; Site admin | Organisation tree, tenant settings, data migration |
| `sentientia_core` (0.8.0-alpha) | `index`, `manage_tenants`, `admin_log` | Site admin | Tenants and admin log |
| `sentientia_roles` (1.3.2-beta) | `index`, `view`, `compare`, `compare_export`, `audit`, `exportcsv` | L&D admin; Site admin | Role and capability views and audit |
| `sentientia_emails` (1.4.3) | `manage`, `editor`, `preview`, `preview_ajax`, `email_detail` | L&D admin | E-mail template editor and rules |
| `sentientia_notifications` (1.5.2) | `index`, `logs`, `log_detail`, `nudge`, `prefs` | L&D admin; Manager (`nudge`); Learner (`prefs`) | Notification log, nudges, preferences |
| `sentientia_calendar` (1.2.1-beta) | `index`, `ics`, `regenerate` | Learner | Calendar feed (iCal) |
| `sentientia_cart` (1.2.2) | `index`, `checkout`, `callback`, `return`, `history`, `receipt`, `invoice`, `creditnote`, `einvoice`, `credits`, `admin_orders`, `daily_sums`, `daily_sums_csv`, `set_price` | Learner (cart, history, receipt, invoice); L&D admin (orders, prices, e-invoice, daily sums) | Paid enrolment, GST invoices, credit notes |
| `sentientia_leaderboard` (0.2.3-alpha) | `index`, `view`, `preferences`, `stream` | Learner | Leaderboards with opt-out. `leaderboards.enabled` default OFF |
| `sentientia_challenge` (1.1.6-alpha) | `index`, `view`, `leaderboard` | Learner; L&D admin | Challenges |
| `sentientia_gamification` (1.0.5-beta) | none (blocks and dashboard widgets) | Learner | Points, badges, streaks (flag `engagement.gamification.enabled` default ON) |
| `sentientia_live` (0.2.4-alpha) | `index`, `stream` | Trainer (create), Learner (join) | Live polls, quizzes, Q&A. `live.enabled` default OFF |
| `sentientia_authoring` (0.3.2-alpha) | `index`, `studio`, `templates`, `review`, `voiceover` | Trainer; L&D admin | SOP to SCORM authoring studio (mock mode) |
| `sentientia_aiquiz` (0.3.1-alpha) | `generate`, `review` | Trainer | AI quiz generation (mock mode) |
| `sentientia_assistant` (1.2.2-alpha) | `agent`, `ai_demo` | Learner; Site admin | Assistant drawer. Flag `ai.assistant.enabled` default ON; model calls mock |
| `sentientia_ai` (0.1.2-alpha) | `index` | Site admin | AI gateway settings page |
| `sentientia_recommendations` (0.1.2-alpha) | `generate` | Learner | Recommendations. Flag OFF |
| `sentientia_translate` (0.2.2-alpha) | `translate`, `brands` | L&D admin | Content translation tool |
| `sentientia_ratings` (1.2.1) | `reviews` | L&D admin | Course ratings and reviews |
| `sentientia_content_market` (1.0.3-beta) | `index` | L&D admin | Third-party content marketplace. 6 flags, all OFF |
| `sentientia_talent` (1.0.0-beta) | `index`, `opportunities` | Learner; L&D admin | Talent opportunities. Flags OFF |
| `sentientia_privacy` (1.1.0) | `index` | Learner (My Privacy & Data); L&D admin | Data-rights self-service |
| `sentientia_pwa` (0.6.1-alpha) | `manifest`, `sw`, `preferences`, `dismiss_install`, `mock_receiver` | Learner | Installable web app. Flags OFF |
| `sentientia_whatsapp` (0.4.1-alpha) | `preferences` | Learner | WhatsApp preferences. Flags OFF |
| `sentientia_api` (1.3.2) | `index`, `scim`, `webhooks` | Site admin | API tokens, SCIM, webhooks |
| `sentientia_integrations` (1.2.0-beta) | `webhook` | System (no UI) | Inbound webhook receiver (HR-system client in `classes/keka_client.php`) |
| `sentientia_xapi` (1.0.2) | `index` | L&D admin | xAPI statement settings |
| `sentientia_m365` (0.2.0-alpha) | admin index only | Site admin | Microsoft 365 scaffold |
| `sentientia_lifecycle` (1.1.0-beta) | none | System | Lifecycle tasks |
| `sentientia_platform` (1.13.1) | none (feature flag registry, tenant helper) | System | Flags, customer and tenant scope |

Blocks and shell (all under `theme/sentientia` or `ME/blocks`): app shell (`templates/shell.mustache`, `sidebar.mustache`, `topbar.mustache`, `mobile_bottom_nav.mustache`), dashboard `/my/` (`layout/dashboard.php`, `templates/dashboard.mustache`), course page (`layout/course.php`), guest landing (`layout/frontpage.php`), login (`layout/login.php`, `templates/core/loginform.mustache`), maintenance page (`layout/maintenance.php`), secure quiz layout (`layout/secure.php`), blocks (`ME/blocks/sentientia_*`). Role switcher: `ME/my/switchrole.php`.

### 2.1 Sidebar navigation by persona

Source: `theme/sentientia/classes/sidebar_navigation.php` (lines 144 to 394); labels in `theme/sentientia/lang/en/theme_sentientia.php` (`nav_*`).

| Persona | Items |
|---|---|
| Site admin | Manage Users, Manage Courses, Course-share Requests, Online Exams, Classrooms, Learning Paths, Programs, Analytics, Compliance, Organisation, Skills, Evaluations, Certificates, Emails, Privacy, Site Admin |
| L&D / tenant admin | Manage Users, Manage Courses, Browse Library, Online Exams, Classrooms, Learning Paths, Programs (built, development), Analytics, Compliance, then the Authoring group when the user holds the capability |
| Manager | Compliance, plus the learner items |
| Learner | My Courses, Catalog, Browse Library and My Requests (cross-tenant, flag-dependent), My Cart (non-Airpay tenant), My Skills, Certificates, Live Sessions (cap-dependent) |
| Mobile bottom bar | Home, My Learning, Search, Me (`templates/mobile_bottom_nav.mustache`; strings `mobilenav_*`) |
| Authoring group (cap-gated) | Authoring Studio, AI Quiz, Skills AI |

## 3. Public (logged-out) surfaces

Eleven surfaces are reachable without signing in. Status for all: **Available (UAT)** (unchanged since `af6e65c0e`).

| # | Surface | URL path | Plugin or file | Purpose | Flag or gate |
|---|---|---|---|---|---|
| 1 | Login | `/login/index.php` | `theme/sentientia/layout/login.php`, `templates/core/loginform.mustache` | Split-screen sign-in: hero panel (3 feature blocks, 3 live stat counters) plus form; SSO buttons when configured | `login_*` strings in `theme/sentientia/lang/en/theme_sentientia.php` lines 453 to 470 |
| 2 | Guest landing (site front page) | `/` | `theme/sentientia/layout/frontpage.php` (812 lines; 10 sections: navbar, hero, trust bar, why us, courses, pillars, testimonials, FAQ, call to action, footer) | Marketing landing for guests; logged-in users are redirected to `/my/` | Layout `frontpage` in `theme/sentientia/config.php` line 78 |
| 3 | Legacy public homepage | `/local/sentientia_pages/homepage.php` | `ME/local/sentientia_pages/homepage.php` (238 lines) | Older landing (hero, trust bar, three pillars, featured courses, call to action). Reachable by URL and by the `frontpage` redirect in `theme/sentientia/layout/columns2.php` line 39; the active front page is #2 | none |
| 4 | Public catalogue (storefront) | `/local/sentientia_catalog/public.php` | `ME/local/sentientia_catalog/public.php` | Guest course search, sort (Popular, Newest, A-Z), 12 per page, price or Free, Details, Add to cart or Enrol free | Comment line 22 "NO require_login()". `sentientia.catalog.public_lxp.enabled` (rail "Popular picks"): registry default `true`, while the flag description says "default OFF" (see section 10) |
| 5 | Course detail | `/local/sentientia_catalog/course.php?id=` | `ME/local/sentientia_catalog/course.php` | Course page for guests and learners; add to cart, enrol, or request enrolment | No `require_login()`; request button flag `sentientia.request.catalog_button` (OFF) |
| 6 | Self-registration | `/local/sentientia_users/signup.php` | `ME/local/sentientia_users/signup.php` | Public-tenant sign-up form with Terms and Privacy tick box; e-mail confirmation | Admin setting `local_sentientia_users` signup enabled (`signup_service::is_enabled()`); when off, an info notice shows. State on UAT: to be confirmed |
| 7 | Privacy policy (sign-up flow) | `/local/sentientia_users/privacypolicy.php` | `ME/local/sentientia_users/privacypolicy.php` | Built-in default text, or admin-supplied HTML in setting `custom_privacy_policy_html` | Layout `login` |
| 8 | Terms of use (sign-up flow) | `/local/sentientia_users/termscondition.php` | `ME/local/sentientia_users/termscondition.php` | Built-in default text (8 sections, governing law India, Mumbai courts), or admin HTML `custom_tos_html` | Layout `login` |
| 9 | Static pages | `/local/sentientia_pages/index.php?page=privacy\|terms\|help\|contact\|dpdp` | `ME/local/sentientia_pages/index.php`, `pages/*.html` | Privacy Policy, Terms of Service, Help Center, Contact Us, DPDP Act 2023 notice (April 2026 text) | No `require_login()` |
| 10 | Certificate verification | `/admin/tool/certificate/index.php?code=` | `admin/tool/certificate/index.php` (vendored; Sentientia patch recorded in `ME/docs/core-mods/2026-11-19-tool-certificate-verify-on-arrival.md`) | Anyone with a code (printed QR code, gallery Verify button, LinkedIn link) sees Verified or Not verified with holder name and certificate name on arrival. Page carries `noindex, nofollow` (theme 1.0.72, `certificate_verify_noindex_test.php`) | Capability `tool/certificate:verify` is allowed to `guest` and `user` by default (`admin/tool/certificate/db/access.php` line 67); a site may revoke it |
| 11 | PWA support files | `/local/sentientia_pwa/manifest.php`, `sw.php`, `offline.html` | `ME/local/sentientia_pwa/` | Web app manifest, service worker, offline page | Flag `sentientia.pwa.enabled` default OFF; with the flag OFF `sw.php` serves a worker that unregisters itself (`db/feature_flags.php` lines 16 to 21) |

Other public-adjacent behaviour:

- The guest landing counts courses and learners for the Public tenant only (`layout/frontpage.php` lines 20 to 60). On the UAT and development databases these counts come from imported test data.
- Guest cart icon on the landing links to the login page (`layout/frontpage.php`, "Login to browse courses").
- Payment (public storefront checkout) is behind `sentientia.catalog.storefront_checkout.enabled` (default OFF), `ME/local/sentientia_catalog/db/feature_flags.php`.

## 4. Theme design system

Source of truth: `theme/sentientia/scss/moodle/_tokens.scss` (CSS custom properties on `:root`); dark overrides `_tokens-dark.scss` (`[data-theme="dark"]`) and `dark_mode.scss` (`body.dark-mode`). Brand adoption record: `ME/docs/audits/brand-revamp-2026-06/REVISED-BRAND-BOOK-2026-06.md` (source: the 25-page official brand guideline file `BB-revamp-noweb.pdf` named there). Earlier study superseded by it: `ME/docs/audits/AIRPAY-CORP-BRANDING-STUDY-2026-06-11.md`.

| Group | Tokens |
|---|---|
| Primary | `--ap-blue-600` / `--ap-color-primary` `#0066A7`; hover `#005590`; dark `#004570`; light `#e8f4fd` |
| Accent | `--ap-blue-bright` / `--ap-color-accent` `#1985DD`; deep `#0d5da1`; sky tint `#9cdbf4`. Teal ramp retired: every `--ap-teal-*` is an alias onto the blue ramp |
| Sparing accents | Orange `#ed692b` (hover `#c9551d`, light `#fdeee6`); purple `#6d58a5` |
| Neutrals | 0 `#ffffff`, 50 / page background `#f2f4fb`, 100 `#e8ebf0`, 200 `#d1d5de`, 600 text-secondary `#475569`, 900 text `#0f172a` |
| Status | success `#15803d`, warning `#b45309`, danger `#dc2626`, info = primary. Success and warning were darkened for contrast ("A11Y-7", `_tokens.scss`) |
| Gradients | Brand `135deg #0066A7 to #0d5da1`; hero `160deg #003050, #0066A7, #0d5da1`; CTA primary to accent |
| Typography | Montserrat, fallbacks -apple-system, Segoe UI, Roboto; mono JetBrains Mono. Scale 12, 14, 16, 18, 20, 24, 30, 36 px (`--ap-text-xs` to `-4xl`); weights 400, 500, 600, 700, 800; line height 1.25, 1.5, 1.75 |
| Spacing | 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80 px (`--ap-space-1` to `-20`) |
| Radius | 4, 8, 12, 16, 20 px, pill 999px, circle 50% |
| Shadows | xs, sm, md, lg, hover, focus ring `0 0 0 3px rgba(0,102,167,.25)` |
| Layout | Sidebar 260px (72px collapsed), top bar 64px, content max width 1400px, mobile bottom bar 64px |
| Breakpoints | 380, 590, 768, 992, 1280, 1600 px (`$ap-bp-*`); primary mobile breakpoint 590px |
| Controls | Heights 32, 40, 48, 56 px; minimum tap target 44px (`--ap-tap-target-min`) |
| Motion | Durations 0, 150, 250, 400, 700 ms; four easing curves; all durations collapse to 0 under `prefers-reduced-motion: reduce` |
| Per-customer branding | `\local_sentientia_platform\customer::branding()` read by `core_renderer` (logo, colours, favicon); theme settings include logo, login slider images (5), login text, social links, brand colours (`theme/sentientia/lang/en/theme_sentientia.php`) |
| Component naming | BEM prefix `.airpay-*` and `.ap-*` (`.claude/rules/frontend.md`) |

Brand Book rules recorded in the adoption spec: blue-dominant, white in smaller proportion, subtle blue gradients, orange and purple only as occasional accents, Montserrat everywhere.

## 5. Languages

| Language | Theme strings | Plugin strings (local plugins and blocks) | Coverage against English | Review status | Source |
|---|---|---|---|---|---|
| English (en) | 343 | 5,871 | 100% | Source | `theme/sentientia/lang/en`, `ME/local/*/lang/en` |
| Hindi (hi) | 343 | 5,866 | 99.9% (5 fewer lines in plugins) | Parity enforced by a pre-commit check and CI (`.claude/hooks/pre-commit.sh`: en/hi parity) | `lang/hi` |
| Kannada (kn) | 189 | 4,736 | 55% theme; 81% plugins | Reviewed by AI bilingual reviewers with back-translation QA (commit `27ce9fc35`: 507 strings checked, 0 fixes); not checked by a human native speaker | `ME/PROJECT-STATE.md` section "2026-10-29 / 10-30" |
| Marathi (mr) | 189 | 4,736 | 55% theme; 81% plugins | Same process (1,028 strings checked, 3 fixes accepted); no human native-speaker check | same |
| Swahili (sw) | 189 | 4,736 | 55% theme; 81% plugins | Same process (1,090 strings checked, 2 fixes accepted); no human native-speaker check | same |

- The packs show only where the matching Moodle core language pack is installed. At the 30 Oct record, UAT had no kn, mr or sw core pack; local development had them (`ME/PROJECT-STATE.md`). Current state: to be confirmed.
- Language switcher in the sidebar: class `theme/sentientia/classes/language_switcher.php`, gated by flag `ux.languageSwitcher.enabled`, default OFF (`ME/local/sentientia_platform/db/feature_flags.php` line 153). The choice is saved to the user's profile language.
- Hard-coded English found in public and shell surfaces (section 10).
- RTL: `theme/sentientia/scss/moodle/bootstrap-rtl.scss` exists; no RTL language is shipped.
- Third-party: a Hindi draft for `tool_certificate` is at `ME/docs/translations/tool_certificate-hi-DRAFT.php`; core-mod record `ME/docs/core-mods/2026-05-29-tool_certificate-hi-pack.md`.

## 6. Accessibility facts

| Fact | Evidence |
|---|---|
| Target standard is WCAG 2.1 AA; the review pack should test against WCAG 2.2 AA | `ME/PHASE-H-A11Y-AUDIT.md`; `.claude/rules/frontend.md` |
| Automated audit on 2026-05-06 with axe-core: 2 critical issues found and fixed (`aria-allowed-attr`, `select-name`); after fixes 0 critical, 5 serious (colour contrast), 11 moderate. The audit's own verdict: not production-ready until contrast closed | `ME/PHASE-H-A11Y-AXE-RESULTS.md` (surfaces: dashboard, manage users, catalogue; two roles) |
| Colour contrast corrections since: text-secondary raised to `#475569` (about 6.4:1 on white), success to `#15803d`, warning to `#b45309`; dark-mode chips raised from 2.42 to 5.62 and 1.83 to 6.41; a real `.text-black-50` 1.06:1 failure fixed | `_tokens.scss` ("A11Y-7"); `ME/PROJECT-STATE.md` "Dark-mode WCAG-AA" |
| No re-run of the axe audit after those fixes is recorded in the files read; re-run needed | to be confirmed (owner: QA Architect) |
| axe-core smoke test in CI per persona and surface | `ME/PROJECT-STATE.md` ("a11y-smoke.spec.ts (axe-core) runs per persona x surface in CI", commit `d84f9ac3d`); the screenshot-diff half was not seeded |
| Focus ring contract: 3px ring, 2px offset on `:focus-visible`; 24 SCSS files use `focus-visible` | `_tokens.scss`; `grep -rl focus-visible theme/sentientia/scss` |
| Reduced motion: 12 SCSS files reference `prefers-reduced-motion`; durations collapse to 0; stylelint rule enforces tokens in `_surface-*.scss` | `_tokens.scss`; `theme/sentientia/.stylelintrc.json`; `.claude/rules/frontend.md` |
| Tap target minimum 44px; mobile bottom nav and edit-mode blocks tab at least 44px | `_tokens.scss`; theme release notes 1.0.66 |
| Status shown with text as well as colour; modal focus trap and Esc; `aria-sort` on tables | `ME/PHASE-H-A11Y-AUDIT.md` A11y-05 to A11y-07 |
| Screen reader pass (NVDA on an admin table) listed as an audit item | `ME/PHASE-H-A11Y-AUDIT.md` A11y-08; result: to be confirmed |
| Skip link: a grep of `shell.mustache`, `head.mustache`, `columns2.mustache`, `login.mustache` for "skip" finds none; Moodle core may supply one | to be confirmed (owner: UI/UX Solution Architect) |
| Theme tests relevant to UI: `sr_heading_text_test.php`, `shell_title_test.php`, `layout_footer_template_test.php`, `sidebar_theme_label_test.php`, `modal_forms_scss_test.php` | `theme/sentientia/tests/` |

## 7. Dark mode, mobile and PWA

| Item | Fact | Status | Source |
|---|---|---|---|
| Dark mode (signed-in shell) | Per-user toggle in the sidebar and top bar; appears only when flag `ux.darkMode.enabled` is ON; registry default OFF; never follows the OS theme; label is translated | Built (behind a feature flag, off by default); state on UAT to be confirmed | `ME/local/sentientia_platform/db/feature_flags.php` line 139; `theme/sentientia/templates/sidebar.mustache` |
| Dark mode (guest landing) | Dark toggle button always present on the landing navbar, state kept with class `dark-mode` | Available (UAT) | `theme/sentientia/layout/frontpage.php` |
| Responsive layout | Six breakpoints; course sidebar starts collapsed at 992px and below; bottom navigation on phones; tables scroll inside cards at 390px | Available (UAT) | `_tokens.scss`; theme release notes 1.0.64 to 1.0.70 |
| Installable web app | For customer 1 the manifest comes from `customer::branding()`: name "Airpay Academy", short name "Academy", theme colour `#0066A7`, start `/my/?utm_source=pwa_install`, icons `local/sentientia_platform/pix/customer/1/icon-192.png` and `icon-512.png` (a provisional "A" monogram on brand blue). The "Sentientia LMS" name and the Moodle SVG icon appear only in the fallback branch used when `local_sentientia_platform` is missing (corrected 20 Nov 2026) | Built (behind a feature flag, off by default): `sentientia.pwa.enabled`, `.push.enabled`, `.push.reminders`, `.push.overdue`, `.install.enabled`, all OFF | `ME/local/sentientia_pwa/manifest.php` lines 36 to 58; `ME/local/sentientia_platform/classes/customer.php` lines 279 to 285; `db/feature_flags.php`; release 0.6.1-alpha |
| Offline | Service worker caches an offline shell and static assets; the offline page is branded "Offline, Sentientia LMS" | Built (behind a feature flag, off by default) | `sw.php`, `offline.html` |
| Web push | Needs VAPID keys; messages routed through the browser vendor | Built (behind a feature flag, off by default) | `ME/local/sentientia_pwa/lang/en` (`subscribe_privacy_note`) |
| Native app | Capacitor scaffold in `ME/mobile/sentientia-app` (android, ios, src); a store-release decision and a framework comparison (Flutter recommended after a 3-day spike, versus Kotlin Multiplatform) are documented | Planned | `ME/docs/plans/2026-11-19-MOBILE-APP-BENCHMARK-AND-ARCHITECTURE.md`, `ME/docs/plans/2026-11-19-APP-STORE-PAPERWORK-PACK.md` |
| Mobile web-service surface | 22 mobile-ready, 14 learner-write, 36 admin-only endpoints | Available (UAT) | `ME/docs/audits/MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md` |

## 8. Customer-visible claim register (input for the marketing claims register)

"Claim" is text a visitor can read. "Observation" is what the repository shows. The marketing pack must not repeat a claim marked "unsupported in repository" without Finance, Legal or Security evidence.

### 8.1 Guest landing (`theme/sentientia/layout/frontpage.php`)

| # | Claim text (abridged, as written) | Line | Observation |
|---|---|---|---|
| C1 | Trust bar: "RBI Compliant" | 454 | No compliance mapping to RBI found in the files read. Unsupported in repository; Compliance to confirm or remove |
| C2 | Trust bar: "DPDP 2023 Ready" | 455 | DPDP design work and ADRs exist; no external audit yet (audit by the security team and SISA is the next step). "Ready" is a claim, not a certification |
| C3 | Trust bar: "SCORM & xAPI" | 456 | SCORM 1.2 is core Moodle; `local_sentientia_xapi` 1.0.2 exists. Supported |
| C4 | Trust bar: "Multi-Tenant" | 457 | Supported (3 tenants on UAT and development) |
| C5 | Trust bar: "24/7 Support" | 458 | Contact page states 24-hour response on business days, Mon to Fri 10:00 to 18:00 IST (`pages/contact.html`). The two statements conflict. Unsupported |
| C6 | Hero: "enterprise learning and development platform for the financial services industry" | 424 | Positioning statement; first deployment is a payments company |
| C7 | Hero stats: "N+ Courses", "N+ Learners" (live counts for the Public tenant) | 432 to 445 | Counts come from imported test data on UAT and development; must not be shown as real usage before cutover |
| C8 | "Purpose-built capabilities that set airpay academy apart from generic LMS platforms" | 470 | Comparative claim without a stated comparator. Unsupported as written |
| C9 | "RBI-mandated training with automated tracking, deadline escalation, and audit-ready export" | 476 | Tracking and export are built (`sentientia_compliance_report`); "RBI-mandated" content is not evidenced here |
| C10 | "Isolated environments for each business unit with white-label branding, separate user pools, and independent reporting" | 481 | Tenant isolation and branding built; "separate user pools" is path-based isolation in one site (`ME/docs/adr/`, ADR-031) |
| C11 | "Progressive web app with offline support" | 486 | PWA flag is OFF; offline works only when the flag is ON. Status: Built (behind a feature flag, off by default) |
| C12 | "Role-based analytics, manager team views, compliance RAG matrices, and real-time completion tracking dashboards" | 491 | Built (`sentientia_analytics`, `sentientia_manager`, `sentientia_compliance_report`) |
| C13 | Testimonials: three 5-star (one 4.5-star) quotes attributed to named people with roles at Airpay and ZEEA | 621 to 655 | No source for these quotes exists in the repository. Marketing must remove or replace with consented, real testimonials. Names are not reproduced here |
| C14 | FAQ: "AI-powered learning" | 671 | AI features run in mock mode; live model use is blocked on a budget cap and a key (`CLAUDE.md` workstream G). Status: Built (behind a feature flag, off by default) |
| C15 | FAQ: "RBI-mandated courses on AML, KYC, POSH, IT and information security awareness, DPDP Act 2023, and financial fraud prevention" | 679 | Course titles depend on imported content; to be confirmed against the course list (owner: Head of Learning & Development) |
| C16 | FAQ: "online assessments with proctoring" | 683 | Proctoring B1/B2 built, flags OFF, not on UAT |
| C17 | FAQ: "Each enterprise customer gets a dedicated tenant with their own logo, brand colours, custom domain, user pool..." | 687 | Branding and tenant built; "custom domain" per customer is not evidenced in the repository. To be confirmed (owner: DevOps lead) |
| C18 | FAQ: "integrates with HRMS platforms (KeKa, Darwinbox)... SSO via SAML/OAuth2... REST APIs... webhooks. CSV bulk import/export" | 691 | Keka client and 24-column Darwinbox/SAP CSV importer exist (`sentientia_integrations/classes/keka_client.php`, `sentientia_users/classes/hrms_importer.php`); OAuth2 SSO guide exists (`ME/SSO-SETUP-GUIDE.md`); SAML is not evidenced in the files read; `identity.sso.enabled` default OFF; REST and webhooks in `sentientia_api` |
| C19 | FAQ: "Digital certificates with unique verification codes... PDF, LinkedIn, public verification URL... customised with your branding" | 695 | Supported (`tool_certificate`, gallery, verification page) |
| C20 | FAQ: pricing "free or per-course; Enterprise plans based on active user count with annual billing, dedicated tenant setup, priority support" | 699 | Per-course pricing built (cart). Enterprise plan terms are not defined in the repository; Finance pack to define. Unsupported |
| C21 | CTA: "Join N+ professionals across financial services who are already learning" | 711 | Count is the Public-tenant user count, test data before cutover. Unsupported until cutover |
| C22 | Footer badge "Made in India" image | footer | Brand asset `theme/sentientia/pix/brand/made-in-india.jpg` |

### 8.2 Legacy homepage (`ME/local/sentientia_pages/homepage.php`)

| # | Claim | Observation |
|---|---|---|
| C23 | Trust bar: "RBI Compliant", "DPDP 2023", "ISO Certified", "24/7 Support" | "ISO Certified" has no evidence in the repository. Same issues as C1, C2, C5. Recommend retiring this page or aligning it |
| C24 | "Join thousands of learners building skills for the digital economy" | Unsupported; the live count shown on the same page is test data |
| C25 | Hero: "A comprehensive learning platform for employability, business training, and financial education" | Positioning |
| C26 | "Get Started Free" button to `/login/signup.php` | Core sign-up path; the Sentientia sign-up is `/local/sentientia_users/signup.php` (the active landing uses that) |

### 8.3 Login page (`theme/sentientia/lang/en/theme_sentientia.php`, lines 453 to 470)

| # | Claim text | Observation |
|---|---|---|
| C27 | "Upskill. Get certified. Get hired." | Slogan; "get hired" depends on the talent plugin (flags OFF) |
| C28 | "Job-ready courses in finance, payments, tech & business, built by the people running real platforms" | Unsupported as a general statement; depends on catalogue |
| C29 | "Take employer-graded tests. Pass once, get shortlisted by hiring partners across the industry" | `sentientia_talent` 1.0.0-beta flags `sentientia.talent.enabled` and `.opportunities` are OFF; no hiring partners recorded. Unsupported |
| C30 | "Every completion ships with a tamper-proof certificate" | Certificates have a verifiable code; "tamper-proof" is a technical claim to be confirmed by the security team |
| C31 | Stats: "Learners upskilling", "Courses available", "Certificates earned" (live counts) | Counts are test data before cutover |

### 8.4 Static pages (`ME/local/sentientia_pages/pages/*.html`)

| # | Page | Claim | Observation |
|---|---|---|---|
| C32 | dpdp.html | Badge "DPDP Act 2023 Compliant" | A compliance claim. The security and privacy reviews are not complete. Recommend "designed to support" wording until audited |
| C33 | dpdp.html | "Encryption in transit (TLS 1.2+) and at rest"; "Regular security audits and vulnerability assessments"; "Secure hosting on AWS infrastructure within India" | Hosting and encryption depend on the IT infrastructure still to be provisioned for Sentientia; security audit is planned. To be confirmed (owner: DevOps lead, Security) |
| C34 | dpdp.html, privacy.html | Response times "72 hours for data access requests, 30 days for deletion requests" | Service levels are stated but no SLA tooling evidenced; to be confirmed (owner: Head of L&D) |
| C35 | dpdp.html | Retention: learning records "account plus 7 years"; account data deleted "within 90 days"; certificates kept permanently, anonymised after deletion | Policy statements; match against the erasure design in the DPDP pack |
| C36 | dpdp.html | "Right to Erasure ... available to external/public users only" | Policy choice for employees; confirm with Legal |
| C37 | help.html | "most assessments allow unlimited retakes and your highest score is recorded" | Course setting, not a platform guarantee |
| C38 | help.html | "airpay academy is a progressive web app ... Add to Home Screen ... offline support" | PWA flag is OFF; statement is untrue until it is ON |
| C39 | help.html | "Most courses for registered users are free. Some premium courses may require payment through the Airpay payment gateway" | Cart and gateway built; storefront checkout flag OFF |
| C40 | contact.html | "We respond within 24 hours on business days (Monday to Friday, 10:00 AM to 6:00 PM IST)"; shared mailbox `academy@airpay.co.in`; address "Mumbai, Maharashtra, India" | Operational commitment; owner Head of L&D |
| C41 | privacy.html (static) vs privacypolicy.php (sign-up flow) | Two privacy texts with different content: static page says "Data is stored on secure servers within India"; sign-up default says rights "under applicable privacy regulations including the EU GDPR and India's DPDP Act", lawful basis "explicit consent", adds rights to object and portability | Two sources of truth for one policy. Recommend one controlled text |
| C42 | `local_sentientia_users` lang line 187 and 191 | "built-in GDPR-compliant default" (admin help text) | Compliance claim in an admin string; GDPR is not a stated target of the platform |

### 8.5 Other customer-visible strings with claims

| # | Location | Text | Observation |
|---|---|---|---|
| C43 | `ME/local/sentientia_pwa/lang/en/local_sentientia_pwa.php` line 22 | "install Sentientia LMS to your home screen ... push notifications appear like any other app, even when the browser is closed" | PWA flag OFF |
| C44 | same file, line 31 | "We never share the message content with the vendor, only an encrypted blob" | Web Push standard behaviour; confirm wording with Security |
| C45 | `ME/local/sentientia_proctoring/lang/en` `secure_banner` | "copying, pasting and printing are blocked, and leaving fullscreen or this page is recorded" | Accurate to B2 design; flag OFF |
| C46 | `theme/sentientia/lang/en` `choosereadme` | "standalone theme with full design system, per-customer branding, and responsive mobile experience" | Supported |
| C47 | Footer (private notice) | Footer says private and confidential to Airpay, not "Licensed under GPL v3" (owner decision recorded in project memory; per-customer string) | Per-customer legal line |

## 9. Visual evidence folders

Root `ME/docs/visual-evidence/`: 1,528 images (PNG, JPG, WebP) in 47 dated folders; the 28 folders listed below hold images, the other 19 hold READMEs or notes only. Another root `docs/visual-evidence/` holds 2 dated folders (2026-05-25, 2026-05-27) with no images in the folder tree counted. Source: `find ... -iname '*.png'` etc., counted per top-level folder.

| Folder | Images | Contents (sub-folders) |
|---|---|---|
| 2026-05-20 | 11 | first Day-0 UI session |
| 2026-05-23 | 37 | |
| 2026-05-25 | 47 | |
| 2026-05-27 | 9 | |
| 2026-05-29 | 20 | QA walk |
| 2026-06-09 | 33 | showcase |
| 2026-06-15 | 10 | |
| 2026-06-16 | 5 | brand book adoption |
| 2026-07-22 | 14 | |
| 2026-08-03 | 1 | UI and navigation upgrade |
| 2026-09-03 | 5 | UAT stage A |
| 2026-09-29 | 170 | site-admin pages, desktop and mobile pairs (courses, featured, roles, emails, privacy DPDP, compliance) |
| 2026-09-30 | 293 | `learningpath-learner-page`, `persona-fix-catalog`, `personas`, `qr-and-loginas`, `theme-shell` |
| 2026-10-08 | 72 | `moodle53` |
| 2026-10-14 | 66 | `ev18`, `gst` |
| 2026-10-16 | 73 | `cart`, `emails`, `evaluation`, `request`, `skills-org`, `theme`, `README.md` |
| 2026-10-21 | 4 | |
| 2026-10-22 | 1 | |
| 2026-10-23 | 4 | |
| 2026-10-24 | 9 | |
| 2026-10-30 | 3 | |
| 2026-11-02 | 8 | |
| 2026-11-11 | 3 | |
| 2026-11-12 | 11 | |
| 2026-11-16 | 102 | `course-header-enrol-date`, `course-reminder-enrol-date`, `demo-quick-wins`, `edit-mode-phone-dark-banner` |
| 2026-11-18 | 157 | `certificate-redesign`, `course-title-overlap`, `walk-fixes` |
| 2026-11-19 | 343 | `phase-c` (README, `repair`, `vp_admin1`, `vp_admin177`, `vp_author1`, `vp_compliance1`), `quick-wins` (`QW-CERT-VERIFY`, `QW-EMAIL`, `QW-MODAL-FORMS`, `QW-ROI`, `QW-SKILLS-UI`), `session-01` (`G-ANALYTICS`, `G-CERT-VENDOR`, `G-COMPLIANCE`, `G-COURSES`, `G-EMAIL`, ...) |
| 2026-11-20 | 17 | `session` (`G-CERT-ISSUER`, `G-RULES`, `G-VENDOR`) |

Other image sets: `ME/docs/visual-audit-2026-05-22/` 31 images (learner, manager, site administrator) with `AUDIT-REPORT.md`; `ME/docs/qa-walk-2026-05-29/` 25; `ME/demo-screenshots/` 12; `ME/screenshots/` 5; `ME/test-screenshots/` 4; `ME/docs/user-guides/screenshots/` 0 (folder holds sub-folders only). Audit documents: `ME/docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`, `PLATFORM-VISUAL-AUDIT-2026-05-25.md`, `UI-NAV-AUDIT-2026-08-03.md`. User guides by persona: `ME/docs/user-guides/` (`learner.md`, `public-learner.md`, `manager.md`, `course-author.md`, `site-admin.md`, `compliance-officer-guide.md`).

Note: `ME/docs/visual-evidence/` filenames include persona login names from test accounts; do not reproduce them in the packs.

## 10. Observations for the UI/UX and marketing reviewers (from code, not opinions)

| # | Observation | Source |
|---|---|---|
| O1 | Two guest landing pages exist (`layout/frontpage.php` active; `local_sentientia_pages/homepage.php` legacy). Trust bar and claims differ between them (C1 to C5 versus C23, C24) | sections 3 and 8 |
| O2 | Brand name is written four ways: "airpay academy" (landing, page titles), "Airpay Academy" (login, sign-up, lang strings), "Sentientia LMS" (PWA manifest, offline page, product), "Sentientia UX" (theme name). Brand Book 2026-06 governs; the customer-name string is `theme_sentientia/customername` | `frontpage.php`, `theme_sentientia.php`, `manifest.php` |
| O3 | Landing and legacy homepage are built in PHP with hard-coded English, inline `style=` attributes and hex values (for example `#e8e2f2`, `#16a34a`, `#6d58a5`), against the rule that tokens are the only colour source and strings are localised | `layout/frontpage.php`, `homepage.php`; `.claude/rules/frontend.md` anti-patterns |
| O4 | Top bar dark-mode button title is hard-coded English ("Toggle dark mode"); the sidebar label is localised | `templates/topbar.mustache` line 38 versus `sidebar.mustache` |
| O5 | Static legal pages (`pages/*.html`) are English only; the five language packs do not cover them | `ME/local/sentientia_pages/pages/` |
| O6 | The static privacy page and the sign-up privacy text differ (C41); the static DPDP notice and privacy page both say "last updated April 2026" | `pages/`, `privacypolicy.php` |
| O7 | Flag registry versus description mismatch: `sentientia.catalog.public_lxp.enabled` has `'default' => true` while its description says "When OFF (default)" | `ME/local/sentientia_catalog/db/feature_flags.php` lines 19 to 25 |
| O8 | PWA manifest icons for customer 1 are a provisional "A" monogram (`local_sentientia_platform/pix/customer/1/icon-192.png`, `icon-512.png`), not final brand icons; the Moodle SVG appears only in the fallback branch when the platform plugin is missing (corrected 20 Nov 2026) | `manifest.php` lines 48 to 58; `customer.php` lines 283 and 284 |
| O9 | The certificate verification page shows the holder's full name to anyone with the code; the page is excluded from search indexing but links are shareable (decision recorded in the core-mod record) | `ME/docs/core-mods/2026-11-19-tool-certificate-verify-result-escaping.md`, theme 1.0.72 |
| O10 | Testimonial block on the landing page has no source record (C13) | `layout/frontpage.php` 621 to 655 |
| O11 | A skip link was not found in four shell templates (section 6) | grep |
| O12 | Support e-mail, response times and the contact page state a business-day schedule while the trust bar says 24/7 (C5) | `frontpage.php`, `pages/contact.html` |
| O13 | Live stat counters (login, landing, sign-up hero) read from the database; before cutover they show imported test data | `core_renderer` `login_stat_*` methods; `frontpage.php` |
| O14 | `local_sentientia_users` hint text states the HR-sync auth header is "stored unencrypted" in the config table (admin string, security relevant) | `ME/local/sentientia_users/lang/en/local_sentientia_users.php` line 235 |

## 11. Gaps and items to be confirmed

| Item | Owner (role) |
|---|---|
| Which UI flags are ON on UAT (`ux.darkMode.enabled`, `ux.languageSwitcher.enabled`, `sentientia.pwa.enabled`, signup enabled) | Head of Learning & Development |
| Kannada, Marathi, Swahili core language packs installed on UAT, and a human native-speaker review | Head of Learning & Development |
| Axe or Pa11y re-run on the current theme and a WCAG 2.2 AA pass | QA Architect |
| Skip link and screen-reader pass | UI/UX Solution Architect |
| Evidence for "RBI Compliant", "ISO Certified", "24/7 Support", "custom domain", "SAML" | Compliance, DevOps lead, Security |
| Real testimonials with consent (DPDP) or removal | Marketing lead |
| Hosting claims ("AWS within India", encryption at rest) once the Sentientia infrastructure exists | DevOps lead |

## Corrections (fact-base cross-check, 20 Nov 2026)

| # | Correction | Evidence |
|---|---|---|
| 1 | Flags: 141 in 42 registries (the enrol plugin's `sentientia.subscriptions.enabled`, default OFF, was missing), 128 OFF and 13 ON. | PHP include of every `db/feature_flags.php` |
| 2 | Visual evidence: 47 dated folders exist; 28 hold images. Image total 1,528 unchanged. | `find ME/docs/visual-evidence` |
| 3 | `tool_certificate` on UAT: the UAT server runs 5.0.12 (with two patched files deployed 19 Nov), not 4.5.7; 4.5.7 is what the repository carried at the UAT commit. | `ME/docs/core-mods/2026-11-20-tool-certificate-5.0.12-revendor.md` lines 1-35 |

- Wording: short decision references (ADR section codes and a working-folder name) replaced by ADR names, per master prompt rule 3.2 (1 places).
