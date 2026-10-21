"""Unit tests for ``scripts.agents.agent5_scorm_packager``.

Agent 5 makes no network calls, so everything is exercised directly:
pure functions (validation, manifest, driver, html) plus end-to-end
``package_one`` runs against tmp_path that assert the produced ZIP is a
valid SCORM 1.2 package (manifest at root, every href resolves).
"""

from __future__ import annotations

import json
import subprocess
import sys
import zipfile
from pathlib import Path

import pytest

from scripts.agents import agent5_scorm_packager as agent5

REPO_ROOT = Path(__file__).resolve().parents[2]
SCRIPT_PATH = REPO_ROOT / "scripts" / "agents" / "agent5_scorm_packager.py"


def _slides(n: int = 4) -> dict:
    return {
        "title": "AML Training",
        "slide_count": n,
        "slides": [
            {"index": i + 1, "title": f"Section {i+1}",
             "bullets": [f"Point {i+1}a", f"Point {i+1}b"],
             "speaker_notes": "notes"}
            for i in range(n)
        ],
    }


# ─── validate_slides_json ────────────────────────────────────────────


def test_validate_accepts_bullets_schema() -> None:
    slides, errs, warns = agent5.validate_slides_json(_slides())
    assert errs == []
    assert len(slides) == 4


def test_validate_accepts_legacy_points_schema() -> None:
    data = {"title": "X", "slides": [
        {"title": f"S{i}", "points": ["a", "b"]} for i in range(3)]}
    slides, errs, warns = agent5.validate_slides_json(data)
    assert errs == []
    assert agent5._slide_bullets(slides[0]) == ["a", "b"]


def test_validate_rejects_missing_slides_array() -> None:
    _, errs, _ = agent5.validate_slides_json({"title": "X"})
    assert any("slides" in e for e in errs)


def test_validate_rejects_too_few_slides() -> None:
    _, errs, _ = agent5.validate_slides_json(_slides(1))
    assert any("too few" in e for e in errs)


def test_validate_rejects_too_many_slides() -> None:
    _, errs, _ = agent5.validate_slides_json(_slides(agent5.MAX_SLIDES_PER_COURSE + 1))
    assert any("too many" in e for e in errs)


def test_validate_warns_on_excess_bullets() -> None:
    data = {"title": "X", "slides": [
        {"title": "S1", "bullets": ["a", "b", "c", "d", "e", "f"]},
        {"title": "S2", "bullets": ["x"]},
        {"title": "S3", "bullets": ["y"]},
    ]}
    _, errs, warns = agent5.validate_slides_json(data)
    assert errs == []
    assert any("bullets >" in w for w in warns)


def test_validate_warns_on_long_bullet() -> None:
    long_bullet = "one two three four five six seven eight nine ten"
    data = {"title": "X", "slides": [
        {"title": "S1", "bullets": [long_bullet]},
        {"title": "S2", "bullets": ["x"]},
        {"title": "S3", "bullets": ["y"]},
    ]}
    _, errs, warns = agent5.validate_slides_json(data)
    assert any("words >" in w for w in warns)


# ─── manifest / driver / html ────────────────────────────────────────


def test_manifest_declares_mastery_and_files() -> None:
    xml = agent5.generate_imsmanifest(
        "AML", "AML Training", ["index.html", "scormdriver.js"], mastery_score=80)
    assert "<adlcp:masteryscore>80</adlcp:masteryscore>" in xml
    assert 'href="index.html"' in xml
    assert 'href="scormdriver.js"' in xml
    assert 'default="ORG_01"' in xml
    assert "ADL SCORM" in xml


def test_driver_injects_mastery_threshold() -> None:
    js = agent5.scorm_driver_js(80)
    assert "var MASTERY = 80;" in js
    assert "LMSInitialize" in js
    assert "score >= MASTERY" in js


def test_index_html_renders_bullets_and_count() -> None:
    html = agent5.index_html("AML Training", _slides()["slides"], has_audio=False)
    assert "AML Training" in html
    assert "Slide 1 of 4" in html
    assert "<li>Point 1a</li>" in html
    assert "<audio" not in html  # no audio tag when has_audio is False


