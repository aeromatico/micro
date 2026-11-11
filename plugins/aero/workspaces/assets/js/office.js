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
    const px = (x, y, col) => R(bx, col, x, y, 1, 1);
    /* pared: papel pintado con friso, zócalo y sombra sobre el piso */
    R(bx, '#f1e7d6', 0, 0, W, 28); R(bx, '#e6d9c2', 0, 17, W, 7); R(bx, '#f7efe1', 0, 17, W, 1); R(bx, '#d3c3a6', 0, 23, W, 1);
    for (let x = 0; x < W; x += 20) R(bx, 'rgba(160,130,90,.10)', x, 0, 1, 17);
    R(bx, '#bba283', 0, 24, W, 4); R(bx, '#d2bd9a', 0, 24, W, 1); R(bx, '#a38a68', 0, 28, W, 1);
    /* piso de madera: tablas con vetas y tonos que varían */
    for (let y = 29; y < 184; y += 8) {
      const row = (y - 28) / 8;
      R(bx, row % 2 ? '#d6a06b' : '#d19b64', 0, y, W, 8);
      R(bx, '#b88550', 0, y + 7, W, 1);
      const off = (row * 37) % 48;
      for (let x = -off; x < W; x += 48) {
        R(bx, '#b88550', x, y, 1, 7);
        if ((x + row * 13) % 3 === 0) R(bx, 'rgba(255,255,255,.07)', x + 1, y, 47, 7);
        px(x + 9 + (row * 5) % 17, y + 3, 'rgba(120,70,30,.28)'); px(x + 10 + (row * 5) % 17, y + 3, 'rgba(120,70,30,.28)'); px(x + 30 + (row * 3) % 11, y + 5, 'rgba(120,70,30,.22)');
      }
    }
    R(bx, 'rgba(60,35,10,.18)', 0, 29, W, 2); R(bx, 'rgba(60,35,10,.10)', 0, 31, W, 2); R(bx, 'rgba(60,35,10,.05)', 0, 33, W, 3);
    /* zona de descanso/cafetería/reuniones: baldosa */
    for (let y = 184; y < H; y += 8) for (let x = 0; x < W; x += 8) {
      R(bx, ((x + y) / 8) % 2 ? '#e9e3d5' : '#dfd8c6', x, y, 8, 8);
      R(bx, 'rgba(255,255,255,.35)', x, y, 8, 1); R(bx, 'rgba(0,0,0,.05)', x, y + 7, 8, 1);
    }
    R(bx, '#9c7f58', 0, 181, W, 1); R(bx, '#b8996d', 0, 182, W, 3); R(bx, '#d4b98c', 0, 182, W, 1); R(bx, 'rgba(0,0,0,.10)', 0, 185, W, 2);
    /* ventanas: marco, cielo, nubes, parteluz y repisa */
    [20, 100, 180, 260].forEach((x, i) => {
      R(bx, '#7d6140', x - 2, 4, 44, 21); R(bx, '#a98a5f', x - 1, 5, 42, 19);
      R(bx, '#8fc9ee', x, 6, 40, 17); R(bx, '#b4defa', x, 6, 40, 7); R(bx, '#d3eefc', x, 6, 40, 3);
      R(bx, '#ffffff', x + 4 + i * 3, 9, 8, 2); R(bx, '#ffffff', x + 6 + i * 3, 8, 5, 1); R(bx, '#ffffff', x + 26 - i * 2, 12, 9, 2); R(bx, '#eaf6ff', x + 28 - i * 2, 11, 5, 1);
      R(bx, '#a98a5f', x + 19, 6, 2, 17); R(bx, '#a98a5f', x, 14, 40, 1);
      R(bx, '#d9c19a', x - 3, 24, 46, 2); R(bx, '#8a6e48', x - 3, 26, 46, 1);
      /* haz de luz sobre el piso */
      for (let y = 30; y < 130; y += 2) R(bx, 'rgba(255,244,205,' + (0.12 - (y - 30) * 0.0009).toFixed(3) + ')', x + 2 + (y - 30) * 0.45, y, 36, 2);
    });
    /* cuadros, reloj y pizarra de métricas */
    const frame = (x, y, w, h, c1, c2) => { R(bx, '#6e5236', x, y, w, h); R(bx, '#f6efe2', x + 1, y + 1, w - 2, h - 2); R(bx, c1, x + 2, y + 2, w - 4, h - 4); R(bx, c2, x + 2, y + h - 6, w - 4, 3); };
    frame(68, 6, 24, 15, '#9cc9d8', '#4f8f6b'); R(bx, '#f3c75a', 82, 9, 4, 4);
    frame(228, 6, 24, 15, '#e8b9a0', '#a9644a'); R(bx, '#fbe7c4', 236, 9, 6, 6);
    R(bx, '#6e5236', 154, 5, 12, 14); R(bx, '#fffdf7', 155, 6, 10, 12); R(bx, '#6e5236', 159, 10, 2, 1);
    R(bx, '#6a6f7a', 322, 3, 62, 22); R(bx, '#fdfdfd', 323, 4, 60, 20); R(bx, '#e9edf2', 323, 22, 60, 2);
    R(bx, '#d9501a', 330, 14, 5, 8); R(bx, '#2f7fd0', 338, 10, 5, 12); R(bx, '#1f9d63', 346, 12, 5, 10); R(bx, '#8a54c7', 354, 7, 5, 15);
    R(bx, '#f6d54a', 366, 6, 6, 6); R(bx, '#f08fb0', 374, 8, 6, 6); R(bx, '#d9d9de', 364, 17, 17, 1); R(bx, '#d9d9de', 364, 20, 12, 1);
    /* alfombras de las zonas */
    const rug = (x, y, w, h, base, mid, edge, dot) => {
      R(bx, 'rgba(0,0,0,.14)', x + 1, y + h, w, 2); R(bx, edge, x, y, w, h); R(bx, base, x + 2, y + 2, w - 4, h - 4); R(bx, mid, x + 5, y + 5, w - 10, h - 10);
      for (let yy = y + 7; yy < y + h - 7; yy += 4) for (let xx = x + 7 + ((yy - y) % 8 ? 2 : 0); xx < x + w - 7; xx += 4) px(xx, yy, dot);
      for (let xx = x; xx < x + w; xx += 3) { px(xx, y - 1, edge); px(xx, y + h, edge); }
    };
    rug(10, 194, 86, 56, '#3f7d5b', '#4b9069', '#2f5f45', 'rgba(255,255,255,.12)');
    rug(270, 196, 124, 58, '#41506e', '#4a5b7c', '#323f58', 'rgba(255,255,255,.10)');
    /* rótulos de zona (placas) */
    bx.font = '600 6px "Pixelify Sans", monospace'; bx.textBaseline = 'alphabetic';
    const sign = (x, y, txt) => { const w = Math.ceil(bx.measureText(txt).width) + 8; R(bx, 'rgba(0,0,0,.18)', x + 1, y + 1, w, 9); R(bx, '#2d2a26', x, y, w, 9); R(bx, '#4b463f', x, y, w, 1); bx.fillStyle = '#f4ead6'; bx.fillText(txt, x + 4, y + 7); };
    sign(14, 186, 'DESCANSO'); sign(139, 186, 'CAFETERÍA'); sign(274, 186, 'REUNIONES');
    /* luz ambiente: viñeta suave */
    const g = bx.createRadialGradient(W / 2, H / 2, 70, W / 2, H / 2, 260);
    g.addColorStop(0, 'rgba(255,240,210,0)'); g.addColorStop(1, 'rgba(40,20,0,.20)');
    bx.fillStyle = g; bx.fillRect(0, 0, W, H);
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
    const working = owner && owner.mode === 'work' && atSeat(owner);
    R(c, 'rgba(0,0,0,.16)', dx + 2, dy + 18, 40, 3);
    /* patas, cajonera y frente */
    R(c, '#6a4326', dx + 1, dy + 10, 3, 8); R(c, '#6a4326', dx + 36, dy + 10, 3, 8);
    R(c, '#86593a', dx, dy + 10, 40, 5); R(c, '#9a6a45', dx, dy + 10, 40, 1); R(c, '#7a4f31', dx + 26, dy + 11, 11, 7); R(c, '#e8c08a', dx + 30, dy + 14, 3, 1);
    /* tablero */
    R(c, '#c98f55', dx, dy + 8, 40, 2); R(c, '#e4b57a', dx, dy, 40, 9); R(c, '#f0c890', dx, dy, 40, 1); R(c, 'rgba(120,70,30,.12)', dx + 3, dy + 4, 16, 1); R(c, 'rgba(120,70,30,.12)', dx + 24, dy + 6, 12, 1);
    /* monitor */
    R(c, '#343a46', dx + 6, dy + 4, 5, 2); R(c, '#2a2f39', dx + 4, dy + 5, 9, 2);
    R(c, '#1b1f27', dx + 1, dy - 7, 16, 12);
    let scr = '#10141b';
    if (owner) scr = working ? (!reduce && Math.floor(time * 5) % 2 ? '#6fd6ff' : '#9bf0b8') : '#2f5390';
    R(c, scr, dx + 2, dy - 6, 14, 9);
    if (owner && !working) { R(c, 'rgba(255,255,255,.18)', dx + 3, dy - 5, 5, 1); R(c, 'rgba(255,255,255,.10)', dx + 3, dy - 3, 9, 1); }
    if (!owner) R(c, 'rgba(255,255,255,.07)', dx + 3, dy - 5, 3, 5);
    if (working) { R(c, 'rgba(20,30,50,.5)', dx + 4, dy - 4, 9, 1); R(c, 'rgba(20,30,50,.5)', dx + 4, dy - 2, 6, 1); R(c, 'rgba(20,30,50,.5)', dx + 4, dy, 8, 1); R(c, 'rgba(111,214,255,.10)', dx - 1, dy + 3, 20, 6); }
    /* teclado, mouse, papeles, taza, planta chica */
    R(c, '#e9e9ec', dx + 19, dy + 5, 11, 3); R(c, '#c9c9ce', dx + 19, dy + 7, 11, 1); R(c, '#ffffff', dx + 31, dy + 5, 3, 3);
    R(c, '#fafafa', dx + 20, dy + 0, 6, 4); R(c, '#cfd3da', dx + 21, dy + 1, 4, 1);
    R(c, '#ffffff', dx + 34, dy + 1, 3, 4); R(c, owner ? '#d9501a' : '#9aa0aa', dx + 34, dy + 1, 3, 1); R(c, '#ffffff', dx + 37, dy + 2, 1, 2);
    if (!owner) { R(c, '#f4efe6', dx + 14, dy + 11, 12, 3); R(c, '#c9c0ae', dx + 15, dy + 12, 10, 1); }
    else { R(c, '#f4efe6', dx + 12, dy + 11, 14, 3); R(c, owner.lead ? '#d9501a' : '#3a8bd8', dx + 13, dy + 12, 12, 1); }
  }
  const plant = (c, x, y) => {
    R(c, 'rgba(0,0,0,.18)', x - 6, y - 1, 12, 2);
    R(c, '#1f6a38', x - 7, y - 14, 5, 7); R(c, '#1f6a38', x + 2, y - 15, 5, 8); R(c, '#2a7f46', x - 5, y - 18, 5, 9); R(c, '#34a057', x - 1, y - 20, 4, 10); R(c, '#3fb866', x + 1, y - 17, 4, 8);
    R(c, '#52cf7f', x, y - 19, 1, 3); R(c, '#52cf7f', x - 4, y - 16, 1, 2);
    R(c, '#8a4524', x - 5, y - 8, 10, 8); R(c, '#b5602f', x - 5, y - 8, 10, 2); R(c, '#6e3519', x - 4, y - 2, 8, 2);
  };
  const furniture = [];
  const addF = (b, fn) => furniture.push({ b, fn });
  for (let c = 0; c < 4; c++) for (let r = 0; r < 3; r++) {
    const dx = 30 + c * 90, dy = 40 + r * 48, owner = owners[c + ',' + r];
    addF(dy + 18, cc => drawDesk(cc, dx, dy, owner));
    const sx = dx + 20, sy = dy + 46;
    if (owner) addF(owner.seat.y - 1, cc => {
      R(cc, '#262c37', owner.seat.x - 7, owner.seat.y - 16, 14, 11); R(cc, '#3a4252', owner.seat.x - 6, owner.seat.y - 15, 12, 9); R(cc, '#4a5468', owner.seat.x - 6, owner.seat.y - 15, 12, 2);
      R(cc, '#262c37', owner.seat.x - 8, owner.seat.y - 9, 2, 5); R(cc, '#262c37', owner.seat.x + 6, owner.seat.y - 9, 2, 5);
    });
    else addF(dy + 19, cc => { R(cc, '#262c37', sx - 6, dy + 13, 12, 4); R(cc, '#3a4252', sx - 5, dy + 13, 10, 2); });
  }
  [[10, 74], [392, 112], [392, 168], [10, 150], [118, 252], [256, 252]].forEach(p => addF(p[1], cc => plant(cc, p[0], p[1])));
  /* sofá doble con cojines + mesa baja */
  addF(200, c => { R(c, '#a8461a', 20, 198, 60, 5); R(c, '#c85a24', 21, 199, 58, 13); R(c, '#d9692f', 22, 200, 56, 6); R(c, '#e5824a', 22, 200, 56, 1); R(c, '#b34f1f', 49, 199, 2, 13); });
  addF(226, c => {
    R(c, 'rgba(0,0,0,.18)', 20, 226, 62, 2);
    R(c, '#a8461a', 20, 204, 5, 22); R(c, '#a8461a', 75, 204, 5, 22); R(c, '#c85a24', 21, 204, 3, 6); R(c, '#c85a24', 76, 204, 3, 6);
    R(c, '#d9692f', 25, 213, 25, 12); R(c, '#d9692f', 50, 213, 25, 12); R(c, '#e5824a', 25, 213, 50, 1); R(c, '#b34f1f', 49, 213, 2, 12); R(c, '#b8531f', 25, 224, 50, 2);
    R(c, '#4a2c18', 22, 226, 3, 2); R(c, '#4a2c18', 75, 226, 3, 2);
    R(c, '#f2d49a', 29, 206, 7, 6); R(c, '#e0b66a', 29, 210, 7, 2);
  });
  addF(242, c => { R(c, 'rgba(0,0,0,.18)', 31, 240, 40, 2); R(c, '#6a4326', 32, 232, 38, 8); R(c, '#c98f55', 31, 230, 40, 4); R(c, '#e4b57a', 31, 230, 40, 1); R(c, '#fff', 38, 229, 4, 3); R(c, '#d9501a', 38, 229, 4, 1); R(c, '#3a8bd8', 56, 230, 8, 2); });
  /* cafetería: barra, máquina con vapor, tazas */
  addF(214, c => {
    R(c, 'rgba(0,0,0,.16)', 140, 214, 98, 3);
    R(c, '#aeb4c0', 140, 198, 96, 4); R(c, '#cfd4de', 140, 198, 96, 1); R(c, '#5b6270', 140, 202, 96, 12); R(c, '#4a505d', 140, 211, 96, 3);
    for (let x = 148; x < 236; x += 22) { R(c, '#6a7180', x, 204, 20, 8); R(c, '#7d8494', x, 204, 20, 1); R(c, '#c9ced8', x + 15, 207, 3, 1); }
    R(c, '#23272f', 216, 184, 16, 15); R(c, '#2f343e', 217, 185, 14, 3); R(c, '#ff5a4f', 221, 189, 3, 2); R(c, '#4ade80', 226, 189, 3, 2); R(c, '#e9e9ec', 220, 195, 8, 4); R(c, '#8b5a2b', 221, 195, 6, 2);
    [150, 158, 196].forEach(x => { R(c, '#ffffff', x, 194, 4, 5); R(c, '#d9d9de', x, 198, 4, 1); R(c, '#ffffff', x + 4, 195, 1, 3); });
    R(c, '#c8a16a', 172, 192, 14, 7); R(c, '#e6c48e', 173, 193, 12, 2); R(c, '#9a6f3c', 174, 196, 4, 3);
    if (!reduce) for (let k = 0; k < 3; k++) { const ph = (time * 0.9 + k / 3) % 1; R(c, 'rgba(255,255,255,' + (0.5 * (1 - ph)).toFixed(2) + ')', 222 + Math.round(Math.sin(time * 2 + k * 2) * 1.5), 182 - Math.round(ph * 9), 2, 2); }
    c.font = '600 5px "Pixelify Sans", monospace'; c.textBaseline = 'alphabetic'; c.fillStyle = '#f4ead6'; c.fillText('CAFÉ', 166, 211);
  });
  /* sala de reuniones: mesa con laptops, papeles y tazas */
  addF(236, c => {
    R(c, 'rgba(0,0,0,.20)', 292, 236, 82, 3);
    R(c, '#8a7f6d', 295, 232, 4, 5); R(c, '#8a7f6d', 366, 232, 4, 5);
    R(c, '#b9b2a4', 292, 213, 80, 22); R(c, '#e6e1d6', 292, 212, 80, 20); R(c, '#f4f0e8', 292, 212, 80, 1); R(c, 'rgba(0,0,0,.06)', 292, 230, 80, 2);
    [[304, 218], [330, 220], [352, 218]].forEach(p => { R(c, '#2b2f38', p[0], p[1], 10, 6); R(c, '#1d2027', p[0] + 1, p[1] + 1, 8, 4); R(c, '#9aa0aa', p[0] - 1, p[1] + 6, 12, 1); });
    R(c, '#7cc4ff', 306, 219, 6, 3); R(c, '#9bf0b8', 332, 221, 6, 3); R(c, '#f6d54a', 354, 219, 6, 3);
    R(c, '#fafafa', 318, 214, 6, 4); R(c, '#fafafa', 343, 225, 6, 4); R(c, '#cfd3da', 319, 215, 4, 1);
    R(c, '#ffffff', 366, 222, 3, 4); R(c, '#3a8bd8', 366, 222, 3, 1); R(c, '#ffffff', 296, 224, 3, 4); R(c, '#d9501a', 296, 224, 3, 1);
  });
  /* reloj de pared con segundero */
  addF(1, c => {
    R(c, '#2b2f38', 159, 10, 2, 1);
    const t = new Date(), s = t.getSeconds(), mi = t.getMinutes();
    const hand = (ang, len, col) => { for (let k = 1; k <= len; k++) R(c, col, Math.round(160 + Math.sin(ang) * k), Math.round(12 + 0 - Math.cos(ang) * k * 1.0), 1, 1); };
    hand((mi / 60) * Math.PI * 2, 4, '#2b2f38'); hand((s / 60) * Math.PI * 2, 5, '#d9501a');
  });

  function labels() {
    ctx.font = '600 6px "Pixelify Sans", monospace'; ctx.textBaseline = 'middle'; ctx.textAlign = 'center';
    const accent = css('--accent');
    agents.forEach(a => {
      const x = Math.round(a.x), top = Math.round(a.y) - 29;
      const w = Math.ceil(ctx.measureText(a.name).width) + 11, sel = selected === a, lx = x - w / 2;
      R(ctx, 'rgba(0,0,0,.22)', lx + 1, top + 1, w, 8);
      R(ctx, sel ? accent : 'rgba(255,255,255,.94)', lx + 1, top, w - 2, 8); R(ctx, sel ? accent : 'rgba(255,255,255,.94)', lx, top + 1, w, 6);
      R(ctx, a.mode === 'work' ? '#ff9f1c' : (a.path.length ? '#9aa0aa' : '#3ecf8e'), lx + 3, top + 3, 2, 2);
      ctx.fillStyle = sel ? '#ffffff' : '#1b2330'; ctx.fillText(a.name, x + 2, top + 4.5);
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
  /* Markdown ligero y seguro (todo se escapa antes): párrafos, listas, tablas, código, títulos y enlaces.
     Cualquier enlace (con formato o suelto) se abre SIEMPRE en una pestaña nueva. */
  function link(url, label) {
    return '<a class="ext" href="' + url + '" target="_blank" rel="noopener noreferrer">' + label + '</a>';
  }
  function inline(raw) {
    const keep = [];
    const hold = h => { keep.push(h); return '\u0001' + (keep.length - 1) + '\u0001'; };
    let t = String(raw == null ? '' : raw).replace(/`([^`\n]+)`/g, (_, c) => hold('<code>' + esc(c) + '</code>'));
    t = esc(t);
    t = t.replace(/\[([^\]\n]+)\]\((https?:\/\/[^)\s]+)\)/g, (_, label, url) => hold(link(url, label)));
    t = t.replace(/https?:\/\/[^\s<]*?(?=&quot;|&lt;|&gt;|\s|$)/g, m => {
      let url = m, tail = '';
      while (/[.,;:!?'\]]$/.test(url) || (/\)$/.test(url) && (url.match(/\(/g) || []).length < (url.match(/\)/g) || []).length)) { tail = url.slice(-1) + tail; url = url.slice(0, -1); }
      return url.length > 8 ? hold(link(url, url)) + tail : m;
    });
    t = t.replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/(^|[\s(])\*([^*\s][^*]*?)\*(?=[\s).,;:!?]|$)/g, '$1<i>$2</i>');
    return t.replace(/\u0001(\d+)\u0001/g, (_, i) => keep[+i]);
  }
  function md(src) {
    const lines = String(src == null ? '' : src).replace(/\r/g, '').split('\n');
    const out = [];
    let i = 0;
    const isRow = l => /^\s*\|.*\|\s*$/.test(l);
    const cells = l => l.trim().replace(/^\||\|$/g, '').split('|').map(c => c.trim());
    while (i < lines.length) {
      const l = lines[i];
      if (/^\s*```/.test(l)) {
        const buf = []; i++;
        while (i < lines.length && !/^\s*```/.test(lines[i])) buf.push(lines[i++]);
        i++; out.push('<pre><code>' + esc(buf.join('\n')) + '</code></pre>'); continue;
      }
      if (!l.trim()) { i++; continue; }
      if (isRow(l) && i + 1 < lines.length && /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/.test(lines[i + 1])) {
        const head = cells(l); i += 2; const rows = [];
        while (i < lines.length && isRow(lines[i])) rows.push(cells(lines[i++]));
        out.push('<div class="tbl"><table><thead><tr>' + head.map(c => '<th>' + inline(c) + '</th>').join('') + '</tr></thead><tbody>'
          + rows.map(r => '<tr>' + head.map((_, k) => '<td>' + inline(r[k] || '') + '</td>').join('') + '</tr>').join('') + '</tbody></table></div>');
        continue;
      }
      let m;
      if ((m = l.match(/^\s{0,3}(#{1,6})\s+(.*)$/))) { out.push('<h' + (m[1].length < 3 ? 3 : 4) + '>' + inline(m[2]) + '</h' + (m[1].length < 3 ? 3 : 4) + '>'); i++; continue; }
      if (/^\s*([-*_])(\s*\1){2,}\s*$/.test(l)) { out.push('<hr>'); i++; continue; }
      if (/^\s*>\s?/.test(l)) {
        const buf = [];
        while (i < lines.length && /^\s*>\s?/.test(lines[i])) buf.push(lines[i++].replace(/^\s*>\s?/, ''));
        out.push('<blockquote>' + inline(buf.join(' ')) + '</blockquote>'); continue;
      }
      if (/^\s*([-*•]|\d+[.)])\s+/.test(l)) {
        const ordered = /^\s*\d+[.)]\s+/.test(l), items = [];
        while (i < lines.length && /^\s*([-*•]|\d+[.)])\s+/.test(lines[i])) {
          let item = lines[i++].replace(/^\s*([-*•]|\d+[.)])\s+/, '');
          while (i < lines.length && lines[i].trim() && /^\s{2,}\S/.test(lines[i]) && !/^\s*([-*•]|\d+[.)])\s+/.test(lines[i])) item += ' ' + lines[i++].trim();
          items.push('<li>' + inline(item) + '</li>');
        }
        out.push((ordered ? '<ol>' : '<ul>') + items.join('') + (ordered ? '</ol>' : '</ul>')); continue;
      }
      const buf = [];
      while (i < lines.length && lines[i].trim() && !/^\s*(```|#{1,6}\s|>|([-*•]|\d+[.)])\s+)/.test(lines[i]) && !isRow(lines[i])) buf.push(lines[i++]);
      if (!buf.length) { buf.push(lines[i++]); }
      out.push('<p>' + buf.map(inline).join('<br>') + '</p>');
    }
    return '<div class="md">' + out.join('') + '</div>';
  }
  const bold = md;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }
  logLive.addEventListener('click', e => {
    const a = e.target.closest && e.target.closest('a[href]');
    if (!a || e.defaultPrevented) return;
    if (a.target !== '_blank') { e.preventDefault(); window.open(a.href, '_blank', 'noopener,noreferrer'); }
  });
  function setBusy(slug, on, label) {
    const a = agents.find(x => x.slug === slug); if (!a) return;
    if (on) { a.mode = 'work'; a.cur = { label: label || 'Trabajando contigo', dur: 1e9, prog: .4, done: 'Listo' }; goTo(a, a.seat, 'toDesk'); say(a, label ? 'Manos a la obra' : 'Pensando…', 2.5); }
    else if (a.cur && a.cur.dur === 1e9) { a.cur = null; a.mode = 'idle'; a.lastDone = 'Respondió'; say(a, '✓ Listo', 2.5); }
    ui();
  }
  function renderLive(data) {
    const stick = logLive.scrollHeight - logLive.scrollTop - logLive.clientHeight < 140 || !logLive.childElementCount;
    const prevTop = logLive.scrollTop;
    logLive.innerHTML = '';
    if (!data.messages.length) logLive.innerHTML = '<li><b class="who">' + esc(data.agent.name) + '</b>' + (chat.agent && chat.agent.orchestrator
      ? 'Hola, soy ' + esc(data.agent.name) + ', la orquestadora. Cuéntame qué quieres lograr: veo quién de tu equipo puede hacerlo de verdad, te propongo un plan y, cuando lo acordemos, reparto el trabajo.'
      : 'Hola, soy ' + esc(data.agent.name) + '. Cuéntame qué quieres automatizar y lo diseñamos juntos; cuando estemos de acuerdo, lo dejo como borrador para que lo revises.') + '</li>';
    data.messages.forEach(m => {
      const li = document.createElement('li');
      if (m.role === 'user') { li.className = 'user'; li.textContent = m.content; }
      else if (m.status === 'pending') { li.className = 'wait'; li.innerHTML = '<span class="dots"><i></i><i></i><i></i></span><span>' + esc(data.agent.name) + ' está trabajando…</span>'; }
      else if (m.status === 'error') {
        li.className = 'err'; li.appendChild(document.createTextNode(m.error || 'No se pudo completar la respuesta.'));
        if (m === data.messages[data.messages.length - 1]) {
          const rb = document.createElement('button'); rb.type = 'button'; rb.className = 'retry'; rb.textContent = 'Reintentar';
          rb.addEventListener('click', () => { const lastUser = data.messages.slice().reverse().find(x => x.role === 'user'); if (lastUser && !chat.pending) sendLive(lastUser.content); });
          li.appendChild(rb);
        }
      }
      else {
        let html = '<b class="who">' + esc(data.agent.name) + '</b>' + md(m.content);
        (m.workflows || []).forEach(w => { if (w.url) html += '<br><a class="made" href="' + esc(w.url) + '" target="_blank" rel="noopener noreferrer">Abrir «' + esc(w.name) + '» (#' + w.id + ') en el editor</a>'; });
        if (m.tools && m.tools.length) html += '<small class="tools">Herramientas usadas: ' + m.tools.map(esc).join(', ') + '</small>';
        li.innerHTML = html;
      }
      logLive.appendChild(li);
    });
    logLive.style.scrollBehavior = 'auto'; logLive.scrollTop = stick ? logLive.scrollHeight : prevTop; logLive.style.scrollBehavior = '';
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
    byId('send').textContent = tab.live ? 'Enviar' : 'Enviar encargo';
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

  function sendLive(text) {
    if (chat.pending) return;
    byId('send').disabled = true;
    $.request('onAgentSend', {
      data: { slug: chat.agent.slug, message: text },
      success: function (r) { if (byId('prompt').value.trim() === text) byId('prompt').value = ''; fit(); renderLive(r); },
      error: function (xhr) { byId('send').disabled = false; alert((xhr && xhr.responseJSON && xhr.responseJSON.result) || (xhr && xhr.responseText) || 'No se pudo enviar el mensaje.'); },
    });
  }
  const promptEl = byId('prompt');
  function fit() { promptEl.style.height = 'auto'; promptEl.style.height = Math.min(220, promptEl.scrollHeight + 2) + 'px'; }
  promptEl.addEventListener('input', fit);
  promptEl.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); byId('form').requestSubmit(); }
  });
  byId('form').addEventListener('submit', e => {
    e.preventDefault();
    const brief = byId('prompt').value.trim();
    if (!brief) return;

    if (chat.mode === 'live') { sendLive(brief); return; }

    if (job) return;
    byId('send').disabled = true;
    $.request('onSendTask', {
      data: { brief },
      success: function (r) {
        byId('prompt').value = ''; fit(); est();
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

  /* ---------- pantalla completa de la oficina ---------- */
  const fsBody = document.querySelector('.ws-office .stage-body');
  const fsClose = document.createElement('button'); fsClose.type = 'button'; fsClose.className = 'fs-btn fs-close'; fsClose.textContent = '✕ Cerrar'; fsBody.appendChild(fsClose);
  const leaveFs = () => { fsBody.classList.remove('pseudo-fs'); if (document.fullscreenElement) document.exitFullscreen(); };
  byId('stage-fs').addEventListener('click', e => {
    e.preventDefault(); e.stopPropagation();
    if (document.fullscreenElement === fsBody || fsBody.classList.contains('pseudo-fs')) { leaveFs(); return; }
    if (!stageBox.open) stageBox.open = true;
    if (fsBody.requestFullscreen) fsBody.requestFullscreen().catch(() => fsBody.classList.add('pseudo-fs'));
    else fsBody.classList.add('pseudo-fs'); // iPhone y navegadores sin la API
  });
  fsClose.addEventListener('click', leaveFs);
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && fsBody.classList.contains('pseudo-fs')) leaveFs(); });

  /* ---------- arranque ---------- */
  const stageBox = byId('stage-box');
  try { if (localStorage.getItem('ws.office.open') === '0') stageBox.open = false; } catch (e) {}
  stageBox.addEventListener('toggle', () => { try { localStorage.setItem('ws.office.open', stageBox.open ? '1' : '0'); } catch (e) {} });
  if (tabs.firstChild) openChat(tabList[0]); else setIdeas(SIM_IDEAS);
  byId('subtitle').textContent = 'Habla con tu equipo de agentes de IA: trabajan de verdad desde el chat y te dejan borradores para revisar.';
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
