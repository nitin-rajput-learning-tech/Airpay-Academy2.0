# Sentientia LMS — BizLMS Decoupling Narrative

**Owner:** Nitin Rajput · **Created:** 2026-05-30 (ADR-018 Wave 1, overnight loop)
**Reads with:** `docs/adr/ADR-018-...md` (the decision record) and `docs/DEPRECATION-SCHEDULE.md` (the asset table).

This is the **prose companion** to those two: it explains, for a new engineer or a
prospective Sentientia customer's technical team, *how* the BizLMS coupling actually
works today and *why* the decouple is staged the way it is. The ADR holds the
go/no-go decisions; the schedule holds the per-asset removal waves; this holds the
story that makes both legible.

---

## 1. Lineage — where the coupling came from

```
Moodle (upstream LMS engine)
  └─► eAbyas "BizLMS" distribution (multi-tenant "company/cost-center" fork of Moodle)
        └─► Airpay Academy fork (this repo) — airpayux theme (forked from theme "epsilon")
              └─► Sentientia LMS (the white-label product we are extracting)
```

Sentientia is **not** a greenfield LMS. It is the Airpay Academy deployment —
itself a fork of the eAbyas BizLMS Moodle distribution — being progressively
de-coupled from its BizLMS/eAbyas/epsilon origins so it can stand as a white-label
product. Customer-zero (Airpay) must keep running on the live engine the entire
time, which is why every step is additive and reversible rather than a rewrite.

---

## 2. The coupling surfaces (what binds us to BizLMS today)

| Surface | How it couples | Hardness |
|---------|----------------|----------|
| **Tenancy** | Tenant identity is read from `$USER->open_path` (a BizLMS cost-center path like `/1/2/3`). 24+ files branch on it; ~294 touch it. | **HARD** |
| **Org hierarchy** | `local_costcenter` (the BizLMS company/department tree) + `open_supervisorid` (manager link) drive reporting + audience targeting. | **SOFT** (already dual-targeted via an `org_manager` fallback) |
| **Tenant allow-list** | `VALID_TENANTS = [1, 77, 177]` is hardcoded (Airpay / Public / ZEEA). | **SOFT** (small, but hardcoded) |
| **Admin UI styling** | The theme styles BizLMS-injected DOM classes (`.costcenter_data`, `.content_right`) + carries 4 `_bizlms-*.scss` partials. | **SOFT** (cosmetic) |
| **Theme lineage** | `airpayux` was forked from theme `epsilon`; the `epsilonnavbar` class + behat test names retain the epsilon name. | **SOFT** (cosmetic / structural) |
| **Product namespace** | 30+ plugins are `local_airpay_*` (437 refs, 150+ capabilities). | **SOFT** but **wide** |
| **The engine itself** | Enrolment, completion, gradebook, quiz, SCORM are Moodle core — the source of truth for 408 live courses. | **RE-PLATFORM ONLY** |

The key distinction: most coupling is **soft** (cosmetic, or already abstracted
behind a fallback) and can be retired additively. Only **tenancy** (`open_path`) is
hard-and-pervasive, and only the **engine** is genuinely re-platform-only.

---

## 3. The decouple strategy — seams, not rewrites

The method is the *seam*: wherever BizLMS leaks through, introduce a Sentientia-owned
abstraction that **defaults to the current BizLMS behaviour** behind a flag, so
nothing changes for customer-zero until the flag flips.

- **Tenancy:** wrap `open_path` reads behind a `tenant_identity` service (Wave 2). The
  service's default implementation simply parses `open_path` (today's behaviour); a
  future implementation reads a Sentientia tenant table. Callers stop touching
  `open_path` directly.
- **Org model:** `local_costcenter` already has an `org_manager` fallback seam — the
  migration (Wave 3) moves data into `local_sentientia_org` and flips the seam.
- **Styling:** the `.ap-admin-*` hook layer (Wave 2 target) replaces direct
  `.costcenter_data` / `.content_right` targeting; the `_bizlms-*.scss` partials are
  already marked DEPRECATED in-code and retire once the hook layer lands.
- **Naming:** `airpay_* → sentientia_*` happens via alias shims + capability
  re-registration (Wave 5) so no install breaks mid-rename.
- **Engine:** left in place. Migrated tenant-by-tenant, **ZEEA-first** (smallest,
  lowest-risk tenant), over 12–18 months — *if* the product strategy ever justifies
  leaving Moodle at all. Until then, Sentientia *is* a white-label, BizLMS-decoupled
  Moodle distribution, which is the correct fundable interim product.

---

## 4. Staged path (prose summary of ADR-018 Waves)

- **Wave 1 — DONE (this overnight run).** White-label + accessibility: the `Epsilon`
  theme name, the OTP login button string, and the `privacy:metadata` GDPR string are
  all de-branded across 5 locales; dark-mode WCAG-AA gaps (catalogue chips, Bootstrap
  `text-*`) closed; the BizLMS coupling is now *documented* (ADR-018, this narrative,
  the deprecation schedule) and *marked in-code* (`_bizlms-*.scss` headers). Zero
  production risk; all additive.
- **Wave 2 — needs Nitin.** Create `local_sentientia_core` + the `tenant_identity`
  service (default-ON legacy flag) + the `.ap-admin-*` hook layer. Greenfield infra +
  a DB table → its own ADR + clone-DB rehearsal.
- **Wave 3 — needs Nitin.** Migrate `local_costcenter` → `local_sentientia_org` +
  supervisor links. Guarded CLI, ZEEA-first, reversible.
- **Wave 4 — needs Nitin.** Replace the `VALID_TENANTS` hardcode with a DB-backed
  tenant registry.
- **Wave 5 — needs Nitin.** Rename `airpay_* → sentientia_*` via alias shims +
  capability re-registration (150+ caps).
- **Wave 6 — SCOPE ONLY.** Engine re-platform spike. Board-level spend; not started.

Waves 2–6 are human-gated because each touches the live DB, capabilities, or the
engine — exactly the surfaces customer-zero cannot lose mid-flight.

---

## 5. The one rule that governs all of it

> **Customer-zero (Airpay) never sees a regression.** Default behaviour matches what
> airpay.academy users see today until a feature flag deliberately flips. Every
> decouple step ships additive + reversible, behind a flag, rehearsed on a clone DB
> before it touches production.

That single constraint is why "move off BizLMS/Moodle" is a multi-quarter staged
program and not a slogan — and why Wave 1 (safe white-label + a11y + documentation)
could ship tonight while Waves 2–6 wait for an explicit go.
