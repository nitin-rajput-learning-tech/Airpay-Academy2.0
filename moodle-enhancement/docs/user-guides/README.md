# Sentientia LMS / Airpay Academy — User Guides

**Platform:** Sentientia LMS (customer-zero: Airpay Academy) — Theme `airpayux` v1.0.37-beta
**Updated:** 2026-05-25 (Goal C close-out — Wave D3 P3 testing-and-docs chip)
**Status:** ✅ Goal C complete — four persona guides shipped at full depth

This directory holds the **end-user documentation** for Sentientia LMS, one guide
per persona. Each guide is a complete ≥20-page walkthrough: login, every menu +
page, mobile (where relevant), troubleshooting, and a "What's new in
v1.0.37-beta" changelog scoped to that persona.

---

## The four core guides (v1.0 — full depth)

| Guide | Persona | Test account(s) | Tenant | Pages |
|-------|---------|-----------------|--------|-------|
| 📘 [**Tenant Admin**](tenant-admin-guide.md) | Runs ONE BizLMS costcenter | `academyexadmin@airpay.co.in` (Public) · `nitin.rajput@airpay.co.in` (Airpay) | 77 + 1 | ~28 |
| 📗 [**Course Author / SME**](course-author-guide.md) | Builds + teaches courses | `asif.ansari@airpay.co.in` | Airpay (`/1/79/197/200`) | ~24 |
| 📙 [**Compliance Officer**](compliance-officer-guide.md) | Owns statutory-training coverage | `joseph.mandapati@airpay.co.in` | Airpay (BizLMS admin role) | ~20 |
| 📕 [**Learner**](learner-guide.md) | Takes courses, earns certs (largest population) | `jitendra.mane@airpay.co.in` (Airpay) · `academyexadmin@airpay.co.in` (Public) | 1 + 77 | ~22 |

> Local password for all capture accounts (XAMPP only): `AcademyAudit2026!`
> Local base URL: `http://localhost:8080/moodle/`

### Screenshots

Each guide's screenshots live under [`screenshots/<persona>/`](screenshots/)
with a per-folder `README.md` manifest listing every shot's filename, URL,
viewport, and capture account. Captures are pending on local XAMPP (the cloud
container that authored these guides cannot reach `localhost:8080`); each guide
ends with a copy-paste PowerShell capture recipe.

- [`screenshots/tenant-admin/`](screenshots/tenant-admin/README.md) — 40 shots
- [`screenshots/course-author/`](screenshots/course-author/README.md) — 35 shots
- [`screenshots/compliance-officer/`](screenshots/compliance-officer/README.md) — 23 shots
- [`screenshots/learner/`](screenshots/learner/README.md) — 45 shots

---

## Sibling guides (v1-draft scaffolds, retained)

These shorter v1-draft guides from the 2026-05-24 night-run still cover personas
not in the core four, or provide a condensed view. The v1.0 guides above
supersede them for the four core personas but the scaffolds remain useful:

| Scaffold | Covers | Status |
|----------|--------|--------|
| [`site-admin.md`](site-admin.md) | Full superuser (all tenants, all customers) | v1 draft — **the canonical Site Admin reference** (no v1.0 superset yet) |
| [`manager.md`](manager.md) | Line manager (team dashboard, approvals, escalations) | v1 draft — canonical Manager reference |
| [`public-learner.md`](public-learner.md) | External Public-tenant detail (signup, refund, deletion) | v1 draft — deep-dive companion to `learner-guide.md` |
| [`tenant-admin.md`](tenant-admin.md) | Condensed Tenant Admin | v1 draft — superseded by `tenant-admin-guide.md` |
| [`course-author.md`](course-author.md) | Condensed Course Author | v1 draft — superseded by `course-author-guide.md` |
| [`learner.md`](learner.md) | Condensed Learner | v1 draft — superseded by `learner-guide.md` |

---

## Which guide should I read? — chooser flowchart

```
                         ┌─────────────────────────────────┐
                         │   What do you DO on Sentientia?  │
                         └─────────────────┬───────────────┘
                                           │
        ┌──────────────────┬───────────────┼───────────────┬──────────────────┐
        │                  │               │               │                  │
        ▼                  ▼               ▼               ▼                  ▼
 "I take courses    "I build &      "I track who's   "I run my       "I manage the
  & earn certs"      teach courses"  done mandatory   team's          whole site /
        │              │              training"        learning"       all tenants"
        │              │               │               │                  │
        ▼              ▼               ▼               ▼                  ▼
  ┌──────────┐   ┌────────────┐  ┌──────────────┐ ┌──────────┐    ┌──────────────┐
  │ LEARNER  │   │  COURSE    │  │ COMPLIANCE   │ │ MANAGER  │    │ SITE ADMIN   │
  │  guide   │   │  AUTHOR    │  │  OFFICER     │ │ (scaffold│    │  (scaffold)  │
  │          │   │   guide    │  │   guide      │ │  .md)    │    │              │
  └──────────┘   └────────────┘  └──────────────┘ └──────────┘    └──────────────┘
        │                                                                  ▲
        │ "...but I also configure                                        │
        │  ONE tenant's users/courses/branding"                          │
        └──────────────────────────────────────────────────────────────► │
                                                                          │
                                                              ┌──────────────────┐
                                                              │  TENANT ADMIN    │
                                                              │     guide        │
                                                              └──────────────────┘

  Special cases:
  • External / paying learner on the Public tenant? → LEARNER guide (🟩 callouts)
    + public-learner.md for refund / account-deletion detail.
  • API / integration developer? → docs/audits/MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md
    (no UI walk; WS endpoints only).
```

