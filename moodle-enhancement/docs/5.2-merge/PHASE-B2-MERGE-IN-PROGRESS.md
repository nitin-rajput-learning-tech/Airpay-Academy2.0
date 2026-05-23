# Phase B.2 — Wholesale 5.2 merge — IN PROGRESS (2026-05-23)

ADR-011 Phase B.2 — first end-to-end attempt to upgrade a clone of our
5.1.3+ DB to Moodle 5.2 using PHP 8.4 via Docker.

---

## Where we are right now

```
[OK]  Phase B.1 — PHP 8.4 via Docker (moodle-5.2-cli image)
[OK]  Workspace prep:
       - 5.2 source robocopied to C:\xampp\htdocs\moodle5.2\ (61,540 files)
       - Airpay customizations overlaid (theme + 30 local plugins +
         4 blocks + admin/tool/certificate + 2 patches + root file)
       - Total tree: 64,677 files / 448 MB, 0 collisions
[OK]  DB clone:
       - moodle5_2 DB created
       - Schema imported (1,219 tables, 26.6s restore)
       - Critical seeds (config, users, courses, roles, contexts,
         enrol, mnet) — total ~50K rows
       - Stored version pre-upgrade: 2025100603.13 (5.1.3+ build 20260415)
[OK]  Dataroot:
       - C:\xampp\moodledata5_2 + 5 subdirs created
       - C:\xampp\moodledata5_2_phpu for PHPUnit later
[OK]  config.php for moodle5.2:
       - Docker-friendly via MOODLE_DBHOST + MOODLE_DATAROOT env vars
       - Lives at C:\xampp\htdocs\moodle5.2\config.php (above public/)

[RUNNING] upgrade.php via PHP 8.4 Docker against moodle5_2 DB
[RUNNING] Currently processing qtype_* (31 plugins done, ~200 to go)
```

---

## First run setbacks (resolved)

**Run 1 — Fatal: $CFG->dataroot is not configured properly**

Cause: config.php had Windows path `C:\xampp\moodledata5_2`, but the
container is Linux. Fix: env-var detection that swaps to Linux path
`/var/moodledata` when running inside the moodle-5.2-cli image.

**Run 2 — UTF-16 mangling on schema dump**

Cause: PowerShell `>` redirect transcodes mysqldump stdout to UTF-16,
mysql.exe rejects with `'\0' appeared in the statement`. Fix: use
`cmd.exe /c "... > file"` for binary-safe redirect.

---

## Currently observed (non-blocking) notices

```
PHP Notice: XMLDB has detected one CHAR NOT NULL column (last_login_date)
            with '' as DEFAULT value.
PHP Notice: XMLDB has detected one CHAR NOT NULL column (filename) ...
PHP Notice: XMLDB has detected one CHAR NOT NULL column (firstname) ...
PHP Notice: XMLDB has detected one CHAR NOT NULL column (lastname) ...
```

These are XMLDB-style warnings about a handful of our airpay plugin
columns where the default is an empty string instead of NULL. Moodle
prefers nullable. Non-blocking — Moodle still processes the upgrade
but logs the notice. Fold into a follow-up cleanup commit.

Affected plugins (preliminary, full list after upgrade completes):
- `last_login_date` — airpay_courses or airpay_users
- `filename` — likely airpay_emails or airpay_evaluation
- `firstname` / `lastname` — likely airpay_users

---

## Plugin upgrade rhythm

Each plugin's upgrade.php takes ~7-9 seconds:
- Plugin's own `xmldb_<plugin>_upgrade` function: ~0.3s
- Core's `update_capabilities`: ~4-5s
- Total per plugin: ~7-9s

With ~200 plugins to upgrade (core 5.1 → 5.2 deltas + our 30 airpay
plugins + vendor blocks), expect total runtime of **25-30 minutes**.

---

## Files produced this leg (not yet committed)

| Path | Purpose |
|------|---------|
| `C:\xampp\htdocs\moodle5.2\` | Fresh 5.2 tree + our overlay |
| `C:\xampp\htdocs\moodle5.2\config.php` | Per-instance config |
| `C:\xampp\moodledata5_2\` | Separate dataroot |
| `moodle5_2` DB | Clone of moodle DB + minimum seed rows |
| `D:\Claude Local\moodle-5.2-diffs\upgrade-run-2.log` | 86KB+ and growing — full transcript |

In-repo artifacts already committed:
- `moodle-enhancement/tools/Dockerfile.moodle-5.2`
- `moodle-enhancement/tools/php-docker.sh`
- `moodle-enhancement/tools/overlay-airpay-customs.ps1`

---

## What we'll know once upgrade.php completes

**Success case:**
- Moodle 5.2 + our 30 local_airpay_* + 4 blocks bootstrap successfully
- Schema migrates cleanly from 5.1.3+ to 5.2
- All upgrade savepoints succeed
- Phase B.2 effectively done — proves the wholesale merge is feasible

**Partial success case:**
- Most plugins upgrade OK; specific airpay plugin trips during its
  own xmldb upgrade
- We catalogue which plugin + which API needs updating
- That becomes the input to Phase B.3-B.9 (component-by-component
  conflict resolution from the A.4b conflict map)

**Fatal failure case:**
- Hard error in Moodle core (5.1 → 5.2 schema) or in one of our
  plugins on bootstrap
- Catalogue the stack trace
- Roll back to the conflict map; identify which file needs prior fix

In all cases the moodle5.2/ tree + moodle5_2 DB stay in place for
iterative debug. We DON'T touch the live moodle5/ + moodle DB.

---

## Next steps after this run

1. **If success**: Run Goal A.y functional matrix against
   http://localhost:8080/moodle5.2 to verify visual + functional
   parity. This is the Phase B.12 work breakdown item.
2. **If partial**: Fix the failing plugin's xmldb_upgrade, re-run.
   Iterate until clean.
3. **If fatal**: Inspect the stack, identify the file, apply the
   Phase A.4b conflict-map resolution for that file.

The point is — we have a reproducible environment for the iteration
loop now. Each iteration takes 25-30 min if the whole upgrade runs,
seconds if a specific plugin pre-fails.

---

## ADR-011 §"Phase B work breakdown" — actual vs estimate

| Session | Estimate | Actual so far |
|---------|----------|---------------|
| B.1 — PHP 8.4 via Docker | 2h | 1h (Docker pivot was simpler than predicted) |
| B.2 — First merge + triage | 2h | In progress (~1h elapsed on overlay + DB clone + first run) |

If B.2 completes cleanly, the merge effort estimate (80h total) starts
to look conservative. The hard surprise was the WDAC block + the
PowerShell encoding quirks, both now solved.

---

## Refs

- ADR-011 — Phase B.2
- PHASE-B1-PHP84-DOCKER-VERIFIED.md
- PHASE-A4B-CONFLICT-MAP.md
- PHP-8.4-INSTALL-WDAC-PIVOT.md
