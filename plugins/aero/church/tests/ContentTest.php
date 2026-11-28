<?php namespace Aero\Church\Tests;

use Aero\Church\Classes\ChurchUsers;
use Aero\Church\Classes\Embed;
use Aero\Church\Classes\HomeContent;
use Aero\Church\Classes\LocalTime;
use Aero\Church\Models\ChurchEvent;
use Aero\Church\Classes\PortalAccess;
use Aero\Church\Classes\SiteMenu;
use Aero\Church\Models\ChurchSettings;
use Aero\Church\Classes\Slugs;
use Aero\Church\Classes\WeeklySchedule;
use Aero\Church\Models\ServiceTime;
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

    public function testSiteMenuListsOnlyActiveSections(): void
    {
        $this->assertSame(['main' => [], 'more' => []], SiteMenu::forTenant(null));
        $this->assertSame(['main' => [], 'more' => []], SiteMenu::forTenant(1), 'Sin configuración no hay enlaces');

        $s = ChurchSettings::forTenant(1);
        $s->forceFill(['site_enabled' => false, 'portal_enabled' => false, 'welcome_enabled' => false, 'prayer_enabled' => true])->save();
        $this->assertSame(['main' => [['url' => '/oracion', 'label' => 'Oración']], 'more' => []], SiteMenu::forTenant(1));

        $s->forceFill(['site_enabled' => true, 'portal_enabled' => true, 'welcome_enabled' => true])->save();
        $menu = SiteMenu::forTenant(1);
        $this->assertSame(['/eventos', '/predicaciones', '/oracion', '/bienvenida'], array_column($menu['main'], 'url'));
        $this->assertSame(['/en-vivo', '/noticias', '/recursos', '/miembro'], array_column($menu['more'], 'url'));
        $this->assertSame(['main' => [], 'more' => []], SiteMenu::forTenant(2), 'Otra iglesia no hereda el menú');
    }

    public function testHomeShowsOnlyPublicContentOfThatChurch(): void
    {
        $s = ChurchSettings::forTenant(1);
        $s->forceFill(['site_enabled' => false])->save();
        $this->assertNull(HomeContent::forTenant(1), 'Con el sitio apagado no hay portada');
        $this->assertNull(HomeContent::forTenant(null));
        $hasHome = fn () => \Event::fire('aero.sites.tenantHasHome', [(object) ['id' => 1]], true);
        $this->assertNull($hasHome(), 'Sin sitio, la portada no cuenta como lista');

        $s->forceFill(['site_enabled' => true, 'prayer_enabled' => true, 'welcome_enabled' => false])->save();
        ChurchEvent::create(['tenant_id' => 1, 'title' => 'Público', 'type' => 'service', 'starts_at' => now()->addDays(2), 'is_public' => true]);
        ChurchEvent::create(['tenant_id' => 1, 'title' => 'Interno', 'type' => 'service', 'starts_at' => now()->addDays(2), 'is_public' => false]);
        ChurchEvent::create(['tenant_id' => 1, 'title' => 'Pasado', 'type' => 'service', 'starts_at' => now()->subDays(2), 'is_public' => true]);
        ChurchEvent::create(['tenant_id' => 2, 'title' => 'Ajeno', 'type' => 'service', 'starts_at' => now()->addDays(2), 'is_public' => true]);
        Sermon::create(['tenant_id' => 1, 'title' => 'Abierta', 'is_published' => true]);
        Sermon::create(['tenant_id' => 1, 'title' => 'De miembros', 'is_published' => true, 'visibility' => 'members']);
        Post::create(['tenant_id' => 2, 'title' => 'Ajena', 'body' => 'x', 'is_published' => true]);

        $home = HomeContent::forTenant(1);
        $this->assertSame(['Público'], array_column($home['events'], 'title'));
        $this->assertSame(['Abierta'], array_column($home['sermons'], 'title'));
        $this->assertSame([], $home['posts']);
        $this->assertNull($home['sermons'][0]['embed']);
        Sermon::where('title', 'Abierta')->first()->update(['media_url' => 'https://youtu.be/wOZQWmiBHx4']);
        $this->assertSame('https://www.youtube-nocookie.com/embed/wOZQWmiBHx4', HomeContent::forTenant(1)['sermons'][0]['embed']);
        $this->assertTrue($home['prayer']);
        $this->assertFalse($home['welcome']);
        $this->assertCount(2, HomeContent::forTenant(1, true)['sermons']);
        $this->assertTrue($hasHome());
    }

    public function testServiceTimeAcceptsCommonHourFormats(): void
    {
        foreach (['15:45' => '15:45', '3:45 pm' => '15:45', '7 p. m.' => '19:00', '9.30' => '09:30', '12 am' => '00:00', '12:30 PM' => '12:30', '11h00' => '11:00'] as $in => $out) {
            $this->assertSame($out, ServiceTime::parseTime((string) $in), (string) $in);
        }
        foreach (['', 'tarde', '25:00', '13 pm', '10:75'] as $bad) {
            $this->assertNull(ServiceTime::parseTime($bad), $bad);
        }
        $this->assertSame('3:45 – 6:00 p. m.', WeeklySchedule::range('15:45', '18:00'));
        $this->assertSame('11:00 a. m. – 12:30 p. m.', WeeklySchedule::range('11:00', '12:30'));

        $this->expectException(\October\Rain\Exception\ValidationException::class);
        ServiceTime::create(['tenant_id' => 1, 'title' => 'Al revés', 'day' => 4, 'starts_at' => '7 pm', 'ends_at' => '9']);
    }

    public function testWeeklyScheduleGroupsByDayAndFindsNextMeeting(): void
    {
        foreach ([[7, '11:00', '12:30', 'General'], [6, '19:00', '21:00', 'Jóvenes'], [7, '9:30', '10:30', 'Santa Cena'], [2, '3:45 pm', '6 pm', 'Femenil']] as [$day, $from, $to, $title]) {
            ServiceTime::create(['tenant_id' => 1, 'title' => $title, 'day' => $day, 'starts_at' => $from, 'ends_at' => $to]);
        }
        ServiceTime::create(['tenant_id' => 1, 'title' => 'Oculta', 'day' => 1, 'starts_at' => '10:00', 'is_active' => false]);
        ServiceTime::create(['tenant_id' => 2, 'title' => 'Ajena', 'day' => 6, 'starts_at' => '18:00']);

        $saturday = \Carbon\Carbon::parse('2026-11-28 09:00', 'America/La_Paz');
        $days = WeeklySchedule::days(1, $saturday);
        $this->assertSame(['Martes', 'Sábado', 'Domingo'], array_column($days, 'label'));
        $this->assertSame([false, true, false], array_column($days, 'today'));
        $this->assertSame(['Santa Cena', 'General'], array_column($days[2]['items'], 'title'));
        $this->assertSame([], WeeklySchedule::days(null));

        $next = fn (string $at) => WeeklySchedule::next(1, \Carbon\Carbon::parse($at, 'America/La_Paz'));
        $this->assertSame(['Jóvenes', 'Hoy', false], [$next('2026-11-28 09:00')['title'], $next('2026-11-28 09:00')['when'], $next('2026-11-28 09:00')['now']]);
        $this->assertTrue($next('2026-11-28 20:00')['now'], 'En curso mientras no termine');
        $this->assertSame(['Santa Cena', 'Mañana'], [$next('2026-11-28 21:00')['title'], $next('2026-11-28 21:00')['when']]);
        $this->assertSame('2026-12-01', $next('2026-11-29 13:00')['date']->format('Y-m-d'), 'Pasa a la semana siguiente');
        $this->assertNull(WeeklySchedule::next(3));

        $s = ChurchSettings::forTenant(1);
        $s->forceFill(['site_enabled' => true])->save();
        $this->assertSame('/horarios', SiteMenu::forTenant(1)['main'][0]['url']);
        $this->assertCount(3, HomeContent::forTenant(1)['schedule']);
        $this->assertSame('/horarios', HomeContent::forTenant(1)['next']['url']);
    }

    public function testEventTimesShowInChurchTimeAndEmptyNumbersAreAllowed(): void
    {
        // El panel envía la hora ya en UTC: las 10:00 de La Paz llegan como 14:00.
        $e = ChurchEvent::create(['tenant_id' => 1, 'title' => 'Retiro', 'type' => 'retreat', 'starts_at' => now()->addDays(3)->setTime(14, 0), 'capacity' => '', 'reminder_hours' => '', 'is_public' => true]);
        $e = $e->fresh();
        $this->assertNull($e->capacity);
        $this->assertNull($e->reminder_hours);
        $this->assertSame('special', ChurchEvent::create(['tenant_id' => 3, 'title' => 'Sin tipo', 'starts_at' => now()])->type, 'Un evento nuevo es especial');
        $this->assertSame('10:00', $e->starts_local->format('H:i'));
        $this->assertSame('14:00', $e->starts_at->format('H:i'), 'En la base sigue en UTC');
        $this->assertSame('2026-12-01 01:00', LocalTime::toUtc(\Carbon\Carbon::parse('2026-11-30'), 21)->format('Y-m-d H:i'));

        ChurchSettings::forTenant(1)->forceFill(['site_enabled' => true])->save();
        ServiceTime::create(['tenant_id' => 1, 'title' => 'General', 'day' => 7, 'starts_at' => '11:00']);
        $next = HomeContent::forTenant(1)['next'];
        $this->assertSame(['Evento especial', 'Retiro'], [$next['label'], $next['title']], 'El evento especial va destacado');
        $this->assertStringEndsWith('10:00', $next['when']);
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
