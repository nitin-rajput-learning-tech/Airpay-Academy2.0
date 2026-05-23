# Phase A.1 — Pre-merge inventory snapshot (2026-05-23)

ADR-011 Phase A.1 deliverable. Locked at tag `v4.1.1-pre-merge`,
working branch `5.2-merge-baseline`.

This document is the codebase baseline against which we measure
conflict density during the Phase B merge.

---

## Branch + tag

```
origin/production               edcf64775 (HEAD of P0 borrow wave + ADR-011)
origin/5.2-merge-baseline        edcf64775 (workspace for the merge)
v4.1.1-pre-merge                 edcf64775 (annotated, rollback point)
v4.1.0-goal-a-audit              earlier   (prior milestone, Goal A complete)
```

## Theme surface

```
theme/airpayux  → 686 files
```

Includes all overrides, all SCSS partials, all AMD source + build
bundles, all Mustache templates, layout PHP, classes/output/*,
classes/customizer/*.

For Phase A.4 theme conflict map, this is the set we diff against
upstream 5.2 `theme/boost/` (and its parent components since we forked
epsilon which extended boost).

## Plugin manifest — 31 plugins

All `local_airpay_*` plus the workstream extras:

```
local_airpay_analytics            — admin reports + dashboards
local_airpay_assistant            — AI assistant feature-flagged
local_airpay_cart                 — course-cart e-commerce surface
local_airpay_catalog              — public-tenant course catalog
local_airpay_challenge            — gamified team challenges
local_airpay_classroom            — instructor-led classroom sessions
local_airpay_compliance_report    — compliance dashboards + exports
local_airpay_core                 — shared infrastructure (this session +3 classes)
local_airpay_courses              — course CRUD + reminder/escalation crons
local_airpay_emails               — tenant-scoped email tooling
local_airpay_evaluation           — surveys + auto-assign + bulk-assign
local_airpay_exams                — quiz reminders + manager escalations
local_airpay_gamification         — points/badges/leaderboards
local_airpay_integrations         — third-party connectors
local_airpay_learningpath         — learning paths + cascade filters
local_airpay_lifecycle            — onboarding/offboarding workflows
local_airpay_manager              — Manager Team Dashboard
local_airpay_notifications        — push subscriptions + delivery log
local_airpay_org                  — org-chart + reporting hierarchy
local_airpay_pages                — privacy/terms/about static pages
local_airpay_privacy              — GDPR data-subject tooling
local_airpay_proctoring           — exam proctoring integration
local_airpay_programs             — multi-course programs + audience enroller
local_airpay_ratings              — course/instructor ratings
local_airpay_recompletion         — periodic recertification
local_airpay_reports              — operational reports
local_airpay_request              — request workflow for paths/programs
local_airpay_roles                — custom-capability matrix
local_airpay_skills               — skills taxonomy + self-rate
local_airpay_users                — HRMS importer + sync cron
local_airpay_whatsapp             — WhatsApp Business API bridge
```

Per ADR-001 (fork strategy) these plugins are owned by us. None of them
ship with upstream Moodle.

## Test surface

```
Total *_test.php files:               91
Test files in local_airpay_core:       9 (this session added 3 new)
```

Each plugin maintains its own `tests/` directory. The Goal-A.x sprint
also added cross-cutting tests in `local/airpay_core/tests/`:

```
audit_log_test.php           — audit-log immutability
backup_filename_test.php      — NEW this session (P0 #11)
cm_navigation_test.php        — NEW this session (P0 #9)
cron_health_test.php          — cron health publisher
customer_brand_test.php       — customer brand resolver (ADR-008)
feature_flags_test.php        — 5-level feature-flag resolver
structured_logger_test.php    — structured logger
tenant_test.php               — tenant scope helper (ADR-009)
user_status_test.php          — NEW this session (P0 #10)
```

Phase B re-run cadence: ALL 91 test files must pass after every merge
commit during conflict resolution.

## Sentientia surface count

Goal A.x surface-restyle pattern applied to (verified per docs/visual-evidence/):

- /user/profile.php
- /badges/mybadges.php
- /grade/report/overview/index.php
- /grade/report/index.php (per-course gradebook)
- /admin/* interior
- /course/view.php
- /message/index.php
- /user/edit.php
- /user/preferences.php
- /calendar/view.php month view
- /course/edit.php (Site Admin only)

Each surface is documented at `docs/sentientia-surface-restyle-pattern.md`.

## Core mods tracking

Per CLAUDE.md §3 every core mod is recorded at `docs/core-mods/`:

```
2026-05-20-moodle-to-sentientia-rename.md
2026-05-23-certificate-image-imageinfo-guard.md
```

Both are minor — the Phase B merge will re-apply them on the new 5.2
codebase. Phase A.4 (theme conflict map) will surface any additional
core-touching changes that crept in.

## Blockers for Phase B

```
1. PHP 8.3 — local XAMPP (currently 8.2.12)
   Decision pending — see ADR-011 §"Open questions" item 1.

2. MySQL 8.4 — production AWS RDS (currently 8.0.44)
   Decision pending — IT change request.
```

Phase A continues regardless of blocker — A.2 (source diff), A.3 (PHP
8.3 lint scan via PHPStan), A.4 (theme conflict map) are all
information-gathering that don't change behaviour.

## What to do in the next session

Per ADR-011 Phase A:

- A.2 — Download Moodle 5.2 source tarball + decompress to a temp dir
- A.2 — Generate `diff -r moodle-5.1.3+ moodle-5.2/ > 5.2-upstream.diff`
- A.2 — Categorise into (additions / our-override-conflicts / removals)
- A.3 — PHP 8.3 lint scan on every `local_airpay_*` plugin
- A.4 — Theme conflict map: for each file in `theme/airpayux/` that
  has a corresponding upstream change in 5.2, document the resolution
  strategy.

Each of A.2, A.3, A.4 fits in a single session with no PHP version
change required.
