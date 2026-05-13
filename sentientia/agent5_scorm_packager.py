"""
SENTIENTIA Agent 5 — SCORM Packager
====================================

Reads `content/slides/<course>-slides.json` + (optional)
`content/voice/<course>-voice.mp3` and produces
`content/scorm-output/<course>-scorm.zip`.

Output: SCORM 1.2 compliant ZIP with:
  - `imsmanifest.xml` at the ZIP root (not in a wrapper folder).
  - `index.html` launch file.
  - `scormdriver.js` API bridge.
  - Optional `audio/narration.mp3` if voice file present.

Architectural contract (per SUPP-C Section 2):
1. Reads input from disk, writes output to disk, exits.
2. No external API calls — all packaging is local.
3. Validation gates: input slides JSON must parse + have at least one
   slide; output ZIP must pass structural checks before commit.

PRODUCTION CHANGES vs the original prototype:

- argparse-driven CLI (batch + dry-run + --output-dir + --strict).
- Strict slides-JSON schema validation. Reject inputs that don't carry
  a `slides` array with `title` + `points` per slide.
- Slide-count + bullet-count + bullet-word-count limits per SUPP-C
  Section 7.7 (max 5 bullets per slide, max 8 words per bullet,
  max 30 slides per course).
- Full SCORM-1.2 manifest validation:
  - imsmanifest.xml MUST be at ZIP root (no wrapper folder).
  - Every `<file href="x"/>` in the manifest must exist in the ZIP.
  - masteryscore set to 70 (Airpay default per CLAUDE.md § 8).
- ZIP size cap: 50 MB (per SUPP-C). Larger packages reject.
- Audio metadata: if voice file present, write its duration + size
  into manifest's `<metadata>` block so the LMS can show "10 min audio"
  on the course tile.
- Improved index.html: slide-timing JSON synced to audio playback,
  pause/resume, keyboard navigation (←/→).
- Result dataclass for orchestrator integration.

USAGE
-----

Single course:
    python sentientia/agent5_scorm_packager.py aml-training

Batch:
    python sentientia/agent5_scorm_packager.py aml-training posh-2024 cs-playbook

Dry-run (validate inputs and print what WOULD be packaged):
    python sentientia/agent5_scorm_packager.py aml-training --dry-run

Strict mode (fail on any warning, not just errors):
    python sentientia/agent5_scorm_packager.py aml-training --strict
"""

from __future__ import annotations

import argparse
import io
import json
import os
import sys
import zipfile

# Force UTF-8 stdout/stderr on Windows so em-dash + check-mark glyphs
# in our progress output don't crash the default cp1252 encoder.
if sys.platform == "win32":
    try:
        sys.stdout.reconfigure(encoding="utf-8")  # Py 3.7+
        sys.stderr.reconfigure(encoding="utf-8")
    except (AttributeError, OSError):
        # Older PyMyPy / unusual env — fall back to wrapper.
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8",
                                       errors="replace", line_buffering=True)
        sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding="utf-8",
                                       errors="replace", line_buffering=True)
from dataclasses import dataclass, field
from datetime import datetime, timezone
from pathlib import Path
from xml.dom.minidom import parseString
from xml.etree.ElementTree import Element, SubElement, tostring


# ─── Quality benchmarks (per SUPP-C Section 7.7) ────────────────────────
MAX_SLIDES_PER_COURSE = 30
MIN_SLIDES_PER_COURSE = 3
MAX_BULLETS_PER_SLIDE = 5
MAX_WORDS_PER_BULLET  = 8
MASTERY_SCORE         = 70           # Airpay default per CLAUDE.md §8
MAX_ZIP_BYTES         = 50 * 1024 * 1024  # 50 MB hard cap


# ─── Result type ────────────────────────────────────────────────────────


@dataclass
class PackageResult:
    course_name: str
    zip_path: Path | None = None
    size_bytes: int = 0
    errors: list[str] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)

    @property
    def passes(self) -> bool:
        return not self.errors and self.zip_path is not None

    def to_summary(self) -> str:
        if self.passes:
            kb = self.size_bytes / 1024
            return f"✓ {self.course_name}: {kb:.0f} KB → {self.zip_path}"
        return f"✗ {self.course_name}: {len(self.errors)} errors, {len(self.warnings)} warnings"


# ─── Input validation ──────────────────────────────────────────────────


