<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Temperature page 500'd from 2026-09-19 (bfe6f36e4c): its vendOptions list
 * is built from DB::table rows — plain stdClass — but the machine-ID sweep gave
 * it $vendOption->codeLabel(), a Vend model method. Nothing hit the route
 * end-to-end, so only the field caught it. This test renders the page.
 */
class VendTempPageRendersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_temp_page_renders_with_prefixed_and_bare_machines(): void
    {
        $chiller = Vend::create(['code' => 6003, 'code_prefix' => 'C', 'is_active' => 1]);
        Vend::create(['code' => 2031, 'is_active' => 1]);

        $operator = Operator::create([
            'code' => 'HIPL', 'name' => 'HI SG', 'country_id' => 1,
            'timezone' => 'Asia/Singapore', 'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create(['operator_id' => $operator->id]))
            ->get("/vends/{$chiller->id}/temp/1")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vend/Temp')
                ->where('vendObj.data.code', 'C6003')
                // The dropdown labels every machine the same way the page header does.
                ->where('vendOptions.0.code', '2031')
                ->where('vendOptions.1.code', 'C6003'));
    }
}
