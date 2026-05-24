"""
Helper that renders structured PDFs for Agent 1 tests and the
checked-in SAMPLE-SOP.pdf fixture.

Uses reportlab. Font sizes encode the intended heading hierarchy so the
parser can recover it: title 22pt, h1 18pt, h2 14pt, body 11pt.
"""

from __future__ import annotations

from dataclasses import dataclass, field
from pathlib import Path

from reportlab.lib.pagesizes import LETTER
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import inch
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import Paragraph, SimpleDocTemplate, Spacer

# Reportlab's built-in Helvetica has no /ToUnicode CMap, so a "•" bullet
# round-trips through pdfplumber as "(cid:127)". Vera ships inside the
# reportlab wheel and has a proper Unicode CMap. Register it once.
_TTF_REGISTERED = False
_TTF_FAMILY = "Vera"
_TTF_FAMILY_BOLD = "Vera-Bold"


def _ensure_ttf_registered() -> None:
    global _TTF_REGISTERED
    if _TTF_REGISTERED:
        return
    fonts_dir = Path(__import__("reportlab").__file__).parent / "fonts"
    pdfmetrics.registerFont(TTFont(_TTF_FAMILY, str(fonts_dir / "Vera.ttf")))
    pdfmetrics.registerFont(TTFont(_TTF_FAMILY_BOLD, str(fonts_dir / "VeraBd.ttf")))
    _TTF_REGISTERED = True

BODY = 11
H2 = 14
H1 = 18
TITLE = 22


@dataclass
class PdfBlock:
    """One block in the simulated SOP."""

    kind: str  # 'title' | 'h1' | 'h2' | 'body' | 'ul' | 'ol'
    text: str | None = None
    items: list[str] = field(default_factory=list)


def build_pdf(path: Path, blocks: list[PdfBlock]) -> Path:
    """Render ``blocks`` to a PDF at ``path``. Returns the path."""
    _ensure_ttf_registered()
    path.parent.mkdir(parents=True, exist_ok=True)
    doc = SimpleDocTemplate(
        str(path),
        pagesize=LETTER,
        leftMargin=0.75 * inch,
        rightMargin=0.75 * inch,
        topMargin=0.75 * inch,
        bottomMargin=0.75 * inch,
    )
    styles = _styles()
    flowables: list = []
    for block in blocks:
        flowables.extend(_render(block, styles))
    doc.build(flowables)
    return path


def _styles() -> dict[str, ParagraphStyle]:
    base = ParagraphStyle("base", fontName=_TTF_FAMILY, fontSize=BODY, leading=14)
    return {
        "title": ParagraphStyle(
            "title", parent=base, fontName=_TTF_FAMILY_BOLD,
            fontSize=TITLE, leading=26, spaceAfter=14,
        ),
        "h1": ParagraphStyle(
            "h1", parent=base, fontName=_TTF_FAMILY_BOLD,
            fontSize=H1, leading=22, spaceBefore=12, spaceAfter=8,
        ),
        "h2": ParagraphStyle(
            "h2", parent=base, fontName=_TTF_FAMILY_BOLD,
            fontSize=H2, leading=18, spaceBefore=10, spaceAfter=6,
        ),
        "body": ParagraphStyle("body", parent=base, spaceAfter=6),
        "li": ParagraphStyle("li", parent=base, leading=14),
    }


def _render(block: PdfBlock, styles: dict[str, ParagraphStyle]) -> list:
    if block.kind in ("title", "h1", "h2", "body"):
        return [Paragraph(block.text or "", styles[block.kind])]
    if block.kind == "ul":
        # Render as one paragraph per item with a literal bullet glyph.
        # Reportlab's ListFlowable bullets in Helvetica become (cid:127)
        # in the PDF text stream because the built-in encoding has no
        # Unicode mapping for the bullet codepoint.
        return [
            Paragraph(f"• {item}", styles["li"])
            for item in block.items
        ] + [Spacer(1, 6)]
    if block.kind == "ol":
        return [
            Paragraph(f"{index + 1}. {item}", styles["li"])
            for index, item in enumerate(block.items)
        ] + [Spacer(1, 6)]
    raise ValueError(f"unknown block kind: {block.kind!r}")


def sample_sop_blocks() -> list[PdfBlock]:
    """The blocks rendered into ``content/sops/SAMPLE-SOP.pdf``."""
    return [
        PdfBlock("title", "Sentientia LMS Sample SOP"),
        PdfBlock(
            "body",
            "This document is a synthetic Standard Operating Procedure used "
            "as a fixture for the SENTIENTIA Content Pipeline. It exercises "
            "every block kind Agent 1 must recognise: titles, headings at "
            "two levels, paragraphs, ordered lists, and unordered lists.",
        ),
        PdfBlock("h1", "Overview"),
        PdfBlock(
            "body",
            "The SENTIENTIA pipeline turns a written SOP into a SCORM "
            "training package. Agent 1 is the entry stage and converts the "
            "PDF into structured JSON the downstream agents can read.",
        ),
        PdfBlock("h2", "Pipeline Stages"),
        PdfBlock(
            "ol",
            items=[
                "Parse the PDF into structured JSON.",
                "Generate a narration script from the JSON.",
                "Synthesise slides and voice from the narration.",
                "Package the result into a SCORM 1.2 ZIP for upload.",
            ],
        ),
        PdfBlock("h2", "Guarantees"),
        PdfBlock(
            "ul",
            items=[
                "Pure local execution; no external API in the parser.",
                "Word count capped at two thousand words per SOP.",
                "Fails loudly when the cap is exceeded.",
                "Deterministic schema for the downstream contract.",
            ],
        ),
        PdfBlock("h1", "Acceptance"),
        PdfBlock(
            "body",
            "A successful Agent 1 run emits a JSON file whose schema "
            "matches the contract published in the agent documentation. "
            "Agent 2 reads that file with no further transformation.",
        ),
    ]


def build_sample_sop(path: Path) -> Path:
    """Build the checked-in SAMPLE-SOP.pdf fixture."""
    return build_pdf(path, sample_sop_blocks())


def build_long_pdf(path: Path, paragraph_count: int) -> Path:
    """Build a PDF with ``paragraph_count`` synthetic body paragraphs.

    Used to verify the 2000-word cap. Each paragraph contributes ~40 words.
    """
    filler = (
        "Lorem ipsum dolor sit amet consectetur adipiscing elit sed do "
        "eiusmod tempor incididunt ut labore et dolore magna aliqua enim "
        "ad minim veniam quis nostrud exercitation ullamco laboris nisi "
        "ut aliquip ex ea commodo consequat duis aute irure dolor."
    )
    blocks: list[PdfBlock] = [PdfBlock("title", "Oversized SOP")]
    for index in range(paragraph_count):
        blocks.append(PdfBlock("body", f"Paragraph {index + 1}. {filler}"))
    return build_pdf(path, blocks)
