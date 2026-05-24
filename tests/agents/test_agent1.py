"""Unit tests for ``scripts.agents.agent1_sop_parser``.

The PDF fixture is rendered on the fly with reportlab (see
``_pdf_builder``) so tests are hermetic — no checked-in binary
fixture is required.
"""

from __future__ import annotations

import json
import re
import subprocess
import sys
from pathlib import Path

import pytest

from scripts.agents import agent1_sop_parser as agent1
from tests.agents._pdf_builder import (
    PdfBlock,
    build_long_pdf,
    build_pdf,
    build_sample_sop,
    sample_sop_blocks,
)

REPO_ROOT = Path(__file__).resolve().parents[2]
SCRIPT_PATH = REPO_ROOT / "scripts" / "agents" / "agent1_sop_parser.py"

ISO8601_UTC_RE = re.compile(r"^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$")


# ─── Fixtures ─────────────────────────────────────────────────────────


@pytest.fixture()
def sample_pdf(tmp_path: Path) -> Path:
    return build_sample_sop(tmp_path / "sample.pdf")


@pytest.fixture()
def minimal_pdf(tmp_path: Path) -> Path:
    blocks = [
        PdfBlock("title", "Minimal SOP"),
        PdfBlock("body", "Single body paragraph for the smallest valid case."),
    ]
    return build_pdf(tmp_path / "minimal.pdf", blocks)


# ─── Schema / happy-path ──────────────────────────────────────────────


def test_parses_sample_pdf_with_all_required_fields(sample_pdf: Path) -> None:
    result = agent1.parse_pdf(sample_pdf)

    assert set(result.keys()) == {
        "title", "headings", "paragraphs", "lists",
        "word_count", "source_file", "parsed_at",
    }

    assert result["title"] == "Sentientia LMS Sample SOP"
    assert result["source_file"] == "sample.pdf"
    assert isinstance(result["word_count"], int)
    assert result["word_count"] > 0
    assert ISO8601_UTC_RE.match(result["parsed_at"]), result["parsed_at"]


def test_headings_have_level_and_text(sample_pdf: Path) -> None:
    result = agent1.parse_pdf(sample_pdf)

    assert len(result["headings"]) >= 4, "fixture has 5 headings"
    for heading in result["headings"]:
        assert set(heading.keys()) == {"level", "text"}
        assert 1 <= heading["level"] <= 3
        assert isinstance(heading["text"], str) and heading["text"].strip()

    heading_texts = [h["text"] for h in result["headings"]]
    assert "Overview" in heading_texts
    assert "Acceptance" in heading_texts


def test_paragraphs_are_non_empty_strings(sample_pdf: Path) -> None:
    result = agent1.parse_pdf(sample_pdf)
    assert isinstance(result["paragraphs"], list)
    assert result["paragraphs"], "fixture has body paragraphs"
    for paragraph in result["paragraphs"]:
        assert isinstance(paragraph, str)
        assert paragraph.strip()


def test_lists_capture_ordered_and_unordered_items(sample_pdf: Path) -> None:
    result = agent1.parse_pdf(sample_pdf)

    assert len(result["lists"]) == 2, "fixture has 1 ordered + 1 unordered list"
    list_types = {lst["type"] for lst in result["lists"]}
    assert list_types == {"ordered", "unordered"}

    for lst in result["lists"]:
        assert lst["type"] in ("ordered", "unordered")
        assert isinstance(lst["items"], list) and lst["items"]
        for item in lst["items"]:
            assert isinstance(item, str) and item.strip()

    ordered = next(lst for lst in result["lists"] if lst["type"] == "ordered")
    assert len(ordered["items"]) == 4
    assert ordered["items"][0].startswith("Parse the PDF")


def test_word_count_matches_actual_word_total(sample_pdf: Path) -> None:
    result = agent1.parse_pdf(sample_pdf)
    counted = sum(len(h["text"].split()) for h in result["headings"])
    counted += sum(len(p.split()) for p in result["paragraphs"])
    counted += sum(len(item.split()) for lst in result["lists"] for item in lst["items"])
    assert result["word_count"] == counted


def test_output_is_valid_json_round_trip(sample_pdf: Path) -> None:
    result = agent1.parse_pdf(sample_pdf)
    encoded = json.dumps(result)
    assert json.loads(encoded) == result


# ─── Title fallback ───────────────────────────────────────────────────


def test_title_falls_back_to_first_heading_when_no_h1(tmp_path: Path) -> None:
    # No level-1-sized heading; only h2-sized and below.
    blocks = [
        PdfBlock("h2", "Smaller Heading One"),
        PdfBlock("body", "Body content for the smaller-heading SOP."),
    ]
    pdf = build_pdf(tmp_path / "no-h1.pdf", blocks)
    result = agent1.parse_pdf(pdf)
    assert result["title"] == "Smaller Heading One"


