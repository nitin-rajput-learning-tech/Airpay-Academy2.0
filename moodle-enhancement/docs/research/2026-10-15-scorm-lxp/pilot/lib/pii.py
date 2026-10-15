"""PII scanner for source material and course strings (India-focused).

Detectors (ASCII digits after Unicode-digit normalisation):
    aadhaar  12 digits, optional 4-4-4 grouping with spaces/hyphens, first digit 2-9;
             confidence "high" when the Verhoeff checksum validates, else "medium"
    pan      [A-Z]{5}[0-9]{4}[A-Z]
    mobile   Indian mobile: optional +91/0 prefix, 10 digits starting 6-9
    email    local@domain.tld
    ifsc     [A-Z]{4}0[A-Z0-9]{6}
    card     13-19 digits (optional single spaces/hyphens) passing Luhn

Detection order matters: card numbers are found and masked first so their digit
groups cannot re-trigger the Aadhaar or mobile detectors.
"""
from __future__ import annotations

import re
import unicodedata
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Any, Iterable

from .common import walk_strings

CARD_RE = re.compile(r"(?<![0-9])(?:[0-9][ -]?){12,18}[0-9](?![0-9])")
AADHAAR_RE = re.compile(r"(?<![0-9])[2-9][0-9]{3}[ -]?[0-9]{4}[ -]?[0-9]{4}(?![0-9])")
MOBILE_RE = re.compile(r"(?<![0-9A-Za-z])(?:\+91[ -]?|0)?[6-9][0-9]{9}(?![0-9])")
PAN_RE = re.compile(r"(?<![A-Z0-9])[A-Z]{5}[0-9]{4}[A-Z](?![A-Z0-9])")
IFSC_RE = re.compile(r"(?<![A-Z0-9])[A-Z]{4}0[A-Z0-9]{6}(?![A-Z0-9])")
EMAIL_RE = re.compile(r"[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}")

_VERHOEFF_D = (
    (0, 1, 2, 3, 4, 5, 6, 7, 8, 9),
    (1, 2, 3, 4, 0, 6, 7, 8, 9, 5),
    (2, 3, 4, 0, 1, 7, 8, 9, 5, 6),
    (3, 4, 0, 1, 2, 8, 9, 5, 6, 7),
    (4, 0, 1, 2, 3, 9, 5, 6, 7, 8),
    (5, 9, 8, 7, 6, 0, 4, 3, 2, 1),
    (6, 5, 9, 8, 7, 1, 0, 4, 3, 2),
    (7, 6, 5, 9, 8, 2, 1, 0, 4, 3),
    (8, 7, 6, 5, 9, 3, 2, 1, 0, 4),
    (9, 8, 7, 6, 5, 4, 3, 2, 1, 0),
)
_VERHOEFF_P = (
    (0, 1, 2, 3, 4, 5, 6, 7, 8, 9),
    (1, 5, 7, 6, 2, 8, 3, 0, 9, 4),
    (5, 8, 0, 3, 7, 9, 6, 1, 4, 2),
    (8, 9, 1, 6, 0, 4, 3, 5, 2, 7),
    (9, 4, 5, 3, 1, 2, 6, 8, 7, 0),
    (4, 2, 8, 6, 5, 7, 3, 9, 0, 1),
    (2, 7, 9, 3, 8, 0, 6, 4, 1, 5),
    (7, 0, 4, 6, 9, 1, 3, 2, 5, 8),
)


@dataclass(frozen=True)
class PiiFinding:
    """One detected identifier. The value is never stored in full; `masked` keeps a tail only."""

    kind: str
    masked: str
    location: str
    confidence: str
    detail: str

    def as_dict(self) -> dict[str, Any]:
        return asdict(self)


def luhn_ok(digits: str) -> bool:
    """Luhn checksum over an all-digit string."""
    if not digits.isdigit() or len(digits) < 2:
        return False
    total = 0
    for index, char in enumerate(reversed(digits)):
        value = int(char)
        if index % 2 == 1:
            value *= 2
            if value > 9:
                value -= 9
        total += value
    return total % 10 == 0


