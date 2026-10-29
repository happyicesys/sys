<?php

namespace Tests\Support\Grab;

use App\Jobs\SyncDeliveryProductMappingVendChannels;
use App\Models\Attachment;
use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingItem;
use App\Models\DeliveryProductMappingVend;
use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Vend;
use App\Models\VendChannel;
use Illuminate\Support\Facades\DB;

/**
 * One GrabMart store the way prod has them (2026-10): an operator with a Mart
 * credential, a Grab template whose items are machine channels, and one machine
 * bound to a Grab merchant through a ref number.
 */
trait BuildsGrabStore
{
    protected DeliveryPlatformOperator $grabOperator;

    protected DeliveryProductMapping $grabMapping;

    /** @var array<string, DeliveryProductMappingItem> keyed by product code */
    protected array $grabItems = [];

    protected function buildGrabOperator(string $type = 'production'): DeliveryPlatformOperator
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Singapore', 'code' => 'SG', 'currency_name' => 'SGD', 'currency_exponent' => 2,
            'currency_symbol' => 'S$', 'phone_code' => 65,
        ]);

        $operator = Operator::forceCreate([
            'name' => 'HIPL', 'code' => 'HIPL', 'country_id' => $countryId, 'created_by' => 1, 'gst_vat_rate' => 9,
        ]);

        $paymentMethod = PaymentMethod::forceCreate(['name' => 'Grab Mart', 'code' => 209]);

        $platform = DeliveryPlatform::forceCreate([
            'country_id' => $countryId, 'name' => 'Grab', 'slug' => 'grab', 'payment_method_id' => $paymentMethod->id,
        ]);

        $this->grabOperator = DeliveryPlatformOperator::forceCreate([
            'delivery_platform_id' => $platform->id,
            'operator_id' => $operator->id,
            'type' => $type,
        ]);

        $this->grabOperator->externalOauthToken()->create([
            'client_id' => 'test-client', 'client_secret' => 'test-secret',
            'access_token' => 'test-token', 'scopes' => 'mart.partner_api',
        ]);

        $this->grabMapping = DeliveryProductMapping::forceCreate([
            'delivery_platform_operator_id' => $this->grabOperator->id,
            'operator_id' => $operator->id,
            'product_mapping_id' => 1,
            'name' => 'UEB test template',
            'category_json' => ['id' => 'SGITEDP20200707065055013739', 'name' => 'Ice Cream & Dessert'],
        ]);

        return $this->grabOperator;
    }

    /** @param  int  $cents  the Grab price */
    protected function grabItem(string $productCode, int $channelCode, int $cents, ?string $photo = 'https://example.test/p.jpg'): DeliveryProductMappingItem
    {
        $product = Product::forceCreate(['name' => "Product {$productCode}", 'code' => $productCode]);

        if ($photo) {
            Attachment::forceCreate([
                'local_url' => $photo, 'full_url' => $photo,
                'modelable_type' => Product::class, 'modelable_id' => $product->id, 'type' => 1,
            ]);
        }

        $item = new DeliveryProductMappingItem;
        $item->forceFill([
            'delivery_product_mapping_id' => $this->grabMapping->id,
            'product_mapping_id' => 1,
            'product_id' => $product->id,
            'channel_code' => $channelCode,
            'sub_category_json' => ['id' => 'SGITEDP20200707065112014691', 'name' => 'Ice Cream Sticks'],
        ]);
        $item->amount = $cents / 100; // the accessor takes dollars, stores cents
        $item->save();

        return $this->grabItems[$productCode] = $item;
    }

    /**
     * A machine with one channel per item (qty / capacity per channel code), bound to
     * $merchantId; the bridge rows are built by the real sync job.
     *
     * @param  array<int, array{qty:int, capacity:int}>  $channels  keyed by channel code
     */
    protected function bindGrabStore(int $vendCode, string $merchantId, array $channels, bool $isActive = true): DeliveryProductMappingVend
    {
        $vend = Vend::query()->withoutGlobalScopes()->where('code', $vendCode)->first()
            ?? Vend::forceCreate(['code' => $vendCode, 'operator_id' => $this->grabMapping->operator_id]);

        foreach ($channels as $code => $channel) {
            VendChannel::forceCreate([
                'vend_id' => $vend->id, 'code' => $code, 'qty' => $channel['qty'], 'capacity' => $channel['capacity'],
                'is_active' => true, 'error_rate_json' => '[]',
            ]);
        }

        $refNumber = DeliveryPlatformRefNumber::query()->where('ref_number', $merchantId)->first()
            ?? DeliveryPlatformRefNumber::forceCreate([
                'delivery_platform_id' => $this->grabOperator->delivery_platform_id,
                'operator_id' => $this->grabMapping->operator_id,
                'ref_number' => $merchantId,
            ]);

        $mappingVend = DeliveryProductMappingVend::forceCreate([
            'delivery_product_mapping_id' => $this->grabMapping->id,
            'vend_id' => $vend->id,
            'vend_code' => $vendCode,
            'platform_ref_id' => $merchantId,
            'delivery_platform_ref_number_id' => $refNumber->id,
            'is_active' => $isActive,
        ]);

        (new SyncDeliveryProductMappingVendChannels($mappingVend))->handle();

        return $mappingVend->fresh();
    }

    /** The prod order fixture with this store's ids filled in. */
    protected function grabOrderPayload(string $merchantId, int $partnerMerchantId, array $itemIds, string $orderId = '001569933881-TEST'): array
    {
        $json = file_get_contents(__DIR__.'/fixtures/mart_order_submit.json');
        $json = strtr($json, [
            '{{ITEM_A}}' => (string) $itemIds[0],
            '{{ITEM_B}}' => (string) ($itemIds[1] ?? $itemIds[0]),
            '{{ORDER_ID}}' => $orderId,
            '{{MERCHANT_ID}}' => $merchantId,
            '{{PARTNER_MERCHANT_ID}}' => (string) $partnerMerchantId,
        ]);

        return json_decode($json, true);
    }
}
