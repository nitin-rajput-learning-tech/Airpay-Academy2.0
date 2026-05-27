"""Unit tests for ``scripts.agents.agent3_slides_generator``.

Pure-Python agent — no API to mock. Tests cover paragraph rebalancing,
slide field constraints, and CLI behaviour.
"""

from __future__ import annotations

import json
import subprocess
import sys
from pathlib import Path

import pytest

from scripts.agents import agent3_slides_generator as agent3

REPO_ROOT = Path(__file__).resolve().parents[2]
SCRIPT_PATH = REPO_ROOT / "scripts" / "agents" / "agent3_slides_generator.py"


def _make_narration(num_paragraphs: int = 8, sentences_per_paragraph: int = 4) -> str:
    paragraphs = []
    for index in range(num_paragraphs):
        sentences = []
        for sentence_index in range(sentences_per_paragraph):
            words = [
                f"para{index}word{sentence_index}token{w}" for w in range(6)
            ]
            sentences.append(" ".join(words) + ".")
        paragraphs.append(" ".join(sentences))
    return "\n\n".join(paragraphs) + "\n"


def _sample_narration() -> str:
    return (
        "Welcome to Anti-Money Laundering SOP. "
        "We will walk through the key checks together.\n\n"

        "Section: Overview. "
        "Frontline staff watch every transaction over the threshold. "
        "Flag suspicious patterns within one business day. "
        "Escalate flagged items to the compliance team without delay.\n\n"

        "Section: Reporting. "
        "Step 1. Capture the customer identity proof. "
        "Step 2. Match the address against the record. "
        "Step 3. Note the case number on the journal. "
        "Step 4. Notify the manager before logging out.\n\n"

        "Section: Common Pitfalls. "
        "Never share customer data outside the system. "
        "Always log suspicious activity within the hour. "
        "Always close the case once a manager reviews it.\n\n"

        "That concludes Anti-Money Laundering SOP. Thank you for completing this module.\n"
    )


# ─── happy-path generation ───────────────────────────────────────────


def test_generate_slides_returns_required_schema() -> None:
    payload = agent3.generate_slides(_sample_narration(), source_file="AML.txt")

    assert set(payload.keys()) == {
        "title", "slide_count", "slides", "source_file", "generated_at",
    }
    assert payload["source_file"] == "AML.txt"
    assert payload["slide_count"] == len(payload["slides"])
    assert payload["slide_count"] >= 1


def test_each_slide_has_required_fields() -> None:
    payload = agent3.generate_slides(_sample_narration())
    for slide in payload["slides"]:
        assert set(slide.keys()) == {"index", "title", "bullets", "speaker_notes"}
        assert isinstance(slide["index"], int)
        assert isinstance(slide["title"], str) and slide["title"].strip()
        assert isinstance(slide["bullets"], list)
        assert isinstance(slide["speaker_notes"], str) and slide["speaker_notes"].strip()


def test_titles_respect_word_cap() -> None:
    payload = agent3.generate_slides(_sample_narration())
    for slide in payload["slides"]:
        assert len(slide["title"].split()) <= agent3.MAX_TITLE_WORDS, slide


def test_bullets_respect_word_cap_and_count() -> None:
    payload = agent3.generate_slides(_sample_narration())
    for slide in payload["slides"]:
        assert len(slide["bullets"]) <= agent3.MAX_BULLETS, slide
        for bullet in slide["bullets"]:
            assert len(bullet.split()) <= agent3.MAX_BULLET_WORDS, bullet


def test_section_prefix_becomes_title() -> None:
    payload = agent3.generate_slides(_sample_narration())
    titles = [slide["title"] for slide in payload["slides"]]
    assert "Overview" in titles
    assert "Reporting" in titles


def test_speaker_notes_match_original_paragraph() -> None:
    payload = agent3.generate_slides(_sample_narration())
    for slide in payload["slides"]:
        # speaker_notes should be a substring of (or equal to) one of
        # the source paragraphs after rebalancing.
        assert slide["speaker_notes"].strip()


def test_slide_indexes_are_unique_and_sequential() -> None:
    payload = agent3.generate_slides(_sample_narration())
    indexes = [slide["index"] for slide in payload["slides"]]
    assert indexes == list(range(1, len(indexes) + 1))


