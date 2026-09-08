// Verify referenced keys exist in both vi.ts and en.ts by importing real modules.
import { readFileSync } from 'node:fs';
import { vi } from './resources/js/locales/vi.ts';
import { en } from './resources/js/locales/en.ts';

const PAT = /(?:\$\s*t|\bt)\s*\(\s*(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)"|`((?:[^`\\]|\\.)*)`)/g;

const files = [
  'resources/js/Pages/Policy/Index.vue',
  'resources/js/Pages/Rostering/Index.vue',
  'resources/js/Pages/Rostering/Shifts/Index.vue',
  'resources/js/Pages/CoreHR/Index.vue',
];

const refs = new Set();
for (const f of files) {
  const src = readFileSync(f, 'utf8');
  for (const m of src.matchAll(PAT)) {
    const lit = m[1] ?? m[2] ?? m[3];
    if (!lit) continue;
    if (lit.includes('{')) continue; // dynamic
    refs.add(lit);
  }
}

function hasKey(root, path) {
  let cur = root;
  for (const seg of path.split('.')) {
    if (cur == null || typeof cur !== 'object' || !(seg in cur)) return false;
    cur = cur[seg];
  }
  return true;
}

const missingVi = [];
const missingEn = [];
for (const r of [...refs].sort()) {
  if (!hasKey(vi, r)) missingVi.push(r);
  if (!hasKey(en, r)) missingEn.push(r);
}

console.log(`References checked: ${refs.size}`);
console.log(`vi total top keys: ${Object.keys(vi).length}`);
console.log(`en total top keys: ${Object.keys(en).length}`);
if (missingVi.length) console.log(`MISSING IN VI (${missingVi.length}):\n  ` + missingVi.join('\n  '));
if (missingEn.length) console.log(`MISSING IN EN (${missingEn.length}):\n  ` + missingEn.join('\n  '));
if (!missingVi.length && !missingEn.length) console.log('ALL REFERENCED KEYS EXIST IN BOTH vi & en');
