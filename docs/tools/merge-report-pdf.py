# Splices the two dedicated figure pages (produced by build-report-pdf.mjs)
# back into the main document at the placeholder pages, and writes the final
# docs/AI-CaseLab-Final-Project-Report.pdf.
#
# Setup: pip install pymupdf
# Usage: python merge-report-pdf.py   (run after build-report-pdf.mjs)
import os
import fitz

WORK = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.environ.get("AICASELAB_ROOT", os.path.abspath(os.path.join(WORK, "..", "..")))
OUT = os.path.join(PROJECT_ROOT, "docs", "AI-CaseLab-Final-Project-Report.pdf")

main = fitz.open(os.path.join(WORK, "main.pdf"))
fig41 = fitz.open(os.path.join(WORK, "fig4-1.pdf"))
fig81 = fitz.open(os.path.join(WORK, "fig8-1.pdf"))

placeholders = {}
for i, page in enumerate(main):
    t = page.get_text()
    if "LANDSCAPE_PLACEHOLDER_FIG4-1" in t:
        placeholders["fig4-1"] = i
    if "LANDSCAPE_PLACEHOLDER_FIG8-1" in t:
        placeholders["fig8-1"] = i

assert "fig4-1" in placeholders and "fig8-1" in placeholders, placeholders
print("placeholders (0-idx):", placeholders)

# Insert after the higher-indexed placeholder first so earlier indices stay valid.
order = sorted(placeholders.items(), key=lambda kv: kv[1], reverse=True)
figs = {"fig4-1": fig41, "fig8-1": fig81}

for key, idx in order:
    src = figs[key]
    main.insert_pdf(src, start_at=idx + 1)
    main.delete_page(idx)

blanks = [i + 1 for i, p in enumerate(main) if not p.get_text().strip() and not p.get_drawings()]
if blanks:
    print("WARNING: blank pages remain at:", blanks)

main.save(OUT, garbage=4, deflate=True)
print("saved", OUT, "pages:", len(main))
