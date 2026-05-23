# Phase A.2 — Execution log (2026-05-23)

Locked workspace + downloaded + extracted 5.2 source per
ADR-011 Phase A.2.

---

## Download

```
URL:        https://packaging.moodle.org/stable502/moodle-latest-502.tgz
File:       D:\Claude Local\moodle-5.2-source\moodle-5.2.tgz
Size:       89,629,050 bytes (85.48 MB)
SHA256:     7DC5511BD72210919B3E9C521D75024955DEF4DF7D4C63D5B50AA9C67DBF5940
Download:   225.5 s (~380 KB/s)
```

### URL detection note

The URL documented in `PHASE-A2-SOURCE-PULL-WORKSPACE.md` was
`https://download.moodle.org/stable502/moodle-latest-502.tgz` —
that returns 404. The actual working URL is on
`packaging.moodle.org`, not `download.moodle.org`. Doc to be updated.

### Probe results

```
download.moodle.org/releases/latest/moodle-latest.tgz       404
download.moodle.org/stable501/moodle-latest-501.tgz         404
download.moodle.org/releases/supported/moodle-latest.tgz    404
packaging.moodle.org/stable501/moodle-latest-501.tgz        200 (5.1)
packaging.moodle.org/stable502/moodle-latest-502.tgz        200 (5.2 ← this one)
github.com/moodle/moodle/archive/refs/heads/MOODLE_502_STABLE.tar.gz  200 (fallback)
github.com/moodle/moodle/archive/refs/heads/MOODLE_501_STABLE.tar.gz  200 (fallback)
```

The github fallbacks track the live stable branches and would work if
packaging.moodle.org ever goes offline.

---

## Extraction

```
Target:     D:\Claude Local\moodle-5.2-source\moodle\
Command:    tar -xzf moodle-5.2.tgz
```

Extraction status logged in
`C:\Users\NITIN~1.RAJ\AppData\Local\Temp\claude\D--Claude-Local-airpay-ld-os\771ba5e6-d068-45f2-9b15-ebb06c90a602\tasks\b6v74dmev.output`.

The extracted root contains both root-level (admin/, Gruntfile.js,
package.json) and public-web-root (`public/`) — confirming Moodle
5.x's split-layout is unchanged in 5.2. Our XAMPP local layout
(`C:\xampp\htdocs\moodle5\public\`) matches.

---

## Verification (after extraction completes)

These commands are queued for the next session step:

```bash
# 1. Verify the release / version
cat "D:/Claude Local/moodle-5.2-source/moodle/public/version.php" \
  | grep -E "release|version|branch|maturity"

# 2. Spot-check Boost theme exists (our merge target)
ls "D:/Claude Local/moodle-5.2-source/moodle/public/theme/boost/"

# 3. Spot-check the new layout PHP files
ls "D:/Claude Local/moodle-5.2-source/moodle/public/theme/boost/layout/"
```

Expected:
- `version.php` declares `$release = '5.2.0+'` or similar
- `theme/boost/` is the parent theme we forked epsilon from
- `theme/boost/layout/*.php` matches/diverges from our `theme/airpayux/layout/*.php`

---

## Diff trio (next step — Phase A.4b)

Once extraction is verified, generate three diffs into
`D:\Claude Local\moodle-5.2-diffs\`:

```powershell
$src = "C:\xampp\htdocs\moodle5\public"
$dst = "D:\Claude Local\moodle-5.2-source\moodle\public"
$out = "D:\Claude Local\moodle-5.2-diffs"

# Brief diff — list of changed/added/removed files only.
diff -r --brief $src $dst > "$out\5.2-brief-summary.txt" 2>&1

# Full diff for theme/boost (our heaviest conflict surface).
diff -r "$src\theme\boost" "$dst\theme\boost" > "$out\5.2-theme-boost-full.diff" 2>&1

# Full diff for lib/ (core API surface).
diff -r "$src\lib" "$dst\lib" > "$out\5.2-lib-full.diff" 2>&1

# Specific conflict-prone subdirectories.
diff -r "$src\blocks\myoverview" "$dst\blocks\myoverview" > "$out\5.2-block-myoverview.diff" 2>&1
diff -r "$src\backup" "$dst\backup" > "$out\5.2-backup.diff" 2>&1
diff -r "$src\course" "$dst\course" > "$out\5.2-course.diff" 2>&1
```

Output rough sizes expected:
- `5.2-brief-summary.txt`     — ~100-500 KB (file paths only)
- `5.2-theme-boost-full.diff` — ~5-15 MB (full content diffs)
- `5.2-lib-full.diff`         — ~10-30 MB
- per-component diffs         — ~50 KB - 2 MB each

---

## Phase A.4b output target

Once the diffs exist, produce
`docs/5.2-merge/PHASE-A4B-CONFLICT-MAP.md`:

For every file in `theme/airpayux/templates/core/`,
`templates/core_form/`, `templates/core_courseformat/`,
`templates/block_myoverview/`, `layout/*.php`, and `classes/output/*`:

| Our file | Upstream changed? | Lines our delta | Lines upstream delta | Resolution |
|----------|-------------------|-----------------|----------------------|------------|

The resolution column will be one of:
- `take ours` — upstream unchanged or trivial change we can ignore
- `take theirs` — we have no real customisation; absorb upstream
- `cherry-pick` — small upstream improvement (e.g. ARIA additions) to graft onto our version
- `re-implement` — significant upstream rewrite; redo our customisation on top of new structure

Per ADR-011 Option B (slow merge, locked 2026-05-23), the
"re-implement" rows are where the 30-40h budget gets spent.

---

## Workspace footprint after this step

```
D:\Claude Local\moodle-5.2-source\         ~600 MB extracted + 85 MB tarball (kept for now)
D:\Claude Local\moodle-5.2-diffs\          ~20-50 MB once diffs land
```

Both directories live OUTSIDE the airpay-ld-os repo; neither enters
git history. Can be deleted once Phase B is complete.

---

## Exit criteria for Phase A.2 execution

- [x] Workspace dirs created
- [x] Tarball downloaded + SHA256 captured
- [x] Tarball extracted (in progress at time of writing)
- [ ] Version.php verified to show 5.2.x
- [ ] theme/boost layout files spot-checked
- [ ] Diff trio generated into moodle-5.2-diffs/
- [ ] Phase A.4b conflict map produced

Items 4-7 happen in the next continuation step.
