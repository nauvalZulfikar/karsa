"""
Dump structure of an existing Laporan Pendahuluan DOCX so we know what to template.
Output: paragraphs + table cell text + image count + headings hierarchy.
"""
import sys, io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
from docx import Document
from docx.oxml.ns import qn

src = sys.argv[1] if len(sys.argv) > 1 else \
    r'D:\Downloads\coding project\project_management\doc-geoteknik-stabilitas-tanah\KAJIAN GEOTEKNIK-20260516T113246Z-3-001\Laporan Pendahuluan Analisis Stabilitas Lereng SR.docx'

doc = Document(src)
print(f"=== {src.split(chr(92))[-1]} ===")
print(f"Sections: {len(doc.sections)} | Paragraphs: {len(doc.paragraphs)} | Tables: {len(doc.tables)}")

# Count images
img_count = 0
for rel in doc.part.rels.values():
    if "image" in rel.target_ref:
        img_count += 1
print(f"Images: {img_count}")
print()

# Headings + first 80 chars of each non-empty paragraph
print("=== STRUCTURE (heading-only + first lines of body) ===")
shown = 0
for i, p in enumerate(doc.paragraphs):
    style = p.style.name if p.style else ''
    txt = p.text.strip()
    if not txt:
        continue
    is_heading = 'Heading' in style or 'Title' in style or 'BAB' in txt[:4].upper() or txt.upper().startswith(('BAB ', 'I.', 'II.', 'III.'))
    if is_heading:
        print(f"[{i:03d}] <{style}> {txt[:140]}")
        shown += 1
    elif shown < 100 and len(txt) > 20:
        print(f"  ({i:03d}) {txt[:100]}")
        shown += 1
    if shown > 150:
        break

print()
print(f"=== TABLES ({len(doc.tables)}) ===")
for ti, t in enumerate(doc.tables[:10]):
    rows = len(t.rows)
    cols = len(t.rows[0].cells) if rows else 0
    print(f"\nTable {ti}: {rows} rows x {cols} cols")
    for ri, r in enumerate(t.rows[:5]):
        cells = ' | '.join(c.text.strip()[:30] for c in r.cells)
        print(f"  R{ri}: {cells}")
    if rows > 5:
        print(f"  ... +{rows-5} rows")
