"""
SENTIENTIA Agent 5 — SCORM Packager (Phase E / Wave-pipeline)
=============================================================

Stage 5 of the SOP -> SCORM pipeline. Reads the slides JSON emitted by
Agent 3 (``scripts/agents/agent3_slides_generator.py``) and the optional
voice MP3 emitted by Agent 4 (``scripts/agents/agent4_voice_generator.py``)
and produces a **SCORM 1.2 compliant** ``content/scorm-output/<course>-scorm.zip``.

Output ZIP layout (everything at the ZIP ROOT — no wrapper folder):
    imsmanifest.xml      SCORM 1.2 manifest, masteryscore declared
    index.html           launch SCO (slide deck + nav + audio + completion)
    scormdriver.js       SCORM 1.2 API bridge (LMSInitialize/SetValue/...)
    audio/narration.mp3  only when a voice file is present

Architectural contract (matches Agents 1-4):
1. Reads input from disk, writes output to disk, exits. No chaining.
2. **No external API calls** — packaging is entirely local, so there is
   no ``--confirm`` gate here (unlike Agent 4 / Agent 6). The expensive /
   irreversible step is the *upload* (Agent 6), not the packaging.
3. Validation gates BEFORE and AFTER writing:
   - input slides JSON must parse + carry a non-empty ``slides`` array;
   - each slide needs a ``title`` and a bullet list (``bullets``, or the
     legacy ``points`` key);
   - the produced ZIP must pass structural checks (manifest at root,
     launch file present, every ``<file href>`` in the manifest exists)
     or it is deleted so a broken package can never be shipped.

Schema note: Agent 3 emits ``{title, slide_count, slides:[{index, title,
bullets:[...], speaker_notes}]}``. The May-13 prototype used ``points``;
this packager accepts either key (``bullets`` preferred) for back-compat.

CLI
---
::

    # package one course (mock or real audio, whichever is on disk):
    python scripts/agents/agent5_scorm_packager.py SAMPLE-SOP

    # batch + custom mastery score (configurable per customer):
    python scripts/agents/agent5_scorm_packager.py aml-2024 posh-2024 \\
        --mastery-score 80

    # validate inputs only, write nothing:
    python scripts/agents/agent5_scorm_packager.py SAMPLE-SOP --dry-run

    # treat quality warnings (too many bullets, long bullets) as failures:
    python scripts/agents/agent5_scorm_packager.py SAMPLE-SOP --strict

Exit codes: 0 all packaged, 1 one or more failed (validation / I/O).
"""

from __future__ import annotations

import argparse
import io
import json
import re
import sys
import zipfile
from dataclasses import dataclass, field
from pathlib import Path
from xml.dom.minidom import parseString
from xml.etree.ElementTree import Element, SubElement, tostring

# Force UTF-8 stdout/stderr on Windows so the em-dash + check-mark glyphs
# in progress output don't crash the default cp1252 encoder.
if sys.platform == "win32":  # pragma: no cover - platform specific
    try:
        sys.stdout.reconfigure(encoding="utf-8")
        sys.stderr.reconfigure(encoding="utf-8")
    except (AttributeError, OSError):
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8",
                                      errors="replace", line_buffering=True)
        sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding="utf-8",
                                      errors="replace", line_buffering=True)


# ─── Quality benchmarks (CLAUDE.md §3 SENTIENTIA + §8 SCORM) ─────────────
MAX_SLIDES_PER_COURSE = 30
MIN_SLIDES_PER_COURSE = 3
MAX_BULLETS_PER_SLIDE = 5
MAX_WORDS_PER_BULLET = 8
DEFAULT_MASTERY_SCORE = 70           # Airpay default; per-customer override
MAX_ZIP_BYTES = 50 * 1024 * 1024     # 50 MB hard cap
AUDIO_ARCNAME = "audio/narration.mp3"
LAUNCH_FILE = "index.html"
DRIVER_FILE = "scormdriver.js"


# ─── Result type ─────────────────────────────────────────────────────────


@dataclass
class PackageResult:
    course_name: str
    zip_path: Path | None = None
    size_bytes: int = 0
    slide_count: int = 0
    has_audio: bool = False
    errors: list[str] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)

    @property
    def passes(self) -> bool:
        return not self.errors and self.zip_path is not None


# ─── Slide-schema normalisation + validation ─────────────────────────────


def _slide_bullets(slide: dict) -> list:
    """Return a slide's bullet list, accepting either the current
    ``bullets`` key (Agent 3) or the legacy ``points`` key (prototype)."""
    bullets = slide.get("bullets")
    if bullets is None:
        bullets = slide.get("points", [])
    return bullets


