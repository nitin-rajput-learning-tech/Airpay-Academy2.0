"""
SENTIENTIA Agent 1 — SOP Parser
Parses SOP PDF files into structured JSON for narration generation.

Input:  content/sops/*.pdf
Output: content/parsed/*-parsed.json

Rules:
- Max 2000 words per parsed output
- Extract: title, sections, key points, compliance requirements
- No external API calls — local PDF parsing only
- One SOP per run

Usage:
  python sentientia/agent1_sop_parser.py content/sops/AML-Training-SOP.pdf
"""
import sys
import json
import os
from pathlib import Path
from datetime import datetime

# Try multiple PDF parsers
def parse_pdf(filepath: str) -> str:
    """Extract text from PDF. Tries multiple methods."""
    text = ''

    # Method 1: PyPDF2 / pypdf
    try:
        from pypdf import PdfReader
        reader = PdfReader(filepath)
        for page in reader.pages:
            text += page.extract_text() or ''
        if text.strip():
            return text
    except ImportError:
        pass

    # Method 2: pdfminer
    try:
        from pdfminer.high_level import extract_text as pdfminer_extract
        text = pdfminer_extract(filepath)
        if text.strip():
            return text
    except ImportError:
        pass

    # Method 3: pdfplumber
    try:
        import pdfplumber
        with pdfplumber.open(filepath) as pdf:
            for page in pdf.pages:
                text += page.extract_text() or ''
        if text.strip():
            return text
    except ImportError:
        pass

    if not text.strip():
        print("ERROR: No PDF parser available. Install: pip install pypdf pdfplumber")
        sys.exit(1)

    return text


def chunk_text(text: str, max_words: int = 2000) -> str:
    """Truncate to max_words while preserving sentence boundaries."""
    words = text.split()
    if len(words) <= max_words:
        return text

    truncated = ' '.join(words[:max_words])
    # Cut at last sentence boundary
    for sep in ['. ', '.\n', '.\t']:
        idx = truncated.rfind(sep)
        if idx > len(truncated) * 0.7:
            return truncated[:idx + 1]

    return truncated


def extract_sections(text: str) -> list:
    """Extract sections from SOP text based on common heading patterns."""
    import re
    lines = text.split('\n')
    sections = []
    current_section = {'title': 'Introduction', 'content': []}

    for line in lines:
        line = line.strip()
        if not line:
            continue

        # Detect section headers (numbered, ALL CAPS, or short lines followed by content)
        is_header = False
        if re.match(r'^\d+[\.\)]\s+[A-Z]', line):
            is_header = True
        elif line.isupper() and len(line) < 80 and len(line) > 3:
            is_header = True
        elif re.match(r'^(Section|Chapter|Part|Step|Module)\s+\d', line, re.I):
            is_header = True

        if is_header:
            if current_section['content']:
                sections.append(current_section)
            current_section = {'title': line, 'content': []}
        else:
            current_section['content'].append(line)

    if current_section['content']:
        sections.append(current_section)

    return sections


def parse_sop(filepath: str) -> dict:
    """Main parser: PDF → structured JSON."""
    path = Path(filepath)
    if not path.exists():
        print(f"ERROR: File not found: {filepath}")
        sys.exit(1)

    if path.suffix.lower() != '.pdf':
        print(f"ERROR: Expected PDF file, got: {path.suffix}")
        sys.exit(1)

    print(f"Parsing: {path.name}")

    # Extract text
    raw_text = parse_pdf(str(path))
    word_count = len(raw_text.split())
    print(f"  Raw text: {word_count} words")

    # Chunk to max 2000 words
    text = chunk_text(raw_text, 2000)
    final_words = len(text.split())
    if final_words < word_count:
        print(f"  Truncated to: {final_words} words (max 2000)")

    # Extract sections
    sections = extract_sections(text)
    print(f"  Sections found: {len(sections)}")

    # Build output
    result = {
        'metadata': {
            'source_file': path.name,
            'parsed_at': datetime.now().isoformat(),
            'word_count': final_words,
            'section_count': len(sections),
            'agent': 'sentientia_agent1_sop_parser',
            'version': '1.0',
        },
        'title': path.stem.replace('-', ' ').replace('_', ' ').title(),
        'sections': [],
        'summary': text[:500] + '...' if len(text) > 500 else text,
    }

    for section in sections:
        result['sections'].append({
            'title': section['title'],
            'content': '\n'.join(section['content']),
            'word_count': len(' '.join(section['content']).split()),
        })

    return result


def main():
    if len(sys.argv) < 2:
        print("Usage: python sentientia/agent1_sop_parser.py <path-to-sop.pdf>")
        print("       python sentientia/agent1_sop_parser.py content/sops/AML-Training-SOP.pdf")
        sys.exit(1)

    filepath = sys.argv[1]
    result = parse_sop(filepath)

    # Output path
    output_dir = Path('content/parsed')
    output_dir.mkdir(parents=True, exist_ok=True)
    output_name = Path(filepath).stem + '-parsed.json'
    output_path = output_dir / output_name

    with open(output_path, 'w', encoding='utf-8') as f:
        json.dump(result, f, indent=2, ensure_ascii=False)

    print(f"\nOutput: {output_path}")
    print(f"  Title: {result['title']}")
    print(f"  Sections: {result['metadata']['section_count']}")
    print(f"  Words: {result['metadata']['word_count']}")
    print("Done.")


if __name__ == '__main__':
    main()
