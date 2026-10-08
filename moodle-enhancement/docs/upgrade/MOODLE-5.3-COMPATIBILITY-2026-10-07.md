# Moodle 5.3 LTS compatibility report: Sentientia LMS

**Date:** 2026-10-07 (verified 2026-10-08)
**Target:** Moodle 5.3 (Build: 20261005), version `2026100500.00`, source at `D:/Claude Local/moodle53/moodle` (SHA-256 verified)
**Baseline:** 5.1.3+ local (`C:/xampp/htdocs/moodle5`), 5.2+ (Build 20260519) staging (`C:/xampp/htdocs/moodle5.2`) and UAT
**Decision record:** ADR-033 (Target Moodle 5.3 LTS instead of 5.2)
**Method:** static analysis only, nothing executed.
- An API diff of 5.1/5.2 against 5.3 produced 69 patterns.
- Two scans ran those patterns:
  - **Scan A** covered theme, blocks, payment, enrol, quizaccess, vendored plugins, packaging and core-mods (946 + 1,016 files).
  - **Scan B** covered `moodle-enhancement/local/sentientia_*` (2,365 files).
- Every doubtful hit was re-read against the 5.3 tree, and the highest-impact claims were re-verified for this report.
- `php -l` ran under PHP 8.4 on all in-scope PHP files (about 2,150).
- No install, upgrade, PHPUnit run or web request was made.

Path roots: **TOP** is the repository root (`D:/Claude Local/airpay-ld-os/`); **ME** is `moodle-enhancement/`; **53** is `D:/Claude Local/moodle53/moodle/`. Line numbers were re-checked on 2026-10-08. The working tree is moving, so re-grep before editing.

---

## 1. Executive summary

**Sentientia is not 5.3-ready today. There are 12 blockers: 5 fatal, 6 broken behaviour and 1 environment.**

Every code fix is small: a few lines each, with no redesign. Each fix can be written to work on both 5.2 and 5.3 (dual-target), so the 5.2 UAT keeps running while 5.3 is validated.

| Origin | Count | Items |
|---|---|---|
| Caused by 5.3 | 5 | F1 the theme calls `external_format_text()`, which now throws, on every page with a page header. F2 the tool_certificate duration typo now throws. F3 the 5.2 `ini_get_bool` config.php polyfill would fatal `r.php`/`theme/font.php`. F4 the packaging script is hard-wired to `moodle5.2` and leaks the dev `config.php` if only renamed. B1 the theme edit-mode switch renders nothing. |
| Already broken on the 5.2 UAT today | 6 | F5 `\Mustache_Engine` in sentientia_emails. B2 the Airpay checkout modal never opens. B3 activity dates are missing from activity pages. B4 the Refund and Enrol-user dialogs have no Save button. B5 the tool_certificate image guard is missing from every shipped tree. B6 learnerscript report modals fail. |
| Environment | 1 | E1: local XAMPP (PHP 8.2.12 mod_php, MariaDB 10.11.16, max_input_vars 1000) cannot install 5.3. |

**Infrastructure.** No new IT request is needed. 5.3 requires the same PHP 8.3 and MySQL 8.4 that the 5.2 plan already asked for. Only the MariaDB floor (10.11 to 11.4) and the PostgreSQL floor (16 to 17) moved; neither is the production engine. The upgrade source floor is unchanged at 4.4, so the cutover stays two hops: 4.1.2 to 4.5.x to 5.3.

**Core modifications.** On 5.3 no existing core file needs editing:
- **setuplib guard:** the `setuplib.php` guard and its config.php polyfill can both retire. 5.3 removed the shutdown-path `ini_get_bool()` call that made them necessary. One runtime check must confirm this.
- **`my/` overlays:** these become added files rather than overrides. Vanilla 5.3 `public/my/` has no `dashboard.php` or `switchrole.php`.
- **Vendor patches:** three patches to tool_certificate remain (`reset_caches(): void`, the image guard, and the new duration typo fix). They patch a vendor plugin, not core.

**Not blocking.** These items do not stop the site from running:
- About 20 deprecated `user_*()` call sites will fail PHPUnit on 5.3 with "unexpected debugging", so they are required for the PHPUnit gate.
- PHP 8.4-only CSV deprecations affect 91 calls.
- Bootstrap 5.3 utility classes are missing from the Bootstrap 4.6 theme, so core modal titles render at h2 size.
- Some items are conditional, such as course async deletion and the `/my/dashboard.php` links that depend on the overlay.

**Clean results.**
- `php -l` (PHP 8.4) over all in-scope PHP reports only two implicit-nullable notices.
- There are zero uses of: the removed `external_*` functions, `FEATURE_GROUPMEMBERSONLY`, flat navigation, MoodleNet, theme_classic, the removed renderer methods, the legacy quiz access classes, the `extend_user_menu` hook, and `core/ajax` with async=false.
- There are zero uses of the legacy global `\external_*` classes in any `local_sentientia_*` plugin (a token-level check over 1,981 files).
- All 26 theme `core_renderer` overrides have signatures identical to the 5.3 parents.

**Support window** (from moodledev.io/general/releases):

| Release | General support until | Security support until |
|---|---|---|
| 5.3 LTS | 2027-10-04 | 2029-10-01 |
| 5.2 | 2027-04-19 | 2027-10-04 |
| 4.5 LTS | ended | 2027-10-04 |

---

## 2. Environment deltas vs the 5.2 plan

Source: `53/public/admin/environment.xml` (the 5.3 block is at :5316 and the 5.2 block at :5111).

