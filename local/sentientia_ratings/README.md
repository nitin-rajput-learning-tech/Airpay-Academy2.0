# local_sentientia_ratings

Star ratings (1 to 5) for courses, classrooms, programmes and learning paths, plus the written reviews and
the like and dislike reactions that BizLMS kept and the ADR-032 import brings over.

| Field | Value |
|---|---|
| Component | `local_sentientia_ratings` |
| Version | 1.2.0 |
| Depends on | nothing at install. The BizLMS importer needs the classroom, program and learningplan importers to have run first |

## What it does

- A star rating per user and item (`rating_manager`, the `local_sentientia_ratings_submit_rating` web service,
  the `rating_widget` AMD module). Averages and counts are **site-wide per item**: there is no tenant gate on
  rating or on the average, as in BizLMS. (An earlier version of this file said ratings were Public-tenant gated.
  The code never did that.)
- One rating per user, item and area; rating again updates it.
- The average shown is over ratings above zero, to one decimal place.
- `reviews.php?itemid=N&area=local_sentientia_courses`: the item page with the average, the like and dislike
  counts and the written reviews. Each of the two new parts ships behind its own flag, **OFF by default**:
  `sentientia.ratings.reviews` and `sentientia.ratings.reactions`. With both off the page does not exist.

## Areas

A rating, review or reaction is filed under one of `rating_manager::AREAS`: `local_sentientia_courses`,
`local_sentientia_classroom`, `local_sentientia_programs`, `local_sentientia_learningpath`,
`local_sentientia_exams`, `local_sentientia_evaluation`. The BizLMS names (`local_courses`, `local_classroom`,
`local_program`, `local_learningplan`) are not accepted any more: the import files those rows under the
Sentientia names, and a new row under an old name would split one item's ratings into two averages.

## Tables

| Table | Holds |
|---|---|
| `local_sentientia_ratings` | One row per user, item and area (UNIQUE). The rating itself. There is **no review column**: the earlier claim of an "optional free-text review" in this table was wrong |
| `local_sentientia_ratings_reviews` | Written reviews. No unique key: a learner may leave several. Raw text, escaped on output |
| `local_sentientia_ratings_reactions` | One like (1) or dislike (2) per user, item and area (UNIQUE). Any other stored value is kept and never counted |

## The BizLMS import (ADR-032)

`classes/bizlms/` holds the `ratings` importer, declared in `db/bizlms_import.php`. It reads `local_rating`,
`local_comment` and `local_like`, never writes them, and never writes `local_ratings_likes` (a derived cache,
used only as the parity oracle) or `block_trending_modules`. Run it through
`local/sentientia_platform/cli/import_bizlms.php`; after it has run,
`php local/sentientia_ratings/cli/ratings_oracle.php` compares the imported averages with what BizLMS showed
and explains each difference of more than 0.05 stars. The importer never calls `rating_manager` and never
flips a flag.

The BizLMS fallback that `rating_manager` used to carry (reading `local_rating` while its own table was empty)
is gone.

## Privacy / GDPR

Ratings are public by design (other learners see the aggregate). Erasing a user **deletes** their rows in all
three tables (it does not anonymise them), so an item's average and counts move after an erasure. The
BizLMS tables the import read keep the same people's data in the legacy archive, which this provider cannot
reach; the legacy-table privacy deliverable of ADR-032 covers it.

## Open backlog

- Comment moderation pipeline (currently no moderation; relies on community reporting).
- Initialise the rating widget on course pages, or render the stars read-only: the course header renders
  clickable buttons but nothing in the theme loads `rating_widget`. A visible change, so it needs the owner's
  call and visual evidence.
- Per-tenant on/off toggle for rating.
