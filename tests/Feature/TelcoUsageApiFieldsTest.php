<?php

namespace Tests\Feature;

use App\Models\Telco;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SimCard Package "Usage API" + "API query link" (2026-09-08): usage_provider
 * and usage_endpoint are set from the form instead of a migration per package.
 * The provider must be a config/simcard_usage.php key; the link is optional
 * and meaningless without a provider.
 */
class TelcoUsageApiFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_stores_provider_and_endpoint(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/telcos/create', [
                'name' => 'VP-Singtel 800MB',
                'desc' => "VoicePing\n800mb per month",
                'usage_provider' => 'voiceping',
                'usage_endpoint' => 'https://usage.voiceping.com/api/singtel-sim-info',
            ])
            ->assertRedirect(route('telcos'));

        $this->assertDatabaseHas('telcos', [
            'name' => 'VP-Singtel 800MB',
            'usage_provider' => 'voiceping',
            'usage_endpoint' => 'https://usage.voiceping.com/api/singtel-sim-info',
        ]);
    }

    public function test_blank_endpoint_falls_back_to_provider_default(): void
    {
        $telco = Telco::create(['name' => 'VP-Starhub 6GB/y']);

        $this->actingAs(User::factory()->create())
            ->post("/telcos/{$telco->id}/update", [
                'name' => 'VP-Starhub 6GB/y',
                'usage_provider' => 'voiceping',
                'usage_endpoint' => '',
            ])
            ->assertRedirect(route('telcos'));

        $telco->refresh();
        $this->assertSame('voiceping', $telco->usage_provider);
        $this->assertNull($telco->usage_endpoint);
    }

    public function test_clearing_provider_also_clears_endpoint(): void
    {
        $telco = Telco::create([
            'name' => 'VP-Starhub 6GB/y',
            'usage_provider' => 'voiceping',
            'usage_endpoint' => 'https://example.test/api',
        ]);

        $this->actingAs(User::factory()->create())
            ->post("/telcos/{$telco->id}/update", [
                'name' => 'VP-Starhub 6GB/y',
                'usage_provider' => null,
                'usage_endpoint' => 'https://example.test/api',
            ])
            ->assertRedirect(route('telcos'));

        $telco->refresh();
        $this->assertNull($telco->usage_provider);
        $this->assertNull($telco->usage_endpoint);
    }

    public function test_unknown_provider_and_non_url_endpoint_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/telcos')
            ->post('/telcos/create', [
                'name' => 'Bogus',
                'usage_provider' => 'not-a-provider',
                'usage_endpoint' => 'not a url',
            ])
            ->assertRedirect('/telcos')
            ->assertSessionHasErrors(['usage_provider', 'usage_endpoint']);

        $this->assertDatabaseMissing('telcos', ['name' => 'Bogus']);
    }

    public function test_index_exposes_fields_and_provider_options(): void
    {
        Telco::create([
            'name' => 'VP-Starhub 6GB/y',
            'usage_provider' => 'voiceping',
            'usage_endpoint' => 'https://example.test/api',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/telcos')
            ->assertInertia(fn ($page) => $page
                ->component('Telco/Index')
                ->where('telcos.data.0.usage_provider', 'voiceping')
                ->where('telcos.data.0.usage_endpoint', 'https://example.test/api')
                ->where('usageProviderOptions.0.id', 'voiceping')
                ->where('usageProviderOptions.0.endpoint', config('simcard_usage.providers.voiceping.endpoint'))
            );
    }
}
