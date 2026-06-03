# ADR-022 — `local_airpay_*` → `local_sentientia_*` component rename (Independence Wave 5)

**Status:** PROPOSED — design only, **gated on Nitin** (capability re-registration + a
strategic go/no-go). **No rename is executed by this ADR.**
**Date:** 2026-06-02 · **Decision-makers:** Nitin Rajput · **Implementer:** TBD (post-approval)
**Parent:** ADR-018 (independence roadmap) Wave 5 · **Relates to:** ADR-001 (product pivot),
ADR-019/020/021 (the `local_sentientia_core` seams already named `sentientia_*`).

> This is a **design/decision record only**. Per ADR-018's governing rule, the rename
> touches capability registration + persisted data (role assignments, config, tables, files,
> web-service tokens), so execution is explicitly gated on Nitin's approval + per-plugin
> clone-DB rehearsal. Writing this ADR changes nothing.

## Context

Sentientia LMS is the product; **Airpay is customer-zero**. The new code already ships under
`local_sentientia_*` (core, pwa, live, leaderboard, aiquiz, calendar, recommendations,
translate, m365 — 9 plugins). The **heritage suite is still `local_airpay_*`** — and it is
large:

| Surface | Count (2026-06-02 inventory) |
|---------|------------------------------|
| `local_airpay_*` plugins | **31** |
| …with capabilities (`db/access.php`) | **22** |
| …with DB schema (`db/install.xml`) | **27** |
| `\local_airpay_*` namespaced class refs | **514+** across 200+ files (head-limited; true count higher) |
| Other `airpay_`-keyed refs | component strings, `local/airpay_X:cap` capability names, `{local_airpay_X_*}` table names, `local_airpay_X_*` WS function names, `get_string`/`get_config` component args, mustache `local_airpay_X/template` paths, `db/tasks.php`/`db/events.php` classnames, `$plugin->dependencies` |
| Also airpay-branded | `theme_airpayux`, `block_airpay_*` (cert_health, cron_health, trainer), `paygw_airpay`, `quizaccess_airpay_proctoring` |

Component names are **invisible to learners** (they see the theme + strings), so the rename
is about **product hygiene / sellability to Enterprise N**, not end-user function.

## The core problem: Moodle has no native "rename plugin"

A plugin's component **is** its directory name. Renaming `local/airpay_x` → `local/sentientia_x`
makes Moodle see the old plugin as **gone** and the new one as **fresh**:
- On the next upgrade it runs the old plugin's **uninstall** (drops its tables, deletes its
  config rows, removes its capabilities + role assignments, deletes its files) and then
  **installs the new plugin empty**.
- Result of a naïve `git mv`: **total data loss** for that plugin + every supervisor/role
  assignment on its capabilities + every saved config + every issued file + broken
  web-service tokens (mobile app calls `local_airpay_x_*` functions by name).

So a rename is a **deliberate migration**, never a move. This is the central risk.

## Reference taxonomy — everything a rename must touch

**A. Source (codemod-able, deterministic):**
1. Directory: `local/airpay_x/` → `local/sentientia_x/`.
2. `version.php` `$plugin->component`, `$plugin->dependencies`.
3. Namespaces + `use`: `\local_airpay_x\…` → `\local_sentientia_x\…` (514+ sites).
4. Lang: dir/file `lang/en/local_airpay_x.php` → `…/local_sentientia_x.php`; every
   `get_string('k','local_airpay_x')` component arg.
5. Capability **strings**: `local/airpay_x:cap` in `db/access.php`, `require_capability`,
   `has_capability`, mustache, lang `local_airpay_x:cap` keys.
6. Table **names** in code + `db/install.xml` + `db/upgrade.php`: `{local_airpay_x_*}`.
7. WS: `db/services.php` function names `local_airpay_x_*` + the external classnames.
8. `db/tasks.php` / `db/events.php` classnames; observers; hooks.
9. Mustache template references `local_airpay_x/foo`; `$OUTPUT->render_from_template`.
10. `get_config('local_airpay_x', …)` component args; settings.php setting names.

**B. Persisted data (DB migration, NOT codemod-able):**
1. `config_plugins` rows `WHERE plugin = 'local_airpay_x'`.
2. Tables `mdl_local_airpay_x_*` → `mdl_local_sentientia_x_*` (xmldb `rename_table`).
3. `capabilities.name`, `role_capabilities.capability`, any context-level overrides — the
   capability identifiers + **role assignments** that grant access.
