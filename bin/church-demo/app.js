/* Sitio público y portal del miembro (themes/microsites: churchSite, churchPublic, churchPortal). */
const ME = MEMBERS.find((m) => m.first === 'Ana');   // Ana Condori, con la sesión del portal «iniciada»
let view = 'inicio', openEvent = null, openSermon = null, lastReg = null, sent = null;
const VIDEO = 'https://www.youtube-nocookie.com/embed/wOZQWmiBHx4';
const player = (title) => `<div class="video"><iframe src="${VIDEO}" title="${esc(title)}" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>`;
const WITH_VIDEO = [0, 3];   // índices de SERMONS con video, como en church:demo
const NAV = [['inicio', 'Inicio'], ['eventos', 'Eventos'], ['predicaciones', 'Predicaciones'], ['noticias', 'Noticias'], ['envivo', 'En vivo'], ['recursos', 'Recursos'], ['oracion', 'Oración'], ['visita', 'Soy nuevo'], ['portal', 'Mi portal']];
const SERMONS = [['La gracia que nos sostiene', 'Pastor Roberto Salinas', 'Fundamentos de la fe', 0], ['Fe en medio de la prueba', 'Pastor Roberto Salinas', 'Fundamentos de la fe', 1], ['Orar sin cesar', 'Hna. Esther Villca', 'Fundamentos de la fe', 2], ['El hogar como santuario', 'Pastor Roberto Salinas', 'Familias fuertes', 3], ['Perdonar para sanar', 'Hna. Esther Villca', 'Familias fuertes', 4]];
const NEWS = [['Bienvenidos a nuestra nueva página', 'Noticia', -2, false], ['Horario especial por feriado', 'Aviso', -1, false], ['Campaña para el templo nuevo', 'Noticia', -10, false], ['Reunión de líderes', 'Aviso', -4, true]];
const RESOURCES = [['Guía de estudio: Fundamentos', false], ['Cuaderno de trabajo para niños', false], ['Manual de célula', true]];
const myReg = (e) => REGS.find((r) => r.event === e.id && r.member === ME.id);
const when = (e) => `${fShort(e.at)} · ${fTime(e.at)} · ${esc(e.place)}`;

function ticket(r) {
  const e = EVENTS.find((x) => x.id === r.event);
  return r.status === 'registered'
    ? `<div class="done"><p><strong>¡Quedaste inscrito en «${esc(e.title)}»!</strong></p><p>Presenta este código al llegar:</p><div class="qr">${qrSvg('reg' + r.id, 160)}</div></div>`
    : `<div class="done"><p><strong>El cupo está lleno.</strong></p><p>Quedaste en lista de espera para «${esc(e.title)}». Si se libera un lugar, te avisaremos.</p></div>`;
}
const eventCard = (e) => `<button class="card event" data-act="event" data-id="${e.id}"><span class="date"><b>${pad(e.at.getDate())}</b>${e.at.toLocaleDateString('es', { month: 'short' }).replace('.', '')}</span><span><strong>${esc(e.title)}</strong><span class="muted small">${when(e)}</span>${seats(e) >= e.cap ? '<span class="tag amber">Lista de espera</span>' : ''}</span></button>`;

