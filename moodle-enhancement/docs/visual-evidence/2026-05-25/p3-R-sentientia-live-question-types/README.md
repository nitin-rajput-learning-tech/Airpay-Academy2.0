# P3-R — `sentientia_live` question-type stubs (Phase E.4-E.9)

**Chip:** P3-R / `elegant-wozniak-z8U4v` · **Merge:** `de2455fed` · **Date:** 2026-05-24

## What changed

New `classes/question_types/` directory in `local_sentientia_live`:
abstract base class + 6 concrete stubs + registry. PHPUnit interface
tests. Unblocks future UI chips for each question type.

### Type roster

| Phase | Code | Display name | Behaviour |
|-------|------|--------------|-----------|
| E.4 | `multiple_choice` | Multiple Choice | Single / multi-select with 2–10 options |
| E.5 | `word_cloud`      | Word Cloud      | Free-text 1–3 word entries aggregated into a live cloud |
| E.6 | `open_ended`      | Open Ended      | Free-text long-form answers, moderated |
| E.7 | `rating_scale`    | Rating Scale    | Likert 1–5 / 1–10 with average + distribution |
| E.8 | `quiz`            | Quiz            | Timed multiple-choice with leaderboard tie-in |
| E.9 | `ranking`         | Ranking         | Drag-to-rank N items by preference |

### Abstract base class contract

```php
abstract class abstract_question_type {
    abstract public function get_code(): string;
    abstract public function get_display_name(): string;
    abstract public function validate_response(mixed $response): bool;
    abstract public function aggregate_responses(array $responses): array;
    abstract public function render_form(\core_renderer $output): string;
    abstract public function render_results(\core_renderer $output): string;
}
```

Each stub returns canonical signatures so the registry interface test
passes today; real implementations land in E.4–E.9.

## Screenshots

| File | What to look for |
|------|------------------|
| `screenshot-desktop-six-question-stubs.png` | Six question-type cards (3×2 grid) — icon, name, code, phase tag, description, "stub · interface only" badge. Footer banner describes the abstract base class contract. |
| `screenshot-desktop-dark.png`               | Same grid in dark mode — icon badges retain primary-light fill |
| `screenshot-mobile-six-question-stubs.png`  | 590px viewport — six cards stack vertically |

## What to look for

1. **All six types render their canonical icon + name.** The registry
   `get_all_types()` round-trips correctly.
2. **`stub · interface only` badge on every card.** Confirms no
   business logic is implemented yet — each stub returns `[]` /
   `false` / empty strings from the abstract methods.
3. **Phase tags match the roadmap.** E.4 → E.9 in display order.

## Acceptance

- ✓ All 6 stubs implement the abstract contract (`abstract_question_type`)
- ✓ Registry `get_all_types()` returns 6 items, sorted by phase
- ✓ Interface test passes for each stub (PHPUnit)
- ✓ Adding a 7th type only requires a new file + auto-registration

## Refs

- State card: `state-cards/local_sentientia_live-state.md`
- ADR: existing ADR-009 (sentientia_live realtime architecture) — extends with type registry
- Phase: E.4–E.9 (UI work for each type lands in subsequent chips)
