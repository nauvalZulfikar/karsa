"""
Compose laporan menggunakan template DOCX sebagai skeleton (preserve images +
layout), REPLACE body paragraphs di project-specific sections dengan LLM-composed
content dari LaporanComposerService.

Sections yang DI-REPLACE (project-specific, content per proyek beda):
  - KATA PENGANTAR  (4 body para + 4 signature line)
  - LATAR BELAKANG  (3 body para)
  - MAKSUD DAN TUJUAN  (2 body para)
  - LOKASI PEKERJAAN  (1 body para + image captions KEEP)

Sections yang TETAP VERBATIM dari template (teori dasar generic — sama untuk
semua proyek geoteknik):
  - METODOLOGI PEKERJAAN (BAB II)
  - DASAR PERENCANAAN (BAB III: Metode Analisis, Teori Dasar, Longsoran, dll)

Global string substitutions juga dilakukan (vendor name, lokasi, etc.) di cover
page dan section text yang tidak di-replace.

Usage:
    python scripts/laporan_compose_template.py \
        --template storage/laporan_templates/geoteknik_pendahuluan.docx \
        --output  dokumen/generated/19/laporan_X.docx \
        --composed-file tmp/composed_sections.json \
        --string-subs-file tmp/string_subs.json

Output: "OK paragraphs_modified=N sections_replaced=N images_preserved=N output=..."
"""
import sys, io, json, argparse, os, re
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')

try:
    from docx import Document
    from docx.oxml.ns import qn
    from copy import deepcopy
except ImportError:
    print("ERR: python-docx not installed (pip install python-docx)")
    sys.exit(1)


# Map composer section name → (template heading marker, next-section markers)
# Heading match: uppercase substring match dgn length < 100 chars
COMPOSED_SECTION_SPEC = {
    'kata_pengantar': {
        'markers': ['KATA PENGANTAR'],
        'next_markers': ['DAFTAR ISI'],
        'preserve_caption': False,  # signature block also replaced
    },
    'latar_belakang': {
        'markers': ['LATAR BELAKANG'],
        'next_markers': ['MAKSUD DAN TUJUAN', 'I.2', 'RUANG LINGKUP'],
        'preserve_caption': False,
    },
    'maksud_tujuan': {
        # 'MAKSUD DAN TUJUAN' covers both 'MAKSUD DAN TUJUAN' (geoteknik)
        # and 'MAKSUD DAN TUJUAN PENELITIAN' (topografi) via substring match
        'markers': ['MAKSUD DAN TUJUAN'],
        'next_markers': ['LOKASI PEKERJAAN', 'LOKASI DAN LUAS', 'RUANG LINGKUP', 'I.3'],
        'preserve_caption': False,
    },
    'ruang_lingkup': {
        'markers': ['RUANG LINGKUP'],
        'next_markers': ['LOKASI', 'DASAR HUKUM', 'I.4', 'I.5'],
        'preserve_caption': False,
    },
    'lokasi_pekerjaan': {
        # Cover 'LOKASI PEKERJAAN' (geoteknik) + 'LOKASI DAN LUAS WILAYAH' (topografi)
        'markers': ['LOKASI PEKERJAAN', 'LOKASI DAN LUAS'],
        'next_markers': ['METODOLOGI PEKERJAAN', 'METODOLOGI PEMETAAN', 'DASAR HUKUM', 'BAB II', 'I.5'],
        'preserve_caption': True,  # keep "Gambar 1.1 ..." captions
    },
    'dasar_hukum': {
        'markers': ['DASAR HUKUM'],
        'next_markers': ['BAB II', 'GAMBARAN UMUM', 'METODOLOGI'],
        'preserve_caption': False,
    },
    'gambaran_umum_lokasi': {
        # Topografi/jalan BAB II content (composer-generated per project)
        'markers': ['GAMBARAN UMUM LOKAS', 'BAB II GAMBARAN'],
        'next_markers': ['BAB III', 'METODOLOGI'],
        'preserve_caption': True,
    },
}


def is_heading_line(text, marker, max_len=80):
    """Para adalah heading kalau text mengandung marker (case-insensitive) DAN
    panjangnya pendek (< 80 chars setelah strip). Trailing digits OK (page no).
    Reject caption lines (start with 'Gambar X', 'Tabel X', dll)."""
    t = text.strip()
    if len(t) > max_len:
        return False
    # Reject caption (image/table reference)
    if re.match(r'^(Gambar|Tabel|Picture|Figure)\s+[\dIVX]+', t, re.IGNORECASE):
        return False
    # strip trailing digits/whitespace (page numbers)
    t_clean = re.sub(r'[\s\d]+$', '', t).strip()
    return marker.upper() in t.upper() and len(t_clean) <= max_len


def is_caption(text):
    """Caption paragraph berupa 'Gambar X.Y' (geoteknik) atau 'Gambar I1' (topografi roman+digit)
    atau 'Tabel X.Y' / 'Figure X' dll."""
    t = text.strip()
    return bool(re.match(r'^(Gambar|Tabel|Picture|Figure)\s+[\dIVX]+', t, re.IGNORECASE))


