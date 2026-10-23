<?php namespace Aero\Church\Tests;

use Aero\Church\Classes\ChurchUsers;
use Aero\Church\Classes\Embed;
use Aero\Church\Classes\PortalAccess;
use Aero\Church\Classes\Slugs;
use Aero\Church\Models\Member;
use Aero\Church\Models\Post;
use Aero\Church\Models\Resource;
use Aero\Church\Models\Sermon;
use PluginTestCase;

class ContentTest extends PluginTestCase
{
    public function testEmbedOnlyForTrustedProviders(): void
    {
        $yt = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ';
        $this->assertSame($yt, Embed::src('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertSame($yt, Embed::src('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertSame($yt, Embed::src('https://www.youtube.com/live/dQw4w9WgXcQ?feature=share'));
        $this->assertSame('https://player.vimeo.com/video/123456789', Embed::src('https://vimeo.com/123456789'));
        $this->assertStringStartsWith('https://www.facebook.com/plugins/video.php', (string) Embed::src('https://www.facebook.com/iglesia/videos/123'));

        $this->assertNull(Embed::src('http://www.youtube.com/watch?v=dQw4w9WgXcQ'), 'http no');
        $this->assertNull(Embed::src('https://evil.example/watch?v=dQw4w9WgXcQ'), 'proveedor desconocido no se incrusta');
        $this->assertNull(Embed::src('https://evil.example/?u=https://youtube.com/watch?v=abcdefghij'));
        $this->assertNull(Embed::src('javascript:alert(1)'));
        $this->assertNull(Embed::src('https://www.youtube.com/watch?v="><script>'));
        $this->assertNull(Embed::src(null));
    }

    public function testSlugsAreUniquePerTenant(): void
    {
        $a = Sermon::create(['tenant_id' => 1, 'title' => 'La fe que mueve']);
        $b = Sermon::create(['tenant_id' => 1, 'title' => 'La fe que mueve']);
        $c = Sermon::create(['tenant_id' => 2, 'title' => 'La fe que mueve']);

        $this->assertSame('la-fe-que-mueve', $a->slug);
        $this->assertSame('la-fe-que-mueve-2', $b->slug);
        $this->assertSame('la-fe-que-mueve', $c->slug, 'Otra iglesia puede repetir el slug');
        $this->assertSame('contenido', Slugs::unique(Sermon::class, 1, '¡¡¡'));
    }

    public function testVisibilityVisitorVsMember(): void
    {
        Sermon::create(['tenant_id' => 1, 'title' => 'Publica', 'is_published' => true]);
        Sermon::create(['tenant_id' => 1, 'title' => 'Solo miembros', 'is_published' => true, 'visibility' => 'members']);
        Sermon::create(['tenant_id' => 1, 'title' => 'Borrador', 'is_published' => false]);
        Sermon::create(['tenant_id' => 2, 'title' => 'Otra iglesia', 'is_published' => true]);

        $this->assertSame(['Publica'], Sermon::forTenant(1)->shown(false)->pluck('title')->all());
        $this->assertEqualsCanonicalizing(['Publica', 'Solo miembros'], Sermon::forTenant(1)->shown(true)->pluck('title')->all());
    }

    public function testPostsFutureAndMembersHidden(): void
    {
        Post::create(['tenant_id' => 1, 'title' => 'Hoy', 'body' => 'x', 'is_published' => true]);
        Post::create(['tenant_id' => 1, 'title' => 'Programada', 'body' => 'x', 'is_published' => true, 'published_at' => now()->addDays(3)]);
        Post::create(['tenant_id' => 1, 'title' => 'Interna', 'body' => 'x', 'is_published' => true, 'visibility' => 'members']);

        $this->assertSame(['Hoy'], Post::forTenant(1)->shown(false)->pluck('title')->all());
        $this->assertEqualsCanonicalizing(['Hoy', 'Interna'], Post::forTenant(1)->shown(true)->pluck('title')->all());
    }

    public function testResourcesVisibility(): void
    {
        Resource::create(['tenant_id' => 1, 'title' => 'Pública', 'visibility' => 'public', 'is_published' => true]);
        Resource::create(['tenant_id' => 1, 'title' => 'Interna', 'visibility' => 'members', 'is_published' => true]);
        Resource::create(['tenant_id' => 1, 'title' => 'Sin publicar', 'visibility' => 'public', 'is_published' => false]);

        $this->assertSame(['Pública'], Resource::forTenant(1)->shown(false)->pluck('title')->all());
        $this->assertCount(2, Resource::forTenant(1)->shown(true)->get());
    }

    public function testUrlsMustBeHttps(): void
    {
        $this->expectException(\ApplicationException::class);
        Sermon::create(['tenant_id' => 1, 'title' => 'X', 'media_url' => 'javascript:alert(1)']);
    }

    public function testPortalMemberIsScopedToTenantAndStatus(): void
    {
        $user = (object) ['id' => 77];
        $this->assertNull(PortalAccess::memberFor($user, 1));

        $m = Member::create(['tenant_id' => 1, 'first_name' => 'Ana', 'status' => 'active']);
        $m->user_id = 77;
        $m->save();

        $this->assertSame($m->id, PortalAccess::memberFor($user, 1)->id);
        $this->assertNull(PortalAccess::memberFor($user, 2), 'El mismo usuario no es miembro de otra iglesia');
        $this->assertNull(PortalAccess::memberFor(null, 1));

        $m->update(['status' => 'inactive']);
        $this->assertFalse(PortalAccess::isMember($user, 1), 'Un miembro inactivo no ve contenido de miembros');
        $m->update(['status' => 'visitor']);
        $this->assertFalse(PortalAccess::isMember($user, 1));
    }

    public function testPortalUserNeedsEmailAndAdult(): void
    {
        $noEmail = Member::create(['tenant_id' => 1, 'first_name' => 'Sin', 'status' => 'active']);
        try {
            app(ChurchUsers::class)->ensureUser($noEmail);
            $this->fail('Debió pedir correo');
        } catch (\Aero\Church\Classes\ChurchException $e) {
            $this->assertStringContainsString('correo', $e->getMessage());
        }

        $kid = Member::create(['tenant_id' => 1, 'first_name' => 'Niño', 'status' => 'active', 'email' => 'nino@example.invalid', 'birth_date' => now()->subYears(10)->toDateString()]);
        $this->expectException(\Aero\Church\Classes\ChurchException::class);
        app(ChurchUsers::class)->ensureUser($kid);
    }
}
