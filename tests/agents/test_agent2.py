"""Unit tests for ``scripts.agents.agent2_narration_generator``.

These tests are hermetic — no network call is made. Live-mode (Anthropic)
paths are exercised through an injected fake ``post_fn`` so the test
suite remains safe to run in CI and on contributors' laptops without an
API key.
"""

from __future__ import annotations

import json
import subprocess
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Any

import pytest

from scripts.agents import agent2_narration_generator as agent2

REPO_ROOT = Path(__file__).resolve().parents[2]
SCRIPT_PATH = REPO_ROOT / "scripts" / "agents" / "agent2_narration_generator.py"


# ─── Fixtures ─────────────────────────────────────────────────────────


def _sample_parsed_dict() -> dict[str, Any]:
    return {
        "title": "Anti-Money Laundering SOP",
        "headings": [
            {"level": 1, "text": "Anti-Money Laundering SOP"},
            {"level": 2, "text": "Overview"},
            {"level": 2, "text": "Reporting"},
        ],
        "paragraphs": [
            "This SOP explains how Airpay frontline staff identify and "
            "report suspicious financial activity to the compliance team.",
            "Anti money laundering rules require us to monitor every "
            "transaction above the threshold and flag unusual patterns.",
            "Staff must escalate any flagged transaction to a manager "
            "within one business day of detection.",
        ],
        "lists": [
            {
                "type": "ordered",
                "items": [
                    "Verify the customer identity document.",
                    "Match the address against the record.",
                    "Record the transaction in the journal.",
                ],
            },
            {
                "type": "unordered",
                "items": [
                    "Never share customer data outside the system.",
                    "Always log suspicious activity within the hour.",
                ],
            },
        ],
        "word_count": 120,
        "source_file": "AML.pdf",
        "parsed_at": "2026-05-25T00:00:00Z",
    }


@pytest.fixture()
def parsed_dict() -> dict[str, Any]:
    return _sample_parsed_dict()


@pytest.fixture()
def parsed_file(tmp_path: Path, parsed_dict: dict[str, Any]) -> Path:
    path = tmp_path / "input-parsed.json"
    path.write_text(json.dumps(parsed_dict, indent=2), encoding="utf-8")
    return path


# ─── validate_parsed_input ───────────────────────────────────────────


def test_validate_parsed_accepts_well_formed_dict(parsed_dict: dict[str, Any]) -> None:
    agent2.validate_parsed_input(parsed_dict)  # must not raise


@pytest.mark.parametrize(
    "missing_field",
    ["title", "headings", "paragraphs", "lists", "word_count", "source_file", "parsed_at"],
)
def test_validate_parsed_rejects_missing_field(
    parsed_dict: dict[str, Any], missing_field: str,
) -> None:
    del parsed_dict[missing_field]
    with pytest.raises(ValueError, match="missing required fields"):
        agent2.validate_parsed_input(parsed_dict)


def test_validate_parsed_rejects_non_dict() -> None:
    with pytest.raises(ValueError):
        agent2.validate_parsed_input(["not", "a", "dict"])


def test_validate_parsed_rejects_unknown_list_type(parsed_dict: dict[str, Any]) -> None:
    parsed_dict["lists"][0]["type"] = "checked"
    with pytest.raises(ValueError, match="unknown list type"):
        agent2.validate_parsed_input(parsed_dict)


# ─── generate_mock_narration ─────────────────────────────────────────


def test_mock_narration_has_required_structure(parsed_dict: dict[str, Any]) -> None:
    text = agent2.generate_mock_narration(parsed_dict)

    assert text.endswith("\n")
    assert "Welcome to" in text
    assert "Thank you" in text
    # The title appears in the opening line.
    assert "Anti-Money Laundering SOP" in text


def test_mock_narration_passes_pipeline_validation(parsed_dict: dict[str, Any]) -> None:
    text = agent2.generate_mock_narration(parsed_dict)
    stats = agent2.validate_narration(text)
    assert 0 < stats.word_count <= agent2.MAX_WORDS
    assert stats.longest_sentence_words <= agent2.MAX_WORDS_PER_SENTENCE
    assert stats.paragraph_count >= 2


def test_mock_narration_is_deterministic(parsed_dict: dict[str, Any]) -> None:
    a = agent2.generate_mock_narration(parsed_dict)
    b = agent2.generate_mock_narration(parsed_dict)
    assert a == b


def test_mock_narration_renders_list_items(parsed_dict: dict[str, Any]) -> None:
    text = agent2.generate_mock_narration(parsed_dict)
    assert "Step 1." in text
    assert "Verify the customer identity document" in text
    assert "Never share customer data outside the system" in text


