#!/usr/bin/env python
"""
fit_check.py - text-fit and safe-zone checker for the C-suite deck (no renderer needed).

    python fit_check.py [deck.pptx]          (default: SENTIENTIA-CSUITE-DECK-2026-11-18.pptx)

1. TEXT FIT. Every text frame (shapes, placeholders, table cells) is wrapped word by word with
   Pillow ImageFont using C:/Windows/Fonts/arial.ttf and arialbd.ttf at each run's size. Aptos
   is not installed here and Arial is slightly wider than Aptos and Calibri, so this is a
   conservative proxy. Internal margins (bodyPr insets, cell margins), paragraph indents,
   space before/after and a line height of 1.2 x size (never less) are accounted for; an
   ellipse or arrow-shaped shape uses its preset's inner text rectangle. A frame FAILS when
   (wrapped text height + top/bottom insets) > 90% of the frame height, or when a single word
   is wider than the frame's text width.
2. SAFE ZONES. The zones are derived from the deck's own master and layouts: every
   non-placeholder shape on the master and on the slide's layout (logo, footer band, the
   www.airpay.co.in and copyright text, the Made-in-India strip, the end slide's logo and
   notice), except background art covering more than 40% of the slide (the pale 'a'
   watermark, a full-slide background fill). A slide shape FAILS when its box (rotation
   included) leaves the slide or intersects a zone.
3. HIDDEN TEXT. A shape with text FAILS when a filled shape later in the z-order (drawn on
   top of it) overlaps its box: a card drawn after a placeholder would hide the text.
Exit status 1 on any FAIL.
"""
import math
import sys
from pathlib import Path

from PIL import ImageFont
from pptx import Presentation
from pptx.enum.shapes import MSO_SHAPE_TYPE
from pptx.oxml.ns import qn

HERE = Path(__file__).resolve().parent
DECK = Path(sys.argv[1]) if len(sys.argv) > 1 else HERE / "SENTIENTIA-CSUITE-DECK-2026-11-18.pptx"
EMU = 914400.0
FONT_FILES = {False: "C:/Windows/Fonts/arial.ttf", True: "C:/Windows/Fonts/arialbd.ttf"}
UNITS = 1000  # fonts are loaded at 1000 px per em; widths scale linearly with size
_fonts = {b: ImageFont.truetype(f, UNITS) for b, f in FONT_FILES.items()}
FILL_LIMIT = 0.90
BACKGROUND_SHARE = 0.40
BODY_PH_TYPES = {"BODY", "OBJECT", "SUBTITLE"}

failures, warnings = [], []


def text_width_in(text, size_pt, bold):
    return _fonts[bool(bold)].getlength(text) / UNITS * size_pt / 72.0


# ------------------------------------------------------------------ paragraph model
def run_props(r_el, inherited_sz, inherited_b):
    rpr = r_el.find(qn("a:rPr"))
    sz = rpr.get("sz") if rpr is not None else None
    b = rpr.get("b") if rpr is not None else None
    size = int(sz) / 100.0 if sz else inherited_sz
    bold = (b in ("1", "true")) if b is not None else inherited_b
    return size, bold


