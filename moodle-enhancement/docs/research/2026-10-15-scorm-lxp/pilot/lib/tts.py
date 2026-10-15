"""Narration audio, captions and word timestamps.

MOCK       a valid silent MP3 (MPEG-1 Layer III, 44.1 kHz mono, 32 kbps frames
           with zeroed side information) of the estimated narration duration,
           plus WebVTT captions and evenly spaced word timestamps.
LOCAL-CMD  runs an owner-supplied command template once per unit and language,
           reads the MP3 it produced, measures its duration by walking the frame
           headers and builds the same captions.

Duration estimate: 130 words per minute for Latin-script text, ~12 characters
per second for Indic/Arabic-script text, never below 2 seconds.
Caption rules: cues last 1.0-7.0 s with an 83 ms gap; HH:MM:SS.mmm timestamps.
Nothing is downloaded or bundled: the mock engine synthesises nothing audible.
"""
from __future__ import annotations

import math
import re
import shlex
import subprocess
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

from .common import HarnessError

SAMPLE_RATE = 44100
SAMPLES_PER_FRAME = 1152
FRAME_BYTES = 144 * 32000 // SAMPLE_RATE  # 104 bytes at 32 kbps, no padding
FRAME_HEADER = bytes([0xFF, 0xFB, 0x10, 0xC0])  # MPEG-1, Layer III, no CRC, 32 kbps, 44.1 kHz, mono
SILENT_FRAME = FRAME_HEADER + bytes(FRAME_BYTES - len(FRAME_HEADER))

WORDS_PER_MINUTE = 130
CHARS_PER_SECOND = 12
MIN_DURATION_MS = 2000
MIN_CUE_MS = 1000
MAX_CUE_MS = 7000
CUE_GAP_MS = 83
SENTENCE_END = ".!?।۔؟"  # . ! ? danda urdu-full-stop arabic-question
CLOSERS = "\"'”’)]"

NON_LATIN_RANGES = (
    (0x0600, 0x06FF), (0x0750, 0x077F), (0x0900, 0x097F), (0x0980, 0x09FF), (0x0A00, 0x0A7F),
    (0x0A80, 0x0AFF), (0x0B00, 0x0B7F), (0x0B80, 0x0BFF), (0x0C00, 0x0C7F), (0x0C80, 0x0CFF),
    (0x0D00, 0x0D7F), (0xFB50, 0xFDFF), (0xFE70, 0xFEFF),
)
BITRATES_V1_L3 = (0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320)
BITRATES_V2_L3 = (0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160)
SAMPLE_RATES = {3: (44100, 48000, 32000), 2: (22050, 24000, 16000), 0: (11025, 12000, 8000)}


@dataclass(frozen=True)
class Cue:
    start_ms: int
    end_ms: int
    text: str


@dataclass(frozen=True)
class NarrationUnit:
    """One narrated unit in one language."""

    key: str  # file stem, e.g. "b01" or "s01_n1"
    block_id: str
    node_id: str | None
    lang: str
    text: str
    voice: str = "default"


@dataclass
class MediaResult:
    """Synthesised audio plus captions for one unit and language."""

    unit: NarrationUnit
    mp3: bytes
    duration_ms: int
    vtt: str
    word_timestamps: list[dict[str, Any]]
    engine: str
    processing_region: str
    audio_path: str
    vtt_path: str
    timestamps_path: str
    cues: list[Cue] = field(default_factory=list)

    def media_ref(self, sha256: str) -> dict[str, Any]:
        return {
            "path": self.audio_path,
            "mime": "audio/mpeg",
            "sha256": sha256,
            "duration_ms": self.duration_ms,
            "engine": self.engine,
            "voice": self.unit.voice,
            "word_timestamps": self.timestamps_path,
            "processing_region": self.processing_region,
        }


# -- script detection and duration ---------------------------------------------


def is_non_latin(text: str) -> bool:
    """True when at least 30% of the letters fall in Indic or Arabic blocks."""
    letters = [c for c in text if c.isalpha()]
    if not letters:
        return False
    hits = sum(1 for c in letters if any(lo <= ord(c) <= hi for lo, hi in NON_LATIN_RANGES))
    return hits / len(letters) >= 0.3


