# SMS — Vista general

**SMS** te permite enviar mensajes de texto simples y **masivos** con tu propio
número, con plantillas, programación y **consumo medido en créditos**.

Todo funciona por sitio (tenant): tus envíos, plantillas y consumo son tuyos.
El superadmin además administra el proveedor y las bajas globales.

## Menú

| Sección | Para qué sirve |
|---------|----------------|
| **Enviar** | Mandar un SMS o un lote pegando números o subiendo un CSV. |
| **Mensajes** | Historial de mensajes y su estado. |
| **Lotes** | Envíos masivos y su progreso. |
| **Consumo** | Cuántos SMS y créditos usaste. |
| **Plantillas** | Mensajes reutilizables con variables. |

## Flujo de un envío

```text
Redactas (o eliges plantilla)  →  pegas destinatarios / CSV
        ▼
Se cotiza (segmentos × destinatarios = créditos)  →  confirmas
        ▼
Mensaje o lote en cola  →  se envía y se registra
```

## Guías de cada función

- [Enviar](sms-enviar) — envío simple y masivo.
- [Mensajes](sms-mensajes) — historial y estado.
- [Lotes](sms-lotes) — envíos masivos.
- [Consumo](sms-consumo) — uso y saldo.
- [Plantillas](sms-plantillas) — mensajes reutilizables.

## Permisos

| Permiso | Desbloquea |
|---------|-----------|
| `aero.sms.use` | Enviar, ver mensajes/lotes, plantillas y consumo propios |
| `aero.sms.superadmin` | Además, configuración, bajas y datos de todos los sitios |