def paragraphs(txbody, default_sz, default_b, default_spc_before):
    """Yield (segments, line_height_pt, before_pt, after_pt, marL_in, indent_in) per paragraph.
    segments: list of (text, size, bold) with '\n' as a forced break."""
    for p in txbody.findall(qn("a:p")):
        ppr = p.find(qn("a:pPr"))
        segs = []
        for child in p:
            tag = etree_local(child.tag)
            if tag in ("r", "fld"):
                t = child.find(qn("a:t"))
                if t is not None and t.text:
                    size, bold = run_props(child, default_sz, default_b)
                    segs.append((t.text, size, bold))
            elif tag == "br":
                segs.append(("\n", default_sz, default_b))
        sizes = [s for _, s, _ in segs] or [default_sz]
        if not segs:
            end = p.find(qn("a:endParaRPr"))
            if end is not None and end.get("sz"):
                sizes = [int(end.get("sz")) / 100.0]
        max_sz = max(sizes)
        line_h = 1.2 * max_sz
        before = default_spc_before
        after = 0.0
        marl = indent = 0.0
        if ppr is not None:
            ln = ppr.find(qn("a:lnSpc"))
            if ln is not None:
                pct = ln.find(qn("a:spcPct"))
                pts = ln.find(qn("a:spcPts"))
                if pct is not None:
                    line_h = 1.2 * max_sz * max(1.0, int(pct.get("val")) / 100000.0)
                elif pts is not None:
                    line_h = max(line_h, int(pts.get("val")) / 100.0)
            for name, attr in (("a:spcBef", "before"), ("a:spcAft", "after")):
                el = ppr.find(qn(name))
                if el is not None:
                    pts = el.find(qn("a:spcPts"))
                    pct = el.find(qn("a:spcPct"))
                    val = int(pts.get("val")) / 100.0 if pts is not None else (
                        int(pct.get("val")) / 100000.0 * max_sz if pct is not None else 0.0)
                    if attr == "before":
                        before = val
                    else:
                        after = val
            if ppr.get("marL") is not None:
                marl = int(ppr.get("marL")) / EMU
            if ppr.get("indent") is not None:
                indent = int(ppr.get("indent")) / EMU
        yield segs, line_h, before, after, marl, indent, bool(segs)


def etree_local(tag):
    return tag.rsplit("}", 1)[-1]


def wrap_height(txbody, avail_w, default_sz, default_b, default_before, label):
    """Return (height_in, lines, problems)."""
    total_pt, total_lines, problems = 0.0, 0, []
    for segs, line_h, before, after, marl, indent, has_text in paragraphs(
            txbody, default_sz, default_b, default_before):
        width = avail_w - max(marl, marl + indent)
        # split into words, each word a list of (text, size, bold) pieces
        words, cur, breaks = [], [], []
        for text, size, bold in segs:
            if text == "\n":
                if cur:
                    words.append(cur)
                    cur = []
                words.append("BR")
                continue
            parts = text.split(" ")
            for i, part in enumerate(parts):
                if i > 0:
                    if cur:
                        words.append(cur)
                        cur = []
                    words.append(("SPACE", size, bold))
                if part:
                    cur.append((part, size, bold))
        if cur:
            words.append(cur)
        lines, line_w, pending_space = 1, 0.0, 0.0
        for w in words:
            if w == "BR":
                lines += 1
                line_w, pending_space = 0.0, 0.0
                continue
            if isinstance(w, tuple):  # a space
                pending_space += text_width_in(" ", w[1], w[2])
                continue
            ww = sum(text_width_in(t, s, b) for t, s, b in w)
            word_text = "".join(t for t, _, _ in w)
            if ww > width + 1e-6:
                problems.append(f"word '{word_text}' is {ww:.2f} in wide, text width is {width:.2f} in")
            if line_w == 0.0:
                line_w = ww
            elif line_w + pending_space + ww <= width + 1e-6:
                line_w += pending_space + ww
            else:
                lines += 1
                line_w = ww
            pending_space = 0.0
        if not has_text:
            lines = 1
        total_pt += lines * line_h + before + after
        total_lines += lines
    return total_pt / 72.0, total_lines, problems


# ------------------------------------------------------------------ geometry helpers
def bbox(shape):
    x, y, w, h = shape.left or 0, shape.top or 0, shape.width or 0, shape.height or 0
    rot = getattr(shape, "rotation", 0.0) or 0.0
    if rot:
        th = math.radians(rot)
        bw = abs(w * math.cos(th)) + abs(h * math.sin(th))
        bh = abs(w * math.sin(th)) + abs(h * math.cos(th))
        cx, cy = x + w / 2, y + h / 2
        x, y, w, h = cx - bw / 2, cy - bh / 2, bw, bh
    return x / EMU, y / EMU, w / EMU, h / EMU


def intersects(a, b, tol=0.0):
    ax, ay, aw, ah = a
    bx, by, bw, bh = b
    return ax < bx + bw - tol and bx < ax + aw - tol and ay < by + bh - tol and by < ay + ah - tol


