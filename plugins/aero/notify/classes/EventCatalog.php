<?php namespace Aero\Notify\Classes;

/**
 * Fuente única del catálogo inicial de eventos. La consumen tanto la migración
 * seed_notify_events.php como el comando notify:seed-events, para que no vuelva
 * a pasar lo del sistema de referencia, donde el seeder de consola y el de
 * migración eran dos copias que se fueron desincronizando.
 *
 * Cada entrada declara su contrato de variables: variables_schema valida el
 * contexto en Notify::fire() y sample_context alimenta el preview y el botón
 * de enviar prueba.
 */
class EventCatalog
{
    /** Variables presentes en todos los eventos con tenant. */
    protected const TENANT_VARS = [
        'tenant_name' => ['type' => 'string', 'required' => false, 'label' => 'Nombre del tenant'],
    ];

    public static function all(): array
    {
        return array_merge(
            static::sites(),
            static::qrbo(),
            static::crm(),
            static::shop(),
            static::hello(),
            static::system(),
        );
    }

    public static function codes(): array
    {
        return array_column(static::all(), 'code');
    }

    public static function byGroup(): array
    {
        $grouped = [];

        foreach (static::all() as $event) {
            $grouped[$event['source_plugin']][] = $event;
        }

        return $grouped;
    }

