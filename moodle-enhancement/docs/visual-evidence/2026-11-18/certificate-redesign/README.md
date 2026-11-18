# Course completion certificate: redesign (2026-11-18)

The 'UAT Course Completion' certificate (tool_certificate) looked broken in the demo. The title was cut off at the left edge, the lines were scattered and left-aligned, and the page had no frame, no logo and no course name.

**Cause.** `tools/uat/provision_test_users.php` created every text element with refpoint 1 (top centre) and `posx=20`. tool_certificate reads `posx` as the centre of the line when the refpoint is top centre. Every line was therefore centred on x = 20 mm, so the title ran off the page.

**Fix.** `tools/uat/certificate_template_redesign.php` rebuilds the template with tool_certificate's own API. The new layout is an A4 landscape page in the Brand Book 2026-06 style, made of 13 elements:

- **Frame:** a background SVG with a blue-tint margin, a #0066A7 frame inset 8 mm with an inner hairline, corner accents and an orange divider.
- **Header:** the airpay academy logo at top centre, then the title.
- **Body:** the learner's name and the course name.
- **Footer:** the date and the certificate code, each with a label, a QR code, and a verification line.

Every element is centred correctly.

Issued PDFs are stored files. `--regenerate-issues` rebuilds them and fills in the course name where it was empty.

## Files

| File | What it shows |
|---|---|
| `before-issued-certificate.png` | A stored issue PDF made with the old template, the same elements the provisioning script creates on UAT |
| `after-issued-certificate.png` | The same stored issue after `--apply --regenerate-issues`. The course name was filled in from the issue's course |
| `after-two-line-course-name.png` | A sample (`--sample-pdf`) with a long course name that wraps to two centred lines |

All three were rendered for the test persona `vp_learner1` ("VP Learner /1") on local XAMPP (Moodle 5.1.3+). The QR code and the verification line point to `http://localhost:8080`. On UAT they show UAT's own site address.

## How it was tested (local only)

1. A local 'UAT Course Completion' template was created with the provisioning script's own element calls. One issue for `vp_learner1` was written directly, with no events and no messages, and its PDF was stored. Result: `emailed` = 0.
2. The script ran as a dry run, then with `--apply --regenerate-issues`. A second `--apply` reported "Nothing to change", so the run is idempotent.
3. The stored issue file was read back and rendered to a PNG.
4. Cleanup: the test template, its one issue, and the `coursefullname` issue field were removed. The local site went back to its earlier state: 9 templates, 11,048 issues and 2,446 issue files.

Nothing was deployed to UAT. Running the script on UAT is Nitin's call.
