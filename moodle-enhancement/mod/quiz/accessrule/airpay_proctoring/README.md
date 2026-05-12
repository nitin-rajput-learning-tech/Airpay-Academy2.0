# quizaccess_airpay_proctoring

Moodle `mod_quiz` access-rule sub-plugin. Gates the quiz attempt
lifecycle on the proctoring session state defined by
`local_airpay_proctoring`.

| Field | Value |
|---|---|
| Component | `quizaccess_airpay_proctoring` |
| Plugin type | `quizaccess` (sub-plugin of `mod_quiz`) |
| Path | `mod/quiz/accessrule/airpay_proctoring/` |
| Depends on | `local_airpay_proctoring` |

## What it does

When a quiz is marked proctored, this rule:

1. **Pre-attempt**: requires the candidate's proctoring session to be in
   `recording` status before allowing `attempt.php` to proceed. If not,
   redirects to the consent/identity flow under `local_airpay_proctoring`.
2. **During attempt**: blocks resume if the session expired or was
   flagged with a critical event.
3. **Post-attempt**: triggers `local_airpay_proctoring\session_manager::finalize()`
   when the quiz attempt finishes — the analyzer runs immediately to
   set the auto-decision.

## How to enable per-quiz

In the quiz edit form (Site admin → quiz settings):

1. **Restrict access** → **Airpay Proctoring** → toggle on.
2. Save. The quiz now requires proctoring for all attempts by all users.

Internally, this writes to `mdl_config_plugins`:
- `plugin = 'quizaccess_airpay_proctoring'`
- `name = "quiz_{$quizid}_enabled"`
- `value = '1'`

(Per re-audit N7: this storage pattern bloats `mdl_config_plugins` at
1000+ quizzes scale — tracked as tech debt for Phase 9, not blocking.)

## How to verify

After enabling on a test quiz:
1. Log in as a non-admin user enrolled in the quiz course.
2. Click "Attempt quiz".
3. **Expected**: redirected to consent screen at
   `/local/airpay_proctoring/consent.php?quizid=X`.
4. Accept consent + submit identity. Score must ≥ `min_match_score`
   (default 0.85 — use `provider=mock` in dev to bypass).
5. **Expected**: redirected back to `attempt.php`, recording starts,
   quiz interactive.

## Privacy / GDPR

No data stored by this sub-plugin directly. All proctoring data lives
in `local_airpay_proctoring` tables (which has its own GDPR provider).

## Why a separate sub-plugin

Moodle's quiz access rule API is the canonical way to gate quiz
attempts. We could have done this from a `mod/quiz/lib.php` callback in
`local_airpay_proctoring`, but the access-rule pattern is more
discoverable to other admins, slots naturally into the quiz edit form,
and survives Moodle core upgrades better than callback hooks.
