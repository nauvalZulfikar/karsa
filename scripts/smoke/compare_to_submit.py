"""
Bandingin 2 laporan candidate ke folder submit docs.
Compute match score (%) berdasarkan vendor, project subject, lokasi, dates, dll
yang muncul di dokumen submit.

Usage:
    python compare_to_submit.py
"""
import sys, io, os, re
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

import fitz  # pymupdf
from docx import Document
from docx.oxml.ns import qn

SUBMIT_DIR = r'D:\Downloads\coding project\project_management\doc-kajian-pemetaan-topografi\submit'
CANDIDATES = {
    'A_real_topografi_template': r'D:\Downloads\coding project\project_management\doc-kajian-pemetaan-topografi\KAJIAN PEMETAAN TOPOGRAFI-20260520T064728Z-3-001\KAJIAN PEMETAAN TOPOGRAFI\Laporan Pendahuluan Kajian Topografi & Pematangan Lahan SR.docx',
    'B_old_generated_geoteknik': r'D:\Downloads\laporan_pendahuluan_geoteknik_template_20260520074650.docx',
    'C_new_generated_topografi': r'D:\Downloads\coding project\project_management\dputr-pm\storage\app\private\dokumen\generated\2\laporan_pendahuluan_topografi_template_20260520092541.docx',
}


def extract_pdf(path):
    doc = fitz.open(path)
    text = []
    for page in doc:
        text.append(page.get_text())
    return '\n'.join(text)


def extract_docx(path):
    doc = Document(path)
    body = doc.element.body
    paragraphs = []
    for p in body.iter(qn('w:p')):
        t = ''.join((tt.text or '') for tt in p.iter(qn('w:t')))
        if t.strip():
            paragraphs.append(t)
    return '\n'.join(paragraphs)


def find_key_facts(text):
    """Extract identifiable facts from text."""
    facts = {}
    # Vendor (PT./CV.)
    vendors = re.findall(r'(PT\.?\s+[A-Z][\w\s&\.]{3,60}?)(?:\s+(?:meyiapkan|menyiapkan|sebagai|dalam|adalah|berhak|dengan|kepada|menyatakan|telah|melakukan|untuk|wajib|menerima|harus|akan|memberikan|membayar|berdasarkan|terhadap|\,|$))', text)
    facts['vendors'] = list(set(v.strip().rstrip('.,;') for v in vendors))
    # Project subject keywords
    facts['has_topografi'] = bool(re.search(r'\btopografi\b|\bpemetaan\b|\bpematangan lahan\b|\bcut.{0,3}fill\b|\bterasering\b|\bgrading\b|\bbench\s*mark\b|\bDEM\b|\bGNSS\b|\bUTM\b', text, re.IGNORECASE))
    facts['has_geoteknik'] = bool(re.search(r'\bgeoteknik\b|\bstabilitas lereng\b|\blongsoran\b|\bPLAXIS\b|\bMohr.{0,3}Coulomb\b|\bDPT\b|\bbronjong\b|\bcerucuk\b|\bsondir\b|\bbor dangkal\b', text, re.IGNORECASE))
    facts['has_jalan']     = bool(re.search(r'\bDED\s+jalan\b|\bperkerasan\b|\bbina marga\b|\bLHR\b|\bMDPJ\b', text, re.IGNORECASE))
    # Lokasi
    facts['has_ciwidey']   = 'ciwidey' in text.lower()
    facts['has_lebakmuncang'] = 'lebakmuncang' in text.lower()
    facts['has_soreang']   = 'soreang' in text.lower()
    facts['has_kab_bandung'] = bool(re.search(r'kab(?:upaten|\.)?\s+bandung', text, re.IGNORECASE))
    # Pagu/kontrak numbers
    rps = re.findall(r'Rp\.?\s*[\d\.\,]+\s*(?:juta|miliar)?', text)
    facts['rp_values'] = rps[:5]
    # SPK/SPMK numbers (format like 602.1/.../...)
    spk = re.findall(r'602\.\d+/[\w\d\.\-/]+', text)
    facts['spk_numbers'] = list(set(spk))[:3]
    # PPK names (with gelar)
    ppk = re.findall(r'([A-Z][a-zA-Z\']+(?:\s+[A-Z][a-zA-Z\']+){1,4}(?:,\s*(?:S\.?T|M\.?T|M\.?M|M\.?Sc|M\.?PSDA|MPSDA|S\.?H|M\.?H|S\.?Pd|M\.?Pd|Ph\.?D|Drs|Dra|Ir|Dr)\.?){1,3}\.?)\b', text)
    facts['names_with_gelar'] = list(set(ppk))[:6]
    # Provide full lowercase text for downstream substring checks
    facts['__text_lower'] = text.lower()
    facts['text_for_spk'] = text
    return facts


