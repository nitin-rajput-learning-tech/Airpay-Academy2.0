# Core-mod record: `tool_certificate` (vendor plugin) x Moodle 5.3: duration element `defaulunit` typo

- **Date:** 2026-10-08 (Moodle 5.3 compatibility pass, finding F2 / fix FX-07)
- **Tag at the site:** `// SENTIENTIA-CORE-MOD (vendor)`
- **File:** `admin/tool/certificate/classes/certificate.php`, `certificate::add_expirydate_to_form()`, line 624
  (top-level tree, which is the package source; the 5.1 webroot and the 5.2 staging tree carry the unpatched vendor copy)

## Before

```php
$group[] =& $mform->createElement('duration', 'expirydaterelative', '', ['defaulunit' => DAYSECS,
    'units' => [DAYSECS, WEEKSECS], ]);
```

## After

```php
// SENTIENTIA-CORE-MOD (vendor): ... (see this record)
$group[] =& $mform->createElement('duration', 'expirydaterelative', '', ['defaultunit' => DAYSECS,
    'units' => [DAYSECS, WEEKSECS], ]);
```

## Why

The option key is misspelt (`defaulunit`). Moodle's `MoodleQuickForm_duration` only reads `defaultunit`.

- **Moodle 5.1 and 5.2** ignore the unknown key, so the default unit stays the class default (`MINSECS`).
  `MINSECS` is not one of the offered units (`DAYSECS`, `WEEKSECS`), which those versions tolerate: the select
  simply starts on its first option.
- **Moodle 5.3** (`public/lib/form/duration.php:112-117`) adds a check that the default unit is one of the
  restricted `units`, and throws `coding_exception` ("... is not one of the units allowed for this
  MoodleQuickForm_duration element") when it is not. The element is built by
  `classes/form/certificate_issues.php:79` (the dynamic form behind "Issue certificates" in the templates
  list and the issues list), so on 5.3 L&D admins could not issue a certificate by hand.

Spelling the key correctly makes the default `DAYSECS`, which is in `units`, so the form builds on every version.

## Behaviour change on 5.1 / 5.2

A blank relative-expiry field now shows "Days" as the preselected unit instead of the first option in the
select ("Weeks", because `units` is displayed largest first). That is what the vendor code always meant.
Saved values are unchanged: only the display default of an empty field moves.

## Also touched in the same commit: the direct-access guard

`classes/certificate.php` and `element/image/classes/element.php` are upstream class files that carry no
`defined('MOODLE_INTERNAL') || die();` line. The repository pre-commit gate (CHECK 2, "MOODLE_INTERNAL guard") rejects any staged
`classes/` PHP file without one, so a patched vendor file cannot be committed unchanged. Both files now carry the
standard guard right after their `use` block, tagged `SENTIENTIA-CORE-MOD (vendor)`. It is inert in every Moodle entry
point (`MOODLE_INTERNAL` is defined before any class is autoloaded) and drops out with the rest of the patches when a
vendor release replaces the files.

## Upgrade-safety (how it merges with a future vendor update)

- One-line key rename inside a larger statement; it applies cleanly to any `tool_certificate` release that
  still has the misspelt key. Search for `SENTIENTIA-CORE-MOD (vendor)` or `defaulunit` to find it.
- The bundled `tool_certificate` is the 4.5.x line (`version.php` declares `supported = [400, 405]`), so on 5.3
  the plugin check page shows "not supported" (informational, not blocking). When a 5.x-compatible upstream
  release is taken, check whether upstream fixed the typo; if it did, take theirs and drop this patch. The
  other two vendor patches are listed below.
- Not a Moodle core file: nothing under `public/lib` is touched.

## The three tool_certificate vendor patches on 5.3

| Record | File | 5.3 status |
|---|---|---|
| `2026-09-03-tool-certificate-5.2-reset-caches.md` | `classes/customfield/issue_handler.php`: `reset_caches(): void` | Still required (5.3 `customfield/classes/handler.php` declares `: void`) |
| `2026-05-23-certificate-image-imageinfo-guard.md` | `element/image/classes/element.php`: non-image guard | Still required; re-applied to the package source on 2026-10-08 (see its addendum) |
| this record | `classes/certificate.php`: `defaultunit` | Required on 5.3 |

## Detected by

A static diff of the 5.1/5.2 and 5.3 form-element sources against every `createElement('duration', ...)` call in the
repository. Not yet exercised at runtime: when a 5.3 instance exists, open "Issue certificates" from the
templates list as a site admin and confirm the dialog builds.
