"""
SENTIENTIA pipeline - RETIRED entry point (2026-10-22)
======================================================

The content pipeline has ONE implementation: ``scripts/agents/`` (agents 1-6, covered by
``tests/agents/`` in CI) driven by ``scripts/agents/run_pipeline_test.py``. This directory held an
older copy of all six agents (last changed 2026-05-13) with a different command-line contract, and
this orchestrator ran those copies, so the pipeline had two sources of truth (research study
2026-10-15, section 5.1; research item 1). The old copies are parked in ``sentientia/_retired-2026-05-13/``.

This file keeps the old command working by forwarding to the current runner:

    python sentientia/run_pipeline.py content/sops/X.pdf [--target-course-id N] [--dry-run] [--local-fallback]
        -> python scripts/agents/run_pipeline_test.py --input content/sops/X.pdf

Every stage runs in mock mode (no Anthropic, ElevenLabs or Moodle call), which is the old
``--local-fallback`` / ``--dry-run`` behaviour. A live run is never started from here: run the
current runner with ``--live --confirm`` yourself, after the [CONFIRM] the cost rules require.
"""
from __future__ import annotations

import subprocess
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent
RUNNER = REPO_ROOT / "scripts" / "agents" / "run_pipeline_test.py"

# Old options that only selected mock behaviour, or that the current runner fixes itself.
MOCK_ONLY = {"--local-fallback", "--dry-run", "--batch"}
TAKES_VALUE = {"--target-course-id", "--local-fallback-stages"}


def main(argv: list[str]) -> int:
    print("NOTE: sentientia/run_pipeline.py is retired; forwarding to scripts/agents/run_pipeline_test.py "
          "(mock mode). See the docstring.", file=sys.stderr)
    forwarded: list[str] = []
    i = 0
    while i < len(argv):
        arg = argv[i]
        if arg in ("--live", "--confirm"):
            print("refused: a live run is not started through the retired entry point; run "
                  "scripts/agents/run_pipeline_test.py --live --confirm after the [CONFIRM].", file=sys.stderr)
            return 2
        if arg in TAKES_VALUE:
            i += 2
            continue
        if arg in MOCK_ONLY or arg.startswith("--local-fallback"):
            i += 1
            continue
        if not arg.startswith("-") and not any(a == "--input" for a in forwarded):
            forwarded += ["--input", arg]
        i += 1
    return subprocess.run([sys.executable, str(RUNNER), *forwarded], check=False).returncode


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
