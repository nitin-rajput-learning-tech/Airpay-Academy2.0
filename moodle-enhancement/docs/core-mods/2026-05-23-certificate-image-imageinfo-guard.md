# 2026-05-23 — tool_certificate image-element imageinfo guard

**Filed:** 2026-05-23 (User report — Site Admin viewing
`/admin/tool/certificate/template.php?id=12`)
**Reporter:** Nitin Rajput
**Severity:** P0 — TypeError crashes entire certificate template page
**Affects:** Every Site Admin user editing a certificate template
that contains an image element pointing to a non-image file.

---

## Bug

```
Exception - tool_certificate\element_helper::render_image_html():
Argument #2 ($imageinfo) must be of type array, bool given,
called in admin/tool/certificate/element/image/classes/element.php
on line 207
```

Stack trace anchored at:
```
line 575 of public\admin\tool\certificate\classes\element_helper.php:
  TypeError thrown
line 207 of public\admin\tool\certificate\element\image\classes\element.php:
  call to tool_certificate\element_helper::render_image_html()
line 99  of public\admin\tool\certificate\classes\output\element.php:
  call to certificateelement_image\element->render_html()
line 121 of public\lib\classes\external\exporter.php:
  call to tool_certificate\output\element->get_other_values()
line 96  of public\admin\tool\certificate\classes\output\page.php:
  call to core\external\exporter->export()
line 121 of public\lib\classes\external\exporter.php:
  call to tool_certificate\output\page->get_other_values()
line 108 of public\admin\tool\certificate\classes\output\template.php:
  call to core\external\exporter->export()
line 121 of public\lib\classes\external\exporter.php:
  call to tool_certificate\output\template->get_other_values()
line 78  of public\admin\tool\certificate\template.php:
  call to core\external\exporter->export()
```

## Root cause

`\stored_file::get_imageinfo()` returns `array` of image metadata
when the file is an image; returns `false` when the file's
mime-type isn't an image. The upstream tool_certificate code
passes that return value straight to
`element_helper::render_image_html()` which has a strict
`array $imageinfo` parameter signature.

When a certificate template's image element points to a stored
file that isn't a valid image (e.g., a PDF was uploaded as the
image, or the file became corrupted/mime-mismatched), every page
render crashes with a fatal TypeError.

The function ALREADY has a no-file branch (line 196-200) that
substitutes safe defaults `['width' => 140, 'height' => 140]`.
The has-file-but-not-image case was simply not anticipated.

## Fix

In `public/admin/tool/certificate/element/image/classes/element.php`,
inside `render_html()`, immediately after the
`$fileimageinfo = $file->get_imageinfo();` call, guard against
non-array return:

```php
if ($fileimageinfo === false || !is_array($fileimageinfo)) {
    $fileimageinfo = ['width' => 140, 'height' => 140];
}
```

Tagged at modification site with `// SENTIENTIA-CORE-MOD (2026-05-23)`
per CLAUDE.md core-mod discipline.

## Upgrade-safety analysis

When we eventually pull the next Moodle release (5.2 → 5.3 etc.),
the file `public/admin/tool/certificate/element/image/classes/element.php`
will be overwritten. Re-apply the patch by searching for the
SENTIENTIA-CORE-MOD marker comment and replicating the guard.

**Better long-term:** file this upstream as a Moodle bug. The fix
is small enough and the failure mode generic enough that it should
land in core. Then we drop our core-mod entirely.

## Reproduction

1. Log in as Site Admin (`academy@airpay.co.in`)
2. Navigate to Site Admin → Plugins → Admin tools → Certificate
3. Edit a template that has an image element
4. If the image's underlying stored file is no longer a valid image
   (PDF substituted, mime-type drift, file corruption), the page
   crashes with the TypeError above.

## Lessons / process implications

This bug should have been caught by:
1. **A functional click-through audit** of every persona × every
   feature, not just the visible-surface chrome audit (Goal A)
   that we shipped this week.
2. **Playwright E2E specs** that drive the actual UI, not just
   assert `getComputedStyle` on CSS markers (which is what
   `tests/surfaces.spec.mjs` does).

The Goal A audit was a **UI audit, not a functional audit**. I
conflated the two in summaries this session and overclaimed
"100% confidence" the platform was healthy. The truth is:
visible chrome is healthy + bug-class invariants are enforced
in CI; the deep functional surfaces of imported Moodle features
(tool_certificate, mod_quiz, mod_assign, etc.) have not been
walked.

Tracked as a new audit cycle: **Goal A.y — functional click-
through audit**. See PROJECT-STATE.md.
