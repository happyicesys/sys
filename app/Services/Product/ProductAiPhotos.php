<?php

namespace App\Services\Product;

use App\Models\CityboxProduct;
use App\Models\Product;
use App\Models\Vend;
use App\Models\ZijiaSkuApplication;
use App\Services\SmartFreezer\Zijia\ZijiaBarcodeSync;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What each AI holds of a product, for the Products index (Brian, 2026-11-04: so an operator sees a
 * SKU that was missed). Smart Freezer: the photos of its Zijia application, and whether the product
 * reaches their AI — the rule of ZijiaSkuLinks, a barcode their library lists. Smart Chiller: the
 * photos of the CityBox catalogue row linked to it. `missing` = on such a machine's planogram now,
 * and the AI does not have it.
 */
class ProductAiPhotos
{
    public const NOTE_NOT_LISTED = 'not_listed';

    public const NOTE_DISABLED = 'disabled';

    public const NOTE_DELISTED = 'delisted';

    public function __construct(private readonly ZijiaBarcodeSync $sync) {}

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, array{freezer: array, chiller: array}> by product id
     */
    public function forProducts(Collection $products): array
    {
        $ids = $products->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        $applications = ZijiaSkuApplication::query()->whereIn('product_id', $ids)->latest('id')
            ->get(['id', 'product_id', 'status', 'package_image_url', 'model_pics'])->groupBy('product_id');
        $catalogue = CityboxProduct::query()->whereIn('product_id', $ids)->latest('id')
            ->get(['id', 'product_id', 'img_url', 'vision_imgs', 'is_delisted', 'citybox_status'])->groupBy('product_id');
        $onFreezer = $this->onMachines($ids, Vend::MACHINE_TYPE_SMART_FREEZER);
        $onChiller = $this->onMachines($ids, Vend::MACHINE_TYPE_SMART_CHILLER);
        $unknownBarcodes = array_flip($this->sync->barcodesNotInLibrary());

        $out = [];
        foreach ($products as $product) {
            $listed = filled($product->barcode) && ! isset($unknownBarcodes[$product->id]);
            $out[$product->id] = [
                'freezer' => $this->freezerCell($applications->get($product->id, collect()), $listed, isset($onFreezer[$product->id])),
                'chiller' => $this->chillerCell($catalogue->get($product->id, collect()), isset($onChiller[$product->id])),
            ];
        }

        return $out;
    }

    /** @param  Collection<int, ZijiaSkuApplication>  $rows  newest first */
    private function freezerCell(Collection $rows, bool $listed, bool $onMachine): array
    {
        $application = $rows->firstWhere('status', ZijiaSkuApplication::STATUS_APPROVED)
            ?? $rows->first(fn ($a) => in_array($a->status, [ZijiaSkuApplication::STATUS_DRAFT, ZijiaSkuApplication::STATUS_SUBMITTED], true))
            ?? $rows->first();
        $approved = $application?->status === ZijiaSkuApplication::STATUS_APPROVED;
        $pics = collect((array) $application?->model_pics)->flatten()->filter()->values();

        return [
            'has_ai' => $listed,
            'photo_url' => $application?->package_image_url ?: $pics->first(),
            'photo_count' => $pics->count(),
            // A photo beside a status: Draft / Submitted / Rejected / Failed, never "it's in".
            'note' => match (true) {
                $application !== null && ! $approved => $application->status,
                $approved && ! $listed && ! $onMachine => self::NOTE_NOT_LISTED,
                default => null,
            },
            'missing' => $onMachine && ! $listed,
        ];
    }

    /** @param  Collection<int, CityboxProduct>  $rows  newest first */
    private function chillerCell(Collection $rows, bool $onMachine): array
    {
        $row = $rows->firstWhere('is_delisted', false) ?? $rows->first();
        $pics = collect((array) $row?->vision_imgs)->filter()->values();

        return [
            'has_ai' => $row !== null,
            'photo_url' => $row?->img_url ?: $pics->first(),
            'photo_count' => $pics->count(),
            'note' => match (true) {
                $row === null => null,
                $row->is_delisted => self::NOTE_DELISTED,
                $row->citybox_status !== null && ! $row->isEnabledInCitybox() => self::NOTE_DISABLED,
                default => null,
            },
            'missing' => $onMachine && $row === null,
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, true> the products among them on a live planogram of that kind of machine
     */
    private function onMachines(array $ids, string $machineType): array
    {
        return DB::table('vend_channels')
            ->join('vends', 'vends.id', '=', 'vend_channels.vend_id')
            ->where('vends.machine_type', $machineType)
            ->where('vend_channels.is_active', true)
            ->whereIn('vend_channels.product_id', $ids)
            ->distinct()
            ->pluck('vend_channels.product_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }
}
