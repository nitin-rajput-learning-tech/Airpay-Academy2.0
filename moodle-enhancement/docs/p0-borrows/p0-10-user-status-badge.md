# P0 borrow #10 — Suspended-user status badge on reports

**Borrow source**: Moodle 5.2 — inline "Suspended" indicator on user lists
**Status**: shipped 2026-05-23 in local_airpay_core 1.5.2 + theme_airpayux 1.0.21-beta
**Migration cost when 5.2 lands**: medium — replace AMD with the 5.2-native column flag, drop the prefetch hook, keep the lang strings as fallback.

---

## The problem in one sentence

When a manager opens the gradebook and sees a 0%-completion row, she can't
tell whether that row's user is *slacking* or has *left the company*.
Without a visible status badge, the audit prep call inevitably becomes a
manual cross-check against HRMS.

Moodle 5.2 adds an inline "Suspended" pill next to user names in every
report renderer. Our backport reproduces the visual + accessible
contract using a server-rendered JSON blob and a thin AMD decorator.

## Surfaces covered

The decorator paints badges on every `<a>` whose href matches
`/user/profile.php?id=N`, restricted to pagetypes:

| Pagetype prefix      | Where you'll see it |
|----------------------|---------------------|
| `grade-report-*`     | Gradebook (grader, overview, history, log) |
| `report-*`           | Activity log, completion, course participants log, outline |
| `user-index`         | Course participants page |
| `course-user`        | Per-user activity report |

Any future report renderer that links to `/user/profile.php?id=N` will
get the badge automatically — no per-renderer work needed.

## Architecture choice — JSON blob, not WS

Three approaches considered:

1. **Server-side renderer override** (rejected — too many touch points).
   `fullname()` is called from dozens of report renderers. Hooking
   each one is a maintenance black hole.

2. **WS round-trip from AMD** (rejected — over-engineered).
   Adds a WS endpoint, capability scope, tenant filter, params schema,
   PHPUnit for params shape, and a network round-trip per page load.

3. **Server pre-renders JSON in `<body>`** (chosen).
   One DB query in `theme_airpayux_before_standard_top_of_body_html()`,
   scoped to the caller's tenant via `$USER->open_path`. The AMD
   decorator reads the JSON, decorates the DOM, watches for AJAX
   mutations. No WS, no extra round-trip, no per-renderer hook.

## Data flow

```
Request → theme_airpayux_before_standard_top_of_body_html()
            │
            ├── Filter by pagetype (only report-y pages)
            ├── Read $USER->open_path (tenant scope)
            ├── Query mdl_user WHERE (suspended=1 OR deleted=1) AND open_path LIKE ?
            └── Output <script id="airpay-user-status-data" type="application/json">

Browser → AMD theme_airpayux/user_status_badge#init()
            │
            ├── Read JSON blob
            ├── Load translated strings (en/hi)
            ├── Paint badges on every a[href*="/user/profile.php"]
            └── MutationObserver — re-paint after AJAX paginators
```

## Tenant safety

The DB query in `lib.php` filters by `open_path LIKE :path` where
`:path = $USER->open_path . '%'`. This restricts the prefetch to users
in the caller's tenant subtree, matching the BizLMS tenant model.

Site admins (who can browse any tenant) get the badges for users in
their *own* tenant only. A Site Admin reviewing a Public-tenant course
won't see suspended Airpay users in that report (correct — the report
is about Public-tenant enrolments).

If `$USER->open_path` is empty (test fixture without the column), the
function silently no-ops. Never blocks a real page on a status-badge
prefetch.

## Accessibility

Each badge has:
- `aria-label="Account status: Suspended"` (translatable, comes from
  `userstatus_badge_aria` with the status substituted)
- `title="Suspended"` (tooltip on hover)
- `role="img"` so screen readers announce it once, not as a navigation
  element
- Order — the badge is inserted **after** the link, so reading order is
  "Jane Doe, Suspended" not the other way around

The visible text is the same as the announced text. On `≤590px` the
text collapses to a coloured dot but the aria-label stays — touch users
can long-press for the tooltip.

## Files

| File | Role |
|------|------|
| `local/airpay_core/classes/user_status.php` | Cached helper class; suspended/active/deleted lookup + badge HTML |
| `local/airpay_core/tests/user_status_test.php` | PHPUnit — 9 cases |
| `local/airpay_core/lang/en/local_airpay_core.php` | +4 strings: `userstatus_suspended`, `userstatus_deleted`, `userstatus_badge_aria`, `privacy:metadata:userstatus` |
| `local/airpay_core/lang/hi/local_airpay_core.php` | Hindi parity (+4 strings) |
| `theme/airpayux/lib.php` | `theme_airpayux_before_standard_top_of_body_html()` — JSON blob injector |
| `theme/airpayux/amd/src/user_status_badge.js` | AMD decorator |
| `theme/airpayux/amd/build/user_status_badge.min.js` | Compiled bundle (rollup via grunt-amd.bat) |
| `theme/airpayux/scss/moodle/partials/_components-user-status-badge.scss` | Badge styles + mobile dot variant + dark-mode flip |
| `theme/airpayux/scss/moodle/custom_changes.scss` | `@import` of the partial |

## Migration on 5.2 wholesale upgrade

1. Remove the JSON blob injector — 5.2 ships the suspension flag
   inline on the report column markup, no prefetch needed.
2. Drop `amd/src/user_status_badge.js` and its build artifact — 5.2's
   `report_renderer` paints the badge server-side.
3. Keep `local_airpay_core\user_status` — it's a useful helper for our
   own renderers (compliance reports, Sentientia Live participant
   panel) that won't go away on the upstream switch.
4. Keep the lang strings (5.2 uses a different identifier but ours can
   stay; theme can prefer the local one).
5. SCSS partial stays — visual contract matches 5.2.

Net delete: `~250 lines (AMD + JSON injector). Net keep: ~150 lines (helper + lang + SCSS).`

## Related

- ADR-009 — Detection consistency (§3 on tenant-filter placement)
- ADR-010 — Moodle 5.2 borrow inventory (P0 #10 row)
- `docs/p0-borrows/p0-9-cm-navigation.md` — sibling backport pattern
