#!/usr/bin/env python3
"""
Cutover Smoke Test — Sentientia LMS / Airpay Academy
=====================================================

Automated smoke-test harness for the Moodle 5.1 -> 5.2 cutover. Verifies
that the upgraded site responds correctly across eight surfaces:

    1. Login page renders and contains a CSRF logintoken
    2. Dashboard route responds (200 OK or 303 to login)
    3. Course catalog REST endpoint returns a valid list
    4. SCORM REST endpoint responds for known courses
    5. BizLMS tenant detection works for ids 1 / 77 / 177
    6. Dark mode toggle assets are present in the theme bundle
    7. Navbar + footer markers render on a public surface
    8. Key REST API endpoints (site_info, users, courses) are healthy

Output: JUnit XML at tests/junit/cutover-smoke.xml — consumed by CI and
by the cutover runbook's go/no-go gate.

Safety:
- --target <url> is REQUIRED.
- Hostname containing "airpay.academy" is REFUSED at parse time. Cutover
  smoke-tests run against the upgrade staging URL (typically a private
  Cloudflare tunnel or RDS-attached staging host), never against the live
  customer-facing domain.
- MOODLE_TOKEN is read from .env. Never logged, never echoed.
- All HTTP requests are read-only (GET + REST READ functions). No write
  endpoints are exercised.

Usage:
    python scripts/cutover-smoke-test.py --target http://localhost:8080/moodle
    python scripts/cutover-smoke-test.py --target https://staging.airpay-academy.in
    # Refused:
    python scripts/cutover-smoke-test.py --target https://www.airpay.academy

Exit codes:
    0  All eight tests passed.
    1  One or more tests failed (JUnit XML still written).
    2  Configuration error (bad --target, missing .env, refused hostname).

Standard-library only — no pip dependencies required at runtime.
"""

from __future__ import annotations

import argparse
import json
import os
import re
import socket
import ssl
import sys
import time
import traceback
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from dataclasses import dataclass, field
from pathlib import Path
from typing import Callable


# ─── Configuration ─────────────────────────────────────────────────────

# Hostnames that MUST NOT receive smoke-test traffic. Matching is
# case-insensitive substring on the URL host component.
REFUSED_HOSTS = (
    "airpay.academy",
    "www.airpay.academy",
)

# JUnit XML output path (committed to .gitignore? no — empty dir lives under tests/).
DEFAULT_JUNIT_PATH = Path("tests/junit/cutover-smoke.xml")

# Per-request timeout.
HTTP_TIMEOUT_SECONDS = 15

# Transport-error retries. A cutover often catches the web server mid-restart
# (Apache graceful, opcache priming, DB reconnect), so a single connection
# refusal is not yet a failure. Retry transport-level errors only — never an
# HTTP error status (a 500 is a real result, not a transient blip).
HTTP_MAX_ATTEMPTS = 3
HTTP_RETRY_BACKOFF_SECONDS = (0.5, 1.5)  # waits between attempts 1->2, 2->3

# Known BizLMS tenant ids — every multi-tenant query MUST be scoped to one of these.
KNOWN_TENANTS = (1, 77, 177)


# ─── Result types ──────────────────────────────────────────────────────


@dataclass
class TestResult:
    name: str
    classname: str = "cutover_smoke_test"
    passed: bool = True
    skipped: bool = False
    skip_reason: str = ""
    failure_message: str = ""
    failure_detail: str = ""
    error_message: str = ""
    error_detail: str = ""
    duration_seconds: float = 0.0
    stdout: str = ""

    def mark_failure(self, message: str, detail: str = "") -> None:
        self.passed = False
        self.failure_message = message
        self.failure_detail = detail

    def mark_error(self, message: str, detail: str = "") -> None:
        self.passed = False
        self.error_message = message
        self.error_detail = detail

    def mark_skipped(self, reason: str) -> None:
        self.skipped = True
        self.skip_reason = reason


