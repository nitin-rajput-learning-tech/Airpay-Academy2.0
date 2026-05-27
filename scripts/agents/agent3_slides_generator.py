"""
SENTIENTIA Agent 3 — Slides Generator (Phase B.2)
=================================================

Stage 3 of the SOP -> SCORM pipeline. Reads the plain-text narration
emitted by Agent 2 (``scripts/agents/agent2_narration_generator.py``)
and produces a structured slides JSON document consumed by Agent 5
(SCORM Packager).

Pure local execution — **no external API**, no [CONFIRM] gate.

Output schema
-------------
::

    {
      "title": "Sentientia LMS Sample SOP",
      "slide_count": 12,
      "slides": [
        {
          "index": 1,
          "title": "Welcome to this SOP",          // <= 8 words
          "bullets": [                              // <= 5, each <= 8 words
            "We will walk through this together",
            "..."
          ],
          "speaker_notes": "Welcome to ... step by step."
        }
      ],
      "source_file": "SAMPLE-SOP-narration.txt",
      "generated_at": "2026-05-25T12:34:56Z"
    }

Constraints (all enforced)
--------------------------
- 1 <= ``slide_count`` <= 30 (target range 10-15; soft-merged or
  split to fit).
- ``title`` <= 8 words.
- ``bullets`` len <= 5, each bullet <= 8 words.
- ``speaker_notes`` non-empty.

CLI
---
::

    python scripts/agents/agent3_slides_generator.py \\
        --input  content/narrations/SAMPLE-SOP-narration.txt \\
        --output content/slides/SAMPLE-SOP-slides.json

Exit codes: 0 success, 1 validation failure (input format / slide
constraints), 2 I/O failure.
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from dataclasses import asdict, dataclass, field
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

MAX_TITLE_WORDS = 8
MAX_BULLET_WORDS = 8
MAX_BULLETS = 5
TARGET_MIN_SLIDES = 10
TARGET_MAX_SLIDES = 15
HARD_MAX_SLIDES = 30
HARD_MIN_SLIDES = 1

_WHITESPACE_RE = re.compile(r"\s+")
_SENTENCE_SPLIT_RE = re.compile(r"(?<=[.!?])\s+")
_SECTION_PREFIX_RE = re.compile(r"^\s*Section:\s*(.+?)\.\s*", re.IGNORECASE)
_HTML_TAG_RE = re.compile(r"<[^>]+>")


@dataclass
class Slide:
    index: int
    title: str
    bullets: list[str] = field(default_factory=list)
    speaker_notes: str = ""

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


# ─── Public API ──────────────────────────────────────────────────────


def generate_slides(narration_text: str, *, source_file: str = "") -> dict[str, Any]:
    """Convert ``narration_text`` into a slides-JSON dict.

    Raises ``ValueError`` if the narration is empty or any slide violates
    the title / bullet / speaker-notes constraints after generation.
    """
    if not isinstance(narration_text, str) or not narration_text.strip():
        raise ValueError("narration text is empty")
    if _HTML_TAG_RE.search(narration_text):
        raise ValueError("narration contains HTML tags — Agent 2 should reject these")

    paragraphs = _read_paragraphs(narration_text)
    if not paragraphs:
        raise ValueError("narration has no usable paragraphs")

    paragraphs = _rebalance_paragraphs(paragraphs)
    slides = [_paragraph_to_slide(index + 1, p) for index, p in enumerate(paragraphs)]

    deck_title = _deck_title(slides, paragraphs)
    payload = {
        "title": deck_title,
        "slide_count": len(slides),
        "slides": [s.to_dict() for s in slides],
        "source_file": source_file,
        "generated_at": datetime.now(tz=timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
    }
    _validate_payload(payload)
    return payload


def _validate_payload(payload: dict[str, Any]) -> None:
    if not isinstance(payload["title"], str) or not payload["title"].strip():
        raise ValueError("deck title is empty")
    if len(payload["title"].split()) > MAX_TITLE_WORDS:
        raise ValueError(
            f"deck title exceeds {MAX_TITLE_WORDS} words: {payload['title']!r}"
        )
    if not (HARD_MIN_SLIDES <= payload["slide_count"] <= HARD_MAX_SLIDES):
        raise ValueError(
            f"slide_count {payload['slide_count']} outside "
            f"[{HARD_MIN_SLIDES}, {HARD_MAX_SLIDES}]"
        )
    if payload["slide_count"] != len(payload["slides"]):
        raise ValueError("slide_count does not match the slides array length")
    seen_indexes: set[int] = set()
    for slide in payload["slides"]:
        index = slide["index"]
        if not (1 <= index <= payload["slide_count"]):
            raise ValueError(f"slide index out of range: {index}")
        if index in seen_indexes:
            raise ValueError(f"duplicate slide index: {index}")
        seen_indexes.add(index)
        if not slide["title"].strip():
            raise ValueError(f"slide {index} has empty title")
        if len(slide["title"].split()) > MAX_TITLE_WORDS:
            raise ValueError(
                f"slide {index} title exceeds {MAX_TITLE_WORDS} words: "
                f"{slide['title']!r}"
            )
        if len(slide["bullets"]) > MAX_BULLETS:
            raise ValueError(
                f"slide {index} has {len(slide['bullets'])} bullets > "
                f"{MAX_BULLETS}"
            )
        for bullet_index, bullet in enumerate(slide["bullets"], start=1):
            if not bullet.strip():
                raise ValueError(
                    f"slide {index} bullet {bullet_index} is empty"
                )
            if len(bullet.split()) > MAX_BULLET_WORDS:
                raise ValueError(
                    f"slide {index} bullet {bullet_index} exceeds "
                    f"{MAX_BULLET_WORDS} words: {bullet!r}"
                )
        if not slide["speaker_notes"].strip():
            raise ValueError(f"slide {index} has empty speaker_notes")


# ─── Paragraph rebalancing (10-15 target) ────────────────────────────


def _read_paragraphs(text: str) -> list[str]:
    """Split narration into trimmed paragraphs on blank-line boundaries."""
    raw = [p.strip() for p in text.split("\n\n")]
    return [_WHITESPACE_RE.sub(" ", p) for p in raw if p.strip()]


def _rebalance_paragraphs(paragraphs: list[str]) -> list[str]:
    """
    Reshape the paragraph list to land in [TARGET_MIN_SLIDES, TARGET_MAX_SLIDES].

    - If too few: split the longest paragraphs at sentence boundaries.
    - If too many: merge the shortest adjacent pairs.

    Always returns at least HARD_MIN_SLIDES paragraphs.
    """
    if not paragraphs:
        return paragraphs

    result = list(paragraphs)
    safety = 0
    while len(result) < TARGET_MIN_SLIDES and safety < 30:
        candidate_index = _index_of_longest(result)
        if candidate_index is None:
            break
        replacement = _split_paragraph(result[candidate_index])
        if len(replacement) <= 1:
            break
        result = result[:candidate_index] + replacement + result[candidate_index + 1 :]
        safety += 1

    safety = 0
    while len(result) > TARGET_MAX_SLIDES and safety < 30:
        merge_index = _index_of_shortest_pair(result)
        if merge_index is None:
            break
        merged = (result[merge_index] + " " + result[merge_index + 1]).strip()
        result = result[:merge_index] + [merged] + result[merge_index + 2 :]
        safety += 1

    # Hard cap so the deck never gets pathologically large.
    if len(result) > HARD_MAX_SLIDES:
        while len(result) > HARD_MAX_SLIDES:
            merge_index = _index_of_shortest_pair(result)
            if merge_index is None:
                break
            merged = (result[merge_index] + " " + result[merge_index + 1]).strip()
            result = result[:merge_index] + [merged] + result[merge_index + 2 :]

    return result


def _index_of_longest(paragraphs: list[str]) -> int | None:
    if not paragraphs:
        return None
    return max(range(len(paragraphs)), key=lambda i: len(paragraphs[i].split()))


def _index_of_shortest_pair(paragraphs: list[str]) -> int | None:
    if len(paragraphs) < 2:
        return None
    return min(
        range(len(paragraphs) - 1),
        key=lambda i: len(paragraphs[i].split()) + len(paragraphs[i + 1].split()),
    )


def _split_paragraph(paragraph: str) -> list[str]:
    """Split a paragraph into two halves at the nearest sentence boundary."""
    sentences = _split_sentences(paragraph)
    if len(sentences) < 2:
        return [paragraph]
    midpoint = max(1, len(sentences) // 2)
    first = " ".join(sentences[:midpoint]).strip()
    second = " ".join(sentences[midpoint:]).strip()
    return [first, second] if first and second else [paragraph]


def _split_sentences(text: str) -> list[str]:
    cleaned = _WHITESPACE_RE.sub(" ", text).strip()
    if not cleaned:
        return []
    parts = _SENTENCE_SPLIT_RE.split(cleaned)
    return [p.strip() for p in parts if p.strip()]


# ─── Slide construction ──────────────────────────────────────────────


def _paragraph_to_slide(index: int, paragraph: str) -> Slide:
    """Turn one narration paragraph into a Slide."""
    speaker_notes = paragraph.strip()

    section_match = _SECTION_PREFIX_RE.match(paragraph)
    if section_match:
        raw_title = section_match.group(1).strip()
        remainder = paragraph[section_match.end():].strip()
        sentences = _split_sentences(remainder)
    else:
        sentences = _split_sentences(paragraph)
        raw_title = sentences[0] if sentences else f"Slide {index}"
        # The title sentence does NOT become a bullet.
        sentences = sentences[1:]

    title = _truncate_words(raw_title, MAX_TITLE_WORDS)
    if not title:
        title = f"Slide {index}"

    bullets: list[str] = []
    for sentence in sentences:
        bullet = _bullet_from_sentence(sentence)
        if bullet:
            bullets.append(bullet)
        if len(bullets) >= MAX_BULLETS:
            break

    return Slide(
        index=index,
        title=title,
        bullets=bullets,
        speaker_notes=speaker_notes,
    )


def _bullet_from_sentence(sentence: str) -> str:
    """Trim a sentence to <=8 words, drop terminal punctuation."""
    cleaned = sentence.strip().rstrip(".!?;,:")
    if not cleaned:
        return ""
    return _truncate_words(cleaned, MAX_BULLET_WORDS)


def _truncate_words(text: str, max_words: int) -> str:
    words = text.split()
    if not words:
        return ""
    if len(words) <= max_words:
        return " ".join(words)
    return " ".join(words[:max_words])


def _deck_title(slides: list[Slide], paragraphs: list[str]) -> str:
    """
    Pick a deck-level title.

    Prefer "Welcome to <X>." in the first paragraph, otherwise the first
    slide's title.
    """
    if not slides:
        return "Untitled"
    if paragraphs:
        first = paragraphs[0]
        match = re.match(r"^\s*Welcome to\s+(.+?)[.!?]", first, flags=re.IGNORECASE)
        if match:
            return _truncate_words(match.group(1).strip(), MAX_TITLE_WORDS)
    return slides[0].title


# ─── CLI plumbing ────────────────────────────────────────────────────


def _build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="agent3_slides_generator",
        description=(
            "SENTIENTIA Agent 3 — turn a narration text file into a slides "
            "JSON document for the SCORM packager. Pure local execution, "
            "no external API."
        ),
    )
    parser.add_argument(
        "--input", "-i", required=True, type=Path,
        help="Path to the narration text file from Agent 2.",
    )
    parser.add_argument(
        "--output", "-o", required=True, type=Path,
        help="Path to write the slides JSON.",
    )
    return parser


def main(argv: list[str] | None = None) -> int:
    args = _build_parser().parse_args(argv)

    if not args.input.exists():
        print(f"agent3: I/O error — narration not found: {args.input}", file=sys.stderr)
        return 2

    try:
        narration = args.input.read_text(encoding="utf-8")
    except OSError as exc:
        print(f"agent3: I/O error reading {args.input}: {exc}", file=sys.stderr)
        return 2

    try:
        payload = generate_slides(narration, source_file=args.input.name)
    except ValueError as exc:
        print(f"agent3: validation error — {exc}", file=sys.stderr)
        return 1

    try:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(
            json.dumps(payload, indent=2, ensure_ascii=False) + "\n",
            encoding="utf-8",
        )
    except OSError as exc:
        print(f"agent3: I/O error writing {args.output}: {exc}", file=sys.stderr)
        return 2

    print(
        f"agent3: wrote {args.output} "
        f"({payload['slide_count']} slides, deck title {payload['title']!r})",
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
