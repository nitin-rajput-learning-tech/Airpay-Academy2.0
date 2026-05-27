"""
SENTIENTIA Content Pipeline — end-to-end integration smoke test
==============================================================

Drives Agents 1 -> 2 -> 3 -> 4 with a single sample SOP and asserts each
stage's output exists and parses. Every external API call is **disabled**
unless the caller passes ``--confirm``; the default invocation costs
nothing.

CLAUDE.md §9 forbids chaining agents inside one process; this script
**spawns a fresh subprocess per agent** so the pipeline-contract rule is
respected. Each subprocess reads its input from disk and writes its
output to disk before the next subprocess starts.

Usage
-----
::

    # Default — mock mode for Agents 2 & 4 (no API spend):
    python scripts/agents/run_pipeline_test.py

    # Custom SOP + custom output dir:
    python scripts/agents/run_pipeline_test.py \\
        --input content/sops/SAMPLE-SOP.pdf \\
        --workdir build/pipeline-smoke

    # Live mode — would call Anthropic + ElevenLabs (refuses without --confirm):
    python scripts/agents/run_pipeline_test.py --live --confirm

Exit codes
----------
- 0: every stage produced a valid output file.
- non-zero: the offending stage's exit code, propagated.
"""

from __future__ import annotations

import argparse
import json
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path
from typing import Sequence

REPO_ROOT = Path(__file__).resolve().parents[2]
AGENT1 = REPO_ROOT / "scripts" / "agents" / "agent1_sop_parser.py"
AGENT2 = REPO_ROOT / "scripts" / "agents" / "agent2_narration_generator.py"
AGENT3 = REPO_ROOT / "scripts" / "agents" / "agent3_slides_generator.py"
AGENT4 = REPO_ROOT / "scripts" / "agents" / "agent4_voice_generator.py"
DEFAULT_SOP = REPO_ROOT / "content" / "sops" / "SAMPLE-SOP.pdf"


def _run(label: str, argv: Sequence[str]) -> None:
    """Run a subprocess and stream its output. Raises ``SystemExit`` on failure."""
    print(f"\n--- {label} ---")
    print("  $ " + " ".join(argv))
    proc = subprocess.run(argv, cwd=str(REPO_ROOT), check=False)
    if proc.returncode != 0:
        print(f"FAIL: {label} exited {proc.returncode}", file=sys.stderr)
        raise SystemExit(proc.returncode)


def _assert_exists(label: str, path: Path, *, min_bytes: int = 1) -> None:
    if not path.exists():
        print(f"FAIL: {label} did not produce {path}", file=sys.stderr)
        raise SystemExit(1)
    size = path.stat().st_size
    if size < min_bytes:
        print(
            f"FAIL: {label} wrote {path} but it is too small ({size} bytes)",
            file=sys.stderr,
        )
        raise SystemExit(1)
    print(f"OK   {label}: {path.relative_to(REPO_ROOT) if path.is_relative_to(REPO_ROOT) else path} ({size} bytes)")


def _assert_valid_json(label: str, path: Path) -> dict:
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        print(f"FAIL: {label} produced invalid JSON at {path}: {exc}", file=sys.stderr)
        raise SystemExit(1) from exc
    if not isinstance(data, dict):
        print(f"FAIL: {label} JSON root is not an object: {type(data)!r}", file=sys.stderr)
        raise SystemExit(1)
    return data


def _build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="run_pipeline_test",
        description="End-to-end smoke test for the SENTIENTIA agent pipeline.",
    )
    parser.add_argument(
        "--input", type=Path, default=DEFAULT_SOP,
        help=f"Source SOP PDF (default: {DEFAULT_SOP.relative_to(REPO_ROOT)}).",
    )
    parser.add_argument(
        "--workdir", type=Path, default=None,
        help="Directory to write intermediate files. Defaults to a tempdir.",
    )
    parser.add_argument(
        "--keep", action="store_true",
        help="Keep the working directory after a successful run.",
    )
    parser.add_argument(
        "--live", action="store_true",
        help="Use live API for Agents 2 and 4 (requires --confirm).",
    )
    parser.add_argument(
        "--confirm", action="store_true",
        help="Acknowledge that --live will cost money. Required with --live.",
    )
    return parser


def main(argv: list[str] | None = None) -> int:
    args = _build_parser().parse_args(argv)

    if args.live and not args.confirm:
        print(
            "ERROR: --live requires --confirm. Refusing to call any "
            "external API without explicit authorisation.",
            file=sys.stderr,
        )
        return 2

    if not args.input.exists():
        print(f"ERROR: SOP not found: {args.input}", file=sys.stderr)
        return 2

    workdir = args.workdir
    cleanup = False
    if workdir is None:
        workdir = Path(tempfile.mkdtemp(prefix="sentientia-pipeline-"))
        cleanup = not args.keep
    workdir.mkdir(parents=True, exist_ok=True)
    print(f"workdir: {workdir}")

    parsed_path = workdir / "parsed.json"
    narration_path = workdir / "narration.txt"
    slides_path = workdir / "slides.json"
    voice_path = workdir / "voice.mp3"

    try:
        _run("Agent 1 (PDF -> JSON)", [
            sys.executable, str(AGENT1),
            "--input", str(args.input),
            "--output", str(parsed_path),
        ])
        _assert_exists("Agent 1", parsed_path, min_bytes=50)
        parsed = _assert_valid_json("Agent 1", parsed_path)
        assert "title" in parsed and "headings" in parsed, "Agent 1 schema drift"

        agent2_args = [
            sys.executable, str(AGENT2),
            "--input", str(parsed_path),
            "--output", str(narration_path),
        ]
        if args.live and args.confirm:
            agent2_args.append("--confirm")
        _run("Agent 2 (JSON -> narration)", agent2_args)
        _assert_exists("Agent 2", narration_path, min_bytes=20)

        _run("Agent 3 (narration -> slides)", [
            sys.executable, str(AGENT3),
            "--input", str(narration_path),
            "--output", str(slides_path),
        ])
        _assert_exists("Agent 3", slides_path, min_bytes=50)
        slides = _assert_valid_json("Agent 3", slides_path)
        assert "slides" in slides and isinstance(slides["slides"], list), \
            "Agent 3 schema drift"
        assert len(slides["slides"]) >= 1, "Agent 3 produced no slides"

        agent4_args = [
            sys.executable, str(AGENT4),
            "--input", str(narration_path),
            "--output", str(voice_path),
        ]
        if args.live and args.confirm:
            agent4_args.append("--confirm")
        _run("Agent 4 (narration -> voice MP3)", agent4_args)
        _assert_exists("Agent 4", voice_path, min_bytes=10)

        print("\nPIPELINE OK")
        print(f"  parsed:    {parsed_path}")
        print(f"  narration: {narration_path}")
        print(f"  slides:    {slides_path}")
        print(f"  voice:     {voice_path}")
        return 0
    finally:
        if cleanup and workdir.exists():
            shutil.rmtree(workdir, ignore_errors=True)
            print(f"\ncleaned up workdir: {workdir}")


if __name__ == "__main__":
    sys.exit(main())
