# -*- coding: utf-8 -*-
"""Search exact substrings in a file (position aware)."""
import sys

needles = [
    "Kỷ luật", "Chấm công", "Phúc lợi", "Tác động Chấm công", "miễn trừ",
    "Loại lương", "hưởng lương", "Danh mục", "Toàn diện", "Chỉnh sửa",
    "Doanh nghiệp", "Phút", "Phân ca", "hằng", "Tạo mới", "Có hưởng",
]
path = sys.argv[1]
with open(path, encoding='utf-8') as f:
    lines = f.readlines()

for i, raw in enumerate(lines, 1):
    s = raw.rstrip('\n')
    for n in needles:
        idx = 0
        while True:
            j = s.find(n, idx)
            if j == -1:
                break
            ctx = s[max(0, j-40):j+len(n)+40].replace('\t', ' ')
            print(f"{path}:{i} [{n}] ...{ctx}...")
            idx = j + 1
            break