def natural_duration_ms(text: str) -> int:
    """Unclamped reading-time estimate for a fragment."""
    text = text.strip()
    if not text:
        return 0
    if is_non_latin(text):
        chars = len(re.sub(r"\s+", "", text))
        return int(round(chars / CHARS_PER_SECOND * 1000))
    words = len(text.split())
    return int(round(words / WORDS_PER_MINUTE * 60 * 1000))


def estimate_duration_ms(text: str) -> int:
    """Reading-time estimate with the 2 s floor."""
    return max(MIN_DURATION_MS, natural_duration_ms(text))


# -- MP3 ---------------------------------------------------------------------------


def silent_mp3(duration_ms: int) -> bytes:
    """Silent MPEG-1 Layer III stream covering at least duration_ms."""
    frames = max(1, math.ceil(duration_ms * SAMPLE_RATE / (SAMPLES_PER_FRAME * 1000)))
    return SILENT_FRAME * frames


def mp3_duration_ms(data: bytes) -> int:
    """Duration by walking Layer III frame headers (ID3v2 tag skipped). 0 when no frames are found."""
    pos = 0
    if data[:3] == b"ID3" and len(data) >= 10:
        size = ((data[6] & 0x7F) << 21) | ((data[7] & 0x7F) << 14) | ((data[8] & 0x7F) << 7) | (data[9] & 0x7F)
        pos = 10 + size
    samples = 0
    rate = 0
    length = len(data)
    while pos + 4 <= length:
        b1, b2, b3 = data[pos], data[pos + 1], data[pos + 2]
        if b1 != 0xFF or (b2 & 0xE0) != 0xE0:
            pos += 1
            continue
        version = (b2 >> 3) & 3
        layer = (b2 >> 1) & 3
        bitrate_index = b3 >> 4
        rate_index = (b3 >> 2) & 3
        padding = (b3 >> 1) & 1
        if version == 1 or layer != 1 or bitrate_index in (0, 15) or rate_index == 3:
            pos += 1
            continue
        if version == 3:
            bitrate, per_frame, coefficient = BITRATES_V1_L3[bitrate_index] * 1000, 1152, 144
        else:
            bitrate, per_frame, coefficient = BITRATES_V2_L3[bitrate_index] * 1000, 576, 72
        rate = SAMPLE_RATES[version][rate_index]
        frame_length = coefficient * bitrate // rate + padding
        if frame_length <= 4:
            pos += 1
            continue
        samples += per_frame
        pos += frame_length
    return int(round(samples * 1000 / rate)) if rate else 0


# -- captions ----------------------------------------------------------------------


def split_sentences(text: str) -> list[str]:
    """Split on sentence-final punctuation (Latin, Devanagari danda, Urdu/Arabic marks) and newlines."""
    sentences: list[str] = []
    current: list[str] = []
    chars = text.strip()
    index = 0
    while index < len(chars):
        char = chars[index]
        current.append(char)
        if char == "\n":
            sentences.append("".join(current).strip())
            current = []
        elif char in SENTENCE_END:
            nxt = index + 1
            while nxt < len(chars) and chars[nxt] in SENTENCE_END + CLOSERS:
                current.append(chars[nxt])
                nxt += 1
            if nxt >= len(chars) or chars[nxt].isspace():
                sentences.append("".join(current).strip())
                current = []
            index = nxt
            continue
        index += 1
    if current:
        sentences.append("".join(current).strip())
    return [s for s in sentences if s]


def _chunk_long(sentence: str) -> list[str]:
    """Split a sentence whose natural duration exceeds MAX_CUE_MS into near-equal word groups."""
    duration = natural_duration_ms(sentence)
    if duration <= MAX_CUE_MS:
        return [sentence]
    words = sentence.split()
    pieces = max(2, math.ceil(duration / MAX_CUE_MS))
    if len(words) < pieces:
        step = math.ceil(len(sentence) / pieces)
        return [sentence[i:i + step] for i in range(0, len(sentence), step)]
    size = math.ceil(len(words) / pieces)
    return [" ".join(words[i:i + size]) for i in range(0, len(words), size)]


