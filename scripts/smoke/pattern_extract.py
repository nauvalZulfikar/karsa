"""
Pattern extractor for laporan files (DOCX + PDF).
Output ke stdout terstruktur supaya bisa di-paste ke pattern matrix.

Usage:
    python pattern_extract.py <file>
"""
import sys, io, os, re, json
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

src = sys.argv[1]
ext = os.path.splitext(src)[1].lower()
fname = os.path.basename(src)
print(f"### FILE: {fname}")
print(f"### SIZE_MB: {os.path.getsize(src) / 1024 / 1024:.2f}")
print(f"### EXT: {ext}")


def heading_class(text):
    """Classify heading level by regex pattern of leading numbering."""
    t = text.strip()
    up = t.upper()
    if re.match(r'^BAB\s+[IVX]+', up): return 'BAB'
    if re.match(r'^[1-9]\.\d+\.\d+', t): return 'L3'  # 1.1.1
    if re.match(r'^[1-9]\.\d+\s', t): return 'L2'   # 1.1
    if re.match(r'^[1-9]\.\s', t): return 'L1'      # 1.
    if up in ('KATA PENGANTAR', 'DAFTAR ISI', 'DAFTAR TABEL', 'DAFTAR GAMBAR', 'LAMPIRAN'):
        return 'FRONT'
    return None


def analyze_paragraphs(paragraphs):
    """paragraphs = list of strings."""
    headings = []
    body_lines = []
    for txt in paragraphs:
        t = (txt or '').strip()
        if not t:
            continue
        cls = heading_class(t)
        if cls:
            headings.append((cls, t[:140]))
        elif len(t) > 30:
            body_lines.append(t)
    return headings, body_lines


def detect_project_strings(body_lines):
    """Find candidate project-specific strings: ALL CAPS phrases, PT./CV. companies, place names."""
    candidates = {}
    # Vendor (PT./CV.)
    for line in body_lines:
        for m in re.finditer(r'\b((?:PT\.?|CV\.?)\s+[A-Z][\w\.\s&]{3,60})', line):
            v = m.group(1).strip().rstrip('.,;')
            candidates.setdefault('vendor', set()).add(v)
        # Lokasi: "Desa X, Kecamatan Y, Kabupaten Z"
        for m in re.finditer(r'(Desa\s+[A-Z][\w]+(?:,\s*Kecamatan\s+[A-Z][\w]+)?(?:,\s*Kabupaten\s+[A-Z][\w]+)?)', line):
            candidates.setdefault('lokasi', set()).add(m.group(1).strip())
        # Pemberi kerja (Kementerian/Dinas/Pemerintah)
        for m in re.finditer(r'\b((?:Kementerian|Dinas|Pemerintah\s+Daerah|Pemerintah\s+Kabupaten)\s+[A-Z][\w\s]{3,60})', line):
            candidates.setdefault('pemberi_kerja', set()).add(m.group(1).strip().rstrip('.,;'))
        # Project subject phrases
        for m in re.finditer(r'\b(Sekolah Rakyat(?:\s+\([A-Z]+\))?\s+\w+|Kajian\s+\w[\w\s]{3,40}|Analisis\s+\w[\w\s]{3,40})', line):
            candidates.setdefault('project_phrase', set()).add(m.group(1).strip())
    return {k: sorted(v) for k, v in candidates.items()}


def from_docx(path):
    from docx import Document
    doc = Document(path)
    # Count images
    imgs = sum(1 for rel in doc.part.rels.values() if 'image' in rel.target_ref)
    # Gather paragraphs (body, tables, headers, footers)
    paragraphs = [p.text for p in doc.paragraphs]
    for t in doc.tables:
        for row in t.rows:
            for c in row.cells:
                for p in c.paragraphs:
                    paragraphs.append(p.text)
    for section in doc.sections:
        for area in (section.header, section.footer):
            if area:
                for p in area.paragraphs:
                    paragraphs.append(p.text)
    return paragraphs, imgs, len(doc.tables), len(doc.sections)


def from_pdf(path):
    try:
        import fitz  # pymupdf
    except ImportError:
        print("ERR: pymupdf not installed (pip install pymupdf)")
        sys.exit(1)
    doc = fitz.open(path)
    paragraphs = []
    img_count = 0
    for page in doc:
        text = page.get_text()
        for line in text.split('\n'):
            paragraphs.append(line)
        img_count += len(page.get_images())
    return paragraphs, img_count, 0, len(doc)


if ext == '.docx':
    paragraphs, imgs, tables, sections = from_docx(src)
    print(f"### PARAGRAPHS: {len(paragraphs)} | IMAGES: {imgs} | TABLES: {tables} | SECTIONS: {sections}")
elif ext == '.pdf':
    paragraphs, imgs, tables, pages = from_pdf(src)
    print(f"### PAGES: {pages} | IMAGES: {imgs} | PARAGRAPH_LINES: {len(paragraphs)}")
else:
    print(f"ERR: unsupported ext {ext}")
    sys.exit(1)

headings, body_lines = analyze_paragraphs(paragraphs)
print()
print(f"### HEADINGS ({len(headings)})")
for cls, t in headings:
    indent = {'BAB': '', 'FRONT': '', 'L1': '  ', 'L2': '    ', 'L3': '      '}.get(cls, '')
    print(f"  [{cls}] {indent}{t}")

print()
print(f"### CANDIDATE PROJECT STRINGS")
cand = detect_project_strings(body_lines)
for k, vs in cand.items():
    print(f"  {k}:")
    for v in vs[:8]:
        print(f"    - {v}")

# First 3 body paragraphs as flavor sample
print()
print("### FIRST 3 BODY PARAGRAPHS (preview)")
for line in body_lines[:3]:
    print(f"  > {line[:200]}")