# ─── Validation gates ─────────────────────────────────────────────────


def test_missing_pdf_raises_file_not_found(tmp_path: Path) -> None:
    with pytest.raises(FileNotFoundError):
        agent1.parse_pdf(tmp_path / "does-not-exist.pdf")


def test_word_cap_raises_value_error(tmp_path: Path) -> None:
    # 60 paragraphs × ~40 words = ~2400 words, comfortably over the cap.
    long_pdf = build_long_pdf(tmp_path / "oversized.pdf", paragraph_count=60)
    with pytest.raises(ValueError, match=r"exceeds 2000-word cap"):
        agent1.parse_pdf(long_pdf)


def test_word_cap_passes_under_threshold(tmp_path: Path) -> None:
    # 30 paragraphs × ~40 words = ~1200 words, under the cap.
    long_pdf = build_long_pdf(tmp_path / "ok.pdf", paragraph_count=30)
    result = agent1.parse_pdf(long_pdf)
    assert result["word_count"] <= agent1.MAX_WORDS


# ─── Bullet-character coverage (parser regex, no PDF) ────────────────


@pytest.mark.parametrize(
    "marker",
    ["•", "‣", "▪", "-", "*", "–", "—"],
)
def test_unordered_regex_matches_common_markers(marker: str) -> None:
    match = agent1._UNORDERED_RE.match(f"{marker} item text here")
    assert match is not None, f"failed to match marker {marker!r}"
    assert match.group(1) == "item text here"


@pytest.mark.parametrize(
    "marker",
    ["1.", "2)", "(3)", "a.", "B)", "iv.", "IX."],
)
def test_ordered_regex_matches_common_markers(marker: str) -> None:
    match = agent1._ORDERED_RE.match(f"{marker} item text here")
    assert match is not None, f"failed to match marker {marker!r}"
    assert match.group(1) == "item text here"


def test_ordered_regex_does_not_match_plain_sentence() -> None:
    # "5 million customers..." should not be misread as an ordered list.
    assert agent1._ORDERED_RE.match("5 million customers") is None


# ─── CLI behaviour ────────────────────────────────────────────────────


def test_cli_writes_json_and_exits_zero(tmp_path: Path) -> None:
    pdf = build_sample_sop(tmp_path / "cli.pdf")
    out = tmp_path / "cli-parsed.json"

    proc = subprocess.run(
        [
            sys.executable, str(SCRIPT_PATH),
            "--input", str(pdf),
            "--output", str(out),
        ],
        cwd=str(REPO_ROOT),
        capture_output=True,
        text=True,
        check=False,
    )
    assert proc.returncode == 0, proc.stderr
    assert out.exists()
    payload = json.loads(out.read_text(encoding="utf-8"))
    assert payload["title"] == "Sentientia LMS Sample SOP"
    assert payload["source_file"] == "cli.pdf"


def test_cli_exits_with_validation_error_on_oversized(tmp_path: Path) -> None:
    pdf = build_long_pdf(tmp_path / "big.pdf", paragraph_count=60)
    out = tmp_path / "big-parsed.json"

    proc = subprocess.run(
        [
            sys.executable, str(SCRIPT_PATH),
            "--input", str(pdf),
            "--output", str(out),
        ],
        cwd=str(REPO_ROOT),
        capture_output=True,
        text=True,
        check=False,
    )
    assert proc.returncode == 1, proc.stdout + proc.stderr
    assert "exceeds 2000-word cap" in proc.stderr
    assert not out.exists()


def test_cli_exits_with_io_error_on_missing_input(tmp_path: Path) -> None:
    out = tmp_path / "missing-parsed.json"
    proc = subprocess.run(
        [
            sys.executable, str(SCRIPT_PATH),
            "--input", str(tmp_path / "absent.pdf"),
            "--output", str(out),
        ],
        cwd=str(REPO_ROOT),
        capture_output=True,
        text=True,
        check=False,
    )
    assert proc.returncode == 2, proc.stdout + proc.stderr
    assert "PDF not found" in proc.stderr


# ─── Determinism (Agent-2 contract guard) ────────────────────────────


def test_repeated_runs_produce_same_structure(tmp_path: Path) -> None:
    pdf = build_pdf(tmp_path / "stable.pdf", sample_sop_blocks())
    first = agent1.parse_pdf(pdf)
    second = agent1.parse_pdf(pdf)
    # Strip the timestamp — everything else must match byte-for-byte.
    first.pop("parsed_at")
    second.pop("parsed_at")
    assert first == second
