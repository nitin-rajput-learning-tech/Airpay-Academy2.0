"""
SENTIENTIA Agent 6 — Moodle Upload (Phase E / Wave-pipeline)
============================================================

Stage 6 (final) of the SOP -> SCORM pipeline. Reads a SCORM ZIP produced
by Agent 5 (``content/scorm-output/<course>-scorm.zip``) and uploads it to
Moodle as a SCORM activity in a target course.

This is the **only** agent that mutates LIVE production state, so per
``.claude/rules/api.md`` it is **[CONFIRM] required**. The gate is the
``--confirm`` flag (matching Agent 4): without it the agent runs in
offline mock mode and never opens a socket.

Pipeline (live mode):
1. Validate the ZIP (belt-and-braces re-check — manifest at root, size,
   not corrupt).
2. ``POST {MOODLE_URL}/webservice/upload.php`` -> draft itemid. This is a
   stock Moodle endpoint and works today.
3. ``--stage-only`` stops here (file is in the draft area for ops to
   inspect). Otherwise call the custom WS
   ``local_airpay_courses_create_scorm_activity`` to attach the package
   as a SCORM activity in the target course.

Server-side dependency: Moodle exposes **no stock** web service to create
a ``mod_scorm`` activity from a draft file, so full activity creation
depends on the custom ``local_airpay_courses_create_scorm_activity`` WS
being registered. Until then, ``--stage-only`` is the complete live path;
the full path surfaces Moodle's "function not available" cleanly.

Modes
-----
- **Mock (default)** — no network. Validates + prints the planned
  requests and a deterministic plan. Safe for CI / rehearsal.
- **Live (``--confirm``)** — performs the real uploads. Requires
  ``MOODLE_URL`` + ``MOODLE_TOKEN`` in the environment (.env).

CLI
---
::

    # mock (no network):
    python scripts/agents/agent6_moodle_upload.py \\
        content/scorm-output/SAMPLE-SOP-scorm.zip --target-course-id 42

    # stage-only LIVE upload to the draft area ([CONFIRM]):
    python scripts/agents/agent6_moodle_upload.py \\
        content/scorm-output/SAMPLE-SOP-scorm.zip --target-course-id 42 \\
        --stage-only --confirm

    # full LIVE upload + activity creation ([CONFIRM]):
    python scripts/agents/agent6_moodle_upload.py \\
        content/scorm-output/SAMPLE-SOP-scorm.zip --target-course-id 42 \\
        --confirm

Exit codes: 0 success, 1 validation failure, 2 I/O failure,
3 API / config failure in live mode.

Security: MOODLE_TOKEN is never logged. Only the function name + course
id appear in output.
"""

from __future__ import annotations

import argparse
import hashlib
import io
import os
import sys
import zipfile
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Callable

if sys.platform == "win32":  # pragma: no cover - platform specific
    try:
        sys.stdout.reconfigure(encoding="utf-8")
        sys.stderr.reconfigure(encoding="utf-8")
    except (AttributeError, OSError):
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8",
                                      errors="replace", line_buffering=True)
        sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding="utf-8",
                                      errors="replace", line_buffering=True)

MAX_ZIP_BYTES = 50 * 1024 * 1024     # 50 MB (matches Agent 5)
UPLOAD_PATH = "/webservice/upload.php"
REST_PATH = "/webservice/rest/server.php"
CREATE_SCORM_WS = "local_airpay_courses_create_scorm_activity"


@dataclass
class UploadResult:
    zip_path: Path
    target_course_id: int
    sha256: str = ""
    size_bytes: int = 0
    draft_itemid: int | None = None
    activity_url: str | None = None
    staged_only: bool = False
    mock: bool = False
    errors: list[str] = field(default_factory=list)

    @property
    def passes(self) -> bool:
        if self.errors:
            return False
        if self.mock:
            return True
        if self.staged_only:
            return self.draft_itemid is not None
        return self.activity_url is not None


# ─── Validation ──────────────────────────────────────────────────────────


def validate_scorm_zip(zip_path: Path) -> list[str]:
    """Re-validate the ZIP before upload. The file could have changed
    between Agent 5's commit and this upload, so re-check the essentials."""
    if not zip_path.exists():
        return [f"file not found: {zip_path}"]
    if zip_path.suffix.lower() != ".zip":
        return [f"expected .zip, got {zip_path.suffix!r}"]

    errors: list[str] = []
    if zip_path.stat().st_size > MAX_ZIP_BYTES:
        errors.append(
            f"too large: {zip_path.stat().st_size} bytes > {MAX_ZIP_BYTES} cap"
        )
    try:
        with zipfile.ZipFile(zip_path, "r") as z:
            names = set(z.namelist())
            if "imsmanifest.xml" not in names:
                errors.append("imsmanifest.xml not at ZIP root")
            if any(n.endswith("/imsmanifest.xml") for n in names):
                errors.append("imsmanifest.xml is nested in a subfolder")
    except zipfile.BadZipFile as exc:
        errors.append(f"corrupt ZIP: {exc}")
    return errors


