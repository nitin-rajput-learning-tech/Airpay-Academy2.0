"""
SENTIENTIA Agent 1 — PDF SOP Parser (MVP / Phase B.0)
=====================================================

Stage 1 of the SOP -> SCORM pipeline. Reads a single PDF from disk and
emits a structured JSON document that Agent 2 (Narration Generator)
consumes.

This is the MVP (Phase B.0): pure local execution, no external API.
A richer prototype lives in ``sentientia/agent1_sop_parser.py`` but
emits a different schema. This MVP is the contract Agent 2 builds
against.

Output schema
-------------
::

    {
      "title": "Anti-Money Laundering SOP",
      "headings": [{"level": 1, "text": "Overview"}, ...],
      "paragraphs": ["First paragraph...", "Second paragraph..."],
      "lists": [
        {"type": "ordered",   "items": ["Step 1", "Step 2"]},
        {"type": "unordered", "items": ["Point A", "Point B"]}
      ],
      "word_count": 1234,
      "source_file": "anti-money-laundering.pdf",
      "parsed_at": "2026-05-24T12:34:56Z"
    }

Validation
----------
- The PDF must contain extractable text (scanned-image PDFs are
  rejected with a clear error).
- ``word_count`` is enforced at <= 2000. ValueError is raised if the
  document exceeds the cap.

CLI
---
::

    python scripts/agents/agent1_sop_parser.py \\
        --input  content/sops/SAMPLE-SOP.pdf \\
        --output content/parsed/SAMPLE-SOP-parsed.json

Exit codes: 0 success, 1 validation failure (over word cap / empty /
schema), 2 I/O failure (input missing, output dir not writable).
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from collections import Counter
from dataclasses import dataclass, field
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import pdfplumber

MAX_WORDS = 2000
HEADING_LEVELS = 3

_BULLET_CHARS = "•‣▪◦●○▶►◆◇■□–—\\-\\*"
_UNORDERED_RE = re.compile(rf"^\s*[{_BULLET_CHARS}]\s+(.*)$")
_ORDERED_RE = re.compile(r"^\s*(?:\(?\d+[.)]|\(?[a-zA-Z][.)]|[ivxIVX]+\.)\s+(.*)$")
_WHITESPACE_RE = re.compile(r"\s+")


@dataclass
class _Line:
    """A single rendered line of text with the font size that wrote it."""

    text: str
    size: float
    page: int


@dataclass
class _ParsedDoc:
    """Intermediate model before JSON serialisation."""

    title: str = ""
    headings: list[dict[str, Any]] = field(default_factory=list)
    paragraphs: list[str] = field(default_factory=list)
    lists: list[dict[str, Any]] = field(default_factory=list)


def parse_pdf(pdf_path: Path) -> dict[str, Any]:
    """Parse ``pdf_path`` into the Agent 2 schema.

    Raises:
        FileNotFoundError: ``pdf_path`` does not exist.
        ValueError: PDF has no extractable text, or word count > MAX_WORDS.
    """
    if not pdf_path.exists():
        raise FileNotFoundError(f"PDF not found: {pdf_path}")
    if not pdf_path.is_file():
        raise ValueError(f"Not a file: {pdf_path}")

    lines = _extract_lines(pdf_path)
    if not lines:
        raise ValueError(
            f"No extractable text in {pdf_path.name}. "
            "Scanned-image PDFs require OCR (not in MVP scope)."
        )

    body_size = _dominant_font_size(lines)
    heading_thresholds = _heading_thresholds(lines, body_size)
    doc = _classify(lines, body_size, heading_thresholds)

    word_count = _count_words(doc)
    if word_count > MAX_WORDS:
        raise ValueError(
            f"SOP exceeds {MAX_WORDS}-word cap: got {word_count} words in "
            f"{pdf_path.name}. Split the SOP before continuing the pipeline."
        )

    return {
        "title": doc.title,
        "headings": doc.headings,
        "paragraphs": doc.paragraphs,
        "lists": doc.lists,
        "word_count": word_count,
        "source_file": pdf_path.name,
        "parsed_at": datetime.now(tz=timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
    }


def _extract_lines(pdf_path: Path) -> list[_Line]:
    """Extract per-line text plus dominant font size for each line."""
    lines: list[_Line] = []
    with pdfplumber.open(str(pdf_path)) as pdf:
        for page_index, page in enumerate(pdf.pages, start=1):
            words = page.extract_words(
                keep_blank_chars=False,
                use_text_flow=True,
                extra_attrs=["size", "fontname"],
            )
            if not words:
                continue
            for line_words in _group_by_line(words):
                text = " ".join(w["text"] for w in line_words).strip()
                if not text:
                    continue
                size = _median_size(line_words)
                lines.append(_Line(text=text, size=size, page=page_index))
    return lines


def _group_by_line(words: list[dict[str, Any]]) -> list[list[dict[str, Any]]]:
    """Group pdfplumber words into visual lines using ``top`` coordinate."""
    if not words:
        return []
    sorted_words = sorted(words, key=lambda w: (round(w["top"], 1), w["x0"]))
    groups: list[list[dict[str, Any]]] = []
    current: list[dict[str, Any]] = []
    current_top: float | None = None
    tolerance = 3.0  # points; covers normal line-spacing jitter
    for word in sorted_words:
        top = word["top"]
        if current_top is None or abs(top - current_top) <= tolerance:
            current.append(word)
            current_top = top if current_top is None else current_top
        else:
            groups.append(current)
            current = [word]
            current_top = top
    if current:
        groups.append(current)
    return groups


def _median_size(words: list[dict[str, Any]]) -> float:
    sizes = sorted(float(w.get("size", 0.0)) for w in words)
    if not sizes:
        return 0.0
    return sizes[len(sizes) // 2]


def _dominant_font_size(lines: list[_Line]) -> float:
    """Mode of rounded sizes — what the body text is set in."""
    counter = Counter(round(line.size, 1) for line in lines)
    return counter.most_common(1)[0][0]


def _heading_thresholds(lines: list[_Line], body_size: float) -> list[float]:
    """Pick up to 3 ascending size buckets larger than body for heading levels.

    Returns thresholds sorted ascending so that:
        size >= thresholds[0] -> level 3
        size >= thresholds[1] -> level 2
        size >= thresholds[2] -> level 1
    Missing levels are filled with ``inf`` so they never match.
    """
    larger = sorted({round(line.size, 1) for line in lines if line.size > body_size + 0.5})
    while len(larger) < HEADING_LEVELS:
        larger.append(float("inf"))
    return larger[:HEADING_LEVELS]


def _level_for(size: float, thresholds: list[float]) -> int | None:
    """Map a font size to a heading level (1 = highest), or None for body."""
    for idx in range(HEADING_LEVELS - 1, -1, -1):
        if size + 0.05 >= thresholds[idx]:
            return HEADING_LEVELS - idx
    return None


def _classify(
    lines: list[_Line],
    body_size: float,
    heading_thresholds: list[float],
) -> _ParsedDoc:
    """Walk the line stream once and split into headings / paragraphs / lists."""
    doc = _ParsedDoc()
    current_paragraph: list[str] = []
    current_list: dict[str, Any] | None = None

    def flush_paragraph() -> None:
        if current_paragraph:
            joined = _normalise(" ".join(current_paragraph))
            if joined:
                doc.paragraphs.append(joined)
            current_paragraph.clear()

    def flush_list() -> None:
        nonlocal current_list
        if current_list and current_list["items"]:
            doc.lists.append(current_list)
        current_list = None

    for line in lines:
        text = line.text.strip()
        if not text:
            flush_paragraph()
            flush_list()
            continue

        level = _level_for(line.size, heading_thresholds)
        unordered = _UNORDERED_RE.match(text)
        ordered = _ORDERED_RE.match(text)
        is_list_item = bool(unordered or ordered)

        if level is not None and not is_list_item:
            flush_paragraph()
            flush_list()
            doc.headings.append({"level": level, "text": _normalise(text)})
            continue

        if is_list_item:
            flush_paragraph()
            list_type = "unordered" if unordered else "ordered"
            item_text = _normalise((unordered or ordered).group(1))
            if not item_text:
                continue
            if current_list is None or current_list["type"] != list_type:
                flush_list()
                current_list = {"type": list_type, "items": []}
            current_list["items"].append(item_text)
            continue

        flush_list()
        current_paragraph.append(text)

    flush_paragraph()
    flush_list()

    doc.title = _pick_title(doc, lines)
    return doc


def _pick_title(doc: _ParsedDoc, lines: list[_Line]) -> str:
    """Title = first level-1 heading, else first heading, else first line."""
    for heading in doc.headings:
        if heading["level"] == 1:
            return heading["text"]
    if doc.headings:
        return doc.headings[0]["text"]
    if lines:
        return _normalise(lines[0].text)
    return ""


def _normalise(text: str) -> str:
    return _WHITESPACE_RE.sub(" ", text).strip()


def _count_words(doc: _ParsedDoc) -> int:
    total = 0
    for heading in doc.headings:
        total += len(heading["text"].split())
    for paragraph in doc.paragraphs:
        total += len(paragraph.split())
    for lst in doc.lists:
        for item in lst["items"]:
            total += len(item.split())
    return total


def _write_output(data: dict[str, Any], output_path: Path) -> None:
    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(
        json.dumps(data, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )


def _build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="agent1_sop_parser",
        description="SENTIENTIA Agent 1 — parse a PDF SOP into JSON for Agent 2.",
    )
    parser.add_argument(
        "--input", "-i",
        required=True,
        type=Path,
        help="Path to a PDF SOP (e.g. content/sops/AML.pdf).",
    )
    parser.add_argument(
        "--output", "-o",
        required=True,
        type=Path,
        help="Path to write the parsed JSON (e.g. content/parsed/AML-parsed.json).",
    )
    return parser


def main(argv: list[str] | None = None) -> int:
    args = _build_parser().parse_args(argv)
    try:
        data = parse_pdf(args.input)
    except FileNotFoundError as exc:
        print(f"agent1: I/O error — {exc}", file=sys.stderr)
        return 2
    except ValueError as exc:
        print(f"agent1: validation error — {exc}", file=sys.stderr)
        return 1

    try:
        _write_output(data, args.output)
    except OSError as exc:
        print(f"agent1: I/O error writing {args.output}: {exc}", file=sys.stderr)
        return 2

    print(
        f"agent1: parsed {args.input.name} -> {args.output} "
        f"({data['word_count']} words, {len(data['headings'])} headings, "
        f"{len(data['paragraphs'])} paragraphs, {len(data['lists'])} lists)",
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
