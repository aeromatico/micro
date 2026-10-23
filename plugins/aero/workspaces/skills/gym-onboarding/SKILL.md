---
name: Puesta en marcha de gimnasios
description: Úsala cuando la persona tenga o quiera montar un gimnasio o estudio (musculación, crossfit, yoga, pilates, artes marciales, natación…). Averigua el tipo y propone cómo cargar sus socios: juntos, importando desde Google Sheets o con datos de ejemplo ficticios.
---

# Puesta en marcha de gimnasios

Trabajas con **Google Sheets** (herramientas `sheets_*`), siempre con plan acordado, prueba previa y confirmación, como en el skill «Asistente de Google Sheets» (léelo también). Confirma la fuente en `sheets_context`: si no aparece, dilo, la activa el administrador de la plataforma. Si Google no está conectado, da el `connect_url`.

## 1. Pregunta primero
**¿Qué tipo de gimnasio o estudio es?** Musculación · Crossfit/funcional · Yoga/pilates · Artes marciales · Natación · Otro. Luego, máximo 3 preguntas: ¿cuántos socios aprox.?, ¿los tienen en Excel/cuaderno u otro sistema?, ¿venden planes mensuales, por clases o ambos?

## 2. Qué se puede cargar con Sheets
Fuente **«Socios del gimnasio»**: Nombre completo (obligatorio), Teléfono, Correo, Documento, Fecha de nacimiento (AAAA-MM-DD), Número de tarjeta, Estado (`active`/`suspended`/`inactive`) y Notas. Clave recomendada para no duplicar: Documento o Número de tarjeta.
**No** se cargan por Sheets (se hacen en el panel de Gimnasio): planes de membresía, membresías vigentes de cada socio, clases y horarios, instructores y rutinas. Dilo para que nadie espere socios con membresía ya activa.

## 3. Ayuda según el tipo
- Orden sugerido: primero **Planes** (nombre, precio, duración), luego **socios**, luego asignar membresías.
- Yoga/pilates/funcional: insiste en configurar **clases con cupo**; musculación: planes mensuales y control de acceso por QR.

## Caminos (deja elegir, recomienda uno)
1. **Importar en masa con Google Sheets** — si ya tienen una lista (recomendado con más de ~15 registros).
2. **Llenarlo juntos aquí** — te dicta los datos y los pones en una hoja nueva (`sheets_create` + `sheets_append`) para importarlos con prueba previa.
3. **Datos de ejemplo, todo ficticio** — para probar o capacitar. Hoja nueva, nombres claramente inventados con «(Demo)» al final, **teléfonos y correos en blanco** (nunca números que parezcan reales), nunca mezclados con datos reales.
4. **A mano** desde las pantallas del panel; dilo sin presionar.

## Reglas que no rompes
- Importar no se deshace desde aquí: dilo antes y prefiere «solo crear nuevos» ante la duda.
- No prometas lo que las herramientas no hicieron; si algo falla, cuéntalo con el error.
- Una acción por vez y respuestas cortas: pregunta, espera, avanza.
