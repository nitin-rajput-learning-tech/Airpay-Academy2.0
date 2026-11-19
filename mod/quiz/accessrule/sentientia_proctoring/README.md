# quizaccess_sentientia_proctoring

Moodle `mod_quiz` access-rule sub-plugin. It adds a "Require proctoring" setting to the quiz form and,
for a proctored quiz, puts consent and an identity confirmation in the quiz's own pre-start check and
loads the monitor on every page of the attempt. Sessions, scoring and review live in
`local_sentientia_proctoring`.

> **Status (B1, session 4 of the post-demo plan, 2026-11-20): behind `sentientia.proctoring.enabled`,
> default OFF everywhere.** While the flag is OFF for the user, the rule does nothing: "Require
> proctoring" is not on the quiz form, a stored Yes is ignored, and the quiz is stock Moodle. It is not
> live anywhere (Sentientia is not in production; live airpay.academy is still BizLMS), and switching
> the flag on UAT is Nitin's call. Until B1 is deployed and the flag is ON, use the core settings in
> `moodle-enhancement/docs/guides/ASSESSMENT-INTEGRITY-ADMIN-GUIDE.md`.
>
> **Before B1 Moodle never loaded this rule.** Core looks for a global class named
> `quizaccess_sentientia_proctoring` in `rule.php`; the file declared only
> `quizaccess_sentientia_proctoring\rule`. So the setting never appeared on the quiz form. The code it
> would have run hid the Start button until a session was `recording` and loaded the consent step only
> on the attempt page, which would have locked learners out (the lock-out the 2026-11-19 version of
> this README described). B1 adds the global name and replaces that code. An earlier version described
> a redirect to a `consent.php` page, a resume and expiry check, an automatic post-attempt analysis and
> a "Restrict access > Airpay Proctoring" toggle stored in `mdl_config_plugins`. None of those exist.

| Field | Value |
|---|---|
| Component | `quizaccess_sentientia_proctoring` |
| Plugin type | `quizaccess` (sub-plugin of `mod_quiz`) |
| Path | `mod/quiz/accessrule/sentientia_proctoring/` |
| Version | `2026112000` (1.2.0, B1) |
| Depends on | `local_sentientia_proctoring` `2026112000` |
| Feature flag | `sentientia.proctoring.enabled` (registered by `local_sentientia_proctoring`, default OFF) |

## What it does (`rule.php`)

1. **Found by core.** `rule.php` ends with a `class_alias` to `quizaccess_sentientia_proctoring`, the
   name `mod_quiz\access_manager` looks for.
2. **Setting.** `add_settings_form_fields()` adds "Require proctoring" (yes/no, default No) only while
   the flag is ON for the person editing the quiz. `save_settings()` stores it in the
   `quizaccess_sentientia_proctor` table, one row per quiz (`enabled`, plus unused `min_match_score` and
   `retention_days_override` columns); when the field is not on the form (flag OFF) the stored row is
   left as it is. `get_settings_sql()` loads the stored value with the quiz, so the form shows it. The
   old per-quiz `mdl_config_plugins` rows were migrated to this table by `db/upgrade.php`.
3. **When the rule applies.** `make()` returns the rule only for a quiz with "Require proctoring" = Yes,
   while the flag is ON for the current user, and never for a preview: teachers and admins previewing
   the quiz get a stock quiz.
4. **Pre-start check.** `is_preflight_check_required()`, `add_preflight_check_form_fields()`,
   `validate_preflight_check()` and `notify_preflight_check_passed()` (the hooks `quizaccess_password`
   and `quizaccess_seb` use) add a notice of what is recorded and two required tick boxes, agreement to
   the monitoring and "I am (name) and I will take this attempt myself", to the form Moodle shows when
   the learner presses Start or continues an attempt that has no active session. The Start button is
   never hidden (`prevent_new_attempt()` is not used). Passing the check stores the consent on a session
   in `local_sentientia_proctoring` (`quiz_integration::pass_preflight()`).
5. **Monitor on every page.** `setup_attempt_page()` loads `local_sentientia_proctoring/proctor` on each
   page of the learner's own open attempt (attempt and summary pages), with the session the server found
   for that attempt. It loads nothing on the review page or on another user's attempt.
6. **After the attempt.** Nothing in this rule: the `attempt_submitted` and `attempt_abandoned`
   observers in `local_sentientia_proctoring` close and score the session on the server.

## How to verify (local site only, test accounts and a test quiz)

1. Flag OFF (the default): as an admin, edit a test quiz. **Expected:** no "Require proctoring" field;
   a test learner starts the quiz as normal.
2. On the local site only, switch `sentientia.proctoring.enabled` ON for the test learner's tenant in
   the Switchboard (`/local/sentientia_platform/admin/switchboard.php`). Edit the test quiz, set
   "Require proctoring" = Yes, save and open the settings again. **Expected:** it still shows Yes.
3. Log in as a non-admin test learner enrolled in the course and open the quiz. **Expected:** the quiz
   page says the quiz is proctored; **Attempt quiz** opens the pre-start form with the notice and two
   tick boxes; **Start attempt** with a box unticked shows an error and the attempt does not start;
   with both ticked the attempt starts and a banner says the attempt is proctored.
4. Switch to another tab and back three or four times, then go to the next page. **Expected:** the
   banner shows again on the next page.
5. Submit the attempt. As an admin, open `/local/sentientia_proctoring/review.php`. **Expected:** the
   session is in the queue (a tab switch scores 8 and a lost window focus 4, so a few switches pass the
   clean limit of 30). An attempt with no switches ends clean and is not listed.
6. As the teacher, preview the quiz. **Expected:** no pre-start check and no banner.
7. Switch the flag OFF again. **Expected:** the quiz is stock again; the stored Yes comes back when the
   flag is ON.

## Privacy

This sub-plugin stores only the per-quiz setting (no personal data; `null_provider`). All session
data lives in `local_sentientia_proctoring`, which has its own provider.

## Why a separate sub-plugin

Moodle's quiz access-rule API is the standard way to gate quiz attempts. It places the setting in
the quiz form and survives Moodle upgrades better than callback hooks. B1 uses the same API's pre-start
(pre-flight) check form for consent and identity.