| Requirement | 5.2 | 5.3 | Change | Local XAMPP today | UAT today | Production target |
|---|---|---|---|---|---|---|
| Upgrade source | >= 4.4 | >= 4.4 | none | n/a | 5.2 | 4.1.2, needs the 4.5.x hop |
| PHP | >= 8.3.0 | >= 8.3.0 (no upper bound, `composer.json` `>=8.3.0`) | none | mod_php 8.2.12 **fails**; php-cgi 8.4 OK | 8.3 OK | 8.3 (bridge version, see below) |
| MySQL | >= 8.4 | >= 8.4 | none | n/a | 8.4 OK | RDS 8.0.44, must go to 8.4 (existing IT request) |
| MariaDB | >= 10.11.0 | **>= 11.4.0** | **raised** | 10.11.16 **fails** | n/a | n/a |
| PostgreSQL | >= 16 | **>= 17** | raised | n/a | n/a | n/a |
| Aurora MySQL | 8.0 | 8.0 | none | n/a | n/a | an alternative for IT only |
| sodium extension | required | required | none | check | OK (5.2 runs) | required |
| 64-bit PHP | required | required | none | OK | OK | required |
| max_input_vars | >= 5000 (hard) | >= 5000 (hard) | none | 1000 **fails** | OK (5.2 runs) | 5000+ |
| Router (`$CFG->routerconfigured` + rewrite to `r.php`) | optional check | optional check; React/ESM UI and fonts need it | more load-bearing | n/a | configured | `deploy/moodle-htaccess.template:75-80` |
| Code layout | `public/` split | `public/` split **plus root `lib/bundles`** (Bootstrap 5.3.8, Font Awesome webfonts, React, design-system) | new | n/a | n/a | the package must carry root `lib/` |
| theme_classic | present | **removed** | removed | n/a | n/a | unused (`theme=sentientia`) |

Notes:
- **PHP 8.3 is the single bridge version.** Hop 1 (4.1.2 to 4.5.x) accepts PHP 8.1 to 8.3 (`ME/docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md:29`). 5.3 needs 8.3 or later. So one PHP 8.3 runtime serves both hops with no PHP switch in the cutover window. The local php-cgi 8.4 can run 5.3 but not 4.5.
- **max_input_vars is a hard failure despite its label.** `check_max_input_vars` is declared `optional` in environment.xml, but it sets level `required` below 5000 (`53/public/lib/upgradelib.php:2733-2741`). Install and upgrade stop.
- **MySQL 8.4 authentication.** MySQL 8.4 disables `mysql_native_password` by default, so the Moodle DB user needs `caching_sha2_password`. UAT already satisfies this because it runs 5.2 on MySQL 8.4.
- **Package README.** The 5.2 package README (`TOP/tools/packaging/build-5.2-standalone.sh:84-85`) says "MySQL 8.4 or MariaDB 10.11". That is correct for 5.2 and wrong for 5.3.
- **Out-of-date stack descriptions.** CLAUDE.md §2 and PROJECT-STATE still describe the 5.1.3 / PHP 8.2.12 / MariaDB 10.11.16 local stack. It stays the 5.1 dev box, but it cannot host 5.3.

---

## 3. Confirmed findings

Legend for the "Breaks on" column:
- **5.3** means new on 5.3.
- **5.2+** means already broken on the 5.2 UAT.
- **all** means broken on 5.1, 5.2 and 5.3.

### 3.1 Fatal (blockers)

**F1. The theme calls `external_format_text()`, which now throws. Every page with a page header dies. Breaks on: 5.3.**
- **Where:**
  - `TOP/theme/sentientia/classes/output/traits/course_view.php:97` (`require_once externallib.php`) and `:103-104` (the call).
  - It is called unconditionally from `core_renderer::full_header()` at `TOP/theme/sentientia/classes/output/core_renderer.php:975`.
- **Evidence:**
  - In 5.3, `external_format_text()` is an argument-less `#[\core\attribute\deprecated(..., final: true)]` stub (`53/public/lib/externallib.php`).
  - `\core\deprecation::emit_deprecation_notice()` throws `coding_exception` when the deprecation is final (`53/public/lib/classes/deprecation.php`, `if ($attribute->final) throw new \coding_exception`).
- **Impact:** `full_header()` is rendered by the course, course_editing, drawers and dashboard layouts. So the dashboard, every course page, every activity page and the admin pages all fatal.
- **Fix:** keep the summary unchanged on empty input and pass the context object, which the 5.3 signature needs (`53/public/lib/external/classes/util.php:528`, it uses `$context->id`).
  - Replace lines 102-104 with `[$course->summary, $course->summaryformat] = \core_external\util::format_text($course->summary, $course->summaryformat, $context, 'course', 'summary', null);`.
  - Guard with `if (!$context) { return $course->summary; }`.
  - Delete line 97.
  - `\core_external\util` exists since 4.2, so this is dual-target.
  - The same bug exists in the non-live `TOP/theme/airpayux/classes/output/core_renderer.php:2142`; exclude airpayux from the package instead of fixing it.

**F2. tool_certificate: the duration-element typo now throws. The "Issue certificates" form crashes. Breaks on: 5.3.**
- **Where:** `TOP/admin/tool/certificate/classes/certificate.php:624`. It passes `['defaulunit' => DAYSECS, 'units' => [DAYSECS, WEEKSECS]]` (note the typo), so the default unit stays MINSECS. It is called from `classes/form/certificate_issues.php:79`, the dynamic form opened from `amd/src/templates-list.js` and `issues-list.js`.
- **Evidence:** `53/public/lib/form/duration.php:112-117` throws "is not one of the units allowed" when the default unit is not in `units`.
- **Impact:** L&D admins cannot issue certificates by hand.
- **Fix:**
  - Change the key to `'defaultunit' => DAYSECS` and tag it `// SENTIENTIA-CORE-MOD (vendor): duration defaultunit typo`.
  - Add a record to `ME/docs/core-mods/`.
  - The fix is valid on 5.1 and 5.2, where the misspelt key is silently ignored today.

**F3. The 5.2 `ini_get_bool()` config.php polyfill fatals `r.php` and `theme/font.php`. Breaks on: 5.3, if the 5.2 recipe is carried over.**
- **Where:** the polyfill recipe in `ME/deploy/apache-sentientia52-vhost.conf.template:20-42` and core-mod record `ME/docs/core-mods/2026-06-11-setuplib-ini-get-bool-guard.md`.
- **Evidence:**
  - 5.3 `public/lib/setuplib.php:530` still declares `ini_get_bool()` without a `function_exists` guard.
  - `public/r.php:28-33` defines `ABORT_AFTER_CONFIG`, then requires `config.php` (which arms the polyfill), then requires `setuplib.php`. Under the 5.2 recipe that produces "Cannot redeclare ini_get_bool()".
  - The reason for the polyfill is gone in 5.3. `core\shutdown_manager::request_shutdown()` now reads `ini_get('child_terminate')` directly (`53/public/lib/classes/shutdown_manager.php:245-256`).
  - The only other early caller, `setup.php:766`, runs after `setuplib.php` is loaded at `:626` and after the `ABORT_AFTER_CONFIG` return at `:607`.
