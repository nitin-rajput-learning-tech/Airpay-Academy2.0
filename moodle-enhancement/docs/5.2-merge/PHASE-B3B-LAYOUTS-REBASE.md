# Phase B.3.b — layouts rebase against Moodle 5.2

**Date:** 2026-05-23
**Status:** Complete. The 4h ADR-011 estimate landed in **~20 min** —
same multiplier we've seen across this Phase B sprint, because the
5.2 boost layout diff was narrowly scoped to one pattern change.

---

## What 5.2 boost changed in its layouts

Per `5.2-theme-boost-full.diff`, the boost layouts changed only ONE
substantive thing: the tertiary navigation overflow dropdown was
rewritten to use the new `\core\output\select_menu` class.

Before (5.1):
```php
$overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
if (!is_null($overflowdata)) {
    $overflow = $overflowdata->export_for_template($OUTPUT);
}
```

After (5.2):
```php
$overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
if (!is_null($overflowdata)) {
    $selectmenu = new \core\output\select_menu(
        'tertiarynavigation',
        $overflowdata->urls,
        $overflowdata->selected,
    );
    $selectmenu->set_label($overflowdata->label, $overflowdata->labelattributes);
    $overflow = $selectmenu->export_for_template($OUTPUT);
}
```

The boost change happened in `theme/boost/layout/columns2.php`. The
identical pattern lives in 3 other airpayux layouts that we'd
forked from boost: `course.php`, `dashboard.php`, `drawers.php`.

---

## What we shipped

Migrated all 4 layouts to the dual-target pattern (same shape we used
for the Phase B.3 hook migration):

```php
$overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
if (!is_null($overflowdata)) {
    if (class_exists('\\core\\output\\select_menu')) {
        // 5.2 path
        $selectmenu = new \core\output\select_menu(...);
        $selectmenu->set_label(...);
        $overflow = $selectmenu->export_for_template($OUTPUT);
    } else {
        // 5.1 legacy path
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}
```

Files touched:

| File | Line | Sentientia-specific changes |
|------|-----:|------------------------------|
| `layout/columns2.php`  | 53-72 | Plain boost-derived layout |
| `layout/course.php`    | 72-91 | Sentientia course header + drawer overrides |
| `layout/dashboard.php` | 81-100 | Sentientia dashboard hero + tenant branding |
| `layout/drawers.php`   | 71-90 | Sentientia drawer chrome + secondary nav |

---

## Layouts NOT touched and why

Per the inventory grep at the top of this leg:

| Layout | Matched grep? | Has overflow pattern? | Action |
|--------|--------------|-----------------------|--------|
| `frontpage.php` | No | No | Already 5.2-compatible — smoke pass proved this |
| `columns1.php`  | Yes (different) | No (`headercontent` only) | No action needed |
| `embedded.php`  | Yes (different) | No (`headercontent` only) | No action needed |
| `login.php`     | No | No | No-op layout; Sentientia content in template |
| `maintenance.php`, `secure.php` | No | No | Minimal layouts |

5 of 10 layouts didn't need any touch.

---

## Versions

```
theme_airpayux : 2026052327 → 2026052328 (1.0.27-beta → 1.0.28-beta)
```

---

## Refs

- ADR-011 §"Phase B work breakdown" — B.3.b 4h estimate
- PHASE-A4B-CONFLICT-MAP.md §"C/D/E. boost/layout/*.php" — original
  "RE-IMPLEMENT" strategy
- 5.2-theme-boost-full.diff — the actual upstream diff
- PHASE-B3-WEB-SMOKE-PASS.md — confirmed frontpage.php works
- This file — Phase B.3.b leg
