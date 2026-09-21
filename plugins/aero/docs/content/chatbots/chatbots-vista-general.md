# Chatbots — Vista general

**Chatbots** conecta un bot de autorespuesta a tus cuentas de mensajería de
Hello (WhatsApp y redes). El bot responde solo, con reglas de palabras clave o
con IA, según el modo que elijas.

Cada bot se ata a **una cuenta** de Hello. Si tienes varias cuentas conectadas,
puedes tener varios bots.

## Menú

| Sección | Para qué sirve |
|---------|----------------|
| **Bots** | Crear y configurar cada bot y sus reglas. |
| **Registro de respuestas** | Historial de lo que respondió automáticamente. |

## Modos de respuesta

| Modo | Cómo responde |
|------|---------------|
| **Desactivar** | La cuenta queda desconectada del bot: no responde nada. |
| **Chatbot** | Solo reglas de palabras clave y, si nada coincide, un mensaje por defecto. |
| **Chatbot IA** | Responde con un modelo de IA según un prompt del sistema. |
| **Super Chatbot IA** | Como Chatbot IA, pero además consulta **datos reales** de tu sitio o tienda. Puede ser una función PRO. |

## Cómo responde

```text
Mensaje entrante
      ▼
¿Hay una regla que coincida?  ── sí ──▶  responde la regla
      │ no
      ▼
¿El modo es Chatbot?  ── sí ──▶  mensaje por defecto
      │ no (IA)
      ▼
Responde la IA (o no responde nada si falla)
```

> [!NOTE]
> Si un **agente responde manualmente** una conversación, el bot deja de
> contestar durante un tiempo de pausa configurable.

## Guías de cada función

- [Bots](chatbots-bots) — crear y configurar un bot.
- [Reglas](chatbots-reglas) — respuestas por palabra clave.
- [Registro de respuestas](chatbots-logs) — auditoría de respuestas.

## Permisos

| Permiso | Desbloquea |
|---------|-----------|
| `aero.chatbots.manage` | Bots y registro del propio sitio |
| `aero.chatbots.superadmin` | Además, los modelos de IA de la plataforma |