- **Impact:** if a 5.3 instance gets the 5.2 config.php, every ESM/React module load and every font cache miss returns 500.
- **Fix:** the 5.3 `config.php` and vhost recipe carry no polyfill, and no core edit is applied. The polyfill and guard stay for the 5.2 UAT instance only.
  - Confirm at runtime under php-cgi that `lib/javascript.php`, `theme/styles.php`, `r.php` and `theme/font.php` all return 200.
  - If any class-1 URL still 500s, re-apply both halves. The hunk applies unchanged at `setuplib.php:530`.

**F4. The packaging script is hard-wired to 5.2 and has a config.php leak trap. Breaks on: 5.3.**
- **Where:** `TOP/tools/packaging/build-5.2-standalone.sh`:
  - `:24-25` and `:79` say "Build 20260519".
  - `:32`, `:72-73` and `:103` hard-code `moodle5.2`.
  - `:46` hard-codes the output name.
  - `:84-85` give the 5.2 prerequisites.
  - `:95-96` say to upgrade by extracting over the root.
  - `:101-102` exclude `moodle5.2/config.php` and `moodle5.2/public/config.php`.
  - `:109` verifies with `^moodle5.2/(public/)?config\.php$`.
- **Impact, the security trap:** if only the tree directory is renamed to `moodle5.3`, two things happen together:
  - The tar excludes match nothing, so the dev `config.php`, with its DB credentials, goes into the zip. This repeats the 2026-08-03 leak.
  - The verify grep also matches nothing and prints `config.php=0`, which is false assurance.
- **Impact, other problems:**
  - "Extract over the root" leaves stale 5.2 files beside the 5.3 ones: `theme/classic`, the `blocks/timeline` AMD and templates, `theme/boost/amd/*/bootstrap`, `public/lib/fonts`.
  - `TOP/tools/packaging/package-sentientia.ps1:75,89` packages `public/` only. On 5.3 that omits root `lib/bundles` (Font Awesome webfonts, Bootstrap, React, design-system; resolved by `53/public/lib/classes/output/theme_config.php:1959-1960`), so icons and all React/ESM UI fail.
  - The overlay copies from the 5.1 webroot, not from git (`ME/tools/overlay-airpay-customs.ps1:25-26`). So a fix committed to git does not necessarily reach the package.
- **Fix:** the build-5.3 recipe in section 6.

**F5. sentientia_emails calls `new \Mustache_Engine()`, which no longer exists. Breaks on: 5.2+.**
- **Where:**
  - `ME/local/sentientia_emails/classes/email_renderer.php:64`
  - `ME/local/sentientia_emails/preview_ajax.php:31`
  - `ME/local/sentientia_emails/classes/external/template_api.php:197` (`TOP/local/...:199`; the twins differ)
- **Evidence:**
  - 5.2 and 5.3 autoload Mustache 3.0 only as PSR-4 `\Mustache\*` (`53/public/lib/classes/component.php:147`).
  - The PSR-0 map is empty (`:111-113`; 5.1 still had `'Mustache' => .../src/Mustache`).
  - The legacy aliases live only in `public/lib/mustache/src/compat.php`, which nothing loads.
  - There is no `vendor/` directory and no entry in renamedclasses.php.
  - Class-not-found is an `\Error`, and all three sites catch only `\Exception`.
- **Impact:**
  - Any email whose template has a tenant DB override (`override->body_html`) fatals in the send path.
  - The ajax preview returns 500.
  - The template-editor preview web service fails.
- **Fix:**
  - Add one factory, for example `\local_sentientia_emails\mustache_factory::engine()`, returning `class_exists(\Mustache\Engine::class) ? new \Mustache\Engine() : new \Mustache_Engine()`. **Correction (2026-10-08, round 1): do not write it that way.** On 5.1 the probe `class_exists(\Mustache\Engine::class)` goes through the PSR-0 loader, loads `src/Mustache/Engine.php` (which declares `\Mustache_Engine`) with a plain `require()`, and the second call, or a first call after any template render, is an uncatchable "Cannot declare class Mustache_Engine" fatal. The factory as shipped asks for an already-loaded `\Mustache\Engine` without autoloading, then for `\Mustache_Engine` by name (safe on every version), and only then builds `\Mustache\Engine`.
  - Use it at all three sites and change `catch (\Exception` to `catch (\Throwable`.
  - Apply in both trees.

### 3.2 Broken behaviour (blockers)

**B1. The theme edit-mode switch renders no control, so there is no UI to turn editing on. Breaks on: 5.3.**
- **Where:**
  - `TOP/theme/sentientia/classes/output/traits/page_helpers.php:96-110` (`edit_switch()` passes `legacyseturl`, `pagecontextid`, `pageurl`, `sesskey` and `checked` only).
  - `TOP/theme/sentientia/config.php:190` (`haseditswitch = true`, so `edit_button()` returns nothing).
  - It is used by `templates/navbar.mustache:135` and `layout/course.php:134`.
- **Evidence:**
  - 5.3 `public/lib/templates/editswitch.mustache:40-89` wraps the whole control in `{{#reactprops}}{{#react}}...{{/react}}{{/reactprops}}` and inits `core/edit_switch` with `{{elementid}}`.
  - Core `core_renderer::edit_switch()` builds both keys (`53/public/lib/classes/output/core_renderer.php:2601`ff).
  - The theme supplies neither, so only the hidden inputs and a `<noscript>` submit render.
- **Impact:** teachers, authors and admins cannot enter editing mode on any page.
- **Fix:**
  - Ship `TOP/theme/sentientia/templates/core/editswitch.mustache` as a verbatim copy of the 5.2 core template (`C:/xampp/htdocs/moodle5.2/public/lib/templates/editswitch.mustache`: `.form-check.form-switch`, `{{uniqid}}-editingswitch`, `editSwitch.init('{{uniqid}}-editingswitch')`).
  - `core/edit_switch` AMD `init(id)` is unchanged in 5.3 (`53/public/lib/amd/src/edit_switch.js:111`).
  - A theme override beats core on 5.1, 5.2 and 5.3. The 5.2 look is kept and no design-system CSS is needed.

**B2. Airpay payment gateway: the checkout modal never opens. Breaks on: 5.2+.**
- **Where:**
  - `TOP/payment/gateway/airpay/amd/src/gateways_modal.js:17,34` (`import ModalFactory from 'core/modal_factory'`) and `amd/build/gateways_modal.min.js`.
  - The ME twin is byte-identical.