def validate_slides_json(data: dict) -> tuple[list[dict], list[str], list[str]]:
    """Validate a slides-JSON dict. Returns (slides, errors, warnings).
    ``errors`` are fatal; ``warnings`` are advisory (fatal under --strict)."""
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
        bullets = _slide_bullets(slide)
        if not isinstance(bullets, list):
            errors.append(f"slide {i}: `bullets` not a list")
            continue
        if len(bullets) > MAX_BULLETS_PER_SLIDE:
            warnings.append(
                f"slide {i}: {len(bullets)} bullets > "
                f"{MAX_BULLETS_PER_SLIDE} recommended"
            )
        for j, bullet in enumerate(bullets):
            if not isinstance(bullet, str):
                errors.append(f"slide {i} bullet {j}: not a string")
                continue
            wc = len(bullet.split())
            if wc > MAX_WORDS_PER_BULLET:
                warnings.append(
                    f"slide {i} bullet {j}: {wc} words > "
                    f"{MAX_WORDS_PER_BULLET} recommended ({bullet[:60]!r})"
                )

    return slides, errors, warnings


# ─── Manifest generation ─────────────────────────────────────────────────


def generate_imsmanifest(course_id: str, course_title: str,
                         file_list: list[str], *,
                         launch_file: str = LAUNCH_FILE,
                         mastery_score: int = DEFAULT_MASTERY_SCORE) -> str:
    """Generate SCORM 1.2 imsmanifest.xml declaring every file in
    ``file_list``. SCORM requires every contained file to be declared;
    anything missing breaks content extraction on stricter LMSes."""
    manifest = Element("manifest", {
        "identifier": f"MANIFEST-{course_id}",
        "version": "1.0",
        "xmlns": "http://www.imsproject.org/xsd/imscp_rootv1p1p2",
        "xmlns:adlcp": "http://www.adlnet.org/xsd/adlcp_rootv1p2",
        "xmlns:xsi": "http://www.w3.org/2001/XMLSchema-instance",
    })

    metadata = SubElement(manifest, "metadata")
    SubElement(metadata, "schema").text = "ADL SCORM"
    SubElement(metadata, "schemaversion").text = "1.2"

    orgs = SubElement(manifest, "organizations", {"default": "ORG_01"})
    org = SubElement(orgs, "organization", {"identifier": "ORG_01"})
    SubElement(org, "title").text = course_title

    item = SubElement(org, "item", {
        "identifier": "ITEM_01",
        "identifierref": "RES_01",
        "isvisible": "true",
    })
    SubElement(item, "title").text = course_title
    SubElement(item, "adlcp:masteryscore").text = str(mastery_score)

    resources = SubElement(manifest, "resources")
    resource = SubElement(resources, "resource", {
        "identifier": "RES_01",
        "type": "webcontent",
        "adlcp:scormtype": "sco",
        "href": launch_file,
    })
    # Sort for stable manifest output (helps regression diffs).
    for fname in sorted(file_list):
        SubElement(resource, "file", {"href": fname})

    rough = tostring(manifest, encoding="unicode")
    return parseString(rough).toprettyxml(indent="  ", encoding="UTF-8").decode("utf-8")


# ─── SCORM 1.2 driver JS ─────────────────────────────────────────────────


def scorm_driver_js(mastery_score: int = DEFAULT_MASTERY_SCORE) -> str:
    """SCORM 1.2 API bridge. ``mastery_score`` sets the pass threshold the
    driver applies when a numeric score is reported."""
    return """\
// SCORM 1.2 API Bridge — Sentientia LMS (Airpay Academy)
// Provides LMSInitialize, LMSGetValue, LMSSetValue, LMSCommit, LMSFinish.
(function () {
    'use strict';

    var api = null;
    var initialized = false;
    var completed = false;
    var MASTERY = %d;

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
                        score >= MASTERY ? 'passed' : 'failed');
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
                if (!completed) { this.complete(); }
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
""" % int(mastery_score)


# ─── index.html generation ───────────────────────────────────────────────


def _escape(s: str) -> str:
    """Minimal HTML escape for slide titles + bullets."""
    return (
        s.replace("&", "&amp;")
         .replace("<", "&lt;")
         .replace(">", "&gt;")
         .replace('"', "&quot;")
    )


