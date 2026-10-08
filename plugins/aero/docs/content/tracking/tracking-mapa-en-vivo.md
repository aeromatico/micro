# Pantalla: Mapa en vivo

**Ruta:** `Tracking → Mapa en vivo`
**Controlador:** `Aero\Tracking\Controllers\LiveMap`
**Permiso:** `aero.tracking.use`

El **Mapa en vivo** muestra, sobre un mapa, la última posición de cada activo
activo y los trabajos abiertos que tiene asignados.

## Qué muestra

- **Lista lateral**: cada activo con su tipo, cuándo envió su última posición,
  su velocidad y el nivel de batería (si el dispositivo lo reporta).
- **Punto en el mapa**: verde si el activo está **en línea**, gris si está
  **sin señal**.
- **Trabajos abiertos**: los trabajos con estado Pendiente, Asignado o En curso
  aparecen bajo el activo que los ejecuta.

Al hacer clic en un activo de la lista, el mapa se centra en él.

La pantalla se **refresca sola cada 5 segundos**.

> [!NOTE]
> Si un activo no tiene posición todavía, aparece en la lista pero no en el
> mapa.

> [!TIP]
> ¿No ves nada en el mapa? Revisá que el activo esté **Activo** y que el
> dispositivo esté enviando a su **URL para OwnTracks**.
