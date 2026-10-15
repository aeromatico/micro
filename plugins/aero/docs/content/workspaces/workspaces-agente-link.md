---
title: Link, diseñador de flujos
sort: 50
featured: false
---
# Link, diseñador de flujos

**Link** diseña contigo los **workflows** (automatizaciones) de tu negocio: lo que debe pasar cuando un cliente escribe por WhatsApp, cuando entra un pedido o cuando ocurre algo en la plataforma. Trabajas con él conversando; él arma el flujo y lo deja listo para que lo revises.

**Dónde:** Workspaces → [Mercado](workspaces-mercado) para contratarlo y Workspaces → [Oficina](workspaces-oficina) para hablar con él · Los flujos se ven en Workflows · **Permiso:** `aero.workspaces.use`

## Qué puede hacer

- **Diseñar flujos nuevos** a partir de lo que quieres lograr (por ejemplo «que el cliente pida su pedido sin hablar con una persona»).
- **Editar flujos que ya tienes**, incluso publicados, cuando acuerdas el cambio con él.
- **Deshacer** el último cambio que hizo en un flujo.
- **Revisar lo que ya existe** para no duplicar: propone reutilizar o ampliar.
- Recibir encargos de la **orquestadora**, que le reparte los pasos de automatización de un pedido más grande.

## Cómo trabaja contigo

Link sigue cinco fases y no se salta ninguna:

1. **Descubrir.** Mira tus cuentas de WhatsApp (y si admiten botones o menús), tus Connectors, los módulos que tienes y tus workflows. Te hace de 1 a 3 preguntas por turno, en lenguaje cotidiano y con opciones: objetivo, qué lo inicia, qué datos usa y qué debe pasar en cada caso.
2. **Proponer.** Te devuelve el flujo en pasos simples y con un dibujo de texto.
3. **Acordar.** Ajusta hasta que le digas «sí» con claridad. Sin ese «sí» no construye.
4. **Construir.** Usa el catálogo vivo de nodos, valida el diseño con las mismas reglas del editor hasta que pase y lo guarda.
5. **Entregar.** Te explica qué quedó, qué debes revisar y cómo probarlo, con el enlace al editor de tu propio panel.

## Qué dispara un flujo

| Disparador | Cuándo corre |
|---|---|
| **Manual** | Lo lanzas tú con el botón Probar, o la IA lo usa como herramienta. |
| **Mensaje** | Un cliente escribe por WhatsApp (opcionalmente con una palabra clave, una cuenta concreta o un botón o menú tocado). |
| **Evento** | Pasa algo en la plataforma, por ejemplo la creación de un pedido. |
| **Webhook** | Un sistema externo llama con una petición firmada. El secreto lo defines tú; Link no lo guarda. |

## Qué queda en borrador y qué pide acuerdo

- Un flujo **nuevo** queda como **borrador desactivado**: no corre hasta que tú lo publiques y lo actives desde el editor. Link **nunca** publica ni activa.
- Un flujo con errores **no se guarda**: Link recibe los errores y los corrige.
- **Editar un flujo publicado** cambia lo que corre en vivo al instante. Link te lo dice antes y antes de cada edición guarda una copia para poder deshacer.
- Si pides un cambio sin nombrar el flujo («agrégale un botón», «ese flujo»), Link edita **el flujo en el que estaban trabajando**; si no está seguro, te pregunta «¿lo agrego al flujo #N o armo uno nuevo?».
- Conserva lo que ya funciona: no cambia ids de botones ni palabras clave que otros flujos usan, salvo que el cambio lo pida.

## Límites

- Hasta **60 nodos** por flujo y **50 borradores** por cuenta.
- Solo usa nodos, campos y salidas que aparecen en el catálogo; no inventa.
- Solo ve y modifica workflows de **tu propia cuenta**, y las cuentas y Connectors que referencia deben ser tuyos.
- No crea secretos ni credenciales: eso lo defines tú.

## Ejemplos de pedidos

- «Cuando alguien escriba *menú* por WhatsApp, respóndele con botones: pedir, ver horarios y hablar con una persona.»
- «Agrégale al flujo de pedidos un aviso a mi WhatsApp cuando el monto pase de Bs 500.»
- «Deshaz el último cambio.»
- «¿Qué flujos tengo ya para pedidos? No quiero duplicar.»

> [!WARNING]
> Un flujo publicado y activo corre sobre tus clientes reales. Revisa y prueba el borrador antes de publicarlo.

> [!NOTE]
> Para el detalle de los nodos y del editor visual, mira [Workflows](../workflows/workflows-vista-general) y [Construir con un agente](../workflows/workflows-agente-constructor).

## Para administradores

Link trabaja de verdad porque sus skills «Diseño de flujos», «Catálogo de nodos de workflows» y «Patrones de flujos» le dan las herramientas `workflows_*` del plugin Workflows (contexto, listado, lectura, catálogo, validación, borrador, edición y reversión). Estas herramientas siempre reciben el tenant del chat desde el motor, nunca de lo que escriba el modelo.

**Versión documentada:** 1.15.0