@dataclass
class SmokeRunContext:
    """Per-run context — base URL, token, parsed host, results list."""

    base_url: str
    parsed_host: str
    token: str = field(repr=False, default="")
    results: list[TestResult] = field(default_factory=list)
    insecure_tls: bool = False

    def has_token(self) -> bool:
        return bool(self.token)


# ─── .env loader (stdlib only) ─────────────────────────────────────────


def load_dotenv(path: Path = Path(".env")) -> dict[str, str]:
    """Tiny .env parser. Returns a dict; missing file returns empty dict.

    Supports KEY=VALUE pairs and # comments. Quotes around values are
    stripped. Does NOT mutate os.environ — the caller decides what to
    merge.
    """
    if not path.exists():
        return {}
    env: dict[str, str] = {}
    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#"):
            continue
        if "=" not in line:
            continue
        key, _, value = line.partition("=")
        key = key.strip()
        value = value.strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in ("'", '"'):
            value = value[1:-1]
        env[key] = value
    return env


# ─── HTTP plumbing ─────────────────────────────────────────────────────


def _ssl_context(insecure: bool) -> ssl.SSLContext | None:
    if not insecure:
        return None
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE
    return ctx


class TransportError(Exception):
    """Raised by http_get / http_post_form when the request never reached the
    application layer (connection refused, DNS, TLS, timeout). Distinct from
    an HTTP error response — those are returned as (status, body)."""


def _urlopen_with_retry(req: urllib.request.Request, *,
                        ctx: SmokeRunContext) -> tuple[int, str]:
    """Execute a prepared Request, returning (status, body).

    HTTP error statuses (4xx/5xx) are returned as (status, body) — they are
    real application results, never retried. Transport-level failures
    (connection refused, DNS, TLS, timeout) are retried up to
    HTTP_MAX_ATTEMPTS times with backoff; if every attempt fails, the last
    one is raised as a TransportError carrying the attempt count.
    """
    label = f"{req.get_method()} {req.full_url}"
    last_reason = ""
    for attempt in range(1, HTTP_MAX_ATTEMPTS + 1):
        try:
            with urllib.request.urlopen(
                req, timeout=HTTP_TIMEOUT_SECONDS,
                context=_ssl_context(ctx.insecure_tls),
            ) as resp:
                return resp.status, resp.read().decode("utf-8", errors="replace")
        except urllib.error.HTTPError as e:
            # A real HTTP response — surface it, do not retry.
            body = e.read().decode("utf-8", errors="replace") if e.fp else ""
            return e.code, body
        except urllib.error.URLError as e:
            last_reason = str(e.reason)
        except (socket.timeout, TimeoutError):
            last_reason = f"timeout after {HTTP_TIMEOUT_SECONDS}s"
        # Transport failure — back off and retry unless this was the last attempt.
        if attempt < HTTP_MAX_ATTEMPTS:
            backoff_idx = min(attempt - 1, len(HTTP_RETRY_BACKOFF_SECONDS) - 1)
            time.sleep(HTTP_RETRY_BACKOFF_SECONDS[backoff_idx])
    raise TransportError(
        f"{label}: {last_reason} (after {HTTP_MAX_ATTEMPTS} attempts)"
    )


def http_get(url: str, *, ctx: SmokeRunContext) -> tuple[int, str]:
    """GET request. Returns (status_code, body_text). HTTP error statuses
    become (status, body). Transport-level failures (connection refused,
    DNS, timeout) raise TransportError after HTTP_MAX_ATTEMPTS retries."""
    req = urllib.request.Request(
        url,
        headers={
            "User-Agent": "sentientia-cutover-smoke/1.0",
            "Accept": "text/html,application/json",
        },
    )
    return _urlopen_with_retry(req, ctx=ctx)


