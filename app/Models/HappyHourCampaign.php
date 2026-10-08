<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Happy Hour campaign: on its days, between window_start and window_end, each bound smart freezer
 * advertises one discounted SKU per slot (slot_minutes long), the SKUs taken in rank order from
 * `selection_rule`. The schedule it produces lives in `happy_hour_slots` (HappyHourPlanner).
 */
class HappyHourCampaign extends Model
{
    use SoftDeletes;

    public const RULE_DAYS_OF_COVER = 'days_of_cover';

    public const RULE_BALANCE_PCT = 'balance_pct';

    public const RULE_RANDOM = 'random';

    public const RULES = [
        self::RULE_DAYS_OF_COVER => 'Most days of cover (stock ÷ daily sales)',
        self::RULE_BALANCE_PCT => 'Highest stock balance % (qty ÷ capacity)',
        self::RULE_RANDOM => 'Random (fixed per machine per day)',
    ];

    public const EVERY_DAY = 0b1111111;

    public const WEEKDAYS = 0b0011111;

    public const WEEKENDS = 0b1100000;

    protected $fillable = [
        'name', 'is_active', 'starts_on', 'ends_on', 'days_mask', 'window_start', 'window_end',
        'slot_minutes', 'sku_count', 'selection_rule', 'lookback_days', 'discount_pct',
        'min_balance_pct', 'min_qty', 'price_step_cents', 'allow_below_cost', 'excluded_product_ids',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'days_mask' => 'integer',
        'slot_minutes' => 'integer',
        'sku_count' => 'integer',
        'lookback_days' => 'integer',
        'discount_pct' => 'integer',
        'min_balance_pct' => 'integer',
        'min_qty' => 'integer',
        'price_step_cents' => 'integer',
        'allow_below_cost' => 'boolean',
        'excluded_product_ids' => 'array',
    ];

    public function vends(): BelongsToMany
    {
        return $this->belongsToMany(Vend::class, 'happy_hour_campaign_vend')
            ->withoutGlobalScopes()
            ->withTimestamps();
    }

    public function slots(): HasMany
    {
        return $this->hasMany(HappyHourSlot::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Whether the campaign runs at all on this calendar day (date range + weekday). */
    public function runsOn(CarbonInterface $day): bool
    {
        $date = $day->toDateString();
        if ($this->starts_on !== null && $date < $this->starts_on->toDateString()) {
            return false;
        }
        if ($this->ends_on !== null && $date > $this->ends_on->toDateString()) {
            return false;
        }

        return self::maskHasDay($this->days_mask, $day);
    }

    public static function maskHasDay(int $mask, CarbonInterface $day): bool
    {
        return ($mask & (1 << ($day->isoWeekday() - 1))) !== 0;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} the window on that day, app timezone */
    public function windowOn(CarbonInterface $day): array
    {
        $date = $day->toDateString();

        return [
            CarbonImmutable::parse($date.' '.$this->window_start),
            CarbonImmutable::parse($date.' '.$this->window_end),
        ];
    }

    /**
     * The slot start times of one day: from window_start every slot_minutes; the last slot is cut
     * at window_end.
     *
     * @return list<array{position: int, starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function slotTimesOn(CarbonInterface $day): array
    {
        [$start, $end] = $this->windowOn($day);
        $minutes = max(1, $this->slot_minutes);
        $slots = [];
        for ($at = $start, $position = 0; $at->lt($end); $at = $at->addMinutes($minutes), $position++) {
            $slots[] = ['position' => $position, 'starts_at' => $at, 'ends_at' => $at->addMinutes($minutes)->min($end)];
        }

        return $slots;
    }

    /** @return list<int> */
    public function excludedProductIds(): array
    {
        return array_values(array_map('intval', (array) ($this->excluded_product_ids ?? [])));
    }

    /** Human text for the days mask, e.g. "Every day", "Weekdays", "Mon, Wed". */
    public function daysLabel(): string
    {
        return match ($this->days_mask) {
            self::EVERY_DAY => 'Every day',
            self::WEEKDAYS => 'Weekdays',
            self::WEEKENDS => 'Weekends',
            default => collect(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])
                ->filter(fn ($d, $i) => ($this->days_mask & (1 << $i)) !== 0)
                ->implode(', '),
        };
    }
}