def sha256_of(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(8192), b""):
            h.update(chunk)
    return h.hexdigest()


def derive_activity_name(zip_path: Path) -> str:
    """Human activity name derived from the ZIP filename."""
    return zip_path.stem.replace("-scorm", "").replace("-", " ").title()


# ─── Moodle REST calls (real; injectable for tests) ──────────────────────


def call_moodle(function_name: str, params: dict, *, base_url: str,
                token: str, post_fn: Callable[..., Any] | None = None,
                timeout: int = 60) -> Any:
    """Call a Moodle REST web-service function. Raises ``ValueError`` on a
    Moodle ``exception`` envelope. ``post_fn`` defaults to ``requests.post``
    but is injected in tests so the suite never hits the network."""
    if post_fn is None:  # pragma: no cover - live default
        import requests
        post_fn = requests.post

    payload = {
        "wstoken": token,
        "wsfunction": function_name,
        "moodlewsrestformat": "json",
        **params,
    }
    response = post_fn(f"{base_url}{REST_PATH}", data=payload, timeout=timeout)
    response.raise_for_status()
    data = response.json()
    if isinstance(data, dict) and "exception" in data:
        raise ValueError(
            f"Moodle [{function_name}]: {data.get('message', data['exception'])}"
        )
    return data


def upload_to_draft(zip_path: Path, *, base_url: str, token: str,
                    post_fn: Callable[..., Any] | None = None,
                    timeout: int = 300) -> int:
    """Upload the ZIP to Moodle's draft file area via the stock
    ``upload.php`` endpoint. Returns the draft ``itemid`` that the
    activity-creation step references."""
    if post_fn is None:  # pragma: no cover - live default
        import requests
        post_fn = requests.post

    with zip_path.open("rb") as fh:
        response = post_fn(
            f"{base_url}{UPLOAD_PATH}",
            files={"file_1": (zip_path.name, fh)},
            data={"token": token, "filearea": "draft", "itemid": 0},
            timeout=timeout,
        )
    response.raise_for_status()
    data = response.json()
    # upload.php returns a list of file dicts on success, or a dict with
    # an "error" key on failure.
    if isinstance(data, dict) and data.get("error"):
        raise ValueError(f"upload.php error: {data['error']}")
    if isinstance(data, list) and data and "itemid" in data[0]:
        return int(data[0]["itemid"])
    raise ValueError(f"unexpected upload.php response: {data!r}")


def create_scorm_activity(course_id: int, draft_itemid: int, *,
                          activity_name: str, base_url: str, token: str,
                          post_fn: Callable[..., Any] | None = None) -> str:
    """Attach the uploaded draft file as a SCORM activity in the target
    course via the custom ``local_airpay_courses_create_scorm_activity``
    WS. Returns the activity URL. Raises ``ValueError`` if the WS is not
    registered (Moodle reports "function not available")."""
    data = call_moodle(
        CREATE_SCORM_WS,
        {
            "courseid": course_id,
            "draftitemid": draft_itemid,
            "name": activity_name,
        },
        base_url=base_url, token=token, post_fn=post_fn,
    )
    if isinstance(data, dict) and data.get("url"):
        return str(data["url"])
    if isinstance(data, dict) and data.get("cmid"):
        return f"{base_url}/mod/scorm/view.php?id={data['cmid']}"
    raise ValueError(f"unexpected {CREATE_SCORM_WS} response: {data!r}")


# ─── Per-file processing ─────────────────────────────────────────────────


