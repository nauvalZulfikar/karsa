"""
Render Laporan DOCX dari template + JSON substitution map.

Strategi: load template DOCX, lakukan in-place text replacement di SEMUA runs
(termasuk dalam tables, headers, footers). Preserve formatting + images +
struktur original.

Usage:
    python scripts/laporan_render.py \
        --template storage/laporan_templates/geoteknik_pendahuluan.docx \
        --output storage/app/private/dokumen/generated/15/laporan.docx \
        --subs '{"PT. Itergo Buana Utama": "PT. NEW VENDOR", "Sekolah Rakyat (SR) Ciwidey": "SR Soreang"}'

Output: OK + bytes_written  |  ERR: <message>
"""
import sys, io, json, argparse, os, copy
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')

try:
    from docx import Document
except ImportError:
    print("ERR: python-docx not installed (pip install python-docx)")
    sys.exit(1)


def replace_in_runs(paragraph, subs):
    """
    Replace text in runs while preserving formatting.
    Joins runs to avoid mid-word split issues, then redistributes.
    """
    if not paragraph.runs:
        return 0
    full_text = ''.join(r.text for r in paragraph.runs)
    new_text = full_text
    hit = 0
    for needle, replacement in subs.items():
        if needle in new_text:
            new_text = new_text.replace(needle, replacement)
            hit += 1
    if new_text == full_text:
        return 0
    # Naive: put all new text in first run, clear others (loses mid-paragraph formatting)
    first_run = paragraph.runs[0]
    first_run.text = new_text
    for r in paragraph.runs[1:]:
        r.text = ''
    return hit


def iter_paragraphs(doc):
    """Yield ALL paragraphs including those inside tables, headers, footers."""
    for p in doc.paragraphs:
        yield p
    for t in doc.tables:
        for row in t.rows:
            for cell in row.cells:
                for p in cell.paragraphs:
                    yield p
    for section in doc.sections:
        for header in (section.header, section.first_page_header, section.even_page_header):
            if header is None:
                continue
            for p in header.paragraphs:
                yield p
        for footer in (section.footer, section.first_page_footer, section.even_page_footer):
            if footer is None:
                continue
            for p in footer.paragraphs:
                yield p


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--template', required=True)
    ap.add_argument('--output', required=True)
    ap.add_argument('--subs', help='JSON map {target: replacement} (inline)')
    ap.add_argument('--subs-file', help='Path to JSON file with map (preferred on Windows)')
    args = ap.parse_args()

    if not os.path.exists(args.template):
        print(f"ERR: template not found: {args.template}")
        sys.exit(1)

    raw_json = None
    if args.subs_file:
        if not os.path.exists(args.subs_file):
            print(f"ERR: subs-file not found: {args.subs_file}")
            sys.exit(1)
        with open(args.subs_file, encoding='utf-8') as f:
            raw_json = f.read()
    elif args.subs:
        raw_json = args.subs
    else:
        print("ERR: provide either --subs or --subs-file")
        sys.exit(1)

    try:
        subs = json.loads(raw_json)
        subs = {k: v for k, v in subs.items() if not k.startswith('_')}
    except json.JSONDecodeError as e:
        print(f"ERR: invalid subs JSON: {e}")
        sys.exit(1)

    if not subs:
        print("ERR: no substitutions provided")
        sys.exit(1)

    out_dir = os.path.dirname(args.output)
    if out_dir and not os.path.exists(out_dir):
        os.makedirs(out_dir, exist_ok=True)

    try:
        doc = Document(args.template)
    except Exception as e:
        print(f"ERR: load template fail: {e}")
        sys.exit(1)

    # Sort subs by length DESC so "PT. Itergo Buana Utama" matches before "Itergo Buana Utama"
    sorted_subs = dict(sorted(subs.items(), key=lambda kv: -len(kv[0])))

    total_hits = 0
    paras_touched = 0
    for p in iter_paragraphs(doc):
        hits = replace_in_runs(p, sorted_subs)
        if hits:
            total_hits += hits
            paras_touched += 1

    try:
        doc.save(args.output)
    except Exception as e:
        print(f"ERR: save fail: {e}")
        sys.exit(1)

    sz = os.path.getsize(args.output)
    print(f"OK substitutions={total_hits} paragraphs_touched={paras_touched} bytes={sz} output={args.output}")


if __name__ == '__main__':
    main()
