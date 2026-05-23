# ADR-010 — Moodle 5.2 borrow inventory (no-production-deploy strategy)

**Status:** Proposed (2026-05-23). Strategy decision: hold production
deploy of v4.1.0-goal-a-audit until the borrow inventory below is
worked through. Production will receive v4.2.0 or later.

**Date:** 2026-05-23
**Deciders:** Nitin Rajput, Claude
**Builds on:** ADR-001 (fork strategy), ADR-009 (bug-class extinction)

---

## Context

Moodle 5.2 released April-May 2026 — eight weeks after our 5.1.3+
fork point. Nitin's directive: **do not deploy v4.1.0 to production
until we have systematically reviewed and borrowed everything
relevant from 5.2.** The current production runs an older release;
shipping v4.1.0 in isolation would orphan us further from upstream
when we eventually do the next upgrade.

Our 5.1.3+ fork has heavy customisation (theme_airpayux 514 files,
30+ `local_airpay_*` plugins, BizLMS layer). Upstream version
jumps land both **borrowable wins** (new APIs, UX improvements,
security tightening) and **landmines** (deprecated APIs we may
still use, changed signatures, removed features). This ADR
catalogues both.

---

## Moodle 5.2 headline facts

```
Version:        5.2.0 (May 13, 2026 stable; April 20 release notes)
Min upgrade:    Moodle 4.4 → 5.2 (we're on 5.1.3+ ✓ in-range)
PHP:            8.3+ required (we're on 8.2.12 — UPGRADE NEEDED)
DB minimums:    MariaDB 10.11.0, MySQL 8.4, PostgreSQL 16, MSSQL 2019
                (we're on MariaDB 10.11.16 ✓; prod on MySQL 8.0.44 ✗)
PHP extensions: `sodium` required (we have it)
PHP config:     `max_input_vars` ≥ 5000
DB prefix:      max 10 chars
Architecture:   64-bit PHP only
Removed:        Oracle Database support
```

**The two hard blockers for upgrading to 5.2 wholesale:**
1. **PHP 8.2 → 8.3** — XAMPP local + AWS RDS PHP runtime
2. **MySQL 8.0.44 → 8.4** — production AWS RDS

Both are independently scheduled IT work. Until they land, we
**cannot upgrade to 5.2**. But we **can borrow features piecemeal**
that don't depend on 5.2-only APIs.

---

## Borrow inventory — by priority tier

### P0 — Borrow now (no PHP 8.3 dependency)

| # | Item | Where it lives in 5.2 | Why borrow | Effort |
|---|------|------------------------|------------|--------|
| 1 | **Sticky footer submit buttons** on common forms | Boost theme + mform output | Direct match for our /user/edit + /course/edit polish; better UX than card-bottom buttons that scroll off | 1 day |
| 2 | **Activity header with completion labels** | core_courseformat output | Our /course/view.php is restyled but doesn't show inline completion — Moodle 5.2's `activityinfoinheader` layout option is the canonical shape | 1 day |
| 3 | **Anchor link navigation highlighting** | course module nav | Tiny CSS-only win; brand-light flash on anchor jump | 2 hrs |
| 4 | **Restricted page with visible availability conditions** | core_availability | UX win — learners see WHY a section is locked instead of just "not available" | 0.5 day |
| 5 | **Multi-language OAuth2 button text** | `core\auth` | Useful for Sentientia LMS i18n — Hindi parity for OAuth flows | 0.5 day |
| 6 | **Toast `visuallyHidden` parameter** | `core/toast` AMD module | Accessibility win — screen reader users get toast announcements | 1 hr |
| 7 | **`core/page_title` JS module** | new in 5.2 | Cleaner than direct `document.title =` mutation | 1 hr |
| 8 | **`core/deprecated` JS module** for warnings | new in 5.2 | We can use this to retire our AMD deprecations gracefully | 2 hrs |
| 9 | **`get_navigation_url()` on `cm_info`** | core | Plugins can override the URL a module's nav link points to (we'd use for `airpay_evaluation` deep-linking) | 0.5 day |
| 10 | **Suspended student status more clearly displayed** | various reports | Helpful for our Manager Team Dashboard | 0.5 day |
| 11 | **Configurable default backup file names** | core_backup | Useful for Sentientia LMS exports | 1 hr |
| 12 | **Manual completion buttons in activity header** | core_courseformat | Coherent with #2; ships together | (included in #2) |
| 13 | **Sticky header with course title in course index** | core_courseformat | Useful for long course pages | 0.5 day |
| 14 | **"Sort by course start date" on My Courses** | block_myoverview | Quick filter improvement | 1 hr |
| 15 | **Quiz overall feedback when marks hidden** | mod_quiz | UX correctness fix | (auto when we upgrade) |

**Subtotal: ~5 days of work, zero PHP-version dependency.**

### P1 — Borrow with light infrastructure work

