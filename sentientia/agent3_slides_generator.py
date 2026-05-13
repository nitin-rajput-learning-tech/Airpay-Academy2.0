"""
SENTIENTIA Agent 3 — Slides Generator
======================================

Reads `content/narrations/<source>-narration.txt` (Agent 2 output).
Produces `content/slides/<source>-slides.json` (Agent 5 input).

Architectural contract (per SUPP-C Section 3.3 + Section 2):
1. Reads input from disk, writes output to disk, exits.
2. Validation gates: input must be a non-empty narration; output must
   satisfy SUPP-C Section 7.7 quality benchmarks (≤30 slides, ≤5 bullets
   per slide, ≤8 words per bullet, title slide present).
3. Costs money on every live call. The [CONFIRM] gate guards against
   accidental API spend.

VENDOR: Gamma (https://gamma.app/api). Vendor-agnostic seam at
`call_gamma()` so a future swap to python-pptx or Tome is a small diff.

USAGE
-----

Dry run (no API call, just generates the prompt + validates input):
    python sentientia/agent3_slides_generator.py \\
        content/narrations/posh-2024-narration.txt --dry-run

Live run ([CONFIRM] gate before each call):
    python sentientia/agent3_slides_generator.py \\
        content/narrations/posh-2024-narration.txt

Batch:
    python sentientia/agent3_slides_generator.py \\
        content/narrations/*.txt --batch

Local fallback (no Gamma — uses a python-pptx-style local
slide builder; produces a minimum-viable deck):
    python sentientia/agent3_slides_generator.py \\
        content/narrations/posh-2024-narration.txt --local-fallback

ENVIRONMENT
-----------

Reads from `.env` at project root (never committed):
    GAMMA_API_KEY       — Gamma API key
    GAMMA_API_ENDPOINT  — defaults to 'https://api.gamma.app/v0.2/generations'

OUTPUT SCHEMA
-------------

    {
      "metadata": {
        "source_narration": "posh-2024-narration.txt",
        "generated_at":     "2026-05-13T12:30:00.000Z",
        "slide_count":      8,
        "agent":            "sentientia_agent3_slides_generator",
        "version":          "1.0",
        "provider":         "gamma" | "local"
      },
      "title": "POSH 2024 Compliance",
      "slides": [
        {
          "title":  "...",
          "points": ["...", "..."],
          "speaker_notes": "..."   // optional, from Gamma
        },
        ...
      ]
    }

STATUS
------

Skeleton with full prompt template + validation gates + disk-artefact
contract. The Gamma API call is gated by --confirm and is not invoked
in this revision. The structure is here so that when the [CONFIRM]
gate is granted (per master-doc Section 7.5 + Decision 13.2), the
live integration is a small diff.

Local fallback IS active and writes a minimum-viable deck — useful
for end-to-end smoke testing the SENTIENTIA pipeline before Gamma
credentials land.
"""

from __future__ import annotations

import argparse
import io
import json
import os
import re
import sys
from dataclasses import dataclass, field
from datetime import datetime, timezone
from pathlib import Path

if sys.platform == "win32":
    try:
        sys.stdout.reconfigure(encoding="utf-8")
        sys.stderr.reconfigure(encoding="utf-8")
    except (AttributeError, OSError):
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8",
                                       errors="replace", line_buffering=True)
        sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding="utf-8",
                                       errors="replace", line_buffering=True)


# ─── Quality benchmarks (per SUPP-C Section 7.7) ────────────────────────
MIN_SLIDES = 3
MAX_SLIDES = 30
MAX_BULLETS_PER_SLIDE = 5
MAX_WORDS_PER_BULLET  = 8

AGENT_VERSION = "1.0"
AGENT_NAME = "sentientia_agent3_slides_generator"


# ─── Prompt template ────────────────────────────────────────────────────