def text_rect_factor(shape):
    """Width/height share of the preset's inner text rectangle."""
    try:
        st = shape.auto_shape_type
    except Exception:
        return 1.0, 1.0, 0.0
    name = str(st)
    if "OVAL" in name:
        return 0.7071, 0.7071, 0.0
    if "CHEVRON" in name:
        return 1.0, 1.0, (shape.height / EMU)  # both notches eat 0.5 x height
    if "PENTAGON" in name:
        return 1.0, 1.0, (shape.height / EMU) * 0.5
    return 1.0, 1.0, 0.0


def derive_zones(prs, slide):
    sw, sh = prs.slide_width, prs.slide_height
    lo = slide.slide_layout
    zones = []
    for owner, shapes in (("master", lo.slide_master.shapes), (f"layout '{lo.name}'", lo.shapes)):
        for s in shapes:
            if s.is_placeholder:
                continue
            if (s.width or 0) * (s.height or 0) > BACKGROUND_SHARE * sw * sh:
                continue  # watermark / full-slide background art
            zones.append((f"{owner}:{s.name}", bbox(s)))
    return zones


def safe_rect(prs, layout_obj, zones):
    """Content-safe rectangle for a layout: placeholder x-span, from the top of the slide to the
    top of the lowest zone band (footer), excluding nothing else (logo handled as a zone)."""
    phs = [p for p in layout_obj.placeholders
           if p.placeholder_format.type is not None and "DATE" not in str(p.placeholder_format.type)
           and "FOOTER" not in str(p.placeholder_format.type)
           and "SLIDE_NUMBER" not in str(p.placeholder_format.type)]
    if phs:
        x0 = min(p.left for p in phs) / EMU
        x1 = max(p.left + p.width for p in phs) / EMU
    else:
        x0, x1 = 0.0, prs.slide_width / EMU
    bottoms = [z[1][1] for z in zones if z[1][1] > prs.slide_height / EMU / 2]
    y1 = min(bottoms) if bottoms else prs.slide_height / EMU
    return x0, x1, y1


# ------------------------------------------------------------------ checks
def check_frame(label, txbody, frame_w, frame_h, ins, default_sz=18.0, default_b=False,
                default_before=0.0, factors=(1.0, 1.0, 0.0)):
    l, t, r, b = ins
    fw, fh, notch = factors
    avail_w = frame_w * fw - notch - l - r
    usable_h = frame_h * fh
    height, lines, problems = wrap_height(txbody, avail_w, default_sz, default_b, default_before, label)
    if not any((tt.text or "").strip() for tt in txbody.iter(qn("a:t"))):
        return None
    need = height + t + b
    ratio = need / usable_h if usable_h > 0 else 99
    status = "ok"
    for pr in problems:
        failures.append(f"{label}: {pr}")
        status = "FAIL"
    if ratio > FILL_LIMIT:
        failures.append(f"{label}: text needs {need:.2f} in of {usable_h:.2f} in "
                        f"({ratio:.0%} > {FILL_LIMIT:.0%}), {lines} line(s)")
        status = "FAIL"
    return status, ratio, lines


def has_solid_fill(sh):
    try:
        return sh.fill.type == 1  # MSO_FILL.SOLID
    except Exception:
        return False


def check_hidden(n, slide):
    """Compare the text's inner rectangle (frame minus insets) with every later filled shape."""
    shapes = list(slide.shapes)
    for i, a in enumerate(shapes):
        has_table = getattr(a, "has_table", False) and a.has_table
        has_text = (a.has_text_frame and a.text_frame.text.strip()) or has_table
        if not has_text:
            continue
        x, y, w, h = bbox(a)
        if a.has_text_frame:
            l, t, r, b_ = insets(a.text_frame._txBody)
            abox = (x + l, y + t, max(0.0, w - l - r), max(0.0, h - t - b_))
        else:
            abox = (x, y, w, h)
        for b in shapes[i + 1:]:
            if not has_solid_fill(b):
                continue
            if intersects(abox, bbox(b), tol=0.005):
                failures.append(f"slide {n} '{a.name}': text is under the later filled shape '{b.name}'")


