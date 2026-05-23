# P0 borrow #11 — Configurable backup filename template

**Borrow source**: Moodle 5.2 — admin-configurable backup filename template
**Status**: shipped 2026-05-23 in local_airpay_core 1.5.3 (helper + admin setting only — no core mod)
**Migration cost when 5.2 lands**: replace helper with upstream `\core_backup\filename_template`

---

## Scope (intentionally narrow — 1 hour effort per ADR-010)

This borrow ships **the API surface**, not the full Moodle integration.
Moodle 5.2 wires its filename template into the `backup_plan_dbops`
flow that vanilla course backups go through. To do that in 5.1 we'd
need a core mod (touching `backup/util/dbops/backup_plan_dbops.class.php`),
which is more risk than the borrow is worth before the wholesale 5.2
upgrade.

What we ship today:

1. **Helper class** `\local_airpay_core\backup_filename::resolve()` —
   token-substitution filename builder.
2. **Admin setting** at Site Admin → Plugins → Local plugins → Airpay
   Core, exposing the configurable template.
3. **Token cheat-sheet** auto-rendered next to the field from
   `backup_filename::token_help()` — adding a token to the helper
   automatically updates the admin help.

What opts in:

- The **SENTIENTIA SCORM pipeline** when packaging
  `content/scorm-output/*-scorm.zip` — switches to
  `backup_filename::resolve(['type' => 'scorm', ...])`.
- Any future **Sentientia LMS export job** (course→ZIP, learning-path
  →PDF, audit-trail export).

What stays on Moodle's built-in filename builder (for now):

- Vanilla **course backups** via /backup/backup.php — these still use
  Moodle's own filename builder. On 5.2 they'll route through the
  upstream config we mirror, no double-handling needed.

## Tokens

```
{site}       Site shortname (from $SITE->shortname, sanitised)
{customer}   Customer name (airpay today; customer-N when Customer 2 lands)
{tenant}     Tenant root id (tenant-N), best-effort from $USER->open_path
{type}       Backup type: course | activity | section | export | scorm
{id}         Subject numeric id (courseid, cmid, etc)
{shortname}  Course or section shortname, sanitised
{date}       YYYYMMDD-HHMM in server timezone
{iso}        ISO-8601 compact YYYYMMDDTHHMMSS
```

Every token value is run through `clean_filename()` plus
`[^a-z0-9-]` normalisation. Spaces become dashes; em-dashes,
brackets, dots, and other shell metacharacters are stripped.
Path-traversal sequences (`..`, `/`, `\`) are double-checked at the
final string assembly.

## Safety guarantees

- **Max filename length 200 chars** (excluding extension). Truncated
  cleanly mid-string; extension is *never* dropped.
- **Empty-input fallback** — even with no useful context the helper
  returns `sentientia-export-{unix}.mbz`. Critical for the SENTIENTIA
  pipeline so a malformed CSV row doesn't crash the batch.
- **Unrecognised tokens are left as their sanitised literal** (e.g.
  `{wrongtoken}` becomes `wrongtoken` in the output). Easier to
  spot during testing than silent expansion to empty.

## Example calls

```php
use local_airpay_core\backup_filename;

// Default template (configurable in admin)
$name = backup_filename::resolve([
    'type' => 'course',
    'id' => 42,
    'shortname' => 'pci-dss',
]);
// → 'backup-moodle2-course-42-pci-dss-20260523-1430.mbz'

// SCORM pipeline override
$name = backup_filename::resolve([
    'type' => 'scorm',
    'id' => 7,
    'shortname' => 'kyc',
    'extension' => 'zip',
]);
// → 'backup-moodle2-scorm-7-kyc-20260523-1430.zip'

// Caller-provided template
$name = backup_filename::resolve([
    'template' => '{site}-{customer}-{type}-{id}-{iso}',
    'type' => 'export',
    'id' => 99,
]);
// → 'airpay-academy-airpay-export-99-20260523t143052.mbz'
```

## Admin setting

Site Admin → Plugins → Local plugins → Airpay Core (added by P0 #11):

| Field | Value |
|-------|-------|
| Default backup filename template | `backup-moodle2-{type}-{id}-{shortname}-{date}` |
| Available tokens | (cheat sheet rendered from `token_help()`) |

The setting writes to `config_plugins` row `(plugin=local_airpay_core,
name=backup_filename_template)`. Default value matches the literal
Moodle 5.1 filename builder so flipping the setting is opt-in.

## PHPUnit

`local/airpay_core/tests/backup_filename_test.php` — 10 cases:

- Default template substitutes type/id/date
- Shortname sanitisation (spaces, parens, em-dashes, exclamations)
- Path-traversal blocking (`/`, `\`, `..`)
- Custom template with all tokens
- Extension override
- Empty-context fallback
- Max-length enforcement
- Token help dictionary completeness
- Configured template is used when no override
- Unrecognised tokens become sanitised literals

## Migration on Moodle 5.2 wholesale upgrade

1. Replace `\local_airpay_core\backup_filename::resolve(['type' => $t, 'id' => $id, ...])`
   call sites with the upstream filename-template API (likely
   `\core_backup\filename_template::build($context)`).
2. Keep the admin setting but rename the key to match 5.2's
   `backupgeneralfilenametemplate` (or equivalent). Migration script
   in `db/upgrade.php` copies the old value across.
3. Delete `classes/backup_filename.php` and `tests/backup_filename_test.php`.
4. Keep the lang strings — they'll still be useful for the admin page.

Net delete: ~200 lines (helper + tests). Net keep: ~50 lines (admin
setting + lang strings).

## Related

- ADR-010 — Moodle 5.2 borrow inventory (P0 #11 row, "Useful for
  Sentientia LMS exports — 1 hr")
- `docs/p0-borrows/p0-9-cm-navigation.md` — sibling helper-only borrow
- `docs/p0-borrows/p0-10-user-status-badge.md` — sibling helper + theme borrow