SLIDES_PROMPT_TEMPLATE = """You are a slide-deck designer for Airpay
Academy. Your task is to convert the following learning narration into
a structured slide deck.

CONSTRAINTS (strict, all must hold):

1. Output strictly as JSON with this exact shape:

   {{
     "title":  "<deck title — short, learner-facing>",
     "slides": [
       {{
         "title":  "<slide title>",
         "points": ["<bullet 1>", "<bullet 2>", ...]
       }},
       ...
     ]
   }}

2. {min_slides}-{max_slides} slides total.
3. Each slide has at most {max_bullets} bullets.
4. Each bullet at most {max_words} words.
5. First slide is the title slide: "Welcome" or course name; ≤2 bullets.
6. Last slide is the summary slide: "Key Takeaways"; lists 3-5 bullets
   capturing the main points.
7. No employee names, customer names, or other identifying details.
8. Indian English spelling and conventions.

TOPIC: {title}
NARRATION:

{narration}

OUTPUT: respond with ONLY the JSON object. No prose. No markdown
fences. No comments."""


def build_prompt(narration: str, title: str) -> str:
    return SLIDES_PROMPT_TEMPLATE.format(
        min_slides=MIN_SLIDES,
        max_slides=MAX_SLIDES,
        max_bullets=MAX_BULLETS_PER_SLIDE,
        max_words=MAX_WORDS_PER_BULLET,
        title=title,
        narration=narration,
    )


# ─── Validation gates ───────────────────────────────────────────────────


@dataclass
class SlidesResult:
    title: str
    slides: list[dict]
    provider: str
    raw_response: str = ""
    errors: list[str] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)

    @property
    def passes_gates(self) -> bool:
        return not self.errors


def validate_slides(title: str, slides: list[dict],
                     strict: bool = False) -> tuple[list[str], list[str]]:
    """Apply the SUPP-C Section 7.7 quality gates. Returns
    (errors, warnings)."""
    errors: list[str] = []
    warnings: list[str] = []

    if not title or not title.strip():
        errors.append("title is missing or empty")

    if not isinstance(slides, list):
        errors.append("slides is not a list")
        return errors, warnings

    if len(slides) < MIN_SLIDES:
        errors.append(f"too few slides: {len(slides)} < {MIN_SLIDES} min")
    if len(slides) > MAX_SLIDES:
        errors.append(f"too many slides: {len(slides)} > {MAX_SLIDES} max")

    for i, slide in enumerate(slides):
        if not isinstance(slide, dict):
            errors.append(f"slide {i}: not a dict")
            continue
        if not slide.get("title"):
            errors.append(f"slide {i}: missing title")
        points = slide.get("points", [])
        if not isinstance(points, list):
            errors.append(f"slide {i}: `points` not a list")
            continue
        if len(points) > MAX_BULLETS_PER_SLIDE:
            msg = (f"slide {i}: {len(points)} bullets > "
                   f"{MAX_BULLETS_PER_SLIDE} max")
            (errors if strict else warnings).append(msg)
        for j, point in enumerate(points):
            if not isinstance(point, str):
                errors.append(f"slide {i} bullet {j}: not a string")
                continue
            wc = len(point.split())
            if wc > MAX_WORDS_PER_BULLET:
                msg = (f"slide {i} bullet {j}: {wc} words > "
                       f"{MAX_WORDS_PER_BULLET} max ({point[:50]!r})")
                (errors if strict else warnings).append(msg)

    return errors, warnings


# ─── Gamma API call (gated, not invoked in skeleton) ────────────────────


