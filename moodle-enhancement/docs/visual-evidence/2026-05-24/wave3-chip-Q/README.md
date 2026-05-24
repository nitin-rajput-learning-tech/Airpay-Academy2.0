# Wave-3 chip Q — P2 #20 / F-20 coursebannerimage XSS sanitisation verification (2026-05-24)

**Branch:** `claude/vibrant-cori-dFy4m` on `nitin-rajput-learning-tech/Airpay-Academy2.0`
**Scope:** Single P2 finding — `course_full_header.mustache` background-image inline-style with templated URL.
**Audit ref:** `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` §3 — F-20.
**Theme version bump:** `2026052403 → 2026052404` (release `1.0.33-beta → 1.0.34-beta`).

---

## TL;DR — Strategy chosen

**Verify + document. No migration to data-attribute + AMD needed.**

The dynamic `coursebannerimage` URL the template embeds in
`style="background-image: url('...');"` is already safe in CSS `url('...')`
context because the upstream renderer routes the URL through Moodle's
`\core\url::make_pluginfile_url(...)->out()`, and that chain calls
`rawurlencode()` on every path segment (including the user-controllable
filename + filepath). `rawurlencode()` percent-encodes every character
that could terminate the CSS url() context: `'`, `"`, `(`, `)`, `;`, `\`,
`<`, `>`, space.

A 41-line Mustache `{{! ... }}` comment block now lives above line 84 of
`course_full_header.mustache` capturing this reasoning so the next
auditor (or any developer changing `course_bannerimage()`) sees the
security invariant inline.

---

## §1 — Upstream sanitisation trace

The audit (F-20) noted:

> File: `moodle-enhancement/theme/airpayux/templates/course_full_header.mustache:41`
> Symbol: `.courseheader` background
> Found: `style="background-image: url('{{coursebannerimage}}');"`
> Spec: Acceptable for dynamic image URLs, but background-image URL injected from user-uploadable course banner is XSS-prone if `coursebannerimage` is not URL-escaped.
> Expected: Verify upstream that `coursebannerimage` passes through `s()` or `out_as_local_url()` before reaching this template. Better still: emit a `data-cover-url` attribute and let an AMD module read it / apply via `CSSStyleDeclaration` to keep the value out of the parsed CSS context.

### §1.1 — Where the value originates

```
templates/course_full_header.mustache:84
        └── {{coursebannerimage}}
            └── core_renderer.php:937    $header->coursebannerimage = $this->course_bannerimage();
                └── classes/output/traits/course_view.php:74-88   course_bannerimage()

                    function course_bannerimage() {
                        global $COURSE;
                        $course = new \core_course_list_element($COURSE);
                        foreach ($course->get_course_overviewfiles() as $file) {
                            if ($file->is_valid_image()) {
                                return \moodle_url::make_pluginfile_url(
                                    $file->get_contextid(), $file->get_component(),
                                    $file->get_filearea(),
                                    null, $file->get_filepath(), $file->get_filename()
                                )->out();
                            }
                        }
                        return $this->image_url('course_default', 'theme_airpayux')->out();
                    }
```

Two possible return values:

| Branch | Return value | User-controllable? |
|---|---|---|
| Banner uploaded | `moodle_url::make_pluginfile_url(...)->out()` | filename + filepath — yes |
| No banner uploaded | `image_url('course_default', 'theme_airpayux')->out()` | No — developer-controlled |

### §1.2 — `make_pluginfile_url` → `make_file_url` → `set_slashargument`

`make_pluginfile_url` (`lib/classes/url.php:685-724`) builds a path
string and delegates to `make_file_url`, which constructs a `moodle_url`
and calls `set_slashargument($path)`:

```php
// lib/classes/url.php:585-601
public function set_slashargument($path, $parameter = 'file', $supported = null) {
    global $CFG;
    if (is_null($supported)) {
        $supported = !empty($CFG->slasharguments);
    }

    if ($supported) {
        $parts = explode('/', $path);
        $parts = array_map('rawurlencode', $parts);       // ← every segment rawurlencode'd
        $path  = implode('/', $parts);
        $this->slashargument = $path;
        unset($this->params[$parameter]);
    } else {
        $this->slashargument = '';
        $this->params[$parameter] = $path;                // ← falls through to get_query_string,
    }                                                     //   which also rawurlencode's
}
```

For the slasharguments-off path, `get_query_string()`
(`lib/classes/url.php:298-323`) also calls `rawurlencode()` on every key
and every value before assembling the query string:

```php
$arr[] = rawurlencode($key) . "=" . rawurlencode($val);
```

So regardless of `$CFG->slasharguments`, every user-controllable byte of
the URL is `rawurlencode()`'d.

### §1.3 — Characters `rawurlencode()` percent-encodes

PHP's `rawurlencode()` follows RFC 3986 — it encodes everything except
unreserved characters (`A-Z`, `a-z`, `0-9`, `-`, `_`, `.`, `~`). Notably:

| CSS-context-terminating char | After `rawurlencode()` |
|------------------------------|------------------------|
| `'`  | `%27` |
| `"`  | `%22` |
| `(`  | `%28` |
| `)`  | `%29` |
| `;`  | `%3B` |
| `\`  | `%5C` |
| `<`  | `%3C` |
| `>`  | `%3E` |
| ` ` (space) | `%20` |
| `{`  | `%7B` |
| `}`  | `%7D` |

Empirical verification on the local PHP runtime:

```
$ php -r "echo rawurlencode(\"foo'); evil('\") . \"\n\";"
foo%27%29%3B%20evil%28%27

