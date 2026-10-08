# Función: Cocina

**Ruta:** `Tienda → Cocina`
**Controlador:** `Aero\Shop\Controllers\Kitchen`
**Modelo:** `Aero\Shop\Models\Order`
**Permiso:** `aero.shop.manage_orders`

Pantalla para el personal de cocina de un **restaurante**. Solo aparece en el
menú cuando el [tipo de tienda](shop-configuracion) es *Restaurante*; si no, muestra un aviso.

## Tablero

Los pedidos se agrupan en columnas que se refrescan solas cada pocos segundos:

| Columna | Botón de avance |
|---------|-----------------|
| **Nuevos** | Empezar |
| **Preparando** | Listo |
| **Listos** | Entregado |

Cada tarjeta muestra número de pedido, tipo (local, recoger, delivery), mesa,
hora programada, cliente, artículos con extras y nota de cocina por línea, y un
cronómetro. Se puede volver un pedido al paso anterior. Los pedidos entregados
en las últimas 3 horas se listan abajo.

Barra superior: **Sonido** (aviso al llegar pedidos nuevos), **Probar**,
**Pantalla completa**, filtros por tipo de pedido y el botón **modo ocupado**.

## Tiempo estimado

- Al pasar un pedido de *Nuevos* a *Preparando* cocina confirma (o ajusta con
  los botones de minutos) el tiempo propuesto; se calcula con la preparación
  del plato más lento, la carga de la cocina, el modo ocupado y, en delivery, el
  tiempo de reparto.
- El cliente ve el tiempo solo cuando cocina acepta el pedido.
- En *Preparando* cocina puede **extender** el tiempo (hasta 60 min por vez) si hay retraso.
- **Modo ocupado:** suma los minutos configurados a los pedidos que se acepten.

## Efectos sobre el pedido

- Marcar **Listo** envía el aviso «listo» al cliente (si las notificaciones están configuradas).
- Marcar **Entregado** pasa un pedido pendiente o pagado a *Despachado/entregado* y lo anota en el historial («cocina: entregado»).
- Pedidos cancelados o reembolsados no aparecen.

> [!NOTE]
> Cuando el pedido viene de un punto de venta, la tarjeta muestra el origen y
> agrupa los artículos por **ronda de comanda**.

**Versión documentada:** 1.7.0