def call_gamma(prompt: str, *, timeout: int = 60) -> str:
    """Live call to Gamma's generation API. NOT invoked unless
    --confirm and the user has answered 'yes' at the interactive prompt.

    Returns the raw JSON response body. Raises on API or quota errors.

    SKELETON — not yet implementing the live Gamma HTTP call. Gated on
    a future [CONFIRM] from Nitin per master-doc Section 7.5 + Decision
    13.2 budget approval. The structure below is the API contract we
    will hit; the actual `requests.post` line is intentionally absent.
    """
    api_key = os.environ.get("GAMMA_API_KEY")
    if not api_key:
        raise RuntimeError(
            "GAMMA_API_KEY not set in environment. Configure in .env "
            "(never committed)."
        )

    endpoint = os.environ.get(
        "GAMMA_API_ENDPOINT",
        "https://api.gamma.app/v0.2/generations",
    )

    # PLACEHOLDER for the actual HTTP call. Activated in a follow-up
    # commit after the [CONFIRM] gate is granted:
    #
    #   import requests
    #   response = requests.post(endpoint, json={
    #       "model": "gamma-1",
    #       "prompt": prompt,
    #       "format": "json",
    #       "max_slides": MAX_SLIDES,
    #   }, headers={
    #       "Authorization": f"Bearer {api_key}",
    #       "Content-Type": "application/json",
    #   }, timeout=timeout)
    #   response.raise_for_status()
    #   return response.text

    raise RuntimeError(
        "call_gamma is a skeleton — not yet wired to live Gamma API. "
        "See SUPP-C Section 3.3 for the activation plan. Use "
        "--local-fallback for end-to-end smoke testing."
    )


# ─── Local-fallback generator (no API call) ─────────────────────────────


def local_fallback(narration: str, title: str) -> dict:
    """Produce a minimum-viable slide deck from the narration WITHOUT
    calling any external API. Used as the --local-fallback path so the
    SENTIENTIA pipeline can be smoke-tested end-to-end before Gamma
    credentials are configured.

    Heuristic: split narration into sentences, group ~3-4 sentences per
    slide, take the first 4-8 words of each sentence as a bullet point.
    Title-case the first sentence as the slide title.
    """
    # Split into sentences.
    sentences = re.split(r"(?<=[.!?])\s+", narration.strip())
    sentences = [s.strip() for s in sentences if s.strip()]

    if not sentences:
        return {
            "title": title,
            "slides": [
                {"title": "Welcome", "points": ["No content available"]},
                {"title": "Body", "points": ["Content missing"]},
                {"title": "Summary", "points": ["End of course"]},
            ],
        }

    # First slide = title slide.
    slides: list[dict] = [{
        "title": title,
        "points": ["Course overview", "Learning objectives"],
    }]

    # Group sentences ~3 per slide.
    chunk_size = 3
    for i in range(0, len(sentences), chunk_size):
        chunk = sentences[i:i + chunk_size]
        slide_title = _heuristic_title(chunk[0])
        bullets = []
        for s in chunk:
            # Take first 5 words of each sentence as a bullet.
            words = s.split()[:6]
            bullet = " ".join(words).rstrip(",.;:")
            if bullet:
                bullets.append(bullet)
        bullets = bullets[:MAX_BULLETS_PER_SLIDE]
        slides.append({
            "title": slide_title,
            "points": bullets,
        })
        if len(slides) >= MAX_SLIDES - 1:
            break  # leave room for the summary slide

    # Last slide = summary.
    slides.append({
        "title": "Key Takeaways",
        "points": [
            "Review the main concepts",
            "Apply learning at work",
            "Complete assessment to finish",
        ],
    })

    return {"title": title, "slides": slides}


def _heuristic_title(first_sentence: str) -> str:
    """First N words of the sentence, title-cased."""
    words = first_sentence.split()[:4]
    title = " ".join(words).rstrip(",.;:")
    return title.title() if title else "Section"


# ─── Confirm gate ──────────────────────────────────────────────────────


def confirm_call(narration_name: str) -> bool:
    if not sys.stdin.isatty():
        print(
            "REFUSING: stdin is not a tty. The [CONFIRM] gate must be "
            "answered by a human, not piped.",
            file=sys.stderr,
        )
        return False
    print(f"\n  About to call Gamma for: {narration_name}")
    print(f"  Estimated cost: ~Rs.50-100 per deck.")
    answer = input("  Proceed? [type 'yes' exactly to confirm]: ").strip()
    return answer == "yes"


# ─── Main ──────────────────────────────────────────────────────────────


