import json, os
D = os.path.dirname(os.path.abspath(__file__))
TPL = open(os.path.join(D, '_form-guide.tpl'), encoding='utf-8').read()
DEF_LOCK = ''

def f(id, label, type='text', t=None, b=None, tip=None, ex=None, **kw):
    d = {'id': id, 'label': label, 'type': type}
    if t:
        d['s'] = {'t': t, 'b': b or '', 'tip': tip or ''}
    if ex is not None:
        d['ex'] = ex
    d.update(kw)
    return d

SUC = [['1', 'Sucursal Centro'], ['2', 'Sucursal Sur']]
SRV = [['1', 'Corte de cabello'], ['2', 'Consulta general']]
WRK = [['1', 'Ana Flores'], ['2', 'Luis Rojas']]
WEEK = [[1, '09:00', '13:00'], [1, '15:00', '19:00']]

GUIDES = {}

GUIDES['guia-office-bookings'] = dict(
    title='Nueva reserva', article='office-reservas', sort=10, crumb='/backend/aero/office/bookings/create',
    updTitle='Editar reserva', crumbUpd='/backend/aero/office/bookings/update/12',
    lock='Al crear, la reserva queda en el estado inicial que elijas. Después de guardar, solo se editan las notas internas; el resto se gestiona desde la agenda.',
    lockUpdate='Al editar solo queda disponible «Notas internas». Cambios de estado, horario o cancelaciones se hacen desde la agenda y la lista de reservas.',
    saveBody='Pulsa Crear. La demo valida los campos obligatorios y te lleva a la pantalla de edición.',
    saveTip='En la app real, el sistema valida la hora contra horarios, ausencias y reservas existentes; si choca, no se guarda.',
    doneMsg='Reserva creada. Ahora estás en la pantalla de edición.', doneClose='Reserva creada. En la app real vuelves a la lista de reservas.',
    fields=[
        f('customer_id', 'Cliente existente', 'select', 'Elige un cliente o crea uno nuevo',
          'Si el cliente ya está registrado, selecciónalo. Si lo dejas vacío, aparecen los campos para dar de alta un cliente nuevo en el mismo paso.',
          'Si el cliente ya existe y lo escribes como nuevo, tendrás duplicados. Búscalo primero en la lista.', '1',
          ctx='create', dyn=True, empty='— Nuevo cliente (completa los datos de abajo) —', opts=[['1', 'María Quispe'], ['2', 'Carlos Mamani']]),
        f('new_name', 'Nombre del cliente nuevo', 'text', 'Nombre del cliente nuevo', 'Nombre con el que se registrará al cliente nuevo.', '', 'Rosa Choque', ctx='create', show='customer_id'),
        f('new_phone', 'Teléfono', 'text', 'Teléfono del cliente nuevo', 'Teléfono de contacto del cliente nuevo.', '', '70012345', ctx='create', show='customer_id'),
        f('new_email', 'Correo', 'text', 'Correo del cliente nuevo', 'Correo de contacto del cliente nuevo.', '', 'rosa@ejemplo.com', ctx='create', show='customer_id', email=True),
        f('branch_id', 'Sucursal', 'select', 'Elige la sucursal', 'Dónde se atenderá la reserva.', 'Solo aparecen sucursales activas.', '1',
          ctx='create', req=True, dyn=True, empty='— Elegir —', opts=SUC),
        f('service_id', 'Servicio', 'select', 'Elige el servicio', 'Qué se va a realizar. Define la duración y el precio de la reserva.', 'El precio del servicio se copia a la reserva en este momento.', '1',
          ctx='create', req=True, dyn=True, empty='— Elegir —', opts=SRV),
        f('worker_id', 'Profesional', 'select', 'Elige el profesional (opcional)', 'Quién atenderá. Si lo dejas en «Cualquiera disponible», el sistema asigna uno libre.', '', '',
          ctx='create', dyn=True, empty='— Cualquiera disponible —', opts=WRK),
        f('starts_at', 'Fecha y hora', 'datetime', 'Fecha y hora de la reserva', 'Cuándo empieza la atención.', 'Se valida contra horarios, ausencias y reservas existentes.', '2026-10-20T10:00',
          ctx='create', req=True, help='Se valida contra horarios, ausencias y reservas existentes.'),
        f('status', 'Estado inicial', 'select', 'Estado inicial', 'Confirmada deja la reserva firme; Pendiente espera tu aprobación.', 'Por defecto la reserva se crea Confirmada.', 'confirmed',
          ctx='create', def='confirmed', opts=[['pending', 'Pendiente'], ['confirmed', 'Confirmada'], ['in_service', 'En atención'], ['completed', 'Completada'], ['cancelled', 'Cancelada'], ['rejected', 'Rechazada'], ['no_show', 'No asistió']]),
        f('customer_notes', 'Notas del cliente', 'textarea', 'Notas del cliente (opcional)', 'Lo que el cliente pidió o aclaró al reservar.', '', 'Prefiere atención por la tarde.', ctx='create'),
        f('internal_notes', 'Notas internas', 'textarea', 'Notas internas (opcional)', 'Observaciones para el personal.', 'Solo las ve el personal, nunca el cliente.', 'Cliente frecuente.', help='Solo las ve el personal.'),
    ])

