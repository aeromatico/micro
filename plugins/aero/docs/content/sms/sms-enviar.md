# Función: Enviar

**Ruta:** `SMS → Enviar`
**Controlador:** `Aero\Sms\Controllers\Compose`
**Permiso:** `aero.sms.use`

Envía un SMS a una persona o a muchos destinatarios. Puedes pegar los números o
subir un **CSV**, y personalizar el texto por destinatario con **plantillas**.

## Cómo se arma un envío

| Paso | Qué eliges |
|------|------------|
| **Mensaje** | Escribe el texto o elige una **plantilla**. |
| **Destinatarios** | Pega los números (uno por línea) o sube un **CSV**. |
| **Programación** | Enviar ahora o programar fecha/hora. |
| **Cotización** | El sistema calcula segmentos y **créditos** antes de enviar. |

## Destinatarios y variables

- Pega números separados por línea, coma o punto y coma.
- Si subes un CSV cuya **primera fila no es un teléfono**, se toma como
  **cabecera** y las demás columnas pasan a ser variables de la plantilla:

```csv
telefono,nombre,monto
71234567,Ana,50
76543210,Luis,30
```

Con la plantilla `Hola {{nombre}}, tu saldo es {{monto}}`, cada persona recibe su
texto personalizado.

- Un mismo número repetido **no se cobra dos veces**: se envía una sola vez.

## Cuota y créditos

Cada SMS se divide en **segmentos** según su longitud y codificación. La
cotización muestra: destinatarios, segmentos por SMS, y el total de **créditos**.
Si no tienes saldo suficiente, **no se envía nada**.

- Un destinatario → **Mensaje**.
- Varios destinatarios → **Lote**.

> [!NOTE]
> Si un número está en la **lista de bajas** (respondió STOP o fue dado de baja),
> el mensaje queda bloqueado y no se envía.

> [!TIP]
> Usa una plantilla con variables y un CSV con cabecera: es la forma más rápida
> de personalizar un envío masivo sin tocar el texto del mensaje.