### Quick decision table

| If you are…                                              | Read…                                  |
|----------------------------------------------------------|-----------------------------------------|
| An employee who just wants to do your assigned training  | [Learner](learner-guide.md)             |
| An external learner who bought / wants a course          | [Learner](learner-guide.md) (🟩 callouts) + [public-learner.md](public-learner.md) |
| A trainer / SME who builds course content                | [Course Author](course-author-guide.md) |
| Responsible for statutory-training audit coverage        | [Compliance Officer](compliance-officer-guide.md) |
| A line manager watching your direct reports' progress    | [manager.md](manager.md)                |
| Running one tenant (users, courses, reports, branding)   | [Tenant Admin](tenant-admin-guide.md)   |
| The site superuser across all tenants                    | [site-admin.md](site-admin.md)          |
| Building an integration against the WS API               | [Mobile WS surface audit](../audits/MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md) |

---

## How these guides are structured

Every core guide follows the same backbone so a reader can jump between personas
and find the same shape:

1. **Who is this persona** — scope + capability matrix
2. **First login** — with the real test account
3. **Navbar / sidebar walkthrough** — every menu item
4. **Feature-by-feature walkthrough** — every page that persona touches
5. **Mobile (590px)** — for Learner + Tenant Admin (the two persona groups with
   significant mobile usage)
6. **What's new in v1.0.37-beta** — the 21-chip Day-0 wave, filtered to changes
   that affect THIS persona
7. **Troubleshooting** — symptom → cause → resolution tables
8. **Escalation cues** — when to call the next tier up
9. **Screenshot capture recipe** — copy-paste PowerShell for local XAMPP
10. **References** — sibling guides + ADRs + state cards + plugin READMEs

---

## The 21-chip wave (v1.0.37-beta) at a glance

All four guides carry a "What's new in v1.0.37-beta" section filtered to their
persona. The full wave is documented in
[`../audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`](../audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md)
and the PROJECT-STATE.md "Day-0 chip-wave summary — 21 merges" H2. Summary:

| Bucket | Chips | Net effect |
|--------|-------|------------|
| P0 cleanup (theme hygiene) | A, B (orphan SCSS + monolith backup), conflict-marker hook | Faster compile, broken-merge defence |
| i18n | B (nav/footer), G (dashboard), #255 (locale parity 178/178 × 5 locales) | 100% translated chrome (en/hi/kn/mr/sw) |
| Inline-style + token discipline | C (dashboard), F-06 (footer), #18, #19/D (reduced-motion) | Dark mode correct everywhere; WCAG 2.3.3 |
| Accessibility | E (`aria-live`), P1 #12/H (`:focus-visible`), P0-B (`_bizlms-admin`) | Screen-reader + keyboard parity |
| SCSS health | I (dark_mode cascade), J (profile split), K (login cleanup), L (footer mobile), M (Live BEM) | Maintainable cascade, mobile coverage |
| Assets | F (cart-badge AMD), N (Chart.js vendored) | CSP-safe, offline-capable |
| Safety docs | Q (coursebanner CSS-url), O/#21 (footer comment trim) | Documented + tidy |

Plus 6 P3 alpha scaffolds (AI Quiz, Calendar OAuth, Leaderboard notifications,
SOP PDF parser, M365 Graph, Live question-types) and 3 CI gates (conflict-marker,
PHPUnit-5.2, Playwright-Linux), all behind feature flags / default OFF.

---

## Maintaining these guides

- **On every UI-touching release:** add a row to the affected persona's "What's
  new in vX" table; bump the version footer.
- **On a new persona:** add a guide following the 10-part backbone above + a
  screenshots manifest + a row in the chooser table.
- **Screenshots:** re-capture against the canonical viewports (1440×900 desktop,
  590 mobile) after any surface restyle. Keep the manifest READMEs in sync.
- **Hindi parity:** these guides are English. A Hindi translation pass is a
  future deliverable (tracked alongside the in-product Hindi parity work).

---

## References

- [`../audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`](../audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md) — visual audit covering each persona's surfaces
- [`../GOAL-A-Y-FUNCTIONAL-AUDIT-MATRIX.md`](../GOAL-A-Y-FUNCTIONAL-AUDIT-MATRIX.md) — per-persona feature matrix + test accounts
- [`../visual-audit-2026-05-22/AUDIT-REPORT.md`](../visual-audit-2026-05-22/AUDIT-REPORT.md) — persona walks + Bug #11
- [`../customer-config/airpay.md`](../customer-config/airpay.md) — customer-zero reference config
- [`../adr/`](../adr/) — architecture decision records
- `../../PROJECT-STATE.md` — current phase + Goal C close-out
- `../../../CLAUDE.md` — operating rules + escalation flags

---

| Version | Date       | Author                       | Notes                                       |
|---------|------------|------------------------------|---------------------------------------------|
| v1.0    | 2026-05-25 | Wave D3 P3 testing-and-docs chip | Goal C close-out — 4 full guides + index + manifests |
