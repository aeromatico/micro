/* Oficina de agentes: el equipo real del tenant (orquestador + contratados) en una oficina de pixel art.
   Los encargos se crean en el servidor; esta pantalla solo los anima. La ejecución es una simulación. */
window.WsOfficeInit = function () {
  const WS = window.WS;
  if (!WS || !document.getElementById('office')) return;
  if (window.WsOfficeCleanup) window.WsOfficeCleanup();
  let stopped = false;
  window.WsOfficeCleanup = () => { stopped = true; };

  const W = 400, H = 256, S = 2, SPEED = 42, CORRIDOR = 176, FW = 14, FH = 20;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const root = document.querySelector('.ws-office');
  const cv = document.getElementById('office');
  const ctx = cv.getContext('2d');
  cv.width = W * S; cv.height = H * S;
  const bg = document.createElement('canvas');
  bg.width = W * S; bg.height = H * S;
  const bx = bg.getContext('2d');
  const R = (c, col, x, y, w, h) => { c.fillStyle = col; c.fillRect(x, y, w, h); };
  const css = n => getComputedStyle(root).getPropertyValue(n).trim() || '#d9501a';
  const byId = id => document.getElementById(id);

  /* ---------- fondo estático ---------- */
  function drawBg() {
    bx.setTransform(S, 0, 0, S, 0, 0);
    bx.imageSmoothingEnabled = false;
    R(bx, '#e8dccb', 0, 0, W, 28); R(bx, '#d6c6ad', 0, 24, W, 4); R(bx, '#b9a487', 0, 28, W, 1);
    for (let y = 28; y < 184; y += 8) {
      R(bx, (y / 8) % 2 ? '#d39b66' : '#cf9660', 0, y, W, 8);
      R(bx, '#bd8753', 0, y + 7, W, 1);
      const off = ((y / 8) * 37) % 48;
      for (let x = -off; x < W; x += 48) R(bx, '#bd8753', x, y, 1, 7);
    }
    for (let y = 184; y < H; y += 8) for (let x = 0; x < W; x += 8) R(bx, ((x + y) / 8) % 2 ? '#e6e0d2' : '#dcd5c4', x, y, 8, 8);
    R(bx, '#a88a63', 0, 182, W, 3);
    [20, 100, 180, 260].forEach(x => {
      R(bx, '#8a6a45', x - 1, 5, 42, 19); R(bx, '#9fd3f2', x, 6, 40, 17); R(bx, '#c9e8fa', x, 6, 40, 6);
      R(bx, '#8a6a45', x + 19, 6, 2, 17); R(bx, '#ffffff', x + 3, 8, 6, 1);
    });
    R(bx, '#7d848f', 328, 3, 52, 22); R(bx, '#fbfbfb', 329, 4, 50, 20);
    R(bx, '#d9501a', 334, 15, 5, 7); R(bx, '#2f7fd0', 342, 11, 5, 11); R(bx, '#1f9d63', 350, 13, 5, 9); R(bx, '#8a54c7', 358, 8, 5, 14);
    R(bx, '#3f7d5b', 10, 194, 86, 56); R(bx, '#4b9069', 13, 197, 80, 50);
    R(bx, '#41506e', 270, 196, 124, 58); R(bx, '#4a5b7c', 273, 199, 118, 52);
    bx.font = '600 6px "Pixelify Sans", monospace'; bx.textBaseline = 'alphabetic';
    bx.fillStyle = 'rgba(255,255,255,.55)'; bx.fillText('DESCANSO', 16, 243);
    bx.fillStyle = 'rgba(0,0,0,.28)'; bx.fillText('CAFETERÍA', 142, 243);
    bx.fillStyle = 'rgba(255,255,255,.55)'; bx.fillText('REUNIONES', 278, 249);
  }
  drawBg();
  if (document.fonts && document.fonts.load) document.fonts.load('600 6px "Pixelify Sans"').then(drawBg).catch(() => {});

  /* ---------- lugares y rutas ---------- */
  const seatOf = (c, r) => ({ x: 30 + c * 90 + 20, y: 40 + r * 48 + 6, lane: 30 + c * 90 + 65 });
  const spots = (arr, y) => arr.map(x => ({ x, y, lane: x, by: null }));
  const sofa = spots([38, 62], 218);
  const cafe = spots([158, 182, 206], 194);
  const meet = [
    [282, 226], [306, 208], [330, 208], [354, 208], [382, 226], [330, 252],
    [294, 244], [318, 226], [342, 226], [366, 226], [306, 246], [354, 246]
  ].map(p => ({ x: p[0], y: p[1], lane: p[0], by: null }));
  function route(from, to) {
    const pts = [];
    const push = (x, y) => { const l = pts[pts.length - 1]; if (!l || l[0] !== x || l[1] !== y) pts.push([x, y]); };
    if (from === to) return pts;
    push(from.lane, from.y); push(from.lane, CORRIDOR); push(to.lane, CORRIDOR); push(to.lane, to.y); push(to.x, to.y);
    return pts;
  }

  /* ---------- agentes: el equipo real (hasta 12 escritorios) ---------- */
  const agents = WS.team.slice(0, 12).map((m, i) => {
    const seat = seatOf(i % 4, Math.floor(i / 4));
    return { idx: i, id: m.id, slug: m.slug, sp: m.avatar, name: m.name.split(' ')[0], role: m.role, lead: m.orchestrator, seat, loc: seat, dest: seat, x: seat.x, y: seat.y,
      path: [], mode: 'idle', dir: 'f', walkT: Math.random() * 3, t: Math.random() * 5, timer: 0, pending: null, held: null, cur: null, bubble: '', bubbleT: 0, lastDone: '' };
  });
  const owners = {};
  agents.forEach(a => { owners[(a.idx % 4) + ',' + Math.floor(a.idx / 4)] = a; });
  const lead = agents.find(a => a.lead) || agents[0];
  let selected = null, job = null, wanderT = 6, time = 0;

  const say = (a, text, sec) => { if (a) { a.bubble = text; a.bubbleT = sec || 3; } };
  const atSeat = a => a.loc === a.seat && !a.path.length;

  function begin(a, spot, mode) {
    if (a.held) { a.held.by = null; a.held = null; }
    if (spot.by !== undefined) { spot.by = a; a.held = spot; }
    a.mode = mode; a.dest = spot; a.path = route(a.loc, spot);
    if (!a.path.length) arrive(a);
  }
  function goTo(a, spot, mode) {
    if (a.path.length) { a.pending = { spot, mode }; return; }
    begin(a, spot, mode);
  }
  function arrive(a) {
    a.loc = a.dest;
    if (a.pending) { const p = a.pending; a.pending = null; begin(a, p.spot, p.mode); return; }
    if (a.mode === 'toBreak') { a.mode = 'break'; a.timer = 5 + Math.random() * 4; }
    else if (a.mode === 'toMeet') a.mode = 'meet';
    else if (a.mode === 'toDesk') a.mode = a.cur ? 'work' : 'idle';
  }
  const freeSpot = arr => { const f = arr.filter(s => !s.by); return f.length ? f[Math.floor(Math.random() * f.length)] : null; };

  /* ---------- encargo (viene del servidor) ---------- */
  function startJob(task, elapsed) {
    elapsed = elapsed || 0;
    const steps = {}; task.steps.forEach(s => { steps[s.staff_id] = s; });
    job = { id: task.id, phase: 'meet', meetT: 0, steps, meetSecs: task.meet_seconds, left: agents.filter(a => steps[a.id]).length, points: task.charged_points };
    if (chat.mode === 'sim') byId('send').disabled = true;
    if (elapsed >= task.meet_seconds) {
      // Retomar a mitad de camino: todos ya en su escritorio, con el avance que les toca.
      job.phase = 'work';
      agents.forEach(a => {
        const s = steps[a.id]; if (!s) return;
        const prog = Math.min(.99, Math.max(0, (elapsed - task.meet_seconds) / s.seconds));
        a.cur = { label: s.label, dur: s.seconds, prog, done: s.done };
        a.loc = a.dest = a.seat; a.x = a.seat.x; a.y = a.seat.y; a.path = []; a.mode = 'work';
      });
    } else {
      agents.forEach((a, i) => { a.cur = null; goTo(a, meet[i % meet.length], 'toMeet'); });
      say(lead, 'Equipo, a reunión', 3);
      sys(lead.name + ' convoca al equipo a la sala de reuniones.');
    }
    ui();
  }
  function dispatch() {
    job.phase = 'work';
    agents.forEach(a => {
      const s = job.steps[a.id]; if (!s) return;
      a.cur = { label: s.label, dur: s.seconds * (0.9 + Math.random() * 0.25), prog: 0, done: s.done };
      goTo(a, a.seat, 'toDesk');
    });
    say(lead, 'Manos a la obra', 3);
    sys('Cada agente vuelve a su escritorio con una tarea asignada.');
  }
  function finish(a) {
    a.lastDone = a.cur.done;
    const done = a.cur.done;
    a.cur = null;
    job.left--;
    say(a, '✓ ' + done, 3.5);
    sys(a.name + ': ' + done.toLowerCase() + '.');
    if (a.lead || job.left <= 0) {
      msg('octo', 'Listo, el encargo terminó. Recuerda que por ahora es una simulación: el equipo todavía no entrega archivos ni resultados.');
      job = null;
      if (chat.mode === 'sim') byId('send').disabled = false;
      refresh();
    } else {
      const s = freeSpot(cafe.concat(sofa));
      if (s) goTo(a, s, 'toBreak'); else a.mode = 'idle';
    }
    ui();
  }

  function update(dt) {
    time += dt;
    agents.forEach(a => {
      a.t += dt;
      if (a.bubbleT > 0) a.bubbleT -= dt;
      if (a.path.length) {
        const [tx, ty] = a.path[0];
        const dx = tx - a.x, dy = ty - a.y, d = Math.hypot(dx, dy), step = SPEED * dt;
        if (d <= step) { a.x = tx; a.y = ty; a.path.shift(); if (!a.path.length) arrive(a); }
        else { a.x += dx / d * step; a.y += dy / d * step; a.dir = Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'r' : 'l') : (dy > 0 ? 'f' : 'b'); a.walkT += dt; }
      } else if (a.mode === 'work' && a.cur && job) {
        const cap = a.lead && job.left > 1 ? .99 : 1;
        a.cur.prog = Math.min(cap, a.cur.prog + dt / a.cur.dur);
        if (a.cur.prog >= 1) finish(a);
      } else if (a.mode === 'break') {
        a.timer -= dt;
        if (a.timer <= 0) goTo(a, a.seat, 'toDesk');
      }
    });
    if (job && job.phase === 'meet' && agents.filter(a => job.steps[a.id]).every(a => a.mode === 'meet')) {
      job.meetT += dt;
      if (job.meetT > 1.6) dispatch();
    }
    if (!job && !reduce) {
      wanderT -= dt;
      if (wanderT <= 0) {
        wanderT = 5 + Math.random() * 6;
        const away = agents.filter(a => a.mode === 'break' || a.mode === 'toBreak').length;
        const pool = agents.filter(a => !a.lead && a.mode === 'idle' && atSeat(a));
        const s = freeSpot(cafe.concat(sofa));
        if (away < 2 && pool.length && s) goTo(pool[Math.floor(Math.random() * pool.length)], s, 'toBreak');
      }
    }
  }

  /* ---------- dibujo ---------- */
  const sheet = new Image(); let sheetOk = false;
  sheet.onload = () => { sheetOk = true; };
  sheet.src = WS.sprites;
  function drawAgent(c, a) {
    const x = Math.round(a.x), y = Math.round(a.y), walking = a.path.length > 0;
    R(c, 'rgba(0,0,0,.22)', x - 5, y - 1, 10, 2);
    if (!sheetOk) return;
    const typing = a.mode === 'work' && atSeat(a) && !reduce;
    const view = walking ? (a.dir === 'b' ? 1 : a.dir === 'f' ? 0 : 2) : 0;
    const flip = walking && a.dir === 'l';
    const phase = walking ? Math.floor(a.walkT * 6) % 2 : 0;
    const bob = walking ? (phase ? -1 : 0) : typing && Math.floor(a.t * 4) % 2 ? -1 : 0;
    const sx = view * FW, sy = a.sp * FH, legs = 5, half = FW / 2;
    c.save();
    c.translate(x, y - FH);
    if (flip) { c.scale(-1, 1); }
    const X = -half;
    c.drawImage(sheet, sx, sy, FW, FH - legs, X, bob, FW, FH - legs);
    const ly = FH - legs;
    if (!walking) c.drawImage(sheet, sx, sy + ly, FW, legs, X, ly + bob, FW, legs);
    else if (view === 2) {
      const d = phase ? 1 : -1;
      c.drawImage(sheet, sx, sy + ly, half, legs, X + d, ly + bob, half, legs);
      c.drawImage(sheet, sx + half, sy + ly, half, legs, X + half - d, ly + bob, half, legs);
    } else {
      c.drawImage(sheet, sx, sy + ly, half, legs, X, ly + (phase ? -1 : 0), half, legs);
      c.drawImage(sheet, sx + half, sy + ly, half, legs, X + half, ly + (phase ? 0 : -1), half, legs);
    }
    c.restore();
  }
  function drawDesk(c, dx, dy, owner) {
    R(c, '#7d5230', dx, dy + 10, 40, 8); R(c, '#6a4326', dx + 2, dy + 12, 10, 5); R(c, '#6a4326', dx + 28, dy + 12, 10, 5);
    R(c, '#d9a56b', dx, dy, 40, 10); R(c, '#e8bb82', dx, dy, 40, 1);
    R(c, '#20242c', dx + 1, dy - 6, 15, 11);
    let scr = '#161a22';
    if (owner) {
      if (owner.mode === 'work' && atSeat(owner)) scr = !reduce && Math.floor(time * 5) % 2 ? '#6fd6ff' : '#9bf0b8';
      else scr = '#2c4f86';
    }
    R(c, scr, dx + 2, dy - 5, 13, 8);
    if (owner && owner.mode === 'work' && atSeat(owner)) { R(c, 'rgba(20,30,50,.45)', dx + 4, dy - 3, 8, 1); R(c, 'rgba(20,30,50,.45)', dx + 4, dy - 1, 5, 1); }
    R(c, '#20242c', dx + 7, dy + 5, 3, 2);
    R(c, '#e9e9ec', dx + 22, dy + 5, 11, 3); R(c, '#ffffff', dx + 35, dy + 3, 3, 4);
  }
  const plant = (c, x, y) => {
    R(c, '#2a7340', x - 6, y - 13, 12, 8); R(c, '#3fae5f', x - 4, y - 17, 8, 7); R(c, '#2f8a4a', x - 2, y - 11, 4, 6); R(c, '#a4562f', x - 4, y - 5, 8, 5);
  };
  const furniture = [];
  const addF = (b, fn) => furniture.push({ b, fn });
  for (let c = 0; c < 4; c++) for (let r = 0; r < 3; r++) {
    const dx = 30 + c * 90, dy = 40 + r * 48, owner = owners[c + ',' + r];
    addF(dy + 18, cc => drawDesk(cc, dx, dy, owner));
    if (owner) addF(owner.seat.y - 1, cc => R(cc, '#39414f', owner.seat.x - 7, owner.seat.y - 15, 14, 10));
  }
  [[10, 74], [392, 112], [392, 168], [10, 150], [118, 252], [256, 252]].forEach(p => addF(p[1], cc => plant(cc, p[0], p[1])));
  addF(200, c => R(c, '#d9692f', 22, 200, 56, 16));
  addF(226, c => { R(c, '#b8531f', 20, 214, 60, 12); R(c, '#b8531f', 20, 202, 4, 12); R(c, '#b8531f', 76, 202, 4, 12); R(c, '#e0793f', 49, 214, 1, 12); });
  addF(214, c => {
    R(c, '#8c93a1', 140, 198, 96, 4); R(c, '#5b6270', 140, 202, 96, 12);
    R(c, '#2b2f38', 218, 186, 12, 14); R(c, '#ff5a4f', 222, 189, 3, 2); R(c, '#ffffff', 150, 195, 3, 3); R(c, '#ffffff', 196, 195, 3, 3);
    c.font = '600 5px "Pixelify Sans", monospace'; c.fillStyle = '#e9e9ec'; c.textBaseline = 'alphabetic'; c.fillText('CAFÉ', 172, 211);
  });
  addF(236, c => {
    R(c, '#bdb8af', 292, 212, 80, 24); R(c, '#d9d5ce', 292, 212, 80, 20);
    R(c, '#2b2f38', 304, 218, 10, 6); R(c, '#2b2f38', 330, 220, 10, 6); R(c, '#2b2f38', 352, 218, 10, 6);
    R(c, '#7cc4ff', 305, 219, 8, 4); R(c, '#9bf0b8', 331, 221, 8, 4);
  });

  function labels() {
    ctx.font = '600 6px "Pixelify Sans", monospace'; ctx.textBaseline = 'middle'; ctx.textAlign = 'center';
    const accent = css('--accent');
    agents.forEach(a => {
      const x = Math.round(a.x), top = Math.round(a.y) - 29;
      const w = Math.ceil(ctx.measureText(a.name).width) + 6, sel = selected === a;
      R(ctx, sel ? accent : 'rgba(255,255,255,.88)', x - w / 2, top, w, 8);
      ctx.fillStyle = sel ? '#ffffff' : '#1b2330'; ctx.fillText(a.name, x, top + 4.5);
      if (sel) { ctx.strokeStyle = accent; ctx.lineWidth = 1; ctx.strokeRect(x - 8.5, Math.round(a.y) - 2.5, 17, 5); }
      if (a.bubbleT > 0) {
        const bw = Math.ceil(ctx.measureText(a.bubble).width) + 8, by = top - 12;
        const bxl = Math.max(2, Math.min(W - bw - 2, x - bw / 2));
        R(ctx, '#1b2330', bxl - 1, by - 1, bw + 2, 12); R(ctx, '#ffffff', bxl, by, bw, 10);
        R(ctx, '#1b2330', x - 1, by + 10, 3, 2); R(ctx, '#ffffff', x, by + 10, 1, 1);
        ctx.fillStyle = '#1b2330'; ctx.fillText(a.bubble, bxl + bw / 2, by + 5.5);
      }
    });
    ctx.textAlign = 'start';
  }
  function draw() {
    ctx.setTransform(S, 0, 0, S, 0, 0); ctx.imageSmoothingEnabled = false;
    ctx.drawImage(bg, 0, 0, W, H);
    const list = furniture.concat(agents.map(a => ({ b: a.y, fn: c => drawAgent(c, a) })));
    list.sort((p, q) => p.b - q.b).forEach(it => it.fn(ctx));
    labels();
  }

  /* ---------- interfaz ---------- */
  const MODE = { idle: ['Disponible', ''], work: ['Trabajando', 'work'], toBreak: ['Caminando', 'walk'], toMeet: ['Caminando', 'walk'], toDesk: ['Caminando', 'walk'], meet: ['En reunión', 'meet'], break: ['Descanso', 'break'] };
  const team = byId('team');
  WS.team.forEach(m => {
    const a = agents.find(x => x.id === m.id);
    const li = document.createElement('li');
    li.innerHTML = '<button class="row" type="button" aria-pressed="false"><span class="av"></span><span class="who"><b></b><small></small></span><span class="pill"></span><span class="task"></span><span class="bar"><i></i></span></button>';
    const b = li.firstChild;
    const av = b.querySelector('.av'); av.style.backgroundImage = 'url(' + WS.avatars + ')'; av.style.backgroundSize = (12 * 28) + 'px 28px'; av.style.backgroundPosition = (-m.avatar * 28) + 'px 0'; av.style.imageRendering = 'pixelated';
    b.querySelector('b').textContent = m.name.split(' ')[0]; b.querySelector('small').textContent = m.role + (m.orchestrator ? ' · orquestador' : '');
    if (a) { b.addEventListener('click', () => select(a)); a.row = b; }
    team.appendChild(li);
  });
  function select(a) {
    selected = selected === a ? null : a;
    agents.forEach(x => x.row && x.row.setAttribute('aria-pressed', String(x === selected)));
  }
  let summary = WS.summary;
  function ui() {
    let active = 0;
    agents.forEach(a => {
      if (!a.row) return;
      const m = MODE[a.mode] || MODE.idle;
      const p = a.row.querySelector('.pill'); p.textContent = m[0]; p.className = 'pill ' + m[1];
      let t = a.cur ? a.cur.label : a.mode === 'break' ? 'Tomando un respiro' : a.lastDone ? a.lastDone : 'Sin tarea';
      if (a.cur && a.mode !== 'work') t = 'En camino: ' + a.cur.label.toLowerCase();
      a.row.querySelector('.task').textContent = t;
      a.row.querySelector('.bar i').style.width = Math.round((a.cur ? a.cur.prog : 0) * 100) + '%';
      if (a.mode === 'work') active++;
    });
    byId('pts').textContent = summary.points == null ? 'Demo' : summary.points.toLocaleString('es-BO');
    byId('ptslabel').textContent = summary.points == null ? 'Sin cobro' : 'Puntos';
    byId('active').textContent = active;
    const ph = byId('phase');
    ph.textContent = !job ? 'En espera' : job.phase === 'meet' ? 'Reunión de equipo' : 'Producción en curso';
    ph.className = 'chip' + (job ? ' live' : '');
  }
  const log = byId('log');
  function msg(kind, text) {
    const li = document.createElement('li'); li.className = kind;
    if (kind === 'octo') { const b = document.createElement('b'); b.textContent = lead ? lead.name : 'Equipo'; li.appendChild(b); }
    li.appendChild(document.createTextNode(text));
    log.appendChild(li); log.scrollTop = log.scrollHeight;
  }
  const sys = t => msg('sys', t);

  function renderHistory(tasks) {
    const ul = byId('history'); ul.innerHTML = '';
    if (!tasks.length) { ul.innerHTML = '<li class="none">Aún no has enviado encargos.</li>'; return; }
    tasks.forEach(t => {
      const li = document.createElement('li');
      const b = document.createElement('span'); b.className = 'b'; b.textContent = t.brief; b.title = t.brief;
      const chip = document.createElement('span'); chip.className = 'chip' + (t.status === 'running' ? ' live' : ''); chip.textContent = t.status === 'running' ? 'En curso' : (t.status === 'cancelled' ? (t.refunded ? 'Cancelado · reembolsado' : 'Cancelado') : 'Terminado');
      const s = document.createElement('small'); s.textContent = '#' + t.id + ' · ' + t.steps.length + ' agentes · ' + (t.charged_points ? t.charged_points + ' pts' : 'sin cobro') + ' · simulación';
      li.appendChild(b); li.appendChild(chip); li.appendChild(s);
      if (t.status === 'running') {
        const x = document.createElement('button'); x.type = 'button'; x.className = 'btn-cancel'; x.textContent = 'Cancelar' + (t.charged_points ? ' y reembolsar' : '');
        x.addEventListener('click', function () {
          if (!confirm('¿Cancelar el encargo #' + t.id + '?' + (t.charged_points ? ' Se te devuelven ' + t.charged_points + ' pts.' : ''))) return;
          $.request('onCancelTask', { data: { id: t.id }, success: function (r) { summary = r.summary; renderHistory(r.tasks); ui(); } });
        });
        li.appendChild(x);
      }
      ul.appendChild(li);
    });
  }
  function refresh() {
    $.request('onPoll', { success: function (r) { summary = r.summary; renderHistory(r.tasks); ui(); } });
  }

  const SIM_IDEAS = ['Video de 5 minutos sobre cómo se cultiva el café en los Yungas', 'Calendario de contenido para YouTube, 4 semanas'];
  const LIVE_IDEAS = ['Quiero que mis clientes elijan con botones: precios o hablar con un asesor', 'Necesito un menú desplegable con mis servicios', 'Quiero saber si llego a la zona de un cliente cuando comparte su ubicación'];
  const LEAD_IDEAS = ['Quiero que mi WhatsApp presente mis servicios con un menú y responda cada opción', 'Necesito automatizar la bienvenida de mis clientes por WhatsApp', '¿Qué puede hacer mi equipo por mí ahora mismo?'];
  const chat = { mode: 'sim', agent: null, timer: null, pending: false, busy: new Set() };

  function setIdeas(list) {
    const box = byId('ideas'); box.innerHTML = '';
    list.forEach(t => {
      const b = document.createElement('button'); b.type = 'button'; b.textContent = t.length > 38 ? t.slice(0, 36) + '…' : t; b.title = t;
      b.addEventListener('click', () => { byId('prompt').value = t; est(); byId('prompt').focus(); });
      box.appendChild(b);
    });
  }
  const est = () => {
    if (chat.mode === 'live') { byId('est').textContent = chat.pending ? chat.agent.name + ' está trabajando…' : 'Responde en unos segundos; puede hacerte preguntas.'; return; }
    const n = Math.round(WS.base_points * (1 + byId('prompt').value.length / 400));
    byId('est').textContent = summary.charging ? 'Estimado: ~' + n + ' pts' : 'Modo demostración: sin costo (~' + n + ' pts de referencia)';
  };
  byId('prompt').addEventListener('input', est);

  /* ---------- chat real con un agente que trabaja de verdad ---------- */
  const logLive = byId('log-live');
  const bold = t => esc(t).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline">$1</a>');
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }
  function setBusy(slug, on, label) {
    const a = agents.find(x => x.slug === slug); if (!a) return;
    if (on) { a.mode = 'work'; a.cur = { label: label || 'Trabajando contigo', dur: 1e9, prog: .4, done: 'Listo' }; goTo(a, a.seat, 'toDesk'); say(a, label ? 'Manos a la obra' : 'Pensando…', 2.5); }
    else if (a.cur && a.cur.dur === 1e9) { a.cur = null; a.mode = 'idle'; a.lastDone = 'Respondió'; say(a, '✓ Listo', 2.5); }
    ui();
  }
  function renderLive(data) {
    logLive.innerHTML = '';
    if (!data.messages.length) logLive.innerHTML = '<li><b class="who">' + esc(data.agent.name) + '</b>' + (chat.agent && chat.agent.orchestrator
      ? 'Hola, soy ' + esc(data.agent.name) + ', la orquestadora. Cuéntame qué quieres lograr: veo quién de tu equipo puede hacerlo de verdad, te propongo un plan y, cuando lo acordemos, reparto el trabajo.'
      : 'Hola, soy ' + esc(data.agent.name) + '. Cuéntame qué quieres automatizar y lo diseñamos juntos; cuando estemos de acuerdo, lo dejo como borrador para que lo revises.') + '</li>';
    data.messages.forEach(m => {
      const li = document.createElement('li');
      if (m.role === 'user') { li.className = 'user'; li.textContent = m.content; }
      else if (m.status === 'pending') { li.className = 'wait'; li.textContent = data.agent.name + ' está trabajando…'; }
      else if (m.status === 'error') { li.className = 'err'; li.textContent = m.error || 'No se pudo completar la respuesta.'; }
      else {
        let html = '<b class="who">' + esc(data.agent.name) + '</b>' + bold(m.content);
        (m.workflows || []).forEach(w => { if (w.url) html += '<br><a class="made" href="' + esc(w.url) + '" target="_blank" rel="noopener">Abrir «' + esc(w.name) + '» (#' + w.id + ') en el editor</a>'; });
        if (m.tools && m.tools.length) html += '<small class="tools">Herramientas usadas: ' + m.tools.map(esc).join(', ') + '</small>';
        li.innerHTML = html;
      }
      logLive.appendChild(li);
    });
    logLive.scrollTop = logLive.scrollHeight;
    chat.pending = data.pending;
    byId('send').disabled = data.pending;
    // Quién está trabajando ahora: el agente del chat y los que él reparte (Katy → Link).
    const want = new Set();
    if (data.pending) want.add(chat.agent.slug);
    (data.working || []).forEach(sl => want.add(sl));
    chat.busy.forEach(sl => { if (!want.has(sl)) setBusy(sl, false); });
    want.forEach(sl => { if (!chat.busy.has(sl)) setBusy(sl, true, sl === chat.agent.slug ? null : 'Trabajando en el encargo'); });
    chat.busy = want;
    est();
    clearTimeout(chat.timer);
    if (data.pending) chat.timer = setTimeout(pollLive, 1800);
  }
  function pollLive() {
    if (chat.mode !== 'live') return;
    $.request('onAgentHistory', { data: { slug: chat.agent.slug }, success: renderLive, error: function () { chat.timer = setTimeout(pollLive, 4000); } });
  }
  function openChat(tab) {
    clearTimeout(chat.timer);
    chat.busy.forEach(sl => setBusy(sl, false)); chat.busy = new Set();
    chat.mode = tab.live ? 'live' : 'sim'; chat.agent = tab.live ? tab : null; chat.pending = false;
    document.querySelectorAll('#chat-tabs .chat-tab').forEach(b => b.setAttribute('aria-selected', String(b.dataset.slug === tab.slug)));
    log.hidden = tab.live; logLive.hidden = !tab.live;
    byId('prompt').placeholder = tab.live ? 'Cuéntale a ' + tab.name + ' qué quieres lograr' : 'Cuéntale qué quieres lograr';
    byId('send').textContent = tab.live ? 'Enviar mensaje' : 'Enviar encargo';
    byId('send').disabled = !tab.live && !!job;
    setIdeas(tab.live ? (tab.orchestrator ? LEAD_IDEAS : LIVE_IDEAS) : SIM_IDEAS); est();
    if (tab.live) pollLive();
  }
  const tabs = byId('chat-tabs');
  // Si la orquestadora trabaja de verdad, su chat real reemplaza al simulado; si no, queda el simulado primero.
  const liveMembers = WS.team.filter(m => m.live).sort((x, y) => (y.orchestrator ? 1 : 0) - (x.orchestrator ? 1 : 0));
  const leadLive = liveMembers.some(m => m.orchestrator);
  const tabList = (leadLive ? [] : [{ slug: '_sim', name: lead ? lead.name : 'Equipo', live: false, label: (lead ? lead.name : 'Equipo'), note: 'simulado' }])
    .concat(liveMembers.map(m => ({ slug: m.slug, name: m.name.split(' ')[0], live: true, orchestrator: m.orchestrator, label: m.name.split(' ')[0], note: m.orchestrator ? 'orquestadora' : 'real' })));
  if (leadLive) byId('history').closest('.card').hidden = true;
  tabList.forEach(t => {
    const b = document.createElement('button'); b.type = 'button'; b.className = 'chat-tab'; b.dataset.slug = t.slug; b.setAttribute('role', 'tab');
    b.innerHTML = esc(t.label) + '<small>' + t.note + '</small>';
    b.addEventListener('click', () => openChat(t)); tabs.appendChild(b);
  });
  tabs.hidden = tabList.length < 2;

  byId('form').addEventListener('submit', e => {
    e.preventDefault();
    const brief = byId('prompt').value.trim();
    if (!brief) return;

    if (chat.mode === 'live') {
      if (chat.pending) return;
      byId('send').disabled = true;
      $.request('onAgentSend', {
        data: { slug: chat.agent.slug, message: brief },
        success: function (r) { byId('prompt').value = ''; renderLive(r); },
        error: function (xhr) { byId('send').disabled = false; alert((xhr && xhr.responseJSON && xhr.responseJSON.result) || (xhr && xhr.responseText) || 'No se pudo enviar el mensaje.'); },
      });
      return;
    }

    if (job) return;
    byId('send').disabled = true;
    $.request('onSendTask', {
      data: { brief },
      success: function (r) {
        byId('prompt').value = ''; est();
        msg('user', brief);
        msg('octo', 'Entendido. Reúno al equipo y reparto el trabajo.');
        summary = r.summary; renderHistory(r.tasks);
        startJob(r.task, 0);
      },
      error: function (xhr) { byId('send').disabled = false; alert((xhr && xhr.responseJSON && xhr.responseJSON.result) || (xhr && xhr.responseText) || 'No se pudo enviar el encargo.'); },
    });
  });
  cv.addEventListener('click', e => {
    const r = cv.getBoundingClientRect();
    const lx = (e.clientX - r.left) / r.width * W, ly = (e.clientY - r.top) / r.height * H;
    let best = null, bd = 1e9;
    agents.forEach(a => { const d = Math.hypot(lx - a.x, ly - (a.y - 10)); if (d < 14 && d < bd) { best = a; bd = d; } });
    if (best) select(best);
  });

  /* ---------- arranque ---------- */
  if (tabs.firstChild) openChat(tabList[0]); else setIdeas(SIM_IDEAS);
  byId('subtitle').textContent = 'Tu equipo de agentes de IA trabaja a la vista. Los agentes marcados «real» (como Link) trabajan de verdad desde su chat; los encargos al orquestador se registran, pero su ejecución todavía es una simulación.';
  msg('octo', 'Hola, soy ' + (lead ? lead.name : 'tu orquestador') + '. Dime qué quieres lograr y reparto el trabajo entre tu equipo.');
  renderHistory(WS.tasks); est();
  if (summary.running_task) { msg('user', summary.running_task.brief); msg('octo', 'Sigo coordinando este encargo.'); startJob(summary.running_task, summary.running_task.elapsed_seconds); }

  let last = performance.now(), uiT = 0;
  function frame(now) {
    const dt = Math.min(.05, (now - last) / 1000); last = now;
    update(dt); draw();
    uiT += dt; if (uiT > .2) { uiT = 0; ui(); }
    if (!stopped) requestAnimationFrame(frame);
  }
  ui(); requestAnimationFrame(frame);
};
