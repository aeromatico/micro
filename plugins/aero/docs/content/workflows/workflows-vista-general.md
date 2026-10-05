---
title: Vista general
sort: 1
---
# Workflows: automatizaciones con nodos

Un **workflow** es un flujo que armas dibujando nodos y conectándolos: algo ocurre (el *disparador*), se evalúan condiciones y se ejecutan acciones, como llamar a una API, enviar un WhatsApp o avisar a tu equipo.

**Ruta:** `Workflows`
**Permiso:** `aero.workflows.use`

## Piezas

| Pieza | Qué hace |
|-------|----------|
| [Workflow](workflows-formulario-workflow) | Nombre, disparador, diseño visual y opciones para el Super Chatbot IA. |
| [Nodos disponibles](workflows-nodos) | Disparadores, condiciones, variables, esperas y acciones. |
| [Ejecuciones](workflows-ejecuciones) | Historial paso a paso de cada vez que corrió un workflow. |
| [Usarlo desde el Super Chatbot IA](workflows-super-chatbot-ia) | Opcional: la IA puede ejecutar un workflow durante una conversación. |

## Límites de seguridad

- Cada ejecución tiene un máximo de **50 pasos** y **60 segundos**.
- Hasta **300 ejecuciones por hora** por cuenta.
- Las URLs que escribas directamente deben ser **https** y públicas (se bloquean direcciones internas).
- Un workflow solo ve y usa datos de **tu propia cuenta**.

> [!NOTE]
> Cada ejecución consume créditos si tu plataforma tiene la acción `workflows.run` configurada.

**Versión documentada:** 1.1.0
