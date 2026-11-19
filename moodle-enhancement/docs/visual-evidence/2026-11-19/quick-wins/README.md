# Quick wins, 19 Nov 2026: visual evidence

Five post-demo quick wins from `docs/plans/2026-11-19-POST-DEMO-PLAN.md` §2, verified in a browser on the
**local** site only (http://localhost:8080, XAMPP, Moodle 5.1.3+). Base: branch `claude/gap-integration` at
`c7ab20d53` (what UAT runs since 09:12 today). Nothing here was deployed to UAT, nothing was saved through a form,
and no feature flag was flipped.

| Quick win | Component and version | Result | Shots |
|---|---|---|---|
| QW-EMAIL | `local_sentientia_emails` 2026101501 → **2026111900** (1.4.0 → 1.4.1) | PASS on every read-only step; 6 save-only steps BLOCKED (see below) | [QW-EMAIL/](QW-EMAIL/) (12) |
| QW-CERT-VERIFY | `theme_sentientia` 2026111802 → **2026111900** (1.0.70-beta → 1.0.71-beta), shared | PASS; UAT step BLOCKED | [QW-CERT-VERIFY/](QW-CERT-VERIFY/) (10) |
| QW-ROI | `local_sentientia_analytics` 2026111801 → **2026111900** (1.2.6-beta → 1.2.7-beta) | PASS; settings-save and UAT steps BLOCKED | [QW-ROI/](QW-ROI/) (12) |
| QW-SKILLS-UI | `local_sentientia_skills` 2026111800 → **2026111900** (1.7.1 → 1.7.2) | PASS; interest chips BLOCKED (no local data) | [QW-SKILLS-UI/](QW-SKILLS-UI/) (8) |
| QW-MODAL-FORMS | `theme_sentientia` 2026111900 (shared with QW-CERT-VERIFY) | 2 defects found in the browser, **fixed and re-checked**; 1 step's expectation was wrong (pre-existing behaviour) | [QW-MODAL-FORMS/](QW-MODAL-FORMS/) (28) |

Desktop shots are 1440 px (plus 1280, 1024 and 800 where the step asks for them); mobile shots are 390 px (590 px for
the certificate page). A learner regression look (`/my/` and a course page as `vp_learner1`, 1440 and 390) found no
page error, console error or horizontal overflow.

---

## How it was run

- **Where:** local XAMPP only. The Playwright helper (`moodle-enhancement/tools/visual-pass/evidence_lib.mjs`) aborts
  every request that is not to localhost. No request went to UAT or to airpay.academy.
- **Personas** (test accounts from `.personas.local.json`, passwords never printed): `vp_siteadmin` (site admin),
  `vp_admin1` (tenant admin, `/1`), `vp_compliance1` (manager with analytics view), `vp_learner1` (learner), and a
  logged-out visitor.
- **Read-only:** every dialogue was closed with Cancel or Escape; no form was submitted, no override row, setting or
  flag was written. Values that need a save were checked by reading the code path instead (recorded per step).
- **Lanes:** lane A (QW-EMAIL, QW-CERT-VERIFY, QW-ROI), lane B (QW-SKILLS-UI, QW-MODAL-FORMS, 12:45-13:10 IST), then a
  repair pass for QW-MODAL-FORMS with a re-check. One browser context at a time; the box is slow (pages 15-140 s).
- **Raw results** (not in the repo): `D:/Claude Local/kit-scratch/1119/qw/verify/A/results-*.json`,
  `.../verify/B/results-{A,B,C,D}.json`, `.../qw/fix/results-fix.json`, plus their run logs and raw shots.

## Masking rule

No real person's name, e-mail address, user id or certificate code appears in these shots or in this file.

- Blurred before the shot was saved (Pillow): the sidebar user block and avatar, user-name and profile links, e-mail
  addresses, certificate holder tables, the table rows behind each dialogue, and the valid certificate code.
- The valid certificate code came from a real local issue. It is not written here, in the results JSON or in the shots.
- Left visible on purpose: test-persona data (`user-edit-page-1440.png` shows `VP Site admin` and
  `vp_siteadmin@visualpass.localhost`, a text input that cannot be blurred by selector), the email plugin's built-in
  sample learner and sample course, the made-up not-found code `6342739542AU`, org-unit and tenant names, course and
  skill names, and the shared academy mailbox in the email footer.
- Kept out of the repo: the learner `/my/` shots (the leaderboard shows names) and one blank element shot of the
  Skill Gap card (the theme scrolls in an inner container, so it came out empty; the full-page shot covers the card).

---

## QW-EMAIL: template previews, unknown keys, override partials

**What changed** (both plugin trees, byte-identical): template keys are accepted only from the registry of 20
templates (`email_renderer::known_template_key`) in `preview.php`, `editor.php`, `preview_ajax.php` and the template
web services. `PARAM_ALPHANUMEXT` had removed the `/` every key contains, so every preview failed, and a crafted key
could read any template file. The preview shows the tenant's effective override and says which version it shows.
BizLMS legacy templates open read-only. Saved overrides render this plugin's own partials again (new
`classes/partials_loader.php`), so an edited template keeps its course card and support footer. 5 new strings in
en + hi. Test: `tests/template_registry_test.php`.

