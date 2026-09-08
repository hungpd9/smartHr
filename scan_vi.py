# -*- coding: utf-8 -*-
"""Scan .vue files for lines containing Vietnamese UI text (excluding comments)."""
import re
import sys

VN = re.compile(r'[àáảãạăằắẳẵặâầấẩẫậèéẻẽẹêềếểễệìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưứừửữựýỳỷỹỵđÀÁẢÃẠĂẰẮẲẴẶÂẦẤẨẪẬÈÉẺẼẸÊỀẾỂỄỆÌÍỈĨỊÒÓỎÕỌÔỒỐỔỖỘƠỜỚỞỠỢÙÚỦŨỤƯỪỨỬỮỰÝỲỶỸỴĐ]')

def scan(path):
    with open(path, encoding='utf-8') as f:
        lines = f.readlines()
    print(f"===== {path} =====")
    for i, raw in enumerate(lines, 1):
        s = raw.rstrip('\n')
        stripped = s.strip()
        if not stripped:
            continue
        if stripped.startswith('//') or stripped.startswith('*') or stripped.startswith('<!--') or stripped.startswith('<!--') or stripped.endswith('-->'):
            continue
        # inline comment markers
        if not VN.search(s):
            continue
        # skip pure HTML comment lines like `<!-- ... -->`
        if re.match(r'^\s*<!--', s):
            continue
        print(f"{i}: {s}")

if __name__ == '__main__':
    for p in sys.argv[1:]:
        scan(p)