def verhoeff_ok(digits: str) -> bool:
    """Verhoeff checksum (used by Aadhaar) over an all-digit string."""
    if not digits.isdigit():
        return False
    check = 0
    for index, char in enumerate(reversed(digits)):
        check = _VERHOEFF_D[check][_VERHOEFF_P[index % 8][int(char)]]
    return check == 0


def normalise_digits(text: str) -> str:
    """Map every Unicode decimal digit (Devanagari, Arabic-Indic, ...) to ASCII, 1:1 in length."""
    out = []
    for char in text:
        if char.isdecimal() and not char.isascii():
            out.append(str(unicodedata.decimal(char)))
        else:
            out.append(char)
    return "".join(out)


def mask(value: str, keep: int = 4) -> str:
    """Keep only the last `keep` alphanumerics of a value, masking the rest."""
    compact = re.sub(r"[ -]", "", value)
    if len(compact) <= keep:
        return "*" * len(compact)
    return "*" * (len(compact) - keep) + compact[-keep:]


def mask_email(value: str) -> str:
    """Keep the first character of the local part and the domain."""
    local, _, domain = value.partition("@")
    return f"{local[:1]}***@{domain}"


def _blank(text: str, start: int, end: int) -> str:
    return text[:start] + ("#" * (end - start)) + text[end:]


def scan_text(text: str, location: str) -> list[PiiFinding]:
    """Scan one string; returns findings in detection order."""
    findings: list[PiiFinding] = []
    work = normalise_digits(text)

    for match in list(CARD_RE.finditer(work)):
        digits = re.sub(r"[ -]", "", match.group(0))
        if 13 <= len(digits) <= 19 and luhn_ok(digits):
            findings.append(PiiFinding("card", mask(digits), location, "high", f"{len(digits)} digits, Luhn valid"))
            work = _blank(work, match.start(), match.end())

    for match in list(AADHAAR_RE.finditer(work)):
        digits = re.sub(r"[ -]", "", match.group(0))
        valid = verhoeff_ok(digits)
        findings.append(
            PiiFinding(
                "aadhaar",
                mask(digits),
                location,
                "high" if valid else "medium",
                "Verhoeff checksum valid" if valid else "Aadhaar-shaped, Verhoeff checksum does not validate",
            )
        )
        work = _blank(work, match.start(), match.end())

    for match in MOBILE_RE.finditer(work):
        findings.append(PiiFinding("mobile", mask(match.group(0)), location, "medium", "Indian mobile number pattern"))
    for match in PAN_RE.finditer(work):
        findings.append(PiiFinding("pan", mask(match.group(0)), location, "high", "PAN pattern"))
    for match in IFSC_RE.finditer(work):
        findings.append(PiiFinding("ifsc", mask(match.group(0)), location, "high", "IFSC pattern"))
    for match in EMAIL_RE.finditer(work):
        findings.append(PiiFinding("email", mask_email(match.group(0)), location, "high", "email address"))
    return findings


def scan_strings(items: Iterable[tuple[str, str]]) -> list[PiiFinding]:
    """Scan (location, text) pairs."""
    findings: list[PiiFinding] = []
    for location, text in items:
        findings.extend(scan_text(text, location))
    return findings


def scan_document(doc: Any, prefix: str = "document") -> list[PiiFinding]:
    """Scan every string leaf of a JSON document; locations are JSON pointers."""
    return scan_strings((f"{prefix}:{pointer}", text) for pointer, text in walk_strings(doc))


def scan_file(path: Path) -> list[PiiFinding]:
    """Scan a UTF-8 text file (errors are replaced so binary junk cannot crash the scan)."""
    text = path.read_text(encoding="utf-8", errors="replace")
    return scan_text(text, f"file:{path.name}")


def summarise(findings: list[PiiFinding]) -> dict[str, Any]:
    """Counts by kind plus the serialised findings."""
    counts: dict[str, int] = {}
    for finding in findings:
        counts[finding.kind] = counts.get(finding.kind, 0) + 1
    return {"total": len(findings), "by_kind": counts, "findings": [f.as_dict() for f in findings]}
