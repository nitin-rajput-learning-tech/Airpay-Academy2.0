# Visual evidence owed: the HRMS sync history pages (identity cluster, 2026-10-07)

**Status: NO screenshots yet.** The build session did not deploy to the local Moodle and ran no browser, so everything below is
owed. Capture it in the UAT pass, desktop and 590 px mobile, with test personas only (no real name), and save it in this folder.
Nitin reviews it before `sentientia.users.imported_sync_history` is turned ON for Airpay. Decisions:
`docs/cutover/OWNER-DECISIONS-2026-10-07.md`, rows IDN-07 and XC-IMPORTED-HISTORY-READERS.

## What the pages show, so a reviewer is not surprised

- **The rejected lines of a run are shown only to the person who uploaded it and to cross-tenant callers** (IDN-07). A colleague of
  the same tenant sees the run's counts and no lines. This is not behind a flag and it applies to native runs too: it matches
  what BizLMS showed (a non-admin saw only their own rejected lines), and the lines hold the e-mail, employee code and name of
  prospective employees. A run nobody uploaded (cron) shows its lines to cross-tenant callers only.
- **Imported runs (source `bizlms`) are left out of the list and refused on the detail page while the flag is OFF** (default).

## Screenshots owed

| # | Page | Flag `sentientia.users.imported_sync_history` | Role | What must be visible |
|---|---|---|---|---|
| 1 | `sync_runs.php` | OFF, then ON | the uploader of a native run (tenant admin) | the run list of the tenant; with the flag ON the imported runs join it |
| 2 | `sync_runs.php` | OFF, then ON | a same-tenant colleague who did not upload | the same list (tenant-wide counts) |
| 3 | `sync_runs.php` | OFF, then ON | a cross-tenant admin | every tenant's runs |
| 4 | `sync_run_detail.php?id=` of a native run | n/a | the uploader | the counts and the rejected lines |
| 5 | `sync_run_detail.php?id=` of the same run | n/a | the same-tenant colleague | the counts and NO lines (IDN-07) |
| 6 | `sync_run_detail.php?id=` of a run of another tenant | n/a | a tenant admin | the refusal |
| 7 | `sync_run_detail.php?id=` of an imported run | OFF (the notice), then ON | uploader, colleague, cross-tenant admin | the notice with the flag OFF; lines for the uploader and the cross-tenant admin only with it ON |

Recommended flip for Airpay at cutover (recorded, not made): the flag ON after these have been reviewed. Nitin's call.
