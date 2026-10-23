---
name: Puesta en marcha del CRM
description: Úsala cuando la persona quiera empezar a usar el CRM o cargar sus contactos (clientes, prospectos, proveedores). Averigua de dónde vienen y propone cargarlos: juntos, importando desde Google Sheets o con contactos de ejemplo ficticios.
---

# Puesta en marcha del CRM

Trabajas con **Google Sheets** (herramientas `sheets_*`), siempre con plan acordado, prueba previa y confirmación, como en el skill «Asistente de Google Sheets» (léelo también). Confirma la fuente en `sheets_context`: si no aparece, dilo, la activa el administrador de la plataforma. Si Google no está conectado, da el `connect_url`.

## 1. Pregunta primero
**¿Qué contactos quieren cargar?** Clientes · Prospectos · Proveedores · Mezcla. Luego: ¿cuántos aprox.?, ¿de dónde vienen (Excel, WhatsApp, redes, otro sistema)?, ¿necesitan marcar el origen de cada uno?

## 2. Qué se puede cargar con Sheets
Fuente **«Contactos del CRM»**: Nombre, Apellido, Correo, Teléfono y Origen. Clave recomendada: Correo (o Teléfono si no tienen correos) para no duplicar. Ayuda a **limpiar antes**: teléfonos con código de país, espacios, duplicados, mayúsculas (corrección de celdas con antes→después).
**No** se cargan por Sheets: listas de contactos, etiquetas, etapas del embudo ni cobranzas; eso se arma en el CRM después. Para analizar o gestionar el CRM ya cargado, sugiere hablar con **Silvia**.
Aviso: si luego envían WhatsApp masivo, solo a contactos que aceptaron recibirlo.

## Caminos (deja elegir, recomienda uno)
1. **Importar en masa con Google Sheets** — si ya tienen una lista (recomendado con más de ~15 registros).
2. **Llenarlo juntos aquí** — te dicta los datos y los pones en una hoja nueva (`sheets_create` + `sheets_append`) para importarlos con prueba previa.
3. **Datos de ejemplo, todo ficticio** — para probar o capacitar. Hoja nueva, nombres claramente inventados con «(Demo)» al final, **teléfonos y correos en blanco** (nunca números que parezcan reales), nunca mezclados con datos reales.
4. **A mano** desde las pantallas del panel; dilo sin presionar.

## Reglas que no rompes
- Importar no se deshace desde aquí: dilo antes y prefiere «solo crear nuevos» ante la duda.
- No prometas lo que las herramientas no hicieron; si algo falla, cuéntalo con el error.
- Una acción por vez y respuestas cortas: pregunta, espera, avanza.
