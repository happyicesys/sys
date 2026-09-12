<?php

namespace App\Services\CardSettlement\Payout;

use App\Models\HolidayDay;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * T+N in BANKING days: weekends and Singapore public holidays are skipped, and
 * a term that would land on a non-banking day rolls FORWARD to the next one.
 * So a Friday capture on T+1 pays out on Monday, and a Friday capture before
 * the National Day pair (9-10 Aug 2026) pays out on the Tuesday.
 *
 * Holidays come from `holiday_days` where is_public = 1 — the derived
 * one-row-per-date projection, so this is an equality lookup, not a range scan.
 * ~100 rows cover 2020-2027; they are loaded once and kept for the life of the
 * instance, which SettlementPayoutResolver holds as a singleton (one query per
 * request, not one per grid row).
 *
 * Singapore only, which is all NETS is. Another country's acquirer needs its
 * own holiday source before its dates can be trusted — the flags below turn the
 * holiday rule off rather than letting it answer with the wrong country's.
 */
class BankingCalendar
{
    /** @var array<string,true>|null date string => true; null until first use. */
    protected ?array $publicHolidays = null;

    /**
     * @param  int  $days  N in T+N; 0 still rolls a weekend capture forward to
     *                     the next banking day, which is what "same day" means
     *                     to a bank.
     */
    public function addBankingDays(CarbonInterface|string $from, int $days): CarbonImmutable
    {
        $date = CarbonImmutable::parse($from)->startOfDay();

        for ($i = 0; $i < max(0, $days); $i++) {
            $date = $this->nextBankingDay($date->addDay());
        }

        return $this->nextBankingDay($date);
    }

    public function isBankingDay(CarbonInterface $date): bool
    {
        if (config('card_settlement.payout_calendar.skip_weekends', true) && $date->isWeekend()) {
            return false;
        }

        if (config('card_settlement.payout_calendar.skip_public_holidays', true)
            && isset($this->publicHolidays()[$date->toDateString()])) {
            return false;
        }

        return true;
    }

    protected function nextBankingDay(CarbonImmutable $date): CarbonImmutable
    {
        // Bounded: the longest closure any calendar here produces is a few days,
        // but cap it so a mis-seeded holidays table cannot spin a web request.
        for ($guard = 0; $guard < 31 && ! $this->isBankingDay($date); $guard++) {
            $date = $date->addDay();
        }

        return $date;
    }

    /** @return array<string,true> */
    protected function publicHolidays(): array
    {
        if ($this->publicHolidays === null) {
            $this->publicHolidays = HolidayDay::query()
                ->where('is_public', true)
                ->pluck('date')
                ->mapWithKeys(fn ($date) => [CarbonImmutable::parse($date)->toDateString() => true])
                ->all();
        }

        return $this->publicHolidays;
    }
}
