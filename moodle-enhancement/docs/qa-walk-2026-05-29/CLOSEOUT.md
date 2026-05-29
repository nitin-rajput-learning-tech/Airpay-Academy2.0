# QA Persona Walkthrough — Closeout (2026-05-29)

Exhaustive, persona-driven UX/QA walk of local Airpay Academy (XAMPP, latest workspace
code synced; Moodle 5.1.3+). Real browser (chrome-devtools MCP) + same-origin `fetch()`
probes; deep functional interaction per persona. Driven by orchestrator + sequential
**Sonnet sub-agents** (one per persona, shared authenticated browser).

## Coverage — 8 personas
Guest · Site Admin · Org/L&D Admin · Compliance Officer · Trainer · Employee/Learner ·
Public Learner · Manager. (Calibration = Guest + Site Admin; remaining 6 deeper per owner sign-off.)
Per-persona reports: `guest.md` · `siteadmin.md` · `orgadmin.md` · `compliance.md` ·
`trainer.md` · `employee.md` · `public.md`; details + screenshots in this folder; full
table in `BUG-LOG.md`.

## Fixes shipped + verified (8)
| ID | Sev | Fix | By |
|----|-----|-----|----|
| G-1 | P1 | Navbar "Register" + OTP form → dead `/local/users/signup.php`; repointed to `/local/airpay_users/signup.php`. → 200. | walk |
| SA-02 | P2 | Theme settings 404 (`themesettingepsilon` fork leftover) → renamed to `themesettingairpayux`. → 200. | walk |
| OA-GRAN / C-001 | P1 | L&D-admin sidebar rendered 8 admin links by shell detection without the caps the pages enforce → category-scoped admins (compliance officers) saw 5/8 dead links. Gated each link by its page's `has_capability(...:view, system)`. qa_compliance: 6 links, 0 dead. | walk |
| T-03 | P2 | `airpay_manager` threw a 500 for a manager-shell user with 0 reports; now renders the graceful empty state. | walk |
| C-002 | P1 | Compliance Export gated on the unregistered `local/courses:manage` → dedicated `:export` cap + `permission::can_export()` (managers view but can't bulk-export PII). | owner |
| C-005 | P2 | Compliance CSV export branch consumed the wrong matrix shape → 404; rebuilt to match xlsx shape. → 200 + text/csv. | owner |
| T-01 | P1 | `sentientia_live:create/:run` excluded the `teacher` archetype (BizLMS trainer role) → trainers couldn't create sessions. Added archetype + `upgrade.php` back-fill onto existing roles. | owner |
| OA-08 / T-02 | P1 | Real Phase-E.1 trainer dashboard (`sentientia_live/trainer/index.php`) was unlinked → added a capability-gated "Live Sessions" sidebar link. | owner |

## Open — recommendations (owner decision; not inline-fixed by design)
- **SA-04 (P2):** `/course/management.php` + `/course/index.php` unconditionally redirect *everyone* (incl. siteadmins) to the catalog → native course/category management unreachable. Recommend gating the redirect by `is_siteadmin()` / `moodle/category:manage` / `moodle/course:create`.
- **E-01 (P1):** Airpay employees can't self-enrol in free courses (no self-enrol method; the "Enroll — Free" CTA dead-ends). Likely auto-enrol on prod. Recommend: enable self-enrol on free courses OR fix the CTA/empty-state. Verify on staging.
- **T-04 / T-05 (P2/P3):** `block_airpay_trainer` dashboard class-not-found + `/my/dashboard.php` trainer redirect never fires. Block-wiring cleanup.
- **P-01 (P2):** sidebar "My Cart" → wrong/empty cart URL (`airpay_cart/index.php` vs working `airpay_catalog/cart.php`).
- **E-02 (P2):** learner can't view own skills page. **SA-05 / C-004 / E-03 / P-02 / M-01 (P3):** cosmetic/minor.

## False positives caught by verification (would have been wrong to "fix")
- **G-2** course-detail 404 → BizLMS `local_search` not deployed locally (correct for prod; rewriting would break prod).
- **OA-01..07** admin `nopermissions` → provisioning artifact (`administrator`@category vs caps@system); 0 real bugs.
- **C-003** "ZEEA tenant leak" → "ATZ"/`airpay.tz` users are Airpay **Tanzania** (`/1` tree), not ZEEA `/177`. Matrix filter is correct.
- **SA-01** bad probe URL (not a Moodle 5.x route).

## Tenant isolation
Verified: qa_public (/77) sees only Public-tenant content and cannot reach Airpay (/1)
internal courses (core enrolment guard + catalog scoping hold). No cross-tenant leak.

## Environment note
The BizLMS plugin suite (`local_search`, `local_users`, `local_courses`, `local_request`,
`local_programs`, `local_classroom` + `local_custom_category`) is **not deployed to local
XAMPP** — only the fork's `local_airpay_*` / `local_sentientia_*` plugins. BizLMS-page flows
(course detail, legacy enrol) must be verified on staging/production.

## Method notes (for future walks)
- `fetch()` breadth-probes + disk-saved screenshots are token-cheap; full a11y snapshots are heavy — use sparingly.
- A `vis:false`/`d-none`/`nopermission` is NOT automatically a bug — verify against fieldtype, cap-context, and provisioning first. Several would-be "P1s" were artifacts.
- Persona provisioning must grant caps at the **context the pages check** (system), or every walk reports false `nopermissions`.
- qa_* test accounts (password `Qa@Airpay#26`) are local-only; created to keep real PII out of screenshots.
