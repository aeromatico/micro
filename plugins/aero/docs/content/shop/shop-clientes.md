# Formulario: Clientes

**Ruta:** `Tienda → Clientes`
**Controlador:** `Aero\Shop\Controllers\Customers`
**Modelo:** `Aero\Shop\Models\Customer`
**Permiso:** `aero.shop.manage_orders`

Ficha de los compradores de la tienda. Se crean al comprar y pueden vincularse
a una cuenta de usuario del sitio.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre** | — | Nombre del cliente. |
| **Apellido** | — | Apellido. |
| **Email** | requerido | Correo (identifica al cliente). |
| **Teléfono** | — | Teléfono de contacto. |
| **Cuenta vinculada** | — | Usuario de RainLab.User, si se registró. |

> [!NOTE]
> Un cliente puede comprar como invitado (si está habilitado): igual queda
> registrado para asociar sus pedidos.

> [!TIP]
> El **email** es la clave del cliente: si coincide con un contacto del
> [CRM](../crm/crm-contactos), puedes enlazarlos para tener la visión completa.