def http_post_form(url: str, data: dict[str, str], *,
                   ctx: SmokeRunContext) -> tuple[int, str]:
    """POST form-encoded. Returns (status, body). Transport failures raise
    TransportError after retries (see _urlopen_with_retry)."""
    body = urllib.parse.urlencode(data).encode("utf-8")
    req = urllib.request.Request(
        url,
        data=body,
        headers={
            "User-Agent": "sentientia-cutover-smoke/1.0",
            "Content-Type": "application/x-www-form-urlencoded",
            "Accept": "application/json",
        },
        method="POST",
    )
    return _urlopen_with_retry(req, ctx=ctx)


def call_rest(function_name: str, params: dict, *,
              ctx: SmokeRunContext) -> dict | list:
    """Call Moodle REST API. Raises ValueError on Moodle exception or
    malformed response. Never logs the token — only the function name.

    Mirrors the pattern in .claude/rules/api.md, but stdlib-only.
    """
    if not ctx.has_token():
        raise RuntimeError("MOODLE_TOKEN not configured")
    url = ctx.base_url.rstrip("/") + "/webservice/rest/server.php"
    payload = {
        "wstoken": ctx.token,
        "wsfunction": function_name,
        "moodlewsrestformat": "json",
        **params,
    }
    status, body = http_post_form(url, payload, ctx=ctx)
    if status != 200:
        raise ValueError(f"HTTP {status} from {function_name}")
    try:
        data = json.loads(body)
    except json.JSONDecodeError as e:
        raise ValueError(f"non-JSON response from {function_name}: {e}") from e
    if isinstance(data, dict) and "exception" in data:
        # Moodle convention: error payload has 'exception' + 'message'.
        # The message may include the function name + tenant info — safe to surface.
        raise ValueError(
            f"Moodle [{function_name}]: "
            f"{data.get('message', data['exception'])}"
        )
    return data


# ─── Safety guard ──────────────────────────────────────────────────────


def parse_and_guard_target(target: str) -> str:
    """Validate the --target URL.

    Returns the parsed hostname so the caller can use it for logging.
    Raises SystemExit(2) on guard failures — see REFUSED_HOSTS.
    """
    if not target:
        print("FATAL: --target is required", file=sys.stderr)
        raise SystemExit(2)
    parsed = urllib.parse.urlparse(target)
    if parsed.scheme not in ("http", "https"):
        print(f"FATAL: --target must be http(s), got: {parsed.scheme!r}",
              file=sys.stderr)
        raise SystemExit(2)
    host = (parsed.hostname or "").lower()
    if not host:
        print(f"FATAL: --target has no hostname: {target!r}", file=sys.stderr)
        raise SystemExit(2)
    for refused in REFUSED_HOSTS:
        if refused in host:
            print(
                f"FATAL: refused host {host!r} matches block-list pattern "
                f"{refused!r}. Cutover smoke-tests must NEVER run against the "
                f"live customer-facing domain. Point --target at the "
                f"upgrade-staging URL instead.",
                file=sys.stderr,
            )
            raise SystemExit(2)
    return host


# ─── Tests ─────────────────────────────────────────────────────────────


def test_login_page_renders(ctx: SmokeRunContext) -> TestResult:
    """1. Login page returns 200 and contains the CSRF logintoken input."""
    r = TestResult(name="test_login_page_renders")
    url = ctx.base_url.rstrip("/") + "/login/index.php"
    try:
        status, body = http_get(url, ctx=ctx)
    except TransportError as e:
        r.mark_failure(f"transport error: {e}")
        return r
    if status != 200:
        r.mark_failure(
            f"expected HTTP 200 from {url}, got {status}",
            detail=body[:400],
        )
        return r
    if not re.search(r'name=["\']logintoken["\']', body):
        r.mark_failure(
            "logintoken input not found in login page HTML",
            detail="login form structure has changed — cutover-critical",
        )
        return r
    if not re.search(r'name=["\']username["\']', body):
        r.mark_failure(
            "username input not found in login page HTML",
        )
        return r
    return r


