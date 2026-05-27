"""Unit tests for ``scripts.agents.agent4_voice_generator``.

The live ElevenLabs path is exercised through an injected fake
``post_fn``; CI runs never POST to the real endpoint. The CLI live-mode
path is verified via the env-var gate (no key set -> exit 3) so no
unintentional spend is possible.
"""

from __future__ import annotations

import os
import subprocess
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Any

import pytest

from scripts.agents import agent4_voice_generator as agent4

REPO_ROOT = Path(__file__).resolve().parents[2]
SCRIPT_PATH = REPO_ROOT / "scripts" / "agents" / "agent4_voice_generator.py"

SAMPLE_NARRATION = (
    "Welcome to Anti Money Laundering SOP. "
    "We will walk through the key checks together.\n\n"
    "Frontline staff watch every transaction over the threshold. "
    "Flag suspicious patterns within one business day.\n\n"
    "That concludes the module. Thank you for participating.\n"
)


# ─── validate_narration_for_voice ────────────────────────────────────


def test_validate_accepts_short_narration() -> None:
    agent4.validate_narration_for_voice(SAMPLE_NARRATION)  # must not raise


def test_validate_rejects_empty() -> None:
    with pytest.raises(ValueError, match="empty"):
        agent4.validate_narration_for_voice("")
    with pytest.raises(ValueError, match="empty"):
        agent4.validate_narration_for_voice("    \n\n   ")


def test_validate_rejects_oversized_narration() -> None:
    too_long = " ".join(["word"] * (agent4.MAX_NARRATION_WORDS + 1))
    with pytest.raises(ValueError, match="too long"):
        agent4.validate_narration_for_voice(too_long)


@pytest.mark.parametrize(
    "tainted",
    [
        "Welcome. Salary: 1234567 INR per month for the trainee.",
        "Welcome. Contact us at staff.member@airpay.in for help.",
        "Welcome. Call the team on +91 98765 43210 immediately.",
        "Welcome. Employee ABCD123456 must complete the form today.",
        "Welcome. Reference SSN 123-45-6789 for the audit record.",
    ],
)
def test_validate_rejects_pii_shaped_tokens(tainted: str) -> None:
    with pytest.raises(ValueError, match="PII"):
        agent4.validate_narration_for_voice(tainted)


def test_estimate_cost_scales_with_length() -> None:
    short = "a" * 1000
    long = "a" * 10000
    assert agent4.estimate_cost_usd(short) == pytest.approx(0.30, rel=1e-3)
    assert agent4.estimate_cost_usd(long) == pytest.approx(3.00, rel=1e-3)


# ─── generate_mock_mp3 ───────────────────────────────────────────────


def test_mock_mp3_starts_with_id3_header() -> None:
    audio = agent4.generate_mock_mp3(SAMPLE_NARRATION)
    assert audio.startswith(agent4.MOCK_MP3_HEADER)


def test_mock_mp3_includes_narration_payload() -> None:
    audio = agent4.generate_mock_mp3("Hello voice over.")
    assert b"SENTIENTIA-MOCK-MP3" in audio
    assert b"Hello voice over." in audio
    assert b"words=" in audio
    assert b"chars=" in audio


def test_mock_mp3_is_deterministic() -> None:
    a = agent4.generate_mock_mp3(SAMPLE_NARRATION)
    b = agent4.generate_mock_mp3(SAMPLE_NARRATION)
    assert a == b


# ─── synthesise_voice (with injected fake post_fn) ───────────────────


@dataclass
class _FakeResponse:
    status_code: int = 200
    content: bytes = b""

    def raise_for_status(self) -> None:
        if self.status_code >= 400:
            raise RuntimeError(f"HTTP {self.status_code}")


def test_synthesise_voice_calls_correct_endpoint_and_headers() -> None:
    captured: dict[str, Any] = {}

    def fake_post(url: str, *, json: dict[str, Any], headers: dict[str, str], timeout: int):
        captured["url"] = url
        captured["json"] = json
        captured["headers"] = headers
        captured["timeout"] = timeout
        return _FakeResponse(content=b"FAKE-MP3-BYTES")

    audio = agent4.synthesise_voice(
        "Hello world.",
        api_key="xi-test",
        voice_id="voice123",
        post_fn=fake_post,
    )
    assert audio == b"FAKE-MP3-BYTES"
    assert captured["url"].endswith("/voice123")
    assert captured["headers"]["xi-api-key"] == "xi-test"
    assert captured["headers"]["Accept"] == "audio/mpeg"
    assert captured["json"]["text"] == "Hello world."
    assert captured["json"]["model_id"] == agent4.DEFAULT_MODEL_ID
    # Voice settings should default to the api.md recommended values.
    settings = captured["json"]["voice_settings"]
    assert settings["stability"] == agent4.DEFAULT_VOICE_SETTINGS["stability"]
    assert settings["use_speaker_boost"] is True


