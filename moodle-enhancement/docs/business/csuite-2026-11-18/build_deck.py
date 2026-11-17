#!/usr/bin/env python
"""
build_deck.py - C-suite deck "Academy 2.0: what we built over today's airpay.academy",
18 November 2026 (content spec v2: deck-content.md in this folder).

Re-runnable:  python build_deck.py
  1. opens airpay-template-0.1.pptx (read only, never modified);
  2. removes the template's six sample slides cleanly (each <p:sldId> leaves <p:sldIdLst> and
     its relationship is dropped, so the old slide parts are not written);
  3. adds 18 slides from the template's named layouts (1_Title Slide, Title Only, Comparison,
     Title and Content, Two Content, End/last Slide) and fills their placeholders run by run;
  4. draws the visuals as native, editable shapes: icon-in-circle motif (white glyph in a
     #0066A7 circle), "Today" vs "Academy 2.0" panels, stat callouts, process flow, table,
     catalogue columns, hub diagram;
  5. reads the speaker notes AND the 112-line catalogue (slides 14-16) from deck-content.md,
     so the deck follows the spec; the catalogue markers are counted and asserted
     (112 lines, 71 new, 41 rebuilt, 48 switched off);
  6. saves SENTIENTIA-CSUITE-DECK-2026-11-18.pptx next to this file.

Facts. Every on-slide line comes from deck-content.md (each slide there carries an HTML comment
naming the repository files its facts come from). Nothing operational is on the slides: no
budgets, asks, servers, test counts, version or decision-record numbers, plugin or switch names.
One wording change for fit: slide 13's title reads "Organisations kept apart, every action
checked" (spec: "Each organisation kept apart, ..."), because the spec wording is wider than the
title box under the Arial proxy that fit_check.py uses.

Geometry. The master carries the airpay logo top right and a blue footer band (www.airpay.co.in,
copyright line, Made-in-India strip) along the bottom; both are read from the master at build
time and content stays clear of them. The pale 'a' watermark is background art and may sit under
content. Every content slide puts its title in the same box (0.92, 0.40, 10.28 x 0.85 in).

Fonts. Every run names Calibri (the template layouts' font), symbol runs and bullets (check,
hollow circle, up arrow) name Segoe UI Symbol so they render on any Windows laptop. Text boxes
are sized with Arial metrics (wider than Calibri), the same conservative proxy as fit_check.py.
"""
import itertools
import math
import re
import sys
from pathlib import Path

from lxml import etree
from PIL import ImageFont
from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.dml import MSO_LINE_DASH_STYLE
from pptx.enum.shapes import MSO_CONNECTOR, MSO_SHAPE, MSO_SHAPE_TYPE
from pptx.enum.text import MSO_ANCHOR, MSO_AUTO_SIZE, PP_ALIGN
from pptx.oxml.ns import qn
from pptx.shapes.placeholder import TablePlaceholder
from pptx.util import Emu, Pt

HERE = Path(__file__).resolve().parent
TEMPLATE = HERE / "airpay-template-0.1.pptx"
SPEC = HERE / "deck-content.md"
OUT = HERE / "SENTIENTIA-CSUITE-DECK-2026-11-18.pptx"

# ---------------------------------------------------------------- palette (Brand Book 2026-06)
PRIMARY = "0066A7"        # airpay logo blue = Brand Book primary (dominant)
PRIMARY_DARK = "004D80"   # Brand Book primary-dark
ACCENT = "1985DD"         # Brand Book accent (secondary)
ACCENT_DEEP = "0D5DA1"    # Brand Book accent-deep
TINT = "F2F4FB"           # Brand Book page background: panels
LIGHT = "E8F2F9"          # Brand Book primary-light: benefit boxes, chips
TEXT = "1A1A2E"           # Brand Book text primary
MUTED = "5A6070"          # Brand Book text secondary
BORDER = "E2E6EF"         # Brand Book border
GREY_UI = "A0A9B8"        # Brand Book disabled
ORANGE = "ED692B"         # Brand Book highlight: at most one emphasis per slide
GREEN = "16A34A"
AMBER = "D97706"
RED = "DC2626"
WHITE = "FFFFFF"

LATIN_FONT = "Calibri"
SYMBOL_FONT = "Segoe UI Symbol"
FOOTER_TEXT = "Academy 2.0 · C-suite review, 18 Nov 2026 · Confidential"
CHECK, OFF, UP, DOT = "\u2713", "\u25CB", "\u2191", "\u2022"
LEGEND_OFF = "ready, switched on when we choose"

TOP, CENTER, BOTTOM = MSO_ANCHOR.TOP, MSO_ANCHOR.MIDDLE, MSO_ANCHOR.BOTTOM
LEFT_AL, CENTER_AL, RIGHT_AL = PP_ALIGN.LEFT, PP_ALIGN.CENTER, PP_ALIGN.RIGHT
EMU_IN = 914400
FIT = 0.88                # size boxes so text uses at most 88% (fit_check fails above 90%)


def E(inches):
    return Emu(int(round(inches * EMU_IN)))


def rgb(hexstr):
    return RGBColor.from_string(hexstr)


# ---------------------------------------------------------------- text model
def R(text, size=16, bold=False, color=TEXT, italic=False, font=None):
    return {"text": text, "size": size, "bold": bold, "color": color, "italic": italic,
            "font": font}


def S(text, size=16, color=PRIMARY, bold=False):
    """A symbol run (check, hollow circle, arrow) in Segoe UI Symbol."""
    return R(text, size, bold, color, font=SYMBOL_FONT)


def P(*runs, align=LEFT_AL, before=0, after=0, bullet=None, marl=0.0, indent=None, field=None):
    """A paragraph. bullet = (char, colour) drawn in Segoe UI Symbol, or None."""
    if indent is None:
        indent = -marl if bullet else 0.0
    return {"runs": list(runs), "align": align, "before": before, "after": after,
            "bullet": bullet, "marl": marl, "indent": indent, "field": field}


def _add_slidenum_field(p_el, size, color):
    fld = etree.SubElement(p_el, qn("a:fld"))
    fld.set("id", "{B6F15528-21DE-4FAA-801E-634DDDAF4B2B}")
    fld.set("type", "slidenum")
    rpr = etree.SubElement(fld, qn("a:rPr"))
    rpr.set("lang", "en-US")
    rpr.set("sz", str(int(size * 100)))
    sf = etree.SubElement(rpr, qn("a:solidFill"))
    etree.SubElement(sf, qn("a:srgbClr")).set("val", color)
    etree.SubElement(rpr, qn("a:latin")).set("typeface", LATIN_FONT)
    etree.SubElement(fld, qn("a:t")).text = "#"


def fill_tf(tf, paras):
    """Replace the text of a text frame paragraph by paragraph, run by run."""
    tf.clear()
    for i, para in enumerate(paras):
        p = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
        p.alignment = para["align"]
        p.space_before = Pt(para["before"])
        p.space_after = Pt(para["after"])
        ppr = p._p.get_or_add_pPr()
        ppr.set("marL", str(E(para["marl"])))
        ppr.set("indent", str(E(para["indent"])))
        if para["bullet"] is None:
            etree.SubElement(ppr, qn("a:buNone"))
        else:
            char, colour = para["bullet"]
            buclr = etree.SubElement(ppr, qn("a:buClr"))
            etree.SubElement(buclr, qn("a:srgbClr")).set("val", colour)
            etree.SubElement(ppr, qn("a:buSzPct")).set("val", "100000")
            etree.SubElement(ppr, qn("a:buFont")).set("typeface", SYMBOL_FONT)
            etree.SubElement(ppr, qn("a:buChar")).set("char", char)
        for run in para["runs"]:
            r = p.add_run()
            r.text = run["text"]
            f = r.font
            f.size = Pt(run["size"])
            f.bold = run["bold"]
            f.italic = run["italic"]
            f.color.rgb = rgb(run["color"])
            f.name = run["font"] or LATIN_FONT
            if run["font"]:
                rpr = r._r.get_or_add_rPr()
                for tag in ("a:ea", "a:cs", "a:sym"):
                    etree.SubElement(rpr, qn(tag)).set("typeface", run["font"])
        if para["field"] == "slidenum":
            _add_slidenum_field(p._p, 12, MUTED)


def set_frame(tf, margins, anchor, autosize_none=True):
    left, top, right, bottom = margins
    tf.margin_left, tf.margin_top = E(left), E(top)
    tf.margin_right, tf.margin_bottom = E(right), E(bottom)
    tf.word_wrap = True
    if anchor is not None:
        tf.vertical_anchor = anchor
    if autosize_none:
        tf.auto_size = MSO_AUTO_SIZE.NONE


# ---------------------------------------------------------------- measuring (Arial proxy)
_FONTS = {False: ImageFont.truetype("C:/Windows/Fonts/arial.ttf", 1000),
          True: ImageFont.truetype("C:/Windows/Fonts/arialbd.ttf", 1000)}


def text_w(text, size, bold):
    return _FONTS[bool(bold)].getlength(text) / 1000 * size / 72.0


def paras_height(paras, width):
    """Height in inches of paragraphs wrapped at `width` (same model as fit_check.py:
    Arial metrics, line height 1.2 x the largest run, space before and after)."""
    total = 0.0
    for para in paras:
        runs = para["runs"]
        avail = width - max(para["marl"], para["marl"] + para["indent"])
        words, cur = [], []
        for run in runs:
            parts = run["text"].split(" ")
            for i, part in enumerate(parts):
                if i > 0:
                    if cur:
                        words.append(cur)
                        cur = []
                    words.append(("SPACE", run["size"], run["bold"]))
                if part:
                    cur.append((part, run["size"], run["bold"]))
        if cur:
            words.append(cur)
        lines, line_w, pending = 1, 0.0, 0.0
        for wd in words:
            if isinstance(wd, tuple):
                pending += text_w(" ", wd[1], wd[2])
                continue
            ww = sum(text_w(t, s, b) for t, s, b in wd)
            if line_w == 0.0:
                line_w = ww
            elif line_w + pending + ww <= avail + 1e-6:
                line_w += pending + ww
            else:
                lines += 1
                line_w = ww
            pending = 0.0
        size = max([r["size"] for r in runs] or [12])
        total += lines * 1.2 * size / 72.0 + (para["before"] + para["after"]) / 72.0
    return total


def fit_h(paras, width, insets=0.04):
    """Box height that keeps the text at FIT of the frame."""
    return (paras_height(paras, width) + insets) / FIT


