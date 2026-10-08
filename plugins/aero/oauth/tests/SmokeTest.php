<?php namespace Aero\Oauth\Tests;

use Aero\Oauth\Classes\Oauth;
use Aero\Oauth\Models\Identity;
use PluginTestCase;

class SmokeTest extends PluginTestCase
{
    public function testTokensAreEncryptedAtRestAndRefreshTokenIsNotWipedByNull(): void
    {
        $i = Identity::create(['backend_user_id' => 1, 'provider' => 'google', 'provider_user_id' => 'sub1']);
        $i->access_token = 'AT';
        $i->refresh_token = 'RT';
        $i->save();

        $raw = \DB::table('aero_oauth_identities')->where('id', $i->id)->first();
        $this->assertStringNotContainsString('RT', $raw->refresh_token);
        $this->assertStringNotContainsString('AT', $raw->access_token);

        $i = Identity::find($i->id);
        $i->refresh_token = null; // Google no lo reenvía en logins posteriores
        $i->save();
        $this->assertSame('RT', Identity::find($i->id)->refresh_token);
    }

    public function testIdentityIsUniquePerProviderAccount(): void
    {
        Identity::create(['backend_user_id' => 1, 'provider' => 'google', 'provider_user_id' => 'sub1']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        Identity::create(['backend_user_id' => 2, 'provider' => 'google', 'provider_user_id' => 'sub1']);
    }

    public function testSafeReturnRejectsOpenRedirects(): void
    {
        $this->assertSame('/backend/x', Oauth::safeReturn('/backend/x', '/'));
        $this->assertSame('/', Oauth::safeReturn('//evil.com', '/'));
        $this->assertSame('/', Oauth::safeReturn('https://evil.com', '/'));
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Oauth\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());
        foreach ($plugin->registerNavigation() as $item) {
            foreach ($item['permissions'] as $p) {
                $this->assertContains($p, $declared);
            }
        }
    }
}
