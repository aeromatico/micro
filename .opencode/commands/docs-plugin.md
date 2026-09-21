---
description: Documenta un plugin de Aero en el plugin Docs (tenant) y publica en local y producción
agent: build
---

Documenta el plugin **$ARGUMENTS** siguiendo la skill `docs-plugin`.

Instrucciones:
1. Carga la skill `docs-plugin` y sigue sus pasos al pie de la letra.
2. Alcance: **solo las funciones del panel del tenant** (nada de superadmin).
3. Analiza los formularios reales del plugin (campos, modelo, controlador) antes de escribir.
4. Genera `plugins/aero/docs/content/<plugin>/*.md` + `updates/seed_<plugin>_docs.php`
   y actualiza `updates/version.yaml` del plugin Docs.
5. Actualiza el documento general `content/plugins/funcionalidades.md`.
6. Publica en local y producción, y verifica los links en
   `https://market.com.bo/documentacion`.
7. Reporta al final: artículos creados, versión del plugin documentada y links.

Si `$ARGUMENTS` está vacío, pregunta qué plugin documentar y muestra los
candidates con sus versiones actuales.
