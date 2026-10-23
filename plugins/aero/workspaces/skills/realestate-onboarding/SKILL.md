---
name: Puesta en marcha de inmobiliarias
description: Úsala cuando la persona tenga o quiera montar una inmobiliaria o administre inmuebles (venta, alquiler, anticrético). Averigua cómo trabaja y propone cargar propietarios, inmuebles y clientes: juntos, importando desde Google Sheets o con datos de ejemplo ficticios.
---

# Puesta en marcha de inmobiliarias

Trabajas con **Google Sheets** (herramientas `sheets_*`), siempre con plan acordado, prueba previa y confirmación, como en el skill «Asistente de Google Sheets» (léelo también). Confirma la fuente en `sheets_context`: si no aparece, dilo, la activa el administrador de la plataforma. Si Google no está conectado, da el `connect_url`.

## 1. Pregunta primero
**¿Qué operaciones manejan?** Venta · Alquiler · Anticrético (varias). **¿Qué tipos de inmueble?** Casas · Departamentos · Terrenos · Oficinas · Locales. Luego: ¿cuántos inmuebles aprox.?, ¿los tienen en Excel/otro portal?, ¿hay agentes que atienden por separado?

## 2. Qué se puede cargar con Sheets (y en qué orden)
1. **Propietarios**: Nombre, Teléfono, Correo, Documento, Dirección, Notas.
2. **Inmuebles**: Título (obligatorio), Código, Tipo (`house`/`apartment`/`land`/`office`/`shop`/`other`), Estado (`available`/`reserved`/`sold`/`rented`/`anticretico`/`inactive`), Dirección, Departamento/Ciudad/Zona/Barrio (texto), superficies, dormitorios, baños, garajes, pisos, antigüedad, Destacado. Clave: Código.
3. **Clientes**: Nombre, Teléfono, Correo, Documento, Origen, Presupuesto mínimo/máximo y Moneda.
Traduce lo que tengan («Casa», «En venta») a los valores exactos. **No** se cargan por Sheets: el vínculo inmueble↔propietario/agente, fotos y documentos, ofertas, contratos ni la publicación en el portal (Publicado se decide en la ficha tras revisarla). Dilo antes de importar.

## Caminos (deja elegir, recomienda uno)
1. **Importar en masa con Google Sheets** — si ya tienen una lista (recomendado con más de ~15 registros).
2. **Llenarlo juntos aquí** — te dicta los datos y los pones en una hoja nueva (`sheets_create` + `sheets_append`) para importarlos con prueba previa.
3. **Datos de ejemplo, todo ficticio** — para probar o capacitar. Hoja nueva, nombres claramente inventados con «(Demo)» al final, **teléfonos y correos en blanco** (nunca números que parezcan reales), nunca mezclados con datos reales.
4. **A mano** desde las pantallas del panel; dilo sin presionar.

## Reglas que no rompes
- Importar no se deshace desde aquí: dilo antes y prefiere «solo crear nuevos» ante la duda.
- No prometas lo que las herramientas no hicieron; si algo falla, cuéntalo con el error.
- Una acción por vez y respuestas cortas: pregunta, espera, avanza.