GUIDES['guia-office-branches'] = dict(
    title='Nueva sucursal', article='office-sucursales', sort=20, crumb='/backend/aero/office/branches/create',
    updTitle='Editar sucursal', crumbUpd='/backend/aero/office/branches/update/3',
    lock='', lockUpdate='',
    saveBody='Pulsa Crear. La demo valida el nombre y los turnos del horario.',
    saveTip='El horario semanal de la sucursal limita cuándo se puede reservar; los horarios individuales de los profesionales se aplican dentro de él.',
    doneMsg='Sucursal creada. Ahora estás en la pantalla de edición.', doneClose='Sucursal creada. En la app real vuelves a la lista de sucursales.',
    fields=[
        f('name', 'Nombre', 'text', 'Nombre de la sucursal', 'Cómo verán el nombre tu equipo y tus clientes.', 'Máximo 255 caracteres.', 'Sucursal Centro', req=True, max=255),
        f('phone', 'Teléfono', 'text', 'Teléfono (opcional)', 'Teléfono de contacto de la sucursal.', '', '2 2123456'),
        f('address', 'Dirección', 'text', 'Dirección (opcional)', 'Dirección física de la sucursal.', '', 'Av. 6 de Agosto 1234', span='full'),
        f('lat', 'Latitud', 'text', 'Latitud (opcional)', 'Coordenada de latitud de la ubicación.', '', '-16.5000'),
        f('lng', 'Longitud', 'text', 'Longitud (opcional)', 'Coordenada de longitud de la ubicación.', '', '-68.1500'),
        f('is_active', 'Activa', 'switch', 'Sucursal activa', 'Una sucursal activa se puede elegir al reservar.', 'Desactivarla la oculta del portal; sus reservas anteriores se conservan.', True,
          def=True, help='Desactivarla la oculta del portal; sus reservas anteriores se conservan.'),
        f('hours', 'Horario semanal', 'repeater', 'Horario semanal', 'Agrega un turno por fila: día, hora de inicio y hora de fin.',
          'Dos turnos el mismo día = descanso entre ambos (p. ej. 09:00–13:00 y 15:00–19:00).', WEEK, prompt='Agregar turno',
          help='Un turno por fila. Dos turnos el mismo día = descanso entre ambos (p. ej. 09:00–13:00 y 15:00–19:00).'),
        f('services', 'Servicios que se ofrecen aquí', 'checks', 'Servicios que se ofrecen aquí', 'Marca los servicios que se prestan en esta sucursal.', '', [0], opts=[x[1] for x in SRV]),
        f('workers', 'Profesionales que atienden aquí', 'checks', 'Profesionales que atienden aquí', 'Marca los profesionales que trabajan en esta sucursal.', '', [0], opts=[x[1] for x in WRK]),
    ])

GUIDES['guia-office-customers'] = dict(
    title='Nuevo cliente', article='office-clientes', sort=30, crumb='/backend/aero/office/customers/create',
    updTitle='Editar cliente', crumbUpd='/backend/aero/office/customers/update/8',
    lock='', lockUpdate='',
    saveBody='Pulsa Crear. La demo valida el nombre y el formato del correo.',
    saveTip='Los clientes también se crean solos cuando alguien reserva desde el portal público.',
    doneMsg='Cliente creado. Ahora estás en la pantalla de edición.', doneClose='Cliente creado. En la app real vuelves a la lista de clientes.',
    fields=[
        f('name', 'Nombre', 'text', 'Nombre del cliente', 'Nombre completo o razón social.', 'Máximo 255 caracteres.', 'María Quispe', req=True, max=255),
        f('document', 'Documento', 'text', 'Documento (opcional)', 'CI, NIT u otro documento de identidad.', '', '1234567 LP'),
        f('phone', 'Teléfono', 'text', 'Teléfono (opcional)', 'Teléfono o WhatsApp de contacto.', '', '70012345'),
        f('email', 'Correo', 'text', 'Correo (opcional)', 'Correo de contacto.', 'Si lo escribes, debe tener formato de correo válido.', 'maria@ejemplo.com', email=True, max=255),
        f('notes', 'Observaciones generales', 'textarea', 'Observaciones generales (opcional)', 'Notas sobre el cliente que le sirven a tu equipo.', '', 'Prefiere atención por la mañana.'),
    ])