def test_mock_narration_splits_long_sentences(tmp_path: Path) -> None:
    long_sentence = " ".join(["word"] * 80) + "."
    parsed = {
        "title": "Long Sentence SOP",
        "headings": [],
        "paragraphs": [long_sentence],
        "lists": [],
        "word_count": 80,
        "source_file": "long.pdf",
        "parsed_at": "2026-05-25T00:00:00Z",
    }
    text = agent2.generate_mock_narration(parsed)
    stats = agent2.validate_narration(text)
    assert stats.longest_sentence_words <= agent2.MAX_WORDS_PER_SENTENCE


def test_mock_narration_strips_html(tmp_path: Path) -> None:
    parsed = {
        "title": "HTML <script>alert(1)</script> SOP",
        "headings": [{"level": 1, "text": "Main <b>Section</b>"}],
        "paragraphs": ["Body <em>with html</em> markup throughout."],
        "lists": [],
        "word_count": 12,
        "source_file": "html.pdf",
        "parsed_at": "2026-05-25T00:00:00Z",
    }
    text = agent2.generate_mock_narration(parsed)
    assert "<" not in text and ">" not in text
    agent2.validate_narration(text)  # must pass


# ─── validate_narration ──────────────────────────────────────────────


def test_validate_narration_rejects_empty() -> None:
    with pytest.raises(ValueError):
        agent2.validate_narration("")
    with pytest.raises(ValueError):
        agent2.validate_narration("   \n\n   ")


def test_validate_narration_rejects_long_sentence() -> None:
    sentence = " ".join(["word"] * (agent2.MAX_WORDS_PER_SENTENCE + 1)) + "."
    text = f"Welcome. {sentence}"
    with pytest.raises(ValueError, match="sentence longer than"):
        agent2.validate_narration(text)


def test_validate_narration_rejects_over_2000_words() -> None:
    sentences = [
        " ".join(["word"] * 20) + "." for _ in range(120)
    ]
    text = "\n\n".join(" ".join(sentences[i:i + 4]) for i in range(0, len(sentences), 4))
    with pytest.raises(ValueError, match="exceeds 2000-word cap"):
        agent2.validate_narration(text)


def test_validate_narration_rejects_html() -> None:
    with pytest.raises(ValueError, match="HTML tags"):
        agent2.validate_narration("Welcome. <p>Hello</p>.")


def test_validate_narration_rejects_markdown_heading() -> None:
    with pytest.raises(ValueError, match="markdown marker"):
        agent2.validate_narration("# Heading\n\nBody sentence here.")


def test_narration_stats_reports_correctly() -> None:
    text = "First sentence here. Second one.\n\nNew paragraph now."
    stats = agent2.narration_stats(text)
    assert stats.paragraph_count == 2
    assert stats.sentence_count == 3
    # 3 + 2 + 3 = 8 words across the three sentences.
    assert stats.word_count == 8
    assert stats.longest_sentence_words == 3


# ─── build_anthropic_prompt ──────────────────────────────────────────


def test_build_anthropic_prompt_embeds_input(parsed_dict: dict[str, Any]) -> None:
    prompt = agent2.build_anthropic_prompt(parsed_dict)
    assert "25 words" in prompt
    assert "2000 words" in prompt
    assert "Anti-Money Laundering SOP" in prompt
    # The original JSON must be reachable so Claude can read it verbatim.
    assert json.dumps(parsed_dict, indent=2, ensure_ascii=False) in prompt


# ─── call_anthropic (with injected fake post_fn) ─────────────────────


@dataclass
class _FakeResponse:
    status_code: int = 200
    payload: dict[str, Any] | None = None

    def raise_for_status(self) -> None:
        if self.status_code >= 400:
            raise RuntimeError(f"HTTP {self.status_code}")

    def json(self) -> dict[str, Any]:
        return self.payload or {}


def test_call_anthropic_parses_text_blocks() -> None:
    captured: dict[str, Any] = {}

    def fake_post(url: str, *, json: dict[str, Any], headers: dict[str, str], timeout: int):
        captured["url"] = url
        captured["json"] = json
        captured["headers"] = headers
        captured["timeout"] = timeout
        return _FakeResponse(payload={
            "content": [{"type": "text", "text": "Hello world. Short clear sentence."}],
        })

    text = agent2.call_anthropic(
        "prompt body",
        api_key="sk-test",
        model="claude-opus-4-7",
        post_fn=fake_post,
    )
    assert text == "Hello world. Short clear sentence."
    assert captured["url"] == agent2.ANTHROPIC_ENDPOINT
    assert captured["headers"]["x-api-key"] == "sk-test"
    assert captured["headers"]["anthropic-version"] == agent2.ANTHROPIC_API_VERSION
    assert captured["json"]["model"] == "claude-opus-4-7"
    assert captured["json"]["messages"][0]["content"] == "prompt body"