- **Evidence:** `core/modal_factory` is absent in 5.2 and 5.3 (`public/lib/amd/src`, `amd/build`), and there is no alias.
- **Impact:** `core_payment/gateways_modal` loads `paygw_airpay/gateways_modal` after the learner picks Airpay. The RequireJS dependency 404s, the module never loads, and no payment can start.
- **Fix:**
  - Use `import Modal from 'core/modal';` and `await Modal.create({body: await Templates.render('paygw_airpay/airpay_button_placeholder', {}), show: true, removeOnClose: true})`. This is the core paygw_paypal shape on 5.2/5.3.
  - Rebuild `amd/build/gateways_modal.min.js` and `.map` in both trees.
  - Delete the dead `amd/src/form_submit.js` and its build files, which nothing requires.

**B3. The theme `full_header` drops `headerextras`, so activity dates (Opened/Due) disappear. Breaks on: 5.2+.**
- **Where:**
  - `TOP/theme/sentientia/classes/output/core_renderer.php:982` (passes `headeractions` only).
  - `TOP/theme/sentientia/templates/full_header.mustache:57-59` (no `{{#headerextras}}`).
  - The theme sets no `activityinfoinheader` layout option.
- **Evidence:** `53/public/lib/classes/output/activity_header.php:298-311` puts the dates into `$PAGE->add_header_extras()`, and `pagelib.php:2410-2419` holds them. The same code is in 5.2 (`activity_header.php:306`).
- **Fix:**
  - After line 982 add `$header->headerextras = method_exists($this->page, 'get_header_extras') ? $this->page->get_header_extras() : [];`. The guard keeps 5.1 working.
  - In `full_header.mustache` after the `headeractions` block add `<div class="header-extras-container ml-auto">{{#headerextras}}<div class="header-extra ml-2">{{{.}}}</div>{{/headerextras}}</div>`.

**B4. The Refund order and Enrol user dialogs have no Save button. Breaks on: all.**
- **Where:**
  - `ME/local/sentientia_cart/amd/src/admin_orders.js:23-40`.
  - `ME/local/sentientia_courses/amd/src/enrolledusers.js:76,85`.
  - The twins are identical, and the build files carry the same code.
- **Evidence:**
  - `Modal.create({modalType: 'SAVE_CANCEL'})` is not a core API. `53/public/lib/amd/src/modal.js:229-231` sets `modalConfig.type = this.TYPE` (default), so a base modal with an empty footer is built and `ModalEvents.save` never fires.
  - The `core/modal_factory` fallbacks are dead on 5.2/5.3.
  - The same bug was already fixed in sentientia_request under WF-024 (`ME/local/sentientia_request/amd/src/decide.js:22-38`).
- **Fix:**
  - `import ModalSaveCancel from 'core/modal_save_cancel'; ModalSaveCancel.create({title, body, show: true})`.
  - Delete both modal_factory branches and rebuild both `.min.js` files in both trees.

**B5. The tool_certificate image-element guard (core-mod 2026-05-23) is missing from every shipped tree. Breaks on: all.**
- **Where:**
  - The guard exists only in `ME/admin/tool/certificate/element/image/classes/element.php:205-214`.
  - `TOP/admin/tool/certificate/element/image/classes/element.php:204` (the package source) and the 5.1 webroot and 5.2 staging copies are unguarded.
  - The overlay copies from the webroot (`ME/tools/overlay-airpay-customs.ps1:200`).
- **Impact:** the recorded P0 TypeError on non-image files is back in every package.
- **Fix:**
  - Apply the guard, tagged `SENTIENTIA-CORE-MOD`, to the TOP file (`if ($fileimageinfo === false || !is_array($fileimageinfo)) { $fileimageinfo = ['width' => 140, 'height' => 140]; }`).
  - Then delete the single-file ME twin or sync it.

**B6. learnerscript (vendor block, shipped by the overlay) report modals fail. Breaks on: 5.2+.**
- **Where:**
  - `TOP/blocks/learnerscript/amd/src/ajax.js`, `ajaxforms.js`, `helper.js`, `newgroup.js` (and their build files).
  - `TOP/blocks/learnerscript/js/design.js:252`.
  - All depend on `core/modal_factory`.
  - The overlay ships it at `ME/tools/overlay-airpay-customs.ps1:189`.
- **Context:** other repo-root BizLMS blocks with the same dependency (achievements, my_event_calendar, myskills, trainerdashboard, trending_modules) are not shipped. The overlay filters on `sentientia_*`/`airpay_*` plus the three vendor blocks.
- **Fix:** Nitin decides one of:
  - (a) Port the five modules to `core/modal` / `core/modal_save_cancel`. Also note that `learnerscript` and `reportdashboard` `externallib.php` extend the legacy global `external_api`, which only logs deprecation debugging.
  - (b) Drop learnerscript, reportdashboard and reporttiles from the package if the Sentientia reports replace them.

### 3.3 Environment (blocker)

**E1. No 5.3 runtime exists on this box.**
- **Database:** MariaDB 10.11.16 is below 11.4.
- **PHP:** mod_php 8.2.12 is below 8.3.
- **Settings:** max_input_vars is the XAMPP default of 1000.
- **Docker:** the daemon is not running.
- **UAT:** UAT (PHP 8.3, MySQL 8.4) already meets every 5.3 requirement.
- **Fix:** see the runtime plan. Prefer a portable MySQL 8.4 over MariaDB 11.4 for parity with UAT and RDS.

### 3.4 Conditional or latent (not blockers; needs a guard or a gate check)