def build_cues(text: str, total_ms: int) -> list[Cue]:
    """Allocate total_ms across sentence chunks proportionally, clamped to 1-7 s with 83 ms gaps."""
    chunks = [c for s in split_sentences(text) for c in _chunk_long(s)] or [text.strip() or "..."]
    weights = [max(1, natural_duration_ms(c)) for c in chunks]
    available = max(0, total_ms - CUE_GAP_MS * (len(chunks) - 1))
    scale = available / sum(weights) if sum(weights) else 1.0
    durations = [min(MAX_CUE_MS, max(MIN_CUE_MS, w * scale)) for w in weights]
    slack = available - sum(durations)
    if slack > 0:
        headroom = [MAX_CUE_MS - d for d in durations]
        total_headroom = sum(headroom)
        if total_headroom > 0:
            durations = [d + slack * h / total_headroom for d, h in zip(durations, headroom)]
    cues: list[Cue] = []
    cursor = 0
    for chunk, duration in zip(chunks, durations):
        start = cursor
        end = start + int(round(duration))
        cues.append(Cue(start, end, chunk))
        cursor = end + CUE_GAP_MS
    return cues


def vtt_timestamp(ms: int) -> str:
    hours, rest = divmod(ms, 3_600_000)
    minutes, rest = divmod(rest, 60_000)
    seconds, millis = divmod(rest, 1000)
    return f"{hours:02d}:{minutes:02d}:{seconds:02d}.{millis:03d}"


def _vtt_escape(text: str) -> str:
    return text.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")


def cues_to_vtt(cues: list[Cue], lang: str) -> str:
    lines = ["WEBVTT", f"Language: {lang}", ""]
    for index, cue in enumerate(cues, start=1):
        lines.append(str(index))
        lines.append(f"{vtt_timestamp(cue.start_ms)} --> {vtt_timestamp(cue.end_ms)}")
        lines.append(_vtt_escape(cue.text))
        lines.append("")
    return "\n".join(lines)


def word_timestamps(cues: list[Cue]) -> list[dict[str, Any]]:
    """Evenly spaced word estimates inside each cue: [{w, start_ms, end_ms}]."""
    out: list[dict[str, Any]] = []
    for cue in cues:
        words = cue.text.split() or [cue.text]
        span = (cue.end_ms - cue.start_ms) / len(words)
        for index, word in enumerate(words):
            out.append({"w": word, "start_ms": int(round(cue.start_ms + index * span)), "end_ms": int(round(cue.start_ms + (index + 1) * span))})
    return out


# -- engines ------------------------------------------------------------------------


def render_command(template: str, mapping: dict[str, str]) -> list[str]:
    """Split the owner's template into argv first, then substitute placeholders token by token.

    No shell is involved, so a path containing spaces or shell metacharacters is
    passed to the engine verbatim as one argument.
    """
    try:
        tokens = shlex.split(template, posix=True)
    except ValueError as exc:
        raise HarnessError(f"--tts-cmd template cannot be parsed: {exc}") from exc
    if not tokens:
        raise HarnessError("--tts-cmd template is empty")
    argv: list[str] = []
    for token in tokens:
        for key, value in mapping.items():
            token = token.replace("{" + key + "}", value)
        argv.append(token)
    return argv


def media_paths(unit: NarrationUnit) -> tuple[str, str, str]:
    return (
        f"audio/{unit.lang}/{unit.key}.mp3",
        f"captions/{unit.lang}/{unit.key}.vtt",
        f"captions/{unit.lang}/{unit.key}.words.json",
    )


class MockTts:
    """Silent MP3 of the estimated duration; captions and timestamps from the text."""

    engine = "mock-silent"
    processing_region = "local"

    def synthesize(self, unit: NarrationUnit) -> MediaResult:
        estimate = estimate_duration_ms(unit.text)
        cues = build_cues(unit.text, estimate)
        duration = max(estimate, cues[-1].end_ms if cues else 0)
        audio_path, vtt_path, ts_path = media_paths(unit)
        return MediaResult(
            unit=unit,
            mp3=silent_mp3(duration),
            duration_ms=duration,
            vtt=cues_to_vtt(cues, unit.lang),
            word_timestamps=word_timestamps(cues),
            engine=self.engine,
            processing_region=self.processing_region,
            audio_path=audio_path,
            vtt_path=vtt_path,
            timestamps_path=ts_path,
            cues=cues,
        )