# ---------------------------------------------------------------- shapes
def _strip_style(shape):
    st = shape._element.find(qn("p:style"))
    if st is not None:
        shape._element.remove(st)


def _alpha(shape, pct):
    clr = shape._element.spPr.find(qn("a:solidFill")).find(qn("a:srgbClr"))
    etree.SubElement(clr, qn("a:alpha")).set("val", str(int(pct * 1000)))


def add_shadow(shape):
    """Soft drop shadow below a white card (Design Ideas: a shadow, never an edge stripe)."""
    eff = etree.SubElement(shape._element.spPr, qn("a:effectLst"))
    sh = etree.SubElement(eff, qn("a:outerShdw"))
    for k, v in (("blurRad", "101600"), ("dist", "25400"), ("dir", "5400000"), ("algn", "t"),
                 ("rotWithShape", "0")):
        sh.set(k, v)
    clr = etree.SubElement(sh, qn("a:srgbClr"))
    clr.set("val", TEXT)
    etree.SubElement(clr, qn("a:alpha")).set("val", "16000")


def tbox(slide, x, y, w, h, paras, name, anchor=TOP, margins=(0, 0.02, 0, 0.02)):
    tb = slide.shapes.add_textbox(E(x), E(y), E(w), E(h))
    tb.name = name
    set_frame(tb.text_frame, margins, anchor)
    fill_tf(tb.text_frame, paras)
    return tb


def shape(slide, kind, x, y, w, h, name, fill=None, line=None, line_w=1.0, dash=None,
          radius=None, paras=None, anchor=CENTER, margins=(0.1, 0.05, 0.1, 0.05), rotation=0):
    s = slide.shapes.add_shape(kind, E(x), E(y), E(w), E(h))
    s.name = name
    _strip_style(s)
    if fill:
        s.fill.solid()
        s.fill.fore_color.rgb = rgb(fill)
    else:
        s.fill.background()
    if line:
        s.line.color.rgb = rgb(line)
        s.line.width = Pt(line_w)
        if dash:
            s.line.dash_style = dash
    else:
        s.line.fill.background()
    if radius is not None and kind in (MSO_SHAPE.ROUNDED_RECTANGLE, MSO_SHAPE.ROUND_2_SAME_RECTANGLE):
        s.adjustments[0] = min(0.5, radius / min(w, h))
    if rotation:
        s.rotation = rotation
    set_frame(s.text_frame, margins, anchor)
    if paras:
        fill_tf(s.text_frame, paras)
    return s


def freeform(slide, pts, name, fill=None, line=None, line_w=1.0, dash=None):
    """Closed polygon through absolute points given in inches."""
    fb = slide.shapes.build_freeform(E(pts[0][0]), E(pts[0][1]))
    fb.add_line_segments([(E(x), E(y)) for x, y in pts[1:]], close=True)
    s = fb.convert_to_shape()
    s.name = name
    _strip_style(s)
    if fill:
        s.fill.solid()
        s.fill.fore_color.rgb = rgb(fill)
    else:
        s.fill.background()
    if line:
        s.line.color.rgb = rgb(line)
        s.line.width = Pt(line_w)
        if dash:
            s.line.dash_style = dash
    else:
        s.line.fill.background()
    return s


def seg(slide, x1, y1, x2, y2, name, color=GREY_UI, width=1.5, dash=None, round_cap=True):
    c = slide.shapes.add_connector(MSO_CONNECTOR.STRAIGHT, E(x1), E(y1), E(x2), E(y2))
    c.name = name
    _strip_style(c)
    c.line.color.rgb = rgb(color)
    c.line.width = Pt(width)
    if dash:
        c.line.dash_style = dash
    if round_cap:
        c.line._get_or_add_ln().set("cap", "rnd")
    return c


def card(slide, x, y, w, h, name, fill=TINT, line=BORDER, radius=0.14):
    return shape(slide, MSO_SHAPE.ROUNDED_RECTANGLE, x, y, w, h, name, fill=fill, line=line,
                 line_w=1.0, radius=radius)


def white_card(slide, x, y, w, h, name, radius=0.14):
    s = shape(slide, MSO_SHAPE.ROUNDED_RECTANGLE, x, y, w, h, name, fill=WHITE, line=BORDER,
              line_w=0.75, radius=radius)
    add_shadow(s)
    return s


def circle(slide, x, y, d, name, fill=PRIMARY, text=None, size=18, color=WHITE, line=None,
           line_w=1.0):
    paras = [P(R(text, size, True, color), align=CENTER_AL)] if text else None
    return shape(slide, MSO_SHAPE.OVAL, x, y, d, d, name, fill=fill, line=line, line_w=line_w,
                 paras=paras, margins=(0, 0, 0, 0))


