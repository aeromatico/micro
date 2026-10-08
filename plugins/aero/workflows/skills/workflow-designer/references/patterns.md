# Patrones de diseño de flujos

Cada patrón usa solo nodos que existen en `nodes.md` y **se valida automáticamente** (si un patrón deja de ser válido, la prueba del plugin falla). Los IDs de nodo son ejemplos; cualquier id único sirve. Los patrones 3 a 6 son workflows completos (`trigger_type`, `trigger_config`, `graph`).

## 1. Consulta de catálogo (tool de IA, solo lectura)

Para cuando la IA necesita responder "¿qué tienen?" o "¿cuánto cuesta X?".

```json
{
  "nodes": [
    {"id": "n1", "type": "trigger.manual", "data": {}},
    {"id": "n2", "type": "shop.products", "data": {
      "category_id": "16", "query": "{{ trigger.query }}", "limit": "10",
      "only_in_stock": "0", "show_description": "1", "save_as": "productos"}},
    {"id": "n3", "type": "action.respond", "data": {"value": "{{ vars.productos.text }}"}},
    {"id": "n4", "type": "action.respond", "data": {"value": "No hay productos disponibles para esa consulta."}}
  ],
  "edges": [
    {"id": "e1", "source": "n1", "target": "n2"},
    {"id": "e2", "source": "n2", "target": "n3", "sourceHandle": "found"},
    {"id": "e3", "source": "n2", "target": "n4", "sourceHandle": "empty"},
    {"id": "e4", "source": "n2", "target": "n4", "sourceHandle": "not_found"}
  ]
}
```

Notas:
- `shop.products` busca **dentro de una categoría**. Si el encargo es de todo el catálogo, usa la tool `shop_products_list`, no un flujo.
- **Cada salida debe estar conectada.** Si `empty` o `not_found` no tienen destino, el flujo termina sin responder y el validador lo rechaza. Aquí las dos van a una respuesta común.

## 2. Decisión con condición

Para cuando el resultado depende de un dato (por ejemplo, un monto o un plan).

```json
{
  "nodes": [
    {"id": "n1", "type": "trigger.manual", "data": {}},
    {"id": "n2", "type": "logic.condition", "data": {"left": "{{ trigger.monto }}", "op": "gt", "right": "100"}},
    {"id": "n3", "type": "logic.set", "data": {"name": "nivel", "value": "grande"}},
    {"id": "n4", "type": "logic.set", "data": {"name": "nivel", "value": "regular"}},
    {"id": "n5", "type": "action.respond", "data": {"value": "Nivel: {{ vars.nivel }}"}}
  ],
  "edges": [
    {"id": "e1", "source": "n1", "target": "n2"},
    {"id": "e2", "source": "n2", "target": "n3", "sourceHandle": "true"},
    {"id": "e3", "source": "n2", "target": "n4", "sourceHandle": "false"},
    {"id": "e4", "source": "n3", "target": "n5"},
    {"id": "e5", "source": "n4", "target": "n5"}
  ]
}
```

Notas:
- Las dos ramas se **unen** en el mismo nodo final. Así el flujo siempre termina en una respuesta.
- Si una rama no necesita hacer nada, aun así conéctala a la unión.

## 3. Responder a un cliente que escribe (texto)

La forma más simple de atender: un mensaje con una palabra concreta y una respuesta. `action.reply` contesta al remitente por la misma cuenta, sin necesitar su teléfono. Sin mensaje entrante (prueba manual) solo muestra lo que diría.

```json
{
  "name": "Horarios de atención",
  "trigger_type": "message",
  "trigger_config": {"keyword": "horario"},
  "graph": {
    "nodes": [
      {"id": "n1", "type": "trigger.message", "data": {}},
      {"id": "n2", "type": "action.reply", "data": {"body": "Atendemos de lunes a viernes de 9:00 a 18:00. ¿Te ayudo con algo más?"}}
    ],
    "edges": [{"id": "e1", "source": "n1", "target": "n2"}]
  }
}
```

Notas: `keyword` acota el disparador; sin él responde a **todo** mensaje entrante.

## 4. Menú con botones + una rama por botón (varios workflows)

