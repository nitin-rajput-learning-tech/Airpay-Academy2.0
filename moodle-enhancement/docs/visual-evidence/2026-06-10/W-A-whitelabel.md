# Visual evidence — W-A white-label (2026-06-10, commit `1ee106d8c`)

**Decision (Q1):** white-label the customer name — resolve at runtime, don't hardcode.

## Change
Replaced the hardcoded `airpay academy` customer-name literals in the rendered chrome with the
live site-name variable already present in each template's context:

| Surface | Was | Now | Source |
|---|---|---|---|
| `loginform.mustache` hero `<h1>` + 2 logo `alt`s | `airpay academy` | `{{sitename}}` | `format_string($SITE->fullname)` |
| `core/email_html.mustache` logo `alt` | `airpay academy` | `{{sitefullname}}` | email context var |
| `core/maintenance.mustache` logo `alt` + footer name | `airpay academy` | `{{#str}}customername, theme_sentientia{{/str}}` | overridable theme string (DB-down safe) |

KEPT: `Airpay Payment Services` copyright (email) + non-rendered `{{! }}` header comments.

## Verification

**Correctness — served-HTML (curl, logged-out `/login/index.php`):**
```
hero <h1 class="airpay-login__hero-title">airpay academy</h1>   ← {{sitename}} resolved
{{ }} mustache leak in body: NONE
```
`$SITE->fullname` (CLI bootstrap) = **`airpay academy`** → the hero value exactly matches the site
config, confirming `{{sitename}}` **resolves at runtime** (not a stale literal). The display is
visually identical today only because the site fullname currently equals the old brand text; a new
customer (or Nitin titlecasing it) just edits **Site admin → Settings → Site name** and the login
hero / logo alts / email follow automatically. Maintenance uses the `customername` lang string
(override via Language customisation) because it renders with minimal context during DB-down.

**No regression — authenticated dashboard (real Chrome, post-deploy + post-purge):** `/my/` renders
healthy — app-shell sidebar, KPI cards (Enrolled / In Progress / Completed / Certificates), Overall
Completion ring, dark-mode toggle, user menu — confirming the template + lang deploy and cache purge
did not break the running app. (W-A only touched login/email/maintenance, all out of the
authenticated path.)

**Login-hero screenshot:** the hero is a *logged-out* surface; capturing it in the live browser would
end the active session, so it is verified via the served-HTML curl above rather than by logging the
user out. A logged-out screenshot can be captured on request.

## Lint / gates
`php -l` clean on both lang packs; pre-commit 15/15 green (incl. comment-leak + end-of-body); CI
`render-smoke` (Gate 1) + `a11y-smoke` (Gate 2 a11y) exercise the surfaces on Linux.