# ---------------------------------------------------------------- icons: white glyph in a circle
def icon(slide, kind, x, y, d, name, fill=PRIMARY, glyph=WHITE):
    """The deck's one visual motif: a simple white glyph in a #0066A7 circle."""
    if kind == "question":      # one text shape: stays top level, so fit_check measures its glyph
        return circle(slide, x, y, d, f"{name} icon", fill=fill, text="?",
                      size=round(d * 72 * 0.5), color=glyph)
    grp = slide.shapes.add_group_shape()     # circle + glyph parts move together in PowerPoint
    grp.name = f"{name} icon group"
    slide = grp                               # GroupShape.shapes has the same add_* API
    circle(slide, x, y, d, f"{name} icon", fill=fill)
    g = d * 0.56
    gx, gy = x + (d - g) / 2, y + (d - g) / 2
    lw = max(1.0, round(d * 4.0, 2))
    nm = f"{name} icon glyph"

    def at(u, v):
        return gx + u * g, gy + v * g

    def box(k, u, v, uw, vh, col=glyph, radius=None, line=None, line_w=None, rotation=0):
        return shape(slide, k, gx + u * g, gy + v * g, uw * g, vh * g, nm, fill=col, line=line,
                     line_w=line_w or lw * 0.6, radius=radius, rotation=rotation)

    def ring(u, v, uw, vh, col=glyph, width=None):
        return shape(slide, MSO_SHAPE.OVAL, gx + u * g, gy + v * g, uw * g, vh * g, nm, fill=None,
                     line=col, line_w=width or lw)

    def poly(pts, col=glyph):
        return freeform(slide, [at(u, v) for u, v in pts], nm, fill=col)

    def stroke(pts, col=glyph, width=None):
        for (u1, v1), (u2, v2) in zip(pts, pts[1:]):
            seg(slide, *at(u1, v1), *at(u2, v2), nm, color=col, width=width or lw)

    def person(u0, v0, s, outline=None):
        box(MSO_SHAPE.OVAL, u0 + 0.31 * s, v0, 0.38 * s, 0.38 * s, line=outline)
        b = box(MSO_SHAPE.ROUND_2_SAME_RECTANGLE, u0 + 0.10 * s, v0 + 0.44 * s, 0.80 * s, 0.56 * s,
                line=outline)
        b.adjustments[0] = 0.5

    if kind == "person":
        person(0.06, 0.0, 0.88)
    elif kind == "team":
        person(0.0, 0.02, 0.66)
        person(0.30, 0.20, 0.80, outline=fill)
    elif kind == "gear":
        box(MSO_SHAPE.GEAR_6, 0.0, 0.0, 1.0, 1.0)
        box(MSO_SHAPE.OVAL, 0.34, 0.34, 0.32, 0.32, col=fill)
    elif kind == "shield":
        poly([(0.5, 0.0), (0.94, 0.15), (0.90, 0.55), (0.74, 0.80), (0.5, 1.0), (0.26, 0.80),
              (0.10, 0.55), (0.06, 0.15)])
        stroke([(0.30, 0.50), (0.45, 0.66), (0.72, 0.36)], col=fill)
    elif kind == "building":
        box(MSO_SHAPE.RECTANGLE, 0.20, 0.0, 0.60, 1.0)
        for u in (0.31, 0.57):
            for v in (0.12, 0.34, 0.56):
                box(MSO_SHAPE.RECTANGLE, u, v, 0.12, 0.13, col=fill)
        box(MSO_SHAPE.RECTANGLE, 0.43, 0.78, 0.14, 0.22, col=fill)
    elif kind == "buildings":
        box(MSO_SHAPE.RECTANGLE, 0.0, 0.32, 0.40, 0.68)
        box(MSO_SHAPE.RECTANGLE, 0.48, 0.0, 0.52, 1.0)
        for u, v in ((0.12, 0.46), (0.12, 0.68), (0.60, 0.14), (0.80, 0.14), (0.60, 0.40),
                     (0.80, 0.40), (0.60, 0.66), (0.80, 0.66)):
            box(MSO_SHAPE.RECTANGLE, u, v, 0.12, 0.12, col=fill)
    elif kind == "home":
        box(MSO_SHAPE.ISOSCELES_TRIANGLE, 0.0, 0.0, 1.0, 0.48)
        box(MSO_SHAPE.RECTANGLE, 0.16, 0.44, 0.68, 0.56)
        box(MSO_SHAPE.RECTANGLE, 0.42, 0.66, 0.16, 0.34, col=fill)
    elif kind == "speech":
        box(MSO_SHAPE.ROUNDED_RECTANGULAR_CALLOUT, 0.0, 0.04, 1.0, 0.66)
        stroke([(0.18, 0.26), (0.82, 0.26)], col=fill)
        stroke([(0.18, 0.46), (0.60, 0.46)], col=fill)
    elif kind == "phone":
        box(MSO_SHAPE.ROUNDED_RECTANGLE, 0.24, 0.0, 0.52, 1.0, radius=0.06 * d)
        box(MSO_SHAPE.RECTANGLE, 0.31, 0.10, 0.38, 0.66, col=fill)
        box(MSO_SHAPE.OVAL, 0.44, 0.83, 0.12, 0.10, col=fill)
    elif kind == "bell":
        poly([(0.44, 0.04), (0.56, 0.04), (0.58, 0.12), (0.72, 0.20), (0.78, 0.34), (0.80, 0.54),
              (0.86, 0.66), (0.94, 0.74), (0.94, 0.82), (0.06, 0.82), (0.06, 0.74), (0.14, 0.66),
              (0.20, 0.54), (0.22, 0.34), (0.28, 0.20), (0.42, 0.12)])
        box(MSO_SHAPE.OVAL, 0.41, 0.84, 0.18, 0.16)
    elif kind == "clock":
        box(MSO_SHAPE.OVAL, 0.0, 0.0, 1.0, 1.0)
        box(MSO_SHAPE.OVAL, 0.13, 0.13, 0.74, 0.74, col=fill)
        stroke([(0.5, 0.5), (0.5, 0.26)])
        stroke([(0.5, 0.5), (0.68, 0.62)])
    elif kind == "orgchart":
        box(MSO_SHAPE.RECTANGLE, 0.36, 0.0, 0.28, 0.24)
        for u in (0.0, 0.36, 0.72):
            box(MSO_SHAPE.RECTANGLE, u, 0.72, 0.28, 0.24)
        stroke([(0.5, 0.24), (0.5, 0.72)], width=lw * 0.7)
        stroke([(0.14, 0.72), (0.14, 0.48), (0.86, 0.48), (0.86, 0.72)], width=lw * 0.7)
    elif kind == "inbox":
        poly([(0.0, 0.46), (0.28, 0.46), (0.36, 0.62), (0.64, 0.62), (0.72, 0.46), (1.0, 0.46),
              (1.0, 1.0), (0.0, 1.0)])
        box(MSO_SHAPE.DOWN_ARROW, 0.34, 0.0, 0.32, 0.44)
    elif kind == "assign":
        box(MSO_SHAPE.RIGHT_ARROW, 0.0, 0.38, 0.46, 0.30)
        person(0.50, 0.10, 0.50)
    elif kind == "sheet":
        box(MSO_SHAPE.RECTANGLE, 0.04, 0.08, 0.92, 0.84)
        for v in (0.36, 0.64):
            stroke([(0.04, v), (0.96, v)], col=fill, width=lw * 0.6)
        for u in (0.36, 0.66):
            stroke([(u, 0.08), (u, 0.92)], col=fill, width=lw * 0.6)
    elif kind == "trophy":
        poly([(0.14, 0.02), (0.86, 0.02), (0.82, 0.32), (0.66, 0.52), (0.34, 0.52), (0.18, 0.32)])
        box(MSO_SHAPE.RECTANGLE, 0.44, 0.50, 0.12, 0.24)
        box(MSO_SHAPE.ROUNDED_RECTANGLE, 0.24, 0.74, 0.52, 0.16, radius=0.02 * d)
    elif kind == "screen":
        box(MSO_SHAPE.RECTANGLE, 0.0, 0.06, 0.74, 0.52)
        for u, v in ((0.10, 0.36), (0.30, 0.24), (0.50, 0.14)):
            box(MSO_SHAPE.RECTANGLE, u, v, 0.13, 0.52 - v, col=fill)
        box(MSO_SHAPE.RECTANGLE, 0.31, 0.58, 0.12, 0.12)
        box(MSO_SHAPE.RECTANGLE, 0.18, 0.70, 0.38, 0.07)
        box(MSO_SHAPE.ROUNDED_RECTANGLE, 0.68, 0.34, 0.32, 0.62, radius=0.04 * d, line=fill)
        box(MSO_SHAPE.RECTANGLE, 0.74, 0.43, 0.20, 0.40, col=fill)
    elif kind == "pen":
        poly([(0.06, 0.94), (0.16, 0.64), (0.70, 0.10), (0.90, 0.30), (0.36, 0.84)])
        stroke([(0.16, 0.64), (0.36, 0.84)], col=fill, width=lw * 0.7)
        stroke([(0.60, 0.20), (0.80, 0.40)], col=fill, width=lw * 0.7)
    elif kind == "docplay":
        box(MSO_SHAPE.FOLDED_CORNER, 0.12, 0.0, 0.76, 1.0)
        box(MSO_SHAPE.ISOSCELES_TRIANGLE, 0.34, 0.34, 0.34, 0.30, col=fill, rotation=90)
    elif kind == "star":
        box(MSO_SHAPE.STAR_5_POINT, 0.0, 0.02, 1.0, 0.95)
    elif kind == "globe":
        ring(0.02, 0.02, 0.96, 0.96)
        ring(0.28, 0.02, 0.44, 0.96)
        stroke([(0.02, 0.5), (0.98, 0.5)])
    elif kind == "chat":
        box(MSO_SHAPE.OVAL_CALLOUT, 0.0, 0.02, 1.0, 0.72)
        for u in (0.25, 0.44, 0.63):
            box(MSO_SHAPE.OVAL, u, 0.31, 0.12, 0.12, col=fill)
    elif kind == "toggle":
        box(MSO_SHAPE.ROUNDED_RECTANGLE, 0.0, 0.24, 1.0, 0.52, radius=0.26 * g)
        box(MSO_SHAPE.OVAL, 0.54, 0.31, 0.38, 0.38, col=fill)
    elif kind == "clipboard":
        box(MSO_SHAPE.ROUNDED_RECTANGLE, 0.12, 0.08, 0.76, 0.92, radius=0.05 * d)
        box(MSO_SHAPE.ROUNDED_RECTANGLE, 0.32, 0.0, 0.36, 0.18, col=fill, radius=0.03 * d)
        for v in (0.40, 0.58, 0.76):
            stroke([(0.27, v), (0.73, v)], col=fill, width=lw * 0.6)
    elif kind == "plug":
        box(MSO_SHAPE.RECTANGLE, 0.30, 0.0, 0.10, 0.30)
        box(MSO_SHAPE.RECTANGLE, 0.60, 0.0, 0.10, 0.30)
        box(MSO_SHAPE.ROUNDED_RECTANGLE, 0.16, 0.28, 0.68, 0.36, radius=0.05 * d)
        box(MSO_SHAPE.RECTANGLE, 0.45, 0.62, 0.10, 0.38)
    elif kind == "target":
        box(MSO_SHAPE.OVAL, 0.0, 0.0, 1.0, 1.0)
        box(MSO_SHAPE.OVAL, 0.17, 0.17, 0.66, 0.66, col=fill)
        box(MSO_SHAPE.OVAL, 0.34, 0.34, 0.32, 0.32)
    elif kind == "bars":
        for u, v in ((0.06, 0.52), (0.39, 0.26), (0.72, 0.0)):
            box(MSO_SHAPE.RECTANGLE, u, v, 0.22, 1.0 - v)
    elif kind == "key":
        box(MSO_SHAPE.OVAL, 0.0, 0.28, 0.44, 0.44)
        box(MSO_SHAPE.OVAL, 0.13, 0.41, 0.18, 0.18, col=fill)
        box(MSO_SHAPE.RECTANGLE, 0.40, 0.44, 0.58, 0.12)
        box(MSO_SHAPE.RECTANGLE, 0.74, 0.56, 0.09, 0.16)
        box(MSO_SHAPE.RECTANGLE, 0.88, 0.56, 0.09, 0.22)
    elif kind == "calendar":
        box(MSO_SHAPE.ROUNDED_RECTANGLE, 0.04, 0.12, 0.92, 0.86, radius=0.05 * d)
        stroke([(0.04, 0.34), (0.96, 0.34)], col=fill, width=lw * 0.7)
        box(MSO_SHAPE.RECTANGLE, 0.24, 0.0, 0.10, 0.24, line=fill)
        box(MSO_SHAPE.RECTANGLE, 0.66, 0.0, 0.10, 0.24, line=fill)
        for u in (0.22, 0.45, 0.68):
            for v in (0.48, 0.72):
                box(MSO_SHAPE.RECTANGLE, u, v, 0.11, 0.11, col=fill)
    elif kind == "tag":
        poly([(0.02, 0.28), (0.64, 0.28), (0.98, 0.5), (0.64, 0.72), (0.02, 0.72)])
        box(MSO_SHAPE.OVAL, 0.66, 0.44, 0.12, 0.12, col=fill)
        stroke([(0.16, 0.90), (0.84, 0.10)], col=fill, width=lw * 1.2)
    elif kind == "link":
        ring(0.0, 0.24, 0.62, 0.52, width=lw * 1.1)
        ring(0.38, 0.24, 0.62, 0.52, width=lw * 1.1)
    elif kind == "gauge":
        box(MSO_SHAPE.BLOCK_ARC, 0.0, 0.12, 1.0, 1.0)
        stroke([(0.5, 0.62), (0.76, 0.32)])
        box(MSO_SHAPE.OVAL, 0.41, 0.53, 0.18, 0.18)
    elif kind == "lock":
        ring(0.26, 0.0, 0.48, 0.66, width=lw * 1.1)
        box(MSO_SHAPE.ROUNDED_RECTANGLE, 0.12, 0.40, 0.76, 0.60, radius=0.04 * d)
        box(MSO_SHAPE.OVAL, 0.43, 0.56, 0.14, 0.14, col=fill)
    elif kind == "layers":
        for v in (0.50, 0.30, 0.10):
            box(MSO_SHAPE.DIAMOND, 0.0, v, 1.0, 0.42, line=fill)
    elif kind == "book":
        box(MSO_SHAPE.RECTANGLE, 0.0, 0.14, 0.47, 0.74)
        box(MSO_SHAPE.RECTANGLE, 0.53, 0.14, 0.47, 0.74)
        for v in (0.34, 0.52, 0.70):
            stroke([(0.10, v), (0.37, v)], col=fill, width=lw * 0.55)
            stroke([(0.63, v), (0.90, v)], col=fill, width=lw * 0.55)
    elif kind == "spark":
        box(MSO_SHAPE.STAR_4_POINT, 0.0, 0.22, 0.74, 0.78)
        box(MSO_SHAPE.STAR_4_POINT, 0.62, 0.0, 0.38, 0.42)
    elif kind == "store":
        box(MSO_SHAPE.ISOSCELES_TRIANGLE, 0.04, 0.0, 0.92, 0.36)
        box(MSO_SHAPE.RECTANGLE, 0.12, 0.40, 0.76, 0.60)
        box(MSO_SHAPE.RECTANGLE, 0.42, 0.62, 0.16, 0.38, col=fill)
    elif kind == "check":
        stroke([(0.14, 0.54), (0.40, 0.80), (0.88, 0.24)], width=lw * 1.15)
    elif kind == "plus":
        box(MSO_SHAPE.MATH_PLUS, 0.0, 0.0, 1.0, 1.0)
    else:
        raise SystemExit(f"unknown icon {kind!r}")