El flujo **no espera** la respuesta: lo que el cliente toca llega como un mensaje nuevo con `interactive_id`. Por eso son tres workflows: el que muestra los botones y uno por cada botón, filtrado por `interactive_id`. Requiere cuenta de API oficial y la ventana de 24 h (el cliente acaba de escribir).

**4A · Muestra el menú**

```json
{
  "name": "Bienvenida – menú",
  "trigger_type": "message",
  "trigger_config": {"keyword": "hola"},
  "graph": {
    "nodes": [
      {"id": "n1", "type": "trigger.message", "data": {}},
      {"id": "n2", "type": "hello.reply_buttons", "data": {
        "body": "¡Hola! ¿En qué te ayudo?",
        "buttons": "Ver precios | precios\nHablar con un asesor | asesor"}}
    ],
    "edges": [{"id": "e1", "source": "n1", "target": "n2"}]
  }
}
```

**4B · Atiende «Ver precios»**

```json
{
  "name": "Bienvenida – precios",
  "trigger_type": "message",
  "trigger_config": {"interactive_id": "precios"},
  "graph": {
    "nodes": [
      {"id": "n1", "type": "trigger.message", "data": {}},
      {"id": "n2", "type": "action.reply", "data": {"body": "Nuestros planes empiezan en Bs 99 al mes. ¿Quieres que te cuente cuál te conviene?"}}
    ],
    "edges": [{"id": "e1", "source": "n1", "target": "n2"}]
  }
}
```

**4C · Atiende «Hablar con un asesor»** (igual que 4B con `"interactive_id": "asesor"` y un texto como «Listo, un asesor te escribirá en unos minutos.»).

Notas: los ids (`precios`, `asesor`) se escriben en el botón con `Texto | id`. Si hay más de 3 opciones, usa el patrón 5.

## 5. Menú desplegable + un solo workflow que atiende todas las opciones

Cuando las opciones son muchas pero las respuestas son parecidas, un único workflow con Condiciones encadenadas ordena mejor que uno por opción. Cada Condición compara el id de la opción tocada.

**5A · Muestra el menú** (hasta 10 opciones; `Título | Descripción | id`, descripción e id opcionales)

```json
{
  "name": "Carta – menú",
  "trigger_type": "message",
  "trigger_config": {"keyword": "carta"},
  "graph": {
    "nodes": [
      {"id": "n1", "type": "trigger.message", "data": {}},
      {"id": "n2", "type": "hello.reply_list", "data": {
        "body": "Elige qué quieres ver:",
        "list_button": "Ver carta",
        "list_section": "Nuestra carta",
        "list_rows": "Pizzas | Desde Bs 40 | pizzas\nPastas | | pastas\nBebidas || bebidas"}}
    ],
    "edges": [{"id": "e1", "source": "n1", "target": "n2"}]
  }
}
```

**5B · Atiende cualquiera de las tres opciones**

```json
{
  "name": "Carta – respuestas",
  "trigger_type": "message",
  "trigger_config": {"interactive_id": ["pizzas", "pastas", "bebidas"]},
  "graph": {
    "nodes": [
      {"id": "n1", "type": "trigger.message", "data": {}},
      {"id": "n2", "type": "logic.condition", "data": {"left": "{{ trigger.data.0.provider_payload.interactive_id }}", "op": "eq", "right": "pizzas"}},
      {"id": "n3", "type": "action.reply", "data": {"body": "Pizzas: margarita Bs 40, pepperoni Bs 48, hawaiana Bs 45."}},
      {"id": "n4", "type": "logic.condition", "data": {"left": "{{ trigger.data.0.provider_payload.interactive_id }}", "op": "eq", "right": "pastas"}},
      {"id": "n5", "type": "action.reply", "data": {"body": "Pastas: boloñesa Bs 38, carbonara Bs 42."}},
      {"id": "n6", "type": "action.reply", "data": {"body": "Bebidas: gaseosa Bs 8, jugo natural Bs 12."}}
    ],
    "edges": [
      {"id": "e1", "source": "n1", "target": "n2"},
      {"id": "e2", "source": "n2", "target": "n3", "sourceHandle": "true"},
      {"id": "e3", "source": "n2", "target": "n4", "sourceHandle": "false"},
      {"id": "e4", "source": "n4", "target": "n5", "sourceHandle": "true"},
      {"id": "e5", "source": "n4", "target": "n6", "sourceHandle": "false"}
    ]
  }
}
```