def test_dashboard_route_responds(ctx: SmokeRunContext) -> TestResult:
    """2. /my/dashboard.php returns either 200 (cached page) or a redirect
    to login (303/302) — both indicate the dashboard PHP file isn't broken
    by the cutover. A 5xx response means catastrophic failure."""
    r = TestResult(name="test_dashboard_route_responds")
    url = ctx.base_url.rstrip("/") + "/my/dashboard.php"
    try:
        status, body = http_get(url, ctx=ctx)
    except TransportError as e:
        r.mark_failure(f"transport error: {e}")
        return r
    if status >= 500:
        r.mark_failure(
            f"dashboard returned 5xx ({status}) — cutover-blocker",
            detail=body[:400],
        )
        return r
    if status not in (200, 301, 302, 303):
        r.mark_failure(
            f"unexpected status {status} from dashboard",
            detail=body[:400],
        )
        return r
    return r


def test_course_catalog_api(ctx: SmokeRunContext) -> TestResult:
    """3. core_course_get_courses returns a list (the site has courses)."""
    r = TestResult(name="test_course_catalog_api")
    if not ctx.has_token():
        r.mark_skipped("MOODLE_TOKEN not set — REST tests skipped")
        return r
    try:
        data = call_rest("core_course_get_courses", {}, ctx=ctx)
    except (ValueError, RuntimeError, TransportError) as e:
        r.mark_failure(f"core_course_get_courses failed: {e}")
        return r
    if not isinstance(data, list):
        r.mark_failure(
            "course catalog did not return a list",
            detail=f"got: {type(data).__name__}",
        )
        return r
    # A live customer site always has at least the site-level course (id=1).
    if len(data) < 1:
        r.mark_failure("course catalog returned zero courses")
        return r
    r.stdout = f"course count: {len(data)}"
    return r


def test_scorm_endpoint_responds(ctx: SmokeRunContext) -> TestResult:
    """4. mod_scorm_get_scorms_by_courses responds without a 5xx — proves
    the SCORM module survives the cutover. Empty course list is a valid
    input that returns an empty SCORMs list."""
    r = TestResult(name="test_scorm_endpoint_responds")
    if not ctx.has_token():
        r.mark_skipped("MOODLE_TOKEN not set — REST tests skipped")
        return r
    try:
        data = call_rest(
            "mod_scorm_get_scorms_by_courses",
            {"courseids[0]": "1"},
            ctx=ctx,
        )
    except ValueError as e:
        # The endpoint may legitimately return "no SCORM in course 1" — but a
        # different error means the module is broken.
        msg = str(e).lower()
        if "function not available" in msg or "function does not exist" in msg:
            r.mark_failure(f"mod_scorm_get_scorms_by_courses not registered: {e}")
            return r
        # Any other Moodle exception is a failure too — record it.
        r.mark_failure(f"SCORM endpoint failed: {e}")
        return r
    except (RuntimeError, TransportError) as e:
        r.mark_failure(f"SCORM endpoint failed: {e}")
        return r
    if not isinstance(data, dict):
        r.mark_failure(
            "SCORM endpoint returned non-dict",
            detail=f"got: {type(data).__name__}",
        )
        return r
    # Expected keys: 'scorms', 'warnings'. We don't require non-empty.
    if "scorms" not in data:
        r.mark_failure(
            "SCORM response missing 'scorms' key",
            detail=f"keys: {sorted(data.keys())}",
        )
        return r
    return r