| Step | Result | Evidence |
|---|---|---|
| 1. Deploy, upgrade to 2026111900, purge | PASS. The 11 touched files on XAMPP equal the repo copy. | `kit-scratch/1119/qw/upgrade.log` |
| 2. Preview `compliance/welcome_enrolled`, tenant 1; open all 20 sidebar entries | PASS. 200 in 17 s, h2 "Enrolled in Mandatory Course", sample greeting, AML course card, Start Course, Keka Helpdesk footer. All 20 entries return 200 with no "Template error". **Deviation (local data):** the subtitle reads "Showing this tenant's edited version" because a tenant-1 override row (edited 2026-10-14) exists; the other 19 templates show "Showing the standard template". | [preview-welcome-enrolled-1440](QW-EMAIL/preview-welcome-enrolled-1440.png), [-390](QW-EMAIL/preview-welcome-enrolled-390.png) |
| 3. `?tpl=` alias, unknown keys, Hindi | PASS. `?tpl=compliance_welcome_enrolled` renders the same frame. `manage/editor` and `../../config` show "This e-mail template does not exist. Choose a template from the list." With `?lang=hi` the page text is Hindi; the email body stays English (known gap 2). | [preview-unknown-template-1440](QW-EMAIL/preview-unknown-template-1440.png), [-390](QW-EMAIL/preview-unknown-template-390.png), [preview-welcome-enrolled-hi-1440](QW-EMAIL/preview-welcome-enrolled-hi-1440.png) |
| 4. Design Studio, ZEEA, save, Full Preview, revert | Read-only part PASS: Templates tab links to Design Studio; `editor.php?...&tenant=177` opens on 177 with a working preview; on the edited template the first preview matches the textarea and shows the course card **and** the support footer. **Save and revert BLOCKED** (write). | [manage-templates-tab-1440](QW-EMAIL/manage-templates-tab-1440.png), [design-studio-edited-welcome-1440](QW-EMAIL/design-studio-edited-welcome-1440.png), [-390](QW-EMAIL/design-studio-edited-welcome-390.png) |
| 5. `editor.php?template=manage/editor` | PASS. Redirects to `manage.php?tab=templates`; override table unchanged (1 row before and after). | results-siteadmin.json "E5" |
| 6. BizLMS legacy View | PASS. 15 legacy View links; `bizlms/course_complete_1` opens with "BizLMS legacy template, read-only", placeholders unfilled, no file error. | [preview-bizlms-legacy-1440](QW-EMAIL/preview-bizlms-legacy-1440.png), [-390](QW-EMAIL/preview-bizlms-legacy-390.png) |
| 7a. Tenant admin preview scoping | PASS. As `vp_admin1`, `tenant=177`, `77` and `1` all show the Airpay version; the editor resolves `tenant=177` to 1. | [tenantadmin-preview-tenant177-param-1440](QW-EMAIL/tenantadmin-preview-tenant177-param-1440.png), [-390](QW-EMAIL/tenantadmin-preview-tenant177-param-390.png) |
| 7b. Tenant admin saves and reverts own override | **BLOCKED** (write). Code path: `editor.php` calls `tenant_scope::require_can_write_tenant()` before save and revert. | code reading |
| 8. Local only | PASS. | evidence_lib routing |
| 9. Broken override falls back to the file | **BLOCKED** (needs a saved broken override). Code path: `render_preview()` catches the Mustache error, renders the file and labels it "standard". | code reading |
| 10. Override labels (tenant and global) | Tenant label seen live (step 2). Global label not seen: there is no global-only override locally. Save part **BLOCKED**. Both labels exist in en and hi. | code reading |
| 11. Legacy `&` escaping | **BLOCKED**: no local legacy row has `&` in its name or subject, and creating one is a write. `course_complete_1` shows no `&amp;` in its h2. | CLI probe |
| 12. `php -l` and tree parity | PASS. | gates below |

