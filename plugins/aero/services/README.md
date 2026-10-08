# Aero.Services

Catálogo de servicios de tecnología (ecommerce y marketing): precio, modalidad de cobro, entregables y requisitos.

Cada servicio puede vincularse, de forma opcional, a uno o varios plugins de la plataforma con una relación
(`built_with`, `integrates`, `recommended`) y una nota. Así un servicio puede quedar atado a Aero.Shop,
Aero.Hello, etc., o ser completamente genérico.

```php
Service::active()->forPlugin('Aero.Shop')->get();   // servicios ligados a un plugin
$service->plugin_links;                             // [['plugin' => 'Aero.Shop', 'relation' => 'built_with', 'note' => '…'], …]
```
