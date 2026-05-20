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

(Empty as of Day 0 — no core modifications yet. ADR-001 enables them but we
haven't shipped any. First entry expected during Phase 1 multi-customer
accesslib extension.)

## Decision criteria

Before touching a core file, ask:

1. **Can a plugin reach this?** Hooks, callbacks, observers, renderer overrides — Moodle has many extension points. Try those first.
2. **Is the change additive or destructive?** Adding a method is safer than rewriting a function.
3. **What's the upstream-merge risk?** A change in a file Moodle modifies frequently is a maintenance burden forever.
4. **Could we contribute upstream?** If our change is generally useful, push it as a Moodle Tracker contribution instead.

If after all four the answer is "must touch core", record the change here.
