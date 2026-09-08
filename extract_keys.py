# -*- coding: utf-8 -*-
"""Extract i18n key references from .vue files: t('a.b.c', ...), $t('a.b.c'), t(`...`)."""
import re
import sys

PAT = re.compile(r"(?:\$\s*t|\bt)\s*\(\s*(?:'((?:[^'\\]|\\.)*)'|\"((?:[^\"\\]|\\.)*)\"|`((?:[^`\\]|\\.)*)`)")

def scan(path):
    keys = set()
    dyn = set()
    with open(path, encoding='utf-8') as f:
        for no, raw in enumerate(f, 1):
            s = raw
            # skip comment-only lines to reduce noise, but keep inline refs
            for m in PAT.finditer(s):
                lit = next((g for g in m.groups() if g is not None), None)
                if lit is None:
                    continue
                # unescape basic
                lit = lit.replace("\\'", "'").replace('\\"', '"').replace('\\`', '`')
                if '{' in lit and '}' in lit:
                    dyn.add(f"{path}:{no}:{lit}")
                else:
                    keys.add(lit)
    return keys, dyn

if __name__ == '__main__':
    all_keys = set()
    for p in sys.argv[1:]:
        keys, dyn = scan(p)
        all_keys |= keys
        for d in sorted(dyn):
            print(f"DYN {d}")
    for k in sorted(all_keys):
        print(k)
