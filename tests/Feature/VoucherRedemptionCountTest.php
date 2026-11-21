<?php

namespace Tests\Feature;

use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherRedemptionCountTest extends TestCase
{
    use RefreshDatabase;

    private function sameCodeVoucher(int $qty): Voucher
    {
        return Voucher::create([
            'code' => 'SAME'.$qty,
            'name' => 'Same code',
            'qty' => $qty,
            'type' => Voucher::TYPE_ITEM,
            'date_from' => now()->subDay(),
            'date_to' => now()->addDay(),
            'status' => Voucher::STATUS_ACTIVE,
            'is_batch_code' => false,
        ]);
    }

    public function test_same_code_voucher_allows_every_claimable_use(): void
    {
        $voucher = $this->sameCodeVoucher(3);
        $service = new VoucherService;

        $service->updateUsedVoucher('SAME3');
        $service->updateUsedVoucher('SAME3');
        $voucher->refresh();
        $this->assertSame(2, (int) $voucher->used_qty);
        $this->assertSame(Voucher::STATUS_ACTIVE, (int) $voucher->status);

        $service->updateUsedVoucher('SAME3');
        $voucher->refresh();
        $this->assertSame(3, (int) $voucher->used_qty);
        $this->assertSame(Voucher::STATUS_REDEEMED, (int) $voucher->status);
    }

    public function test_single_use_same_code_voucher_closes_after_one_use(): void
    {
        $voucher = $this->sameCodeVoucher(1);

        (new VoucherService)->updateUsedVoucher('SAME1');

        $voucher->refresh();
        $this->assertSame(1, (int) $voucher->used_qty);
        $this->assertSame(Voucher::STATUS_REDEEMED, (int) $voucher->status);
    }

    public function test_unique_code_redeems_only_that_code(): void
    {
        $voucher = Voucher::create([
            'code' => '',
            'name' => 'Unique codes',
            'qty' => 2,
            'type' => Voucher::TYPE_ITEM,
            'date_from' => now()->subDay(),
            'date_to' => now()->addDay(),
            'status' => Voucher::STATUS_ACTIVE,
            'is_batch_code' => true,
        ]);
        (new VoucherService)->syncVoucherItems($voucher);
        [$first, $second] = $voucher->voucherItems()->orderBy('id')->get()->all();

        (new VoucherService)->updateUsedVoucher($first->code);

        $this->assertSame(Voucher::STATUS_REDEEMED, (int) $first->fresh()->status);
        $this->assertTrue($first->fresh()->is_redeemed);
        $this->assertSame(Voucher::STATUS_ACTIVE, (int) $second->fresh()->status);
        $this->assertSame(1, (int) $voucher->fresh()->used_qty);
    }
}
