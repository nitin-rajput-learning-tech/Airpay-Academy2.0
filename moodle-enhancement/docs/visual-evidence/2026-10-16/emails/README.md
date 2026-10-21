# Visual evidence: e-mail template editor (branch `claude/ui-fix-emails`, merged 2026-10-16 as 3a49e109b)

Local XAMPP (Moodle 5.1, the merged code), desktop 1440 px and mobile 590 px. Captured 2026-10-19 by
`tools/visual-pass/fix_branches_checks.mjs --only emails` as `vp_siteadmin`, on
`/local/sentientia_emails/editor.php?template=compliance/welcome_enrolled`.

**What changed (emails 2026101501)**
1. **The live preview renders on first load.** The iframe's `srcdoc` was written with triple braces, so the first
   double quote of the preview HTML (the XHTML doctype's `PUBLIC "..."`) ended the attribute. About 22 characters
   reached the frame, and the frame was blank. It is now attribute-escaped.
2. **The editor no longer runs 16 px past the viewport.** `.ap-studio` lost its `-1rem` side margins.

**Measured on the page** (`results.json`):

| | 1440 px | 590 px |
|---|---|---|
| `srcdoc` length | 14,835 characters, doctype quotes included | 14,835 |
| Text rendered in the preview frame | 940 characters ("Learning & Development Hi Priya, You have been enrolled...") | 940 |
| `.ap-studio` left / right edge | 0 / 1440 | 0 / 590 |
| Page scroll width | 1440 (no horizontal scroll) | 590 |
| Overflowing elements | 0 | 0 |

Shot: `01-siteadmin-template-editor-first-load` (desktop, mobile). The editor was opened only: nothing was saved or
reverted, and no test mail was sent. No flag is involved.

**Privacy:** "Priya Singh" is the editor's built-in sample person (`classes/email_context.php`), not a real one.

**Known, not part of this fix** (listed in the emails state card under "Left alone, found on the same page", for Nitin
to decide):
- the placeholder sample values are escaped twice: "Product &amp; T..." in the left column;
- the body textarea round-trip (`{{{current_body_raw}}}`), so the Visual tab shows the template's HTML source;
- the Placeholders buttons insert nothing.