def test_call_anthropic_raises_on_missing_text() -> None:
    def fake_post(*args, **kwargs):  # noqa: ARG001
        return _FakeResponse(payload={"content": []})

    with pytest.raises(ValueError, match="missing text content"):
        agent2.call_anthropic("prompt", api_key="sk-test", post_fn=fake_post)


def test_call_anthropic_concatenates_multiple_blocks() -> None:
    def fake_post(*args, **kwargs):  # noqa: ARG001
        return _FakeResponse(payload={
            "content": [
                {"type": "text", "text": "First block."},
                {"type": "text", "text": "Second block."},
            ],
        })

    text = agent2.call_anthropic("prompt", api_key="sk-test", post_fn=fake_post)
    assert "First block." in text
    assert "Second block." in text


# ─── CLI behaviour ───────────────────────────────────────────────────


def test_cli_mock_mode_writes_narration(tmp_path: Path, parsed_file: Path) -> None:
    out = tmp_path / "narration.txt"
    proc = subprocess.run(
        [sys.executable, str(SCRIPT_PATH), "--input", str(parsed_file), "--output", str(out)],
        cwd=str(REPO_ROOT),
        capture_output=True,
        text=True,
        check=False,
    )
    assert proc.returncode == 0, proc.stderr
    assert out.exists()
    text = out.read_text(encoding="utf-8")
    assert "Welcome to" in text
    # No network call should have left fingerprints in stderr.
    assert "[CONFIRM]" not in proc.stderr


def test_cli_rejects_confirm_without_api_key(
    tmp_path: Path, parsed_file: Path, monkeypatch: pytest.MonkeyPatch,
) -> None:
    monkeypatch.delenv("ANTHROPIC_API_KEY", raising=False)
    out = tmp_path / "narration.txt"
    proc = subprocess.run(
        [
            sys.executable, str(SCRIPT_PATH),
            "--input", str(parsed_file),
            "--output", str(out),
            "--confirm",
        ],
        cwd=str(REPO_ROOT),
        capture_output=True,
        text=True,
        check=False,
        env={**__import__("os").environ, "ANTHROPIC_API_KEY": ""},
    )
    assert proc.returncode == 3, proc.stdout + proc.stderr
    assert "ANTHROPIC_API_KEY" in proc.stderr
    assert not out.exists()


def test_cli_io_error_on_missing_input(tmp_path: Path) -> None:
    out = tmp_path / "out.txt"
    proc = subprocess.run(
        [
            sys.executable, str(SCRIPT_PATH),
            "--input", str(tmp_path / "nope.json"),
            "--output", str(out),
        ],
        cwd=str(REPO_ROOT),
        capture_output=True,
        text=True,
        check=False,
    )
    assert proc.returncode == 2, proc.stdout + proc.stderr
    assert "not found" in proc.stderr
    assert not out.exists()


def test_cli_validation_error_on_bad_json(tmp_path: Path) -> None:
    bad = tmp_path / "broken.json"
    bad.write_text("{not valid json", encoding="utf-8")
    out = tmp_path / "out.txt"
    proc = subprocess.run(
        [
            sys.executable, str(SCRIPT_PATH),
            "--input", str(bad),
            "--output", str(out),
        ],
        cwd=str(REPO_ROOT),
        capture_output=True,
        text=True,
        check=False,
    )
    assert proc.returncode == 1, proc.stdout + proc.stderr
    assert "validation error" in proc.stderr
    assert not out.exists()


def test_cli_validation_error_on_schema_drift(tmp_path: Path) -> None:
    bad = tmp_path / "schema-drift.json"
    bad.write_text(json.dumps({"title": "x"}), encoding="utf-8")
    out = tmp_path / "out.txt"
    proc = subprocess.run(
        [
            sys.executable, str(SCRIPT_PATH),
            "--input", str(bad),
            "--output", str(out),
        ],
        cwd=str(REPO_ROOT),
        capture_output=True,
        text=True,
        check=False,
    )
    assert proc.returncode == 1, proc.stdout + proc.stderr
    assert "missing required fields" in proc.stderr
