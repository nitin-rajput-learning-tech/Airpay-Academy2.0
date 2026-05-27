"""
SENTIENTIA Agent 4 — Voice Generator (Phase B.3)
================================================

Stage 4 of the SOP -> SCORM pipeline. Reads the narration text emitted
by Agent 2 (``scripts/agents/agent2_narration_generator.py``) and
synthesises a single MP3 voice-over for the SCORM packager (Agent 5).

Modes
-----
- **Mock mode (default)** — writes a placeholder MP3 file derived from
  the narration text. No network call, no API key, no spend. Used for
  CI and pipeline rehearsal so Agent 5 has a real file on disk.
- **Live mode (``--confirm`` required)** — POSTs the narration to the
  ElevenLabs ``text-to-speech`` endpoint and writes the returned audio.
  Cost is ~$0.30 per 1000 characters; the cost estimate is printed
  before any byte leaves the script.

Per .claude/rules/api.md the ElevenLabs API is **[CONFIRM] required** —
that gate is implemented as the ``--confirm`` flag. Without it the
script never opens a socket.

CLI
---
::

    # mock mode (default — no API call):
    python scripts/agents/agent4_voice_generator.py \\
        --input  content/narrations/SAMPLE-SOP-narration.txt \\
        --output content/voice/SAMPLE-SOP-voice.mp3

    # live mode ([CONFIRM] required — calls ElevenLabs):
    python scripts/agents/agent4_voice_generator.py \\
        --input  content/narrations/SAMPLE-SOP-narration.txt \\
        --output content/voice/SAMPLE-SOP-voice.mp3 \\
        --confirm

Exit codes: 0 success, 1 validation failure (narration size / PII),
2 I/O failure, 3 API / config failure in live mode.
"""

from __future__ import annotations

import argparse
import os
import re
import sys
from pathlib import Path
from typing import Any, Callable

MAX_NARRATION_WORDS = 2100  # matches api.md: Agent 4 hard ceiling
ELEVENLABS_ENDPOINT = "https://api.elevenlabs.io/v1/text-to-speech"
COST_PER_1000_CHARS_USD = 0.30
MOCK_MP3_HEADER = b"ID3\x04\x00\x00\x00\x00\x00\x00"  # 10-byte ID3v2.4 stub
DEFAULT_VOICE_SETTINGS = {
    "stability": 0.50,
    "similarity_boost": 0.75,
    "style": 0.25,
    "use_speaker_boost": True,
}
DEFAULT_MODEL_ID = "eleven_multilingual_v2"

# Crude PII guards. The CLAUDE.md rule says never send employee data to
# ElevenLabs; this catches the most common shapes so a stray paste
# trips the gate instead of being silently uploaded.
_PII_PATTERNS = [
    re.compile(r"\b(?:salary|gross\s+pay|net\s+pay|ctc)\s*[:=]\s*\d", re.IGNORECASE),
    re.compile(r"\bAPI\s+key\b", re.IGNORECASE),
    re.compile(r"\b[A-Z]{3,4}\d{6,}\b"),  # employee-id-shaped tokens
    re.compile(r"\b[\w.+-]+@[\w-]+\.[\w.-]+\b"),  # email
    re.compile(r"\b(?:\+?\d[\s-]?){10,}\b"),  # phone-shaped
    re.compile(r"\b\d{3}-?\d{2}-?\d{4}\b"),  # SSN-shaped
]


# ─── Validation ──────────────────────────────────────────────────────


def validate_narration_for_voice(text: str) -> None:
    """Raise ``ValueError`` if ``text`` is not safe / sized for ElevenLabs."""
    if not isinstance(text, str) or not text.strip():
        raise ValueError("narration is empty")
    word_count = len(text.split())
    if word_count > MAX_NARRATION_WORDS:
        raise ValueError(
            f"narration too long for voice synthesis: {word_count} words "
            f"(max {MAX_NARRATION_WORDS})"
        )
    for pattern in _PII_PATTERNS:
        match = pattern.search(text)
        if match:
            raise ValueError(
                f"narration contains PII-shaped token {match.group(0)!r} — "
                "strip employee data before voice synthesis (CLAUDE.md §13)"
            )


def estimate_cost_usd(text: str) -> float:
    """Approximate USD cost of synthesising ``text`` via ElevenLabs."""
    return len(text) / 1000.0 * COST_PER_1000_CHARS_USD


# ─── Mock generator ──────────────────────────────────────────────────


def generate_mock_mp3(narration: str) -> bytes:
    """
    Build a deterministic placeholder MP3 payload for offline runs.

    The output is NOT playable audio — it is a synthetic byte string
    that downstream tooling can ZIP into a SCORM package for the
    pipeline rehearsal. The first 10 bytes are a valid ID3v2.4 header
    so file-type sniffers still report ``audio/mpeg``.
    """
    word_count = len(narration.split())
    char_count = len(narration)
    body = (
        f"SENTIENTIA-MOCK-MP3\n"
        f"words={word_count}\n"
        f"chars={char_count}\n"
        f"cost-estimate-usd={estimate_cost_usd(narration):.4f}\n"
        f"--- narration follows ---\n"
        f"{narration}"
    ).encode("utf-8")
    return MOCK_MP3_HEADER + body