Notas: la última rama `false` es la opción restante; las dos salidas de cada Condición están conectadas. Los precios del ejemplo son inventados: usa los reales de la persona.

## 6. Pedir la ubicación y comprobar la cobertura (delivery)

Dos workflows: uno pide la ubicación con el botón nativo de WhatsApp y otro procesa la que llega. La ubicación compartida entra como un mensaje cuyo texto empieza con 📍, así que el disparador la filtra con `keyword: "📍"`.

**6A · Pide la ubicación**

```json
{
  "name": "Delivery – pedir ubicación",
  "trigger_type": "message",
  "trigger_config": {"keyword": "delivery"},
  "graph": {
    "nodes": [
      {"id": "n1", "type": "trigger.message", "data": {}},
      {"id": "n2", "type": "hello.reply_location_request", "data": {"body": "Para ver si llegamos a tu zona, toca el botón y comparte tu ubicación."}}
    ],
    "edges": [{"id": "e1", "source": "n1", "target": "n2"}]
  }
}
```

**6B · Revisa la zona** (centro y radio de ejemplo: ponle los reales)

```json
{
  "name": "Delivery – cobertura",
  "trigger_type": "message",
  "trigger_config": {"keyword": "📍"},
  "graph": {
    "nodes": [
      {"id": "n1", "type": "trigger.message", "data": {}},
      {"id": "n2", "type": "action.location", "data": {"center_lat": "-16.5", "center_lng": "-68.15", "radius_km": "5"}},
      {"id": "n3", "type": "logic.condition", "data": {"left": "{{ vars.ubicacion.in_zone }}", "op": "eq", "right": "1"}},
      {"id": "n4", "type": "action.reply", "data": {"body": "¡Sí llegamos a tu zona! Estás a {{ vars.ubicacion.distance_km }} km."}},
      {"id": "n5", "type": "action.reply", "data": {"body": "Por ahora no llegamos a esa zona, pero puedes recoger tu pedido en el local."}},
      {"id": "n6", "type": "action.reply", "data": {"body": "No pude leer tu ubicación. ¿La compartes de nuevo con el botón?"}}
    ],
    "edges": [
      {"id": "e1", "source": "n1", "target": "n2"},
      {"id": "e2", "source": "n2", "target": "n3", "sourceHandle": "found"},
      {"id": "e3", "source": "n2", "target": "n6", "sourceHandle": "not_found"},
      {"id": "e4", "source": "n3", "target": "n4", "sourceHandle": "true"},
      {"id": "e5", "source": "n3", "target": "n5", "sourceHandle": "false"}
    ]
  }
}
```

## 7. Cobrar con QR y confirmar el pago (cobros)

Un cliente escribe «pagar», se le genera el cobro con la cuenta del negocio, se le manda el QR y a los 5 minutos se comprueba si pagó. `pay.charge` usa la única cuenta activa del tenant (si hay varias, indica `bank_account_id`). El monto del ejemplo es fijo: en un flujo real sale de un dato (`{{ vars.pedido.total }}`).

