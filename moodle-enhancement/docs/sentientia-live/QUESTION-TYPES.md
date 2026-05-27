# Sentientia Live — Question Types Reference

**Plugin:** `local_sentientia_live` (Mentimeter-style real-time engagement)
**Phase covered:** E.4 scaffold (2026-05-24) — base class + 6 stubs + registry;
Wave D4 (2026-05-24) — full implementation of `open_ended`, `rating_scale`,
`quiz`, `ranking` (`multichoice` + `word_cloud` via parallel chips C1/C2).
**Status:** Implemented. All 6 types ship full `render` / `persist_response` /
`tally` / `validate_config` / `get_aria_announcements` plus
`qt_<type>_audience` + `qt_<type>_result` Mustache templates. Plugin
`0.2.0-alpha`.

---

## What lives here

Every question type Sentientia Live can run is a concrete subclass of
`local_sentientia_live\question_types\abstract_question_type`. The
abstract base class declares the contract that audience-facing rendering,
response persistence, tally computation and screen-reader announcement
logic must satisfy.

`local_sentientia_live\question_types\question_type_registry` maps a slug
string (the value stored in the `type` column of
`{local_sentientia_live_slides}`) to the FQCN of the corresponding
subclass. Callers that need to "do something with a slide" look the
class up by slug and invoke methods on the instance — they never
hard-code class names.

Storage stays the same as Phase E.0: one row in
`{local_sentientia_live_slides}` per question, type-specific config in
`settings_json`, one row in `{local_sentientia_live_responses}` per
audience response, with `value_int` / `value_text` populated according
to the type's contract.

---

## The 6 registered types

| Slug | Class | Feature flag | Storage shape | Tally shape |
|------|-------|---------------|---------------|-------------|
| `multichoice` | `multiple_choice` | `live.questiontype.multichoice` | `value_int` = option index | `[idx => count, ...]` |
| `wordcloud` | `word_cloud` | `live.questiontype.wordcloud` | `value_text` = the entered word | `[word => count, ...]` desc sorted |
| `openended` | `open_ended` | `live.questiontype.openended` | `value_text` = free text | flat array of strings |
| `rating` | `rating_scale` | `live.questiontype.rating` | `value_int` = chosen rating | `[step => count, ..., '_avg' => float, '_count' => int]` |
| `quiz` | `quiz` | `live.questiontype.quiz` | `value_int` = chosen option index | `[idx => count, ..., '_correct_index' => int, '_correct_count' => int, '_total' => int, '_leaderboard' => [...]]` |
| `ranking` | `ranking` | `live.questiontype.ranking` | `value_text` = JSON array of item indices | `[item_idx => avg_position_float, ...]` asc sorted |

Each type's class doc-comment carries the precise settings-blob schema
and response-payload schema. `slide_manager::validate_settings()` is the
single source of truth for the on-write validation today; Phase E.4
moves that validation into each type's `validate_config()` (the
existing switch-on-type stays as a thin delegation until callers have
migrated).

---

## Abstract interface — what every type must implement

```php
abstract class abstract_question_type {

    public const SLUG = '';                    // The 'type' value in DB.
    public const FEATURE_FLAG = '';            // local_airpay_core flag key.
    public const NAME_STRING_KEY = '';         // Lang key for display name.
    public const DESCRIPTION_STRING_KEY = '';  // Lang key for picker desc.

    abstract public function render(array $context): string;
    abstract public function persist_response(int $userid, array $payload): int;
    abstract public function tally(int $sessionid, int $slideid): array;
    abstract public function validate_config(array $config): array;
    abstract public function get_aria_announcements(): array;

    // Concrete helpers provided by the base:
    public function get_display_name(): string;
    public function get_description(): string;
    public function get_slug(): string;
    public function is_enabled(): bool;        // Consults feature flag.
}
```

### Method contracts

- **`render(array $context): string`**
  Returns the audience-facing HTML for one slide. `$context` carries
  the slide row, parsed settings, current participant context, and an
  `aria_id_prefix` for accessibility IDs. Returned HTML must be safe
  to echo (every dynamic value pre-escaped). Templates are rendered
  via `$OUTPUT->render_from_template()` in the concrete class.

- **`persist_response(int $userid, array $payload): int`**
  Validate and persist one response. Returns the new response row ID
  from `{local_sentientia_live_responses}`. The instance is responsible
  for resolving `$userid` → `participantid` (via
  `participant_manager`) and for type-checking the payload before
  delegating to `response_recorder::submit()`.

