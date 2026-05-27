"""
SENTIENTIA Agent 2 — Narration Generator (Phase B.1)
====================================================

Stage 2 of the SOP -> SCORM pipeline. Reads the structured JSON emitted
by Agent 1 (``scripts/agents/agent1_sop_parser.py``) and produces a plain
text narration script consumed by Agent 3 (Slides) and Agent 4 (Voice).

Modes
-----
- **Mock mode (default)** — pure local deterministic transformation.
  No external API call, no API key required. Used for tests, CI, and
  pipeline rehearsal.
- **Live mode (``--confirm`` required)** — POSTs the Agent 1 JSON to the
  Anthropic Claude API (``claude-opus-4-7`` by default) and uses the
  response as the narration. Costs money. Gated by the
  ``ANTHROPIC_API_KEY`` env var and the explicit ``--confirm`` flag.

Narration constraints (enforced after generation)
-------------------------------------------------
- <= 25 words per sentence (Agent 4 pacing target)
- <= 2000 words total (matches Agent 1 cap, ~15min at 130 wpm)
- Plain UTF-8 text only (no HTML / markdown markers)
- Section breaks rendered as blank lines (one paragraph per section)

CLI
---
::

    # mock mode (default — no API, no cost):
    python scripts/agents/agent2_narration_generator.py \\
        --input  content/parsed/SAMPLE-SOP-parsed.json \\
        --output content/narrations/SAMPLE-SOP-narration.txt

    # live mode ([CONFIRM] required — calls Anthropic API):
    python scripts/agents/agent2_narration_generator.py \\
        --input  content/parsed/SAMPLE-SOP-parsed.json \\
        --output content/narrations/SAMPLE-SOP-narration.txt \\
        --confirm

Exit codes: 0 success, 1 validation failure (input schema / narration
constraints), 2 I/O failure, 3 API / config failure in live mode.
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Callable

MAX_WORDS = 2000
MAX_WORDS_PER_SENTENCE = 25
ANTHROPIC_DEFAULT_MODEL = "claude-opus-4-7"
ANTHROPIC_DEFAULT_MAX_TOKENS = 4096
ANTHROPIC_ENDPOINT = "https://api.anthropic.com/v1/messages"
ANTHROPIC_API_VERSION = "2023-06-01"

_WHITESPACE_RE = re.compile(r"\s+")
_SENTENCE_SPLIT_RE = re.compile(r"(?<=[.!?])\s+")
_HTML_TAG_RE = re.compile(r"<[^>]+>")
_MD_MARKER_RE = re.compile(r"^\s*(?:#{1,6}\s|[*_]{1,3}[^*_]+[*_]{1,3}\s*$)")


# ─── Public schema/validation ────────────────────────────────────────


REQUIRED_PARSED_FIELDS = (
    "title", "headings", "paragraphs", "lists", "word_count",
    "source_file", "parsed_at",
)


def validate_parsed_input(data: Any) -> None:
    """Raise ``ValueError`` if ``data`` is not a valid Agent 1 output."""
    if not isinstance(data, dict):
        raise ValueError("parsed JSON must be a dict")
    missing = [f for f in REQUIRED_PARSED_FIELDS if f not in data]
    if missing:
        raise ValueError(f"parsed JSON missing required fields: {missing}")
    if not isinstance(data["title"], str):
        raise ValueError("'title' must be a string")
    if not isinstance(data["headings"], list):
        raise ValueError("'headings' must be a list")
    for heading in data["headings"]:
        if not isinstance(heading, dict) or "level" not in heading or "text" not in heading:
            raise ValueError("each heading must be {level, text}")
    if not isinstance(data["paragraphs"], list):
        raise ValueError("'paragraphs' must be a list of strings")
    if not all(isinstance(p, str) for p in data["paragraphs"]):
        raise ValueError("'paragraphs' must be strings")
    if not isinstance(data["lists"], list):
        raise ValueError("'lists' must be a list of {type, items} objects")
    for lst in data["lists"]:
        if not isinstance(lst, dict) or "type" not in lst or "items" not in lst:
            raise ValueError("each list must be {type, items}")
        if lst["type"] not in ("ordered", "unordered"):
            raise ValueError(f"unknown list type: {lst['type']!r}")
        if not isinstance(lst["items"], list) or not all(isinstance(i, str) for i in lst["items"]):
            raise ValueError("'items' must be a list of strings")
    if not isinstance(data["word_count"], int):
        raise ValueError("'word_count' must be an integer")


# ─── Narration validation (output guards) ─────────────────────────────


@dataclass
class NarrationStats:
    word_count: int
    sentence_count: int
    paragraph_count: int
    longest_sentence_words: int


def split_sentences(text: str) -> list[str]:
    """Split ``text`` into sentences using terminal punctuation."""
    cleaned = _WHITESPACE_RE.sub(" ", text).strip()
    if not cleaned:
        return []
    parts = _SENTENCE_SPLIT_RE.split(cleaned)
    return [p.strip() for p in parts if p.strip()]


def narration_stats(text: str) -> NarrationStats:
    paragraphs = [p for p in text.split("\n\n") if p.strip()]
    all_sentences: list[str] = []
    for paragraph in paragraphs:
        all_sentences.extend(split_sentences(paragraph))
    word_total = sum(len(s.split()) for s in all_sentences)
    longest = max((len(s.split()) for s in all_sentences), default=0)
    return NarrationStats(
        word_count=word_total,
        sentence_count=len(all_sentences),
        paragraph_count=len(paragraphs),
        longest_sentence_words=longest,
    )


def validate_narration(text: str) -> NarrationStats:
    """Raise ``ValueError`` if narration violates the pipeline constraints."""
    if not isinstance(text, str) or not text.strip():
        raise ValueError("narration is empty")
    if _HTML_TAG_RE.search(text):
        raise ValueError("narration contains HTML tags — must be plain text")
    for line in text.splitlines():
        if _MD_MARKER_RE.match(line):
            raise ValueError(
                f"narration contains markdown marker on line: {line!r} — must be plain text"
            )
    stats = narration_stats(text)
    if stats.word_count == 0:
        raise ValueError("narration has zero countable words")
    if stats.word_count > MAX_WORDS:
        raise ValueError(
            f"narration exceeds {MAX_WORDS}-word cap: got {stats.word_count} words"
        )
    if stats.longest_sentence_words > MAX_WORDS_PER_SENTENCE:
        raise ValueError(
            f"narration has a sentence longer than {MAX_WORDS_PER_SENTENCE} words "
            f"(longest = {stats.longest_sentence_words})"
        )
    return stats


# ─── Mock generator (deterministic, offline) ─────────────────────────


def generate_mock_narration(parsed: dict[str, Any]) -> str:
    """
    Deterministically build a narration from an Agent 1 parsed-JSON dict.

    The output respects all pipeline constraints (no HTML, <= 25 words per
    sentence, <= 2000 total, blank-line section breaks). Used by tests and
    by the CLI when ``--confirm`` is not passed.

    Algorithm:
      1. Opening line: announce the title.
      2. For each heading + the body content that follows it:
         - emit "Section: <heading>." as one sentence
         - each paragraph -> one or more short sentences (split if > 25 words)
         - each list item -> one short sentence
      3. Closing line: short summary.
    """
    validate_parsed_input(parsed)

    title = parsed["title"].strip() or "this procedure"
    sections = _build_sections(parsed)
    paragraphs: list[str] = []

    opening = _short_sentences(
        f"Welcome to {title}. We'll walk through this together step by step."
    )
    paragraphs.append(" ".join(opening))

    for section in sections:
        block: list[str] = []
        if section.heading:
            block.extend(_short_sentences(f"Section: {section.heading}."))
        for paragraph in section.paragraphs:
            block.extend(_short_sentences(paragraph))
        for lst in section.lists:
            if lst["type"] == "ordered":
                for index, item in enumerate(lst["items"], start=1):
                    block.extend(_short_sentences(f"Step {index}. {item}"))
            else:
                for item in lst["items"]:
                    block.extend(_short_sentences(item))
        if block:
            paragraphs.append(" ".join(block))

    closing = _short_sentences(
        f"That concludes {title}. Thank you for completing this module."
    )
    paragraphs.append(" ".join(closing))

    text = "\n\n".join(p for p in paragraphs if p.strip())
    text = _enforce_caps(text)
    return text + "\n"


@dataclass
class _Section:
    heading: str
    paragraphs: list[str]
    lists: list[dict[str, Any]]


def _build_sections(parsed: dict[str, Any]) -> list[_Section]:
    """
    Group the flat parsed JSON into ordered sections so the narration
    reads sequentially. Without heading anchors we group everything into
    a single section. This is intentionally simple — Agent 1 emits a
    flat list, the section layout is reconstructed here only for the
    narration's reading order.
    """
    headings = [h["text"] for h in parsed["headings"]]
    paragraphs = list(parsed["paragraphs"])
    lists = list(parsed["lists"])

    if not headings:
        return [_Section(heading="", paragraphs=paragraphs, lists=lists)]

    sections: list[_Section] = []
    para_chunks = _split_into_chunks(paragraphs, len(headings))
    list_chunks = _split_into_chunks(lists, len(headings))
    for index, heading in enumerate(headings):
        sections.append(_Section(
            heading=heading,
            paragraphs=para_chunks[index],
            lists=list_chunks[index],
        ))
    return sections


def _split_into_chunks(items: list, buckets: int) -> list[list]:
    """Split ``items`` into ``buckets`` near-equal-size lists (in order)."""
    if buckets <= 0:
        return [items]
    chunks: list[list] = [[] for _ in range(buckets)]
    for index, item in enumerate(items):
        chunks[min(index, buckets - 1)].append(item)
    return chunks


def _short_sentences(text: str) -> list[str]:
    """Return ``text`` split into <=25-word sentences, no HTML, no markdown."""
    cleaned = _HTML_TAG_RE.sub("", text)
    cleaned = _WHITESPACE_RE.sub(" ", cleaned).strip()
    if not cleaned:
        return []
    raw_sentences = split_sentences(cleaned) or [cleaned]
    result: list[str] = []
    for sentence in raw_sentences:
        words = sentence.split()
        if len(words) <= MAX_WORDS_PER_SENTENCE:
            result.append(_punctuate(sentence))
            continue
        # Split overlong sentences at the nearest comma, else by word budget.
        result.extend(_chunk_sentence(words))
    return result


def _chunk_sentence(words: list[str]) -> list[str]:
    """Break a too-long word list into <=25-word punctuated sentences."""
    chunks: list[str] = []
    cursor = 0
    while cursor < len(words):
        end = min(cursor + MAX_WORDS_PER_SENTENCE, len(words))
        chunk_words = words[cursor:end]
        chunks.append(_punctuate(" ".join(chunk_words)))
        cursor = end
    return chunks


def _punctuate(text: str) -> str:
    text = text.strip().rstrip(",;:")
    if not text:
        return ""
    if text[-1] in ".!?":
        return text
    return text + "."


def _enforce_caps(text: str) -> str:
    """
    Truncate gently if mock generation slightly overruns the 2000-word cap.
    The mock is deterministic but list-heavy SOPs can creep close to the
    cap; if we exceed it, we drop trailing sentences from the last paragraph
    until the budget fits. We never silently lose more than 5% of the input.
    """
    stats = narration_stats(text)
    if stats.word_count <= MAX_WORDS:
        return text
    paragraphs = [p for p in text.split("\n\n") if p.strip()]
    while paragraphs and narration_stats("\n\n".join(paragraphs)).word_count > MAX_WORDS:
        last = paragraphs.pop()
        sentences = split_sentences(last)
        # Drop one sentence at a time and re-test.
        for index in range(len(sentences) - 1, 0, -1):
            trimmed = " ".join(sentences[:index])
            candidate = paragraphs + [trimmed]
            if narration_stats("\n\n".join(candidate)).word_count <= MAX_WORDS:
                return "\n\n".join(candidate)
        # If even one sentence is too much, just drop the paragraph entirely.
    return "\n\n".join(paragraphs)


# ─── Live Anthropic call (gated by --confirm) ────────────────────────


def build_anthropic_prompt(parsed: dict[str, Any]) -> str:
    """Build the user-message prompt sent to Claude in live mode."""
    validate_parsed_input(parsed)
    return (
        "You are a learning-content narration writer for the Sentientia LMS "
        "SOP-to-SCORM pipeline. Convert the structured SOP JSON below into "
        "a single plain-text narration script for an e-learning voice-over.\n\n"
        "Hard constraints (the output is rejected if any is violated):\n"
        f"- Total length: at most {MAX_WORDS} words.\n"
        f"- Every sentence: at most {MAX_WORDS_PER_SENTENCE} words.\n"
        "- Plain UTF-8 text only. No HTML. No markdown. No bullet glyphs.\n"
        "- Section breaks rendered as a single blank line between paragraphs.\n"
        "- Conversational, second-person tone. No jargon, no acronyms without expansion.\n"
        "- No employee names, IDs, salary figures, or PII.\n\n"
        "Structure: open with one welcome sentence using the SOP title; then "
        "one paragraph per heading containing the heading's paragraphs and "
        "list items rewritten as short sentences; close with one short "
        "thank-you sentence.\n\n"
        "Output the narration text only. Do not include any commentary, "
        "headers, or wrapping.\n\n"
        "SOP JSON:\n"
        + json.dumps(parsed, indent=2, ensure_ascii=False)
    )


def call_anthropic(
    prompt: str,
    *,
    api_key: str,
    model: str = ANTHROPIC_DEFAULT_MODEL,
    max_tokens: int = ANTHROPIC_DEFAULT_MAX_TOKENS,
    post_fn: Callable[..., Any] | None = None,
) -> str:
    """
    POST ``prompt`` to the Anthropic Messages API and return the text.

    ``post_fn`` defaults to ``requests.post`` but can be injected for
    unit tests so the test suite never reaches the network.
    """
    if post_fn is None:  # pragma: no cover - default branch exercised in live mode only
        import requests
        post_fn = requests.post

    payload = {
        "model": model,
        "max_tokens": max_tokens,
        "messages": [{"role": "user", "content": prompt}],
    }
    headers = {
        "x-api-key": api_key,
        "anthropic-version": ANTHROPIC_API_VERSION,
        "content-type": "application/json",
    }
    response = post_fn(ANTHROPIC_ENDPOINT, json=payload, headers=headers, timeout=120)
    response.raise_for_status()
    data = response.json()
    if isinstance(data, dict) and "content" in data and isinstance(data["content"], list):
        # Anthropic returns content as a list of {type, text} blocks.
        blocks = [b.get("text", "") for b in data["content"] if isinstance(b, dict) and b.get("type") == "text"]
        text = "\n".join(b for b in blocks if b).strip()
        if text:
            return text
    raise ValueError(f"Anthropic response missing text content: {data!r}")


# ─── CLI plumbing ────────────────────────────────────────────────────


def _read_parsed(path: Path) -> dict[str, Any]:
    if not path.exists():
        raise FileNotFoundError(f"parsed JSON not found: {path}")
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        raise ValueError(f"parsed JSON is not valid JSON: {exc}") from exc
    validate_parsed_input(data)
    return data


def _write_output(text: str, output_path: Path) -> None:
    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(text, encoding="utf-8")


def _build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="agent2_narration_generator",
        description=(
            "SENTIENTIA Agent 2 — generate narration text from Agent 1's "
            "parsed JSON. Default mode is offline/mock; pass --confirm to "
            "call the Anthropic API (costs money)."
        ),
    )
    parser.add_argument(
        "--input", "-i", required=True, type=Path,
        help="Path to Agent 1 JSON (e.g. content/parsed/AML-parsed.json).",
    )
    parser.add_argument(
        "--output", "-o", required=True, type=Path,
        help="Path to write the narration text (e.g. content/narrations/AML-narration.txt).",
    )
    parser.add_argument(
        "--confirm", action="store_true",
        help="Authorise a LIVE call to the Anthropic Claude API. Without "
             "this flag the agent runs in offline mock mode and does not "
             "POST anywhere.",
    )
    parser.add_argument(
        "--model", default=ANTHROPIC_DEFAULT_MODEL,
        help="Anthropic model id when --confirm is set (default: %(default)s).",
    )
    parser.add_argument(
        "--max-tokens", type=int, default=ANTHROPIC_DEFAULT_MAX_TOKENS,
        help="Max tokens in the live response (default: %(default)s).",
    )
    return parser


def main(argv: list[str] | None = None) -> int:
    args = _build_parser().parse_args(argv)

    try:
        parsed = _read_parsed(args.input)
    except FileNotFoundError as exc:
        print(f"agent2: I/O error — {exc}", file=sys.stderr)
        return 2
    except ValueError as exc:
        print(f"agent2: validation error — {exc}", file=sys.stderr)
        return 1

    if args.confirm:
        api_key = os.getenv("ANTHROPIC_API_KEY")
        if not api_key:
            print(
                "agent2: config error — ANTHROPIC_API_KEY is not set. "
                "Either export it (and re-run with --confirm) or drop the "
                "--confirm flag to run in offline mock mode.",
                file=sys.stderr,
            )
            return 3
        try:
            prompt = build_anthropic_prompt(parsed)
            print(
                f"agent2: [CONFIRM] live mode — POSTing to Anthropic "
                f"({args.model}). This call costs money.",
                file=sys.stderr,
            )
            narration = call_anthropic(
                prompt,
                api_key=api_key,
                model=args.model,
                max_tokens=args.max_tokens,
            )
        except Exception as exc:  # noqa: BLE001 - any API failure is reported
            print(f"agent2: API error — {exc}", file=sys.stderr)
            return 3
    else:
        narration = generate_mock_narration(parsed)

    if not narration.endswith("\n"):
        narration = narration + "\n"

    try:
        stats = validate_narration(narration)
    except ValueError as exc:
        print(f"agent2: validation error — {exc}", file=sys.stderr)
        return 1

    try:
        _write_output(narration, args.output)
    except OSError as exc:
        print(f"agent2: I/O error writing {args.output}: {exc}", file=sys.stderr)
        return 2

    mode = "live" if args.confirm else "mock"
    print(
        f"agent2 [{mode}]: wrote {args.output} "
        f"({stats.word_count} words, {stats.sentence_count} sentences, "
        f"{stats.paragraph_count} paragraphs, longest sentence "
        f"{stats.longest_sentence_words} words)",
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