def test_bizlms_tenant_switching(ctx: SmokeRunContext) -> TestResult:
    """5. BizLMS multi-tenant attribution survives the cutover.

    Design note (corrected in Wave D1 P3 after first live run): the original
    implementation passed criteria[0][key]=profile_field_costcenterid to
    core_user_get_users. That key is invalid — core_user_get_users validates
    the criteria key against PARAM_ALPHA, so any underscore-bearing key is
    rejected with "Invalid parameter value detected" on EVERY Moodle,
    production included. It also can't work architecturally: one WS token
    sees one tenant's scope, so looping tenant ids through a single token
    cannot compare tenants.

    Correct approach: ask core_user_get_users for the confirmed-user set and
    read each user's `costcenterid` custom profile field out of the returned
    `customfields`. The distribution of that field IS the tenant attribution.

    - PASS  : >=2 distinct costcenterid values present (multi-tenant data
              intact — e.g. {1, 77, 177}).
    - FAIL  : the field is present but every attributed user collapses to a
              single tenant value (isolation/attribution broken — this is the
              runbook rollback trigger).
    - SKIP  : the costcenterid field is absent from every user (BizLMS
              multi-tenancy not provisioned on this instance — a vanilla
              Moodle or a non-airpay customer-zero, not a cutover failure).
    """
    r = TestResult(name="test_bizlms_tenant_switching")
    if not ctx.has_token():
        r.mark_skipped("MOODLE_TOKEN not set — REST tests skipped")
        return r
    try:
        data = call_rest(
            "core_user_get_users",
            {"criteria[0][key]": "confirmed", "criteria[0][value]": "1"},
            ctx=ctx,
        )
    except (ValueError, RuntimeError, TransportError) as e:
        r.mark_failure(f"core_user_get_users failed: {e}")
        return r
    users = data.get("users") if isinstance(data, dict) else data
    if not isinstance(users, list):
        r.mark_failure(
            "core_user_get_users did not return a users list",
            detail=f"got: {type(users).__name__}",
        )
        return r

    # Tally users per costcenterid value found in their custom profile fields.
    tenant_counts: dict[str, int] = {}
    field_seen = False
    for user in users:
        for cf in user.get("customfields", []) or []:
            if cf.get("shortname") == "costcenterid":
                field_seen = True
                value = str(cf.get("value", "")).strip()
                if value:
                    tenant_counts[value] = tenant_counts.get(value, 0) + 1
                break

    if not field_seen:
        r.mark_skipped(
            "costcenterid profile field absent from all users — BizLMS "
            "multi-tenancy not provisioned on this instance (vanilla Moodle "
            "or non-airpay customer); tenant isolation not applicable"
        )
        return r

    distinct = sorted(tenant_counts)
    r.stdout = f"tenant attribution (costcenterid -> users): {tenant_counts}"
    if not tenant_counts:
        r.mark_failure(
            "costcenterid field exists but no user carries a value — "
            "tenant attribution lost in the cutover",
        )
        return r
    if len(distinct) < 2:
        r.mark_failure(
            "all attributed users collapsed to a single tenant — multi-tenant "
            "isolation may be broken (rollback trigger)",
            detail=f"costcenterid distribution: {tenant_counts}",
        )
        return r
    # Informational: flag any tenant id outside the known set, but don't fail —
    # a new customer-zero tenant tree is a valid future state.
    unknown = [v for v in distinct if v.isdigit() and int(v) not in KNOWN_TENANTS]
    if unknown:
        r.stdout += f"; note: costcenterid(s) outside known {KNOWN_TENANTS}: {unknown}"
    return r


def test_dark_mode_assets(ctx: SmokeRunContext) -> TestResult:
    """6. Dark mode toggle assets are present in the theme. We inspect
    the public landing page (no auth required) for theme markers that
    confirm the dark mode toggle shipped with the build."""
    r = TestResult(name="test_dark_mode_assets")
    url = ctx.base_url.rstrip("/") + "/login/index.php"
    try:
        status, body = http_get(url, ctx=ctx)
    except TransportError as e:
        r.mark_failure(f"transport error: {e}")
        return r
    if status != 200:
        r.mark_failure(
            f"could not fetch landing page (status {status})",
            detail=body[:200],
        )
        return r
    # The airpayux theme exposes dark mode via either:
    #   - data-bs-theme="dark"|"light" attribute on <html> (Bootstrap 5.3 pattern)
    #   - data-theme="dark"|"light" attribute (older airpayux pattern)
    #   - prefers-color-scheme CSS rule (mandatory per Wave 2 P2 #19)
    has_data_attr = re.search(
        r'data-(?:bs-)?theme=["\'](dark|light)["\']', body, re.IGNORECASE
    )
    # The toggle button has a stable class — airpay-theme-toggle — set by
    # the theme's navbar template.
    has_toggle = "airpay-theme-toggle" in body or "theme-toggle" in body
    if not (has_data_attr or has_toggle):
        r.mark_failure(
            "no dark mode markers found on landing page",
            detail="expected data-theme attribute or theme-toggle class",
        )
        return r
    return r


