"""Unit tests for ``scripts.agents.agent6_moodle_upload``.

Agent 6 is the only production-mutating agent, so the live HTTP paths are
exercised exclusively through an injected fake ``post_fn`` — the suite
never opens a socket. The CLI ``--confirm`` gate is verified to refuse
without credentials so no unintentional upload is possible.
"""

from __future__ import annotations

import json
import os
import subprocess
import sys
import zipfile
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

import pytest

from scripts.agents import agent6_moodle_upload as agent6

REPO_ROOT = Path(__file__).resolve().parents[2]
SCRIPT_PATH = REPO_ROOT / "scripts" / "agents" / "agent6_moodle_upload.py"


# ─── fixtures ────────────────────────────────────────────────────────


def _make_scorm_zip(path: Path, *, nested: bool = False,
                    omit_manifest: bool = False) -> Path:
    prefix = "wrapper/" if nested else ""
    with zipfile.ZipFile(path, "w") as z:
        if not omit_manifest:
            z.writestr(f"{prefix}imsmanifest.xml", "<manifest/>")
        z.writestr(f"{prefix}index.html", "<html></html>")
        z.writestr(f"{prefix}scormdriver.js", "//")
    return path


@dataclass
class _FakeResponse:
    payload: Any = None
    status_code: int = 200

    def raise_for_status(self) -> None:
        if self.status_code >= 400:
            raise RuntimeError(f"HTTP {self.status_code}")

    def json(self) -> Any:
        return self.payload


# ─── validate_scorm_zip ──────────────────────────────────────────────


def test_validate_passes_well_formed(tmp_path: Path) -> None:
    zp = _make_scorm_zip(tmp_path / "ok.zip")
    assert agent6.validate_scorm_zip(zp) == []


def test_validate_flags_missing_file(tmp_path: Path) -> None:
    assert any("not found" in e
               for e in agent6.validate_scorm_zip(tmp_path / "nope.zip"))


def test_validate_flags_non_zip_suffix(tmp_path: Path) -> None:
    p = tmp_path / "thing.txt"
    p.write_text("x")
    assert any("expected .zip" in e for e in agent6.validate_scorm_zip(p))


def test_validate_flags_nested_manifest(tmp_path: Path) -> None:
    zp = _make_scorm_zip(tmp_path / "nested.zip", nested=True)
    errs = agent6.validate_scorm_zip(zp)
    assert any("root" in e or "nested" in e for e in errs)


def test_validate_flags_missing_manifest(tmp_path: Path) -> None:
    zp = _make_scorm_zip(tmp_path / "nomani.zip", omit_manifest=True)
    assert any("imsmanifest" in e for e in agent6.validate_scorm_zip(zp))


def test_derive_activity_name() -> None:
    assert agent6.derive_activity_name(Path("posh-2024-scorm.zip")) == "Posh 2024"


# ─── call_moodle (injected fake) ─────────────────────────────────────


def test_call_moodle_builds_rest_payload() -> None:
    captured: dict[str, Any] = {}

    def fake_post(url: str, *, data: dict, timeout: int):
        captured["url"] = url
        captured["data"] = data
        return _FakeResponse(payload={"ok": 1})

    out = agent6.call_moodle("some_fn", {"a": 1},
                             base_url="https://m", token="T", post_fn=fake_post)
    assert out == {"ok": 1}
    assert captured["url"] == "https://m" + agent6.REST_PATH
    assert captured["data"]["wstoken"] == "T"
    assert captured["data"]["wsfunction"] == "some_fn"
    assert captured["data"]["moodlewsrestformat"] == "json"
    assert captured["data"]["a"] == 1


def test_call_moodle_raises_on_exception_envelope() -> None:
    def fake_post(url: str, *, data: dict, timeout: int):
        return _FakeResponse(payload={"exception": "invalidtoken",
                                      "message": "Invalid token"})

    with pytest.raises(ValueError, match="Invalid token"):
        agent6.call_moodle("f", {}, base_url="https://m", token="T",
                           post_fn=fake_post)


# ─── upload_to_draft (injected fake) ─────────────────────────────────


def test_upload_to_draft_parses_itemid(tmp_path: Path) -> None:
    zp = _make_scorm_zip(tmp_path / "c.zip")
    captured: dict[str, Any] = {}

    def fake_post(url: str, *, files: dict, data: dict, timeout: int):
        captured["url"] = url
        captured["data"] = data
        return _FakeResponse(payload=[{"itemid": 9981, "filename": "c.zip"}])

    itemid = agent6.upload_to_draft(zp, base_url="https://m", token="T",
                                    post_fn=fake_post)
    assert itemid == 9981
    assert captured["url"] == "https://m" + agent6.UPLOAD_PATH
    assert captured["data"]["token"] == "T"
    assert captured["data"]["filearea"] == "draft"


def test_upload_to_draft_raises_on_error_dict(tmp_path: Path) -> None:
    zp = _make_scorm_zip(tmp_path / "c.zip")

    def fake_post(url: str, *, files: dict, data: dict, timeout: int):
        return _FakeResponse(payload={"error": "token expired"})

    with pytest.raises(ValueError, match="token expired"):
        agent6.upload_to_draft(zp, base_url="https://m", token="T",
                               post_fn=fake_post)


