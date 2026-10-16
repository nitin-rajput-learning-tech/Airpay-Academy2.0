"""Build the Sentientia Content Engine leadership deck locally with python-pptx (no Gamma, no network).

Usage:  python -I build_deck.py <research_dir> <out.pptx>
Reads   <research_dir>/matrix/overall.json (matrix totals), <research_dir>/prototypes/screenshots/*.png and
        <research_dir>/pilot/evidence/*.png. Text is measured with Arial (C:/Windows/Fonts) so every text box is
        sized to fit before it is written; there is no auto-shrink at render time.
"""
from __future__ import annotations

import json
import sys
from pathlib import Path

from PIL import Image, ImageFont
from pptx import Presentation
from pptx.chart.data import CategoryChartData
from pptx.dml.color import RGBColor
from pptx.enum.chart import XL_CHART_TYPE, XL_LABEL_POSITION, XL_LEGEND_POSITION
from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import MSO_ANCHOR, PP_ALIGN
from pptx.oxml.ns import qn
from pptx.util import Emu, Inches, Pt

BRAND = RGBColor(0x00, 0x66, 0xA7)
ACCENT = RGBColor(0x19, 0x85, 0xDD)
DEEP = RGBColor(0x0D, 0x5D, 0xA1)
TXT = RGBColor(0x1A, 0x1A, 0x2E)
GREY = RGBColor(0x5A, 0x60, 0x70)
PANEL = RGBColor(0xF2, 0xF4, 0xFB)
TINT = RGBColor(0xE8, 0xF2, 0xF9)
LINE = RGBColor(0xC9, 0xD2, 0xE0)
OK = RGBColor(0x15, 0x80, 0x3D)
WARN = RGBColor(0xB4, 0x53, 0x09)
BAD = RGBColor(0xDC, 0x26, 0x26)
ORANGE = RGBColor(0xED, 0x69, 0x2B)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)
FONT = "Arial"
SW, SH = 13.333, 7.5
MX = 0.6
FONT_FILES = {False: "C:/Windows/Fonts/arial.ttf", True: "C:/Windows/Fonts/arialbd.ttf"}
_fonts: dict = {}


def _font(size: float, bold: bool):
    key = (round(size * 4), bold)
    if key not in _fonts:
        _fonts[key] = ImageFont.truetype(FONT_FILES[bold], size=max(8, int(size * 10)))
    return _fonts[key]


def text_w(text: str, size: float, bold: bool = False) -> float:
    return _font(size, bold).getlength(text) / 10.0  # points


def wrap_count(text: str, size: float, width_pt: float, bold: bool = False) -> int:
    lines, cur = 1, ""
    for word in text.split():
        trial = (cur + " " + word).strip()
        if text_w(trial, size, bold) <= width_pt:
            cur = trial
        else:
            if cur:
                lines += 1
            cur = word
    return lines + text.count("\n")


def para_h(text: str, size: float, width_pt: float, bold: bool = False, spacing: float = 1.22) -> float:
    return wrap_count(text, size, width_pt, bold) * size * spacing


def new_prs() -> Presentation:
    prs = Presentation()
    prs.slide_width, prs.slide_height = Inches(SW), Inches(SH)
    return prs


def blank(prs) -> object:
    return prs.slides.add_slide(prs.slide_layouts[6])


def rect(slide, x, y, w, h, fill=None, line=None, shape=MSO_SHAPE.RECTANGLE, radius=None):
    shp = slide.shapes.add_shape(shape, Inches(x), Inches(y), Inches(w), Inches(h))
    shp.shadow.inherit = False
    if fill is None:
        shp.fill.background()
    else:
        shp.fill.solid()
        shp.fill.fore_color.rgb = fill
    if line is None:
        shp.line.fill.background()
    else:
        shp.line.color.rgb = line
        shp.line.width = Pt(1)
    if radius is not None and shape == MSO_SHAPE.ROUNDED_RECTANGLE:
        shp.adjustments[0] = radius
    return shp


def textbox(slide, x, y, w, h, text="", size=16, bold=False, color=TXT, align=PP_ALIGN.LEFT, anchor=MSO_ANCHOR.TOP):
    box = slide.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h))
    tf = box.text_frame
    tf.word_wrap = True
    tf.margin_left = tf.margin_right = Inches(0.05)
    tf.margin_top = tf.margin_bottom = Inches(0.03)
    tf.vertical_anchor = anchor
    p = tf.paragraphs[0]
    p.alignment = align
    r = p.add_run()
    r.text = text
    r.font.size, r.font.bold, r.font.name = Pt(size), bold, FONT
    r.font.color.rgb = color
    return box


