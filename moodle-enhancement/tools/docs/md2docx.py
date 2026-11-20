"""Convert a stakeholder-pack Markdown file to a branded Word document.

Usage: python md2docx.py <input.md> <output.docx>

Supports the Markdown subset defined in docs/stakeholder-packs/2026-11/00-MASTER-PROMPT.md section 6:
headings (# to ####), paragraphs, bullet and numbered lists (nested by indentation), pipe tables,
fenced code blocks, block quotes, horizontal rules, **bold**, *italic*, `code` and [links](url).
Adds a confidentiality footer and page numbers. Brand: Airpay primary #0066A7, accent #1985DD.
"""
import re
import sys

from docx import Document
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Pt, RGBColor, Cm

PRIMARY = RGBColor(0x00, 0x66, 0xA7)
TEXT = RGBColor(0x1A, 0x1A, 0x2E)
MUTED = RGBColor(0x5A, 0x60, 0x70)
FONT = 'Arial'
CONFIDENTIAL = 'Confidential: Airpay Payment Services internal. Not for external distribution.'

INLINE = re.compile(r'(\*\*[^*]+\*\*|\*[^*\s][^*]*\*|`[^`]+`|\[[^\]]+\]\([^)]+\))')


def set_cell_shading(cell, hex_fill):
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement('w:shd')
    shd.set(qn('w:val'), 'clear')
    shd.set(qn('w:color'), 'auto')
    shd.set(qn('w:fill'), hex_fill)
    tc_pr.append(shd)


def add_runs(par, text, base_bold=False, size=None, color=None):
    pos = 0
    for m in INLINE.finditer(text):
        if m.start() > pos:
            r = par.add_run(text[pos:m.start()])
            r.bold = base_bold
            _style(r, size, color)
        tok = m.group(0)
        if tok.startswith('**'):
            r = par.add_run(tok[2:-2]); r.bold = True; _style(r, size, color)
        elif tok.startswith('`'):
            r = par.add_run(tok[1:-1]); r.font.name = 'Consolas'; r.font.size = Pt(9); r.bold = base_bold
        elif tok.startswith('['):
            label, url = re.match(r'\[([^\]]+)\]\(([^)]+)\)', tok).groups()
            r = par.add_run(label); r.underline = True; r.font.color.rgb = PRIMARY; r.bold = base_bold
            if not url.startswith('#'):
                r2 = par.add_run(f' ({url})'); r2.font.size = Pt(8); r2.font.color.rgb = MUTED
        else:
            r = par.add_run(tok[1:-1]); r.italic = True; r.bold = base_bold; _style(r, size, color)
        pos = m.end()
    if pos < len(text):
        r = par.add_run(text[pos:]); r.bold = base_bold; _style(r, size, color)


def _style(run, size, color):
    if size:
        run.font.size = size
    if color is not None:
        run.font.color.rgb = color


def add_footer(doc):
    for section in doc.sections:
        fp = section.footer.paragraphs[0]
        fp.text = ''
        fp.alignment = WD_ALIGN_PARAGRAPH.CENTER
        r = fp.add_run(CONFIDENTIAL + '   |   Page ')
        r.font.size = Pt(8); r.font.color.rgb = MUTED
        # PAGE field
        run = fp.add_run()
        for tag, text in (('begin', None), (None, 'PAGE'), ('end', None)):
            if tag:
                el = OxmlElement('w:fldChar'); el.set(qn('w:fldCharType'), tag); run._r.append(el)
            else:
                it = OxmlElement('w:instrText'); it.set(qn('xml:space'), 'preserve'); it.text = text; run._r.append(it)
        run.font.size = Pt(8)


