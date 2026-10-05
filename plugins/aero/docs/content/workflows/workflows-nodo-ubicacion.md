---
title: Nodo Capturar ubicación
sort: 25
---
# Nodo: Capturar ubicación

Extrae una **coordenada** (latitud y longitud) de lo que el cliente comparta. Sirve para pedidos a domicilio, comprobar si una dirección está en tu zona de cobertura, asignar repartidores, calcular distancias o guardar el punto de un cliente.

## Qué entiende

| El cliente envía | Resultado |
|------------------|-----------|
| La **ubicación de WhatsApp** (📎 → Ubicación) | Latitud y longitud, y el nombre o dirección que acompañe. |
| Coordenadas escritas: `-16.5000, -68.1500` | Latitud y longitud. |
| Un enlace de **Google Maps** (largo o `maps.app.goo.gl`), Apple Maps u OpenStreetMap | Latitud y longitud del enlace. |
| Nada reconocible (un «hola», un teléfono, un precio) | Sale por **sin ubicación**. |

> [!NOTE]
> Con enlaces cortos de Google Maps, la plataforma abre el enlace una sola vez para leer a dónde redirige. Solo lo hace con `maps.app.goo.gl` y `goo.gl`.

## Opciones

| Campo | Para qué sirve |
|-------|----------------|
| **Texto o enlace a analizar** | Déjalo vacío para usar el mensaje que disparó el flujo. Si vienes de un webhook o de la IA, escribe por ejemplo `{{ trigger.texto }}`. |
| **Latitud / Longitud** | Si ya las tienes (por ejemplo desde un webhook), se usan en lugar del texto. |
| **Guardar en la variable** | Por defecto `ubicacion`. |
| **Cobertura: centro y radio (km)** | Opcional. Calcula la distancia al centro y si la ubicación cae dentro. |

## Qué obtienes

Con la variable `ubicacion` (o la que elijas):

| Dato | Ejemplo |
|------|---------|
| `{{ vars.ubicacion.lat }}` · `.lng` | `-16.5123` · `-68.1301` |
| `{{ vars.ubicacion.coords }}` | `-16.5123,-68.1301` (útil para APIs de rutas) |
| `{{ vars.ubicacion.maps_url }}` | Enlace de Google Maps para tu repartidor. |
| `{{ vars.ubicacion.name }}` | Nombre o dirección que escribió el cliente, si hay. |
| `{{ vars.ubicacion.distance_km }}` y `.in_zone` | Solo si definiste cobertura. |

## Salidas

- **con ubicación:** sigue el flujo con los datos anteriores.
- **sin ubicación:** úsala para pedirle al cliente que comparta su ubicación (`📎 → Ubicación → Enviar tu ubicación actual`).

> [!TIP]
> Para decidir según la cobertura, conecta una **Condición** con valor `{{ vars.ubicacion.in_zone }}` igual a `1`.

> [!IMPORTANT]
> Las coordenadas `0, 0` y las fuera de rango se descartan. En texto libre se exigen decimales (`-16.5, -68.15`) para no confundir teléfonos o precios con una ubicación.

## Ejemplo

El flujo **Ejemplo: ubicación para delivery** valida que el cliente esté a menos de 15 km del centro y responde con el enlace del mapa. Se puede probar con **Probar** y la entrada `{"texto":"📍 (-16.5123, -68.1301)\nCasa de Ana"}`.

**Versión documentada:** 1.1.0
