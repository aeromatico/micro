<?php namespace Aero\Sites\Tests;

use Aero\Sites\Classes\BusinessHours;
use Carbon\Carbon;
use PluginTestCase;

class BusinessHoursTest extends PluginTestCase
{
    protected $autoRegister = false;

    protected function migrateCurrentPlugin()
    {
    }

    protected function week(): array
    {
        return [
            ['day' => 'mon', 'open' => '08:00', 'close' => '19:30', 'closed' => false],
            ['day' => 'tue', 'open' => '08:00', 'close' => '19:30', 'closed' => false],
            ['day' => 'wed', 'open' => '09:00', 'close' => '19:30', 'closed' => false],
            ['day' => 'thu', 'open' => '08:00', 'close' => '19:30', 'closed' => false],
            ['day' => 'fri', 'open' => '09:00', 'close' => '20:00', 'closed' => false],
            ['day' => 'sat', 'open' => null, 'close' => null, 'closed' => true],
            ['day' => 'sun', 'open' => null, 'close' => null, 'closed' => true],
        ];
    }

    public function testFormularioSeConvierteEnLista(): void
    {
        [$list, $errors] = BusinessHours::fromForm([
            'mon' => ['shifts' => [['open' => '8:00', 'close' => '12:00'], ['open' => '14:00', 'close' => '18:30:00']]],
            'tue' => ['closed' => '1'],
        ]);

        $this->assertSame([], $errors);
        $this->assertSame(['08:00', '12:00'], [$list[0]['open'], $list[0]['close']]);
        $this->assertSame(['14:00', '18:30'], [$list[1]['open'], $list[1]['close']]);
        $this->assertSame('mon', $list[1]['day']);
        $this->assertTrue($list[2]['closed']);
        // Los días que no llegan en el formulario quedan cerrados, no «sin dato».
        $this->assertCount(2 + 1 + 5, $list); // 2 turnos del lunes + martes cerrado + 5 días sin dato
    }

    public function testHorasIncompletasOInvalidasDanError(): void
    {
        [, $errors] = BusinessHours::fromForm(['mon' => ['shifts' => [['open' => '08:00', 'close' => '']]]]);
        $this->assertNotEmpty($errors);

        [, $errors] = BusinessHours::fromForm(['tue' => ['shifts' => [['open' => '25:00', 'close' => '10:00']]]]);
        $this->assertNotEmpty($errors);

        [, $errors] = BusinessHours::fromForm(['wed' => ['shifts' => [['open' => '10:00', 'close' => '10:00']]]]);
        $this->assertNotEmpty($errors);
    }

    public function testAgrupaDiasConsecutivosIguales(): void
    {
        $this->assertSame([
            ['label' => 'Lun a Mar', 'value' => '8:00 – 19:30', 'closed' => false],
            ['label' => 'Miércoles', 'value' => '9:00 – 19:30', 'closed' => false],
            ['label' => 'Jueves', 'value' => '8:00 – 19:30', 'closed' => false],
            ['label' => 'Viernes', 'value' => '9:00 – 20:00', 'closed' => false],
            ['label' => 'Sáb a Dom', 'value' => 'Cerrado', 'closed' => true],
        ], BusinessHours::grouped($this->week()));

        $this->assertSame([], BusinessHours::grouped([]));
    }

    public function testEstaAbiertoSegunDiaYHoraSinDependerDelIdioma(): void
    {
        Carbon::setLocale('es'); // el locale de la app no debe alterar el resultado
        $at = fn (string $d) => Carbon::parse($d, 'America/La_Paz');
        $w = $this->week();

        // 2026-10-16 es viernes.
        $this->assertFalse(BusinessHours::isOpenNow($w, 'America/La_Paz', $at('2026-10-16 08:59')));
        $this->assertTrue(BusinessHours::isOpenNow($w, 'America/La_Paz', $at('2026-10-16 09:00')));
        $this->assertTrue(BusinessHours::isOpenNow($w, 'America/La_Paz', $at('2026-10-16 19:59')));
        $this->assertFalse(BusinessHours::isOpenNow($w, 'America/La_Paz', $at('2026-10-16 20:00')));
        // Jueves 19:30 ya cerró; sábado y domingo cerrado.
        $this->assertFalse(BusinessHours::isOpenNow($w, 'America/La_Paz', $at('2026-10-15 19:30')));
        $this->assertFalse(BusinessHours::isOpenNow($w, 'America/La_Paz', $at('2026-10-17 12:00')));
        $this->assertFalse(BusinessHours::isOpenNow($w, 'America/La_Paz', $at('2026-10-18 12:00')));
    }

    public function testTurnoPartidoYCierrePasadaMedianoche(): void
    {
        $list = [
            ['day' => 'fri', 'open' => '12:00', 'close' => '15:00', 'closed' => false],
            ['day' => 'fri', 'open' => '19:00', 'close' => '02:00', 'closed' => false],
        ];
        $at = fn (string $d) => Carbon::parse($d, 'America/La_Paz');

        $this->assertSame('12:00 – 15:00 y 19:00 – 2:00', BusinessHours::shiftsText(BusinessHours::byDay($list)['fri']));
        $this->assertFalse(BusinessHours::isOpenNow($list, 'America/La_Paz', $at('2026-10-16 16:00')));
        $this->assertTrue(BusinessHours::isOpenNow($list, 'America/La_Paz', $at('2026-10-16 23:30')));
        // Sábado 01:00 sigue siendo el turno del viernes.
        $this->assertTrue(BusinessHours::isOpenNow($list, 'America/La_Paz', $at('2026-10-17 01:00')));
        $this->assertFalse(BusinessHours::isOpenNow($list, 'America/La_Paz', $at('2026-10-17 02:00')));
    }
}