- **`tally(int $sessionid, int $slideid): array`**
  Compute the tally shape this type renders into a chart. Each
  subclass owns its tally semantics — see the per-type rows above for
  the exact array shape.

- **`validate_config(array $config): array`**
  Type-creation-time settings validation. Returns an array of error
  messages (empty array = valid). Used by the slide editor form's
  server-side `validate()` hook so errors render as field-level
  feedback. **Never throws** — return errors, don't bubble exceptions.

- **`get_aria_announcements(): array`**
  Returns the static screen-reader announcement strings this type
  needs registered with its aria-live region. Map: announcement key
  (e.g. `'response_recorded'`) → human-readable string (already
  localised via `get_string`). The AMD chart_updater module emits
  these on `response_added` events.

---

## The registry

```php
use local_sentientia_live\question_types\question_type_registry;

// All registered types, keyed by slug, fresh instances:
$all = question_type_registry::get_all();

// Only types whose feature flag is ON (used by the slide-type picker):
$enabled = question_type_registry::get_enabled();

// Resolve a single slug — returns null if unknown:
$instance = question_type_registry::get_by_slug($slide->type);
if ($instance === null) {
    throw new \moodle_exception('invalidslidetype', 'local_sentientia_live');
}

// Cheap check that doesn't allocate:
if (question_type_registry::exists($slug)) { ... }

// Canonical slug list (matches slide_manager::VALID_TYPES):
$slugs = question_type_registry::list_slugs();
```

The registry is **stateless and side-effect-free**. Two resolutions of
the same slug yield equivalent (but not necessarily identical) instances.

---

## Adding a 7th type

The whole point of the abstract+registry split is to make this a
2-file (+ lang) change. To add (say) `pin_on_image`:

1. **Create the subclass.** Drop
   `classes/question_types/pin_on_image.php` mirroring the existing
   stubs:

   ```php
   class pin_on_image extends abstract_question_type {
       public const SLUG = 'pinimage';
       public const FEATURE_FLAG = 'live.questiontype.pinimage';
       public const NAME_STRING_KEY = 'qtype_pinimage_name';
       public const DESCRIPTION_STRING_KEY = 'qtype_pinimage_desc';

       public function render(array $context): string { /* ... */ }
       public function persist_response(int $userid, array $payload): int { /* ... */ }
       public function tally(int $sessionid, int $slideid): array { /* ... */ }
       public function validate_config(array $config): array { /* ... */ }
       public function get_aria_announcements(): array { /* ... */ }
   }
   ```

2. **Register it.** Add the entry to
   `question_type_registry::TYPES`:

   ```php
   private const TYPES = [
       'multichoice' => multiple_choice::class,
       // ... existing 5 ...
       'pinimage'    => pin_on_image::class,
   ];
   ```

3. **Lang strings (EN + HI).** Add the four keys to both
   `lang/en/local_sentientia_live.php` and `lang/hi/local_sentientia_live.php`
   — Hindi parity is enforced by the lang-parity pre-commit check.

   ```php
   $string['qtype_pinimage_name']        = 'Pin on image';
   $string['qtype_pinimage_desc']        = '...';
   ```

4. **Feature flag.** Register the flag in
   `local_airpay_core/db/feature_flags.php`. Default OFF — flip on
   when ready.

5. **Storage slug.** Add `'pinimage'` to
   `slide_manager::VALID_TYPES` and to the type column comment in
   `db/install.xml` (purely documentary; no schema change).

6. **Test.** The
   `question_type_registry_test::test_list_slugs_matches_slide_manager_valid_types`
   assertion will refuse to pass unless storage and registry agree —
   that's by design.

That's it. No callers need shotgun updates; templates / SSE / SCSS
slot into the type's own files.

---

## Cross-references

- ADR-004 — Realtime mechanism (SSE) for Sentientia Live
- `local_sentientia_live\slide_manager::VALID_TYPES` — storage-side
  whitelist; canonical truth that the registry MUST match
- `local_sentientia_live\response_recorder::tally()` — switch-on-type
  tally fallback; will move into each type's `tally()` method in
  Phase E.4 (deferred — keep both wired during the migration so the
  current SSE clients don't break)
- `theme/airpayux/scss/moodle/partials/_bizlms-modern.scss` — BEM
  classes (`airpay-badge`, `airpay-btn`) the result panel already
  uses; new type renderers should follow the same token conventions
