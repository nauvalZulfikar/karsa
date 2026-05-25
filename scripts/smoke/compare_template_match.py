"""
Compare generated laporan vs original Itergo template.
Computes match score 0-100 based on structural + content fidelity dimensions.

Usage:
    python scripts/smoke/compare_template_match.py \
      --template storage/laporan_templates/geoteknik_pendahuluan.docx \
      --output  storage/app/private/dokumen/generated/19/laporan_X.docx \
      [--pekerjaan-nama "Kajian Geoteknik Stabilis Tanah"] \
      [--vendor "PT. ITERGO BUANA UTAMA"]

Match Score Dimensions:
  D1 — Image preservation (weight 25): images_generated / images_template
  D2 — BAB structure (weight 20): expected BAB headings present in generated
  D3 — Paragraph count similarity (weight 15): 1 - |diff|/template_count, clipped
  D4 — Section coverage (weight 20): KATA PENGANTAR, LATAR BELAKANG, MAKSUD-TUJUAN,
                                     LOKASI PEKERJAAN, METODOLOGI, DASAR PERENCANAAN
                                     all present
  D5 — No leak (weight 10): zero contamination from referenced proyek lain
                            (Sekolah Rakyat, Itergo if not project vendor, etc)
  D6 — Cover content (weight 5): vendor name + project name present in cover
  D7 — Signature block (weight 5): "Tim Penyusun" + "Direktur" present in kata pengantar

Output: JSON to stdout + readable summary
"""
import sys, io, json, argparse, os, re
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

from docx import Document
from docx.oxml.ns import qn

DEFAULT_FORBIDDEN_LEAKS = [
    'Sekolah Rakyat Ciwidey', 'SR Ciwidey', 'Sekolah Rakyat (SR) Ciwidey',
    'AHMAD SAMSUDIN', 'Ahmad Samsudin',
    'Lebakmuncang', 'Kemensos',
]

EXPECTED_BAB_HEADINGS = [
    'BAB I PENDAHULUAN', 'BAB II METODOLOGI', 'BAB III DASAR PERENCANAAN',
]

EXPECTED_SECTIONS = [
    'KATA PENGANTAR', 'DAFTAR ISI',
    'LATAR BELAKANG', 'MAKSUD DAN TUJUAN', 'LOKASI PEKERJAAN',
    'METODOLOGI PEKERJAAN', 'DASAR PERENCANAAN',
    'METODE ANALISIS', 'TEORI DASAR', 'LONGSORAN',
]


def load_text_and_count(path):
    """Load DOCX dan capture SEMUA paragraph (body + table + text-frame) via xpath."""
    doc = Document(path)
    body = doc.element.body
    paragraphs = []
    for p in body.iter(qn('w:p')):
        t = ''.join((tt.text or '') for tt in p.iter(qn('w:t')))
        paragraphs.append(t)
    text_all = '\n'.join(paragraphs)
    images = sum(1 for rel in doc.part.rels.values() if 'image' in rel.target_ref)
    return {
        'paragraphs': paragraphs,
        'text': text_all,
        'image_count': images,
        'para_count': len([p for p in paragraphs if p.strip()]),
        'total_chars': len(text_all),
    }


