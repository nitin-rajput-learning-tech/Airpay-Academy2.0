# Core-mod record: `tool_tcpdffonts` (vendor plugin): the privacy string its provider names

- **Date:** 2026-11-13
- **Author:** Claude (cloud session), task "make core privacy compliance tests fully green"
- **Tag at the site:** `// SENTIENTIA-CORE-MOD (vendor)` ... `// END SENTIENTIA-CORE-MOD`
- **File:** `admin/tool/tcpdffonts/lang/en/tool_tcpdffonts.php` (one string appended at the end; top-level tree only,
  the plugin has no `moodle-enhancement/` copy)

## Why

`tool_tcpdffonts` (Sebsoft, 2021, `v1.0.1 (2025120101)`) declares `null_provider`, and its `get_reason()` returns
`'privacy:metadata'`, but the English pack never defined that string. Core's
`privacy/tests/privacy/provider_test.php::test_null_provider` printed "Invalid get_string() identifier" and was
risky. The Plugin privacy registry page showed the raw identifier instead of a reason.

## What changed

```php
$string['privacy:metadata'] = 'The TCPDF fonts tool only manages font files for PDF generation. It stores no personal data.';
```

The plugin has no table and keeps only font files, which are not personal data.

## Not changed

- The provider class, and the plugin version (a lang string needs only the cache purge every deploy runs).
- No schema change.

## Upgrade-merge notes

If upstream adds the string, drop this block. If upstream ships a new release without it, re-append the tagged block.
