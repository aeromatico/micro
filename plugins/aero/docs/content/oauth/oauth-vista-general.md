---
title: Cuentas conectadas — vista general
sort: 1
featured: false
---
# Cuentas conectadas (acceso con Google)

**Ruta:** `/backend/aero/oauth/connections` · **Permiso:** `aero.oauth.use`

Te permite vincular tu propia cuenta de Google (y, en el futuro, otros proveedores) a tu usuario del panel. Con la cuenta vinculada puedes:

- **Iniciar sesión con Google** desde la pantalla de acceso del panel, si la plataforma lo tiene activado.
- Dar permiso a otras funciones del panel (por ejemplo, la sincronización con hojas de cálculo) para trabajar con Google **en tu nombre**.

## Permisos incrementales

Cada función te pide solo el permiso que necesita, cuando lo necesita. Puedes ver tus cuentas vinculadas en la pantalla **Cuentas conectadas**. Los tokens se guardan cifrados.

> [!NOTE]
> Si el acceso con Google falla, vuelves a la pantalla de acceso correcta. Desde la versión 1.0.1 un acceso correcto entra directo al panel.

## Qué no ves

Los interruptores del inicio de sesión y la URI de redirección son ajustes de la plataforma.

**Versión documentada:** 1.0.1
