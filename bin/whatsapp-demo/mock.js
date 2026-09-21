/* Demo OmniChat — API simulada de /api/v1/chat para "Altiplano Café" (empresa ficticia).
 * Se carga ANTES de app.js: intercepta fetch y responde con los mismos formatos que aero/chat. */
(function () {
    'use strict';

    var API = '/api/v1/chat';
    var NOW = Date.now();
    var seq = 1000, convSeq = 100, orderSeq = 141, ticketSeq = 87, dealSeq = 30, chargeSeq = 50;

    // ---------- utilidades ----------
    function ago(min) { return new Date(NOW - min * 60000).toISOString(); }
    function day(back, h, m) { var d = new Date(NOW); d.setDate(d.getDate() - back); d.setHours(h, m, 0, 0); return d.toISOString(); }
    function nowIso() { return new Date().toISOString(); }
    function bs(n) { return 'Bs ' + Number(n).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function pad(n, w) { return String(n).length >= w ? String(n) : new Array(w - String(n).length + 1).join('0') + n; }
    function svgUri(s) { return 'data:image/svg+xml;utf8,' + encodeURIComponent(s); }
    function cut(s, n) { s = String(s || ''); return s.length > n ? s.slice(0, n - 1) + '…' : s; }

    // ---------- medios generados (sin archivos externos) ----------
    function shape(kind, color, l1, l2) {
        var body = kind === 'jar'
            ? '<rect x="46" y="30" width="68" height="14" rx="4" fill="#3b2a1d"/><rect x="40" y="44" width="80" height="96" rx="14" fill="' + color + '"/>'
            : '<path d="M44 34h72l8 16v92a8 8 0 0 1-8 8H44a8 8 0 0 1-8-8V50z" fill="' + color + '"/><path d="M44 34h72l8 16H36z" fill="rgba(0,0,0,.2)"/>';
        return svgUri('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 160"><rect width="160" height="160" fill="#efe7d8"/>' + body +
            '<rect x="50" y="72" width="60" height="44" rx="4" fill="#f6efe0"/>' +
            '<text x="80" y="92" text-anchor="middle" font-family="Georgia,serif" font-size="11" font-weight="700" fill="#2a1d12">' + l1 + '</text>' +
            '<text x="80" y="106" text-anchor="middle" font-family="Georgia,serif" font-size="9" fill="#5a4630">' + l2 + '</text></svg>');
    }

    function qrSvg(seed) {
        var h = 2166136261;
        for (var i = 0; i < seed.length; i++) h = Math.imul(h ^ seed.charCodeAt(i), 16777619) >>> 0;
        h = h || 1;
        function rnd() { h ^= h << 13; h >>>= 0; h ^= h >>> 17; h ^= h << 5; h >>>= 0; return h / 4294967296; }
        function fin(x, y) { return '<rect x="' + x + '" y="' + y + '" width="7" height="7" fill="#111"/><rect x="' + (x + 1) + '" y="' + (y + 1) + '" width="5" height="5" fill="#fff"/><rect x="' + (x + 2) + '" y="' + (y + 2) + '" width="3" height="3" fill="#111"/>'; }
        var cells = '';
        for (var y = 0; y < 25; y++) for (var x = 0; x < 25; x++) {
            if ((x < 8 && y < 8) || (x > 16 && y < 8) || (x < 8 && y > 16)) continue;
            if (rnd() > 0.52) cells += '<rect x="' + x + '" y="' + y + '" width="1" height="1" fill="#111"/>';
        }
        return svgUri('<svg xmlns="http://www.w3.org/2000/svg" viewBox="-2 -2 29 29" shape-rendering="crispEdges"><rect x="-2" y="-2" width="29" height="29" fill="#fff"/>' + cells + fin(0, 0) + fin(18, 0) + fin(0, 18) + '</svg>');
    }

    function wavBlob(sec) {
        var rate = 8000, n = rate * sec, buf = new ArrayBuffer(44 + n), v = new DataView(buf), i;
        function s(o, t) { for (var k = 0; k < t.length; k++) v.setUint8(o + k, t.charCodeAt(k)); }
        s(0, 'RIFF'); v.setUint32(4, 36 + n, true); s(8, 'WAVEfmt '); v.setUint32(16, 16, true); v.setUint16(20, 1, true); v.setUint16(22, 1, true);
        v.setUint32(24, rate, true); v.setUint32(28, rate, true); v.setUint16(32, 1, true); v.setUint16(34, 8, true); s(36, 'data'); v.setUint32(40, n, true);
        for (i = 0; i < n; i++) v.setUint8(44 + i, 128 + Math.round(Math.sin(2 * Math.PI * (190 + 50 * Math.sin(i / 1400)) * i / rate) * Math.sin(Math.PI * i / n) * 38));
        return new Blob([buf], { type: 'audio/wav' });
    }
    function blobToDataUri(blob) {
        return new Promise(function (ok) { var r = new FileReader(); r.onload = function () { ok(r.result); }; r.onerror = function () { ok(''); }; r.readAsDataURL(blob); });
    }
    var AUDIO_URI = '';
    blobToDataUri(wavBlob(3)).then(function (u) { AUDIO_URI = u; DATA.audioMsg.media_url = u; });

    // ---------- catálogo ----------
    var ME = { id: 1, name: 'Valeria Mendoza', initials: 'VM' };
    var AGENTS = [ME, { id: 2, name: 'Diego Quispe', initials: 'DQ' }, { id: 3, name: 'Camila Rojas', initials: 'CR' }];
    function agent(id) { return AGENTS.filter(function (a) { return a.id === id; })[0] || null; }
    var TENANT = { handle: 'altiplano', name: 'Altiplano Café' };
    var CAPS = { quote_reply: true, poll: true, location: true, media: true };
    var ACCOUNTS = [
        { id: 1, label: 'Ventas', platform: 'whatsapp', phone_number: '+591 71234567', capabilities: CAPS },
        { id: 2, label: 'Soporte', platform: 'whatsapp', phone_number: '+591 72345678', capabilities: CAPS },
        { id: 3, label: 'Instagram', platform: 'instagram', phone_number: null, capabilities: {} },
    ];
    function acct(id) { return ACCOUNTS.filter(function (a) { return a.id === id; })[0]; }

    var PRODUCTS = [
        { id: 1, name: 'Yungas tostado medio', price: 48, has_price_range: true, stock: null, in_stock: true, has_variants: true, requires_shipping: true, image_url: shape('bag', '#7a4a2b', 'YUNGAS', 'medio'),
          variants: [{ id: 11, label: '250 g · grano', price: 48, in_stock: true }, { id: 12, label: '500 g · grano', price: 90, in_stock: true }, { id: 13, label: '1 kg · grano', price: 170, in_stock: true }] },
        { id: 2, name: 'Caranavi especial lavado 250 g', price: 62, stock: 14, in_stock: true, requires_shipping: true, image_url: shape('bag', '#3f5a45', 'CARANAVI', 'lavado') },
        { id: 3, name: 'Blend Casa Altiplano 250 g', price: 42, stock: 40, in_stock: true, requires_shipping: true, image_url: shape('bag', '#8a3f2a', 'BLEND', 'casa') },
        { id: 4, name: 'Descafeinado suave 250 g', price: 58, stock: 0, in_stock: false, requires_shipping: true, image_url: shape('bag', '#5c5f6b', 'DECAF', 'suave') },
        { id: 5, name: 'Prensa francesa 600 ml', price: 135, stock: 9, in_stock: true, requires_shipping: true, image_url: shape('jar', '#9fb7b3', 'PRENSA', '600 ml') },
        { id: 6, name: 'Cold brew botella 500 ml', price: 30, stock: 22, in_stock: true, requires_shipping: true, image_url: shape('jar', '#4a3324', 'COLD', 'brew') },
        { id: 7, name: 'Cápsulas compatibles x10', price: 55, stock: 31, in_stock: true, requires_shipping: true, image_url: shape('bag', '#b4532a', 'CÁPSULAS', 'x10') },
        { id: 8, name: 'Molinillo manual', price: 210, stock: 5, in_stock: true, requires_shipping: true, image_url: shape('jar', '#6b665c', 'MOLINILLO', 'manual') },
    ];
    var GATEWAYS = [{ id: 1, name: 'Pago QR · Banco Económico' }, { id: 2, name: 'Efectivo contra entrega' }];
    var BANKS = [{ id: 1, label: 'Banco Económico · Altiplano Café' }];
    var PRESETS = [{ days: 1, label: '1 día' }, { days: 7, label: '1 semana' }, { days: 365, label: '1 año' }];
    var DEPTS = [{ id: 1, name: 'Ventas mayoristas' }, { id: 2, name: 'Postventa' }, { id: 3, name: 'Envíos' }];
    var LISTS = [{ id: 1, name: 'Clientes VIP', color: '#17695a' }, { id: 2, name: 'Mayoristas', color: '#3b5fa8' }, { id: 3, name: 'Suscripción mensual', color: '#b4532a' }];
    var STAGES = [{ id: 1, name: 'Nuevo' }, { id: 2, name: 'Contactado' }, { id: 3, name: 'Propuesta' }, { id: 4, name: 'Negociación' }, { id: 5, name: 'Ganado', is_won: true }, { id: 6, name: 'Perdido', is_lost: true }];
    var QUICK = [
        { id: 1, shortcut: 'saludo', title: 'Saludo', area_label: 'Ventas', uses_count: 31, sort_order: 1, body: '¡Hola! Bienvenido/a a Altiplano Café ☕ ¿En qué te puedo ayudar?' },
        { id: 2, shortcut: 'envio', title: 'Envíos', area_label: 'Ventas', uses_count: 24, sort_order: 2, body: 'Enviamos en La Paz y El Alto el mismo día si confirmas antes de las 16:00. A otras ciudades, por encomienda en 24–48 h.' },
        { id: 3, shortcut: 'pago', title: 'Pago por QR', area_label: 'Ventas', uses_count: 19, sort_order: 3, body: 'Puedes pagar por QR desde cualquier app bancaria. Te envío el QR con el monto exacto.' },
        { id: 4, shortcut: 'horario', title: 'Horario', area_label: 'Ventas', uses_count: 9, sort_order: 4, body: 'Atendemos de lunes a sábado, de 8:00 a 19:00. Los domingos solo pedidos web.' },
        { id: 5, shortcut: 'mayorista', title: 'Mayoristas', area_label: 'Ventas', uses_count: 7, sort_order: 5, body: 'Para compras desde 5 kg manejamos precio mayorista y factura. ¿Cuántos kg necesitas al mes?' },
        { id: 6, shortcut: 'postventa', title: 'Postventa', area_label: 'Soporte', uses_count: 12, sort_order: 6, body: 'Lamentamos el inconveniente. Ya abrimos un ticket y te avisamos por aquí apenas lo resolvamos.' },
        { id: 7, shortcut: 'gracias', title: 'Gracias', area_label: 'Soporte', uses_count: 15, sort_order: 7, body: '¡Gracias por tu compra! Cualquier duda, aquí estamos ☕' },
    ];

    // ---------- constructores de mensajes ----------
    function M(dir, body, at, x) {
        var o = { kind: 'message', id: 'm' + (++seq), direction: dir, type: 'text', body: body, media_url: null, media_type: null, status: dir === 'outbound' ? 'read' : 'delivered', failed_reason: null, quote: null, at: at };
        for (var k in (x || {})) o[k] = x[k];
        return o;
    }
    function E(type, body, by, at, data) { return { kind: 'event', id: 'e' + (++seq), type: type, body: body, data: data || null, by: by || null, at: at }; }
    function N(body, by, at) { return { kind: 'note', id: 'e' + (++seq), type: 'note', body: body, data: null, by: by, at: at }; }
    function crm(o) { return { id: o.id, first: o.first, last: o.last, email: o.email || '', lists: o.lists || [], ticket: o.ticket || null, lead: o.lead || null, deal: o.deal || null, collections: o.collections || [] }; }
    function dateStr(offset) { var d = new Date(NOW); d.setDate(d.getDate() + offset); return d.getFullYear() + '-' + pad(d.getMonth() + 1, 2) + '-' + pad(d.getDate(), 2); }

    // ---------- conversaciones de ejemplo ----------
    var DATA = { audioMsg: M('inbound', '', ago(150), { type: 'audio', media_type: 'audio', media_url: '' }) };
    var QUOTED_POLL = M('outbound', '📊 ¿Qué contenido prefieren?\n• Café 250 g + taza\n• Café 250 g + galletas\n• Solo café 500 g', day(2, 10, 12), { type: 'poll' });

    var CONVS = [
        { id: 101, code: 'mchoq1', account_id: 1, contact: { id: 501, name: 'Mariana Choque', phone: '+591 76543210' }, assigned_to: 1, unread: 2,
          script: ['Perfecto, quedo atenta 🙌'], locations: [{ id: 1, lat: -16.5142, lng: -68.13, label: 'Sopocachi', at: ago(33) }], orders: [], charges: [],
          crm: crm({ id: 901, first: 'Mariana', last: 'Choque', lists: [] }),
          items: [
            M('inbound', 'Buenas tardes! Vi su cuenta en Instagram. ¿Tienen café de Yungas en grano?', ago(42)),
            M('outbound', '¡Hola Mariana! Bienvenida a Altiplano Café ☕ Sí, tenemos Yungas tostado medio en grano, desde 250 g.', ago(40)),
            M('inbound', 'Quiero *2 bolsas de 250 g* en grano y una *prensa francesa*. ¿Hacen envío a Sopocachi?', ago(38)),
            M('outbound', 'Claro, enviamos en La Paz el mismo día si confirmas antes de las 16:00. ¿Me compartes tu ubicación?', ago(36)),
            M('inbound', 'Ubicación: -16.514200, -68.130000', ago(33), { type: 'location' }),
            M('inbound', 'Ahí es, edificio con portón verde, segundo piso', ago(32)),
            M('outbound', 'Perfecto 🙌 Te armo el pedido ahora mismo y te paso el QR para pagar.', ago(30)),
            M('inbound', 'Listo, espero el QR. Gracias!', ago(2)),
            M('inbound', '¿Puede venir con factura?', ago(1)),
          ] },
        { id: 102, code: 'cceib2', account_id: 1, contact: { id: 502, name: 'Carlos Ríos · Casa Ceibo', phone: '+591 77712345' }, assigned_to: 2, unread: 1,
          script: ['Genial. Si van con factura, mándeme la proforma por favor.'], locations: [], orders: [], charges: [],
          crm: crm({ id: 902, first: 'Carlos', last: 'Ríos', email: 'carlos@casaceibo.example', lists: [2],
              lead: { id: 1, status: 'qualified', converted: true }, deal: { id: 21, title: 'Provisión mensual Casa Ceibo', value: 2360, currency: 'BOB', stage_id: 4, status: 'open' },
              collections: [{ id: 1, concept: 'Anticipo primera entrega (20 kg)', amount: 1180, currency: 'BOB', due_date: dateStr(3) }, { id: 2, concept: 'Muestra 2 kg + envío', amount: 185, currency: 'BOB', due_date: dateStr(-2) }] }),
          items: [
            M('inbound', 'Buenas, soy Carlos, del restaurante Casa Ceibo en Calacoto. Queremos cambiar de proveedor de café, consumimos unos 20 kg al mes.', day(1, 17, 40)),
            M('outbound', 'Hola Carlos, un gusto. Con ese volumen manejamos precio mayorista: *Bs 118/kg* en el Blend Casa. ¿Le mando ficha técnica y una muestra?', day(1, 17, 52)),
            E('lead', 'Lead creado desde este chat', agent(2), day(1, 17, 53)),
            M('inbound', 'Sí, por favor, la muestra. ¿Facturan?', day(1, 18, 5)),
            M('outbound', 'Sí, emitimos factura. Coordino la muestra de 1 kg Blend + 1 kg Yungas para mañana.', day(1, 18, 9)),
            N('Carlos decide junto a su socia. Cerrar antes del jueves; si piden entrega quincenal, ofrecer 10 kg por entrega.', agent(2), day(1, 18, 12)),
            E('deal', 'Negocio creado: Provisión mensual Casa Ceibo · Bs 2.360,00', agent(2), day(1, 18, 14)),
            M('inbound', 'Probamos la muestra, a mi socia le gustó el Yungas. ¿Pueden entregar cada 15 días?', ago(55)),
          ] },
        { id: 103, code: 'rfern3', account_id: 2, contact: { id: 503, name: 'Roberto Fernández', phone: '+591 70011223' }, assigned_to: 3, unread: 0,
          script: [], locations: [], orders: [], charges: [],
          crm: crm({ id: 903, first: 'Roberto', last: 'Fernández', lists: [3],
              ticket: { id: 87, number: 'TCK-000087', subject: 'Pedido ORD-000131 con molienda equivocada', status: 'pending', priority: 'normal', department: 'Postventa', assignee: agent(3) } }),
          items: [
            M('inbound', 'Buenas, mi pedido ORD-000131 llegó con café molido y yo pedí en grano 😕', ago(200)),
            M('inbound', '', ago(199), { type: 'image', media_type: 'image', media_url: shape('bag', '#7a4a2b', 'MOLIDO', 'lote 0912') }),
            M('outbound', 'Hola Roberto, lamento el error. Lo reviso ahora mismo. ¿Me confirma su dirección de entrega?', ago(190)),
            null, // nota de voz (se completa abajo)
            E('ticket', 'Ticket TCK-000087 creado (Postventa)', agent(3), ago(170)),
            N('Error de empaque, lote 0912. Reenviar en grano sin costo y avisar a producción.', agent(3), ago(165)),
            M('outbound', 'Le reenviamos hoy, sin costo, el pedido en grano. Lo recibe mañana antes del mediodía. Disculpe la molestia.', ago(160)),
            M('inbound', 'Gracias, quedo atento 👍', ago(20)),
          ] },
        { id: 104, code: 'lmama4', account_id: 1, contact: { id: 504, name: 'Lucía Mamani', phone: '+591 68899001' }, assigned_to: null, unread: 1,
          script: ['Gracias! ¿Y aceptan pago con QR?', 'Perfecto, lo quiero. ¿Cómo coordinamos la entrega?'], locations: [], orders: [], charges: [],
          crm: crm({ id: 904, first: 'Lucía', last: 'Mamani' }),
          items: [M('inbound', 'Hola! ¿Tienen café descafeinado? Es para mi mamá, no puede tomar cafeína', ago(6))] },
        { id: 105, code: 'hsola5', account_id: 1, contact: { id: 505, name: 'Gabriela Antezana · Hotel Sol de los Andes', phone: '+591 72200456' }, assigned_to: 1, unread: 0,
          script: [], locations: [], orders: [], charges: [],
          crm: crm({ id: 905, first: 'Gabriela', last: 'Antezana', lists: [2], lead: { id: 2, status: 'qualified', converted: false } }),
          items: [
            M('inbound', 'Buen día, necesitamos 40 cajas de regalo para fin de año, con el logo del hotel. ¿Es posible?', day(2, 9, 30)),
            M('outbound', '¡Hola Gabriela! Sí, hacemos cajas personalizadas desde 30 unidades. Te comparto opciones de contenido:', day(2, 10, 10)),
            QUOTED_POLL,
            M('inbound', 'La opción 2, con galletas. ¿Para cuándo tendrían el presupuesto?', day(2, 11, 2), { quote: { id: QUOTED_POLL.id, direction: 'outbound', body: '📊 ¿Qué contenido prefieren?' } }),
            M('outbound', 'Mañana antes del mediodía te lo envío por aquí, con fotos del empaque.', day(2, 11, 8)),
          ] },
        { id: 106, code: 'pbari6', account_id: 3, contact: { id: 506, name: 'paola.barista', phone: '@paola.barista' }, assigned_to: null, unread: 1,
          script: ['Genial, avísenme por acá 🙌'], locations: [], orders: [], charges: [], crm: null,
          items: [M('inbound', 'Hola! Vi que dan talleres de barismo, ¿cuándo es el próximo?', ago(25))] },
        { id: 107, code: 'avill7', account_id: 1, contact: { id: 507, name: 'Andrés Villca', phone: '+591 71122334' }, assigned_to: 1, unread: 0,
          script: [], locations: [], orders: [],
          charges: [{ id: 41, amount: 170, currency: 'BOB', description: 'Suscripción mensual Yungas 1 kg', status: 'paid', due_at: dateStr(0), created_at: day(1, 15, 3) }],
          crm: crm({ id: 907, first: 'Andrés', last: 'Villca', lists: [1, 3] }),
          items: [
            M('inbound', 'Quiero renovar mi suscripción mensual de Yungas 1 kg', day(1, 15, 0)),
            M('outbound', '¡Claro Andrés! Te genero el cobro por QR de *Bs 170,00*.', day(1, 15, 2)),
            E('charge', 'Cobro QR de Bs 170,00 emitido · Suscripción mensual Yungas 1 kg', ME, day(1, 15, 3)),
            M('outbound', 'Escanea para pagar · Bs 170,00', day(1, 15, 3), { type: 'image', media_type: 'image', media_url: qrSvg('avill7-41') }),
            E('payment', 'Pago recibido: Bs 170,00 · Suscripción mensual Yungas 1 kg', null, day(1, 15, 9)),
            M('outbound', '¡Recibimos tu pago, gracias Andrés! Tu café sale mañana temprano ☕', day(1, 15, 9)),
            M('inbound', 'Gracias 🙌', day(1, 15, 12)),
          ] },
        { id: 108, code: 'salca8', account_id: 2, contact: { id: 508, name: 'Sofía Alcón', phone: '+591 79000112' }, assigned_to: 2, unread: 0,
          script: [], locations: [], orders: [], charges: [],
          crm: crm({ id: 908, first: 'Sofía', last: 'Alcón' }),
          items: [
            M('inbound', '¿Cómo preparo el cold brew que compré? No dice la proporción', day(4, 16, 20)),
            M('outbound', 'Hola Sofía. Mezcla *1 parte de concentrado por 3 de agua fría* o leche, con hielo. Dura 10 días en refrigerador.', day(4, 16, 31)),
            M('inbound', 'Perfecto, quedó buenísimo. Gracias!', day(4, 17, 2)),
          ] },
    ];
    // la nota de voz necesita el data-uri del audio, que se genera de forma asíncrona
    CONVS[2].items[3] = DATA.audioMsg;

    // ---------- acceso a datos ----------
    function conv(id) { return CONVS.filter(function (c) { return c.id === +id; })[0] || null; }
    function messagesOf(c) { return c.items.filter(function (i) { return i.kind === 'message'; }); }
    function lastMsg(c) { var m = messagesOf(c); return m.length ? m[m.length - 1] : null; }
    function unreadOf(c) { var l = lastMsg(c); return l && l.direction === 'outbound' ? 0 : c.unread; }
    function push(c, item) { c.items.push(item); return item; }
    function row(c) {
        var l = lastMsg(c);
        return {
            id: c.id, code: c.code, account_id: c.account_id, status: 'open', unread_count: unreadOf(c),
            last_message_at: l ? l.at : null,
            contact: { id: c.contact.id, name: c.contact.name, phone: c.contact.phone, avatar_url: null },
            last_message: l ? { body: l.body, direction: l.direction, media_type: l.media_type, type: l.type } : null,
            assigned_to: c.assigned_to ? agent(c.assigned_to) : null,
        };
    }
    function progress(m) {
        setTimeout(function () { if (m.status === 'sent') m.status = 'delivered'; }, 1200);
        setTimeout(function () { if (m.status === 'delivered') m.status = 'read'; }, 3400);
    }
    function customerReplies(c) {
        if (!c.script || !c.script.length) return;
        var text = c.script.shift();
        setTimeout(function () { push(c, M('inbound', text, nowIso())); c.unread++; }, 3200);
    }
    function agentSays(c, body, x) { var m = push(c, M('outbound', body, nowIso(), x || { status: 'sent' })); if (!x) progress(m); return m; }
    function sendQr(c, label, seed) { return push(c, M('outbound', label, nowIso(), { status: 'sent', type: 'image', media_type: 'image', media_url: qrSvg(seed) })); }

    // Pago simulado: unos segundos después de emitir el QR, como si el cliente lo escaneara.
    function settle(c, done, text, thanks) {
        setTimeout(function () {
            done();
            push(c, E('payment', text, null, nowIso()));
            if (thanks) agentSays(c, thanks);
            c.unread++;
        }, 8000);
    }

    // ---------- paneles ----------
    function panel(c) {
        var wa = acct(c.account_id).platform === 'whatsapp';
        var out = { crm: true, whatsapp: wa, contact: null, departments: DEPTS, all_lists: LISTS, pay: true };
        if (!wa || !c.crm) return out;
        var k = c.crm;
        out.contact = { id: k.id, name: (k.first + ' ' + k.last).trim(), first_name: k.first, last_name: k.last, phone: c.contact.phone, email: k.email };
        out.list_ids = k.lists; out.ticket = k.ticket; out.lead = k.lead; out.deal = k.deal;
        out.stages = k.deal ? STAGES : [];
        var today = dateStr(0);
        out.collections = k.collections.map(function (i) { return { id: i.id, concept: i.concept, amount: i.amount, currency: i.currency, due_date: i.due_date, overdue: i.due_date < today }; });
        return out;
    }
    function payState(c) {
        return { available: true, media: acct(c.account_id).capabilities.media === true, presets: PRESETS, banks: BANKS, charges: c.charges.slice().reverse() };
    }
    function shopState(c) {
        return { available: true, currency: { code: 'BOB', symbol: 'Bs' }, customer: { name: c.contact.name, phone: c.contact.phone }, gateways: GATEWAYS, orders: c.orders.slice().reverse(), locations: c.locations.slice().reverse() };
    }
    function orderSummary(o, lines) {
        return '*Pedido ' + o.number + '*\n' + lines.map(function (l) { return l.qty + ' × ' + l.name + ' — ' + bs(l.price * l.qty); }).join('\n') +
            '\nTotal: *' + bs(o.total) + '*\nSigue tu pedido: https://altiplano.example/pedido/' + o.number;
    }

    // ---------- rutas ----------
    function ok(data, s) { return { s: s || 200, b: { data: data } }; }
    function fail(code, message, s) { return { s: s, b: { error: code, message: message } }; }
    var NOT_FOUND = fail('not_found', 'No encontrado.', 404);

    var routes = [];
    function on(method, re, fn) { routes.push({ m: method, re: new RegExp('^' + re + '$'), fn: fn }); }

    on('GET', '/tenants/[^/]+', function () { return ok(TENANT); });
    on('POST', '/tenants/[^/]+/login', function () { return ok({ token: 'demo-token', user: ME, tenant: TENANT }); });
    on('POST', '/logout', function () { return ok({}); });
    on('GET', '/me', function () { return ok({ user: ME, tenant: TENANT }); });
    on('GET', '/accounts', function () {
        return ok(ACCOUNTS.map(function (a) {
            var n = CONVS.filter(function (c) { return c.account_id === a.id; }).reduce(function (t, c) { return t + unreadOf(c); }, 0);
            return Object.assign({}, a, { unread: n });
        }));
    });
    on('GET', '/agents', function () {
        return ok(AGENTS.map(function (a) { return Object.assign({}, a, { open: CONVS.filter(function (c) { return c.assigned_to === a.id; }).length }); }));
    });
    on('GET', '/conversations', function (r) {
        var q = (r.q.q || '').toLowerCase(), list = CONVS.filter(function (c) {
            if (r.q.account_id && c.account_id !== +r.q.account_id) return false;
            if (r.q.filter === 'mine' && c.assigned_to !== ME.id) return false;
            if (r.q.filter === 'free' && c.assigned_to) return false;
            if (r.q.filter === 'unread' && !unreadOf(c)) return false;
            if (q) { var l = lastMsg(c); return (c.contact.name + ' ' + c.contact.phone + ' ' + (l ? l.body : '')).toLowerCase().indexOf(q) >= 0; }
            return true;
        }).sort(function (a, b) { var x = lastMsg(a), y = lastMsg(b); return Date.parse(y ? y.at : 0) - Date.parse(x ? x.at : 0); });
        return { s: 200, b: { data: list.map(row), meta: { current_page: 1, last_page: 1, total: list.length } } };
    });
    on('POST', '/conversations/new', function (r) {
        var b = r.body, name = (b.name || '').trim() || b.phone, c = { id: ++convSeq, code: 'n' + convSeq, account_id: +b.account_id, contact: { id: 600 + convSeq, name: name, phone: b.phone }, assigned_to: 1, unread: 0, script: [], locations: [], orders: [], charges: [], crm: crm({ id: 950 + convSeq, first: name, last: '' }), items: [] };
        CONVS.push(c); progress(push(c, M('outbound', b.body, nowIso(), { status: 'sent' })));
        return ok(row(c), 202);
    });
    on('GET', '/conversations/code/([^/]+)', function (r) { var c = CONVS.filter(function (x) { return x.code === r.p[1]; })[0]; return c ? ok(row(c)) : NOT_FOUND; });
    on('GET', '/conversations/(\\d+)/messages', function (r) {
        var c = conv(r.p[1]); if (!c) return NOT_FOUND;
        var after = r.q.after ? Date.parse(r.q.after) : 0;
        return ok(c.items.filter(function (i) { return !after || Date.parse(i.at) > after; }).slice(-100));
    });
    on('POST', '/conversations/(\\d+)/read', function (r) { var c = conv(r.p[1]); if (c) c.unread = 0; return ok({}); });
    on('POST', '/conversations/(\\d+)/reply', function (r) {
        var c = conv(r.p[1]); if (!c) return NOT_FOUND;
        var quote = null;
        if (r.body.reply_to) {
            var q = c.items.filter(function (i) { return i.id === 'm' + r.body.reply_to; })[0];
            if (q) quote = { id: q.id, direction: q.direction, body: cut(q.body || (q.media_type ? '[' + q.media_type + ']' : ''), 140) };
        }
        if (!c.assigned_to) c.assigned_to = ME.id;
        var m = push(c, M('outbound', r.body.body, nowIso(), { status: 'sent', quote: quote })); progress(m); customerReplies(c);
        return ok(m);
    });
    on('POST', '/conversations/(\\d+)/note', function (r) { var c = conv(r.p[1]); if (!c) return NOT_FOUND; return ok({ id: push(c, N(r.body.body, ME, nowIso())).id }); });
    on('POST', '/conversations/(\\d+)/delegate', function (r) {
        var c = conv(r.p[1]); if (!c) return NOT_FOUND;
        c.assigned_to = r.body.agent_id || null;
        push(c, E('delegated', r.body.note || null, ME, nowIso(), { to: c.assigned_to ? agent(c.assigned_to) : null }));
        return ok({ assigned_to: c.assigned_to ? agent(c.assigned_to) : null });
    });
    on('POST', '/conversations/(\\d+)/poll', function (r) {
        var c = conv(r.p[1]); if (!c) return NOT_FOUND;
        if (!c.assigned_to) c.assigned_to = ME.id;
        var m = push(c, M('outbound', '📊 ' + r.body.question + '\n' + r.body.options.map(function (o) { return '• ' + o; }).join('\n'), nowIso(), { status: 'sent', type: 'poll' })); progress(m);
        return ok(m, 201);
    });
    on('POST', '/conversations/(\\d+)/location', function (r) {
        var c = conv(r.p[1]); if (!c) return NOT_FOUND;
        var m = push(c, M('outbound', 'Ubicación: ' + r.body.latitude.toFixed(6) + ', ' + r.body.longitude.toFixed(6), nowIso(), { status: 'sent', type: 'location' })); progress(m);
        return ok(m, 201);
    });
    on('POST', '/conversations/(\\d+)/attachment', function (r) {
        var c = conv(r.p[1]); if (!c) return NOT_FOUND;
        var f = r.form.get('file'), t = f.type || '', kind = t.indexOf('image/') === 0 ? 'image' : t.indexOf('video/') === 0 ? 'video' : t.indexOf('audio/') === 0 ? 'audio' : 'document';
        return blobToDataUri(f).then(function (uri) {
            if (!c.assigned_to) c.assigned_to = ME.id;
            var m = push(c, M('outbound', r.form.get('caption') || '', nowIso(), { status: 'sent', type: kind, media_type: kind, media_url: uri, file_name: f.name })); progress(m);
            return ok(m, 201);
        });
    });
    on('GET', '/quick-replies', function () { return ok(QUICK); });
    on('POST', '/quick-replies/(\\d+)/use', function (r) { QUICK.forEach(function (q) { if (q.id === +r.p[1]) q.uses_count++; }); return ok({}); });

    // --- CRM ---
    function withCrm(fn) {
        return function (r) {
            var c = conv(r.p[1]); if (!c) return NOT_FOUND;
            if (!c.crm || acct(c.account_id).platform !== 'whatsapp') return fail('no_contact', 'El CRM solo aplica a chats de WhatsApp con teléfono.', 422);
            return fn(c, c.crm, r) || ok(panel(c));
        };
    }
    function ev(c, type, body) { push(c, E(type, body, ME, nowIso())); }
    on('GET', '/conversations/(\\d+)/crm', function (r) { var c = conv(r.p[1]); return c ? ok(panel(c)) : NOT_FOUND; });
    on('POST', '/conversations/(\\d+)/crm/contact', withCrm(function (c, k, r) {
        var f = (r.body.first_name || '').trim(), l = (r.body.last_name || '').trim();
        if (!f && !l) return fail('name_required', 'Escribe al menos un nombre o apellido.', 422);
        k.first = f; k.last = l; k.email = (r.body.email || '').trim(); ev(c, 'contact', 'Datos del contacto actualizados');
    }));
    on('POST', '/conversations/(\\d+)/crm/lists', withCrm(function (c, k, r) {
        var id = +r.body.list_id, i = k.lists.indexOf(id);
        if (r.body.on && i < 0) k.lists.push(id); else if (!r.body.on && i >= 0) k.lists.splice(i, 1);
    }));
    on('POST', '/conversations/(\\d+)/crm/ticket', withCrm(function (c, k, r) {
        var d = DEPTS.filter(function (x) { return x.id === +r.body.department_id; })[0] || DEPTS[0], num = 'TCK-' + pad(++ticketSeq, 6);
        k.ticket = { id: ticketSeq, number: num, subject: r.body.subject || 'Chat con ' + c.contact.name, status: 'open', priority: 'normal', department: d.name, assignee: ME };
        ev(c, 'ticket', 'Ticket ' + num + ' creado (' + d.name + ')');
    }));
    on('POST', '/conversations/(\\d+)/crm/ticket/(\\d+)', withCrm(function (c, k, r) {
        if (!k.ticket) return NOT_FOUND;
        k.ticket.status = r.body.status;
        ev(c, 'ticket', 'Ticket ' + k.ticket.number + ' → ' + ({ open: 'Abierto', pending: 'En espera', resolved: 'Resuelto', closed: 'Cerrado' }[r.body.status] || r.body.status));
    }));
    function startDeal(c, k) {
        k.deal = { id: ++dealSeq, title: 'Negocio · ' + c.contact.name, value: 0, currency: 'BOB', stage_id: 1, status: 'open' };
        ev(c, 'deal', 'Negocio creado: ' + k.deal.title);
    }
    on('POST', '/conversations/(\\d+)/crm/lead/convert', withCrm(function (c, k) { if (k.lead) k.lead.converted = true; startDeal(c, k); }));
    on('POST', '/conversations/(\\d+)/crm/lead', withCrm(function (c, k, r) {
        if (!k.lead) { k.lead = { id: 50 + convSeq, status: r.body.status || 'new', converted: false }; ev(c, 'lead', 'Lead creado desde este chat'); }
        else if (r.body.status) { k.lead.status = r.body.status; ev(c, 'lead', 'Lead marcado como ' + ({ new: 'nuevo', contacted: 'contactado', qualified: 'calificado', disqualified: 'descartado' }[r.body.status] || r.body.status)); }
    }));
    on('POST', '/conversations/(\\d+)/crm/deal/create', withCrm(function (c, k) { startDeal(c, k); }));
    on('POST', '/conversations/(\\d+)/crm/deal', withCrm(function (c, k, r) {
        var st = STAGES.filter(function (s) { return s.id === +r.body.stage_id; })[0]; if (!st || !k.deal) return NOT_FOUND;
        k.deal.stage_id = st.id; k.deal.status = st.is_won ? 'won' : st.is_lost ? 'lost' : 'open'; ev(c, 'deal', 'Negocio movido a «' + st.name + '»');
    }));
    on('POST', '/conversations/(\\d+)/crm/collections/(\\d+)/qr', withCrm(function (c, k, r) {
        var it = k.collections.filter(function (x) { return x.id === +r.p[2]; })[0]; if (!it) return NOT_FOUND;
        ev(c, 'charge', 'Cobro QR de ' + bs(it.amount) + ' enviado · ' + it.concept);
        sendQr(c, 'Escanea para pagar · ' + bs(it.amount) + ' · ' + it.concept, 'col-' + it.id);
        settle(c, function () { k.collections = k.collections.filter(function (x) { return x.id !== it.id; }); }, 'Pago recibido: ' + bs(it.amount) + ' · ' + it.concept, '¡Recibimos tu pago, gracias! Coordinamos la entrega ☕');
    }));

    // --- cobro rápido ---
    on('GET', '/conversations/(\\d+)/pay', function (r) { var c = conv(r.p[1]); return c ? ok(payState(c)) : NOT_FOUND; });
    on('POST', '/conversations/(\\d+)/pay/charge', function (r) {
        var c = conv(r.p[1]); if (!c) return NOT_FOUND;
        var b = r.body, ch = { id: ++chargeSeq, amount: b.amount, currency: 'BOB', description: b.description, status: 'pending', due_at: dateStr(b.days || 1), created_at: nowIso() };
        c.charges.push(ch); if (!c.assigned_to) c.assigned_to = ME.id;
        push(c, E('charge', 'Cobro QR de ' + bs(b.amount) + ' emitido · ' + b.description, ME, nowIso()));
        sendQr(c, 'Escanea para pagar · ' + bs(b.amount) + ' · ' + b.description, 'ch-' + ch.id);
        settle(c, function () { ch.status = 'paid'; }, 'Pago recibido: ' + bs(b.amount) + ' · ' + b.description, b.notify_on_paid ? '¡Recibimos tu pago, gracias! ☕' : null);
        return ok(payState(c));
    });

    // --- tienda ---
    on('GET', '/shop/products', function (r) {
        var q = (r.q.q || '').toLowerCase(), list = PRODUCTS.filter(function (p) { return !q || p.name.toLowerCase().indexOf(q) >= 0; })
            .map(function (p) { return Object.assign({ has_variants: false, variants: [], has_price_range: false, stock: null }, p); });
        return { s: 200, b: { data: list, meta: { current_page: 1, last_page: 1, total: list.length } } };
    });
    on('GET', '/conversations/(\\d+)/shop', function (r) { var c = conv(r.p[1]); return c ? ok(shopState(c)) : NOT_FOUND; });
    on('POST', '/conversations/(\\d+)/shop/order', function (r) {
        var c = conv(r.p[1]); if (!c) return NOT_FOUND;
        var b = r.body, lines = (b.items || []).map(function (i) {
            var p = PRODUCTS.filter(function (x) { return x.id === i.product_id; })[0], v = p && i.variant_id ? p.variants.filter(function (x) { return x.id === i.variant_id; })[0] : null;
            return p ? { name: p.name + (v ? ' (' + v.label + ')' : ''), price: v ? v.price : p.price, qty: i.quantity } : null;
        }).filter(Boolean);
        if (!lines.length) return fail('empty', 'El pedido no tiene productos.', 422);
        var qr = +b.payment_gateway_id === 1, total = lines.reduce(function (t, l) { return t + l.price * l.qty; }, 0);
        var o = { id: ++orderSeq, number: 'ORD-' + pad(orderSeq, 6), status: qr ? 'awaiting_payment' : 'pending', total: total, currency: 'BOB', items: lines.reduce(function (t, l) { return t + l.qty; }, 0), created_at: nowIso(), _lines: lines };
        c.orders.push(o); if (!c.assigned_to) c.assigned_to = ME.id;
        push(c, E('order', 'Pedido ' + o.number + ' creado · ' + bs(total), ME, nowIso()));
        agentSays(c, orderSummary(o, lines));
        if (qr) {
            sendQr(c, 'Escanea para pagar · ' + bs(total), o.number);
            settle(c, function () { o.status = 'paid'; }, 'Pago recibido: ' + bs(total) + ' · Pedido ' + o.number, b.notify_on_paid ? '¡Recibimos tu pago, gracias! Preparamos tu pedido y sale hoy ☕' : null);
        } else agentSays(c, 'Pagas en efectivo al recibir el pedido. Te avisamos cuando salga.');
        return ok(shopState(c), 201);
    });
    on('POST', '/conversations/(\\d+)/shop/orders/(\\d+)/(resend|cancel)', function (r) {
        var c = conv(r.p[1]); if (!c) return NOT_FOUND;
        var o = c.orders.filter(function (x) { return x.id === +r.p[2]; })[0]; if (!o) return NOT_FOUND;
        if (r.p[3] === 'cancel') { o.status = 'cancelled'; push(c, E('order', 'Pedido ' + o.number + ' cancelado', ME, nowIso())); }
        else agentSays(c, orderSummary(o, o._lines));
        return ok(shopState(c));
    });
    on('POST', '/conversations/(\\d+)/shop/products/(\\d+)/card', function (r) {
        var c = conv(r.p[1]), p = PRODUCTS.filter(function (x) { return x.id === +r.p[2]; })[0]; if (!c || !p) return NOT_FOUND;
        agentSays(c, '*' + p.name + '*\n' + (p.has_price_range ? 'Desde ' : '') + bs(p.price) + '\nhttps://altiplano.example/tienda/' + p.id, { status: 'sent', type: 'image', media_type: 'image', media_url: p.image_url });
        return ok({});
    });

    // ---------- fetch simulado ----------
    var realFetch = window.fetch ? window.fetch.bind(window) : null;
    window.fetch = function (input, opts) {
        var url = typeof input === 'string' ? input : input.url;
        if (url.indexOf(API) !== 0) return realFetch ? realFetch(input, opts) : Promise.reject(new TypeError('offline'));
        opts = opts || {};
        var rest = url.slice(API.length), qi = rest.indexOf('?'), path = qi >= 0 ? rest.slice(0, qi) : rest, method = (opts.method || 'GET').toUpperCase();
        var query = {}; if (qi >= 0) new URLSearchParams(rest.slice(qi + 1)).forEach(function (v, k) { query[k] = v; });
        var req = { q: query, body: {}, form: null, p: null };
        if (opts.body instanceof FormData) req.form = opts.body; else if (opts.body) { try { req.body = JSON.parse(opts.body); } catch (e) { req.body = {}; } }
        var out = NOT_FOUND;
        for (var i = 0; i < routes.length; i++) {
            var rt = routes[i]; if (rt.m !== method) continue;
            var mt = rt.re.exec(path); if (!mt) continue;
            req.p = mt; out = rt.fn(req); break;
        }
        return new Promise(function (resolve) { setTimeout(resolve, 90 + Math.random() * 140); })
            .then(function () { return out; })
            .then(function (res) { return new Response(JSON.stringify(res.b), { status: res.s, headers: { 'Content-Type': 'application/json' } }); });
    };

    // ---------- vida propia del demo ----------
    setTimeout(function () { var c = conv(104); push(c, M('inbound', 'Y si no tienen descafeinado, ¿cuál me recomiendan? Que sea suave 🙏', nowIso())); c.unread++; }, 16000);
    setTimeout(function () {
        var c = { id: ++convSeq, code: 'fter9', account_id: 1, contact: { id: 509, name: 'Fabiola Terrazas', phone: '+591 60123987' }, assigned_to: null, unread: 1, script: ['Somos una cafetería pequeña, unos 6 kg al mes.'], locations: [], orders: [], charges: [], crm: crm({ id: 909, first: 'Fabiola', last: 'Terrazas' }),
            items: [M('inbound', 'Hola, ¿venden por mayor a cafeterías en Cochabamba?', nowIso())] };
        CONVS.push(c);
    }, 52000);

    // ---------- soporte del navegador (demo sin permisos ni servicios) ----------
    window.AltiplanoDemo = { conv: conv, wav: function () { return wavBlob(2); } };
})();