$ php -r "echo rawurlencode('<script>alert(1)</script>') . \"\n\";"
%3Cscript%3Ealert%281%29%3C%2Fscript%3E
```

Both attack payloads are rendered inert by URL-encoding.

### §1.4 — End-to-end CSS-context worked example

A malicious actor with course-edit capability uploads a file named:

```
foo'); background:url('evil
```

After `make_pluginfile_url(...)->out()`:

```
https://example.test/pluginfile.php/123/course/overviewfiles/0/foo%27%29%3B%20background%3Aurl%28%27evil
```

Embedded into the template via `{{ }}` double-brace HTML-escaping:

```html
<div class="courseheader" style="background-image: url('https://example.test/pluginfile.php/123/course/overviewfiles/0/foo%27%29%3B%20background%3Aurl%28%27evil');">
```

The browser parses the CSS value. The percent-encoded sequences stay as
literal percent-encoded URL bytes — they are **not** decoded inside CSS
parsing. The browser then issues an HTTP GET for the URL with the
percent-encoded path, which `pluginfile.php` decodes server-side after
the request is received. No CSS-context escape; no XSS.

### §1.5 — Defense in depth — the `{{ }}` double-brace

The Mustache template uses `{{coursebannerimage}}` (double-brace), which
HTML-escapes the output before it lands in the `style="..."` attribute.
Even though `rawurlencode()` already removed every CSS-terminating char,
the double-brace adds a second layer: any future regression in upstream
encoding would also need to bypass HTML escaping to be exploitable.

The committed comment block instructs maintainers explicitly: **DO NOT
migrate to `{{{ }}}` triple-brace here.**

---

## §2 — Decision matrix

| Option | Risk reduction | Invasiveness | Choice |
|---|---|---|---|
| **A. Verify + document (chosen)** | Captures the proof inline so the next auditor or maintainer sees it; preserves runtime behaviour. | None — comment-only change. | ✅ |
| B. Migrate to `data-cover-url` + AMD | Adds a second URL-handling layer (DOM API instead of CSS parser). Marginal incremental safety only — `rawurlencode()` already removed every CSS-context-terminator char before the URL even reached the template. | Template rewrite + new AMD source + hand-rolled `amd/build/*.min.js` + `js_call_amd` wiring in `layout/course.php`. Surface-area increase for a P2 finding. | ❌ |

The audit's spec text begins with **"Acceptable for dynamic image
URLs"** and only escalates to **"Better still"** for the AMD migration.
The verification approach is the lower-risk path that closes the
finding.

---

## §3 — Files touched

```
moodle-enhancement/
├── theme/airpayux/
│   ├── templates/course_full_header.mustache    (+41 lines — Mustache comment block)
│   └── version.php                              (version + release + audit-trail comment)
├── docs/visual-evidence/2026-05-24/wave3-chip-Q/
│   └── README.md                                (this file)
└── PROJECT-STATE.md                             (appended H2 — at end of file)
```

No SCSS, no lang, no PHP behaviour changes. Theme version bump alone
invalidates any cached Mustache template binaries on the next admin
notifications run.

---

## §4 — Safety + parity

- ✅ `php -l moodle-enhancement/theme/airpayux/version.php` clean.
- ✅ Mustache: `coursebannerimage` still emitted via `{{ }}` (double-brace HTML-escape). No `{{{ }}}` triple-brace introduced.
- ✅ Mustache comment block syntax: `{{! ... }}` — does not render to output, will not increase HTML payload.
- ✅ No upstream `coursebannerimage` values changed (per chip scope).
- ✅ `course_drawer_header.mustache` (the sibling template that also consumes `coursebannerimage`) is OUT-OF-SCOPE per the prompt; left untouched.
- ✅ No plugin code touched. No lang file touched. No SCSS file touched.

---

## §5 — Test procedure (manual, for the next deploy)

When the next deploy lands locally, run the following sanity check:

1. Log in as Site administrator.
2. Go to a course; open Course settings → Description → drag in a course image with a filename that contains `'` (e.g. `foo'bar.jpg`).
   *(macOS Finder allows single-quote in filenames; on Windows, rename via WSL.)*
3. Save course settings.
4. View the course as a Learner.
5. Open browser devtools → Elements → inspect the `<div class="courseheader">` `style` attribute. Confirm the inline style reads:

   ```
   background-image: url('https://.../pluginfile.php/.../foo%27bar.jpg');
   ```

   — the apostrophe in the original filename appears as `%27`, not as a
   literal `'`. CSS context is intact.

6. Open the Network panel; confirm the request to the encoded URL returns 200 with the banner image body.

If the URL contains a literal apostrophe (instead of `%27`), upstream
sanitisation has regressed and Step 2 (the AMD migration) becomes
mandatory — re-open this chip.

---

## §6 — Cross-reference

- Audit finding: `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` (§3 F-20, §6 row #20)
- Upstream code: `theme/airpayux/classes/output/traits/course_view.php:74-88`
- Renderer wiring: `theme/airpayux/classes/output/core_renderer.php:937`
- Moodle URL encoding: `lib/classes/url.php:585-601` (slasharg path) and `:298-323` (querystring path)
- Frontend rules: `.claude/rules/frontend.md` — Mustache correctness section
- CLAUDE.md §5 — input/output escaping rules
