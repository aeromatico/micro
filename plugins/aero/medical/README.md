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

## Fase 4: vacunas, archivos, documentos, importación y exportación
- **Vacunación:** catálogo y esquema (dosis y días entre una y otra, refuerzo periódico) definidos por el negocio; el sistema no trae cifras.
  La próxima dosis sale del esquema o se indica a mano. Calendario del paciente con vencidas/próximas y «Agendar» (Office, `BookingService`).
- **Recordatorios:** `medical:remind` (diario 09:00). Apagado por defecto, por negocio (`vaccine_reminders`). Notify `medical.vaccine.due`;
  en personas el aviso no nombra la vacuna. El evento `aero.medical.vaccineDue` lleva solo ids.
- **Archivos clínicos:** cifrados en disco (`storage/app/medical-private`, nombre aleatorio), tipo decidido por el contenido (PDF, PNG, JPG, WebP, Word, texto),
  máx. 20 MB, lectura solo por `Controllers\Files` (permiso clínico + tenant + bitácora) con `nosniff` y CSP `sandbox`. Los resultados numéricos de un estudio van a la hoja.
- **Formatos de documentos:** texto plano con `{{variables}}` conocidas (se rechazan las desconocidas); el documento emitido guarda el texto ya resuelto,
  con huella, firma y sello; borrador editable por su autor. Los datos faltantes salen como `__________` y solo un consentimiento puede emitirse así.
- **Importación:** el original se conserva; la asociación (documento/nombre en el nombre del archivo y en el texto de Word/txt) es una *sugerencia*: nada entra
  a una ficha hasta que una persona la confirma. PDF e imágenes solo se asocian por el nombre del archivo.
- **Exportación:** ZIP con JSON descifrado y los archivos originales, por paciente y del negocio (propietario). Quedan en la bitácora.

## Fase 6: arranque, cobros y API
- **Rangos de referencia para adultos** (botón en «Rangos alto/bajo», solo modo humano, nunca automático, nunca pisa rangos existentes): borrador **sin revisión médica**.
  Fuentes: OMS y ESC/ESH (hipertensión desde 140/90; hipotensión por debajo de 90/60), OMS (IMC 18,5–24,9), ADA/OMS (glucosa en ayunas 70–99 mg/dL; HbA1c por debajo de 5,7 %),
  ATP III (colesterol total por debajo de 200 y triglicéridos por debajo de 150 mg/dL), valores habituales del adulto en reposo (FC 60–100, FR 12–20, T 36,0–37,5 °C, SpO2 ≥ 95 %).
  No incluye niños, embarazo ni valores que dependen del sexo o del laboratorio (hemoglobina, creatinina). Al editar uno, pasa a ser «del negocio». Para animales no se carga nada.
- **Plantillas sugeridas** (control de paciente crónico, chequeo preventivo; variantes para animal): solo estructura de secciones.
- **Lista inicial de medicina general:** 39 códigos CIE-10 (títulos del catálogo cargado), sin revisión médica; no fija ninguno.
- **Cobros:** efectivo/transferencia/otro quedan cobrados; QR con Aero.Pay (`QrIssuer`, origen `medical`, descripción genérica «Atención #n») conciliado por `aero.pay.qrStatusChanged`.
  Eventos `aero.medical.chargePaid/chargeVoided` con solo ids; Aero.Finance los asienta («Atención #n», sin concepto ni diagnóstico) si el negocio lo permite.
  El QR no se probó contra un banco real.
- **API/MCP:** `GET /api/v1/medical/patients[/{id}]` (scope `medical.patients.read`, solo datos administrativos, requiere la key del negocio) y recursos MCP de pacientes y cobros (sin concepto). Nada clínico.

## Seguridad
- Texto clínico cifrado en la BD (`encrypted`). Firma y sello cifrados en la BD (nginx publica `storage/`, ni el disco «protegido» es privado).
- Nota firmada = inmutable (modelo + huella SHA-256 con contenido, signos vitales, diagnósticos, receta, órdenes y cuestionarios). Cambios = adendas.
- Bitácora inmutable de accesos y cambios (solo nombres de campos). Eventos `aero.medical.*` llevan solo ids (Aero.Api reenvía a webhooks lo que viaja en eventos).
- Recepción no ve contenido clínico (campos con `permissions`, pantallas con `requiredPermissions`).

## Qué NO hay (a propósito)
- Rangos «alto/bajo» y cuestionarios clínicos de fábrica: los define el negocio. Sin rango no se marca nada en rojo.
- Quintos caracteres opcionales de la CIE-10 (sitio en M, fracturas abiertas/cerradas).
- Firma electrónica certificada: la firma es una imagen para impresión; la integridad la da la huella.