# ─── create_scorm_activity (injected fake) ───────────────────────────


def test_create_scorm_activity_returns_url() -> None:
    def fake_post(url: str, *, data: dict, timeout: int):
        assert data["wsfunction"] == agent6.CREATE_SCORM_WS
        return _FakeResponse(payload={"url": "https://m/mod/scorm/view.php?id=7"})

    url = agent6.create_scorm_activity(
        42, 9981, activity_name="AML", base_url="https://m", token="T",
        post_fn=fake_post)
    assert url == "https://m/mod/scorm/view.php?id=7"


def test_create_scorm_activity_builds_url_from_cmid() -> None:
    def fake_post(url: str, *, data: dict, timeout: int):
        return _FakeResponse(payload={"cmid": 7})

    url = agent6.create_scorm_activity(
        42, 9981, activity_name="AML", base_url="https://m", token="T",
        post_fn=fake_post)
    assert url.endswith("/mod/scorm/view.php?id=7")


# ─── process_one ─────────────────────────────────────────────────────


def test_process_one_mock_makes_no_network_call(tmp_path: Path) -> None:
    zp = _make_scorm_zip(tmp_path / "c.zip")

    def boom(*args, **kwargs):  # noqa: ARG001
        raise AssertionError("mock mode must not POST")

    res = agent6.process_one(zp, 42, confirm=False, stage_only=False,
                             activity_name=None, post_fn=boom)
    assert res.passes
    assert res.mock is True
    assert res.draft_itemid is None


def test_process_one_live_full_path(tmp_path: Path) -> None:
    zp = _make_scorm_zip(tmp_path / "c.zip")

    def fake_post(url: str, **kwargs):
        if url.endswith(agent6.UPLOAD_PATH):
            return _FakeResponse(payload=[{"itemid": 555}])
        return _FakeResponse(payload={"url": "https://m/mod/scorm/view.php?id=3"})

    res = agent6.process_one(zp, 42, confirm=True, stage_only=False,
                             activity_name="AML", base_url="https://m",
                             token="T", post_fn=fake_post)
    assert res.passes, res.errors
    assert res.draft_itemid == 555
    assert res.activity_url.endswith("id=3")


def test_process_one_stage_only_skips_activity(tmp_path: Path) -> None:
    zp = _make_scorm_zip(tmp_path / "c.zip")

    def fake_post(url: str, **kwargs):
        assert url.endswith(agent6.UPLOAD_PATH), "stage-only must not create activity"
        return _FakeResponse(payload=[{"itemid": 555}])

    res = agent6.process_one(zp, 42, confirm=True, stage_only=True,
                             activity_name=None, base_url="https://m",
                             token="T", post_fn=fake_post)
    assert res.passes, res.errors
    assert res.draft_itemid == 555
    assert res.activity_url is None


def test_process_one_validation_failure_blocks_upload(tmp_path: Path) -> None:
    bad = _make_scorm_zip(tmp_path / "bad.zip", nested=True)

    def boom(*args, **kwargs):  # noqa: ARG001
        raise AssertionError("must not upload an invalid package")

    res = agent6.process_one(bad, 42, confirm=True, stage_only=False,
                             activity_name=None, base_url="https://m",
                             token="T", post_fn=boom)
    assert not res.passes
    assert res.errors


# ─── CLI ─────────────────────────────────────────────────────────────


def test_cli_mock_mode_exit_zero(tmp_path: Path) -> None:
    zp = _make_scorm_zip(tmp_path / "c.zip")
    proc = subprocess.run(
        [sys.executable, str(SCRIPT_PATH), str(zp), "--target-course-id", "42"],
        cwd=str(REPO_ROOT), capture_output=True, text=True, check=False,
    )
    assert proc.returncode == 0, proc.stdout + proc.stderr
    assert "[MOCK]" in proc.stdout


def test_cli_confirm_without_creds_exits_three(tmp_path: Path) -> None:
    zp = _make_scorm_zip(tmp_path / "c.zip")
    env = {**os.environ}
    env.pop("MOODLE_URL", None)
    env.pop("MOODLE_TOKEN", None)
    proc = subprocess.run(
        [sys.executable, str(SCRIPT_PATH), str(zp),
         "--target-course-id", "42", "--confirm"],
        cwd=str(REPO_ROOT), capture_output=True, text=True, check=False, env=env,
    )
    assert proc.returncode == 3, proc.stdout + proc.stderr
    assert "MOODLE_URL" in proc.stderr


def test_cli_missing_input_exits_two(tmp_path: Path) -> None:
    proc = subprocess.run(
        [sys.executable, str(SCRIPT_PATH), str(tmp_path / "missing.zip"),
         "--target-course-id", "42"],
        cwd=str(REPO_ROOT), capture_output=True, text=True, check=False,
    )
    assert proc.returncode == 2, proc.stdout + proc.stderr