4. `mdl_files.component = 'local_airpay_x'` (issued files, draft areas).
5. `external_functions` / `external_services` (WS) — re-registered on install, but **existing
   tokens / mobile clients reference the OLD function names** (back-compat shim needed).
6. `task_scheduled`, `events_*`, logstore `component`, saved reports/dashboards that filter on
   component, `mdl_block_instances` (for `block_airpay_*`), grade/competency component refs.

## Decision (proposed) — codemod + per-plugin DB migration, leaf-first, core-last

A **two-track** rename, run **one plugin per maintenance window**, each rehearsed on a clone:

### Track 1 — Source codemod (the A-list)
A deterministic, idempotent rewrite script (PHP or Python) that, for one plugin `x`:
- `git mv`s the directory + lang files,
- rewrites component / namespace / capability-string / table-name / WS-name / lang-key /
  template-path / config-component occurrences **for `x` and every cross-plugin reference to
  `x`**,
- leaves a reviewable diff,
- and is gated by a **guard**: after the codemod, `grep -r 'airpay_x' moodle-enhancement/`
  must return only intentional back-compat shims (else fail).

### Track 2 — DB migration (the B-list), shipped IN the renamed plugin's `db/upgrade.php`
The renamed `local_sentientia_x` ships an upgrade step that, **guarded on the old plugin's
presence**, performs the data hand-over so Moodle never runs the old uninstall:
- `rename_table` for each `local_airpay_x_*` table,
- `UPDATE {config_plugins} SET plugin='local_sentientia_x' WHERE plugin='local_airpay_x'`,
- capability migration (define new caps, then re-point `role_capabilities` + any overrides
  from `local/airpay_x:*` to `local/sentientia_x:*`, preserving every role assignment),
- `UPDATE {files} SET component='local_sentientia_x' WHERE component='local_airpay_x'`,
- de-register the old component from `config_plugins` version row last.
- A thin **`local_airpay_x` shim** (or a documented WS-function alias) for one release, so
  mobile/API clients calling old WS names don't break (see Open Q3).

### Phasing (lowest blast-radius first)
1. **Leaf plugins** with no dependents + small/no schema first (e.g. `airpay_ratings`,
   `airpay_recompletion`).
2. Then mid-tier feature plugins.
3. **`local_airpay_core` LAST** — almost everything depends on it (514+ refs concentrate on
   `\local_airpay_core\tenant`, `audit_log`, `cron_health`, `feature_flags`). Its rename is a
   flag day for the whole suite; do it only after the pattern is proven on ≥10 leaf plugins.
4. `theme_airpayux`, `block_airpay_*`, `paygw_airpay`, `quizaccess_airpay_proctoring` are
   separate plugin **types** with their own rename quirks (block instances, payment gateway
   registration, quiz-access subplugin) — each its own follow-up.

Each plugin = its own commit + clone-DB rehearsal + `parity`-style smoke (the WS surface +
capability assignments + row counts match pre/post).

## Consequences

- **Positive:** the suite becomes uniformly `local_sentientia_*` — a clean, sellable product
  with no customer-zero branding leaking into component names; matches the already-`sentientia_*`
  new plugins.
- **Negative / accepted:** very large mechanical surface (31 plugins, 500+ refs); each rename
  is a maintenance-window migration with real data + role-assignment + WS-token risk; the core
  rename is a flag day; mobile/API back-compat needs shims.
- **Risk:** the highest-risk wave — a botched capability migration silently strips access for
  ~110 supervisors / thousands of learners; a missed table rename loses data. Mitigated by
  clone-rehearsal, leaf-first, one-per-window, and the codemod guard.

## Alternatives considered

