# Retired 2026-10-22: the older copy of the SENTIENTIA agents

These are the 2026-05-13 versions of the six pipeline agents, their orchestrator (`run_pipeline.py`) and the
regression runner. They were moved here, not deleted, when the pipeline was reduced to one implementation (research
study 2026-10-15, section 5.1; research item 1, approved by Nitin on 2026-10-21).

**The pipeline is `scripts/agents/`.** It has agents 1-6, covered in CI by `tests/agents/`, and is driven by
`scripts/agents/run_pipeline_test.py`. Every stage is mock by default; paid or live calls need `--live --confirm`.
`sentientia/run_pipeline.py` now forwards the old command line to it.

Why these copies were retired:
- They had a different command-line contract (`--output-dir`, `--batch`, `--local-fallback`), so no file was identical
  between the two trees.
- Agent 4's ElevenLabs path here was a skeleton ("not yet wired").
- Agent 3 carried a Gamma seam that no current code uses.
- No test covered them.

Nothing references them any more. Delete this folder once nobody needs to compare against them (that needs Nitin's
ok).
