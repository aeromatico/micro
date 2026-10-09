# Tarifas: ¿dónde y cuánto te cobramos?

Todo lo que consume monedas en tu sitio pasa por el mismo sistema de créditos
(**Aero.Credits**), así que la contabilidad es siempre la misma: se descuenta
al momento de usar el servicio, y si el servicio falla se te **reembolsa
automáticamente**. Nunca se cobra "por si acaso": si no hay saldo suficiente,
la acción simplemente no se ejecuta.

Esta página lista **todos los lugares donde tu sitio puede generar consumo**
y a qué tarifa, para que sepas de antemano cuánto te cuesta cada cosa.

> [!NOTE]
> Las tarifas pueden ajustarse con el tiempo (por ejemplo si el costo del
> proveedor de IA cambia). Cuando eso pasa, el sistema siempre te muestra el
> costo *antes* de confirmar la acción — esta página es una referencia, no un
> contrato de precio fijo.

## Tipos de moneda

| Moneda | Equivalencia aprox. | Se usa para |
|--------|----------------------|-------------|
| 🟤 **Bronce** | ≈ Bs 0.055 / moneda | Mensajería (WhatsApp, SMS), cobros con QR, API e IA de uso frecuente |
| ⚪ **Plata** | ≈ Bs 1.11 / moneda | Servicios más costosos por unidad (ej. llamadas de voz) |
| 🟡 **Oro** | ≈ Bs 11.07 / moneda | Servicios de IA de mayor consumo (no se puede intercambiar por otra moneda) |
| 💵 **Saldo en Bs** | 1:1 | Sobrante de tus recargas; sirve para comprar cualquier moneda al instante |

Podés ver el detalle de cómo recargar, intercambiar y consultar tu historial
en [Wallet (Mis monedas)](credits-wallet).

## Dónde te cobramos

### WhatsApp (Aero.Hello)

| Servicio | Costo |
|----------|-------|
| Mensaje enviado por la API | **1 moneda de bronce** por mensaje |
| Llamada de voz con IA | **5 monedas de plata** por llamada |

Los mensajes enviados desde el panel/bandeja también cuentan como envío por
API. Si un mensaje termina sin poder entregarse, el cobro se revierte solo.

### SMS (Aero.Sms)

| Servicio | Costo |
|----------|-------|
| SMS enviado | **5 monedas de bronce por segmento** |

Un SMS largo (más de 160 caracteres) se divide en varios segmentos; el costo
se cotiza *antes* de enviar, tanto para un mensaje suelto como para un lote.
Si el envío falla, se reembolsa.

### IA en formularios (Aero.AiFields)

| Servicio | Costo |
|----------|-------|
| Generar / mejorar / traducir texto | **1 moneda de bronce** |
| Modo Developer (generación de código/diseño) | **2 monedas de bronce** |

### Chatbots con IA (Aero.Chatbots)

| Servicio | Costo |
|----------|-------|
| Respuesta generada por el bot | Según el **modelo de IA** que elegiste para ese bot |

Cada bot tiene asignado un modelo de IA (en **Chatbots → Modelos**), y cada
modelo tiene su propio costo y color de moneda — los modelos más potentes
cuestan más. El costo se muestra en la configuración del bot antes de
activarlo. En **modo Súper IA** (razonamiento en varios pasos), el cobro se
aplica por cada paso real que ejecuta el bot, no solo por la respuesta final.

Si no hay saldo suficiente para cobrar una respuesta, igual se le contesta al
contacto — no se le corta la conversación al cliente final por falta de
saldo tuyo, pero verás la alerta de saldo bajo en tu panel.

### Aero Hub — APIs de datos y modelos de IA

Aero Hub agrupa decenas de endpoints (scraping, SERP, redes sociales,
generación de IA) bajo un solo panel. Cada endpoint tiene su propia tarifa en
monedas de bronce, según cuánto le cuesta a la plataforma consultarlo.
Algunos ejemplos:

