# local_sentientia_proctoring

Robust proctoring for hiring assessments + skill-evaluation quizzes.
Three-layer architecture: identity verification + live recording + AI
behaviour flagging. Pairs with the `quizaccess_airpay_proctoring`
sub-plugin that gates the quiz `attempt.php` lifecycle.

| Field | Value |
|---|---|
| Component | `local_sentientia_proctoring` |
| Version | `2026051201` (1.0.1) |
| Requires | Moodle 4.5+ (`2024042200`) |
| Maturity | `MATURITY_STABLE` |
| Depends on | `local_sentientia_org`, `local_sentientia_privacy`, `local_sentientia_platform` |

## What it does

State machine:
```
new → consenting → verifying → recording → finished
                                         → flagged → reviewed
```

1. **Start session** when candidate opens a proctored quiz.
2. **Consent** screen — candidate must accept recording terms.
3. **Submit identity** — base64 ID photo + selfie → AWS Rekognition CompareFaces → match score ≥ 0.85 required.
4. **Recording** — webcam + screen chunks uploaded direct-to-S3 via presigned URL; we only store object keys + metadata.
5. **Finalize** — risk analyzer scans event log + recordings, sets `auto_decision` (clean/warn/fail).
6. **Review** — flagged sessions go to a reviewer queue (tenant-scoped).

## Capabilities

| Capability | Granted to | Purpose |
|---|---|---|
| `local/sentientia_proctoring:attempt` | student | take a proctored quiz |
| `local/sentientia_proctoring:review` | manager | review flagged sessions |
| `local/sentientia_proctoring:viewattempts` | manager, editingteacher | list/view attempts |
| `local/sentientia_proctoring:configure` | _(siteadmin)_ | configure providers/retention |

## Tables (5)

| Table | Purpose |
|---|---|
| `local_sentientia_proctor_sessions` | One row per quiz attempt |
| `local_sentientia_proctor_identity` | ID match score (photos NEVER stored, only the score) |
| `local_sentientia_proctor_events` | Per-attempt event log (tab_switch, face_lost, etc.) |
| `local_sentientia_proctor_recordings` | S3 object keys + retention metadata (no bytes server-side) |
| `local_sentientia_proctor_reviews` | Human reviewer decisions |

## Web services (12)

| Function | Purpose |
|---|---|
| `local_sentientia_proctoring_start_session` | Create a session row on quiz start |
| `local_sentientia_proctoring_give_consent` | Candidate accepts recording terms |
| `local_sentientia_proctoring_submit_identity` | Base64 ID+selfie → AWS verify (rate-limited, MIME-sniffed) ← Phase 8.1 B7 |
| `local_sentientia_proctoring_upload_chunk` | Register a chunk (after browser direct-uploaded to S3) |
| `local_sentientia_proctoring_report_event` | Add to per-session event log (owner-only) ← Phase 8.1 B3 |
| `local_sentientia_proctoring_finalize_session` | Trigger AI analysis + decision |
| `local_sentientia_proctoring_list_attempts` | Admin attempt list (tenant-scoped) |
| `local_sentientia_proctoring_get_attempt` | Fetch single attempt (tenant-scoped) |
| `local_sentientia_proctoring_flag_session` | Manual flag-for-review |
| `local_sentientia_proctoring_list_review_queue` | Reviewer queue (tenant-scoped) |
| `local_sentientia_proctoring_submit_review` | Reviewer decision (tenant-scoped) |
| `local_sentientia_proctoring_compliance_report` | Daily aggregate (tenant-scoped) |

## Settings (Site admin → Plugins → Local plugins → Airpay Proctoring)

| Setting | Purpose |
|---|---|
| `provider` | `mock` (dev) or `aws` (production) |
| `aws_region` | AWS region (e.g. `ap-south-1`) |
| `aws_access_key` | AWS access key id |
| `aws_secret_key` | AWS secret (configpasswordunmask) |
| `s3_bucket` | S3 bucket for recordings |
| `min_match_score` | Identity match threshold (default 0.85) |
| `retention_days` | Recording auto-delete (default 90) |
| `default_reviewer` | User id receiving flagged-session notifications |

## Scheduled tasks

| Task | Schedule | Purpose |
|---|---|---|
| `\local_sentientia_proctoring\task\purge_old_recordings` | 03:30 daily | Mark expired recordings for S3 delete (stub in current build; production version performs the actual S3 delete) |

## Cache definitions

`db/caches.php` defines:
- `identity_rate` — per-user-per-hour rate limit for `submit_identity` (Phase 8.1 B7).

## Phase 8.1 security hardening

- **B2** (CVSS 8.1): All 7 read paths tenant-scoped (`list_review_queue`, `list_attempts`, `compliance_report`, `get_attempt`, `submit_review`, `flag_session`, `attempt.php`).
- **B3** (CVSS 8.2): `register_chunk`, `record_event`, `finalize` verify caller owns the session via `assert_session_owner()`. Strict s3_key regex (`^[a-zA-Z0-9/_.-]{1,512}$`). Size/duration bounds.
- **B7** (CVSS 6.8): `submit_identity` adds 5-per-hour rate limit, size cap 14MB→5.5MB, strict base64 decode, JPEG/PNG magic-byte sniff.

## How to verify after install

```powershell
# 1. CLI smoke (no browser needed):
php "C:/xampp/htdocs/moodle5/public/local/sentientia_proctoring/cli/smoke_proctoring.php"
# Expected: 22/22 cases pass

# 2. Set provider to 'mock' in settings during testing, switch to 'aws' for production.
```

## Privacy / GDPR

`classes/privacy/provider.php`:
- Identity photos are NEVER persisted — only the match score and pass/fail flag.
- Recording S3 keys are deleted after `retention_days`.
- DSR delete recursively removes all session+event+recording rows for a userid.
- Pre-exam consent screen is the audit trail for the recording.

## AWS integration

`classes/identity/aws_verifier.php` implements SigV4 signing — no key/secret leaks
to logs. Constant-time hash compare for the webhook signature. AWS Rekognition
called via direct HTTPS without the SDK (zero dep weight).
