# Funcionalidades por plugin

Documento maestro de la **oferta de la plataforma**. Reúne, plugin por plugin,
qué hace cada módulo y para qué sirve. Se mantiene como fuente para la
descripción comercial general.

> [!NOTE]
> Cada sección indica la **versión documentada** del plugin. Si la versión
> actual del código es mayor, esta ficha debe revisarse.

## Sites

**Qué es:** SaaS multitenant de micrositios web por nichos.
**Versión documentada:** 1.40.0

| Funcionalidad | Descripción breve | Cualidades de impacto | Casos de uso |
|---------------|-------------------|-----------------------|--------------|
| Gestión de tenants | Alta, estados, planes y dominios de cada micrositio | Ciclo de vida completo, incluida alta pública con cobro por QR | Operar decenas o cientos de micrositios desde un panel |
| Editor visual con IA | Portada por bloques (Puck) generada por IA | Arquetipos por rubro + temas curados: resultados homogéneos sin diseñador | Negocio sin equipo técnico que necesita su sitio hoy |
| Páginas y plantilla | Páginas internas, HTML propio y layout personalizable | Control total sin salir del panel | Landing de campaña, páginas institucionales |
| Temas visuales y branding | Paletas claro/oscuro, tipografía, logo, favicon | Personalización por overrides sin tocar código | Adaptar la imagen a cada cliente |
| SEO y contacto | Metadatos, sitemap, robots, formulario y ubicación | Indexación y captación de leads integradas | Sitio que quiere aparecer en Google y recibir consultas |
| API y tokens | Acceso REST por sitio con permisos | Integraciones externas y apps móviles | Sincronizar contenido con otros sistemas |

## Pagos (Bolivia Pay)

**Qué es:** Pasarela de pagos unificada (bancos QR bolivianos, PayPal y cripto).
**Versión documentada:** 1.22.0

| Funcionalidad | Descripción breve | Cualidades de impacto | Casos de uso |
|---------------|-------------------|-----------------------|--------------|
| Cuentas multi-proveedor | Conecta BNB, Banco Económico, correo, PayPal y NOWPayments | Una sola interfaz para medios muy distintos | Cobrar por varias vías sin cambiar de herramienta |
| QR dinámico | Un QR por transacción con monto fijo | Confirmación por webhook casi instantánea | Cobros puntuales de ventas o servicios |
| QR estático (correo) | Detecta pagos del QR fijo leyendo el correo del banco | Sin API bancaria; funciona con cualquier QR | Comercios con QR interbancario impreso |
| Sucursales, USD e impuestos | Código de sucursal, cobros en USD y cálculo de impuesto | Trazabilidad por punto de cobro | Redes con varias sucursales |
| API REST y webhooks | Generación/consulta de QR y avisos a tu sistema | Integración programática con firma | POS o e-commerce propio |
| Cobranzas | Recordatorios de pago con QR integrado | Enlaza con el CRM | Cobranza recurrente automatizada |

## CRM

**Qué es:** CRM minimalista por tenant: clientes, pipeline, cobranzas y mesa de ayuda.
**Versión documentada:** 1.6.1

| Funcionalidad | Descripción breve | Cualidades de impacto | Casos de uso |
|---------------|-------------------|-----------------------|--------------|
| Empresas y contactos | Ficha de organizaciones y personas con origen y redes | Base única de clientes | Centralizar la cartera |
| Listas | Agrupación de contactos | Organiza campañas y cobranzas | Segmentar por campaña o estado |
| Leads | Prospectos con estados | Puerta de entrada al embudo | Captación y calificación |
| Pipeline | Tablero kanban de negocios por etapa | Arrastrar y soltar; etapas por defecto listas | Seguimiento comercial visual |
| Actividades | Llamadas, reuniones, tareas y notas | Próximo paso siempre visible | Rutina diaria del vendedor |
| Cobranzas + automatización | Cobros y recordatorios en cascada | Plantillas con variables y QR de pago | Recuperar cartera vencida |
| Tickets y departamentos | Mesa de ayuda con conversación y notas internas | Asignación por área y prioridad | Soporte al cliente |
| Equipo y respuestas rápidas | Accesos por rol y atajos de chat | Delega sin perder control; atención más ágil | Trabajo en equipo |

## Shop

**Qué es:** Tienda en línea por tenant con catálogo, inventario y pedidos.
**Versión documentada:** 1.0.9

