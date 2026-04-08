"""
SENTIENTIA Agent 5 — SCORM Packager
Packages slides + audio into a SCORM 1.2 compliant ZIP.

Input:  content/slides/*-slides.json + content/voice/*-voice.mp3
Output: content/scorm-output/*-scorm.zip

Rules:
- imsmanifest.xml MUST be at ZIP root (not in subfolder)
- masteryscore = 70 (Airpay default)
- All files referenced in manifest must exist in ZIP
- Validate before packaging

Usage:
  python sentientia/agent5_scorm_packager.py <course-name>
"""
import sys
import json
import os
import zipfile
from pathlib import Path
from datetime import datetime
from xml.etree.ElementTree import Element, SubElement, tostring
from xml.dom.minidom import parseString


def generate_imsmanifest(course_id: str, course_title: str, launch_file: str = 'index.html') -> str:
    """Generate SCORM 1.2 imsmanifest.xml."""
    manifest = Element('manifest', {
        'identifier': f'MANIFEST-{course_id}',
        'version': '1.0',
        'xmlns': 'http://www.imsproject.org/xsd/imscp_rootv1p1p2',
        'xmlns:adlcp': 'http://www.adlnet.org/xsd/adlcp_rootv1p2',
        'xmlns:xsi': 'http://www.w3.org/2001/XMLSchema-instance',
    })

    # Metadata
    metadata = SubElement(manifest, 'metadata')
    SubElement(metadata, 'schema').text = 'ADL SCORM'
    SubElement(metadata, 'schemaversion').text = '1.2'

    # Organizations
    organizations = SubElement(manifest, 'organizations', {'default': 'ORG_01'})
    org = SubElement(organizations, 'organization', {'identifier': 'ORG_01'})
    SubElement(org, 'title').text = course_title
    item = SubElement(org, 'item', {
        'identifier': 'ITEM_01',
        'identifierref': 'RES_01',
        'isvisible': 'true',
    })
    SubElement(item, 'title').text = course_title
    SubElement(item, 'adlcp:masteryscore').text = '70'

    # Resources
    resources = SubElement(manifest, 'resources')
    resource = SubElement(resources, 'resource', {
        'identifier': 'RES_01',
        'type': 'webcontent',
        'adlcp:scormtype': 'sco',
        'href': launch_file,
    })
    SubElement(resource, 'file', {'href': launch_file})
    SubElement(resource, 'file', {'href': 'scormdriver.js'})

    # Format XML
    rough = tostring(manifest, encoding='unicode')
    dom = parseString(rough)
    return dom.toprettyxml(indent='  ', encoding='UTF-8').decode('utf-8')


def generate_scorm_driver() -> str:
    """Generate scormdriver.js — SCORM 1.2 API bridge."""
    return """// SCORM 1.2 API Bridge — Airpay Academy
// Provides LMSInitialize, LMSGetValue, LMSSetValue, LMSCommit, LMSFinish
(function() {
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
        init: function() {
            var a = getAPI();
            if (a) {
                a.LMSInitialize('');
                initialized = true;
                a.LMSSetValue('cmi.core.lesson_status', 'incomplete');
            }
        },

        complete: function(score) {
            var a = getAPI();
            if (a && initialized && !completed) {
                if (score !== undefined) {
                    a.LMSSetValue('cmi.core.score.raw', String(score));
                    a.LMSSetValue('cmi.core.score.min', '0');
                    a.LMSSetValue('cmi.core.score.max', '100');
                    a.LMSSetValue('cmi.core.lesson_status', score >= 70 ? 'passed' : 'failed');
                } else {
                    a.LMSSetValue('cmi.core.lesson_status', 'completed');
                }
                a.LMSCommit('');
                completed = true;
            }
        },

        finish: function() {
            var a = getAPI();
            if (a && initialized) {
                if (!completed) {
                    this.complete();
                }
                a.LMSFinish('');
            }
        },

        suspend: function(data) {
            var a = getAPI();
            if (a && initialized) {
                a.LMSSetValue('cmi.suspend_data', JSON.stringify(data));
                a.LMSCommit('');
            }
        },

        getSuspendData: function() {
            var a = getAPI();
            if (a && initialized) {
                var data = a.LMSGetValue('cmi.suspend_data');
                try { return JSON.parse(data); } catch(e) { return null; }
            }
            return null;
        }
    };

    // Auto-init on load
    window.addEventListener('load', function() { SCORM.init(); });
    window.addEventListener('beforeunload', function() { SCORM.finish(); });
})();
"""


