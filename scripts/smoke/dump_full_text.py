"""
Dump FULL body text of a DOCX or PDF, segmented per BAB/section.
Strips headings & body separately. Output: paragraph-per-line plain text.
"""
import sys, io, os, re
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

src = sys.argv[1]
ext = os.path.splitext(src)[1].lower()
fname = os.path.basename(src)


def from_docx(path):
    from docx import Document
    from docx.oxml.ns import qn
    doc = Document(path)
    paragraphs = []
    body = doc.element.body
    for p in body.iter(qn('w:p')):
        t = ''.join((tt.text or '') for tt in p.iter(qn('w:t')))
        paragraphs.append(t.strip())
    return paragraphs


def from_pdf(path):
    import fitz
    doc = fitz.open(path)
    paragraphs = []
    for page in doc:
        text = page.get_text()
        for blk in text.split('\n'):
            paragraphs.append(blk.strip())
    return paragraphs


paras = from_docx(src) if ext == '.docx' else from_pdf(src)

print(f"### FILE: {fname}")
print(f"### TOTAL_PARAGRAPHS: {len(paras)}")
print()

# Detect section breaks (BAB markers) and emit content grouped
BAB_RE = re.compile(r'^\s*BAB\s+[IVX]+\b', re.IGNORECASE)
SUB_RE = re.compile(r'^\s*([IVX]+\.?\d+\.?\d*\.?|\d+\.\d+(?:\.\d+)?)\s*(.+)?')
FRONT_TOKS = {'KATA PENGANTAR', 'DAFTAR ISI', 'DAFTAR TABEL', 'DAFTAR GAMBAR', 'LAMPIRAN', 'DAFTAR PUSTAKA'}

current_section = '__PREAMBLE__'
section_buffer = []
sections = []


def flush():
    if section_buffer:
        sections.append((current_section, list(section_buffer)))


for p in paras:
    t = p.strip()
    if not t:
        continue
    up = t.upper()
    if up in FRONT_TOKS:
        flush()
        current_section = up
        section_buffer = []
        continue
    if BAB_RE.match(up):
        flush()
        # Strip trailing page number digits
        clean = re.sub(r'(\d+|[ivxIVX]+\-?\d+)$', '', t).strip()
        current_section = clean[:80]
        section_buffer = []
        continue
    # Subsection — keep as header marker inside the same BAB
    section_buffer.append(t)

flush()

# Emit
for name, buf in sections:
    print(f"=== {name} ===  ({len(buf)} paragraphs)")
    for line in buf:
        print(line)
    print()
