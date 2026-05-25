"""Dump every non-empty paragraph (numbered) for full visual inspection."""
import sys, io, os, re
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
from docx import Document
from docx.oxml.ns import qn

src = sys.argv[1]
mode = sys.argv[2] if len(sys.argv) > 2 else 'all'  # 'bab' to filter only BAB-like, 'sect' to filter section nums
print(f"### {os.path.basename(src)}")

doc = Document(src)
# include text inside text-boxes (mc:AlternateContent + w:txbxContent)
# python-docx doesn't expose them directly; we'll xpath the body
def iter_all_text(doc):
    body = doc.element.body
    # All <w:t> elements anywhere in the body (catches text in text-boxes too)
    for p in body.iter(qn('w:p')):
        # Aggregate text in this paragraph
        texts = []
        for t in p.iter(qn('w:t')):
            texts.append(t.text or '')
        yield ''.join(texts)

i = 0
for txt in iter_all_text(doc):
    t = (txt or '').strip()
    if not t:
        continue
    i += 1
    if mode == 'bab':
        if re.search(r'\bBAB\s+[IVX]+\b', t.upper()) or re.match(r'^\s*\d+\.\d+', t) or t.upper() in ('KATA PENGANTAR','DAFTAR ISI','DAFTAR TABEL','DAFTAR GAMBAR','LAMPIRAN','DAFTAR PUSTAKA'):
            print(f"  [{i:04d}] {t[:200]}")
    else:
        print(f"  [{i:04d}] {t[:200]}")

print(f"### TOTAL NONEMPTY PARAGRAPHS: {i}")
