/* Armazón común de los paneles (menú lateral como en el backend). Cada demo define MENU y VIEWS. */
let view = '';
const table = (heads, rows, empty = 'No hay registros.') => `<div class="table"><table><thead><tr>${heads.map((h) => `<th>${h}</th>`).join('')}</tr></thead><tbody>${rows.join('') || `<tr><td colspan="${heads.length}" class="muted">${empty}</td></tr>`}</tbody></table></div>`;
const tag = (label, color = '') => `<span class="tag ${color}">${esc(label)}</span>`;
const options = (map, sel, first) => (first ? `<option value="">${first}</option>` : '') + Object.entries(map).map(([k, v]) => `<option value="${esc(k)}"${String(k) === String(sel) ? ' selected' : ''}>${esc(v)}</option>`).join('');
const stat = (label, big, sub = '', go = '') => `<div class="card stat"><span class="muted small">${label}</span><b>${big}</b><span class="small muted">${sub}${go ? ` <button class="link" data-act="go" data-id="${go}">Ver</button>` : ''}</span></div>`;
function render() {
  $('#menu').innerHTML = MENU.map(([k, l, badge]) => { const n = badge ? badge() : 0; return `<button data-act="go" data-id="${k}" class="${view === k ? 'on' : ''}">${l}${n ? `<b>${n}</b>` : ''}</button>`; }).join('');
  $('#main').innerHTML = VIEWS[view]();
}
ACT.go = (v) => { view = v; closeModal(); render(); window.scrollTo(0, 0); };
function start() {
  view = MENU[0][0]; render();
  // Las fechas se aplican al confirmar («change»): redibujar mientras se teclean les quitaría el foco.
  const apply = (e) => { const t = e.target; if (!t.dataset.filter || (e.type === 'input') === (t.type === 'date')) return; FILTER[t.dataset.filter] = t.value; const id = t.id, p = t.selectionStart; render(); const el = document.getElementById(id); if (el) { el.focus(); if (el.type === 'search') el.setSelectionRange(p, p); } };
  document.addEventListener('input', apply); document.addEventListener('change', apply);
}
const FILTER = {};