def index_html(course_title: str, slides: list[dict], has_audio: bool,
               audio_filename: str = AUDIO_ARCNAME) -> str:
    """Generate the SCORM launch file: a slide deck with prev/next +
    keyboard nav, optional narration audio, and a Complete button that
    reports completion to the LMS via the SCORM driver."""
    slide_html = ""
    for i, slide in enumerate(slides):
        active = " active" if i == 0 else ""
        bullets = "".join(
            f"        <li>{_escape(str(b))}</li>\n"
            for b in _slide_bullets(slide)
        )
        slide_html += (
            f'\n    <div class="slide{active}" data-slide="{i}">\n'
            f'      <h2>{_escape(str(slide.get("title", f"Slide {i+1}")))}</h2>\n'
            f'      <ul>\n{bullets}      </ul>\n'
            f'    </div>'
        )

    audio_tag = (
        f'<audio id="narration" src="{audio_filename}" preload="auto" controls></audio>'
        if has_audio else ""
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
        audio {{ width: 100%; margin-bottom: 20px; }}
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
            <button class="prev" onclick="nav(-1)">&larr; Previous</button>
            <span class="progress" id="progress">Slide 1 of {len(slides)}</span>
            <button class="next" id="nextBtn" onclick="nav(1)">Next &rarr;</button>
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
                btn.textContent = 'Complete \\u2713';
                btn.className = 'complete';
                btn.onclick = function () {{
                    SCORM.complete(100);
                    alert('Course completed!');
                }};
            }} else {{
                btn.textContent = 'Next \\u2192';
                btn.className = 'next';
                btn.onclick = function () {{ nav(1); }};
            }}
        }}
        document.addEventListener('keydown', function (e) {{
            if (e.key === 'ArrowRight') nav(1);
            if (e.key === 'ArrowLeft')  nav(-1);
        }});
    </script>
