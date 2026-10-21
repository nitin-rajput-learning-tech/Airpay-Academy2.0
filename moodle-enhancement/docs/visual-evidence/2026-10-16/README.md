# Visual evidence for the seven branches merged on 2026-10-16

Nitin approved all seven on 2026-10-16 ("ok 1 to 7"; `docs/REVIEW-QUEUE-2026-10-16.md`). The shots were taken
2026-10-16 to 10-21 on local XAMPP (Moodle 5.1) running the merged code, at desktop 1440 px and mobile 590 px. Real
people's names and e-mail addresses on the local copy are masked in the page before every shot (never in the
database). Every flag switched for a shot was restored to its exact previous value in the same run.

| Folder | Branch | README |
|---|---|---|
| `cart/` | `claude/gst-invoices` (UI part; the GST documents are in `../2026-10-14/gst/`) | Refund button on admin orders only; order history in words with ₹ |
| `evaluation/` | `claude/eval-followups` | My evaluations: page title, labelled dates |
| `emails/` | `claude/ui-fix-emails` | The template editor's preview on first load; no overflow |
| `theme/` | `claude/ui-fix-theme` | The edit switch; edit mode survives a reload for course authors; BS5 dropdowns |
| `request/` | `claude/ui-fix-request` | Request enrolment on the catalogue (flag); the line manager approves |
| `skills-org/` | `claude/ui-fix-skills-org` | The self-rate warning; "&" printed once |

The research branch (`claude/research-scorm-lxp`) is documents only and has no UI.

**Defects this pass found and fixed (all on gap-integration):**
1. `1b6a00698`: evaluation 2026101900. The EV-17 upgrade steps sat below a version already shipped, so a site at
   2026100802 never got `evaluationmode`. UAT and fresh installs were not affected. This fix is in the UAT package
   v4.3.1.
2. `c3cc82ba6`: three message providers had no `messageprovider:*` string, so every user's notification preferences
   showed `[[...]]`. The providers are emails, lifecycle and classroom.
3. `feb6f6cc9`: the theme's screen-reader page heading escaped "&" twice.

**Seen, left for a later pass:**
- the e-mail editor issues already listed in the emails state card;
- "No groups" wrapping and the empty left drawer on a course page in edit mode;
- the duplicated heading and breadcrumb on `approvals.php`.

The capture code is in `tools/visual-pass/`: `fix_branches_checks.mjs`, `eval_checks.mjs`, `fix_request_seed.php`,
`fix_request_flags.php` and `gst_evidence_flags.php`.
