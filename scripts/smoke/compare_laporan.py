"""Compare Laporan AI vs Asli for both projects."""
import sys
sys.stdout.reconfigure(encoding='utf-8')

from docx import Document
from difflib import SequenceMatcher

def extract_text(path):
    doc = Document(path)
    return "\n".join(p.text.strip() for p in doc.paragraphs if p.text.strip())

def score(a, b):
    return SequenceMatcher(None, a.lower(), b.lower()).ratio() * 100

def check_keywords(text, keywords):
    found = sum(1 for kw in keywords if kw.lower() in text.lower())
    return found, len(keywords)

projects = [
    {
        "name": "Topografi (Purna Wahana)",
        "asli": "D:/Downloads/coding project/project_management/doc-kajian-pemetaan-topografi/laporan/Laporan Pendahuluan Asli.docx",
        "ai": "D:/Downloads/coding project/project_management/doc-kajian-pemetaan-topografi/laporan/Laporan Pendahuluan AI.docx",
        "keywords": [
            "Kajian Pemetaan Topografi", "Desa Lebakmuncang", "Ciwidey",
            "PURNA WAHANA", "Laporan Pendahuluan",
            "survey", "topografi", "GPS", "pemetaan",
            "APBD", "Kabupaten Bandung", "DPUTR",
        ],
    },
    {
        "name": "Geoteknik (Itergo)",
        "asli": "D:/Downloads/coding project/project_management/doc-geoteknik-stabilitas-tanah/laporan/Laporan Pendahuluan Asli.docx",
        "ai": "D:/Downloads/coding project/project_management/doc-geoteknik-stabilitas-tanah/laporan/Laporan Pendahuluan AI.docx",
        "keywords": [
            "Kajian Geoteknik", "Stabilitas Tanah", "Desa Lebakmuncang", "Ciwidey",
            "ITERGO", "Laporan Pendahuluan",
            "sondir", "bor log", "geoteknik", "stabilitas",
            "APBD", "Kabupaten Bandung", "DPUTR",
        ],
    },
]

for proj in projects:
    print(f"\n{'='*60}")
    print(f"  {proj['name']}")
    print(f"{'='*60}")

    asli = extract_text(proj["asli"])
    ai = extract_text(proj["ai"])

    print(f"  Asli: {len(asli.split())} kata")
    print(f"  AI:   {len(ai.split())} kata")

    s = score(ai, asli)
    print(f"\n  Match AI vs Asli: {s:.1f}%")

    found_asli, total = check_keywords(asli, proj["keywords"])
    found_ai, _ = check_keywords(ai, proj["keywords"])
    print(f"  Keywords di Asli: {found_asli}/{total}")
    print(f"  Keywords di AI:   {found_ai}/{total}")

    # Check for leaks (wrong project names)
    leaks = ["Sekolah Rakyat", "Jalan Soreang", "Drainase"]
    leak_found = [l for l in leaks if l.lower() in ai.lower()]
    print(f"  Leaks di AI: {leak_found if leak_found else 'CLEAN'}")

print()