class LocalCmdTts:
    """Runs `template` with {text_file} {out_mp3} {lang} {voice} substituted, once per unit."""

    engine = "local-cmd"
    processing_region = "local"

    def __init__(self, template: str, work_dir: Path, timeout_s: int = 120):
        if not template.strip():
            raise HarnessError("--tts local-cmd requires --tts-cmd TEMPLATE")
        for placeholder in ("{text_file}", "{out_mp3}"):
            if placeholder not in template:
                raise HarnessError(f"--tts-cmd must contain the {placeholder} placeholder")
        self.template = template
        self.work_dir = work_dir
        self.timeout_s = timeout_s

    def synthesize(self, unit: NarrationUnit) -> MediaResult:
        audio_path, vtt_path, ts_path = media_paths(unit)
        text_file = self.work_dir / "tts-input" / unit.lang / f"{unit.key}.txt"
        out_mp3 = self.work_dir / audio_path
        text_file.parent.mkdir(parents=True, exist_ok=True)
        out_mp3.parent.mkdir(parents=True, exist_ok=True)
        text_file.write_text(unit.text, encoding="utf-8", newline="\n")
        argv = render_command(
            self.template,
            {"text_file": str(text_file), "out_mp3": str(out_mp3), "lang": unit.lang, "voice": unit.voice},
        )
        try:
            completed = subprocess.run(argv, timeout=self.timeout_s, capture_output=True)
        except FileNotFoundError as exc:
            raise HarnessError(f"TTS command not found: {argv[0]!r} (is the engine on PATH?)") from exc
        except subprocess.TimeoutExpired as exc:
            raise HarnessError(f"TTS command timed out after {self.timeout_s}s for {unit.key}/{unit.lang}") from exc
        if completed.returncode != 0:
            stderr = completed.stderr.decode("utf-8", errors="replace")[-400:]
            raise HarnessError(f"TTS command failed (exit {completed.returncode}) for {unit.key}/{unit.lang}: {stderr}")
        if not out_mp3.is_file() or out_mp3.stat().st_size == 0:
            raise HarnessError(f"TTS command produced no audio at {out_mp3}")
        data = out_mp3.read_bytes()
        duration = mp3_duration_ms(data)
        if duration <= 0:
            raise HarnessError(f"{out_mp3} contains no decodable MPEG Layer III frames")
        cues = build_cues(unit.text, duration)
        return MediaResult(
            unit=unit,
            mp3=data,
            duration_ms=duration,
            vtt=cues_to_vtt(cues, unit.lang),
            word_timestamps=word_timestamps(cues),
            engine=self.engine,
            processing_region=self.processing_region,
            audio_path=audio_path,
            vtt_path=vtt_path,
            timestamps_path=ts_path,
            cues=cues,
        )


def narration_units(doc: dict[str, Any], langs: list[str], voice: str = "default") -> list[NarrationUnit]:
    """Every (slide narration | scenario node prompt) x language that has text."""
    units: list[NarrationUnit] = []
    for block in doc.get("blocks", []):
        kind = block.get("type")
        if kind == "slide":
            text_obj = block.get("narration") or block.get("title") or {}
            for lang in langs:
                text = text_obj.get(lang)
                if isinstance(text, str) and text.strip():
                    units.append(NarrationUnit(block["id"], block["id"], None, lang, text, voice))
        elif kind == "scenario":
            for node in block.get("nodes", []):
                prompt = node.get("prompt") or {}
                for lang in langs:
                    text = prompt.get(lang)
                    if isinstance(text, str) and text.strip():
                        units.append(NarrationUnit(f"{block['id']}_{node['id']}", block["id"], node["id"], lang, text, voice))
    return units
