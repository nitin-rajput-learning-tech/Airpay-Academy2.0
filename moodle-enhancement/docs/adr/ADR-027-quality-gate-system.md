# ADR-027 — Quality-gate system (stop auditing, start gating) + surface-upgrade workstream

- **Status:** **Accepted** — Gate 0 shipped 2026-06-09 (commit `5337f38e2`, branch
  `feat/theme-canonicalize-2026-06-09`); Gates 1–3 + the surface-upgrade
  workstream staged below.
- **Date:** 2026-06-09
- **Decision-makers:** Nitin Rajput
- **Implementer:** Claude (engineering)
- **Relates to:** ADR-009 (detection-consistency + WS-contract gate — the prior
  "make it a gate" precedent), ADR-026 (theme canonicalization / git-as-source),
  `docs/audits/AMD-LOADING-FIXES-2026-06-09.md`.

---

## Context — why repeated visual audits keep missing real bugs

Despite many "every surface, every persona" visual audits, user-facing defects
keep surfacing in production-like use. The three worst this cycle:

1. **Dashboard charts blank** — `dashboard.mustache` never emitted
   `standard_end_of_body_html`; AMD never booted; *every* JS feature was dead.
2. **Stale `theme_airpayux/*` AMD define names** after the de-brand — modules
   silently no-op'd platform-wide.
3. **Mustache comment leak** on `/course/view.php` — a `{{! … }}` comment whose
   body embedded `{{ }}` dumped a security rationale + internal doc paths onto
   the page (14 templates affected).

**The common thread: every one rendered as "looks basically fine."** The root
causes:

- **Screenshots verify *looks*, not *correctness*.** A human scanning a
  screenshot checks branding + layout; their eye slides past dead charts, leaked
  text, or un-rendered tags. Screenshot QA is structurally blind to dead JS,
  plausible-but-wrong text, leaked/un-rendered template tags, and silent data
  errors.
- **Audits are sampled + point-in-time.** "Every surface" means the *main*
  pages; parameterized pages (a specific course, a specific data state) and
  templates added *after* the audit are never seen.
- **No gate fails the build when a class recurs.** Each fix was a one-off.
- **Multiple sources of truth** (live webroot vs git vs `theme_airpayux` vs
  `theme_sentientia`) — a fix/audit on one doesn't protect the others.

**You cannot screenshot your way to correctness.** Humans must stop being the
gate.

## Decision — a layered gate system + a coverage matrix

| Gate | Runs | Catches | Status |
|------|------|---------|--------|
| **0 — Static template lint** | pre-commit + CI | Mustache comment leaks; (planned) unescaped `{{{ }}}` on user data, missing `standard_end_of_body_html`, stale `theme_airpayux/*` AMD names, hardcoded English in `.mustache` | **Comment-leak shipped** (`scan_mustache_comment_leaks.php`, hook CHECK 13, CI step); siblings staged |
| **1 — Render-smoke** | CI (matrix: surface × persona) | The "looks-fine-but-broken" class: 0 console errors/warnings; `window.require` is a function (AMD booted); **rendered `<body>` contains no literal `{{`/`}}`** (comment leaks + un-rendered tags); key landmarks present | **Next build** — extends `tests/playwright` persona specs |
| **2 — Visual snapshot diff + a11y** | CI (surface × breakpoint × light/dark) | Unexpected pixel change (regressions); axe WCAG violations. *This* is where screenshots belong — automated diffs, not human eyeballing | Staged (Playwright visual baselines partly exist) |
| **3 — Coverage matrix = "definition of done"** | tracked doc | Every route/template × persona × [static ✓, render-smoke ✓, visual ✓, a11y ✓, **Sentientia-styled? ✓**]. A surface is not "done" until the row is green | Staged |

**Structural fix (ADR-026):** finish git-as-source + a single deploy-from-git
pipeline, so the gates run on what actually ships and the webroot/git drift
stops re-introducing fixed bugs.

**The iron rule:** *every recurring bug class becomes an automated gate, not
another manual audit.* Gate 0's comment-leak scanner is the first instance.

## Consequences

**Positive**
- Correctness defects (dead JS, leaks, un-rendered tags) fail the build, not the
  user. Gate 1 check (c) alone would have caught bugs #1, #2 **and** #3 above.
- Audits shrink to "fill the matrix / triage diffs" — far less human time, far
  higher coverage, continuous instead of point-in-time.
- Regressions can't silently return — each is pinned by a gate.

**Negative / cost**
- Up-front build of Gate 1 (Playwright matrix + persona fixtures) and Gate 2
  (visual baselines). Amortized over every future change.
- CI gets slower (render-smoke boots Moodle). Mitigate with a curated
  high-value surface list, not all ~hundreds of routes.

## Implementation actions

1. **Gate 0 — DONE (comment-leak):** `scan_mustache_comment_leaks.php` (file +
   dir modes) → pre-commit CHECK 13 (blocks) + CI `php-lint` step (blocks).
   Verified end-to-end. **Next:** add the sibling static checks (XSS triple-brace,
   end-of-body, stale AMD names, hardcoded strings) as the same kind of scanner.
2. **Gate 1 — render-smoke (next):** a Playwright spec that logs in as each
   persona, visits a curated surface list, and asserts (a) 0 console
   errors/warnings, (b) `typeof window.require === 'function'`, (c) no literal
   `{{`/`}}` in `document.body.innerText`, (d) landmark elements present. Wire
   into CI (`playwright-linux` job) as a blocking gate once green.
3. **Gate 2:** per-surface visual snapshot baselines + axe a11y in the same job.
4. **Gate 3:** publish the surface-coverage matrix (see workstream below) and
   make it the merge "definition of done."
5. **Structural:** execute ADR-026 (git-as-source) so gates protect production.

## Surface-upgrade workstream (folds into Gate 3)

The Gate-3 matrix carries a **"Sentientia-styled?"** column — it doubles as the
upgrade tracker. The first inventory pass should classify every route/template
as app-shell-styled vs legacy and flag the gaps. Known/expected gaps:

- **`/course/view.php`** — still the legacy BizLMS blue-banner header
  (`course_full_header.mustache`), not the Sentientia app-shell treatment.
- **In-course activity surfaces** (quiz attempt, SCORM player, assignment /
  forum views) — the deep Moodle pages least likely to have been restyled.
- **Enrolment / payment surfaces.**

Each gap becomes a tracked upgrade task, executed **behind** Gates 0–2 so the new
UI can't regress correctness. Sequencing (Nitin, 2026-06-09): **gates first,
then upgrades.**

## References

- `moodle-enhancement/tools/scan_mustache_comment_leaks.php` (Gate 0 scanner)
- `.claude/hooks/pre-commit.sh` CHECK 13; `.github/workflows/ci.yml` php-lint step
- `docs/audits/AMD-LOADING-FIXES-2026-06-09.md` (bugs #1/#2 evidence)
- ADR-009 (WS-contract gate precedent), ADR-026 (git-as-source)

## Open questions for Nitin

1. **Curated surface list for Gate 1** — which ~20–30 routes are the
   highest-value to render-smoke every CI run (vs the full route set)?
2. **Gate 1/2 blocking vs advisory** at first — block immediately, or run
   `continue-on-error` for a calibration window like the existing playwright gate?