def find_section_body_range(paragraphs, markers, next_markers):
    """Return (start_idx, end_idx) — exclusive end. start_idx is index AFTER heading.
    Returns (None, None) if section not found. `markers` is list — any match counts.
    Skips matches in TOC region (heuristic: first occurrence is usually TOC if doc starts
    with KATA PENGANTAR/DAFTAR ISI listing). We prefer the SECOND occurrence which is
    typically the actual section heading in body."""
    matches = []
    for i, p in enumerate(paragraphs):
        for mk in markers:
            if is_heading_line(p.text, mk):
                matches.append(i)
                break

    if not matches:
        return None, None

    # If we have 2+ matches, prefer the LAST one (body heading after TOC)
    # except for kata_pengantar which is usually the first non-TOC standalone.
    # Heuristic: pick the match that has substantial body text following it.
    heading_idx = None
    for mi in reversed(matches):
        # Check if there are body paragraphs (> 30 chars) within next 8 paragraphs
        body_chars = sum(len(paragraphs[k].text.strip()) for k in range(mi + 1, min(mi + 8, len(paragraphs))))
        if body_chars > 100:
            heading_idx = mi
            break
    if heading_idx is None:
        heading_idx = matches[-1]  # fallback to last match

    end_idx = len(paragraphs)
    for j in range(heading_idx + 1, len(paragraphs)):
        for nm in next_markers:
            if is_heading_line(paragraphs[j].text, nm):
                end_idx = j
                break
        if end_idx != len(paragraphs):
            break
    return heading_idx + 1, end_idx


def set_paragraph_text(para, new_text):
    """Replace all run text dengan new_text. Preserve formatting dari first run.
    Kalau ga ada runs, add one."""
    if not para.runs:
        para.add_run(new_text)
        return
    para.runs[0].text = new_text
    for r in para.runs[1:]:
        r.text = ''


def clone_paragraph_below(para, new_text):
    """Insert new paragraph BELOW `para` using same style + text.
    Return new paragraph."""
    new_xml = deepcopy(para._element)
    # clear all runs in clone first
    for r in new_xml.findall(qn('w:r')):
        new_xml.remove(r)
    # insert after original
    para._element.addnext(new_xml)
    # now wrap new_xml as Paragraph via re-find
    parent = para._element.getparent()
    new_para_obj = None
    for p in parent.findall(qn('w:p')):
        if p is new_xml:
            from docx.text.paragraph import Paragraph
            new_para_obj = Paragraph(p, para._parent)
            break
    if new_para_obj is None:
        # fallback: walk
        from docx.text.paragraph import Paragraph
        new_para_obj = Paragraph(new_xml, para._parent)
    new_para_obj.add_run(new_text)
    return new_para_obj


def remove_paragraph(para):
    """Remove paragraph from its parent."""
    elem = para._element
    elem.getparent().remove(elem)


def replace_section_body(doc, section_name, composed_text, log):
    """Find section body, replace with composed_text paragraphs.
    Return dict with stats."""
    spec = COMPOSED_SECTION_SPEC[section_name]
    paragraphs = list(doc.paragraphs)
    start_idx, end_idx = find_section_body_range(paragraphs, spec['markers'], spec['next_markers'])
    if start_idx is None:
        log.append(f"  ! section '{section_name}' (markers={spec['markers']}) NOT FOUND in template")
        return {'replaced': 0, 'cleared': 0, 'added': 0, 'preserved_caption': 0}

    # Identify body paragraphs to potentially replace (skip captions if preserve_caption)
    body_targets = []  # indices in paragraphs list
    preserved = []
    for k in range(start_idx, end_idx):
        text = paragraphs[k].text.strip()
        if not text:
            continue
        if spec['preserve_caption'] and is_caption(text):
            preserved.append(k)
            continue
        body_targets.append(k)

    # Split composer text into paragraphs (preserve blank-line separated paras)
    composed_paras = []
    current = []
    for line in composed_text.split('\n'):
        if line.strip() == '':
            if current:
                composed_paras.append(' '.join(current).strip())
                current = []
        else:
            # bulleted line = standalone paragraph
            if re.match(r'^[-•*]\s+|\d+\.\s+', line.strip()):
                if current:
                    composed_paras.append(' '.join(current).strip())
                    current = []
                composed_paras.append(line.strip())
            else:
                current.append(line.strip())
    if current:
        composed_paras.append(' '.join(current).strip())
    composed_paras = [p for p in composed_paras if p]

    if not composed_paras:
        log.append(f"  ! section '{section_name}' composed text empty, skipping")
        return {'replaced': 0, 'cleared': 0, 'added': 0, 'preserved_caption': len(preserved)}

    log.append(f"  → '{section_name}': {len(body_targets)} template body slots, "
               f"{len(composed_paras)} composer paras, {len(preserved)} captions preserved")

    # 1:1 replacement strategy:
    # - composed[i] → body_targets[i] for i < min(len(composed), len(body_targets))
    # - leftover composed → insert new paragraphs after last body_target
    # - leftover body_targets → clear text

    replaced = 0
    cleared = 0
    added = 0
    min_count = min(len(composed_paras), len(body_targets))

    for i in range(min_count):
        set_paragraph_text(paragraphs[body_targets[i]], composed_paras[i])
        replaced += 1

    # Clear extra template body paragraphs (composer has fewer)
    if len(body_targets) > len(composed_paras):
        for j in range(len(composed_paras), len(body_targets)):
            set_paragraph_text(paragraphs[body_targets[j]], '')
            cleared += 1

    # Add extra paragraphs (composer has more)
    if len(composed_paras) > len(body_targets) and body_targets:
        anchor = paragraphs[body_targets[-1]]
        prev = anchor
        for k in range(len(body_targets), len(composed_paras)):
            new_para = clone_paragraph_below(prev, composed_paras[k])
            prev = new_para
            added += 1

    return {'replaced': replaced, 'cleared': cleared, 'added': added,
            'preserved_caption': len(preserved)}