def overlap_score(submit_facts, candidate_facts):
    """Compute match score (%) — measures how well candidate laporan describes
    the PROJECT IDENTITY from submit docs.

    EXPECTED facts (hardcoded for Purna Wahana topografi project):
      - Vendor: PT. PURNA WAHANA LESTARI
      - Project topic: TOPOGRAFI (not geoteknik or jalan)
      - Lokasi: Desa Lebakmuncang, Kec. Ciwidey, Kabupaten Bandung
    """
    score = 0.0
    detail = []
    text_lower = candidate_facts['__text_lower']

    # 1. Vendor name in candidate — weight 30
    has_purna = 'purna wahana' in text_lower
    has_itergo = 'itergo' in text_lower
    if has_purna and not has_itergo:
        score += 30
        detail.append("vendor: PURNA WAHANA ✓, no Itergo leak (+30)")
    elif has_purna and has_itergo:
        score += 15
        detail.append("vendor partial: PURNA WAHANA ✓ but Itergo also present (+15)")
    elif has_itergo:
        score += 0
        detail.append("vendor MISMATCH: only Itergo (should be Purna Wahana) (+0)")
    else:
        detail.append("vendor: no match (+0)")

    # 2. Project topic (jenis) — weight 25
    # Target = TOPOGRAFI focused (no false-positive geoteknik or jalan in title)
    # Topografi content is NORMAL to mention "stabilitas lereng" briefly in BAB V (Pematangan Lahan),
    # so geoteknik mention is OK — but jenis should be TOPOGRAFI primary
    if candidate_facts['has_topografi']:
        score += 25
        detail.append("jenis: TOPOGRAFI ✓ (+25)")
    else:
        detail.append("jenis MISMATCH: target=topografi, candidate doesn't mention topografi (+0)")

    # 3. Lokasi match — weight 30 (high signal — Karta should propagate lokasi from KAK)
    expected_lok = ['has_lebakmuncang', 'has_ciwidey', 'has_kab_bandung']
    lok_matches = sum(1 for f in expected_lok if candidate_facts.get(f))
    lok_score = (lok_matches / len(expected_lok)) * 30
    score += lok_score
    present = [f.replace('has_', '') for f in expected_lok if candidate_facts.get(f)]
    detail.append(f"lokasi: {lok_matches}/{len(expected_lok)} {present} (+{lok_score:.0f})")

    # 4. SPK number match — weight 15 (laporan body usually references SPK)
    # Expected: 602.1/09/SPK/Kajian.Topografi/BG-DPUTR/2026
    expected_spk_substr = '602.1/09/SPK/Kajian.Topografi'
    if expected_spk_substr.lower() in text_lower or '602.1/09' in candidate_facts.get('text_for_spk', text_lower):
        score += 15
        detail.append(f"SPK match: {expected_spk_substr} ✓ (+15)")
    else:
        detail.append(f"SPK: '{expected_spk_substr}' not found (+0)")

    return score, detail


def main():
    # Load submit docs (combined)
    submit_text = ''
    print("=== SUBMIT FOLDER DOCS ===")
    for f in os.listdir(SUBMIT_DIR):
        fp = os.path.join(SUBMIT_DIR, f)
        if not f.lower().endswith('.pdf'):
            continue
        t = extract_pdf(fp)
        submit_text += '\n\n' + t
        print(f"  - {f} ({len(t)} chars)")
    submit_facts = find_key_facts(submit_text)
    print()
    print("Submit Key Facts:")
    for k, v in submit_facts.items():
        print(f"  {k}: {v}")
    print()

    # Compare candidates
    results = {}
    for label, path in CANDIDATES.items():
        if not os.path.exists(path):
            print(f"[{label}] FILE NOT FOUND: {path}")
            continue
        cand_text = extract_docx(path)
        cand_facts = find_key_facts(cand_text)
        score, detail = overlap_score(submit_facts, cand_facts)
        results[label] = {
            'path': path,
            'facts': cand_facts,
            'score': score,
            'detail': detail,
            'chars': len(cand_text),
        }

    print('=' * 70)
    print("CANDIDATE COMPARISON")
    print('=' * 70)
    for label, r in results.items():
        print(f"\n## {label}")
        print(f"  File: {os.path.basename(r['path'])}")
        print(f"  Chars: {r['chars']}")
        print(f"  Vendors found: {r['facts']['vendors'][:3]}")
        print(f"  Topic: topografi={r['facts']['has_topografi']} geoteknik={r['facts']['has_geoteknik']} jalan={r['facts']['has_jalan']}")
        print(f"  Lokasi: ciwidey={r['facts']['has_ciwidey']} lebakmuncang={r['facts']['has_lebakmuncang']} kab_bandung={r['facts']['has_kab_bandung']}")
        print(f"  → Match Score: {r['score']:.1f}%")
        for d in r['detail']:
            print(f"      • {d}")

    print('\n' + '=' * 70)
    print("VERDICT")
    print('=' * 70)
    sorted_r = sorted(results.items(), key=lambda x: -x[1]['score'])
    for i, (label, r) in enumerate(sorted_r):
        marker = "🥇" if i == 0 else "🥈"
        print(f"  {marker} {label}: {r['score']:.1f}%")


if __name__ == '__main__':
    main()
