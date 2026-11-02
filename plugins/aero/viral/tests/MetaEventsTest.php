<?php namespace Aero\Viral\Tests;

use Aero\Viral\Classes\MetaEvents;
use Aero\Viral\Models\Application;
use PluginTestCase;
use RainLab\User\Models\User;

class MetaEventsTest extends PluginTestCase
{
    public function testLeadIdIsStableAndSubmitWithoutCapiDoesNotBreak(): void
    {
        config(['services.meta_pixel.access_token' => null]);
        $u = new User;
        $u->first_name = 'Ana'; $u->email = 'ana@example.com'; $u->username = 'ana@example.com';
        $u->password = $u->password_confirmation = 'claveSegura123'; $u->save();
        $app = Application::create(['user_id' => $u->id, 'track' => 'shorts', 'level' => 1, 'rate_model' => 'per_second', 'rate_amount' => 3, 'phone' => '+59171234567']);

        $this->assertSame('lead_viral_' . $app->uuid, MetaEvents::leadId($app));
        MetaEvents::lead($app);
        $this->assertTrue(true);
    }
}
