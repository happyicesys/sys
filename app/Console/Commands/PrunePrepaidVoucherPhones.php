<?php

namespace App\Console\Commands;

use App\Models\PrepaidVoucher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Erases the mobile number of every voucher finished for longer than `prepaid_voucher.phone_retention_days`
 * (used, voided, or expired unused). PDPA: the number is kept only while the voucher needs it, plus time
 * for a complaint. The voucher row and its money stay.
 */
class PrunePrepaidVoucherPhones extends Command
{
    protected $signature = 'prepaid-vouchers:prune-phones {--dry-run : Count only}';

    protected $description = 'Erase customer numbers of next-visit vouchers finished past the retention period';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subDays(max(1, (int) config('prepaid_voucher.phone_retention_days', 90)));
        $query = PrepaidVoucher::query()
            ->whereNotNull('phone')
            ->where(fn ($q) => $q
                ->where(fn ($r) => $r->where('status', PrepaidVoucher::STATUS_REDEEMED)->where('redeemed_at', '<', $cutoff))
                ->orWhere(fn ($r) => $r->where('status', PrepaidVoucher::STATUS_VOIDED)->where('voided_at', '<', $cutoff))
                ->orWhere(fn ($r) => $r->whereIn('status', [PrepaidVoucher::STATUS_ACTIVE, PrepaidVoucher::STATUS_RESERVED])->where('expires_at', '<', $cutoff)));

        $count = (clone $query)->count();
        if (! $this->option('dry-run') && $count > 0) {
            $query->update(['phone' => null, 'phone_purged_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
        }
        $this->info(($this->option('dry-run') ? 'Would erase ' : 'Erased ').$count.' number(s).');

        return self::SUCCESS;
    }
}