## QW-CERT-VERIFY: certificate verification page

**What changed** (theme only; the vendored `tool_certificate` is not edited by this quick win): on
`admin/tool/certificate/index.php` only, the "(!) Required" legend under the Verify button is hidden
(`_legacy-admin.scss`, scoped to `#page-admin-tool-certificate-index`), and a code that matches no certificate gets an
explanation under "Not verified" (theme override `templates/tool_certificate/verify_certificate_results.mustache`, new
string `certverify_notfound` in en + hi). Test: `tests/certificate_verify_template_test.php`.

| Step | Result | Evidence |
|---|---|---|
| 1. Deploy | PASS. The template override on XAMPP equals the repo copy. | upgrade.log |
| 2. Not found, logged out and as `vp_learner1` | PASS. No "(!) Required" line under Verify; the red marker beside "Code" stays; a red `role=alert` reads "Not verified" plus the explanation. No overflow at 1440 or 390. | [verify-guest-notfound-1440](QW-CERT-VERIFY/verify-guest-notfound-1440.png), [-390](QW-CERT-VERIFY/verify-guest-notfound-390.png), [verify-learner-notfound-1440](QW-CERT-VERIFY/verify-learner-notfound-1440.png), [-390](QW-CERT-VERIFY/verify-learner-notfound-390.png) |
| 3. Valid code | PASS. **Deviation:** `vp_learner1` holds no certificate (gallery empty), so a valid local issue code was entered directly. Green "This certificate is valid", details table, "View certificate". Code and holder table blurred. Side effect: one `certificate_verified` log row locally. | [verify-valid-1440](QW-CERT-VERIFY/verify-valid-1440.png), [-390](QW-CERT-VERIFY/verify-valid-390.png) |
| 4. Hindi | PASS. The explanation is Hindi. "Not verified" stays English: it is tool_certificate's own string, with no hi translation in the vendored plugin. | [verify-notfound-hi-1440](QW-CERT-VERIFY/verify-notfound-hi-1440.png) |
| 5. 590 px, dark mode, other forms keep the legend | PASS. Alert legible in light and dark at 590 px. `/user/edit.php` still shows its "(!) Required" legend. | [verify-notfound-590](QW-CERT-VERIFY/verify-notfound-590.png), [verify-notfound-dark-590](QW-CERT-VERIFY/verify-notfound-dark-590.png), [other-form-required-legend-1440](QW-CERT-VERIFY/other-form-required-legend-1440.png) |
| 6. `?code=` on arrival (known, unchanged) | As expected: the code is filled in, no result until Verify is pressed, two inputs named `code`. The D32 core-mod that changes this is a separate change and is not deployed to XAMPP (see "Not in this commit"). | results-cert_learner.json "C6" |
| 7. UAT | **BLOCKED**: UAT is out of bounds; Nitin's call after the demo. | |

## QW-ROI: Training ROI card and Skill Gap card

**What changed:** the ROI card prints its figures (the page and the template used different field names, so the card
was empty), with each benefit and cost line and its formula, all 7 assumptions (marked "(default)" until changed),
honest empty states and every label in en + hi. New settings page `settings.php` for the 7 `roi_*` assumptions
(defaults equal the old constants). Skill Gap: required counts include every category per team, "All business units"
covers every tenant, one "Uncovered skills" row per team (was N rows of N tags). Card CSS added. Cache keys moved to
`roi_v2_` / `skillgap_v2_`. Both cards stay behind `sentientia.analytics.roi.enabled` and
`sentientia.analytics.predictive.enabled` (default OFF; ON locally for tenants 1, 77 and 177, read, not flipped).
Tests: `tests/roi_calculator_test.php` (rewritten), `tests/predictive_engine_test.php` (3 tests added).

