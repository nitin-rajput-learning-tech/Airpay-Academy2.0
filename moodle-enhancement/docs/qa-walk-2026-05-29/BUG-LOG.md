# QA Walkthrough — Bug Log (2026-05-29)

Persona-driven exhaustive UX/QA walk of local Airpay Academy (XAMPP, latest code synced).
Severity: **P0** blocker · **P1** broken/wrong · **P2** minor/UX · **P3** cosmetic.
Fix status: `OPEN` / `FIXED` / `WONTFIX` / `ENV-GAP` / `OPEN-QUESTION`.

| ID | Sev | Persona | Surface | Issue | Status |
|----|-----|---------|---------|-------|--------|
| G-1 | P1 | Guest | `frontpage.php:372` + `otploginform.mustache:134` | Navbar "Register" CTA + OTP "register with us" pointed to legacy `/local/users/signup.php` → **404**. Hero/footer/onboarding already use `/local/airpay_users/signup.php`. Repointed both. Verified → 200. | **FIXED** ✅ |
| G-2 | — | Guest | course "Details"/"Enroll" → `/local/search/coursedetails.php` (6 files) | 404 **locally only**. `local_search` is a BizLMS production plugin not mirrored to workspace/XAMPP (confirmed via `config_plugins` + missing `local_custom_category` + `table_exists()` guards). Correct for prod; rewriting would break prod. | ENV-GAP (no change) |
| SA-02 | P2 | Site Admin | `theme/airpayux/settings.php:27` | Theme settings page registered under leftover parent name `themesettingepsilon`; Moodle's selector auto-links `themesettingairpayux` → `sectionerror`. Renamed to convention. Verified → 200. | **FIXED** ✅ |
| SA-03 | — | Site Admin | `/local/sentientia_recommendations/index.php` | 404 — plugin is backend+CLI scaffold with no `index.php`. **Nothing links to it** (grep clean), so no user impact. | NOT-A-BUG |
| SA-04 | P2? | Site Admin | `/course/management.php` | Redirects (200→`/local/airpay_catalog/index.php`) **for site admins too** — native course/category management, bulk ops, restore-to-category unreachable at canonical URL. Intentional storefront override or admin gap? | **OPEN-QUESTION** (your call) |
| SA-05 | P3 | Site Admin | theme settings `configtitle` lang string | Theme settings page title still reads "Epsilon" (un-rebranded fork string). Cosmetic branding leak (matters for white-label product). | OPEN (cosmetic) |

> SA-01 (`/admin/course/index.php` 404) was a **bad probe URL** on my part — that path isn't a Moodle 5.x route. Dismissed, not a finding.

## Personas
- ✅ Guest (unauthenticated) — done, report `guest.md`
- ✅ Site Admin (qa_siteadmin) — done (subagent breadth walk + my verification), report `siteadmin.md`
- ⏸️ **CALIBRATION CHECKPOINT** — paused here for review before the remaining 6
- [pending] Org/L&D Admin, Trainer, Manager, Employee, Public, Compliance

## Env note (blocks some local QA — expected, not a regression)
BizLMS plugin suite (`local_search`, `local_users`, `local_courses`, `local_request`, `local_programs`,
`local_classroom`, + `local_custom_category` table) is **not deployed to local XAMPP** — only the fork's
`local_airpay_*` / `local_sentientia_*` plugins are. Flows that land on a BizLMS page (course detail,
legacy enrol) can't be exercised locally; verify on staging/production.

## Notes
- Latest code synced to XAMPP (theme/local/blocks), upgrade + purge done, login 200.
- qa_siteadmin provisioned (siteadmin; `admin/search.php` → 200 confirms full access).
- Other 6 personas provisioned post-checkpoint.
- Subagent delegation (Sonnet, shared authenticated browser) validated end-to-end.
