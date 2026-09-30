# Formulario: Bots

**Ruta:** `Chatbots → Bots`
**Controlador:** `Aero\Chatbots\Controllers\Bots`
**Modelo:** `Aero\Chatbots\Models\Bot`
**Permiso:** `aero.chatbots.manage`

Crea y configura cada bot. Un bot se ata a **una cuenta** de Hello y define
cómo responde: con reglas, con IA o desactivado.

## Campos

### Identificación y modo

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre del bot** | opcional | Para identificarlo en la lista y en los registros. Vacío = se genera a partir de la cuenta. |
| **Modo de respuesta** | — | Desactivar, Chatbot, Chatbot IA o Super Chatbot IA. |
| **Cuenta** | requerido, única | Cuenta de Hello que este bot contesta. |
| **Tenant** | — | Solo lo elige el superadmin; a un tenant se le asigna solo y el campo no aparece. |

### IA (modos Chatbot IA y Super Chatbot IA)

| Campo | Para qué sirve |
|-------|----------------|
| **Conector de IA** | Conector de Aero.Connector que se usará. |
| **Modelo** | Modelo del conector elegido (catálogo de la plataforma). |
| **Prompt del sistema** | Cómo se presenta y comporta el bot. Vacío = prompt genérico de atención por WhatsApp en español. |

### Respuestas automáticas (reglas)

| Campo | Para qué sirve |
|-------|----------------|
| **Incluir respuestas automáticas** | Solo en modos con IA. Activo (por defecto): primero se revisan las reglas; si una coincide responde la regla y, si no, responde la IA. Inactivo: la IA responde siempre y las reglas se ocultan. |

### Mensaje por defecto

| Campo | Para qué sirve |
|-------|----------------|
| **Mensaje por defecto** | Se envía cuando nada coincide, **solo en modo Chatbot**. En modos IA, si la IA falla no se responde nada. Vacío = no responde. |

### Pausa y datos en tiempo real

| Campo | Para qué sirve |
|-------|----------------|
| **Pausa tras respuesta humana (minutos)** | Si un agente contesta a mano, el bot se detiene ese tiempo (por defecto 15). |
| **Fuentes de datos en tiempo real** | Exclusivo de Super Chatbot IA: el bot consulta datos reales de las fuentes marcadas (Sitio web, Tienda). |

## Notas de formato (WhatsApp)

WhatsApp solo soporta `*negrita*`, `_cursiva_`, `~tachado~` y `` `monoespaciado` ``.
Los títulos, listas y enlaces con formato Markdown llegan con los símbolos
literales. Para emojis usa el atajo del sistema (Win+. / Cmd+Ctrl+Space).

> [!NOTE]
> *Desactivar* es la única fuente de verdad del estado: no existe un interruptor
> aparte de activo/inactivo. Al elegirlo, el bot corta por completo sus respuestas.

> [!TIP]
> La pestaña **Reglas** (ver [Reglas](chatbots-reglas)) es donde cargas las
> respuestas de palabra clave. Si usas IA, las reglas siguen teniendo prioridad
> cuando coinciden, mientras **Incluir respuestas automáticas** esté activo.

> [!NOTE]
> La pestaña de reglas se ve en modo Chatbot y, en los modos con IA, solo si
> **Incluir respuestas automáticas** está activo. Las reglas se cargan después
> de crear el bot.

**Versión documentada:** 1.6.0
