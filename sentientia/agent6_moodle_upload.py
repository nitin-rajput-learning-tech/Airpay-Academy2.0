"""
SENTIENTIA Agent 6 — Moodle Upload
===================================

Reads `content/scorm-output/<course>-scorm.zip` (Agent 5 output).
Uploads the SCORM package to Moodle as a SCORM activity in a target
course.

Architectural contract (per SUPP-C Section 3.6 + Section 2):
1. Reads input from disk; writes nothing to disk; touches live Moodle.
2. Validation gates: SCORM ZIP must exist + be valid + < 50 MB.
3. [CONFIRM] gate is MANDATORY in non-dry-run mode because this is
   the only agent that mutates production state.

This agent talks to Moodle's REST API. Per `.claude/rules/api.md`:
- WRITE functions need [CONFIRM].
- Use core_files_upload + module-instance creation via the standard
  SCORM activity creation pattern.
- Never log MOODLE_TOKEN.

USAGE
-----

Dry run (validate ZIP, build request, do not POST):
    python sentientia/agent6_moodle_upload.py \\
        content/scorm-output/posh-2024-scorm.zip \\
        --target-course-id 42 --dry-run

Live run ([CONFIRM] gate before POST):
    python sentientia/agent6_moodle_upload.py \\
        content/scorm-output/posh-2024-scorm.zip \\
        --target-course-id 42

Stage-only (upload the file to draft area but do NOT attach as
activity yet — useful when ops wants to inspect before committing):
    python sentientia/agent6_moodle_upload.py \\
        content/scorm-output/posh-2024-scorm.zip \\
        --target-course-id 42 --stage-only

ENVIRONMENT
-----------

Reads from `.env` at project root (never committed):
    MOODLE_URL       — e.g. https://www.airpay.academy
    MOODLE_TOKEN     — web service token with the required scopes:
                       core_files_upload
                       mod_scorm_view_scorm  (read)
                       (and a write-scope for activity creation;
                        Moodle exposes this through
                        core_course_create_courses or a dedicated
                        SCORM-activity creation endpoint depending
                        on Moodle version)

STATUS
------

Skeleton with full payload + validation + [CONFIRM] gate + dry-run
mode. The actual HTTP POST is gated and not invoked in this revision.
Activation is a small diff once the [CONFIRM] gate is granted per
master-doc Section 7 + Decision 13.2.

For end-to-end smoke testing without touching production, --dry-run
prints the request payload and exits.
"""

from __future__ import annotations

import argparse
import hashlib
import io
import json
import os
import sys
import zipfile
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


MAX_ZIP_BYTES = 50 * 1024 * 1024     # 50 MB per SUPP-C
AGENT_VERSION = "1.0"
AGENT_NAME = "sentientia_agent6_moodle_upload"


@dataclass
class UploadResult:
    zip_path: Path
    target_course_id: int
    sha256: str = ""
    size_bytes: int = 0
    draft_itemid: int | None = None     # filled after upload
    activity_url: str | None = None     # filled after activity creation
    errors: list[str] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)
    dry_run: bool = False

    @property
    def passes(self) -> bool:
        return not self.errors and (self.dry_run or self.activity_url is not None)


# ─── Input validation ──────────────────────────────────────────────────


def validate_zip(zip_path: Path) -> list[str]:
    """Re-run Agent 5's validation. Belt and braces — the file could
    have been tampered with between Agent 5's commit and Agent 6's
    upload, especially in batch runs."""
    errors: list[str] = []
    if not zip_path.exists():
        return [f"file not found: {zip_path}"]
    if zip_path.suffix.lower() != ".zip":
        return [f"expected .zip, got {zip_path.suffix}"]

    size = zip_path.stat().st_size
    if size > MAX_ZIP_BYTES:
        errors.append(f"too large: {size} bytes > {MAX_ZIP_BYTES} cap")

    try:
        with zipfile.ZipFile(zip_path, "r") as z:
            names = set(z.namelist())
            if "imsmanifest.xml" not in names:
                errors.append("imsmanifest.xml not at ZIP root")
            if any(n.endswith("/imsmanifest.xml") for n in names):
                errors.append("imsmanifest.xml is nested in a subfolder")
    except zipfile.BadZipFile as e:
        errors.append(f"corrupt ZIP: {e}")

    return errors


def sha256_of(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(8192), b""):
            h.update(chunk)
    return h.hexdigest()


