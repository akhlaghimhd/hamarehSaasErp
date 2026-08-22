from pathlib import Path
from pypdf import PdfReader
from docx import Document
import re
import sys

root = Path(r'E:\myProject\hamareherp\architectureDocs')
out = root / '_all_extracted_text.txt'
files = sorted([f for f in root.rglob('*') if f.is_file() and f.suffix.lower() in {'.pdf', '.docx'}])
print(f'FOUND {len(files)} documents')
with out.open('w', encoding='utf-8', errors='replace') as writer:
    writer.write(f'TOTAL_DOCS={len(files)}\n\n')
    for idx, f in enumerate(files, 1):
        rel = f.relative_to(root)
        writer.write(f'===== FILE {idx}/{len(files)}: {rel} =====\n')
        print(f'PROCESSING {idx}/{len(files)} {rel}')
        try:
            text = ''
            if f.suffix.lower() == '.pdf':
                reader = PdfReader(str(f))
                for page_no, page in enumerate(reader.pages, 1):
                    page_text = page.extract_text() or ''
                    text += f'\n--- PAGE {page_no} ---\n' + page_text + '\n'
            elif f.suffix.lower() == '.docx':
                doc = Document(str(f))
                for para in doc.paragraphs:
                    text += para.text + '\n'
            cleaned = re.sub(r'\s+', ' ', text).strip()
            writer.write(cleaned[:15000])
            if len(cleaned) > 15000:
                writer.write('\n...[TRUNCATED]...\n')
            writer.write('\n\n')
            print(f'OK {rel} chars={len(cleaned)}')
        except Exception as e:
            writer.write(f'ERROR: {type(e).__name__}: {e}\n\n')
            print(f'ERROR {rel}: {type(e).__name__}: {e}')
print(f'OUTPUT {out}')
