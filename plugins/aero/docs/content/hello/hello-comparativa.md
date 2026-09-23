# WhatsApp Web vs WhatsApp Cloud API: ¿cuál elijo?

Al conectar una cuenta en `Hello → Conectar cuenta` eliges entre dos formas de
vincular un número de WhatsApp. No son mejor o peor entre sí: sirven para
cosas distintas y puedes tener **ambas conectadas a la vez** (cada una en su
propio número) y elegir un canal por defecto en `Configuración → WhatsApp`.

## Comparación

| | **WhatsApp Web** | **WhatsApp Cloud API** |
|---|---|---|
| **Cómo se conecta** | Tú mismo, al instante: QR o código de 8 dígitos desde *Dispositivos vinculados* de tu WhatsApp. | Alta asistida: agregas `social@market.com.bo` como administrador de tu portafolio comercial de Meta; el equipo completa la conexión. |
| **Tiempo hasta quedar activa** | Minutos. | Depende de la verificación de Meta (horas o días). |
| **Origen del número** | Un número de WhatsApp normal (personal o de negocio), el mismo que usarías en tu celular. | Un número dado de alta como WhatsApp Business oficial ante Meta. |
| **Mensaje nuevo a un contacto que nunca te escribió** | Libre, sin restricción. | No permitido: exige una **plantilla aprobada por Meta** (o categoría `utility` si tu cuenta califica). |
| **Responder dentro de las 24 h de su último mensaje** | Libre, sin límite. | Libre, sin plantilla — es la llamada "ventana de 24 h". |
| **Responder después de 24 h sin mensaje entrante** | Libre, como siempre. | Bloqueado: necesitas una plantilla aprobada. Redactar avisa en pantalla cuando estás fuera de ventana. |
| **Plantillas aprobadas por Meta** | No hacen falta (se ignoran si las cargas). | Obligatorias para reabrir conversación. Se gestionan en `Hello → Plantillas`. |
| **Acuses de envío** | Enviado, entregado y leído, con el id real de WhatsApp. | Enviado, entregado y leído, por webhook de Meta/Zernio. |
| **Ubicación, tarjeta de contacto y encuestas** | Sí, disponibles al redactar. | No — Meta las rechaza; Redactar muestra un aviso claro si lo intentas. |
| **Adjuntos** (imagen, video, audio, documento) | Sí, mismos límites de tamaño que Cloud API. | Sí, mismos límites de tamaño que WhatsApp Web. |
| **Nombre de perfil autocompletado en Contactos** | Sí: toma el nombre de WhatsApp de quien te escribe. | Aún no: el nombre hay que ponerlo a mano. |
| **Llamadas de voz (recepcionista con IA)** | No disponible por este canal. | Sí — es el único canal que las soporta (`Hello → Llamadas`). |
| **Verificación oficial / insignia verde de Meta** | No aplica: es un número normal, no un perfil de negocio verificado. | Sí, gestionable ante Meta una vez conectado. |
| **Riesgo de bloqueo del número** | El mismo riesgo que cualquier conexión por QR fuera de la app oficial: existe, aunque bajo con uso normal. | Ninguno por este motivo: es el canal oficial de Meta. |
| **Costo por mensaje enviado desde el panel (Redactar, Bandeja)** | Sin costo adicional del sistema. | Sin costo adicional del sistema. |
| **Costo por mensaje enviado por tu propia integración (API)** | 1 crédito naranja por mensaje (igual en ambos canales). | 1 crédito naranja por mensaje (igual en ambos canales). |
| **Qué pasa si el sitio se reinicia** | Se reconecta sola en unos segundos, sin volver a escanear. | No aplica: no depende de una sesión que se caiga. |
| **Requiere que mantengas la app de WhatsApp de ese número** | Sí, es la cuenta detrás del vínculo. | No: el número vive en Meta, no en un celular. |

## En una frase

- **WhatsApp Web** — arranca ya, sin trámites, y no tiene restricciones de
  ventana ni de plantillas. A cambio, es un canal no oficial y no habilita
  llamadas con IA.
- **WhatsApp Cloud API** — canal oficial de Meta, único que habilita llamadas
  con IA, pero exige plantillas aprobadas para reabrir conversaciones y su
  alta depende de la verificación de tu portafolio comercial.

## Recomendación práctica

- Si necesitas escribir primero a contactos nuevos todo el tiempo (ventas,
  soporte reactivo, avisos puntuales): **WhatsApp Web**.
- Si tu negocio ya tiene, o puede tramitar, un portafolio comercial verificado
  en Meta, y te interesa un recepcionista con IA por llamadas: **Cloud API**.
- Puedes usar las dos: por ejemplo, Cloud API como número oficial de atención
  y WhatsApp Web para un número de ventas más ágil. Elige cuál es el canal
  **por defecto** en `Configuración → WhatsApp`.

> [!NOTE]
> Los límites de tamaño de adjuntos, la lista de tipos de mensaje permitidos y
> el detalle de plantillas están en [Redactar](hello-compose) y
> [Plantillas](hello-templates).
