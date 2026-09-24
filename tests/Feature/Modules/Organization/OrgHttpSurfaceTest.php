<?php

namespace Tests\Feature\Modules\Organization;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

/**
 * Lightweight route-registration smoke (no DB). Ensures P-gap HTTP surface exists.
 */
class OrgHttpSurfaceTest extends TestCase
{
    #[Test]
    public function organization_extended_routes_are_registered(): void
    {
        $names = collect(Route::getRoutes())->map(fn ($r) => $r->uri())->all();

        $expectedFragments = [
            'ownerships',
            'fiscal-assignments',
            'sales-organizations',
            'purchasing-organizations',
            'consolidation-runs',
            'structure/apply-template',
            'business-units',
            'hierarchies',
            'intercompany/partners',
            'bank-accounts',
            'officers',
            'cost-centers',
        ];

        foreach ($expectedFragments as $frag) {
            $found = false;
            foreach ($names as $uri) {
                if (str_contains((string) $uri, $frag)) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, "Route fragment missing: {$frag}");
        }
    }
}