| # | Finding | Where | Breaks on | Action |
|---|---|---|---|---|
| C1 | `/my/dashboard.php` links resolve only through the `my/` overlay. Vanilla 5.3 `public/my/` has no `dashboard.php` (verified). | Plugins: `ME/local/sentientia_cart/index.php:26`; `sentientia_emails/classes/email_context.php:36,108,147`; `parity_senders.php:441`; `sentientia_notifications/classes/rule_engine.php:1219`; `sentientia_platform/classes/customer.php:259` (PWA `start_url`, also stored by `db/upgrade.php:215`); `hook_callbacks.php:63`; `sentientia_pwa/manifest.php:57`; `sentientia_users/signup.php:23`. Theme: `core_renderer.php:694,1203,1344,1608`; `user_menu.php:209,230,619,625`. | 5.3 without the overlay | Keep shipping the overlay (it is required). Separately repoint links to `/my/`, add a new upgrade step that rewrites stored `start_url`, and update the tests at `sentientia_platform/tests/customer_brand_test.php:68,95` and `cli/verify_brand_resolver.php:32`. |
| C2 | With `moodlecourse/enablecourseasyncdeletion` on (default 0), `delete_course()` only marks `course.deletioninprogress` and queues a task (`53/public/lib/moodlelib.php:4467,4523`). `course_manager::delete()` would report success while the course still exists, and listings do not filter the new column. | `ME/local/sentientia_courses/classes/course_manager.php:973`; listings in `TOP/theme/sentientia/layout/frontpage.php:52`, `layout/dashboard.php:289-290`, `blocks/sentientia_compliance/classes/audit.php:155`, `export.php:43` | 5.3, if the setting is on | `delete_course($course, false, false)`. The extra argument is ignored on 5.1/5.2. Filter listings only behind `$DB->get_columns('course')` feature detection. |
| C3 | BS4 `data-toggle` attributes in plugin templates work only because the theme vendors Bootstrap 4.6 JS. | `ME/local/sentientia_evaluation/templates/questions.mustache:146`; `sentientia_org/templates/manage.mustache:97,118`; `org_node.mustache:21` | only if the theme moves to core Bootstrap 5.3 | Emit `data-toggle` and `data-bs-toggle` side by side. |
| C4 | `window.bootstrap` exists in no tree, so the switchboard "Review changes" confirmation is skipped and submits directly. The skills self-rate modal works only through the theme's BS4 jQuery plugin. | `ME/local/sentientia_platform/amd/src/switchboard.js:148-153`; `ME/local/sentientia_skills/amd/src/skill_actions.js:307-318` | all | Use `core/modal_save_cancel` / `core/modal_cancel` `.create({show: true})`. |
| C5 | React-rendered core UI is styled only by the design-system SCSS that Boost's preset imports. The standalone theme imports none. `block_timeline` is now React-only and is in the default `/my` block list. | theme SCSS; upgraded user dashboards that still hold a Timeline instance | 5.3 | Keep Timeline off Sentientia dashboards. Optionally import `lib/bundles/design-system` tokens behind a `file_exists($CFG->root.'/lib/bundles/design-system')` guard, because a direct import compiles only on 5.3. |
| C6 | The bundled `tool_certificate` 4.5.7 declares `supported = [400, 405]`. On 5.3 this only shows "not supported" on the plugin check page (`53/public/lib/classes/plugin_manager.php:950-984`); `incompatible` is the only blocking check. It also uses legacy global `\external_*` classes (deprecation debugging). | `TOP/admin/tool/certificate/version.php:32` | informational | Take a 5.x-compatible upstream release before cutover, then re-apply or drop the vendor patches. |
| C7 | Root `.htaccess` `ErrorDocument` paths hard-code the `/moodle` alias, which is wrong on a docroot vhost such as UAT. | `ME/deploy/moodle-htaccess.template:26-29` | all | Parameterise per deployment. The rewrite block (`:75-80`) is exactly what 5.3 needs. |
| C8 | `http_build_query()` without a separator follows the ini setting: `&amp;` on 5.1, `&` on 5.2/5.3. Correct on 5.3; wrong on 5.1. | `ME/local/sentientia_content_market/classes/adapter/{coursera,go1,skillsoft,udemy_business}_provider.php`; `sentientia_integrations/classes/keka_client.php:676,706` | 5.1 only | Pin `http_build_query($p, '', '&')`. |
| C9 | The audit-log allowlist names event classes that exist in no Moodle version (`user_loggedin_as`, `course_visibility_updated`, `users_bulk_imported`), so those entries never match. | `ME/local/sentientia_platform/classes/audit_log.php:64,69,86` | all (latent) | Use `\core\event\user_loggedinas` and drop the other two. |
| C10 | A dev seed CLI requires the deleted `badges/lib/awardlib.php` and dies. The line is unused. | `ME/local/sentientia_org/cli/seed_badges.php:28` (and the TOP twin) | 5.2+ | Delete the line. |
| C11 | The twin trees drift. `template_api.php` differs between TOP and ME. paygw `get_form.php` differs, and the ME copy (which ships) still uses the legacy global classes. The overlay copies from the 5.1 webroot, not git. | `TOP/local` vs `ME/local`; `TOP/payment` vs `ME/payment` | process | Reconcile the twins. Add payment, enrol, quizaccess and admin/tool/certificate to `check-tree-drift.php`. Build from git (section 6). |
| C12 | `theme/airpayux` (not served) is broken on 5.2 and 5.3: `external_format_text`, `M.util.set_user_preference` (`amd/src/drawers.js:446`), FA4 fonts, the editswitch problem. | `TOP/theme/airpayux/**` | 5.2+ | Exclude it from the package. Retiring it needs its own decision. |

### 3.5 Deprecations (not runtime blockers; needed for the PHPUnit-on-5.3 gate)

5.3 moved 25 `user/lib.php` functions to `\core\user::*`. The old globals now live in `lib/deprecatedlib.php` and emit `DEBUG_DEVELOPER` on every call. PHPUnit fails tests that reach them with "unexpected debugging". The replacements exist only on 5.3 (`53/public/lib/classes/user.php:1797` `create_user`, `:1887` `update_user`, `:2405` `count_login_failures`, `:2423` `convert_text_to_menu_items`, `:2811` `can_view_profile`; none are present in 5.2). So every call needs a `method_exists` guard while UAT is on 5.2.