# ─── Live ElevenLabs call ────────────────────────────────────────────


def synthesise_voice(
    narration: str,
    *,
    api_key: str,
    voice_id: str,
    model_id: str = DEFAULT_MODEL_ID,
    voice_settings: dict[str, Any] | None = None,
    post_fn: Callable[..., Any] | None = None,
) -> bytes:
    """
    POST ``narration`` to ElevenLabs ``text-to-speech`` and return the
    raw audio bytes.

    ``post_fn`` defaults to ``requests.post`` but can be injected for
    unit tests so the test suite never reaches the network.
    """
    if post_fn is None:  # pragma: no cover - default branch only used live
        import requests
        post_fn = requests.post

    settings = dict(DEFAULT_VOICE_SETTINGS)
    if voice_settings:
        settings.update(voice_settings)

    url = f"{ELEVENLABS_ENDPOINT}/{voice_id}"
    payload = {
        "text": narration,
        "model_id": model_id,
        "voice_settings": settings,
    }
    headers = {
        "xi-api-key": api_key,
        "Content-Type": "application/json",
        "Accept": "audio/mpeg",
    }
    response = post_fn(url, json=payload, headers=headers, timeout=120)
    response.raise_for_status()
    audio = response.content
    if not audio:
        raise ValueError("ElevenLabs returned an empty audio body")
    return audio


# ─── CLI plumbing ────────────────────────────────────────────────────


def _read_narration(path: Path) -> str:
    if not path.exists():
        raise FileNotFoundError(f"narration not found: {path}")
    text = path.read_text(encoding="utf-8")
    if not text.strip():
        raise ValueError(f"narration is empty: {path}")
    return text


def _build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="agent4_voice_generator",
        description=(
            "SENTIENTIA Agent 4 — synthesise a voice-over MP3 from "
            "narration text. Default mode is offline/mock; pass --confirm "
            "to call ElevenLabs (costs ~$0.30 per 1000 characters)."
        ),
    )
    parser.add_argument(
        "--input", "-i", required=True, type=Path,
        help="Path to the narration text file from Agent 2.",
    )
    parser.add_argument(
        "--output", "-o", required=True, type=Path,
        help="Path to write the MP3 (or mock MP3) file.",
    )
    parser.add_argument(
        "--confirm", action="store_true",
        help="Authorise a LIVE call to the ElevenLabs API. Without this "
             "flag the agent runs in offline mock mode and does not "
             "POST anywhere.",
    )
    parser.add_argument(
        "--voice-id", default=None,
        help="ElevenLabs voice id. Falls back to env ELEVENLABS_VOICE_ID.",
    )
    parser.add_argument(
        "--model-id", default=DEFAULT_MODEL_ID,
        help="ElevenLabs model id (default: %(default)s).",
    )
    return parser


def main(argv: list[str] | None = None) -> int:
    args = _build_parser().parse_args(argv)

    try:
        narration = _read_narration(args.input)
    except FileNotFoundError as exc:
        print(f"agent4: I/O error — {exc}", file=sys.stderr)
        return 2
    except ValueError as exc:
        print(f"agent4: validation error — {exc}", file=sys.stderr)
        return 1

    try:
        validate_narration_for_voice(narration)
    except ValueError as exc:
        print(f"agent4: validation error — {exc}", file=sys.stderr)
        return 1

    estimated_cost = estimate_cost_usd(narration)
    char_count = len(narration)

    if args.confirm:
        api_key = os.getenv("ELEVENLABS_API_KEY")
        voice_id = args.voice_id or os.getenv("ELEVENLABS_VOICE_ID")
        if not api_key:
            print(
                "agent4: config error — ELEVENLABS_API_KEY is not set. "
                "Either export it (and re-run with --confirm) or drop the "
                "--confirm flag to run in offline mock mode.",
                file=sys.stderr,
            )
            return 3
        if not voice_id:
            print(
                "agent4: config error — voice id missing. Pass --voice-id "
                "or set ELEVENLABS_VOICE_ID in .env.",
                file=sys.stderr,
            )
            return 3
        print(
            f"agent4: [CONFIRM] live mode — POSTing {char_count} chars "
            f"to ElevenLabs (estimated cost ${estimated_cost:.4f}).",
            file=sys.stderr,
        )
        try:
            audio = synthesise_voice(
                narration,
                api_key=api_key,
                voice_id=voice_id,
                model_id=args.model_id,
            )
        except Exception as exc:  # noqa: BLE001 - any API failure is reported
            print(f"agent4: API error — {exc}", file=sys.stderr)
            return 3
    else:
        audio = generate_mock_mp3(narration)

    try:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_bytes(audio)
    except OSError as exc:
        print(f"agent4: I/O error writing {args.output}: {exc}", file=sys.stderr)
        return 2

    mode = "live" if args.confirm else "mock"
    print(
        f"agent4 [{mode}]: wrote {args.output} "
        f"({len(audio)} bytes, {char_count} input chars, "
        f"~${estimated_cost:.4f} live-cost estimate)",
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
