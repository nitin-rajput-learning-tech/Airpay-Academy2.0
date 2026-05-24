# Contributing — Parallel Session Conventions

**Audience:** Any Claude session (foreground or spawned chip) working on
the Sentientia LMS / Airpay Academy repo.

**Purpose:** Prevent merge conflicts and convention drift when multiple
sessions run in parallel across separate git worktrees.

> This is the **project-specific** contribution guide. The root-level
> `CONTRIBUTING.md` is upstream Moodle's contribution guide (kept
> for plugin-directory listing compliance). For our overrides, read THIS
> file.

---

## 0. Read first

Every session, before any tool call:

1. `D:\Claude Local\airpay-ld-os\CLAUDE.md` — operating rules, safety gates, hard prohibitions.
2. `D:\Claude Local\airpay-ld-os\moodle-enhancement\PROJECT-STATE.md` — current phase + last shipped commits.
3. This file — parallel-session conventions.
4. The latest ADR in `moodle-enhancement\docs\adr\` (architectural context).

---

## 1. Parallel-session rule of one-touch ownership

When 2+ sessions are dispatched to the same repo via spawned chips (each
running in its own git worktree), conflicts arise when both sessions
modify the SAME file. The rule:

**Each chip owns ONE plugin / ONE feature scope. Do not touch files
outside that scope unless the chip's prompt explicitly authorises it.**

Examples of safe cross-session work:
- Chip A creates `local/sentientia_aiquiz/`, Chip B creates `local/sentientia_calendar/`. Disjoint plugin dirs → no conflict.
- Chip A adds tests under `local/sentientia_aiquiz/tests/`. Chip B adds tests under `local/sentientia_calendar/tests/`. Same convention, different files.

Examples of conflict risk that needs coordination:
- Both chips append to `moodle-enhancement/PROJECT-STATE.md` (every chip wants to log its work). See §3.
- Both chips register a feature flag in `local_airpay_core/db/feature_flags.php`. See §2.
- Both chips claim ADR-012 in `docs/adr/`. See §4.

---

## 2. Feature flag registration — append-only

**File:** `moodle-enhancement/local/airpay_core/db/feature_flags.php`

This is the registry every plugin's feature flags land in. To prevent
collision when two chips both want to register a new flag:

1. **Always append at the end of the appropriate category array**, never insert in the middle.
2. **Use a unique `flag_key`** namespaced by plugin: `<plugin>.<feature>.enabled` (e.g. `sentientia.aiquiz.enabled`, `sentientia.calendar_sync.enabled`).
3. **Default to OFF** (per CLAUDE.md §5 mandatory rule).
4. **If you see a merge conflict on this file**, resolve by keeping all entries from both branches (additive). Sort alphabetically by `flag_key` within each category.
5. **Don't modify other plugins' flag entries.** If you need to change their default state at runtime, do it via the customer-scope DB layer, not the registry.

Reference: `docs/adr/ADR-002-customer-level-feature-flags.md`.

---

## 3. PROJECT-STATE.md append convention

**File:** `moodle-enhancement/PROJECT-STATE.md`

This file is the running session log. Every session SHOULD append a brief entry. Conflict-avoidance pattern:

1. Each session appends a **new H2 section at the end of the file**, with its own anchor: `## 🎯 <Feature name> — <date>`.
2. **Never edit existing H2 sections** from prior sessions, even your own — they're already historical.
3. The first line under the H2 should reference your commit hash(es), so reviewers can drill back to the diff.
4. On merge conflict, resolution is to keep BOTH sections (additive timeline).

---

## 4. ADR numbering — reserve before claiming

**Directory:** `moodle-enhancement/docs/adr/`

ADRs are numbered sequentially (`ADR-001` through `ADR-NNN`). Two parallel chips writing ADR-012 simultaneously will conflict at filename level.

Convention:

1. Before writing your ADR, **list the existing dir to see the highest number**: `ls docs/adr/`.
2. **Claim the next number + 1 above any reserved-but-pending numbers** listed below.
3. Filename pattern: `ADR-NNN-<slug>.md`.
4. Once committed, add your number + topic to the table at the bottom of this file.

**Reserved (in-flight on cloud chips, may be uncommitted yet):**

| ADR # | Topic | Chip / session |
|-------|-------|----------------|
| ADR-012 | AI Quiz Generation | spawned 2026-05-24 |
| ADR-013 | Calendar Sync (ICS export) | spawned 2026-05-24 |
| ADR-014 | Real-time Leaderboards | spawned 2026-05-24 |

If you see one of these numbers already used in `docs/adr/` AND you were intending to claim it, take the NEXT available number and update this table.

---

## 5. State cards per plugin

**Directory:** `moodle-enhancement/state-cards/`

