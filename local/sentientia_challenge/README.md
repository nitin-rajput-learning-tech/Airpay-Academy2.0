# local_sentientia_challenge

Gamification challenges. Sits alongside `local_sentientia_gamification`
(points + badges + streaks) but holds the workflow + leaderboard.

| Field | Value |
|---|---|
| Component | `local_sentientia_challenge` |
| Version | beta 1.1.1 |
| Depends on | `local_sentientia_org`, `local_sentientia_gamification` |

## What it does

- Challenge definitions (e.g. "Complete three Compliance courses in
  a week", "Score ≥80 on five quizzes", "Maintain a 7-day login streak").
- Per-user challenge attempts with timestamps.
- Leaderboard ranking.
- Web push notifications on challenge completion (planned — see backlog).

## Tables (3)

- `local_sentientia_challenge_challenges` — challenge definitions.
- `local_sentientia_challenge_attempts` — per-user progress.
- `local_sentientia_challenge_leaderboard` — ranking snapshot.

## Capabilities (4)

`:view`, `:viewall`, `:manage`, `:participate`.

## Scheduled tasks

Recomputes the leaderboard hourly.

## Verify after install

```powershell
# Visit /local/sentientia_challenge/index.php as a participant
# Expected: list of active challenges with progress bars
```

## Open backlog (Phase 2 — Section 12.1)

- Streak challenges (login N days in a row).
- Quiz-score challenges with anonymous peer comparison.
- Badges (Moodle core badges integration via sentientia_gamification).
- Web push notifications.
- Frontend dashboard widget.