def test_index_html_includes_audio_when_present() -> None:
    html = agent5.index_html("X", _slides()["slides"], has_audio=True)
    assert '<audio id="narration"' in html
    assert agent5.AUDIO_ARCNAME in html


def test_escape_neutralises_markup() -> None:
    assert agent5._escape('<b>"x"</b> & y') == "&lt;b&gt;&quot;x&quot;&lt;/b&gt; &amp; y"


# ─── validate_zip ────────────────────────────────────────────────────


def _write_zip(path: Path, members: dict[str, str]) -> Path:
    with zipfile.ZipFile(path, "w") as z:
        for name, content in members.items():
            z.writestr(name, content)
    return path


def test_validate_zip_passes_well_formed(tmp_path: Path) -> None:
    manifest = agent5.generate_imsmanifest(
        "X", "X", ["index.html", "scormdriver.js"])
    zp = _write_zip(tmp_path / "ok.zip", {
        "imsmanifest.xml": manifest,
        "index.html": "<html></html>",
        "scormdriver.js": "//",
    })
    assert agent5.validate_zip(zp) == []


def test_validate_zip_flags_nested_manifest(tmp_path: Path) -> None:
    zp = _write_zip(tmp_path / "bad.zip", {
        "wrapper/imsmanifest.xml": "<manifest/>",
        "wrapper/index.html": "x",
        "wrapper/scormdriver.js": "x",
    })
    errs = agent5.validate_zip(zp)
    assert any("root" in e for e in errs)


def test_validate_zip_flags_missing_referenced_file(tmp_path: Path) -> None:
    manifest = agent5.generate_imsmanifest(
        "X", "X", ["index.html", "scormdriver.js", "audio/narration.mp3"])
    zp = _write_zip(tmp_path / "bad.zip", {
        "imsmanifest.xml": manifest,
        "index.html": "x",
        "scormdriver.js": "x",
        # audio/narration.mp3 deliberately omitted
    })
    errs = agent5.validate_zip(zp)
    assert any("narration.mp3" in e for e in errs)


# ─── package_one end-to-end ──────────────────────────────────────────


def _setup_dirs(tmp_path: Path, course: str = "demo",
                with_audio: bool = False) -> tuple[Path, Path, Path]:
    slides_dir = tmp_path / "slides"
    voice_dir = tmp_path / "voice"
    out_dir = tmp_path / "out"
    slides_dir.mkdir()
    voice_dir.mkdir()
    (slides_dir / f"{course}-slides.json").write_text(
        json.dumps(_slides()), encoding="utf-8")
    if with_audio:
        (voice_dir / f"{course}-voice.mp3").write_bytes(b"ID3\x04\x00mock")
    return slides_dir, voice_dir, out_dir


def test_package_one_writes_valid_scorm_no_audio(tmp_path: Path) -> None:
    slides_dir, voice_dir, out_dir = _setup_dirs(tmp_path)
    res = agent5.package_one("demo", dry_run=False, strict=False,
                             slides_dir=slides_dir, voice_dir=voice_dir,
                             output_dir=out_dir)
    assert res.passes, res.errors
    assert res.zip_path.exists()
    with zipfile.ZipFile(res.zip_path) as z:
        names = set(z.namelist())
    assert "imsmanifest.xml" in names
    assert "index.html" in names
    assert "scormdriver.js" in names
    assert "audio/narration.mp3" not in names
    assert res.has_audio is False


def test_package_one_includes_audio_when_present(tmp_path: Path) -> None:
    slides_dir, voice_dir, out_dir = _setup_dirs(tmp_path, with_audio=True)
    res = agent5.package_one("demo", dry_run=False, strict=False,
                             slides_dir=slides_dir, voice_dir=voice_dir,
                             output_dir=out_dir)
    assert res.passes, res.errors
    with zipfile.ZipFile(res.zip_path) as z:
        names = set(z.namelist())
        manifest = z.read("imsmanifest.xml").decode()
    assert agent5.AUDIO_ARCNAME in names
    assert agent5.AUDIO_ARCNAME in manifest
    assert res.has_audio is True


