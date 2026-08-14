#!/usr/bin/env node
/**
 * Exporta el catálogo de bloques Puck (label, desc, fields) de
 * src/components.jsx a JSON, para que SiteGenerator.php lo lea en vez de
 * duplicar el catálogo a mano. Las funciones (render, getItemSummary) se
 * descartan — no son necesarias para armar el prompt de la IA.
 *
 * Se ejecuta como parte de `npm run build` (ver package.json).
 */
import { buildSync } from 'esbuild';
import { writeFileSync, mkdirSync } from 'fs';
import { dirname, resolve } from 'path';
import { createRequire } from 'module';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const entry = resolve(__dirname, '../src/components.jsx');
const outfile = resolve(__dirname, '../dist/catalog.json');

const result = buildSync({
  entryPoints: [entry],
  bundle: true,
  write: false,
  format: 'cjs',
  platform: 'node',
  jsx: 'transform',
  logLevel: 'silent',
});

const code = result.outputFiles[0].text;
const req = createRequire(import.meta.url);
const mod = { exports: {} };
new Function('module', 'exports', 'require', code)(mod, mod.exports, req);

const { components } = mod.exports;
if (!components) {
  console.error('export-catalog: no se encontró el export "components" en components.jsx');
  process.exit(1);
}

function stripFunctions(value) {
  if (Array.isArray(value)) {
    return value.map(stripFunctions);
  }
  if (value && typeof value === 'object') {
    const out = {};
    for (const [key, val] of Object.entries(value)) {
      if (typeof val === 'function') continue;
      out[key] = stripFunctions(val);
    }
    return out;
  }
  return value;
}

const catalog = {};
for (const [name, def] of Object.entries(components)) {
  catalog[name] = {
    label: def.label,
    desc: def.desc || '',
    fields: stripFunctions(def.fields || {}),
  };
}

mkdirSync(dirname(outfile), { recursive: true });
writeFileSync(outfile, JSON.stringify(catalog, null, 2) + '\n');

console.log(`export-catalog: ${Object.keys(catalog).length} componentes -> ${outfile}`);