def apply_global_string_subs(doc, subs, log):
    """Apply string substitutions across ALL paragraphs (body, tables, headers, footers).
    Preserves run formatting where possible (joins runs in paragraph, replaces, puts in first run)."""
    if not subs:
        return 0
    # Sort by length desc — longer literal matches first to avoid partial overrides
    sorted_subs = sorted(subs.items(), key=lambda kv: -len(kv[0]))

    def replace_in_para(para):
        if not para.runs:
            return 0
        full = ''.join(r.text or '' for r in para.runs)
        new = full
        for needle, replacement in sorted_subs:
            if needle in new:
                new = new.replace(needle, replacement)
        if new == full:
            return 0
        para.runs[0].text = new
        for r in para.runs[1:]:
            r.text = ''
        return 1

    count = 0
    for p in doc.paragraphs:
        count += replace_in_para(p)
    for t in doc.tables:
        for row in t.rows:
            for cell in row.cells:
                for p in cell.paragraphs:
                    count += replace_in_para(p)
    for section in doc.sections:
        for area in (section.header, section.footer,
                     section.first_page_header, section.first_page_footer,
                     section.even_page_header, section.even_page_footer):
            if area is None:
                continue
            for p in area.paragraphs:
                count += replace_in_para(p)
    log.append(f"  → global string subs applied to {count} paragraphs")
    return count


def count_images(doc):
    return sum(1 for rel in doc.part.rels.values() if 'image' in rel.target_ref)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--template', required=True)
    ap.add_argument('--output', required=True)
    ap.add_argument('--composed-file', required=True, help='JSON: {section_name: "composed text"}')
    ap.add_argument('--string-subs-file', help='JSON: {literal: replacement} (global subs)')
    args = ap.parse_args()

    if not os.path.exists(args.template):
        print(f"ERR: template not found: {args.template}")
        sys.exit(1)
    if not os.path.exists(args.composed_file):
        print(f"ERR: composed-file not found: {args.composed_file}")
        sys.exit(1)

    with open(args.composed_file, encoding='utf-8') as f:
        composed = json.load(f)

    string_subs = {}
    if args.string_subs_file and os.path.exists(args.string_subs_file):
        with open(args.string_subs_file, encoding='utf-8') as f:
            string_subs = json.load(f)

    out_dir = os.path.dirname(args.output)
    if out_dir and not os.path.exists(out_dir):
        os.makedirs(out_dir, exist_ok=True)

    try:
        doc = Document(args.template)
    except Exception as e:
        print(f"ERR: load template fail: {e}")
        sys.exit(1)

    img_before = count_images(doc)
    log = []

    # Step 1: apply global string subs FIRST (vendor name, lokasi, etc.)
    if string_subs:
        apply_global_string_subs(doc, string_subs, log)

    # Step 2: replace project-specific section bodies with composer content
    stats = {'replaced': 0, 'cleared': 0, 'added': 0, 'sections': 0}
    for section_name in COMPOSED_SECTION_SPEC.keys():
        if section_name not in composed or not composed[section_name].strip():
            log.append(f"  - skip '{section_name}': no composer content")
            continue
        section_stats = replace_section_body(doc, section_name, composed[section_name], log)
        stats['replaced'] += section_stats['replaced']
        stats['cleared'] += section_stats['cleared']
        stats['added'] += section_stats['added']
        stats['sections'] += 1

    # Save
    try:
        doc.save(args.output)
    except Exception as e:
        print(f"ERR: save fail: {e}")
        sys.exit(1)

    img_after = count_images(Document(args.output))
    size_bytes = os.path.getsize(args.output)

    # Output log lines (info only) + final OK line
    for line in log:
        print(line)
    print(f"OK paragraphs_replaced={stats['replaced']} cleared={stats['cleared']} added={stats['added']} "
          f"sections={stats['sections']} images_preserved={img_after}/{img_before} "
          f"bytes={size_bytes} output={args.output}")


if __name__ == '__main__':
    main()
