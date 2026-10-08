# Wallet (Mis monedas)

**Ruta:** `Panel → Wallet` (menú inferior)  
**Controlador:** `Aero\Credits\Controllers\Wallet`  
**Permiso:** Ninguno (visible para todos los usuarios del tenant)

Wallet es tu centro de gestión de monedas. Desde acá podés:

- Ver tu saldo actual por color
- Recargar monedas por QR
- Comprar monedas con tu saldo en Bs
- Intercambiar entre diferentes tipos de monedas
- Revisar el historial de movimientos (últimos 100)

## Sección: Saldo actual

Muestra el balance de cada tipo de moneda activa:

| Dato | Descripción |
|------|-------------|
| **Color** | Indicador visual del tipo de moneda |
| **Nombre** | Etiqueta de la moneda (ej. "Bronce", "Plata", "Oro") |
| **Saldo** | Cantidad de monedas disponibles |
| **Precio** | Valor en Bs por unidad (solo si es comprable) |

También ves tu **saldo en Bs** (dinero sobrante de recargas previas).

## Recargar monedas

### Opción 1: Monto fijo

1. Elegí un monto de la lista (ej. 50 Bs, 100 Bs, 200 Bs)
2. Opcionalmente, seleccioná un tipo de moneda específico
3. Clic en **Generar QR**

Si no elegís moneda, el sistema calcula la mejor combinación de monedas disponibles para ese monto.

### Opción 2: Cantidad exacta

1. Elegí el tipo de moneda
2. Ingresá la cantidad de monedas que querés
3. Clic en **Generar QR**

El sistema calcula el monto exacto en Bs. Si hay sobrante (por redondeo), queda en tu saldo en Bs.

### Proceso de pago

1. **QR generado** — Escanealo con tu app de banco o copiá la referencia
2. **Realizá el pago** — Tenés 30 minutos (configurable) antes de que expire
3. **Confirmación automática** — Las monedas se acreditan al confirmar el pago
4. **Notificación** — Recibís un aviso cuando se acrediten

> [!IMPORTANT]
> Si el pago es por un monto diferente al esperado, la recarga queda en revisión manual.

## Comprar con saldo en Bs

Si tenés saldo en Bs (de recargas anteriores), podés comprar monedas directamente:

1. Seleccioná el tipo de moneda
2. Ingresá la cantidad
3. Clic en **Comprar**

La operación es instantánea (sin QR).

## Intercambiar monedas

Podés cambiar un tipo de moneda por otro:

1. **Moneda de origen** — Lo que vas a dar
2. **Moneda de destino** — Lo que vas a recibir
3. **Cantidad** — Cuántas monedas querés cambiar
4. **Cotización** — El sistema muestra cuánto recibís y la comisión (si aplica)
5. Clic en **Intercambiar**

> [!NOTE]
> El intercambio tiene una comisión configurable (% sobre el monto). Verificá la cotización antes de confirmar.

### Restricciones

- Solo podés intercambiar monedas marcadas como "intercambiables"
- Necesitás saldo suficiente de la moneda de origen
- La comisión se descuenta en la moneda de destino

## Historial de movimientos

La tabla muestra los últimos 100 movimientos:

| Columna | Descripción |
|---------|-------------|
| **Fecha** | Cuándo ocurrió el movimiento |
| **Tipo** | Qué lo originó (compra, consumo, intercambio, etc.) |
| **Moneda** | Color y nombre del tipo |
| **Cantidad** | Monto (positivo = entrada, negativo = salida) |
| **Saldo** | Cuánto quedó después de ese movimiento |
| **Detalle** | Descripción adicional (opcional) |

### Tipos de movimiento

**Entradas (+):**
- **Compra** — Recarga por QR confirmada
- **Regalo** — Monedas otorgadas manualmente
- **Incluidas en tu plan** — Crédito inicial del plan
- **Reembolso** — Devolución por servicio fallido
- **Intercambio (entrada)** — Recibiste monedas de un intercambio
- **Ajuste (+)** — Corrección manual de saldo
- **Saldo en Bs** — Sobrante de recarga convertido

**Salidas (−):**
- **Consumo** — Uso de un servicio (mensaje, IA, etc.)
- **Intercambio (salida)** — Diste monedas en un intercambio
- **Comisión de intercambio** — Cargo por intercambio
- **Compra con saldo en Bs** — Compraste monedas con tu saldo
- **Ajuste (−)** — Corrección manual de saldo
- **Vencimiento** — Monedas con fecha de caducidad

> [!TIP]
> Para ver movimientos más antiguos, contactá con soporte.

## Reglas importantes

1. **Una recarga, una moneda** — Cada QR es para un solo tipo de moneda (o mezcla automática si no elegís)
2. **30 minutos para pagar** — Después de ese tiempo, el QR expira y debés generar uno nuevo
3. **Sin edición** — No podés modificar una recarga pendiente; cancelala y creá una nueva
4. **Saldo negativo no permitido** — Si no tenés monedas suficientes, el servicio no se ejecuta
5. **Intercambio irreversible** — Una vez confirmado, no se puede deshacer

---

**Artículos relacionados:**
- [Vista general del sistema de monedas](credits-vista-general)
- [Tarifas: ¿dónde y cuánto te cobramos?](credits-tarifas)
- [Widget de dashboard](credits-widget-dashboard)