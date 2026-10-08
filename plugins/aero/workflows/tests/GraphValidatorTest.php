<?php namespace Aero\Workflows\Tests;

use Aero\Workflows\Classes\GraphValidator;
use Aero\Workflows\Classes\NodeRegistry;
use Event;
use PluginTestCase;

/**
 * Validador de grafos: reglas estructurales, salidas de cada nodo y la
 * restricción de nodos con efectos en borradores.
 */
class GraphValidatorTest extends PluginTestCase
{
    protected function node(string $id, string $type, array $data = []): array
    {
        return ['id' => $id, 'type' => $type, 'position' => ['x' => 0, 'y' => 0], 'data' => $data];
    }

    protected function edge(string $source, string $target, ?string $handle = null): array
    {
        $edge = ['id' => "{$source}-{$target}", 'source' => $source, 'target' => $target];

        if ($handle !== null) {
            $edge['sourceHandle'] = $handle;
        }

        return $edge;
    }

    public function testFlujoSimpleEsValido(): void
    {
        $graph = [
            'nodes' => [
                $this->node('n1', 'trigger.manual'),
                $this->node('n2', 'logic.set', ['name' => 'saludo', 'value' => 'Hola']),
                $this->node('n3', 'action.respond', ['value' => '{{ vars.saludo }}']),
            ],
            'edges' => [$this->edge('n1', 'n2'), $this->edge('n2', 'n3')],
        ];

        $this->assertSame([], GraphValidator::validate($graph));
    }

    public function testSinNodosOConDemasiadosNodos(): void
    {
        $this->assertNotEmpty(GraphValidator::validate([]));

        $many = array_map(fn ($i) => $this->node("n{$i}", 'logic.set'), range(1, GraphValidator::MAX_NODES + 1));
        $this->assertStringContainsString('más de', GraphValidator::validate(['nodes' => $many])[0]);
    }

