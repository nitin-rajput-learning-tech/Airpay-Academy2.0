# Sentientia LMS — Surface Coverage Matrix (ADR-027 Gate 3)

**Owner:** Nitin Rajput · **Updated:** 2026-06-10 · **Status:** living document

This is **Gate 3** of the ADR-027 quality-gate system — the answer to *"how do we
KNOW a surface is actually covered?"* It is the **merge definition-of-done** for any
UI-touching change and the **surface-upgrade tracker** for the de-Moodle / Sentientia
design work. The earlier gates run automatically; this matrix is the human-readable
ledger of what those gates (and the manual passes) actually reach.

> **Why this exists.** Visual audits kept finding UI bugs *after* sign-off because
> "it renders" was being confused with "it works" — a page can look fine while its
> JavaScript never booted, a Mustache comment leaked, or a stale AMD name silently
> no-op'd. The gates below each kill one of those failure modes; this matrix shows
> their reach so gaps are visible instead of assumed-covered.

---

## The five dimensions

| Dim | Gate | What it proves | Mechanism | Automated? |
|-----|------|----------------|-----------|------------|
| **Static** | Gate 0 | Template can't ship a known static defect (comment leak, stale `theme_airpayux` AMD name, missing `standard_end_of_body_html`) | `moodle-enhancement/tools/scan_*.php` → pre-commit hook (15 CHECKS) + CI | ✅ yes |
| **Render** | Gate 1 | Page boots: AMD up (`window.require` is a fn), no leaked `{{ }}`, a landmark is visible, **0** non-benign console errors | `tests/playwright/render-smoke.spec.ts` (CI Linux authoritative) | ✅ yes |
| **Visual** | Gate 2 | Pixels match an approved baseline (no layout regression) | Playwright screenshot diff | ❌ **not built** (P2-6) |
| **A11y** | Gate 2 | No axe-core violations (contrast, landmarks, labels) | axe in the Playwright job | ❌ **not built** (P2-6) |
| **Styled** | manual | Sentientia design system applied (not raw Moodle/Boost) | Goal-A persona walks + visual evidence | ◑ manual, per-surface |

Legend in the tables: **✓** covered · **◑** partial / indirect · **✗** not yet · **—** N/A.

---

## A. Layout chrome coverage

Every page renders through one of `theme/sentientia/config.php`'s 10 layout files.
Because the layouts define the shell, covering them covers the chrome for *all* pages
that use them. Gate 0 covers **all** `.mustache` unconditionally (the scanners walk the
whole tree); Gate 1 only exercises the layouts behind the curated render-smoke surfaces.

| Layout file | `$THEME->layouts` keys | Static (G0) | Render (G1) | Visual (G2) | Styled |
|-------------|------------------------|:----:|:----:|:----:|:----:|
| `dashboard.php` → `dashboard.mustache` | `mydashboard` | ✓ | ✓ (`/my/`) | ✗ | ✓ |
| `columns2.php` → `columns2.mustache` | `standard` `mycourses` `mypublic` `coursecategory` `report` | ✓ | ✓ (`/my/courses.php`, `/user/profile.php`) | ✗ | ✓ |
| `course.php` → `course.mustache` | `course` `incourse` | ✓ | ◑ (`/course/view.php`, only when `PLAYWRIGHT_COURSE_ID` set) | ✗ | ✓ |
| `drawers.php` → `drawers.mustache` | `admin` | ✓ | ✗ | ✗ | ✓ (Goal-A `/admin/*`) |
| `columns1.php` | `popup` `frametop` `print` | ✓ | ✗ | ✗ | ◑ |
| `frontpage.php` (pure PHP) | `frontpage` | — (not `.mustache`) | ✗ | ✗ | ✓ (LXP storefront) |
| `login.php` → `login.mustache` | `login` | ✓ | ✗ (pre-auth; not in the authenticated walk) | ✗ | ✓ |
| `embedded.php` → `embedded.mustache` | `embedded` `redirect` | ✓ | ✗ | ✗ | — |
| `secure.php` → `secure.mustache` | `secure` | ✓ | ✗ | ✗ | ◑ |
| `maintenance.php` → `maintenance.mustache` | `maintenance` | ✓ | — (no DB/JS by design) | ✗ | — |

**Notes**
- `incourse` (every quiz/scorm/assign/forum *view*) maps to the **same `course.php`** as the
  main course page — so in-course activity surfaces inherit the validated course chrome by
  construction (see `docs/visual-evidence/2026-06-10/README.md`).
- `core/email_html.mustache` is a full-page (`</body>`) document but carries an
  `end-of-body-allow` opt-out — an email body must never ship the AMD `<script>` bootstrap.

---

## B. Persona × surface coverage

Personas are computed per the audit (`docs/audits/SENTIENTIA-CAPABILITY-AND-GAP-AUDIT-2026-06-09.md`
§1). Render-smoke (Gate 1) currently drives **5 personas** (`LEARNER` `MANAGER` `COMPLIANCE`
`AUTHOR` `ADMIN`) across the **curated surface set** (`/my/`, `/my/courses.php`,
`/user/profile.php`, +`/course/view.php` when a course id is wired). Surfaces below that the
curated set does **not** yet visit are marked ✗ under Render even where the layout is covered.

