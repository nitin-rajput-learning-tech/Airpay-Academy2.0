# UAT push 1, 21 Nov 2026: browser smoke (runbook step 6)

**What changed on UAT:** `af6e65c0e..2962954a6` deployed 13:05 IST (180 files, 12 plugin upgrades; record in
`moodle-enhancement/PROJECT-STATE.md`, 2026-11-21 ~15:45 entry; runbook
`moodle-enhancement/docs/cutover/UAT-DEPLOY-RUNBOOK-2026-11-21.md`).

**Who checked:** Nitin, signed in himself on https://academy2.airpay.ninja with the UAT test accounts (VPN off).
Result reported in chat ~15:30 IST: "all checks ok, no number of sections".

| # | Check | Result |
|---|---|---|
| 1 | Login page in the Airpay brand | OK |
| 2 | Learner test account: `/my/` and one course page, no red console errors | OK |
| 3 | L&D admin test account: Programs sidebar link, `/local/sentientia_compliance_report/index.php`, Manage Courses New course > Cancel > Edit | OK: no console TypeError (form.js guard, D-14); the edit form has no "Number of sections" (create-only since the 19 Nov quick win) |
| 4 | Signed out: `/admin/tool/certificate/index.php?code=NOTAVALIDCODE` | OK: "Not verified", one code input |
| 5 | Proctored quiz page | Skipped: no quiz had the proctoring rule enabled (M = 0) |

## Screenshots

- `meera-edit-course-modal-console.png`: Edit Course on `/local/sentientia_courses/index.php` with DevTools open.
  Console: an info line and a TinyMCE autosave warning only (it keeps the stored description instead of a draft);
  no errors. The 7 entries in the Issues tab are Chrome markup hints (form labels and autofill), not script errors;
  the two red "label for=" entries are most likely the start and end date pickers, a core Moodle form pattern.
  Logged as a later accessibility item, not a deploy finding. Browser bar cropped (it showed a profile picture).
- `meera-add-course-modal.png`: Add Course modal. All four sections open collapsed; follow-up in the course
  content how-to research started after the smoke (Nitin asked how content is added to and edited in courses).

No personal data: test accounts and seeded UAT test courses only.