| Step | Result | Evidence |
|---|---|---|
| 1-3. Deploy, upgrade, purge | PASS. The 7 `roi_*` settings hold their defaults: 2, 500, 1000, 40, 50000, 50, 3. | CLI `get_config` |
| 4. Flags | PASS (read only). | CLI `feature_flags` |
| 5. Hero, YTD, all business units | PASS. "ROI -88%" in red with a down arrow. | [roi-card-ytd-1440](QW-ROI/roi-card-ytd-1440.png), [-390](QW-ROI/roi-card-ytd-390.png) |
| 6. Sentence and net benefit | PASS. "...costs exceeded estimated benefits by ₹7,404,950 (ROI -88%)" and "Net Benefit: -₹7,404,950" (label capitalised). | same |
| 7. Panels | PASS. Benefits ₹1,019,000 (2 lines with formulas); Costs ₹8,423,950 (3 lines). | same |
| 8. Defaults note and assumptions | PASS. Note with a "Set ROI assumptions" link; 7 assumption rows, all default (the collapsed `<details>` text confirmed from the CLI). | results-siteadmin.json |
| 9. 30-day empty state | PASS. "No completions in this period" with a settings button. | [roi-card-30d-empty-1440](QW-ROI/roi-card-30d-empty-1440.png), [-390](QW-ROI/roi-card-30d-empty-390.png) |
| 10. Settings page and validation | PASS. "Training ROI assumptions" heading and 7 fields. Form not submitted; each setting's `validate()` run from the CLI: `2.50` accepted, `1,000` and `-5` refused. | [roi-settings-1440](QW-ROI/roi-settings-1440.png), [-390](QW-ROI/roi-settings-390.png) |
| 11. Save hourly rate 650 | **BLOCKED** (settings save). Code path: the cache key hashes the settings, so a change shows at once; `is_default` is per assumption. | code reading |
| 12. Hindi | PASS. Only "ROI" stays Latin. | [roi-card-ytd-hi-1440](QW-ROI/roi-card-ytd-hi-1440.png) |
| 13. 390 px and dark mode | PASS. Panels stack, no overflow; dark card legible. | [roi-card-ytd-390](QW-ROI/roi-card-ytd-390.png), [roi-card-ytd-dark-1440](QW-ROI/roi-card-ytd-dark-1440.png), [-390](QW-ROI/roi-card-ytd-dark-390.png) |
| 14. `vp_compliance1` | PASS. Same card, no settings link; -76% because this viewer is scoped to tenant `/1`. | [roi-card-compliance-1440](QW-ROI/roi-card-compliance-1440.png) |
| 15. Skill Gap card | PASS. 52 team rows across all tenants, one "Uncovered Skills" row per team with a gap (26), coloured bars, required counts up to 6. A before/after count comparison was not made. | [analytics-ytd-1440](QW-ROI/analytics-ytd-1440.png), [-390](QW-ROI/analytics-ytd-390.png) |
| 16. UAT empty-card diagnosis | **BLOCKED**: UAT is out of bounds. | |

## QW-SKILLS-UI: Skills Management spacing

**What changed** (both plugin trees, byte-identical): the theme ships Bootstrap 4.6, which has no `gap-*` classes, so
the templates' gap classes rendered as 0 px. `manage.mustache` drops its inline `<style>` for BEM classes; spacing now
comes from `styles.css` on the `--ap-space-*` / `--ap-radius-*` tokens, scoped to `.ap-skills-admin` and new
`ap-skills-*` classes (dashboard chips, self-rate panel, designation matrix). Category grid `col-sm-6 col-xl-4`.
`admin.php` declares the datatable's Actions column (the header had 5 cells over 6). Test: `tests/ui_spacing_test.php`.