def test_synthesise_voice_merges_custom_voice_settings() -> None:
    captured: dict[str, Any] = {}

    def fake_post(url: str, *, json: dict[str, Any], headers: dict[str, str], timeout: int):
        captured["json"] = json
        return _FakeResponse(content=b"OK")

    agent4.synthesise_voice(
        "Hi.",
        api_key="xi-test",
        voice_id="voice123",
        voice_settings={"stability": 0.9},
        post_fn=fake_post,
    )
    assert captured["json"]["voice_settings"]["stability"] == 0.9
    # Other defaults preserved.
    assert captured["json"]["voice_settings"]["similarity_boost"] == \
        agent4.DEFAULT_VOICE_SETTINGS["similarity_boost"]


def test_synthesise_voice_raises_on_empty_body() -> None:
    def fake_post(*args, **kwargs):  # noqa: ARG001
        return _FakeResponse(content=b"")

    with pytest.raises(ValueError, match="empty audio"):
        agent4.synthesise_voice("Hi.", api_key="x", voice_id="v", post_fn=fake_post)


# ─── CLI behaviour ───────────────────────────────────────────────────


def _write_narration(path: Path, text: str = SAMPLE_NARRATION) -> Path:
    path.write_text(text, encoding="utf-8")
    return path


def test_cli_mock_mode_writes_file(tmp_path: Path) -> None:
    in_path = _write_narration(tmp_path / "narration.txt")
    out_path = tmp_path / "voice.mp3"
    proc = subprocess.run(
        [
            sys.executable, str(SCRIPT_PATH),
            "--input", str(in_path),
            "--output", str(out_path),
        ],
        cwd=str(REPO_ROOT),
        capture_output=True,
        text=True,
        check=False,
    )
    assert proc.returncode == 0, proc.stderr
    assert out_path.exists()
    data = out_path.read_bytes()
    assert data.startswith(agent4.MOCK_MP3_HEADER)
    assert b"SENTIENTIA-MOCK-MP3" in data
    # Mock mode should never claim a live call.
    assert "[CONFIRM]" not in proc.stderr


def test_cli_confirm_without_api_key_exits_three(tmp_path: Path) -> None:
    in_path = _write_narration(tmp_path / "narration.txt")
    out_path = tmp_path / "voice.mp3"
    env = {**os.environ}
    env.pop("ELEVENLABS_API_KEY", None)
    env.pop("ELEVENLABS_VOICE_ID", None)
    proc = subprocess.run(
        [
            sys.executable, str(SCRIPT_PATH),
            "--input", str(in_path),
            "--output", str(out_path),
            "--confirm",
        ],
        cwd=str(REPO_ROOT),
        capture_output=True,
        text=True,
        check=False,
        env=env,
    )
    assert proc.returncode == 3, proc.stdout + proc.stderr
    assert "ELEVENLABS_API_KEY" in proc.stderr
    assert not out_path.exists()


def test_cli_confirm_without_voice_id_exits_three(tmp_path: Path) -> None:
    in_path = _write_narration(tmp_path / "narration.txt")
    out_path = tmp_path / "voice.mp3"
    env = {**os.environ, "ELEVENLABS_API_KEY": "xi-test"}
    env.pop("ELEVENLABS_VOICE_ID", None)
    proc = subprocess.run(
        [
            sys.executable, str(SCRIPT_PATH),
            "--input", str(in_path),
            "--output", str(out_path),
            "--confirm",
        ],
        cwd=str(REPO_ROOT),
        capture_output=True,
        text=True,
        check=False,
        env=env,
    )
    assert proc.returncode == 3, proc.stdout + proc.stderr
    assert "voice id missing" in proc.stderr
    assert not out_path.exists()


def test_cli_validation_error_on_pii(tmp_path: Path) -> None:
    in_path = _write_narration(
        tmp_path / "tainted.txt",
        text="Welcome. Contact us at hr.lead@airpay.in for support.",
    )
    out_path = tmp_path / "voice.mp3"
    proc = subprocess.run(
        [
            sys.executable, str(SCRIPT_PATH),
            "--input", str(in_path),
            "--output", str(out_path),
        ],
        cwd=str(REPO_ROOT),
        capture_output=True,
        text=True,
        check=False,
    )
    assert proc.returncode == 1, proc.stdout + proc.stderr
    assert "PII" in proc.stderr
    assert not out_path.exists()


def test_cli_io_error_on_missing_input(tmp_path: Path) -> None:
    out_path = tmp_path / "voice.mp3"
    proc = subprocess.run(
        [
            sys.executable, str(SCRIPT_PATH),
            "--input", str(tmp_path / "missing.txt"),
            "--output", str(out_path),
        ],
        cwd=str(REPO_ROOT),
        capture_output=True,
        text=True,
        check=False,
    )
    assert proc.returncode == 2, proc.stdout + proc.stderr
    assert "not found" in proc.stderr
    assert not out_path.exists()
