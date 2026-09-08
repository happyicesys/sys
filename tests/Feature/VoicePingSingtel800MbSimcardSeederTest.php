<?php

namespace Tests\Feature;

use App\Models\Simcard;
use App\Models\Telco;
use Database\Seeders\VoicePingSingtel800MbSimcardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoicePingSingtel800MbSimcardSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_90_sims_onto_the_singtel_package_and_is_idempotent(): void
    {
        $telco = Telco::create(['name' => 'VP-Singtel 800MB']);
        // One ICCID already entered through the UI must be left alone.
        Simcard::create(['code' => '8910300000050055365', 'telco_id' => $telco->id, 'created_by' => 1, 'is_active' => 0]);

        $this->seed(VoicePingSingtel800MbSimcardSeeder::class);

        $this->assertCount(90, VoicePingSingtel800MbSimcardSeeder::ICCIDS);
        $this->assertSame(90, Simcard::where('telco_id', $telco->id)->count());
        $this->assertSame(0, (int) Simcard::where('code', '8910300000050055365')->value('is_active'));
        $this->assertDatabaseHas('simcards', ['code' => '8910300000050055454', 'telco_id' => $telco->id, 'is_active' => 1, 'created_by' => 1]);

        $this->seed(VoicePingSingtel800MbSimcardSeeder::class);
        $this->assertSame(90, Simcard::count());
    }

    public function test_throws_when_package_is_missing(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->seed(VoicePingSingtel800MbSimcardSeeder::class);
    }
}
