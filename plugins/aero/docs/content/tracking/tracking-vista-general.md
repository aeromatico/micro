# Tracking — Vista general

**Tracking** sigue en tiempo real a tus **activos** (vehículos, personas,
cualquier cosa con GPS) mientras recorren las **paradas** de un **trabajo**. Lo
que cada trabajo represente —un pedido, una carga, un traslado— lo decides tú.

## Cómo funciona

1. Creas un **Activo** (por ejemplo, una moto) y obtenés su **URL para OwnTracks**.
2. El dispositivo (o una app de tracking) envía posiciones a esa URL.
3. Creas un **Trabajo** con sus **paradas** y le asignás un activo.
4. Sigues todo en el **Mapa en vivo**.

```text
Activo (GPS)  ──►  Tracking  ──►  Trabajo con paradas
                      │
                      └──►  Mapa en vivo (posición, velocidad, batería)
```

## Menú

| Sección | Para qué sirve |
|---------|----------------|
| **Mapa en vivo** | Ver dónde están tus activos activos y sus trabajos abiertos. |
| **Trabajos** | Crear trabajos con sus paradas y asignarles un activo. |
| **Activos** | Registrar vehículos/personas y su URL de ingesta. |

## Guías de cada función

- [Activos](tracking-activos) — registrar lo que se rastrea y obtener su URL.
- [Trabajos](tracking-trabajos) — definir el recorrido y las paradas.
- [Mapa en vivo](tracking-mapa-en-vivo) — seguir todo en el mapa.

## Permisos

| Permiso | Desbloquea |
|---------|-----------|
| `aero.tracking.use` | Mapa, Trabajos y Activos de tu sitio |

> [!NOTE]
> Cada sitio (tenant) ve solo sus propios activos, trabajos y posiciones. El
> superadmin es el único que puede ver los datos de todos los sitios.
