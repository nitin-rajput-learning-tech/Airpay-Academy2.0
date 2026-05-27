# local_sentientia_calendar

**Sentientia LMS Calendar Sync — outbound ICS subscription URL per user.**

Tier 2 #6 on the Sentientia LMS roadmap. Phase 1 ships **outbound only**:
each user gets a personal ICS subscription URL they paste into
Outlook / Google Calendar / Apple Calendar; their course deadlines,
classroom (ILT) sessions, and exam close-dates appear in that calendar
automatically.

Bi-directional sync (events edited in the calendar app pushing back to
Sentientia LMS) is Phase 2, deferred pending customer demand — see
[ADR-013](../../docs/adr/ADR-013-calendar-sync.md).

---

## Quick start (admin)

1. Install the plugin: copy `local/sentientia_calendar/` into your
   Moodle `local/` directory and run `php admin/cli/upgrade.php`.
2. Enable the master flag: Switchboard → "Sentientia" category →
   `sentientia.calendar_sync.enabled` → ON.
3. (Optional) Toggle sub-flags for each event source if a customer
   doesn't use one of them:
   - `sentientia.calendar_sync.events.courses`
   - `sentientia.calendar_sync.events.classroom`
   - `sentientia.calendar_sync.events.exams`

The "Calendar subscription" nav item appears under each user's profile
sidebar as soon as the master flag is ON.

## Quick start (user)

1. Profile sidebar → **Calendar subscription**.
2. Click **Copy subscription URL**.
3. Paste into your calendar app following the on-page instructions.

The page also includes a **Regenerate URL** button — use this if you
ever paste the URL somewhere by accident.

---

## Architecture in one paragraph

A 64-character cryptographically-random token is issued per user and
stored in `local_sentientia_calendar_token`. The user pastes
`/local/sentientia_calendar/ics.php?token=<…>` into their calendar
app, which periodically fetches the URL. `ics.php` runs without
Moodle session cookies, looks up the token, and streams back an
RFC 5545 `text/calendar` body containing one VEVENT per upcoming
deadline. Token leak is mitigated by per-user regenerate (revokes the
old token immediately).

## Why no OAuth?

OAuth would give bi-directional sync at the cost of:
- Per-customer Microsoft / Google API app registrations
- Per-user refresh-token storage + rotation
- Three-way trust between learner ↔ provider ↔ Sentientia
- Provider data-processing privacy reviews

For "see my Sentientia deadlines in Outlook" — which is what learners
asked for — outbound is sufficient. See ADR-013 for the full trade-off
matrix.

## Files

```
version.php                                   Plugin manifest
lib.php                                       Nav callback
index.php                                     User-facing subscription page (+ OAuth status section)
regenerate.php                                Token regenerate POST
ics.php                                       The feed endpoint
oauth/connect.php                             OAuth connect entry (sesskey-protected)
oauth/callback.php                            OAuth callback (state-CSRF validated)
oauth/disconnect.php                          OAuth disconnect/revoke (sesskey-protected)
classes/token_manager.php                     ICS token lifecycle
classes/ics_builder.php                       RFC 5545 generator
classes/oauth/oauth_base.php                  OAuth 2.0 Auth-Code + PKCE engine (live)
classes/oauth/m365_oauth.php                  Microsoft 365 provider
classes/oauth/google_oauth.php                Google Calendar provider
classes/oauth/token_vault.php                 Encrypted-at-rest OAuth token store
classes/task/purge_old_tokens.php             Daily cleanup
classes/privacy/provider.php                  GDPR / DPDP (both tables)
db/install.xml                                Two tables (token + oauth)
db/access.php                                 Two capabilities
db/feature_flags.php                          1 master + 3 sub-flags + 1 OAuth master
db/tasks.php                                  Cron registration
settings.php                                  Admin OAuth client ID/secret config
lang/en/local_sentientia_calendar.php         88 strings
lang/hi/local_sentientia_calendar.php         88 strings (100% parity)
templates/subscription_page.mustache          UI (+ OAuth provider rows)
tests/token_manager_test.php
tests/ics_builder_test.php
tests/token_vault_test.php                    Vault round-trip, flag toggle, privacy, PKCE
tests/oauth_flow_test.php                     Live flow: callback, refresh, revoke, expiry
```

## Phase 2.1 — OAuth bi-directional sync (Wave C4)

The plugin now wires the OAuth 2.0 Authorization Code + PKCE flow for
Microsoft 365 (Microsoft Graph) and Google Calendar. Gated behind the
master flag `sentientia.calendar_sync.oauth.enabled` (default OFF). See
[CALENDAR-OAUTH.md](../../docs/integrations/CALENDAR-OAUTH.md) for the
full flow, scopes, token-storage, rotation, and revocation design.

## Feature flags

| Flag | Default | When OFF |
|------|---------|---------|
| `sentientia.calendar_sync.enabled` | OFF | Every surface returns 403/404; the nav node hides |
| `sentientia.calendar_sync.events.courses` | ON | Course-deadline VEVENTs omitted |
| `sentientia.calendar_sync.events.classroom` | ON | Classroom-session VEVENTs omitted |
| `sentientia.calendar_sync.events.exams` | ON | Exam-close VEVENTs omitted |
| `sentientia.calendar_sync.oauth.enabled` | OFF | OAuth connect/callback/refresh throw; no provider HTTP; OAuth section hidden on index.php |

## Security model

- Token: 64 chars × 56-char alphabet → ~381 bits of entropy
- Generated via `random_bytes()` (cryptographically secure)
- One ACTIVE token per user; regenerate revokes the old
- Revoked rows kept 90 days for forensics, then purged by cron
- `ics.php` returns **404** for every authentication failure mode —
  denies an attacker the ability to enumerate users / tenants
- Token comparison short-circuits on syntactic gates (length, charset)
  before any DB hit
- `ics.php` defines `NO_MOODLE_COOKIES` so the fetch does NOT establish
  a session for the token bearer

## Privacy

Stored data: one row per user in `local_sentientia_calendar_token`
containing the userid, the token itself, audit fields (last_used_at,
last_used_ip, use_count), customerid + tenantid for cross-customer
reporting (Phase 2).

The feed body contains the user's own learning data only — no other
users' names / emails / IDs, no course content, no API tokens. See
`classes/privacy/provider.php` for the full DPDP / GDPR export +
delete implementation.

## Tests

```
vendor\bin\phpunit local/sentientia_calendar
```

- `token_manager_test.php` — 17 assertions on token lifecycle, idempotency, isolation
- `ics_builder_test.php` — 11 assertions on RFC 5545 conformance, user isolation, feature-flag scoping

## Dependencies

Required:
- `local_airpay_core` 2026051401+ — feature_flags resolver

Optional (graceful degradation if missing — the relevant event category just disappears from the feed):
- `local_airpay_courses` for COURSE-DEADLINE events
- `local_airpay_classroom` for CLASSROOM-SESSION events
- `local_airpay_exams` for EXAM-CLOSE events

## References

- [ADR-013 — Calendar sync token-URL vs OAuth](../../docs/adr/ADR-013-calendar-sync.md)
- [State card](../../state-cards/local_sentientia_calendar-state.md)
- RFC 5545 — iCalendar specification
