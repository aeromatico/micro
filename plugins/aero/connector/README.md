# Aero.Connector

Conector genérico de endpoints HTTP, modelos de IA y APIs/webhooks sociales,
pensado para reutilizarse tal cual en otros proyectos OctoberCMS 4 / Laravel 12.

## Qué resuelve

- **Saliente**: define un `Connector` (nombre, tipo, URL base, config, credenciales
  cifradas) y pruébalo desde el backend con un tester integrado que muestra la
  respuesta cruda (status, headers, body, tiempo).
- **Entrante**: define un `WebhookEndpoint` y obtén una URL pública
  (`/connector/webhooks/{slug}`) para dar a cualquier proveedor externo; cada
  entrega se verifica (HMAC, token o ninguna), se registra y dispara un evento
  Laravel interno para que otro plugin reaccione.

## Tipos incluidos

- `http` — REST genérico (método/headers/auth configurables).
- `ai_openai_compatible` — OpenAI, OpenRouter, GLM y cualquier API compatible con
  `/chat/completions`.
- `ai_anthropic` — API de Claude (`/v1/messages`).

## Agregar un tipo nuevo (desde otro plugin)

```php
Event::listen('aero.connector.registerTypes', function () {
    return [
        'mi_tipo' => [
            'label'    => 'Mi API',
            'category' => 'http', // http | ai | social
            'driver'   => \Mi\Plugin\Drivers\MiDriver::class, // implementa Aero\Connector\Contracts\ConnectorDriver
            'auth'     => 'bearer', // none|bearer|basic|api_key_header|api_key_query
        ],
    ];
});
```

## Reaccionar a un webhook entrante

```php
Event::listen('aero.connector.webhook.mi_evento', function ($endpoint, array $payload, $request) {
    // ...
});
```

El nombre del evento es el que se configuró en el campo "Evento a disparar" del
`WebhookEndpoint` — no está fijado por este plugin, cada endpoint declara el suyo.
