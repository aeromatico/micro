# Aero.Medical — historia clínica por negocio

Humana o veterinaria en un solo plugin. Datos de salud: sensibles.

## Cómo se separa humano / animal (decisión de diseño)
Como **datos y configuración**, no como dos plugins ni `if` repartidos:
- `ClinicSettings.mode` (`human|animal`, por negocio) y `Patient.kind` (congelado al crear el paciente).
- `Classes\Mode`: vocabulario («Médico»/«Veterinario», «Persona de contacto»/«Tutor»…) y qué campos del paciente existen en cada modo.
- `Classes\VitalsSchema`: qué signos vitales existen por modo (IMC solo en humano; condición corporal, mucosas y TLLC en animal).
- Plantillas de nota por negocio (`NoteTemplate`), cuestionarios, analitos, exámenes, medicamentos y rangos: tablas por negocio.

## Diagnósticos: CIE-10 en humano, catálogo propio en animal
- **Humano:** búsqueda por código o nombre sobre el catálogo global CIE-10 (`aero_medical_cie10`, OPS/OMS en español; ver `data/README.md`).
  El título guardado sale del catálogo, no del cliente. Además hay «Frecuentes» por negocio (los más usados en notas firmadas + los fijados) y texto libre.
- **Animal:** CIE-10 **no aplica** y el servidor lo rechaza. Se usa el catálogo propio del negocio (`aero_medical_diagnosis_catalog`,
  origen `custom`; también alimenta sus Frecuentes) o texto libre. Una especie no tiene catálogo oficial; lo curan los veterinarios del negocio.
- Cada diagnóstico es principal/secundario (uno solo principal) y presuntivo/confirmado. Se bloquean al firmar y entran en la huella de la nota.

## Receta
- **Humano:** LINAME (Bolivia) si está cargada + medicamentos del negocio + texto libre. La LINAME **no viene cargada** (el PDF oficial
  solo trae OCR con errores; ver `data/README.md`): hasta entonces, medicamentos del negocio o texto libre.
- **Animal:** solo medicamentos del negocio o texto libre.
- Dosis, vía, frecuencia, duración, cantidad e indicaciones las escribe siempre el profesional. Todo va cifrado.

## Seguridad
- Texto clínico cifrado en la BD (`encrypted`). Firma y sello cifrados en la BD (nginx publica `storage/`, ni el disco «protegido» es privado).
- Nota firmada = inmutable (modelo + huella SHA-256 con contenido, signos vitales, diagnósticos, receta, órdenes y cuestionarios). Cambios = adendas.
- Bitácora inmutable de accesos y cambios (solo nombres de campos). Eventos `aero.medical.*` llevan solo ids (Aero.Api reenvía a webhooks lo que viaja en eventos).
- Recepción no ve contenido clínico (campos con `permissions`, pantallas con `requiredPermissions`).

## Qué NO hay (a propósito)
- Rangos «alto/bajo» y cuestionarios clínicos de fábrica: los define el negocio. Sin rango no se marca nada en rojo.
- Quintos caracteres opcionales de la CIE-10 (sitio en M, fracturas abiertas/cerradas).
- Firma electrónica certificada: la firma es una imagen para impresión; la integridad la da la huella.
