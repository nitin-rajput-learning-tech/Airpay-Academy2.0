# Sentientia LMS: UI/UX review pack

**Content, claims, Airpay brand consistency and functionality of the Airpay Academy user interface**

| Field | Value |
|---|---|
| Prepared for | Mansi Solanki, UI/UX Solution Architect |
| Document owner | Head of Learning & Development, Airpay Payment Services |
| Version | 1.0 |
| Date | 20 November 2026 |
| Status | Draft for review |
| Product state | Sentientia LMS is not live. It runs on the UAT environment (academy2.airpay.ninja), which holds a fresh installation with synthetic test personas and sample courses, and on development machines, which hold a copy of the production database imported for testing only (about 2,871 users). Its three tenants are Airpay (internal employees), Public (consumer storefront) and ZEEA Mafunzo (Tanzania). www.airpay.academy still runs the legacy BizLMS platform. First real user load is the production cutover, after the live-backup rehearsal (Stage B). |

Confidential: Airpay Payment Services internal. Not for external distribution.

---

## Document control

### Version history

| Version | Date | Author (role) | Change |
|---|---|---|---|
| 1.0 | 20 Nov 2026 | Technical writer for the Head of Learning & Development | First issue for UI/UX review |

### Reviewers and sign-off

| Role | Signs off on | Signature and date |
|---|---|---|
| UI/UX Solution Architect | Sections 2 to 7; the per-surface sign-off table in section 7 | |
| Head of Learning & Development (document owner) | Claims decisions (section 4); flag set-up for the review (section 9) | |
| Marketing lead | Customer-facing claims marked "Remove" or "Needs evidence" (section 4) | |
| QA Architect | Accessibility test plan (section 6) | |

### Related documents

| Document | Path (from the repository root) |
|---|---|
| Canonical facts | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/00-FACTS-INDEX.md` |
| UI surfaces fact file | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/70-ui-surfaces.md` |
| Brand Book adoption spec (2026-06) | `moodle-enhancement/docs/audits/brand-revamp-2026-06/REVISED-BRAND-BOOK-2026-06.md` |
| Design tokens | `theme/sentientia/scss/moodle/_tokens.scss`, `_tokens-dark.scss` |
| Design rationale cited by the token file (UI/UX manifesto, May 2026) | `docs/platform-review-2026-05-14/UI-UX-MANIFESTO.md` |
| Surface walk, 18 Nov 2026 | `moodle-enhancement/docs/business/csuite-2026-11-18/SURFACE-WALK-2026-11-18.md` |
| Walk-fix evidence | `moodle-enhancement/docs/visual-evidence/2026-11-18/walk-fixes/README.md`, `2026-11-19/phase-c/README.md` |
| Post-demo evidence | `moodle-enhancement/docs/visual-evidence/2026-11-19/quick-wins/README.md`, `2026-11-19/session-01/README.md` |
| Accessibility audit (May 2026) | `moodle-enhancement/PHASE-H-A11Y-AUDIT.md`, `moodle-enhancement/PHASE-H-A11Y-AXE-RESULTS.md` |
| User guides by persona | `moodle-enhancement/docs/user-guides/` |
| Sibling packs | Marketing study (`06-marketing-study/`), QA review (`07-qa-review/`), security architecture (`02-security-architecture/`) |

---

## Executive summary