def bullets(slide, x, y, w, h, items, max_size=20, color=TXT, bullet=True, spacing_after=7):
    """items: str or (bold lead, rest). Text is sized to fit the box."""
    indent = 16.0 if bullet else 0.0
    plain = [(it[0] + it[1]) if isinstance(it, tuple) else it for it in items]
    size = max_size
    width_pt = w * 72 - 14.4 - indent
    while size > 11 and sum(para_h(t, size, width_pt) + spacing_after for t in plain) > h * 72 - 8:
        size -= 0.5
    box = slide.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h))
    tf = box.text_frame
    tf.word_wrap = True
    tf.margin_left = tf.margin_right = Inches(0.05)
    tf.margin_top = tf.margin_bottom = Inches(0.03)
    for i, it in enumerate(items):
        p = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
        p.space_after = Pt(spacing_after)
        if bullet:
            p_pr = p._p.get_or_add_pPr()
            p_pr.set("marL", str(int(indent * 12700)))
            p_pr.set("indent", str(int(-indent * 12700)))
            bu = p_pr.makeelement(qn("a:buChar"), {"char": "•"})
            p_pr.append(bu)
        lead, rest = (it if isinstance(it, tuple) else ("", it))
        if lead:
            r = p.add_run()
            r.text = lead
            r.font.bold, r.font.size, r.font.name = True, Pt(size), FONT
            r.font.color.rgb = color
        r2 = p.add_run()
        r2.text = rest
        r2.font.size, r2.font.name = Pt(size), FONT
        r2.font.color.rgb = color
    return box


def title(slide, text, sub=None):
    textbox(slide, MX, 0.38, SW - 2 * MX, 0.75, text, size=28, bold=True, color=TXT, anchor=MSO_ANCHOR.MIDDLE)
    rect(slide, MX + 0.05, 1.16, 1.4, 0.05, fill=BRAND)
    if sub:
        textbox(slide, MX, 1.26, SW - 2 * MX, 0.4, sub, size=14, color=GREY)


def footer(slide, n):
    textbox(slide, MX, 7.08, 9, 0.3, "Sentientia Content Engine  |  15 October 2026  |  Confidential", size=9, color=GREY)
    textbox(slide, SW - MX - 1.0, 7.08, 1.0, 0.3, str(n), size=9, color=GREY, align=PP_ALIGN.RIGHT)


def notes(slide, text):
    slide.notes_slide.notes_text_frame.text = text


def table(slide, x, y, w, col_w, rows, size=12, header=True, max_h=5.3, first_bold=False):
    """rows: list of lists of str. Heights are computed so the table never runs past max_h (font shrinks to fit)."""
    total = sum(col_w)
    col_in = [w * c / total for c in col_w]
    while True:
        heights = []
        for r_i, row in enumerate(rows):
            h = max(para_h(cell, size, col_in[c] * 72 - 14, bold=(r_i == 0 and header) or (first_bold and c == 0)) for c, cell in enumerate(row))
            heights.append(h / 72 + 0.14)
        if sum(heights) <= max_h or size <= 9:
            break
        size -= 0.5
    shape = slide.shapes.add_table(len(rows), len(col_w), Inches(x), Inches(y), Inches(w), Inches(sum(heights)))
    tbl = shape.table
    for c, wi in enumerate(col_in):
        tbl.columns[c].width = Inches(wi)
    for r_i, row in enumerate(rows):
        tbl.rows[r_i].height = Inches(heights[r_i])
        for c, text in enumerate(row):
            cell = tbl.cell(r_i, c)
            cell.margin_left = cell.margin_right = Inches(0.08)
            cell.margin_top = cell.margin_bottom = Inches(0.05)
            cell.vertical_anchor = MSO_ANCHOR.MIDDLE
            cell.fill.solid()
            is_head = header and r_i == 0
            cell.fill.fore_color.rgb = BRAND if is_head else (PANEL if r_i % 2 == 0 else WHITE)
            tf = cell.text_frame
            tf.word_wrap = True
            p = tf.paragraphs[0]
            run = p.add_run()
            run.text = text
            run.font.size, run.font.name = Pt(size), FONT
            run.font.bold = is_head or (first_bold and c == 0)
            run.font.color.rgb = WHITE if is_head else TXT
    return shape, sum(heights)


def tile(slide, x, y, w, h, big, small, fill=TINT, big_color=BRAND):
    rect(slide, x, y, w, h, fill=fill, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.08)
    textbox(slide, x, y + 0.15, w, h * 0.5, big, size=36, bold=True, color=big_color, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)
    textbox(slide, x + 0.1, y + h * 0.58, w - 0.2, h * 0.38, small, size=13, color=TXT, align=PP_ALIGN.CENTER)


def card(slide, x, y, w, h, head, items, head_fill=BRAND, size=15):
    rect(slide, x, y, w, h, fill=PANEL, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.04)
    rect(slide, x, y, w, 0.5, fill=head_fill, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.12)
    textbox(slide, x + 0.1, y + 0.03, w - 0.2, 0.44, head, size=15, bold=True, color=WHITE, anchor=MSO_ANCHOR.MIDDLE)
    bullets(slide, x + 0.1, y + 0.62, w - 0.2, h - 0.72, items, max_size=size, spacing_after=5)


def picture(slide, path: Path, x, y, w, h):
    with Image.open(path) as im:
        iw, ih = im.size
    scale = min(w / iw, h / ih)
    pw, ph = iw * scale, ih * scale
    pic = slide.shapes.add_picture(str(path), Inches(x + (w - pw) / 2), Inches(y + (h - ph) / 2), Inches(pw), Inches(ph))
    pic.line.color.rgb = LINE
    pic.line.width = Pt(0.75)
    return pic