Every plugin you touch in a session SHOULD have a state card `<plugin-name>-state.md` describing:
- Current version + release string from `version.php`
- DB tables it owns (with schema sketch)
- Key files (lib, classes, templates)
- Feature flags it registers
- Tests + coverage status
- Open items / TODOs

If the card doesn't exist, create it. If it exists, update it.

Pure-additive — no merge conflict risk because each plugin has its own file.

---

## 6. Visual evidence — date-stamped dirs

**Pattern:** `moodle-enhancement/docs/visual-evidence/YYYY-MM-DD/`

Each session puts screenshots into a date-stamped subdir. Two sessions on the same day share a dir, but file-name collisions are unlikely because they touch different features.

Convention:
- 3+ screenshots minimum for UI-touching work.
- Include a `README.md` in the dir noting what changed.
- File-naming pattern: `<surface>-<state>.png` (e.g. `aiquiz-generate-form.png`, `calendar-subscription-url.png`).

---

## 7. Hindi parity — mandatory

Every new user-facing string MUST land in BOTH `lang/en/<plugin>.php` and `lang/hi/<plugin>.php` in the same commit. 100% parity is enforced by `local_airpay_core/hindi_audit.php`.

Convention:
- Translate to Hindi via the export pipeline at repo root: `export_translations.php` produces a CSV; paste into a translator; re-import.
- Don't leave English strings in lang/hi/ files as placeholders — the audit will flag them.

---

## 8. Git protocol — co-author every commit

Per CLAUDE.md §14:
- Branch: `production` on `nitin-rajput-learning-tech/Airpay-Academy2.0`.
- Every commit ends with `Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>` (or whichever Claude model version is producing the commit).
- Push after every commit. Don't accumulate.
- Don't skip pre-commit hooks (`--no-verify` is forbidden unless the user explicitly asks).
- Don't `--amend` if a pre-commit hook fails. Fix and create a NEW commit instead — hook failure means the commit didn't land, so `--amend` would mutate the previous (already-good) commit.

---

## 9. Hard prohibitions (verbatim from CLAUDE.md)

- NEVER deploy to live `airpay.academy` server. Local repo + GitHub `production` branch only.
- NEVER POST to live Moodle / ElevenLabs / Gamma / Anthropic APIs without explicit `[CONFIRM]` from the user (they cost money).
- NEVER skip pre-commit hooks.
- NEVER bypass signing (`--no-gpg-sign`).
- NEVER write project files outside `D:\Claude Local\airpay-ld-os\`.
- NEVER touch Moodle core files without an entry in `docs/core-mods/` and a `// SENTIENTIA-CORE-MOD: <reason>` marker at the modification site.
- NEVER commit credentials. `.env` is gitignored; verify before staging anything that looks like a secret.

---

## 10. Pre-merge sanity checklist

Before you call work "done":

- [ ] `php -l <files>` clean (PHP lint passes).
- [ ] `git status` clean — no leftover untracked or modified files.
- [ ] `git log --oneline -5` shows your work, with co-author lines.
- [ ] All commits pushed to `origin/production`.
- [ ] PROJECT-STATE.md has your H2 section appended.
- [ ] If UI: 3+ screenshots in `docs/visual-evidence/YYYY-MM-DD/`.
- [ ] If new feature: ADR committed, state card committed.
- [ ] If new strings: Hindi parity verified.

---

## 11. When in doubt

Halt and ask. The user prefers a delayed-but-clean commit to a fast-but-broken one. Specifically halt if:
- A pre-commit hook fails in a way you don't understand.
- You'd need to `--force` push.
- You're about to touch a file outside your chip's scope.
- A test starts failing that was passing before.
- You see a credential-shaped file (`.env`, `*.pem`, `credentials.*`) in your diff.

---

## 12. ADR ledger

(Update as ADRs land. Reserved entries from §4 graduate here once committed.)

| ADR # | Topic | Date | Author |
|-------|-------|------|--------|
| ADR-001 | Fork strategy + product pivot | 2026-05-20 | Nitin |
| ADR-002 | Customer-level feature flags | 2026-05-20 | Nitin |
| ADR-004 | Real-time mechanism (SSE vs WS) | (Phase E.0.a) | Claude |
| ADR-005 | PWA install flow + native wrapper | (Phase D) | Claude |
| ADR-008 | Customer brand table design | (Phase D) | Claude |
| ADR-009 | Detection consistency + WS contract gate | (Bug #10 fallout) | Claude |
| ADR-011 | Moodle 5.2 upgrade plan | (Phase A.5) | Claude |
| ADR-012 | AI Quiz Generation | RESERVED | spawn 2026-05-24 |
| ADR-013 | Calendar Sync (ICS export) | RESERVED | spawn 2026-05-24 |
| ADR-014 | Real-time Leaderboards | RESERVED | spawn 2026-05-24 |