**What this is.** A review pack for the UI/UX Solution Architect. It sets out what to review, how, and against which standards (Nielsen's ten usability heuristics, WCAG 2.2 level AA, the Airpay Brand Book 2026-06 and plain-language content style). It gives an inventory of every user-facing surface by persona, a register of every customer-facing claim, the known UI issues, the accessibility status and a sign-off table to fill in.

**Key facts.**

- The interface is the standalone theme `theme_sentientia` (release 1.0.72-beta on UAT; 1.0.73-beta in the repository) plus 47 local plugins, 6 blocks and 3 other plugins. Source: `_facts/00-FACTS-INDEX.md` section 2.1.
- There are 11 logged-out surfaces, 7 app-shell components and 81 signed-in surfaces across 9 personas, including the external trainer (section 3). 179 top-level PHP entry points exist in the local plugins; this pack groups them into reviewable surfaces, and Appendix E lists the entry points folded into a parent surface or left out, with the reason.
- 142 feature flags are registered; 129 default OFF. Several UI features are therefore **Built (behind a feature flag, off by default)**, including signed-in dark mode, the language switcher, the installable web app (PWA), leaderboards, live sessions and the authoring studio. Their state on UAT is to be confirmed by the Head of Learning & Development.
- The design tokens carry the Brand Book's primary blue `#0066A7`, bright blue `#1985DD`, deep blue `#0d5da1`, sky tint `#9cdbf4`, orange `#ed692b`, purple `#6d58a5` and Montserrat. Teal is retired. This pack lists **18 mismatches** between the tokens, the Brand Book adoption spec and what the pages actually use (section 2.4). The largest: the guest landing page carries its own colour set (including a retired teal tint), and 1,098 hard-coded colour values remain in 24 of 34 theme style partials.
- The claims register (section 4) holds **56 customer-visible claims**: 3 proven, 34 needing evidence, 19 to remove or reword. The guest landing page, visible on UAT, states "RBI Compliant", "24/7 Support" and shows testimonials with no source record. The login and landing counters show test data: synthetic personas and sample courses on UAT, imported test data on the development copy.
- 30 known UI issues and open items are listed with status (section 5). The two with the most user impact: course certificates are not issued automatically on completion on UAT (a fix is built on development), and the Public learner sees two different carts.
- Accessibility: an automated axe-core audit in May 2026 fixed 2 critical issues and left 5 serious colour-contrast findings; contrast tokens were raised since, but no re-run is recorded. No WCAG 2.2 AA assessment, screen-reader pass or keyboard-only walk has been done (section 6).

**What we ask of you** (section 10).

1. Review the surfaces in section 3 on UAT and against the screenshots, and fill in the sign-off table in section 7.
2. Rule on the brand questions in section 2.4 and section 8 (background tint, sidebar colour, logo treatment, how the name is written).
3. Confirm or overrule the "Remove" and "Needs evidence" calls in the claims register (section 4).
4. Lead the WCAG 2.2 AA review with the QA Architect (section 6.4).

**Top risks.**

- Unsupported compliance and service claims on public pages ("RBI Compliant", "ISO Certified", "DPDP Act 2023 Compliant", "24/7 Support") would be published at cutover unless removed.
- Database-driven counters and testimonials would present test data and unsourced quotes as real.
- The landing and login copy promises certificates on completion; automatic issue is not yet on UAT (UI-01).
- The brand accent `#1985DD` and orange `#ed692b` fail WCAG AA contrast for normal-size text on white (3.86:1 and 3.15:1).
- English-only legal and static pages, and English strings left in the Hindi interface, weaken the multi-language offer.

---

## 1. Review scope and method

### 1.1 In scope

| Area | What is reviewed | Where |
|---|---|---|
| Content | Page copy, labels, empty states, error messages, help text, legal and static pages, e-mail templates as seen in previews | All surfaces in section 3 |
| Claims | Every statement a visitor or learner can read that asserts a capability, compliance, service level, number or endorsement | Section 4 |
| Brand consistency | Colours, typography, logo use, gradients, naming, tone, against the Airpay Brand Book 2026-06 | Section 2 |
| Functionality | Does each surface do its job for its persona; navigation, states, mobile behaviour, dark mode, languages | Sections 3 and 5 |
| Accessibility | WCAG 2.2 AA, with the focus on what automated tools miss | Section 6 |

### 1.2 Out of scope

- Moodle core pages that the theme only restyles (gradebook, quiz attempt, admin settings), except where they appear in a persona journey.
- The certificate PDF artwork beyond the redesigned UAT template (legacy imported templates are listed as an issue in section 5).
- The native mobile app (**Planned**, ADR-040 Proposed) and the content pipeline output (SCORM packages).
- Security and privacy controls; these are in the security and DPDP packs. Where a UI issue touches them, this pack says so and refers on.

### 1.3 Standards and how they are applied

| Standard | How it is applied in this pack | Reviewer's job |
|---|---|---|
| Nielsen's ten usability heuristics | Section 1.5 maps the known observations to each heuristic | Heuristic evaluation per surface; severity 0 to 4 |
| WCAG 2.2 level AA | Section 6 states what was tested (WCAG 2.0 and 2.1 rule sets only) and lists the 2.2 additions not yet tested | Lead a 2.2 AA assessment on the key journeys |
| Airpay Brand Book 2026-06 | Section 2 compares the tokens and page code with the adoption spec | Confirm against the original 25-page Brand Book file, which is not in the repository |
| Plain-language content style | Section 1.6 lists content observations | Review tone, terminology and reading level |

We do not claim compliance with any of these standards. The pack is "mapped against" them.

### 1.4 Method used to prepare this pack

1. Surfaces enumerated from plugin entry points, theme layouts and templates (`_facts/70-ui-surfaces.md` section 2).
2. Design tokens read from `theme/sentientia/scss/moodle/_tokens.scss` and `_tokens-dark.scss`, and compared line by line with `REVISED-BRAND-BOOK-2026-06.md` section 5. Colour literals counted by script over `theme/sentientia/scss/moodle/partials/` and `theme/sentientia/layout/frontpage.php`.
3. Contrast ratios computed with the WCAG 2.x relative-luminance formula (Appendix D).
4. Claims taken from page code and language strings, and checked against the repository (`_facts/70-ui-surfaces.md` section 8, re-read in `frontpage.php` lines 454 to 458 and `theme_sentientia.php` lines 453 to 470).
5. Known issues taken from the 18 Nov surface walk, the 18 to 19 Nov fix-verification READMEs, and the 19 to 20 Nov follow-up evidence READMEs.
6. No new browser walk was done for this pack. All screenshots cited were taken earlier, mostly on the development environment (local XAMPP, Moodle 5.1.3+) with seeded test personas. UAT shots exist for the login and landing pages (21 Oct), the dashboard, blocks drawer and notification preferences (30 Oct), notification preferences, the dashboard calendar and the Manage Users search (11 Nov), and an issued test certificate (18 Nov); section 3.10 lists them.

### 1.5 Pre-review observations by Nielsen heuristic

These are starting points from the evidence, not a completed heuristic evaluation.

| # | Heuristic | Observations from the evidence | Where to look |
|---|---|---|---|
| H1 | Visibility of system status | Notification bell now shows an unread count (Available (UAT)). Loading states are not captured in any evidence; pages on development took 15 to 140 s. Compliance pages say "Run the snapshot task first" when data is missing | Bell, dashboards, compliance report |
| H2 | Match with the real world | Course art falls back to a graduation-cap icon instead of the shortname on My courses, but catalogue thumbnails still print shortnames. "Enrol" and "Enroll" are mixed on the storefront | Catalogue, storefront |
| H3 | User control and freedom | Dialogs cancel cleanly in the walks. Clicking a course you are not enrolled in now lands on the catalogue detail page instead of silently on the dashboard | Course access, dialogs |
| H4 | Consistency and standards | Two guest landing pages with different claims; the brand name written five ways; two privacy texts; the topbar dark-mode label is English while the sidebar label is translated | Landing, legal pages, shell |
| H5 | Error prevention | On UAT, editing a seeded e-mail rule silently resets its channel, audience, trigger and conditions; a fix is Built (verified on development) but not yet on UAT (section 5, UI-09) | E-mail rules |
| H6 | Recognition rather than recall | My evaluations is reachable only by URL; the trainer classroom page fails with a permission dialog | Evaluations, classroom |
| H7 | Flexibility and efficiency | The command-palette flag defaults ON, but no command-palette interface was found in the theme or plugins (to be confirmed by the platform engineer). Programs link added for L&D admins on development | Shell |
| H8 | Aesthetic and minimalist design | Admin dashboards and analytics were clipped at 1440 px and 390 px before the walk fixes; several tables still clip at 390 px (section 5) | Dashboards, admin tables |
| H9 | Help users recover from errors | Certificate verification "Not verified" now explains itself; the logged-out verify page has no header or heading | Certificate verification |
| H10 | Help and documentation | Static Help Center page is English only and describes the PWA as available although its flag is OFF | `/local/sentientia_pages/index.php?page=help` |

### 1.6 Content style observations

| # | Observation | Source |
|---|---|---|
| CS1 | The login page addresses job seekers ("Upskill. Get certified. Get hired.", "Hiring assessments") while the same page is the sign-in for Airpay employees, ZEEA staff and Public learners | `theme/sentientia/lang/en/theme_sentientia.php` lines 453 to 457 |
| CS2 | Brand and product names vary five ways: "airpay academy" (landing title), "Airpay Academy" (login, strings, `customername`), "Academy" (short name in the static landing manifest), "Sentientia LMS" (PWA plugin manifest, offline page), "Sentientia UX" (theme name in admin). The static manifest also describes the product as an "L&D Operating System" | `layout/frontpage.php` lines 102 and 104; `theme/sentientia/pix/brand/manifest.json`; `theme_sentientia.php` lines 37 and 70; `local/sentientia_pwa/manifest.php` |
| CS3 | Developer wording on learner and author screens: the authoring review page prints a raw status and the word "mock" | 2026-11-19 phase-c README, open items |
| CS4 | Grammar defects: "1 courses", "1 badges"; mixed "Enrol" and "Enroll" | Phase-c README; surface walk |
| CS5 | Comparative and absolute wording without proof: "set airpay academy apart from generic LMS platforms", "tamper-proof", "24/7" | Section 4 |
| CS6 | Static legal pages (privacy, terms, help, contact, DPDP notice) are English only and dated April 2026 | `moodle-enhancement/local/sentientia_pages/pages/*.html` |
| CS7 | Two privacy texts with different content: the static page and the sign-up flow text | Section 4, C41 |

---

## 2. Design system as implemented

### 2.1 Source of truth

| Item | Location | Note |
|---|---|---|
| Token file (light) | `theme/sentientia/scss/moodle/_tokens.scss` (321 lines, CSS custom properties on `:root`) | Header says every colour, spacing, radius, shadow and type value must come from this file |
| Dark overrides | `theme/sentientia/scss/moodle/_tokens-dark.scss` (`[data-theme="dark"]`) and `dark_mode.scss` (`.dark-mode` class) | Two mechanisms are set together by `templates/head.mustache` lines 40 to 52 |
| Brand adoption spec | `moodle-enhancement/docs/audits/brand-revamp-2026-06/REVISED-BRAND-BOOK-2026-06.md` | Records Phase 1 (token foundation and teal retirement) as complete on 16 Jun 2026 and Phase 2 (brand-forward landing and login) as an open design decision. The spec is stale on Phase 2: part of it was built the same week (theme versions 2026061601 and 2026061602; see M18) and the spec needs updating |
| Brand Book source | Text extract `brandbook-text.txt` and page images `pages/p01, p06, p07, p14, p15.png` in the same folder | The original 25-page PDF is not in the repository |
| Per-customer branding | `\local_sentientia_platform\customer::branding()`, read by `theme/sentientia/classes/output/core_renderer.php` | Logo, colours, favicon; theme settings add login slider images, login text, social links |

### 2.2 Tokens as implemented

| Group | Tokens (values) | Status |
|---|---|---|
| Primary | `--ap-color-primary` = `--ap-blue-600` `#0066A7`; hover `#005590`; dark `#004570`; light `#e8f4fd` | Available (UAT) |
| Brand secondaries | `--ap-blue-bright` `#1985DD`, `--ap-blue-sky` `#9cdbf4`, `--ap-blue-deep` `#0d5da1`, `--ap-orange-500` `#ed692b` (light `#fdeee6`, dark `#c9551d`), `--ap-purple-500` `#6d58a5` | Available (UAT) |
| Semantic accent | `--ap-color-accent` = `#1985DD`; hover `#0d5da1`; light `#e8f4fd`. Teal ramp kept only as aliases onto the blue ramp | Available (UAT) |
| Highlight and tertiary | `--ap-color-highlight` orange; `--ap-color-tertiary` purple; both marked "sparing" in the file | Available (UAT) |
| Gradients | Brand `135deg #0066A7 to #0d5da1`; bright `#1985DD to #0066A7`; hero `160deg #003050, #0066A7, #0d5da1`; CTA primary to accent | Available (UAT) |
| Neutrals | 0 `#ffffff`, 25 `#fafbfc`, 50 `#f2f4fb` (page background), 100 `#e8ebf0` (borders), 200 to 950 slate ramp; text `#0f172a`, secondary `#475569`, muted `#607286` | Available (UAT) |
| Status | Success `#15803d`, warning `#b45309`, danger `#dc2626`, info = primary | Available (UAT) |
| Typography | Montserrat with system fallbacks; mono JetBrains Mono; sizes 12 to 36 px (8 steps); weights 400 to 800; line heights 1.25, 1.5, 1.75 | Available (UAT) |
| Spacing and radius | 4 to 80 px spacing (8 px base); radius 4, 8, 12, 16, 20 px, pill, circle | Available (UAT) |
| Layout | Sidebar 260 px (72 px collapsed), top bar 64 px, content max 1400 px, mobile bottom bar 64 px | Available (UAT) |
| Breakpoints | 380, 590, 768, 992, 1280, 1600 px; 590 px is the primary mobile breakpoint | Available (UAT) |
| Controls and focus | Heights 32, 40, 48, 56 px; minimum tap target 44 px; focus ring 3 px, 2 px offset, on `:focus-visible` | Available (UAT) |
| Motion | 0, 150, 250, 400, 700 ms; four easing curves; all collapse to 0 ms under `prefers-reduced-motion: reduce` | Available (UAT) |

### 2.3 Brand Book rules recorded in the adoption spec

- Airpay Blue `#0066A7` is the primary; white is used in a smaller proportion than blue.
- Secondary palette: bright blue `#1985DD`, sky tint `#9cdbf4`, deep blue `#0d5da1`, orange `#ed692b`, grey `#bababa`, purple `#6d58a5`.
- "Tints of blue and white are preferred with subtle gradients"; orange and purple only as occasional accents, never as broad fills.
- Montserrat for websites, web pages, social, print and presentations.
- Logo: lowercase "airpay" wordmark in Airpay Blue; no crop, distortion, transparency change, recolouring, re-typesetting, effects or drop shadows.

### 2.4 Mismatches found

Severity: **High** affects brand perception or accessibility on a primary surface; **Medium** visible inconsistency; **Low** documentation or internal.

| # | Mismatch | Evidence | Severity | Proposed resolution |
|---|---|---|---|---|
| M1 | The guest landing page defines its own colour set in an inline `<style>` block instead of using the tokens. It sets `--ap-accent-light: #e5f4f3`, the retired teal tint, and uses it behind icons, tags and the courses label. Its primary-dark (`#004d80`) and hero start (`#003052`) also differ from the tokens | `theme/sentientia/layout/frontpage.php` lines 120 to 140 (line 125), 246, 479, 587 to 594 | High | Move landing styles into a theme partial that consumes `_tokens.scss`; replace `#e5f4f3` with `--ap-color-accent-light` |
| M2 | Purple is not used sparingly on the landing page: `#6d58a5` appears 10 times and its tint `#e8e2f2` 6 times (a full pillar card with four tags, an icon, an avatar). Off-palette colours also appear: indigo `#1e40af` in two card gradients and amber `#f59e0b` for testimonial stars | `frontpage.php` lines 92 to 95, 303, 489, 597 to 605, 648 | Medium | Restrict purple to a single accent; replace indigo with deep blue; decide star colour (orange is the brand warm accent) |
| M3 | 1,098 six-digit colour literals (plus 232 three-digit) remain in 24 of the 34 theme partials, against the token rule. Most frequent: `#0066a7` (207), `#1a1a2e` (95), `#e5e7eb` (72), `#5a6070` (55). They include colours the contrast fixes replaced in the tokens: `#8896a6` (16, 3.02:1 on white) and `#16a34a` (14, 3.30:1) | `grep` over `theme/sentientia/scss/moodle/partials/`, 20 Nov 2026 | High (contrast), Medium (consistency) | Sweep partials to tokens, starting with the two failing colours. Add a stylelint colour-literal rule (for example `color-no-hex`, scoped to the partials) and extend it progressively; the theme's `.stylelintrc.json` has no colour rule today (its only rule covers motion timing in `_surface-*.scss`) |
| M4 | The app-shell sidebar is a dark slate gradient (`#0f172a` to `#1e293b`), hard-coded in the shell partial rather than the `--ap-gradient-sidebar` token. The Brand Book is blue-dominant | `theme/sentientia/scss/moodle/partials/_layout-shell.scss` line 49 | Medium (design decision) | Decide: keep neutral slate or move to a deep-blue sidebar; in both cases use the token |
| M5 | The brand accent `#1985DD` gives 3.86:1 on white, and white text on orange `#ed692b` gives 3.15:1. Both fail AA for normal-size text (4.5:1). The CTA gradient ends in the accent colour. The adoption spec itself warns about bright blue on white | Appendix D; `REVISED-BRAND-BOOK-2026-06.md` section 8a | High | Use bright blue and orange only for large text (24 px, or 18.66 px bold), icons and non-text UI (3:1); keep primary blue for body links and button labels |
| M6 | The blue ramp stops were not aligned to the book as the spec proposed (`--ap-blue-200` is `#8ec8ef` not `#9cdbf4`; `-400` is `#1a8cd8` not `#1985DD`; `-700` is `#005590` not `#0d5da1`). The book values were added as separate tokens instead. Primary hover is therefore `#005590`, not a book colour | `_tokens.scss` lines 42 to 51, 74 to 76, 100; spec section 5 | Low | Decide whether the ramp should carry the book values at those stops |
| M7 | Page background stays `#f2f4fb`; the spec suggested a blue-tinted `#eef4fb` ("tints of blue and white preferred") and called the current value "slight lavender" | `_tokens.scss` line 84; spec sections 1 and 5 | Low (design decision) | Rule on the background tint |
| M8 | Brand grey `#bababa` has no token. The spec says the neutral ramp covers it; the nearest stop is `#b0b7c3` | `_tokens.scss` lines 82 to 94 | Low | Add a token or confirm the substitute |
| M9 | Typography: the Brand Book specimen pages show Montserrat weights from Thin to Black, including Light, and the text extract of pages 10 and 11 also shows specimens for Roboto and Calibri. The adoption spec records "Montserrat universally". The landing page loads weights 400 to 800 only (no Light); the shell loads the full variable range; the tokens define 400 to 800 | `brandbook-text.txt` around lines 150 to 330; `frontpage.php` line 107; `templates/head.mustache` line 125; `_tokens.scss` lines 222 to 226 | Medium | Confirm from the original Brand Book which families and weights apply to web UI; align font loading |
| M10 | The logo is turned white with a CSS filter (`brightness(0) invert(1)`) on the footer and the login hero. The book forbids recolouring and effects. The Brand Book text extract describes logo colour variants for dark backgrounds ("Creatives with dark coloured backgrounds"; eight social-media adaptives with logo colour variations), and the logo lockup page image `pages/p07.png` is in the repository, but no reversed logo file is in the theme | `partials/_surface-footer.scss` line 92; `partials/_surface-login.scss` line 97; `brandbook-text.txt` about lines 745 to 805; `brand-revamp-2026-06/pages/p07.png` | Medium | Confirm the dark-background variant against the original Brand Book file and use the official reversed logo file instead of the filter |
| M11 | The brand name is written five ways, across two web-app manifests (CS2, M16). The book writes the wordmark and the name in lowercase ("airpay"); interface strings use "Airpay Academy" | `brandbook-text.txt`; `theme_sentientia.php` line 37 | Medium | Set one written form for running text, one for the logo, per customer string |
| M12 | Dark mode uses the brand orange as the warning status colour (`--ap-color-warning: #ed692b`), mixing a brand highlight with a status meaning | `_tokens-dark.scss` | Low | Use a dedicated amber for warning in dark mode |
| M13 | Dark mode does not override `--ap-color-primary`. Primary blue on the dark surface `#1e293b` gives 2.41:1; where primary-coloured text or links sit on dark surfaces without a component override, they fail AA | `_tokens-dark.scss`; Appendix D | High (if present on pages; to be checked) | Check primary-coloured text in dark mode; add a lighter dark-mode primary for text |
| M14 | Contrast ratios written in the token-file comments do not match the WCAG calculation (for example `#475569` noted "about 6.4:1", computes 7.58:1; `#607286` noted "about 3.7:1", computes 4.94:1) | `_tokens.scss` lines 129 to 151; Appendix D | Low | Correct the comments so later decisions rest on accurate figures |
| M15 | The design-rationale document that the token file cites ("UI-UX-MANIFESTO", sections 3, 5, 8, 9) is at `docs/platform-review-2026-05-14/UI-UX-MANIFESTO.md` (repository-root `docs/` folder); the reviewer can use it as the design rationale. The theme editing rules list older Sass values (text `#1a1a2e`, secondary `#5a6070`, success `#16a34a`, warning `#d97706`) and older breakpoints (1400, 1200, 480 px) | `_tokens.scss` comments; manifesto path above; the theme editing rules | Low | Align the theme editing rules with the tokens; check the manifesto against the current tokens |
| M16 | Two web-app manifests exist. The PWA plugin manifest names the app "Sentientia LMS" and uses a placeholder Moodle icon. A second, static manifest (`theme/sentientia/pix/brand/manifest.json`) is linked from the guest landing on every load, whatever the PWA flag: name "Airpay Academy", short name "Academy", description "L&D Operating System ...", and the brand icons. Brand app icons already exist (`pix/brand/favicon_io/android-chrome-192x192.png`, `-512x512.png`) | `moodle-enhancement/local/sentientia_pwa/manifest.php` lines 55 to 56; `theme/sentientia/pix/brand/manifest.json`; `layout/frontpage.php` line 104 | Medium | Reuse the existing brand icons in `pix/brand/favicon_io` and keep one manifest |
| M17 | The legacy public homepage repeats a different trust bar and uses `#16a34a`, `#e3eaf3` and inline styles; it is still reachable by URL | `moodle-enhancement/local/sentientia_pages/homepage.php` (238 lines; 5 colour literals, 7 inline styles) | Medium | Retire the page or redirect it to the active landing |
| M18 | Phase 2 of the brand adoption is partly implemented, Available (UAT): a faint "a" monogram watermark and a sparing brand-orange accent on the login hero (theme 2026061601) and on the landing and storefront hero, with the card-thumbnail palette remapped to brand secondaries (theme 2026061602). The remaining items (diagonal or corner accents, more white space) are a design decision. The adoption spec still lists Phase 2 as open and needs updating | `theme/sentientia/version.php` lines 376 to 380; `layout/frontpage.php` lines 193 to 198 and 420; `partials/_surface-login.scss` lines 46 and 83; spec sections 6 and 7 | Medium (design decision) | Reviewer judges the implemented elements and says what else is needed (section 8, Q2); update the adoption spec |

What matches the book: the primary blue, every secondary hex value as a token, the retirement of teal from the token cascade (verified on the compiled stylesheet in June 2026, spec section 8a), the all-blue hero gradient, Montserrat as the theme font, and the Phase 2 monogram and orange accent described in M18.

---

## 3. Surface inventory

### 3.1 How to read the inventory

- **Status** uses these labels:
  - **Available (UAT)**: deployed and verified on the UAT environment.
  - **Built (verified on development)**: code complete, verified on the development environment, not yet on UAT.
  - **Built (behind a feature flag, off by default)**: shipped dark; turned on per customer or tenant.
  - **In progress**: code started, not complete.
  - **Planned**: in the roadmap; no code yet.
  - **Not planned**: considered and rejected; the reason is stated.

  Flag states on UAT are not in the repository and are **to be confirmed** by the Head of Learning & Development from the UAT Switchboard. Flag keys are given where a sub-feature is flag-gated.
- **States.** "Empty" and "Error" name a state seen in a screenshot. Loading states were not captured in any evidence folder; treat every "Loading" cell as "not evidenced".
- **Mobile.** Evidence width is 390 px unless stated (590 px for some UAT shots).
- **Dark.** Dark mode exists only when `ux.darkMode.enabled` is ON (default OFF; it never follows the operating-system setting). This includes the guest landing: its own toggle (`#ap-dark-toggle`, `layout/frontpage.php` line 410) is hidden, and light mode forced, by the same flag check that runs on every page (`theme/sentientia/classes/output/core_renderer.php`, `standard_head_html()`, lines 207 to 240). Status: Built (behind a feature flag, off by default); state on UAT to be confirmed. "Evidenced" means a dark screenshot exists.
- **Languages.** Plugin strings: English 5,871, Hindi 5,866 (parity enforced), Kannada, Marathi and Swahili 4,736 each (81%). Theme strings: English and Hindi 343, Kannada, Marathi and Swahili 189. The three newer languages show only where the Moodle core language pack is installed; on UAT this is **to be confirmed**. The Sentientia sidebar language switcher is behind `ux.languageSwitcher.enabled` (default OFF). With it OFF, the language comes from the user's profile, a Moodle core language item in the user menu may be available depending on site settings, and `?lang=hi` on a page URL also switches language; whether the core menu item shows on UAT is **to be confirmed** by the platform engineer. "en, hi" in the tables means both are supported; "hi evidenced" means a Hindi screenshot exists.
- **Evidence** links are relative to this file. Evidence folder names such as `vp_learner1` are synthetic labels for seeded test personas on the development environment (Appendix C maps them to persona labels). The development copy holds a test import of a production backup, so other people's names can appear in those shots; they are blurred.

### 3.2 App shell (all signed-in personas)

| ID | Component | Location | Purpose and key components | Status |
|---|---|---|---|---|
| X-01 | Sidebar | `theme/sentientia/templates/sidebar.mustache`, `classes/sidebar_navigation.php` | Persona-specific navigation (Appendix C.2), tenant logo, user block, sign out, dark-mode toggle (flagged), language switcher (flagged) | Available (UAT); Programs link for L&D admins Built (verified on development) |
| X-02 | Top bar | `templates/topbar.mustache` | Page title, notification bell with unread count, Messages link only when messaging is on, dark-mode button | Available (UAT) |
| X-03 | Mobile bottom bar | `templates/mobile_bottom_nav.mustache` | Home, My Learning, Search, Me; translated | Available (UAT) |
| X-04 | Footer | `templates/footer.mustache`, `partials/_surface-footer.scss` | White logo, private and confidential notice (per-customer string), legal links | Available (UAT) |
| X-05 | Assistant button and drawer | `local_sentientia_assistant` (`agent.php`) | Floating button opens a chat drawer; 20 questions per user per day; answers are mock responses until the AI gateway is funded and switched on | Available (UAT) for the drawer (flag `ai.assistant.enabled` default ON); model answers Built (behind a feature flag, off by default) |
| X-06 | Role switcher | `moodle-enhancement/my/switchrole.php` (additive core overlay) | Switch the active persona for multi-role users | Available (UAT) |
| X-07 | Onboarding wizard | `/local/sentientia_pages/onboarding.php` | First-login steps (4 steps evidenced) | Available (UAT) |

| ID | States | Mobile | Dark | Languages | Best evidence |
|---|---|---|---|---|---|
| X-01 | n/a | Drawer; brand text underlined at 390 px (UI-18) | Evidenced (label translated) | en, hi evidenced | [dark, hi label](../../../visual-evidence/2026-11-19/phase-c/vp_public77/PB-5-mycourses-hi-dark-label-1440.png), [L&D admin 1440](../../../visual-evidence/2026-11-19/session-01/G-NAV/ldadmin-my-sidebar-1440.png), [390](../../../visual-evidence/2026-11-19/session-01/G-NAV/ldadmin-my-sidebar-390.png), [hi](../../../visual-evidence/2026-11-19/session-01/G-NAV/ldadmin-my-sidebar-hi-1440.png) |
| X-02 | Unread badge | Evidenced | Evidenced | en; "Toggle dark mode" title is English only | [light](../../../visual-evidence/2026-11-19/phase-c/vp_public77/G2-V20-bell-light-1440.png), [dark](../../../visual-evidence/2026-11-19/phase-c/vp_public77/G2-V20-bell-dark-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_public77/G2-V20-bell-390.png) |
| X-03 | n/a | Phone only | Not evidenced | en, hi evidenced | [en](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/V8-F-my-en-bottomnav-390.png), [hi](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/V8-F-my-hi-bottomnav-390.png) |
| X-04 | n/a | Assistant button overlaps the legal line (UI-07) | Not evidenced | en; Public tenant footnote English | [certificates page footer](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/V21-logo-certificates-1440.png), [390 overlap](../../../visual-evidence/2026-11-19/phase-c/vp_public77/G2-V21-fab-footer-overlap-390.png) |
| X-05 | Not evidenced | Not evidenced | Not evidenced | Panel strings English (UI-23) | No recent screenshot |
| X-06 | n/a | Not evidenced | Not evidenced | en, hi | No recent screenshot |
| X-07 | Steps 1 to 4 | Evidenced | Not evidenced | en, hi evidenced | [en](../../../visual-evidence/2026-11-19/phase-c/vp_report1b/V8-D-onboarding-en-step1-1440.png), [hi 390](../../../visual-evidence/2026-11-19/phase-c/vp_report1b/V8-D-onboarding-hi-step1-390.png) |

### 3.3 Public (logged-out) surfaces

| ID | Surface | URL path | Purpose and key components | Status |
|---|---|---|---|---|
| P-01 | Login | `/login/index.php` | Split screen: hero panel (3 feature blocks, 3 database-driven counters, slider) and sign-in form; SSO buttons when configured (`identity.sso.enabled` default OFF) | Available (UAT) |
| P-02 | Guest landing | `/` | Ten sections: navbar, hero, trust bar, why us, courses, pillars, testimonials, FAQ, call to action, footer. Dark toggle in the navbar, hidden while `ux.darkMode.enabled` is OFF. Signed-in users go to `/my/` | Available (UAT); landing dark mode Built (behind a feature flag, off by default) |
| P-03 | Legacy public homepage | `/local/sentientia_pages/homepage.php` | Older landing; different trust bar | Available (UAT) (recommended for retirement) |
| P-04 | Public catalogue (storefront) | `/local/sentientia_catalog/public.php` | Search, sort (Popular, Newest, A-Z), 12 per page, price or Free, Details, Add to cart or Enrol free, "Popular picks" rail | Available (UAT); rail flag `sentientia.catalog.public_lxp.enabled` default ON (its description says OFF: registry inconsistency) |
| P-05 | Course detail | `/local/sentientia_catalog/course.php?id=` | Course page for guests and learners: add to cart, enrol, or request enrolment | Available (UAT); request button Built (behind a feature flag, off by default: `sentientia.request.catalog_button`) |
| P-06 | Self-registration | `/local/sentientia_users/signup.php` | Public-tenant sign-up with Terms and Privacy tick box, e-mail confirmation, reCAPTCHA v2 when configured | Available (UAT); enabled by an admin setting, state on UAT to be confirmed |
| P-07 | Privacy policy (sign-up flow) | `/local/sentientia_users/privacypolicy.php` | Default text or admin-supplied HTML | Available (UAT) |
| P-08 | Terms of use (sign-up flow) | `/local/sentientia_users/termscondition.php` | Default text (8 sections) or admin-supplied HTML | Available (UAT) |
| P-09 | Static pages | `/local/sentientia_pages/index.php?page=privacy`, `terms`, `help`, `contact`, `dpdp` | Privacy, terms, help centre, contact, DPDP Act 2023 notice | Available (UAT) |
| P-10 | Certificate verification | `/admin/tool/certificate/index.php?code=` | Verified or Not verified, holder and certificate name, shown on arrival; `noindex, nofollow` | Available (UAT) (verify on arrival and noindex); the 5.0.12 re-vendor in the repository is Built (verified on development) |
| P-11 | PWA support files | `/local/sentientia_pwa/manifest.php`, `sw.php`, `offline.html` | Manifest, service worker, branded offline page | Built (behind a feature flag, off by default) |

| ID | States | Mobile | Dark | Languages | Best evidence |
|---|---|---|---|---|---|
| P-01 | Counters show test data before cutover | Evidenced (UAT, 590 px) | Not evidenced | en, hi (theme strings) | [UAT desktop](../../../visual-evidence/2026-10-21/uat/01-login-desktop.png), [UAT mobile](../../../visual-evidence/2026-10-21/uat/01-login-mobile.png), [band check](../../../visual-evidence/2026-11-18/walk-fixes/vp_public77/BAND-login-1440.png) |
| P-02 | n/a | Evidenced (UAT) | Flag-gated (`ux.darkMode.enabled`); not evidenced | **English only** (hard-coded) | [UAT desktop](../../../visual-evidence/2026-10-21/uat/02-front-page-desktop.png), [UAT mobile](../../../visual-evidence/2026-10-21/uat/02-front-page-mobile.png), [footer logo](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/V21-logo-front-loggedout-footerlogo-1440.png) |
| P-03 | n/a | Not evidenced | Not evidenced | English only | No screenshot |
| P-04 | Not evidenced | Evidenced (Sep) | Not evidenced | en, hi | [guest storefront](../../../visual-evidence/2026-09-30/personas/088-guest-storefront-desktop.png), [mobile](../../../visual-evidence/2026-09-30/personas/088-guest-storefront-mobile.png) |
| P-05 | Error (refused for wrong tenant), Added notice | Evidenced | Not evidenced | en, hi evidenced | [guest priced detail](../../../visual-evidence/2026-09-30/personas/089-guest-priced-detail-desktop.png), [Public learner add to cart](../../../visual-evidence/2026-11-19/phase-c/vp_public77/G1A-course71-addtocart-back-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_public77/G1A-course71-addtocart-back-390.png), [hi](../../../visual-evidence/2026-11-19/phase-c/vp_public77/G9-course71-hi-1440.png) |
| P-06 | Disabled notice when sign-up is off (not evidenced) | Evidenced (Sep) | Not evidenced | en, hi | [desktop](../../../visual-evidence/2026-09-30/personas/090-guest-signup-desktop.png), [mobile](../../../visual-evidence/2026-09-30/personas/090-guest-signup-mobile.png) |
| P-07, P-08 | n/a | Not evidenced | Not evidenced | Default text in strings; admin HTML is single-language | [privacy policy (learner view)](../../../visual-evidence/2026-09-30/personas/013-learner-privacy-policy-desktop.png) |
| P-09 | n/a | Evidenced | Not evidenced | **English only** | [contact](../../../visual-evidence/2026-11-18/walk-fixes/vp_learner1/ICONS-contact-1440.png), [DPDP](../../../visual-evidence/2026-11-18/walk-fixes/vp_learner1/ICONS-dpdp-1440.png), [DPDP 390](../../../visual-evidence/2026-11-18/walk-fixes/vp_learner1/ICONS-dpdp-390.png) |
| P-10 | Valid; Not verified; no code; no header or heading when logged out (UI-21) | Evidenced | Evidenced (590 px) | en, hi evidenced | [valid, logged out](../../../visual-evidence/2026-11-20/session/G-VENDOR/verify-new-code-loggedout-1440.png), [390](../../../visual-evidence/2026-11-20/session/G-VENDOR/verify-new-code-loggedout-390.png), [not found](../../../visual-evidence/2026-11-19/quick-wins/QW-CERT-VERIFY/verify-guest-notfound-1440.png), [dark](../../../visual-evidence/2026-11-19/quick-wins/QW-CERT-VERIFY/verify-notfound-dark-590.png), [hi](../../../visual-evidence/2026-11-19/quick-wins/QW-CERT-VERIFY/verify-notfound-hi-1440.png) |
| P-11 | Offline page | n/a | n/a | English | No screenshot |

### 3.4 Learner (Airpay, Public and ZEEA Mafunzo tenants)

| ID | Surface | URL path | Purpose and key components | Status |
|---|---|---|---|---|
| L-01 | Dashboard | `/my/` | Greeting, continue learning, gamification row (points, badges, streak), mini leaderboard (other people shown as first name and last initial), recommended courses (own tenant only), quick navigation | Available (UAT); gamification flag default ON |
| L-02 | Tenant catalogue | `/local/sentientia_catalog/index.php` (Airpay and ZEEA); `public.php` (Public) | Search, filter chips, tiles, pagination; one catalogue home per tenant | Available (UAT) |
| L-03 | Course detail | `/local/sentientia_catalog/course.php?id=` | Course summary, enrol or request, price for paid courses | Available (UAT) |
| L-04 | Course page | `/course/view.php?id=` | Course header (title, 7 icons, completion pill, certificate download), course index sidebar, activities | Available (UAT) |
| L-05 | My courses | `/local/sentientia_catalog/mycourses.php` | In progress and completed tabs, icon fallback for course art | Available (UAT) |
| L-06 | Cart and order history | `/local/sentientia_catalog/cart.php`; `/local/sentientia_cart/index.php`, `history.php`, `receipt.php`, `invoice.php` | Basket, checkout through the Airpay gateway, history, receipts, GST invoices | Available (UAT); storefront checkout, receipts and GST invoices Built (behind a feature flag, off by default: `sentientia.catalog.storefront_checkout.enabled`, `sentientia.cart.purchase_receipts`, `sentientia.cart.gst_tax_invoices`) |
| L-07 | Certificates gallery | `/local/sentientia_pages/certificates.php` | Earned certificates with Download, Verify and LinkedIn; names the course for course and imported certificates | Available (UAT); course naming Built (verified on development) |
| L-08 | Profile | `/local/sentientia_users/profile.php` | Initials avatar, role chip, details grid (consumer grid for Public), badges, skills card | Available (UAT) |
| L-09 | My skills | `/local/sentientia_skills/index.php`, `view.php` | Role skills, self-rating, summary hidden without role skills | Available (UAT) |
| L-10 | Notifications page and preferences | `/message/output/popup/notifications.php`; `/local/sentientia_notifications/prefs.php` | Notification cards with unread bar; channel preferences | Available (UAT) |
| L-11 | My requests | `/local/sentientia_request/index.php` | Enrolment requests and their status | Available (UAT) |
| L-12 | My evaluations and response form | `/local/sentientia_evaluation/my_evaluations.php`, `respond.php` | Pending surveys and feedback forms; response context checked on the server | Available (UAT); server-side context check Built (verified on development); learner history Built (behind a feature flag, off by default: `sentientia.evaluation.learner_history`) |
| L-13 | My learning paths, My programs | `/local/sentientia_learningpath/mypaths.php`; `/local/sentientia_programs/myprograms.php` | Learner views of paths and programs | Built (behind a feature flag, off by default) |
| L-14 | My classrooms and QR attendance scan | `/local/sentientia_classroom/my.php`; `/local/sentientia_pages/qr_scan.php` | Sessions, calendar file; scan a QR code to mark attendance | Available (UAT); QR entry point Built (behind a feature flag, off by default: `sentientia.classroom.qr_attendance`) |
| L-15 | Leaderboards and challenges | `/local/sentientia_leaderboard/index.php`; `/local/sentientia_challenge/index.php` | Rankings with opt-out; challenges | Leaderboards Built (behind a feature flag, off by default); challenges Available (UAT) |
| L-16 | Live session (join) | `/local/sentientia_live/index.php` | Polls, quizzes, Q&A | Built (behind a feature flag, off by default) |
| L-17 | My privacy and data | `/local/sentientia_privacy/index.php` | Data-rights self-service | Available (UAT) |
| L-18 | Calendar feed | `/local/sentientia_calendar/index.php` | iCal subscription | Built (behind a feature flag, off by default) |
| L-19 | Talent opportunities | `/local/sentientia_talent/opportunities.php` | Internal opportunity board | Built (behind a feature flag, off by default) |
| L-20 | WhatsApp and PWA preferences | `/local/sentientia_whatsapp/preferences.php`; `/local/sentientia_pwa/preferences.php` | Channel opt-in; install and push | Built (behind a feature flag, off by default); WhatsApp live sending not built (mock only) |
| L-21 | Online exam (learner view) | `/local/sentientia_exams/view.php` | Exam landing for the learner: instructions, attempt entry into the Moodle quiz | Available (UAT) |
| L-22 | Course player and SCORM | `/mod/scorm/player.php`, other Moodle activity pages inside `/course/view.php` | Activity playback restyled by the theme; SCORM resume at the last slide | Available (UAT) |
| L-23 | Secure assessment attempt | `/local/sentientia_proctoring/attempt.php`; theme layout `layout/secure.php` | Fullscreen secure mode with copy, paste and print blocked and a banner | Built (behind a feature flag, off by default: `sentientia.assessment.secure_mode.enabled`, `sentientia.proctoring.enabled`); B2 changes not yet on UAT and not verified end to end (UI-13) |
| L-24 | My share requests | `/local/sentientia_courses/my_requests.php` | Learner's own cross-tenant course share requests and their status | Available (UAT) |

| ID | States | Mobile | Dark | Languages | Best evidence |
|---|---|---|---|---|---|
| L-01 | Empty Continue Learning removed; leaderboard caption | Evidenced | Not evidenced | en, hi evidenced | [Airpay 1440](../../../visual-evidence/2026-11-18/walk-fixes/vp_learner1/MYCHROME-my-full-1440.png), [390](../../../visual-evidence/2026-11-18/walk-fixes/vp_learner1/MYCHROME-my-full-390.png), [Public](../../../visual-evidence/2026-11-18/walk-fixes/vp_public77/GAMIF-dash-gamification-1440.png), [ZEEA](../../../visual-evidence/2026-11-19/phase-c/vp_learner177/PhaseB-RECS177-my-recommended-zeea-1440.png), [UAT dashboard (30 Oct)](../../../visual-evidence/2026-10-30/uat-my-check/01-my-dashboard-desktop.jpg), [UAT blocks drawer](../../../visual-evidence/2026-10-30/uat-my-check/02-my-blocks-drawer-open-desktop.jpg), [UAT calendar (11 Nov)](../../../visual-evidence/2026-11-11/uat-fixes-check/02-dashboard-drawer-calendar-day-names.jpg) |
| L-02 | Results, filter removal | Evidenced | Not evidenced | en, hi evidenced; some aria labels English | [en](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/V3-V8-B-catalog-en-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/V3-V8-B-catalog-en-390.png), [hi](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/V8-B-catalog-hi-1440.png), [ZEEA](../../../visual-evidence/2026-11-19/phase-c/vp_learner177/PhaseB-CAT177-catalog-zeea-courses-1440.png), [Public](../../../visual-evidence/2026-11-18/walk-fixes/vp_public77/CAT77-public-sidebar-1440.png) |
| L-03 | Error: course from another tenant refused | Evidenced | Not evidenced | en, hi evidenced | [enrolled](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/G1A-course448-enrolled-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/G1A-course448-enrolled-390.png), [hi](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/G1A-course448-hi-1440.png), [refused](../../../visual-evidence/2026-11-19/phase-c/vp_learner177/PhaseB-CAT177-course448-airpay-refused-1440.png) |
| L-04 | n/a | Evidenced (390 to 1920 px) | Evidenced | en, hi | [1440](../../../visual-evidence/2026-11-18/course-title-overlap/after-learner-1440px.png), [390](../../../visual-evidence/2026-11-18/course-title-overlap/after-learner-390px.png), [dark](../../../visual-evidence/2026-11-18/course-title-overlap/after-learner-dark-1440px.png), [header icons](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/V19-COURSE-HEADER-course256-1440.png) |
| L-05 | Empty completed tab | Evidenced | Not evidenced | en, hi evidenced ("General" fallback English) | [en](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/G1B-mycourses-en-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/G1B-mycourses-en-390.png), [hi](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/G1B-mycourses-hi-1440.png), [empty](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/G1B-mycourses-empty-completed-1440.png) |
| L-06 | Empty cart with browse link | Evidenced | Not evidenced | en, hi (cart page partly English) | [empty cart](../../../visual-evidence/2026-11-19/phase-c/vp_public77/G1B-cart-empty-browse-1440.png), [ZEEA 390](../../../visual-evidence/2026-11-19/phase-c/vp_learner177/G1B-cart-empty-browse-courses-390.png), [order history](../../../visual-evidence/2026-10-16/cart/06-public77-order-history-desktop.png) |
| L-07 | Empty state; gallery with course names | Evidenced | Not evidenced | en, hi evidenced | [gallery (development)](../../../visual-evidence/2026-11-20/session/G-CERT-ISSUER/learner-certificates-gallery-1440.png), [390](../../../visual-evidence/2026-11-20/session/G-CERT-ISSUER/learner-certificates-gallery-390.png), [empty](../../../visual-evidence/2026-11-18/walk-fixes/vp_learner1/CERTS-certificates-en-1440.png), [UAT issued certificate, test persona](../../../visual-evidence/2026-11-18/certificate-redesign/uat-issued-certificate-test-persona.png), [PDF (development)](../../../visual-evidence/2026-11-18/certificate-redesign/after-issued-certificate.png) |
| L-08 | N/A fields shown as a dash | Evidenced | Evidenced | en, hi evidenced | [en](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/G4A-profile-en-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/G4A-profile-en-390.png), [dark](../../../visual-evidence/2026-11-19/phase-c/vp_public77/PB-1-profile-dark-1440.png), [hi](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/G4A-G9-profile-hi-1440.png) |
| L-09 | Empty (no role skills) | Evidenced | Not evidenced | en, hi evidenced | [title](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/V3-skills-title-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/V3-skills-title-390.png), [self-rate](../../../visual-evidence/2026-11-19/quick-wins/QW-SKILLS-UI/skill-view-selfrate-1440.png), [empty hi](../../../visual-evidence/2026-11-18/walk-fixes/vp_manager1/SKILLS-hi-emptystate-1440.png) |
| L-10 | Unread rows | Evidenced | Evidenced; unread rows hard to read (UI-06) | en, hi | [page](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/V4-V20-notifications-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/V4-V20-notifications-390.png), [dark](../../../visual-evidence/2026-11-19/phase-c/vp_public77/G2-V20-notifications-page-dark-1440.png), [preferences](../../../visual-evidence/2026-11-19/phase-c/vp_learner1/V4-preferences-1440.png), [UAT preferences (30 Oct)](../../../visual-evidence/2026-10-30/uat-my-check/03-notification-preferences-desktop.jpg), [UAT preferences (11 Nov)](../../../visual-evidence/2026-11-11/uat-fixes-check/01-notification-preferences-one-trail.jpg) |
| L-11 | Pending request | Evidenced | Evidenced (Oct) | en, hi | [requests](../../../visual-evidence/2026-11-18/walk-fixes/vp_learner1/CRUMBS-request-1440.png), [390](../../../visual-evidence/2026-11-18/walk-fixes/vp_learner1/CRUMBS-request-390.png), [pending dark](../../../visual-evidence/2026-10-16/request/13b-learner-card-request-pending-dark-desktop.png) |
| L-12 | Refusal page (not on the classroom roster) | Evidenced | Not evidenced | en, hi evidenced | [refusal](../../../visual-evidence/2026-11-19/session-01/G-EVAL/refuse-classroom-not-on-roster-1440.png), [my evaluations](../../../visual-evidence/2026-10-16/evaluation/01-my-evaluations-en-desktop.png), [hi mobile](../../../visual-evidence/2026-10-16/evaluation/02-my-evaluations-hi-mobile.png) |
| L-13 | Not evidenced | Not evidenced | Not evidenced | en, hi | No recent screenshot |
| L-14 | Errors: expired token, cancelled class, not enrolled, other organisation, outside window | Evidenced | Not evidenced | en, hi evidenced | [recorded](../../../visual-evidence/2026-09-30/qr-and-loginas/01-scan-recorded-mobile.png), [expired](../../../visual-evidence/2026-09-30/qr-and-loginas/03-scan-expired-token-mobile.png), [hi](../../../visual-evidence/2026-09-30/qr-and-loginas/18-scan-already-marked-hindi-mobile.png) |
| L-15 | Opt-out | Evidenced | Not evidenced | en, hi evidenced | [leaderboard](../../../visual-evidence/2026-11-18/walk-fixes/vp_learner1/LEADER-leaderboard-1440.png), [hi](../../../visual-evidence/2026-11-19/phase-c/vp_report1b/PhaseB-leaderboard-hi-1440.png), [challenges](../../../visual-evidence/2026-09-29/50-learner1-challenges-desktop.png) |
| L-16 | Not evidenced | Not evidenced | Not evidenced | en, hi | Trainer view only (T-03) |
| L-17 | n/a | Evidenced (Sep) | Not evidenced | en, hi | [desktop](../../../visual-evidence/2026-09-30/personas/012-learner-privacy-desktop.png), [mobile](../../../visual-evidence/2026-09-30/personas/012-learner-privacy-mobile.png) |
| L-18 to L-20 | Not evidenced | Not evidenced | Not evidenced | en, hi | No recent screenshot |
| L-21 | Not evidenced | Not evidenced | Not evidenced | en, hi | Learner view not captured; admin list at AD-07 |
| L-22 | Resume at last slide | Not evidenced | Not evidenced | en, hi (package content is single-language) | [SCORM resume (development, 22 Oct)](../../../visual-evidence/2026-10-22/scorm-resume/01-learner-reopened-resumes-at-slide-3-desktop.png) |
| L-23, L-24 | Not evidenced | Not evidenced | Not evidenced | en, hi | No screenshot |

### 3.5 Line manager

| ID | Surface | URL path | Purpose and key components | Status |
|---|---|---|---|---|
| M-01 | Manager dashboard | `/my/` | Team greeting, team table that scrolls inside its card on phones | Available (UAT) |
| M-02 | My Team | `/local/sentientia_manager/index.php` | Team list; "Never" for no access | Available (UAT) |
| M-03 | Team member | `/local/sentientia_manager/member.php` | One person's progress; mobile breadcrumb left-aligned | Available (UAT) |
| M-04 | Team performance | `/local/sentientia_manager/performance.php` | Period pills, stat cards, table | Available (UAT) |
| M-05 | Allocations | `/local/sentientia_manager/allocations.php` | Assign courses to direct reports | Available (UAT) |
| M-06 | Approvals | `/local/sentientia_request/approvals.php` | Approve or reject enrolment requests; manager requests page hands off here | Available (UAT) |
| M-07 | Team compliance | `/local/sentientia_compliance_report/index.php` | RAG matrix limited to the reporting tree | Available (UAT) |
| M-08 | Nudge | `/local/sentientia_notifications/nudge.php` | Send a reminder | Available (UAT) |

| ID | States | Mobile | Dark | Languages | Best evidence |
|---|---|---|---|---|---|
| M-01 | n/a | Evidenced | Not evidenced | en, hi | [greeting](../../../visual-evidence/2026-11-19/phase-c/vp_manager1/V16-my-manager-greeting-1440.png), [team table 390](../../../visual-evidence/2026-11-19/phase-c/vp_manager1/G3-my-team-table-scrolled-390.png) |
| M-02 | "Never" for no access | Evidenced | Not evidenced | en, hi | [1440](../../../visual-evidence/2026-11-18/walk-fixes/vp_manager1/TEAM-myteam-never-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_manager1/G2-V3-myteam-title-390.png) |
| M-03 | n/a | Evidenced | Not evidenced | en, hi evidenced (stat cards partly English) | [member](../../../visual-evidence/2026-11-19/phase-c/vp_manager1/G4B-member-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_manager1/G2-V15-member-breadcrumb-390.png), [hi](../../../visual-evidence/2026-11-19/phase-c/vp_manager1/G9-member-hindi-1440.png) |
| M-04 | n/a | Evidenced | Not evidenced | en, hi evidenced (partly English) | [1440](../../../visual-evidence/2026-11-19/phase-c/vp_manager1/G4B-perf-standard-shell-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_manager1/G4B-perf-pills-cards-table-390.png), [hi 390](../../../visual-evidence/2026-11-19/phase-c/vp_manager1/G9-perf-hindi-390.png) |
| M-05 | n/a | Not evidenced | Not evidenced | en, hi | [1440](../../../visual-evidence/2026-11-19/phase-c/vp_manager1/G4B-allocations-1440.png) |
| M-06 | n/a | Evidenced | Not evidenced | en, hi | [1440](../../../visual-evidence/2026-11-18/walk-fixes/vp_manager1/APPROVALS-approvals-breadcrumb-1440.png), [390](../../../visual-evidence/2026-11-18/walk-fixes/vp_manager1/APPROVALS-approvals-breadcrumb-390.png), [layout](../../../visual-evidence/2026-10-23/request-approvals-layout/01-linemanager-approvals-standard-layout-desktop.png) |
| M-07 | Empty states for all four tabs; "Run the snapshot task first" when data is missing | Evidenced | Not evidenced | en, hi | [team scope](../../../visual-evidence/2026-11-19/phase-c/vp_manager1/G7-compliance-team-scope-1440.png), [empty matrix](../../../visual-evidence/2026-11-18/walk-fixes/vp_manager1/MGRCOMP-matrix-emptystate-1440.png), [390](../../../visual-evidence/2026-11-18/walk-fixes/vp_manager1/MGRCOMP-matrix-emptystate-390.png) |
| M-08 | Not evidenced | Not evidenced | Not evidenced | en, hi | No recent screenshot |

### 3.6 Trainers (internal and external) and course author

| ID | Surface | URL path | Purpose and key components | Status |
|---|---|---|---|---|
| T-01 | Trainer dashboard | `/my/` | "Courses you teach" instead of a learner view of taught courses | Available (UAT) |
| T-02 | Classrooms | `/local/sentientia_classroom/index.php`, `view.php`, `attendance.php` | Instructor-led sessions and attendance grid | Available (UAT); trainer page fails with a permission dialog (UI-05) |
| T-03 | Live sessions (run) | `/local/sentientia_live/index.php` | Create and run polls, quizzes and Q&A | Built (behind a feature flag, off by default) |
| T-04 | QR attendance projector | `/local/sentientia_pages/qr_attendance.php` | Rotating QR code for a session | Available (UAT); entry point Built (behind a feature flag, off by default: `sentientia.classroom.qr_attendance`) |
| T-05 | Evaluations (trainer view) | `/local/sentientia_evaluation/index.php` | Feedback forms for sessions | Available (UAT) |
| A-01 | Authoring studio | `/local/sentientia_authoring/index.php`, `studio.php`, `templates.php`, `review.php`, `voiceover.php` | SOP-to-course drafts, preview badge, drafts link, model field only for site administrators | Built (behind a feature flag, off by default); generation runs in mock mode |
| A-02 | AI quiz generation | `/local/sentientia_aiquiz/generate.php`, `review.php` | Generate and review questions | Built (behind a feature flag, off by default); mock mode |
| A-03 | Skills AI | `/local/sentientia_skillsai/index.php`, `extract.php`, `gaps.php`, `review.php`, `taxonomy.php` | Skill extraction and gaps | Built (behind a feature flag, off by default); mock mode |
| A-04 | Course editing | `/course/view.php?id=&edit=on` | Edit mode on phones, section and activity editing | Available (UAT) |
| ET-01 | External trainer view | `/local/sentientia_classroom/view.php`, `attendance.php` (assigned sessions only) | Time-limited account for a non-employee trainer (role `sentientia_externaltrainer`, 2 capabilities: view assigned classroom rosters, names only, and mark attendance; nothing else; account expires 30 days after the last session; ADR-036) | Built (behind a feature flag, off by default: `sentientia.users.external_trainer.enabled`); not on UAT |

| ID | States | Mobile | Dark | Languages | Best evidence |
|---|---|---|---|---|---|
| T-01 | n/a | Evidenced | Not evidenced | en, hi evidenced | [trainer](../../../visual-evidence/2026-11-19/phase-c/vp_author1/V16-UNFLAG-my-courses-you-teach-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_author1/V16-UNFLAG-my-courses-you-teach-390.png), [course author](../../../visual-evidence/2026-11-19/phase-c/vp_courseauthor1/G3-my-teaching-1440.png) |
| T-02 | Error: permission dialog | Filter row clipped at 390 px | Not evidenced | en, hi | [error](../../../visual-evidence/2026-11-19/phase-c/vp_author1/PhaseB-classroom-org-ajax-error-1440.png), [Sep view](../../../visual-evidence/2026-09-30/personas/033-trainer-classroom-desktop.png) |
| T-03 | Not evidenced | Evidenced (Sep) | Not evidenced | en, hi | [live index](../../../visual-evidence/2026-11-19/phase-c/vp_author1/PhaseB-live-index-1440.png), [mobile](../../../visual-evidence/2026-09-30/personas/034-trainer-live-mobile.png) |
| T-04 | Refused for learners and other tenants | Evidenced | Not evidenced | en, hi | [heading](../../../visual-evidence/2026-09-30/qr-and-loginas/08-qr-attendance-heading-desktop.png), [refused](../../../visual-evidence/2026-09-30/qr-and-loginas/17-qr-attendance-other-tenant-admin-refused-desktop.png) |
| T-05 | Not evidenced | Evidenced (Sep) | Not evidenced | en, hi | [desktop](../../../visual-evidence/2026-09-30/personas/035-trainer-evaluations-desktop.png) |
| A-01 | Generate status shown | Evidenced; drafts table not responsive at 390 px | Not evidenced | en, hi evidenced | [studio](../../../visual-evidence/2026-11-19/phase-c/vp_author1/G4B-studio-preview-badge-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_author1/G4B-studio-preview-badge-390.png), [index](../../../visual-evidence/2026-11-19/phase-c/vp_author1/G4B-index-generate-status-1440.png), [hi](../../../visual-evidence/2026-11-19/phase-c/vp_author1/G9-index-hi-status-labels-1440.png) |
| A-02, A-03 | Not evidenced | Not evidenced | Not evidenced | en, hi | No recent screenshot |
| ET-01 | Not evidenced | Not evidenced | Not evidenced | en, hi, kn, mr, sw (users plugin strings) | No screenshot |
| A-04 | n/a | Evidenced (390, 590, 768 px) | Evidenced | en, hi | [edit 1440](../../../visual-evidence/2026-11-16/demo-quick-wins/after-04-courseauthor-course-edit-1440.png), [edit 390 dark](../../../visual-evidence/2026-11-16/edit-mode-phone-dark-banner/after-04-courseauthor-edit-390-dark.png), [drawer 390](../../../visual-evidence/2026-11-16/edit-mode-phone-dark-banner/after-07-courseauthor-edit-390-drawer-open.png) |

### 3.7 L&D administrator / tenant administrator

| ID | Surface | URL path | Purpose and key components | Status |
|---|---|---|---|---|
| AD-01 | Admin dashboard | `/my/` | KPI tiles ("Active (30 days)"), charts, course distribution | Available (UAT) |
| AD-02 | Manage users | `/local/sentientia_users/index.php` (+ `bulk_csv`, `bulk_import`, `bulk_hrms`, `sync_runs`) | User list with Actions column, bulk import, HR-system sync history | Available (UAT) |
| AD-03 | External trainers | `/local/sentientia_users/external_trainers.php` | Time-limited trainer accounts | Built (behind a feature flag, off by default: `sentientia.users.external_trainer.enabled`) |
| AD-04 | Manage courses | `/local/sentientia_courses/index.php` | Course list, New course and Edit course pop-ups; row actions Open course and Advanced settings | Available (UAT); row actions Built (behind a feature flag, off by default: `sentientia.courses.advanced_actions.enabled`) |
| AD-05 | Browse library and share requests | `/local/sentientia_courses/browse_airpay.php`, `share.php`, `manage_requests.php` | Cross-tenant course library | Available (UAT); paging open (UI-04) |
| AD-06 | Featured courses | `/local/sentientia_courses/featured.php` | Curate featured courses | Available (UAT) |
| AD-07 | Online exams | `/local/sentientia_exams/index.php` | Exam list | Available (UAT) |
| AD-08 | Classrooms (admin) | `/local/sentientia_classroom/index.php` | Session management, New classroom pop-up | Available (UAT) |
| AD-09 | Learning paths | `/local/sentientia_learningpath/index.php`, `view.php` | Paths, courses tab, enrolment, unenrol dialog | Available (UAT); dialog titles Built (verified on development); lock on imported rows Built, not yet verified (no local data to test it) |
| AD-10 | Programs | `/local/sentientia_programs/index.php`, `view.php` | Multi-level programs; Edit program pop-up | Available (UAT); sidebar link Built (verified on development) |
| AD-11 | Analytics | `/local/sentientia_analytics/index.php`, `drilldown.php` | KPIs, funnel, department heatmap, YTD comparison; ROI card and predictive panels | Available (UAT); ROI and predictive Built (behind a feature flag, off by default); YTD comparison Built (verified on development) |
| AD-12 | Compliance report | `/local/sentientia_compliance_report/index.php` | Matrix, defaulters, manager and scorecard tabs, export | Available (UAT); "Not applicable" cells Built (verified on development) |
| AD-13 | Organisation and tenant settings | `/local/sentientia_org/admin.php`, `tenant_settings.php` | Org tree, tenant card with menu | Available (UAT) |
| AD-14 | Skills administration | `/local/sentientia_skills/admin.php`, `course_mapping.php`, `designation_matrix.php` | Skills framework, matrix | Available (UAT) |
| AD-15 | Evaluations (admin) | `/local/sentientia_evaluation/index.php`, `analysis.php`, `non_respondents.php` | Forms, analysis, non-respondents | Available (UAT); response drill-down Built (behind a feature flag, off by default: `sentientia.evaluation.response_drilldown`) |
| AD-16 | Certificate templates | `/local/sentientia_pages/certificate_templates.php` | Template browser with ID and empty-template badge | Available (UAT) |
| AD-17 | E-mail templates and rules | `/local/sentientia_emails/manage.php`, `editor.php`, `preview.php` | 20 registry templates, previews, design studio, rules | Available (UAT); rule editor fix (keeps every stored value on edit, Enabled box can switch a rule off, 5 more rule types; `local_sentientia_emails` 1.4.3) Built (verified on development), not on UAT (UI-09) |
| AD-18 | Notifications log | `/local/sentientia_notifications/index.php`, `logs.php` | Delivery log | Available (UAT) |
| AD-19 | Reports | `/local/sentientia_reports/index.php` | Report builder | Available (UAT) |
| AD-20 | Roles | `/local/sentientia_roles/index.php`, `compare.php`, `audit.php` | Role and capability views | Available (UAT) |
| AD-21 | Re-completion rules | `/local/sentientia_recompletion/index.php` | Recertification cycles | Available (UAT) |
| AD-22 | Orders and prices | `/local/sentientia_cart/admin_orders.php`, `set_price.php`, `daily_sums.php` | Orders, refunds, prices | Available (UAT); imported orders and credits Built (behind a feature flag, off by default: `sentientia.cart.imported_orders.enabled`, `sentientia.cart.imported_credits.enabled`) |
| AD-23 | Ratings and reviews | `/local/sentientia_ratings/reviews.php` | Course reviews | Available (UAT); review list, reactions and widget Built (behind a feature flag, off by default: `sentientia.ratings.reviews`, `sentientia.ratings.reactions`, `sentientia.ratings.widget`) |
| AD-24 | Translation, content marketplace | `/local/sentientia_translate/translate.php`; `/local/sentientia_content_market/index.php` | AI translation; third-party content | Built (behind a feature flag, off by default) |
| AD-25 | Secure assessment administration and review | `/local/sentientia_proctoring/index.php`, `admin.php`, `review.php` | Secure-mode settings per quiz, violation review | Built (behind a feature flag, off by default: `sentientia.proctoring.enabled`, `sentientia.assessment.secure_mode.enabled`); B2 changes not yet on UAT |
| AD-26 | xAPI settings | `/local/sentientia_xapi/index.php` | xAPI statement settings and learning record store endpoint | Built (behind a feature flag, off by default: `sentientia.xapi.enabled`) |

| ID | States | Mobile | Dark | Languages | Best evidence |
|---|---|---|---|---|---|
| AD-01 | n/a | Evidenced | Not evidenced | en, hi evidenced | [1440](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/G3-my-admin-dashboard-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/PhaseB-DASHA-my-admin-390.png), [hi](../../../visual-evidence/2026-11-18/walk-fixes/vp_compliance1/DASH-hindi-active30-1440.png) |
| AD-02 | Clean load, 0 console errors | Not evidenced | Not evidenced | en, hi | [Actions header](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/G5-users-actions-header-1440.png), [clean load](../../../visual-evidence/2026-11-19/phase-c/repair/users-clean-load-0-errors-1440.png), [UAT full-name search (11 Nov)](../../../visual-evidence/2026-11-11/uat-fixes-check/03-manage-users-full-name-search.jpg) |
| AD-03 | Refused with flag OFF | Not evidenced | Not evidenced | en, hi, kn, mr, sw | No screenshot |
| AD-04 | Flag OFF rows; pop-ups | Evidenced | Not evidenced | en, hi | [list](../../../visual-evidence/2026-11-19/session-01/G-COURSES/manage-courses-flag-off-1440.png), [390](../../../visual-evidence/2026-11-19/session-01/G-COURSES/manage-courses-flag-off-390.png), [edit pop-up](../../../visual-evidence/2026-11-19/quick-wins/QW-MODAL-FORMS/course-edit-modal-after-fix-1440.png), [new pop-up 390](../../../visual-evidence/2026-11-19/session-01/G-COURSES/new-course-modal-390.png) |
| AD-05 | Search with no results | Table clipped at 390 px | Not evidenced | en, hi, sw evidenced | [after revert](../../../visual-evidence/2026-11-19/phase-c/repair/V23-reverted-browse-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/repair/V23-reverted-browse-390.png), [clipped](../../../visual-evidence/2026-11-19/phase-c/vp_admin177/G5-table-clipped-390.png), [Swahili](../../../visual-evidence/2026-11-19/phase-c/vp_admin177/G5-lang-sw-1440.png) |
| AD-06 | n/a | Evidenced (375 px); buttons touch | Not evidenced | en, hi evidenced | [hi](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/V8-E-featured-hi-1440.png), [375](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/V8-E-featured-hi-375.png) |
| AD-07 | n/a | Evidenced (Sep) | Not evidenced | en, hi | [icons](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/PhaseB-ICONSA-exams-1440.png), [mobile](../../../visual-evidence/2026-09-29/22-admin1-exams-mobile.png) |
| AD-08 | n/a | Evidenced | Not evidenced | en, hi | [Actions header](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/G5-classroom-actions-header-1440.png), [pop-up](../../../visual-evidence/2026-11-19/quick-wins/QW-MODAL-FORMS/classroom-new-modal-after-fix-1440.png), [390](../../../visual-evidence/2026-11-19/quick-wins/QW-MODAL-FORMS/classroom-new-modal-390.png) |
| AD-09 | Unenrol dialog | Evidenced; Organisation select wider than card at 390 px | Not evidenced | en, hi evidenced (Courses tab partly English) | [table](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/G5-learningpath-table-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/G5-learningpath-table-390.png), [courses tab](../../../visual-evidence/2026-11-19/session-01/G-LP/view-courses-tab-path24-en-1440.png), [dialog hi](../../../visual-evidence/2026-11-19/session-01/G-LP/view-unenrol-user-dialog-hi-1440.png) |
| AD-10 | n/a | Evidenced | Not evidenced | en, hi (title and KPI labels English) | [page](../../../visual-evidence/2026-11-19/session-01/G-NAV/ldadmin-programs-page-1440.png), [390](../../../visual-evidence/2026-11-19/session-01/G-NAV/ldadmin-programs-page-390.png), [pop-up](../../../visual-evidence/2026-11-19/quick-wins/QW-MODAL-FORMS/program-edit-modal-after-fix-1440.png) |
| AD-11 | ROI empty (30 days) | Evidenced; heatmap bars thin, columns clipped at 390 px | Evidenced (ROI) | en, hi evidenced | [funnel](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/G6-analytics-funnel-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/G6-analytics-funnel-390.png), [YTD](../../../visual-evidence/2026-11-19/session-01/G-ANALYTICS/analytics-ytd-1440.png), [ROI dark](../../../visual-evidence/2026-11-19/quick-wins/QW-ROI/roi-card-ytd-dark-1440.png), [ROI empty](../../../visual-evidence/2026-11-19/quick-wins/QW-ROI/roi-card-30d-empty-1440.png) |
| AD-12 | "No compliance assignments"; stale snapshot warning | Evidenced; filter bar stacks at 390 px | Evidenced | en, hi | [KPIs](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/G6-compliance-kpis-rate-1440.png), [scorecard](../../../visual-evidence/2026-11-18/walk-fixes/vp_compliance1/SCORE-tree-default-1440.png), [filter 390](../../../visual-evidence/2026-11-19/phase-c/vp_compliance1/PB-2-matrix-filterbar-stacked-390.png), [dark](../../../visual-evidence/2026-11-19/phase-c/vp_compliance1/PB-2-matrix-filterbar-dark-1440.png), [empty ZEEA](../../../visual-evidence/2026-11-19/session-01/G-COMPLIANCE/report-matrix-admin177-before-execute-1440.png) |
| AD-13 | Confirm dialog | Evidenced; chevron wraps at 390 px | Not evidenced | en, hi | [tenant card](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/V19-ORG-CARD-tenant-card-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/V19-ORG-CARD-tenant-card-390.png), [tenant settings](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/PhaseB-BANDA-tenant-settings-1440.png) |
| AD-14 | n/a | Evidenced | Evidenced | en, hi | [admin](../../../visual-evidence/2026-11-19/quick-wins/QW-SKILLS-UI/admin-1440.png), [390](../../../visual-evidence/2026-11-19/quick-wins/QW-SKILLS-UI/admin-390.png), [dark](../../../visual-evidence/2026-11-19/quick-wins/QW-SKILLS-UI/admin-dark-1440.png), [matrix](../../../visual-evidence/2026-11-19/quick-wins/QW-SKILLS-UI/designation-matrix-1440.png) |
| AD-15 | Not evidenced | Evidenced (Sep) | Not evidenced | en, hi | [Sep](../../../visual-evidence/2026-09-29/21-admin1-evaluation-desktop.png) |
| AD-16 | Empty-template badge | Evidenced | Not evidenced | en, hi evidenced | [en](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/G5-G9-certificate-templates-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/G5-G9-certificate-templates-390.png), [hi](../../../visual-evidence/2026-11-19/phase-c/vp_admin1/G9-certificate-templates-hi-1440.png) |
| AD-17 | Unknown template message; legacy read-only | Evidenced; rules Actions column overflows at 1440 px | Not evidenced | en, hi page; e-mail body English | [preview](../../../visual-evidence/2026-11-19/quick-wins/QW-EMAIL/preview-welcome-enrolled-1440.png), [390](../../../visual-evidence/2026-11-19/quick-wins/QW-EMAIL/preview-welcome-enrolled-390.png), [unknown](../../../visual-evidence/2026-11-19/quick-wins/QW-EMAIL/preview-unknown-template-1440.png), [rules](../../../visual-evidence/2026-11-19/session-01/G-EMAIL/rules-list-1440.png), [rule form](../../../visual-evidence/2026-11-20/session/G-RULES/rule-6-edit-form-1440.png) |
| AD-18 | n/a | Evidenced (Sep) | Not evidenced | en, hi | [log](../../../visual-evidence/2026-09-29/27-admin1-notifications-logs-desktop.png) |
| AD-19 | n/a | Evidenced (Sep) | Not evidenced | en, hi | [reports](../../../visual-evidence/2026-09-29/28-admin1-reports-desktop.png) |
| AD-20 | n/a | Evidenced | Not evidenced | en, hi | [roles](../../../visual-evidence/2026-09-29/15-admin1-roles-desktop.png), [capability pop-up](../../../visual-evidence/2026-11-19/quick-wins/QW-MODAL-FORMS/roles-edit-cap-modal-default-size-after-fix-1440.png) |
| AD-21 | Not evidenced | Not evidenced | Not evidenced | en, hi | No screenshot |
| AD-22 | Refund dialog | Evidenced (Oct) | Not evidenced | en, hi | [orders](../../../visual-evidence/2026-10-16/cart/04-admin1-admin-orders-desktop.png), [mobile](../../../visual-evidence/2026-10-16/cart/04-admin1-admin-orders-mobile.png), [refund](../../../visual-evidence/2026-10-16/cart/05-admin1-refund-dialog-paid-order-desktop.png) |
| AD-23 to AD-26 | Not evidenced | Not evidenced | Not evidenced | en, hi | No screenshot |

### 3.8 Compliance officer

| ID | Surface | URL path | Purpose and key components | Status |
|---|---|---|---|---|
| C-01 | Compliance report (tenant scope) | `/local/sentientia_compliance_report/index.php` | Matrix, defaulters, manager and scorecard tabs; reached from the sidebar | Available (UAT) |
| C-02 | Analytics (compliance view) | `/local/sentientia_analytics/index.php` | Department rates from the compliance source; range pills; Hindi labels | Available (UAT) |
| C-03 | Compliance officer dashboard | `/my/` | Organisation-admin shell when the officer also holds an org-admin role | Available (UAT) |

| ID | States | Mobile | Dark | Languages | Best evidence |
|---|---|---|---|---|---|
| C-01 | Defaulters, manager, scorecard | Evidenced | Evidenced | en, hi | [defaulters](../../../visual-evidence/2026-11-19/phase-c/vp_compliance1/G6-defaulters-tab-1440.png), [scorecard](../../../visual-evidence/2026-11-19/phase-c/vp_compliance1/G6-scorecard-tab-1440.png), [opens 390](../../../visual-evidence/2026-11-19/phase-c/vp_compliance1/G7-compliance-report-opens-390.png), [scorecard dark](../../../visual-evidence/2026-11-18/walk-fixes/vp_compliance1/SCORE-dark-legible-1440.png) |
| C-02 | n/a | Evidenced | Not evidenced | en, hi evidenced | [rates](../../../visual-evidence/2026-11-18/walk-fixes/vp_compliance1/ANALYTICS-dept-rates-1440.png), [390](../../../visual-evidence/2026-11-18/walk-fixes/vp_compliance1/ANALYTICS-dept-rates-390.png), [hi](../../../visual-evidence/2026-11-18/walk-fixes/vp_compliance1/ANALYTICS-hindi-1440.png) |
| C-03 | n/a | Evidenced | Not evidenced | en, hi | [1440](../../../visual-evidence/2026-11-19/phase-c/vp_compliance1/G7-my-dashboard-orgadmin-shell-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_compliance1/G7-my-dashboard-orgadmin-shell-390.png) |

### 3.9 Site administrator and platform L&D (cross-tenant)

| ID | Surface | URL path | Purpose and key components | Status |
|---|---|---|---|---|
| S-01 | Site admin dashboard | `/my/` | Platform overview | Available (UAT) |
| S-02 | Switchboard (feature flags) | `/local/sentientia_platform/admin/switchboard.php` | Show and set flags with an audit trail | Available (UAT) |
| S-03 | Tenants and admin log | `/local/sentientia_core/manage_tenants.php`, `admin_log.php` | Tenant registry, imported admin log | Available (UAT); imported admin log Built (behind a feature flag, off by default: `sentientia.legacy_logs.report.enabled`) |
| S-04 | Privacy requests and DPDP | `/admin/tool/dataprivacy/`, `/local/sentientia_privacy/index.php` | Data requests, DPDP administration | Available (UAT) |
| S-05 | API, SCIM and webhooks | `/local/sentientia_api/index.php`, `scim.php`, `webhooks.php` | Tokens and integrations | Built (behind a feature flag, off by default) |
| S-06 | AI gateway settings | `/local/sentientia_ai/index.php` | Keys, quotas, spend ledger; mock by default | Built (behind a feature flag, off by default) |
| S-07 | Admin search and admin-layout pages | `/admin/search.php` | One footer and one script load on admin pages | Available (UAT) |
| S-08 | Microsoft 365 administration | `/local/sentientia_m365/admin/index.php` | Microsoft 365 integration scaffold; site administrators only | Built (behind a feature flag, off by default: `sentientia_m365_enabled`); scaffold only |
| S-09 | Platform style guide | `/local/sentientia_platform/admin/styleguide.php` | Rendered reference of the design system (tokens and components) for administrators; relevant to the design-system review in section 2 | Available (UAT) |
| S-10 | Maintenance page and secure quiz layout | Theme layouts `layout/maintenance.php`, `layout/secure.php` | Page shown to everyone during maintenance; reduced chrome for quiz attempts in secure mode | Available (UAT) |

| ID | States | Mobile | Dark | Languages | Best evidence |
|---|---|---|---|---|---|
| S-01 | n/a | Evidenced (Sep) | Not evidenced | en, hi | [Sep](../../../visual-evidence/2026-09-30/personas/116-siteadmin-dashboard-desktop.png) |
| S-02 | n/a | Evidenced (Sep) | Not evidenced | en | [Sep](../../../visual-evidence/2026-09-30/personas/120-siteadmin-switchboard-desktop.png), [mobile](../../../visual-evidence/2026-09-30/personas/120-siteadmin-switchboard-mobile.png) |
| S-03 | Not evidenced | Not evidenced | Not evidenced | en, hi | No screenshot |
| S-04 | n/a | Evidenced (Sep) | Not evidenced | en, hi | [privacy requests](../../../visual-evidence/2026-09-30/personas/121-siteadmin-privacy-requests-desktop.png), [DPDP](../../../visual-evidence/2026-09-30/personas/122-siteadmin-dpdp-desktop.png) |
| S-05 | n/a | Evidenced (Sep) | Not evidenced | en, hi | [SCIM](../../../visual-evidence/2026-09-29/recheck-emails/08-siteadmin-scim-desktop.png) |
| S-06 | Not evidenced | Not evidenced | Not evidenced | en, hi | No screenshot |
| S-07 | n/a | Evidenced | Not evidenced | Moodle core | [1440](../../../visual-evidence/2026-11-19/phase-c/vp_siteadmin/G2-adminsearch-footer-1440.png), [390](../../../visual-evidence/2026-11-19/phase-c/vp_siteadmin/G2-adminsearch-footer-390.png) |
| S-08 to S-10 | Not evidenced | Not evidenced | Not evidenced | en, hi | No screenshot |

### 3.10 Coverage gaps in the evidence

- **No recent screenshots** for: the assistant drawer (X-05), the legacy homepage (P-03), PWA files (P-11), learner paths and programs pages (L-13), calendar, talent, WhatsApp and PWA preferences (L-18 to L-20), the learner exam view, secure attempt and share requests (L-21, L-23, L-24), AI quiz and Skills AI (A-02, A-03), the external trainer view (ET-01), re-completion (AD-21), ratings, translation, marketplace, secure assessment administration and xAPI (AD-23 to AD-26), tenants and AI gateway (S-03, S-06), Microsoft 365, the style guide, the maintenance page and the secure quiz layout (S-08 to S-10).
- **Dark mode** is evidenced on 12 surfaces only (X-01, X-02, P-10, L-04, L-08, L-10, L-11, A-04, AD-11, AD-12, AD-14, C-01). No dark screenshot exists for the login page or the guest landing; both are behind `ux.darkMode.enabled`.
- **Loading states** are not evidenced anywhere.
- **Kannada and Marathi** have no screenshot in the recent folders; Swahili has one (AD-05).
- **UAT** screenshots exist for P-01 and P-02 (21 Oct, `2026-10-21/uat/`); the dashboard, blocks drawer and notification preferences (30 Oct, `2026-10-30/uat-my-check/`, L-01, L-10); notification preferences, the dashboard calendar and the Manage Users full-name search (11 Nov, `2026-11-11/uat-fixes-check/`, L-01, L-10, AD-02); and an issued test certificate (18 Nov, `2026-11-18/certificate-redesign/uat-issued-certificate-test-persona.png`, L-07). All other evidence is from the development environment, which runs later code with different data (a test import).

---

## 4. Claims and content register

Evidence status: **Proven** (the repository shows it is true today), **Needs evidence** (may be true or partly true; a named owner must supply evidence or the wording changes), **Remove** (false, unsupported or misleading; remove or reword before cutover). Claims C1 to C47 follow `_facts/70-ui-surfaces.md` section 8; C48 to C56 were added from a re-read of the page code for this pack. Text is abridged.

**Scope.** The register covers every page, string and file a visitor, learner or administrator can read. Two text sets are in scope for content review but hold no separate rows:

- **E-mail template copy** (20 registry templates in `moodle-enhancement/local/sentientia_emails/templates/`, sent to learners and managers). A keyword scan for capability, compliance and service-level claims found none; one template asks the learner to finish a course "to stay compliant", which is an instruction, not a claim. Review tone and plain language through the previews (AD-17).
- **Certificate PDF wording.** Only the redesigned UAT test template is in scope (L-07, AD-16). Imported legacy templates are listed as an issue (UI-02), not as claims.

### 4.1 Guest landing (`theme/sentientia/layout/frontpage.php`, visible on UAT)

| # | Claim text | Location | Evidence status | Observation and proposed action |
|---|---|---|---|---|
| C1 | "RBI Compliant" (trust bar) | line 454 | **Remove** | No RBI compliance mapping in the repository. Compliance team to confirm or remove |
| C2 | "DPDP 2023 Ready" | line 455 | Needs evidence | DPDP design work exists (ADR-035, `local_sentientia_privacy` Available (UAT)); no external audit. Reword "Designed to support DPDP Act 2023" |
| C3 | "SCORM & xAPI" | line 456 | Needs evidence | SCORM 1.2 is core; xAPI statements are Built (behind a feature flag, off by default). Keep "SCORM"; add xAPI when the flag is on |
| C4 | "Multi-Tenant" | line 457 | Proven | 3 tenants (`local/sentientia_platform/classes/tenant.php`) |
| C5 | "24/7 Support" | line 458 | **Remove** | Contact page states business days, 10:00 to 18:00 IST |
| C6 | Hero: "Build a skilled, compliant workforce at scale"; "enterprise learning and development platform for the financial services industry" | lines 423 to 424 | Needs evidence | Positioning statement. "At scale" is unproven: no load or capacity test has been run (`_facts/00-FACTS-INDEX.md` section 3). Marketing to reword, or the QA Architect to supply a load test |
| C7 | Hero counters "N+ Courses", "N+ Learners" | lines 432 to 445 | **Remove** (until cutover) | Counts read the Public tenant on the current database: test data before cutover |
| C8 | "Purpose-built capabilities that set airpay academy apart from generic LMS platforms" | line 470 | **Remove** (or reword) | Comparative claim without a comparator |
| C9 | "RBI-mandated training with automated tracking, deadline escalation, and audit-ready export" | line 476 | Needs evidence | Tracking and export are Available (UAT); "RBI-mandated" needs the Compliance team |
| C10 | "Isolated environments for each business unit with white-label branding, separate user pools, and independent reporting" | line 481 | Needs evidence | Isolation is path-based inside one site (ADR-031); reword "separate user pools" |
| C11 | "Progressive web app with offline support" | line 486 | **Remove** (until the flag is on) | PWA is Built (behind a feature flag, off by default) |
| C12 | "Role-based analytics, manager team views, compliance RAG matrices, and real-time completion tracking dashboards" | line 491 | Needs evidence | All built; analytics figures refresh hourly, so "real-time" is not accurate |
| C13 | Three testimonials with names, roles and star ratings | lines 621 to 655 | **Remove** | No source or consent record in the repository |
| C14 | FAQ: "AI-powered learning" | line 671 | **Remove** (or requalify) | AI features run in mock mode; live use needs budget, key and flags |
| C15 | FAQ: "RBI-mandated courses on AML, KYC, POSH, IT and information security, DPDP Act 2023, financial fraud prevention" | line 679 | Needs evidence | Course list to be checked by the Head of Learning & Development |
| C16 | FAQ: "online assessments with proctoring" | line 683 | Needs evidence | Core quizzes and online exams are Available (UAT); secure assessment mode is Built (behind a feature flag, off by default) |
| C17 | FAQ: dedicated tenant with logo, colours, **custom domain**, user pool | line 687 | Needs evidence | Branding and tenants built; per-customer custom domain not evidenced (DevOps lead) |
| C18 | FAQ: HRMS (Keka, Darwinbox), SSO via SAML/OAuth2, REST APIs, "webhook notifications for events like course completions and compliance deadlines", CSV | line 691 | Needs evidence | Keka client and CSV importer exist; OAuth2 guide exists; SAML not evidenced; SSO and API flags OFF. The outbound webhook events are `course.completed` and `certificate.issued` only (`local/sentientia_api/classes/webhooks/subscription.php` line 25): there is no compliance-deadline event, so remove "compliance deadlines" |
| C19 | FAQ: digital certificates "automatically issued upon course completion", with verification codes, PDF, LinkedIn, public verification URL, branding | line 695 | Needs evidence | Codes, PDF, LinkedIn and the verification page exist (`tool_certificate`, gallery, P-10). Automatic issue on completion is not on UAT or in the committed code (UI-01). Reword "automatically issued" until the issuer is on UAT |
| C20 | FAQ: pricing "free or per-course; Enterprise plans ... annual billing, priority support" | line 699 | Needs evidence | Per-course pricing built; enterprise terms not defined (Finance) |
| C21 | "Join N+ professionals across financial services who are already learning" | line 711 | **Remove** (until cutover) | Count is test data |
| C22 | "Made in India" footer badge | footer | Needs evidence | Image asset `theme/sentientia/pix/brand/made-in-india.jpg`; usage rights and intent to be confirmed by Marketing |
| C48 | Hero: "train, track, and certify your teams across every location" | line 424 | Needs evidence | Certification on completion is not automatic on UAT (UI-01). Owner: Marketing lead |
| C49 | Mobile-Ready card: "SCORM content that works on any device" | line 486 | Needs evidence | SCORM resume is evidenced on development only; no device matrix test exists. Owner: QA Architect. (The same card's PWA claim is C11) |
| C50 | FAQ: audiences include "enterprise organisations that need to train and certify distributed teams at scale" and "external partners such as business correspondents, field agents, and channel partners" | line 675 | Needs evidence | Three tenants exist (Airpay, Public, ZEEA); no partner tenant. External trainer accounts are Built (behind a feature flag, off by default). "At scale" as C6. Owner: Head of Learning & Development |
| C51 | FAQ: compliance training with "escalation emails to managers" | line 679 | Needs evidence | Escalation logic exists in the e-mail rules and the compliance engine; the manager copy of a completion is behind a default-OFF flag; no end-to-end evidence of an escalation e-mail. Owner: Head of Learning & Development |
| C52 | FAQ: content formats include "H5P interactive modules ... instructor-led virtual classrooms ... authored in-platform, or sourced from third-party content providers" | line 683 | Needs evidence | H5P is a Moodle core activity; classrooms are instructor-led sessions with attendance, with no video-conference integration evidenced; in-platform authoring and the content marketplace are Built (behind a feature flag, off by default). Reword until those flags are on. Owner: Marketing lead |
| C53 | Call to action "Get Started Free" | line 713 | Needs evidence | Links to the Sentientia sign-up (P-06), which an admin setting enables (state on UAT to be confirmed); "free" holds only while free courses exist for the Public tenant. Owner: Head of Learning & Development |
| C54 | Call to action "Request Enterprise Demo" (pre-filled e-mail to the shared academy mailbox) | line 714 | Needs evidence | Enterprise terms are not defined (C20); mailbox staffing as C40. Owner: Head of Learning & Development |

### 4.2 Legacy homepage (`moodle-enhancement/local/sentientia_pages/homepage.php`)

| # | Claim text | Evidence status | Observation and proposed action |
|---|---|---|---|
| C23 | "RBI Compliant", "DPDP 2023", "ISO Certified", "24/7 Support" | **Remove** | "ISO Certified" has no evidence; retire the page |
| C24 | "Join thousands of learners building skills for the digital economy" | **Remove** | Unsupported |
| C25 | "A comprehensive learning platform for employability, business training, and financial education" | **Remove** (with the page) | Retire with the page |
| C26 | "Get Started Free" links to the core sign-up page | **Remove** | Functional defect: the Sentientia sign-up is `/local/sentientia_users/signup.php` |

### 4.3 Login page (`theme/sentientia/lang/en/theme_sentientia.php`, lines 453 to 470)

| # | Claim text | Evidence status | Observation and proposed action |
|---|---|---|---|
| C27 | "Upskill. Get certified. Get hired." | Needs evidence | "Get hired" depends on talent features that are Built (behind a feature flag, off by default); also a poor fit for employees (CS1) |
| C28 | "Job-ready courses in finance, payments, tech & business, built by the people running real platforms" | Needs evidence | Depends on the catalogue; Marketing to source |
| C29 | "Hiring assessments ... get shortlisted by hiring partners across the industry" | **Remove** | No hiring partners recorded |
| C30 | "Every completion ships with a tamper-proof certificate" | **Remove** (or reword) | On UAT no certificate is issued automatically on course completion (UI-01); "tamper-proof" needs the Security team |
| C31 | Counters: learners, courses, certificates | **Remove** (until cutover) | Test data |
| C55 | Sign-in form subtitle: "Pick up your courses, take assessments, and earn your next certificate." (line 464) | Needs evidence | Certificates are not issued automatically on completion on UAT (UI-01); acceptable once the issuer is deployed. Owner: Head of Learning & Development |

### 4.4 Static and legal pages (`moodle-enhancement/local/sentientia_pages/pages/*.html`)

| # | Page | Claim text | Evidence status | Observation and proposed action |
|---|---|---|---|---|
| C32 | dpdp | Badge "DPDP Act 2023 Compliant" | **Remove** (reword) | No audit yet; use "designed to support" |
| C33 | dpdp | TLS 1.2+ and encryption at rest; regular security audits; "Secure hosting on AWS infrastructure within India" | Needs evidence | Target infrastructure not provisioned (DevOps lead, Security) |
| C34 | dpdp, privacy | "72 hours for data access requests, 30 days for deletion requests" | Needs evidence | No service-level tooling evidenced (Head of Learning & Development, Legal) |
| C35 | dpdp | Retention: learning records account plus 7 years; account data deleted within 90 days; certificates kept, anonymised | Needs evidence | No enforcing setting for 7 years; check against the DPDP pack |
| C36 | dpdp | Right to erasure "available to external/public users only" | Needs evidence | Policy choice; Legal to confirm |
| C37 | help | "most assessments allow unlimited retakes and your highest score is recorded" | Needs evidence | A course setting, not a platform rule; reword |
| C38 | help | "airpay academy is a progressive web app ... Add to Home Screen ... offline support" | **Remove** (until the flag is on) | PWA Built (behind a feature flag, off by default: `sentientia.pwa.enabled`) |
| C39 | help | "Most courses for registered users are free. Some premium courses may require payment through the Airpay payment gateway" | Needs evidence | Storefront checkout Built (behind a feature flag, off by default: `sentientia.catalog.storefront_checkout.enabled`) |
| C40 | contact | Response within 24 hours on business days, Monday to Friday, 10:00 to 18:00 IST; shared academy mailbox; Mumbai address | Needs evidence | An operational service commitment with no evidence that the mailbox is staffed to meet it. Owner: Head of Learning & Development confirms staffing |
| C41 | privacy (static) vs sign-up privacy text | Two different privacy texts: one says data is stored in India; the other cites EU GDPR and DPDP, consent, objection and portability | **Remove** (one of them) | Keep one controlled policy text (Legal) |
| C42 | `local_sentientia_users` admin help, lines 187 and 191 | "built-in GDPR-compliant default" | **Remove** | Compliance claim in an admin string |

### 4.5 Other customer-visible strings

| # | Location | Claim text | Evidence status | Observation |
|---|---|---|---|---|
| C43 | `local_sentientia_pwa` language file, line 22 | Install to home screen; push notifications appear even when the browser is closed | Needs evidence | Shown only when the flag is on. Untested: push needs VAPID (Voluntary Application Server Identification) keys, and no push test has been run. Owner: platform engineer |
| C44 | same file, line 31 | "We never share the message content with the vendor, only an encrypted blob" | Needs evidence | Standard Web Push behaviour; Security to confirm wording |
| C45 | `local_sentientia_proctoring` `secure_banner` | Copy, paste and print blocked; leaving fullscreen is recorded | Needs evidence | Shown only when the flag is on. Matches the design, but secure assessment mode has not been verified (no PHPUnit, no browser test; UI-13). Verify B2 end to end before the flag is turned on |
| C46 | Theme description `choosereadme` | "standalone theme with full design system, per-customer branding, and responsive mobile experience" | Proven | Admin-facing |
| C47 | Footer notice | Private and confidential to Airpay (per-customer string) | Proven | Public learners also see it (UI-25) |
| C56 | Static web-app manifest `theme/sentientia/pix/brand/manifest.json`, linked from the guest landing whatever the PWA flag (`frontpage.php` line 104) | "Airpay Academy — L&D Operating System for employability, business training, and financial education" | Needs evidence | Product description shown when a browser offers to install the landing; a further name variant (M16, CS2) and the "employability" positioning removed elsewhere (C25). Owner: Marketing lead; keep one manifest |

### 4.6 Register totals

| Evidence status | Count | Claims |
|---|---|---|
| Proven | 3 | C4, C46, C47 |
| Needs evidence | 34 | C2, C3, C6, C9, C10, C12, C15, C16, C17, C18, C19, C20, C22, C27, C28, C33, C34, C35, C36, C37, C39, C40, C43, C44, C45, C48, C49, C50, C51, C52, C53, C54, C55, C56 |
| Remove | 19 | C1, C5, C7, C8, C11, C13, C14, C21, C23, C24, C25, C26, C29, C30, C31, C32, C38, C41, C42 |
| Total | 56 | |

The marketing pack keeps the master claims register; this register is its UI-located input.

---

## 5. Known UI issues and open items

Status: **Open** (no fix); **Fix built (development)** (code exists, not on UAT); **Workaround** (avoidable in use); **Decision needed**. "Development" evidence was taken on the local environment. Sources: the surface walk of 18 Nov, the fix-verification READMEs of 18 and 19 Nov, the follow-up READMEs of 19 Nov, the 20 Nov evidence folders, and `moodle-enhancement/PROJECT-STATE.md` (20 Nov entry). Issue IDs run in order by subsection: 5.1 holds UI-01 to UI-15, 5.2 UI-16 to UI-22, 5.3 UI-23 to UI-28 and 5.4 UI-29 and UI-30.

### 5.1 Functional

| # | Issue | Surfaces | Status | Notes |
|---|---|---|---|---|
| UI-01 | No course certificate is issued automatically on course completion (UAT and the committed code). The legacy platform issued about 320 a month | L-04, L-07 | Fix built (development) | Certificate issuer and course map in `local_sentientia_courses` (uncommitted, 20 Nov); evidence `2026-11-20/session/G-CERT-ISSUER/` |
| UI-02 | Imported certificate templates print blank or without the course name (an element type that no installed plugin provides; one template has no elements) | AD-16, L-07 | Open | The UAT test template was redesigned (18 Nov) |
| UI-03 | The Public learner has two different carts (catalogue session cart and order cart) | L-06 | Open, decision needed | Product design change |
| UI-04 | Browse library lists about 207 courses on one page; the paging fix was reverted because the paging bar sat under a notice; the table clips at 390 px and the title repeats | AD-05 | Open | |
| UI-05 | The classroom page shows a "no permission" dialog for the internal trainer (the organisation filter calls a user-view web service) | T-02 | Open, decision needed | Capability decision |
| UI-06 | Notifications page: the heading stays 30 px; in dark mode unread rows are hard to read | L-10 | Open | |
| UI-07 | The assistant button covers the footer's legal line on phones; the padding fix loses to a legacy `!important` rule | X-04, X-05 | Open | |
| UI-08 | JavaScript errors after dialogs open on users, programs, organisation and certificate templates pages; not reproduced on a clean load | AD-02, AD-10, AD-13, AD-16 | Open (investigate) | A related modal-form error is fixed by the `form.js` core patch (ADR-044, Built (verified on development)) |
| UI-09 | On UAT, editing a seeded e-mail rule resets its channel, audience, trigger and conditions; the Enabled box cannot switch a rule off; 5 rule types are missing | AD-17 | Fix built (development) | `local_sentientia_emails` 1.4.3 (version 2026112000, uncommitted 20 Nov work) keeps rule type, channel, audience, trigger and conditions on edit, lets the Enabled box switch a rule off and adds course_completed, course_incomplete, course_enrolled, learning_path_enrolled and manager_course_completed. Built (verified on development); PHPUnit not yet run; not on UAT. Evidence `2026-11-20/session/G-RULES/`. Until it is deployed, do not re-save seeded rules on UAT |
| UI-10 | The Programs sidebar link for L&D admins is not on UAT yet (theme 1.0.73-beta is on development only). Once deployed, the link shows only if the L&D admin role is assigned at system level | X-01, AD-10 | Fix built (development) | Check the UAT role assignment before deploying |
| UI-11 | My evaluations is reachable only by URL; a pending row is not clickable; an expired assignment still opens an answerable form | L-12 | Open (server-side context check Fix built (development)) | |
| UI-12 | Two guest landing pages exist; the legacy one is reachable by URL and redirect | P-02, P-03 | Open, decision needed | |
| UI-13 | Secure assessment mode end to end (start, monitoring across pages) is not verified: no PHPUnit and no browser run of B1 and B2 | L-23, AD-25 | Built (behind a feature flag, off by default) | Keep OFF until verified |
| UI-14 | "Enrol now, free" shows only when the one-click enrolment flag is on for the tenant | L-03 | Decision needed | Flag state on UAT to be confirmed |
| UI-15 | A compliance page says "Run the snapshot task first" when the snapshot has not run | M-07, AD-12 | Workaround | Operational task on UAT |

### 5.2 Layout, responsive and visual

| # | Issue | Surfaces | Status |
|---|---|---|---|
| UI-16 | Analytics: funnel bars drawn unequal; heatmap bars thin at 390 px; Risk Signals column clipped and Course Effectiveness headers run together at 390 px; name cells ellipsised at 1440 px | AD-11 | Open |
| UI-17 | Authoring drafts table has no responsive wrapper at 390 px; the review page prints a raw status and the word "mock" | A-01 | Open |
| UI-18 | Cosmetic: classroom filter row clipped at 390 px; bottom bar covers the drawer's user row; organisation card chevron wraps at 390 px; Featured widget buttons touch; seventh course-header icon wraps; learning-path Organisation select wider than its card at 390 px; e-mail rules Actions column overflows at 1440 px; mobile sidebar brand text underlined | Various | Open |
| UI-19 | Catalogue thumbnails print course shortnames; storefront card pill reads "COURSE CATALOG"; "Popular picks" are hiring assessments and the fourth card is cut | L-02, P-04 | Open (partly data) |
| UI-20 | Manager period pill size; an e-mail address set at 9.6 px | M-04 | Open (9.6 px text is also an accessibility issue) |
| UI-21 | The logged-out certificate verification page has no header and no heading | P-10 | Open |
| UI-22 | The PWA plugin manifest uses a placeholder Moodle icon although brand icons exist in `pix/brand/favicon_io`; a second static manifest is linked from the landing (M16) | P-11, P-02 | Open (PWA plugin manifest behind `sentientia.pwa.enabled`, off by default; static manifest always loaded) |

### 5.3 Content and language

| # | Issue | Surfaces | Status |
|---|---|---|---|
| UI-23 | English left in the Hindi interface: cart page; "General" and "Free" fallbacks; dashboard "Toggle theme" and topbar "Toggle dark mode" titles; "Toggle sidebar" label; catalogue aria labels (sort tabs, "Remove filter:", difficulty, bookmark, "Results for"); course-header titles; manager performance and member stat cards; user filters "All"; assistant panel; Programs title and KPI labels; learning-path Courses-tab headers | Various | Open |
| UI-24 | Double-escaped names show "&amp;" (learning-path course list, at-risk names, a thumbnail URL); compliance option labels use unescaped organisation names (referred to the security review) | AD-09, AD-11, AD-12 | Open |
| UI-25 | Public learners see the Airpay-only footer notice, two copyright lines and an English footnote | X-04 | Open |
| UI-26 | Swahili coverage is low in several plugins (the walk found compliance, analytics and platform strings mostly English) and the Sentientia language switcher is off by default (`ux.languageSwitcher.enabled`); a Moodle core user-menu language item or `?lang=` may serve meanwhile, to be confirmed on UAT by the platform engineer | ZEEA surfaces | Open, decision needed |
| UI-27 | Static legal pages English only (CS6); two privacy texts (C41) | P-07, P-09 | Open |
| UI-28 | "1 courses" and similar plural defects | L-02 | Open |

### 5.4 Data, privacy optics and environment

| # | Issue | Status |
|---|---|---|
| UI-29 | UAT holds synthetic test personas and sample courses. The development copy holds a test import of a production backup, so its admin and manager screens (at-risk learners, compliance matrix) and the screenshots taken there can show real names; those shots are masked. On the dashboard mini leaderboard other people show as "First L." (Available (UAT)); the standalone leaderboard page still shows full names | Decision needed (standalone leaderboard); confidentiality rule for evidence screenshots in section 9 |
| UI-30 | Pages on the development machine take 15 to 140 s; UAT page times have not been measured | Open (measure on UAT) |

### 5.5 Fixed and on UAT since 18 Nov (for regression checking)

Deployed to UAT on 19 Nov (walk fixes) and 19 Nov afternoon (quick wins and certificate verification on arrival): sidebar Catalog by tenant; enrol redirect to the catalogue detail page; dashboard tile and chart overflow; "Active (30 days)" label; no cross-tenant recommendation; Messages link only when messaging is on; empty band under the top bar removed; one footer and bottom bar on `/my/`; compliance pager, department column, scorecard tree and empty states; analytics heatmap source and "&amp;" fix; "Never" instead of "999d ago"; profile role chip, avatar and badges; My Skills summary; Font Awesome icons; visible page titles; bell unread badge; learning-path status pills; certificate templates banner; trainer "Courses you teach"; trainers no longer see the tenant compliance matrix; e-mail previews; certificate verification legend and "Not verified" explanation; ROI card figures (flag OFF); skills page spacing; modal form labels. Evidence: `2026-11-18/walk-fixes/`, `2026-11-19/phase-c/`, `2026-11-19/quick-wins/`.

---

## 6. Accessibility status

### 6.1 What was done

| Item | Result | Source |
|---|---|---|
| Target standard recorded | WCAG 2.1 AA | `moodle-enhancement/PHASE-H-A11Y-AUDIT.md` |
| Automated audit, 6 May 2026 (axe-core, WCAG 2.0 and 2.1 A and AA rules) on dashboard, manage users and catalogue, two roles | 2 critical issues found and fixed (`aria-allowed-attr`, `select-name`); 5 serious (all colour contrast) and 11 moderate (heading and landmark structure) left; the audit's own verdict was "not production-ready" until contrast was fixed | `moodle-enhancement/PHASE-H-A11Y-AXE-RESULTS.md` |
| Contrast fixes since | Secondary text, success and warning tokens darkened; dark-mode chips and muted text raised; one 1.06:1 failure fixed | `_tokens.scss`, `_tokens-dark.scss` |
| Focus | 3 px focus ring with 2 px offset on `:focus-visible` for every element; 24 SCSS files use `focus-visible` | `_tokens.scss` lines 312 to 321 |
| Reduced motion | All durations collapse to 0 ms; stylelint rule enforces motion tokens in `_surface-*.scss`; 12 SCSS files reference the media query | `_tokens.scss`; `theme/sentientia/.stylelintrc.json` |
| Target size | Minimum tap target token 44 px; bottom bar and edit-mode tabs at least 44 px | `_tokens.scss`; theme release notes 1.0.66 |
| Structure | Status shown with text as well as colour; modal focus trap and Escape; `aria-sort` on sortable tables | `PHASE-H-A11Y-AUDIT.md` items A11y-05 to A11y-07 |
| Automated smoke test | `tests/playwright/a11y-smoke.spec.ts` asserts no serious or critical axe violations (WCAG 2.0 and 2.1 tags) on `/my/`, `/my/courses.php` and `/user/profile.php` per persona; it skips when test-account variables are not set, and the Playwright lane is advisory in CI | `tests/playwright/a11y-smoke.spec.ts`; `.github/workflows/ci.yml` |
| Skip link | Moodle core registers a "Skip to main content" link to `#maincontent` in `standard_top_of_body_html()`, which the theme templates call. On the guest landing the main-content region is inside a `display:none` block, so the skip link target is hidden there | `theme/sentientia/layout/frontpage.php` line 391; core `page_requirements_manager` |

### 6.2 What was not tested

- No WCAG 2.2 AA assessment. The automated rules cover 2.0 and 2.1 only.
- No re-run of the full axe audit after the contrast fixes is recorded (owner: QA Architect).
- No manual keyboard-only walk of any journey.
- No screen-reader pass. An NVDA check of an admin table is listed in the audit plan; its result is to be confirmed.
- No zoom or reflow test at 200% and 400% (WCAG 1.4.4, 1.4.10).
- No test of the guest landing page, login, sign-up, static pages, certificate verification, cart and checkout, course player or SCORM content.
- No test of the Hindi, Kannada, Marathi or Swahili interfaces with assistive technology.
- Colour use in the 1,098 hard-coded literals (M3) has not been checked page by page.

### 6.3 Known accessibility defects from code and evidence

| # | Defect | WCAG 2.2 criterion | Source |
|---|---|---|---|
| A1 | Brand accent `#1985DD` on white 3.86:1; white on orange `#ed692b` 3.15:1 (normal text) | 1.4.3 Contrast (Minimum) | Appendix D |
| A2 | Hard-coded `#8896a6` (3.02:1) and `#16a34a` (3.30:1) still used in partials | 1.4.3 | M3 |
| A3 | Primary blue text on dark surfaces would be 2.41:1 if not overridden | 1.4.3 | M13 (to be checked) |
| A4 | Skip link target hidden on the guest landing | 2.4.1 Bypass Blocks | Section 6.1 |
| A5 | Pages without a visible heading (fixed on shell pages; logged-out verify page still has none) | 1.3.1, 2.4.6 | UI-21 |
| A6 | 9.6 px e-mail text on manager pages | 1.4.4 Resize Text (risk) | UI-20 |
| A7 | Topbar dark-mode button and some aria labels in English under the Hindi interface | 3.1.2 Language of Parts; 4.1.2 Name, Role, Value | UI-23 |
| A8 | Unread notification rows in dark mode hard to read | 1.4.3 | UI-06 |
| A9 | Sign-up uses reCAPTCHA v2 when configured; an accessible alternative is not documented | 1.1.1 Non-text Content | `local_sentientia_users/classes/form/signup_form.php` lines 119 to 122 |

### 6.4 Proposed WCAG 2.2 AA review focus

| WCAG 2.2 addition or risk area | What to check here |
|---|---|
| 2.4.11 Focus Not Obscured (Minimum) | Focused items under the 64 px sticky top bar, the 64 px mobile bottom bar and the assistant button |
| 2.5.7 Dragging Movements | Any drag-to-reorder (learning paths, course editing) has a single-pointer alternative |
| 2.5.8 Target Size (Minimum) | Table-row controls at 32 px and the 40 px "cozy" size meet 24 px with spacing |
| 3.2.6 Consistent Help | Help and contact links sit in the same place on every page (login footer, shell footer, static pages) |
| 3.3.7 Redundant Entry | Checkout, sign-up and multi-step forms (onboarding wizard) |
| 3.3.8 Accessible Authentication (Minimum) | Login allows password managers and paste; no cognitive test |
| Existing 2.1 risks | Contrast (A1 to A3, A8), headings and landmarks (11 moderate findings in May), reflow at 320 CSS px |

---

## 7. Per-surface review sign-off

Mark each cell "OK", "Changes needed" or "Not reviewed", and add comments. Surface IDs refer to section 3.

| Surface | Content OK | Claims OK | Brand OK | Functionality OK | Comments |
|---|---|---|---|---|---|
| X-01 Sidebar | | | | | |
| X-02 Top bar | | | | | |
| X-03 Mobile bottom bar | | | | | |
| X-04 Footer | | | | | |
| X-05 Assistant drawer | | | | | |
| X-06 Role switcher | | | | | |
| X-07 Onboarding wizard | | | | | |
| P-01 Login | | | | | |
| P-02 Guest landing | | | | | |
| P-03 Legacy homepage | | | | | |
| P-04 Public storefront | | | | | |
| P-05 Course detail (guest) | | | | | |
| P-06 Self-registration | | | | | |
| P-07 Privacy policy (sign-up) | | | | | |
| P-08 Terms of use (sign-up) | | | | | |
| P-09 Static pages | | | | | |
| P-10 Certificate verification | | | | | |
| P-11 PWA files and offline page | | | | | |
| L-01 Learner dashboard | | | | | |
| L-02 Catalogue | | | | | |
| L-03 Course detail | | | | | |
| L-04 Course page | | | | | |
| L-05 My courses | | | | | |
| L-06 Cart and orders | | | | | |
| L-07 Certificates gallery | | | | | |
| L-08 Profile | | | | | |
| L-09 My skills | | | | | |
| L-10 Notifications | | | | | |
| L-11 My requests | | | | | |
| L-12 My evaluations | | | | | |
| L-13 Learning paths, programs (learner) | | | | | |
| L-14 Classrooms and QR scan | | | | | |
| L-15 Leaderboards and challenges | | | | | |
| L-16 Live session (join) | | | | | |
| L-17 My privacy and data | | | | | |
| L-18 to L-20 Calendar, talent, channel preferences | | | | | |
| L-21 Online exam (learner view) | | | | | |
| L-22 Course player and SCORM | | | | | |
| L-23 Secure assessment attempt | | | | | |
| L-24 My share requests | | | | | |
| M-01 Manager dashboard | | | | | |
| M-02 My Team | | | | | |
| M-03 Team member | | | | | |
| M-04 Team performance | | | | | |
| M-05 Allocations | | | | | |
| M-06 Approvals | | | | | |
| M-07 Team compliance | | | | | |
| M-08 Nudge | | | | | |
| T-01 Trainer dashboard | | | | | |
| T-02 Classrooms (trainer) | | | | | |
| T-03 Live sessions (run) | | | | | |
| T-04 QR attendance projector | | | | | |
| T-05 Evaluations (trainer) | | | | | |
| A-01 Authoring studio | | | | | |
| A-02 AI quiz | | | | | |
| A-03 Skills AI | | | | | |
| A-04 Course editing | | | | | |
| ET-01 External trainer view | | | | | |
| AD-01 Admin dashboard | | | | | |
| AD-02 Manage users | | | | | |
| AD-03 External trainers | | | | | |
| AD-04 Manage courses | | | | | |
| AD-05 Browse library | | | | | |
| AD-06 Featured courses | | | | | |
| AD-07 Online exams | | | | | |
| AD-08 Classrooms (admin) | | | | | |
| AD-09 Learning paths | | | | | |
| AD-10 Programs | | | | | |
| AD-11 Analytics | | | | | |
| AD-12 Compliance report | | | | | |
| AD-13 Organisation | | | | | |
| AD-14 Skills administration | | | | | |
| AD-15 Evaluations (admin) | | | | | |
| AD-16 Certificate templates | | | | | |
| AD-17 E-mail templates and rules | | | | | |
| AD-18 Notifications log | | | | | |
| AD-19 Reports | | | | | |
| AD-20 Roles | | | | | |
| AD-21 Re-completion | | | | | |
| AD-22 Orders and prices | | | | | |
| AD-23 Ratings and reviews | | | | | |
| AD-24 Translation, marketplace | | | | | |
| AD-25 Secure assessment administration and review | | | | | |
| AD-26 xAPI settings | | | | | |
| C-01 Compliance report (tenant) | | | | | |
| C-02 Analytics (compliance) | | | | | |
| C-03 Compliance dashboard | | | | | |
| S-01 Site admin dashboard | | | | | |
| S-02 Switchboard | | | | | |
| S-03 Tenants and admin log | | | | | |
| S-04 Privacy requests and DPDP | | | | | |
| S-05 API, SCIM, webhooks | | | | | |
| S-06 AI gateway settings | | | | | |
| S-07 Admin-layout pages | | | | | |
| S-08 Microsoft 365 administration | | | | | |
| S-09 Platform style guide | | | | | |
| S-10 Maintenance page and secure quiz layout | | | | | |

Overall verdict (tick one): Approved / Approved with changes / Not approved. Signature and date: ______

---

## 8. Questions for the reviewer

| # | Question | Context |
|---|---|---|
| Q1 | Which surfaces must pass your review before UAT sign-off, and which can follow after cutover? | Section 7 has about 100 rows |
| Q2 | Phase 2 of the brand adoption is partly built and on UAT: the "a" monogram watermark and sparing orange accent on the login and landing heroes, and the card palette remapped to brand secondaries. Do these elements meet the Brand Book, and what else (corner or diagonal accents, more white space, other surfaces) is needed before cutover? | M18 |
| Q3 | Page background: keep `#f2f4fb` or move to a blue-tinted off-white such as `#eef4fb`? | M7 |
| Q4 | Sidebar: keep the neutral dark slate, or move to deep blue? | M4 |
| Q5 | The Brand Book extract describes logo colour variants for dark backgrounds (`brandbook-text.txt` about lines 745 to 805; logo lockups on `pages/p07.png`). Please confirm the dark-background variant against the original file. Is the CSS-inverted white logo acceptable until the official file is in the theme? | M10 |
| Q6 | How should the name be written in running text: "airpay academy", "Airpay Academy", or per customer string? And should "Sentientia" appear to learners at all (PWA name, offline page)? | M11, CS2 |
| Q7 | Which Montserrat weights (including Light) and which other families from the Brand Book apply to web UI? | M9 |
| Q8 | Where may bright blue and orange appear given their contrast (large text, icons, borders only)? | M5, A1 |
| Q9 | Should the login hero speak to employees, consumers or both, given it is shared by all three tenants? | CS1, C27 to C29 |
| Q10 | Do you agree with the "Remove" calls in section 4, in particular the testimonials and the database-driven counters? | Section 4 |
| Q11 | Should the standalone leaderboard page mask other people's names the way the dashboard does? | UI-29 |
| Q12 | Is a single cart (UI-03) a requirement for cutover? | UI-03 |
| Q13 | Is English-only acceptable for legal and static pages at cutover, or must Hindi (and Swahili for ZEEA) follow? | UI-27, CS6 |
| Q14 | Which feature-flagged surfaces do you want switched on for review on UAT (dark mode, including the guest landing's dark toggle, language switcher, PWA, leaderboards, live sessions, authoring, external trainers)? | Section 9 |
| Q15 | Who owns the skip-link and screen-reader checks: you or the QA Architect? | Section 6 |
| Q16 | No plain-language content-style guide is adopted for the product. Which one should the content review use (for example an existing Airpay house style, or a published plain-language standard)? | Section 1.6 |

---

## 9. How to access a review environment

### 9.1 Environments

| Environment | Use for this review | Notes |
|---|---|---|
| UAT, `https://academy2.airpay.ninja` | Primary. Moodle 5.3, theme 1.0.72-beta, code at commit `af6e65c0e` | Network access requirements (for example a VPN) **to be confirmed** by the DevOps lead |
| Screenshot evidence in the repository | Flag-gated surfaces and fixes not yet on UAT | `moodle-enhancement/docs/visual-evidence/`; development environment, Moodle 5.1.3+ |
| Development machines | Not offered for this review | Shared and slow; used for verification only |

UAT holds synthetic test personas and sample courses; it is a fresh install (Stage A), not a copy of production data. The development copy, and the screenshots in the evidence folders taken on it, hold a test import of a production backup and can show real names; those shots are masked. Treat the evidence screenshots as confidential: do not share them outside Airpay, and keep names blurred in any screenshot you reuse. Treat UAT screenshots as internal material as well.

### 9.2 Accounts by role

UAT has one seeded test account per role below, except the external trainer. Credentials are issued individually by the document owner (Head of Learning & Development) through a secure channel. They are not in this document or in the repository. Never use a real employee's account.

| Role | Tenant | What to review with it |
|---|---|---|
| Guest (no account) | n/a | P-01 to P-11 |
| Learner | Airpay | X-01 to X-07, L-01 to L-24; has completed test courses and an issued certificate |
| Learner with Hindi interface | Airpay | Hindi review of learner surfaces |
| Learner | Public | Storefront, cart, consumer profile |
| Learner | ZEEA | ZEEA catalogue, Swahili (if the core pack is installed) |
| Line manager | Airpay | M-01 to M-08 |
| Internal trainer | Airpay | T-01 to T-05 |
| External trainer | Airpay | ET-01; needs `sentientia.users.external_trainer.enabled` ON and a test account, which does not exist on UAT yet (to be created by the Head of Learning & Development if the flag is switched on for the review) |
| Course author | Airpay | A-01 to A-04 |
| L&D administrator | Airpay | AD-01 to AD-26 |
| Tenant administrator | ZEEA | Tenant isolation as seen in the interface |
| Compliance officer | Airpay | C-01 to C-03 |
| Site administrator | All | S-01 to S-10; issued only if needed |

### 9.3 Feature flags for the review

Flag-gated surfaces are invisible until their flag is on. Flags are changed on UAT only by the Head of Learning & Development, from the Switchboard. To review them, list the flags you need (Q14). The most relevant: `ux.darkMode.enabled` (signed-in pages and the guest landing), `ux.languageSwitcher.enabled`, `sentientia.pwa.enabled`, `sentientia.leaderboards.enabled`, `live.enabled`, `sentientia.authoring.enabled`, `sentientia.learningpath.learner_paths.enabled`, `sentientia.programs.learner.enabled`, `sentientia.catalog.free_oneclick_enrol.enabled`, `sentientia.analytics.roi.enabled`, `sentientia.users.external_trainer.enabled`.

### 9.4 Practical notes

- Warm the cache by opening each page once before judging speed.
- Review at 1440 px and 390 px, and at 590 px (the primary mobile breakpoint).
- Use the user guides in `moodle-enhancement/docs/user-guides/` (learner, public learner, manager, course author, tenant admin, compliance officer, site admin) for the intended journeys. Some guide sections name pages that were never built; note any you find.
- Record findings against the surface IDs in section 3 so they can be traced to code.

---

## 10. Asks and next steps

| # | Ask | Form | By when |
|---|---|---|---|
| 1 | Review the surfaces in section 3 on UAT and against the evidence | Section 7 table, filled in | Proposed: Friday 11 December 2026 (three weeks from issue), and in any case before UAT sign-off |
| 2 | Answer Q1 to Q16 | Comments in this document or a reply memo | Proposed: Friday 27 November 2026 (Q1, Q2 and Q14 first, because they set the review scope and the flags to switch on) |
| 3 | Confirm or overrule the claims calls in section 4 | Mark each "Agree" or "Disagree" with a reason | Proposed: Friday 4 December 2026, before the marketing pack is finalised |
| 4 | Agree the WCAG 2.2 AA test scope with the QA Architect | Short test plan using section 6.4 | Proposed: Friday 4 December 2026 |
| 5 | Name any surface that blocks cutover on UI/UX grounds | List with severity | With the sign-off (proposed 11 December 2026) |

All dates are proposals from the document owner and are to be confirmed with the reviewer when the review starts. If UAT sign-off is scheduled earlier, the review dates move ahead of it.

Next steps after your review:

1. The Head of Learning & Development decides the brand and claims questions and assigns changes.
2. Engineering fixes the agreed items behind the usual gates (feature flags for new features, visual evidence for every UI change).
3. The QA Architect re-runs the automated accessibility audit and the agreed 2.2 AA checks.
4. A second UAT walk confirms the fixes before the Stage B rehearsal.

---

## 11. Risks and open questions

| # | Risk or open question | Owner (role) | Proposed resolution |
|---|---|---|---|
| R1 | Unsupported compliance and service claims (C1, C5, C23, C32, C42) are published at cutover | Marketing lead with the Compliance team | Remove or reword before cutover; marketing claims register as the gate |
| R2 | Counters and testimonials present test data and unsourced quotes as real (C7, C13, C21, C24, C31) | Marketing lead | Remove until cutover; replace testimonials with consented ones |
| R3 | Brand accent and orange fail AA contrast for normal text | UI/UX Solution Architect | Usage rules (Q8); token guidance in the theme rules file |
| R4 | 1,098 hard-coded colour literals undermine per-customer branding and contrast fixes | Platform engineer | Phased sweep to tokens; add a stylelint colour-literal rule (for example `color-no-hex` scoped to the partials) and extend it progressively |
| R5 | No WCAG 2.2 AA assessment and no assistive-technology test | QA Architect | Section 6.4 plan |
| R6 | Course certificates are not issued automatically on UAT (UI-01) | Head of Learning & Development | Review and deploy the built issuer after its tests run |
| R7 | Flag states on UAT are unknown, so the review may miss or misjudge surfaces | Head of Learning & Development | Export the Switchboard state for the reviewer before the review starts |
| R8 | Kannada, Marathi and Swahili are 81% translated and AI-reviewed only; core packs on UAT unconfirmed | Head of Learning & Development | Native-speaker review; confirm the core packs |
| R9 | The original Brand Book file is not in the repository, so token checks rest on an extract | Head of Learning & Development | Provide the original file to the reviewer |
| R10 | Evidence screenshots from the development copy (a test import of a production backup) can show real names if masking is missed; UAT itself holds synthetic personas | Head of Learning & Development | Confidentiality rule in section 9.1; check masking before any screenshot leaves the repository; use UAT or masked shots for any external review |
| R11 | The command-palette flag defaults ON with no interface found | Platform engineer | Confirm and either build or set the default OFF |
| R12 | The 20 Nov evidence folders (`docs/visual-evidence/2026-11-20/session/`) have no README, against the visual-evidence rule, so the reviewer has no change notes for those shots | Platform engineer | Add the README before the review starts |
| R13 | The Brand Book adoption spec still lists Phase 2 as open although parts are built (M18), so the spec misleads later brand work | UI/UX Solution Architect with the platform engineer | Update the spec after the reviewer's answer to Q2 |

---

## Appendix A. Glossary

| Term | Meaning |
|---|---|
| Sentientia LMS (Sentientia) | The white-label, multi-tenant learning platform built by Airpay Payment Services on Moodle 5.3 LTS |
| LMS, LXP | Learning management system; learning experience platform |
| Airpay Academy | The Airpay deployment of Sentientia; customer zero |
| BizLMS | The legacy platform on Moodle 4.1.2 that runs www.airpay.academy today |
| UAT | User-acceptance test environment, academy2.airpay.ninja |
| Tenant | A top-level organisation inside a customer: Airpay (1), Public (77), ZEEA (177) |
| Persona | A role-based user archetype used for design and testing |
| Surface | A page or a persistent interface component that a persona uses |
| Feature flag | A registered on/off switch with a default, overridable per customer or tenant |
| Switchboard | The site-administrator page that shows and sets feature flags |
| Design token | A named design value (colour, size, timing) defined once and reused, here as a CSS custom property |
| App shell | Sidebar, top bar, mobile bottom bar and footer around every signed-in page |
| ADR | Architecture Decision Record in `moodle-enhancement/docs/adr/` |
| PWA | Progressive Web App: an installable web app with an offline page and push notifications |
| RAG | Red, amber, green status rating |
| KPI | Key performance indicator |
| ROI | Return on investment |
| YTD | Year to date |
| SSO, SAML, OAuth2 | Single sign-on and two of its protocols |
| SCIM | System for Cross-domain Identity Management |
| SCORM, xAPI | E-learning packaging standard; Experience API learning statements |
| HRMS | Human resources management system |
| GST | Goods and Services Tax (India) |
| DPDP | Digital Personal Data Protection Act 2023 (India) |
| RBI | Reserve Bank of India |
| WCAG | Web Content Accessibility Guidelines (W3C) |
| ARIA | Accessible Rich Internet Applications (W3C): attributes such as `aria-label` that give assistive technology names and roles |
| TLS | Transport Layer Security: encryption of data in transit |
| AML | Anti-money laundering |
| KYC | Know your customer |
| POSH | Prevention of sexual harassment (at the workplace) |
| iCal | The iCalendar file format for calendar subscriptions |
| VPN | Virtual private network |
| VAPID | Voluntary Application Server Identification: key pair used to send Web Push notifications |
| H5P | An open format for interactive HTML5 learning content, supported by Moodle core |
| AA | The middle WCAG conformance level |
| axe-core | An automated accessibility testing engine |
| NVDA | NonVisual Desktop Access, a screen reader for Windows |
| QR code | Quick Response code |
| FAB | Floating action button |
| SOP | Standard operating procedure |
| CTA | Call to action |
| BEM | Block, Element, Modifier: a CSS class naming convention |
| CSS | Cascading Style Sheets |
| SCSS | Sass syntax for CSS |
| IST | Indian Standard Time |

## Appendix B. References

### B.1 Repository files (paths from the repository root)

| Reference | Path |
|---|---|
| Fact base index and UI surfaces | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/00-FACTS-INDEX.md`, `70-ui-surfaces.md`, `20-flags-roles.md` |
| Tokens | `theme/sentientia/scss/moodle/_tokens.scss`, `_tokens-dark.scss`, `dark_mode.scss` |
| Shell styles | `theme/sentientia/scss/moodle/partials/_layout-shell.scss`, `_surface-footer.scss`, `_surface-login.scss` |
| Guest landing | `theme/sentientia/layout/frontpage.php` (812 lines) |
| Login strings | `theme/sentientia/lang/en/theme_sentientia.php` |
| Shell templates | `theme/sentientia/templates/shell.mustache`, `sidebar.mustache`, `topbar.mustache`, `mobile_bottom_nav.mustache`, `head.mustache`, `footer.mustache` |
| Sidebar items | `theme/sentientia/classes/sidebar_navigation.php` |
| Legacy homepage and static pages | `moodle-enhancement/local/sentientia_pages/homepage.php`, `pages/*.html` |
| PWA manifest | `moodle-enhancement/local/sentientia_pwa/manifest.php` |
| Flag registries | `moodle-enhancement/local/sentientia_platform/db/feature_flags.php`, `moodle-enhancement/local/sentientia_catalog/db/feature_flags.php` |
| Brand Book adoption | `moodle-enhancement/docs/audits/brand-revamp-2026-06/REVISED-BRAND-BOOK-2026-06.md`, `brandbook-text.txt`, `pages/` |
| Design rationale | `docs/platform-review-2026-05-14/UI-UX-MANIFESTO.md` (cited by `_tokens.scss`) |
| Static web-app manifest and brand icons | `theme/sentientia/pix/brand/manifest.json`, `pix/brand/favicon_io/` |
| Earlier audits | `moodle-enhancement/docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`, `PLATFORM-VISUAL-AUDIT-2026-05-25.md`, `UI-NAV-AUDIT-2026-08-03.md` |
| Accessibility | `moodle-enhancement/PHASE-H-A11Y-AUDIT.md`, `PHASE-H-A11Y-AXE-RESULTS.md`, `tests/playwright/a11y-smoke.spec.ts` |
| Surface walk and deploy record | `moodle-enhancement/docs/business/csuite-2026-11-18/SURFACE-WALK-2026-11-18.md`, `UAT-DEPLOY-WALK-FIXES.md` |
| Post-demo plan | `moodle-enhancement/docs/plans/2026-11-19-POST-DEMO-PLAN.md` |
| Project state | `moodle-enhancement/PROJECT-STATE.md` (entries of 18, 19 and 20 Nov 2026) |
| Core modifications | `moodle-enhancement/docs/core-mods/2026-11-19-tool-certificate-verify-on-arrival.md`, `2026-11-20-tool-certificate-5.0.12-revendor.md` |

### B.2 Architecture Decision Records cited

| ADR | Title | Status | Relevance |
|---|---|---|---|
| ADR-031 | Cross-tenant authority | Accepted | Tenant isolation wording (C10) |
| ADR-035 | Privacy erasure completeness | Accepted | DPDP claims (C2, C32) |
| ADR-036 | Training programs | Proposed | External trainers (AD-03, ET-01) |
| ADR-038 | Assessment integrity levels | Proposed | Secure assessment mode (C16, C45, L-23, AD-25) |
| ADR-040 | Native mobile app | Proposed | Out of scope; PWA status |
| ADR-041 | Patching vendored plugins | Accepted | Certificate verification page |
| ADR-044 | Modal form dependency guard | Accepted | `form.js` core patch (UI-08) |

### B.3 External standards and content-style basis

| Standard | Reference |
|---|---|
| Web Content Accessibility Guidelines 2.2 (W3C Recommendation) | https://www.w3.org/TR/WCAG22/ (accessed 20 November 2026) |
| Nielsen Norman Group, 10 usability heuristics for user interface design | https://www.nngroup.com/articles/ten-usability-heuristics/ (accessed 20 November 2026) |
| Airpay Brand Book 2026-06 (25 pages) | Internal; original file held by the Head of Learning & Development |
| Plain-language content style | None adopted for the product. Section 1.6 applies general plain-language practice (short sentences, active voice, consistent terms, no unexplained jargon); the reviewer is asked to choose a basis (Q16) |

## Appendix C. Evidence folder key and navigation

### C.1 Evidence folders to persona labels

Evidence folder names are synthetic labels for seeded test personas on the development environment. They are not UAT accounts, carry no credentials and are used here only to locate screenshots.

| Evidence folder | Persona label |
|---|---|
| `vp_learner1` | Learner (Airpay tenant) |
| `vp_report1b` | Learner (Airpay tenant, Hindi interface) |
| `vp_public77` | Learner (Public tenant) |
| `vp_learner177` | Learner (ZEEA tenant) |
| `vp_manager1` | Line manager (Airpay) |
| `vp_author1` | Internal trainer (Airpay) |
| `vp_courseauthor1` | Course author (Airpay) |
| `vp_admin1` | L&D / tenant administrator (Airpay) |
| `vp_admin177` | Tenant administrator (ZEEA) |
| `vp_compliance1` | Compliance officer (Airpay; also holds an organisation-admin role on development) |
| `vp_siteadmin` | Site administrator |
| `2026-09-30/personas/` | Numbered persona journey (learner, manager, trainer, author, admin, compliance, Public, guest, ZEEA, site admin) |
| `2026-10-21/uat/` | UAT, logged out |

### C.2 Sidebar items by persona

Source: `theme/sentientia/classes/sidebar_navigation.php`.

| Persona | Items |
|---|---|
| Site administrator | Manage Users, Manage Courses, Course-share Requests, Online Exams, Classrooms, Learning Paths, Programs, Analytics, Compliance, Organisation, Skills, Evaluations, Certificates, Emails, Privacy, Site Admin |
| L&D / tenant administrator | Manage Users, Manage Courses, Browse Library, Online Exams, Classrooms, Learning Paths, Programs (Built (verified on development)), Analytics, Compliance; Authoring group when permitted |
| Line manager | Compliance, plus the learner items |
| Learner | My Courses, Catalog, Browse Library and My Requests (cross-tenant, flag-dependent), My Cart (non-Airpay tenants), My Skills, Certificates, Live Sessions (permission-dependent) |
| Authoring group | Authoring Studio, AI Quiz, Skills AI |
| Mobile bottom bar | Home, My Learning, Search, Me |

## Appendix D. Contrast calculations

WCAG 2.x relative-luminance formula, computed for this pack. Pass thresholds: 4.5:1 normal text, 3:1 large text and non-text UI.

| Foreground | Background | Ratio | Normal text | Large text / UI |
|---|---|---|---|---|
| `#0066A7` primary | `#ffffff` | 6.07:1 | Pass | Pass |
| `#0066A7` primary | `#f2f4fb` page background | 5.53:1 | Pass | Pass |
| `#ffffff` | `#0066A7` primary button | 6.07:1 | Pass | Pass |
| `#1985DD` accent | `#ffffff` | 3.86:1 | **Fail** | Pass |
| `#ffffff` | `#1985DD` accent button | 3.86:1 | **Fail** | Pass |
| `#ffffff` | `#ed692b` orange | 3.15:1 | **Fail** | Pass |
| `#ed692b` orange | `#ffffff` | 3.15:1 | **Fail** | Pass |
| `#6d58a5` purple | `#ffffff` | 5.85:1 | Pass | Pass |
| `#6d58a5` purple | `#e8e2f2` landing tag tint | 4.62:1 | Pass | Pass |
| `#475569` secondary text | `#ffffff` | 7.58:1 | Pass | Pass |
| `#607286` muted text | `#ffffff` | 4.94:1 | Pass | Pass |
| `#8896a6` (legacy literal) | `#ffffff` | 3.02:1 | **Fail** | Pass |
| `#5a6070` (legacy literal) | `#ffffff` | 6.28:1 | Pass | Pass |
| `#15803d` success | `#ffffff` | 5.02:1 | Pass | Pass |
| `#16a34a` (legacy literal) | `#ffffff` | 3.30:1 | **Fail** | Pass |
| `#b45309` warning | `#ffffff` | 5.02:1 | Pass | Pass |
| `#dc2626` danger | `#ffffff` | 4.83:1 | Pass | Pass |
| `#9ca3af` dark muted text | `#1e293b` dark surface | 5.76:1 | Pass | Pass |
| `#ed692b` dark warning | `#1e293b` | 4.64:1 | Pass | Pass |
| `#0066A7` primary | `#1e293b` dark surface | 2.41:1 | **Fail** | **Fail** |

## Appendix E. Entry points grouped into a parent surface or left out

Section 3 groups the 179 plugin entry points into reviewable surfaces. These entry points have no row of their own. Review them as part of the parent surface named, or not at all for the reason given.

| Entry point | Persona | Treated as | Reason |
|---|---|---|---|
| `/local/sentientia_users/skillprofile.php` (skill radar and gap analysis) | Learner | Part of L-08 Profile and L-09 My skills | Same data as My skills, reached from the profile |
| `/local/sentientia_users/help.php` (help and support links) | Learner | Part of P-09 Static pages (help) | Same purpose as the static help centre; check both for consistent help (WCAG 3.2.6) |
| `/local/sentientia_users/photo.php` (profile photo upload) | Learner | Part of L-08 Profile | A form step of the profile |
| `/local/sentientia_cart/credits.php`, `creditnote.php`, `einvoice.php`, `daily_sums.php` | L&D administrator, Finance | Part of AD-22 Orders and prices | Finance back-office pages; imported credits behind `sentientia.cart.imported_credits.enabled` (off by default) |
| `/local/sentientia_cart/checkout.php`, `callback.php`, `return.php` | Learner | Part of L-06 Cart | Payment hand-off and return; checkout behind `sentientia.catalog.storefront_checkout.enabled` |
| `/local/sentientia_leaderboard/preferences.php`, `view.php` | Learner | Part of L-15 Leaderboards | Opt-out preference and single board |
| `/local/sentientia_talent/index.php` (talent console) | HR, manager, L&D administrator | Part of L-19 Talent opportunities | Same plugin, behind the same default-OFF talent flags |
| `/local/sentientia_assistant/ai_demo.php` | Site administrator | Part of S-06 AI gateway settings | Administrator test page for the AI bridge; mock by default |
| `/local/sentientia_recommendations/generate.php` | Manager | Out of scope | AI recommendations, Built (behind a feature flag, off by default: `sentientia.recommendations.enabled`); no learner-facing output |
| `/local/sentientia_org/data_migration.php` | Site administrator | Out of scope | Retired script that refuses to run (replaced by the import framework of ADR-032) |
| Export and download endpoints (`exportcsv.php`, `export.php`, `compare_export.php`, `daily_sums_csv.php`, `sample.php`, `ics.php`) | Various | Out of scope | Produce files, not pages; check file names and column headings during the parent surface review |
| Streams and services (`stream.php`, `sw.php`, `preview_ajax.php`, `mock_receiver.php`, `dismiss_install.php`, `regenerate.php`) | System | Out of scope | No visible page |
| `/local/sentientia_integrations/webhook.php` | System | Out of scope | Inbound webhook receiver; no user interface |
| Detail pages (`sync_run_detail.php`, `log_detail.php`, `history_detail.php`, `email_detail.php`, `response_detail.php`, `response_list.php`, `levelcourses.php`, `level_definitions.php`, `questions.php`, `import_template.php`, `export_template.php`, `enrolledusers.php`, `enrol_csv.php`, `bulk_unenrol.php`, `all.php`, `requests.php`, `edit.php`, `history.php` of re-completion) | Administrators, managers | Part of the parent list surface in the same plugin | Drill-down or form pages of a listed surface |

Confidential: Airpay Payment Services internal. Not for external distribution.
