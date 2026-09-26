#!/usr/bin/env node
/**
 * Guardrail para el Flujo 6 (ver docs/workflows.md) — "Safelist de Tailwind,
 * CRÍTICO": toda clase Tailwind literal usada por components.jsx o
 * PuckHtmlRenderer.php tiene que estar en el `safelist` de
 * themes/microsites/tailwind.config.js, porque ninguno de esos dos archivos
 * es escaneado por `content` (uno es JS, el otro genera HTML dinámico desde
 * PHP) — sin el safelist, Tailwind purga la clase del CSS compilado y el
 * bloque se ve roto en producción/galerías aunque el render() esté perfecto.
 *
 * Antes esto dependía 100% de que el dev se acuerde de editar el safelist a
 * mano (y de que, por casualidad, la clase ya apareciera en algún .htm
 * escaneado por `content` — así se coló brand-primary/shrink-0/etc. sin
 * error visible). Este script corre en cada `npm run build` del editor y
 * hace FALLAR el build si aparece una clase nueva sin safelistear, en vez de
 * dejarlo pasar silenciosamente hasta que alguien note un header roto.
 *
 * No es perfecto (heurística de regex, no un parser de Tailwind real) —
 * prioriza no tener falsos negativos (clase real sin safelistear) aunque
 * eso implique algún falso positivo ocasional (agregar ese caso a
 * IGNORE_TOKENS de abajo).
 */
import { readFileSync, readdirSync, statSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '../../../../../..'); // raíz del repo

const SOURCES = [
    resolve(__dirname, '../src/components.jsx'),
    resolve(root, 'plugins/aero/sites/classes/ai/PuckHtmlRenderer.php'),
];

const CATALOG_PHP = resolve(root, 'plugins/aero/sites/classes/ComponentBlockCatalog.php');
const TAILWIND_CONFIG = resolve(root, 'themes/microsites/tailwind.config.js');
const CONTENT_GLOB_ROOTS = [
    resolve(root, 'themes/microsites/layouts'),
    resolve(root, 'themes/microsites/pages'),
    resolve(root, 'themes/microsites/partials'),
    resolve(root, 'plugins/aero/sites/components'),
    resolve(root, 'plugins/aero/shop/components'),
    resolve(root, 'plugins/aero/crm/components'),
    resolve(root, 'plugins/aero/docs/components'),
];

// Tokens que matchean la heurística pero no son clases Tailwind reales
// (valores de atributos, placeholders, nombres de ícono, etc.) — agregar acá
// si aparece un falso positivo nuevo que no encaje en ninguna regla genérica
// de looksLikeTailwindClass().
const IGNORE_TOKENS = new Set([
    'tabler', 'group-hover', 'group-open', 'aria-expanded', 'aria-label',
    'picture-in-picture', 'border-box',
]);

/**
 * Los valores de `variant` (ej. 'barra-marca', 'imagen-lateral') tienen la
 * misma pinta kebab-case que una clase Tailwind, pero son enums de negocio,
 * no CSS — aparecen como keys de los arrays *_VARIANTS de
 * ComponentBlockCatalog.php (y, en espejo, como `value:` en los
 * *_VARIANT_OPTIONS de components.jsx). Se excluyen leyéndolos de ahí en vez
 * de mantener una lista aparte a mano — si el catálogo cambia, el ignore se
 * actualiza solo.
 */
function readVariantEnumValues() {
    const source = readFileSync(CATALOG_PHP, 'utf8');
    const values = new Set();
    const re = /'([a-z][a-z0-9]*(?:-[a-z0-9]+)+)'\s*=>/g;
    let m;
    while ((m = re.exec(source)) !== null) values.add(m[1]);
    return values;
}