# ─── Moodle REST calls (skeleton) ──────────────────────────────────────


def call_moodle(function_name: str, params: dict, *, write: bool = False,
                 timeout: int = 60) -> dict | list:
    """Wrapper over Moodle's REST endpoint.

    Per `.claude/rules/api.md`, WRITE functions require [CONFIRM] from
    the caller — this function does NOT enforce that gate (the caller
    must); but the `write` parameter is preserved as a hint for the
    eventual live integration to do additional defensive checks.

    SKELETON — not yet wired.
    """
    base_url = os.environ.get("MOODLE_URL")
    token = os.environ.get("MOODLE_TOKEN")
    if not base_url or not token:
        raise RuntimeError(
            "MOODLE_URL and MOODLE_TOKEN required in .env "
            "(see .claude/rules/api.md)"
        )

    # PLACEHOLDER for the actual HTTP call. Activated after [CONFIRM]:
    #
    #   import requests
    #   payload = {
    #       "wstoken":            token,
    #       "wsfunction":         function_name,
    #       "moodlewsrestformat": "json",
    #       **params,
    #   }
    #   response = requests.post(
    #       f"{base_url}/webservice/rest/server.php",
    #       data=payload,
    #       timeout=timeout,
    #   )
    #   response.raise_for_status()
    #   data = response.json()
    #   if isinstance(data, dict) and "exception" in data:
    #       raise ValueError(
    #           f"Moodle [{function_name}]: "
    #           f"{data.get('message', data['exception'])}"
    #       )
    #   return data

    raise RuntimeError(
        "call_moodle is a skeleton — live HTTP not yet wired. See "
        ".claude/rules/api.md and SUPP-C Section 3.6 for activation."
    )


def upload_scorm_to_draft(zip_path: Path, *, dry_run: bool) -> int:
    """Upload the SCORM ZIP to Moodle's draft file area. Returns the
    itemid that subsequent module-creation calls reference.

    Endpoint: POST {MOODLE_URL}/webservice/upload.php
    Auth: form param 'token' (NOT the REST wstoken)
    """
    if dry_run:
        print(f"  [DRY RUN] Would upload {zip_path.name} to /webservice/upload.php")
        return 0

    # PLACEHOLDER — activated after [CONFIRM]:
    #
    #   base_url = os.environ.get('MOODLE_URL')
    #   token = os.environ.get('MOODLE_TOKEN')
    #   with zip_path.open('rb') as f:
    #       response = requests.post(
    #           f'{base_url}/webservice/upload.php',
    #           files={'file_1': (zip_path.name, f)},
    #           data={'token': token, 'filearea': 'draft', 'itemid': 0},
    #           timeout=300,   # large uploads
    #       )
    #   response.raise_for_status()
    #   data = response.json()
    #   if isinstance(data, list) and data:
    #       return int(data[0]['itemid'])
    #   raise ValueError(f"Unexpected upload response: {data}")

    raise RuntimeError(
        "upload_scorm_to_draft is a skeleton — live HTTP not yet wired"
    )


def create_scorm_activity(course_id: int, draft_itemid: int,
                            *, course_module_name: str,
                            dry_run: bool) -> str:
    """Create a SCORM activity attached to the target course, sourced
    from the draft itemid uploaded earlier. Returns the activity URL.

    Moodle 4.5 exposes this through the standard module-creation pattern.
    """
    if dry_run:
        print(f"  [DRY RUN] Would create SCORM activity '{course_module_name}' "
              f"in course {course_id} from draft itemid {draft_itemid}")
        return f"<dry-run-url-for-course-{course_id}>"

    # PLACEHOLDER — activated after [CONFIRM]:
    #
    #   # Moodle doesn't expose a public WS for direct activity creation
    #   # in 4.5; the production approach uses a custom endpoint in
    #   # local_airpay_courses that wraps the internal API. Activate
    #   # via local_airpay_courses\create_scorm_activity once that
    #   # local endpoint is registered.

    raise RuntimeError(
        "create_scorm_activity is a skeleton — needs the corresponding "
        "local_airpay_courses WS to be registered (see SUPP-C Section "
        "3.6 + future engineering pass)"
    )


# ─── Confirm gate ───────────────────────────────────────────────────────


