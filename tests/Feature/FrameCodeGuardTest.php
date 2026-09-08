<?php

namespace Tests\Feature;

use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Support\DispenseVerdict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Code 99 ("Machine transaction not found (NA)") is server-reserved: the
 * marking jobs write it, a TRADE frame may never carry it. Every frame-code
 * lookup goes through VendChannelError::forFrameCode(), which refuses it.
 * The Eloquent success/error scopes resolve their ids from codes, so a 99
 * row counts as a sale and is not a machine error.
 */
class FrameCodeGuardTest extends TestCase
{
    use RefreshDatabase;

    private function seedCodes(): void
    {
        foreach ([[0, 'No Malfunction (0)'], [6, 'Microswitch pressed over time (6)'], [7, 'Sensor error (7)'], [DispenseVerdict::NOT_FOUND_CODE, 'Machine transaction not found (NA)']] as [$code, $desc]) {
            VendChannelError::create(['code' => $code, 'desc' => $desc]);
        }
    }

    public function test_frame_lookup_resolves_real_codes_and_refuses_reserved_ones(): void
    {
        $this->seedCodes();
        Log::spy();

        $this->assertSame(7, VendChannelError::forFrameCode('07')->code);
        $this->assertSame(0, VendChannelError::forFrameCode(0)->code);
        $this->assertNull(VendChannelError::forFrameCode(null));
        $this->assertNull(VendChannelError::forFrameCode('abc'));

        $this->assertNull(VendChannelError::forFrameCode(99, null, '2031'));
        $this->assertNull(VendChannelError::forFrameCode('99', VendChannelError::all()->keyBy('code'), '2031'));
        Log::shouldHaveReceived('warning')->twice();
    }

    public function test_frame_lookup_uses_the_callers_cache(): void
    {
        $this->seedCodes();
        $byCode = VendChannelError::all()->keyBy('code');

        $this->assertSame(6, VendChannelError::forFrameCode('6', $byCode)->code);
        $this->assertSame(6, VendChannelError::forFrameCode(6, $byCode)->code);
    }

    public function test_success_and_error_scopes_resolve_ids_from_codes(): void
    {
        $this->seedCodes();
        $ids = VendChannelError::idsForCodes(DispenseVerdict::SALE_CODES)->pluck('id')->sort()->values()->all();

        $this->assertSame(
            VendChannelError::whereIn('code', [0, 6, 99])->pluck('id')->sort()->values()->all(),
            $ids
        );

        // The scopes compile against the code-resolved sub-select, not literal ids.
        $successful = VendTransaction::query()->isSuccessful()->toSql();
        $error = VendTransaction::query()->isError()->toSql();
        $this->assertStringContainsString('select `id` from `vend_channel_errors` where `code` in', $successful);
        $this->assertStringContainsString('select `id` from `vend_channel_errors` where `code` in', $error);
        $this->assertStringNotContainsString('in (1, 5)', $successful);
    }
}