GUIDES['guia-office-services'] = dict(
    title='Nuevo servicio', article='office-servicios', sort=40, crumb='/backend/aero/office/services/create',
    updTitle='Editar servicio', crumbUpd='/backend/aero/office/services/update/5',
    lock='', lockUpdate='',
    saveBody='Pulsa Crear. La demo valida nombre, duración, descanso y precio.',
    saveTip='Sin sucursales ni profesionales asignados, el servicio no se podrá reservar.',
    doneMsg='Servicio creado. Ahora estás en la pantalla de edición.', doneClose='Servicio creado. En la app real vuelves a la lista de servicios.',
    fields=[
        f('name', 'Nombre', 'text', 'Nombre del servicio', 'Cómo lo verán tus clientes al reservar.', 'Máximo 255 caracteres.', 'Corte de cabello', req=True, max=255),
        f('category', 'Categoría', 'text', 'Categoría (opcional)', 'Agrupa servicios parecidos.', '', 'Peluquería'),
        f('description', 'Descripción', 'textarea', 'Descripción (opcional)', 'Texto breve que explica el servicio.', '', 'Corte y peinado, incluye lavado.'),
        f('duration_minutes', 'Duración (minutos)', 'number', 'Duración', 'Cuánto dura la atención, en minutos.', 'Debe estar entre 5 y 1440 minutos.', '45', req=True, def='30', min=5, maxn=1440),
        f('buffer_minutes', 'Descanso posterior (minutos)', 'number', 'Descanso posterior (opcional)', 'Tiempo de preparación entre una atención y la siguiente.', 'Entre 0 y 240 minutos.', '10', def='0', min=0, maxn=240,
          help='Tiempo de preparación entre una atención y la siguiente.'),
        f('price', 'Precio', 'number', 'Precio', 'Precio del servicio.', 'Se copia a cada reserva; cambiarlo no altera las ya hechas.', '80', def='0', min=0, step='0.01',
          help='Se copia a cada reserva; cambiarlo no altera las ya hechas.'),
        f('requires_approval', 'Requiere aprobación', 'switch', 'Requiere aprobación', 'Si lo activas, las reservas públicas quedan pendientes hasta que las apruebes.', 'Apagado = las reservas públicas quedan confirmadas al instante.', False,
          help='Apagado = las reservas públicas quedan confirmadas al instante.'),
        f('is_active', 'Activo', 'switch', 'Servicio activo', 'Un servicio activo se puede reservar.', '', True, def=True),
        f('is_public', 'Visible en el portal público', 'switch', 'Visible en el portal público', 'Controla si los clientes lo ven al reservar en línea.', 'Oculto = solo se reserva manualmente desde el panel.', True, def=True,
          help='Oculto = solo se reserva manualmente desde el panel.'),
        f('branches', 'Sucursales', 'checks', 'Sucursales', 'Marca dónde se ofrece este servicio.', '', [0], opts=[x[1] for x in SUC]),
        f('workers', 'Profesionales que lo realizan', 'checks', 'Profesionales que lo realizan', 'Marca quiénes pueden prestar este servicio.', '', [0, 1], opts=[x[1] for x in WRK]),
    ])

GUIDES['guia-office-timeoffs'] = dict(
    title='Nueva ausencia', article='office-ausencias-feriados', sort=50, crumb='/backend/aero/office/timeoffs/create',
    updTitle='Editar ausencia', crumbUpd='/backend/aero/office/timeoffs/update/4',
    lock='', lockUpdate='',
    saveBody='Pulsa Crear. La demo valida que «Hasta» sea posterior a «Desde».',
    saveTip='Mientras dure la ausencia, el sistema no ofrecerá esas horas para reservar.',
    doneMsg='Ausencia creada. Ahora estás en la pantalla de edición.', doneClose='Ausencia creada. En la app real vuelves a la lista de ausencias.',
    fields=[
        f('type', 'Tipo', 'select', 'Tipo de ausencia', 'Clasifica el bloqueo: feriado o cierre, vacaciones, descanso o ausencia.', '', 'vacation', def='absence',
          opts=[['holiday', 'Feriado / cierre'], ['vacation', 'Vacaciones'], ['break', 'Descanso'], ['absence', 'Ausencia']]),
        f('reason', 'Motivo', 'text', 'Motivo (opcional)', 'Nota breve para tu equipo.', '', 'Vacaciones de fin de año'),
        f('worker_id', 'Profesional', 'select', 'Profesional (opcional)', 'A quién aplica la ausencia.', 'Vacío = aplica a todo el negocio (feriado, cierre).', '1', dyn=True,
          empty='— Todo el negocio —', opts=WRK, help='Vacío = aplica a todo el negocio (feriado, cierre).'),
        f('branch_id', 'Sucursal', 'select', 'Sucursal (opcional)', 'Limita la ausencia a una sucursal.', '', '', dyn=True, empty='— Todas las sucursales —', opts=SUC),
        f('starts_at', 'Desde', 'datetime', 'Desde cuándo', 'Fecha y hora de inicio.', '', '2026-12-24T00:00', req=True),
        f('ends_at', 'Hasta', 'datetime', 'Hasta cuándo', 'Fecha y hora de fin.', 'Debe ser posterior a «Desde».', '2026-12-26T23:59', req=True, after='starts_at'),
    ])

