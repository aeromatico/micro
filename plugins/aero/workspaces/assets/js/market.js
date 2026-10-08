/* Mercado de agentes: pantalla del tenant. Los datos llegan en window.WS y las acciones (contratar, crear skill) van al servidor. */
window.WsMarketInit = function () {
  const WS = window.WS;
  if (!WS || !document.getElementById('view')) return;
  // Cada arranque limpia el anterior (el panel puede reutilizar la página al navegar).
  if (window.WsMarketCleanup) window.WsMarketCleanup();
  const offs = [];
  const on = (type, fn) => { document.addEventListener(type, fn); offs.push(() => document.removeEventListener(type, fn)); };
  window.WsMarketCleanup = () => offs.forEach(f => f());

  const AXES = [['creatividad', 'Creatividad'], ['viralidad', 'Viralidad'], ['ejecucion', 'Ejecución'], ['narrativa', 'Narrativa'], ['estetica', 'Estética'], ['eficiencia', 'Eficiencia']];
  const CATS = { all: 'Todos', video: 'Video', imagen: 'Imagen', contenido: 'Contenido', ppt: 'Presentaciones', info: 'Investigación', automatizacion: 'Automatización', ecommerce: 'eCommerce', web: 'Desarrollo Web', integraciones: 'Integraciones y APIs' };
  const byId = id => document.getElementById(id);
  const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const fmt = n => n >= 1000 ? (n / 1000).toFixed(1).replace('.0', '') + 'K' : String(n);

  const st = { tab: 'market', cat: 'all', rar: 'all', q: '', sort: 'hires', profile: null, sktab: 'official', skq: '' };
  const state = { agents: WS.agents, team: WS.team, skills: WS.skills, summary: WS.summary };
  const agentBySlug = slug => state.agents.concat(state.team).find(a => a.slug === slug);

  /* ---------- gráficos (pixel art) ---------- */
  function rng(seed) { let h = 2166136261; for (const ch of String(seed)) { h ^= ch.charCodeAt(0); h = Math.imul(h, 16777619); } return () => { h ^= h << 13; h ^= h >>> 17; h ^= h << 5; return ((h >>> 0) % 10000) / 10000; }; }
  const pick = (r, a) => a[Math.floor(r() * a.length)];
  const cache = {};
  function scene(seed) {
    const key = 's' + seed; if (cache[key]) return cache[key];
    const r = rng(seed), c = document.createElement('canvas'); c.width = 64; c.height = 40;
    const x = c.getContext('2d'), P = (col, a, b, w, h) => { x.fillStyle = col; x.fillRect(a, b, w, h); };
    const pal = pick(r, [['#ffb36b', '#ff7a5c', '#5b3f8c', '#2b1d4d'], ['#9fd3f2', '#6fb1e0', '#3f7d5b', '#2b5440'], ['#f5e1a4', '#e9a34f', '#8c4a2b', '#4a2518'], ['#c9b8f5', '#8d78d6', '#2f3f7a', '#161c3d']]);
    for (let i = 0; i < 4; i++) P(pal[i === 0 ? 0 : i === 1 ? 1 : 0], 0, i * 6, 64, 6);
    P(pal[1], 0, 24, 64, 4);
    P('#fff6c9', 8 + Math.floor(r() * 40), 6 + Math.floor(r() * 6), 6, 6);
    let h = 22; for (let i = 0; i < 64; i += 4) { h = Math.max(14, Math.min(30, h + Math.floor(r() * 9) - 4)); P(pal[2], i, h, 4, 40 - h); }
    for (let i = 0; i < 8; i++) P(pal[3], Math.floor(r() * 60), 30 + Math.floor(r() * 8), 3, 8);
    return (cache[key] = c.toDataURL());
  }
  function avatar(idx, size) {
    return '<span class="avimg" style="display:block;width:' + size + 'px;height:' + size + 'px;image-rendering:pixelated;background:url(' + WS.avatars + ') no-repeat;background-size:' + (12 * size) + 'px ' + size + 'px;background-position:' + (-idx * size) + 'px 0"></span>';
  }
  function radar(caps) {
    const vals = AXES.map(a => Number((caps || {})[a[0]] || 0));
    const cx = 160, cy = 120, R = 74, n = 6;
    const pt = (i, k) => { const a = -Math.PI / 2 + i * 2 * Math.PI / n; return [cx + Math.cos(a) * R * k, cy + Math.sin(a) * R * k]; };
    let g = '';
    [.33, .66, 1].forEach(k => { g += '<polygon points="' + [0, 1, 2, 3, 4, 5].map(i => pt(i, k).join(',')).join(' ') + '" fill="none" stroke="var(--line)"/>'; });
    for (let i = 0; i < n; i++) g += '<line x1="' + cx + '" y1="' + cy + '" x2="' + pt(i, 1)[0] + '" y2="' + pt(i, 1)[1] + '" stroke="var(--line)"/>';
    g += '<polygon points="' + vals.map((v, i) => pt(i, v / 100).join(',')).join(' ') + '" fill="var(--accent)" fill-opacity=".22" stroke="var(--accent)" stroke-width="2"/>';
    vals.forEach((v, i) => { const p = pt(i, v / 100); g += '<rect x="' + (p[0] - 3) + '" y="' + (p[1] - 3) + '" width="6" height="6" fill="var(--accent)"/>'; });
    AXES.forEach((a, i) => { const p = pt(i, 1.3), anchor = i === 0 || i === 3 ? 'middle' : (i < 3 ? 'start' : 'end'); const dx = i === 0 || i === 3 ? 0 : (i < 3 ? -6 : 6); g += '<text x="' + (p[0] + dx) + '" y="' + (p[1] - 4) + '" text-anchor="' + anchor + '">' + a[1] + '</text><text class="v" x="' + (p[0] + dx) + '" y="' + (p[1] + 11) + '" text-anchor="' + anchor + '">' + vals[i] + '</text>'; });
    return '<svg class="radar" viewBox="0 0 320 250" role="img" aria-label="Capacidades">' + g + '</svg>';
  }

  /* ---------- vistas ---------- */
  const TABS = [['market', 'Mercado'], ['mine', 'Mi equipo'], ['skills', 'Skills']];
  function pointsText() {
    const p = state.summary.points;
    return p == null ? 'Modo demostración' : p.toLocaleString('es-BO') + ' pts';
  }
  function renderTabs() {
    byId('tabs').innerHTML = TABS.map(t => '<button class="tab" role="tab" data-tab="' + t[0] + '" aria-selected="' + (st.tab === t[0] && !st.profile) + '">' + t[1] + (t[0] === 'mine' ? ' (' + state.team.length + ')' : '') + '</button>').join('');
    byId('pts').textContent = pointsText();
    byId('pts').title = state.summary.charging ? 'Puntos disponibles' : 'El cobro de puntos está apagado: contratar y enviar encargos no cuesta.';
  }
  const ribbon = q => '<span class="ribbon">' + (q || '').toUpperCase() + '</span>';
  const costText = a => a.task_fee + ' <small>pts / encargo</small>' + (a.hire_fee ? ' · <small>contratar ' + a.hire_fee + ' pts</small>' : '');
  function hireBtn(a, full) {
    if (a.orchestrator) return '<button class="btn hired" disabled' + (full ? ' style="width:100%"' : '') + '>Siempre en tu equipo</button>';
    if (a.hired) return '<button class="btn hired" disabled' + (full ? ' style="width:100%"' : '') + '>En tu equipo</button>';
    return '<button class="btn" data-hire="' + esc(a.slug) + '"' + (full ? ' style="width:100%"' : '') + '>Contratar</button>';
  }
  function card(a) {
    return '<article class="card ' + esc(a.rarity) + '" tabindex="0" role="button" data-open="' + esc(a.slug) + '" aria-label="Ver perfil de ' + esc(a.name) + '">' +
      '<span class="shine"></span>' + ribbon(a.orchestrator ? 'jefe' : a.rarity) +
      '<div class="who"><div class="av">' + avatar(a.avatar, 72) + '</div><div><div class="nm">' + esc(a.name) + '</div><div class="rl">[ ' + esc(a.role) + ' ]</div></div></div>' +
      '<p class="bio">' + esc(a.bio) + '</p>' +
      '<div class="nums"><div><b>' + fmt(a.contracts) + '</b><span>Contratos</span></div><div><b>' + a.skills.length + '</b><span>Skills</span></div><div><b>' + a.task_fee + '</b><span>Pts/encargo</span></div></div>' +
      '<div class="work"><img class="px" src="' + scene(a.slug) + '" alt="" style="image-rendering:pixelated"></div>' +
      '<div class="foot"><span class="cost">' + costText(a) + '</span>' + hireBtn(a) + '</div></article>';
  }
  function market(list, withFilters) {
    const q = st.q.toLowerCase();
    const l = list.filter(s => (st.cat === 'all' || s.category === st.cat) && (st.rar === 'all' || s.rarity === st.rar) && (!q || (s.name + s.role + s.bio + s.tags.join(' ')).toLowerCase().includes(q)));
    const ord = { hires: (a, b) => b.contracts - a.contracts, cost: (a, b) => a.task_fee - b.task_fee, name: (a, b) => a.name.localeCompare(b.name) };
    l.sort(ord[st.sort]);
    const head = withFilters ? '<div class="tools"><input class="search" id="q" type="search" placeholder="Buscar por nombre, rol o habilidad" value="' + esc(st.q) + '" aria-label="Buscar">' +
      '<select id="sort" aria-label="Ordenar"><option value="hires"' + (st.sort === 'hires' ? ' selected' : '') + '>Más contratados</option><option value="cost"' + (st.sort === 'cost' ? ' selected' : '') + '>Menor costo</option><option value="name"' + (st.sort === 'name' ? ' selected' : '') + '>Nombre</option></select></div>' +
      '<div class="filters">' + Object.entries(CATS).map(c => '<button class="chip-f" data-cat="' + c[0] + '" aria-pressed="' + (st.cat === c[0]) + '">' + c[1] + '</button>').join('') +
      '<span style="flex:1"></span>' + ['all', 'r', 'sr', 'ssr'].map(r => '<button class="chip-f" data-rar="' + r + '" aria-pressed="' + (st.rar === r) + '">' + (r === 'all' ? 'Toda rareza' : r.toUpperCase()) + '</button>').join('') + '</div>' : '';
    return '<div style="display:grid;gap:14px">' + head + (l.length ? '<div class="grid">' + l.map(card).join('') + '</div>' : '<p class="empty">Ningún agente coincide. Prueba con otro filtro.</p>') + '</div>';
  }
  function profile(a) {
    const skills = a.skills.map(k => '<div class="sk"><span class="si" style="background:' + k.color + '22;color:' + k.color + '">' + esc(k.name[0]) + '</span><div><b>' + esc(k.name) + '</b><p>' + esc(k.description) + '</p></div></div>').join('') || '<p class="empty">Sin skills asignadas.</p>';
    return '<div style="display:grid;gap:14px"><button class="btn alt back" id="back">← Volver</button><div class="profile">' +
      '<aside class="pl ' + esc(a.rarity) + '">' + ribbon(a.orchestrator ? 'jefe' : a.rarity) + '<div class="av">' + avatar(a.avatar, 128) + '</div><div><div class="nm">' + esc(a.name) + '</div><div class="rl">[ ' + esc(a.role) + ' ]</div></div>' +
      '<p class="bio">' + esc(a.bio) + '</p><div class="tags">' + a.tags.map(t => '<span class="tag">' + esc(t) + '</span>').join('') + '</div>' +
      '<div class="cost">' + costText(a) + '</div>' + hireBtn(a, true) + '</aside>' +
      '<div class="pr"><section class="box"><h3>Rendimiento</h3><div class="perf"><div><b>' + fmt(a.contracts) + '</b><span>Contratos</span></div><div><b>' + a.skills.length + '</b><span>Skills</span></div><div><b>' + a.task_fee + '</b><span>Pts por encargo</span></div></div></section>' +
      '<div class="two"><section class="box"><h3>Capacidades</h3>' + radar(a.capabilities) + '</section><section class="box"><h3>Skills</h3><div class="skl">' + skills + '</div></section></div>' +
      (a.guide.length ? '<section class="box"><h3>Guía de uso</h3><ul class="guide">' + a.guide.map(g => '<li>' + esc(g) + '</li>').join('') + '</ul></section>' : '') + '</div></div></div>';
  }
  function skills() {
    const q = st.skq.toLowerCase();
    const l = state.skills.filter(k => k.kind === st.sktab && (!q || (k.name + k.description).toLowerCase().includes(q)));
    const names = { official: 'Oficiales', hub: 'Skill Hub', personal: 'Personales' };
    const form = st.sktab === 'personal' ? '<form class="form" id="skform"><label>Nombre<input id="skn" required maxlength="40" placeholder="Ej.: Tono de la marca"></label><label>Cuándo usarla<textarea id="skd" required rows="2" maxlength="200" placeholder="Úsala cuando…"></textarea></label><div><button class="btn" type="submit">Crear skill</button></div></form>' : '';
    return '<div style="display:grid;gap:14px"><div class="tools"><div class="stabs">' + Object.entries(names).map(n => '<button class="chip-f" data-sk="' + n[0] + '" aria-pressed="' + (st.sktab === n[0]) + '">' + n[1] + '</button>').join('') + '</div><input class="search" id="skq" type="search" placeholder="Buscar skills" value="' + esc(st.skq) + '" aria-label="Buscar skills" style="margin-left:auto"></div>' + form +
      (l.length ? '<div class="skgrid">' + l.map(k => '<article class="skc"><span class="si" style="background:' + k.color + '22;color:' + k.color + '">' + esc(k.name[0]) + '</span><div><b>' + esc(k.name) + '</b><p>' + esc(k.description) + '</p><small>' + (k.users || 0) + ' agentes la usan</small></div></article>').join('') + '</div>' : '<p class="empty">Aún no hay skills aquí.</p>') + '</div>';
  }
  function render() {
    renderTabs();
    const v = byId('view');
    if (st.profile) { const a = agentBySlug(st.profile); v.innerHTML = a ? profile(a) : ''; }
    else if (st.tab === 'market') v.innerHTML = market(state.agents, true);
    else if (st.tab === 'mine') v.innerHTML = '<div style="display:grid;gap:14px"><p class="empty" style="padding:0;text-align:left">El orquestador siempre está en tu equipo: recibe tus encargos y los reparte entre todos.</p><div class="grid">' + state.team.map(card).join('') + '</div></div>';
    else v.innerHTML = skills();
  }
  let tt;
  function toast(t) { const e = byId('toast'); e.textContent = t; e.hidden = false; clearTimeout(tt); tt = setTimeout(() => { e.hidden = true; }, 2600); }

  function hire(slug) {
    const a = agentBySlug(slug);
    if (!a || a.hired) return;
    if (a.hire_fee > 0 && state.summary.charging && !confirm('Contratar a ' + a.name + ' cuesta ' + a.hire_fee + ' pts. ¿Continuar?')) return;
    $.request('onHire', {
      data: { slug },
      success: function (r) {
        state.team = r.team; state.summary = r.summary;
        state.agents.forEach(x => { if (x.slug === slug) { x.hired = true; x.contracts += 1; } });
        toast(a.name + ' se unió a tu equipo.'); render();
      },
    });
  }

  /* ---------- eventos ---------- */
  on('click', e => {
    const t = e.target.closest('[data-tab],[data-hire],[data-open],[data-cat],[data-rar],[data-sk],#back'); if (!t || !byId('view') || !byId('view').closest('.ws-market')) return;
    if (t.dataset.tab) { st.tab = t.dataset.tab; st.profile = null; render(); }
    else if (t.dataset.hire) { e.stopPropagation(); hire(t.dataset.hire); }
    else if (t.dataset.open) { st.profile = t.dataset.open; render(); scrollTo(0, 0); }
    else if (t.dataset.cat) { st.cat = t.dataset.cat; render(); }
    else if (t.dataset.rar) { st.rar = t.dataset.rar; render(); }
    else if (t.dataset.sk) { st.sktab = t.dataset.sk; render(); }
    else if (t.id === 'back') { st.profile = null; render(); }
  });
  on('keydown', e => { const c = e.target.closest && e.target.closest('.ws-market .card'); if (c && e.target === c && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); st.profile = c.dataset.open; render(); } });
  on('input', e => {
    if (e.target.id === 'q') { st.q = e.target.value; const p = e.target.selectionStart; render(); const n = byId('q'); n.focus(); n.setSelectionRange(p, p); }
    else if (e.target.id === 'skq') { st.skq = e.target.value; const p = e.target.selectionStart; render(); const n = byId('skq'); n.focus(); n.setSelectionRange(p, p); }
  });
  on('change', e => { if (e.target.id === 'sort') { st.sort = e.target.value; render(); } });
  on('submit', e => {
    if (e.target.id !== 'skform') return;
    e.preventDefault();
    const name = byId('skn').value.trim(), description = byId('skd').value.trim(); if (!name || !description) return;
    $.request('onCreateSkill', { data: { name, description }, success: function (r) { state.skills.push(r.skill); toast('Skill creada.'); render(); } });
  });
  on('pointermove', e => {
    const c = e.target.closest && e.target.closest('.ws-market .card'); if (!c) return;
    const r = c.getBoundingClientRect();
    c.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100) + '%'); c.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100) + '%');
  });

  render();
};
