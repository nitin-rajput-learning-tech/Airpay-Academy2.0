# Visual UI Audit — 2026-05-22

This folder will hold the artefacts of **Goal A** from the 2026-05-22 priority
list: a Chrome walkthrough of every page surface in Airpay Academy 2.0 for
each of the 9 user types in Section 10 of the May-12 master doc, with the
explicit objective of identifying surfaces that "still look like Moodle"
and feeding a prioritised UI-upgrade backlog.

## Why this audit exists

Quote from Nitin, 2026-05-22 afternoon:

> visual audit of each and every page surface of the platform for each
> possible type of user, use google chrome, we have to upgrade the UI,
> still looks like moodle.

So the audit has **two outputs**:

1. A **screenshot inventory** organised by persona, with desktop and
   mobile breakpoints, that doubles as the raw material for the user
   guides (Goal C).
2. An **audit report** (`AUDIT-REPORT.md` in this folder once the walk
   completes) listing every surface graded on a four-point scale:
     - 🟢  **Branded** — already looks like an Airpay product
     - 🟡  **Mixed**   — most elements branded, but legacy Moodle UI bleeds in
     - 🟠  **Moodle**  — clearly looks like a Moodle page with logo swap
     - 🔴  **Broken**  — the page is unusable, broken layout, or hard error
   The graded list becomes the backlog for Goal A.x (UI-upgrade work).

## Persona scope &amp; coverage matrix

The audit covers all 9 user types from Section 10 of the May-12 doc. The
table below is the canonical coverage list; entries get filled as the
walk progresses.

| # | Persona | Test credentials | Tenant | Pages walked | Status |
|---|---|---|---|---|---|
| 1 | Learner | _TBD by L&amp;D before walk starts_ | `/1` (Airpay) | 0 / ~25 | ⏳ pending |
| 2 | Manager | _TBD_ | `/1` | 0 / ~30 | ⏳ pending |
| 3 | L&amp;D Administrator | _TBD_ | `/1` | 0 / ~50 | ⏳ pending |
| 4 | Course Author / SME | _TBD_ | `/1` | 0 / ~15 | ⏳ pending |
| 5 | Compliance Officer | _TBD_ | `/1` | 0 / ~15 | ⏳ pending |
| 6 | Tenant Administrator | _TBD_ | `/1` | 0 / ~50 | ⏳ pending |
| 7 | Site Administrator | nitin@airpay (existing super-admin) | site | 0 / ~80 | ⏳ pending |
| 8 | External Public Learner | _TBD — create on /77 if none exists_ | `/77` | 0 / ~20 | ⏳ pending |
| 9 | API Consumer | _N/A — no UI to walk; covered in Goal C only_ | — | — | N/A |

Total estimated surfaces: **~285 pages × 2 breakpoints (desktop + 590px) = ~570 screenshots**.

## Required from L&amp;D before the walk starts

To unblock the audit, the following must be in place:

1. **Test user accounts** for personas 1-6 + 8 (persona 7 already exists)
2. **Each test user populated with realistic data** — at least one course
   enrolment per Learner, one direct report per Manager, one course
   ownership per L&amp;D Admin, etc. Empty-state walkthroughs are not the
   audit goal; we want to see how the platform looks in normal use.
3. **A clean session** (purged caches, no leftover banners) so we capture
   steady-state UI rather than transient install banners.

Once that's ready, the walk itself is mechanical and can be done in a
single ~4-6 hour session per persona by an engineer driving Chrome
DevTools manually (or scripted via Playwright if/when we want to repeat).

## Folder layout

```
visual-audit-2026-05-22/
├── README.md                      ← this file
├── AUDIT-REPORT.md                ← graded summary, generated at walk end
├── 01-learner/
│   ├── desktop/
│   │   ├── 01-login.png
│   │   ├── 02-dashboard.png
│   │   ├── 03-catalogue.png
│   │   └── ... (all surfaces)
│   └── mobile-590px/
│       └── ... (same surfaces at 590px)
├── 02-manager/
├── 03-ld-administrator/
├── ... (one folder per persona)
└── shared/                        ← cross-persona surfaces (login, 404, etc.)
    ├── desktop/
    └── mobile-590px/
```

## Screenshot naming convention

`NN-surface-name.png` where NN is a 2-digit zero-padded sequence within the
persona folder. The sequence follows the natural user flow (login → dashboard
→ catalogue → enrol → consume → cert) rather than alphabetical, so reviewers
can see the journey.

If a surface has multiple states worth capturing (empty / populated / error),
suffix with `-empty`, `-populated`, `-error`.

## Methodology — driving Chrome

Two options:

### Option 1 — Manual walkthrough (preferred for first pass)

The auditor logs in as each persona, walks every surface in the table
above, takes a screenshot via Chrome DevTools (Cmd/Ctrl-Shift-P → "screenshot")
at both desktop (1440×900) and mobile (390×844, iPhone 14 frame) widths,
and grades each surface 🟢/🟡/🟠/🔴 in the audit report.

Strength: catches UX awkwardness automation misses.
Weakness: not repeatable on every PR.

### Option 2 — Playwright-scripted (for v2 and beyond)

Once we know the persona credentials are stable, a Playwright script
per persona logs in, navigates each surface, captures both breakpoints,
and writes the screenshots in the layout above. Re-runnable on every
deploy. Good for regression but harder to grade subjectively.

The first pass uses Option 1; the second pass (after Goal A.x ships)
uses Option 2 to confirm the upgrade is durable.

## Linking back to the user guides (Goal C)

Every screenshot in this folder is referenced by the corresponding
section of the user guides at `docs-site/docs/personas/NN-persona/`.
The convention:

```markdown
![Learner dashboard, desktop](../../../moodle-enhancement/docs/visual-audit-2026-05-22/01-learner/desktop/02-dashboard.png)
```

When the docs site builds, MkDocs resolves the relative path. When
images change in this folder (e.g. after a UI upgrade), the guides
automatically reflect the new state without any content edit.

## Status

⏳ **Pending L&amp;D unblocker** — test credentials + populated test data.
Tracked as task #148 in the session task list.