| Step | Result | Evidence |
|---|---|---|
| 1. Deploy | PASS. The 7 changed files on XAMPP equal the repo copy; computed gap 8 px. | results-A.json |
| 2. Header buttons | PASS. One row beside the title at 1440 px with 8 px gaps; at 1280/1024/800 the whole group moves under the title together. | [admin-1440](QW-SKILLS-UI/admin-1440.png), [admin-1024](QW-SKILLS-UI/admin-1024.png) |
| 3. Category cards | PASS. 3 per row at ≥1200 px, 2 at 1024/800, 1 at 390; 16 px padding, 12 px icon-to-name, 4 px name-to-count, equal heights. Long-name wrapping not testable (longest local name is 18 characters). | same |
| 4. Filter bar and table | PASS. Styled select; 6 header cells over 6 row cells; icons on one line. | [admin-1440](QW-SKILLS-UI/admin-1440.png) |
| 5. 390 px | PASS. 2x2 button grid with 8 px gaps, stacked tiles and cards, full-width select, table scrolls inside its card, no page scroll. | [admin-390](QW-SKILLS-UI/admin-390.png) |
| 6a. Designation matrix | PASS. 8 px between buttons; 8 px under the select at 390 px. | [designation-matrix-1440](QW-SKILLS-UI/designation-matrix-1440.png), [-390](QW-SKILLS-UI/designation-matrix-390.png) |
| 6b. Self-rate panel (`vp_learner1`) | PASS. 12 px gap, wraps at 390 px. | [skill-view-selfrate-1440](QW-SKILLS-UI/skill-view-selfrate-1440.png), [-390](QW-SKILLS-UI/skill-view-selfrate-390.png) |
| 6c. Interest chips | **BLOCKED**: the interest table has no rows for any user, and adding rows is a write. A client-side probe element computes to `display:flex; gap:8px`, so the rule is served. | results-C.json |
| 7. Dark mode | PASS. Cards and filter bar on the dark surface and border tokens. | [admin-dark-1440](QW-SKILLS-UI/admin-dark-1440.png) |

## QW-MODAL-FORMS: label layout in modal forms

**What changed** (theme only): new partial `scss/moodle/partials/_modal-forms.scss`, imported in `custom_changes.scss`
after `forms.scss`. Every selector is under `.modal:not(.openLMStransition)`, so page forms and the legacy BizLMS
modals are unchanged. Rule 1: no 2.5rem first-line indent on labels in collapsible sections. Rule 2 (large dialogues
`.modal-lg` / `.modal-xl` only, md and up): the label column is a flex row, the required/help icons sit beside the label
level with its first line. Rule 3: in large dialogues 30 / 70 columns from 992 px, labels above fields from 768 to
991 px (icons right after the label text). `.full-width-labels` forms are excluded. Test:
`tests/modal_forms_scss_test.php` (4 tests).

