# Formulario: Configuración de pagos QR

**Ruta:** `Bolivia Pay → Configuración de pagos QR`
**Controlador:** `Aero\Pay\Controllers\QrboSettings`
**Modelo:** `Aero\Pay\Models\QrboSettings`
**Permiso:** `aero.pay.manage_settings`

Define los valores por defecto del cobro con QR para tu sitio. Se guardan por
tenant y se aplican al **Generar código**, aunque puedes cambiarlos en cada QR.

## Campos

| Campo | Para qué sirve |
|-------|----------------|
| **Habilitar sucursales** | Muestra el selector de sucursal al generar un QR. Apagado, no aparece aunque tengas sucursales. |
| **Habilitar dólares (USD)** | Permite emitir QR en USD. Actívalo **solo** si tienes una cuenta en dólares conectada. |
| **Vencimiento por defecto** | Fecha que se precarga: 1 día, 1 semana o 1 año. |
| **Habilitar manejo de impuestos** | Muestra el campo NIT y aplica el porcentaje. **No emite facturas**: solo facilita la cobranza. |
| **Porcentaje de impuesto** | Porcentaje aplicado (solo si el manejo de impuestos está activo). |
| **El monto ya incluye el impuesto** | Define si el monto ingresado es el precio final o si el impuesto se suma. |

## Cómo se interpreta el impuesto

| Opción | Comportamiento |
|--------|----------------|
| **Activado** (monto incluye) | El importe que escribes ya es el total; el impuesto se calcula y guarda como referencia. |
| **Desactivado** (monto no incluye) | Al importe que escribes se le suma el impuesto, y ese total es el del QR. |

> [!WARNING]
> Emitir un QR en USD contra una cuenta en bolivianos puede no acreditarse.
> Habilita dólares solo con una cuenta en dólares conectada.

> [!NOTE]
> Si dejas el vencimiento por defecto en un valor distinto a 1, 7 o 365 días, el
> sistema vuelve a 1 semana (7 días).

> [!TIP]
> Esta misma pantalla suele estar accesible además desde la **Configuración**
> central, para tener los ajustes del sitio en un solo lugar.
