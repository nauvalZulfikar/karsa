"""
Render PDF pages to base64-encoded PNG.
Output (stdout): one line per page → "PAGE:<base64>"
On error: "ERROR:<message>"

Usage: python pdf_ocr_render.py <path/to/file.pdf> <max_pages>

Requires: pip install pymupdf
"""
import sys
import base64
import io

def main():
    if len(sys.argv) < 2:
        print("ERROR:Usage: pdf_ocr_render.py <pdf_path> <max_pages>")
        sys.exit(1)

    pdf_path = sys.argv[1]
    max_pages = int(sys.argv[2]) if len(sys.argv) > 2 else 5

    try:
        import fitz  # pymupdf
    except ImportError:
        print("ERROR:pymupdf belum terinstall. Run: pip install pymupdf")
        sys.exit(1)

    try:
        doc = fitz.open(pdf_path)
    except Exception as e:
        print(f"ERROR:Gagal buka PDF: {e}")
        sys.exit(1)

    total = min(len(doc), max_pages)
    # Render at 150 DPI for OCR-friendly quality but reasonable size
    zoom = 150 / 72  # 150 DPI / default 72 DPI
    mat = fitz.Matrix(zoom, zoom)

    for i in range(total):
        try:
            page = doc[i]
            pix = page.get_pixmap(matrix=mat, alpha=False)
            png_bytes = pix.tobytes("png")
            b64 = base64.b64encode(png_bytes).decode("ascii")
            print(f"PAGE:{b64}", flush=True)
        except Exception as e:
            print(f"ERROR:Page {i+1}: {e}")
            sys.exit(1)

    doc.close()

if __name__ == "__main__":
    main()
