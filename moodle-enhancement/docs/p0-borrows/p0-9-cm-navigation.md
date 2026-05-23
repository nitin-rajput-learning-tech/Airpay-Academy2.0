# P0 borrow #9 — cm_info::get_navigation_url() shim

**Borrow source**: Moodle 5.2 core — `cm_info::get_navigation_url()`
**Status**: shipped 2026-05-23 in local_airpay_core 1.5.1
**Migration cost when 5.2 lands**: ~10 min mechanical search-and-replace.

---

## What we're backporting and why

Moodle 5.2 added a public `get_navigation_url()` method on `cm_info`. It
lets a module override its launch URL — useful for SCORM (jump to player
with attempt-id), URL activities (link straight to the external URL),
and our own `mod_airpay_evaluation` (land each learner on their attempt
page rather than a generic /view.php).

In 4.5/5.1 there is no public hook for this — modules can only set
`$cm->url` at install time via `cm_info::set_no_view_link()` /
`->set_extra_classes()`, which is too coarse-grained.

The shim is purely additive: any caller still using `$cm->url` keeps
working unchanged. Callers that want the override semantics route
through the resolver.

---

## Surface

```php
\local_airpay_core\cm_navigation::resolve_url(\cm_info $cm): ?\moodle_url
\local_airpay_core\cm_navigation::resolve_url_string(\cm_info $cm): string
```

**Resolution order**

1. Module-defined callback — `mod_<modname>_get_navigation_url($cm)` via
   `component_callback()`. If the callback returns a `moodle_url`, that
   wins. If it returns null or doesn't exist, fall through.
2. Default Moodle URL — `$cm->url`. Same as today's behaviour.
3. `null` — module has no launchable URL (e.g. label).

A throwing module callback does **not** take down the page. The
exception is caught, logged via `debugging()` at `DEBUG_DEVELOPER`, and
the resolver falls through to step 2.

---

## How a module opts in

In your module's `lib.php`:

```php
function mod_airpay_evaluation_get_navigation_url(\cm_info $cm): ?\moodle_url {
    global $USER;

    // Custom logic — e.g. land the learner on their own attempt.
    $attemptid = \mod_airpay_evaluation\attempt::current_for_user(
        $USER->id, $cm->instance
    );
    if ($attemptid) {
        return new \moodle_url(
            '/mod/airpay_evaluation/attempt.php',
            ['attempt' => $attemptid]
        );
    }

    // Return null to fall through to default.
    return null;
}
```

The callback is plain Moodle `component_callback()` style — no plugin
registration ceremony, no hook system, no DB. Module authors who already
know Moodle conventions need no new knowledge.

---

## Tenant safety

The resolver does **not** scope by tenant. It operates on a `cm_info`
that already carries the course context, and course contexts are
tenant-scoped upstream. Adding a tenant filter here would double-filter
and break legitimate cross-tenant admin views (Site Admin browsing a
Public-tenant course from an Airpay session).

See ADR-009 §3 — "Detection consistency": tenant filtering happens once,
as close to the user-input boundary as possible, never inside leaf
helpers.

---

## Tests

`local/airpay_core/tests/cm_navigation_test.php`

- `test_default_path_returns_module_view_url` — vanilla page module
  resolves to /mod/page/view.php?id=cmid
- `test_resolve_url_string_wrapper` — string variant returns `->out(false)`
- `test_label_module_returns_null` — labels have no launchable URL,
  both APIs return their "empty" sentinel (null / '')
- `test_callback_returning_null_falls_back_to_default` — documents the
  fallthrough contract; a real fixture module override is left as a
  future enhancement
- `test_resilience_contract_documented` — placeholder that points to the
  null-URL branch for future maintainers

---

## Migration when Moodle 5.2 lands

Find-and-replace across the codebase:

```
\local_airpay_core\cm_navigation::resolve_url($cm)
  → $cm->get_navigation_url()

\local_airpay_core\cm_navigation::resolve_url_string($cm)
  → $cm->get_navigation_url()?->out(false) ?? ''
```

Then delete:

- `local/airpay_core/classes/cm_navigation.php`
- `local/airpay_core/tests/cm_navigation_test.php`
- This doc.

Bump local_airpay_core version with a `-cm_navigation removed` release
note. That's it.

---

## Current consumers

1. `theme_airpayux/classes/output/traits/course_view.php::activityurl_get_course()`
   — single-activity-mode course landing URL.

Any future override consumer should be added to this list so the 5.2
search-and-replace is exhaustive.

---

## Related

- ADR-010 — Moodle 5.2 borrow inventory (P0 #9 row)
- ADR-009 — Detection consistency + WS contract gate
