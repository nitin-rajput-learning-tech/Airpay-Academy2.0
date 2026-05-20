# Visual Evidence Ledger — Sentientia LMS

Every session that touches UI ends with screenshots saved here, dated.

## Why this exists

Nitin's Day 0 directive: "we will not go to production until [...] visually
verified, UI/UX is world class". This folder is where verification happens.

Each session that ships UI changes leaves:
1. Screenshots — desktop (1920×1080) + mobile (390×844 iPhone 14 viewport)
2. Short README describing what changed
3. Before/after pairs when modifying existing UI

Nitin reviews each session's screenshots before approving merge to production.

## Folder structure

```
visual-evidence/
├── README.md (this file)
├── YYYY-MM-DD/
│   ├── README.md (what changed this session)
│   ├── before/
│   │   ├── desktop-<surface>.png
│   │   └── mobile-<surface>.png
│   └── after/
│       ├── desktop-<surface>.png
│       └── mobile-<surface>.png
```

## Capture conventions

- **Desktop:** 1920×1080, Chrome on Windows, devtools closed, theme = airpayux
- **Mobile:** 390×844 (iPhone 14), Chrome devtools device toolbar, theme = airpayux
- **Tenants:** Capture both Airpay tenant (id=1) AND Public tenant (id=77) if styling differs per-tenant
- **Roles:** Capture as Learner (not admin — admin bypasses some access checks)
- **State:** Capture both empty-state AND populated-state if the surface has both

## File naming inside a session folder

`<surface>-<viewport>-<state>.png`

Examples:
- `dashboard-desktop-populated.png`
- `dashboard-mobile-empty.png`
- `signup-desktop-error.png`
- `course-detail-mobile-default.png`

## Required per-session README content

```markdown
# Visual Evidence — YYYY-MM-DD

## Session
[Brief description of what shipped this session]

## Surfaces affected
- /path/to/surface (description)
- /path/to/another (description)

## Reviewed against prototypes
- D:\Claude Local\Moodle Backup\03-prototypes\preview\<prototype>.html (✓ match / ✗ deviation)

## Sign-off
- [ ] Nitin reviewed
- [ ] Mobile responsive verified at 590px breakpoint
- [ ] Hindi language tested (lang=hi user switch)
- [ ] Dark mode tested (if applicable)
- [ ] Both tenants verified (if tenant-specific styling)
- [ ] Browser console: zero JS errors
```

## Index

(Empty as of Day 0 — Foundation session is documentation-only, no UI changes
shipped.)
