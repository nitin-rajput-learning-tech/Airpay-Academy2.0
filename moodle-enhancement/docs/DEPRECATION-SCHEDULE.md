# Sentientia LMS — Deprecation Schedule

**Owner:** Nitin Rajput · **Created:** 2026-05-30 (ADR-018 Wave 1, overnight loop) · **Pairs with:** `docs/adr/ADR-018-sentientia-independence-and-stabilization-roadmap.md`

This document tracks every **BizLMS- / eAbyas- / epsilon-coupled asset** that the
Sentientia independence program plans to retire or replace, and maps each to its
ADR-018 removal **wave**. It is the single place to answer "is it safe to delete X
yet, and what replaces it?".

It is descriptive, not a green light: nothing here is removed until its wave is
approved by Nitin (Waves 2–6 are `needs_human` gates). Wave 1 entries are the only
ones safe to action autonomously, and those are cosmetic/additive only.

---

## Legend

| Status | Meaning |
|--------|---------|
| 🟢 **cosmetic-safe** | Cosmetic/string/comment only. Can be deprecated/replaced additively without a DB or capability change. |
| 🟠 **needs-human** | Requires a DB migration, capability re-registration, component rename, or engine work. Gated on Nitin (ADR-018 Waves 2–6). |
| ⚪ **structural** | Build/test/provenance artefact. Leave until the relevant wave; removing early breaks tooling. |

---

## Deprecation targets

| # | Asset (verified path) | Coupling | Replacement | Wave | Status |
|---|------------------------|----------|-------------|------|--------|
| 1 | `theme/airpayux/scss/moodle/partials/_bizlms-admin.scss` | Styles legacy BizLMS admin DOM | `.ap-admin-*` Sentientia hook layer (to be built) | 2 | 🟢 cosmetic-safe (deprecate-mark now) |
| 2 | `theme/airpayux/scss/moodle/partials/_bizlms-dark.scss` | BizLMS admin dark-mode overrides | folds into `dark_mode.scss` token cascade | 2 | 🟢 cosmetic-safe |
| 3 | `theme/airpayux/scss/moodle/partials/_bizlms-modern.scss` | BizLMS "modern" skin overrides | `.ap-admin-*` hook layer | 2 | 🟢 cosmetic-safe |
| 4 | `theme/airpayux/scss/moodle/partials/_bizlms-overrides.scss` | Catch-all BizLMS DOM overrides | `.ap-admin-*` hook layer | 2 | 🟢 cosmetic-safe |
| 5 | `.costcenter_data` / `.content_right` selectors (in `dark_mode.scss`, `custom_media.scss`) | Theme styles against BizLMS-injected DOM class names | Sentientia-owned wrapper classes emitted by `core_renderer` | 2 | 🟠 needs-human (renderer + markup) |
| 6 | `theme/airpayux/classes/epsilonnavbar.php` (class `epsilonnavbar`) | epsilon-fork class name | `sentientia_navbar` (alias shim, then rename) | 5 | 🟠 needs-human (class rename) |
| 7 | `$USER->open_path` tenant identifier (24+ files) | Hard BizLMS tenant coupling | `local_sentientia_core\tenant_identity` service (default-ON legacy flag) | 2 | 🟠 needs-human (greenfield infra) |
| 8 | `local_costcenter` org hierarchy + `open_supervisorid` | BizLMS org/manager model | `local_sentientia_org` + migration (ZEEA-first) | 3 | 🟠 needs-human (DB migration) |
| 9 | `VALID_TENANTS = [1,77,177]` hardcode (`local_airpay_core\tenant`) | Hardcoded tenant allow-list | DB-backed `tenant_registry` | 4 | 🟠 needs-human (DB + capability) |
| 10 | `airpay_*` component namespace (437 refs, 150+ caps) | Legacy product name | `sentientia_*` via alias shims + capability re-registration | 5 | 🟠 needs-human (rename) |
| 11 | `lang/en/deprecated.txt` `totop,theme_epsilon` mapping | Moodle deprecated-string mapping | leave — structural (removing breaks the deprecation map) | — | ⚪ structural |
| 12 | `tests/behat/*` + `behat_theme_epsilon_*.php` (epsilon names) | Behat infra inherited from the epsilon fork | rename alongside the component rename | 5 | ⚪ structural |
| 13 | eAbyas / "forked from epsilon" copyright docblocks (~10 files) | Provenance metadata (non-user-visible) | `© 2026 Airpay Payment Services` | 1–2 | 🟢 cosmetic-safe (low priority) |

---

## Already retired (ADR-018 Wave 1 — done)

These leaks were closed by the Wave-1 overnight loop (see ADR-018 execution log):

- **`Epsilon` theme name** in `configtitle` / `pluginname` (all 5 lang packs) → `Sentientia Academy UX` / `Airpay Academy UX (Sentientia)`. *(commit `dd44503aa`)*
- **OTP login button** core string `{{#str}}login, moodle{{/str}}` → theme-owned `login_submit` (5 locales). *(commit `9a11eecad`)*
- **`privacy:metadata`** "Epsilon theme…" → "Airpay Academy UX (Sentientia)…" (hi/kn/mr/sw). *(commit `b324edaf5`)*

---

## How to use this schedule

1. Before deleting/renaming any BizLMS-coupled asset, find its row here.
2. If 🟢 **cosmetic-safe** and its wave ≤ current approved wave → action it additively.
3. If 🟠 **needs-human** → it requires its own ADR + clone-DB rehearsal + Nitin's go/no-go. Do **not** action it in the autonomous loop.
4. If ⚪ **structural** → leave until the named wave; early removal breaks build/test/provenance.

When an asset is retired, move its row to **Already retired** with the commit SHA.
