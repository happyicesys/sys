<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The Site and Machine edit pages post back the record exactly as Laravel serialized it, dates as UTC ISO-8601
 * ("2023-10-21T16:00:00.000000Z" for a Singapore midnight). Until 2026-10-19 every save stored that UTC wall time
 * as Singapore time, so begin_date / termination_date / last_invoice_date crept 8 hours back per save (Site 5735's
 * termination date 10-08 → 10-07 in two saves). StoresDatesInAppTimezone converts such a string first.
 */
class DateRoundTripTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private function site(): Customer
    {
        return Customer::create([
            'name' => 'Site', 'code' => 'TZ1', 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE,
            'begin_date' => '2023-10-22', 'termination_date' => '2026-10-08 00:00:00', 'last_invoice_date' => '2026-05-24',
        ]);
    }

    private function stored(string $table, int $id, string $column): string
    {
        return (string) DB::table($table)->where('id', $id)->value($column);
    }

    public function test_a_site_saved_three_times_from_its_own_json_keeps_its_dates(): void
    {
        $site = $this->site();

        for ($i = 0; $i < 3; $i++) {
            $json = json_decode(json_encode($site->fresh()), true);
            $this->assertStringEndsWith('Z', $json['begin_date'], 'the page receives UTC ISO-8601');
            $site->fresh()->fill(array_intersect_key($json, array_flip(['begin_date', 'termination_date', 'last_invoice_date'])))->save();
        }

        $this->assertSame('2023-10-22 00:00:00', $this->stored('customers', $site->id, 'begin_date'));
        $this->assertSame('2026-10-08 00:00:00', $this->stored('customers', $site->id, 'termination_date'));
        $this->assertSame('2026-05-24 00:00:00', $this->stored('customers', $site->id, 'last_invoice_date'));
    }

    public function test_the_site_edit_save_no_longer_moves_the_dates_back(): void
    {
        $site = $this->site();
        Permission::findOrCreate('update customers', 'web');
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo('update customers');

        $json = json_decode(json_encode($site->fresh()), true);
        $this->actingAs($user)->post("/customers/{$site->id}/update", [
            'customer' => [
                'status_id' => Customer::STATUS_ACTIVE,
                'begin_date' => $json['begin_date'],
                'termination_date' => $json['termination_date'],
                'last_invoice_date' => $json['last_invoice_date'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('2023-10-22 00:00:00', $this->stored('customers', $site->id, 'begin_date'));
        $this->assertSame('2026-10-08 00:00:00', $this->stored('customers', $site->id, 'termination_date'));
        $this->assertSame('2026-05-24 00:00:00', $this->stored('customers', $site->id, 'last_invoice_date'));
    }

    public function test_a_machine_saved_from_its_own_json_keeps_its_begin_date(): void
    {
        $vend = Vend::forceCreate(['code' => 99001, 'operator_id' => 1, 'begin_date' => '2024-03-01 00:00:00']);

        for ($i = 0; $i < 3; $i++) {
            $json = json_decode(json_encode($vend->fresh()), true);
            $vend->fresh()->fill(['begin_date' => $json['begin_date']])->save();
        }

        $this->assertSame('2024-03-01 00:00:00', $this->stored('vends', $vend->id, 'begin_date'));
    }

    public function test_dates_without_a_zone_and_carbons_are_stored_as_before(): void
    {
        $site = $this->site();

        $site->update(['begin_date' => '2024-01-02 13:45:00', 'termination_date' => '2026-10-09']);
        $this->assertSame('2024-01-02 13:45:00', $this->stored('customers', $site->id, 'begin_date'));
        $this->assertSame('2026-10-09 00:00:00', $this->stored('customers', $site->id, 'termination_date'));

        $site->update(['begin_date' => now()->setDate(2025, 5, 6)->setTime(7, 30)]);
        $this->assertSame('2025-05-06 07:30:00', $this->stored('customers', $site->id, 'begin_date'));

        // An explicit offset is honoured: 09:00 in UTC+05:30 is 11:30 in Singapore.
        $site->update(['begin_date' => '2025-05-06T09:00:00+05:30']);
        $this->assertSame('2025-05-06 11:30:00', $this->stored('customers', $site->id, 'begin_date'));
    }
}
