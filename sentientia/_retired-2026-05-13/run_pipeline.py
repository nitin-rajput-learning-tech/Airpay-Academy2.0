"""
SENTIENTIA Pipeline Orchestrator
=================================

Chains all six SENTIENTIA agents in sequence. Each agent reads from
disk, writes to disk, then exits. The orchestrator's role is purely
flow control: which agent to run next, how to handle a per-stage
failure, and whether to ask for [CONFIRM] at each spend gate.

Per `docs/SUPP-C-SENTIENTIA-DETAILED-PLAN-2026-05-12.md` Section 4.

USAGE
-----

Smoke test (local-fallback on all vendor-dependent stages):
    python sentientia/run_pipeline.py content/sops/POSH-2024.pdf \\
        --local-fallback

Dry-run (validate every stage, do not call any API or write final
artefacts):
    python sentientia/run_pipeline.py content/sops/POSH-2024.pdf --dry-run

Production run (one-time [CONFIRM] at each spend gate):
    python sentientia/run_pipeline.py content/sops/POSH-2024.pdf \\
        --target-course-id 42

Force a particular stage to use the local fallback (e.g. you have
Gamma credentials but not ElevenLabs):
    python sentientia/run_pipeline.py content/sops/POSH-2024.pdf \\
        --target-course-id 42 \\
        --local-fallback-stages voice

PIPELINE FLOW
-------------

    content/sops/<x>.pdf
       v  agent1_sop_parser
    content/parsed/<x>-parsed.json
       v  agent2_narration_generator  [CONFIRM Claude]
    content/narrations/<x>-narration.txt
       v  agent3_slides_generator     [CONFIRM Gamma]
    content/slides/<x>-slides.json
       v
    content/narrations/<x>-narration.txt
       v  agent4_voice_generator      [CONFIRM ElevenLabs]
    content/voice/<x>-voice.mp3
       v
    [slides + voice]
       v  agent5_scorm_packager
    content/scorm-output/<x>-scorm.zip
       v  agent6_moodle_upload        [CONFIRM Moodle live]
    Live SCORM activity on www.airpay.academy
"""

from __future__ import annotations

import argparse
import io
import subprocess
import sys
from dataclasses import dataclass, field
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


SENTIENTIA_DIR = Path(__file__).parent


@dataclass
class StageOutcome:
    stage: str
    ok: bool
    output_path: Path | None = None
    notes: str = ""


@dataclass
class PipelineRun:
    sop_path: Path
    target_course_id: int | None
    dry_run: bool
    local_fallback_stages: set[str]
    stages: list[StageOutcome] = field(default_factory=list)

    def add(self, stage: str, ok: bool, output: Path | None = None,
            notes: str = "") -> None:
        self.stages.append(StageOutcome(stage, ok, output, notes))

    @property
    def last_output(self) -> Path | None:
        for outcome in reversed(self.stages):
            if outcome.ok and outcome.output_path:
                return outcome.output_path
        return None

    @property
    def passes(self) -> bool:
        return all(s.ok for s in self.stages)


# ─── Stage runner ──────────────────────────────────────────────────────


def run_stage(script: str, args: list[str], *, stage_label: str) -> bool:
    """Invoke a SENTIENTIA agent as a subprocess. Returns True on
    successful exit (rc == 0). Stdout + stderr are streamed through
    to the orchestrator's own stdout.
    """
    cmd = [sys.executable, str(SENTIENTIA_DIR / script)] + args
    print(f"\n>>> {stage_label}: {' '.join(cmd[2:])}")
    try:
        rc = subprocess.run(cmd, check=False).returncode
    except FileNotFoundError as e:
        print(f"  ORCHESTRATOR ERROR: cannot run {script}: {e}")
        return False
    if rc == 0:
        print(f"<<< {stage_label}: OK")
        return True
    else:
        print(f"<<< {stage_label}: FAILED (rc={rc})")
        return False


def should_local_fallback(stage_name: str, run: PipelineRun) -> bool:
    """A stage uses --local-fallback if EITHER the run-wide flag is set
    OR the per-stage override includes this stage."""
    return "all" in run.local_fallback_stages or stage_name in run.local_fallback_stages


def fallback_flag_args(stage_name: str, run: PipelineRun) -> list[str]:
    return ["--local-fallback"] if should_local_fallback(stage_name, run) else []


