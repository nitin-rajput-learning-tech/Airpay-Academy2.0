"""Append-only JSONL audit log with a sha256 hash chain.

Every entry carries: seq, run_id, UTC time, actor, mode, event, details,
prev_sha256 and its own sha256 = sha256(prev_sha256 + canonical JSON of the
entry without the sha256 field). The final chain value is the sha256 of the
last entry; verify_chain() recomputes it from the file.
"""
from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from .common import canonical_json, sha256_text

GENESIS = "0" * 64


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat(timespec="seconds").replace("+00:00", "Z")


class AuditLog:
    """Writes one JSON line per event; the file is only ever opened for append."""

    def __init__(self, path: Path, run_id: str, actor: str, mode: str):
        self.path = path
        self.run_id = run_id
        self.actor = actor
        self.mode = mode
        self.prev_sha256 = GENESIS
        self.seq = 0
        self.path.parent.mkdir(parents=True, exist_ok=True)

    @property
    def chain_sha256(self) -> str:
        return self.prev_sha256

    def record(self, event: str, **details: Any) -> dict[str, Any]:
        """Append an entry and advance the chain."""
        entry: dict[str, Any] = {
            "seq": self.seq,
            "run_id": self.run_id,
            "ts_utc": utc_now(),
            "actor": self.actor,
            "mode": self.mode,
            "event": event,
            "details": details,
            "prev_sha256": self.prev_sha256,
        }
        entry["sha256"] = sha256_text(self.prev_sha256 + canonical_json(entry))
        with self.path.open("a", encoding="utf-8", newline="\n") as handle:
            handle.write(json.dumps(entry, ensure_ascii=True, sort_keys=True) + "\n")
        self.prev_sha256 = entry["sha256"]
        self.seq += 1
        return entry


def verify_chain(path: Path) -> tuple[bool, int, str]:
    """Recompute the chain over a log file: (valid, entries, final sha256)."""
    prev = GENESIS
    count = 0
    for line in path.read_text(encoding="utf-8").splitlines():
        if not line.strip():
            continue
        entry = json.loads(line)
        claimed = entry.pop("sha256", None)
        if entry.get("prev_sha256") != prev:
            return False, count, prev
        expected = sha256_text(prev + canonical_json(entry))
        if claimed != expected:
            return False, count, prev
        prev = expected
        count += 1
    return True, count, prev