    public function testTipoDeNodoDesconocido(): void
    {
        $errors = GraphValidator::validate(['nodes' => [
            $this->node('n1', 'trigger.manual'),
            $this->node('n2', 'inventado.nodo'),
        ], 'edges' => [$this->edge('n1', 'n2')]]);

        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'tipo desconocido')));
    }

    public function testDebeHaberExactamenteUnDisparador(): void
    {
        $none = GraphValidator::validate(['nodes' => [$this->node('n1', 'logic.set')]]);
        $two = GraphValidator::validate(['nodes' => [
            $this->node('n1', 'trigger.manual'),
            $this->node('n2', 'trigger.event'),
        ]]);

        $this->assertStringContainsString('exactamente un disparador', implode(' ', $none));
        $this->assertStringContainsString('exactamente un disparador', implode(' ', $two));
    }

    public function testConexionAUnNodoInexistente(): void
    {
        $errors = GraphValidator::validate(['nodes' => [$this->node('n1', 'trigger.manual')], 'edges' => [$this->edge('n1', 'fantasma')]]);

        $this->assertStringContainsString('no existe', implode(' ', $errors));
    }

    public function testCondicionExigeSalidaSiONo(): void
    {
        $base = [
            $this->node('n1', 'trigger.manual'),
            $this->node('n2', 'logic.condition', ['left' => '{{ trigger.monto }}', 'op' => 'gt', 'right' => '100']),
            $this->node('n3', 'action.respond', ['value' => 'grande']),
            $this->node('n4', 'action.respond', ['value' => 'chico']),
        ];

        $sinSalida = GraphValidator::validate(['nodes' => $base, 'edges' => [
            $this->edge('n1', 'n2'), $this->edge('n2', 'n3'), $this->edge('n2', 'n4'),
        ]]);
        $this->assertNotEmpty($sinSalida);

        $conSalidas = GraphValidator::validate(['nodes' => $base, 'edges' => [
            $this->edge('n1', 'n2'), $this->edge('n2', 'n3', 'true'), $this->edge('n2', 'n4', 'false'),
        ]]);
        $this->assertSame([], $conSalidas);
    }

    public function testSalidaInventadaEnNodoSinSalidas(): void
    {
        $errors = GraphValidator::validate(['nodes' => [
            $this->node('n1', 'trigger.manual'),
            $this->node('n2', 'logic.set', ['name' => 'x', 'value' => 'y']),
        ], 'edges' => [$this->edge('n1', 'n2', 'true')]]);

        $this->assertStringContainsString('no tiene la salida', implode(' ', $errors));
    }

    public function testCicloNoEsValido(): void
    {
        $errors = GraphValidator::validate(['nodes' => [
            $this->node('n1', 'trigger.manual'),
            $this->node('n2', 'logic.set', ['name' => 'a', 'value' => '1']),
            $this->node('n3', 'logic.set', ['name' => 'b', 'value' => '2']),
        ], 'edges' => [
            $this->edge('n1', 'n2'), $this->edge('n2', 'n3'), $this->edge('n3', 'n2'),
        ]]);

        $this->assertStringContainsString('ciclo', implode(' ', $errors));
    }

    public function testNodoDesconectadoNoEsValido(): void
    {
        $errors = GraphValidator::validate(['nodes' => [
            $this->node('n1', 'trigger.manual'),
            $this->node('n2', 'logic.set', ['name' => 'a', 'value' => '1']),
            $this->node('n3', 'logic.set', ['name' => 'b', 'value' => '2']),
        ], 'edges' => [$this->edge('n1', 'n2')]]);

        $this->assertStringContainsString('no está conectado', implode(' ', $errors));
    }

    public function testPlantillaSinCerrar(): void
    {
        $errors = GraphValidator::validate(['nodes' => [
            $this->node('n1', 'trigger.manual'),
            $this->node('n2', 'action.respond', ['value' => 'Hola {{ trigger.nombre']),
        ], 'edges' => [$this->edge('n1', 'n2')]]);

        $this->assertStringContainsString('sin cerrar', implode(' ', $errors));
    }

    public function testBorradorBloqueaNodosConEfectos(): void
    {
        $graph = [
            'nodes' => [
                $this->node('n1', 'trigger.manual'),
                $this->node('n2', 'action.message', ['to' => '{{ trigger.phone }}', 'body' => 'hola']),
                $this->node('n3', 'action.respond', ['value' => 'ok']),
            ],
            'edges' => [$this->edge('n1', 'n2'), $this->edge('n2', 'n3')],
        ];

        $this->assertSame([], GraphValidator::validate($graph), 'publicado puede usar checkout');
        $this->assertStringContainsString('efectos', implode(' ', GraphValidator::validate($graph, true)));
    }

    /** Los ejemplos de references/patterns.md deben validar como borrador: el skill los copia tal cual. */
    /** Aero.Shop no carga en el entorno de test: se registra un stub con las mismas salidas. */
    protected function registerShopProductsStub(): void
    {
        Event::listen('aero.workflows.registerNodes', fn () => [
            'shop.products' => [
                'label' => 'Tienda › Productos de una categoría', 'category' => 'action',
                'handler' => fn () => [],
                'handles' => [
                    ['id' => 'found', 'label' => 'con productos'],
                    ['id' => 'empty', 'label' => 'categoría vacía'],
                    ['id' => 'not_found', 'label' => 'no entendí'],
                ],
                'fields' => [],
            ],
        ]);
        NodeRegistry::flush();
    }

    public function testPatronConsultaDeCatalogoEsValidoComoBorrador(): void
    {
        $this->registerShopProductsStub();

        $graph = [
            'nodes' => [
                $this->node('n1', 'trigger.manual'),
                $this->node('n2', 'shop.products', ['category_id' => '16', 'query' => '{{ trigger.query }}', 'limit' => '10', 'only_in_stock' => '0', 'show_description' => '1', 'save_as' => 'productos']),
                $this->node('n3', 'action.respond', ['value' => '{{ vars.productos.text }}']),
                $this->node('n4', 'action.respond', ['value' => 'No hay productos disponibles para esa consulta.']),
            ],
            'edges' => [
                $this->edge('n1', 'n2'),
                $this->edge('n2', 'n3', 'found'), $this->edge('n2', 'n4', 'empty'), $this->edge('n2', 'n4', 'not_found'),
            ],
        ];

        $this->assertSame([], GraphValidator::validate($graph, true));
    }

    /** Cobrar y consultar: cada salida de los nodos de Aero.Pay debe estar conectada, y los que cobran/anulan tienen efectos. */
    public function testNodosDeCobroValidanYSoloLosQueCobranTienenEfectos(): void
    {
        if (!class_exists(\Aero\Pay\Classes\Workflows\PaymentNodes::class)) {
            $this->markTestSkipped('Aero.Pay no está instalado.');
        }

        Event::listen('aero.workflows.registerNodes', fn () => \Aero\Pay\Classes\Workflows\PaymentNodes::definitions());
        NodeRegistry::flush();

        $graph = [
            'nodes' => [
                $this->node('n1', 'trigger.manual'),
                $this->node('n2', 'pay.charge', ['amount' => '{{ trigger.monto }}', 'description' => 'Pedido']),
                $this->node('n3', 'pay.status', ['reference' => '{{ vars.cobro.reference }}']),
                $this->node('n4', 'action.respond', ['value' => 'listo']),
            ],
            'edges' => [
                $this->edge('n1', 'n2'),
                $this->edge('n2', 'n3', 'created'), $this->edge('n2', 'n4', 'failed'),
                $this->edge('n3', 'n4', 'paid'), $this->edge('n3', 'n4', 'pending'), $this->edge('n3', 'n4', 'expired'),
                $this->edge('n3', 'n4', 'cancelled'), $this->edge('n3', 'n4', 'not_found'),
            ],
        ];

        $this->assertSame([], GraphValidator::validate($graph), 'publicado puede cobrar');
        $this->assertStringContainsString('pay.charge', implode(' ', GraphValidator::validate($graph, true)));

        $readOnly = ['nodes' => [$this->node('n1', 'trigger.manual'), $this->node('n2', 'pay.summary', ['period' => 'today']), $this->node('n3', 'action.respond', ['value' => '{{ vars.resumen_pagos.text }}'])],
            'edges' => [$this->edge('n1', 'n2'), $this->edge('n2', 'n3', 'found'), $this->edge('n2', 'n3', 'empty')]];
        $this->assertSame([], GraphValidator::validate($readOnly, true), 'consultar no tiene efectos');
    }

    /** Nodos de pagos a la plataforma: la recarga cobra (efectos); saldo y estado del plan solo leen. */
    public function testNodosDeMonedasYPlanValidanYSoloLaRecargaTieneEfectos(): void
    {
        foreach ([\Aero\Credits\Classes\Workflows\CreditNodes::class, \Aero\Sites\Classes\Workflows\PlanNodes::class] as $nodes) {
            if (!class_exists($nodes)) {
                $this->markTestSkipped("{$nodes} no está instalado.");
            }

            Event::listen('aero.workflows.registerNodes', fn () => $nodes::definitions());
        }

        NodeRegistry::flush();

        $read = [
            'nodes' => [
                $this->node('n1', 'trigger.manual'),
                $this->node('n2', 'credits.balance'),
                $this->node('n3', 'sites.plan_status'),
                $this->node('n4', 'action.respond', ['value' => '{{ vars.saldo.text }} {{ vars.plan.text }}']),
            ],
            'edges' => [
                $this->edge('n1', 'n2'), $this->edge('n2', 'n3'),
                $this->edge('n3', 'n4', 'active'), $this->edge('n3', 'n4', 'expiring'), $this->edge('n3', 'n4', 'overdue'), $this->edge('n3', 'n4', 'no_plan'),
            ],
        ];
        $this->assertSame([], GraphValidator::validate($read, true), 'consultar no tiene efectos');

        $recharge = [
            'nodes' => [
                $this->node('n1', 'trigger.manual'),
                $this->node('n2', 'credits.recharge', ['amount' => '100']),
                $this->node('n3', 'credits.purchase_status', ['reference' => '{{ vars.recarga.reference }}']),
                $this->node('n4', 'action.respond', ['value' => 'ok']),
            ],
            'edges' => [
                $this->edge('n1', 'n2'), $this->edge('n2', 'n3', 'created'), $this->edge('n2', 'n4', 'failed'),
                $this->edge('n3', 'n4', 'paid'), $this->edge('n3', 'n4', 'pending'), $this->edge('n3', 'n4', 'expired'),
                $this->edge('n3', 'n4', 'review'), $this->edge('n3', 'n4', 'not_found'),
            ],
        ];
        $this->assertSame([], GraphValidator::validate($recharge), 'publicado puede recargar');
        $this->assertStringContainsString('credits.recharge', implode(' ', GraphValidator::validate($recharge, true)));
    }

    public function testPatronDecisionConCondicionEsValidoComoBorrador(): void
    {
        $graph = [
            'nodes' => [
                $this->node('n1', 'trigger.manual'),
                $this->node('n2', 'logic.condition', ['left' => '{{ trigger.monto }}', 'op' => 'gt', 'right' => '100']),
                $this->node('n3', 'logic.set', ['name' => 'nivel', 'value' => 'grande']),
                $this->node('n4', 'logic.set', ['name' => 'nivel', 'value' => 'regular']),
                $this->node('n5', 'action.respond', ['value' => 'Nivel: {{ vars.nivel }}']),
            ],
            'edges' => [
                $this->edge('n1', 'n2'),
                $this->edge('n2', 'n3', 'true'), $this->edge('n2', 'n4', 'false'),
                $this->edge('n3', 'n5'), $this->edge('n4', 'n5'),
            ],
        ];

        $this->assertSame([], GraphValidator::validate($graph, true));
    }

    public function testBorradorPermiteLecturasDeTienda(): void
    {
        $graph = [
            'nodes' => [
                $this->node('n1', 'trigger.manual'),
                $this->node('n2', 'shop.products', ['category_id' => '16', 'limit' => '10']),
                $this->node('n3', 'action.respond', ['value' => '{{ vars.productos.text }}']),
            ],
            'edges' => [$this->edge('n1', 'n2', 'found'), $this->edge('n2', 'n3', 'found')],
        ];

        $errors = GraphValidator::validate($graph, true);
        $this->assertStringNotContainsString('efectos', implode(' ', $errors));
    }
}