| # | Item | Where it lives in 5.2 | What we'd need to build first | Effort |
|---|------|------------------------|-------------------------------|--------|
| 16 | **AI provider framework** (AWS Bedrock + Gemini in core) | `core_ai` subsystem | Adopt 5.2's `core_ai` provider interface; add our Anthropic provider | 1 week |
| 17 | **Multiple-markers assignment workflow** | mod_assign | Our customers haven't asked but Enterprise N may | 1 week |
| 18 | **Override reason capture** for assignments/quizzes/lessons | various | Audit-trail win; pairs with ADR-009 invariants | 2 days |
| 19 | **Site-wide notes with rich text + multi-language footer** | core_admin | Useful for Sentientia LMS announcements | 1 day |
| 20 | **Asynchronous large course resets** | core_course | Performance win for Airpay's 411 courses | 1 day |
| 21 | **Forum auto-locking for inactive discussions** | mod_forum | Compliance + housekeeping | 0.5 day |
| 22 | **Q&A forum live mode** | mod_forum | UX win | 0.5 day |
| 23 | **"Jump to anchor after editing"** | core_courseformat | Save-and-stay-in-context UX | 0.5 day |
| 24 | **Database read/write count filters for Task logs** | tool_log + report_builder | Observability for our 30+ scheduled tasks | 0.5 day |
| 25 | **TinyMCE "Select all/none" for Unused files** | editor_tiny | Course Author productivity | 0.5 day |
| 26 | **Improved file upload error messaging** | core_files | UX correctness | 1 day |

**Subtotal: ~4 weeks of work.**

### P2 — Architectural pivots (require migration planning)

| # | Item | What it changes | When to consider |
|---|------|-----------------|------------------|
| 27 | **React library + JSX** as external bundle | New JS framework alongside AMD | After we've delivered customer 2 — too risky to mid-migrate now |
| 28 | **TypeScript opt-in** with `tsconfig.json` | Static typing for new AMD | Worth it for net-new modules; not retro-fitting |
| 29 | **esbuild bundling** (`.esbuild/` dir) | Replace Grunt/Shifter AMD pipeline | Pair with TypeScript adoption |
| 30 | **Composer for third-party libs** | Moves vendor/ to managed deps | Audit our use of `require_once 'vendor/...'` first |
| 31 | **Moodle Design System tokens bundled as external package** | Canonical CSS variables | Cross-walk against our `.claude/rules/frontend.md` tokens; reconcile |
| 32 | **Open Telemetry integration** | Tracing + metrics for the platform | Critical for Sentientia LMS SaaS at scale |
| 33 | **Navigation classes moved to `\core\navigation\*` namespace** | BC layer in place but deprecation runway | Update our `\theme_airpayux\sidebar_navigation` consumer when we upgrade |
| 34 | **`\cm_info` → `\course\cm_info` namespace move** | Affects every plugin that uses cm_info | Plan a sweep before 5.2 upgrade |
| 35 | **Hook Manager now uses `localcache`** | Cache backend shift | Verify our `local_airpay_core` hook callbacks |
| 36 | **Subsection pages removed; inline display** | UX shift in course pages | Verify our `local_airpay_*` plugin templates don't assume subsection page route |

**Subtotal: ~2-3 quarters of incremental work.**

### P3 — Hard requirements for the 5.2 upgrade itself

| # | Blocker | Owner | Status |
|---|---------|-------|--------|
| 37 | **PHP 8.2 → 8.3** on XAMPP local | Claude (next session) | Not started |
| 38 | **PHP 8.2 → 8.3** on AWS RDS prod | IT team | Not started |
| 39 | **MySQL 8.0.44 → 8.4** on AWS RDS prod | IT team | Not started |
| 40 | **`max_input_vars` ≥ 5000** in php.ini | Claude + IT | Verify both envs |
| 41 | **Audit our DB prefix length** ≤ 10 chars | Claude | Quick check; we use `mdl_` so ✓ |
| 42 | **Run `\core\setup::warn_if_upgrade_is_running()`** instead of deprecated `upgrade_ensure_not_running()` | Code sweep | Search our plugins |

### P4 — Security tightening to mirror

| # | Item | Mirror in our code | Why |
|---|------|---------------------|-----|
| 43 | **JSON instead of PHP serialization** in repositories | Audit our `serialize()`/`unserialize()` calls | RCE risk via malicious cached data — same security tightening upstream did |
| 44 | **Don't send email to AirNotifier** push gateway | Audit our `push_sender.php` payload | PII minimisation; we already strip employee data per CLAUDE.md |
| 45 | **`kill_all_sessions.php` dry-run default** | Mirror in our `local_airpay_core/cli/` if any | Operational safety |
| 46 | **MoodleNet outbound sharing removed** | Confirm we never used it | We didn't; just close the loop |
| 47 | **`$CFG->wwwrootendsinpublic` enforcement** | Verify our XAMPP + AWS server config | Server config audit |

### P5 — Deprecation sweep (audit our code)

Before we upgrade to 5.2, we MUST audit our codebase for:

