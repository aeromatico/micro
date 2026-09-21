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

Con cuentas que usan la ventana de 24 h (Cloud API/Zernio), **no** puedes
escribir libremente si el contacto no te escribió en las últimas 24 h. En ese
caso necesitas una **plantilla aprobada**.

> [!TIP]
> Para envíos grandes, sube un poco el **delay** y procura que el mensaje tenga
> valor para el destinatario: reduce el riesgo de reportes y bloqueos.

> [!CAUTION]
> Revisa bien los destinatarios antes de confirmar: el envío es masivo y sale de
> inmediato si no lo programas.