    protected static function sites(): array
    {
        return [
            [
                'code' => 'sites.contact.submitted',
                'source_plugin' => 'Aero.Sites',
                'category' => 'support',
                'name' => 'Formulario de contacto recibido',
                'description' => 'Alguien envió el formulario de contacto de un micrositio.',
                'priority' => 3,
                'default_audiences' => ['tenant_admin'],
                'default_channels' => ['email', 'inapp'],
                'variables_schema' => [
                    'name'    => ['type' => 'string', 'required' => true,  'label' => 'Nombre de quien escribe'],
                    'email'   => ['type' => 'string', 'required' => false, 'label' => 'Email de contacto'],
                    'phone'   => ['type' => 'string', 'required' => false, 'label' => 'Teléfono'],
                    'message' => ['type' => 'string', 'required' => true,  'label' => 'Mensaje'],
                    'page'    => ['type' => 'string', 'required' => false, 'label' => 'Página de origen'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'name' => 'María Quispe', 'email' => 'maria@ejemplo.bo',
                    'phone' => '+59170000000', 'message' => 'Quisiera una cotización.',
                    'page' => '/contacto', 'tenant_name' => 'Demo',
                ],
            ],
            [
                'code' => 'sites.tenant.created',
                'source_plugin' => 'Aero.Sites',
                'category' => 'system',
                'name' => 'Tenant creado',
                'description' => 'Se aprovisionó un micrositio nuevo.',
                'priority' => 4,
                'default_audiences' => ['superadmin'],
                'default_channels' => ['email', 'inapp'],
                'variables_schema' => [
                    'tenant_name' => ['type' => 'string', 'required' => true, 'label' => 'Nombre del tenant'],
                    'handle'      => ['type' => 'string', 'required' => true, 'label' => 'Handle'],
                    'niche_type'  => ['type' => 'string', 'required' => false, 'label' => 'Nicho'],
                    'admin_email' => ['type' => 'string', 'required' => false, 'label' => 'Email del admin'],
                ],
                'sample_context' => [
                    'tenant_name' => 'Panadería Delicia', 'handle' => 'delicia',
                    'niche_type' => 'restaurant', 'admin_email' => 'admin@delicia.bo',
                ],
            ],
            [
                'code' => 'sites.tenant.suspended',
                'source_plugin' => 'Aero.Sites',
                'category' => 'system',
                'name' => 'Tenant suspendido',
                'description' => 'Un micrositio pasó a estado suspendido.',
                'priority' => 2,
                'default_audiences' => ['superadmin', 'tenant_admin'],
                'default_channels' => ['email'],
                'variables_schema' => [
                    'tenant_name' => ['type' => 'string', 'required' => true, 'label' => 'Nombre del tenant'],
                    'reason'      => ['type' => 'string', 'required' => false, 'label' => 'Motivo'],
                ],
                'sample_context' => ['tenant_name' => 'Panadería Delicia', 'reason' => 'Falta de pago'],
            ],
            [
                'code' => 'sites.domain.verified',
                'source_plugin' => 'Aero.Sites',
                'category' => 'system',
                'name' => 'Dominio verificado',
                'description' => 'Un dominio personalizado quedó apuntando correctamente.',
                'priority' => 5,
                'default_audiences' => ['tenant_admin'],
                'default_channels' => ['email', 'inapp'],
                'variables_schema' => [
                    'domain' => ['type' => 'string', 'required' => true, 'label' => 'Dominio'],
                ] + self::TENANT_VARS,
                'sample_context' => ['domain' => 'delicia.bo', 'tenant_name' => 'Panadería Delicia'],
            ],
            [
                'code' => 'sites.domain.expiring',
                'source_plugin' => 'Aero.Sites',
                'category' => 'system',
                'name' => 'Dominio por vencer',
                'description' => 'Un dominio vence pronto.',
                'priority' => 3,
                'default_audiences' => ['tenant_admin', 'superadmin'],
                'default_channels' => ['email'],
                'variables_schema' => [
                    'domain'     => ['type' => 'string',  'required' => true, 'label' => 'Dominio'],
                    'expires_at' => ['type' => 'date',    'required' => true, 'label' => 'Fecha de vencimiento'],
                    'days_left'  => ['type' => 'number',  'required' => true, 'label' => 'Días restantes'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'domain' => 'delicia.bo', 'expires_at' => '2026-10-15',
                    'days_left' => 15, 'tenant_name' => 'Panadería Delicia',
                ],
            ],
            [
                'code' => 'sites.user.invited',
                'source_plugin' => 'Aero.Sites',
                'category' => 'system',
                'name' => 'Usuario invitado al tenant',
                'description' => 'Se invitó a alguien a colaborar en un micrositio.',
                'priority' => 4,
                'default_audiences' => ['actor'],
                'default_channels' => ['email', 'whatsapp'],
                'variables_schema' => [
                    'invitee_name' => ['type' => 'string', 'required' => true, 'label' => 'Nombre del invitado'],
                    'role'         => ['type' => 'string', 'required' => true, 'label' => 'Rol asignado'],
                    'invite_url'   => ['type' => 'string', 'required' => true, 'label' => 'Enlace de invitación'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'invitee_name' => 'Carlos Rojas', 'role' => 'admin',
                    'invite_url' => 'https://micro.clouds.com.bo/invite/abc123',
                    'tenant_name' => 'Panadería Delicia',
                ],
            ],
        ];
    }

    protected static function qrbo(): array
    {
        $money = [
            'amount'   => ['type' => 'number', 'required' => true,  'label' => 'Monto'],
            'currency' => ['type' => 'string', 'required' => false, 'label' => 'Moneda'],
        ];

        return [
            [
                'code' => 'qrbo.payment.received',
                'source_plugin' => 'Aero.Qrbo',
                'category' => 'billing',
                'name' => 'Pago QR recibido',
                'description' => 'El banco confirmó un pago sobre un QR generado.',
                'priority' => 2,
                'default_audiences' => ['tenant_admin'],
                'default_channels' => ['inapp', 'email'],
                'variables_schema' => $money + [
                    'payer_name' => ['type' => 'string', 'required' => false, 'label' => 'Pagador'],
                    'reference'  => ['type' => 'string', 'required' => false, 'label' => 'Referencia'],
                    'branch'     => ['type' => 'string', 'required' => false, 'label' => 'Sucursal'],
                    'paid_at'    => ['type' => 'datetime', 'required' => false, 'label' => 'Fecha de pago'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'amount' => 350.00, 'currency' => 'BOB', 'payer_name' => 'Juan Pérez',
                    'reference' => 'QR-00123', 'branch' => 'Sucursal Centro',
                    'paid_at' => '2026-08-28 14:32:00', 'tenant_name' => 'Panadería Delicia',
                ],
            ],
            [
                'code' => 'qrbo.payment.failed',
                'source_plugin' => 'Aero.Qrbo',
                'category' => 'billing',
                'name' => 'Pago QR fallido',
                'description' => 'El procesamiento de un pago falló o fue rechazado.',
                'priority' => 2,
                'default_audiences' => ['tenant_admin'],
                'default_channels' => ['inapp'],
                'variables_schema' => $money + [
                    'reference' => ['type' => 'string', 'required' => false, 'label' => 'Referencia'],
                    'reason'    => ['type' => 'string', 'required' => false, 'label' => 'Motivo'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'amount' => 350.00, 'currency' => 'BOB', 'reference' => 'QR-00123',
                    'reason' => 'Cuenta destino inactiva', 'tenant_name' => 'Panadería Delicia',
                ],
            ],
            [
                'code' => 'qrbo.qr.expired',
                'source_plugin' => 'Aero.Qrbo',
                'category' => 'billing',
                'name' => 'QR expirado sin pago',
                'description' => 'Un QR llegó a su fecha límite sin recibir pago.',
                'priority' => 6,
                'default_audiences' => ['tenant_admin'],
                'default_channels' => ['inapp'],
                'variables_schema' => $money + [
                    'reference' => ['type' => 'string', 'required' => false, 'label' => 'Referencia'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'amount' => 120.00, 'currency' => 'BOB',
                    'reference' => 'QR-00456', 'tenant_name' => 'Panadería Delicia',
                ],
            ],
            [
                'code' => 'qrbo.charge.created',
                'source_plugin' => 'Aero.Qrbo',
                'category' => 'billing',
                'name' => 'Cargo mensual generado',
                'description' => 'La facturación mensual generó un cargo para el tenant.',
                'priority' => 3,
                'default_audiences' => ['tenant_admin'],
                'default_channels' => ['email', 'inapp'],
                'variables_schema' => $money + [
                    'period_start' => ['type' => 'date', 'required' => true, 'label' => 'Inicio del periodo'],
                    'period_end'   => ['type' => 'date', 'required' => true, 'label' => 'Fin del periodo'],
                    'due_date'     => ['type' => 'date', 'required' => false, 'label' => 'Vencimiento'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'amount' => 210.50, 'currency' => 'BOB', 'period_start' => '2026-08-01',
                    'period_end' => '2026-08-31', 'due_date' => '2026-09-10',
                    'tenant_name' => 'Panadería Delicia',
                ],
            ],
            [
                'code' => 'qrbo.charge.overdue',
                'source_plugin' => 'Aero.Qrbo',
                'category' => 'billing',
                'name' => 'Cargo vencido',
                'description' => 'Un cargo mensual pasó su fecha de vencimiento sin pagarse.',
                'priority' => 2,
                'default_audiences' => ['tenant_admin', 'superadmin'],
                'default_channels' => ['email'],
                'variables_schema' => $money + [
                    'due_date'  => ['type' => 'date',   'required' => true, 'label' => 'Vencimiento'],
                    'days_late' => ['type' => 'number', 'required' => true, 'label' => 'Días de atraso'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'amount' => 210.50, 'currency' => 'BOB', 'due_date' => '2026-09-10',
                    'days_late' => 5, 'tenant_name' => 'Panadería Delicia',
                ],
            ],
            [
                'code' => 'qrbo.subscription.renewed',
                'source_plugin' => 'Aero.Qrbo',
                'category' => 'billing',
                'name' => 'Suscripción renovada',
                'description' => 'La suscripción del tenant se renovó por otro periodo.',
                'priority' => 5,
                'default_audiences' => ['tenant_admin'],
                'default_channels' => ['email'],
                'variables_schema' => [
                    'plan_name'  => ['type' => 'string', 'required' => true,  'label' => 'Plan'],
                    'renewed_at' => ['type' => 'date',   'required' => false, 'label' => 'Fecha de renovación'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'plan_name' => 'Profesional', 'renewed_at' => '2026-09-01',
                    'tenant_name' => 'Panadería Delicia',
                ],
            ],
            [
                'code' => 'qrbo.subscription.cancelled',
                'source_plugin' => 'Aero.Qrbo',
                'category' => 'billing',
                'name' => 'Suscripción cancelada',
                'description' => 'La suscripción del tenant fue cancelada.',
                'priority' => 3,
                'default_audiences' => ['tenant_admin', 'superadmin'],
                'default_channels' => ['email'],
                'variables_schema' => [
                    'plan_name' => ['type' => 'string', 'required' => true,  'label' => 'Plan'],
                    'reason'    => ['type' => 'string', 'required' => false, 'label' => 'Motivo'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'plan_name' => 'Profesional', 'reason' => 'Solicitud del cliente',
                    'tenant_name' => 'Panadería Delicia',
                ],
            ],
            [
                'code' => 'qrbo.quota.exceeded',
                'source_plugin' => 'Aero.Qrbo',
                'category' => 'billing',
                'name' => 'Límite de plan excedido',
                'description' => 'El tenant intentó una operación por encima de su plan.',
                'priority' => 3,
                'default_audiences' => ['tenant_admin'],
                'default_channels' => ['inapp', 'email'],
                'variables_schema' => [
                    'quota'     => ['type' => 'string', 'required' => true,  'label' => 'Límite alcanzado'],
                    'limit'     => ['type' => 'number', 'required' => false, 'label' => 'Valor del límite'],
                    'plan_name' => ['type' => 'string', 'required' => false, 'label' => 'Plan'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'quota' => 'cuentas bancarias', 'limit' => 2, 'plan_name' => 'Básico',
                    'tenant_name' => 'Panadería Delicia',
                ],
            ],
            [
                'code' => 'qrbo.billing.run_completed',
                'source_plugin' => 'Aero.Qrbo',
                'category' => 'system',
                'name' => 'Corrida de facturación terminada',
                'description' => 'Resumen de la generación mensual de cargos. Pensado como digest.',
                'priority' => 6,
                'default_audiences' => ['superadmin'],
                'default_channels' => ['email'],
                'variables_schema' => [
                    'charges_count' => ['type' => 'number', 'required' => true,  'label' => 'Cargos generados'],
                    'total_amount'  => ['type' => 'number', 'required' => false, 'label' => 'Monto total'],
                    'errors_count'  => ['type' => 'number', 'required' => false, 'label' => 'Errores'],
                ],
                'sample_context' => ['charges_count' => 47, 'total_amount' => 9870.00, 'errors_count' => 0],
            ],
        ];
    }

    protected static function crm(): array
    {
        // Mismos nombres de variable que el message_template actual de
        // CollectionReminderRule, para que la migración de la plantilla a Twig
        // sea textual: {{contacto}}, {{monto}}, {{moneda}}, {{concepto}}, {{vencimiento}}.
        $collectionVars = [
            'contacto'    => ['type' => 'string', 'required' => true,  'label' => 'Nombre del contacto'],
            'monto'       => ['type' => 'number', 'required' => true,  'label' => 'Monto del cobro'],
            'moneda'      => ['type' => 'string', 'required' => false, 'label' => 'Moneda'],
            'concepto'    => ['type' => 'string', 'required' => false, 'label' => 'Concepto'],
            'vencimiento' => ['type' => 'date',   'required' => true,  'label' => 'Fecha de vencimiento'],
        ];

        $collectionSample = [
            'contacto' => 'Juan Pérez', 'monto' => 450.00, 'moneda' => 'Bs',
            'concepto' => 'Plan mensual agosto', 'vencimiento' => '2026-09-05',
        ];

        return [
            [
                'code' => 'crm.collection.reminder',
                'source_plugin' => 'Aero.Crm',
                'category' => 'billing',
                'name' => 'Recordatorio de cobranza',
                'description' => 'Paso de la cascada de recordatorios previa al vencimiento.',
                'priority' => 4,
                'default_audiences' => ['actor'],
                'default_channels' => ['whatsapp'],
                'variables_schema' => $collectionVars,
                'sample_context' => $collectionSample,
            ],
            [
                'code' => 'crm.collection.overdue',
                'source_plugin' => 'Aero.Crm',
                'category' => 'billing',
                'name' => 'Cobro vencido',
                'description' => 'Un cobro pasó su fecha de vencimiento sin registrarse el pago.',
                'priority' => 3,
                'default_audiences' => ['actor', 'tenant_admin'],
                'default_channels' => ['whatsapp'],
                'variables_schema' => $collectionVars + [
                    'dias_atraso' => ['type' => 'number', 'required' => false, 'label' => 'Días de atraso'],
                ],
                'sample_context' => $collectionSample + ['dias_atraso' => 7],
            ],
            [
                'code' => 'crm.lead.created',
                'source_plugin' => 'Aero.Crm',
                'category' => 'sales',
                'name' => 'Lead creado',
                'description' => 'Entró un lead nuevo al pipeline.',
                'priority' => 5,
                'default_audiences' => ['tenant_admin'],
                'default_channels' => ['inapp'],
                'variables_schema' => [
                    'lead_name' => ['type' => 'string', 'required' => true,  'label' => 'Nombre del lead'],
                    'source'    => ['type' => 'string', 'required' => false, 'label' => 'Origen'],
                    'owner'     => ['type' => 'string', 'required' => false, 'label' => 'Responsable'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'lead_name' => 'Ferretería Los Andes', 'source' => 'Formulario web',
                    'owner' => 'Ana Gutiérrez', 'tenant_name' => 'Demo',
                ],
            ],
            [
                'code' => 'crm.deal.stage_changed',
                'source_plugin' => 'Aero.Crm',
                'category' => 'sales',
                'name' => 'Oportunidad cambió de etapa',
                'description' => 'Una oportunidad se movió en el kanban.',
                'priority' => 6,
                'default_audiences' => ['tenant_admin'],
                'default_channels' => ['inapp'],
                'variables_schema' => [
                    'deal_name'  => ['type' => 'string', 'required' => true,  'label' => 'Oportunidad'],
                    'from_stage' => ['type' => 'string', 'required' => false, 'label' => 'Etapa anterior'],
                    'to_stage'   => ['type' => 'string', 'required' => true,  'label' => 'Etapa nueva'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'deal_name' => 'Contrato anual Los Andes', 'from_stage' => 'Propuesta',
                    'to_stage' => 'Negociación', 'tenant_name' => 'Demo',
                ],
            ],
            [
                'code' => 'crm.deal.won',
                'source_plugin' => 'Aero.Crm',
                'category' => 'sales',
                'name' => 'Oportunidad ganada',
                'description' => 'Se cerró una oportunidad con éxito.',
                'priority' => 4,
                'default_audiences' => ['tenant_admin'],
                'default_channels' => ['inapp', 'email'],
                'variables_schema' => [
                    'deal_name' => ['type' => 'string', 'required' => true,  'label' => 'Oportunidad'],
                    'amount'    => ['type' => 'number', 'required' => false, 'label' => 'Monto'],
                    'owner'     => ['type' => 'string', 'required' => false, 'label' => 'Responsable'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'deal_name' => 'Contrato anual Los Andes', 'amount' => 12000.00,
                    'owner' => 'Ana Gutiérrez', 'tenant_name' => 'Demo',
                ],
            ],
            [
                'code' => 'crm.deal.lost',
                'source_plugin' => 'Aero.Crm',
                'category' => 'sales',
                'name' => 'Oportunidad perdida',
                'description' => 'Se cerró una oportunidad sin venta.',
                'priority' => 6,
                'default_audiences' => ['tenant_admin'],
                'default_channels' => ['inapp'],
                'variables_schema' => [
                    'deal_name' => ['type' => 'string', 'required' => true,  'label' => 'Oportunidad'],
                    'reason'    => ['type' => 'string', 'required' => false, 'label' => 'Motivo'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'deal_name' => 'Contrato anual Los Andes',
                    'reason' => 'Precio', 'tenant_name' => 'Demo',
                ],
            ],
            [
                'code' => 'crm.task.due',
                'source_plugin' => 'Aero.Crm',
                'category' => 'sales',
                'name' => 'Actividad por vencer',
                'description' => 'Una actividad del CRM vence hoy.',
                'priority' => 5,
                'default_audiences' => ['tenant_admin'],
                'default_channels' => ['inapp'],
                'variables_schema' => [
                    'subject'  => ['type' => 'string',   'required' => true,  'label' => 'Asunto'],
                    'due_at'   => ['type' => 'datetime', 'required' => true,  'label' => 'Vence'],
                    'contact'  => ['type' => 'string',   'required' => false, 'label' => 'Contacto'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'subject' => 'Llamar para confirmar entrega', 'due_at' => '2026-08-28 16:00:00',
                    'contact' => 'Juan Pérez', 'tenant_name' => 'Demo',
                ],
            ],
        ];
    }

    protected static function shop(): array
    {
        $orderVars = [
            'order_number'  => ['type' => 'string', 'required' => true,  'label' => 'Número de pedido'],
            'customer_name' => ['type' => 'string', 'required' => true,  'label' => 'Cliente'],
            'total'         => ['type' => 'number', 'required' => true,  'label' => 'Total'],
            'currency'      => ['type' => 'string', 'required' => false, 'label' => 'Moneda'],
            'items'         => ['type' => 'array',  'required' => false, 'label' => 'Líneas del pedido'],
        ];

        $orderSample = [
            'order_number' => 'P-01042', 'customer_name' => 'María Quispe',
            'total' => 187.50, 'currency' => 'Bs',
            'items' => [
                ['name' => 'Pan integral', 'qty' => 3, 'price' => 12.50],
                ['name' => 'Torta de chocolate', 'qty' => 1, 'price' => 150.00],
            ],
            'tenant_name' => 'Panadería Delicia',
        ];

        return [
            [
                'code' => 'shop.order.placed',
                'source_plugin' => 'Aero.Shop',
                'category' => 'orders',
                'name' => 'Pedido realizado',
                'description' => 'Un cliente completó el checkout.',
                'priority' => 3,
                'default_audiences' => ['tenant_admin', 'actor'],
                'default_channels' => ['email', 'inapp'],
                'variables_schema' => $orderVars + self::TENANT_VARS,
                'sample_context' => $orderSample,
            ],
            [
                'code' => 'shop.order.paid',
                'source_plugin' => 'Aero.Shop',
                'category' => 'orders',
                'name' => 'Pedido pagado',
                'description' => 'Se confirmó el pago de un pedido.',
                'priority' => 3,
                'default_audiences' => ['tenant_admin', 'actor'],
                'default_channels' => ['email', 'inapp'],
                'variables_schema' => $orderVars + [
                    'payment_method' => ['type' => 'string', 'required' => false, 'label' => 'Medio de pago'],
                ] + self::TENANT_VARS,
                'sample_context' => $orderSample + ['payment_method' => 'QR'],
            ],
            [
                'code' => 'shop.order.shipped',
                'source_plugin' => 'Aero.Shop',
                'category' => 'orders',
                'name' => 'Pedido enviado',
                'description' => 'El pedido salió a reparto.',
                'priority' => 4,
                'default_audiences' => ['actor'],
                'default_channels' => ['email', 'whatsapp'],
                'variables_schema' => $orderVars + [
                    'tracking_code' => ['type' => 'string', 'required' => false, 'label' => 'Código de seguimiento'],
                ] + self::TENANT_VARS,
                'sample_context' => $orderSample + ['tracking_code' => 'ENV-9912'],
            ],
            [
                'code' => 'shop.order.cancelled',
                'source_plugin' => 'Aero.Shop',
                'category' => 'orders',
                'name' => 'Pedido cancelado',
                'description' => 'Se anuló un pedido.',
                'priority' => 4,
                'default_audiences' => ['tenant_admin', 'actor'],
                'default_channels' => ['email'],
                'variables_schema' => $orderVars + [
                    'reason' => ['type' => 'string', 'required' => false, 'label' => 'Motivo'],
                ] + self::TENANT_VARS,
                'sample_context' => $orderSample + ['reason' => 'Sin stock'],
            ],
            [
                'code' => 'shop.stock.low',
                'source_plugin' => 'Aero.Shop',
                'category' => 'orders',
                'name' => 'Stock bajo',
                'description' => 'Un producto bajó del umbral de stock configurado.',
                'priority' => 5,
                'default_audiences' => ['tenant_admin'],
                'default_channels' => ['inapp'],
                'variables_schema' => [
                    'product_name' => ['type' => 'string', 'required' => true,  'label' => 'Producto'],
                    'stock'        => ['type' => 'number', 'required' => true,  'label' => 'Stock actual'],
                    'threshold'    => ['type' => 'number', 'required' => false, 'label' => 'Umbral'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'product_name' => 'Pan integral', 'stock' => 3, 'threshold' => 10,
                    'tenant_name' => 'Panadería Delicia',
                ],
            ],
            [
                'code' => 'shop.customer.registered',
                'source_plugin' => 'Aero.Shop',
                'category' => 'orders',
                'name' => 'Cliente registrado',
                'description' => 'Un cliente creó su cuenta en la tienda.',
                'priority' => 5,
                'default_audiences' => ['actor'],
                'default_channels' => ['email'],
                'variables_schema' => [
                    'customer_name' => ['type' => 'string', 'required' => true, 'label' => 'Cliente'],
                ] + self::TENANT_VARS,
                'sample_context' => ['customer_name' => 'María Quispe', 'tenant_name' => 'Panadería Delicia'],
            ],
            [
                'code' => 'shop.cart.abandoned',
                'source_plugin' => 'Aero.Shop',
                'category' => 'marketing',
                'name' => 'Carrito abandonado',
                'description' => 'Un carrito quedó sin completar. No es transaccional: respeta el opt-out global.',
                'priority' => 8,
                'is_transactional' => false,
                'default_audiences' => ['actor'],
                'default_channels' => ['email'],
                'variables_schema' => [
                    'customer_name' => ['type' => 'string', 'required' => true,  'label' => 'Cliente'],
                    'total'         => ['type' => 'number', 'required' => false, 'label' => 'Total del carrito'],
                    'cart_url'      => ['type' => 'string', 'required' => false, 'label' => 'Enlace al carrito'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'customer_name' => 'María Quispe', 'total' => 187.50,
                    'cart_url' => 'https://delicia.bo/carrito', 'tenant_name' => 'Panadería Delicia',
                ],
            ],
        ];
    }

    protected static function hello(): array
    {
        return [
            [
                'code' => 'hello.message.received',
                'source_plugin' => 'Aero.Hello',
                'category' => 'support',
                'name' => 'Mensaje entrante',
                'description' => 'Llegó un mensaje a una cuenta conectada en Hello.',
                'priority' => 4,
                'default_audiences' => ['tenant_admin'],
                'default_channels' => ['inapp'],
                'variables_schema' => [
                    'contact_name' => ['type' => 'string', 'required' => true,  'label' => 'Contacto'],
                    'platform'     => ['type' => 'string', 'required' => true,  'label' => 'Plataforma'],
                    'preview'      => ['type' => 'string', 'required' => false, 'label' => 'Vista previa'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'contact_name' => 'Juan Pérez', 'platform' => 'whatsapp',
                    'preview' => '¿Tienen pan integral hoy?', 'tenant_name' => 'Panadería Delicia',
                ],
            ],
            [
                'code' => 'hello.call.missed',
                'source_plugin' => 'Aero.Hello',
                'category' => 'support',
                'name' => 'Llamada perdida',
                'description' => 'Una llamada entrante de WhatsApp no fue atendida.',
                'priority' => 3,
                'default_audiences' => ['tenant_admin'],
                'default_channels' => ['inapp', 'email'],
                'variables_schema' => [
                    'from_number' => ['type' => 'string',   'required' => true,  'label' => 'Número'],
                    'contact_name'=> ['type' => 'string',   'required' => false, 'label' => 'Contacto'],
                    'started_at'  => ['type' => 'datetime', 'required' => false, 'label' => 'Hora'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'from_number' => '+59170000000', 'contact_name' => 'Juan Pérez',
                    'started_at' => '2026-08-28 09:15:00', 'tenant_name' => 'Panadería Delicia',
                ],
            ],
            [
                'code' => 'hello.account.disconnected',
                'source_plugin' => 'Aero.Hello',
                'category' => 'system',
                'name' => 'Cuenta desconectada',
                'description' => 'Una cuenta conectada perdió la sesión con el proveedor.',
                'priority' => 2,
                'default_audiences' => ['tenant_admin', 'superadmin'],
                'default_channels' => ['email', 'inapp'],
                'variables_schema' => [
                    'account_label' => ['type' => 'string', 'required' => true,  'label' => 'Cuenta'],
                    'platform'      => ['type' => 'string', 'required' => true,  'label' => 'Plataforma'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'account_label' => 'WhatsApp principal', 'platform' => 'whatsapp',
                    'tenant_name' => 'Panadería Delicia',
                ],
            ],
            [
                'code' => 'hello.campaign.finished',
                'source_plugin' => 'Aero.Hello',
                'category' => 'marketing',
                'name' => 'Campaña terminada',
                'description' => 'Una campaña de mensajería terminó de procesarse.',
                'priority' => 6,
                'default_audiences' => ['tenant_admin'],
                'default_channels' => ['inapp'],
                'variables_schema' => [
                    'campaign_name' => ['type' => 'string', 'required' => true,  'label' => 'Campaña'],
                    'sent_count'    => ['type' => 'number', 'required' => false, 'label' => 'Enviados'],
                    'failed_count'  => ['type' => 'number', 'required' => false, 'label' => 'Fallidos'],
                ] + self::TENANT_VARS,
                'sample_context' => [
                    'campaign_name' => 'Promo agosto', 'sent_count' => 320,
                    'failed_count' => 4, 'tenant_name' => 'Panadería Delicia',
                ],
            ],
        ];
    }

    protected static function system(): array
    {
        return [
            [
                'code' => 'system.user.welcome',
                'source_plugin' => 'Aero.Notify',
                'category' => 'system',
                'name' => 'Bienvenida',
                'description' => 'Un usuario nuevo se registró en la plataforma.',
                'priority' => 5,
                'is_system' => true,
                'default_audiences' => ['actor'],
                'default_channels' => ['email'],
                'variables_schema' => [
                    'user_name' => ['type' => 'string', 'required' => true,  'label' => 'Nombre'],
                    'login_url' => ['type' => 'string', 'required' => false, 'label' => 'Enlace de acceso'],
                ],
                'sample_context' => [
                    'user_name' => 'Carlos Rojas',
                    'login_url' => 'https://micro.clouds.com.bo/backend',
                ],
            ],
            [
                'code' => 'system.user.password_reset',
                'source_plugin' => 'Aero.Notify',
                'category' => 'security',
                'name' => 'Restablecer contraseña',
                'description' => 'Se solicitó el restablecimiento de una contraseña.',
                'priority' => 1,
                'is_system' => true,
                'default_audiences' => ['actor'],
                'default_channels' => ['email'],
                'variables_schema' => [
                    'user_name' => ['type' => 'string', 'required' => true, 'label' => 'Nombre'],
                    'reset_url' => ['type' => 'string', 'required' => true, 'label' => 'Enlace de restablecimiento'],
                ],
                'sample_context' => [
                    'user_name' => 'Carlos Rojas',
                    'reset_url' => 'https://micro.clouds.com.bo/backend/reset/abc123',
                ],
            ],
            [
                'code' => 'system.security.login_new_device',
                'source_plugin' => 'Aero.Notify',
                'category' => 'security',
                'name' => 'Acceso desde un dispositivo nuevo',
                'description' => 'Se detectó un inicio de sesión desde un dispositivo no visto antes.',
                'priority' => 2,
                'is_system' => true,
                'default_audiences' => ['actor'],
                'default_channels' => ['email'],
                'variables_schema' => [
                    'user_name'  => ['type' => 'string',   'required' => true,  'label' => 'Nombre'],
                    'ip'         => ['type' => 'string',   'required' => false, 'label' => 'IP'],
                    'user_agent' => ['type' => 'string',   'required' => false, 'label' => 'Navegador'],
                    'at'         => ['type' => 'datetime', 'required' => false, 'label' => 'Fecha y hora'],
                ],
                'sample_context' => [
                    'user_name' => 'Carlos Rojas', 'ip' => '181.115.0.1',
                    'user_agent' => 'Chrome / Windows', 'at' => '2026-08-28 08:03:00',
                ],
            ],
            [
                'code' => 'notify.delivery.failed_burst',
                'source_plugin' => 'Aero.Notify',
                'category' => 'system',
                'name' => 'Ráfaga de entregas fallidas',
                'description' => 'La tasa de fallo de un canal superó el umbral. Meta-alerta del propio gateway.',
                'priority' => 1,
                'is_system' => true,
                'default_audiences' => ['superadmin'],
                'default_channels' => ['email'],
                'variables_schema' => [
                    'channel'      => ['type' => 'string', 'required' => true,  'label' => 'Canal'],
                    'failed_count' => ['type' => 'number', 'required' => true,  'label' => 'Fallos'],
                    'window'       => ['type' => 'string', 'required' => false, 'label' => 'Ventana observada'],
                ],
                'sample_context' => ['channel' => 'whatsapp', 'failed_count' => 34, 'window' => 'última hora'],
            ],
        ];
    }
}
