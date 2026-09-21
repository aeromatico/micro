# Función: Consumo

**Ruta:** `SMS → Consumo`
**Controlador:** `Aero\Sms\Controllers\Usage`
**Permiso:** `aero.sms.use`

Muestra cuántos SMS usaste y cuántos **créditos** consumiste, en el rango de
días que elijas (por defecto 30). Los datos se agrupan **por día** y **por API
key**.

## Qué muestra

| Bloque | Para qué sirve |
|--------|----------------|
| **Por día** | Mensajes, segmentos, entregados, fallidos y créditos de cada día. |
| **Por key** | El mismo resumen agrupado por credencial de envío. |
| **Saldo** | Créditos disponibles (si el cobro por créditos está activo). |
| **Valor por segmento** | Créditos que cuesta cada segmento. |

## Para qué sirve

- Controlar el gasto en SMS mes a mes.
- Detectar picos o campañas con muchos fallos.
- Ver el **saldo** antes de lanzar un envío grande.

> [!NOTE]
> El superadmin ve el consumo **agregado por tenant** (para cobrar y auditar);
> tú ves el consumo **de tu sitio**.

> [!TIP]
> Compara **segmentos** con **mensajes**: un mensaje largo ocupa varios
> segmentos, así que unos pocos SMS pueden consumir más de lo que parece.
