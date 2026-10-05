---
title: Nodos disponibles
sort: 20
---
# Nodos disponibles

| Nodo | Categoría | Opciones | Notas |
|------|-----------|----------|-------|
| Manual / Evento / Mensaje entrante / Webhook | Disparador | — | Punto de partida; entrega los datos en `trigger`. |
| Condición | Lógica | Valor, operador, comparar con | Salidas *sí* y *no*. Operadores: igual, distinto, contiene, mayor, menor, vacío, no vacío. |
| Guardar variable | Lógica | Nombre, valor | Disponible luego como `{{ vars.nombre }}`. |
| Esperar | Lógica | Segundos (máx. 86400) | Pausa la ejecución y la retoma después. |
| Llamar URL / Connector | Acción | Connector o URL https, método, datos | Con **Connector**, las credenciales quedan cifradas y no se guardan en el flujo. |
| Enviar mensaje (Hello) | Acción | Teléfono, mensaje, cuenta | Requiere Hello. La cuenta debe ser tuya. |
| Notificar (Notify) | Acción | Evento del catálogo, contexto JSON | Requiere Notify. |
| Responder | Acción | Valor | Es lo que recibe quien llamó al workflow, por ejemplo la IA. |

> [!NOTE]
> Otros módulos pueden sumar sus propios nodos; aparecerán automáticamente en el editor.

> [!WARNING]
> Si un nodo falla, la ejecución termina con error y queda registrado el paso que falló.

**Versión documentada:** 1.0.1
