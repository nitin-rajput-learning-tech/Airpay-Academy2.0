# local_sentientia_ratings

Course ratings (five-star with optional comment). Public-tenant gated —
internal Airpay employees don't rate (workplace-training pressure
distorts the signal).

| Field | Value |
|---|---|
| Component | `local_sentientia_ratings` |
| Version | 1.0.0 |
| Depends on | `local_sentientia_org` |

## What it does

- Rating widget on the course view page.
- One rating per user per course.
- Optional free-text review.
- Aggregate display on course tile (average + count).

## Tables

`local_sentientia_ratings` — one row per user-course pair.

## Privacy / GDPR

Ratings are public by design (other learners see the aggregate). DSR
delete anonymises the user-id but preserves the rating value for
aggregate integrity.

## Open backlog

- Comment moderation pipeline (currently no moderation; relies on
  community reporting).
- Per-tenant on/off toggle (currently hardcoded to Public + ZEEA tenants
  via the cart-enabled flag).