# ---------------------------------------------------------------- template plumbing
def remove_all_slides(prs):
    sld_id_lst = prs.slides._sldIdLst
    for sld_id in list(sld_id_lst):
        rid = sld_id.rId
        sld_id_lst.remove(sld_id)
        prs.part.drop_rel(rid)
        if rid in prs.part.rels:
            raise SystemExit(f"relationship {rid} still present after removing its slide")
    if len(prs.slides) != 0:
        raise SystemExit("template slides were not all removed")


def layout(prs, name):
    for lo in prs.slide_layouts:
        if lo.name == name:
            return lo
    raise SystemExit(f"layout {name!r} not in template")


def derive_geometry(prs):
    """Read the footer band and the logo from the master so nothing is hard-wired blind."""
    m = prs.slide_master
    sw, sh = prs.slide_width, prs.slide_height
    band_top, logo = None, None
    for s in m.shapes:
        if s.is_placeholder or s.width * s.height > 0.4 * sw * sh:
            continue  # placeholders and the watermark
        if s.top > sh / 2:
            band_top = s.top if band_top is None else min(band_top, s.top)
        elif s.shape_type == MSO_SHAPE_TYPE.PICTURE:
            logo = s
    if band_top is None or logo is None:
        raise SystemExit("could not find the footer band or the logo on the master")
    return band_top / EMU_IN, logo.left / EMU_IN, (logo.top + logo.height) / EMU_IN


def update_doc_props(prs, slide_titles):
    """docProps: neutral author (no personal names), title, and app.xml counts."""
    from datetime import datetime, timezone
    from xml.sax.saxutils import escape
    cp = prs.core_properties
    cp.title = "Academy 2.0: what we built over today's airpay.academy"
    cp.subject = "C-suite review, 18 November 2026 (built, not yet live)"
    cp.author = "Airpay Learning & Development"
    cp.last_modified_by = "Airpay Learning & Development"
    cp.revision = 1
    now = datetime.now(timezone.utc).replace(microsecond=0, tzinfo=None)
    cp.created = now
    cp.modified = now
    fonts = ["Aptos", "Arial", "Calibri", "Segoe UI Symbol"]
    titles = fonts + ["Custom Design"] + slide_titles
    vec = "".join(f"<vt:lpstr>{escape(t)}</vt:lpstr>" for t in titles)
    app = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\r\n'
        '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
        'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
        '<TotalTime>0</TotalTime><Words>0</Words><Application>Microsoft Office PowerPoint</Application>'
        '<PresentationFormat>Widescreen</PresentationFormat><Paragraphs>0</Paragraphs>'
        f'<Slides>{len(slide_titles)}</Slides><Notes>{len(slide_titles)}</Notes><HiddenSlides>0</HiddenSlides>'
        '<MMClips>0</MMClips><ScaleCrop>false</ScaleCrop><HeadingPairs><vt:vector size="6" baseType="variant">'
        f'<vt:variant><vt:lpstr>Fonts Used</vt:lpstr></vt:variant><vt:variant><vt:i4>{len(fonts)}</vt:i4></vt:variant>'
        '<vt:variant><vt:lpstr>Theme</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant>'
        '<vt:variant><vt:lpstr>Slide Titles</vt:lpstr></vt:variant>'
        f'<vt:variant><vt:i4>{len(slide_titles)}</vt:i4></vt:variant></vt:vector></HeadingPairs>'
        f'<TitlesOfParts><vt:vector size="{len(titles)}" baseType="lpstr">{vec}</vt:vector></TitlesOfParts>'
        '<Company></Company><LinksUpToDate>false</LinksUpToDate><SharedDoc>false</SharedDoc>'
        '<HyperlinksChanged>false</HyperlinksChanged><AppVersion>16.0000</AppVersion></Properties>')
    for part in prs.part.package.iter_parts():
        if str(part.partname) == "/docProps/app.xml":
            part._blob = app.encode("utf-8")
            break
    else:
        raise SystemExit("docProps/app.xml not found")


# ---------------------------------------------------------------- spec parsing
def spec_sections(spec_text):
    out = {}
    for sec in re.split(r"^(?=## Slide \d+\.)", spec_text, flags=re.M):
        m = re.match(r"## Slide (\d+)\.", sec)
        if m:
            out[int(m.group(1))] = sec
    return out


def load_notes(sections, count):
    notes = {}
    for key, sec in sections.items():
        lines = sec.splitlines()
        for i, line in enumerate(lines):
            if line.startswith("- **Speaker notes:**"):
                buf = [line[len("- **Speaker notes:**"):].strip()]
                for nxt in lines[i + 1:]:
                    if not nxt.strip() or nxt.lstrip().startswith("<!--") or nxt.startswith("- **"):
                        break
                    buf.append(nxt.strip())
                notes[key] = re.sub(r"\s+", " ", " ".join(buf)).replace("**", "").strip()
                break
    missing = [k for k in range(1, count + 1) if k not in notes]
    if missing:
        raise SystemExit(f"speaker notes missing in the spec for slides {missing}")
    return notes


def load_catalogue(sections):
    """Slides 14-16: [(heading, [item, ...]), ...] per slide; item = dict(text, off, up)."""
    cat = {}
    for num in (14, 15, 16):
        groups = []
        for line in sections[num].splitlines():
            m = re.match(r"  - \*\*(.+)\*\*$", line)
            if m:
                groups.append((m.group(1), []))
                continue
            m = re.match(r"    - (.+)$", line)
            if m and groups:
                t = m.group(1).strip()
                off = t.startswith(OFF + " ")
                if off:
                    t = t[2:].strip()
                up = t.endswith(" " + UP)
                if up:
                    t = t[:-2].strip()
                t = re.sub(r'"([^"]*)"', "\u201c\\1\u201d", t).replace("'", "\u2019")
                groups[-1][1].append({"text": t, "off": off, "up": up})
        cat[num] = groups
    items = [i for g in cat.values() for _, its in g for i in its]
    n, n_up, n_off = len(items), sum(i["up"] for i in items), sum(i["off"] for i in items)
    if (n, n - n_up, n_up, n_off) != (112, 71, 41, 48):
        raise SystemExit(f"catalogue counts changed: {n} lines, {n - n_up} new, {n_up} rebuilt, "
                         f"{n_off} switched off (spec says 112/71/41/48): update slide 2")
    return cat, (n, n - n_up, n_up, n_off)