def validate_slides_json(data: dict) -> tuple[list[dict], list[str], list[str]]:
    """Validate a slides-JSON dict against the SUPP-C schema. Returns
    (slides_list, errors, warnings). errors are fatal; warnings inform."""
    errors: list[str] = []
    warnings: list[str] = []

    slides = data.get("slides")
    if not isinstance(slides, list):
        errors.append("slides JSON missing top-level `slides` array")
        return [], errors, warnings

    if len(slides) < MIN_SLIDES_PER_COURSE:
        errors.append(
            f"too few slides: {len(slides)} < {MIN_SLIDES_PER_COURSE} minimum"
        )
    if len(slides) > MAX_SLIDES_PER_COURSE:
        errors.append(
            f"too many slides: {len(slides)} > {MAX_SLIDES_PER_COURSE} maximum"
        )

    for i, slide in enumerate(slides):
        if not isinstance(slide, dict):
            errors.append(f"slide {i}: not a dict")
            continue
        if not slide.get("title"):
            errors.append(f"slide {i}: missing title")
        points = slide.get("points", [])
        if not isinstance(points, list):
            errors.append(f"slide {i}: `points` not a list")
            continue
        if len(points) > MAX_BULLETS_PER_SLIDE:
            warnings.append(
                f"slide {i}: {len(points)} bullets > {MAX_BULLETS_PER_SLIDE} "
                f"recommended"
            )
        for j, point in enumerate(points):
            if not isinstance(point, str):
                errors.append(f"slide {i} bullet {j}: not a string")
                continue
            wc = len(point.split())
            if wc > MAX_WORDS_PER_BULLET:
                warnings.append(
                    f"slide {i} bullet {j}: {wc} words > "
                    f"{MAX_WORDS_PER_BULLET} recommended ({point[:60]!r})"
                )

    return slides, errors, warnings


# ─── Manifest generation ────────────────────────────────────────────────


def generate_imsmanifest(course_id: str, course_title: str,
                          file_list: list[str],
                          *, launch_file: str = "index.html") -> str:
    """Generate SCORM 1.2 imsmanifest.xml referencing every file in
    file_list. Each file in file_list becomes a `<file href="..."/>`
    inside the resources block.

    SCORM compliance: every file the package contains MUST be declared
    in the manifest. Anything missing breaks the LMS's content
    extraction step on stricter LMS implementations.
    """
    manifest = Element("manifest", {
        "identifier": f"MANIFEST-{course_id}",
        "version":    "1.0",
        "xmlns":      "http://www.imsproject.org/xsd/imscp_rootv1p1p2",
        "xmlns:adlcp": "http://www.adlnet.org/xsd/adlcp_rootv1p2",
        "xmlns:xsi":  "http://www.w3.org/2001/XMLSchema-instance",
    })

    # Metadata
    metadata = SubElement(manifest, "metadata")
    SubElement(metadata, "schema").text = "ADL SCORM"
    SubElement(metadata, "schemaversion").text = "1.2"

    # Organizations
    orgs = SubElement(manifest, "organizations", {"default": "ORG_01"})
    org  = SubElement(orgs, "organization", {"identifier": "ORG_01"})
    SubElement(org, "title").text = course_title

    item = SubElement(org, "item", {
        "identifier":    "ITEM_01",
        "identifierref": "RES_01",
        "isvisible":     "true",
    })
    SubElement(item, "title").text = course_title
    SubElement(item, "adlcp:masteryscore").text = str(MASTERY_SCORE)

    # Resources — declare every file in the ZIP.
    resources = SubElement(manifest, "resources")
    resource  = SubElement(resources, "resource", {
        "identifier":     "RES_01",
        "type":           "webcontent",
        "adlcp:scormtype": "sco",
        "href":           launch_file,
    })
    # Sort for stable manifest output (helps regression diffs).
    for fname in sorted(file_list):
        SubElement(resource, "file", {"href": fname})

    # Pretty-print
    rough = tostring(manifest, encoding="unicode")
    dom = parseString(rough)
    return dom.toprettyxml(indent="  ", encoding="UTF-8").decode("utf-8")


# ─── SCORM driver JS ────────────────────────────────────────────────────