| # | Finding | Where | Fix |
|---|---|---|---|
| D1 | Deprecated `user_create_user` / `user_update_user` in plugins | `ME/local/sentientia_api/classes/scim/handler.php:442`; `sentientia_integrations/classes/keka_client.php:338,367,385`; `sentientia_users/classes/hrms_importer.php:451,471`; `signup_service.php:186`; `user_manager.php:632,705,943`; dev CLIs `sentientia_pages/cli/seed_production_data.php:147,191`, `seed_users.php:82` | Add one shim, `\local_sentientia_platform\compat\user_api::create()/update()`. It calls `\core\user::*` when the method exists, otherwise it requires `user/lib.php` and calls the old function. Cast arrays to `(object)`. |
| D2 | Deprecated user API calls in the theme | `TOP/theme/sentientia/classes/output/core_renderer.php:1190` (`user_count_login_failures`), `:1234` (`user_convert_text_to_menu_items`, which loses `$page`); `traits/context_header.php:99` (`user_can_view_profile`); `classes/language_switcher.php:201` (`user_update_user`) | Use a `method_exists(\core\user::class, ...)` ternary per call. The theme must not depend on a local plugin. |
| D3 | Legacy global `\external_*` classes resolve through `lib/db/renamedclasses.php` with debugging | `ME/payment/gateway/airpay/classes/external/get_form.php:25-42` (this is the copy that ships); vendor `TOP/admin/tool/certificate/classes/external/*`, `lib.php:140`; learnerscript/reportdashboard `externallib.php` | Copy the TOP `get_form.php` (already `use core_external\...`) over the ME twin. Vendor files: take upstream releases. |
| D4 | PHP 8.4 implicit nullable `array $options = null` (confirmed with PHP 8.4 `php -l`) | `TOP/theme/sentientia/classes/output/core_renderer_maintenance.php:193` | Change to `?array $options = null`. |
| D5 | PHP 8.4 only: `fputcsv` / `fgetcsv` / `str_getcsv` without `$escape` (91 of 95 calls). This does not affect PHP 8.3 hosts (UAT and production), but it does show on the local php-cgi 8.4. | 19 files, e.g. `ME/local/sentientia_analytics/export.php`, `sentientia_compliance_report/export.php`, `sentientia_live/trainer/export.php`, `sentientia_users/classes/{bulk_csv_processor,bulk_import_processor,hrms_importer}.php` | Pass `',', '"', '\\'` explicitly. The output is byte-identical. |
| D6 | Deprecated drawer icons `t/index_drawer`, `t/blocks_drawer(_rtl)`. They still render and only get a CSS class. | `TOP/theme/sentientia/templates/course_editing.mustache:111,127-128` | Switch to `e/sidebar_left` / `e/sidebar_right` once the 5.1 tree is retired. Those icons do not exist on 5.1. |

### 3.6 Checked and rejected (verified not a 5.3 problem)

| Pattern | Why it is not a problem |
|---|---|
| Flat navigation | `nav-drawer.mustache` and `flat_navigation.mustache` are dead templates. No `$PAGE->flatnav` is used anywhere. `BLOCK_ADDBLOCK_POSITION_FLATNAV` still exists. |
| Removed renderer methods | `htmllize_file_tree()` in `core_renderer_maintenance.php:154` is a standalone stub with no `parent::` call. |
| User-menu hook | No `extend_user_menu` / `add_navitem` anywhere. The theme builds its own `navitems`. |
| Bootstrap 4 vs 5 in the theme | The theme ships its own BS4.6 jQuery plugins and its own `theme_sentientia/bootstrap/*` AMD modules. It never imports `theme_boost/bootstrap/*` or `bs4-compat`. |
| Navigation markup | The theme keeps `{{> core/moremenu}}`, which is byte-identical in 5.3. Only Boost moved to the React nav. |
| Tertiary navigation | Layouts already use `\core\output\select_menu`. |
| Import map ordering | `head.mustache` emits `standard_head_html` first, and the override calls the parent first. |
| Login form context | `hasinstructions` appears only in the dead OTP template. |
| myoverview fullname | `{{{fullname}}}` receives exporter-cleaned text. |
| Font Awesome | `[[font:core|fa-solid-900.woff2]]` resolves to `lib/bundles/fontawesome/webfonts`, verified present. |
| quizaccess rule | `access_rule_base.php` and `access_manager.php` are byte-identical 5.2 to 5.3, and the rule uses only the new classes. |
| customfield handler cache | `issue_handler` keeps its own singleton with a matching `create()` signature. |
| Persistent records keyed by id | Callers only `foreach` over the result. |
| Email hook | All `email_to_user()` callers treat `false` as failure, and no plugin subscribes to the hook. |
| `NO_MOODLE_COOKIES` | Defined before `config.php`, so it is still honoured. |
| Dynamic properties | None found. |
| PHP `${}` interpolation | All hits are JavaScript template literals. |
| Duration elements in plugins | None in `local_sentientia_*`. |
| AI token columns | They belong to the plugin's own ledger table. |
| Question versions | Read-only counts only. |
| Colour modes | The theme runs its own dark mode. |
| Sticky footer | The override has no `h-100`. |
| Removed core plugins | Only dead CSS references. |

---

## 4. Core-mods verdict per record (`ME/docs/core-mods/`; there is no root `docs/core-mods/`)

| Record | What it is | 5.3 verdict | Action |
|---|---|---|---|
| `2026-05-20-moodle-to-sentientia-rename.md` | A rename map of user-visible "Moodle" strings, pending approval. It does not modify code. | Version independent | None for 5.3. If it is ever implemented as core string overrides, re-check that the keys exist in 5.3 (`lang/en/deprecated.txt`). |
| `2026-05-23-certificate-image-imageinfo-guard.md` | A guard in tool_certificate `element/image/classes/element.php` | **Still required** (`get_imageinfo()` still returns `false` for non-images), but **missing from every shipped tree** | Re-apply to `TOP/admin/tool/certificate/...:204` (B5). Add a "5.3: still required; source of truth is TOP" section. |
| `2026-05-29-tool_certificate-hi-pack.md` | An additive Hindi lang file, staged and not applied | Version independent | None. |
| `2026-06-04-open-substrate-ownership.md` | Raw `ALTER` adding 37 `open_*` columns to `user` and 18 to `course` | **No collision.** 5.3 adds `course.deletioninprogress` and no `open_*` names (`53/public/lib/db/install.xml`). MySQL 8.4 with these columns is already proven on the 5.2 UAT. | Keep. Runtime: confirm the 4.5 to 5.3 upgrade alters `course` cleanly with the extra columns. `check_database_schema.php` will list them as extra columns, which is expected. |
| `2026-06-11-setuplib-ini-get-bool-guard.md` | A `function_exists` guard on `ini_get_bool()` in `public/lib/setuplib.php`, plus a config.php polyfill | **Retire on 5.3** (F3). 5.3 `shutdown_manager.php:245-256` no longer calls it, and no `ABORT_AFTER_CONFIG` path reaches it. Keeping only the polyfill is fatal. | Do not apply the guard; leave the polyfill out of 5.3 config.php. Add a 5.3 addendum. Gate it on the php-cgi render smoke. Fallback: both halves, with the hunk unchanged at `:530`. |
| `2026-06-19-my-overlays-5.2.md` | `my/dashboard.php` (redirect shim), `my/switchrole.php`, `my/templates/dropdown.mustache` | The premise ("core ships its own copies") does not hold on 5.3: `53/public/my/` has only `classes`, `tests`, `courses.php`, `index.php`, `indexsys.php`, `lib.php`, `upgrade.txt`. **`dashboard.php` and `switchrole.php` are still required** and become pure additions. **`dropdown.mustache` is referenced by nothing.** | Rewrite as an "additive overlay on 5.3" record. Keep the cutover gate (`/my/dashboard.php` resolves; role switch works for an org-role user). Stop shipping `dropdown.mustache`. Delete the stale `ME/my_dashboard_redirect.php`. |
| `2026-09-03-tool-certificate-5.2-reset-caches.md` | `issue_handler::reset_caches(): void` | **Still required.** 5.3 `customfield/classes/handler.php:116` declares `reset_caches(): void`. `create(int $itemid = 0)` at `:99` matches `issue_handler.php:52`. The reverted handler caching (`CACHE_HANDLER_INSTANCES`, `:46`) does not affect it. | Keep (`TOP/admin/tool/certificate/classes/customfield/issue_handler.php:289`). Drop it when a vendor release carries `: void`. |
| **NEW**: tool_certificate duration typo | `certificate.php:624`, `defaulunit` to `defaultunit` | Required on 5.3 (F2) | New record and new `SENTIENTIA-CORE-MOD` tag. |