```json
{
  "name": "Cobrar con QR",
  "trigger_type": "message",
  "trigger_config": {"keyword": "pagar"},
  "graph": {
    "nodes": [
      {"id": "n1", "type": "trigger.message", "data": {}},
      {"id": "n2", "type": "pay.charge", "data": {"amount": "100", "currency": "BOB", "description": "Pago de servicio", "save_as": "cobro"}},
      {"id": "n3", "type": "action.reply", "data": {"body": "{{ vars.cobro.text }}"}},
      {"id": "n4", "type": "logic.delay", "data": {"seconds": "300"}},
      {"id": "n5", "type": "pay.status", "data": {"reference": "{{ vars.cobro.reference }}", "save_as": "cobro"}},
      {"id": "n6", "type": "action.reply", "data": {"body": "¡Recibimos tu pago! Gracias."}},
      {"id": "n7", "type": "action.reply", "data": {"body": "Aún no vemos tu pago. Si ya pagaste, espera unos minutos y escríbenos."}},
      {"id": "n8", "type": "action.reply", "data": {"body": "Ese cobro ya no está disponible. Escribe «pagar» para generar uno nuevo."}},
      {"id": "n9", "type": "action.reply", "data": {"body": "No pudimos generar tu cobro. Un asesor te ayudará."}}
    ],
    "edges": [
      {"id": "e1", "source": "n1", "target": "n2"},
      {"id": "e2", "source": "n2", "target": "n3", "sourceHandle": "created"},
      {"id": "e3", "source": "n2", "target": "n9", "sourceHandle": "failed"},
      {"id": "e4", "source": "n3", "target": "n4"},
      {"id": "e5", "source": "n4", "target": "n5"},
      {"id": "e6", "source": "n5", "target": "n6", "sourceHandle": "paid"},
      {"id": "e7", "source": "n5", "target": "n7", "sourceHandle": "pending"},
      {"id": "e8", "source": "n5", "target": "n8", "sourceHandle": "expired"},
      {"id": "e9", "source": "n5", "target": "n8", "sourceHandle": "cancelled"},
      {"id": "e10", "source": "n5", "target": "n8", "sourceHandle": "not_found"}
    ]
  }
}
```

Notas:
- `pay.charge` y `pay.cancel` **cobran/anulan de verdad** (⚠): solo después de que la persona aprobó el plan; el flujo queda en borrador.
- La referencia del cobro viaja en `{{ vars.cobro.reference }}`; `pay.status` la reconsulta al banco mientras siga pendiente. Con una cuenta de **QR fijo** (`vars.cobro.manual_confirmation` = true) el banco no avisa: el negocio marca el pago a mano.
- Para reaccionar apenas llega el pago, no esperes: usa otro workflow con el disparador de evento `aero.pay.paymentReceived`.
- `pay.summary` («cuánto cobré hoy») es **dato interno del negocio**: úsalo para el dueño, nunca para responder a clientes.

## 8. Estado del plan y renovación (para el dueño del negocio)

Responde «mi plan» con el estado de la suscripción del tenant a la plataforma. Si hay una renovación pendiente, manda el QR como imagen. `sites.plan_status` es de solo lectura: el cobro de la renovación lo genera la plataforma antes del vencimiento.

```json
{
  "name": "Mi plan",
  "trigger_type": "message",
  "trigger_config": {"keyword": "mi plan"},
  "graph": {
    "nodes": [
      {"id": "n1", "type": "trigger.message", "data": {}},
      {"id": "n2", "type": "sites.plan_status", "data": {"save_as": "plan"}},
      {"id": "n3", "type": "action.reply", "data": {"body": "{{ vars.plan.text }}"}},
      {"id": "n4", "type": "logic.condition", "data": {"left": "{{ vars.plan.renewal_pending }}", "op": "eq", "right": "1"}},
      {"id": "n5", "type": "action.reply_media", "data": {"media_url": "{{ vars.plan.renewal_image_url }}", "media_type": "image", "body": "{{ vars.plan.text }}"}},
      {"id": "n6", "type": "action.reply", "data": {"body": "Este negocio todavía no tiene un plan asignado."}}
    ],
    "edges": [
      {"id": "e1", "source": "n1", "target": "n2"},
      {"id": "e2", "source": "n2", "target": "n3", "sourceHandle": "active"},
      {"id": "e3", "source": "n2", "target": "n4", "sourceHandle": "expiring"},
      {"id": "e4", "source": "n2", "target": "n4", "sourceHandle": "overdue"},
      {"id": "e5", "source": "n2", "target": "n6", "sourceHandle": "no_plan"},
      {"id": "e6", "source": "n4", "target": "n5", "sourceHandle": "true"},
      {"id": "e7", "source": "n4", "target": "n3", "sourceHandle": "false"}
    ]
  }
}
```

Notas:
- Es información **interna del negocio**: acota el disparador (`keyword`) y úsalo solo en cuentas del dueño; nunca como respuesta pública a clientes.
- Si la renovación aún no se generó, `renewal_pending` es falso y solo se manda el texto.