const VIEWS = {
  inicio: () => `<div class="hero"><p class="kicker">La Paz · dos sedes</p><h1>Un lugar para creer, crecer y servir</h1><p>Domingos 10:00 y 18:00. Ven como eres.</p><div class="row"><button class="btn light" data-act="go" data-id="visita">Es mi primera vez</button><button class="btn ghost" data-act="go" data-id="envivo">Ver en vivo</button></div></div>
    <div class="row between"><h2>Próximos eventos</h2><button class="link" data-act="go" data-id="eventos">Ver todos</button></div>
    <div class="stack">${EVENTS.filter((e) => e.pub).map(eventCard).join('')}</div>
    <button class="card pray" data-act="go" data-id="oracion"><strong>¿Podemos orar por ti?</strong><span class="muted small">Envía tu petición, privada si prefieres.</span></button>`,
  eventos: () => {
    const e = EVENTS.find((x) => x.id === openEvent);
    if (!e) return `<h1>Próximos eventos</h1><div class="stack">${EVENTS.filter((x) => x.pub).map(eventCard).join('')}</div>`;
    const full = seats(e) >= e.cap;
    return `<button class="link" data-act="go" data-id="eventos">← Eventos</button><h1>${esc(e.title)}</h1><p class="muted">${fDate(e.at)} ${fTime(e.at)} · ${esc(e.place)}</p><p>${esc(e.text)}</p>
      <div class="card stack">${lastReg ? ticket(lastReg) : `<h3>Inscríbete</h3>${full ? '<p class="muted">El cupo está lleno: te anotaremos en lista de espera.</p>' : `<p class="muted small">Quedan ${e.cap - seats(e)} de ${e.cap} lugares.</p>`}
      <input id="f-name" class="inp" placeholder="Tu nombre" maxlength="120" aria-label="Nombre"><input id="f-phone" class="inp" placeholder="Teléfono / WhatsApp" aria-label="Teléfono">
      <label class="check"><input type="checkbox" id="f-consent"> Autorizo que se guarden mis datos para esta inscripción</label><button class="btn primary" data-act="register">Inscribirme</button>`}</div>`;
  },
  predicaciones: () => {
    const meta = ([, by, serie, w]) => `${esc(by)} · ${serie} · ${fShort(day(nextSunday - 7 * (w + 1)))}`;
    const s = SERMONS[openSermon];
    if (!s) return `<h1>Predicaciones</h1><div class="stack">${SERMONS.map((x, i) => `<button class="card sermon" data-act="sermon" data-id="${i}"><span class="play">▶</span><span><strong>${esc(x[0])}</strong><span class="muted small">${meta(x)}</span></span></button>`).join('')}</div>`;
    return `<button class="link" data-act="go" data-id="predicaciones">← Predicaciones</button><h1>${esc(s[0])}</h1><p class="muted">${meta(s)}</p>${WITH_VIDEO.includes(openSermon) ? player(s[0]) : ''}<p>Mensaje del domingo sobre «${esc(s[0])}», con los puntos principales de la enseñanza.</p>`;
  },
  noticias: () => `<h1>Noticias</h1><div class="stack">${NEWS.filter((n) => !n[3]).map(([t, k, d]) => `<div class="card"><span class="tag blue">${k}</span><h3>${t}</h3><p class="muted small">${fDate(day(d))}</p></div>`).join('')}</div>`,
  envivo: () => `<h1>Culto dominical en vivo</h1><p class="muted">Domingos 10:00 y 18:00</p>${player('Culto dominical en vivo')}`,
  recursos: () => `<h1>Recursos</h1><div class="stack">${RESOURCES.filter((r) => !r[1]).map(([t]) => `<div class="card row between"><strong>${t}</strong><button class="btn sm" data-act="download">Descargar PDF</button></div>`).join('')}</div><p class="muted small">Hay material exclusivo para miembros en Mi portal.</p>`,
  oracion: () => `<h1>Petición de oración</h1><p class="muted">Cuéntanos por qué quieres que oremos. Lo trataremos con respeto y reserva.</p><div class="card stack">${sent === 'oracion' ? '<div class="done"><p><strong>Recibimos tu petición.</strong></p><p>Estaremos orando por ti.</p></div>' : `
    <input id="f-name" class="inp" placeholder="Tu nombre (opcional)" aria-label="Nombre"><textarea id="f-req" class="inp" rows="4" placeholder="¿Por qué podemos orar?" aria-label="Petición"></textarea><input class="inp" placeholder="Teléfono / WhatsApp (solo si quieres que te contactemos)" aria-label="Teléfono">
    <label class="check"><input type="checkbox" checked> Mantener mi petición privada (solo la verá el equipo pastoral)</label><label class="check"><input type="checkbox"> Quiero que me contacte un pastor</label><label class="check"><input type="checkbox" id="f-consent"> Autorizo que se guarde mi petición y mis datos para acompañarme</label><button class="btn primary" data-act="pray">Enviar petición</button>`}</div>`,
  visita: () => `<h1>¡Bienvenido a ${CHURCH}!</h1><p class="muted">Nos alegra que nos visites. Déjanos tus datos y te saludaremos.</p><div class="card stack">${sent === 'visita' ? '<div class="done"><p><strong>¡Gracias por visitarnos!</strong></p><p>Pronto alguien de la iglesia se pondrá en contacto contigo.</p></div>' : `
    <input id="f-name" class="inp" placeholder="Tu nombre" aria-label="Nombre"><input class="inp" placeholder="Teléfono / WhatsApp" aria-label="Teléfono"><input class="inp" placeholder="Correo (opcional)" aria-label="Correo"><input class="inp" placeholder="¿Cómo supiste de nosotros?" aria-label="Cómo supiste de nosotros"><textarea class="inp" rows="3" placeholder="¿Qué te gustaría conocer? (ministerios, grupos, reuniones…)" aria-label="Interés"></textarea>
    <label class="check"><input type="checkbox"> Acepto recibir mensajes por WhatsApp de la iglesia</label><label class="check"><input type="checkbox" id="f-consent"> Autorizo que se guarden mis datos para contactarme</label><button class="btn primary" data-act="visit">Enviar</button>`}</div>`,
  portal: () => `<div><p class="kicker">Hola</p><h1>${ME.first}</h1></div>
    <h2>Mis datos</h2><div class="card stack"><p class="muted">${fullName(ME)} · para cambiar tu nombre u otros datos, habla con la secretaría.</p><input class="inp" placeholder="Teléfono / WhatsApp" aria-label="Teléfono"><input class="inp" value="ana.condori@example.invalid" aria-label="Correo"><input class="inp" value="Zona Sopocachi #214" aria-label="Dirección"><label class="check"><input type="checkbox" checked> Acepto recibir mensajes por WhatsApp de la iglesia</label><div><button class="btn primary" data-act="save">Guardar</button></div></div>
    <h2>Mis grupos</h2><div class="card"><strong>Célula Norte</strong> · Miembro · miércoles 19:00 · Casa de la familia Mamani</div>
    <h2>Próximos eventos</h2><div class="stack">${EVENTS.map((e) => { const r = myReg(e); return `<div class="card"><div class="row between"><span><strong>${esc(e.title)}</strong> <span class="muted small">${when(e)}</span></span>${r ? `<span class="row"><span class="tag ${r.status === 'registered' ? 'green' : 'amber'}">${r.status === 'registered' ? 'Inscrito' : 'En espera'}</span>${r.status === 'registered' ? `<button class="link" data-act="myqr" data-id="${r.id}">Mi código</button>` : ''}<button class="link" data-act="cancel" data-id="${r.id}">Cancelar</button></span>` : `<button class="btn primary sm" data-act="join" data-id="${e.id}">Inscribirme</button>`}</div></div>`; }).join('')}</div>
    <h2>Para miembros</h2><div class="card"><strong>Reunión de líderes</strong> <span class="muted small">${fDate(day(-4))}</span></div>
    <h2>Recursos</h2><div class="card row between"><strong>Manual de célula</strong><button class="btn sm" data-act="download">Descargar PDF</button></div>`,
};
function render(top) {
  $('#nav').innerHTML = NAV.map(([k, l]) => `<button data-act="go" data-id="${k}" class="${view === k ? 'on' : ''}"${view === k ? ' aria-current="page"' : ''}>${l}</button>`).join('');
  $('#main').innerHTML = VIEWS[view]();
  if (top) { window.scrollTo(0, 0); const a = $('#nav .on'); if (a) $('#nav').scrollLeft = a.offsetLeft - 60; }
}
const need = (name) => { if (name && !val('f-name')) { toast('Escribe tu nombre', 'err'); return false; } if (!on('f-consent')) { toast('Marca la autorización de datos', 'err'); return false; } return true; };
Object.assign(ACT, {
  go: (v) => { view = v; openEvent = null; openSermon = null; lastReg = null; sent = null; render(true); },
  sermon: (i) => { openSermon = +i; render(true); },
  event: (id) => { view = 'eventos'; openEvent = +id; lastReg = null; render(true); },
  register: () => { if (!need(true)) return; lastReg = enroll(EVENTS.find((e) => e.id === openEvent), val('f-name')); render(); },
  pray: () => { if (!val('f-req')) return toast('Escribe tu petición', 'err'); if (!need(false)) return; sent = 'oracion'; render(true); },
  visit: () => { if (!need(true)) return; sent = 'visita'; render(true); },
  join: (id) => { const r = enroll(EVENTS.find((e) => e.id === +id), fullName(ME), ME.id); render(); modal(ticket(r)); },
  myqr: (id) => modal(ticket(REGS.find((r) => r.id === +id))),
  cancel: (id) => confirmBox('¿Cancelar tu inscripción?', () => { unenroll(REGS.find((r) => r.id === +id)); render(); toast('Inscripción cancelada'); }),
  save: () => toast('Tus datos se guardaron'),
  download: () => toast('En la demo no se descargan archivos'),
});
render(true);