def test_navbar_footer_rendering(ctx: SmokeRunContext) -> TestResult:
    """7. The theme's chrome renders intact on the anonymous surface.

    Design note (corrected in Wave D1 P3 after first live run): the original
    implementation asserted a <nav> AND a <footer> on /login/index.php. But
    the airpayux `login` layout is deliberately minimal — it sets
    nonavbar=true and emits neither a <nav> nor a <footer> (see
    theme/airpayux/config.php and templates/login.mustache). The standard
    <nav class="airpay-nav"> + <footer class="airpay-footer"> only render on
    authenticated content layouts (columns2 / drawers), which an anonymous,
    read-only smoke test cannot reach. So the old assertion failed on a
    perfectly healthy site AND, because login never includes navbar.mustache,
    could not have detected a broken navbar template anyway.

    What a cutover actually risks here is the THEME failing to render — a
    Mustache syntax error or a missing template makes Moodle fall back to a
    bare/boost page or 500. We detect that on the only guaranteed-anonymous
    surface (login) by requiring EITHER:
      (a) a classic full-layout navbar + footer (covers a non-airpayux theme
          or a site whose landing page is a full layout), OR
      (b) proof the airpayux theme rendered: its login structural markup
          (airpay-login*) or its active-theme asset URL (styles.php/airpayux).
          The login template shares the same head/render pipeline as the
          navbar + footer, so a healthy airpayux login render is strong
          evidence the chrome templates compiled.
    Fail only if neither is present — the theme did not render.

    This test is a non-blocking surface in the runbook (section 4.3): a miss
    is a cosmetic hotfix, not a rollback trigger.
    """
    r = TestResult(name="test_navbar_footer_rendering")
    url = ctx.base_url.rstrip("/") + "/login/index.php"
    try:
        status, body = http_get(url, ctx=ctx)
    except TransportError as e:
        r.mark_failure(f"transport error: {e}")
        return r
    if status != 200:
        r.mark_failure(
            f"could not fetch login page (status {status})",
            detail=body[:200],
        )
        return r

    # (a) Classic full-layout chrome: a navbar marker AND a <footer> element.
    has_navbar = bool(
        re.search(r"<nav[^>]*", body)
        or "airpay-navbar" in body
        or "airpay-nav" in body
        or "navbar-brand" in body
    )
    has_footer = "<footer" in body.lower()
    if has_navbar and has_footer:
        r.stdout = "full-layout navbar + footer present"
        return r

    # (b) airpayux theme rendered on the anonymous (login) surface.
    airpayux_marker = (
        "airpay-login" in body
        or "styles.php/airpayux" in body
        or re.search(r"theme/[\w./]*airpayux", body) is not None
    )
    if airpayux_marker:
        r.stdout = "airpayux theme rendered on anonymous surface (login)"
        return r

    r.mark_failure(
        "neither full-layout chrome nor airpayux theme markers found — "
        "theme may have failed to render in the cutover",
        detail="expected <nav>+<footer>, or airpayux markers "
               "(airpay-login / styles.php/airpayux)",
    )
    return r


