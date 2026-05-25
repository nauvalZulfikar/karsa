"""Dump paragraphs around DAFTAR ISI to see subsection layout per doc."""
import sys, io, os, re
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
from docx import Document
from docx.oxml.ns import qn

src = sys.argv[1]
print(f"### {os.path.basename(src)}")
doc = Document(src)
body = doc.element.body
paragraphs = []
for p in body.iter(qn('w:p')):
    t = ''.join((t.text or '') for t in p.iter(qn('w:t'))).strip()
    paragraphs.append(t)

# show 30 paragraphs after DAFTAR ISI
for i, t in enumerate(paragraphs):
    if t.upper() == 'DAFTAR ISI':
        for j in range(i+1, min(i+45, len(paragraphs))):
            txt = paragraphs[j]
            if not txt:
                continue
            print(f"  [{j:04d}] {txt[:200]}")
        break

# Now extract all numbered subsection patterns (1.1, 1.2.3) seen ANYWHERE for reference
print("\n### NUMBERED SECTIONS (all 1.x patterns found)")
seen = set()
for j, t in enumerate(paragraphs):
    m = re.match(r'^(\d+\.\d+(?:\.\d+)?)\s+(.+?)(?:\s+\d+\s*$|$)', t)
    if m:
        sec = m.group(1)
        title = m.group(2).strip()
        if (sec, title[:60]) not in seen:
            seen.add((sec, title[:60]))
            print(f"  {sec}  {title[:120]}")
