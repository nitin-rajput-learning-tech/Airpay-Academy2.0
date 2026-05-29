# C10 — Certificate stack investigation

**Date:** 2026-05-28
**Author:** Nitin Rajput (with Claude)
**Audit ref:** `docs/audits/PLATFORM-STABILIZATION-AUDIT-2026-05-28.md` Bucket C / C10 / F-038
**Probe tool:** `tools/probe_certificate_state.php`

---

## Why this exists

The Stabilization Audit's Bucket C row C10 lists "Certificate stack —
finish builder + verify endpoint" with the note "Already partial". This
doc answers what "partial" means by walking the actual code and DB.

The runtime turns out to be **more complete than the audit suggested**.
What remains is polish + white-label readiness, not core feature work.

---

## What's actually shipped (works on production)

The certificate stack is a **STABLE Moodle plugin (`tool_certificate`
v4.5.7)** plus Sentientia overlays. Runtime evidence from the local DB
(today's probe via `tools/probe_certificate_state.php`):

| Table | Row count on local | Interpretation |
|-------|---------------------|----------------|
| `tool_certificate_templates` | 9 | Templates: Course Completion, Learning Path Completion, Exam Completion, AISECT variant |
| `tool_certificate_issues` | **11,415** | Real production issuances |
| `tool_certificate_pages` | 9 | One page per template |
| `tool_certificate_elements` | 31 | ~3.4 design elements per page (text, image, signature) |

**11,415 issued certificates on local** means the issuer pipeline runs
end-to-end against real production data. The plugin is not partial in
any runtime sense.

### Surfaces that exist + work

| Surface | Path | Status |
|---------|------|--------|
| Public verify endpoint | `/admin/tool/certificate/view.php?code=<code>` | ✅ Works. Uses `tool/certificate:verify` capability; default allows guest verification when the cap is granted to the Guest role. |
| Admin's "my certificates" list | `/admin/tool/certificate/my.php` | ✅ Works. Default Moodle layout (table). |
| Learner's Sentientia-styled gallery | `/local/airpay_pages/certificates.php` | ✅ Works. Card grid, LinkedIn-share button, download. Replaces the default `my.php` UX for end users. |
| Template manager | `/admin/tool/certificate/manage_templates.php` | ✅ Works. Site-admin only. |
| Template editor | `/admin/tool/certificate/template.php?id=<id>` | ✅ Works. Drag-and-drop element placement on a PDF page. |
| Element editor (image, text, signature, etc.) | `/admin/tool/certificate/element/<type>/edit.php` | ✅ Works. 10+ element types. |
| PDF generation | `tool_certificate\template::generate_pdf()` | ✅ Works. tcpdf-backed. P0 fixed 2026-05-23 (render_image_html TypeError on non-image files — see `docs/core-mods/2026-05-23-certificate-image-imageinfo-guard.md`). |
| Course-completion auto-issue | `airpay_emails\observer::course_completed()` + `certificate_helper::materialise_pdf()` | ✅ Works. Sprint B (2026-05-13) hooked the completion event to attach the issued PDF to the congrats email. |
| LinkedIn share | `airpay_pages/certificates.php` line 62 | ✅ Works. Build LinkedIn "Add to Profile" URL with certId + certUrl pointing at the verify endpoint. |

### Tested touch points

- The 2026-05-23 core-mod patch added a guard for non-image-mime files
  in `tool_certificate/element/image/classes/element.php`. That patch
  shipped to production and is documented in
  `docs/core-mods/2026-05-23-certificate-image-imageinfo-guard.md`.
- `airpay_emails/tests/certificate_helper_test.php` provides PHPUnit
  coverage for the auto-attach flow.

---

## What "partial" actually means (the open work)

Walking the existing code surfaces 6 discrete gaps. None are
runtime-blocking; all are about white-label polish + Sentientia
product readiness.

### Gap 1 — Per-customer template branding (white-label)

**Why it matters for the product:** the 9 existing templates are all
Airpay-branded (Airpay logo on the signature block, Airpay watermark,
Airpay colour palette). For a hypothetical Sentientia LMS customer that
isn't Airpay, the template image elements would all show Airpay branding
unless the template is duplicated and rebuilt manually.

**What's needed:**
- Hook `tool_certificate\template::generate_pdf()` to resolve image
  elements via `local_airpay_core::get_customer_branding()` (the same
  resolver ADR-008 introduced for the airpayux theme).
- Per-customer template-id overrides — e.g. customer 1 uses templateid
  1 for "Course Completion", customer 2 uses templateid 17 (a separate
  template with its own branding).
- ADR for the resolution path.

**Effort estimate:** M. ~1-2 days. Touches both `tool_certificate`
(core mod) and `local_airpay_emails::certificate_helper`. Should ship
behind a per-customer feature flag.

### Gap 2 — Bulk re-issuance UI

**Why it matters:** when a template image is updated (new Airpay logo,
typo fix in legal text), the 11,415 existing issuances on production
STILL render with the old image because the PDF was materialised at
issue time. There's no admin UI to "re-render all certificates for
template id=X with the new design".

**What's needed:**
- An admin page that lists templates, shows "N issues using v1 design,
  M issues using v2 design", and a button to re-materialise.
- A scheduled task that walks `tool_certificate_issues` for a template
  and regenerates the PDF file area entries.
- A change-log table that tracks which template revision a given issue
  was rendered with.

**Effort estimate:** L. ~3-5 days. Schema change + admin UI + task +
template revision tracking.

### Gap 3 — Tenant-scoped template filtering

**Why it matters:** L&D Admins in the Public tenant currently see the
Airpay-tenant templates in their template picker. Not a security issue
(can_manage() gates editing) but it's a UX leak — Public tenant admins
shouldn't see template names like "AISECT Course Completion" that don't
apply to them.

**What's needed:**
- Add `tenantid` column to `tool_certificate_templates` (nullable —
  null = all tenants, set = tenant-specific).
- Filter `manage_templates.php` list query by current user's tenant.
- Filter the per-issuance template dropdowns the same way.

**Effort estimate:** S. ~1 day. Schema migration + a few WHERE clauses.

### Gap 4 — Hindi language pack for `tool_certificate`

**Why it matters:** `tool_certificate` ships English only.
Hindi-language users (~30% of Airpay's user base) see English admin
labels even though we have full Hindi packs for every airpay_* /
sentientia_* plugin. Inconsistent.

**What's needed:**
- Translate `admin/tool/certificate/lang/en/tool_certificate.php`
  (currently ~120 strings) to `lang/hi/tool_certificate.php`.
- Apply the same convention used by P1 #43 Hindi gap analysis —
  100% parity, native-script proper names.
- This is a core-mod (vendored 3rd-party plugin), so it goes in
  `docs/core-mods/` with a track-record of changes.

**Effort estimate:** S. ~half day. Pure translation.

### Gap 5 — Mobile-optimized PDF layout

**Why it matters:** the 9 templates are A4 landscape — designed for
print. On a mobile phone the PDF renders zoomed-out and unreadable
without pinch-and-zoom. Per the Mobile App roadmap (Workstream D), this
will matter when learners view certificates on phones.

**What's needed:**
- A mobile-aware template variant (portrait orientation, larger fonts,
  simplified layout).
- Optional fallback at issue-time: render both A4 + mobile, learner
  app picks the one matching device.

**Effort estimate:** M. ~1-2 days design + ~half day code. Could
defer to Workstream D mobile work.

### Gap 6 — JSON verify API

**Why it matters:** `view.php?code=<code>` currently redirects to a
Moodle-served PDF. Tools like LinkedIn Skills, HR background-check
services, and HR-tech integrators would prefer a JSON endpoint that
returns `{"valid": true, "issuee": "...", "course": "...", "issued_at": "..."}`
instead of a PDF download.

**What's needed:**
- New `verify_api.php?code=<code>` endpoint (no auth, rate-limited).
- Returns minimal JSON: valid flag, issuee name (consent-gated),
  course name, issue date. Never includes the PDF content.
- Per-customer rate limit + abuse logging.

**Effort estimate:** S. ~half day. Pure JSON wrapper around the
existing `template::get_issue_from_code()` resolver.

---

## Closeout recommendation

The audit's "partial" assessment was technically correct but the
runtime is more complete than it implied. **All 6 gaps are polish
items, not core feature work.** Order of impact for the Sentientia
LMS product story:

| Priority | Gap | Why |
|----------|-----|-----|
| **P0 if non-Airpay customer signs** | Gap 1 (per-customer branding) | White-label is a sales blocker for anyone who isn't Airpay |
| **P1 anytime** | Gap 4 (Hindi pack) | Closes the last English-only surface on the platform |
| **P1 anytime** | Gap 3 (tenant-scoped templates) | Tiny effort, cleans up the L&D Admin UX |
| **P2 with mobile push** | Gap 5 (mobile-PDF) | Bundles with Workstream D |
| **P2 anytime** | Gap 2 (bulk re-issue) | Operational nice-to-have; affects all 11,415 existing issues every time a template changes |
| **P3 with integration ask** | Gap 6 (JSON verify) | Customer-driven; no current ask |

**No code shipped today** beyond the investigation tool. The audit row
C10 is now triaged with concrete next-step ranking rather than a vague
"already partial" label.

### Update 2026-05-29 — Gap 3 + Gap 4 shipped (C10 P1)

- **Gap 3 (tenant-scoped template filter): SHIPPED.** Implemented as a
  Sentientia-native, upgrade-safe overlay — NOT a vendored-schema
  change. New `local/airpay_pages/certificate_templates.php` reads the
  `tool_certificate_*` tables READ-ONLY and filters by a JSON map
  (`local_airpay_pages | cert_template_tenant_map`). Gated behind
  `sentientia.certificate.tenant_scope.enabled` (default OFF = today's
  behaviour: all admins see all templates). Root cause confirmed
  during build: all 9 templates sit at SYSTEM context, so Moodle treats
  them as global — the map is what introduces per-tenant scoping
  without touching the vendored plugin.
- **Gap 4 (Hindi pack): STAGED, review-gated.** Full 173-string Hindi
  DRAFT at `docs/translations/tool_certificate-hi-DRAFT.php`, NOT in
  the active lang dir (Moodle has no per-plugin lang feature-flag, so
  the staging location IS the enforceable gate). Activation procedure +
  core-mod record: `docs/translations/README.md` +
  `docs/core-mods/2026-05-29-tool_certificate-hi-pack.md`. Awaits L&D
  Hindi sign-off per CLAUDE.md §12.

Remaining C10 gaps unshipped: Gap 1 (per-customer branding, P0-if-
customer-signs), Gap 2 (bulk re-issue), Gap 5 (mobile PDF), Gap 6
(JSON verify) — all still ranked in the table above.

---

## Cross-reference

- The investigation tool: `tools/probe_certificate_state.php`
  (read-only DB probe, safe on production)
- Sprint B observer + auto-attach flow: `local/airpay_emails/classes/observer.php`
  → `local/airpay_emails/classes/certificate_helper.php`
- Sentientia-styled gallery: `local/airpay_pages/certificates.php`
- Core mod history: `docs/core-mods/2026-05-23-certificate-image-imageinfo-guard.md`
- Customer branding resolver (Gap 1 dependency): ADR-008
  `docs/adr/ADR-008-customer-brand-table.md`
- Audit row this closes: F-038 / Bucket C / C10
