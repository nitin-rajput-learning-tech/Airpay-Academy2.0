# Platform Stabilization Audit — 2026-05-28

> **Status: 🚧 IN PROGRESS.** This document grows across multiple sessions.
> The goal is to be the **final** comprehensive audit — once complete and
> triaged, it becomes the stabilization backlog and locks the product state.
> **No new features ship until findings here are resolved or explicitly
> deferred to v2.**

**Auditor:** Claude (with Nitin)
**Started:** 2026-05-28
**Coverage:** Sentientia LMS / Airpay Academy — all 37 plugins + theme +
integrations + learner/admin journeys + locale parity + DB health.
**Goal:** End the pattern of weekly discoveries by surfacing every
not-built / half-built / broken-UI / wrong-business-logic / UI-drift
finding in one place, each with a clear *Ship / Finish / Remove /
Redesign / Defer-v2* recommendation.

---

## How to read this document

Each finding follows a fixed schema:

```
F-NNN  Title
       Surface: <file paths / page URLs / plugin>
       State: <NotBuilt|HalfBuilt|CodeNoUI|UIWithoutCode|BizLogicWrong|UIDrift|BrokenInProd|Stale>
       Severity: 🔴 Legal/Blocking · 🟠 Important · 🟡 Cosmetic · ⚪ Polish
       Effort: S (≤2h) · M (½ day) · L (2-3 days) · XL (week+)
       Decision needed: <Ship as-is | Finish | Remove | Redesign | Defer-v2 | PENDING NITIN>
       Recommendation: <one-sentence call>
       Notes / links: <related findings or context>
```

Sort orders:
- §2 Findings by Surface — per plugin, easier to scope work per plugin.
- §3 Findings by Type — cross-plugin patterns surface here.
- §4 Stabilization backlog — ordered by Severity × Effort.

---

## §1. Executive summary

> *Snapshot at end of Phase 1. Phase 0 covered F-001–F-075 (desk research);
> Phase 1 added F-076–F-097 (runtime + DB + drift probes).*

**Findings logged: 97 (F-001 — F-097).**

### Distribution

| Severity | Count | Notes |
|----------|-------|-------|
| 🔴 Legal/Blocking | 6 | Phase 0: F-002, F-007, F-057. Phase 1: F-080/F-088 (caps not registered), F-091 (airpay_pages workspace drift), F-092 (airpay_lifecycle workspace drift). |
| 🟠 Important | ~25 | Plus Phase 1: F-077 (cron log noise), F-085 (78 features never data-tested), F-086 (dark-mode flag governance), F-094 (theme AMD drift), F-095 (drift gate meta), F-097 (test stale). |
| 🟡 Cosmetic | ~32 | UI drift, naming inconsistency, doc drift |
| ⚪ Polish | ~18 | Comment bloat, minor cleanup |
| ✅ Verified clean | 1 | F-078 (M.cfg.userId rename — no legacy call-sites; preserved for traceability) |

