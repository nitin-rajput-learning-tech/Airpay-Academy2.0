# Learning paths: learner page and cover image (ADR-032) - visual evidence PENDING

**Status: not captured.** The build session was not allowed to deploy to the local XAMPP site or to start the
browser against it, so there are no screenshots yet. CLAUDE.md section 5 requires them before Nitin reviews the
change, and decision `framework.reader_flags_airpay_at_cutover` says the flag is flipped ON for Airpay only after
he has reviewed this evidence.

What changed on screen (plugin `local_sentientia_learningpath` 1.9.0):

1. New learner page `/local/sentientia_learningpath/mypaths.php`, behind the default-OFF flag
   `sentientia.learningpath.learner_paths.enabled`. Template `templates/mypaths.mustache` (Bootstrap cards, a native
   `progress` element, no inline colours).
2. The path detail page `/local/sentientia_learningpath/view.php?id=N&tab=overview` shows the cover image the import
   copied (`templates/view.mustache`, block `data-region="path-cover"`).
3. Admin list and export: status 0 reads "Archived" (was "Cancelled"), the "Learners completed" tile counts
   learners, dates of 0 print a dash, and the path-users CSV has Status and Completed on columns.

To capture (desktop and 590 px mobile, as a Learner role, never as admin; both tenants; light and dark):

| File | Page | Setup |
|---|---|---|
| `001-mypaths-desktop.png`, `001-mypaths-mobile.png` | `mypaths.php` | flag ON for the test customer; a learner enrolled in one active path in progress, one completed, one not started |
| `002-mypaths-empty-desktop.png` | `mypaths.php` | a learner with no path |
| `003-mypaths-flag-off.png` | `mypaths.php` | flag OFF: the page must refuse with "not available yet" |
| `004-path-view-cover-desktop.png` | `view.php?tab=overview` | after an import rehearsal that copied a cover |
| `005-archived-history-hidden.png` | `mypaths.php` | a learner whose only completed path is archived: it must not appear |

Nothing was flipped: the flag is OFF and stays OFF.