| Deprecated in 5.2 | Replacement | Action |
|---|---|---|
| `core/modal_factory`, `core/modal_registry` AMD | `core/modal` directly | Grep `theme/airpayux/amd/` + `local/airpay_*/amd/` |
| `moodle-core-notification-confirm` YUI | `core/modal` | Grep for `moodle-core-notification` |
| `M.util.set_user_preference()` | core WS or new helper | Grep `set_user_preference` |
| `xmlize()` | `\core\xml_parser` | Grep `xmlize(` |
| `file_encode_url()` | `core\url` factory | Grep `file_encode_url` |
| `course_delete_module()`, `course_module_flag_for_async_deletion()` | `core_courseformat\cmactions` | Grep |
| `get_moodlenet_info()` | none (removed) | Grep, remove callers |
| `switch_question_bank` renderable | client-side | Plugin audit if we use question banks |
| YUI TreeView in `mod_folder` | replaced upstream | Verify we don't custom-extend |
| `core/checkbox-toggleall-master-button` selectors | `toggler`/`target` selectors | Grep `master-button` |

---

## Decision

1. **Hold production deploy of v4.1.0-goal-a-audit** until at least
   tier P0 is shipped on top of it. Production will receive
   v4.2.0 or later, not v4.1.0.

2. **Sequence P0 work** in priority order, one item per session,
   each shipped as `feat(borrow): <item> from Moodle 5.2` commit.
   Target: P0 complete in ~5 working days.

3. **Schedule P3 infra upgrades** (PHP 8.3, MySQL 8.4) as IT-team
   work, parallel to our P0 work. These don't unblock anything
   immediate but block the eventual 5.2 wholesale upgrade.

4. **P5 deprecation audit** runs as a single sweep — one session,
   grep-based, mechanical. Output: list of code locations + their
   replacement path. Don't fix yet; just inventory.

5. **P1 features** trigger only when a customer asks. Don't build
   AI provider framework or multi-marker assignments speculatively.

6. **P2 architectural pivots** stay on the horizon. React +
   TypeScript + esbuild are the right long-term direction but
   migrating mid-customer-onboarding is high-risk.

---

## Consequences

### Positive

- **No fork drift accumulation.** By borrowing P0 + P1 items into
  v4.2.0 before deploying, we keep our fork's distance from upstream
  bounded. Each subsequent Moodle minor (5.3, 5.4) gets the same
  treatment.

- **Production gets a polished v4.2.0** rather than a stale v4.1.0
  that's already two minors behind upstream when it lands.

- **Tier P0 work fits inside the existing audit-cycle rhythm.** Each
  item is 1-day or less; ship-verify-commit pattern works perfectly.

- **The audit-cycle test infrastructure (ws_contract gate +
  role_detector + Playwright surfaces) protects us against the
  borrow work** — any regression caused by porting a 5.2 feature
  fails CI immediately.

### Negative / cost

- **Production is now ~10 weeks behind on visible improvements** by
  the time v4.2.0 ships (Goal A.x audit findings are not reaching
  airpay.academy users until then).

- **PHP 8.2 → 8.3 upgrade** is an operational risk that needs
  scheduling. Our PHP code is mostly 8.3-clean already (we use
  `match`, `enum`, named arguments) but `php -l` doesn't catch
  runtime breakage.

- **MySQL 8.0 → 8.4 upgrade** on AWS RDS has a downtime window
  cost; needs to be scheduled with stakeholders.

### Risk mitigation

- Tag `v4.1.0-goal-a-audit` is preserved as the rollback point if
  any borrow item destabilises the fork. We don't delete it.

- Each P0 borrow commit references the upstream Moodle commit/PR
  number in its body. Future archaeology stays traceable.

- The standalone CI ws_contract gate (commit `80d18ed79`) catches
  contract drift introduced by Moodle 5.2's expanded
  `core_webservice_get_site_info` shape (3 new fields).

---

## Open questions (for Nitin)

1. **Is the PHP 8.3 + MySQL 8.4 IT work blocked on budget,
   personnel, or timing?** If timing, what's the realistic ETA?
2. **Which P1 customer-driven features (#16-#26) are already in
   the buyer conversation?** Sequencing P1 should follow real
   demand, not speculation.
3. **Do we want to mirror Moodle 5.2's React/TypeScript adoption
   path (P2 #27-#29) or pick our own JS framework?** This is a
   1-quarter strategic decision worth its own ADR.
4. **For the airpay_compliance_report plugin, does the "Override
   reason capture" feature (P1 #18) overlap with what compliance
   officers need?**

---

## Related artifacts

- `moodle-enhancement/docs/adr/ADR-001-fork-strategy-and-product-pivot.md`
  — why we forked, sets the precedent
- `moodle-enhancement/docs/adr/ADR-009-detection-consistency-and-ws-contract-invariants.md`
  — bug-class extinction patterns that protect borrow work
- `moodle-enhancement/PROJECT-STATE.md` — running session ledger
- Moodle 5.2 release notes: https://moodledev.io/general/releases/5.2
- Moodle 5.2 UPGRADING.md (in MOODLE_502_STABLE branch)
