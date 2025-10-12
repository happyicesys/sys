<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Operator;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $operatorId = Operator::query()->value('id');

        if (! $operatorId) {
            $this->command?->warn('CampaignSeeder skipped: no operators found.');

            return;
        }

        $campaigns = [
            [
                'name' => 'Weekend Bundle Blast',
                'slug' => 'weekend-bundle-blast',
                'description' => 'Buy three snacks and enjoy 15% off the bundle.',
                'promo_type' => Campaign::TYPE_PERCENTAGE,
                'is_using_qty' => 'qty',
                'bundle_qty' => 3,
                'value' => 15.0,
                'remarks' => 'Applies every Friday to Sunday.',
                'start_at' => Carbon::today()->startOfDay(),
                'end_at' => Carbon::today()->addMonth()->endOfDay(),
                'labels_x' => ['buy_3_cornetto_at_$6.00'],
                'labels_y' => ['buy_2_cornetto_classics_at_$3.90'],
            ],
            [
                'name' => 'Lunch Hour Saver',
                'slug' => 'lunch-hour-saver',
                'description' => 'Save $5 on orders above $20 during lunch hours.',
                'promo_type' => Campaign::TYPE_AMOUNT,
                'is_using_qty' => 'amount',
                'bundle_qty' => null,
                'value' => 5.0,
                'min_basket_value' => 20.0,
                'remarks' => 'Available from 11am to 2pm daily.',
                'start_at' => Carbon::today()->addDays(3)->startOfDay(),
                'end_at' => Carbon::today()->addMonths(2)->endOfDay(),
                'labels_x' => ['buy_2_magnum_at_$7.50'],
                'labels_y' => [],
            ],
            [
                'name' => 'Combo Delight Deal',
                'slug' => 'combo-delight-deal',
                'description' => 'Mix-and-match two items for 10% off, capped at $8.',
                'promo_type' => Campaign::TYPE_PERCENTAGE,
                'is_using_qty' => 'both',
                'bundle_qty' => 2,
                'value' => 10.0,
                'max_discount_value' => 8.0,
                'remarks' => 'Limit of three redemptions per customer.',
                'start_at' => Carbon::today()->subWeek()->startOfDay(),
                'end_at' => Carbon::today()->addMonths(3)->endOfDay(),
                'labels_x' => ['buy_2_pints_get_10%_off'],
                'labels_y' => ['buy_2_cups_get_10%_off'],
            ],
            [
                'name' => 'Mega Freebie Promo',
                'slug' => 'mega-freebie-promo',
                'description' => 'Get a free drink when purchasing four ice creams.',
                'promo_type' => Campaign::TYPE_ITEM,
                'is_using_qty' => 'qty',
                'bundle_qty' => 4,
                'value' => null,
                'remarks' => 'While stocks last.',
                'start_at' => Carbon::today()->startOfDay(),
                'end_at' => Carbon::today()->addMonths(1)->endOfDay(),
                'labels_x' => ['bulla_classics_pint'],
                'labels_y' => ['bulla_frozen_yogurt'],
            ],
        ];

        foreach ($campaigns as $campaignData) {
            $labelsX = $this->resolveTags(Arr::get($campaignData, 'labels_x', []));
            $labelsY = $this->resolveTags(Arr::get($campaignData, 'labels_y', []));

            $campaign = Campaign::updateOrCreate(
                ['slug' => $campaignData['slug']],
                array_merge(
                    Arr::except($campaignData, ['bundle_qty', 'labels_x', 'labels_y']),
                    [
                        'operator_id' => $operatorId,
                        'bundle_qty' => $campaignData['bundle_qty'],
                        'is_active' => true,
                    ]
                )
            );

            if ($campaign) {
                if ($labelsX->isNotEmpty()) {
                    $campaign->labelsX()->sync($labelsX->mapWithKeys(fn ($id) => [$id => ['type' => 'x']])->toArray(), false);
                }

                if ($labelsY->isNotEmpty()) {
                    $campaign->labelsY()->sync($labelsY->mapWithKeys(fn ($id) => [$id => ['type' => 'y']])->toArray(), false);
                }
            }
        }
    }

    /**
     * Resolve or create tags from provided slugs.
     *
     * @param  array<int, string>  $slugs
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function resolveTags(array $slugs): Collection
    {
        return collect($slugs)
            ->filter()
            ->map(function ($slug) {
                $tag = Tag::firstOrCreate(
                    ['slug' => $slug],
                    [
                        'classname' => 'App\\Models\\Product',
                        'name' => Str::slug($slug, '_'),
                        'desc' => Str::headline(str_replace(['_', '-'], ' ', $slug)),
                    ]
                );

                return $tag->id;
            });
    }
}
