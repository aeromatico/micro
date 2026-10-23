---
title: Iglesia — vista general
sort: 0
featured: true
---
# Iglesia — vista general

El módulo **Iglesia** ordena la vida de una congregación: quiénes son sus miembros y familias, cómo se organizan en ministerios y células, los eventos y la asistencia, los diezmos y ofrendas, el seguimiento de visitantes, las peticiones de oración y la comunicación por WhatsApp. Incluye un sitio público (predicaciones, noticias, eventos, transmisión en vivo) y un portal privado para cada miembro.

**Ruta:** `Iglesia` (menú lateral)
**Permiso:** `aero.church.use` (administración) y los roles de [roles y permisos](church-roles-permisos)

> [!NOTE]
> Cada iglesia es un espacio independiente: nadie ve los datos de otra iglesia. Una misma iglesia puede tener varias **sedes**.

## Qué incluye

| Área | Para qué sirve | Artículo |
|------|----------------|----------|
| **Miembros, familias y sedes** | Registro de personas, estados, consentimientos y acceso al portal. | [Miembros](church-miembros) |
| **Ministerios y células** | Grupos con líderes, integrantes, reuniones y asistencia. | [Grupos](church-grupos) |
| **Eventos** | Cultos y actividades con inscripción, cupos y asistencia con QR. | [Eventos](church-eventos) |
| **Aportes** | Diezmos, ofrendas y donaciones con QR bancario y comprobante. | [Aportes](church-aportes) |
| **Visitantes** | Formulario de bienvenida con QR y seguimiento. | [Visitantes](church-visitantes) |
| **Oración** | Peticiones privadas con acompañamiento pastoral. | [Oración](church-oracion) |
| **Comunicados** | WhatsApp segmentado, cumpleaños y avisos de cambios. | [Comunicados](church-comunicados) |
| **Sitio público y portal** | Predicaciones, recursos, noticias, en vivo y portal del miembro. | [Sitio público](church-sitio-publico) |
| **Resumen, reportes y auditoría** | Indicadores, Excel/PDF e historial de operaciones. | [Reportes](church-reportes-auditoria) |

## Se conecta con el resto de tu plataforma

Todas las conexiones son opcionales: Iglesia funciona sola y se enriquece con lo que ya tengas activo.

- **Bolivia Pay:** los aportes por QR se emiten con la cuenta bancaria que elijas y se confirman solos al pagarse.
- **Finanzas:** cada aporte confirmado se registra como ingreso (diezmos, ofrendas, donaciones); si se anula, se anula su asiento.
- **WhatsApp (Hello):** comunicados, recordatorios de eventos y cumpleaños, siempre con el consentimiento de la persona.
- **Notificaciones:** aviso interno cuando llega un visitante o una petición de oración (sin su contenido).
- **API y Google Sheets:** consulta de miembros, eventos y visitantes por API; importación y exportación con Sheets.

> [!IMPORTANT]
> El sitio público y el portal del miembro vienen **apagados**. Se activan en *Iglesia → Configuración → Sitio público*.

**Versión documentada:** 1.6.0
