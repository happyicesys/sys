<?php

namespace App\Support\Sales;

use App\Models\HappyHourCampaign;
use App\Models\HappyHourSlot;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The "Campaign Label(s)" of a sale — one definition for the Sales Transactions column, its filter
 * and the CSV exports (Brian, 2026-10-15: Happy Hour is the default campaign, so it is a label).
 *
 * A sale carries two kinds of label:
 *   - product campaign labels: the tag ids/names/slugs in `vend_transactions.label_json`;
 *   - Happy Hour: `vend_transactions.happy_hour_slot_id` (HappyHourSales::tag), shown as "HH 30%"
 *     from the slot's frozen discount.
 *
 * Filter values (request key `tag`, kept for bookmarked URLs):
 *   `all`        no filter
 *   `any`        any label at all — a product label OR Happy Hour
 *   `hh`         any Happy Hour sale
 *   `hhc:<id>`   a Happy Hour sale of that campaign (the dropdown names it, Brian 2026-10-15)
 *   `hh:<pct>`   a Happy Hour sale at that discount — no longer offered, kept for bookmarked URLs
 *   anything else a product tag id, name or slug
 */
final class CampaignLabelFilter
{
    public const ALL = 'all';

    public const ANY = 'any';

    public const HAPPY_HOUR = 'hh';

    private const HAPPY_HOUR_PCT = 'hh:';

    private const HAPPY_HOUR_CAMPAIGN = 'hhc:';

    /** "HH 30%" — the label a Happy Hour sale shows at that discount. */
    public static function happyHourLabel(int $discountPct): string
    {
        return "HH {$discountPct}%";
    }

    /**
     * The Happy Hour filter options: all Happy Hour, then one per campaign by its name (Brian,
     * 2026-10-15: the name, not the discount). A deleted campaign is listed while it has slots,
     * since its sales stay tagged. The campaign's operator scope applies: a viewer sees its own.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function happyHourOptions(): array
    {
        $campaigns = HappyHourCampaign::withTrashed()
            ->where(fn ($q) => $q->whereNull('deleted_at')->orWhereExists(fn ($sub) => $sub->selectRaw('1')
                ->from('happy_hour_slots')->whereColumn('happy_hour_slots.happy_hour_campaign_id', 'happy_hour_campaigns.id')))
            ->orderByRaw('deleted_at IS NOT NULL')->orderBy('name')->orderBy('id')
            ->get(['id', 'name', 'deleted_at']);

        $options = [['id' => self::HAPPY_HOUR, 'name' => '★ Happy Hour (all)']];
        foreach ($campaigns as $campaign) {
            $options[] = [
                'id' => self::HAPPY_HOUR_CAMPAIGN.$campaign->id,
                'name' => '★ '.$campaign->name.($campaign->deleted_at ? ' (deleted)' : ''),
            ];
        }

        return $options;
    }

    /**
     * "HH 30%" per slot id, one query — for the CSV exports.
     *
     * @param  iterable<int|string|null>  $slotIds
     * @return array<int, string>
     */
    public static function happyHourLabels(iterable $slotIds): array
    {
        $ids = collect($slotIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return HappyHourSlot::query()->whereIn('id', $ids)->pluck('discount_pct', 'id')
            ->map(fn ($pct) => self::happyHourLabel((int) $pct))->all();
    }

    /** Narrows a vend_transactions query to the chosen label. `all` / empty leaves it alone. */
    public static function apply(EloquentBuilder|QueryBuilder $q, ?string $value): void
    {
        $value = trim((string) $value);
        if ($value === '' || $value === self::ALL) {
            return;
        }

        if ($value === self::ANY) {
            $q->where(fn ($sub) => $sub
                ->whereRaw('JSON_LENGTH(COALESCE(vend_transactions.label_json, JSON_ARRAY())) > 0')
                ->orWhereNotNull('vend_transactions.happy_hour_slot_id'));

            return;
        }

        if ($value === self::HAPPY_HOUR) {
            $q->whereNotNull('vend_transactions.happy_hour_slot_id');

            return;
        }

        if (str_starts_with($value, self::HAPPY_HOUR_CAMPAIGN)) {
            $campaignId = (int) substr($value, strlen(self::HAPPY_HOUR_CAMPAIGN));
            $q->whereNotNull('vend_transactions.happy_hour_slot_id')
                ->whereExists(fn ($sub) => $sub->selectRaw('1')->from('happy_hour_slots as hh_slot')
                    ->whereColumn('hh_slot.id', 'vend_transactions.happy_hour_slot_id')
                    ->where('hh_slot.happy_hour_campaign_id', $campaignId));

            return;
        }

        if (str_starts_with($value, self::HAPPY_HOUR_PCT)) {
            $pct = (int) substr($value, strlen(self::HAPPY_HOUR_PCT));
            // The header names the slot for a single sale and for a basket holding a promo unit.
            $q->whereNotNull('vend_transactions.happy_hour_slot_id')
                ->whereExists(fn ($sub) => $sub->selectRaw('1')->from('happy_hour_slots as hh_slot')
                    ->whereColumn('hh_slot.id', 'vend_transactions.happy_hour_slot_id')
                    ->where('hh_slot.discount_pct', $pct));

            return;
        }

        self::applyProductTag($q, $value);
    }

    /** A product campaign label: its id, name or slug, however label_json stored it. */
    private static function applyProductTag(EloquentBuilder|QueryBuilder $q, string $tag): void
    {
        $isNumeric = is_numeric($tag);

        $q->where(function ($sub) use ($isNumeric, $tag) {
            // Direct id / name / slug stored in the JSON array.
            $sub->whereJsonContains('vend_transactions.label_json', $isNumeric ? (int) $tag : $tag);

            // Cross-match via tags (the JSON holds a name while the filter is an id, or vice versa).
            [$where, $bindings] = $isNumeric ? ['t.id = ?', [(int) $tag]] : ['t.name = ? OR t.slug = ?', [$tag, $tag]];
            $sub->orWhereRaw(
                "EXISTS (\n".
                "  SELECT 1\n".
                "  FROM JSON_TABLE(\n".
                "         COALESCE(vend_transactions.label_json, JSON_ARRAY()),\n".
                "         '$[*]' COLUMNS(\n".
                "           tag_id BIGINT PATH '$',\n".
                "           tag_name VARCHAR(255) PATH '$'\n".
                "         )\n".
                "       ) jt\n".
                "  JOIN tags t ON (t.id = jt.tag_id OR t.name = jt.tag_name)\n".
                "  WHERE {$where}\n".
                ')',
                $bindings
            );
        });
    }
}
