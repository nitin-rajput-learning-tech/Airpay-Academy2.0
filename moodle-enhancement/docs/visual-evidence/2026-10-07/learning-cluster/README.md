# Visual evidence owed - learning cluster owner decisions (2026-10-07)

**Status: NOT CAPTURED.** The session that made these changes had no browser access to a build that carries them (the
branch `claude/owner-decisions-y` is not deployed anywhere, and the local XAMPP tree must not be overwritten from a
worktree). CLAUDE.md section 5 requires desktop and 590 px screenshots for every UI change, so Nitin does not review these
changes as finished until the captures below exist. Capture them on the UAT build after the lead merges, save the images in
this folder (`NNN-<what>-desktop.png`, `NNN-<what>-mobile.png`) and tick the list.

Every flag below stays OFF until Nitin has seen the images (`framework.reader_flags_airpay_at_cutover`). No flag was flipped.
For each pair, capture the flag OFF first (the page must look as it did before the import), then ON.

## Recompletion (LRN-02, LRN-04) - plugin `local_sentientia_recompletion`, flag `sentientia.recompletion.evidence_view`

As a tenant admin (a manager-archetype role at system level) on a database that holds imported BizLMS resets:

- [ ] `/local/sentientia_recompletion/history.php`, flag OFF: only resets the Sentientia engine made; no "Legacy" badge, no
      `~` estimated time, the row count agrees with the list.
- [ ] same page, flag ON: the imported resets appear with the "Legacy" badge and `~` on estimated times; a tenant admin still
      sees only their own tenant's learners.
- [ ] `/local/sentientia_recompletion/index.php` with an imported rule (flag OFF): the "Imported from BizLMS" marker is still
      there (it is a configuration label, not history).
- [ ] `/local/sentientia_recompletion/edit.php?id=<imported rule>`: the notice says the rule cannot be enabled yet; ticking
      "Enabled" and saving shows the error under the checkbox and nothing is saved. A native rule still saves enabled.

## Learning paths (LRN-08, LRN-10) - plugin `local_sentientia_learningpath`, flag `sentientia.learningpath.learner_paths.enabled`

- [ ] `/local/sentientia_learningpath/view.php?id=<path with a BizLMS cover>` as a tenant admin, flag OFF: no cover image
      (the page is what it was before the import); flag ON: the cover shows above the description.
- [ ] Users tab of an imported path: remove an imported learner who has not started (flag state does not matter): the
      success message is followed by a warning that lists the course enrolments the import made from that plan (each with a
      link to the course's participants page) and that they are not removed. A learner in progress or completed: the
      protected-history error. A native learner: no warning.

## Classroom (XC-CLS-ENROL, LRN-10, LRN-17) - plugin `local_sentientia_classroom`, flag `sentientia.classroom.bulk_enrol_audience`

- [ ] Users tab of a classroom as a tenant admin, flag OFF: no "Bulk enrol by target audience" button; flag ON: the button is
      back, the modal opens, the preview count updates, and submitting with no filter shows "Pick at least one filter
      criterion".
- [ ] Classroom list with the Draft and On hold status filter buttons, and the edit form's status select (LRN-17: the two
      states ship unflagged).
- [ ] Users tab: removing an imported learner with no attendance from an active classroom works; a completed learner, a
      learner with any attendance mark, or any learner of a cancelled or completed classroom shows the protected-history
      error.

## Programs (LRN-10, doc item) - plugin `local_sentientia_programs`

- [ ] `/local/sentientia_programs/index.php` status filter: the button for status 0 now reads "Draft" (it said "Cancelled"),
      in English and Hindi.
- [ ] Users tab of an imported program: the trash action shows on a learner who has not started, has no current level and no
      stored level completion (program active), and is absent on a completed or in-progress learner and on every learner of
      an archived program.

## Not UI (nothing to capture)

LRN-01, LRN-03, LRN-05 (recompletion import dating, DPDP free text, the reset contract), LRN-07 (skills level map and the
preflight warning), LRN-11 (the stalled-path nudge scope), XC-TENANT-GUESS (report only) and the privacy and capability test
guards change no page. The classroom smoke script and the ICS description change no page either (the .ics file is a download:
open one for a session whose notes carry markup and check the description in a calendar app).