GUIDES['guia-office-workers'] = dict(
    title='Nuevo profesional', article='office-profesionales', sort=60, crumb='/backend/aero/office/workers/create',
    updTitle='Editar profesional', crumbUpd='/backend/aero/office/workers/update/6',
    lock='', lockUpdate='',
    saveBody='Pulsa Crear. La demo valida nombre, correo y turnos del horario.',
    saveTip='Un profesional sin sucursales ni servicios asignados no aparecerá al reservar.',
    doneMsg='Profesional creado. Ahora estás en la pantalla de edición.', doneClose='Profesional creado. En la app real vuelves a la lista de profesionales.',
    fields=[
        f('name', 'Nombre', 'text', 'Nombre del profesional', 'Nombre con el que aparece en la agenda y en el portal.', 'Máximo 255 caracteres.', 'Ana Flores', req=True, max=255),
        f('title', 'Cargo / especialidad', 'text', 'Cargo o especialidad (opcional)', 'Su función o especialidad.', '', 'Estilista senior'),
        f('phone', 'Teléfono', 'text', 'Teléfono (opcional)', 'Teléfono de contacto.', '', '70098765'),
        f('email', 'Correo', 'text', 'Correo (opcional)', 'Correo de contacto.', 'Si lo escribes, debe tener formato de correo válido.', 'ana@ejemplo.com', email=True, max=255),
        f('user_id', 'Cuenta de acceso al panel', 'select', 'Cuenta de acceso al panel (opcional)', 'Vincula al profesional con un usuario del panel.',
          'Con cuenta (rol Profesional) verá solo sus reservas; sin cuenta solo figura en la agenda.', '1', dyn=True, empty='', opts=[['1', 'ana@ejemplo.com']],
          help='Opcional. Con cuenta (rol Profesional) verá solo sus reservas; sin cuenta solo figura en la agenda.'),
        f('bio', 'Presentación', 'textarea', 'Presentación (opcional)', 'Texto breve que se muestra al elegir profesional.', '', 'Diez años de experiencia en color y corte.'),
        f('is_active', 'Activo', 'switch', 'Profesional activo', 'Un profesional activo recibe reservas.', '', True, def=True),
        f('is_public', 'Visible para elegir en el portal', 'switch', 'Visible para elegir en el portal', 'Los clientes podrán elegirlo al reservar en línea.', '', True, def=True),
        f('hours', 'Horario individual', 'repeater', 'Horario individual (opcional)', 'Turnos propios del profesional: día, hora de inicio y hora de fin.',
          'Vacío = sigue el horario de la sucursal. Si se llena, se aplica dentro del horario de la sucursal.', WEEK, prompt='Agregar turno',
          help='Vacío = sigue el horario de la sucursal. Si se llena, se aplica dentro del horario de la sucursal. Dos turnos el mismo día = descanso entre ambos (p. ej. 09:00–13:00 y 15:00–19:00).'),
        f('branches', 'Sucursales donde atiende', 'checks', 'Sucursales donde atiende', 'Marca en qué sucursales trabaja.', '', [0], opts=[x[1] for x in SUC]),
        f('services', 'Servicios que realiza', 'checks', 'Servicios que realiza', 'Marca qué servicios presta.', '', [0], opts=[x[1] for x in SRV]),
    ])

for slug, g in GUIDES.items():
    meta = json.dumps({'title': g['title'], 'article': g['article'], 'sort': g['sort']}, ensure_ascii=False)
    cfg = {k: g[k] for k in ('title', 'crumb', 'updTitle', 'crumbUpd', 'lock', 'lockUpdate', 'saveBody', 'saveTip', 'doneMsg', 'doneClose', 'fields')}
    # user_id: select sin opción vacía real si empty == ''
    for fld in cfg['fields']:
        if fld.get('empty') == '':
            fld['empty'] = '— Sin cuenta —'
    html = TPL.replace('__META__', meta).replace('__TITLE__', g['title']).replace('__CFG__', json.dumps(cfg, ensure_ascii=False).replace('</', '<\\/'))
    open(os.path.join(D, slug + '.html'), 'w', encoding='utf-8').write(html)
    print('ok', slug)