`ME/docs/core-mods/README.md` still says "Empty as of Day 0", so the index needs updating.

---

## 5. Theme impact

**theme_sentientia** is the live theme: standalone, `$THEME->parents = []`, vendored Bootstrap 4.6.0, version `2026093002`, top-level only.

| Area | 5.3 impact | Severity | Ref |
|---|---|---|---|
| Course summary in the page header | `external_format_text()` throws | **Fatal** | F1 |
| Edit-mode switch | The core template needs `reactprops`/`elementid`, so nothing renders | **Broken** | B1 |
| Activity dates in the header | `headerextras` is not rendered | **Broken** (since 5.2) | B3 |
| Core modal titles | Core renders `<h2 class="modal-title fs-5">`, and there is no `.fs-5` in the BS4.6 theme, so titles render at h2 size. `.text-bg-*`, `.bg-body-*`, `ms-/me-`, `fw-` are also missing. | Cosmetic, visible on every core modal | Add the shims to `scss/moodle/partials/_bs5-compat.scss` |
| Design-system / React UI | Unstyled `mds-*` markup wherever core React renders (`block_timeline`) | Cosmetic, conditional | C5 |
| `core/block` override | A pre-5.2 copy: `.pull-right`, the `d-inlines` typo, no `tabindex=-1` on the skip anchor | Cosmetic/a11y | Re-sync with `53/public/lib/templates/block.mustache`, keeping BS4 classes |
| `core_course/coursecard` override | Keeps `mx-1` | Cosmetic | Drop `mx-1` (`templates/core_course/coursecard.mustache:42`) |
| Deprecated user API | 4 call sites | PHPUnit gate | D2 |
| Implicit nullable | `core_renderer_maintenance.php:193` | PHPUnit gate on PHP 8.4 | D4 |
| Fonts | Resolve from root `lib/bundles` | None, if the package carries root `lib/` | F4 |
| Unchanged on 5.3 | `core/moremenu`, `select_menu` tertiary nav, `loginform` override, `head.mustache` order, 26 renderer override signatures, `core_form/*`, `block_myoverview/*`, courseindex drawer, `email_html` overrides (byte-identical 5.2 to 5.3), own dark mode | None | Section 3.6 |
| Dead weight | `templates/nav-drawer.mustache`, `flat_navigation.mustache`, `core/otploginform.mustache`; the `htmllize_file_tree()` stub | None | Optional cleanup |

**theme_airpayux** is not served. It is fatal on 5.3 and already broken on 5.2 (C12): exclude it from the 5.3 package.

**Strategic note.** 5.3 core increasingly assumes Bootstrap 5.3 classes, `--bs-*` variables, design-system tokens and React components. The vendored BS4.6 theme will keep needing shims at each upgrade. Re-basing the theme SCSS on `lib/bundles/bootstrap` (5.3.8) is a separate design decision for a future ADR, not part of this upgrade.

---

## 6. Packaging: the build-5.3 recipe

Replace `build-5.2-standalone.sh` with a parameterised `tools/packaging/build-standalone.sh --target 5.3`. It must still build 5.2 while UAT runs 5.2.

1. **Variables.** Define `TARGET=5.3`, `TREE_NAME=moodle5.3`, `RELEASE="5.3 (Build: 20261005)"`, and `STAGE_PARENT=D:/Claude Local/moodle53-stage`.
   - Every README string, output name (`Sentientia-LMS-5.3-Complete-Standalone-<date>.zip`), theme/plugin path, tar `--exclude` and verify regex is derived from them.
   - No literal `moodle5.2` or `20260519` may remain.
2. **Base.** Copy the SHA-256-verified vanilla extract `D:/Claude Local/moodle53/moodle` to the stage. Never overlay into the verified extract; keep it pristine for diffs.
3. **Source from git, not from the 5.1 webroot.** Run the overlay with `-Source` set to a git export of the fixed branch and `-Target "$STAGE/$TREE_NAME/public"`:
   - `ME/local/sentientia_*` and `ME/blocks/*` (the tree UAT runs)
   - `TOP/theme/sentientia`
   - the reconciled `payment/gateway/airpay`
   - `TOP/admin/tool/certificate` with all three vendor patches
   - `enrol/sentientiasub`
   - `mod/quiz/accessrule/sentientia_proctoring`
   - learnerscript, reportdashboard and reporttiles only if B6 option (a) is chosen
4. **Core-adjacent files.** Add `public/my/dashboard.php` and `public/my/switchrole.php` (not `my/templates/dropdown.mustache`). Generate `public/.htaccess` from `ME/deploy/moodle-htaccess.template` with the `ErrorDocument` base parameterised.
5. **Exclusions.**
   - **Apply no core-file edits:** `public/lib/setuplib.php` stays vanilla.
   - **Exclude from the zip:** `$TREE_NAME/config.php`, `$TREE_NAME/public/config.php`, `theme/airpayux`, `node_modules`, `_stale-*`, `*.log`.