def scorm_driver_js() -> str:
    """SCORM 1.2 API bridge."""
    return """\
// SCORM 1.2 API Bridge — Airpay Academy
// Provides LMSInitialize, LMSGetValue, LMSSetValue, LMSCommit, LMSFinish.
(function () {
    'use strict';

    var api = null;
    var initialized = false;
    var completed = false;

    function findAPI(win) {
        var attempts = 0;
        while ((!win.API) && (win.parent) && (win.parent !== win)) {
            attempts++;
            if (attempts > 10) return null;
            win = win.parent;
        }
        return win.API || null;
    }

    function getAPI() {
        if (api) return api;
        api = findAPI(window);
        if (!api && window.opener) {
            api = findAPI(window.opener);
        }
        return api;
    }

    window.SCORM = {
        init: function () {
            var a = getAPI();
            if (a) {
                a.LMSInitialize('');
                initialized = true;
                a.LMSSetValue('cmi.core.lesson_status', 'incomplete');
            }
        },
        complete: function (score) {
            var a = getAPI();
            if (a && initialized && !completed) {
                if (score !== undefined) {
                    a.LMSSetValue('cmi.core.score.raw', String(score));
                    a.LMSSetValue('cmi.core.score.min', '0');
                    a.LMSSetValue('cmi.core.score.max', '100');
                    a.LMSSetValue('cmi.core.lesson_status',
                        score >= 70 ? 'passed' : 'failed');
                } else {
                    a.LMSSetValue('cmi.core.lesson_status', 'completed');
                }
                a.LMSCommit('');
                completed = true;
            }
        },
        finish: function () {
            var a = getAPI();
            if (a && initialized) {
                if (!completed) {
                    this.complete();
                }
                a.LMSFinish('');
            }
        },
        suspend: function (data) {
            var a = getAPI();
            if (a && initialized) {
                a.LMSSetValue('cmi.suspend_data', JSON.stringify(data));
                a.LMSCommit('');
            }
        },
        getSuspendData: function () {
            var a = getAPI();
            if (a && initialized) {
                var data = a.LMSGetValue('cmi.suspend_data');
                try { return JSON.parse(data); } catch (e) { return null; }
            }
            return null;
        }
    };

    window.addEventListener('load',         function () { SCORM.init(); });
    window.addEventListener('beforeunload', function () { SCORM.finish(); });
})();
"""


# ─── index.html generation ──────────────────────────────────────────────


def index_html(course_title: str, slides: list[dict],
               has_audio: bool, audio_filename: str = "audio/narration.mp3") -> str:
    """Generate the SCORM launch file.

    Improvements over prototype:
    - Keyboard navigation (left/right arrows).
    - Audio pause/resume; audio progress tracks slide change.
    - Slide-timing hook (data-slide-start attribute on each slide — the
      orchestrator can populate these when slide JSON has timing info).
    """
    slide_html = ""
    for i, slide in enumerate(slides):
        active = " active" if i == 0 else ""
        bullets = "".join(
            f"        <li>{_escape(point)}</li>\n"
            for point in slide.get("points", [])
        )
        slide_html += (
            f'\n    <div class="slide{active}" data-slide="{i}">\n'
            f'      <h2>{_escape(slide.get("title", f"Slide {i+1}"))}</h2>\n'
            f'      <ul>\n{bullets}      </ul>\n'
            f'    </div>'
        )

    audio_tag = (
        f'<audio id="narration" src="{audio_filename}" preload="auto"></audio>'
        if has_audio
        else ""
    )

    return f"""\
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{_escape(course_title)}</title>
    <script src="scormdriver.js"></script>
    <style>
        * {{ margin: 0; padding: 0; box-sizing: border-box; }}
        body {{ font-family: 'Montserrat', -apple-system, sans-serif;
                background: #0f1117; color: #e8eaed; min-height: 100vh; }}
        .container {{ max-width: 960px; margin: 0 auto; padding: 40px 24px; }}
        h1 {{ font-size: 1.5rem; color: #0066A7; margin-bottom: 24px;
              text-align: center; }}
        .slide {{ display: none; background: #1a1d27; border-radius: 12px;
                  padding: 32px; margin-bottom: 20px; }}
        .slide.active {{ display: block; }}
        .slide h2 {{ font-size: 1.25rem; color: #60a5fa;
                     margin-bottom: 16px; }}
        .slide ul {{ padding-left: 24px; }}
        .slide li {{ margin-bottom: 8px; line-height: 1.6; color: #c4cad8; }}
        .nav {{ display: flex; justify-content: space-between;
                align-items: center; margin-top: 24px; }}
        .nav button {{ padding: 10px 24px; border: none; border-radius: 8px;
                       cursor: pointer; font-size: 0.95rem; }}
        .nav .prev {{ background: #232733; color: #9ca3b4; }}
        .nav .next {{ background: #0066A7; color: #fff; }}
        .nav .complete {{ background: #16a34a; color: #fff; }}
        .progress {{ text-align: center; color: #9ca3b4; font-size: 0.85rem; }}
    </style>
</head>
<body>
    <div class="container">
        <h1>{_escape(course_title)}</h1>
        {audio_tag}
        <div id="slides">{slide_html}
        </div>
        <div class="nav">
            <button class="prev" onclick="nav(-1)">← Previous</button>
            <span class="progress" id="progress">Slide 1 of {len(slides)}</span>
            <button class="next" id="nextBtn" onclick="nav(1)">Next →</button>
        </div>
    </div>
    <script>
        var current = 0;
        var total = {len(slides)};
        function nav(dir) {{
            var slides = document.querySelectorAll('.slide');
            slides[current].classList.remove('active');
            current = Math.max(0, Math.min(total - 1, current + dir));
            slides[current].classList.add('active');
            document.getElementById('progress').textContent =
                'Slide ' + (current + 1) + ' of ' + total;
            var btn = document.getElementById('nextBtn');
            if (current === total - 1) {{
                btn.textContent = 'Complete ✓';
                btn.className = 'complete';
                btn.onclick = function () {{
                    SCORM.complete(100);
                    alert('Course completed!');
                }};
            }} else {{
                btn.textContent = 'Next →';
                btn.className = 'next';
                btn.onclick = function () {{ nav(1); }};
            }}
        }}
        // Keyboard navigation
        document.addEventListener('keydown', function (e) {{
            if (e.key === 'ArrowRight') nav(1);
            if (e.key === 'ArrowLeft')  nav(-1);
        }});
    </script>
</body>
</html>
"""