1. **Brand-only-the-surface (RECOMMENDED to weigh first).** Keep the proven `local_airpay_*`
   components internally; rename **nothing** in code. Brand only what users/buyers see — the
   theme, strings, login, emails, and ship all NEW code as `local_sentientia_*` (already the
   policy). Component names are invisible to learners; an Enterprise-N buyer sees the product
   UI, not `mdl_local_airpay_*` table names. **Near-zero risk, near-zero cost.** The rename
   buys internal tidiness + demo-DB aesthetics, at very high migration risk. Strongly consider
   deferring the full rename indefinitely and doing it opportunistically (a plugin gets renamed
   only when it's already being substantially rewritten).
2. **Big-bang rename (all 31 at once).** Rejected — unrehearsable blast radius; a single
   capability-migration bug locks everyone out; impossible to bisect.
3. **`git mv` + reinstall (accept data loss, re-seed).** Rejected — loses role assignments,
   config, issued files, and historical data; unacceptable for a live customer.
4. **Symlink/alias the component.** Rejected — Moodle resolves components by real directory;
   no supported aliasing.

## Implementation actions (ONLY after approval — none taken now)

- [ ] Nitin decides: **full rename** vs **brand-the-surface / defer** (the strategic fork).
- [ ] If full rename: build + unit-test the codemod on ONE leaf plugin in a throwaway branch.
- [ ] Author the per-plugin DB-migration template (`db/upgrade.php` hand-over + capability
      re-point + files/config/table renames), rehearsed on a clone DB.
- [ ] Define the WS back-compat shim policy (one-release alias) for the mobile/API surface.
- [ ] Sequence the 31 plugins leaf-first; `local_airpay_core` last; theme/blocks/paygw/
      quizaccess as separate follow-ups.

## References
- ADR-018 (independence roadmap, Wave 5), ADR-001 (product pivot), ADR-019/020/021 (the
  `local_sentientia_core` seams). `PROJECT-STATE.md`. The 2026-06-02 marathon inventory
  (this ADR's Context table).

## Open questions for Nitin (resolve before any execution)
1. **Do we rename at all, or brand-the-surface + defer?** (Alternative 1 — the highest-leverage
   decision; rename is high-risk for invisible-to-user benefit.)
2. **Sequencing + window cadence** if we proceed — how many plugins per maintenance window?
3. **WS back-compat:** does the mobile app / any external integration call `local_airpay_*_*`
   web-service functions by name? If so, the one-release alias shim is mandatory before any
   WS-bearing plugin is renamed.
4. **`local_airpay_core` rename** — accept it as a flag day, or keep `airpay_core` as the one
   permanent legacy name (everything already tolerates it via the `class_exists()`-guarded
   `local_sentientia_core` seams)?

## Batch-1 execution (2026-06-03): `local_airpay_ratings` → `local_sentientia_ratings`

First plugin renamed end-to-end (leaf-first), on the local production-import (2,871 users,
411 courses, 3 tenants). Chosen first because it is a leaf with a small but representative
footprint: a table, a capability with **real role-capability grants**, and a write web
service consumed by the theme. Verified **12/12** (plugin recognized, old gone, table renamed,
capability migrated, **capability component re-pointed**, **7 role-capability grants preserved**,
WS migrated + old removed, both classes autoload, WS method executes, zero `airpay_ratings`
residue in the classmap, version bumped). Tooling: `tools/rename/codemod.php` (source) +
`tools/rename/handover.php` (DB) + a bumped `version.php` with a no-op `db/upgrade.php`.

### Lessons (folded into `handover.php` and the procedure)

1. **Re-point `capabilities.component`, not just `capabilities.name`.** If only `.name` is
   re-pointed, the version-bump upgrade's `update_capabilities()` no longer recognises the cap
   as belonging to the new component and tries to **INSERT** it again →
   `dml_write_exception: Duplicate entry '<cap>' for key 'mdl_capa_nam_uix'`. Re-point both.

2. **DELETE old `external_functions` / `external_services` rows; do not sweep their
   component.** The upgrade re-registers the WS from the new `db/services.php`. Re-pointing the
   old rows' component would keep the old classname and break the endpoint. The broad
   `component`-column sweep therefore **excludes** the WS tables.

3. **`core_component.php` (the bootstrap classmap cache) is not cleared by `purge_caches`.** It
   is rebuilt on the `get_all_component_hash` step of a **version-bump upgrade** — hence the
   no-op `db/upgrade.php` paired with a bumped `version.php`. A plain cache purge is not enough.

4. **Order matters:** source codemod → deploy (bumped version + `db/upgrade.php`) → DB
   hand-over → `admin/cli/upgrade.php`. The hand-over makes Moodle see the plugin as
   already-installed (no fresh install, no uninstall); the upgrade then rebuilds the classmap
   and re-registers the WS.

5. **Verification gotcha (harness, not product):** PHP double-quoted strings interpret `\e`,
   `\t`, `\n` as escapes — a probe like `class_exists("...\external\submit_rating")` silently
   becomes `...<ESC>xternal...` and reports a **false** negative. Verify class names with
   `::class` (compiler-resolved) or single-quoted strings, never double-quoted FQNs.