| Persona | Primary surfaces | Static (G0) | Render (G1) | Styled | Notes |
|---------|------------------|:----:|:----:|:----:|-------|
| **Learner** (employee) | `/my/`, `/my/courses.php`, `/course/view.php`, `/user/profile.php`, `/local/sentientia_skills/` | ✓ | ✓ (skills page ✗) | ✓ | core walk green |
| **Manager** (direct reports) | learner set + team report tiles | ✓ | ✓ (team report ✗) | ✓ | |
| **Course Author / SME** | `/course/edit.php`, course content, `/local/sentientia_live/` | ✓ | ◑ (authoring pages ✗) | ✓ | |
| **Compliance Officer** | compliance dashboard + RAG reports | ✓ | ◑ (compliance report ✗) | ✓ | sidebar reach fixed (Bug #11) |
| **L&D Administrator** | `/local/sentientia_*` admin, user mgmt | ✓ | ✗ (not in persona set) | ◑ | **render-gap** |
| **Tenant Administrator** | tenant-scoped admin (`/admin/*` drawers) | ✓ | ✗ | ◑ | **render-gap** |
| **Site Administrator** | `/admin/*` (drawers layout) | ✓ | ✓ (curated set only) | ✓ | |
| **External Public Learner** (consumer) | `/local/sentientia_pages/homepage.php`, `public.php`, signup, paid courses | ✓ | ✗ (pre-/cross-auth flow) | ✓ | LXP storefront styled |
| **API Consumer** | web-service endpoints (no UI) | — | — | — | covered by ws-contract gate (ADR-009) |

---

## C. Definition of done (per surface)

A UI-touching change is "done" for a surface when:

1. **Static (G0)** — passes the pre-commit hook + CI scanners (automatic; no excuse to skip).
2. **Render (G1)** — the surface is in `render-smoke.spec.ts`'s curated set **or** a note here
   explains why it's excluded (pre-auth, popup, email, etc.), and the relevant persona run is green on CI.
3. **Styled** — the Sentientia design system is applied (app-shell or a deliberate documented exception).
4. **Visual + A11y (G2)** — *aspirational until P2-6 lands*; until then, a manual visual-evidence
   capture under `docs/visual-evidence/YYYY-MM-DD/` substitutes.

A surface with an unavoidable gap (e.g. blocked by local tooling) is **done** only if the gap is
recorded here or in the dated visual-evidence README — never silently.

---

## D. Known gaps (honest ledger — do not read blank cells as "fine")

1. **Gate 2 not built (P2-6).** No automated visual-diff or axe a11y pass exists yet. Every
   "Visual" column is ✗. Manual visual-evidence READMEs are the current substitute.
2. **Render-smoke surface set is small by design** (3–4 surfaces). It is the *correct* core, not
   the *complete* set — skills, team reports, authoring, compliance reports, admin interiors, and
   the public/pre-auth flows are not yet walked. Expand `SURFACES` in `render-smoke.spec.ts` as the
   matrix matures (the spec comment says exactly this).
3. **Persona render-gap.** Gate 1 drives 5 of the 9 personas. L&D Admin, Tenant Admin, External
   Public Learner, and API Consumer have **no render-smoke run** (API Consumer is covered instead by
   the ws-contract gate, ADR-009).
4. **`course/view` render is conditional** on `PLAYWRIGHT_COURSE_ID` — unset = that row is unproven
   on a given CI run.
5. **Local screenshot tooling is unreliable** on the XAMPP dev box (slow first loads + post-purge
   SCSS recompile). CI Linux is the authoritative render environment; local visual evidence depends
   on a Chrome-extension `localhost:8080` permission grant.

---

## E. How to extend this gate

- **Add a surface to Gate 1:** append to `SURFACES` in `tests/playwright/render-smoke.spec.ts`,
  then add its row here.
- **Add a persona to Gate 1:** add to `PERSONAS` + wire `PLAYWRIGHT_<PERSONA>_USER/_PASS`
  (the test `test.skip`s cleanly until creds exist).
- **Close the Visual/A11y column:** build P2-6 (Playwright screenshot baseline + axe), then flip
  the G2 cells as surfaces gain baselines.
- **Iron rule (ADR-027):** every recurring bug class becomes an automated gate. If a UI bug escapes
  to a human reviewer, the fix is incomplete until a Gate-0/1/2 check would have caught it — then
  this matrix records the new coverage.

---

### References
- ADR-027 — quality-gate system (gates first, then upgrades).
- Gate 0 scanners: `moodle-enhancement/tools/scan_mustache_comment_leaks.php`,
  `scan_stale_theme_refs.php`, `scan_missing_end_of_body.php`.
- Gate 1 spec: `tests/playwright/render-smoke.spec.ts` + `persona-helpers.ts`.
- Pre-commit hook: `.claude/hooks/pre-commit.sh` (CHECK 13–15). CI: `.github/workflows/ci.yml`.
- Capability/persona source: `docs/audits/SENTIENTIA-CAPABILITY-AND-GAP-AUDIT-2026-06-09.md`.
- ws-contract gate (API personas): ADR-009.