def test_package_one_dry_run_writes_nothing(tmp_path: Path) -> None:
    slides_dir, voice_dir, out_dir = _setup_dirs(tmp_path)
    res = agent5.package_one("demo", dry_run=True, strict=False,
                             slides_dir=slides_dir, voice_dir=voice_dir,
                             output_dir=out_dir)
    assert res.passes  # dry-run still "passes" (zip_path set as planned)
    assert not res.zip_path.exists()


def test_package_one_strict_promotes_warning_to_error(tmp_path: Path) -> None:
    slides_dir = tmp_path / "slides"
    slides_dir.mkdir()
    (slides_dir / "demo-slides.json").write_text(json.dumps({
        "title": "X", "slides": [
            {"title": "S1", "bullets": ["a", "b", "c", "d", "e", "f"]},
            {"title": "S2", "bullets": ["x"]},
            {"title": "S3", "bullets": ["y"]},
        ]}), encoding="utf-8")
    res = agent5.package_one("demo", dry_run=False, strict=True,
                             slides_dir=slides_dir, voice_dir=tmp_path / "v",
                             output_dir=tmp_path / "o")
    assert not res.passes
    assert any("strict" in e for e in res.errors)


def test_package_one_missing_slides_file(tmp_path: Path) -> None:
    res = agent5.package_one("nope", dry_run=False, strict=False,
                             slides_dir=tmp_path, voice_dir=tmp_path,
                             output_dir=tmp_path / "o")
    assert not res.passes
    assert any("not found" in e for e in res.errors)


# ─── CLI (subprocess; thin smoke) ────────────────────────────────────


def test_cli_packages_sample(tmp_path: Path) -> None:
    slides_dir, voice_dir, out_dir = _setup_dirs(tmp_path)
    proc = subprocess.run(
        [sys.executable, str(SCRIPT_PATH), "demo",
         "--slides-dir", str(slides_dir), "--voice-dir", str(voice_dir),
         "--output-dir", str(out_dir)],
        cwd=str(REPO_ROOT), capture_output=True, text=True, check=False,
    )
    assert proc.returncode == 0, proc.stdout + proc.stderr
    assert (out_dir / "demo-scorm.zip").exists()


def test_cli_rejects_bad_mastery(tmp_path: Path) -> None:
    proc = subprocess.run(
        [sys.executable, str(SCRIPT_PATH), "demo", "--mastery-score", "150"],
        cwd=str(REPO_ROOT), capture_output=True, text=True, check=False,
    )
    assert proc.returncode == 1
    assert "mastery-score" in proc.stderr


# ─── Runtime behaviour (2026-10-22): resume, no completion on close ──────


def test_driver_finish_never_completes_the_course() -> None:
    js = agent5.scorm_driver_js()
    finish = js[js.index("finish: function"):js.index("suspend: function")]
    assert "complete(" not in finish, "closing the page must not mark the course completed"
    assert "'cmi.core.exit', 'suspend'" in finish
    assert "cmi.core.session_time" in finish


def test_driver_listens_to_pagehide_and_visibility() -> None:
    js = agent5.scorm_driver_js()
    assert "addEventListener('pagehide'" in js
    assert "visibilitychange" in js


def test_index_html_resumes_and_records_location() -> None:
    html = agent5.index_html("T", _slides(3)["slides"], has_audio=False)
    assert "SCORM.getLocation()" in html and "SCORM.setLocation(current)" in html
    assert "alert(" not in html, "completion is announced in the page, not by a blocking alert"
    assert 'role="status"' in html


def test_index_html_language_and_direction() -> None:
    assert '<html lang="en" dir="ltr">' in agent5.index_html("T", _slides(3)["slides"], has_audio=False)
    assert '<html lang="hi" dir="ltr">' in agent5.index_html("T", _slides(3)["slides"], False, language="hi")
    assert '<html lang="ar" dir="rtl">' in agent5.index_html("T", _slides(3)["slides"], False, language="ar")
    # Anything but letters, digits and hyphens is dropped from the tag.
    assert '<html lang="enx" dir="ltr">' in agent5.index_html("T", _slides(3)["slides"], False, language='en"><x')


