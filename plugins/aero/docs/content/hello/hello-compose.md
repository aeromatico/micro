# Función: Redactar

**Ruta:** `Hello → Redactar`
**Controlador:** `Aero\Hello\Controllers\Compose`
**Permiso:** `aero.hello.manage_conversations`

Es la **única pantalla de envío**: sirve tanto para un mensaje manual a una
persona como para un envío masivo a muchos destinatarios. Desde aquí eliges
cuenta, destinatarios, mensaje (o plantilla), adjuntos, ritmo y momento de
envío.

## Cómo se arma un envío

| Paso | Qué eliges |
|------|------------|
| **Cuenta** | Desde qué número/cuenta sale (se preselecciona la del canal por defecto). |
| **Destinatarios** | Escritos a mano, o traídos de los **contactos** y **listas** del CRM. |
| **Contenido** | Un mensaje libre y/o una **plantilla** de WhatsApp, con adjuntos. |
| **Ritmo** | Delay mínimo y máximo entre mensajes (evita bloqueos por ráfaga). |
| **Programación** | Enviar de inmediato al confirmar o **programar** para una fecha/hora. |
| **Estado** | Panel con los envíos recientes y su resultado. |

## Tipos de mensaje

Sobre el cuadro de mensaje eliges el tipo. Cada tipo aparece solo si la cuenta
elegida lo admite.

| Tipo | Disponible con | Qué envía |
|------|----------------|-----------|
| **Texto** | Todas las cuentas | Mensaje libre con formato y adjunto opcional. |
| **Ubicación**, **Contacto**, **Encuesta** | Solo WhatsApp Web | Coordenadas, tarjeta de contacto o encuesta de 2 a 12 opciones. |
| **Botones**, **Menú**, **Enlace**, **Pedir ubicación**, **Llamada** | Solo cuentas de la API oficial de WhatsApp | Mensajes interactivos (ver abajo). |

### Mensajes interactivos

| Tipo | Qué ve el cliente | Límites |
|------|-------------------|---------|
| **Botones** | Texto y de 1 a 3 botones de respuesta. | Cada botón, hasta 20 caracteres. |
| **Menú** | Texto y un botón que abre un menú desplegable. | De 1 a 10 opciones; título hasta 24 y descripción hasta 72 caracteres. Texto del botón del menú hasta 20 (por defecto «Ver opciones»); título de sección opcional, hasta 24. |
| **Enlace** | Texto y un botón que abre una URL. | Texto del botón hasta 20; la URL debe empezar con `http://` o `https://`. |
| **Pedir ubicación** | Texto y un botón «Enviar ubicación». | Solo el texto. La respuesta llega como un mensaje de ubicación normal. |
| **Llamada** | Texto y un botón de llamada. | Texto del botón opcional (hasta 20). Requiere WhatsApp Business Calling activado en el número que envía. |

- El **texto del mensaje** es obligatorio y admite hasta **1024** caracteres.
- Solo se envían dentro de las **24 h** posteriores al último mensaje del cliente; no hay plantilla de respaldo.
- Si algún texto supera su límite, el envío se **rechaza con un aviso**; nunca se recorta en silencio.
- Cada botón u opción lleva un **id**. Si no lo escribes, se genera a partir del título. Solo admite letras, números, guion y guion bajo, y no puede repetirse dentro del mensaje.
- Cuando el cliente toca una opción, su id llega con el mensaje entrante como `interactive_id`, disponible para el chatbot y para los [workflows](workflows-nodos-interactivos).
- Los mismos mensajes están disponibles como nodos del editor de Workflows.

> [!NOTE]
> Con una cuenta de WhatsApp Web, botones y menús se mostrarían como texto plano; por eso Redactar no los ofrece ahí.

## Adjuntos permitidos

| Tipo | Formatos | Tope |
|------|----------|------|
| Imagen | JPG, PNG, WEBP | 5 MB |
| Video | MP4, 3GPP | 16 MB |
| Audio | MP3, OGG, MP4, AAC, AMR | 16 MB |
| Documento | PDF, TXT, CSV, Office, ZIP | 100 MB |

## Límites

- Texto: **4096** caracteres. Con adjunto, el texto es el pie y baja a **1024**.
- Máximo **300 destinatarios** por envío (cada mensaje sale escalonado).

## Ventana de 24 h de WhatsApp

Con cuentas que usan la ventana de 24 h (Cloud API/Hello), **no** puedes
escribir libremente si el contacto no te escribió en las últimas 24 h. En ese
caso necesitas una **plantilla aprobada**.

> [!TIP]
> Para envíos grandes, sube un poco el **delay** y procura que el mensaje tenga
> valor para el destinatario: reduce el riesgo de reportes y bloqueos.

> [!CAUTION]
> Revisa bien los destinatarios antes de confirmar: el envío es masivo y sale de
> inmediato si no lo programas.
