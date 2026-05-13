#!/usr/bin/env python3
r"""
Tenant-guard linter for Airpay external WS classes.

Scans every PHP file under `moodle-enhancement/local/airpay_*/classes/external/`
and reports any file that:

  (a) calls `require_capability(...)` at `\context_system::instance()`
  (b) AND does NOT also call any of the tenant helper methods:
        \local_airpay_core\tenant::require_access()
        \local_airpay_core\tenant::require_path_access()
        \local_airpay_core\tenant::sql_filter()
        \local_airpay_core\tenant::path_filter()
        \local_airpay_core\tenant::viewer_can_access()

This is the architectural prevention layer recommended by the Phase 8.1
re-audit. 10 of 11 blocking findings shared the same shape — a
system-context cap check without a tenant equality check. The audit's
recommendation: make the guard non-negotiable for new code. This
linter is the mechanical enforcement.

USAGE

  python moodle-enhancement/deploy/lint_tenant_guard.py
    -- scans defaults; exits 0 if all clean, 1 if any violations.

  python moodle-enhancement/deploy/lint_tenant_guard.py --path <dir>
    -- scan a specific directory.

  python moodle-enhancement/deploy/lint_tenant_guard.py --json
    -- emit machine-readable JSON for CI integration.

WHITELIST

  Some externals legitimately operate only on the caller's OWN data
  (e.g. cart::add_item, cart::get_cart). These don't need a tenant
  guard because the data is keyed by userid. Whitelist via the
  `# tenant-guard: own-data` comment near the top of the file, or
  add the file to TENANT_GUARD_WHITELIST.

EXIT CODES
  0  every external passes the rule (clean code)
  1  one or more violations found
  2  invalid arguments
"""

from __future__ import annotations

import argparse
import io
import json
import re
import sys
from dataclasses import dataclass
from pathlib import Path


# Lines that count as a system-context cap check.
CAP_CHECK_PATTERNS = [
    re.compile(r"require_capability\s*\(\s*[\"'].+[\"']\s*,\s*"
                r"\\?context_system::instance\(\)"),
    # validate_context called with a system context.
    re.compile(r"validate_context\s*\(\s*\\?context_system::instance\(\)"),
]

# Lines that count as a tenant guard.
TENANT_GUARD_PATTERNS = [
    re.compile(r"\\local_airpay_core\\tenant::require_access"),
    re.compile(r"\\local_airpay_core\\tenant::require_path_access"),
    re.compile(r"\\local_airpay_core\\tenant::sql_filter"),
    re.compile(r"\\local_airpay_core\\tenant::path_filter"),
    re.compile(r"\\local_airpay_core\\tenant::viewer_can_access"),
    # Legacy inline pattern — still satisfies the spirit of the rule.
    # (We're back-porting these, but they're not violations.)
    re.compile(r"open_path"),
]

# Files that legitimately have no tenant guard because they operate
# only on the caller's own data (keyed by userid).
TENANT_GUARD_WHITELIST = {
    "local/airpay_cart/classes/external/add_item.php",
    "local/airpay_cart/classes/external/remove_item.php",
    "local/airpay_cart/classes/external/get_cart.php",
    "local/airpay_cart/classes/external/checkout.php",
    # Note: get_order + refund_order are NOT in the whitelist because
    # they can be called for OTHER users' orders (admin path).
    "local/airpay_emails/classes/external/rule_api.php",
    # ↑ rule_api operates on platform-wide rule definitions, not
    # tenant-scoped data; admin-only via :manage cap, no resource path.
}

# Inline comment opt-out (per-file annotation).
INLINE_OPTOUT = re.compile(r"#\s*tenant-guard:\s*own-data")


@dataclass
class FileResult:
    relpath: str
    has_cap_check: bool
    has_tenant_guard: bool
    cap_check_lines: list[int]

    @property
    def violates_rule(self) -> bool:
        return self.has_cap_check and not self.has_tenant_guard