6. **Verify. Fail the build** if any of these is wrong:
   - `config.php` count = 0, using the tree-name-aware regex
   - `lib/bundles/bootstrap/`, `lib/bundles/fontawesome/webfonts/fa-solid-900.woff2`, `lib/components.json` and `lib/plugins.json` are present
   - `public/lib/fonts`, `public/theme/classic` and `public/theme/airpayux` are absent
   - `public/my/dashboard.php` and `public/theme/sentientia/version.php` are present
   - `SENTIENTIA-CORE-MOD` count in `public/lib/setuplib.php` = 0
   - the shipped `amd/build/*.min.js` contains no `core/modal_factory`, with B6 handled
   - no `\Mustache_Engine` remains in shipped PHP
7. **README** with the 5.3 prerequisites:
   - PHP >= 8.3, 64-bit, with intl, soap, sodium, gd, mbstring, xml, zip, curl, opcache; `max_input_vars >= 5000`
   - MySQL 8.4+ with the `caching_sha2_password` user, or MariaDB 11.4+, or PostgreSQL 17+
   - upgrade only from 4.4+, so the live 4.1.2 needs the 4.5.x hop first
   - Apache rewrite to `r.php` plus `$CFG->routerconfigured`
   - a `config.php` with **no** `ini_get_bool` polyfill
8. **Upgrade wording.** Replace "extract over the root" with these steps:
   1. Move the old code tree aside.
   2. Extract the package into a clean directory.
   3. Copy back `config.php`.
   4. Run `admin/cli/upgrade.php --non-interactive`, then `purge_caches.php`, then cron.

   This is the same rule the migration plan already sets for each hop.
9. **Retire `TOP/tools/packaging/package-sentientia.ps1` for 5.3.** It packages `public/` only, which misses the root `lib/bundles`.
10. **Hash and tag.** Pin the hash in `UAT-SENTIENTIA-DEPLOY-CHECKLIST.md` and tag `v<next>-sentientia-5.3-package-<date>`.

The 5.3 tree has no `vendor/`. `check_composer_dependencies_installed` is an optional check, and runtime libraries remain under `public/lib`. Only PHPUnit needs `composer install --dev`.

---

## 7. What cannot be known without a runtime test

1. **php-cgi bootstrap.** Static reading says no `ABORT_AFTER_CONFIG` path under php-cgi reaches `ini_get_bool()` on 5.3. Only a render smoke of `lib/javascript.php`, `theme/styles.php`, `r.php` and `theme/font.php` proves it.
2. **Upgrade on real data.** The 4.5.10 to 5.3 upgrade has not been run on the BizLMS-shaped database (`open_*` columns on `user`/`course`, 93 legacy BizLMS tables left in place). Its duration and its 5.2/5.3 steps are unknown: the `task_adhoc.identityhash` backfill, the `assign_allocated_marker` migration, the move of the AI token columns, the classic-theme reset, `course.deletioninprogress`.
3. **The 5.2 UAT database to 5.3** has not been upgraded either.
4. **Signature fatals on write paths.** These appear only when a class first loads on a write path. The 5.2 `reset_caches` fatal was caught only by issuing a real certificate. Vendor plugins (tool_certificate, learnerscript) are the main risk.
5. **React/ESM delivery.** It is unknown whether the import map plus `r.php` serve correctly behind the UAT vhost and the root `.htaccess`.
6. **Visual result** of the 5.3 core templates inside a Bootstrap 4.6 theme: modals, block headers, the course index drawer, the activity header with extras, upgraded `/my` pages that still hold a Timeline block.
7. **Theme SCSS compile** with the new shims, under 5.3 scssphp. The version is unchanged from 5.2, but the compile has not been run.
8. **PHPUnit on 5.3** (`failOnDeprecation`, `failOnWarning`), including the vendor plugin suites.
9. **Raw plugin SQL** on MySQL 8.4 / MariaDB 11.4. Reserved words were checked: none are used.
10. **Production data facts:**
    - `theme = 'classic'` on any course, category, cohort or user
    - Timeline block instances on user dashboards
    - the size of `task_adhoc`
11. **Performance:** the hook-callback cache, React bundle payload, and php-cgi stability under authenticated load on Windows. A crash is already recorded with OPcache on.
12. **Payment end to end:** Airpay checkout through to the callback, after B2.

---

## 8. Evidence index (5.3 tree)

| Topic | Location in `53/` |
|---|---|
| Environment blocks | `public/admin/environment.xml:5316` (5.3), `:5111` (5.2) |
| max_input_vars check | `public/lib/upgradelib.php:2733-2741` |
| Final external stubs | `public/lib/externallib.php`; `public/lib/classes/deprecation.php` (`emit_deprecation_notice`) |
| Edit switch | `public/lib/templates/editswitch.mustache:40-89`; `public/lib/classes/output/core_renderer.php:2601` |
| Duration element | `public/lib/form/duration.php:112-117` |
| Mustache autoload | `public/lib/classes/component.php:111-113,147`; `public/lib/mustache/src/compat.php` |
| setuplib and bootstrap | `public/lib/setuplib.php:530`; `public/lib/setup.php:442,607,626,766`; `public/lib/classes/shutdown_manager.php:240-256`; `public/r.php:28-33` |
| Activity header extras | `public/lib/classes/output/activity_header.php:298-311`; `public/lib/pagelib.php:2410-2419` |
| Course deletion | `public/lib/moodlelib.php:4467,4523` |
| `\core\user` replacements | `public/lib/classes/user.php:1797,1887,2405,2423,2811` |
| Customfield handler | `public/customfield/classes/handler.php:46,99,116` |
| Fonts | `public/lib/classes/output/theme_config.php:1959-1960`; `lib/bundles/fontawesome/webfonts/` |
| Plugin support range | `public/lib/classes/plugin_manager.php:950-984` |
| Modal API | `public/lib/amd/src/modal.js:229-231` |
| `core/edit_switch` | `public/lib/amd/src/edit_switch.js:111` |
| Layout | `public/my/` listing; no `public/.htaccess`, no `public/lib/fonts`, no `public/theme/classic` |

Release dates: https://moodledev.io/general/releases