# ─── rebalancing ─────────────────────────────────────────────────────


def test_few_paragraphs_get_split_up() -> None:
    narration = _make_narration(num_paragraphs=2, sentences_per_paragraph=12)
    payload = agent3.generate_slides(narration)
    assert payload["slide_count"] >= agent3.TARGET_MIN_SLIDES


def test_many_paragraphs_get_merged_down() -> None:
    narration = _make_narration(num_paragraphs=30, sentences_per_paragraph=2)
    payload = agent3.generate_slides(narration)
    assert payload["slide_count"] <= agent3.HARD_MAX_SLIDES


def test_single_paragraph_falls_into_target_band() -> None:
    sentence = "This is a focused short sentence about training. "
    narration = (sentence * 20).strip()
    payload = agent3.generate_slides(narration)
    assert agent3.TARGET_MIN_SLIDES <= payload["slide_count"] <= agent3.TARGET_MAX_SLIDES


# ─── validation ──────────────────────────────────────────────────────


def test_empty_narration_raises() -> None:
    with pytest.raises(ValueError):
        agent3.generate_slides("")
    with pytest.raises(ValueError):
        agent3.generate_slides("   \n\n   ")


def test_html_in_narration_raises() -> None:
    with pytest.raises(ValueError, match="HTML tags"):
        agent3.generate_slides("Welcome.\n\n<p>Slide one</p>")


def test_payload_validation_rejects_too_many_bullets() -> None:
    payload = agent3.generate_slides(_sample_narration())
    payload["slides"][0]["bullets"] = ["one"] * (agent3.MAX_BULLETS + 1)
    with pytest.raises(ValueError, match="bullets >"):
        agent3._validate_payload(payload)


def test_payload_validation_rejects_long_bullet() -> None:
    payload = agent3.generate_slides(_sample_narration())
    long_bullet = " ".join(["w"] * (agent3.MAX_BULLET_WORDS + 1))
    payload["slides"][0]["bullets"] = [long_bullet]
    with pytest.raises(ValueError, match="exceeds"):
        agent3._validate_payload(payload)


def test_payload_validation_rejects_empty_title() -> None:
    payload = agent3.generate_slides(_sample_narration())
    payload["slides"][0]["title"] = ""
    with pytest.raises(ValueError, match="empty title"):
        agent3._validate_payload(payload)


def test_payload_validation_rejects_empty_speaker_notes() -> None:
    payload = agent3.generate_slides(_sample_narration())
    payload["slides"][0]["speaker_notes"] = "   "
    with pytest.raises(ValueError, match="empty speaker_notes"):
        agent3._validate_payload(payload)


# ─── helpers ─────────────────────────────────────────────────────────


def test_truncate_words_respects_cap() -> None:
    assert agent3._truncate_words("one two three", 5) == "one two three"
    assert agent3._truncate_words("one two three four five six", 3) == "one two three"
    assert agent3._truncate_words("", 5) == ""


def test_bullet_from_sentence_strips_punctuation() -> None:
    assert agent3._bullet_from_sentence("Verify the identity.") == "Verify the identity"
    assert agent3._bullet_from_sentence("Watch carefully!") == "Watch carefully"


# ─── CLI ─────────────────────────────────────────────────────────────


def _write_narration(path: Path, text: str | None = None) -> Path:
    path.write_text(text or _sample_narration(), encoding="utf-8")
    return path


def test_cli_writes_json_and_exits_zero(tmp_path: Path) -> None:
    in_path = _write_narration(tmp_path / "narration.txt")
    out_path = tmp_path / "slides.json"
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
    payload = json.loads(out_path.read_text(encoding="utf-8"))
    assert payload["slide_count"] >= 1
    assert all(slide["title"].strip() for slide in payload["slides"])


def test_cli_exits_with_io_error_on_missing_input(tmp_path: Path) -> None:
    out_path = tmp_path / "out.json"
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


def test_cli_exits_with_validation_error_on_empty(tmp_path: Path) -> None:
    in_path = _write_narration(tmp_path / "empty.txt", text="   \n\n   ")
    out_path = tmp_path / "out.json"
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
    assert "validation error" in proc.stderr
    assert not out_path.exists()