def scan_file(filepath: Path, project_root: Path) -> FileResult:
    """Inspect one external WS file."""
    relpath = str(filepath.relative_to(project_root)).replace("\\", "/")
    text = filepath.read_text(encoding="utf-8")

    if INLINE_OPTOUT.search(text):
        return FileResult(relpath, False, True, [])  # treat as guarded

    cap_lines: list[int] = []
    for i, line in enumerate(text.splitlines(), 1):
        for pat in CAP_CHECK_PATTERNS:
            if pat.search(line):
                cap_lines.append(i)

    has_cap = bool(cap_lines)
    has_guard = any(p.search(text) for p in TENANT_GUARD_PATTERNS)
    return FileResult(relpath, has_cap, has_guard, cap_lines)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Tenant-guard linter for Airpay external WS classes"
    )
    parser.add_argument(
        "--path",
        default=None,
        help="Directory to scan (default: project_root/moodle-enhancement/local)",
    )
    parser.add_argument(
        "--json",
        action="store_true",
        help="Emit JSON output instead of human-readable",
    )
    parser.add_argument(
        "--show-whitelist",
        action="store_true",
        help="Print the whitelist and exit",
    )
    args = parser.parse_args(argv)

    if args.show_whitelist:
        for p in sorted(TENANT_GUARD_WHITELIST):
            print(p)
        return 0

    project_root = Path(__file__).resolve().parents[2]

    if args.path:
        scan_root = Path(args.path)
    else:
        scan_root = project_root / "moodle-enhancement" / "local"

    if not scan_root.exists():
        print(f"Scan root does not exist: {scan_root}", file=sys.stderr)
        return 2

    results: list[FileResult] = []
    for f in scan_root.rglob("classes/external/*.php"):
        if not f.is_file():
            continue
        if "airpay_core" in f.parts:
            continue  # the helper itself
        result = scan_file(f, project_root)
        results.append(result)

    violations = [
        r for r in results
        if r.violates_rule and r.relpath not in
           {f"moodle-enhancement/{p}" for p in TENANT_GUARD_WHITELIST}
    ]

    if args.json:
        payload = {
            "scanned": len(results),
            "passed":  len(results) - len(violations),
            "violations": [
                {
                    "file":      v.relpath,
                    "cap_lines": v.cap_check_lines,
                }
                for v in violations
            ],
        }
        print(json.dumps(payload, indent=2))
        return 0 if not violations else 1

    print(f"Scanned: {len(results)} external WS class files")
    print(f"Passed:  {len(results) - len(violations)}")
    print(f"Violations: {len(violations)}")

    if violations:
        print()
        print("Files with a system-context capability check but no tenant guard:")
        for v in violations:
            lines = ", ".join(str(n) for n in v.cap_check_lines)
            print(f"  - {v.relpath}")
            print(f"      capability check at line(s): {lines}")
            print(f"      add ONE of:")
            print(f"        \\local_airpay_core\\tenant::require_access(...)")
            print(f"        \\local_airpay_core\\tenant::require_path_access(...)")
            print(f"        \\local_airpay_core\\tenant::sql_filter(...) in your query")
            print(f"        \\local_airpay_core\\tenant::path_filter(...) in your query")
            print(f"      OR if this external is legitimately own-data-only,")
            print(f"      add `# tenant-guard: own-data` comment near the top")
            print(f"      OR add the relative path to TENANT_GUARD_WHITELIST")
            print(f"      in moodle-enhancement/deploy/lint_tenant_guard.py")
        print()
        print(f"Fix the {len(violations)} violation(s) before commit.")
        return 1

    print()
    print("All external WS classes pass the tenant-guard rule.")
    return 0


if sys.platform == "win32":
    try:
        sys.stdout.reconfigure(encoding="utf-8")
        sys.stderr.reconfigure(encoding="utf-8")
    except (AttributeError, OSError):
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8",
                                       errors="replace", line_buffering=True)


if __name__ == "__main__":
    sys.exit(main())
