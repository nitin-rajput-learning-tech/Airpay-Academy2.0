# learnerscript `parse_url(null)` deprecation — P3 deferral

**Status:** Deferred — third-party plugin, not Airpay-owned
**Priority:** P3 (deprecation noise only, no functional impact)
**First captured:** state-cards/2026-05-06-session-state.md "Pending engineering follow-ups"

## The deprecation

```
parse_url(): Passing null to parameter #1 ($url) of type string is deprecated
  C:\xampp\htdocs\moodle5\public\blocks\learnerscript\classes\observer.php:153
  C:\xampp\htdocs\moodle5\public\blocks\learnerscript\classes\observer.php:163
```

Both lines do `parse_url($_SERVER['REQUEST_URI'])['path']`. In a real
HTTP request `$_SERVER['REQUEST_URI']` is set; in a CLI context (PHPUnit
test runs, cron) it can be undefined → `null` → deprecation noise.

```php
// Line 153 (in else branch):
$_SESSION['pageurl_timeme'] = parse_url($_SERVER['REQUEST_URI'])['path'];

// Line 163 (later in method):
$_SESSION['pageurl_timeme'] = parse_url($_SERVER['REQUEST_URI'])['path'];
```

A 1-line guard would fix both:
```php
$_SESSION['pageurl_timeme'] = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
```

## Why we're not patching it

1. **Third-party code.** `blocks/learnerscript/` is a Moodle marketplace
   block we use for the LearnerScript reporting suite. Modifying it
   would mean carrying a private patch across upgrades.

2. **Functional impact: zero.** The deprecation only fires in CLI/test
   contexts where `$_SERVER['REQUEST_URI']` is unset. In live HTTP
   requests it's always set, so production users never see this.

3. **Test impact: noise only.** PHPUnit reports it as a `deprecation`
   notice, not a failure. Our 64/64 PHPUnit tests still pass cleanly.

4. **Right fix is upstream.** Should be filed at:
   https://moodle.org/plugins/block_learnerscript

## What to do instead

- **Now:** ignore the deprecation noise in PHPUnit output. It's clearly
  attributed in the test output to its source line.
- **Before next learnerscript upgrade:** check the changelog — likely
  fixed by the block maintainers in any release post-PHP 8.1.
- **If we ever decide to patch locally:** the guard above goes in
  `observer.php` at lines 153 and 163, and the patch is added to a
  `learnerscript-patches/` directory with a README explaining how to
  re-apply after upgrades.

## Pending: similar third-party deprecations

- `blocks/learnerscript/classes/observer.php:153` — `parse_url(null)`
- `blocks/learnerscript/classes/observer.php:163` — `parse_url(null)`
- `blocks/learnerscript/classes/observer.php:???` — `Undefined array key "REQUEST_URI"` (related to the parse_url issue, same root cause)

All three are CLI-context-only and trace to the same `$_SERVER['REQUEST_URI']` access pattern.

This deferral is logged so it doesn't get re-investigated next session.
