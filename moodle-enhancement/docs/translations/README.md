# Staged translations — pending review

This directory holds **machine-assisted translation DRAFTS that are
NOT active**. They live here, outside any plugin's `lang/` directory,
specifically so they cannot reach a learner until a human reviewer
signs off.

## Why a staging directory instead of a runtime feature flag?

Moodle's language resolver (`get_string()`) has **no per-plugin
feature-flag hook**. The moment a `lang/<code>/<component>.php` file
exists inside a plugin, every user whose language is set to `<code>`
sees it — there is no `if (flag_enabled())` gate available in the lang
layer. So the only enforceable "keep English until reviewed" mechanism
is to keep the draft *out of the active lang directory*. That's what
this folder is.

## Current drafts

| File | Target plugin | Strings | Status | Audit ref |
|------|---------------|---------|--------|-----------|
| `tool_certificate-hi-DRAFT.php` | `admin/tool/certificate` (vendored) | 173 | DRAFT — pending L&D Hindi review | C10 P1 / Gap 4 |

## Review + activation process (per draft)

1. **Review.** An Airpay L&D Hindi reviewer reads the draft against the
   live English pack (`admin/tool/certificate/lang/en/tool_certificate.php`),
   fixing terminology — especially the long `*_help` / `*_desc` strings
   and anything compliance-sensitive. CLAUDE.md §12 makes this review
   mandatory: "Compliance content needs L&D review before publish."
2. **Sign-off.** The reviewer records approval (PR comment / commit
   trailer) so there's an audit trail of who approved the Hindi.
3. **Activate.** Copy the reviewed file into the plugin's active lang
   dir and bump nothing (lang files don't need a version bump, but a
   cache purge is required):
   ```powershell
   Copy-Item "moodle-enhancement/docs/translations/tool_certificate-hi-DRAFT.php" `
             "moodle-enhancement/admin/tool/certificate/lang/hi/tool_certificate.php" -Force
   # then deploy to xampp + purge:
   Copy-Item "...workspace.../admin/tool/certificate/lang/hi/tool_certificate.php" `
             "C:/xampp/htdocs/moodle5/public/admin/tool/certificate/lang/hi/tool_certificate.php" -Force
   php "C:/xampp/htdocs/moodle5/public/../admin/cli/purge_caches.php"
   ```
4. **Record the core-mod.** Because `tool_certificate` is a vendored
   third-party plugin, adding a `lang/hi/` file is a tracked core-mod —
   see `docs/core-mods/2026-05-29-tool_certificate-hi-pack.md`. On a
   future upstream plugin upgrade the file may be overwritten; the
   core-mod record is how we know to re-apply it.

## What this draft does NOT affect

Certificate **content printed on the PDF** (recipient name, course
title, issue/expiry dates, signature blocks) comes from each
template's admin-authored elements, not from these `get_string()`
labels. Reviewing/activating this pack therefore does **not** change
any of the 11,415 already-issued certificates — it localises the admin
editor UI, the "My certificates" learner-page chrome, and the
event/notification labels.
