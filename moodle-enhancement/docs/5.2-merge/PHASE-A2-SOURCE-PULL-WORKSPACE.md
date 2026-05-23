# Phase A.2 — Workspace prep for 5.2 source pull

ADR-011 Phase A.2 deliverable (prep). The actual download (hundreds of
MB) is deferred to a session with explicit user approval — this doc
locks in the workspace + the commands.

---

## Why this is a separate session

The 5.2 source tarball is ~150 MB compressed, ~600 MB extracted. The
network pull is a one-shot operation and the file lives outside the
repo (in `D:/Claude Local/moodle-5.2-source/`). Treating it as its
own session means:

- The user explicitly approves the download (network + disk impact).
- The download time doesn't eat session context budget that could be
  used for productive work.
- If we abort or restart, we re-run the prep doc rather than re-pull.

---

## Target workspace layout

```
D:/Claude Local/
├── airpay-ld-os/                   ← our fork repo (existing)
│   └── moodle-enhancement/
│       └── docs/5.2-merge/         ← this directory
│           ├── PHASE-A1-INVENTORY-2026-05-23.md
│           ├── PHASE-A2-SOURCE-PULL-WORKSPACE.md   ← this file
│           ├── PHASE-A3-PHP83-LINT-REPORT.md
│           ├── PHASE-A4-THEME-OVERRIDE-INVENTORY.md
│           ├── PHASE-A4B-CONFLICT-MAP.md           ← created in next session
│           └── PHASE-A5-TEST-INVENTORY.md
├── moodle-5.2-source/              ← NEW, will hold extracted 5.2 source
│   └── moodle/                     ← extracted tarball root
└── moodle-5.2-diffs/               ← NEW, will hold diffs we generate
    ├── 5.2-upstream-full.diff      ← diff -r 5.1.3+ vs 5.2 (full)
    ├── 5.2-theme-conflicts.diff    ← scoped to theme/boost/
    ├── 5.2-lib-conflicts.diff      ← scoped to lib/
    └── 5.2-blocks-conflicts.diff   ← scoped to blocks/
```

The `moodle-5.2-source/` and `moodle-5.2-diffs/` directories MUST live
**outside** the airpay-ld-os repo so they don't accidentally get
committed. Add them to `.gitignore` defensively (already implicit
because they're a parent-sibling directory, not inside the repo).

---

## Commands to run in the next session

### Step 1 — Create the target directories

```powershell
New-Item -ItemType Directory -Path "D:\Claude Local\moodle-5.2-source" -Force
New-Item -ItemType Directory -Path "D:\Claude Local\moodle-5.2-diffs" -Force
```

### Step 2 — Download 5.2 stable tarball

```powershell
$url = "https://download.moodle.org/stable502/moodle-latest-502.tgz"
$out = "D:\Claude Local\moodle-5.2-source\moodle-5.2.tgz"

Invoke-WebRequest -Uri $url -OutFile $out -UseBasicParsing
```

Expected size: ~150 MB. SHA256 checksum check (against
`https://download.moodle.org/stable502/moodle-latest-502.tgz.sha256`)
is part of the next session's first verification step.

### Step 3 — Extract

```powershell
Set-Location "D:\Claude Local\moodle-5.2-source"
tar -xzf moodle-5.2.tgz   # extracts to ./moodle/
Remove-Item moodle-5.2.tgz # save disk space
```

Expected: `D:\Claude Local\moodle-5.2-source\moodle\` with ~30,000
files.

### Step 4 — Generate the full upstream diff

```powershell
cd "D:\Claude Local\moodle-5.2-diffs"

# Full diff — large output, ~50 MB plain text.
diff -r --brief `
    "C:\xampp\htdocs\moodle5\public" `
    "D:\Claude Local\moodle-5.2-source\moodle\public" `
    > 5.2-upstream-brief.txt 2>&1

# Then scoped diffs for the heavy-conflict directories.
diff -r `
    "C:\xampp\htdocs\moodle5\public\theme\boost" `
    "D:\Claude Local\moodle-5.2-source\moodle\public\theme\boost" `
    > 5.2-theme-boost-diff.txt 2>&1

diff -r `
    "C:\xampp\htdocs\moodle5\public\lib" `
    "D:\Claude Local\moodle-5.2-source\moodle\public\lib" `
    > 5.2-lib-diff.txt 2>&1

# Repeat for blocks/myoverview, course/, etc.
```

### Step 5 — Categorise the diff

Three buckets, each into its own file:

```
5.2-additions.txt        ← files in 5.2 but not in 5.1 (no conflict)
5.2-deletions.txt        ← files in 5.1 but not in 5.2 (must remove from our fork)
5.2-modifications.txt    ← files in both with content diffs (conflict candidates)
```

The third file is the input to Phase A.4b (theme conflict map).

---

## What this session will NOT do

- Download anything (defer to user-approved session)
- Modify any file in `C:\xampp\htdocs\moodle5\public\`
- Branch into Phase B activities

---

## Approval checklist for the next session

Before running step 2 above, confirm:

- [ ] Disk free space ≥ 2 GB on `D:\`
- [ ] Network access to `download.moodle.org`
- [ ] No active production deploys (download alone is safe, but the
      follow-up diff generation produces transient high CPU)
- [ ] Nitin's verbal OK on the download window (so we don't surprise
      his connection)

---

## Exit criteria for Phase A.2 (after the next session runs steps 1-5)

- [x] Workspace layout documented (this file)
- [x] Commands documented (this file)
- [ ] 5.2 tarball downloaded + checksum verified
- [ ] 5.2 source extracted to `D:\Claude Local\moodle-5.2-source\`
- [ ] Three categorised diff files in `D:\Claude Local\moodle-5.2-diffs\`
- [ ] Phase A.4b conflict map produced from the diff

The last item is then the input to Phase B.