function extractStringLiterals(source) {
    const literals = [];
    // "..." / '...' / `...` — suficiente para className="a b c", class="...",
    // y template literals tipo `hidden md:flex ${x}` (se ignora la parte ${})
    const re = /"([^"\\]*(?:\\.[^"\\]*)*)"|'([^'\\]*(?:\\.[^'\\]*)*)'|`([^`\\]*(?:\\.[^`\\]*)*)`/g;
    let m;
    while ((m = re.exec(source)) !== null) {
        literals.push(m[1] ?? m[2] ?? m[3] ?? '');
    }
    return literals;
}

function looksLikeTailwindClass(token, variantEnumValues) {
    if (!token || token.length > 40) return false;
    if (IGNORE_TOKENS.has(token)) return false;
    if (variantEnumValues.has(token)) return false;
    // Tailwind es case-sensitive-lowercase (salvo casos exóticos que este
    // proyecto no usa) — descarta headers HTTP (X-Foo-Bar), etc.
    if (token !== token.toLowerCase()) return false;
    // necesita al menos una letra (descarta "-1", "2:", etc.)
    if (!/[a-z]/.test(token)) return false;
    // fragmento incompleto: prefijo de un template literal con `${i}` (ej.
    // "puck-tabs-label-") o propiedad CSS de un `style="color: ...;"`
    // (ej. "color:") — ninguno es una clase completa.
    if (token.endsWith('-') || token.endsWith(':')) return false;
    // formato típico: letras/números, ':' (variantes/pseudo), '-', '/', '.'
    // (opacidades tipo bg-black/50, decimales tipo gap-2.5)
    if (!/^[a-z0-9:/.\-]+$/.test(token)) return false;
    // rutas de archivo / URLs (contienen '//' o empiezan con '/', o extensión
    // de archivo tipo .png/.svg)
    if (token.startsWith('/') || token.includes('//')) return false;
    if (/\.(png|svg|jpg|jpeg|webp|json|php|jsx?|css)$/.test(token)) return false;
    // nombres de ícono tipo "tabler:star"
    if (token.startsWith('tabler:')) return false;
    // necesita pinta de utilidad: guion, ':' de variante, o palabra Tailwind
    // conocida sin guion (flex, block, hidden, group, etc.)
    return /-/.test(token) || /:/.test(token) || KNOWN_BARE_UTILITIES.has(token);
}

const KNOWN_BARE_UTILITIES = new Set([
    'flex', 'block', 'hidden', 'relative', 'absolute', 'fixed', 'group',
    'container', 'grid', 'table', 'italic', 'underline', 'truncate', 'sr-only',
    'border', 'shadow', 'rounded', 'antialiased',
]);

function collectCandidateClasses(filePath, variantEnumValues) {
    const source = readFileSync(filePath, 'utf8');
    const classes = new Set();
    for (const literal of extractStringLiterals(source)) {
        // Solo nos interesan literales que parecen listas de clases
        // separadas por espacio (evita capturar URLs sueltas, textos, etc.
        // — igual cada token se re-valida individualmente abajo).
        for (const token of literal.split(/\s+/)) {
            if (looksLikeTailwindClass(token, variantEnumValues)) classes.add(token);
        }
    }
    return classes;
}

function readSafelist() {
    const source = readFileSync(TAILWIND_CONFIG, 'utf8');
    const match = source.match(/safelist:\s*\[([\s\S]*?)\n\s*\],?\n\s*\}\s*;?\s*$/);
    const block = match ? match[1] : source; // fallback: buscar en todo el archivo
    const strings = new Set();
    const re = /'([^'\\]*(?:\\.[^'\\]*)*)'/g;
    let m;
    while ((m = re.exec(block)) !== null) strings.add(m[1]);
    return strings;
}

function walkHtmFiles(dirPath, out) {
    let entries;
    try {
        entries = readdirSync(dirPath);
    } catch {
        return;
    }
    for (const entry of entries) {
        const full = resolve(dirPath, entry);
        const stat = statSync(full);
        if (stat.isDirectory()) {
            walkHtmFiles(full, out);
        } else if (entry.endsWith('.htm') || entry.endsWith('.html')) {
            out.push(full);
        }
    }
}

function readStaticContentClasses(variantEnumValues) {
    const files = [];
    for (const dir of CONTENT_GLOB_ROOTS) walkHtmFiles(dir, files);
    const classes = new Set();
    for (const file of files) {
        for (const c of collectCandidateClasses(file, variantEnumValues)) classes.add(c);
    }
    return classes;
}

function main() {
    const variantEnumValues = readVariantEnumValues();

    const used = new Set();
    for (const file of SOURCES) {
        for (const c of collectCandidateClasses(file, variantEnumValues)) used.add(c);
    }

    const safelist = readSafelist();
    const staticallyScanned = readStaticContentClasses(variantEnumValues);

    const missing = [...used]
        .filter((c) => !safelist.has(c) && !staticallyScanned.has(c))
        .sort();

    if (missing.length === 0) {
        console.log(`check-tailwind-safelist: OK (${used.size} clases usadas, todas cubiertas por safelist o content-scan).`);
        return;
    }

    console.error('check-tailwind-safelist: FALTAN clases en themes/microsites/tailwind.config.js (safelist):');
    for (const c of missing) console.error('  - ' + c);
    console.error('');
    console.error('Agregalas al array `safelist` de themes/microsites/tailwind.config.js y volvé a correr');
    console.error('`npm run build` ahí (ver docs/workflows.md, Flujo 6) — si no, Tailwind las purga del');
    console.error('CSS compilado y el bloque se ve roto en producción/galerías aunque el render() esté bien.');
    process.exitCode = 1;
}

main();
