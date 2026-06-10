# Skills de Claude Code

Skills = comandos slash (`/nombre`) disponibles en Claude Code para este proyecto.

Los archivos están en `.claude/commands/` en la raíz del proyecto y son cargados automáticamente cuando se trabaja en este directorio.

## Resumen de todos los skills

### Frontend (Tema + UI)

| Skill | Descripción breve |
|-------|------------------|
| [`/october-theme`](frontend.md#october-theme) | Configurar Tailwind + Alpine.js en el tema |
| [`/october-page`](frontend.md#october-page) | Nueva página CMS con Pines/Tailwind |
| [`/october-partial`](frontend.md#october-partial) | Nuevo partial reutilizable con Pines |
| [`/pines`](frontend.md#pines) | Agregar cualquier componente Pines a un archivo |

### Backend (Plugins + Lógica)

| Skill | Descripción breve |
|-------|------------------|
| [`/october-plugin`](backend.md#october-plugin) | Scaffold de plugin completo |
| [`/october-crud`](backend.md#october-crud) | CRUD completo: Model + Controller + YAMLs |
| [`/october-model`](backend.md#october-model) | Solo Model + Migration + Controller base |
| [`/october-component`](backend.md#october-component) | Componente CMS (PHP + template Twig) |
| [`/october-relation`](backend.md#october-relation) | Relaciones entre modelos + RelationController |
| [`/october-api`](backend.md#october-api) | Endpoint REST API con Resource/Collection |
| [`/october-tailor`](backend.md#october-tailor) | Blueprint Tailor (entry/global/stream/mixin) |
| [`/october-event`](backend.md#october-event) | Eventos, Listeners, Subscribers, Hooks del core |
| [`/october-job`](backend.md#october-job) | Job asíncrono con Redis queue |
| [`/october-mail`](backend.md#october-mail) | Mailable + template Twig + registro |
| [`/october-scope`](backend.md#october-scope) | Scopes Eloquent, filtros, cache Redis, Observers |
| [`/october-command`](backend.md#october-command) | Comando Artisan con progress bar y scheduling |

## Cómo usar un skill

Escribir el nombre del skill en el prompt de Claude Code:

```
/october-crud Micro/Blog Post title:string content:richtext published_at:datepicker
```

Claude Code cargará el skill y ejecutará las instrucciones con los argumentos proporcionados.

## Cómo crear un skill nuevo

1. Crear un archivo `.md` en `.claude/commands/`
2. El nombre del archivo = nombre del skill (sin extensión)
3. El contenido describe qué debe hacer Claude cuando se invoca

```markdown
# Mi Skill

## Uso
`/mi-skill <arg1> [arg2]`

## Instrucciones
Dado $ARGUMENTS, hacer:
1. Crear archivo X
2. Modificar Y
3. Ejecutar Z
```

Ver [documentación de skills de Claude Code](https://docs.anthropic.com/claude-code/skills) para más detalles.
