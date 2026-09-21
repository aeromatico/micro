# Widget "Mis créditos"

**Ruta:** `Dashboard → Agregar widget → Mis créditos`  
**Clase:** `Aero\Credits\ReportWidgets\MyCredits`  
**Permiso:** Ninguno (visible para todos los usuarios del tenant)

El widget "Mis créditos" muestra tu saldo de monedas actual directamente en el dashboard. Es una manera rápida de consultar cuántas monedas tenés sin entrar a Wallet.

## Qué muestra

Para cada tipo de moneda activa:

- **Color** — Indicador visual
- **Nombre** — Etiqueta de la moneda
- **Saldo** — Cantidad disponible
- **Valor en USD** — Equivalente aproximado en dólares (opcional, según configuración)

> [!NOTE]
> Si no tenés un sitio activo (sos superadmin sin sitio elegido), el widget no muestra nada.

## Cómo agregarlo

1. Andá al **Dashboard**
2. Clic en **Personalizar dashboard** (esquina superior derecha)
3. Clic en **Agregar widget**
4. Buscá y seleccioná **Mis créditos**
5. (Opcional) Cambiá el título en las propiedades
6. Clic en **Agregar widget**

## Propiedades configurables

| Propiedad | Descripción | Valor por defecto |
|-----------|-------------|-------------------|
| **Título** | Encabezado del widget | "Mis créditos" |

## Diferencia con el widget de navbar

El widget de navbar (barra superior) se muestra **siempre**, mientras que este widget solo aparece si lo agregaste al dashboard. Ambos muestran los mismos datos, pero el de navbar es más compacto y tiene un link directo a Wallet.

> [!TIP]
> Si solo querés ver tu saldo rápidamente, el widget de navbar es suficiente. Este widget es útil si querés tener una vista más detallada junto con otros reportes en tu dashboard.

---

**Artículos relacionados:**
- [Vista general del sistema de monedas](credits-vista-general)
- [Wallet (Mis monedas)](credits-wallet)