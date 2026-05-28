# Workspace Policy — git ↔ deployed contract

**Status:** Living document. Last updated: 2026-05-28
**Audit ref:** `docs/audits/PLATFORM-STABILIZATION-AUDIT-2026-05-28.md`
F-091, F-092, F-095, F-099 (Bucket E3)

---

## Why this exists

During Phase 1 of the Stabilization Audit we found 30+ files that existed
in the deployed XAMPP copy of two plugins (`local/airpay_pages`,
`local/airpay_lifecycle`) but were **missing from the git workspace**.

This is the most dangerous form of drift:

- `DEPLOYMENT-RUNBOOK.md` instructs IT to deploy from the workspace
- IT runs `rsync -a moodle-enhancement/local/airpay_pages/ /var/www/.../local/airpay_pages/`
- The deployed plugin is **smaller than what production currently runs**
- Features silently break — and IT has no way to know what's missing

We fixed the immediate F-091 / F-092 case by back-porting 30 files.
This doc is the policy that prevents recurrence.

---

## The contract

**The workspace is the source of truth.** The deployed XAMPP copy is a
build artifact, not a parallel branch.

Concretely:

1. **No file may exist in deployed-only.** Every PHP, Mustache, SCSS,
   JS, lang, XMLDB, or class file under
   `C:\xampp\htdocs\moodle5\public\local\airpay_*\` must have an
   identical copy under
   `D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_*\`.

2. **Workspace-to-deployed flow is one-way.** Edits land in workspace
   first, deploy second. Never edit deployed files directly except for
   transient debugging — and then back-port immediately.

3. **The gate enforces it.** `tools/check_workspace_sync.sh` runs on
   every commit (soft) and on every CI build (hard, `--strict`).

---

## The mechanism

### Local pre-commit (soft warning)

The pre-commit hook (`.claude/hooks/pre-commit.sh`) calls
`check_workspace_sync.sh` without `--strict`. If drift exists, it warns
but does not block — the commit author may have deployed-only files in
flight that they're about to back-port.

This means the warning surfaces _early_, so the author can decide:

- "Yes, I just edited a deployed file. Let me back-port it now."
- "Yes, but I'm splitting commits — drift will close in commit #2."

### CI gate (hard block)

GitHub Actions `.github/workflows/ci.yml` runs the same script with
`--strict`. If drift exists at PR merge time, the workflow fails. No
exceptions; the green checkmark is the merge gate.

Run from the repo root:

```bash
tools/check_workspace_sync.sh --strict
```

### On-demand pre-deploy

Before deploying to production (per `DEPLOYMENT-RUNBOOK.md`), IT runs
the script as part of the pre-flight checklist. If the workspace passed
CI but local drift exists, that's a sign that this clone of the repo is
behind the canonical workspace — `git pull` first.

---

## Common drift scenarios + remediation

### Scenario A: "I edited deployed files for a quick local test"

Symptom: drift gate shows deployed-only files in `local/airpay_X/`.

Fix:
```bash
cp -r /c/xampp/htdocs/moodle5/public/local/airpay_X/<changed> \
      moodle-enhancement/local/airpay_X/<changed>
git add moodle-enhancement/local/airpay_X/<changed>
git commit
```

### Scenario B: "A teammate deployed a file I don't have in my workspace"

Symptom: drift gate shows deployed-only files. `git status` shows them
as untracked (because they were never workspace-committed).

Fix:
```bash
git pull origin production
tools/check_workspace_sync.sh   # should now report clean
```

If still dirty: the teammate forgot to commit. Ping them.

### Scenario C: "Moodle's plugin loader auto-created a file I don't own"

Symptom: drift gate shows `*_BACKUP.php`, `*.log`, or `node_modules/`
files in deployed-only.

These are tool artifacts and the script's skip list (
`check_workspace_sync.sh` lines 117–125) explicitly ignores them:

- `*_BACKUP*`, `*MONOLITH*` — local-only backup files
- `.git/`, `.vscode/`, `Claude/` — IDE/tool artifacts
- `node_modules/`, `vendor/` — dependency directories
- `*.min.js.map` — auto-built sourcemaps
- `*.log`, `*.tmp` — transient files

If a new artifact type appears, add it to the skip list with a comment
explaining why.

### Scenario D: "I want to delete a file from deployed but keep it in workspace"

You almost never want this. The reverse is the normal case — you delete
in workspace, then re-deploy to remove from deployed.

If you genuinely need to deprecate a deployed file without removing it
from workspace (e.g. because it's referenced in a state card), use a
file-content marker:

```php
<?php
// SOFT-DEPRECATED 2026-XX-XX — kept for state-card reference.
// Renderer is unwired; do not call directly.
defined('MOODLE_INTERNAL') || die();
```

And delete the file from deployed manually + document in state-card.

---

## Edge cases — what the gate does NOT check

1. **It does not check workspace-only files.** A file present in
   workspace but missing from deployed is by definition a not-yet-
   deployed change, which is normal. The gate is one-directional.

2. **It does not check file contents.** Two files with the same path
   but different bytes count as in-sync. (We've considered MD5 mode
   but the false-positive rate from CRLF / encoding differences makes
   it too noisy. PHP lint + Mustache validate handle content drift
   on the workspace side.)

3. **It does not check core Moodle files.** Per CLAUDE.md §13, core
   files are tracked in `docs/core-mods/`. The gate's COMPONENTS list
   is local plugins + airpayux theme only.

4. **It does not check 3rd-party vendored code.** `blocks/learnerscript`
   is upstream-vendored; we own one file (`classes/observer.php` for
   F-077 CLI guard). The rest is excluded.

---

## Cross-reference

- The gate script: `tools/check_workspace_sync.sh`
- Pre-commit hook: `.claude/hooks/pre-commit.sh` (CHECK 12)
- CI gate: `.github/workflows/ci.yml` (workspace-sync job)
- Deployment runbook: `docs/operations/DEPLOYMENT-RUNBOOK.md`
- Audit case study: `docs/audits/PLATFORM-STABILIZATION-AUDIT-2026-05-28.md` F-091, F-092
- State-card freshness gate (sibling policy): `tools/check_state_card_freshness.sh`
