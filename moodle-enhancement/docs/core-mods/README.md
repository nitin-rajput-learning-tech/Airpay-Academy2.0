# Core Modifications Ledger — Sentientia LMS

Every modification to a Moodle core file is recorded here, one file per change.
This ledger is the source of truth for upgrade-merge work when we pull new
Moodle releases from upstream.

## Why this exists

Day 0 ADR-001 lifted the previous "NEVER modify Moodle core files" rule. We can
now touch core when justified. But every touch creates a merge conflict the
next time Moodle ships a release we want to pull.

This ledger lets us:
1. Know WHICH core files we've touched (so we know which merges to inspect)
2. Know WHY we touched each (so we can decide whether the change is still needed against newer upstream)
3. Re-apply each change cleanly to a fresh upstream pull

## File-naming convention

```
YYYY-MM-DD-<short-slug>.md
```

Example: `2026-06-01-add-customer-level-context.md`

## Required content per record

Each `.md` file MUST include:

1. **Date** of modification
2. **Author** (Claude session ID or human)
3. **File modified** (absolute Moodle path, e.g., `lib/accesslib.php`)
4. **Line range** before modification
5. **Justification** — why a plugin couldn't reach this
6. **Before** code excerpt
7. **After** code excerpt
8. **Upgrade-merge notes** — what to watch for when next pulling Moodle upstream
9. **Marker** — confirm the modification site has `// SENTIENTIA-CORE-MOD: <reason>` inline comment

## Tagging convention

Inside the actual modified core file, mark every change site:

```php
// SENTIENTIA-CORE-MOD: <short reason> — see docs/core-mods/YYYY-MM-DD-<slug>.md
<modified code>
// END SENTIENTIA-CORE-MOD
```

This makes it grep-able when scanning a fresh upstream Moodle for our changes.

## How the rules are checked

Two checks apply the same rules: the pre-commit hook (`.claude/hooks/pre-commit.sh`
CHECK 6, per commit, can be skipped with `--no-verify`) and CI
(`.github/workflows/core-mods.yml` running `tools/ci/check-core-mods.sh`, on every
pull request and every push to production, no paths filter). A changed file under
`lib/`, `admin/`, `course/`, `my/`, `mod/assign/` or `mod/quiz/` (also under `public/`)
fails unless a record here names its exact path and the file carries a
`SENTIENTIA-CORE-MOD` marker. Our own plugins (`admin/tool/sentientia_*`,
`mod/quiz/accessrule/sentientia_*`) and the `my/` overlays are not checked. JSON,
source maps, CSV, binary files and deletions need only the record. This README is
never read as a record.

On a pull request CI blocks the merge. A push to production has landed before CI
starts, so there CI only reports, but it checks everything since a pinned baseline
(the 2026-05-23 Moodle 5.2 merge), so a violation stays red until it is fixed.

### Undoing a core mod (revert)

When upstream fixes the problem or a patch is rolled back, the file goes back to its
upstream content and loses its marker. A file whose previous version had a
`SENTIENTIA-CORE-MOD` marker and whose new version has none counts as a revert: it
needs only the record. **Keep the record** and add a `Reverted` note (date, commit,
why), and update the Index below. Deleting the record makes the revert fail.

### Upstream Moodle or vendor imports

Upstream files carry no marker and a release changes hundreds of them, so an import
does not list every file. Its record carries one line per imported directory, at the
start of a line:

```
Core-Mods-Upstream-Sync: <guarded dir prefix>/ <upstream version>
```

for example `Core-Mods-Upstream-Sync: admin/tool/certificate/ v5.0.3 (2025041403)` for
a vendor plugin update, or one line each for `lib/`, `admin/`, `course/`, `my/`,
`mod/assign/` and `mod/quiz/` for a Moodle release. The prefix must be one of the
guarded dirs or a directory under one, and must end with `/`. The line says: every
file under the prefix is upstream content, plus our recorded and marked patches, as
of the commit that adds the line. Files under the prefix that are unchanged since that
commit need neither a record path nor a marker.

- Commit the line in the same commit as the import (the hook only honours a line that
  is staged with the commit) or after it, never before it.
- Re-apply our patches (with their markers) after the import. A file edited after the
  sync commit follows the normal rules again.
- A line already in the history before the checked range exempts nothing. Adding,
  editing or moving the line re-asserts the claim as of that commit, so do that only
  for a real import.
- Neither check can prove that the files are upstream content: a local edit committed
  with the import passes. Review the import commit against the upstream release.
- The line is a record too, so keep it when the record is updated later.

## Index

No existing Moodle core file is edited on Moodle 5.3 (ADR-033). The records below are either
vendor-plugin patches (`// SENTIENTIA-CORE-MOD (vendor)`), additive overlay files, or notes. The status column is the verdict for
Moodle 5.3; 5.2 stays the fallback target and keeps its notes.

| Record | What it is | Status on 5.3 |
|---|---|---|
| `2026-05-20-moodle-to-sentientia-rename.md` | Rename map of user-visible "Moodle" strings (pending approval, no code) | Version independent |
| `2026-05-23-certificate-image-imageinfo-guard.md` | tool_certificate image-element guard (vendor patch) | Still required; applied to the package source (top-level tree) |
| `2026-05-29-tool_certificate-hi-pack.md` | Additive Hindi language file for tool_certificate (staged) | Version independent |
| `2026-06-04-open-substrate-ownership.md` | 37 `open_*` columns on `user`, 18 on `course` (raw ALTER) | No collision; runtime check in the upgrade rehearsal |
| `2026-06-11-setuplib-ini-get-bool-guard.md` | `ini_get_bool()` guard in `lib/setuplib.php` plus a config.php polyfill (5.2 only) | **Retired on 5.3**: do not apply the guard, no polyfill (it is fatal alone); fallback recorded |
| `2026-06-19-my-overlays-5.2.md` | `my/dashboard.php` and `my/switchrole.php` | Additive overlay on 5.3 (new files, both required); `dropdown.mustache` no longer shipped |
| `2026-09-03-tool-certificate-5.2-reset-caches.md` | `issue_handler::reset_caches(): void` (vendor patch) | Still required |
| `2026-10-08-tool-certificate-duration-defaultunit.md` | `defaulunit` typo fix (vendor patch; throws on 5.3) | Required on 5.3 |
| `2026-10-08-learnerscript-modal-factory-port.md` | learnerscript report modals ported off the removed modal factory (vendor patch) | Required on 5.2 and 5.3 |

## Decision criteria

Before touching a core file, ask:

1. **Can a plugin reach this?** Hooks, callbacks, observers, renderer overrides — Moodle has many extension points. Try those first.
2. **Is the change additive or destructive?** Adding a method is safer than rewriting a function.
3. **What's the upstream-merge risk?** A change in a file Moodle modifies frequently is a maintenance burden forever.
4. **Could we contribute upstream?** If our change is generally useful, push it as a Moodle Tracker contribution instead.

If after all four the answer is "must touch core", record the change here.
