<?php namespace Aero\Church\Tests;

use Aero\Church\Classes\Audit;
use Aero\Church\Models\AuditLog;
use Aero\Church\Models\Contribution;
use Aero\Church\Models\Fund;
use Aero\Church\Models\Member;
use Aero\Church\Models\Prayer;
use PluginTestCase;

class AuditTest extends PluginTestCase
{
    public function testCreateUpdateDeleteAreLoggedWithFieldNamesOnly(): void
    {
        $m = Member::create(['tenant_id' => 1, 'first_name' => 'Ana', 'phone' => '70012345', 'status' => 'active']);
        $m->update(['phone' => '79999999', 'address' => 'Calle Secreta 123']);
        $m->delete();

        $rows = AuditLog::where('subject_type', 'member')->where('subject_id', $m->id)->orderBy('id')->get();
        $this->assertSame(['created', 'updated', 'deleted'], $rows->pluck('action')->all());
        $this->assertEqualsCanonicalizing(['phone', 'address'], $rows[1]->changes);

        // Nunca se guardan valores: ni teléfonos ni direcciones.
        $dump = json_encode($rows->toArray());
        $this->assertStringNotContainsString('70012345', $dump);
        $this->assertStringNotContainsString('79999999', $dump);
        $this->assertStringNotContainsString('Calle Secreta', $dump);
        $this->assertSame(1, $rows[0]->tenant_id);
    }

    public function testContributionConfirmCancelAreSingleExplicitEntriesWithoutAmounts(): void
    {
        $f = Fund::create(['tenant_id' => 1, 'name' => 'Diezmo', 'kind' => 'tithe']);
        $c = Contribution::create(['tenant_id' => 1, 'fund_id' => $f->id, 'amount' => 12345.67, 'currency' => 'BOB', 'date' => now()->toDateString(), 'method' => 'qr']);
        $c->markConfirmed();
        $c->markCancelled();

        $actions = AuditLog::where('subject_type', 'contribution')->orderBy('id')->pluck('action')->all();
        $this->assertSame(['created', 'confirmed', 'cancelled'], $actions, 'confirmar y anular no se duplican como «cambio»');
        $this->assertStringNotContainsString('12345', json_encode(AuditLog::all()->toArray()), 'Los montos no se anotan');
    }

    public function testAuditLogIsAppendOnly(): void
    {
        Audit::record('viewed', 'prayer', 5, 'Petición #5 consultada', 1);
        $row = AuditLog::first();

        try {
            $row->summary = 'manipulado';
            $row->save();
            $this->fail('No debe poder modificarse');
        } catch (\ApplicationException $e) {
            $this->assertStringContainsString('no se modifica', $e->getMessage());
        }

        $this->expectException(\ApplicationException::class);
        $row->delete();
    }

    public function testAuditFailureNeverBreaksTheOperation(): void
    {
        \Schema::drop('aero_church_audit_logs');
        $p = Prayer::create(['tenant_id' => 1, 'request' => 'x']);   // el registro falla en silencio
        $this->assertNotNull($p->id);
    }

    public function testNoiseOnlyChangesAreNotLogged(): void
    {
        $m = Member::create(['tenant_id' => 1, 'first_name' => 'Ana', 'status' => 'active']);
        $before = AuditLog::count();
        $m->touch();
        $this->assertSame($before, AuditLog::count());
    }
}