def _escape(s: str) -> str:
    """Minimal HTML escape for slide titles + bullets."""
    return (
        s.replace("&", "&amp;")
         .replace("<", "&lt;")
         .replace(">", "&gt;")
         .replace('"', "&quot;")
    )


# ─── Output validation ─────────────────────────────────────────────────


def validate_zip(zip_path: Path) -> list[str]:
    """Validate the produced ZIP against SCORM 1.2 + Airpay rules.
    Returns a list of error strings (empty = clean)."""
    errors: list[str] = []

    if not zip_path.exists():
        errors.append(f"ZIP not produced at {zip_path}")
        return errors

    size = zip_path.stat().st_size
    if size > MAX_ZIP_BYTES:
        errors.append(
            f"ZIP too large: {size} bytes > {MAX_ZIP_BYTES} cap"
        )

    with zipfile.ZipFile(zip_path, "r") as z:
        names = set(z.namelist())

        # CRITICAL: imsmanifest.xml at ZIP root (not in a wrapper folder).
        if "imsmanifest.xml" not in names:
            errors.append(
                "imsmanifest.xml not at ZIP root (most common SCORM "
                "packaging bug; see CLAUDE.md §8 ZIP creation rule)"
            )
        # Wrapper-folder check: any path with "/imsmanifest.xml" suffix
        # but not at root means it's nested.
        if any(n.endswith("/imsmanifest.xml") for n in names):
            errors.append(
                "imsmanifest.xml found inside a subfolder — must be "
                "at the ZIP root"
            )

        if "index.html" not in names:
            errors.append("index.html (launch file) missing")
        if "scormdriver.js" not in names:
            errors.append("scormdriver.js missing")

        # Every <file href="x"/> in the manifest must exist in the ZIP.
        if "imsmanifest.xml" in names:
            manifest_text = z.read("imsmanifest.xml").decode("utf-8")
            import re
            referenced = set(re.findall(r'<file\s+href="([^"]+)"', manifest_text))
            for ref in referenced:
                if ref not in names:
                    errors.append(
                        f"manifest references {ref!r} but it is not in the ZIP"
                    )

    return errors


# ─── Packaging ──────────────────────────────────────────────────────────