| Endpoint | Costo aproximado |
|----------|-------------------|
| Chat con modelos de IA (texto) | desde **3 monedas de bronce** por llamada |
| Búsquedas SERP / scraping general | **3 monedas de bronce** |
| Buscador de correo de autor | **11 monedas de bronce** |
| SEO con ranking en ChatGPT | **13 monedas de bronce** |
| Generación de imagen / video / audio (trabajos en cola) | **390 monedas de bronce** |

El costo exacto de cada endpoint que tenés habilitado se muestra siempre en
el catálogo de **Aero Hub → Endpoints** antes de usarlo, junto con el color
de moneda con el que se cobra.

### Pagos con QR (Aero.Pay)

| Servicio | Costo |
|----------|-------|
| QR de cobro completado (pagado) | **3 monedas de bronce** por QR |

La tarifa se cobra **solo cuando el QR se paga**, no al generarlo: un QR
pendiente, vencido o anulado no te cuesta nada. Aplica a los cobros de tu
tienda, cobranzas, API, automatizaciones y Shopify. Se cobra una sola vez por
QR. El pago de tu cliente nunca se frena por falta de saldo: si en ese momento
no te alcanzan las monedas, el cobro del cliente se acredita igual, pero la
tarifa de ese QR queda sin cobrar y te llega la alerta de saldo bajo.

### API pública (Aero.Api)

Cada petición a la API con una **API key de tu sitio** puede tener un precio,
definido **por módulo** (Hello, Pay, Shop, SMS, Tracking, Hub, etc.). Todas las
peticiones a un mismo módulo cuestan lo mismo, y cada módulo tiene su moneda y
su tarifa.

- Se cobra **solo si la petición sale bien**: una respuesta con error
  (código 400 o superior) no se cobra.
- Si no te alcanza el saldo, la API responde **402 `insufficient_credits`**
  y la petición no se ejecuta.
- Los endpoints que **ya tienen su propia tarifa** no se cobran dos veces:
  por ejemplo, el envío de mensajes y las llamadas de voz de WhatsApp siguen
  costando lo indicado arriba, sin cargo adicional por petición.
- Un módulo sin precio asignado es gratis. Las tarifas se ajustan por módulo
  y pueden cambiar; ante la duda, consultá el precio vigente con soporte.

### Conectores personalizados (Aero.Connector)

Si configurás un conector propio (una integración con IA o un servicio
externo) podés asignarle un costo en monedas por cada llamada. Si no le
asignás costo, esa llamada es gratis para el tenant. El costo se define por
conector — consultalo en **Conectores → [tu conector] → Configuración**.

## Cómo funciona el cobro por dentro

1. **Al ejecutar la acción**, se descuenta el costo de tu saldo (nunca antes,
   nunca "por si acaso").
2. Si la acción **falla** (mensaje no entregado, llamada al proveedor caída,
   etc.), el cobro se **reembolsa automáticamente** — no necesitás pedirlo.
3. Algunas acciones (llamadas de voz, trabajos en cola, llamadas a conectores)
   usan un **cobro con espera** (*hold*): se reserva el monto, y si el proceso
   no confirma un resultado dentro de un tiempo límite, el sistema lo
   reembolsa solo — así nunca queda un cobro "colgado" por un error técnico.
4. Si tenés monedas de regalo incluidas en tu plan con fecha de vencimiento,
   se gastan **primero** (antes que las que compraste), para que no se te
   venzan sin usar.

## ¿No hay saldo suficiente?

La acción no se ejecuta y verás el motivo (saldo insuficiente en esa moneda).
Podés recargar o intercambiar monedas desde
[Wallet (Mis monedas)](credits-wallet) en cualquier momento.

---

**Artículos relacionados:**
- [Vista general del sistema de monedas](credits-vista-general)
- [Wallet (Mis monedas)](credits-wallet)
- [Widget de dashboard](credits-widget-dashboard)
