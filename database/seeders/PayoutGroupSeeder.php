<?php

namespace Database\Seeders;

use App\Models\Operator;
use App\Models\PayoutGroup;
use Illuminate\Database\Seeder;

/**
 * Seeds the HIPL payout group and assigns the operators that share HIPL's single
 * CIMB account (HIMD / LEA / HIESG / UL-ST are managed by HIPL). Idempotent — safe
 * to re-run.
 *
 * ⚠ ACTION REQUIRED: replace the placeholder bank_account_no / bank_account_name
 * below with HIPL's real shared CIMB account before exporting a live settlement
 * (or set them afterwards on the payout group row). Third-party operators are NOT
 * touched here — they keep their own operators.bank_account_no.
 */
class PayoutGroupSeeder extends Seeder
{
    public function run(): void
    {
        $group = PayoutGroup::updateOrCreate(
            ['code' => 'HIPL'],
            [
                'name' => 'HIPL Group',
                // TODO(brian): set the real shared CIMB account before go-live.
                'bank_account_no' => env('REFUND_CIMB_ACCOUNT_NO', ''),
                'bank_account_name' => env('REFUND_CIMB_ACCOUNT_NAME', 'HAPPY ICE PTE LTD'),
                'is_active' => true,
            ]
        );

        // Operators that pay refunds from the HIPL account.
        $memberCodes = ['HIPL', 'HIMD', 'LEA', 'HIESG', 'UL-ST'];

        Operator::withoutGlobalScopes()
            ->whereIn('code', $memberCodes)
            ->update(['payout_group_id' => $group->id]);
    }
}