def dry_run_args(run: PipelineRun) -> list[str]:
    return ["--dry-run"] if run.dry_run else []


# ─── Per-stage orchestration ───────────────────────────────────────────


def stage_1_parse(run: PipelineRun) -> Path | None:
    """Agent 1 — SOP Parser."""
    base = run.sop_path.stem
    out_dir = Path("content/parsed")
    out_path = out_dir / f"{base}-parsed.json"

    args = [str(run.sop_path), "--output-dir", str(out_dir)]
    args += dry_run_args(run)

    ok = run_stage("agent1_sop_parser.py", args, stage_label="Agent 1 (SOP parser)")
    run.add("parse", ok, out_path if ok and not run.dry_run else None)
    return out_path if ok and (run.dry_run or out_path.exists()) else None


def stage_2_narrate(run: PipelineRun, parsed_path: Path) -> Path | None:
    """Agent 2 — Narration Generator. Currently SKELETON — only --dry-run
    works without Anthropic API. Falls through to the next stage if
    Agent 2 is in skeleton mode but dry-run is requested."""
    base = parsed_path.stem.replace("-parsed", "")
    out_dir = Path("content/narrations")
    out_path = out_dir / f"{base}-narration.txt"

    args = [str(parsed_path), "--output-dir", str(out_dir)]
    args += dry_run_args(run)
    # No --local-fallback yet on Agent 2 (Claude integration is
    # documented but skeleton). If full smoke test required, the
    # orchestrator stops here unless --dry-run.

    ok = run_stage("agent2_narration_generator.py", args,
                   stage_label="Agent 2 (Narration / Claude)")
    run.add("narrate", ok, out_path if ok and not run.dry_run else None)
    return out_path if ok and (run.dry_run or out_path.exists()) else None


def stage_3_slides(run: PipelineRun, narration_path: Path) -> Path | None:
    """Agent 3 — Slides Generator."""
    base = narration_path.stem.replace("-narration", "")
    out_dir = Path("content/slides")
    out_path = out_dir / f"{base}-slides.json"

    args = [str(narration_path), "--output-dir", str(out_dir)]
    args += dry_run_args(run)
    args += fallback_flag_args("slides", run)

    ok = run_stage("agent3_slides_generator.py", args,
                   stage_label="Agent 3 (Slides / Gamma)")
    run.add("slides", ok, out_path if ok and not run.dry_run else None)
    return out_path if ok and (run.dry_run or out_path.exists()) else None


def stage_4_voice(run: PipelineRun, narration_path: Path) -> Path | None:
    """Agent 4 — Voice Generator."""
    base = narration_path.stem.replace("-narration", "")
    out_dir = Path("content/voice")
    out_path = out_dir / f"{base}-voice.mp3"

    args = [str(narration_path), "--output-dir", str(out_dir)]
    args += dry_run_args(run)
    args += fallback_flag_args("voice", run)

    ok = run_stage("agent4_voice_generator.py", args,
                   stage_label="Agent 4 (Voice / ElevenLabs)")
    run.add("voice", ok, out_path if ok and not run.dry_run else None)
    return out_path if ok and (run.dry_run or out_path.exists()) else None


def stage_5_pack(run: PipelineRun, base: str) -> Path | None:
    """Agent 5 — SCORM Packager."""
    out_dir = Path("content/scorm-output")
    out_path = out_dir / f"{base}-scorm.zip"

    args = [base, "--output-dir", str(out_dir)]
    args += dry_run_args(run)

    ok = run_stage("agent5_scorm_packager.py", args,
                   stage_label="Agent 5 (SCORM packager)")
    run.add("pack", ok, out_path if ok and not run.dry_run else None)
    return out_path if ok and (run.dry_run or out_path.exists()) else None


def stage_6_upload(run: PipelineRun, zip_path: Path) -> bool:
    """Agent 6 — Moodle Upload."""
    if run.target_course_id is None:
        print(">>> Agent 6 (Moodle upload): SKIPPED — no --target-course-id provided")
        run.add("upload", True, notes="skipped (no course id)")
        return True

    args = [str(zip_path), "--target-course-id", str(run.target_course_id)]
    args += dry_run_args(run)

    ok = run_stage("agent6_moodle_upload.py", args,
                   stage_label="Agent 6 (Moodle upload)")
    run.add("upload", ok, notes=f"course id {run.target_course_id}")
    return ok


