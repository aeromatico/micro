# Aero.Clinica — historia clínica por negocio

Medicina humana para consultorios, centros de salud y clínicas. Datos de salud: sensibles.

## Diseño
- Pensado solo para pacientes humanos.
- `Classes\VitalsSchema`: signos vitales (PA, FC, FR, temperatura, SpO2, peso, talla, IMC calculado).

## Diagnósticos: CIE-10
- Búsqueda por código o nombre sobre el catálogo global CIE-10 (`aero_clinica_cie10`, OPS/OMS en español; ver `data/README.md`).
  El título guardado sale del catálogo, no del cliente. Además hay «Frecuentes» por negocio (los más usados en notas firmadas + los fijados), catálogo propio y texto libre.
- Cada diagnóstico es principal/secundario (uno solo principal) y presuntivo/confirmado. Se bloquean al firmar y entran en la huella de la nota.

## Receta
- Texto libre con sugerencias de los medicamentos que la propia clínica ya recetó: **no hay catálogo ni lista única** (en Bolivia no se limita a la LINAME).
- Principio activo, concentración, presentación, dosis, vía, frecuencia, duración, cantidad e indicaciones las escribe siempre el profesional. Todo va cifrado.

## Fase 4: vacunas, archivos, documentos, importación y exportación
- **Vacunación:** registro de dosis (nombre libre con sugerencias, dosis, lote, fabricante, vía/sitio, próxima dosis). Sin catálogo ni esquema de fábrica.
  Calendario del paciente con vencidas/próximas y «Agendar» (Office, `BookingService`).
- **Recordatorios:** `clinica:remind` (diario 09:00). Apagado por defecto, por negocio (`vaccine_reminders`). Notify `clinica.vaccine.due`;
  en personas el aviso no nombra la vacuna. El evento `aero.clinica.vaccineDue` lleva solo ids.
- **Archivos clínicos:** cifrados en disco (`storage/app/clinica-private`, nombre aleatorio), tipo decidido por el contenido (PDF, PNG, JPG, WebP, Word, texto),
  máx. 20 MB, lectura solo por `Controllers\Files` (permiso clínico + tenant + bitácora) con `nosniff` y CSP `sandbox`. Los resultados numéricos de un estudio van a la hoja.
- **Formatos de documentos:** texto plano con `{{variables}}` conocidas (se rechazan las desconocidas); el documento emitido guarda el texto ya resuelto,
  con huella, firma y sello; borrador editable por su autor. Los datos faltantes salen como `__________` y solo un consentimiento puede emitirse así.
- **Importación:** el original se conserva; la asociación (documento/nombre en el nombre del archivo y en el texto de Word/txt) es una *sugerencia*: nada entra
  a una ficha hasta que una persona la confirma. PDF e imágenes solo se asocian por el nombre del archivo.
- **Exportación:** ZIP con JSON descifrado y los archivos originales, por paciente y del negocio (propietario). Quedan en la bitácora.

## Fase 6: arranque, cobros y API
- **Rangos de referencia para adultos** (botón en «Rangos alto/bajo», nunca automático, nunca pisa rangos existentes): borrador **sin revisión médica**.
  Fuentes: OMS y ESC/ESH (hipertensión desde 140/90; hipotensión por debajo de 90/60), OMS (IMC 18,5–24,9), ADA/OMS (glucosa en ayunas 70–99 mg/dL; HbA1c por debajo de 5,7 %),
  ATP III (colesterol total por debajo de 200 y triglicéridos por debajo de 150 mg/dL), valores habituales del adulto en reposo (FC 60–100, FR 12–20, T 36,0–37,5 °C, SpO2 ≥ 95 %).
  No incluye niños, embarazo ni valores que dependen del sexo o del laboratorio (hemoglobina, creatinina). Al editar uno, pasa a ser «del negocio».
- **Plantillas sugeridas** (control de paciente crónico, chequeo preventivo): solo estructura de secciones.
- **Lista inicial de medicina general:** 39 códigos CIE-10 (títulos del catálogo cargado), sin revisión médica; no fija ninguno.
- **Cobros:** efectivo/transferencia/otro quedan cobrados; QR con Aero.Pay (`QrIssuer`, origen `clinica`, descripción genérica «Atención #n») conciliado por `aero.pay.qrStatusChanged`.
  Eventos `aero.clinica.chargePaid/chargeVoided` con solo ids; Aero.Finance los asienta («Atención #n», sin concepto ni diagnóstico) si el negocio lo permite.
  El QR no se probó contra un banco real.
- **API/MCP:** `GET /api/v1/clinica/patients[/{id}]` (scope `clinica.patients.read`, solo datos administrativos, requiere la key del negocio) y recursos MCP de pacientes y cobros (sin concepto). Nada clínico.

## Seguridad
- Texto clínico cifrado en la BD (`encrypted`). Firma y sello cifrados en la BD (nginx publica `storage/`, ni el disco «protegido» es privado).
- Bitácora inmutable de accesos y cambios (solo nombres de campos). Eventos `aero.clinica.*` llevan solo ids (Aero.Api reenvía a webhooks lo que viaja en eventos).
- Recepción no ve contenido clínico (campos con `permissions`, pantallas con `requiredPermissions`).

## Qué NO hay (a propósito)
- Quintos caracteres opcionales de la CIE-10 (sitio en M, fracturas abiertas/cerradas).
- Firma electrónica certificada: la firma es una imagen para impresión; la integridad la da la huella.
