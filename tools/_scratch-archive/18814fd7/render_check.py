import os, win32com.client, pymupdf

DOCX = r"D:\Social-Creative-Target-List-Sept-2026.docx"
PDF  = r"C:\Users\sumit\AppData\Local\Temp\claude\c--Users-sumit-Local-Sites-plugintesting-app-public\18814fd7-f825-40ce-b88b-9a6a4b18a82e\scratchpad\check.pdf"
OUT  = r"C:\Users\sumit\AppData\Local\Temp\claude\c--Users-sumit-Local-Sites-plugintesting-app-public\18814fd7-f825-40ce-b88b-9a6a4b18a82e\scratchpad"

word = win32com.client.Dispatch("Word.Application")
word.Visible = False
try:
    d = word.Documents.Open(DOCX, ReadOnly=True)
    d.SaveAs(PDF, FileFormat=17)   # wdFormatPDF
    pages = d.ComputeStatistics(2) # wdStatisticPages
    d.Close(False)
finally:
    word.Quit()

print("PDF pages:", pages)
doc = pymupdf.open(PDF)
print("rendered pages:", doc.page_count)
for i in range(doc.page_count):
    p = doc[i]
    pix = p.get_pixmap(dpi=110)
    pix.save(os.path.join(OUT, f"page{i+1}.png"))
print("images written")