# ─── Top-level orchestration ───────────────────────────────────────────


def run_pipeline(sop_path: Path, *,
                  target_course_id: int | None,
                  dry_run: bool,
                  local_fallback_stages: set[str]) -> PipelineRun:
    run = PipelineRun(
        sop_path=sop_path,
        target_course_id=target_course_id,
        dry_run=dry_run,
        local_fallback_stages=local_fallback_stages,
    )

    print(f"================================================================")
    print(f"  SENTIENTIA pipeline — {sop_path.name}")
    print(f"================================================================")
    print(f"  Target course id:      {target_course_id}")
    print(f"  Dry run:               {dry_run}")
    print(f"  Local-fallback stages: {sorted(local_fallback_stages) or '(none)'}")

    # Stage 1.
    parsed = stage_1_parse(run)
    if not parsed:
        return run

    # Stage 2.
    narration = stage_2_narrate(run, parsed)
    if not narration:
        return run

    # Stage 3 + 4 are independent and could run in parallel. We run them
    # sequentially here to keep the [CONFIRM] flow predictable.
    slides = stage_3_slides(run, narration)
    if not slides:
        return run
    voice = stage_4_voice(run, narration)
    if not voice:
        return run

    base = parsed.stem.replace("-parsed", "")
    # Stage 5.
    scorm = stage_5_pack(run, base)
    if not scorm:
        return run

    # Stage 6 (optional).
    stage_6_upload(run, scorm)

    return run


# ─── Summary printer ───────────────────────────────────────────────────


def print_summary(run: PipelineRun) -> None:
    print("\n================================================================")
    print(f"  SUMMARY — {run.sop_path.name}")
    print("================================================================")
    for outcome in run.stages:
        mark = "OK  " if outcome.ok else "FAIL"
        loc = outcome.output_path or outcome.notes or "(no output)"
        print(f"  {mark}  {outcome.stage:10s}  {loc}")

    if run.passes:
        print("\n  Pipeline complete.")
    else:
        print("\n  Pipeline stopped at first failure.")


# ─── CLI ───────────────────────────────────────────────────────────────


def parse_fallback_stages(s: str) -> set[str]:
    """Parse --local-fallback-stages value into a set."""
    if not s:
        return set()
    parts = [p.strip().lower() for p in s.split(",") if p.strip()]
    valid = {"slides", "voice", "all"}
    invalid = set(parts) - valid
    if invalid:
        raise argparse.ArgumentTypeError(
            f"unknown stage(s): {sorted(invalid)}. valid: {sorted(valid)}"
        )
    return set(parts)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="SENTIENTIA pipeline orchestrator"
    )
    parser.add_argument(
        "sop_pdf",
        help="Path to the source SOP PDF (e.g. content/sops/POSH-2024.pdf)",
    )
    parser.add_argument(
        "--target-course-id",
        type=int,
        default=None,
        help="Moodle course id for the final upload step. Omit to skip "
        "Agent 6 entirely (useful for end-to-end testing without "
        "touching live Moodle).",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Pass --dry-run to every agent. Validates without writing "
        "any output or calling any API.",
    )
    parser.add_argument(
        "--local-fallback",
        action="store_true",
        help="Pass --local-fallback to all vendor-dependent stages "
        "(equivalent to --local-fallback-stages all).",
    )
    parser.add_argument(
        "--local-fallback-stages",
        type=parse_fallback_stages,
        default=set(),
        help="Comma-separated list of stages to run in local-fallback "
        "mode. Valid: slides, voice, all. Example: --local-fallback-"
        "stages voice (use real Gamma for slides, silent MP3 for voice).",
    )
    args = parser.parse_args(argv)

    sop_path = Path(args.sop_pdf)
    if not sop_path.exists():
        print(f"FATAL: SOP file not found: {sop_path}", file=sys.stderr)
        return 2

    fallback = args.local_fallback_stages
    if args.local_fallback:
        fallback = fallback | {"all"}

    run = run_pipeline(
        sop_path,
        target_course_id=args.target_course_id,
        dry_run=args.dry_run,
        local_fallback_stages=fallback,
    )

    print_summary(run)
    return 0 if run.passes else 1


if __name__ == "__main__":
    sys.exit(main())
