# Core-mod record — `tool_certificate` (vendor plugin) × Moodle 5.2: `issue_handler::reset_caches(): void`

- **Date:** 2026-09-03 (UAT Stage A follow-up, finding F-7)
- **Change:** `admin/tool/certificate/classes/customfield/issue_handler.php:288` —
  `public static function reset_caches()` → `public static function reset_caches(): void`,
  tagged `// SENTIENTIA-CORE-MOD`.
- **Where:** repo `admin/tool/certificate/` (top-level, the package source), local XAMPP webroot,
  UAT `public/admin/tool/certificate/` (patched in place, `.bak-20260903` kept).
- **Why:** Moodle 5.2 declares `core_customfield\handler::reset_caches(): void`
  (`public/customfield/classes/handler.php:110`). PHP 8.3 refuses the child override without the
  return type (`Declaration ... must be compatible`), so the class autoload fatals. Every path that
  touches certificate custom fields died silently: `template::issue_certificate()` on UAT returned
  exit 255 with no exception (surfaced by the UAT test-user seed as "Error writing to database" after
  the completion had already been written), and the admin certificate-template pages would fatal the
  same way. 5.1.3 has no return type on the parent, so local never showed it.
- **Upgrade-safety:** adding `: void` is compatible with both 5.1.3 and 5.2 parents. Re-check on
  every `tool_certificate` vendor update; if the vendor ships a 5.2-compatible release, take theirs
  and drop this patch.
- **Detected by:** running a real issue on UAT (the P4 static pass and `php -l` cannot see a
  signature-compatibility fatal). Lesson: exercise each vendor plugin's write path once on the 5.2
  runtime.