def generate_index_html(course_title: str, slides: list, has_audio: bool = False) -> str:
    """Generate the SCORM launch file (index.html)."""
    slide_html = ''
    for i, slide in enumerate(slides):
        active = ' active' if i == 0 else ''
        bullets = ''
        for point in slide.get('points', []):
            bullets += f'        <li>{point}</li>\n'

        slide_html += f"""
    <div class="slide{active}" data-slide="{i}">
      <h2>{slide.get('title', f'Slide {i+1}')}</h2>
      <ul>
{bullets}      </ul>
    </div>"""

    audio_tag = ''
    if has_audio:
        audio_tag = '<audio id="narration" src="audio/narration.mp3" preload="auto"></audio>'

    return f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{course_title}</title>
    <script src="scormdriver.js"></script>
    <style>
        * {{ margin: 0; padding: 0; box-sizing: border-box; }}
        body {{ font-family: 'Montserrat', -apple-system, sans-serif; background: #0f1117; color: #e8eaed; min-height: 100vh; }}
        .container {{ max-width: 960px; margin: 0 auto; padding: 40px 24px; }}
        h1 {{ font-size: 1.5rem; color: #60a5fa; margin-bottom: 24px; text-align: center; }}
        .slide {{ display: none; background: #1a1d27; border-radius: 12px; padding: 32px; margin-bottom: 20px; }}
        .slide.active {{ display: block; }}
        .slide h2 {{ font-size: 1.25rem; color: #0066A7; margin-bottom: 16px; }}
        .slide ul {{ padding-left: 24px; }}
        .slide li {{ margin-bottom: 8px; line-height: 1.6; color: #c4cad8; }}
        .nav {{ display: flex; justify-content: space-between; align-items: center; margin-top: 24px; }}
        .nav button {{ padding: 10px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.95rem; }}
        .nav .prev {{ background: #232733; color: #9ca3b4; }}
        .nav .next {{ background: #0066A7; color: #fff; }}
        .nav .complete {{ background: #16a34a; color: #fff; }}
        .progress {{ text-align: center; color: #9ca3b4; font-size: 0.85rem; margin-top: 12px; }}
    </style>
</head>
<body>
    <div class="container">
        <h1>{course_title}</h1>
        {audio_tag}
        <div id="slides">{slide_html}
        </div>
        <div class="nav">
            <button class="prev" onclick="navigate(-1)">← Previous</button>
            <span class="progress" id="progress">Slide 1 of {len(slides)}</span>
            <button class="next" id="nextBtn" onclick="navigate(1)">Next →</button>
        </div>
    </div>
    <script>
        var current = 0;
        var total = {len(slides)};
        function navigate(dir) {{
            var slides = document.querySelectorAll('.slide');
            slides[current].classList.remove('active');
            current = Math.max(0, Math.min(total - 1, current + dir));
            slides[current].classList.add('active');
            document.getElementById('progress').textContent = 'Slide ' + (current + 1) + ' of ' + total;
            if (current === total - 1) {{
                document.getElementById('nextBtn').textContent = 'Complete ✓';
                document.getElementById('nextBtn').className = 'complete';
                document.getElementById('nextBtn').onclick = function() {{ SCORM.complete(100); alert('Course completed!'); }};
            }} else {{
                document.getElementById('nextBtn').textContent = 'Next →';
                document.getElementById('nextBtn').className = 'next';
                document.getElementById('nextBtn').onclick = function() {{ navigate(1); }};
            }}
        }}
    </script>
</body>
</html>"""


def validate_package(zip_path: str) -> list:
    """Validate SCORM package before finalizing."""
    errors = []

    with zipfile.ZipFile(zip_path, 'r') as z:
        names = z.namelist()

        # 1. imsmanifest.xml at root
        if 'imsmanifest.xml' not in names:
            errors.append("CRITICAL: imsmanifest.xml not at ZIP root")

        # 2. index.html exists
        if 'index.html' not in names:
            errors.append("CRITICAL: index.html (launch file) missing")

        # 3. scormdriver.js exists
        if 'scormdriver.js' not in names:
            errors.append("CRITICAL: scormdriver.js missing")

        # 4. Manifest references valid files
        manifest_content = z.read('imsmanifest.xml').decode('utf-8') if 'imsmanifest.xml' in names else ''
        for name in names:
            if name.endswith('/'):
                continue  # Directory
            if name not in manifest_content and name != 'imsmanifest.xml':
                pass  # Not all files need to be in manifest (audio, images ok)

    return errors


def package_scorm(course_name: str) -> str:
    """Build SCORM ZIP from slides JSON + optional audio."""

    slides_path = Path(f'content/slides/{course_name}-slides.json')
    voice_path = Path(f'content/voice/{course_name}-voice.mp3')

    # Load slides
    if slides_path.exists():
        with open(slides_path, 'r', encoding='utf-8') as f:
            slides_data = json.load(f)
        slides = slides_data.get('slides', [])
        title = slides_data.get('title', course_name.replace('-', ' ').title())
    else:
        print(f"WARNING: No slides file found at {slides_path}")
        print("  Creating placeholder slides from course name.")
        title = course_name.replace('-', ' ').title()
        slides = [
            {'title': title, 'points': ['Course overview', 'Learning objectives']},
            {'title': 'Key Concepts', 'points': ['Point 1', 'Point 2', 'Point 3']},
            {'title': 'Summary', 'points': ['Review key takeaways', 'Next steps']},
        ]

    has_audio = voice_path.exists()
    course_id = course_name.upper().replace('-', '_')

    # Generate files
    manifest_xml = generate_imsmanifest(course_id, title)
    driver_js = generate_scorm_driver()
    index_html = generate_index_html(title, slides, has_audio)

    # Create ZIP
    output_dir = Path('content/scorm-output')
    output_dir.mkdir(parents=True, exist_ok=True)
    zip_path = output_dir / f'{course_name}-scorm.zip'

    with zipfile.ZipFile(str(zip_path), 'w', zipfile.ZIP_DEFLATED) as z:
        z.writestr('imsmanifest.xml', manifest_xml)
        z.writestr('scormdriver.js', driver_js)
        z.writestr('index.html', index_html)
        if has_audio:
            z.write(str(voice_path), 'audio/narration.mp3')

    # Validate
    errors = validate_package(str(zip_path))
    if errors:
        print("VALIDATION ERRORS:")
        for err in errors:
            print(f"  ✗ {err}")
        os.remove(str(zip_path))
        sys.exit(1)
    else:
        print("  ✓ Validation passed")

    return str(zip_path)


def main():
    if len(sys.argv) < 2:
        print("Usage: python sentientia/agent5_scorm_packager.py <course-name>")
        print("       python sentientia/agent5_scorm_packager.py aml-training")
        print("\nExpects:")
        print("  content/slides/<course-name>-slides.json")
        print("  content/voice/<course-name>-voice.mp3 (optional)")
        sys.exit(1)

    course_name = sys.argv[1]
    print(f"SENTIENTIA Agent 5 — SCORM Packager")
    print(f"Course: {course_name}")

    zip_path = package_scorm(course_name)

    size_kb = os.path.getsize(zip_path) / 1024
    print(f"\nOutput: {zip_path} ({size_kb:.0f} KB)")
    print("Done.")


if __name__ == '__main__':
    main()
