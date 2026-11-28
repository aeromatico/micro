/* Utilidades y datos ficticios compartidos por las tres demos (mismos nombres que `church:demo`). Nada se guarda ni se envía. */
const $ = (s) => document.querySelector(s);
const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const val = (id) => { const e = document.getElementById(id); return e ? e.value.trim() : ''; };
const on = (id) => { const e = document.getElementById(id); return !!(e && e.checked); };
const money = (v) => Number(v).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const CHURCH = 'Comunidad Vida Nueva';
const TODAY = new Date(); TODAY.setHours(0, 0, 0, 0);
const day = (n, h = 0, m = 0) => { const d = new Date(TODAY); d.setDate(d.getDate() + n); d.setHours(h, m); return d; };
const pad = (n) => String(n).padStart(2, '0');
const fDate = (d) => d ? `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}` : '—';
const fShort = (d) => `${pad(d.getDate())}/${pad(d.getMonth() + 1)}`;
const fTime = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const nextSunday = ((7 - TODAY.getDay()) % 7) || 7;
let seed = 25025;
const rnd = (a, b) => { seed = (seed * 16807) % 2147483647; return a + (seed % (b - a + 1)); };
const pick = (a) => a[rnd(0, a.length - 1)];
let uid = 1000;

/** Dibujo con aspecto de QR. Es decorativo a propósito: no se puede escanear. */
function qrSvg(text, size = 160) {
  let h = 7; for (const c of String(text)) h = (h * 31 + c.charCodeAt(0)) % 2147483647;
  const n = 25; let out = '';
  const finder = (x, y) => `<path d="M${x} ${y}h7v7h-7zM${x + 1} ${y + 1}v5h5v-5z" fill-rule="evenodd"/><path d="M${x + 2} ${y + 2}h3v3h-3z"/>`;
  for (let y = 0; y < n; y++) for (let x = 0; x < n; x++) {
    h = (h * 16807) % 2147483647;
    if ((x < 8 && (y < 8 || y > n - 9)) || (y < 8 && x > n - 9)) continue;
    if (h % 100 < 47) out += `M${x} ${y}h1v1h-1z`;
  }
  return `<svg viewBox="-1 -1 ${n + 2} ${n + 2}" width="${size}" height="${size}" fill="#111" style="background:#fff" role="img" aria-label="Código QR de ejemplo">${finder(0, 0)}${finder(n - 7, 0)}${finder(0, n - 7)}<path d="${out}"/></svg>`;
}

function toast(msg, kind = 'ok') {
  const t = document.createElement('div'); t.className = 'toast ' + kind; t.setAttribute('role', 'status'); t.textContent = msg;
  document.body.appendChild(t); setTimeout(() => t.remove(), 3200);
}
function closeModal() { const m = $('#modal'); if (m) m.remove(); }
function modal(html, wide) {
  closeModal();
  const m = document.createElement('div'); m.id = 'modal'; m.className = 'overlay';
  m.innerHTML = `<div class="dialog${wide ? ' wide' : ''}" role="dialog" aria-modal="true"><button class="x" data-act="close" aria-label="Cerrar">×</button>${html}</div>`;
  m.addEventListener('click', (e) => { if (e.target === m) closeModal(); });
  document.body.appendChild(m);
}
let pendingYes = null;
function confirmBox(msg, yes) {
  pendingYes = yes;
  modal(`<p class="ask">${esc(msg)}</p><div class="actions"><button class="btn" data-act="close">Volver</button><button class="btn primary" data-act="yes">Confirmar</button></div>`);
}
const ACT = { close: closeModal, yes: () => { const f = pendingYes; pendingYes = null; closeModal(); if (f) f(); } };
document.addEventListener('click', (e) => {
  const el = e.target.closest('[data-act]'); if (!el || el.disabled) return;
  if (el.tagName === 'A' || el.tagName === 'BUTTON') e.preventDefault();
  const f = ACT[el.dataset.act]; if (f) f(el.dataset.id, el);
});
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