def insets(tf_el, defaults=(0.1, 0.05, 0.1, 0.05)):
    body = tf_el.find(qn("a:bodyPr"))
    vals = list(defaults)
    if body is not None:
        for i, a in enumerate(("lIns", "tIns", "rIns", "bIns")):
            if body.get(a) is not None:
                vals[i] = int(body.get(a)) / EMU
    return tuple(vals)


def main():
    prs = Presentation(str(DECK))
    sw_in, sh_in = prs.slide_width / EMU, prs.slide_height / EMU
    frames = 0
    seen_layouts = {}
    for n, slide in enumerate(prs.slides, start=1):
        zones = derive_zones(prs, slide)
        check_hidden(n, slide)
        lo = slide.slide_layout
        if lo.name not in seen_layouts:
            seen_layouts[lo.name] = (safe_rect(prs, lo, zones), zones)
        for sh in slide.shapes:
            label = f"slide {n} '{sh.name}'"
            box = bbox(sh)
            x, y, w, h = box
            if x < -1e-3 or y < -1e-3 or x + w > sw_in + 1e-3 or y + h > sh_in + 1e-3:
                failures.append(f"{label}: outside the slide ({x:.2f},{y:.2f},{w:.2f}x{h:.2f})")
            for zname, zbox in zones:
                if intersects(box, zbox):
                    failures.append(f"{label}: overlaps template zone {zname} "
                                    f"(shape {x:.2f},{y:.2f}-{x + w:.2f},{y + h:.2f}; zone "
                                    f"{zbox[0]:.2f},{zbox[1]:.2f}-{zbox[0] + zbox[2]:.2f},{zbox[1] + zbox[3]:.2f})")
            if sh.has_text_frame:
                txbody = sh.text_frame._txBody
                default_sz, default_b, default_before = 18.0, False, 0.0
                if sh.is_placeholder:
                    pt = str(sh.placeholder_format.type)
                    if any(k in pt for k in BODY_PH_TYPES):
                        default_before = 10.0   # master body lvl1 spcBef 10 pt
                    if "TITLE" in pt:
                        default_sz = 24.0
                res = check_frame(label, txbody, w, h, insets(txbody), default_sz, default_b,
                                  default_before, text_rect_factor(sh))
                if res:
                    frames += 1
            if sh.has_table:
                tbl = sh.table
                col_w = [c.width / EMU for c in tbl.columns]
                row_h = [r.height / EMU for r in tbl.rows]
                for ri, row in enumerate(tbl.rows):
                    for ci, ce in enumerate(row.cells):
                        tc = ce._tc
                        if tc.get("hMerge") or tc.get("vMerge"):
                            continue
                        span_c = int(tc.get("gridSpan", "1"))
                        span_r = int(tc.get("rowSpan", "1"))
                        cw = sum(col_w[ci:ci + span_c])
                        ch = sum(row_h[ri:ri + span_r])
                        ins = (ce.margin_left / EMU, ce.margin_top / EMU, ce.margin_right / EMU,
                               ce.margin_bottom / EMU)
                        res = check_frame(f"{label} cell r{ri + 1}c{ci + 1}", tc.find(qn("a:txBody")),
                                          cw, ch, ins)
                        if res:
                            frames += 1
    print(f"deck: {DECK.name}  slides: {len(prs.slides)}  text frames checked: {frames}")
    print("zones and content-safe rectangles (inches), derived from the master and layouts:")
    for name, ((x0, x1, y1), zones) in seen_layouts.items():
        print(f"  layout '{name}': safe x {x0:.3f}-{x1:.3f}, y top-{y1:.3f}")
        for zname, (zx, zy, zw, zh) in zones:
            print(f"      zone {zname}: x {zx:.3f}-{zx + zw:.3f}, y {zy:.3f}-{zy + zh:.3f}")
    if failures:
        print(f"\nFAIL ({len(failures)}):")
        for f in failures:
            print("  - " + f)
        return 1
    print("\nPASS: every text frame fits (<= 90% of its height with Arial metrics), no word wider "
          "than its frame, no shape outside the slide or on a template zone, no text under a later shape.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