def convert(src, dst):
    lines = open(src, encoding='utf-8').read().replace('\r\n', '\n').split('\n')
    doc = Document()
    st = doc.styles['Normal']
    st.font.name = FONT
    st.font.size = Pt(10.5)
    st.font.color.rgb = TEXT
    st.element.rPr.rFonts.set(qn('w:eastAsia'), FONT)
    for lvl, size in ((1, 20), (2, 15), (3, 12.5), (4, 11)):
        hs = doc.styles[f'Heading {lvl}']
        hs.font.name = FONT; hs.font.size = Pt(size); hs.font.bold = True
        hs.font.color.rgb = PRIMARY if lvl <= 2 else TEXT
        hs.element.rPr.rFonts.set(qn('w:eastAsia'), FONT)
    for s in doc.sections:
        s.left_margin = s.right_margin = Cm(2.0)
        s.top_margin = s.bottom_margin = Cm(2.0)

    i = 0
    first_h1 = True
    while i < len(lines):
        line = lines[i]
        stripped = line.strip()
        if not stripped:
            i += 1; continue
        if stripped.startswith('```'):
            code = []
            i += 1
            while i < len(lines) and not lines[i].strip().startswith('```'):
                code.append(lines[i]); i += 1
            i += 1
            p = doc.add_paragraph()
            p.paragraph_format.left_indent = Cm(0.4)
            r = p.add_run('\n'.join(code)); r.font.name = 'Consolas'; r.font.size = Pt(8.5)
            continue
        m = re.match(r'^(#{1,4})\s+(.*)$', stripped)
        if m:
            lvl = len(m.group(1)); text = m.group(2).strip()
            if lvl == 1 and first_h1:
                first_h1 = False
                t = doc.add_paragraph(); t.paragraph_format.space_before = Pt(60)
                add_runs(t, text, base_bold=True, size=Pt(26), color=PRIMARY)
                c = doc.add_paragraph(); add_runs(c, CONFIDENTIAL, size=Pt(9), color=MUTED)
            else:
                h = doc.add_heading(level=lvl); add_runs(h, text)
            i += 1; continue
        if re.match(r'^(-{3,}|\*{3,}|_{3,})$', stripped):
            p = doc.add_paragraph(); p.paragraph_format.space_after = Pt(4)
            i += 1; continue
        if stripped.startswith('|') and i + 1 < len(lines) and re.match(r'^\s*\|?\s*:?-{2,}', lines[i + 1]):
            rows = []
            while i < len(lines) and lines[i].strip().startswith('|'):
                rows.append(lines[i].strip()); i += 1
            header = [c.strip() for c in rows[0].strip('|').split('|')]
            body = [[c.strip() for c in r.strip('|').split('|')] for r in rows[2:]]
            ncols = len(header)
            table = doc.add_table(rows=1, cols=ncols)
            table.style = 'Table Grid'
            table.alignment = WD_TABLE_ALIGNMENT.CENTER
            for j, h in enumerate(header):
                cell = table.rows[0].cells[j]; cell.text = ''
                add_runs(cell.paragraphs[0], h, base_bold=True, size=Pt(9), color=RGBColor(0xFF, 0xFF, 0xFF))
                set_cell_shading(cell, '0066A7')
            for r in body:
                cells = table.add_row().cells
                for j in range(ncols):
                    val = r[j] if j < len(r) else ''
                    cells[j].text = ''
                    add_runs(cells[j].paragraphs[0], val.replace('<br>', ' '), size=Pt(9))
            doc.add_paragraph()
            continue
        bm = re.match(r'^(\s*)([-*+]|\d+[.)])\s+(.*)$', line)
        if bm:
            indent = len(bm.group(1).replace('\t', '    ')) // 2
            numbered = bm.group(2)[0].isdigit()
            text = bm.group(3)
            text = re.sub(r'^\[( |x|X)\]\s+', lambda mm: '☐ ' if mm.group(1) == ' ' else '☑ ', text)
            style = 'List Number' if numbered else 'List Bullet'
            if indent >= 1:
                style += ' 2' if indent == 1 else ' 3'
            try:
                p = doc.add_paragraph(style=style)
            except KeyError:
                p = doc.add_paragraph(style='List Number' if numbered else 'List Bullet')
            add_runs(p, text)
            i += 1; continue
        if stripped.startswith('>'):
            p = doc.add_paragraph()
            p.paragraph_format.left_indent = Cm(0.8)
            add_runs(p, stripped.lstrip('> ').strip(), color=MUTED)
            i += 1; continue
        para = [stripped]
        i += 1
        while i < len(lines) and lines[i].strip() and not re.match(r'^(#{1,4}\s|```|\||\s*([-*+]|\d+[.)])\s|>)', lines[i]):
            para.append(lines[i].strip()); i += 1
        p = doc.add_paragraph(); add_runs(p, ' '.join(para))
    add_footer(doc)
    doc.save(dst)


if __name__ == '__main__':
    convert(sys.argv[1], sys.argv[2])
    print('wrote', sys.argv[2])