/* ───── personas ───── */
const BRANCHES = ['Sede Central', 'Sede Zona Sur'];
const MSTAT = { visitor: 'Visitante', new: 'Nuevo asistente', active: 'Miembro activo', inactive: 'Miembro inactivo' };
const MEMBERS = [];
[['Mamani Quispe', 'Juan', 'María', ['Valentina', 'Mateo', 'Camila']], ['Condori Flores', 'Carlos', 'Ana', ['Emanuel', 'Abigail']], ['Choque Gutiérrez', 'Luis', 'Rosa', ['Josué']],
 ['Vargas Rojas', 'Pedro', 'Lucía', ['Noemí', 'Isaac', 'Lía']], ['Quispe Mendoza', 'José', 'Elena', []], ['Torrez Salazar', 'Marcos', 'Carmen', ['Thiago', 'Sara']],
 ['Huanca Pérez', 'Daniel', 'Sofía', ['Benjamín']], ['Rodríguez Cruz', 'Samuel', 'Patricia', ['Ruth', 'Gael']]].forEach(([sur, h, w, kids], i) => {
  const branch = BRANCHES[i % 3 === 2 ? 1 : 0], family = 'Familia ' + sur;
  const add = (first, role, age) => MEMBERS.push({ id: ++uid, first, last: sur, family, role, age, branch, status: 'active', consent: age >= 18 && uid % 4 !== 0, since: -rnd(40, 330), birth: rnd(20, 330) });
  add(h, 'Cabeza de familia', rnd(32, 55)); add(w, 'Cónyuge', rnd(29, 52)); kids.forEach((k) => add(k, 'Hijo/a', rnd(3, 16)));
});
[['Andrea', 'Lima Torrico', 19, 'active', -210], ['Diego', 'Paco Ticona', 22, 'active', -150], ['Karen', 'Poma Limachi', 24, 'active', -95], ['Eulogia', 'Ticona Mamani', 71, 'active', -300],
 ['Silvia', 'Mercado Ortiz', 38, 'active', -62], ['Rolando', 'Gómez Vera', 45, 'active', -180], ['Gonzalo', 'Rivero Sandoval', 31, 'new', -12], ['Mariela', 'Cárdenas Ruiz', 27, 'new', -6],
 ['Teresa', 'Nina Choque', 49, 'inactive', -320]].forEach(([first, last, age, status, since], i) =>
  MEMBERS.push({ id: ++uid, first, last, family: '', role: '', age, branch: BRANCHES[i % 2], status, consent: status !== 'inactive' && i % 3 !== 2, since, birth: rnd(20, 330) }));
[[0, 1], [3, 3], [9, 5]].forEach(([i, n]) => { MEMBERS[i].birth = n; });
const fullName = (m) => m.first + ' ' + m.last;
const member = (id) => MEMBERS.find((m) => m.id === +id);
const adults = () => MEMBERS.filter((m) => m.age >= 18);

/* ───── eventos (cupo, lista de espera, llegada) ───── */
const ETYPE = { service: 'Culto', retreat: 'Retiro', conference: 'Conferencia', special: 'Actividad especial' };
const EVENTS = [
  { id: 1, title: 'Culto dominical', type: 'service', at: day(nextSunday, 10), place: 'Templo principal', cap: 150, pub: true, open: true, text: 'Te esperamos este domingo.', n: 12 },
  { id: 3, title: 'Conferencia de familia', type: 'conference', at: day(10, 18, 30), place: 'Sede Zona Sur', cap: 100, pub: false, open: true, text: 'Solo para miembros.', n: 8 },
  { id: 2, title: 'Retiro de jóvenes', type: 'retreat', at: day(21, 8), place: 'Casa de retiros, Coroico', cap: 12, pub: true, open: true, text: 'Dos días de comunión, enseñanza y descanso. Cupo limitado.', n: 15 },
];
const REGS = [];
// Ana (la del portal) solo empieza inscrita al culto, para que pueda probar las demás inscripciones.
EVENTS.forEach((e) => adults().filter((m) => e.id === 1 || m.first !== 'Ana').slice(e.id, e.id + e.n).forEach((m, i) => REGS.push({ id: ++uid, event: e.id, member: m.id, name: fullName(m), status: i < e.cap ? 'registered' : 'waitlist', in: null })));
const seats = (e) => REGS.filter((r) => r.event === e.id && r.status === 'registered').length;
const waiting = (e) => REGS.filter((r) => r.event === e.id && r.status === 'waitlist').length;
function enroll(e, name, memberId) {
  const r = { id: ++uid, event: e.id, member: memberId || null, name, status: seats(e) < e.cap ? 'registered' : 'waitlist', in: null };
  REGS.push(r); return r;
}
/** Igual que el servicio real: al liberarse un cupo sube el primero de la lista de espera. */
function unenroll(r) {
  const seated = r.status === 'registered'; REGS.splice(REGS.indexOf(r), 1);
  const e = EVENTS.find((x) => x.id === r.event);
  if (!seated || seats(e) >= e.cap) return null;
  const next = REGS.find((x) => x.event === e.id && x.status === 'waitlist');
  if (next) next.status = 'registered';
  return next || null;
}