## 9. Recargar monedas con QR y confirmar la acreditación

El dueño escribe «recargar», se genera el QR de la recarga de la plataforma y se lo manda como imagen; a los 3 minutos se comprueba si pagó. Las monedas **las acredita únicamente el pago confirmado**; el flujo solo informa.

```json
{
  "name": "Recargar monedas",
  "trigger_type": "message",
  "trigger_config": {"keyword": "recargar"},
  "graph": {
    "nodes": [
      {"id": "n1", "type": "trigger.message", "data": {}},
      {"id": "n2", "type": "credits.recharge", "data": {"amount": "100", "save_as": "recarga"}},
      {"id": "n3", "type": "action.reply_media", "data": {"media_url": "{{ vars.recarga.image_url }}", "media_type": "image", "body": "{{ vars.recarga.text }}"}},
      {"id": "n4", "type": "logic.delay", "data": {"seconds": "180"}},
      {"id": "n5", "type": "credits.purchase_status", "data": {"reference": "{{ vars.recarga.reference }}", "save_as": "recarga"}},
      {"id": "n6", "type": "action.reply", "data": {"body": "¡Recarga recibida! Tus monedas ya están acreditadas."}},
      {"id": "n7", "type": "action.reply", "data": {"body": "Todavía no vemos el pago. Cuando se confirme, las monedas se acreditan solas."}},
      {"id": "n8", "type": "action.reply", "data": {"body": "Esa recarga venció. Escribe «recargar» para generar otra."}},
      {"id": "n9", "type": "action.reply", "data": {"body": "Tu pago está en revisión: te avisaremos cuando se acredite."}},
      {"id": "n10", "type": "action.reply", "data": {"body": "No pudimos generar la recarga ahora. Intenta de nuevo en unos minutos."}}
    ],
    "edges": [
      {"id": "e1", "source": "n1", "target": "n2"},
      {"id": "e2", "source": "n2", "target": "n3", "sourceHandle": "created"},
      {"id": "e3", "source": "n2", "target": "n10", "sourceHandle": "failed"},
      {"id": "e4", "source": "n3", "target": "n4"},
      {"id": "e5", "source": "n4", "target": "n5"},
      {"id": "e6", "source": "n5", "target": "n6", "sourceHandle": "paid"},
      {"id": "e7", "source": "n5", "target": "n7", "sourceHandle": "pending"},
      {"id": "e8", "source": "n5", "target": "n8", "sourceHandle": "expired"},
      {"id": "e9", "source": "n5", "target": "n9", "sourceHandle": "review"},
      {"id": "e10", "source": "n5", "target": "n8", "sourceHandle": "not_found"}
    ]
  }
}
```

Notas:
- `credits.recharge` **cobra al tenant** (⚠): solo con el plan aprobado. El monto debe ser uno de los que ofrece la plataforma; generar otra recarga **anula la pendiente**.
- La imagen del QR viaja en `{{ vars.recarga.image_url }}`; `action.reply_media` la manda como imagen por WhatsApp.
- Para el cobro que el tenant hace a **sus clientes** (QR del negocio) usa el patrón 7 (`pay.*`).

## Lista de revisión antes de guardar

- [ ] La persona confirmó el plan y lo guardaste en `agreed_plan`.
- [ ] Un solo disparador y coincide con `trigger_type`.
- [ ] El disparador de mensaje está **acotado** (keyword, cuenta o `interactive_id`).
- [ ] Cada condición tiene `true` y `false` conectados; cada salida de cada nodo está conectada.
- [ ] Ninguna rama paralela se une (solo se unen ramas de una Condición).
- [ ] Todas las plantillas `{{ }}` están cerradas y apuntan a datos que existen.
- [ ] Botones/menús: cuenta de API oficial, textos dentro de los límites, ids legibles y un workflow que atienda cada opción.
- [ ] El flujo siempre termina en una respuesta o en un resultado guardado; hay salida de escape.
- [ ] Si es tool de IA, `tool_schema` describe exactamente los campos que usa `{{ trigger.* }}`.
- [ ] `workflows_validate` devolvió `valid: true`.
