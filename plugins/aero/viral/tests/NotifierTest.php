<?php namespace Aero\Viral\Tests;

use Aero\Viral\Classes\Notifier;
use Aero\Viral\Models\Application;
use PluginTestCase;
use RainLab\User\Models\User;

class NotifierTest extends PluginTestCase
{
    protected array $sent = [];

    public function setUp(): void
    {
        parent::setUp();
        Notifier::$fire = function ($code, $ctx, $opts) { $this->sent[] = [$code, $ctx, $opts]; };
    }

    public function tearDown(): void
    {
        Notifier::$fire = null;
        parent::tearDown();
    }

    protected function app(array $over = []): Application
    {
        $u = new User;
        $u->first_name = 'Ana'; $u->email = 'ana@example.com'; $u->username = 'ana@example.com';
        $u->password = $u->password_confirmation = 'claveSegura123'; $u->save();

        return Application::create($over + ['user_id' => $u->id, 'track' => 'shorts', 'level' => 1, 'rate_model' => 'per_second', 'rate_amount' => 3, 'phone' => '+59171234567']);
    }

    public function testApprovalAndRejectionReachTheCreatorWithPhoneAndEmail(): void
    {
        $app = $this->app();
        $app->forceFill(['kyc_status' => 'verified'])->save();
        $app->approve(null, 'nota interna');
        $app->reject(null, 'Motivo interno');
        $this->assertSame(['viral.application.approved', 'viral.application.rejected'], array_column($this->sent, 0));
        $actor = $this->sent[0][2]['actor'];
        $this->assertSame(['name' => 'Ana', 'email' => 'ana@example.com', 'phone' => '+59171234567'], $actor);
        $this->assertSame('utility', $this->sent[0][1]['category']);
        $this->assertSame('', $this->sent[1][1]['notes'], 'Las notas de un rechazo son internas y no se envían');
    }

    public function testChangesRequestedCarriesTheTeamMessage(): void
    {
        $app = $this->app();
        $app->requestChanges(null, 'Sube un video con mejor luz.');
        $this->assertSame('viral.application.changes_requested', $this->sent[0][0]);
        $this->assertSame('Sube un video con mejor luz.', $this->sent[0][1]['notes']);
    }

    public function testSubmittedNotifiesCreatorAndTeam(): void
    {
        Notifier::submitted($this->app());
        $this->assertSame(['viral.application.received', 'viral.application.new'], array_column($this->sent, 0));
        $this->assertArrayHasKey('actor', $this->sent[0][2]);
        $this->assertSame([], $this->sent[1][2]);   // el equipo se resuelve por audiencia (superadmin)
    }

    public function testNotifierFailureNeverBreaksTheFlow(): void
    {
        Notifier::$fire = function () { throw new \RuntimeException('Notify caído'); };
        $app = $this->app();
        $app->requestChanges(null, 'x');
        $this->assertSame('changes_requested', $app->fresh()->status);
    }
}
