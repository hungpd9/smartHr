# -*- coding: utf-8 -*-
"""Parse locale ts into flat key set (namespace aware, brace counting). Check references exist & duplicates."""
import re
import sys

KEYLINE = re.compile(r'^\s*([a-z_][a-z0-9_]*)\s*:')


def parse_ts(path):
    """Return (full_path_set, duplicates list, namespace_sets dict, namespace_line dict)."""
    paths = set()
    dups = []
    ns_lines = {}
    with open(path, encoding='utf-8') as f:
        lines = f.readlines()

    stack = []  # list of (name, brace_depth_entered)
    depth = 0
    in_str = False
    esc = False
    for no, raw in enumerate(lines, 1):
        s = raw
        # tokenize the line char by char for braces, respecting strings
        line_key = None
        m = KEYLINE.match(s)
        if m and not in_str:
            line_key = m.group(1)
        # count braces ignoring string contents
        i = 0
        open_add = close_add = 0
        while i < len(s):
            c = s[i]
            if in_str:
                if esc:
                    esc = False
                elif c == '\\':
                    esc = True
                elif c == "'":
                    in_str = False
            else:
                if c == "'":
                    in_str = True
                elif c == '{':
                    open_add += 1
                elif c == '}':
                    close_add += 1
            i += 1
        if line_key:
            # record key at current depth: key path = stack names + this key
            p = '.'.join(stack + [line_key])
            if p in paths:
                dups.append((p, no))
            paths.add(p)
            if len(stack) == 0:  # top-level namespace start on this line
                ns_lines.setdefault(p, no)
        depth += open_add
        depth -= close_add
        if open_add and m:
            # namespace or nested object opened
            stack.append(line_key)
        # close popped keys when depth reduces: track with stack of depths
        # simpler: maintain separate stack of depths where objects opened
        # REBUILD simpler using brace depth markers below instead
    return paths, dups


def parse_ts2(path):
    """Parse with per-line depth stack accounting objects opened at line starts."""
    paths = set()
    dups = []
    ns_lines = {}
    with open(path, encoding='utf-8') as f:
        lines = f.readlines()

    # We track, for each brace level, the key name that opened at that level.
    key_at_depth = {}  # depth -> key name
    depth = 0
    in_str = False
    esc = False
    pending_key = None
    for no, raw in enumerate(lines, 1):
        s = raw
        m = KEYLINE.match(s)
        pending_key = m.group(1) if m and not in_str else None

        i = 0
        while i < len(s):
            c = s[i]
            if in_str:
                if esc:
                    esc = False
                elif c == '\\':
                    esc = True
                elif c == "'":
                    in_str = False
            else:
                if c == "'":
                    in_str = True
                elif c == '{':
                    # an object opens here (either value at current key, or array)
                    if pending_key and not key_at_depth.get(depth + 1):
                        # object belongs to pending_key at new depth
                        key_at_depth[depth + 1] = pending_key
                        pending_key = None
                    depth += 1
                elif c == '}':
                    if depth in key_at_depth:
                        key_at_depth.pop(depth)
                    depth -= 1
            i += 1
        if m:
            p = '.'.join(key_at_depth.get(d, '') for d in sorted(key_at_depth) if key_at_depth.get(d))
            full = p + ('.' + m.group(1) if p else m.group(1))
            if full in paths:
                dups.append((full, no))
            paths.add(full)
            if len(key_at_depth) == 0:
                ns_lines.setdefault(full, no)
    return paths, dups, ns_lines


if __name__ == '__main__':
    mode = sys.argv[1]  # 'check' or 'parse'
    if mode == 'parse':
        p = sys.argv[2]
        paths, dups, ns = parse_ts2(p)
        print(f"== {p}: total keys {len(paths)}, duplicates {len(dups)}")
        for d, no in dups[:60]:
            print(f"  DUP {d} (line {no})")
        top = sorted(k for k in ns)
        print("namespaces:", ', '.join(top))
    elif mode == 'check':
        vi_path, en_path = sys.argv[2], sys.argv[3]
        if sys.argv[4] == '@file':
            with open(sys.argv[5], encoding='utf-8') as f:
                refs = sorted(set(line.strip() for line in f if line.strip()))
        else:
            refs = sorted(set(sys.argv[4:]))
        vi_paths, vi_dups, _ = parse_ts2(vi_path)
        en_paths, en_dups, _ = parse_ts2(en_path)
        print(f"vi keys={len(vi_paths)} dups={len(vi_dups)} | en keys={len(en_paths)} dups={len(en_dups)}")
        for d, no in vi_dups:
            print(f"  VI-DUP {d} line {no}")
        for d, no in en_dups:
            print(f"  EN-DUP {d} line {no}")
        missing_vi = [r for r in refs if r not in vi_paths]
        missing_en = [r for r in refs if r not in en_paths]
        if missing_vi:
            print("MISSING IN VI:")
            for r in missing_vi:
                print("  ", r)
        if missing_en:
            print("MISSING IN EN:")
            for r in missing_en:
                print("  ", r)
        if not missing_vi and not missing_en:
            print("ALL REFERENCED KEYS EXIST IN BOTH vi & en")
