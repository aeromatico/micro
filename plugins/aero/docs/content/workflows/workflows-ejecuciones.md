---
title: Ejecuciones
sort: 30
---
# Función: Ejecuciones

**Ruta:** `Workflows → Ejecuciones`
**Controlador:** `Aero\Workflows\Controllers\Runs`
**Modelo:** `Aero\Workflows\Models\Run`
**Permiso:** `aero.workflows.use`

Historial de cada vez que se ejecutó uno de tus workflows. Es de solo lectura.

| Dato | Significado |
|------|-------------|
| **Estado** | `queued` en cola, `running` ejecutando, `waiting` esperando (nodo Esperar), `ok`, `error`. |
| **Origen** | manual, evento, mensaje, webhook o herramienta de IA. |
| **Pasos** | Cantidad de nodos ejecutados. |
| **Error** | Motivo si falló. |

Al abrir una ejecución ves la entrada, el resultado y cada paso con su estado, duración y salida.

> [!TIP]
> Si un workflow no hace lo esperado, abre la última ejecución y revisa en qué paso se detuvo.

**Versión documentada:** 1.1.0