def confirm_call(zip_name: str, course_id: int, mode: str) -> bool:
    """[CONFIRM] gate. MANDATORY for live mode because this is the only
    SENTIENTIA agent that mutates production state."""
    if not sys.stdin.isatty():
        print(
            "REFUSING: stdin is not a tty. The [CONFIRM] gate must be "
            "answered by a human, not piped.",
            file=sys.stderr,
        )
        return False
    print(f"\n  About to upload SCORM to LIVE Moodle:")
    print(f"    file:    {zip_name}")
    print(f"    course:  id={course_id}")
    print(f"    mode:    {mode}")
    print(f"  This MUTATES production state. Once attached, the SCORM ")
    print(f"  activity is visible to enrolled learners.")
    answer = input("  Proceed? [type 'yes' exactly to confirm]: ").strip()
    return answer == "yes"


# ─── Main per-file processing ───────────────────────────────────────────


def process_one(zip_path: Path, target_course_id: int,
                 *, dry_run: bool, stage_only: bool,
                 activity_name: str | None) -> UploadResult:
    result = UploadResult(
        zip_path=zip_path,
        target_course_id=target_course_id,
        dry_run=dry_run,
    )

    print(f"\n-- Uploading {zip_path.name} -> course {target_course_id} --")

    # Validate ZIP.
    val_errors = validate_zip(zip_path)
    if val_errors:
        result.errors.extend(val_errors)
        return result

    result.size_bytes = zip_path.stat().st_size
    result.sha256 = sha256_of(zip_path)
    print(f"  Size:   {result.size_bytes // 1024} KB")
    print(f"  SHA256: {result.sha256[:16]}...")

    # Activity name.
    if not activity_name:
        activity_name = zip_path.stem.replace("-scorm", "").replace("-", " ").title()
    print(f"  Activity name: {activity_name}")

    mode = "stage-only" if stage_only else "full upload + activity creation"

    # [CONFIRM] gate (skipped on dry-run).
    if not dry_run:
        if not confirm_call(zip_path.name, target_course_id, mode):
            result.errors.append("ABORTED by operator at [CONFIRM] gate")
            return result

    # Step 1: upload to draft file area.
    try:
        result.draft_itemid = upload_scorm_to_draft(zip_path, dry_run=dry_run)
    except RuntimeError as e:
        result.errors.append(f"upload failed: {e}")
        return result
    print(f"  Step 1 OK: draft itemid={result.draft_itemid}")

    if stage_only:
        print(f"  [STAGE ONLY] Not attaching as activity. Stop here.")
        # Stage-only is success; record the activity URL as None.
        return result

    # Step 2: create the SCORM activity in the target course.
    try:
        result.activity_url = create_scorm_activity(
            target_course_id, result.draft_itemid,
            course_module_name=activity_name, dry_run=dry_run,
        )
    except RuntimeError as e:
        result.errors.append(f"activity creation failed: {e}")
        return result
    print(f"  Step 2 OK: {result.activity_url}")

    return result


# ─── CLI ───────────────────────────────────────────────────────────────


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="SENTIENTIA Agent 6 - Moodle Upload"
    )
    parser.add_argument(
        "inputs",
        nargs="+",
        help="One or more SCORM ZIP files (Agent 5 output)",
    )
    parser.add_argument(
        "--target-course-id",
        type=int,
        required=True,
        help="Moodle course id to attach the SCORM activity to",
    )
    parser.add_argument(
        "--activity-name",
        default=None,
        help="Custom name for the SCORM activity (default: derived from filename)",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Validate ZIP + build request payload, but do NOT POST to Moodle",
    )
    parser.add_argument(
        "--stage-only",
        action="store_true",
        help="Upload to draft area but DO NOT attach as activity. Lets ops "
        "inspect the file in Moodle's file manager before committing.",
    )
    args = parser.parse_args(argv)

    inputs = [Path(p) for p in args.inputs]
    for p in inputs:
        if not p.exists():
            print(f"FATAL: {p} does not exist", file=sys.stderr)
            return 2

    succeeded = 0
    failed = 0
    for p in inputs:
        result = process_one(p, args.target_course_id,
                              dry_run=args.dry_run,
                              stage_only=args.stage_only,
                              activity_name=args.activity_name)
        for e in result.errors:
            print(f"  ERR:  {e}")
        if result.passes:
            succeeded += 1
        else:
            failed += 1

    print(f"\n-- Summary: {succeeded} uploaded, {failed} failed --")
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