def build(base: Path) -> Presentation:
    prs = new_prs()
    overall = json.loads((base / "matrix" / "overall.json").read_text(encoding="utf-8"))["overall"]
    n = 0

    # 1 title
    s = blank(prs)
    n += 1
    rect(s, 0, 0, SW, SH, fill=BRAND)
    rect(s, 0, 5.6, SW, 1.9, fill=DEEP)
    textbox(s, 0.9, 1.5, 11.5, 0.5, "SENTIENTIA LMS", size=20, bold=True, color=RGBColor(0xC5, 0xE3, 0xF7))
    textbox(s, 0.9, 2.05, 11.5, 2.15, "The content engine: SOP to multilingual, scenario-based course, built in India", size=36, bold=True, color=WHITE)
    textbox(s, 0.9, 4.3, 11.5, 0.9, "Study findings, staged plan, costs and the decisions we need", size=22, color=WHITE)
    textbox(s, 0.9, 6.05, 11.5, 0.4, "Prepared for Nitin Rajput, Head of L&D, Airpay  |  15 October 2026  |  For the founder and C-suite  |  Confidential", size=14, color=RGBColor(0xC5, 0xE3, 0xF7))
    notes(s, "Source: STUDY.md in docs/research/2026-10-15-scorm-lxp and the C-suite brief. Nothing in this deck claims a feature is live; all AI and voice calls so far were mocked.")

    # 2 why now
    s = blank(prs)
    n += 1
    title(s, "The category moved. Our content engine has not.")
    bullets(s, MX, 1.6, 7.4, 4.9, [
        ("Authoring moved inside the LMS. ", "Vendors now turn a document into a course with AI, voice it, translate it and package it to SCORM or cmi5 automatically."),
        ("Our moat is governance, not generation. ", "Regulated buyers ask who approved this Hindi sentence, where this voice was made, and whether it runs in their LMS."),
        ("Thirteen languages is a cost problem first. ", "Machine translation is cheap; reviewers are not."),
        ("Voice scales badly when rented. ", "Per-character fees across thirteen languages never end."),
    ], max_size=19)
    rect(s, 8.4, 1.7, 4.3, 3.6, fill=TINT, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.05)
    textbox(s, 8.6, 1.85, 3.9, 0.4, "THE DECISION", size=13, bold=True, color=BRAND)
    textbox(s, 8.6, 2.3, 3.9, 2.9, "Build the content engine as a Sentientia product, on the platform we already own, instead of renting one.", size=22, bold=True, color=TXT)
    footer(s, n)
    notes(s, "STUDY.md section 2 and the C-suite brief section 1.")

    # 3 what we did
    s = blank(prs)
    n += 1
    title(s, "What we did: tested a report, a market and our own code")
    xs = [MX + i * 2.05 for i in range(6)]
    for x, big, small in zip(xs, ["20", "51", "13", "55", "6", "INR 0"], ["platforms compared", "criteria, weighted for an India BFSI buyer", "languages planned, incl. Urdu and Arabic", "tests on the pilot harness", "clickable prototypes", "spent: no paid API call"]):
        tile(s, x, 1.7, 1.9, 2.1, big, small)
    bullets(s, MX, 4.2, SW - 2 * MX, 2.6, [
        "The report's 30 checkable vendor claims were verified one by one against vendor pages and reviews.",
        "Sentientia was scored from its code, not its brochure; where the two disagree the code wins (six places).",
        "A mock pilot compiles one course into SCORM 1.2, SCORM 2004 and cmi5 and was played in a real browser.",
    ], max_size=18)
    footer(s, n)
    notes(s, "STUDY.md sections 1, 3, 4, 5, 13. Vendor scores come from public pages and are scored low where only marketing exists.")

    # 4 report claims
    s = blank(prs)
    n += 1
    title(s, "The report: right architecture, uneven facts")
    cd = CategoryChartData()
    cd.categories = ["Verified", "Verified in part", "Found nowhere", "Contradicted"]
    cd.add_series("Claims", (7, 18, 4, 1))
    gf = s.shapes.add_chart(XL_CHART_TYPE.COLUMN_CLUSTERED, Inches(MX), Inches(1.6), Inches(5.9), Inches(4.8), cd)
    ch = gf.chart
    ch.has_legend = False
    ch.has_title = True
    ch.chart_title.text_frame.text = "30 checkable claims about five vendors"
    ch.chart_title.text_frame.paragraphs[0].runs[0].font.size = Pt(14)
    ch.chart_title.text_frame.paragraphs[0].runs[0].font.bold = True
    plot = ch.plots[0]
    plot.gap_width = 60
    plot.has_data_labels = True
    plot.data_labels.font.size = Pt(16)
    plot.data_labels.font.bold = True
    plot.data_labels.position = XL_LABEL_POSITION.OUTSIDE_END
    for idx, col in enumerate([OK, ACCENT, WARN, BAD]):
        pt = plot.series[0].points[idx]
        pt.format.fill.solid()
        pt.format.fill.fore_color.rgb = col
    ch.value_axis.visible = False
    ch.value_axis.has_major_gridlines = False
    ch.category_axis.tick_labels.font.size = Pt(13)
    bullets(s, 6.9, 1.7, 5.8, 4.8, [
        ("Right: ", "a canonical course document, a proxy for live AI, tag-safe translation with human stages, a packager hardened for Moodle. We adopt all four."),
        ("Thin: ", "no buyer, no price, no compliance, voice assumed to be rented."),
        ("Wrong: ", "the \"forget 50% in an hour\" figures are folklore, not Ebbinghaus's data."),
        ("Vendor marketing: ", "\"150+ languages\", \"four months to four weeks\" and template counts are vendor claims."),
    ], max_size=18)
    footer(s, n)
    notes(s, "STUDY.md sections 2 and 3.1; sources/R1 (claim table) and sources/R6 (learning science).")

    # 5 matrix
    s = blank(prs)
    n += 1
    title(s, "No vendor in the set is ahead; the lanes we want are open", "Weighted score on 51 criteria, % of maximum (scored low where only marketing exists)")
    vendors = sorted([(k, v) for k, v in overall.items() if not k.startswith("S:")], key=lambda kv: -kv[1])[:9]
    cats, a, b, c = [], [], [], []
    rows = [(k, v, 0, 0) for k, v in vendors]
    rows.insert(1, ("Sentientia today", 0, overall["S:today"], 0))
    rows.append(("Sentientia after Stage 1 (target)", 0, 0, overall["S:s1"]))
    rows.append(("Sentientia after Stage 3 (target)", 0, 0, overall["S:s3"]))
    rows.sort(key=lambda r: -(r[1] + r[2] + r[3]))
    for name, v, t, g in rows:
        cats.append(name)
        a.append(round(v))
        b.append(round(t))
        c.append(round(g))
    cd = CategoryChartData()
    cd.categories = cats
    cd.add_series("Vendors (documented today)", a)
    cd.add_series("Sentientia today (from code)", b)
    cd.add_series("Sentientia plan targets", c)
    gf = s.shapes.add_chart(XL_CHART_TYPE.BAR_STACKED, Inches(MX), Inches(1.75), Inches(8.2), Inches(5.2), cd)
    ch = gf.chart
    ch.has_legend = True
    ch.legend.position = XL_LEGEND_POSITION.BOTTOM
    ch.legend.include_in_layout = False
    ch.legend.font.size = Pt(12)
    plot = ch.plots[0]
    plot.gap_width = 40
    plot.overlap = 100
    plot.has_data_labels = True
    plot.data_labels.number_format = '0;;;'
    plot.data_labels.number_format_is_linked = False
    plot.data_labels.font.size = Pt(11)
    plot.data_labels.font.bold = True
    plot.data_labels.position = XL_LABEL_POSITION.INSIDE_END
    for ser, col in zip(plot.series, [LINE, BRAND, ACCENT]):
        ser.format.fill.solid()
        ser.format.fill.fore_color.rgb = col
    ch.category_axis.reverse_order = True
    ch.category_axis.tick_labels.font.size = Pt(12)
    ch.value_axis.maximum_scale = 100
    ch.value_axis.tick_labels.font.size = Pt(11)
    ch.value_axis.has_major_gridlines = False
    bullets(s, 9.0, 1.8, 3.8, 5.0, [
        ("Moodle Workplace 39%, Articulate 30%. ", "Sentientia is at 31% today."),
        ("Targets, not facts: ", "58% after Stage 1, 84% after Stage 3."),
        ("15 of 51 criteria ", "have no vendor at \"shipped and documented\"."),
        ("Confidence: ", "10 vendors low, 10 medium, none high."),
    ], max_size=16)
    footer(s, n)
    notes(s, "matrix/capability-matrix.csv and matrix/scoring-method.md. Sentientia is scored from code with full visibility; vendors from public pages. The structure of the finding survives a one-point change in any cell; the exact percentages do not.")

    # 6 open lanes
    s = blank(prs)
    n += 1
    title(s, "Where the market is open")
    card(s, MX, 1.6, 3.95, 3.5, "Localisation governance", ["Translation memory with XLIFF and a two-stage human review: documented by almost no one", "Machine translation plus glossary is everywhere; reviewer workflow is not"], size=15)
    card(s, MX + 4.1, 1.6, 3.95, 3.5, "In-region speech", ["Where speech is processed, how a brand name is pronounced, how captions are aligned: documented by none of 20", "Self-hosting in India is a differentiator, if it passes a quality gate"], size=15)
    card(s, MX + 8.2, 1.6, 3.95, 3.5, "Decision analytics", ["Scenario choices as data, not just a score", "Spaced practice after completion; roleplay with human-calibrated scoring"], size=15)
    rect(s, MX, 5.35, SW - 2 * MX, 1.4, fill=TINT, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.08)
    textbox(s, MX + 0.2, 5.4, SW - 2 * MX - 0.4, 1.3, "No vendor shows an India hosting region or a DPDP statement. Self-hosted Moodle and Tenneo's client-server option are the only on-premise paths found. That is what an Indian bank's security questionnaire probes.", size=18, bold=True, color=TXT, anchor=MSO_ANCHOR.MIDDLE)
    footer(s, n)
    notes(s, "STUDY.md sections 3, 4.2 and 9. Tenneo and Invince name large bank customers; every reference is vendor-sourced and unverified.")

    # 7 where Sentientia stands
    s = blank(prs)
    n += 1
    title(s, "Where Sentientia stands, from code and not the brochure")
    colw = 2.95
    heads = [("Exists today", OK), ("Proved in this study", BRAND), ("Designed, not built", WARN), ("Deferred, costed only", GREY)]
    items = [
        ["AI gateway with spend caps", "Authoring drafts with review gate", "Real Moodle course builder", "Translation of pasted text, 4 languages", "xAPI endpoint, cmi5 session tracker", "No live AI call has ever run"],
        ["Canonical course schema", "Compiler to 3 standards", "Hardened runtimes and validation", "55 tests, browser run", "Six prototypes"],
        ["Self-hosted speech service and gate", "XLIFF, memory, two-stage review", "Scenario editor", "Daily Review practice", "Live roleplay (formative first)"],
        ["AI avatar video", "Native mobile app (funded separately)", "SCORM import / decompile"],
    ]
    for i, ((h, col), it) in enumerate(zip(heads, items)):
        card(s, MX + i * (colw + 0.12), 1.6, colw, 4.4, h, it, head_fill=col, size=14.5)
    footer(s, n)
    notes(s, "STUDY.md section 5 (and 5.5, where the written record overstates the code). Flags default OFF; nothing here is live for learners.")

    # 8 architecture
    s = blank(prs)
    n += 1
    title(s, "One course document, three standards")
    rect(s, MX, 2.0, 2.2, 1.4, fill=PANEL, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.08)
    textbox(s, MX + 0.1, 2.1, 2.0, 1.2, "SOP, PDF, DOCX, PPTX or prompt", size=15, bold=True, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)
    rect(s, MX + 2.35, 2.5, 0.5, 0.4, fill=BRAND, shape=MSO_SHAPE.RIGHT_ARROW)
    rect(s, MX + 2.95, 1.7, 3.9, 2.0, fill=BRAND, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.06)
    textbox(s, MX + 3.05, 1.75, 3.7, 0.45, "Canonical course document", size=17, bold=True, color=WHITE, align=PP_ALIGN.CENTER)
    textbox(s, MX + 3.05, 2.25, 3.7, 1.4, "slides  |  branching scenario  |  quiz\nevery language  |  protected terms\napprovals  |  provenance  |  audit", size=13, color=WHITE, align=PP_ALIGN.CENTER)
    rect(s, MX + 6.95, 2.5, 0.5, 0.4, fill=BRAND, shape=MSO_SHAPE.RIGHT_ARROW)
    for i, (t, sub) in enumerate([("SCORM 1.2", "Airpay default today"), ("SCORM 2004 4th Ed.", "buyers who require it"), ("cmi5 / xAPI", "LRS-first, decision analytics")]):
        rect(s, MX + 7.55, 1.55 + i * 0.95, 4.4, 0.8, fill=TINT, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.12)
        textbox(s, MX + 7.7, 1.57 + i * 0.95, 4.1, 0.4, t, size=16, bold=True, color=BRAND)
        textbox(s, MX + 7.7, 1.94 + i * 0.95, 4.1, 0.35, sub, size=12, color=GREY)
    bullets(s, MX, 4.55, SW - 2 * MX, 2.4, [
        ("Generate and translate on Claude ", "through our own gateway: keys, spend ledger, caps, mock-first."),
        ("Speech in India ", "on a service we control, with a quality gate per language and a fallback."),
        ("One static player ", "written once and hardened against Moodle's runtime traps (the 4,096-character progress limit, exit state, interaction logging)."),
        ("Nothing machine-made reaches learners ", "without a named human approval, recorded in a hash-chained audit log."),
    ], max_size=17)
    footer(s, n)
    notes(s, "ADR-034 decisions D1 to D3; STUDY.md section 6. Package mode default is multilingual (one activity, one completion); per-language packages are an option.")

    # 9 localisation
    s = blank(prs)
    n += 1
    title(s, "Thirteen languages, two human stages")
    steps = ["Segment", "Memory", "Machine draft", "Auto checks", "Translator", "Subject expert", "Admin sign-off", "Voice"]
    wbox = (SW - 2 * MX - 0.15 * 7) / 8
    for i, st in enumerate(steps):
        fill = BRAND if st in ("Translator", "Subject expert", "Admin sign-off") else PANEL
        col = WHITE if fill == BRAND else TXT
        rect(s, MX + i * (wbox + 0.15), 1.7, wbox, 0.85, fill=fill, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.12)
        textbox(s, MX + i * (wbox + 0.15), 1.72, wbox, 0.8, st, size=13, bold=True, color=col, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)
    textbox(s, MX, 2.6, SW - 2 * MX, 0.35, "Blue = human stages. Only approved languages reach learners; machine drafts are preview-only.", size=13, color=GREY)
    bullets(s, MX, 3.1, 6.2, 3.8, [
        ("Languages: ", "Hindi, Marathi, Kannada, Tamil, Telugu, Bengali, Gujarati, Malayalam, Punjabi, Odia, Assamese, Urdu, Arabic, English (Swahili kept)."),
        ("Right-to-left done properly: ", "mirrored layout, isolated terms and amounts, script fonts."),
        ("XLIFF out and in ", "for agencies; checks catch placeholders, numbers and brand terms."),
    ], max_size=17)
    rect(s, 7.1, 3.1, 5.6, 3.6, fill=TINT, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.05)
    textbox(s, 7.3, 3.2, 5.2, 0.4, "THE NUMBER THAT SETS THE LANGUAGE COUNT", size=12, bold=True, color=BRAND)
    textbox(s, 7.3, 3.6, 5.2, 0.9, "5 to 6 hours", size=36, bold=True, color=BRAND)
    textbox(s, 7.3, 4.5, 5.2, 2.1, "of human review per language per course. Fifty courses a year in thirteen languages is about two full-time reviewers. Translation memory makes repeat content 30 to 50% cheaper, so languages are enabled by demand.", size=16)
    footer(s, n)
    notes(s, "STUDY.md section 7 (7.4 reviewer capacity). Capacity planning values are assumptions to be replaced by quotes.")

    # 10 speech
    s = blank(prs)
    n += 1
    title(s, "Our own voice: cheap to run, only worth it if it passes a gate")
    table(s, MX, 1.6, 7.7, [2.2, 3.6, 1.9], [
        ["Check (per language)", "How", "Pass"],
        ["Naturalness", "Blind listening panel, 20 native raters", "MOS 4.0 or more"],
        ["Intelligibility", "ASR round trip vs a human recording", "within 1.2x"],
        ["Brand terms, numbers", "60-term and 50-item sets", "95% or more"],
        ["Speed and cost", "Real-time factor on a T4-class GPU", "0.5 or less"],
        ["Licence", "Commercial use confirmed in writing", "no CC-BY-NC"],
    ], size=13, max_h=4.2)
    bullets(s, 8.6, 1.65, 4.1, 3.2, [
        ("Compute is a few rupees: ", "INR 5 to 11 per course-language on on-demand India GPUs."),
        ("Why self-host: ", "residency, unlimited regeneration while reviewing, one consistent voice."),
        ("Fallback stays wired: ", "an India-region voice (INR 12 to 24)."),
    ], max_size=15)
    rect(s, MX, 5.5, SW - 2 * MX, 1.3, fill=TINT, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.08)
    textbox(s, MX + 0.2, 5.55, SW - 2 * MX - 0.4, 1.2, "A language that fails the gate stays on the fallback until it passes. Urdu and Arabic will probably stay there longest. Several strong open models are non-commercial licences and are excluded.", size=16, bold=True, anchor=MSO_ANCHOR.MIDDLE)
    footer(s, n)
    notes(s, "STUDY.md section 8; sources/R3 (candidate engines, licences, India GPU cost). No model was downloaded and no voice was synthesised in this study.")

    # 11 compliance
    s = blank(prs)
    n += 1
    title(s, "Compliance built in, for DPDP and a bank's vendor questionnaire")
    table(s, MX, 1.6, SW - 2 * MX, [2.6, 5.4, 4.0], [
        ["Control", "What it does", "Answers"],
        ["Personal-data gate", "Scans every source and generated string for Aadhaar, PAN, mobile, email, IFSC, card; blocks the run", "DPDP safeguards; no PII to any model"],
        ["Residency record", "Every artefact records where it was processed; speech runs in India", "Data-location statement per component"],
        ["Hash-chained audit", "Who generated, translated, approved; model; hashes; cannot be overwritten", "Audit and access rights"],
        ["Vendor posture pack", "Sub-processor list, DPA template, exit plan (JSON, XLIFF, TMX, standard packages)", "RBI IT-outsourcing style questionnaire"],
        ["Incident path", "6-hour CERT-In reporting folded into the existing runbook", "CERT-In 2022 directions"],
    ], size=13, max_h=4.4)
    textbox(s, MX, 6.15, SW - 2 * MX, 0.7, "Open for counsel: is Claude inference available in India? Is an employee-training system a material IT outsourcing for Airpay? These are the first questions, not afterthoughts.", size=15, bold=True, color=WARN)
    footer(s, n)
    notes(s, "STUDY.md section 9 and sources/R4. Not legal advice; clause references are marked verified or unverified in R4.")

    # 12 prototypes
    s = blank(prs)
    n += 1
    title(s, "Six clickable prototypes on our design tokens")
    shots = [("01-learner-home", "Learner home"), ("02-course-player", "Course player"), ("03-authoring-studio", "Authoring studio"), ("04-localisation-review", "Localisation review"), ("05-admin-analytics", "Admin analytics"), ("06-scenario-editor", "Scenario editor")]
    pw, ph = 3.95, 2.1
    for i, (stem, label) in enumerate(shots):
        col, row = i % 3, i // 3
        x, y = MX + col * (pw + 0.15), 1.55 + row * (ph + 0.6)
        picture(s, base / "prototypes" / "screenshots" / f"{stem}-desktop-light.png", x, y, pw, ph)
        textbox(s, x, y + ph + 0.02, pw, 0.35, label, size=14, bold=True, color=BRAND)
    footer(s, n)
    notes(s, "prototypes/index.html (open from the file system). Light and dark, LTR and RTL, 1440 and 375 px. Synthetic data; no screen-reader or performance testing yet. Self-assessment in prototypes/README.md.")

    # 13 pilot evidence
    s = blank(prs)
    n += 1
    title(s, "The pilot: one document, three standards, zero rupees")
    bullets(s, MX, 1.6, 5.9, 5.2, [
        ("Compiles ", "a UPI-dispute course (four slides, nine-step scenario, five questions, 13 languages) to SCORM 1.2, SCORM 2004 and cmi5."),
        ("Validates ", "every package (21 checks), simulates a learner, and the build is byte-for-byte repeatable."),
        ("Played in a real browser ", "against a stand-in LMS: pass, fail and resume after reload."),
        ("55 tests; ", "a browser bug that all unit tests missed was found and fixed."),
        ("Live path refuses to start ", "without the spend cap, the API key and a typed confirmation per call."),
    ], max_size=17)
    picture(s, base / "pilot" / "evidence" / "07-stub-lms-scorm12-passed.png", 6.75, 1.65, 6.0, 4.4)
    textbox(s, 6.75, 6.1, 6.0, 0.6, "Stand-in LMS log beside the player: score 100, passed, 8 interaction records, 102-character saved state.", size=12, color=GREY)
    footer(s, n)
    notes(s, "pilot/evidence/ (reports, audit logs, screenshots) and STUDY.md section 13. Mock mode proves the compiler and runtimes; it does not prove translation quality, voice quality or Moodle 5.3 behaviour.")

    # 14 plan
    s = blank(prs)
    n += 1
    title(s, "The plan: 90, 180 and 365 days, each with a stop rule")
    table(s, MX, 1.6, SW - 2 * MX, [1.7, 3.8, 3.4, 3.2], [
        ["Stage", "Delivers", "Evidence gate", "Stop rule"],
        ["1 Foundation\ndays 0 to 90", "Course document, compiler for 3 standards, translation core with two-stage review, speech service v0, Hindi end to end, live pilot", "Runs in Moodle 5.3 with resume and pass/fail; Hindi approved by named reviewers; speech scorecard", "Compiler fails Moodle tests after 3 rounds: SCORM 1.2 only. Hindi voice fails twice: rented voice stays primary"],
        ["2 Scenarios and scale\ndays 90 to 180", "Scenario editor, Daily Review practice, 13 languages, right-to-left, decision analytics", "Scenario authored in under 2 hours; practice engagement; reviewer throughput", "Authoring over 4 hours: simplify; reviewers under half of plan: cut languages"],
        ["3 Roleplay and product\ndays 180 to 365", "Formative AI roleplay, learner speech, sellable tier, conformance, hardening", "Rubric agreement with raters 0.6 or more; second customer in a day", "No customer asks by day 240: defer; scores never count until calibrated"],
    ], size=12.5, max_h=4.2, first_bold=True)
    textbox(s, MX, 5.95, SW - 2 * MX, 0.9, "About 72 Claude sessions in all. One developer takes over the speech service and pulls analytics forward; two bring the 13-language rollout in by day 150; a localisation lead owns reviewers and glossaries.", size=15, color=TXT)
    footer(s, n)
    notes(s, "STUDY.md section 11 (11.1 table, 11.2 developer unlocks). Every feature flag defaults OFF; nothing flips for learners without Nitin's call.")

    # 15 costs
    s = blank(prs)
    n += 1
    title(s, "What it costs per course per language")
    table(s, MX, 1.6, 8.3, [1.9, 3.4, 1.7, 1.6], [
        ["Tier", "What it is", "Per language", "13 languages"],
        ["T0 Mock", "Everything simulated", "INR 0", "INR 0"],
        ["T1 Lean", "AI draft, self-hosted voice, one review", "INR 1,450 to 3,050", "INR 19k to 40k"],
        ["T2 Standard (compliance)", "AI draft, voice and quality check, two-stage review, audit export", "INR 2,800 to 5,850", "INR 36k to 76k"],
        ["T3 Premium / fallback", "Premium AI, rented voice or professional translation, two-stage review", "INR 5,100 to 12,200", "INR 66k to 160k"],
    ], size=13, max_h=3.9, first_bold=True)
    bullets(s, 9.2, 1.65, 3.6, 4.2, [
        ("People are over 90%. ", "AI is INR 25 to 45; voice INR 5 to 11 self-hosted."),
        ("Fixed: ", "India GPU about INR 7.5k to 17k a year at 100 courses; listening panel INR 5k to 15k per language."),
        ("Pilot month cap: ", "INR 10,000."),
    ], max_size=15)
    textbox(s, MX, 5.55, SW - 2 * MX, 1.2, "Planning values at list prices. Human rates are assumptions to be replaced by quotes. The first live run measures the real cost per course per language.", size=14, color=GREY)
    footer(s, n)
    notes(s, "STUDY.md section 12. Model lines at INR 87 per USD; speech lines at about INR 95 per USD (a 9% spread that changes no conclusion).")

    # 16 risks
    s = blank(prs)
    n += 1
    title(s, "Risks and how each is gated")
    table(s, MX, 1.6, SW - 2 * MX, [3.3, 5.2, 3.5], [
        ["Risk", "Gate or mitigation", "Fallback"],
        ["Voice quality not good enough", "Per-language listening-panel gate; pronunciation lexicon", "Rented India-region voice; self-hosted stays evaluation-only"],
        ["Model licence not commercial", "Written confirmation before any engine ships", "Switch engine"],
        ["Reviewer capacity not staffed", "Languages by demand; memory reuse; localisation lead", "Fewer languages; machine drafts preview-only"],
        ["Moodle runtime surprises", "SCORM 1.2 default; cmi5 external first; test inside Moodle", "SCORM 1.2 only"],
        ["Model data leaves India", "Only de-identified content; PII gate; sub-processor pack", "Customer-specific on-premise model, costed later"],
        ["Engineering bandwidth", "Stop rules per stage; whole builds only", "Stage 3 defers first"],
    ], size=13, max_h=4.9)
    footer(s, n)
    notes(s, "STUDY.md section 14 (twelve risks with likelihood and impact). What we will not claim until evidence exists: live, serving learners, conformant, 150+ languages, minutes-not-months.")

    # 17 asks
    s = blank(prs)
    n += 1
    title(s, "The six asks")
    asks = [
        ("Accept ADR-034 ", "and the Stage 1 scope."),
        ("Set the AI spend cap ", "(Addendum A, still blank): INR 10,000 for the pilot month, and provide the API key."),
        ("Approve the speech line: ", "India GPU batch jobs (about INR 10k to 20k a year), listening-panel honoraria, a six-week evaluation time-box."),
        ("Name Hindi reviewers: ", "a translator and a subject expert, so the two-stage loop runs on real content."),
        ("Decide the product stance: ", "part of the Enterprise plus AI tier, or a priced add-on."),
        ("Reconfirm data residency with compliance: ", "de-identified content to Claude outside India with the study's controls, or cost an on-premise option."),
    ]
    for i, (lead, rest) in enumerate(asks):
        y = 1.6 + i * 0.86
        rect(s, MX, y, 0.62, 0.62, fill=BRAND, shape=MSO_SHAPE.OVAL)
        textbox(s, MX, y, 0.62, 0.62, str(i + 1), size=20, bold=True, color=WHITE, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)
        bullets(s, MX + 0.8, y + 0.08, SW - 2 * MX - 0.8, 0.8, [(lead, rest)], max_size=17, bullet=False, spacing_after=0)
    footer(s, n)
    notes(s, "C-suite brief section 9. Until the cap and key exist, no AI or voice call can run live; the pilot harness refuses to start without them.")

    # 18 next
    s = blank(prs)
    n += 1
    title(s, "What happens next")
    table(s, MX, 1.7, SW - 2 * MX, [2.4, 9.6], [
        ["Horizon", "Focus"],
        ["Now to +90 days", "Stage 1 foundation; live pilot once the cap and key exist; Hindi reviewed end to end; first speech gate"],
        ["+3 to +6 months", "Scenarios, Daily Review, thirteen languages, right-to-left, decision analytics"],
        ["+6 to +12 months", "Live roleplay (formative), engine packaged for a second customer, conformance testing"],
    ], size=16, max_h=3.0, first_bold=True)
    bullets(s, MX, 4.9, SW - 2 * MX, 1.8, [
        "Study, matrix, architecture decision, pilot and prototypes are on the branch claude/research-scorm-lxp, not merged.",
        "Nothing is live; every flag defaults OFF; the next step on your side is the cap, the key and the reviewers.",
    ], max_size=16)
    footer(s, n)
    notes(s, "docs/research/2026-10-15-scorm-lxp/README.md lists every deliverable. Pilot prerequisites: pilot/LIVE-RUN-CHECKLIST.md.")

    return prs