# ---------------------------------------------------------------- build
def build():
    prs = Presentation(str(TEMPLATE))
    band_top, logo_left, logo_bottom = derive_geometry(prs)
    content_title = layout(prs, "Title and Content").placeholders[0]
    L = round(content_title.left / EMU_IN, 3)                               # 0.917 in
    R_EDGE = round((content_title.left + content_title.width) / EMU_IN, 3)  # 12.417 in
    W = R_EDGE - L                                                          # 11.5 in
    TITLE_TOP, TITLE_H = 0.399, 0.85
    TITLE_W = round(logo_left - L - 0.1, 3)            # stops 0.1 in short of the logo
    FOOT_H = 0.28
    FOOT_Y = round(band_top - 0.04 - FOOT_H, 3)       # deck footer line, above the band
    TOP_Y = 1.40                                       # content top, below the title box
    BOT_Y = round(FOOT_Y - 0.19, 3)                    # content bottom (6.47 in)
    LEG_Y, LEG_H = round(BOT_Y - 0.28, 3), 0.28        # legend line at the foot
    BEN_Y, BEN_H = 5.62, 0.46                          # benefit box (ends 6.08, clear of the legend)
    TODAY_Y = round(BEN_Y - 0.08 - 0.34, 3)            # Today line above the benefit box (slides 7, 9-12)
    CONTENT_END = round(TODAY_Y - 0.06, 3)             # content bottom on those slides (5.14 in)
    assert abs(band_top - 6.979) < 0.01 and abs(logo_left - 11.292) < 0.01, (band_top, logo_left)
    assert TITLE_TOP + TITLE_H < TOP_Y and logo_bottom < TOP_Y

    sections = spec_sections(SPEC.read_text(encoding="utf-8"))
    notes = load_notes(sections, 18)
    catalogue, (n_all, n_new, n_up, n_off) = load_catalogue(sections)
    remove_all_slides(prs)

    def new_slide(layout_name):
        return prs.slides.add_slide(layout(prs, layout_name))

    def title(slide, text):
        ph = slide.shapes.title
        ph.left, ph.top, ph.width, ph.height = E(L), E(TITLE_TOP), E(TITLE_W), E(TITLE_H)
        set_frame(ph.text_frame, (0, 0, 0, 0), CENTER, autosize_none=False)
        fill_tf(ph.text_frame, [P(R(text, 32, True, TEXT))])
        return ph

    def body_ph(slide, idx, x, y, w, h, paras, anchor=TOP, margins=(0, 0.03, 0, 0.03)):
        ph = slide.placeholders[idx]
        ph.left, ph.top, ph.width, ph.height = E(x), E(y), E(w), E(h)
        set_frame(ph.text_frame, margins, anchor, autosize_none=False)
        fill_tf(ph.text_frame, paras)
        # A placeholder sits low in the z-order; bring it above the panel drawn behind it.
        el = ph._element
        tree = el.getparent()
        tree.remove(el)
        tree.append(el)
        return ph

    def footer(slide):
        tbox(slide, L, FOOT_Y, 6.6, FOOT_H, [P(R(FOOTER_TEXT, 12, color=MUTED))],
             "Deck footer", anchor=CENTER)
        tbox(slide, R_EDGE - 0.6, FOOT_Y, 0.6, FOOT_H, [P(align=RIGHT_AL, field="slidenum")],
             "Slide number", anchor=CENTER)

    def legend(slide, paras=None):
        paras = paras or [P(S(OFF, 12, MUTED), R("  " + LEGEND_OFF, 12, color=MUTED))]
        tbox(slide, L, LEG_Y, W, LEG_H, paras, "Legend", anchor=CENTER)

    def benefit(slide, text, y=BEN_Y):
        card(slide, L, y, W, BEN_H, "Benefit box", fill=LIGHT, line=None, radius=0.12)
        icon(slide, "check", L + 0.16, y + (BEN_H - 0.32) / 2, 0.32, "Benefit")
        tbox(slide, L + 0.62, y, W - 0.8, BEN_H,
             [P(R("Benefit", 14, True, PRIMARY), R("   " + text, 16, False, TEXT))],
             "Benefit text", anchor=CENTER)

    def today_line(slide, text, y, h=0.34):
        tbox(slide, L, y, W, h, [P(R("Today: ", 14, True, MUTED, italic=True),
                                   R(text, 14, False, MUTED, italic=True))], "Today line",
             anchor=CENTER)

    def items(lines, size=18, before=8, marl=0.36, bullet_on=CHECK):
        """Body lines: a check for the standard build, a hollow circle for 'switched on when
        we choose'. A line given as (text, True) is switched off."""
        out = []
        for ln in lines:
            text, off = (ln, False) if isinstance(ln, str) else ln
            colour = MUTED if off else PRIMARY
            out.append(P(R(text, size, False, TEXT), before=before, marl=marl,
                         bullet=(OFF if off else bullet_on, colour)))
        if out:
            out[0]["before"] = 0
        return out

    def set_notes(slide, key):
        slide.notes_slide.notes_text_frame.text = notes[key]

    # ---------------------------------------------- comparison helper (slides 4, 6, 13)
    PANEL_Y, PANEL_H = TOP_Y, 4.10
    PW = (W - 0.4) / 2

    def comparison(slide, left_lines, right_lines, icon_kind, left_runs=None):
        lx, rx = L, L + PW + 0.4
        card(slide, lx, PANEL_Y, PW, PANEL_H, "Today panel", fill=TINT, line=None)
        white_card(slide, rx, PANEL_Y, PW, PANEL_H, "Academy 2.0 card")
        icon(slide, icon_kind, rx + 0.3, PANEL_Y + 0.22, 0.56, "Academy 2.0 heading")
        body_ph(slide, 1, lx + 0.3, PANEL_Y + 0.22, PW - 0.6, 0.56,
                [P(R("Today on airpay.academy", 20, True, MUTED))], anchor=CENTER)
        left = left_runs or [P(R(t, 18, False, MUTED), before=10, marl=0.32, bullet=(DOT, MUTED))
                             for t in left_lines]
        left[0]["before"] = 0
        body_ph(slide, 2, lx + 0.3, PANEL_Y + 1.0, PW - 0.6, PANEL_H - 1.2, left)
        body_ph(slide, 3, rx + 1.02, PANEL_Y + 0.22, 2.6, 0.56,
                [P(R("Academy 2.0", 20, True, PRIMARY))], anchor=CENTER)
        right = items(right_lines, 18, 10)
        body_ph(slide, 4, rx + 0.3, PANEL_Y + 1.0, PW - 0.6, PANEL_H - 1.2, right)
        return rx

    # ============================================================ 1. Cover
    s = new_slide("1_Title Slide")
    fill_tf(s.shapes.title.text_frame, [P(R("Academy 2.0", 54, True, PRIMARY), align=CENTER_AL)])
    fill_tf(s.placeholders[1].text_frame, [
        P(R("What we built over today\u2019s airpay.academy", 26, False, TEXT), align=CENTER_AL),
        P(R("C-suite review, 18 November 2026 \u00b7 Learning & Development", 16, False, MUTED),
          align=CENTER_AL, before=14),
    ])
    set_notes(s, 1)

    # ============================================================ 2. Headline
    s = new_slide("Title Only")
    title(s, "From today\u2019s academy to Academy 2.0")
    stats = [(str(n_all), f"capabilities built ({n_new} new)"),
             ("5", "ways to reach a learner (today: mainly e-mail)"),
             ("2", "English and Hindi on every screen"),
             ("3", "organisations, each kept apart")]
    pad, gap = 0.22, 0.22
    cw = (W - 2 * pad - 3 * gap) / 4
    num_h = (1.2 * 60 / 72 + 0.04) / FIT
    lab_h = max(fit_h([P(R(lab, 14))], cw - 0.4) for _, lab in stats)
    card_h = 0.08 + num_h + lab_h + 0.1
    panel_h = card_h + 2 * pad
    card(s, L, TOP_Y, W, panel_h, "Stat panel", fill=TINT, line=None)
    for i, (num, lab) in enumerate(stats):
        x = L + pad + i * (cw + gap)
        white_card(s, x, TOP_Y + pad, cw, card_h, f"Stat {i + 1} card")
        tbox(s, x + 0.2, TOP_Y + pad + 0.06, cw - 0.4, num_h, [P(R(num, 60, True, PRIMARY))],
             f"Stat {i + 1} number", anchor=CENTER)
        tbox(s, x + 0.2, TOP_Y + pad + 0.06 + num_h, cw - 0.4, lab_h, [P(R(lab, 14, False, TEXT))],
             f"Stat {i + 1} label")
    shifts = [("Vendor platform", "our own product"), ("Deadlines tracked", "deadlines chased"),
              ("One screen for all", "a home per role"), ("No AI", "AI with a person in charge")]
    row_y0, row_h, row_gap = TOP_Y + panel_h + 0.2, 0.44, 0.08
    for i, (frm, to) in enumerate(shifts):
        y = row_y0 + i * (row_h + row_gap)
        circle(s, L + 0.01, y + (row_h - 0.42) / 2, 0.42, f"Shift {i + 1} number", text=str(i + 1),
               size=14)
        tbox(s, L + 0.6, y, 2.6, row_h, [P(R(frm, 18, False, MUTED))], f"Shift {i + 1} from",
             anchor=CENTER)
        shape(s, MSO_SHAPE.RIGHT_ARROW, L + 3.3, y + row_h / 2 - 0.13, 0.6, 0.26,
              f"Shift {i + 1} arrow", fill=ORANGE if i == 1 else MUTED)   # 6.3:1 on white
        tbox(s, L + 4.15, y, W - 4.15, row_h, [P(R(to, 20, True, TEXT))], f"Shift {i + 1} to",
             anchor=CENTER)
    foot_y = row_y0 + 4 * row_h + 3 * row_gap + 0.1
    tbox(s, L, foot_y, W, 0.32, [P(R("Built, not yet live.", 14, True, MUTED, italic=True))],
         "Status line", anchor=CENTER)
    assert foot_y + 0.32 <= BOT_Y, foot_y
    footer(s)
    set_notes(s, 2)

    # ============================================================ 3. Each group
    s = new_slide("Title Only")
    title(s, "What changes for each group")
    groups3 = [("person", "Learners", "What is due, at a glance, in Hindi or English, on any phone"),
               ("team", "Managers", "The whole team\u2019s progress, approvals and assignments in one place"),
               ("gear", "L&D and admins", "HR uploads previewed first; every access change logged"),
               ("shield", "Compliance", "Audit-ready status and a trail of every reminder"),
               ("building", "The business", "Our own platform, upgraded on our schedule")]
    rh, rg = 0.80, 0.16
    for i, (ic, grp, line) in enumerate(groups3):
        y = TOP_Y + 0.05 + i * (rh + rg)
        card(s, L, y, W, rh, f"{grp} row")
        icon(s, ic, L + 0.22, y + (rh - 0.56) / 2, 0.56, grp)
        tbox(s, L + 1.0, y, 2.55, rh, [P(R(grp, 20, True, PRIMARY))], f"{grp} name", anchor=CENTER)
        tbox(s, L + 3.6, y, W - 3.8, rh, [P(R(line, 18, False, TEXT))], f"{grp} benefit",
             anchor=CENTER)
    footer(s)
    set_notes(s, 3)

    # ============================================================ 4. Learner home
    s = new_slide("Comparison")
    title(s, "A clear home for every learner")
    comparison(s, ["Vendor theme, tabbed dashboard",
                   "Catalogue search, trending and suggested blocks",
                   "Certificates in a block and a plain list"],
               ["A home per role: due, overdue, continue",
                "One navigation on every page",
                "Catalogue with search, filters and trending",
                "Every certificate on one wall",
                "First-day onboarding; accessibility built in"], "home")
    benefit(s, "Learners see what is due without hunting.")
    footer(s)
    set_notes(s, 4)

    # ============================================================ 5. Language and phone
    s = new_slide("Title Only")
    title(s, "In their language, on their phone")
    # A line is a string (standard build) or a run list (holds the hollow circle: switched off).
    cols5 = [("speech", "Language", ["Hindi on every screen.",
                                   [S(OFF, 18, MUTED),
                                    R(" Marathi, Kannada and Swahili prepared.", 18, False, TEXT)]],
              "English in practice."),
             ("phone", "Phone", ["Designed for the phone first.",
                                 [S(OFF, 18, MUTED), R(" Installable app with lock-screen alerts.", 18, False, TEXT)]],
              "no app or phone alerts of our own."),
             ("bell", "Channels", [[R("E-mail and in-app; ", 18, False, TEXT), S(OFF, 18, MUTED),
                                    R(" phone, WhatsApp and SMS.", 18, False, TEXT)],
                                   "Learners pick channels and quiet hours."], "mainly e-mail.")]
    cw5, cg5, ch5 = (W - 2 * 0.3) / 3, 0.3, BEN_Y - 0.2 - TOP_Y
    def today5(today):          # 14pt, like the Today line on every other slide
        return [P(R("Today: ", 14, True, MUTED, italic=True), R(today, 14, False, MUTED, italic=True))]

    # The Today box sits 0.14 in above the column foot; the text box fills the space above it.
    th5 = max(fit_h(today5(c[3]), cw5 - 0.6) for c in cols5)
    ty5 = TOP_Y + ch5 - 0.14 - th5
    text5_y = TOP_Y + 1.32
    text5_h = round(ty5 - 0.03 - text5_y, 3)
    for i, (ic, head, lines, today) in enumerate(cols5):
        x = L + i * (cw5 + cg5)
        card(s, x, TOP_Y, cw5, ch5, f"{head} column")
        icon(s, ic, x + 0.3, TOP_Y + 0.28, 0.8, head)
        tbox(s, x + 1.3, TOP_Y + 0.28, cw5 - 1.5, 0.8, [P(R(head, 22, True, PRIMARY))],
             f"{head} heading", anchor=CENTER)
        paras5 = [P(*(t if isinstance(t, list) else [R(t, 18, False, TEXT)]), before=0 if k == 0 else 10)
                  for k, t in enumerate(lines)]
        assert fit_h(paras5, cw5 - 0.6) <= text5_h, head
        tbox(s, x + 0.3, text5_y, cw5 - 0.6, text5_h, paras5, f"{head} text")
        tbox(s, x + 0.3, ty5, cw5 - 0.6, th5, today5(today), f"{head} today", anchor=CENTER)
    benefit(s, "Reminders land where people actually look.")
    legend(s)
    footer(s)
    set_notes(s, 5)

    # ============================================================ 6. Compliance
    s = new_slide("Comparison")
    title(s, "Compliance training that chases itself")
    left6 = [P(R("A deadline reminder that ", 18, False, MUTED), R("has never fired", 18, True, TEXT),
               marl=0.32, bullet=("\u25CF", ORANGE)),
             P(R("No escalation to managers", 18, False, MUTED), before=10, marl=0.32,
               bullet=(DOT, MUTED)),
             P(R("No compliance-officer role", 18, False, MUTED), before=10, marl=0.32,
               bullet=(DOT, MUTED))]
    rx = comparison(s, None, ["Red, amber, green matrix per organisation",
                              ("Reminders 7, 3 and 1 day before", True),
                              ("Manager escalation after the deadline", True),
                              "Every nudge logged",
                              "Exemptions with a reason; auditor export"], "clock", left_runs=left6)
    # Illustration only (no data): a small red, amber, green and grey matrix, top right.
    pattern = [[GREEN, GREEN, AMBER, GREEN, RED], [GREEN, RED, GREEN, GREEN, AMBER],
               [AMBER, GREEN, GREEN, GREY_UI, GREEN]]
    d6, p6 = 0.15, 0.21
    gx6 = rx + PW - 0.3 - (4 * p6 + d6)
    for r_i, row in enumerate(pattern):
        for c_i, colour in enumerate(row):
            circle(s, gx6 + c_i * p6, PANEL_Y + 0.28 + r_i * p6, d6, f"Matrix dot {r_i + 1}.{c_i + 1}",
                   fill=colour)
    benefit(s, "Audit-ready any time; overdue people chased automatically.")
    legend(s)
    footer(s)
    set_notes(s, 6)

    # ============================================================ 7. Managers
    s = new_slide("Title Only")
    title(s, "Managers see and act on their team")
    cards7 = [("team", "My Team", "Progress, compliance, last activity"),
              ("orgchart", "Wider team", "Everyone below them in the reporting line"),
              ("inbox", "Approvals inbox", "Approve, reject with a reason, delegate"),
              ("assign", "Assign learning", "To many people at once, with due dates"),
              ("sheet", "Team export", "A performance summary as a spreadsheet"),
              ("bell", "Digests and nudges", [S(OFF, 16, MUTED),
                                              R(" A monthly summary; an alert at 3+ overdue", 16, False, TEXT)])]
    pad7, g7 = 0.18, 0.2
    panel7_h = round(CONTENT_END - TOP_Y, 3)   # room for the Today line, benefit box and legend
    card(s, L, TOP_Y, W, panel7_h, "Manager panel", fill=TINT, line=None)
    cw7 = (W - 2 * pad7 - 2 * g7) / 3
    ch7 = (panel7_h - 2 * pad7 - g7) / 2
    for i, (ic, head, line) in enumerate(cards7):
        r_i, c_i = divmod(i, 3)
        x = L + pad7 + c_i * (cw7 + g7)
        y = TOP_Y + pad7 + r_i * (ch7 + g7)
        white_card(s, x, y, cw7, ch7, f"{head} card")
        icon(s, ic, x + 0.25, y + 0.2, 0.56, head)
        tbox(s, x + 0.92, y + 0.18, cw7 - 1.05, 0.6, [P(R(head, 18, True, TEXT))], f"{head} heading",
             anchor=CENTER)
        runs7 = [R(line, 16, False, TEXT)] if isinstance(line, str) else line
        assert fit_h([P(*runs7)], cw7 - 0.5) <= ch7 - 0.92, head
        tbox(s, x + 0.25, y + 0.84, cw7 - 0.5, ch7 - 0.92, [P(*runs7)], f"{head} text")
    today_line(s, "direct reports only; requests approved one at a time.", TODAY_Y)
    benefit(s, "Managers act on their own team without waiting for an L&D report.")
    legend(s)
    footer(s)
    set_notes(s, 7)

    # ============================================================ 8. Training formats (table)
    s = new_slide("Title and Content")
    title(s, "Today\u2019s training formats, kept and strengthened")
    rows8 = [("Learning plans", [S(OFF, 16, MUTED), R(" Paths that branch on quiz scores", 16, True)]),
             ("Programs", [S(OFF, 16, MUTED), R(" A “My programs” page for each learner", 16, True)]),
             ("Classroom", [R("Calendar invites; ", 16, True), S(OFF, 16, MUTED),
                            R(" QR attendance", 16, True)]),
             ("Online exams", [R("Proctoring with human review", 16, True)]),
             ("Feedback forms", [R("NPS scoring; per-question anonymity", 16, True)]),
             ("Recertification", [S(OFF, 16, MUTED), R(" Renewal rules set centrally; every reset logged", 16, True)])]
    ph = s.placeholders[1]
    ph.left, ph.top, ph.width = E(L), E(TOP_Y), E(W)
    gf = TablePlaceholder(ph._element, ph._parent).insert_table(len(rows8) + 1, 2)
    gf.name = "Formats table"
    tbl = gf.table
    tbl.first_row, tbl.horz_banding = True, False
    col_w, head_h, row_h8 = [3.4, W - 3.4], 0.52, 0.58
    for i, w in enumerate(col_w):
        tbl.columns[i].width = E(w)
    tbl.rows[0].height = E(head_h)
    for i in range(1, len(rows8) + 1):
        tbl.rows[i].height = E(row_h8)
    gf.height = E(head_h + row_h8 * len(rows8))

    def cell(r, c, paras, fill, margins=(0.2, 0.05, 0.15, 0.05)):
        ce = tbl.cell(r, c)
        ce.fill.solid()
        ce.fill.fore_color.rgb = rgb(fill)
        ce.margin_left, ce.margin_top = E(margins[0]), E(margins[1])
        ce.margin_right, ce.margin_bottom = E(margins[2]), E(margins[3])
        ce.vertical_anchor = CENTER
        fill_tf(ce.text_frame, paras)

    cell(0, 0, [P(R("Today", 16, True, WHITE))], PRIMARY)
    cell(0, 1, [P(R("Academy 2.0 adds", 16, True, WHITE))], PRIMARY)
    for i, (today, runs) in enumerate(rows8, start=1):
        band = WHITE if i % 2 == 1 else TINT
        cell(i, 0, [P(R(today, 16, False, MUTED))], band)
        cell(i, 1, [P(R("+  ", 16, True, PRIMARY), *runs)], band)
    benefit(s, "Every format in use today is kept, and each does more.")
    legend(s)
    footer(s)
    set_notes(s, 8)

    # ============================================================ 9. Engagement
    s = new_slide("Two Content")
    title(s, "Learning people want to come back to")
    panel9_h = round(CONTENT_END - TOP_Y, 3)
    lx9, rx9 = L, L + PW + 0.4
    white_card(s, lx9, TOP_Y, PW, panel9_h, "Motivation card")
    white_card(s, rx9, TOP_Y, PW, panel9_h, "Live polls card")
    icon(s, "trophy", lx9 + 0.3, TOP_Y + 0.22, 0.6, "Motivation")
    icon(s, "screen", rx9 + 0.3, TOP_Y + 0.22, 0.6, "Live polls")
    tbox(s, lx9 + 1.05, TOP_Y + 0.22, PW - 1.35, 0.6, [P(R("Motivation", 20, True, PRIMARY))],
         "Motivation heading", anchor=CENTER)
    tbox(s, rx9 + 1.05, TOP_Y + 0.22, PW - 1.35, 0.6,
         [P(S(OFF, 20, MUTED), R(" Live polls in the room", 20, True, PRIMARY))],
         "Live polls heading", anchor=CENTER)
    # Illustrations (no data): a seven-day streak row; bars of live results.
    sy = TOP_Y + panel9_h - 0.62
    for k in range(7):
        done = k < 5
        circle(s, lx9 + 0.3 + k * 0.48, sy, 0.34, f"Streak day {k + 1}",
               fill=PRIMARY if done else WHITE, line=None if done else PRIMARY, line_w=1.5)
    bx0, base_y = rx9 + PW - 0.3 - 5 * 0.3, TOP_Y + panel9_h - 0.25
    bars9 = (0.3, 0.5, 0.4, 0.62, 0.24)
    body9_y, body9_h = TOP_Y + 1.0, 1.8
    assert body9_y + body9_h <= min(sy, base_y - max(bars9)) - 0.05, (body9_y, sy, base_y)
    for k, hgt in enumerate(bars9):
        shape(s, MSO_SHAPE.RECTANGLE, bx0 + k * 0.3, base_y - hgt, 0.22, hgt, f"Result bar {k + 1}",
              fill=PRIMARY if k % 2 == 0 else ACCENT)
    seg(s, bx0 - 0.06, base_y, bx0 + 5 * 0.3 - 0.02, base_y, "Result baseline", color=GREY_UI,
        width=1.25, round_cap=False)
    body_ph(s, 1, lx9 + 0.3, body9_y, PW - 0.6, body9_h,
            items(["Points, levels, badges, daily streaks",
                   ("Live leaderboards with a \u201chide me\u201d switch", True),
                   ("Challenges and course ratings", True)], 17, 9))
    body_ph(s, 2, rx9 + 0.3, body9_y, PW - 0.6, body9_h,
            items(["Polls, quizzes, word clouds, Q&A", "The audience answers on their phones",
                   "Results update live on screen", "No outside subscription"], 17, 9,
                  bullet_on=DOT))
    today_line(s, "points, badges and ratings; no streaks, leaderboards or live polls.", TODAY_Y)
    benefit(s, "Reasons to come back, and livelier sessions with no paid polling tool.")
    legend(s)
    footer(s)
    set_notes(s, 9)

    # ============================================================ 10. AI
    s = new_slide("Title Only")
    title(s, "AI drafts, a person approves, spend capped")
    ai = [("pen", "Course drafts from a topic or document"),
          ("question", "Quizzes in English and Hindi"),
          ("docplay", "Procedure document to e-learning"),
          ("star", "\u201cFor You\u201d picks, each with a reason"),
          ("globe", "Translation that keeps key terms"),
          ("chat", "A copilot that acts only on \u201cyes\u201d")]
    cw10, g10, ch10, rg10 = (W - 2 * 0.3) / 3, 0.3, 1.32, 0.24
    for i, (ic, text) in enumerate(ai):
        r_i, c_i = divmod(i, 3)
        x = L + c_i * (cw10 + g10)
        y = TOP_Y + r_i * (ch10 + rg10)
        card(s, x, y, cw10, ch10, f"AI helper {i + 1} card")
        icon(s, ic, x + 0.25, y + (ch10 - 0.62) / 2, 0.62, f"AI helper {i + 1}")
        # Every helper is built and switched off (sample mode): hollow circle, as on slide 15.
        tbox(s, x + 1.1, y + 0.1, cw10 - 1.3, ch10 - 0.2,
             [P(S(OFF, 17, MUTED), R(" " + text, 17, False, TEXT))],
             f"AI helper {i + 1} text", anchor=CENTER)
    chips = ["A person approves every draft", "Hard spend caps", "Course content de-identified"]
    chip_y, chip_h = TOP_Y + 2 * ch10 + rg10 + 0.26, 0.54
    for i, text in enumerate(chips):
        x = L + i * (cw10 + g10)
        shape(s, MSO_SHAPE.ROUNDED_RECTANGLE, x, chip_y, cw10, chip_h, f"Guardrail {i + 1} chip",
              fill=LIGHT, radius=chip_h / 2)
        icon(s, "shield", x + 0.12, chip_y + (chip_h - 0.36) / 2, 0.36, f"Guardrail {i + 1}")
        tbox(s, x + 0.6, chip_y, cw10 - 0.75, chip_h, [P(R(text, 14, True, TEXT))],
             f"Guardrail {i + 1} text", anchor=CENTER)
    tbox(s, L, TODAY_Y, W, 0.34,
         [P(R("Today: ", 14, True, MUTED, italic=True), R("no AI.  ", 14, False, MUTED, italic=True),
            R("Academy 2.0: ", 14, True, PRIMARY, italic=True),
            R("built, in sample mode for now.", 14, False, MUTED, italic=True))],
         "Status line", anchor=CENTER)
    assert chip_y + chip_h <= CONTENT_END, chip_y
    benefit(s, "Faster first drafts, with a person deciding what learners see.")
    legend(s)
    footer(s)
    set_notes(s, 10)

    # ============================================================ 11. Skills and insight
    s = new_slide("Two Content")
    title(s, "See skill gaps and risks early")
    panel11_h = round(CONTENT_END - TOP_Y, 3)
    lx11, rx11 = L, L + PW + 0.4
    white_card(s, lx11, TOP_Y, PW, panel11_h, "Skills card")
    white_card(s, rx11, TOP_Y, PW, panel11_h, "Insight card")
    icon(s, "target", lx11 + 0.3, TOP_Y + 0.18, 0.6, "Skills")
    icon(s, "bars", rx11 + 0.3, TOP_Y + 0.18, 0.6, "Insight")
    tbox(s, lx11 + 1.05, TOP_Y + 0.18, PW - 1.35, 0.6, [P(R("Skills and talent", 20, True, PRIMARY))],
         "Skills heading", anchor=CENTER)
    tbox(s, rx11 + 1.05, TOP_Y + 0.18, PW - 1.35, 0.6, [P(R("Insight for L&D", 20, True, PRIMARY))],
         "Insight heading", anchor=CENTER)
    body11_y, body11_h = TOP_Y + 0.88, 1.52
    ill_y = body11_y + body11_h + 0.05
    ill_h = round(TOP_Y + panel11_h - 0.1 - ill_y, 3)
    # Radar (illustration, no data): role need (dashed) against a person's profile (filled).
    rcx, rcy, rr = lx11 + PW / 2, ill_y + ill_h / 2, ill_h / 2

    def hexpts(scales):
        return [(rcx + rr * sc * math.cos(math.radians(-90 + 60 * k)),
                 rcy + rr * sc * math.sin(math.radians(-90 + 60 * k))) for k, sc in enumerate(scales)]

    freeform(s, hexpts([1] * 6), "Radar outer ring", line=GREY_UI, line_w=1.0)
    freeform(s, hexpts([0.5] * 6), "Radar inner ring", line=BORDER, line_w=1.0)
    for k, (px, py) in enumerate(hexpts([1] * 6)):
        seg(s, rcx, rcy, px, py, f"Radar spoke {k + 1}", color=BORDER, width=1.0, round_cap=False)
    prof = freeform(s, hexpts([0.62, 0.85, 0.45, 0.7, 0.55, 0.8]), "Radar profile", fill=ACCENT,
                    line=PRIMARY, line_w=1.5)
    _alpha(prof, 35)
    freeform(s, hexpts([0.9, 0.9, 0.8, 0.85, 0.9, 0.85]), "Radar role need", line=MUTED, line_w=1.25,
             dash=MSO_LINE_DASH_STYLE.DASH)
    # Funnel (illustration, no figures).
    steps = [("Enrolled", 2.9, PRIMARY_DARK), ("Started", 2.35, PRIMARY), ("Completed", 1.8, ACCENT_DEEP),
             ("Certified", 1.3, ACCENT)]          # white 14 pt bold on ACCENT is 3.9:1 (large text)
    fg = 0.03
    fh = (ill_h - 3 * fg) / 4          # 14 pt label with no vertical inset: about 85%
    fy0 = ill_y + (ill_h - (4 * fh + 3 * fg)) / 2
    for k, (lab, fw, colour) in enumerate(steps):
        shape(s, MSO_SHAPE.ROUNDED_RECTANGLE, rx11 + PW / 2 - fw / 2, fy0 + k * (fh + fg), fw, fh,
              f"Funnel step {k + 1}", fill=colour, radius=0.06,
              paras=[P(R(lab, 14, True, WHITE), align=CENTER_AL)], margins=(0.04, 0, 0.04, 0))
    body_ph(s, 1, lx11 + 0.3, body11_y, PW - 0.6, body11_h,
            items(["Skills each role needs, and the gap", "Managers validate skills",
                   ("AI proposes skills from course content", True),
                   ("Career paths, succession, internal jobs", True)], 16, 5, marl=0.32))
    body_ph(s, 2, rx11 + 0.3, body11_y, PW - 0.6, body11_h,
            items(["Completion and learning-funnel dashboard",
                   ("At-risk learners flagged, with reasons", True),
                   ("Training ROI with visible assumptions", True),
                   "Today\u2019s standard reports kept"], 16, 5, marl=0.32))
    today_line(s, "a skills list without gap analysis; vendor reports.", TODAY_Y)
    benefit(s, "Training aimed at real skill gaps, and an early warning on learners falling behind.")
    legend(s)
    footer(s)
    set_notes(s, 11)

    # ============================================================ 12. HR data
    s = new_slide("Title Only")
    title(s, "Less admin: the academy follows HR data")
    flow = [[P(R("HR file", 17, True, TEXT)),
             P(S(OFF, 14, MUTED), R(" or live Keka sync", 14, False, MUTED), before=2)],
            [P(R("Accounts created, moved, closed", 17, True, TEXT))],
            [P(S(OFF, 17, MUTED), R(" Joiners get mandatory courses", 17, True, TEXT))],
            [P(R("Reminders and reports", 17, True, TEXT))]]
    ag = 0.4
    bw12 = (W - 3 * ag) / 4
    th_flow = max(fit_h(p, bw12 - 0.36) for p in flow)
    bh12 = 0.7 + th_flow + 0.08
    for i, paras in enumerate(flow):
        x = L + i * (bw12 + ag)
        card(s, x, TOP_Y, bw12, bh12, f"Step {i + 1} box", fill=LIGHT, line=None)
        circle(s, x + 0.2, TOP_Y + 0.18, 0.44, f"Step {i + 1} number", text=str(i + 1), size=16)
        tbox(s, x + 0.2, TOP_Y + 0.7, bw12 - 0.36, th_flow, paras, f"Step {i + 1} text")
        if i < 3:
            shape(s, MSO_SHAPE.RIGHT_ARROW, x + bw12 + 0.07, TOP_Y + bh12 / 2 - 0.14, ag - 0.14, 0.28,
                  f"Step {i + 1} arrow", fill=MUTED)          # 6.3:1 on white
    cards12 = [("toggle", [R("Switchboard", 19, True, TEXT)],
                "Each feature on per organisation; every change recorded"),
               ("clipboard", [R("Permission log", 19, True, TEXT)], "Who changed what, and when"),
               ("plug", [S(OFF, 19, MUTED), R(" Plugs into our systems", 19, True, TEXT)],
                "Open interface, automatic account set-up, notices to other systems")]
    cy12 = TOP_Y + bh12 + 0.2
    cw12 = (W - 2 * 0.3) / 3
    tw12 = cw12 - 0.95 - 0.2
    paras12 = [[P(*head), P(R(line, 15, False, TEXT), before=4)] for _, head, line in cards12]
    th12 = max(fit_h(p, tw12) for p in paras12)
    ch12 = max(0.2 + th12 + 0.14, 1.2)
    assert cy12 + ch12 <= CONTENT_END, (cy12, ch12)
    for i, (ic, head, line) in enumerate(cards12):
        x = L + i * (cw12 + 0.3)
        white_card(s, x, cy12, cw12, ch12, f"Control card {i + 1}")
        icon(s, ic, x + 0.25, cy12 + 0.2, 0.56, f"Control {i + 1}")
        tbox(s, x + 0.95, cy12 + 0.2, tw12, th12, paras12[i], f"Control {i + 1} text")
    today_line(s, "HR file upload; no permission log, no feature switches.", TODAY_Y)
    benefit(s, "Less manual admin, and a record of every access change.")
    legend(s)
    footer(s)
    set_notes(s, 12)

    # ============================================================ 13. Kept apart, checked
    s = new_slide("Comparison")
    title(s, "Organisations kept apart, every action checked")
    comparison(s, ["Access checks missing in add-ons", "Payments not verified",
                   "No multi-factor sign-in", "Most add-ons outside data rights"],
               ["Every action checks role and organisation", ("Payments checked before access", True),
                ("Single sign-on and multi-factor login", True),
                "Data requests and consent in one hub", "Audit logs throughout"], "shield")
    benefit(s, "Built to host partners safely, and for India\u2019s data-protection law.")
    legend(s)
    footer(s)
    set_notes(s, 13)

    # ============================================================ 14-16. Catalogue
    group_icons = {"Learner experience": "person", "Languages and phones": "phone",
                   "Reaching learners": "bell", "Engagement": "trophy", "Training formats": "book",
                   "AI, with people in charge": "spark", "Skills and talent": "target",
                   "Insight": "bars", "Managers": "team", "Compliance": "shield",
                   "Public storefront": "store", "L&D administration": "gear",
                   "Organisations and brand": "buildings", "Trust and privacy": "lock",
                   "Integrations": "plug", "Platform": "layers"}
    CAT_PAD_X, CAT_PAD_Y, CAT_GAP = 0.2, 0.1, 0.25
    CAT_H = LEG_Y - 0.06 - TOP_Y
    CAT_INNER = CAT_H - 2 * CAT_PAD_Y
    ICON_D, HEAD_GAP, GROUP_GAP = 0.32, 0.03, 0.14
    CAT_FIT = 0.895           # fit_check fails above 0.90

    def cat_paras(its):
        out = []
        for k, it in enumerate(its):
            colour = MUTED if it["off"] else TEXT
            runs = [R(it["text"], 12, False, colour)]
            if it["up"]:
                runs.append(S(" " + UP, 12, PRIMARY, bold=True))   # no-break: arrow stays with its word
            out.append(P(*runs, before=0 if k == 0 else 1.5, marl=0.19,
                         bullet=(OFF, MUTED) if it["off"] else (CHECK, PRIMARY)))
        return out

    def head_paras(text):
        return [P(R(text, 14, True, PRIMARY))]

    def group_dims(grp, colw):
        head, its = grp
        hh = max(ICON_D, fit_h(head_paras(head), colw - ICON_D - 0.1, 0.02))
        ih = (paras_height(cat_paras(its), colw) + 0.04) / CAT_FIT
        return hh, ih

    def plan(groups, total_w, min_w=2.2, max_w=3.8, step=0.05):
        n = len(groups)
        widths = [round(min_w + k * step, 2) for k in range(int(round((max_w - min_w) / step)) + 1)]
        cache = {}

        def dims(i, w):
            if (i, w) not in cache:
                cache[(i, w)] = group_dims(groups[i], w)
            return cache[(i, w)]

        # Columns keep the spec's group order. The first heading row is aligned across the four
        # columns (as tall as its tallest heading). Score: fits first, then fewest headings that
        # wrap to two lines, then the lowest tallest column, then the most even widths.
        best = None
        for cuts in itertools.combinations(range(1, n), 3):
            parts = [list(range(a, b)) for a, b in zip((0,) + cuts, cuts + (n,))]
            for w1, w2, w3 in itertools.product(widths, repeat=3):
                w4 = round(total_w - w1 - w2 - w3, 2)
                if not min_w <= w4 <= max_w:
                    continue
                ws = (w1, w2, w3, w4)
                first = max(dims(p[0], ws[c])[0] for c, p in enumerate(parts))
                hs, wraps = [], 0
                for c, p in enumerate(parts):
                    h = GROUP_GAP * (len(p) - 1)
                    for k, i in enumerate(p):
                        hh, ih = dims(i, ws[c])
                        wraps += hh > ICON_D + 0.05
                        h += (first if k == 0 else hh) + HEAD_GAP + ih
                    hs.append(h)
                score = (max(hs) > CAT_INNER, wraps, round(max(hs), 2), round(max(ws) - min(ws), 2))
                if best is None or score < best[0]:
                    best = (score, parts, ws, hs, first)
        return best

    cat_titles = {14: "Everything we built: learners and reach",
                  15: "Everything we built: learning, AI and insight",
                  16: "Everything we built: control, trust and platform"}
    plans = {}
    for num in (14, 15, 16):
        groups = catalogue[num]
        s = new_slide("Title Only")
        title(s, cat_titles[num])
        card(s, L, TOP_Y, W, CAT_H, "Catalogue panel", fill=TINT, line=None)
        total_w = W - 2 * CAT_PAD_X - 3 * CAT_GAP
        (score, parts, ws, hs, first) = plan(groups, total_w)
        if score[0]:
            raise SystemExit(f"slide {num}: catalogue needs {max(hs):.2f} in, has {CAT_INNER:.2f} in")
        plans[num] = (parts, ws, hs)
        x = L + CAT_PAD_X
        for c, part in enumerate(parts):
            y = TOP_Y + CAT_PAD_Y
            for k, gi in enumerate(part):
                head, its = groups[gi]
                hh, ih = group_dims(groups[gi], ws[c])
                row_h = first if k == 0 else hh
                if row_h <= ICON_D + 0.05:     # one-line heading row: centred on the icon
                    icon(s, group_icons[head], x, y + (row_h - ICON_D) / 2, ICON_D, head)
                    tbox(s, x + ICON_D + 0.1, y, ws[c] - ICON_D - 0.1, row_h, head_paras(head),
                         f"{head} heading", anchor=CENTER, margins=(0, 0.01, 0, 0.01))
                else:                          # tall row: first text line level with the icon
                    icon(s, group_icons[head], x, y, ICON_D, head)
                    tbox(s, x + ICON_D + 0.1, y + 0.03, ws[c] - ICON_D - 0.1, row_h - 0.03,
                         head_paras(head), f"{head} heading", anchor=TOP, margins=(0, 0.01, 0, 0.01))
                y += row_h + HEAD_GAP
                tbox(s, x, y, ws[c], ih, cat_paras(its), f"{head} items")
                y += ih + GROUP_GAP
            x += ws[c] + CAT_GAP
        leg = [P(S(CHECK, 12, PRIMARY), R(" standard build \u00b7 ", 12, color=MUTED),
                 S(OFF, 12, MUTED), R(" " + LEGEND_OFF + " \u00b7 ", 12, color=MUTED),
                 S(UP, 12, PRIMARY, bold=True),
                 R(" rebuilds what today\u2019s site has; no arrow: new to airpay.academy", 12,
                   color=MUTED),
                 *([R(" \u00b7 * native-speaker review first", 12, color=MUTED)] if num == 14 else []))]
        if fit_h(leg, W, 0.04) > LEG_H:
            raise SystemExit(f"slide {num}: legend does not fit on one line")
        legend(s, leg)
        footer(s)
        set_notes(s, num)

    # ============================================================ 17. Owning the platform
    s = new_slide("Title Only")
    title(s, "Why owning the platform matters")
    own = [("key", "Ours", "Our own code and design; no vendor needed to upgrade it"),
           ("calendar", "Current", "The long-term Moodle release, supported to 2029"),
           ("tag", "Open source", "No paid polling or push service"),
           ("buildings", "One platform", "Every organisation, each kept apart, each branded"),
           ("link", "Sellable", "Another company\u2019s brand by configuration, not code"),
           ("gauge", "Controlled", "AI spend capped; every new feature behind a switch")]
    cw17, ch17, g17 = 4.05, 1.36, 0.28
    hub_d = 2.5
    stack_h = 3 * ch17 + 2 * g17
    y17 = TOP_Y + (BOT_Y - 0.1 - TOP_Y - stack_h) / 2
    hub_cx, hub_cy = L + W / 2, y17 + stack_h / 2
    for i in range(6):
        side, row = divmod(i, 3)
        cy = y17 + row * (ch17 + g17) + ch17 / 2
        x_edge = L + cw17 if side == 0 else R_EDGE - cw17
        seg(s, x_edge, cy, hub_cx, hub_cy, f"Hub link {i + 1}", color=ACCENT, width=1.75,
            round_cap=False)                       # 3.9:1 on white: visible on a projector
    circle(s, hub_cx - hub_d / 2, hub_cy - hub_d / 2, hub_d, "Hub", fill=PRIMARY)
    tbox(s, hub_cx - hub_d * 0.35, hub_cy - hub_d * 0.35, hub_d * 0.7, hub_d * 0.7,
         [P(R("Sentientia", 24, True, WHITE), align=CENTER_AL),
          P(R("LMS", 24, True, WHITE), align=CENTER_AL),
          P(R("Airpay-owned", 15, False, WHITE), align=CENTER_AL, before=4)],
         "Hub text", anchor=CENTER)
    for i, (ic, head, line) in enumerate(own):
        side, row = divmod(i, 3)
        x = L if side == 0 else R_EDGE - cw17
        y = y17 + row * (ch17 + g17)
        white_card(s, x, y, cw17, ch17, f"{head} card")
        icon(s, ic, x + 0.22, y + 0.22, 0.56, head)
        paras17 = [P(R(head, 19, True, ORANGE if head == "Ours" else TEXT)),
                   P(R(line, 16, False, TEXT), before=3)]
        assert fit_h(paras17, cw17 - 1.1) <= ch17 - 0.2, head
        tbox(s, x + 0.95, y + 0.1, cw17 - 1.1, ch17 - 0.2, paras17, f"{head} text", anchor=CENTER)
    footer(s)
    set_notes(s, 17)

    # ============================================================ 18. End
    s = new_slide("End/last Slide")
    ph = s.shapes.title
    mid = prs.slide_width / EMU_IN / 2
    ph.left, ph.top, ph.width, ph.height = E(mid - 2.9), E(1.6), E(5.8), E(1.2)
    ph.text_frame.vertical_anchor = BOTTOM
    fill_tf(ph.text_frame, [P(R("Thank you", 40, True, WHITE), align=CENTER_AL)])
    set_notes(s, 18)

    # Slide-number fields: cache each slide's own number as the field text.
    for n, slide in enumerate(prs.slides, start=1):
        for fld in slide._element.iter(qn("a:fld")):
            if fld.get("type") == "slidenum":
                fld.find(qn("a:t")).text = str(n)

    titles = [sl.shapes.title.text_frame.text for sl in prs.slides]
    update_doc_props(prs, titles)
    prs.save(str(OUT))
    print(f"wrote {OUT} ({len(prs.slides)} slides)")
    print(f"catalogue: {n_all} lines, {n_new} new, {n_up} rebuilt (arrow), {n_off} switched off")
    for num, (parts, ws, hs) in plans.items():
        names = [[catalogue[num][i][0] for i in p] for p in parts]
        print(f"  slide {num}: columns {names} widths {ws} heights {[round(h, 2) for h in hs]} "
              f"of {CAT_INNER:.2f} in")
    print(f"geometry: band top {band_top:.3f} in, logo left {logo_left:.3f} in, logo bottom "
          f"{logo_bottom:.3f} in; content x {L:.3f}-{R_EDGE:.3f}, y {TOP_Y:.2f}-{BOT_Y:.2f}; "
          f"title box {L:.3f},{TITLE_TOP:.3f} {TITLE_W:.3f} x {TITLE_H:.2f}; deck footer y "
          f"{FOOT_Y:.3f}-{FOOT_Y + FOOT_H:.3f}")


if __name__ == "__main__":
    sys.exit(build())
