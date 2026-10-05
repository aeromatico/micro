---
title: Workflow
sort: 10
---
# Función: Workflow

**Ruta:** `Workflows → Workflows`
**Controlador:** `Aero\Workflows\Controllers\Workflows`
**Modelo:** `Aero\Workflows\Models\Workflow`
**Permiso:** `aero.workflows.use`

Aquí creas y editas un flujo de automatización.

## Campos

| Campo | Para qué sirve |
|-------|----------------|
| **Nombre** | Cómo lo reconoces en la lista. |
| **Código** | Identificador único dentro de tu cuenta (letras, números, guiones). |
| **Descripción** | Nota interna. |
| **Activo** | Solo los workflows activos se disparan solos. La prueba manual funciona siempre. |
| **Disparador** | Manual, evento de la plataforma, mensaje entrante o webhook. |
| **Configuración del disparador (JSON)** | Ver abajo. |
| **Diseño** | El editor visual con los nodos. |
| **Ofrecer como herramienta al Super Chatbot IA** | Opcional. Ver [Super Chatbot IA](workflows-super-chatbot-ia). |

## Configuración del disparador

| Disparador | Ejemplo |
|------------|---------|
| Evento | `{"event":"aero.shop.orderCreated"}` (admite `*` como comodín) |
| Mensaje entrante | `{"keyword":"precio","account_id":1}` (ambos opcionales) |
| Webhook | `{"secret":"clave-larga"}` (obligatorio) |

> [!IMPORTANT]
> Un evento solo dispara tu workflow si pertenece a tu cuenta. Los eventos sin cuenta identificable se ignoran.

## Webhook entrante

Envía un `POST` con JSON a `https://tu-dominio/workflows/hook/{id}` con **uno** de estos encabezados:

- `X-Signature: sha256=<HMAC-SHA256 del cuerpo con tu secret>`
- `X-Webhook-Token: <tu secret>`

Responde `202` con el número de ejecución. Sin `secret` configurado, el webhook no existe (404).

## Editor visual

1. Agrega nodos desde la columna izquierda.
2. Conéctalos arrastrando desde el punto inferior de uno al superior de otro. La **Condición** tiene dos salidas: *sí* y *no*.
3. Selecciona un nodo para editar sus opciones a la derecha. Allí ves las variables que puedes usar.
4. Guarda. Con **Probar** ejecutas el flujo con una entrada JSON de ejemplo y ves el resultado en [Ejecuciones](workflows-ejecuciones).

Plantillas: `{{ trigger.campo }}` (datos de entrada), `{{ vars.nombre }}` (variables guardadas), `{{ nodes.n2.body.email }}` (salida de otro nodo).

> [!TIP]
> El editor avisa si falta el disparador, hay nodos sueltos o hay un ciclo.

**Versión documentada:** 1.0.1
