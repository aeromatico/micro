<?php

use Aero\Docs\Models\Article;
use Aero\Docs\Models\Category;
use October\Rain\Database\Updates\Seeder;

return new class extends Seeder
{
    public function run(): void
    {
        if (Category::count() > 0) {
            return;
        }

        $start = Category::create(['name' => 'Primeros pasos', 'slug' => 'primeros-pasos', 'icon' => '🚀', 'description' => 'Todo lo que necesitas para empezar.']);
        $acct  = Category::create(['name' => 'Tu cuenta', 'slug' => 'tu-cuenta', 'icon' => '👤', 'parent_id' => $start->id]);
        $api   = Category::create(['name' => 'API', 'slug' => 'api', 'icon' => '🔌', 'description' => 'Integra Market con tus sistemas.']);
        $auth  = Category::create(['name' => 'Autenticación', 'slug' => 'autenticacion', 'icon' => '🔑', 'parent_id' => $api->id]);

        Article::create([
            'category_id' => $start->id, 'title' => 'Bienvenido a la documentación', 'slug' => 'bienvenido', 'is_featured' => true,
            'content' => <<<'MD'
# Bienvenido

Esta documentación está escrita en **Markdown** y organizada en categorías multinivel.

> [!TIP]
> Usa el buscador de arriba o el árbol de la izquierda para moverte rápido.

## Qué puedes escribir

- Listas, **negritas**, _cursivas_ y ~~tachado~~
- [ ] Listas de tareas
- Tablas:

| Plan | Precio |
|------|--------|
| Básico | Gratis |
| Pro | Bs 99 |

### Código

```php
echo "Hola, Market";
```

> [!WARNING]
> Nunca compartas tus claves de API.
MD,
        ]);

        Article::create([
            'category_id' => $acct->id, 'title' => 'Crear tu cuenta', 'slug' => 'crear-tu-cuenta',
            'content' => "## Registro\n\n1. Entra a **Comprar**.\n2. Elige tu plan.\n3. Confirma tu correo.\n\n## Siguientes pasos\n\nConfigura tu micrositio desde el panel.",
        ]);

        Article::create([
            'category_id' => $auth->id, 'title' => 'Claves de API', 'slug' => 'claves-de-api', 'is_featured' => true,
            'content' => "## Enviar la clave\n\n```bash\ncurl -H \"Authorization: Bearer TU_CLAVE\" https://micro.clouds.com.bo/api/v1/ping\n```\n\n> [!NOTE]\n> Cada clave tiene permisos por área.\n\n## Rotación\n\nPuedes revocar y crear claves desde el panel.",
        ]);
    }
};