def audit_overflow(prs) -> list[str]:
    """Estimate, for every text box, whether its text fits the box at the sizes written (Arial metrics)."""
    problems = []
    for idx, slide in enumerate(prs.slides, start=1):
        for shp in slide.shapes:
            if not shp.has_text_frame or not shp.text_frame.text.strip():
                continue
            tf = shp.text_frame
            width_pt = shp.width / 12700 - (tf.margin_left + tf.margin_right) / 12700
            need = (tf.margin_top + tf.margin_bottom) / 12700
            for p in tf.paragraphs:
                text = "".join(r.text for r in p.runs)
                if not text:
                    continue
                size = max((r.font.size.pt if r.font.size else 18) for r in p.runs)
                bold = any(bool(r.font.bold) for r in p.runs)
                marl = int(p._p.pPr.get("marL", "0")) / 12700 if p._p.pPr is not None else 0
                need += para_h(text, size, width_pt - marl, bold) + (p.space_after.pt if p.space_after else 0)
            if need > shp.height / 12700 + 4:
                problems.append(f"slide {idx}: '{tf.text[:40]}' needs {need / 72:.2f} in, box is {shp.height / 914400:.2f} in")
    return problems


def main(base: str, out: str) -> int:
    prs = build(Path(base))
    prs.save(out)
    print(f"Wrote {out}: {len(prs.slides)} slides")
    problems = audit_overflow(prs)
    for line in problems:
        print("OVERFLOW?", line)
    return 1 if problems else 0


if __name__ == "__main__":
    if len(sys.argv) != 3:
        print(__doc__)
        sys.exit(2)
    sys.exit(main(*sys.argv[1:]))
