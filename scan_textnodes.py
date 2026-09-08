# -*- coding: utf-8 -*-
"""Extract hardcoded UI text candidates from Vue SFC template."""
import re
import sys

ATTR = re.compile(r'\b(label|title|placeholder|empty-text|no-data|active-text|inactive-text|confirm-button-text|cancel-button-text|message|content|header|sub-title)="([^"]+)"')
VN = re.compile(r'[àáảãạăằắẳẵặâầấẩẫậèéẻẽẹêềếểễệìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưứừửữựýỳỷỹỵđÀÁẢÃẠĂẰẮẲẴẶÂẦẤẨẪẬÈÉẺẼẸÊỀẾỂỄỆÌÍỈĨỊÒÓỎÕỌÔỒỐỔỖỘƠỜỚỞỠỢÙÚỦŨỤƯỪỨỬỮỰÝỲỶỸỴĐ]')
WORD = re.compile(r'[A-Za-zÀ-ỹ]{2,}')


def analyze(path):
    with open(path, encoding='utf-8') as f:
        lines = f.readlines()

    # find template bounds
    start = end = None
    for i, raw in enumerate(lines):
        if '<template' in raw and start is None:
            start = i
        if start is not None and '</template>' in raw:
            end = i
            break
    if start is None:
        print("NO TEMPLATE")
        return
    for i in range(start, end + 1):
        s = lines[i].rstrip('\n')
        # strip HTML comments (same line)
        s2 = re.sub(r'<!--.*?-->', '', s)
        if s2.strip() == '':
            continue
        # attribute candidates
        for m in ATTR.finditer(s2):
            val = m.group(2)
            if '{{' not in val and not val.startswith(':') and WORD.search(val) and VN.search(val):
                print(f"{i}: ATTR {m.group(1)}={val!r}")
        # text node candidates: remove tags and bindings, see remaining words
        txt = re.sub(r'<[^>]+>', ' ', s2)
        txt = re.sub(r'\{\{[^}]*\}\}', ' ', txt)
        txt = re.sub(r'\s+', ' ', txt).strip()
        if VN.search(txt):
            # only report lines where a real VN word remains outside tags
            print(f"{i}: TEXT {txt!r}")


if __name__ == '__main__':
    for p in sys.argv[1:]:
        print(f"===== {p} =====")
        analyze(p)
