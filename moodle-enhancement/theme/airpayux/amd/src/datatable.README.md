# `theme_airpayux/datatable` — shared admin datatable component

**Status:** Phase 0B of the BizLMS Feature Port — production-ready, used by 10 plugins.

This module is the single source of truth for AJAX-paginated admin tables across
Airpay Academy. It replaces the BizLMS `costcenter/amd/src/cardPaginate.js` +
`cardPaginate.mustache` pair with a modern ES module that uses our design tokens.

If you need a tabular admin UI with search / sort / pagination / row-actions,
**use this component**. Don't roll a new one.

---

## Usage from a plugin

### 1. Drop the table mount point in your Mustache template

```mustache
<div data-airpay-table
     data-endpoint="local_airpay_users_list_users"
     data-columns="{{{columns_json}}}"
     data-search-placeholder="Search by name, email, or employee ID…"
     data-perpage="25"
     data-selectable="1"
     data-export-url="{{{export_url}}}">
</div>
```

`data-columns` is a JSON array describing each column:
```json
[
  {"key": "fullname",  "label": "Name",  "sortable": true,  "sortkey": "u.firstname"},
  {"key": "email",     "label": "Email", "sortable": true},
  {"key": "status",    "label": "Status", "sortable": false, "format": "badge"},
  {"key": "lastaccess","label": "Last Access", "sortable": true, "sortkey": "u.lastaccess"}
]
```

| Column field | Meaning |
|---|---|
| `key` | Server response field name (`row.fullname`, `row.email`, …) |
| `label` | Visible header text |
| `sortable` | If `true`, header is clickable + keyboard-activatable |
| `sortkey` | (optional) Backend SQL column override — sent as `sort` param when this header is clicked. Defaults to `key`. |
| `format` | (optional) `"badge"` to wrap value in `<span class="badge {{key}}_class">`; `"html"` to render unescaped HTML; default escapes |

### 2. Initialise from your JS module

```js
require([
    'theme_airpayux/datatable',
    'local_airpay_users/user_actions',
], function(Datatable, UserActions) {
    Datatable.init();
    UserActions.init();

    // Optional: get the instance to call public methods.
    var root = document.querySelector('[data-airpay-table]');
    var dt = Datatable.getInstance(root);

    // Wire a custom filter to the table.
    document.querySelector('[data-airpay-users-filter="orgid"]')
        .addEventListener('change', function(e) {
            dt.setFilter('orgid', e.target.value);
        });
});
```

### 3. Implement the listing web service

The `data-endpoint` value is a Moodle WS function. It receives:
```php
function execute(string $search, string $sort, string $sortdir,
                 int $page, int $perpage, string $filters): array
```

…and must return:
```php
[
    'total'   => int,            // total matching rows across all pages
    'rows'    => array<array>,   // page slice, each row keyed by your columns
    'page'    => int,            // echoed back
    'perpage' => int,            // echoed back
]
```

See `local/airpay_users/classes/external/list_users.php` for a reference
implementation including:
- Cross-tenant `/`-bounded prefix scoping (`(open_path = :exact OR open_path LIKE :prefix)`)
- Sort whitelist (rejects unknown sort keys → falls back to `name`)
- JSON filter bounds (rejects `> 4KB` payloads with `filterstoolong`)
- LIKE wildcard escape (`$DB->sql_like_escape($search)` — '%' treated literally)

All five of those security tests are covered by `tests/external/list_*_test.php`.

---

## Public JS API

```ts
const dt = Datatable.getInstance(rootElement);

// State manipulation
dt.setFilter(key: string, value: any): void;   // page resets to 0, refetches
dt.refresh(): void;                             // refetch with current state
dt.clearSelection(): void;                      // when data-selectable="1"
dt.getSelected(): number[];                     // currently-selected row IDs

// Events the component dispatches on root (bubbles=true)
//   "airpay:datatable:rendered"   detail = current state — fires after each fetch
//   "airpay:datatable:selection"  detail = {selected: number[]} — fires on row check toggle
```

---

## Accessibility (A11Y-1 compliant)

This component meets WCAG 2.1 AA for table interaction:

