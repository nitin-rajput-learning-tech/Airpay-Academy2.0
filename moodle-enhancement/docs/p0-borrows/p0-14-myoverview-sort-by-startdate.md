# P0 borrow #14 — Sort My Courses by start/end date

**Borrow source**: Moodle 5.2 — block_myoverview nav-sort-selector
**Status**: shipped 2026-05-23 in theme_airpayux 1.0.22-beta
**Migration cost when 5.2 lands**: zero — delete the template override.

---

## What this adds

Two new options in the "Sort by" dropdown on the My Courses block:

| Label | data-value | Use case |
|-------|------------|----------|
| Course start date | `c.startdate desc` | Rolling-classroom cohorts — newest cohort at the top |
| Course end date   | `c.enddate asc`    | Compliance courses with hard-stop close dates — soonest-closing first |

Existing dropdown options (Title, Shortname, Last accessed) are preserved.

## Why this works without a core mod

`enrol_get_my_courses($sort)` validates `$sort` against a whitelist of
`c.<column>` / `ul.<column>` / `ue.<column>` (lib/enrollib.php ~line 603):

```php
$allowedprefixesandfields = [
    'c'  => array_keys($DB->get_columns('course')),
    'ul' => array_keys($DB->get_columns('user_lastaccess')),
    'ue' => array_keys($DB->get_columns('user_enrolments')),
];
```

Both `c.startdate` and `c.enddate` are real columns on `mdl_course`, so
they pass the whitelist check and the ORDER BY fragment is appended
to the SQL unmodified. No PHP change needed — the dropdown items
just need to exist in the template.

## Why this works as a pure theme template override

Moodle templates are resolved in this order:

1. `theme/<themename>/templates/<component>/<name>.mustache`
2. `<component>/templates/<name>.mustache`

A file at `theme/airpayux/templates/block_myoverview/nav-sort-selector.mustache`
shadows the core one with no other plumbing required.

## Limitation: initial active-label render

When a user has previously selected "Start date" and reloads the page,
the active-label region (lines 32-36 of the template) renders empty
because Moodle 5.1's `block_myoverview\output\main::export_for_template`
only sets booleans for `{title, lastaccessed, shortname}`.

Acceptable trade-off:

- The dropdown button text is briefly empty on page paint.
- Moodle's `data-active-item` JS populates the button text from the
  active `<a>` immediately after page load.
- After any click, the button text is correct and stays that way.

On Moodle 5.2 wholesale upgrade we delete this template override and
the upstream renderer surfaces the label server-side. Net delete: ~30
lines override + 4 lang strings.

## Files

| File | Change |
|------|--------|
| `theme/airpayux/templates/block_myoverview/nav-sort-selector.mustache` | NEW — override of core template, adds 2 dropdown items |
| `theme/airpayux/lang/en/theme_airpayux.php` | +2 strings: `sortbystartdate`, `sortbyenddate` |
| `theme/airpayux/lang/hi/theme_airpayux.php` | Hindi parity (+2 strings) |
| `theme/airpayux/version.php` | 2026052321 → 2026052322, 1.0.22-beta |

## Smoke test

1. Site Admin → Site Home → log in as a learner with ≥3 courses
2. Look at the "My Courses" block — dropdown should show 5 items:
   Course full name / Course short name (if site setting on) /
   Last accessed / **Course start date** / **Course end date**
3. Click "Course start date" — courses re-sort by their `startdate`
   field, newest first. Confirm visually that the order matches.
4. Reload — order persists (Moodle stores the choice as
   `user_preference: block_myoverview_user_sort_preference`).

## Related

- ADR-010 — Moodle 5.2 borrow inventory (P0 #14, "Quick filter
  improvement — 1 hr")
- `docs/p0-borrows/p0-9-cm-navigation.md` — sibling helper-only borrow
- `docs/p0-borrows/p0-11-backup-filename-template.md` — sibling admin-setting borrow