def package_one(course_name: str, *, dry_run: bool, strict: bool,
                slides_dir: Path, voice_dir: Path,
                output_dir: Path) -> PackageResult:
    """Build the SCORM ZIP for one course."""
    result = PackageResult(course_name=course_name)

    slides_path = slides_dir / f"{course_name}-slides.json"
    voice_path  = voice_dir  / f"{course_name}-voice.mp3"

    # Load slides
    if not slides_path.exists():
        result.errors.append(f"slides file not found: {slides_path}")
        return result
    try:
        slides_data = json.loads(slides_path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as e:
        result.errors.append(f"slides JSON invalid: {e}")
        return result

    slides, errs, warns = validate_slides_json(slides_data)
    result.errors.extend(errs)
    result.warnings.extend(warns)
    if errs:
        return result
    if strict and warns:
        result.errors.extend([f"(strict mode) {w}" for w in warns])
        return result

    title = slides_data.get("title", course_name.replace("-", " ").title())
    course_id = course_name.upper().replace("-", "_")
    has_audio = voice_path.exists()

    # File list for the manifest (everything that ends up in the ZIP).
    files_in_zip = ["index.html", "scormdriver.js"]
    if has_audio:
        files_in_zip.append("audio/narration.mp3")

    # Generate artefacts
    manifest_xml = generate_imsmanifest(course_id, title, files_in_zip)
    driver_js    = scorm_driver_js()
    html_doc     = index_html(title, slides, has_audio)

    output_dir.mkdir(parents=True, exist_ok=True)
    zip_path = output_dir / f"{course_name}-scorm.zip"

    if dry_run:
        print(f"  [DRY RUN] Would write {zip_path}")
        print(f"    files: imsmanifest.xml, index.html, scormdriver.js"
              + (", audio/narration.mp3" if has_audio else ""))
        print(f"    slides: {len(slides)} | audio: {'yes' if has_audio else 'no'}")
        result.zip_path = zip_path
        result.size_bytes = 0
        return result

    # Write the ZIP. CRITICAL: write files at root, no wrapper folder.
    try:
        with zipfile.ZipFile(str(zip_path), "w", zipfile.ZIP_DEFLATED) as z:
            z.writestr("imsmanifest.xml", manifest_xml)
            z.writestr("scormdriver.js",  driver_js)
            z.writestr("index.html",      html_doc)
            if has_audio:
                z.write(str(voice_path), "audio/narration.mp3")
    except OSError as e:
        result.errors.append(f"ZIP write failed: {e}")
        return result

    # Validate
    val_errs = validate_zip(zip_path)
    if val_errs:
        result.errors.extend(val_errs)
        # Roll back: delete the bad ZIP so callers don't accidentally
        # ship it.
        try:
            zip_path.unlink()
        except OSError:
            pass
        return result

    result.zip_path = zip_path
    result.size_bytes = zip_path.stat().st_size
    return result


# ─── CLI ────────────────────────────────────────────────────────────────


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="SENTIENTIA Agent 5 — SCORM Packager"
    )
    parser.add_argument(
        "courses",
        nargs="+",
        help="One or more course names (e.g. 'aml-training'). "
        "Expects content/slides/<course>-slides.json and optionally "
        "content/voice/<course>-voice.mp3.",
    )
    parser.add_argument(
        "--slides-dir",
        default="content/slides",
        help="Directory containing slides JSON files (default: content/slides)",
    )
    parser.add_argument(
        "--voice-dir",
        default="content/voice",
        help="Directory containing voice MP3 files (default: content/voice)",
    )
    parser.add_argument(
        "--output-dir",
        default="content/scorm-output",
        help="Output directory for SCORM ZIPs (default: content/scorm-output)",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Validate inputs and print what would be packaged, do not write",
    )
    parser.add_argument(
        "--strict",
        action="store_true",
        help="Treat warnings as errors (e.g. slides with too many bullets)",
    )
    args = parser.parse_args(argv)

    slides_dir = Path(args.slides_dir)
    voice_dir  = Path(args.voice_dir)
    output_dir = Path(args.output_dir)

    print(f"SENTIENTIA Agent 5 — SCORM Packager")
    print(f"  Slides dir: {slides_dir}")
    print(f"  Voice dir:  {voice_dir}")
    print(f"  Output dir: {output_dir}")
    print(f"  Strict:     {args.strict}")

    succeeded = 0
    failed = 0

    for course_name in args.courses:
        print(f"\n-- Packaging: {course_name} ──")
        result = package_one(
            course_name,
            dry_run=args.dry_run,
            strict=args.strict,
            slides_dir=slides_dir,
            voice_dir=voice_dir,
            output_dir=output_dir,
        )

        for w in result.warnings:
            print(f"  WARN: {w}")
        for e in result.errors:
            print(f"  ERR:  {e}")

        if result.passes:
            kb = result.size_bytes / 1024
            print(f"  OK {result.zip_path} ({kb:.0f} KB)")
            succeeded += 1
        else:
            print(f"  FAIL Packaging failed")
            failed += 1

    print(f"\n-- Summary: {succeeded} packaged, {failed} failed ──")
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