_NODE_HARNESS = r"""
const vm = require('vm');
const src = require('fs').readFileSync(process.argv[2], 'utf8');
function run(initial) {
  const store = Object.assign({}, initial);
  const calls = [];
  const api = {
    LMSInitialize: () => { calls.push('init'); return 'true'; },
    LMSGetValue: k => (k in store ? store[k] : ''),
    LMSSetValue: (k, v) => { store[k] = v; return 'true'; },
    LMSCommit: () => { calls.push('commit'); return 'true'; },
    LMSFinish: () => { calls.push('finish'); return 'true'; },
  };
  const listeners = {};
  // In a browser `window` IS the global object, so the driver's bare `SCORM` resolves: mirror that.
  const win = { API: api, addEventListener: (t, f) => { listeners[t] = f; }, console: { warn() {} } };
  win.parent = win;
  win.window = win;
  win.document = { addEventListener: (t, f) => { listeners['doc:' + t] = f; }, visibilityState: 'visible' };
  vm.createContext(win);
  vm.runInContext(src, win);
  return { store, calls, fire: t => listeners[t] && listeners[t](), SCORM: win.SCORM };
}
const out = {};
// 1. First launch, two moves, close the page.
let r = run({ 'cmi.core.lesson_status': 'not attempted' });
r.fire('load'); r.SCORM.setLocation(2); r.fire('pagehide'); r.fire('beforeunload');
out.closed = { status: r.store['cmi.core.lesson_status'], exit: r.store['cmi.core.exit'],
  location: r.store['cmi.core.lesson_location'], finishes: r.calls.filter(c => c === 'finish').length,
  time: /^\d{4}:\d{2}:\d{2}\.\d{2}$/.test(r.store['cmi.core.session_time'] || '') };
// 2. Relaunch of a passed attempt: the status stays passed.
r = run({ 'cmi.core.lesson_status': 'passed', 'cmi.core.lesson_location': '3' });
r.fire('load');
out.relaunch = { status: r.store['cmi.core.lesson_status'], location: r.SCORM.getLocation() };
// 3. Complete with a score at mastery.
r = run({ 'cmi.core.lesson_status': 'incomplete' });
r.fire('load'); r.SCORM.complete(100); r.fire('pagehide');
out.done = { status: r.store['cmi.core.lesson_status'], exit: r.store['cmi.core.exit'], raw: r.store['cmi.core.score.raw'] };
// 4. suspend_data over the SCORM 1.2 limit is refused.
r = run({}); r.fire('load');
out.big = r.SCORM.suspend({ x: 'y'.repeat(5000) });
console.log(JSON.stringify(out));
"""


def test_driver_runtime_in_node(tmp_path: Path) -> None:
    import shutil
    node = shutil.which("node")
    if not node:
        pytest.skip("node not available")
    drv = tmp_path / "scormdriver.js"
    drv.write_text(agent5.scorm_driver_js(80), encoding="utf-8")
    harness = tmp_path / "harness.js"
    harness.write_text(_NODE_HARNESS, encoding="utf-8")
    proc = subprocess.run([node, str(harness), str(drv)], capture_output=True, text=True, check=False,
                          stdin=subprocess.DEVNULL)
    assert proc.returncode == 0, proc.stderr
    out = json.loads(proc.stdout.strip().splitlines()[-1])
    # Closing an unfinished course suspends it: not completed, place kept, finished exactly once.
    assert out["closed"] == {"status": "incomplete", "exit": "suspend", "location": "2", "finishes": 1, "time": True}
    # A relaunch of a passed attempt is not downgraded, and the place is readable.
    assert out["relaunch"] == {"status": "passed", "location": 3}
    # Completion at or above mastery passes and exits normally.
    assert out["done"] == {"status": "passed", "exit": "", "raw": "100"}
    assert out["big"] is False
