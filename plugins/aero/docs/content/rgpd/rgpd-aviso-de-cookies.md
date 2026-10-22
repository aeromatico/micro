---
title: Aviso de cookies
sort: 10
featured: false
---
# Aviso de cookies

**Ruta:** `/backend/aero/rgpd/configs` · **Permiso:** `aero.rgpd.use`

Configura el aviso que ven los visitantes de tu sitio.

## Campos

| Campo | Descripción |
| --- | --- |
| Mostrar aviso de cookies | Apagado: el sitio no muestra aviso ni registra consentimientos. |
| Posición | Dónde aparece el aviso (lista). |
| Enlace a la política de privacidad/cookies | Ruta (`/privacidad`) o URL completa. Recomendado por RGPD. |
| Título | Por ejemplo «Usamos cookies». |
| Mensaje | Déjalo vacío para usar el texto estándar. |
| Botón aceptar | Texto del botón; por defecto «Aceptar todo». |
| Botón rechazar | Texto del botón; por defecto «Rechazar». |
| Botón personalizar | Texto del botón; por defecto «Personalizar». |
| Ofrecer categoría «Análisis» | Permite al visitante aceptar o rechazar las cookies de análisis. |
| Ofrecer categoría «Marketing» | Permite al visitante aceptar o rechazar las cookies de marketing. |
| Guardar registro de consentimientos | Guarda una prueba anónima (id aleatorio, IP con hash). Recomendado. |
| Versión del aviso | Solo lectura. Sube sola al cambiar textos o categorías y vuelve a pedir consentimiento. |

> [!IMPORTANT]
> Cambiar los textos o las categorías sube la versión del aviso: todos los visitantes tendrán que dar su consentimiento otra vez.

**Versión documentada:** 1.0.0
