# Catálogos globales de Aero.Medical

Se cargan con `php artisan medical:import-cie10` / `medical:import-liname`, nunca desde migraciones,
y no llevan `tenant_id`: son los mismos para todos los negocios.

## CIE-10 (`cie10_es.tsv`)
- **Fuente:** Clasificación Internacional de Enfermedades, 10.ª revisión, Volumen 1, **español**, edición en línea de la
  OPS/OMS: <https://ais.paho.org/classifications/Chapters/> (capítulos `CAP01.html`…`CAP22.html`), descargada el 2026-10-17.
- **Contenido:** 2.037 categorías de 3 caracteres + 9.977 subcategorías de 4 caracteres (12.014 códigos).
- **Subdivisiones comunes (códigos derivados):** la CIE-10 define algunos cuartos caracteres UNA sola vez para un rango de
  categorías (E10–E14, F10–F19, F70–F79, K25–K28, N00–N07, O03–O06, R83–R89, V01–V06, V10–V18, V20–V28, V30–V38, V40–V48,
  V50–V58, V60–V68, V70–V78, V90–V94, Y70–Y82), de modo que `E11.9` no figura como línea propia en el texto de la OPS. Esos
  1.025 códigos se **generaron** combinando el título de la categoría con el de la subdivisión común, tomados ambos del propio
  texto de la OPS (`E11` + `.9 Sin mención de complicación` → «Diabetes mellitus no insulinodependiente, sin mención de
  complicación»). El código es exacto; la redacción del título es una composición y puede diferir de la impresa
  (p. ej. N05.9). No se generaron los quintos caracteres opcionales (M, fracturas abiertas/cerradas).
- **Cómo se obtuvo:** extracción automática de los párrafos de código/título del HTML; los títulos se normalizaron
  (espacios, guion blando). No se corrigió ni reescribió ningún título.
- **Edición:** es la edición en español de la OPS (no incluye las actualizaciones de la OMS posteriores). Contrastada con la
  versión inglesa OMS 2019 (ClaML): ~300 códigos de la 2019 no están en el texto de la OPS y ~490 del texto de la OPS
  (p. ej. duplicados con asterisco †/*) no están en la 2019. Es una diferencia de edición, no de extracción.
- Los códigos con `*` en el título (p. ej. `(G01*)`) son las manifestaciones de la notación daga/asterisco de la propia OPS.

## LINAME (Bolivia)
- **Fuente oficial:** AGEMED, LINAME 2022-2024 (RM 0132, 6-abr-2022):
  <https://www.agemed.gob.bo/reg-far/doc_reg_far/liname.pdf>.
- **No se carga por defecto:** el PDF solo trae una capa de texto de OCR con errores sistemáticos (`mg`→`my`, `ml`→`mI`,
  `0`↔`O`, `5`↔`S` en códigos ATC…). Importarlo tal cual dejaría concentraciones y nombres erróneos en recetas.
- El comando `medical:import-liname <archivo.csv>` acepta un CSV **verificado** (columnas: `code,name,form,concentration,atc,restricted`)
  y NO acepta nada que no tenga ese formato. Hasta entonces el catálogo queda vacío y la receta usa texto libre
  y el catálogo propio del negocio.