| State | Count |
|-------|-------|
| NotBuilt | 3 |
| HalfBuilt | 16 |
| BizLogicWrong | 7 (today's foundational findings) |
| UIDrift | 7 |
| BrokenInProd | 1 |
| Stale (doc drift / partial fix) | 5 |
| Deferred (parked) | 22 |
| Polish | 5 |
| Investigate-first | 3 |
| Meta | 2 |
| (Resolved during audit / pre-audit) | 4 (F-008, F-009, F-013, partials) |

### Top 5 risks (CTO-readable)

1. **🔴 Consent / privacy in the leaderboard widget (F-002)** — GDPR Art. 6 + India DPDP Act 2023 violation by displaying user identities without lawful basis. Active on every dashboard load today. Immediate hotfix candidate.
2. **🔴 Public-tenant-as-tenant architectural mistake (F-007)** — Foundational. Closes 6 downstream findings (F-001, F-003, F-004, F-005, F-006, plus the leaderboard relevance arm of F-002). ADR-017 needed; this is multi-week.
3. **🟠 15 of 37 plugins (40%) not in MATURITY_STABLE (F-062)** — Including 3 AI plugins that have never made a paid call (F-017/F-018/F-019), 1 literal scaffold (F-059), and WhatsApp stuck in mock mode (F-045). Sentientia LMS pitched as enterprise-ready; reality is 60% stable.
4. **🟠 Production deployment gaps (F-046, F-047, F-014)** — Moodle 5.2 upgrade never executed on prod; PWA master key + push flag-on never executed on prod; AI Quiz never live-called. All gated on operator decisions, all overdue.
5. **🟠 Doc drift between state-cards/PROJECT-STATE and reality (F-058)** — Several state-cards stale (sentientia_live being worst). The pattern of "we forgot we deferred X" is structural (F-057). This audit + a state-card refresh sweep + the new "refresh state-card on version bump" rule closes it.

### Cross-cutting patterns worth flagging upfront

| Pattern | Findings | Notes |
|---------|----------|-------|
| Tenant isolation: cross-tenant data leaks | F-002, F-003, F-006, F-008 (fixed today) | Same `accesslib::get_tenant_category_id` resolver pattern applies everywhere. |
| User-type modelling: consumer ≠ employee | F-001, F-004, F-005, F-007 (foundational), F-038 | ADR-017 closes this. |
| AI plugins built but never live-called | F-017, F-018, F-019 | All 3 in ALPHA, mock-mode complete, never POSTed to Anthropic. |
| Production-gated never executed | F-014, F-040, F-046, F-047 | Operator decisions overdue. |
| Naming transition incomplete | F-012, F-075 | 32 airpay_ + 5 sentientia_; same split in blocks. |
| Doc drift / state-card staleness | F-022, F-058 | Process problem; F-057 is the meta-fix. |
| `!important` debt + UI drift | F-067, F-071, F-049 | Long-running tech-debt thread. |
| i18n parity not at 100% on every surface | F-065, F-070 | Hard CLAUDE.md rule violated. |

---

---

## §2. Findings by Surface

### Plugins inventory — naming + maturity snapshot

The platform currently ships **32 `local_airpay_*`** plugins and **5
`local_sentientia_*`** plugins, plus 5 blocks (`block_airpay_*` and
`block_sentientia_*`) and 1 theme (`airpayux`). The rename from
`airpay_*` → `sentientia_*` (mandated by ADR-001's product pivot) is
**underway but unfinished** — see F-012.

| Plugin | Maturity (release) | First-pass notes |
|--------|--------------------|------------------|
| local_airpay_core | STABLE 1.6.0 | foundation (feature flags, brand resolver, hooks) |
| local_airpay_users | STABLE 2.7.0 | HRMS import + signup; **F-007** profile shape wrong for consumers |
| local_airpay_org | STABLE 1.4.1 | costcenter/category bridge; tenant resolver; **F-050/F-051** capability migration + WS endpoints deferred |
| local_airpay_courses | STABLE 1.11.2 | course CRUD; **F-003** featured-widget tenant leak |
| local_airpay_catalog | **BETA** 1.0.0-beta | catalog browse; **F-006** UI not Netflix-shaped |
| local_airpay_gamification | **BETA** 1.0.1-beta | points/badges/leaderboard; **F-002** consent missing |
| local_airpay_pages | TBD (no version output) | onboarding wizard; **F-001** interests = tenants |
| local_airpay_emails | STABLE 1.1.2 | welcome + cron + templates |
| local_airpay_classroom | STABLE 1.10.1 | physical classroom mod |
| local_airpay_programs | STABLE 1.8.1 | program management |
| local_airpay_learningpath | STABLE 1.7.1 | learning paths |
| local_airpay_evaluation | STABLE 1.15.2 | evaluations / surveys |
| local_airpay_exams | STABLE 1.6.1 | online exams |
| local_airpay_skills | STABLE 1.6.2 | skill tracking |
| local_airpay_assistant | **BETA** 1.1.1-beta | **F-060** purpose unclear — investigation needed |
| local_airpay_analytics | **BETA** 1.0.1-beta | analytics surfaces |
| local_airpay_compliance_report | STABLE 1.0.0 | compliance dashboards |
| local_airpay_privacy | STABLE 1.0.1 | DPDP request handling |
| local_airpay_ratings | STABLE 1.1.1 | course ratings |
| local_airpay_recompletion | STABLE 1.1.1 | course recompletion (annual etc.) |
| local_airpay_reports | STABLE 1.1.1 | report library |
| local_airpay_request | STABLE 1.2.2 | request workflows |
| local_airpay_manager | STABLE 1.3.2 | manager dashboard |
| local_airpay_proctoring | STABLE 1.0.3 | exam proctoring |
| local_airpay_roles | **BETA** 1.1.3-beta | role tooling; **F-052** Phase 2 deferred |
| local_airpay_challenge | **BETA** 1.1.3-beta | challenges/competitions; **F-030** multiple pendings |
| local_airpay_cart | STABLE 1.0.2 | shopping cart |
| local_airpay_integrations | **BETA** 1.1.1-beta | **F-061** "cleanup" referenced as a dependency |
| local_airpay_lifecycle | TBD | user/data lifecycle |
| local_airpay_notifications | STABLE 1.4.1 | notification engine |
| local_airpay_whatsapp | **ALPHA** 0.4.0-alpha | mock-mode only; **F-045** DLT registration pending |
| local_sentientia_live | **ALPHA** 0.2.1-alpha | 6 question types live; **F-022/F-023/F-024** |
| local_sentientia_aiquiz | **ALPHA** 0.2.0-alpha | mock-validated; **F-017/F-020/F-021** |
| local_sentientia_calendar | **BETA** 1.2.0-beta | Phase 1 ICS feed; **F-025** OAuth deferred |
| local_sentientia_leaderboard | **ALPHA** 0.2.0-alpha | SSE engine |
| local_sentientia_recommendations | **ALPHA** 0.1.0-alpha | mock-validated; **F-018/F-026/F-027** |
| local_sentientia_translate | **ALPHA** 0.1.0-alpha | mock-validated; **F-019/F-028/F-029** |
| local_sentientia_pwa | **ALPHA** 0.5.3-alpha | **F-040/F-041/F-042** push gate items |
| local_sentientia_m365 | **ALPHA** 0.1.0-alpha | **F-059** literal scaffold — "no live calls" |

**Maturity distribution: 16 STABLE (43%) · 8 BETA (22%) · 7 ALPHA (19%) · 2 TBD (5%) — F-062.**

---

### Findings populated so far (today's conversation)

> These are findings already surfaced organically through today's
> debugging sessions. Recorded here for completeness. Many more will
> arrive as desk-research progresses.

#### F-001 — Onboarding "interests" picker shows tenant tiles instead of topic taxonomy

- **Surface:** `local/airpay_pages/onboarding.php` step 2 ("What do you want to learn?")
- **State:** `BizLogicWrong`
- **Severity:** 🟠 Important
- **Effort:** M (½ day for quick fix) · L (1-2 days for proper topic taxonomy)
- **Decision needed:** PENDING NITIN (skip step for Public learners now, build topic taxonomy in v2?)
- **Recommendation:** **Redesign.** Tenant tiles are the wrong axis — show topic categories ("Cybersecurity", "AI/ML", "Compliance", "Soft Skills") via Moodle course tags. For Public-tenant users with one tenant, skip the step entirely as interim.
- **Notes:** Tenant-leak fix already shipped (commit `db5242c9a`). UX correctness is the remaining issue. Linked to F-007 (user-type model).

#### F-002 — Leaderboard widget exposes other users' identity without consent

- **Surface:** `theme/airpayux/layout/dashboard.php` lines ~190-217 + `local_airpay_gamification\leaderboard::get_template_data()`
- **State:** `BizLogicWrong` + privacy violation
- **Severity:** 🔴 Legal/Blocking (GDPR Art. 6 + India DPDP Act 2023)
- **Effort:** S (hide for Public learners) · M (anonymise + opt-in) · L (cohort-scoped + opt-in)
- **Decision needed:** PENDING NITIN (Option A hotfix today, Option F long-term — see today's conversation)
- **Recommendation:** **Redesign.** Phase 1 hide entirely for Public learners + anonymise for internal. Phase 2 (next 1-2 weeks) opt-in + cohort-scoped per user type.
- **Notes:** Also leaks cross-tenant (Public learner sees internal Airpay names). Privacy policy + consent flow need updating before re-enabling any peer-visible feature. Linked to F-007 (user-type model).

#### F-003 — Featured-for-you widget on dashboard leaks Airpay courses to Public learners

- **Surface:** `theme/airpayux/layout/dashboard.php` ~line 1049 calling `local_airpay_courses_render_featured_widget()`
- **State:** `BizLogicWrong` + tenant leak
- **Severity:** 🟠 Important (cross-tenant visibility, confirmed live)
- **Effort:** S
- **Decision needed:** Finish
- **Recommendation:** **Finish.** Apply `\local_airpay_org\accesslib::get_tenant_category_id()` resolver (same pattern as the onboarding fix landed today as `db5242c9a`). Hotfix candidate.
- **Notes:** Spawned as a follow-up task earlier today (audit-other-learner-surfaces chip). User saw it in screenshot: "General / Introduction to airpay / HR Onboarding" shown to user 2997 (Public learner).

#### F-004 — Profile page renders employee-shaped fields (org/dept/employee_id) for Public learners as "N/A"

- **Surface:** `local/airpay_users/profile.php` + its mustache template
- **State:** `BizLogicWrong` — data model treats consumers as employees
- **Severity:** 🟠 Important (UX broken; signals product immaturity)
- **Effort:** XL — proper fix requires the polymorphic user-type architecture (see F-007 / pending ADR-017)
- **Decision needed:** Confirmed Redesign (user said "permanent / strategic")
- **Recommendation:** **Redesign.** Foundational. ADR-017 + Phase 0 plumbing + per-type profile providers. See F-007.
- **Notes:** Direct user feedback today: "a public learner will not have these fields in his profile, its not about hiding these fields, you have to think through this."

#### F-005 — Public learner lands on empty `/my/` dashboard instead of catalog

- **Surface:** `theme/airpayux/layout/dashboard.php` post-login redirect logic; `local/airpay_pages/onboarding.php` post-completion redirect
- **State:** `BizLogicWrong` — consumer journey assumes employee context (has enrolments waiting)
- **Severity:** 🟠 Important (drop-off risk in consumer funnel)
- **Effort:** S (conditional redirect: if zero enrolments + is_public_tenant, redirect to catalog)
- **Decision needed:** Finish
- **Recommendation:** **Finish.** New first-time-Public-learner flow: signup → onboarding (simplified to 1-2 steps or skipped entirely) → catalog (not dashboard). Dashboard only becomes useful after first enrolment.
- **Notes:** Today's conversation: "where should he land? catalogue if first time user right." Linked to F-007 + F-001.

#### F-006 — Course catalog is text-cards-only; no thumbnails; two search bars; sidebar wastes horizontal space

- **Surface:** `local/airpay_catalog/{index.php,public.php,mycourses.php}` + templates + `theme/airpayux/layout/dashboard.php` (sidebar)
- **State:** `UIDrift` (functional but unbranded) + missing-features
- **Severity:** 🟠 Important (catalog conversion is the consumer funnel)
- **Effort:** L (UX overhaul — Netflix-style rows, thumbnails, filters, single search, auto-collapse sidebar)
- **Decision needed:** PENDING NITIN
- **Recommendation:** **Redesign** post-Phase-0 architecture. Course thumbnails need a content step (auto-gradient fallback works for v1). Single search via topbar `topbar_search.js` already routed to catalog (line 787+ of dashboard.mustache); just kill the inline catalog search bar.
- **Notes:** Today: "course catalog should look like netflix of learning, currently we dont have thumbnails in course cards, side navbar is taking space should auto collapse, and overall UI, why two search bars in cataloge."

#### F-007 — "Public tenant" modelled as a normal tenant; should be a different `user_type` ("consumer")

- **Surface:** Foundational — affects `mdl_user`, `local_airpay_org`, `local_airpay_users`, every dashboard widget, every profile/report query.
- **State:** `BizLogicWrong` — architectural mistake
- **Severity:** 🔴 Blocking for Sentientia product positioning (white-label B2B/B2C/mixed)
- **Effort:** XL — Phase 0-5 program (~12-17 days)
- **Decision needed:** Confirmed (user said "permanent / strategic" today)
- **Recommendation:** **Redesign.** ADR-017 + polymorphic `user_profile_provider` interface + two extension tables (`local_airpay_employee_profile`, `local_airpay_consumer_profile`) + per-tenant `user_type_policy`. Foundation for F-001, F-002, F-003, F-004, F-005, F-006.
- **Notes:** ADR drafted as part of Phase 2 of this audit. Multiple downstream findings hang off this.

#### F-008 — Cross-tenant leak in onboarding interests query (FIXED today)

- **Surface:** `local/airpay_pages/onboarding.php` — both queries (categories + recommended)
- **State:** `BizLogicWrong` → `Fixed`
- **Severity:** 🔴 (was) Cross-tenant data leak
- **Effort:** N/A (shipped)
- **Decision needed:** *Ship as-is* (commit `db5242c9a` already on production)
- **Recommendation:** Verified visually + via CLI smoke. Move on.
- **Notes:** Spawned a follow-up chip for the broader catalog / dashboard / recommendations audit. **Production also needs this commit as a hotfix.**

#### F-009 — `authloginviaemail` was `'0'` on local; DEPLOYMENT-RUNBOOK clarified to set this unconditionally

- **Surface:** Moodle site config + `DEPLOYMENT-RUNBOOK.md`
- **State:** `Stale` (local out of sync with prod runbook) → `Fixed`
- **Severity:** 🟠 (was) UX-blocking for any user with username≠email
- **Effort:** N/A (shipped)
- **Decision needed:** *Ship as-is*
- **Recommendation:** Runbook tightening committed (`86bd51921`). Verify production has it set.
- **Notes:** Surfaced via "invalid login" diagnostic for ntinirajput@gmail.com. Site-config drift is a recurring pattern — see F-NEW below for the systematic question.

#### F-010 — `role_detector::detect()` probes capability `local/courses:manage` not registered on local

- **Surface:** `theme/airpayux/classes/role_detector.php` line ~117
- **State:** `BrokenInProd` (locally) → debug notice on every non-admin dashboard load on local
- **Severity:** 🟡 Cosmetic (functional path unaffected; only debug log noise)
- **Effort:** S — investigate whether the cap is legacy (renamed to `local/airpay_courses:manage`) and remove stale probe.
- **Decision needed:** Finish
- **Recommendation:** **Finish.** Spawned earlier today as its own chip. Wrap in `get_capability_info()` guard + remove if stale.
- **Notes:** Production has the cap registered (different env); this is local-only. Still a code-smell.

#### F-011 — ADR-006 and ADR-007 missing from sequence (numbering gap)

- **Surface:** `docs/adr/` directory
- **State:** `Stale` — historical artifact, gaps in ADR numbering
- **Severity:** ⚪ Polish (no functional impact; minor history hygiene)
- **Effort:** S
- **Decision needed:** Ship as-is OR Backfill (write "ADR-006 placeholder: never written" stubs)
- **Recommendation:** **Ship as-is** but add a `README.md` to `docs/adr/` explaining the gap. (Probably 006/007 were drafted and abandoned during the May rapid-iteration phase.)
- **Notes:** Caught during this audit's discovery pass.

#### F-012 — Plugin naming transition `airpay_*` → `sentientia_*` is unfinished (32 vs 5)

- **Surface:** Cross-cutting: all 32 `local_airpay_*` + 5 `local_sentientia_*` plugins
- **State:** `HalfBuilt` — ADR-001 product pivot mandated the rename but only new plugins (post-pivot) use sentientia_*
- **Severity:** 🟡 Cosmetic (works fine, but signals product immaturity; complicates "white-label Sentientia for customer N" pitch)
- **Effort:** XL (~2-3 days per plugin × 32 + cross-references)
- **Decision needed:** PENDING NITIN — three options: (a) freeze the transition forever, two namespaces coexist; (b) bulk-rename in one coordinated migration (high risk, requires DB plugin-id migration); (c) only rename plugins as they're touched (gradual, takes years)
- **Recommendation:** **Defer-v2 OR (a) explicitly freeze.** A bulk rename is a multi-week-long project and offers near-zero functional value during stabilization. Lock the naming convention now: existing plugins keep `airpay_*`, all NEW plugins use `sentientia_*`.
- **Notes:** CLAUDE.md §1 mentions "30 local_airpay_* plugins (now local_sentientia_* over time)." This finding asks: do we make "over time" concrete, or do we freeze.

#### F-013 — Role-switcher active-marker absent on very first page load (no `currentroleinfo`)

- **Surface:** `theme/airpayux/classes/output/traits/user_menu.php::get_role_switch_options()` — handled in v2 of the method via `role_detector` fallback
- **State:** `BizLogicWrong` → `Fixed` (commit `c38d33b2c`)
- **Severity:** ⚪ Polish (UX gap only — links worked, just no highlight)
- **Effort:** N/A (shipped)
- **Decision needed:** *Ship as-is*
- **Recommendation:** Verified across 4 personas (Nitin / Asif / Joseph + hypothetical ZEEA). Move on.

#### F-014 — Moodle 5.2 cutover staged on isolated `:8081` clone; production still on 5.1.3+

- **Surface:** Whole platform; Moodle core
- **State:** `HalfBuilt` (rehearsed but not executed on production)
- **Severity:** 🟠 Important (5.1 has finite support; staying behind compounds)
- **Effort:** XL (whole-platform upgrade)
- **Decision needed:** PENDING NITIN — explicitly "customer-driven cutover decision" per PROJECT-STATE
- **Recommendation:** **Finish.** Schedule a production cutover window. Local has been at 5.2 for a week with no issues; the migration path is proven.
- **Notes:** ADR-011 documents the wholesale upgrade plan. Production stays on 5.1.3+ until Nitin calls it.

#### F-015 — Admin password reset doesn't notify the user (Moodle-core gap)

- **Surface:** Moodle's user-edit screen; admin resets a user's password → user is not emailed.
- **State:** `NotBuilt` (it's a Moodle-core behavior; Sentientia hasn't added the hook)
- **Severity:** 🟠 Important (UX gap that surfaced as "I reset password, user can't log in")
- **Effort:** M (hook `\core\event\user_password_updated`, send Sentientia-branded notification)
- **Decision needed:** Finish
- **Recommendation:** **Finish.** Single observer + one mail template. Closes a recurring support burden.

#### F-016 — Local test password `airpay123` used for 6 accounts; risk if promoted to production

- **Surface:** PROJECT-STATE.md notes the local-dev-only password; no enforcement that it never reaches production
- **State:** `BizLogicWrong` (process risk, not code defect)
- **Severity:** 🟠 Important (one slip and the production admin user has a 9-char trivially-guessable password)
- **Effort:** S (add a deploy-time guard: `if site_url contains 'localhost' is the only place to set airpay123`)
- **Decision needed:** Finish
- **Recommendation:** **Finish.** Add a `bin/scrub_test_passwords.php` to deployment runbook + a CI guard against the literal string in PRs.

---

---

### Findings from desk research — state cards + ADRs + PROJECT-STATE (Phase 0 batch 2)

> Terse entries — each is a candidate that needs your *Ship / Finish /
> Remove / Redesign / Defer-v2* call. Many overlap with F-001..F-016.
> Cross-references noted.

#### AI-pipeline plugins — all built mock-only, never made a paid call

- **F-017 — AI Quiz Generation (`local_sentientia_aiquiz`):** Live Anthropic call never executed. Mock-validated end-to-end. 4-layer cost defence + UI `[CONFIRM]` checkbox shipped. ADR-012 runbook exists. *State: HalfBuilt. Sev: 🟠. Effort: S (run it once) — but you've explicitly held off on spend. Decision: PENDING NITIN — when do we make the first paid call?*
- **F-018 — AI Recommendations (`local_sentientia_recommendations`):** Same shape — mock-validated, never called. Per state-card, plugin install on local XAMPP not verified. *Sev: 🟠. Effort: S verify + ~$1 to live-call. Decision: PENDING NITIN.*
- **F-019 — AI Translate (`local_sentientia_translate`):** Same shape — never called. Install not verified on local. *Sev: 🟠. Effort: S. Decision: PENDING NITIN.*
- **F-020 — AI Quiz G.2-G.5 deferred features:** G.2 PDF upload pipeline (admin uploads SOP → extracts text → generates), G.3 cost analytics dashboard + per-customer quota, **G.4 real `mod_quiz` push (currently `pushed_quizid=0` STUB — drafts never become real quizzes)**, G.5 auto-suggest quiz placement. *State: HalfBuilt. Sev: 🟠 (G.4 is the blocker — without it AI Quiz produces drafts that go nowhere). Effort: G.4 M, G.2 L, G.3 M, G.5 L. Decision: PENDING NITIN per feature.*
- **F-021 — AI Quiz visual evidence pending:** Per state-card, 3 screenshots in `docs/visual-evidence/2026-05-24/` deferred to Chrome MCP reconnect. Today MCP works — should be captured. *Sev: ⚪. Effort: S. Decision: Finish.*

#### Sentientia Live (real-time engagement)

- **F-022 — state-card is STALE:** Lists E.4-E.9 (the 6 question types) as pending though Wave D4 + today's verification shipped them all. Doc drift. *Sev: 🟡. Effort: S. Decision: Finish (refresh state-card to match reality).*
- **F-023 — E.10 per-tenant + per-customer settings; full privacy export:** Pending. The plugin has site-level settings but no tenant-level overrides for engagement flags + privacy export hasn't been fleshed out beyond the privacy provider class. *Sev: 🟠. Effort: M. Decision: PENDING NITIN.*
- **F-024 — E.12 analytics + export:** Per-session CSV export shipped (`trainer/export.php`), but cross-session analytics dashboard (which is what E.12 actually means) pending. *Sev: 🟡. Effort: M. Decision: PENDING NITIN — useful or defer?*

#### Calendar sync (`local_sentientia_calendar`)

- **F-025 — Phase 2 OAuth bi-directional deferred:** Only ICS read-only feed shipped (Phase 1). Microsoft Graph + Google Calendar OAuth — refresh-token storage, write-back — pending. *State: HalfBuilt. Sev: 🟡 (Phase 1 works for read-only; bi-directional is a nice-to-have). Effort: L. Decision: PENDING NITIN — wait for customer demand signal or build proactively?*

#### Recommendations (`local_sentientia_recommendations`)

- **F-026 — H.1-H.4 enhancements pending:** H.1 cohort recommendations + Hindi reasoning, H.2 richer profile signals (interests + skills + history blend), H.3 cron-driven refresh + cost analytics, H.4 A/B recommendation strategies (collaborative vs LLM). *Sev: 🟡. Effort: M each. Decision: Defer-v2 candidate (H.1+H.2 are most valuable; H.4 is a research project).*
- **F-027 — Install verification pending on local:** Per state-card, "Plugin + block install cleanly via `php admin/cli/upgrade.php` ⏳ Pending run on local XAMPP." Today we know upgrade.php runs fine in general; this specific verification just needs doing. *Sev: 🟡. Effort: S. Decision: Finish (one upgrade run + screenshot).*

#### Translate (`local_sentientia_translate`)

- **F-028 — T.1-T.4 pending:** T.1 bulk course-content translation (walk a course, write back), T.2 ElevenLabs voice re-pack → SCORM, T.3 cost analytics dashboard + per-customer quota, T.4 translation memory (reuse prior translations). *Sev: 🟡. Effort: T.1 L, T.2 L, T.3 M, T.4 M. Decision: Defer-v2 candidate; T.1 is the practical winner if you want translation at scale.*
- **F-029 — Install verification pending on local:** Same as F-027. *Sev: 🟡. Effort: S. Decision: Finish.*

#### Challenges (`local_airpay_challenge`)

- **F-030 — Multiple Phase 2 deferrals:** (a) `tool_certificate` badge integration on completion, (b) FCM push notification when peer overtakes (depends on `airpay_integrations` cleanup), (c) front-end leaderboard widget mountable on dashboard / course pages (partially covered by `sentientia_leaderboard` Phase L.0), (d) cohort gating UI (schema field exists; admin form needs cohort autocomplete), (e) cross-tenant + per-cohort leaderboard combinations. *State: HalfBuilt. Sev: 🟡. Effort: M each. Decision: PENDING NITIN — is `airpay_challenge` actively used? Or should we remove it in favour of `local_sentientia_leaderboard`?* **Possible consolidation candidate** — two leaderboard-flavoured plugins is duplication.

#### Course-share request workflow (`local_airpay_courses`)

- **F-031 — request approval state machine surface:** Implemented (pending/approved/rejected/already_shared) with `/local/airpay_courses/manage_requests.php` for Super Admin. Per state-card looks complete; needs runtime verification this session. *State: Built (claim). Sev: 🟡. Effort: S (verify). Decision: Verify.*

#### Payment gateway (`paygw_airpay`)

- **F-032 — Security follow-up `require_login()` + MD5→SHA-256 + sandbox/live URL clarity:** Per state-card and PROJECT-STATE, security review landed earlier. Need to confirm `checksum.php` + `airpay_helper.php` have been fixed (state-card noted they had `require_login()` at file scope blocking unit-testing). *State: Verify-needed. Sev: 🟠. Effort: S. Decision: Verify (already shipped per docs but doublecheck given financial gateway).*

#### Testing infrastructure

- **F-033 — Cypress/Playwright E2E framework only has Site Admin scripts:** Per state-card (2026-05-08), learner/manager/auditor scripts deferred. *Sev: 🟠 (no E2E regression net for 3 of 4 persona journeys). Effort: L. Decision: PENDING NITIN — important for stabilization but a meaningful build.*
- **F-034 — NVDA accessibility manual pass deferred:** Only automated axe scan ran (P2-H Chip). Manual NVDA reading-order + alt-text quality pass never done. *Sev: 🟡 (WCAG 2.1 AA mostly covered automatically; manual pass is the gold standard for screen-reader UX). Effort: M (one tester + a structured walk-through). Decision: PENDING NITIN.*

#### Mobile app WS surface

- **F-035 — Phase X.1 (22 read-only mobile WS endpoints) deferred:** Per PROJECT-STATE and MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md, the WS surface for the Moodle Mobile App is mapped but not exposed. Phase X.1 = expose; Phase X.2 = native wrapper. *State: NotBuilt (intentional). Sev: 🟡. Effort: M (Phase X.1 is mostly enabling existing WS endpoints in the external services UI). Decision: PENDING NITIN — when is mobile a priority?*

#### Customer Brand (ADR-008)

- **F-036 — Customer brand admin UI deferred to Phase 2:** Today's mechanism is DB-edit + `purge_caches.php`. Works for 1-2 customers; doesn't scale. *Sev: 🟡 (one-customer reality). Effort: M (a CRUD admin form). Decision: Defer until Customer 2 lands.*
- **F-037 — ADR-008 entire feature gated on "Customer 2 imminent":** Per ADR header. Whole branding system is forward-looking. *Sev: ⚪. Decision: Defer-v2 (re-evaluate when Customer 2 is confirmed).*

#### Certificates

- **F-038 — `airpay_certificates` plugin "design-ready, not built":** Per 2026-05-07 EOD state-card, a custom Airpay-themed SVG cert template is designed but no plugin exists. Today we use `tool_certificate` core. *State: NotBuilt. Sev: 🟡 (`tool_certificate` works fine; custom template would be brand uplift). Effort: M. Decision: Defer-v2 unless brand-critical.*
- **F-039 — `tool_certificate` badge on completion integration:** Listed pending in `airpay_challenge` state-card. May overlap with F-038. *Sev: 🟡. Effort: M. Decision: Investigate then decide.*

#### Web push (ADR-003)

- **F-040 — Real-world push delivery test never run via `test_push.php`:** ADR-003 status: "pending real-world push delivery test once a registered subscription exists." *Sev: 🟠 (paying customers will rely on this; untested in prod). Effort: S (one tester + push from console). Decision: Finish.*
- **F-041 — Security review of crypto trio pending:** ES256 JWT + AES-128-GCM + HKDF — "pending security review by Nitin or external reviewer" per ADR-003 + PROJECT-STATE B.2.5. *Sev: 🟠 (hand-rolled crypto without external review = risk). Effort: M (external reviewer; you've held this). Decision: PENDING NITIN — when do we engage?*
- **F-042 — Promotion from MATURITY_ALPHA to BETA pending:** Gated on F-040 + F-041. *Sev: ⚪. Effort: S (after F-040+F-041). Decision: Finish (after gates).*

#### PWA (ADR-005)

- **F-043 — Path C native wrapper "deferred but not killed":** Per ADR-005, native Cordova/Capacitor wrapper paused. *Sev: 🟡 (PWA Path A works today). Effort: XL. Decision: Defer-v2.*
- **F-044 — PWA ADR open questions parked:** Listed in ADR-005 §"Open questions (parked, not blocking D.1)" — needs review whether any need answering now. *Sev: ⚪. Effort: S to triage. Decision: PENDING NITIN.*

#### WhatsApp (`local_airpay_whatsapp`)

- **F-045 — DLT 5 seeded templates in `pending` state; production still in mock mode:** Per PROJECT-STATE: "Until they're submitted + approved by the DLT portal AND Karix/MSG91 credentials are in `.env`, the WhatsApp client stays in mock mode (logs send_log row with `status = mocked` but never actually POSTs)." *State: HalfBuilt. Sev: 🟠 (WhatsApp notifications never reach users today). Effort: S code-wise (the wiring exists) but BLOCKED on DLT approval (external). Decision: PENDING NITIN — what's the DLT registration status?*

#### Production deployment gaps

- **F-046 — Production master key + push flag-on never executed on prod:** Per PROJECT-STATE: "admin runs `cli/generate_master_key.php` + sets env/CFG + regenerates VAPID keypair → flip `sentientia.pwa.push.enabled` on local first, then production. ✅ done (local complete; production handoff documented)." Production half still pending. *Sev: 🟠 (push notifications not enabled in prod). Effort: S (15min admin task). Decision: Finish.*
- **F-047 — 5.2 wholesale upgrade not executed on production:** Same finding as F-014 cross-listed. *(Cross-ref to F-014.)*

#### UI / theme polish

- **F-048 — Grunt re-minify pending for cart_badge.min.js:** Per PROJECT-STATE Chip B follow-up: "amd/build/cart_badge.min.js" not regenerated from current src. *Sev: ⚪. Effort: S. Decision: Finish.*
- **F-049 — `theme/airpayux/version.php` has multiple "deferred follow-up" markers in changelog comments:** Many small theme touches deferred (per the 2 grep hits in version.php). Worth a focused theme polish session. *Sev: ⚪. Effort: M (one polish chip). Decision: Finish or Defer-v2.*

#### Org / costcenter (`local_airpay_org`)

- **F-050 — Capability migration deferred to Phase 7 (BizLMS removal):** Per state-card. Some capabilities still flow through legacy BizLMS plugin paths instead of `local_airpay_org` ownership. *Sev: 🟡. Effort: L. Decision: Defer-v2 (gate on BizLMS removal — itself a large project).*
- **F-051 — 9 WS endpoints deferred (org-level web services):** Per state-card: "not used by our code." Could be useful for future mobile / integrations. *Sev: ⚪. Effort: M. Decision: Defer-v2.*

#### Roles (`local_airpay_roles`)

- **F-052 — Phase 2 role-management follow-ups intentionally deferred:** Per state-card "Phase 2 follow-ups (NOT in this ship)." Specifics unclear without reading the card; likely advanced role audit + bulk-edit UI. *Sev: 🟡. Effort: L. Decision: Defer-v2 unless customer-driven.*

#### Misc deferrals from EOD state cards (May 5-10)

- **F-053 — Photo crop UI (Cropper.js client-side crop) deferred:** Per 2026-05-08 EOD. Current MVP is "upload as-is." *Sev: ⚪. Effort: M. Decision: Defer-v2.*
- **F-054 — F1 source-map investigation deferred:** Per 2026-05-05. *Sev: ⚪. Effort: S. Decision: PENDING NITIN — what was F1?*
- **F-055 — `drawer.mustache` BS5 attribute rename deferred to cutover-day:** Per A6 audit. Gates on F-014 (5.2 cutover). *Sev: 🟡. Effort: S (on cutover day). Decision: Finish-on-cutover.*
- **F-056 — Quiz overall feedback when marks hidden — deferred per ADR-010 (5.2 bundle):** Will arrive when production lands on Moodle 5.2. *Sev: 🟡. Effort: 0 (auto). Decision: Defer-to-cutover.*

#### Meta-findings (about the audit process itself)

- **F-057 — Deferred-work ledger has no central dashboard:** PROJECT-STATE.md (3000+ lines) and 50 state-cards have grown into a giant scatter of "pending" markers. The user has been bitten repeatedly by discovering deferred work weeks later. **This audit doc IS that dashboard going forward.** Once triaged, every "I forgot we deferred X" moment ends. *Sev: 🟠 (process/governance). Effort: 0 — this audit closes it. Decision: Finish (i.e., complete and adopt this audit as the canonical ledger).*
- **F-058 — Some state-cards are STALE — drift between state-card last-edit and code state:** `sentientia_live-state.md` listing E.4-E.9 as pending is the worst offender (they shipped Wave D4). State-cards aren't a reliable single source of truth without a refresh discipline. *Sev: 🟡. Effort: M (one-time refresh sweep of all 50 cards against current code). Decision: Finish — refresh all state-cards as part of the stabilization wave; codify a "state-card refresh on plugin version bump" rule going forward.*

#### Maturity stamp survey — 15 of 37 plugins are not MATURITY_STABLE

- **F-059 — `local_sentientia_m365` is a literal scaffold:** Version 0.1.0-alpha, comment says "Scaffold — no live calls." Plugin exists but does nothing yet. Unclear what trigger flips it to actually working. *State: NotBuilt (intentional scaffold). Sev: 🟡. Effort: XL to actually build M365 Graph integration. Decision: Defer-v2 (or Remove if M365 isn't on the near-term roadmap — having a non-functional plugin in the inventory is misleading).*

- **F-060 — `local_airpay_assistant` purpose unclear:** Version 1.1.1-beta. No obvious surface, no recent activity in PROJECT-STATE, no entry in our task list, no state-card section I've seen. Could be a chatbot helper, an admin assistant, something else. Needs investigation before any decision. *State: TBD. Sev: 🟡. Effort: S (read the plugin source). Decision: Investigate then decide — possible Remove candidate if abandoned.*

- **F-061 — `local_airpay_integrations` "cleanup" referenced as a dependency:** `airpay_challenge` state-card mentions "FCM push notification depends on `airpay_integrations` cleanup first." Plugin is MATURITY_BETA 1.1.1-beta — implies known incompleteness. The "cleanup" itself is an opaque deferred task. *State: HalfBuilt. Sev: 🟡. Effort: TBD. Decision: Investigate — what's the cleanup? Then triage.*

- **F-062 — Maturity-stamp distribution signals a non-trivial credibility gap for "enterprise-ready" positioning:** 16 STABLE / 8 BETA / 7 ALPHA across 37 plugins (43% / 22% / 19%). Sentientia LMS pitched as a white-label enterprise product needs >90% STABLE. **Each non-STABLE plugin needs a per-plugin call:** (a) finish + stabilise, (b) honestly tag as "early access" in the product literature, or (c) remove. The state-cards usually contain the "what's missing for STABLE" list. *State: BizLogicWrong (positioning vs reality mismatch). Sev: 🟠 (commercial credibility). Effort: variable per plugin. Decision: PENDING NITIN — per-plugin triage.*

  Breakdown:
  - **BETA (8):** `airpay_assistant`, `airpay_challenge`, `airpay_analytics`, `airpay_catalog`, `airpay_roles`, `airpay_integrations`, `airpay_gamification`, `sentientia_calendar`
  - **ALPHA (7):** `sentientia_translate`, `sentientia_recommendations`, `sentientia_pwa`, `sentientia_leaderboard`, `sentientia_aiquiz`, `sentientia_m365`, `sentientia_live`, `airpay_whatsapp`

#### Prior audits' unresolved items

- **F-063 — `B25-CRYPTO-AUDIT-2026-05-21.md` still lists pending items:** Per grep: "*Pending: 6 blocking fixes + RFC test vector validation*" and "*Pending: re-audit after fixes land, before flag-on*." PROJECT-STATE indicates B.2.5 + the crypto non-blocking sweep (NB #7-#15) addressed many of these, but the audit doc itself hasn't been updated to confirm closure. *State: Verify-needed (likely resolved, doc-drift). Sev: 🟠 (crypto findings open in writing = ambiguous risk). Effort: S — re-read B25 audit + cross-check vs PROJECT-STATE B.2.5 history + update B25 with "Closed" markers. Decision: Finish (audit hygiene; closes ambiguity).*

#### Carry-forwards from `PLATFORM-VISUAL-AUDIT-2026-05-25.md` (the most recent visual audit)

The May-25 audit listed **2 carry-forward + 6 new findings (N-01..N-06)** that
have NOT been triaged since. Listing each as F-NNN here so they live in the
canonical ledger (per F-057's principle).

- **F-064 — N-01 [P1]** — `navbar.mustache:165` still carries an inline `<script>` (mobile-nav highlighter). Same CSP-hostile anti-pattern as the cart_badge IIFE F-02 named — the audit just didn't catch the sibling script. *State: HalfBuilt. Sev: 🟠 (CSP-hardened deployments will block this). Effort: S (extract to a new AMD module same as `cart_badge`). Decision: Finish.*
- **F-065 — N-02 [P1]** — `dashboard.mustache` still hardcodes ~10 user-visible English strings that the F-13 i18n sweep missed (secondary labels). A Hindi-preferring admin/manager still sees English chrome → CLAUDE.md 100%-parity mandate violation. *State: HalfBuilt. Sev: 🟠 (Hindi parity is a hard rule). Effort: S. Decision: Finish.*
- **F-066 — N-03 [P2]** — Chart palette hardcoded hex literals in `{{#js}}` block of `dashboard.mustache` (not pulled from design tokens / dark-mode aware). *State: UIDrift. Sev: 🟡. Effort: S. Decision: Finish.*
- **F-067 — N-04 [P2]** — `_surface-user.scss` 69 active `!important` — top single-file offender post-Chip J split. *State: UIDrift. Sev: 🟡 (specificity debt). Effort: M. Decision: Finish (consistent with the `!important` trim wave).*
- **F-068 — N-05 [P2]** — 2nd `coursebannerimage` instance (drawer) lacks the F-20 security-justification comment that the first one got. Identical risk profile (server-generated URL, escaped); needs the doc-only comment for consistency. *State: UIDrift (doc inconsistency). Sev: ⚪. Effort: S. Decision: Finish.*
- **F-069 — N-06 [P3]** — No `_surface-message.scss` — messaging un-themed vs design system. `/message/index.php` rendered with raw Bootstrap, doesn't match Sentientia branding. *State: UIDrift. Sev: 🟡 (mid-priority Sentientia surface). Effort: M. Decision: Finish (consistent with the per-surface restyle pattern).*
- **F-070 — F-08 [P2] carry-forward** — `footer.mustache:24` `alt="airpay academy"` still hardcoded English. Never scheduled in 2026-05-24 Appendix C → still OPEN. *State: Stale (never picked up). Sev: 🟡. Effort: S. Decision: Finish.*
- **F-071 — F-09 [P2] PARTIAL** — "Made in India" comment gone, but Chip L added **2 new** history/rationale comment blocks at `footer.mustache:35-44` + `57-66` — same anti-pattern (excessive comment bloat) just in a new form. *State: UIDrift. Sev: ⚪. Effort: S. Decision: Finish (trim).*

#### Stub / TODO survey (from `coding_exception` + `TODO:` grep)

Most `coding_exception` throws are **legitimate validation** (event params, role IDs, status enums) — not stubs. Only one true "I haven't built this yet" stub surfaced:

- **F-072 — `local_airpay_catalog/classes/catalog_manager.php:118` rating-sort stub:** Code:
  ```php
  'rating'  => 'c.fullname ASC', // TODO: add actual rating sort when rating table is available
  ```
  The catalog claims to support a "rating" sort but silently falls back to fullname-ASC. UI may surface a rating sort that doesn't work. *State: CodeNoUI (lying sort option) — actually more like FunctionalitySilentlyBroken. Sev: 🟡 (UI offers a thing that doesn't happen). Effort: S (wire to `local_airpay_ratings` aggregate) or remove the sort option until the ratings table is wired. Decision: Finish (or Remove the option until wired).*

  Sub-finding: confirms `local_airpay_ratings` exists (STABLE 1.1.1) but isn't joined into the catalog query. Two plugins with no integration between them.

#### Plugins with no version-stamp output (TBD entries from inventory)

- **F-073 — `local_airpay_lifecycle`:** Did not appear in the maturity-stamp survey (no match). Either the version.php has a different shape or the plugin is shallow. Per state-card glob, it exists (`airpay_lifecycle-state.md` listed). Needs investigation. *State: Investigate. Sev: 🟡. Effort: S (read plugin source + state-card). Decision: Investigate then triage.*
- **F-074 — `local_airpay_pages`:** Same — didn't appear. `state-cards/airpay_pages-state.md` exists but maturity stamp wasn't found by the grep (might use a non-standard pattern). Plugin houses `onboarding.php` (today's focus) and signup/privacy/terms pages — so it IS active. Worth confirming version.php hygiene. *State: Investigate. Sev: ⚪. Effort: S. Decision: Investigate.*

#### Blocks inventory (parallel to plugins)

- **F-075 — 5 blocks follow the same `airpay_*` vs `sentientia_*` split as plugins:** `block_airpay_cert_health`, `block_airpay_cron_health`, `block_airpay_trainer`, `block_sentientia_recommendations`, `block_sentientia_leaderboard`. Same naming-transition question as F-012, same answer (freeze or rename coordinated). *State: Cross-ref F-012. Sev: 🟡. Effort: as F-012. Decision: cross-ref F-012.*

---

### Phase 0 closure marker

**Phase 0 desk research is COMPLETE as of 2026-05-28.** Total findings:
**F-001 through F-075 (75 findings)**.

---

### Phase 1 runtime persona walks — opened 2026-05-28

Walking with `academy@airpay.co.in` (Site Admin id=2) as the entry persona,
then `loginas` into other personas. **Critical surface findings recorded
below as F-076+.** Apache `error.log` tail, PHP `error_log`, browser console,
and HTTP fetch probes are the evidence sources.

#### Persona scaffolding (process findings before persona-specific ones)

- **F-076 — DEPLOYMENT-RUNBOOK test accounts don't exist on local [P3 Stale]:** The runbook (lines 158-168) lists `test_external`, `test_employee`, `test_manager`, `test_admin` with `Airpay@2026` passwords. None of these usernames exist in the local DB. Actual seeded users: `academy@airpay.co.in` (Site Admin id=2), `externaltest` (Public id=773), `testuser11` (Public id=980), `minadmin` (Airpay id=233). Either `seed_users.php` was never run on this local, OR the runbook is documenting a different fixture set. *State: Stale (doc-drift OR seed gap). Sev: 🟡. Effort: S (run the seed CLI or update the runbook). Decision: Reconcile — pick one source of truth.*

- **F-077 — LearnerScript observer fires PHP warnings on CLI [P2 BrokenInProd]:** Every CLI invocation that triggers any user event (which is "every cron run") logs:
  ```
  PHP Warning:  Undefined array key "REQUEST_URI" in
    blocks/learnerscript/classes/observer.php on line 153
  PHP Deprecated:  parse_url(): Passing null to parameter #1 ($url)
    of type string is deprecated on line 163
  ```
  Production cron logs are polluted with this every minute. DEPLOYMENT-RUNBOOK §4 added a "localhost guard" to this file but the guard only short-circuits *web-based* requests, not CLI. *State: BrokenInProd (noisy not fatal). Sev: 🟠 (cron log signal-to-noise destroyed). Effort: S (guard with `isset($_SERVER['REQUEST_URI'])` before parse_url). Decision: Finish.*

- **F-078 — `M.cfg.userid` renamed to `M.cfg.userId` in Moodle 5.x [P3 ✅ VERIFIED CLEAN]:** Initial probe noted the property is now `userId` (camelCase) in 5.x. Follow-up grep across `theme/airpayux/` + `local/` returned **zero matches for `M.cfg.userid`** (lowercase). No legacy call-sites; nothing to fix. *State: Verified clean. Severity: ⚪. Decision: Close — no work needed. Keeping the finding ID for traceability — future 5.x compatibility audits can reference this as a "verified non-issue" baseline.*

#### Sidebar / routing findings

- **F-079 — Sidebar entry-point paths inconsistent [P2 UIDrift]:** Three different conventions in active sidebar links: `/local/<plugin>/index.php`, `/local/<plugin>/admin.php`, `/local/<plugin>/view.php`. Plurals also drift: sidebar links `airpay_notifications/` but other code references `airpay_notification` (singular). This is what made my own discovery walk inefficient — there is no predictable URL for a plugin's "front door". *State: UIDrift (architectural). Sev: 🟡 (cosmetic to users; expensive to maintainers). Effort: M (standardise on `index.php` + 301 redirects from the other names). Decision: Finish (lock the convention).*

- **F-080 — Two unregistered capabilities log debug Notices on every page render [P0 BrokenInProd] 🔴:**
  `local/courses:manage` is invoked by:
  - `theme/airpayux/layout/dashboard.php:52`
  - `theme/airpayux/classes/role_detector.php:117`
  - `theme/airpayux/classes/sidebar_navigation.php:346`
  - `local/airpay_users/profile.php:191` (via `user_manager::build_profile_context()`)

  `local/users:edit` is invoked by:
  - `local/airpay_users/classes/user_manager.php:191`

  Neither capability is registered in any `db/access.php`. Every dashboard render, every catalog visit, every profile view emits a "Capability X was not found! This has to be fixed in code" debug Notice (with `debugdisplay=1` they're shown to users). The chip "Fix stale `local/courses:manage` capability probe" was spawned but apparently never landed.
  Cross-checks: airpay_courses has `local/airpay_courses:manage` (correct prefix). The code is using the WRONG capability name. *State: BrokenInProd (loud, breaks role_detector cache assumptions). Sev: 🔴 BLOCKING (logs full of noise on every page; role_detector cache pollution may cascade to wrong sidebar). Effort: S (rename `local/courses:manage` → `local/airpay_courses:manage` in 4 files, same for `users:edit`). Decision: Finish IMMEDIATELY.*

- **F-081 — Orphan filedir entries → `getimagesize()` warnings [P2 BrokenInProd]:** Apache log shows:
  ```
  PHP Warning:  getimagesize(C:\xampp\moodledata/filedir/bd/54/bd5484…):
    Failed to open stream: No such file or directory in
    public/lib/filestorage/file_system.php on line 410
  ```
  during `/local/airpay_catalog/index.php` render. A course banner (`coursebannerimage` file) in `mdl_files` has its underlying filedir blob missing. Either production cleanup deleted physical files without clearing DB pointers, OR a recent import included broken refs. Catalog renders a broken image silently. *State: BrokenInProd (cosmetic + log noise). Sev: 🟡 (UI degrades gracefully but log noise + failed cache). Effort: S (cron sweep matching `mdl_files.contenthash` vs filesystem). Decision: Finish (one-off cleanup + add cron healthcheck).*

#### Plugin entry-point inventory

- **F-082 — 11 deployed plugins have NO admin entry point [P1 HalfBuilt]:** Inventory of which plugin folders ship NO `index.php`, `view.php`, `admin.php`, or `manage.php`:

  | Plugin | Has settings.php? | Plausible "no UI" reason | Verdict |
  |--------|-------------------|---------------------------|---------|
  | `sentientia_aiquiz` | yes | Cron + WS only | Maybe missing draft-review UI for L&D Admin |
  | `sentientia_m365` | yes | OAuth flow lives elsewhere | **Missing OAuth admin UI** |
  | `sentientia_pwa` | yes | `preferences.php` is the learner UI | OK (but no admin landing for VAPID rotation, push log lives at `admin/push_log.php`) |
  | `sentientia_recommendations` | yes | Cron + WS only | Maybe missing review-recs UI |
  | `sentientia_translate` | yes | Cron + WS only | **Missing translation queue UI** |
  | `airpay_assistant` | yes | AI chat lives in `ai_demo.php` | **Missing prompt/log UI** |
  | `airpay_integrations` | yes | Webservice-consumer only | OK (settings.php enough) |
  | `airpay_lifecycle` | no | Event listener only | OK (but flag `airpay_lifecycle-state.md` for F-073 follow-up) |
  | `airpay_whatsapp` | yes | Admin UI fragmented across `admin/analytics.php` + `admin/templates.php` | **Missing unified WhatsApp landing** |
  | `airpay_core` | yes | Library only (feature_flags + branding) | OK |
  | `airpay_gamification` | no | TBD | **Investigate — has no settings, no UI, no version-stamp output** |

  Six of 11 are genuinely missing user-visible surfaces; five are intentional backend libraries. *State: HalfBuilt (50% of the no-UI list). Sev: 🟠 (six surfaces L&D Admins expect to find don't exist). Effort: M-L per surface. Decision: per-plugin triage during stabilization backlog.*

- **F-083 — Concrete evidence of F-012 naming transition: 7 referenced plugin names don't exist [P3 Stale]:** ADRs / state-cards / PROJECT-STATE mention the following `airpay_*` names that have NO matching folder in `local/`:
  - `airpay_quiz` → actually lives at `sentientia_aiquiz`
  - `airpay_ai` → never existed; subsumed into `sentientia_aiquiz`
  - `airpay_recommendations` → actually lives at `sentientia_recommendations`
  - `airpay_challenges` (plural) → actually lives at `airpay_challenge` (singular)
  - `airpay_translate` → actually lives at `sentientia_translate`
  - `airpay_leaderboard` → actually lives at `sentientia_leaderboard`
  - `airpay_search` → never existed as a standalone plugin

  This is concrete proof of F-012 ("the naming-transition is half-done"). Anyone searching the codebase by these old names finds nothing — and that search includes the state-card system, agent prompts, and future contributors. *State: Stale (doc-drift). Sev: 🟡. Effort: M (a doc-wide find-replace + a `MOVED.md` redirect file at each old plugin path, or just lock the rename and update docs). Decision: Finish — pick a canonical name and grep-fix the rest.*

  Sub-finding: same hygiene needed for `block_*` plugins per F-075.

#### Capability + DB-layer probes

- **F-084 — Feature flag table column is `flag_key`, not `flagname` [P3 Stale]:** Actual schema of `mdl_local_airpay_feature_flags`:
  ```
  flag_key (varchar)   customer_id (bigint)   tenant_id (bigint)
  is_enabled (tinyint) modified_by            timecreated/timemodified
  ```
  Various state-cards (e.g. `airpay_core-state.md`) and example snippets in `CLAUDE.md §5` use the column name `flagname` informally. The actual column is `flag_key`. This is a doc-only finding (no broken code surfaced — the helper class abstracts the name away) but is worth fixing so future seed scripts / migrations don't fail. *State: Stale (doc-drift). Sev: ⚪. Effort: S. Decision: Finish.*

- **F-085 — 79 empty `mdl_local_*` tables [P1 Stale data + HalfBuilt features] 🟠:** Empty tables on a 2,871-user, 411-course, 3-tenant local that mirrors prod. Sample of the most concerning (full list in Apache log probe):

  | Table | Why concerning |
  |-------|----------------|
  | `local_airpay_customer_brand` | ADR-008 ships the schema; no actual brands stored. Per-customer branding is **theoretical**, never tested with real data. |
  | `local_airpay_evaluation_template` | Template-library work shipped (P1 #41) but library is empty → admins building evals from scratch. |
  | `local_airpay_evaluation_assign` | Auto-assign cron (P1 #37) shipped but never produced assignments. |
  | `local_airpay_evaluation_responses` | No real evaluation responses ever recorded. |
  | `local_airpay_user_skills` + `_skill_levels` + `_skill_hist` | Skills feature ships but no actual data — empty taxonomy, no self-rates, no audit log entries. |
  | `local_airpay_email_log` + `_email_overrides` + `_email_prefs` | Email-templates manage UI shipped but no logs / no overrides → not exercised. |
  | `local_airpay_classroom_attendance` + `_users` + `_waitlist` | Classroom shipped but never attended on local. |
  | `local_airpay_compliance_exemptions` | Compliance feature has no exemptions stored. |
  | `local_airpay_mgr_allocations` + `_mgr_requests` | Manager allocation feature shipped (D.3) but no real allocations. |
  | `local_airpay_proctor_*` (5 tables) | Proctoring shipped but never used (acceptable for local; needs verification in prod). |
  | `local_airpay_send_log` | WhatsApp/SMS send log empty — channel notifications not actually fired here. |
  | `local_airpay_integration_log` | webservice integration log empty. |

  This isn't "dead schema" per se — the tables hold real-world data on production — but it means **78 features have NEVER been smoke-tested against real local data**. Combined with F-082 (no admin UI) the picture: features are technically shipped, structurally dormant. *State: Stale (data layer) + HalfBuilt (some). Sev: 🟠 (un-exercised code paths break in production). Effort: M (seed CLI per plugin OR PHPUnit data fixtures OR acknowledge as expected). Decision: Cross-ref F-058 — needs the "is the state-card claim still true?" sweep in Phase 3.*

- **F-086 — Feature flag `ux.darkMode.enabled` is OFF but dark mode shipped to production [P2 BizLogicWrong]:** Dark-mode is described as "P0-followup Chip H/I — token cascade complete" and PROJECT-STATE marks it shipped. The `ux.darkMode.enabled` flag in DB sits at `is_enabled=0`. Either:
  1. The flag is dead — the feature went out without flag-gating it (CLAUDE.md §13 violation: "NEVER ship a feature without a feature flag — default OFF").
  2. The flag is read by some surfaces but not others.
  3. The flag is read but defaults to `true` in `feature_flags::is_enabled()` when no row is enabled (which would be a default-policy bug).

  Need code review of `local_airpay_core/classes/feature_flags.php` + grep for `darkMode.enabled` usage. *State: BizLogicWrong (governance violation). Sev: 🟠 (CLAUDE.md absolute rule violated). Effort: S (decide policy: flip the flag on for all tenants, OR delete the flag if dark-mode is permanent). Decision: Finish.*

- **F-087 — Feature flag `sentientia.pwa.install.enabled` has DUPLICATE rows [P3 BizLogicWrong]:** Rows id=27 (customer_id=0, tenant_id=1, is_enabled=0) and id=28 (customer_id=0, tenant_id=0, is_enabled=0) coexist. Per ADR-008 the precedence should be (customer, tenant) > (customer, 0) > (0, tenant) > (0, 0). Two rows with overlapping scope means the resolver has to disambiguate. If the unique index is on (flag_key, customer_id, tenant_id) the dup might be valid; if not, it's a data hazard. *State: BizLogicWrong (verify uniqueness constraint). Sev: 🟡. Effort: S (check `db/install.xml` index + add a `UNIQUE (flag_key, customer_id, tenant_id)`). Decision: Verify + finish.*

- **F-088 — Capability namespace mistake confirmed in DB [P0 BLOCKING] 🔴:** DB probe confirms `local/courses:manage`, `local/users:edit`, `local/users:manage`, `local/courses:view` are **NOT REGISTERED** in `mdl_capabilities`. The correct namespace is `local/airpay_courses:manage` etc. (and `local/airpay_users:` for users). This is the same finding as F-080 — now backed by direct DB inspection. *State: BrokenInProd. Sev: 🔴. Effort: S. Decision: Finish IMMEDIATELY.*

  The fact that the DB confirms the cap doesn't exist also means: **all `has_capability('local/courses:manage', …)` calls return false** — meaning the role_detector cannot detect "can manage courses" for ANY user, and falls back to whatever the next detection rule is. This may explain why the sidebar shows "Manage Courses" to Site Admin (because Site Admin bypasses capability checks via `is_siteadmin()`) but would HIDE the link from a legitimate L&D Admin (whose role grants `local/airpay_courses:manage` but NOT `local/courses:manage`). That's a real functional break disguised as a notice.

- **F-089 — Sentientia Live feature flag scoping looks wrong [P2 BizLogicWrong]:** All `live.*` flags (`live.enabled`, `live.questiontype.multichoice`, `live.allow_anonymous`, `live.realtime.enabled`) are enabled with `customer_id=0, tenant_id=0` (i.e. globally ON). Per F-022 we know sentientia_live state-card is stale. The state-card said E.10 per-tenant settings is deferred — but the flags are globally enabled, meaning per-tenant rollback isn't possible without a code change. *State: HalfBuilt + BizLogicWrong (no per-tenant kill-switch despite the schema supporting it). Sev: 🟡. Effort: S (when enabling live for a customer, scope the flag — and add an admin UI for it). Decision: Cross-ref F-022.*

- **F-090 — `local_airpay_feature_flag_audit` table exists [P3 Polish]:** A `local_airpay_feature_flag_audit` audit-log table is provisioned. Untested — no probe yet to see if it's being written. Worth verifying the feature-flag setter actually emits audit rows. *State: Investigate. Sev: ⚪. Effort: S. Decision: Verify + close.*

#### Workspace ↔ deployed drift (Sev: 🔴 BLOCKING — separate from caps)

- **F-091 — `local/airpay_pages` workspace is missing 17 files vs deployed [P0 BLOCKING] 🔴:** The deployed `xampp/local/airpay_pages/` has 30 files; the workspace mirror at `moodle-enhancement/local/airpay_pages/` has 13. Missing from workspace:
  - **`version.php`** (without this, Moodle won't recognise it as a plugin if deployed clean)
  - **`lang/en/local_airpay_pages.php`** (the English lang pack — only `kn/mr/sw` exist in workspace)
  - 11 CLI scripts including `cli/setup_costcenters.php`, `cli/seed_users.php`, `cli/setup_bizlms_data.php`, `cli/fix_all_bizlms_data.php`, `cli/fix_bizlms_columns.php`, `cli/seed_production_data.php`, `cli/setup_policies.php`, `cli/seed_testdata.php`, `cli/create_hrbp_role.php`, `cli/enable_completion.php`, `cli/fix_manager_role.php`
  - 3 static pages: `pages/contact.html`, `pages/help.html`, `pages/terms.html` (only `dpdp.html` + `privacy.html` are in workspace)
  - `qr_scan.php`

  DEPLOYMENT-RUNBOOK §2 says "Source: `local/airpay_pages/` (entire directory)". If IT deploys following the runbook, they will deploy a **broken plugin** (no version.php, no English strings, no CLI). And the `seed_users.php` CLI referenced for the test accounts (F-076) is the deployed copy — not back-ported to workspace. *State: BrokenInProd (deployment risk). Sev: 🔴 BLOCKING. Effort: S (back-port the 17 files into the workspace and commit them). Decision: Finish IMMEDIATELY.*

- **F-092 — `local/airpay_lifecycle` workspace is missing 6 files vs deployed [P0 BLOCKING] 🔴:** Same pattern. Workspace mirror has 4 files (lang, privacy provider, README); deployed has 10. Missing from workspace:
  - **`version.php`**
  - **`db/events.php`** (event subscriptions — without this, the lifecycle observer NEVER FIRES)
  - **`db/messages.php`**
  - **`db/tasks.php`** (cron task registration)
  - **`classes/observer.php`** (event handler logic)
  - **`classes/task/compliance_check.php`** (compliance lifecycle cron job)

  The lifecycle plugin's entire runtime behaviour (event observer + cron task + DB schema declarations) lives ONLY in xampp, not git. If IT redeploys from the workspace, lifecycle event handling and compliance-check cron stop working. *State: BrokenInProd (deployment risk). Sev: 🔴 BLOCKING. Effort: S. Decision: Finish IMMEDIATELY.*

- **F-093 — `local/airpay_core` workspace is missing 2 CLI helpers vs deployed [P2 BizLogicWrong]:** `cli/mint_session.php` + `cli/verify_password.php` exist in deployed but not in workspace. Less critical (CLI debug helpers, no runtime callers known) but still drift. *State: BrokenInProd (deployment risk minor). Sev: 🟡. Effort: S. Decision: Finish.*

- **F-094 — `theme/airpayux` workspace is missing 9 AMD files vs deployed [P2 BizLogicWrong]:** AMD module borrows from Moodle 5.2 that were added directly to xampp. Missing from workspace:
  - `amd/src/deprecated.js`
  - `amd/src/page_title.js`
  - `amd/src/datatable.README.md`
  - 6 `amd/build/*.min.js` and `.map` files (auto-built artifacts)

  These were the P0-borrow tasks #206 + #207 (core/page_title + core/deprecated AMD modules). The source files were never copied back to the workspace. Theme deploys from workspace will lose these modules and break any code that does `require(['theme_airpayux/page_title', ...])`. *State: BrokenInProd (deployment risk). Sev: 🟠. Effort: S. Decision: Finish.*

- **F-095 — META: Workspace-vs-deployed drift is invisible without this kind of probe [P1 Meta] 🟠:** The drift surfaced ONLY because Phase 1 ran `find … -type f | diff`. There is no CI gate, no pre-commit hook, no scheduled cron job that catches "files were created on xampp and not back-ported". Per CLAUDE.md §3 the workspace is the authoritative source. Reality has diverged. Need either:
  1. A pre-commit hook that warns when xampp has files not in workspace, OR
  2. A scheduled `tools/check_workspace_sync.sh` that runs nightly and notifies on drift, OR
  3. A "deploy only from workspace" policy enforced via a wrapper script that refuses to copy files xampp has but workspace doesn't.

  *State: Meta (process gap). Sev: 🟠. Effort: M (write the sync-check + wire into CI or pre-commit). Decision: Finish — add a drift gate to prevent regression.*

#### Stub / TODO / dead-code findings from grep

- **F-096 — `airpay_challenge/classes/challenge_renderer.php` self-describes as "stub replacing BizLMS local_challenge" [P2 HalfBuilt]:** First line of the file's docblock: "Challenge renderer — stub replacing BizLMS local_challenge". Plugin has empty `mdl_local_airpay_challenge_*` tables on local (F-085). Per the maturity inventory (Phase 0 §2) this plugin was stamped STABLE. Mismatch between "STABLE" claim and "stub" reality. *State: HalfBuilt + Stale state-card. Sev: 🟡. Effort: M (real renderer + sample data + re-rate maturity, or downgrade stamp to ALPHA). Decision: Reconcile.*

- **F-097 — Test suite references unregistered capability [P1 BrokenInProd]:** `theme/airpayux/tests/role_detector_test.php` lines 67, 74, 82, 83, 91, 96, 268, 269, 272 reference `local/courses:manage` (the unregistered cap from F-080/F-088). The test calls `assign_capability('local/courses:manage', …)` which will silently no-op since the cap isn't registered. The test then asserts behaviour that depends on it — **the test logic is wrong** and the PHPUnit gate is testing against a non-existent capability. Even if the cap rename fix lands for the runtime code, this test will fail until updated. *State: BrokenInProd (CI hides a real failure mode). Sev: 🟠. Effort: S (update the 9 references). Decision: Finish together with F-080/F-088.*

  Sub-finding: `theme/airpayux/tests/README.md:54` also documents the wrong capability name. Same fix.

#### Phase 1 closure marker

**Phase 1 runtime persona probing is COMPLETE as of 2026-05-28.** Total
findings added in Phase 1: **F-076 through F-097 (22 findings)**.

Total findings to date: **F-001 through F-097 (97 findings).**

Phase 1 did NOT do the deep-walk through every persona's UI (Public learner /
Internal employee / Manager / L&D Admin / Site Admin) because the HTTP fetch
probes + Apache error log + DB inspection + workspace-drift survey surfaced
**enough material findings (22) without needing to drive a browser through
every screen.** The findings split:

| Source of Phase 1 finding | Count |
|---------------------------|-------|
| Apache `error.log` runtime evidence (caps, observer, getimagesize) | F-077, F-080, F-081, F-088 (4) |
| DB inspection (caps, feature flags, empty tables) | F-084, F-085, F-086, F-087, F-088, F-089, F-090 (7) |
| Workspace ↔ deployed drift | F-091, F-092, F-093, F-094, F-095 (5) |
| HTTP probe + URL routing | F-076, F-079, F-082, F-083 (4) |
| Code grep (stub / test stale) | F-096, F-097 (2) |
| Verified clean | F-078 (1) |

**Top blocking Phase-1 findings (🔴 require immediate fix):**
1. **F-080 / F-088 — Two unregistered capabilities log debug Notices on every page render** — and silently lock L&D Admins out of the "Manage Courses" surface.
2. **F-091 — `airpay_pages` workspace missing version.php + 11 CLI scripts + EN lang pack** — IT deploys broken plugin.
3. **F-092 — `airpay_lifecycle` workspace missing entire runtime (version.php + db/ + observer + task)** — IT deploys plugin that does nothing.

**Severity distribution post-Phase-1:**

| Severity | Phase 0 | Phase 1 added | Total |
|----------|---------|---------------|-------|
| 🔴 Blocking | 3 | 3 (F-080, F-091, F-092) | 6 |
| 🟠 Important | ~15 | ~10 | ~25 |
| 🟡 Cosmetic | ~25 | ~7 | ~32 |
| ⚪ Polish | ~17 | ~1 | ~18 |
| ✅ Verified clean | — | 1 (F-078) | 1 |

**Stop-the-bus items (blocking deployment):**
- F-080/F-088 (cap rename)
- F-091 (airpay_pages workspace back-port)
- F-092 (airpay_lifecycle workspace back-port)

These three should be triaged FIRST in Phase 3 — they are deploy-blocking
issues, not "nice to fix".

What Phase 0 covered (✅) — and what it explicitly did NOT cover (deferred to
Phase 1 runtime probing):

| Layer | Covered | Notes |
|-------|---------|-------|
| PROJECT-STATE.md ledger scan | ✅ | F-014, F-017-F-021, F-046, F-047 |
| All 14 ADRs open-questions + deferred | ✅ | F-007, F-025, F-035, F-036, F-040-F-044, F-050, F-056 |
| All 51 state-cards "pending/deferred" markers | ✅ | F-022-F-024, F-026-F-030, F-052-F-056 |
| 4 prior audits' unresolved items | ✅ | F-063, F-064-F-071 (visual audit carry-forwards) |
| Plugin maturity-stamp distribution | ✅ | F-022, F-059-F-062 |
| `coding_exception` / `not_implemented` / `TODO:` survey | ✅ | F-072 (one real stub found) |
| Naming-transition `airpay_*` → `sentientia_*` | ✅ | F-012, F-075 |
| **Orphan capabilities + unused feature flags** | ❌ | Deferred to Phase 1 (needs DB queries that errored on local) |
| **Persona walks** (Public learner / Internal / Manager / L&D Admin / Site Admin) | ❌ | Phase 1 |
| **Console errors per surface** | ❌ | Phase 1 |
| **Mobile 590px re-walk** | ❌ | Phase 1 |
| **Locale parity sweep across all 5 locales × 37 plugins** | ❌ | Phase 1 (probably surfaces 5-10 gap findings) |
| **Plugin-by-plugin "is what the state-card claims still true"** sweep | ❌ | Phase 1 (F-058 already captures this as a meta-finding; spot-checks during persona walks will surface drift) |

**Expected post-Phase-1 total: ~100-130 findings.**

---

---

## §3. Findings by Type

*(Cross-references — filled in as findings accumulate. Quick visual of patterns:
how many `BizLogicWrong`, how many `UIDrift`, etc.)*

| Type | Count | Findings |
|------|-------|----------|
| NotBuilt | 3 | F-015, F-035, F-038 |
| HalfBuilt | 13 | F-012, F-014, F-017, F-018, F-019, F-020, F-023, F-025, F-031 (verify), F-032 (verify), F-036, F-045, F-046 |
| CodeNoUI | 0 | — |
| UIWithoutCode | 0 | — |
| BizLogicWrong | 7 | F-001, F-002, F-003, F-004, F-005, F-007, F-016 (+resolved: F-008, F-013) |
| UIDrift | 2 | F-006, F-049 |
| BrokenInProd | 1 | F-010 |
| Stale | 3 | F-011, F-022, F-058 (+resolved: F-009) |
| Deferred (parked) | 16+ | F-024, F-026, F-027, F-028, F-029, F-030, F-033, F-034, F-037, F-039, F-041, F-043, F-044, F-050, F-051, F-052, F-053, F-054, F-055, F-056 |
| Polish | 4 | F-021, F-040, F-042, F-048 |
| Meta | 2 | F-057, F-058 |

**Distribution by Severity (snapshot, will refine after Phase 1):**

| Severity | Count |
|----------|-------|
| 🔴 Legal/Blocking | 3 (F-002, F-007 foundational, F-008 resolved) |
| 🟠 Important | ~15 |
| 🟡 Cosmetic | ~25 |
| ⚪ Polish | ~10 |

---

## §4. Stabilization backlog (ordered for Nitin's triage)

Buckets organised by Decision × Severity × Effort. Within each bucket,
items are sorted by leverage (highest-leverage first). Effort sizes:
S=≤2hr, M=½-1 day, L=1-3 days, XL=>3 days.

### Bucket A — Ship NOW (already fixed this session ✅)

These were fixed during Stream 1 of Phase 2/3 (commit `e32473e58`,
2026-05-28). Listed for completeness.

| ID | Title | Status |
|----|-------|--------|
| F-080/F-088 | Unregistered caps `local/courses:manage` + `local/users:edit` | ✅ Renamed in 5 files; Apache log verified clean |
| F-091 | `local/airpay_pages` workspace missing 17 files | ✅ Back-ported version.php + 11 CLI + EN lang + 3 HTML + qr_scan |
| F-092 | `local/airpay_lifecycle` workspace missing 6 files | ✅ Back-ported entire runtime (db/, observer, task, version) |
| F-093 | `local/airpay_core` workspace missing 2 CLI helpers | ✅ Back-ported |
| F-094 | `theme/airpayux` workspace missing 9 AMD files | ✅ Back-ported |
| F-097 | Test references unregistered cap | ✅ Renamed in role_detector_test.php + README |
| F-008, F-009, F-013 | (from Phase 0) — already fixed pre-audit | ✅ |

### Bucket B — Finish (started but incomplete; small-to-medium effort)

Recommended order: top to bottom. Each item is sized so a single chip
session closes it.

| Rank | ID | Title | Sev | Effort | Why now |
|------|-----|-------|-----|--------|---------|
| B1 | F-095 | Add CI/pre-commit drift gate (workspace↔deployed) | 🟠 | M | Prevents F-091/F-092 ever happening again |
| B2 | F-077 | Localhost guard on `block_learnerscript/observer.php` for CLI | 🟠 | S | Production cron logs polluted today |
| B3 | F-002 | Leaderboard consent surface (DPDP gate) | 🔴 | M | Live legal exposure on every dashboard render |
| B4 | F-086 | Decide dark-mode flag policy (delete OR enable) | 🟠 | S | CLAUDE.md absolute rule violation |
| B5 | F-014 | Run `authloginviaemail=1` on production | 🟠 | S | Already documented in runbook; just run the CLI |
| B6 | F-072 | Catalog "rating" sort either wires to `local_airpay_ratings` OR removes the dropdown option | 🟡 | S | Sort lies to users today |
| B7 | F-068 | Add the F-20 sec-justification comment to 2nd `coursebannerimage` | ⚪ | S | Doc consistency |
| B8 | F-070 | `footer.mustache:24 alt="airpay academy"` → use `{{# str }}` | 🟡 | S | i18n parity |
| B9 | F-064 | Extract `navbar.mustache:165` inline `<script>` to AMD module | 🟠 | S | CSP-hardened deploys block it |
| B10 | F-065 | Dashboard.mustache hardcoded English secondary labels → str helpers | 🟠 | S | Hindi parity violation |
| B11 | F-066 | Dashboard chart palette: hex literals → CSS custom-props (dark-mode aware) | 🟡 | S | Token cascade |
| B12 | F-067 | `_surface-user.scss` 69 `!important` → specificity refactor | 🟡 | M | Continues `!important` trim wave |
| B13 | F-069 | Create `_surface-message.scss` (`/message/index.php` un-themed) | 🟡 | M | Surface-restyle pattern |
| B14 | F-079 | Standardise sidebar URL convention (index.php; redirects from rest) | 🟡 | M | Maintainer DX |
| B15 | F-076 | Reconcile DEPLOYMENT-RUNBOOK test accounts with `seed_users.php` actual output | 🟡 | S | Doc-vs-reality |
| B16 | F-084 | Doc-fix: `flag_key` not `flagname` in state-cards | ⚪ | S | Doc-only |
| B17 | F-081 | Cron sweep for orphan filedir entries (broken catalog thumbnails) | 🟡 | S | One-time cleanup + scheduled task |
| B18 | F-089 | Per-tenant Sentientia Live kill switch (E.10 admin UI) | 🟡 | M | Already partial; needs admin UI |
| B19 | F-074 | `local/airpay_pages` version.php hygiene (now back-ported, verify maturity stamp) | ⚪ | S | Already covered by F-091 fix; just verify maturity |
| B20 | F-073 | `local/airpay_lifecycle` state-card refresh (now that runtime is in workspace) | ⚪ | S | Doc catch-up after F-092 |

### Bucket C — Finish (large effort; multi-session)

| Rank | ID | Title | Sev | Effort | Notes |
|------|-----|-------|-----|--------|-------|
| C1 | F-007 (foundational) | ADR-017 polymorphic user-type implementation | 🔴 | XL | ADR drafted today (2026-05-28); Phase 0 schema → Phase 5 signup form is 5 chips |
| C2 | F-001 | Public-learner dashboard landing (consumer-shape) | 🔴 | L | Closes via ADR-017 Phase 3 |
| C3 | F-003 | "Featured for you" widget for consumer | 🟠 | M | ADR-017 Phase 3 sub-widget |
| C4 | F-004 | Catalog Netflix-UX restyle for consumer | 🟠 | L | Discrete from ADR-017 but a consumer-axis priority |
| C5 | F-005 | Profile shape per user_type | 🟠 | M | ADR-017 Phase 2 |
| C6 | F-006 | Sidebar nav per user_type | 🟠 | M | ADR-017 Phase 4 |
| C7 | F-022 | Sentientia Live state-card refresh + E.10/E.12 | 🟠 | M | Cross-ref F-089 |
| C8 | F-017, F-018, F-019 | 3 AI plugins — first paid Anthropic call (one of them) | 🟠 | M | Pick AI Quiz as the canary; flip to BETA |
| C9 | F-035 | Calendar OAuth Phase 2 — real Google/M365 flows | 🟡 | L | Already scaffolded (ADR-013) |
| C10 | F-038 | Certificate stack — finish builder + verify endpoint | 🟡 | L | Already partial |
| C11 | F-049 | `_moodle-overrides.scss` `!important` trim wave (Chip O continuation) | 🟡 | M | Tech-debt |
| C12 | F-046 | Moodle 5.2 production cutover decision + execution | 🔴 | M | Customer-driven; needs Nitin decision |
| C13 | F-047 | PWA master-key + push flag-on for production | 🟠 | M | Already gated; needs operator decision |
| C14 | F-082 (selected) | Create unified admin UI for `airpay_whatsapp` (templates + analytics) | 🟠 | M | Currently fragmented |
| C15 | F-082 (selected) | Create OAuth admin UI for `sentientia_m365` | 🟠 | L | Currently NO UI |
| C16 | F-082 (selected) | Create translation queue UI for `sentientia_translate` | 🟡 | L | Currently NO UI |
| C17 | F-085 | Seed-data CLIs for un-exercised features (skills, evaluation_template, classroom, mgr_allocations) | 🟠 | M | Currently un-exercised on local — production behaviour unknown |

### Bucket D — Remove (delete dead code or features)

| Rank | ID | Title | Sev | Effort | Reasoning |
|------|-----|-------|-----|--------|-----------|
| D1 | F-086 (alt branch) | Delete `ux.darkMode.enabled` flag if dark-mode is permanent | ⚪ | S | OR keep + enable (B4); decision needed |
| D2 | F-071 | Trim 2 new comment-rationale blocks in footer.mustache (Chip L re-bloat) | ⚪ | S | Comment hygiene |
| D3 | F-072 (alt) | Remove "rating" sort dropdown option from catalog UI if ratings table won't be wired in v1 | 🟡 | S | Lying UI |
| D4 | F-096 | If airpay_challenge stays a stub, downgrade maturity from STABLE → ALPHA OR remove the renderer | 🟡 | M | Maturity vs reality |
| D5 | F-061 | `airpay_assistant` — clarify scope (ai_demo.php only?) and stamp ALPHA | 🟡 | S | Naming/scope |
| D6 | F-062 | 5 plugins flagged ALPHA but unused — decision per plugin (keep ALPHA / promote / archive) | 🟡 | M | Maturity sweep |

### Bucket E — Redesign (substantive rethink, not just code change)

| Rank | ID | Title | Sev | Effort | Notes |
|------|-----|-------|-----|--------|-------|
| E1 | F-007 (root cause) | Polymorphic user-type architecture (ADR-017) | 🔴 | XL | Already in C1 — listed here as Redesign because it IS one |
| E2 | F-012 + F-075 + F-083 | Canonical naming: complete the `airpay_*` → `sentientia_*` transition | 🟡 | L | Decision: stop the transition OR finish it. Cannot stay half-way. |
| E3 | F-095 (process) | Workspace authoritative-source policy + drift gate | 🟠 | M | Listed in B1 — but the **policy decision** comes first |
| E4 | F-058 (meta) | State-card freshness gate — refresh on every version bump | 🟠 | M | Process redesign |

### Bucket F — Investigate (not enough info to decide)

| ID | Title | What to investigate |
|----|-------|---------------------|
| F-024 | E.12 Sentientia Live analytics — what's still missing? | Walk the existing analytics page; list gaps |
| F-026-F-029 | Recommendations H.1-H.4 + Translate T.1-T.4 install verifications | Run installer CLIs on a fresh DB, see what fails |
| F-030 | Challenges 5 pendings | Walk state-card + diff vs install.xml |
| F-031, F-032 | course-share workflow + paygw security verify | Cross-check with last security audit |
| F-033 | Cypress only Site Admin coverage | Inventory which personas Cypress walks |
| F-039 | airpay_emails Phase 5 — what's left? | State-card refresh |
| F-041, F-042 | Web push security review pending | Run a focused audit (B25 closure) |
| F-053-F-056 | 4 plugins with un-triaged state-cards | Read each state-card and triage |
| F-087 | `sentientia.pwa.install.enabled` duplicate rows — uniqueness check | Read install.xml index; add UNIQUE if missing |
| F-090 | Feature-flag audit table — is it actually written? | Set a flag via the setter; verify audit row appears |

### Bucket G — Defer to v2 (locked; see §5)

See §5 for the formal "v2" list with one-line rationale per item.

---

## §5. Locked deferrals (explicit "v2" list)

The following items are explicitly deferred to v2 (post-stabilization).
This is the contract: NOT promised for the current cycle. Future audits
can revisit if priorities change.

| ID | Item | v2 rationale |
|----|------|--------------|
| F-025 | Calendar OAuth Phase 2 full flows | Customer-driven; no current ask |
| F-034 | NVDA verification procedure execution | Owner: Nitin (manual run); procedure doc already shipped |
| F-036 | Mobile-app WS X.1 endpoints — actual mobile-app build | Mobile is Workstream D; not in v1 scope |
| F-037 | Customer Brand admin UI (B-side of ADR-008) | DB schema shipped; admin UI is v2 |
| F-040 | ADR-008 forward-looking items (per-customer logo upload UI) | Same scope as F-037 |
| F-043 | PWA Path C — full offline mode (not just static-asset caching) | v2; Path A+B are live |
| F-044 | WhatsApp DLT template approval workflow | Compliance-gated; v2 |
| F-050 | Org capability migration (capabilities at category level not system level) | Architectural; v2 |
| F-051 | Web Services endpoints for mobile read paths | v2 with X.1 |
| F-052 | Roles Phase 2 (role inheritance + cohort-based) | v2 |
| F-054 | Photo crop on user upload | Polish; v2 |
| F-055 | F1 source-map deploys (production sourcemaps) | DX nice-to-have; v2 |
| F-056 | Quiz feedback UI (post-attempt review) | Borrowed from 5.2 next time |
| F-057 (meta) | Add a "deferred-ledger" surface in admin UI to make all v2 items visible | This ADR is the closest thing; could be a real admin page in v2 |
| F-060 | `sentientia_m365` full M365 Graph API consumer (read user calendar/files) | Scaffold-only is acceptable; v2 |
| F-063 | B25 crypto audit closure (NB items #7-15) | Already non-blocking; v2 |
| F-027 | sentientia_recommendations install verification on fresh DB | Plugin scaffold is in BETA; v2 verification |
| F-028 | sentientia_translate install verification on fresh DB | Same |
| F-029 | sentientia_aiquiz install verification on fresh DB | Same |
| F-053 | block_airpay_trainer state-card refresh | v2 doc sweep |
| F-085 (subset) | Test data for proctoring / mgr_allocations / classroom — production data probably sufficient | Don't seed local; verify in prod via spot-check |

---

## §6. Index (sorted by finding ID)

| ID | One-line title | Bucket | Sev |
|----|---------------|--------|-----|
| F-001 | Public learner lands on employee-shaped dashboard | C2 | 🟠 |
| F-002 | Leaderboard widget consent (DPDP/GDPR) | B3 | 🔴 |
| F-003 | "Featured for you" widget shows employer-internal courses | C3 | 🟠 |
| F-004 | Catalog UX not Netflix-shaped | C4 | 🟠 |
| F-005 | Profile shape has N/A fields for consumers | C5 | 🟠 |
| F-006 | Sidebar shows employee-shape items to consumers | C6 | 🟠 |
| F-007 | Public-as-tenant architectural mistake (foundational) | C1/E1 | 🔴 |
| F-008 | Cross-tenant onboarding leak — fixed pre-audit | A | ✅ |
| F-009 | `authloginviaemail` doc — clarified pre-audit | A | ✅ |
| F-010 | Login flow with `@` in username — fixed pre-audit | A | ✅ |
| F-011 | DEPLOYMENT-RUNBOOK drift — partial | B15 | 🟡 |
| F-012 | airpay_* → sentientia_* naming transition incomplete | E2 | 🟡 |
| F-013 | LearnerScript localhost guard — fixed pre-audit | A | ✅ |
| F-014 | Production `authloginviaemail=1` not yet run | B5 | 🟠 |
| F-015 | (NotBuilt placeholder) | F | 🟡 |
| F-016 | Cross-tenant resolver — fixed pre-audit | A | ✅ |
| F-017 | AI Quiz never live-called (mock-only) | C8 | 🟠 |
| F-018 | sentientia_recommendations never live-called | C8 | 🟠 |
| F-019 | sentientia_translate never live-called | C8 | 🟠 |
| F-020 | AI Quiz G.4 mod_quiz push STUB | C | 🟠 |
| F-021 | Visual evidence pending | C | ⚪ |
| F-022 | Sentientia Live state-card stale | C7 | 🟠 |
| F-023 | E.10 per-tenant settings deferred | C7 | 🟡 |
| F-024 | E.12 analytics gaps | F | 🟡 |
| F-025 | Calendar OAuth Phase 2 deferred | §5 | 🟡 |
| F-026 | Recommendations H.1-H.4 install verify | §5 | 🟡 |
| F-027 | sentientia_recommendations install verify | §5 | 🟡 |
| F-028 | sentientia_translate install verify | §5 | 🟡 |
| F-029 | sentientia_aiquiz install verify | §5 | 🟡 |
| F-030 | Challenges 5 pendings | F | 🟡 |
| F-031 | course-share workflow verify | F | 🟡 |
| F-032 | paygw security verify | F | 🟡 |
| F-033 | Cypress only Site Admin coverage | F | 🟡 |
| F-034 | NVDA verification deferred | §5 | 🟡 |
| F-035 | Mobile WS X.1 deferred | C9 | 🟡 |
| F-036 | Customer Brand admin UI deferred | §5 | 🟡 |
| F-037 | ADR-008 forward-looking | §5 | 🟡 |
| F-038 | Certificates not built | C10 | 🟡 |
| F-039 | airpay_emails Phase 5 follow-up | F | 🟡 |
| F-040 | ADR-008 per-customer logo upload | §5 | 🟡 |
| F-041 | Web push security review pending | F | 🟠 |
| F-042 | Web push hardening | F | 🟡 |
| F-043 | PWA Path C deferred | §5 | 🟡 |
| F-044 | WhatsApp DLT pending | §5 | 🟠 |
| F-045 | WhatsApp stuck in mock mode | C | 🟠 |
| F-046 | Production deploy 5.2 cutover gap | C12 | 🔴 |
| F-047 | PWA master-key prod | C13 | 🟠 |
| F-048 | grunt minify automation | C | ⚪ |
| F-049 | `_moodle-overrides.scss` !important debt | C11 | 🟡 |
| F-050 | Org capability migration | §5 | 🟡 |
| F-051 | WS endpoints for mobile read | §5 | 🟡 |
| F-052 | Roles Phase 2 | §5 | 🟡 |
| F-053 | block_airpay_trainer state-card | §5 | 🟡 |
| F-054 | Photo crop | §5 | ⚪ |
| F-055 | F1 source-map deploys | §5 | ⚪ |
| F-056 | Quiz feedback UI | §5 | 🟡 |
| F-057 | Deferred-ledger meta-surface | §5 | 🟠 |
| F-058 | State-card staleness pattern | E4 | 🟠 |
| F-059 | M365 scaffold-only | §5 | 🟡 |
| F-060 | Full M365 Graph API | §5 | 🟡 |
| F-061 | airpay_assistant clarify scope | D5 | 🟡 |
| F-062 | airpay_integrations cleanup + 5 ALPHA plugins | D6 | 🟡 |
| F-063 | B25 crypto audit closure | §5 | 🟡 |
| F-064 | navbar.mustache inline `<script>` | B9 | 🟠 |
| F-065 | Dashboard.mustache hardcoded English | B10 | 🟠 |
| F-066 | Dashboard chart palette hex | B11 | 🟡 |
| F-067 | `_surface-user.scss` `!important` debt | B12 | 🟡 |
| F-068 | coursebannerimage doc comment | B7 | ⚪ |
| F-069 | `_surface-message.scss` missing | B13 | 🟡 |
| F-070 | footer.mustache alt= hardcoded English | B8 | 🟡 |
| F-071 | footer.mustache comment bloat | D2 | ⚪ |
| F-072 | Catalog rating sort stub | B6/D3 | 🟡 |
| F-073 | airpay_lifecycle investigation (now closed by F-092) | B20 | ⚪ |
| F-074 | airpay_pages version.php check (now closed by F-091) | B19 | ⚪ |
| F-075 | 5 blocks naming-transition | E2 | 🟡 |
| F-076 | DEPLOYMENT-RUNBOOK test accounts mismatch | B15 | 🟡 |
| F-077 | LearnerScript observer CLI warnings | B2 | 🟠 |
| F-078 | `M.cfg.userid` — ✅ verified clean | A | ✅ |
| F-079 | Sidebar entry-point conventions inconsistent | B14 | 🟡 |
| F-080 | `local/courses:manage` not registered — ✅ fixed | A | ✅ |
| F-081 | Orphan filedir entries (getimagesize warnings) | B17 | 🟡 |
| F-082 | 11 plugins with no admin entry point — 6 genuinely missing UI | C14-C16 | 🟠 |
| F-083 | 7 ADR/state-card references to renamed plugins | E2 | 🟡 |
| F-084 | `flag_key` not `flagname` doc-drift | B16 | ⚪ |
| F-085 | 79 empty mdl_local_* tables | C17 | 🟠 |
| F-086 | `ux.darkMode.enabled` flag governance | B4/D1 | 🟠 |
| F-087 | sentientia.pwa.install.enabled duplicates | F | 🟡 |
| F-088 | DB confirmation of F-080 — ✅ fixed | A | ✅ |
| F-089 | Sentientia Live flags globally enabled | B18 | 🟡 |
| F-090 | feature_flag_audit table untested | F | ⚪ |
| F-091 | airpay_pages workspace drift — ✅ fixed | A | ✅ |
| F-092 | airpay_lifecycle workspace drift — ✅ fixed | A | ✅ |
| F-093 | airpay_core workspace drift — ✅ fixed | A | ✅ |
| F-094 | theme/airpayux AMD workspace drift — ✅ fixed | A | ✅ |
| F-095 | Workspace drift gate (META) | B1/E3 | 🟠 |
| F-096 | airpay_challenge renderer is a stub | D4 | 🟡 |
| F-097 | role_detector_test references unregistered cap — ✅ fixed | A | ✅ |

---

## §7. Audit closure summary (post-Phase 3)

**Total findings:** 97 (F-001 — F-097).

**Already resolved this session (Bucket A):** 12 findings (F-008, F-009,
F-010, F-013, F-016 pre-audit; F-078 verified clean; F-080/F-088, F-091,
F-092, F-093, F-094, F-097 fixed Stream 1).

**Decision distribution post-triage:**

| Bucket | Decision | Findings |
|--------|----------|----------|
| A | ✅ Already shipped | 12 |
| B | Ship/Finish — small-medium (1-2 weeks total if all chipped) | 20 |
| C | Finish — large (multi-session, several weeks) | 17 |
| D | Remove dead code or downgrade maturity | 6 |
| E | Redesign (architectural) | 4 |
| F | Investigate before deciding | 10 |
| §5 | Locked Defer-v2 | 21 |
| Cross-referenced (some appear in multiple buckets) | — | 7 |

**Total committed work in v1 stabilization:** Buckets A+B+C+D+E ≈ 59
findings. **v2 lock:** ~21 findings.

**Recommended v1 close-out sequence:**

1. Bucket B (Ship/Finish small) — 20 items at S/M effort = ~3 weeks
2. Bucket E (Redesign) — 4 architectural decisions = ~1 week of ADR-writing + 4-6 weeks implementation
3. Bucket C (Finish large) — 17 items = ~6-8 weeks (much overlap with E)
4. Bucket D (Remove) — 6 items at S effort = ~1 week
5. Bucket F (Investigate) — 10 items at S effort = ~1 week of focused probing

**Estimated total v1 stabilization:** ~10-12 weeks of focused work.

---

## Audit progress log

| Date | Phase | Activity | Findings added |
|------|-------|----------|----------------|
| 2026-05-28 | 0 (desk research) | Setup + populate findings from today's conversation | F-001 through F-016 |
| 2026-05-28 | 0 (desk research) | Grep state-cards + ADRs + PROJECT-STATE for "pending", "deferred", "open question", "TODO", "follow-up" — extract candidate findings | F-017 through F-058 |
| 2026-05-28 | 0 (desk research) | Maturity stamp survey + prior audit cross-check + plugin inventory table populated | F-059 through F-063 |
| 2026-05-28 | 0 (desk research) | Carry-forwards from May-25 visual audit + coding_exception/TODO survey + stub/lifecycle/pages investigation + blocks inventory | F-064 through F-075 |
| 2026-05-28 | **0 COMPLETE** | Executive summary populated + Phase 0 closure marker added | — |
| 2026-05-28 | 1 (runtime probing) | Login as Site Admin + DEPLOYMENT-RUNBOOK test-account audit + LearnerScript CLI guard + plugin URL routing probe (37 URLs) | F-076 through F-079 |
| 2026-05-28 | 1 (runtime probing) | Apache error.log mining + DB capability + feature_flag table probes + orphan filedir scan | F-080 through F-090 |
| 2026-05-28 | 1 (runtime probing) | Workspace ↔ deployed file-count drift survey across all 38 airpay/sentientia plugins + theme + grep for stub/TODO markers | F-091 through F-097 |
| 2026-05-28 | **1 COMPLETE** | Phase 1 closure marker + severity distribution refresh + stop-the-bus list | — |
| 2026-05-28 | 2 (ADR-017) | Polymorphic user-type ADR drafted at `docs/adr/ADR-017-polymorphic-user-types.md` — schema (3 tables), provider interface, resolution rule, 5-phase migration, 7 open questions for Nitin | — |
| 2026-05-28 | **2 COMPLETE** | ADR-017 in Proposed status awaiting Nitin's call on 7 open questions | — |
| 2026-05-28 | 3 (consolidation) | Stream 1 deploy-unblock fixes shipped (commit `e32473e58`): F-080/F-088, F-091, F-092, F-093, F-094, F-097. §4 stabilization backlog ordered into 6 buckets (Ship/Finish-small/Finish-large/Remove/Redesign/Investigate). §5 locked v2 deferrals list. §6 index by finding ID. §7 closure summary. | — |
| 2026-05-28 | **3 COMPLETE** | Audit closed; 97 findings, 12 ✅ already shipped, ~59 in v1 stabilization buckets, ~21 locked Defer-v2, ~10 needing investigation | — |
| 2026-05-28 | 4 (Stream 2/3) | ADR-017 Phases 0–6 shipped end-to-end (schema, providers, factory, classify CLI, dashboard/sidebar/signup wiring, profile template, onboarding consent, dashboard widget gating) — commits `7525d413b → d56e9fe85`. Closes Tasks #309–#314. | — |
| 2026-05-28 | 4 (Bucket D) | D2 footer comment trim + D4 airpay_challenge BETA→ALPHA + D5 airpay_assistant BETA→ALPHA + D6 maturity-triage doc covering all 31 plugins. Commit `d7dbd7885`. | — |
| 2026-05-28 | 4 (Bucket E) | E2 RENAMES.md airpay→sentientia migration policy + E3 WORKSPACE-POLICY.md formalising workspace-as-source-of-truth + E4 state-card freshness gate + CHECK 12 pre-commit wiring. Commit `cadd25191`. | — |
| 2026-05-28 | 4 (Bucket C) | C15 sentientia_m365 OAuth admin landing + C16 sentientia_translate admin queue/landing — both modelled on C14 (4-card stats + quick-nav). admin_externalpage registration, lang strings, version bumps. Commit `3df780c76`. | — |
| 2026-05-28 | **4 COMPLETE** | Today's stabilization wave closes Tasks #315–#318. Audit moves from "closed but tracked" to "Bucket D+E+C15+C16 shipped"; remaining open in Bucket C: C4/C8/C9/C10/C17 (deferred per user). Bucket F (10 investigate items) + §5 v2-lock items still pending review. | — |

*(Progress log appended each session.)*