def has_section(text, marker):
    # case-insensitive substring match
    return marker.upper() in text.upper()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--template', required=True)
    ap.add_argument('--output',   required=True)
    ap.add_argument('--pekerjaan-nama', default='', help='nama_pekerjaan to verify present in cover')
    ap.add_argument('--vendor',         default='', help='vendor name to verify present (and to whitelist from leak check)')
    args = ap.parse_args()

    if not os.path.exists(args.template):
        print(f"ERR: template not found: {args.template}")
        sys.exit(1)
    if not os.path.exists(args.output):
        print(f"ERR: output not found: {args.output}")
        sys.exit(1)

    T = load_text_and_count(args.template)
    G = load_text_and_count(args.output)

    score = {}

    # D1: Image preservation (weight 25)
    img_ratio = G['image_count'] / T['image_count'] if T['image_count'] > 0 else 1.0
    score['D1_image_preservation'] = {
        'weight': 25,
        'value': round(min(1.0, img_ratio), 3),
        'detail': f"generated_images={G['image_count']}, template_images={T['image_count']}",
    }

    # D2: BAB structure (weight 20)
    bab_present = [h for h in EXPECTED_BAB_HEADINGS if has_section(G['text'], h.split()[1] + ' ')]  # match by roman+
    # Better: just check "BAB I", "BAB II", "BAB III" substrings
    bab_hits = sum(1 for h in ['BAB I', 'BAB II', 'BAB III'] if h in G['text'].upper())
    score['D2_bab_structure'] = {
        'weight': 20,
        'value': round(bab_hits / 3, 3),
        'detail': f"BAB headings found: {bab_hits}/3",
    }

    # D3: Paragraph count similarity (weight 15)
    diff = abs(G['para_count'] - T['para_count'])
    similarity = max(0, 1 - diff / max(T['para_count'], 1))
    score['D3_paragraph_count'] = {
        'weight': 15,
        'value': round(similarity, 3),
        'detail': f"template_paras={T['para_count']}, generated_paras={G['para_count']} (diff={diff})",
    }

    # D4: Section coverage (weight 20)
    sections_found = []
    sections_missing = []
    for sec in EXPECTED_SECTIONS:
        if has_section(G['text'], sec):
            sections_found.append(sec)
        else:
            sections_missing.append(sec)
    score['D4_section_coverage'] = {
        'weight': 20,
        'value': round(len(sections_found) / len(EXPECTED_SECTIONS), 3),
        'detail': f"found={len(sections_found)}/{len(EXPECTED_SECTIONS)}, missing={sections_missing}",
    }

    # D5: No leak (weight 10) — penalize each forbidden token found
    # but allow vendor if it overlaps with a forbidden token
    forbid = [f for f in DEFAULT_FORBIDDEN_LEAKS if f.lower() not in args.vendor.lower()]
    leaks_found = {}
    text_lower = G['text'].lower()
    for f in forbid:
        n = text_lower.count(f.lower())
        if n > 0:
            leaks_found[f] = n
    # Score: 1.0 if no leaks, scale down by total leak count
    total_leaks = sum(leaks_found.values())
    leak_score = 1.0 if total_leaks == 0 else max(0, 1 - total_leaks / 10)
    score['D5_no_leak'] = {
        'weight': 10,
        'value': round(leak_score, 3),
        'detail': f"leaks_found={leaks_found if leaks_found else 'NONE'}",
    }

    # D6: Cover content (weight 5)
    cover_text = '\n'.join(G['paragraphs'][:30])  # first 30 paras = cover + early section
    cover_ok = 0
    cover_total = 0
    if args.vendor:
        cover_total += 1
        if args.vendor.upper() in cover_text.upper():
            cover_ok += 1
    if args.pekerjaan_nama:
        cover_total += 1
        if args.pekerjaan_nama.upper() in cover_text.upper() or any(
            kw.upper() in cover_text.upper() for kw in args.pekerjaan_nama.split() if len(kw) > 4
        ):
            cover_ok += 1
    cover_val = (cover_ok / cover_total) if cover_total > 0 else 1.0
    score['D6_cover_content'] = {
        'weight': 5,
        'value': round(cover_val, 3),
        'detail': f"cover_ok={cover_ok}/{cover_total} (vendor='{args.vendor}', nama='{args.pekerjaan_nama}')",
    }

    # D7: Signature block (weight 5)
    sig_markers = ['Tim Penyusun', 'Direktur']
    sig_hits = sum(1 for m in sig_markers if m in G['text'])
    score['D7_signature_block'] = {
        'weight': 5,
        'value': round(sig_hits / len(sig_markers), 3),
        'detail': f"signature markers found: {sig_hits}/{len(sig_markers)}",
    }

    # Total weighted score
    total_w = sum(d['weight'] for d in score.values())
    weighted_sum = sum(d['weight'] * d['value'] for d in score.values())
    total_score = round((weighted_sum / total_w) * 100, 1)

    summary = {
        'match_score': total_score,
        'verdict': 'EXCELLENT' if total_score >= 90 else 'GOOD' if total_score >= 80 else 'OK' if total_score >= 70 else 'POOR',
        'template_file': os.path.basename(args.template),
        'generated_file': os.path.basename(args.output),
        'template_size_kb': round(os.path.getsize(args.template) / 1024, 1),
        'generated_size_kb': round(os.path.getsize(args.output) / 1024, 1),
        'dimensions': score,
    }

    # Pretty print
    print(f"\n=== Match Score: {total_score}/100 — {summary['verdict']} ===")
    print(f"Template: {summary['template_file']} ({summary['template_size_kb']} KB)")
    print(f"Generated: {summary['generated_file']} ({summary['generated_size_kb']} KB)")
    print()
    for k, d in score.items():
        pct = round(d['value'] * 100, 1)
        weighted = round(d['weight'] * d['value'], 1)
        bar = '█' * int(pct / 5) + '░' * (20 - int(pct / 5))
        print(f"  {k}  [{bar}] {pct:5.1f}%  (weight={d['weight']}, contribution={weighted})")
        print(f"    {d['detail']}")
    print()
    print(json.dumps(summary, indent=2))


if __name__ == '__main__':
    main()