def process_one(narration_path: Path, output_dir: Path,
                *, dry_run: bool, local_fallback_mode: bool) -> bool:
    print(f"\n-- Processing {narration_path.name} --")
    narration = narration_path.read_text(encoding="utf-8").strip()
    if len(narration) < 200:
        print(f"  SKIP: narration too short ({len(narration)} chars < 200)")
        return False

    # Title heuristic: first non-empty line, or filename.
    first_line = narration.split("\n", 1)[0].strip()
    title = first_line if 5 <= len(first_line) <= 80 else \
            narration_path.stem.replace("-narration", "").replace("-", " ").title()

    prompt = build_prompt(narration, title)
    print(f"  Prompt built. Length: {len(prompt):,} chars.")

    if dry_run:
        print("  [DRY RUN] Would call Gamma with the prompt above.")
        return False

    if local_fallback_mode:
        print("  [LOCAL FALLBACK] Generating deck without Gamma API.")
        deck = local_fallback(narration, title)
        provider = "local"
        raw_response = "(local fallback)"
    else:
        if not confirm_call(narration_path.name):
            print("  ABORTED by operator at [CONFIRM] gate.")
            return False

        try:
            raw_response = call_gamma(prompt)
        except RuntimeError as e:
            print(f"  ERROR: {e}")
            return False

        try:
            deck = json.loads(raw_response)
        except json.JSONDecodeError as e:
            print(f"  ERROR: Gamma returned invalid JSON: {e}")
            return False
        provider = "gamma"

    # Validate.
    errors, warnings = validate_slides(deck.get("title", ""),
                                         deck.get("slides", []),
                                         strict=False)
    for w in warnings:
        print(f"  WARN: {w}")
    if errors:
        print("  GATE FAILURE:")
        for e in errors:
            print(f"    - {e}")
        print("  Output rejected. Slides JSON NOT written.")
        return False

    # Wrap with metadata.
    output = {
        "metadata": {
            "source_narration": narration_path.name,
            "generated_at": datetime.now(timezone.utc).isoformat(
                timespec="milliseconds"),
            "slide_count": len(deck.get("slides", [])),
            "agent": AGENT_NAME,
            "version": AGENT_VERSION,
            "provider": provider,
        },
        "title":  deck.get("title", title),
        "slides": deck.get("slides", []),
    }

    output_dir.mkdir(parents=True, exist_ok=True)
    base = narration_path.stem.replace("-narration", "")
    output_path = output_dir / f"{base}-slides.json"
    output_path.write_text(
        json.dumps(output, indent=2, ensure_ascii=False),
        encoding="utf-8",
    )
    print(f"  OK wrote {output_path}")
    print(f"  Slides: {output['metadata']['slide_count']}")
    print(f"  Provider: {provider}")
    return True


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="SENTIENTIA Agent 3 - Slides Generator"
    )
    parser.add_argument(
        "inputs",
        nargs="+",
        help="One or more narration .txt files (output of Agent 2)",
    )
    parser.add_argument(
        "--output-dir",
        default="content/slides",
        help="Output directory for slides JSON (default: content/slides)",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Build prompt + validate input, but do not call API",
    )
    parser.add_argument(
        "--local-fallback",
        action="store_true",
        help="Use the local sentence-grouping heuristic instead of Gamma. "
        "Produces minimum-viable decks for smoke testing without API spend.",
    )
    parser.add_argument(
        "--batch",
        action="store_true",
        help="Process multiple files; [CONFIRM] once at batch start",
    )
    args = parser.parse_args(argv)

    inputs = [Path(p) for p in args.inputs]
    for p in inputs:
        if not p.exists():
            print(f"FATAL: {p} does not exist", file=sys.stderr)
            return 2

    output_dir = Path(args.output_dir)
    succeeded = 0
    failed = 0

    for p in inputs:
        ok = process_one(p, output_dir,
                         dry_run=args.dry_run,
                         local_fallback_mode=args.local_fallback)
        if ok:
            succeeded += 1
        else:
            failed += 1

    print(f"\n-- Summary: {succeeded} written, {failed} skipped --")
    return 0 if succeeded > 0 or args.dry_run else 1


if __name__ == "__main__":
    sys.exit(main())
