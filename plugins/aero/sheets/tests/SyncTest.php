<?php namespace Aero\Sheets\Tests;

use Aero\Sheets\Classes\ColumnResolver;
use Aero\Sheets\Classes\SheetsClient;
use Aero\Sheets\Classes\SyncService;
use Aero\Sheets\Models\Mapping;
use Aero\Sheets\Models\Source;
use Illuminate\Support\Facades\Schema;
use PluginTestCase;

class FakeClient extends SheetsClient
{
    public array $written = [];
    public array $cleared = [];

    public function __construct(public array $sheet = [])
    {
    }

    public function get(string $id, string $range): array
    {
        // «'tab'!1:1» → fila de cabeceras; «'tab'!A2:ZZ…» → desde la fila 2.
        if (preg_match('/!(\d+):\d+$/', $range, $m)) {
            return [$this->sheet[$m[1] - 1] ?? []];
        }
        preg_match('/!A(\d+):ZZ(\d+)$/', $range, $m);

        return array_slice($this->sheet, $m[1] - 1, $m[2] - $m[1] + 1);
    }

    public function batchUpdate(string $id, array $data): void
    {
        $this->written = $data;
    }

    public function batchClear(string $id, array $ranges): void
    {
        $this->cleared = $ranges;
    }
}

class SyncTest extends PluginTestCase
{
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
    }

    protected function source(): Source
    {
        return Source::create([
            'model_class' => \Aero\Sheets\Models\TestItem::class,
            'label' => 'Items', 'tenant_access' => true,
            'fields' => [
                ['key' => 'email', 'label' => 'Email', 'type' => 'string', 'import' => true, 'export' => true],
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'string', 'import' => true, 'export' => true],
                ['key' => 'price', 'label' => 'Precio', 'type' => 'decimal', 'import' => true, 'export' => true],
                ['key' => 'active', 'label' => 'Activo', 'type' => 'bool', 'import' => true, 'export' => true],
                ['key' => 'tenant_id', 'label' => 'Tenant', 'type' => 'int', 'import' => true, 'export' => true],
            ],
        ]);
    }

    protected function mapping(Source $s, array $extra = []): Mapping
    {
        return Mapping::create($extra + [
            'name' => 'm', 'source_id' => $s->id, 'spreadsheet_id' => 'abcdefghijklmnopqrstuv', 'sheet_title' => 'Hoja 1',
            'direction' => 'import', 'header_row' => 2, 'start_row' => 3, 'key_field' => 'email',
            'columns' => [
                ['field' => 'email', 'column' => 'Correo'],
                ['field' => 'name', 'column' => 'B'],
                ['field' => 'price', 'column' => 'Precio'],
                ['field' => 'active', 'column' => 'D'],
                ['field' => 'tenant_id', 'column' => 'E'], // intento de colarse en otro tenant
            ],
        ]);
    }

    protected function sheet(): array
    {
        return [
            ['Título que se salta'],
            ['Correo', 'Nombre', 'Precio', 'Activo', 'T'],
            ['a@x.com', 'Ana', '1.234,50', 'Sí', '99'],
            ['', '', '', '', ''],
            ['b@x.com', 'Beto', 'abc', 'no', '99'],
            ['c@x.com', 'Cris', '10', 'x', '99'],
        ];
    }

    public function testImportSkipsHeadersEmptyRowsAndReportsBadRows(): void
    {
        $m = $this->mapping($this->source(), ['tenant_id' => 7]);
        $run = (new SyncService(new FakeClient($this->sheet())))->run($m, null);

        $this->assertSame(3, $run->rows_read);
        $this->assertSame(2, $run->created);
        $this->assertSame(1, $run->failed);
        $this->assertSame('partial', $run->status);
        $this->assertStringContainsString('Fila 5', $run->errors[0]);

        $ana = \Aero\Sheets\Models\TestItem::where('email', 'a@x.com')->first();
        $this->assertEquals(1234.5, $ana->price);
        $this->assertTrue((bool) $ana->active);
        // tenant_id de la hoja se ignora: manda el del mapeo.
        $this->assertSame(7, (int) $ana->tenant_id);
    }

    public function testDryRunSavesNothingAndUpsertUpdates(): void
    {
        $m = $this->mapping($this->source(), ['tenant_id' => 7]);
        $svc = new SyncService(new FakeClient($this->sheet()));

        $dry = $svc->run($m, null, true);
        $this->assertSame(2, $dry->created);
        $this->assertSame(0, \Aero\Sheets\Models\TestItem::count());

        $svc->run($m, null);
        $again = $svc->run($m, null);
        $this->assertSame(2, $again->updated);
        $this->assertSame(2, \Aero\Sheets\Models\TestItem::count());
    }

    public function testTenantMappingNeverTouchesOtherTenantRecords(): void
    {
        \Aero\Sheets\Models\TestItem::create(['email' => 'a@x.com', 'name' => 'Ajena', 'tenant_id' => 8]);
        $m = $this->mapping($this->source(), ['tenant_id' => 7]);

        (new SyncService(new FakeClient($this->sheet())))->run($m, null);

        $this->assertSame('Ajena', \Aero\Sheets\Models\TestItem::where('tenant_id', 8)->first()->name);
        $this->assertSame(1, \Aero\Sheets\Models\TestItem::where('tenant_id', 7)->where('email', 'a@x.com')->count());
    }

    public function testExportOnlyWritesMappedColumnsAndOwnTenant(): void
    {
        \Aero\Sheets\Models\TestItem::create(['email' => 'mia@x.com', 'name' => 'Mía', 'tenant_id' => 7]);
        \Aero\Sheets\Models\TestItem::create(['email' => 'otra@x.com', 'name' => 'Otra', 'tenant_id' => 8]);

        $m = $this->mapping($this->source(), [
            'tenant_id' => 7, 'direction' => 'export', 'write_headers' => true, 'clear_before_export' => true,
            'columns' => [['field' => 'email', 'column' => 'Correo'], ['field' => 'name', 'column' => 'Apodo']],
        ]);

        $client = new FakeClient([[], ['Correo', 'Otra cosa']]);
        $run = (new SyncService($client))->run($m, null);

        $this->assertSame('ok', $run->status);
        $this->assertSame(1, $run->rows_read);
        // Solo A (Correo existente) y C (Apodo nuevo): la columna B no se toca.
        $this->assertSame(["'Hoja 1'!A3:A"], array_slice($client->cleared, 0, 1));
        $this->assertArrayHasKey("'Hoja 1'!A3:A3", $client->written);
        $this->assertArrayHasKey("'Hoja 1'!C3:C3", $client->written);
        $this->assertSame([['mia@x.com']], $client->written["'Hoja 1'!A3:A3"]);
        $this->assertArrayNotHasKey("'Hoja 1'!B3:B3", $client->written);
        $this->assertSame([['Nombre']], $client->written["'Hoja 1'!C2"]);
    }

    public function testTenantCannotUseSourceNotOpenedToTenants(): void
    {
        $s = $this->source();
        $s->update(['tenant_access' => false]);
        $run = (new SyncService(new FakeClient($this->sheet())))->run($this->mapping($s, ['tenant_id' => 7]), null);

        $this->assertSame('failed', $run->status);
        $this->assertSame(0, \Aero\Sheets\Models\TestItem::count());
    }

    public function testHelpers(): void
    {
        $this->assertSame('1AbC_dEf-1234567890xyz', SheetsClient::spreadsheetId('https://docs.google.com/spreadsheets/d/1AbC_dEf-1234567890xyz/edit#gid=0'));
        $this->assertNull(SheetsClient::spreadsheetId('https://evil.example/x'));
        $this->assertSame('AA', SheetsClient::colLetter(26));
        $this->assertSame(27, SheetsClient::colIndex('AB'));
        $this->assertSame(1, ColumnResolver::index('nombre', ['Correo', 'Nombre']));
        $this->assertSame(2, ColumnResolver::index('C', ['Correo', 'Nombre']));
        $this->assertNull(ColumnResolver::index('Inexistente', ['Correo']));
        $this->assertSame("'It''s'!A1", SheetsClient::a1("It's", 'A1'));
    }

    public function testDeniedFieldsAreNeverOffered(): void
    {
        $this->source();
        $f = \Aero\Sheets\Classes\SourceRegistry::candidateFields(\Aero\Sheets\Models\TestItem::class);
        $this->assertArrayHasKey('email', $f);
        $this->assertTrue($f['tenant_id']['readonly']);
        $this->assertSame(1, preg_match(\Aero\Sheets\Classes\SourceRegistry::DENY, 'api_key'));
    }
}