def test_rest_api_health(ctx: SmokeRunContext) -> TestResult:
    """8. core_webservice_get_site_info — the canonical health-check
    REST call. Returns site name + token user info; failure here means
    the WS token is dead or the WS layer is broken."""
    r = TestResult(name="test_rest_api_health")
    if not ctx.has_token():
        r.mark_skipped("MOODLE_TOKEN not set — REST tests skipped")
        return r
    try:
        data = call_rest("core_webservice_get_site_info", {}, ctx=ctx)
    except (ValueError, RuntimeError, TransportError) as e:
        r.mark_failure(f"core_webservice_get_site_info failed: {e}")
        return r
    if not isinstance(data, dict):
        r.mark_failure(
            "site_info did not return a dict",
            detail=f"got: {type(data).__name__}",
        )
        return r
    required_keys = {"sitename", "username", "userid", "release"}
    missing = required_keys - data.keys()
    if missing:
        r.mark_failure(
            "site_info response missing required keys",
            detail=f"missing: {sorted(missing)}",
        )
        return r
    # Token must not be logged, but the SITE NAME and RELEASE are safe to
    # surface — they help operators verify they hit the right host.
    r.stdout = f"sitename={data['sitename']!r} release={data['release']!r}"
    return r


# Ordered list of every test, in the sequence the runbook expects.
TEST_FUNCTIONS: list[Callable[[SmokeRunContext], TestResult]] = [
    test_login_page_renders,
    test_dashboard_route_responds,
    test_course_catalog_api,
    test_scorm_endpoint_responds,
    test_bizlms_tenant_switching,
    test_dark_mode_assets,
    test_navbar_footer_rendering,
    test_rest_api_health,
]


# ─── Runner ────────────────────────────────────────────────────────────


def run_tests(ctx: SmokeRunContext) -> list[TestResult]:
    """Run every test in TEST_FUNCTIONS, capturing duration + uncaught
    exceptions. Returns the populated results list."""
    print(f"Running {len(TEST_FUNCTIONS)} smoke tests against {ctx.base_url}")
    print(f"Host: {ctx.parsed_host}")
    print(f"Token: {'configured' if ctx.has_token() else 'NOT configured (REST tests will skip)'}")
    print()
    for fn in TEST_FUNCTIONS:
        start = time.time()
        try:
            result = fn(ctx)
        except Exception as e:    # pragma: no cover - defensive catch
            result = TestResult(name=fn.__name__)
            result.mark_error(
                f"unhandled exception: {type(e).__name__}: {e}",
                detail=traceback.format_exc(),
            )
        result.duration_seconds = round(time.time() - start, 3)
        ctx.results.append(result)
        _print_result(result)
    return ctx.results


def _print_result(r: TestResult) -> None:
    if r.skipped:
        symbol, label = "SKIP", "SKIP"
    elif r.passed:
        symbol, label = "PASS", "PASS"
    else:
        symbol, label = "FAIL", "FAIL"
    line = f"  [{symbol}] {r.name} ({r.duration_seconds}s)"
    if r.stdout:
        line += f" — {r.stdout}"
    print(line)
    if r.skipped:
        print(f"        skipped: {r.skip_reason}")
    elif r.failure_message:
        print(f"        failure: {r.failure_message}")
        if r.failure_detail:
            for ln in r.failure_detail.splitlines()[:6]:
                print(f"                 {ln}")
    elif r.error_message:
        print(f"        error: {r.error_message}")


# ─── JUnit XML ─────────────────────────────────────────────────────────