def process_one(zip_path: Path, target_course_id: int, *, confirm: bool,
                stage_only: bool, activity_name: str | None,
                base_url: str | None = None, token: str | None = None,
                post_fn: Callable[..., Any] | None = None) -> UploadResult:
    """Validate + (in live mode) upload one SCORM ZIP. In mock mode no
    network call is made; the planned actions are reported instead."""
    result = UploadResult(
        zip_path=zip_path, target_course_id=target_course_id,
        staged_only=stage_only, mock=not confirm,
    )

    val_errors = validate_scorm_zip(zip_path)
    if val_errors:
        result.errors.extend(val_errors)
        return result

    result.size_bytes = zip_path.stat().st_size
    result.sha256 = sha256_of(zip_path)
    name = activity_name or derive_activity_name(zip_path)
    mode = "stage-only" if stage_only else "upload + activity creation"

    print(f"\n-- {zip_path.name} -> course {target_course_id} --")
    print(f"  size={result.size_bytes // 1024} KB sha256={result.sha256[:16]}... "
          f"activity={name!r} mode={mode}")

    if not confirm:
        # Mock mode — no network. Report the plan deterministically.
        print(f"  [MOCK] POST {UPLOAD_PATH} (draft upload)")
        if not stage_only:
            print(f"  [MOCK] call {CREATE_SCORM_WS}(courseid={target_course_id})")
        print("  [MOCK] no network call made — pass --confirm for a LIVE upload.")
        return result

    # ── LIVE mode from here ([CONFIRM]) ──
    if not base_url or not token:
        result.errors.append(
            "config error — MOODLE_URL and MOODLE_TOKEN required for "
            "--confirm (see .claude/rules/api.md)"
        )
        return result

    print(f"  [CONFIRM] LIVE upload to {base_url} — this mutates production.")
    try:
        result.draft_itemid = upload_to_draft(
            zip_path, base_url=base_url, token=token, post_fn=post_fn)
    except Exception as exc:  # noqa: BLE001 - any failure is reported
        result.errors.append(f"draft upload failed: {exc}")
        return result
    print(f"  draft itemid={result.draft_itemid}")

    if stage_only:
        print("  [STAGE ONLY] file is in the draft area; not attaching.")
        return result

    try:
        result.activity_url = create_scorm_activity(
            target_course_id, result.draft_itemid,
            activity_name=name, base_url=base_url, token=token, post_fn=post_fn)
    except Exception as exc:  # noqa: BLE001
        result.errors.append(f"activity creation failed: {exc}")
        return result
    print(f"  activity: {result.activity_url}")
    return result


# ─── CLI ─────────────────────────────────────────────────────────────────


def _build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="agent6_moodle_upload",
        description="SENTIENTIA Agent 6 — upload a SCORM ZIP to Moodle. "
                    "Default mode is offline/mock; pass --confirm for a "
                    "LIVE upload that mutates production.",
    )
    parser.add_argument("inputs", nargs="+", type=Path,
                        help="One or more SCORM ZIP files (Agent 5 output).")
    parser.add_argument("--target-course-id", type=int, required=True,
                        help="Moodle course id to attach the SCORM activity to.")
    parser.add_argument("--activity-name", default=None,
                        help="Activity name (default: derived from filename).")
    parser.add_argument("--stage-only", action="store_true",
                        help="Upload to the draft area but do NOT attach as an "
                             "activity (lets ops inspect first).")
    parser.add_argument("--confirm", action="store_true",
                        help="Authorise a LIVE upload to Moodle. Without this "
                             "flag the agent runs offline and POSTs nowhere.")
    return parser


def main(argv: list[str] | None = None) -> int:
    args = _build_parser().parse_args(argv)

    base_url = os.getenv("MOODLE_URL")
    token = os.getenv("MOODLE_TOKEN")

    # I/O pre-check so a missing file is exit 2, not a validation error.
    for path in args.inputs:
        if not path.exists():
            print(f"agent6: I/O error — {path} does not exist", file=sys.stderr)
            return 2

    if args.confirm and (not base_url or not token):
        print(
            "agent6: config error — --confirm requires MOODLE_URL and "
            "MOODLE_TOKEN in the environment. Drop --confirm to run in "
            "offline mock mode.",
            file=sys.stderr,
        )
        return 3

    print("SENTIENTIA Agent 6 — Moodle Upload")
    print(f"  mode={'LIVE [CONFIRM]' if args.confirm else 'mock'} "
          f"stage_only={args.stage_only} target_course={args.target_course_id}")

    succeeded = failed = 0
    worst_exit = 0
    for path in args.inputs:
        result = process_one(
            path, args.target_course_id, confirm=args.confirm,
            stage_only=args.stage_only, activity_name=args.activity_name,
            base_url=base_url, token=token,
        )
        for e in result.errors:
            print(f"  ERR:  {e}")
        if result.passes:
            succeeded += 1
        else:
            failed += 1
            # Distinguish validation (1) from API/config (3) for exit code.
            if any("config error" in e or "failed" in e for e in result.errors):
                worst_exit = max(worst_exit, 3)
            else:
                worst_exit = max(worst_exit, 1)

    print(f"\n-- Summary: {succeeded} ok, {failed} failed --")
    return 0 if failed == 0 else worst_exit


if __name__ == "__main__":
    sys.exit(main())