| Funcionalidad | Descripción breve | Cualidades de impacto | Casos de uso |
|---------------|-------------------|-----------------------|--------------|
| Catálogo con variantes | Productos, variantes y colecciones | Precio y stock por variante | Vender talles/colores |
| Inventario | Control de existencias | Evita sobreventa | Reposición y disponibilidad |
| Pedidos y clientes | Registro de compras y compradores | Flujo de checkout integrado | E-commerce propio |
| Pasarelas y monedas | Múltiples medios y monedas | Se apoya en Bolivia Pay | Cobros locales e internacionales |
| API y tool de chatbot | Consulta de productos por IA | El bot puede recomendar productos | Atención automatizada |

## Notify

**Qué es:** Gateway omnicanal de notificaciones transaccionales.
**Versión documentada:** 1.5.3

| Funcionalidad | Descripción breve | Cualidades de impacto | Casos de uso |
|---------------|-------------------|-----------------------|--------------|
| Eventos y reglas | Catálogo de eventos con reglas por tenant | Cada sitio decide qué recibe y por dónde | Avisos de pago, altas, suspensiones |
| Canales y plantillas | Email, WhatsApp, Telegram, SMS, in-app y push | Un solo motor para todos los canales | Comunicación unificada |
| Entregas y reintentos | Registro de envíos con reintentos y colas | Trazabilidad y confiabilidad | Auditar qué se envió |
| Bandeja in-app y Web Push | Buzón interno y push del navegador | Llega al usuario sin depender del correo | Centro de avisos del tenant |
| Consolas | Auditar envíos directos, ver fallos, generar claves VAPID | Operación y diagnóstico | Soporte y mantenimiento |

## Hello

**Qué es:** Centro de mensajes y notificaciones multicanal sobre Zernio.
**Versión documentada:** 1.21.0

| Funcionalidad | Descripción breve | Cualidades de impacto | Casos de uso |
|---------------|-------------------|-----------------------|--------------|
| Conversaciones | Bandeja de chats multicanal | Atención centralizada | WhatsApp con clientes |
| Cuentas y perfiles | Conectar cuentas y perfiles | Multi-número y multi-marca | Redes de sucursales |
| Redactar | Envío manual o masivo con plantilla opcional, ritmo y programación | Una sola pantalla para todo envío | Promociones y avisos |
| Llamadas | Historial, grabación, transcripción y costos | Voz y texto en el mismo lugar | Seguimiento comercial |
| Social posts | Publicaciones en redes | Presencia sin salir del panel | Marketing de contenidos |

## Chatbots

**Qué es:** Chatbots de autorespuesta por tenant sobre Hello.
**Versión documentada:** 1.5.0

| Funcionalidad | Descripción breve | Cualidades de impacto | Casos de uso |
|---------------|-------------------|-----------------------|--------------|
| Bots y reglas | Respuestas automáticas configurables | Ahorro en atención repetitiva | FAQ y horarios |
| Registro de respuestas | Historial de lo respondido | Auditoría y mejora | Optimizar guiones |
| Super Chatbot IA | Modo con herramientas (contacto, landing, productos) | Responde con datos reales del negocio | Asistente 24/7 |

## SMS

**Qué es:** Envío de SMS simples y masivos con API propia.
**Versión documentada:** 1.1.0

| Funcionalidad | Descripción breve | Cualidades de impacto | Casos de uso |
|---------------|-------------------|-----------------------|--------------|
| Envío simple y masivo | Mensajes individuales o por lotes | API propia con atribución por tenant | Avisos y notificaciones |
| Plantillas | Mensajes reutilizables | Estandariza la comunicación | Recordatorios |
| Consumo y créditos | Uso por tenant y cobro en créditos | Control de gasto | Monetizar envíos |
| Bajas (opt-outs) | Gestión de desuscripciones | Cumplimiento y buena práctica | Listas responsables |

## Chat

**Qué es:** API de la PWA de mensajería multiagente.
**Versión documentada:** 1.2.0

| Funcionalidad | Descripción breve | Cualidades de impacto | Casos de uso |
|---------------|-------------------|-----------------------|--------------|
| PWA multiagente | App instalable de chat para el equipo | Push, sesión de larga duración y modo offline | Atención desde el móvil |
| Bandeja y delegación | Asignación de conversaciones | Trabajo coordinado | Reparto de chats |
| Respuestas rápidas | Atajos de texto | Respuestas ágiles | Atención al cliente |

## WAPI

**Qué es:** Integración de WhatsApp API (instancias) y registro de mensajes.
**Versión documentada:** 1.5.0