| Feature | Standard |
|---|---|
| `aria-sort="ascending|descending|none"` on every sortable `<th>`, updated on each sort | 1.3.1 (Info & Relationships) |
| `role="button"` + `tabindex="0"` + Enter/Space keyboard activation on sortable headers | 2.1.1 (Keyboard) |
| Focus restoration on the same column header after re-render | 2.4.3 (Focus Order) |
| High-contrast `:focus-visible` outline (light + dark mode) | 2.4.7 (Focus Visible) |
| `aria-busy="true"` on root during AJAX fetches | 4.1.3 (Status Messages) |
| `aria-live="polite"` on the meta region (e.g. "1–25 of 200") | 4.1.3 (Status Messages) |
| `role="status"` on loading / empty rows; `role="alert"` on errors | 4.1.3 (Status Messages) |
| `scope="col"` on every `<th>` for column-header association | 1.3.1 |
| Decorative caret icons (`<i class="fa fa-caret-up">`) marked `aria-hidden="true"` | 4.1.2 |
| Per-row + select-all checkboxes have `aria-label` | 4.1.2 (Name, Role, Value) |

---

## Phase 0B feature parity vs BizLMS `cardPaginate`

| Feature | Status | Notes |
|---|---|---|
| Server-side pagination via Moodle WS | ✅ | `Ajax.call` to `data-endpoint` |
| Sort by column headers | ✅ | + aria-sort + keyboard activation (improvement over BizLMS) |
| Search box | ✅ | 250ms debounce |
| Empty state | ✅ | Includes "no results for {query}" branch |
| Loading skeleton | ✅ | Spinner row; sets `aria-busy` (improvement) |
| Filter API | ✅ | `setFilter(key, value)` — plugin renders its own filter UI |
| Export CSV button | ✅ | Opt-in via `data-export-url` — appends current `search`, `sort`, `sortdir`, `filter_*` to the URL so the export matches what the user sees |
| Row selection (bulk actions) | ✅ | Opt-in via `data-selectable="1"` — fires `airpay:datatable:selection` |
| Card view | ⏸ deferred | No current consumer needs it; opt-in once a plugin asks |
| Org-hierarchy cascading select | ⏸ deferred | Plugins use simple `<select>` filters today; revisit if a 3-level cascade becomes a real need |
| Uses design tokens (no inline brand colours) | ✅ | `var(--ap-color-primary)`, `var(--ap-color-bg-surface)`, etc. |

---

## How to test changes to this component

1. Edit `amd/src/datatable.js` (ES module).
2. Mirror your edits to `amd/build/datatable.min.js` (AMD format) — there's no
   grunt setup currently; the build is hand-edited to match the source.
3. Deploy both files + `scss/moodle/partials/_datatable.scss` to XAMPP:
   ```powershell
   Copy-Item "moodle-enhancement\theme\airpayux\amd\src\datatable.js" `
             "C:\xampp\htdocs\moodle5\public\theme\airpayux\amd\src\datatable.js"
   Copy-Item "moodle-enhancement\theme\airpayux\amd\build\datatable.min.js" `
             "C:\xampp\htdocs\moodle5\public\theme\airpayux\amd\build\datatable.min.js"
   Copy-Item "moodle-enhancement\theme\airpayux\scss\moodle\partials\_datatable.scss" `
             "C:\xampp\htdocs\moodle5\public\theme\airpayux\scss\moodle\partials\_datatable.scss"
   ```
4. Purge Moodle caches:
   ```powershell
   "C:\xampp\php\php.exe" "C:\xampp\htdocs\moodle5\admin\cli\purge_caches.php"
   ```
5. Hard-refresh browser (Ctrl+Shift+R).
6. Test in light + dark mode + 590px mobile viewport.
7. Test as both siteadmin and a learner-with-airpay-cap to verify behaviour at
   both ends of the capability spectrum.

---

## Plugins that use this component (10)

`airpay_classroom`, `airpay_courses`, `airpay_evaluation`, `airpay_exams`,
`airpay_learningpath`, `airpay_notifications`, `airpay_programs`, `airpay_reports`,
`airpay_skills`, `airpay_users`.

If you're adding an 11th plugin, follow `airpay_users` as the cleanest
reference — it covers create/edit/delete via core_form/modalform, status toggle,
bulk select, and export.
