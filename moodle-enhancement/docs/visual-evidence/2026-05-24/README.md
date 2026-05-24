# Visual evidence — 2026-05-24 — `local_sentientia_aiquiz` Phase G.0 MVP

**Owner:** Claude (engineering)
**Reviewer:** Nitin Rajput
**Plugin:** `local_sentientia_aiquiz` v0.1.0-alpha (2026052400)

---

## What changed this session

Tier 1 #4 of the Sentientia LMS roadmap shipped its Phase G.0 MVP. Two
user-facing routes plus an admin settings page were added:

1. **Generate page** — `/local/sentientia_aiquiz/generate.php`
   Course Author pastes source text, picks a course, sets question
   count, ticks the [CONFIRM] checkbox, hits Generate.

2. **Review queue** — `/local/sentientia_aiquiz/review.php`
   Lists the actor's drafts. Click into a draft for the detail page.

3. **Review detail page** — `/local/sentientia_aiquiz/review.php?draftid=N`
   Per-question approve / edit / reject. Finalise + push buttons.

4. **Admin settings** — Site admin → Plugins → Local plugins → AI Quiz
   API key, default model, max questions, daily token cap, max source
   words.

5. **Switchboard flags** — three new entries:
   `sentientia.aiquiz.enabled`, `sentientia.aiquiz.live_api`,
   `sentientia.aiquiz.auto_push` (all default OFF).

---

## Screenshots required (capture-checklist)

This session was developed in an environment without Chrome MCP
access, so screenshots are deferred to Nitin's next local-XAMPP
verification run. Capture the following and save them in this folder:

| # | File name | What to capture |
|---|-----------|-----------------|
| 1 | `01-switchboard-flags-default-off.png` | Switchboard UI showing the three new flags all OFF (`sentientia.aiquiz.enabled`, `sentientia.aiquiz.live_api`, `sentientia.aiquiz.auto_push`) |
| 2 | `02-switchboard-master-flag-on.png` | Same Switchboard after flipping `sentientia.aiquiz.enabled` to ON. Live-API + auto-push still OFF. |
| 3 | `03-generate-form-empty.png` | Generate page on first visit. Mode badge should say "MOCK MODE — no live API call". |
| 4 | `04-generate-form-filled.png` | Generate page with form filled in: a title, a course picked, a paragraph of source text, num=10, [CONFIRM] checkbox ticked. |
| 5 | `05-generate-validation-pii-block.png` | Generate page after submitting a source that contains a fake Aadhaar number "1234 5678 9012". The error banner should say "Source content appears to contain PII (Aadhaar or PAN numbers)." |
| 6 | `06-review-detail-after-mock-generation.png` | Review detail page right after mock generation. 10 [MOCK Q] questions visible with options. Approve/Reject buttons per question. Status banner = "Awaiting review". |
| 7 | `07-review-finalised.png` | Same draft after finalising with at least one Approved + others Rejected. Status banner = "Approved (ready to push)". Push button visible but disabled with hint "Push to mod_quiz is gated behind sentientia.aiquiz.auto_push". |
| 8 | `08-review-list-empty.png` (optional) | Drafts list page with empty state ("You have no AI quiz drafts yet"). |
| 9 | `09-hindi-language-switch.png` (optional but high-value) | Generate page after switching site language to Hindi. Form labels render in Hindi (e.g. "ड्राफ्ट का शीर्षक"). |

---

## Mobile-breakpoint screenshots (taken at 590px)

| # | File name | Notes |
|---|-----------|-------|
| 10 | `10-mobile-generate-form.png` | Generate form at 590px. Verify form fields wrap, [CONFIRM] alert is readable, submit button is full-width or appropriately stacked. |
| 11 | `11-mobile-review-detail.png` | Review detail at 590px. Question cards stack vertically, action buttons remain reachable. |

---

## Manual test plan (run before promoting flag to ON)

1. **Install gate.** Run `php admin/cli/upgrade.php`. Plugin installs
   cleanly. Tables `local_sentientia_aiquiz_draft` +
   `local_sentientia_aiquiz_question` exist via `SHOW TABLES`.
2. **Flag default verification.** Visit `/local/sentientia_aiquiz/generate.php`
   without flipping flags. Should redirect to login if logged out;
   throw `err_feature_off` exception if logged in but flag is OFF.
3. **Switchboard flip.** Toggle `sentientia.aiquiz.enabled` to ON.
   Generate page now loads. Mode badge shows "MOCK MODE".
4. **Capability gate.** Log in as a regular learner (no editingteacher
   role). Visit generate.php. Capability check fails.
5. **Validation.** Submit form with empty source — see error. Submit
   with Aadhaar/PAN — see PII error. Submit with num=0 — see range
   error. Submit without ticking confirm — see confirm-required error.
6. **Mock generation.** Submit valid form. Redirects to review page.
   10 mock questions appear. Every stem starts with "[MOCK Qn]".
7. **Per-question actions.** Click Approve on Q1, Reject on Q2-Q5,
   Approve on Q6-Q10. Page reloads showing badge colour change on
   each.
8. **Finalise.** Click Finalise. Status banner flips to "Approved
   (ready to push)".
9. **Push (gated).** With auto_push OFF, push button is disabled.
   Flip `sentientia.aiquiz.auto_push` to ON in Switchboard, reload —
   push button is now enabled. Click it. Notice "Approved questions
   pushed to quiz #0 (X questions)". This is the G.0 stub — real
   `mod_quiz` creation lands in G.4.
10. **PHPUnit.** Run all 4 test files. ~47 tests pass without an API
    key.
11. **Privacy export.** Trigger a Moodle privacy data export for a user
    who has created drafts. JSON export contains their drafts. Trigger
    delete — drafts + their questions are removed; the drafts where
    they appeared as reviewer have `reviewed_by` nulled.
12. **Hindi.** Switch site language to Hindi (`?lang=hi`). Reload
    generate.php. Form labels render in Hindi. Reload review.php.
    Buttons + status badges render in Hindi.

---

## Sign-off

| Gate | Status | Verified by | Date |
|------|--------|-------------|------|
| `admin/cli/upgrade.php` clean install | ⏳ | Nitin | |
| Mock end-to-end demo | ⏳ | Nitin | |
| All PHPUnit tests pass | ⏳ | Nitin | |
| Switchboard wiring | ⏳ | Nitin | |
| Hindi parity inspected | ⏳ | Nitin | |
| Visual evidence captured | ⏳ | Nitin | |
| ADR-012 reviewed | ⏳ | Nitin | |
| State card reviewed | ⏳ | Nitin | |
