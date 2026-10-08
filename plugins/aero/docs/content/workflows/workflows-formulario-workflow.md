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
| **Estado** | Borrador, Publicado o Archivado. Solo los **publicados** y activos se disparan solos (por evento, mensaje, webhook o como herramienta de IA). Un borrador solo corre con **Probar**. |
| **Activo** | Interruptor para encender o apagar un workflow publicado. La prueba manual funciona siempre. |
| **Disparador** | Manual, evento de la plataforma, mensaje entrante o webhook. |
| **Configuración del disparador (JSON)** | Ver abajo. |
| **Diseño** | El editor visual con los nodos. |
| **Ofrecer como herramienta al Super Chatbot IA** | Opcional. Ver [Super Chatbot IA](workflows-super-chatbot-ia). |

## Configuración del disparador

| Disparador | Ejemplo |
|------------|---------|
| Evento | `{"event":"aero.shop.orderCreated"}` (admite `*` como comodín) |
| Mensaje entrante | `{"keyword":"precio","account_id":1,"interactive_id":"ver_precios_1"}` (todos opcionales; `keyword` admite varias palabras separadas por coma, sin distinguir mayúsculas ni acentos; `interactive_id` dispara solo si el cliente tocó ese botón u opción y admite una lista) |
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

### Flujos grandes

- **⛶ Pantalla completa:** el editor ocupa toda la ventana. Sal con **✕ Salir** o la tecla **Esc**.
- **⟲ Ordenar:** acomoda los nodos por capas, cada uno debajo de lo que lo alimenta, para que se crucen menos las líneas. Solo cambia las posiciones, no las conexiones.
- **Minimapa:** en la esquina; arrástralo para moverte por el flujo.

## Publicar y borradores

Un workflow puede ser **borrador** (por ejemplo, los que deja un agente: ver [Construir con un agente](workflows-agente-constructor)). Los borradores no se disparan por eventos, mensajes, webhooks ni herramientas de IA. Al publicar se revisan los límites de cada nodo, incluidos los [nodos interactivos de Hello](workflows-nodos-interactivos): un borrador a medias se puede guardar, uno publicado no. Los workflows anteriores quedaron como publicados.

## Importar, exportar y eliminar

En la lista de workflows:

- **Importar:** sube un archivo JSON exportado. Entra siempre como **borrador, desactivado** y sin ofrecerse a la IA; revísalo, reconecta lo indicado y publícalo. Se rechazan archivos mal formados o con nodos que esta plataforma no tiene.
- **Exportar seleccionados:** marca uno o varios y descarga el JSON. No viajan datos de tu cuenta (cuentas de WhatsApp, Connectors, categorías, productos, métodos de pago) ni secretos; quedan listados como «por reconectar».
- **Eliminar seleccionados:** borra los marcados **con todas sus ejecuciones**. No se puede deshacer.

> [!TIP]
> El editor avisa si falta el disparador, hay nodos sueltos o hay un ciclo.

**Versión documentada:** 1.6.1