</body>
</html>
"""


# ─── Output validation ───────────────────────────────────────────────────


def validate_zip(zip_path: Path) -> list[str]:
    """Validate a produced ZIP against SCORM 1.2 + Airpay rules.
    Returns a list of error strings (empty = clean)."""
    errors: list[str] = []

    if not zip_path.exists():
        errors.append(f"ZIP not produced at {zip_path}")
        return errors

    if zip_path.stat().st_size > MAX_ZIP_BYTES:
        errors.append(
            f"ZIP too large: {zip_path.stat().st_size} bytes > {MAX_ZIP_BYTES} cap"
        )

    with zipfile.ZipFile(zip_path, "r") as z:
        names = set(z.namelist())

        # CRITICAL: imsmanifest.xml at ZIP root (not in a wrapper folder).
        if "imsmanifest.xml" not in names:
            errors.append(
                "imsmanifest.xml not at ZIP root (most common SCORM "
                "packaging bug; see CLAUDE.md §8 ZIP creation rule)"
            )
        if any(n.endswith("/imsmanifest.xml") for n in names):
            errors.append(
                "imsmanifest.xml found inside a subfolder — must be at root"
            )
        if LAUNCH_FILE not in names:
            errors.append(f"{LAUNCH_FILE} (launch file) missing")
        if DRIVER_FILE not in names:
            errors.append(f"{DRIVER_FILE} missing")

        # Every <file href="x"/> in the manifest must exist in the ZIP.
        if "imsmanifest.xml" in names:
            manifest_text = z.read("imsmanifest.xml").decode("utf-8")
            for ref in set(re.findall(r'<file\s+href="([^"]+)"', manifest_text)):
                if ref not in names:
                    errors.append(
                        f"manifest references {ref!r} but it is not in the ZIP"
                    )

    return errors


# ─── Packaging ───────────────────────────────────────────────────────────


def package_one(course_name: str, *, dry_run: bool, strict: bool,
                slides_dir: Path, voice_dir: Path, output_dir: Path,
                mastery_score: int = DEFAULT_MASTERY_SCORE) -> PackageResult:
    """Build the SCORM ZIP for one course. Validates inputs, writes the
    ZIP with all entries at root, re-validates the artefact, and deletes
    it if the post-write checks fail (so a broken package is never kept)."""
    result = PackageResult(course_name=course_name)

    slides_path = slides_dir / f"{course_name}-slides.json"
    voice_path = voice_dir / f"{course_name}-voice.mp3"

    if not slides_path.exists():
        result.errors.append(f"slides file not found: {slides_path}")
        return result
    try:
        slides_data = json.loads(slides_path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        result.errors.append(f"slides JSON invalid: {exc}")
        return result

    slides, errs, warns = validate_slides_json(slides_data)
    result.errors.extend(errs)
    result.warnings.extend(warns)
    if errs:
        return result
    if strict and warns:
        result.errors.extend([f"(strict) {w}" for w in warns])
        return result

    title = slides_data.get("title", course_name.replace("-", " ").title())
    course_id = re.sub(r"[^A-Z0-9_]", "_", course_name.upper())
    has_audio = voice_path.exists()
    result.slide_count = len(slides)
    result.has_audio = has_audio

    files_in_zip = [LAUNCH_FILE, DRIVER_FILE]
    if has_audio:
        files_in_zip.append(AUDIO_ARCNAME)

    manifest_xml = generate_imsmanifest(
        course_id, title, files_in_zip, mastery_score=mastery_score)
    driver_js = scorm_driver_js(mastery_score)
    html_doc = index_html(title, slides, has_audio)

    output_dir.mkdir(parents=True, exist_ok=True)
    zip_path = output_dir / f"{course_name}-scorm.zip"

    if dry_run:
        result.zip_path = zip_path  # what WOULD be written
        return result

    try:
        with zipfile.ZipFile(str(zip_path), "w", zipfile.ZIP_DEFLATED) as z:
            # CRITICAL: root-relative arcnames — no wrapper folder.
            z.writestr("imsmanifest.xml", manifest_xml)
            z.writestr(DRIVER_FILE, driver_js)
            z.writestr(LAUNCH_FILE, html_doc)
            if has_audio:
                z.write(str(voice_path), AUDIO_ARCNAME)
    except OSError as exc:
        result.errors.append(f"ZIP write failed: {exc}")
        return result

    val_errs = validate_zip(zip_path)
    if val_errs:
        result.errors.extend(val_errs)
        try:
            zip_path.unlink()  # roll back the bad ZIP
        except OSError:
            pass
        return result

    result.zip_path = zip_path
    result.size_bytes = zip_path.stat().st_size
    return result


# ─── CLI ─────────────────────────────────────────────────────────────────


def _build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="agent5_scorm_packager",
        description="SENTIENTIA Agent 5 — package slides + voice into a "
                    "SCORM 1.2 ZIP. No external API; packaging is local.",
    )
    parser.add_argument(
        "courses", nargs="+",
        help="One or more course names (e.g. SAMPLE-SOP). Expects "
             "content/slides/<course>-slides.json and optionally "
             "content/voice/<course>-voice.mp3.",
    )
    parser.add_argument("--slides-dir", default="content/slides", type=Path,
                        help="Slides JSON dir (default: %(default)s)")
    parser.add_argument("--voice-dir", default="content/voice", type=Path,
                        help="Voice MP3 dir (default: %(default)s)")
    parser.add_argument("--output-dir", default="content/scorm-output", type=Path,
                        help="SCORM ZIP output dir (default: %(default)s)")
    parser.add_argument("--mastery-score", type=int, default=DEFAULT_MASTERY_SCORE,
                        help="Pass threshold written to the manifest + driver "
                             "(default: %(default)s; configurable per customer).")
    parser.add_argument("--dry-run", action="store_true",
                        help="Validate inputs + report what would be packaged; write nothing.")
    parser.add_argument("--strict", action="store_true",
                        help="Treat quality warnings as errors.")
    return parser


def main(argv: list[str] | None = None) -> int:
    args = _build_parser().parse_args(argv)

    if not (1 <= args.mastery_score <= 100):
        print(f"agent5: --mastery-score must be 1-100 (got {args.mastery_score})",
              file=sys.stderr)
        return 1

    print("SENTIENTIA Agent 5 — SCORM Packager")
    print(f"  slides={args.slides_dir} voice={args.voice_dir} "
          f"out={args.output_dir} mastery={args.mastery_score} "
          f"strict={args.strict} dry_run={args.dry_run}")

    succeeded = failed = 0
    for course_name in args.courses:
        print(f"\n-- {course_name} --")
        result = package_one(
            course_name, dry_run=args.dry_run, strict=args.strict,
            slides_dir=args.slides_dir, voice_dir=args.voice_dir,
            output_dir=args.output_dir, mastery_score=args.mastery_score,
        )
        for w in result.warnings:
            print(f"  WARN: {w}")
        for e in result.errors:
            print(f"  ERR:  {e}")
        if result.passes:
            if args.dry_run:
                print(f"  [DRY RUN] would write {result.zip_path} "
                      f"({result.slide_count} slides, "
                      f"audio={'yes' if result.has_audio else 'no'})")
            else:
                print(f"  OK {result.zip_path} ({result.size_bytes/1024:.0f} KB, "
                      f"{result.slide_count} slides, "
                      f"audio={'yes' if result.has_audio else 'no'})")
            succeeded += 1
        else:
            print("  FAIL packaging failed")
            failed += 1

    print(f"\n-- Summary: {succeeded} packaged, {failed} failed --")
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
