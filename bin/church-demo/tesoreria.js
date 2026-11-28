/* Tesorería: resumen de aportes, aportes (QR, comprobante, anulación) y conceptos. */
const METHODS = { cash: 'Efectivo', transfer: 'Transferencia bancaria', qr: 'QR bancario' };
const CSTAT = { pending: 'Pendiente', confirmed: 'Confirmado', cancelled: 'Anulado' };
const CCOL = { pending: 'amber', confirmed: 'green', cancelled: 'red' };
const FKIND = { tithe: 'Diezmo', offering: 'Ofrenda', donation: 'Donación', campaign: 'Campaña' };
const FUNDS = [{ id: 1, name: 'Diezmo', kind: 'tithe' }, { id: 2, name: 'Ofrenda', kind: 'offering' }, { id: 3, name: 'Donación', kind: 'donation' }, { id: 4, name: 'Misiones', kind: 'offering' }, { id: 5, name: 'Campaña templo nuevo', kind: 'campaign', goal: 80000, ends: day(240) }];
const CONTRIB = []; let receipts = 0;
function add(fund, who, date, amount, method, extra = {}) {
  const c = { id: ++uid, fund, member: who ? who.id : null, donor: who ? fullName(who) : '', anon: false, amount, cur: 'BOB', date, method, status: method === 'qr' ? 'pending' : 'confirmed', branch: who ? who.branch : BRANCHES[0], receipt: '', qr: false, ...extra };
  CONTRIB.unshift(c); return c;
}
const payers = adults().slice(0, 14);
for (let w = 40; w >= 1; w--) {
  const d = day(nextSunday - 7 * w);
  payers.forEach((p) => { if (rnd(1, 100) <= (w > 12 ? 42 : 55)) add(1, p, d, pick([100, 150, 200, 250, 300, 400, 500, 800]), rnd(1, 100) <= 70 ? 'cash' : 'transfer'); });
  add(2, null, d, rnd(8, 25) * 10, 'cash', { anon: true });
  if (rnd(1, 100) <= 60) add(4, pick(payers), d, pick([50, 100, 150]), 'cash');
  if (w <= 18 && rnd(1, 100) <= 60) add(5, pick(payers), d, pick([500, 1000, 1500, 2000]), 'transfer');
}
const receipt = (c) => { if (!c.receipt) c.receipt = 'REC-' + String(++receipts).padStart(6, '0'); return c.receipt; };
add(3, null, day(-9), 300, 'cash', { cur: 'USD', donor: 'Visitante extranjero' });
add(1, payers[4], day(-10), 999, 'cash', { status: 'cancelled' });
add(2, payers[2], day(-2), 120, 'qr', { qr: true });
add(1, payers[1], day(-1), 250, 'qr');
add(5, payers[3], TODAY, 3000, 'qr');
CONTRIB.sort((a, b) => b.date - a.date || b.id - a.id);
CONTRIB.filter((c) => c.status === 'confirmed' && c.member).slice(0, 6).reverse().forEach(receipt);
const fund = (id) => FUNDS.find((f) => f.id === +id);
const who = (c) => c.anon ? 'Anónimo' : c.donor;
const MENU = [['resumen', 'Resumen de aportes'], ['aportes', 'Aportes', () => CONTRIB.filter((c) => c.status === 'pending').length], ['conceptos', 'Conceptos de aporte']];
const sum = (rows, key) => { const o = {}; rows.forEach((c) => { const k = key(c) + '|' + c.cur; o[k] = (o[k] || 0) + c.amount; }); return Object.entries(o).map(([k, v]) => [...k.split('|'), v]).sort((a, b) => b[2] - a[2]); };
let shown = 25;
const VIEWS = {
  resumen: () => {
    const from = FILTER.from || iso(new Date(TODAY.getFullYear(), TODAY.getMonth() - 2, 1)), to = FILTER.to || iso(TODAY), br = FILTER.br || '';
    const ok = CONTRIB.filter((c) => c.status === 'confirmed' && (!br || c.branch === br));
    const rows = ok.filter((c) => iso(c.date) >= from && iso(c.date) <= to);
    const totals = sum(rows, () => 't');
    const months = Array.from({ length: 9 }, (_, i) => { const d = new Date(TODAY.getFullYear(), TODAY.getMonth() - 8 + i, 1); return [d, ok.filter((c) => c.cur === 'BOB' && c.date.getMonth() === d.getMonth() && c.date.getFullYear() === d.getFullYear()).reduce((s, c) => s + c.amount, 0)]; });
    const max = Math.max(1, ...months.map((m) => m[1]));
    const block = (title, data, label) => `<div class="card"><h3>${title}</h3>${data.map(([k, cur, v]) => `<div class="row between"><span>${esc(label(k))}</span><strong>${money(v)} ${cur}</strong></div>`).join('') || '<p class="muted">Sin datos.</p>'}</div>`;
    return `<div class="bar"><h1>Resumen de aportes</h1><div class="row"><label class="small">Desde <input id="from" data-filter="from" type="date" class="inp" value="${from}"></label><label class="small">Hasta <input id="to" data-filter="to" type="date" class="inp" value="${to}"></label><select id="br" data-filter="br" class="inp" aria-label="Sede">${options(Object.fromEntries(BRANCHES.map((b) => [b, b])), br, 'Todas las sedes')}</select></div></div>
    <div class="grid">${totals.map(([, cur, v]) => stat('Total del periodo (' + cur + ')', money(v), ((n) => `en ${n} ${n === 1 ? 'aporte' : 'aportes'}`)(rows.filter((c) => c.cur === cur).length))).join('') || stat('Total del periodo', '0,00', 'sin aportes confirmados')}${stat('QR pendientes de pago', CONTRIB.filter((c) => c.status === 'pending').length, '', 'aportes')}</div>
    <div class="cols">${block('Por concepto', sum(rows, (c) => c.fund), (k) => fund(k).name)}${block('Por sede', sum(rows, (c) => c.branch), (k) => k)}${block('Por forma de pago', sum(rows, (c) => c.method), (k) => METHODS[k])}</div>
    <div class="card" style="margin-top:12px"><h3>Evolución mensual (BOB)</h3>${months.map(([d, v]) => `<div class="hbar"><span>${d.toLocaleDateString('es', { month: 'short' }).replace('.', '')} ${String(d.getFullYear()).slice(2)}</span><i style="width:${Math.round(v / max * 55)}%"></i>${money(v)}</div>`).join('')}</div>`;
  },
  aportes: () => {
    const s = FILTER.cs || '', f = FILTER.cf || '';
    const list = CONTRIB.filter((c) => (!s || c.status === s) && (!f || c.fund === +f));
    return `<div class="bar"><h1>Aportes</h1><div class="row"><select id="cf" data-filter="cf" class="inp" aria-label="Concepto">${options(Object.fromEntries(FUNDS.map((x) => [x.id, x.name])), f, 'Todos los conceptos')}</select><select id="cs" data-filter="cs" class="inp" aria-label="Estado">${options(CSTAT, s, 'Todos los estados')}</select><button class="btn primary" data-act="newC">Nuevo aporte</button></div></div>` +
      table(['Fecha', 'Concepto', 'Aportante', 'Monto', 'Mon.', 'Forma de pago', 'Estado', 'Comprobante'], list.slice(0, shown).map((c) => `<tr data-act="open" data-id="${c.id}"><td>${fDate(c.date)}</td><td>${fund(c.fund).name}</td><td>${esc(who(c))}</td><td class="right">${money(c.amount)}</td><td>${c.cur}</td><td>${METHODS[c.method]}</td><td>${tag(CSTAT[c.status], CCOL[c.status])}</td><td>${c.receipt || '—'}</td></tr>`)) +
      (list.length > shown ? `<div class="actions" style="justify-content:center"><button class="btn" data-act="more">Ver más (${list.length - shown})</button></div>` : '');
  },
  conceptos: () => `<h1>Conceptos de aporte</h1>` + table(['Concepto', 'Tipo', 'Meta', 'Recaudado', 'Avance', 'Hasta'], FUNDS.map((f) => { const r = CONTRIB.filter((c) => c.fund === f.id && c.status === 'confirmed' && c.cur === 'BOB').reduce((s, c) => s + c.amount, 0); return `<tr><td>${f.name}</td><td>${FKIND[f.kind]}</td><td>${f.goal ? money(f.goal) : '—'}</td><td>${money(r)} BOB</td><td>${f.goal ? `<div class="hbar g"><i style="width:${Math.min(140, Math.round(r / f.goal * 140))}px"></i>${Math.round(r / f.goal * 100)}%</div>` : '—'}</td><td>${f.ends ? fDate(f.ends) : '—'}</td></tr>`; })),
};
const get = (id) => CONTRIB.find((c) => c.id === +id);
function detail(c) {
  const pend = c.method === 'qr' && c.status === 'pending';
  modal(`<h3>${money(c.amount)} ${c.cur} · ${fund(c.fund).name}</h3>${tag(CSTAT[c.status], CCOL[c.status])}${c.receipt ? ` <span class="small">Comprobante ${c.receipt}</span>` : ''}
  <dl class="kv"><dt>Fecha</dt><dd>${fDate(c.date)}</dd><dt>Aportante</dt><dd>${esc(who(c))}</dd><dt>Sede</dt><dd>${c.branch}</dd><dt>Forma de pago</dt><dd>${METHODS[c.method]}</dd></dl>
  ${pend ? (c.qr ? `<div style="text-align:center">${qrSvg('pago' + c.id, 200)}<p class="muted small">Se confirma solo cuando el banco avisa el pago.</p><button class="btn" data-act="bank" data-id="${c.id}">Demo: simular el aviso del banco</button></div>` : '<p class="muted">Aún no hay QR. Genera el código y envíaselo a quien aporta.</p>') : ''}
  <div class="actions">${pend ? `<button class="btn" data-act="qr" data-id="${c.id}">${c.qr ? 'Volver a mostrar el QR' : 'Generar QR'}</button><button class="btn ok" data-act="manual" data-id="${c.id}">Confirmar pago manualmente</button>` : ''}${c.status === 'confirmed' && !c.anon ? `<button class="btn primary" data-act="receipt" data-id="${c.id}">${c.receipt ? 'Ver comprobante' : 'Emitir comprobante'}</button>` : ''}${c.status !== 'cancelled' ? `<button class="btn danger" data-act="void" data-id="${c.id}">Anular aporte</button>` : ''}</div>`);
}
Object.assign(ACT, {
  more: () => { shown += 25; render(); },
  open: (id) => detail(get(id)),
  qr: (id) => { const c = get(id); c.qr = true; detail(c); },
  bank: (id) => { const c = get(id); c.status = 'confirmed'; render(); detail(c); toast('Pago recibido: el aporte quedó confirmado'); },
  manual: (id) => confirmBox('¿Confirmar que este aporte ya se recibió?', () => { const c = get(id); c.status = 'confirmed'; render(); detail(c); toast('Aporte confirmado'); }),
  receipt: (id) => { const c = get(id); receipt(c); render(); modal(`<div style="border:1px solid #888;padding:18px;margin-top:14px"><h3 style="font-size:18px">${CHURCH}</h3><p>Comprobante de aporte N.º <strong>${c.receipt}</strong></p><dl class="kv"><dt>Fecha</dt><dd>${fDate(c.date)}</dd><dt>Recibido de</dt><dd>${esc(who(c))}</dd><dt>Concepto</dt><dd>${fund(c.fund).name}</dd><dt>Forma de pago</dt><dd>${METHODS[c.method]}</dd><dt>Monto</dt><dd style="font-size:20px;font-weight:700">${money(c.amount)} ${c.cur}</dd></dl><p class="muted small">Gracias por su aporte. Este comprobante acredita la recepción del monto indicado.</p></div>`); },
  void: (id) => { const c = get(id); confirmBox('¿Anular este aporte?' + (c.status === 'confirmed' ? ' También se anula su asiento en Finanzas.' : ''), () => { c.status = 'cancelled'; render(); toast('Aporte anulado'); }); },
  newC: () => modal(`<h3>Nuevo aporte</h3><div class="form"><label>Concepto<select id="n-fund" class="inp">${options(Object.fromEntries(FUNDS.map((x) => [x.id, x.name])), 1)}</select></label><label>Sede<select id="n-br" class="inp">${BRANCHES.map((b) => `<option>${b}</option>`).join('')}</select></label><label>Monto<input id="n-amount" type="number" min="1" class="inp" value="200"></label><label>Moneda<select id="n-cur" class="inp"><option>BOB</option><option>USD</option></select></label><label>Forma de pago<select id="n-method" class="inp">${options(METHODS, 'qr')}</select></label><label>Miembro<select id="n-member" class="inp">${options(Object.fromEntries(adults().map((m) => [m.id, fullName(m)])), adults()[0].id, '— Ninguno —')}</select></label><label>Aportante externo<input id="n-donor" class="inp" placeholder="Solo si no es miembro"></label><label class="check full"><input type="checkbox" id="n-anon"> Aporte anónimo (no se guarda quién aportó ni se emite comprobante)</label></div><p class="muted small" style="margin-top:8px">Efectivo y transferencia quedan confirmados al guardar; el QR queda pendiente hasta que se pague.</p><div class="actions"><button class="btn primary" data-act="create">Guardar</button></div>`, true),
  create: () => {
    const amount = Math.round(parseFloat(val('n-amount')) * 100) / 100, anon = on('n-anon'), m = anon ? null : member(val('n-member')), donor = anon ? '' : (m ? fullName(m) : val('n-donor'));
    if (!(amount > 0)) return toast('Escribe un monto válido', 'err');
    if (!anon && !donor) return toast('Indica quién aporta o márcalo como anónimo', 'err');
    const c = add(+val('n-fund'), m, TODAY, amount, val('n-method'), { anon, donor, cur: val('n-cur'), branch: val('n-br') });
    FILTER.cs = ''; FILTER.cf = ''; view = 'aportes'; render(); detail(c); toast('Aporte registrado');
  },
});
start();