| Step | Result | Evidence |
|---|---|---|
| 1. Deploy | PASS. Partial, `custom_changes.scss` and `version.php` on XAMPP equal the repo copy. | |
| 2. Edit course (`vp_admin1`, 1440) | PASS. Labels on one line, flush, icons within 1 px of the first line, 30 / 70. | [course-edit-modal-1440](QW-MODAL-FORMS/course-edit-modal-1440.png), after repair [course-edit-modal-after-fix-1440](QW-MODAL-FORMS/course-edit-modal-after-fix-1440.png) |
| 3. Add classroom | PASS. "Basic Information" fully visible right after opening (sampled 0-3 s at 1440 and 1280, `scrollTop` 0): the half-hidden heading did not reproduce locally. | [classroom-new-modal-on-open-1440](QW-MODAL-FORMS/classroom-new-modal-on-open-1440.png), [-on-open-1280](QW-MODAL-FORMS/classroom-new-modal-on-open-1280.png), [classroom-new-modal-1440](QW-MODAL-FORMS/classroom-new-modal-1440.png), [after-fix-1440](QW-MODAL-FORMS/classroom-new-modal-after-fix-1440.png) |
| 4. Edit program | PASS. Same layout. | [program-edit-modal-1440](QW-MODAL-FORMS/program-edit-modal-1440.png), [after-fix-1440](QW-MODAL-FORMS/program-edit-modal-after-fix-1440.png) |
| 5. 800 px and 390 px | Labels stack at 800 and 390 as expected. **Defect at 800 px** (icons pushed 257-315 px to the dialogue's right edge): **fixed**, now 4-5 px after the label text. | before [course-edit-modal-800](QW-MODAL-FORMS/course-edit-modal-800.png), [classroom-new-modal-800](QW-MODAL-FORMS/classroom-new-modal-800.png), [program-edit-modal-800](QW-MODAL-FORMS/program-edit-modal-800.png); after [course-edit-modal-after-fix-800](QW-MODAL-FORMS/course-edit-modal-after-fix-800.png), [classroom-new-modal-after-fix-800](QW-MODAL-FORMS/classroom-new-modal-after-fix-800.png), [program-edit-modal-after-fix-800](QW-MODAL-FORMS/program-edit-modal-after-fix-800.png); 390: [course-edit-modal-390](QW-MODAL-FORMS/course-edit-modal-390.png), [classroom-new-modal-390](QW-MODAL-FORMS/classroom-new-modal-390.png), [program-edit-modal-390](QW-MODAL-FORMS/program-edit-modal-390.png) |
| 6a. Page forms unchanged | PASS. `/course/edit.php` and `/user/edit.php` labels keep their 2.5rem indent. | [course-edit-page-1440](QW-MODAL-FORMS/course-edit-page-1440.png), [user-edit-page-1440](QW-MODAL-FORMS/user-edit-page-1440.png) |
| 6b. Default-size (`large:false`) modal | **FAIL, then fixed.** Roles > Edit capability (500 px dialogue): rule 2, then unscoped, broke "Permission" mid-word ("Permiss / ion") and wrapped "Reason for change" to 3 lines. Rule 2 is now limited to large dialogues: "Permission" on one line (icon on the next), "Reason for change" on 2. Skills > New Skill turned out to be `modal-lg` (core ModalForm default), so it is kept as an extra large-dialogue shot. | before [roles-edit-cap-modal-default-size-1440](QW-MODAL-FORMS/roles-edit-cap-modal-default-size-1440.png), [-390](QW-MODAL-FORMS/roles-edit-cap-modal-default-size-390.png); after [-after-fix-1440](QW-MODAL-FORMS/roles-edit-cap-modal-default-size-after-fix-1440.png), [-after-fix-800](QW-MODAL-FORMS/roles-edit-cap-modal-default-size-after-fix-800.png); [skills-new-skill-modal-1440](QW-MODAL-FORMS/skills-new-skill-modal-1440.png) |
| 7. Compiled order | PASS. The modal rules come after the `forms.scss` indent rule in the served CSS. | results-A.json |
| 8. Rule 2 compiled | PASS. After the repair the page's stylesheets hold 4 large-dialogue rules and 0 of the old unscoped selector. | results-fix.json |
| 9. 1280 px | PASS. Same as 1440. | [course-edit-modal-1280](QW-MODAL-FORMS/course-edit-modal-1280.png), [classroom-new-modal-1280](QW-MODAL-FORMS/classroom-new-modal-1280.png), [program-edit-modal-1280](QW-MODAL-FORMS/program-edit-modal-1280.png) |
| 10. `full-width-labels` added in DevTools | **FAIL as written, not caused by this change.** Labels sit above full-width fields with no indent, but the icons float to the far right: the theme's element template prints `float-sm-right` (unchanged since HEAD), and the partial excludes `.full-width-labels` by design. The step's expectation was wrong; the partial's comment that claimed otherwise was corrected. The `femptylabel` half cannot be tested: ModalForm puts Save/Cancel in the dialogue footer. | [course-edit-modal-full-width-labels-devtools-1440](QW-MODAL-FORMS/course-edit-modal-full-width-labels-devtools-1440.png) |

---

## BLOCKED steps

| Step | Why | What stood in for it |
|---|---|---|
| QW-EMAIL-4 (save and revert part) | Needs a Design Studio save | Read-only parts run; first preview shows course card + footer |
| QW-EMAIL-7b | Needs a save and a revert | Code reading of the tenant write guard |
| QW-EMAIL-9 | Needs a saved broken override | Code reading of the fallback path |
| QW-EMAIL-10 (save part, global label) | Needs a save; no global override exists locally | Tenant label seen live |
| QW-EMAIL-11 | No legacy row with `&` locally; creating one is a write | Code reading; one legacy page checked |
| QW-CERT-VERIFY-7 | UAT out of bounds | none |
| QW-ROI-11 | Needs a settings save | Code reading of the cache key and `is_default` |
| QW-ROI-16 | UAT out of bounds | none |
| QW-SKILLS-UI-6c | No interest rows locally; adding rows is a write | Client-side probe: rule served |
| QW-MODAL-FORMS-10 (`femptylabel` half) | No such rows in these ModalForms | none |

## Repaired, reverted, removed

- **Repaired (QW-MODAL-FORMS, `_modal-forms.scss` + its test):** rule 2 scoped to `.modal-lg` / `.modal-xl`; rule 3a
  sets `margin-left: 0` on the icons between 768 and 991 px; header comment corrected. SCSS only, so no further
  version bump (theme stays 2026111900 / 1.0.71-beta); the repo and XAMPP copies are identical and caches were purged.
  Large dialogues at 1440 px measure the same as before the repair.
- **Reverted:** nothing. No code change was rolled back, and the XAMPP rollback recipe below was not used.
- **Removed from the evidence:** one blank element shot of the Skill Gap card (kept in kit-scratch as unusable).

## Gates (run on the working tree before the repair; re-checked after it where it applies)

`php -l` on 47 PHP files with PHP 8.2.12 and 8.4: 0 errors (re-run after the repair on the theme test and version
files). `tools/check-tree-drift.php`: 0 new, 0 stale. `tools/check-lang-parity.php`: 0 failures (en = hi: emails 107,
analytics 112, skills 109). `tools/check-upgrade-step-versions.php`, `check-path-boundary.php`,
`check-theme-layout-regions.php`: 0 findings. stylelint on the 3 touched SCSS files: exit 0 (re-run on the partial after
the repair); no transition, hex or teal literals. Plugin pairs: 25 byte-identical; 6 analytics files exist only in
`moodle-enhancement/` and are grandfathered in the drift baseline. Pre-commit hook on a throwaway index: 19 of 20 checks
passed; the skills state-card warning was fixed. The theme state card was brought to 2026111900 as well.

**Not run:** PHPUnit (6 new or changed test files), Behat.

## Local deploy and rollback

40 files copied to `C:/xampp/htdocs/moodle5/public` (31 from `moodle-enhancement/local/`, 9 from `theme/sentientia/`),
each `cmp`-identical; list in `kit-scratch/1119/qw/copy-manifest.txt`. `upgrade.php` (12:15-12:22) took the 4
components to 2026111900 with no upgrade step and no schema change, and wrote the 7 `roi_*` defaults. The scssphp
"Array to string conversion" warning also appears in the 03:55 deploy log, so it is older than these changes. Caches
purged. Pre-deploy backups: `kit-scratch/1119/qw/backup/*.tgz`.

Rollback (Git Bash, deletes nothing; keeps the new `version.php` files because Moodle refuses a downgrade):

```
P=C:/xampp/htdocs/moodle5/public; Q="D:/Claude Local/kit-scratch/1119/qw"; B="$Q/backup"
mkdir -p "$Q/rollback/keep" "$Q/rollback/moved"
for c in local/sentientia_emails local/sentientia_skills local/sentientia_analytics theme/sentientia; do cp -p "$P/$c/version.php" "$Q/rollback/keep/$(echo $c | tr / -)-version.php"; done
for n in sentientia_emails sentientia_skills sentientia_analytics; do tar --force-local -xzf "$B/local-$n-pre.tgz" -C "$P/local"; done
tar --force-local -xzf "$B/theme-sentientia-pre.tgz" -C "$P/theme"
for c in local/sentientia_emails local/sentientia_skills local/sentientia_analytics theme/sentientia; do cp -p "$Q/rollback/keep/$(echo $c | tr / -)-version.php" "$P/$c/version.php"; done
for f in local/sentientia_analytics/settings.php local/sentientia_emails/classes/partials_loader.php local/sentientia_emails/tests/template_registry_test.php local/sentientia_skills/tests/ui_spacing_test.php theme/sentientia/scss/moodle/partials/_modal-forms.scss theme/sentientia/templates/tool_certificate/verify_certificate_results.mustache theme/sentientia/tests/certificate_verify_template_test.php theme/sentientia/tests/modal_forms_scss_test.php; do mkdir -p "$Q/rollback/moved/$(dirname $f)"; mv "$P/$f" "$Q/rollback/moved/$f"; done
cd $P && timeout 900 php ../admin/cli/purge_caches.php
```

Local data side effects of the whole exercise: the 7 `roi_*` config rows, two `certificate_verified` log rows (one from
the build-stage CLI render, one from step QW-CERT-VERIFY-3), and the upgrade log rows. No override, setting or flag row
was written by the browser checks.

## Not in this commit

- **D32 vendor patch** (tool_certificate "verify on arrival"): `admin/tool/certificate/index.php`, its two new test
  files, `docs/adr/ADR-041-patching-vendored-plugins.md`, `docs/core-mods/2026-11-19-tool-certificate-verify-on-arrival.md`
  and the two README index lines. That is a separate change made by the D32 step; it was not deployed to XAMPP or
  checked in these lanes (step QW-CERT-VERIFY-6 still shows the old behaviour). It needs its own gates and commit.
- Older untracked evidence (`2026-09-30/`, `2026-10-01/`), `2026-11-19/phase-c/vp_public77/PB-5-my-hi-toggle-1440.png`,
  the c-suite deck v1 working files, and the two mobile-track plan documents written in parallel
  (`docs/plans/2026-11-19-APP-STORE-PAPERWORK-PACK.md`, `2026-11-19-MOBILE-APP-BENCHMARK-AND-ARCHITECTURE.md`).
  The post-demo plan `docs/plans/2026-11-19-POST-DEMO-PLAN.md`, which defines these quick wins, is in the commit.

## Open follow-ups

1. **UAT** (Nitin's call): deploy the 40 files with an upgrade, then QW-CERT-VERIFY-7 (valid and not-found codes,
   logged-out access) and the QW-ROI-16 empty-card diagnosis.
2. **PHPUnit:** run the 6 new or changed test files (emails, skills, analytics x2, theme x2).
3. **Save-dependent checks** in a write-allowed local pass: QW-EMAIL-4/7b/9/10/11 and QW-ROI-11, then revert.
4. **ROI defaults:** the default model charges every visible course's development cost to every period, so YTD reads
   -88% locally. Nitin to review the defaults (amortise development cost, prorate platform cost, read compliance
   assignments) before anyone demos this card.
5. **Emails, known gaps:** saved subjects are never delivered; all 20 bodies are English only; Placeholder buttons insert
   nothing; the editor textarea prints the body raw; the subject is double-escaped (the placeholder list shows
   `&amp;` in a sample department); Global-scope Preview shows tenant 1. Pre-existing: the logo in the email header is
   blue on the blue band, so it is invisible (`core/email_html.mustache`).
6. **Skills:** `manage.mustache` and `admin.php` hardcode English labels (needs en + hi strings); the designation matrix
   select is unstyled and its two buttons differ in height; other plugins likely use `gap-*`, `form-select` and
   `text-start/end` on Bootstrap 4.6 (sweep or a theme-level shim, Nitin's call).
7. **Modal forms:** default-size dialogues keep a narrow 25% label column; port core's autocomplete-in-scrollable-modal
   rule; align the theme's `core_form/element-template` with core 5.x (it never calls `form-display-errors` enhance()
   and prints `float-sm-right` icons).
8. **Pre-existing, seen in passing:** Escape on the Edit course dialogue throws a JS error (Cancel does not); the
   certificate page has no visible heading and no site header when logged out, its form panel stays light in dark mode,
   and the success alert shows a nested left bar; the datatable pagination bar stays light in dark mode; the Update
   profile and Cancel buttons on `/user/edit.php` are misaligned.
9. **PROJECT-STATE.md** still needs the quick-win entry.
10. A timed-out smoke run may have left headless Chrome processes; they were not killed because they cannot be told
    apart from the user's own Chrome.