| Funcionalidad | Descripción breve | Cualidades de impacto | Casos de uso |
|---------------|-------------------|-----------------------|--------------|
| Instancias | Administración de conexiones de WhatsApp | Multi-instancia por operación | Varios números |
| Registro de mensajes | Consolida mensajes enviados desde otros dispositivos | Historial completo | Auditoría de comunicaciones |

## Connector

**Qué es:** Conectores HTTP y de IA, webhooks y logs.
**Versión documentada:** 1.4.0

| Funcionalidad | Descripción breve | Cualidades de impacto | Casos de uso |
|---------------|-------------------|-----------------------|--------------|
| Conectores HTTP | Integración con servicios externos | Configurable sin código | APIs de terceros |
| Conectores de IA | OpenAI-compatible y Anthropic | Base de toda la IA del ecosistema | Generación de contenido |
| Webhooks y logs | Entrada/salida de datos con registro | Trazabilidad | Automatizaciones |

## Credits

**Qué es:** Sistema de créditos por tenant para acciones facturables.
**Versión documentada:** 1.2.0

| Funcionalidad | Descripción breve | Cualidades de impacto | Casos de uso |
|---------------|-------------------|-----------------------|--------------|
| Cuentas y transacciones | Saldo y movimientos por tenant | Base de monetización | Cobrar por uso |
| Acciones y tipos | Catálogo de acciones facturables | Se conecta a IA, SMS y mensajería | Consumo medible |
| Widgets de saldo | Indicadores de créditos | Visibilidad de consumo | Autogestión del cliente |

## API

**Qué es:** Gateway REST `api/v1` con API keys y permisos.
**Versión documentada:** 1.1.0

| Funcionalidad | Descripción breve | Cualidades de impacto | Casos de uso |
|---------------|-------------------|-----------------------|--------------|
| API keys y scopes | Claves con permisos por área | Aislamiento por tenant | Integraciones seguras |
| Monedas y tasas | Tipo de cambio centralizado | Datos financieros reutilizables | Precios en varias monedas |

## AI Fields

**Qué es:** Asistencia de IA en campos de formulario.
**Versión documentada:** 1.0.4

| Funcionalidad | Descripción breve | Cualidades de impacto | Casos de uso |
|---------------|-------------------|-----------------------|--------------|
| Campos con IA | Autocompletado y generación de texto | Proveedores configurables | Redactar contenido rápido |
| Modo Developer | Asistencia para código y diseño | Acelera el desarrollo | Prototipado |

## Master Ads

**Qué es:** Optimizador de campañas Meta Ads asistido por IA (SaaS multi-tenant).
**Versión documentada:** 1.0.8

| Funcionalidad | Descripción breve | Cualidades de impacto | Casos de uso |
|---------------|-------------------|-----------------------|--------------|
| Cuentas y campañas | Conexión de Meta, campañas, adsets y anuncios | Vista jerárquica completa | Gestión de pauta |
| Recomendaciones IA | Sugerencias de optimización | Análisis automático programado | Bajar costo por resultado |
| Análisis IA | Diagnóstico de rendimiento | Informes accionables | Revisión de campañas |
| Planes y suscripciones | Billing por suscripción | Modelo SaaS listo | Monetización |

## Docs

**Qué es:** Documentación en Markdown con categorías multinivel y multi-sitio.
**Versión documentada:** 1.1.1

| Funcionalidad | Descripción breve | Cualidades de impacto | Casos de uso |
|---------------|-------------------|-----------------------|--------------|
| Artículos y categorías | Contenido Markdown con árbol anidable y búsqueda | Historial de versiones, «¿fue útil?» y anteriores/siguientes | Centro de ayuda |
| Multi-sitio | Cada tenant administra su propia documentación | Aislamiento por sitio, sin mezclar contenidos | Marca blanca / centros de ayuda por cliente |
| Documentos globales | El superadmin marca contenido de uso general | Se muestra tal cual en el centro de ayuda de todos los sitios | Guías de plataforma sin duplicar |
| Versión por artículo | Guarda la versión del plugin documentada | Detecta documentación desactualizada | Mantener la oferta al día |

## Blog

**Qué es:** Blog del sitio (basado en RainLab.Blog, personalizado).
**Versión documentada:** 3.5.1

| Funcionalidad | Descripción breve | Cualidades de impacto | Casos de uso |
|---------------|-------------------|-----------------------|--------------|
| Entradas y categorías | Publicación de artículos | SEO y contenido propio | Marketing de contenidos |
