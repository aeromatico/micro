# Función: Conectar cuenta

**Ruta:** `Hello → Conectar cuenta`
**Controlador:** `Aero\Hello\Controllers\Connect`
**Permiso:** `aero.hello.manage_accounts`

Vincula un número de WhatsApp a tu sitio. Hay **dos caminos**, según cómo
quieras conectar el número.

> [!TIP]
> ¿No sabes cuál te conviene? Ver la [comparación completa](hello-comparativa).

## WhatsApp Web (por QR)

Conexión **self-service** e inmediata:

1. Ponle un **nombre** a la conexión (el número o el negocio).
2. Elige el método: **QR** o **código de emparejamiento** (con teléfono).
3. Escanea el QR (o ingresa el código) desde WhatsApp → *Dispositivos
   vinculados*.
4. Al conectar, la cuenta queda disponible para redactar, bandeja y bots.

> [!NOTE]
> Este camino requiere el plugin **aero/wapi** (WhatsApp Web). Sin él, solo se
> muestra la opción de Cloud API.

## WhatsApp Cloud API (Hello/Meta)

Alta **asistida**: agrega al correo del equipo
(`social@market.com.bo`) como administrador de tu portafolio comercial de Meta
y el equipo completa la conexión desde la configuración de Hello cuando tenga
acceso.

> [!IMPORTANT]
> Cloud API exige **plantilla aprobada por Meta** para escribir fuera de la
> ventana de 24 h. La cuenta de WhatsApp Web envía el texto tal cual.

## Listado de cuentas

Muestra tus conexiones con su estado y fecha de conexión. Desde aquí también
puedes administrarlas.

> [!TIP]
> Usa un **nombre claro** por número (el negocio o la sucursal): si manejas
> varias cuentas, te ayudará a elegir la correcta al redactar o responder.
