# UI Navigation & Chrome Audit — 2026-08-03

**Trigger:** owner report "dark mode is weird… UI navigation on each page, for each user type — identify
what is wrong first."
**Method:** live runtime walk (Playwright, local :8080) — personas `qa_employee`, `qa_trainer`,
`qa_orgadmin`, `qa_siteadmin` × their sidebar destinations + course/activity pages + `/admin` + mobile
590px. Each page fingerprinted for: which shell renders, nav affordances, breadcrumbs, footer, title.

---

## The headline: ONE structural split explains most of "not inline"

| Surface cluster | Chrome that renders | Navigation offered |
|---|---|---|
| Dashboard, My Courses, Catalog, My Skills, Certificates, Profile, Live Sessions, **all** admin plugin pages (Manage Users/Courses, Reports, Analytics, Compliance…), even Moodle `/admin/search.php` | **Sentientia app-shell** | Persistent left sidebar (persona-scoped), ap-topbar with search, footer, mobile bottom-nav (Home/My Learning/Search/Me) + off-canvas sidebar |
| **Course + activity pages** (`pagelayout-course`/`incourse`: course view, quiz, SCORM, forum) | **Boost drawers world** | Left sidebar VANISHES; a different fixed-top horizontal navbar appears (Dashboard/My Courses/Catalog/Profile as top links); course-index drawer behind a hamburger; different footer |

Verified identically for learner AND siteadmin: `shell:false, drawers:true` on course pages;
`shell:true, drawers:false` everywhere else. **The moment any user enters a course, the platform's
navigation model is replaced wholesale** — left rail becomes top links, search moves, the way "back"
works changes. This is the single biggest inconsistency and it affects every persona on the most-used
flow in the product (doing a course).

## Findings register

| # | Sev | Finding | Evidence |
|---|-----|---------|----------|
| **N-01** | **P0** | **Dual navigation systems** — app-shell everywhere vs boost-drawers inside courses (above). The genuine fix is the deferred "true re-shell" of `pagelayout-course`/`incourse` (P1-3 structural completion) | fingerprints: all shell pages `{shell:true,drawers:false,top:ap-topbar}`; course/quiz `{shell:false,drawers:true,top:boost-navbar}` |
| **N-02** | **P1** | **Duplicated course TOC in-course** — the boost course-index drawer AND the ap-course-player sidebar both list course contents; plus the sticky-bar toggle = three competing wayfinding widgets on one page | course 403: `drawer-open-index` true + `.ap-course-player` + `#ap-sidebar-toggle` all present |
| **N-03** | **P1** | **No breadcrumbs anywhere in the shell** — every shell page fingerprinted `crumbs:false`. Nested flows (Manage Courses → course → enrolments; catalog → detail → module) give no path context; the sidebar only highlights top-level | all shell fingerprints |
| **N-04** | **P1** | **Dark-mode patchiness (the "weird")** — 136 hardcoded hex values across 14 SCSS partials bypass the token system, so dark mode flips unevenly: quiz/activity description card renders near-WHITE on dark (screenshot in evidence 2026-07-22), cold navy slates in some components vs token grays in others | `grep dark-mode partials | hex` count; quiz screenshot |
| **N-05** | ~~P2~~ **WAD** | ~~Logout confirm is a UX hazard~~ **Re-examined during execution: works as designed.** The confirm page renders Cancel (btn-secondary, left) + Continue (btn-primary, right) — correct primary-right convention and correct styling. The original repro was the audit script clicking the FIRST submit button (Cancel), not a user-facing defect. Residual polish idea only: label "Log out" instead of the generic "Continue" | logout-confirm DOM dump 2026-08-03 |
| **N-06** | **P2** | **Notifications/messages access is inconsistent** — bell/cart icons exist in the course-page (drawers) navbar; the shell sidebar/topbar offers no notifications entry on plugin pages | fingerprints + course screenshots |
| **N-07** | ~~P2~~ **WAD** | ~~Trainer sees "Compliance" — likely nav leakage~~ **Re-examined: deliberate.** The link is gated on `moodle/site:viewreports` (Goal A Bug #11, 2026-05-22) — the compliance page itself accepts that capability, so the sidebar correctly mirrors page-layer auth; the trainer role holds viewreports by design | `sidebar_navigation.php` §iscomplianceuser |
| **N-08** | **P3** | **Title/label hygiene** — "My Certificates \| \| airpay" (empty segment), "Airpay User Engine" (internal jargon as page title), h1s embedding product-internal names ("Airpay Skills Matrix") | walk titles |
| **N-09** | **P3** | Legacy course-header remnants still render for admins in edit mode by design; empty-metric cells only hidden for learners (2026-07-22 fix). Editing view could still use compaction polish | course fingerprint as siteadmin: header 193px non-editing ✓ |
| **N-10** | **OK** | Mobile shell is healthy: sidebar goes off-canvas (translateX −260), bottom-nav + hamburger present at 590px | mobile fingerprint |

## Upgrade plan (phased; approval to execute pending)

- **Phase A — Unify the course experience into the app-shell (N-01, N-02).** Re-shell
  `pagelayout-course` + `incourse`: render the persona sidebar + ap-topbar on course pages; make the
  ap-course-player sidebar the ONE in-course TOC (retire the boost course-index drawer for non-editing
  users); keep drawers only for edit mode where Moodle tooling expects it. This is the deferred P1-3
  structural completion — the single highest-impact change.
- **Phase B — Wayfinding (N-03).** Breadcrumb strip in the shell content header on nested pages
  (shell-aware, persona-aware); sidebar section highlighting for sub-pages.
- **Phase C — Dark-mode tokenization (N-04).** Sweep the 136 hardcoded hexes onto `--ap-color-*`
  tokens partial-by-partial (worst first: activity/quiz cards, login/signup slates already done);
  contrast-check AA on each swap. Kills the patchiness class permanently.
- **Phase D — Nav correctness (N-05, N-06, N-07).** Fix logout-confirm button hierarchy; add a
  notifications entry to the shell topbar; capability-gate the trainer Compliance link.
- **Phase E — Polish (N-08, N-09).** Title format fixes, de-jargon page titles/h1s, editing-mode
  header tidy.
- **Phase F — Verification.** Re-run this persona×surface fingerprint walk (it is scripted and
  repeatable), light+dark screenshots per persona, evidence + PROJECT-STATE.

Sequencing note: A is a structural layout change (course.php/course.mustache) — do it first and alone;
B–E are additive and parallelizable after A lands.