def write_junit_xml(results: list[TestResult], path: Path) -> None:
    """Emit a JUnit-compatible XML report. The schema matches what
    CI providers (GitHub Actions, Jenkins, GitLab) auto-render. We pick
    the most widely supported subset.
    """
    total = len(results)
    failures = sum(1 for r in results if not r.passed and not r.error_message and not r.skipped)
    errors = sum(1 for r in results if r.error_message)
    skipped = sum(1 for r in results if r.skipped)
    duration = round(sum(r.duration_seconds for r in results), 3)

    testsuites = ET.Element(
        "testsuites",
        {
            "name": "cutover-smoke",
            "tests": str(total),
            "failures": str(failures),
            "errors": str(errors),
            "skipped": str(skipped),
            "time": f"{duration}",
        },
    )
    testsuite = ET.SubElement(
        testsuites,
        "testsuite",
        {
            "name": "cutover_smoke_test",
            "tests": str(total),
            "failures": str(failures),
            "errors": str(errors),
            "skipped": str(skipped),
            "time": f"{duration}",
            "timestamp": time.strftime("%Y-%m-%dT%H:%M:%S"),
        },
    )
    for r in results:
        case = ET.SubElement(
            testsuite,
            "testcase",
            {
                "classname": r.classname,
                "name": r.name,
                "time": f"{r.duration_seconds}",
            },
        )
        if r.skipped:
            ET.SubElement(case, "skipped", {"message": r.skip_reason})
        elif r.error_message:
            err = ET.SubElement(case, "error", {"message": r.error_message})
            if r.error_detail:
                err.text = r.error_detail
        elif not r.passed:
            fail = ET.SubElement(case, "failure", {"message": r.failure_message})
            if r.failure_detail:
                fail.text = r.failure_detail
        if r.stdout:
            so = ET.SubElement(case, "system-out")
            so.text = r.stdout

    path.parent.mkdir(parents=True, exist_ok=True)
    # ET.indent landed in Python 3.9 — repo standard PHP host runs 3.11+ in CI.
    try:
        ET.indent(testsuites, space="  ")
    except AttributeError:    # pragma: no cover - py<3.9 fallback
        pass
    tree = ET.ElementTree(testsuites)
    tree.write(path, encoding="utf-8", xml_declaration=True)


# ─── CLI ───────────────────────────────────────────────────────────────


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Cutover smoke test for Sentientia LMS / Airpay Academy",
    )
    parser.add_argument(
        "--target",
        required=True,
        help="Base URL of the Moodle site under test "
             "(e.g. http://localhost:8080/moodle). Refuses any host "
             "containing 'airpay.academy'.",
    )
    parser.add_argument(
        "--junit-out",
        default=str(DEFAULT_JUNIT_PATH),
        help=f"JUnit XML output path (default: {DEFAULT_JUNIT_PATH}).",
    )
    parser.add_argument(
        "--env-file",
        default=".env",
        help="Path to .env (default: .env). MOODLE_TOKEN is read from here.",
    )
    parser.add_argument(
        "--insecure-tls",
        action="store_true",
        help="Disable TLS verification (use only for self-signed staging certs).",
    )
    args = parser.parse_args(argv)

    # Safety guard FIRST — refuse live host before doing anything else.
    parsed_host = parse_and_guard_target(args.target)

    # Load .env (non-fatal if missing — operator can pass env vars directly).
    env_file = Path(args.env_file)
    dotenv = load_dotenv(env_file)
    token = os.environ.get("MOODLE_TOKEN") or dotenv.get("MOODLE_TOKEN", "")
    if not token:
        print(
            "WARNING: MOODLE_TOKEN is empty. REST-API tests will be skipped. "
            "Anonymous (HTML) tests will still run.",
            file=sys.stderr,
        )

    ctx = SmokeRunContext(
        base_url=args.target.rstrip("/"),
        parsed_host=parsed_host,
        token=token,
        insecure_tls=args.insecure_tls,
    )

    # Pre-flight: confirm the host is reachable. Fail fast if not.
    try:
        socket.gethostbyname(parsed_host)
    except socket.gaierror as e:
        print(f"FATAL: cannot resolve {parsed_host}: {e}", file=sys.stderr)
        return 2

    run_tests(ctx)

    junit_path = Path(args.junit_out)
    write_junit_xml(ctx.results, junit_path)
    print()
    print(f"JUnit XML: {junit_path}")

    fail_count = sum(1 for r in ctx.results if not r.passed and not r.skipped)
    pass_count = sum(1 for r in ctx.results if r.passed and not r.skipped)
    skip_count = sum(1 for r in ctx.results if r.skipped)
    print(f"Result: {pass_count} pass, {fail_count} fail, {skip_count} skip "
          f"({len(ctx.results)} total)")
    return 0 if fail_count == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
