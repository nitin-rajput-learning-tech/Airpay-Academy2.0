# BizLMS -> Sentientia De-brand & Absorption Audit (2026-06-04)

Source of truth: the purchased eAbyas BizLMS suite, mirrored at
`D:\Claude Local\Moodle Backup\01-production-codebase\html\` (the live
production codebase). Airpay **owns** this code (purchased from eAbyas Info
Solutions Pvt Ltd). Goal: every dependency re-created as first-party
Sentientia code, with **zero** "BizLMS"/"eAbyas" identity remaining.

---

## 1. The purchased eAbyas suite (22 `local_*` plugins)

All are `@package BizLMS`, `@author eabyas <info@eabyas.in>`,
`Copyright eAbyas Info Solutons Pvt Ltd`, GPLv3, Moodle 3.3/3.4 vintage
(2017 builds). They are the substrate Airpay Academy production runs on.

| # | eAbyas plugin | Sentientia first-party equivalent | Disposition |
|---|---------------|-----------------------------------|-------------|
| 1 | local_assignroles | local_airpay_roles | retire original + de-brand fork |
| 2 | local_biz_cart | local_airpay_cart | retire + de-brand |
| 3 | local_classroom | local_airpay_classroom | retire + de-brand |
| 4 | **local_costcenter** (6 tables) | local_airpay_org + local_sentientia_core | **OWN OUTRIGHT (deep substrate)** |
| 5 | local_courses | local_airpay_courses | retire + de-brand |
| 6 | local_custom_category | local_airpay_catalog (?) | **verify (Wave 0)** |
| 7 | local_evaluation | local_airpay_evaluation | retire + de-brand |
| 8 | local_forum | (core mod_forum?) | **verify (Wave 0)** |
| 9 | local_groups | (core groups?) | **verify / own** |
| 10 | local_learningplan | local_airpay_learningpath | retire + de-brand |
| 11 | local_location | (none) | **own outright** |
| 12 | local_myteam | local_airpay_manager | retire + de-brand |
| 13 | local_notifications | local_airpay_notifications | retire + de-brand |
| 14 | local_onlineexams | local_airpay_exams | retire + de-brand |
| 15 | local_program | local_airpay_programs | retire + de-brand |
| 16 | local_ratings | local_sentientia_ratings | retire + de-brand |
| 17 | local_recompletion | local_airpay_recompletion | retire + de-brand |
| 18 | local_request | local_airpay_request | retire + de-brand |
| 19 | local_search | local_airpay_catalog (?) | **verify (Wave 0)** |
| 20 | local_skillrepository | local_airpay_skills | retire + de-brand |
| 21 | local_tags | (core tags?) | **verify / own** |
| 22 | local_users | local_airpay_users + admin UI gap | **own admin UI + de-brand** |

Plus loose eAbyas helpers at `local/`: `course_completions.php`,
`filterclass.php`, `includes.php`, `lib.php`, `info.php`, `styles.css`.

**Summary:** ~16 already have first-party forks (retire original + de-brand
our fork). ~6 need Wave-0 verification, of which `costcenter`/`location`/
the `local_users` admin UI are genuine first-party-build work.

---

## 2. The substrate schema (the deep dependency)

### 2.1 `local_costcenter` plugin owns 6 tables
`local_costcenter`, `local_costcenter_permissions`, `local_coursedetails`,
`local_moduleconfig`, `local_filters`, `local_certificate`.
(`local_costcenter` is the tenant/org tree. Also created by `local_classroom`
install.xml - a shared dependency.)

### 2.2 `open_*` columns are scattered across 10+ eAbyas plugins
`open_path` and siblings are added by the `db/install.php` / `db/upgrade.php`
of: classroom, courses, evaluation, groups, learningplan, notifications,
program, request, skillrepository, users. Full captured set:
**37 columns on `mdl_user`, 18 on `mdl_course`** (see
`local_sentientia_core/cli/bootstrap_substrate.php`, which already reproduces
them first-party + idempotently).

> Tenant detection at runtime = `$USER->open_path` string parsing
> (roots 1=Airpay, 77=Public, 177=ZEEA). `local_costcenter` rows are used by
> the eAbyas admin UI + JOINs, not by the core tenant check.

---

## 3. De-brand footprint

| Surface | `bizlms` files | `eabyas` files | `open_*` refs |
|---------|---------------|----------------|---------------|
| Purchased eAbyas source (production html/local) | 331 | 608 | (scattered, see 2.2) |
| Our current first-party code (local/ + theme) | 222 | 16 | 274 |

### Branding tokens to strip (verbatim from source)
- `@package BizLMS` -> `@package Sentientia`
- `@author eabyas <info@eabyas.in>` -> Sentientia authorship
- `Copyright eAbyas Info Solutons Pvt Ltd, India` -> `Copyright Airpay Payment Services / Sentientia LMS`
- `This file is part of eAbyas` -> `This file is part of Sentientia LMS`
- CSS classes/ids `bizlms-*` -> `sentientia-*` (functional - migrate templates + SCSS together)
- DB identifiers `local_costcenter` / `open_*` -> first-party names (Wave 4, schema migration, human-gated)

### Functional vs cosmetic (de-brand risk tiers)
- **Cosmetic (safe, do first):** comments, docblocks, copyright, display
  strings, doc text. The bulk of the 608 eabyas / 331 bizlms hits.
- **Functional (gated, migrate with care):** CSS class/id `bizlms-*`
  (template+SCSS in lockstep), config keys, table/column names, capability
  names, component names (capability re-registration = human-gated).

---

## 4. Wave plan (see ADR-024 for governance)

- **Wave 0** - verify the 6 uncertain dispositions (custom_category, forum,
  groups, location, search, tags); confirm which `airpay_*` forks fully
  replace their eAbyas origin vs still call into it.
- **Wave 1** - cosmetic de-brand of our OWN code (222 bizlms + 16 eabyas):
  comments/docs/strings only. Additive, no functional rename.
- **Wave 2** - own the substrate schema first-party (bootstrap_substrate.php
  done; promote to a Sentientia plugin install.xml/upgrade so install is
  automatic - ADR-gated core-table note).
- **Wave 3** - absorb the genuine-build plugins (costcenter -> sentientia
  org/tenant, location, users admin UI) as first-party, de-branded.
- **Wave 4** - migrate `open_*` readers (274 files) onto the sentientia_core
  tenant/org seams; retire `open_*` once parity = 100% (flag-gated, ADR-020/021).
- **Wave 5** - component rename + capability re-registration (HUMAN-GATED).

Each wave: additive, flag-gated where behavioural, locally rehearsed,
production-branch only, never breaking live Airpay Academy behaviour.
