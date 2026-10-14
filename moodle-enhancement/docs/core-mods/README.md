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
