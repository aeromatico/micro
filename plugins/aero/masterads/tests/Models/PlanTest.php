<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Models;

use Aero\MasterAds\Models\Plan;
use October\Rain\Exception\ValidationException;
use PluginTestCase;

/**
 * PlanTest — Validation rules, uniqueness and Eloquent casts for the
 * billing catalog entity. Validates Requirements 9.1, 9.2 of the
 * master-ads spec.
 */
class PlanTest extends PluginTestCase
{
    public function testRequiresCodeAndCaps(): void
    {
        // Empty payload violates `code`, `name`, `monthly_price`,
        // `max_meta_accounts` and `max_analyses_month` required rules.
        $this->expectException(ValidationException::class);

        Plan::create([]);
    }

    public function testCodeIsUnique(): void
    {
        Plan::create([
            'code'               => 'pro-tier',
            'name'               => 'Pro',
            'monthly_price'      => 19.99,
            'max_meta_accounts'  => 5,
            'max_analyses_month' => 100,
            'auto_apply_allowed' => true,
        ]);

        $this->expectException(ValidationException::class);

        Plan::create([
            'code'               => 'pro-tier',
            'name'               => 'Pro Duplicate',
            'monthly_price'      => 29.99,
            'max_meta_accounts'  => 5,
            'max_analyses_month' => 100,
            'auto_apply_allowed' => true,
        ]);
    }

    public function testCastsBooleanAndDecimals(): void
    {
        Plan::create([
            'code'               => 'cast-tier',
            'name'               => 'Cast Tier',
            'monthly_price'      => 9.99,
            'max_meta_accounts'  => 1,
            'max_analyses_month' => 10,
            // Provide an integer here so the bool cast is exercised on
            // the round-trip read below.
            'auto_apply_allowed' => 1,
        ]);

        // Refetch from DB so we exercise the cast on the read path.
        $reloaded = Plan::where('code', 'cast-tier')->firstOrFail();

        // `auto_apply_allowed` is declared `boolean` in $casts — should be
        // a real PHP bool, not the raw int the DB returns.
        $this->assertIsBool($reloaded->auto_apply_allowed);
        $this->assertTrue($reloaded->auto_apply_allowed);

        // `monthly_price` is declared `decimal:2` — Eloquent returns it as
        // a string with exactly two decimals. We assert both shape and value.
        $this->assertIsString($reloaded->monthly_price);
        $this->assertSame('9.99', $reloaded->monthly_price);
    }
}
