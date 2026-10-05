<?php

namespace App\Services\SmartFreezer\Zijia;

use App\Models\Product;
use App\Models\Vend;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies a product-audit push from Zijia (requested 2026-10-05; payload not final) to mark1's
 * product barcodes, which decide what the AI is asked to recognise (`goodsList`).
 *
 * The product is found by the 商品编码 their portal carries = `products.code`, exactly — never by
 * name. Approved: the barcode is filled in if empty; a different existing barcode is left alone
 * and reported (a person decides). Rejected / withdrawn: a barcode equal to theirs is cleared,
 * because sending a barcode they no longer know makes Zijia reject the whole session.
 *
 * Field names are read tolerantly until Zijia fixes the shape; the raw push is always kept.
 */
class ZijiaSkuApprovalService
{
    public const OUTCOME_SET = 'set';

    public const OUTCOME_CLEARED = 'cleared';

    public const OUTCOME_ALREADY = 'already_set';

    public const OUTCOME_CONFLICT = 'conflict';

    public const OUTCOME_NO_PRODUCT = 'no_product';

    public const OUTCOME_AMBIGUOUS = 'ambiguous_code';

    public const OUTCOME_NO_CODE = 'missing_fields';

    public const OUTCOME_UNKNOWN_STATUS = 'unknown_status';

    public const OUTCOME_NOT_OURS = 'not_ours';

    /** Outcomes a person should look at (the 08:30 health email). */
    public const NEEDS_A_PERSON = [self::OUTCOME_CONFLICT, self::OUTCOME_NO_PRODUCT, self::OUTCOME_AMBIGUOUS,
        self::OUTCOME_NO_CODE, self::OUTCOME_UNKNOWN_STATUS];

    /**
     * @param  array<string, mixed>  $biz  the push's business content
     * @return array{merchant_goods_code: ?string, product_code: ?string, sku_name: ?string, audit_status: ?string, product_id: ?int, outcome: string}
     */
    public function apply(array $biz): array
    {
        $code = self::first($biz, ['merchantGoodsCode', 'goodsCode', 'merchantCode', 'merchantSkuCode', 'commodityCode']);
        $barcode = self::first($biz, ['productCode', 'barcode', 'barCode', 'identificationId']);
        $status = self::first($biz, ['auditStatus', 'approveStatus', 'status', 'auditState']);
        $fields = ['merchant_goods_code' => $code, 'product_code' => $barcode, 'sku_name' => self::first($biz, ['skuName', 'goodsName', 'name']), 'audit_status' => $status];

        $kind = self::statusKind($status);
        if ($kind === null) {
            return $fields + ['product_id' => null, 'outcome' => self::OUTCOME_UNKNOWN_STATUS];
        }
        if ($code === null || $barcode === null) {
            return $fields + ['product_id' => null, 'outcome' => self::OUTCOME_NO_CODE];
        }

        $products = Product::withoutGlobalScopes()->where('code', $code)->get(['id', 'code', 'name', 'barcode']);
        if ($products->count() > 1) {
            // A code shared by two products (prod: U-79, 000): the one on a smart freezer's
            // planogram is the one the AI sees.
            $onFreezers = DB::table('vend_channels')->join('vends', 'vends.id', '=', 'vend_channels.vend_id')
                ->where('vends.machine_type', Vend::MACHINE_TYPE_SMART_FREEZER)
                ->whereIn('vend_channels.product_id', $products->pluck('id'))
                ->distinct()->pluck('vend_channels.product_id');
            $onFreezer = $products->whereIn('id', $onFreezers)->values();
            if ($onFreezer->count() !== 1) {
                return $fields + ['product_id' => null, 'outcome' => self::OUTCOME_AMBIGUOUS];
            }
            $products = $onFreezer;
        }
        if ($products->isEmpty()) {
            return $fields + ['product_id' => null, 'outcome' => self::OUTCOME_NO_PRODUCT];
        }
        if ($products->count() > 1) {
            return $fields + ['product_id' => null, 'outcome' => self::OUTCOME_AMBIGUOUS];
        }
        $product = $products->first();
        $current = $product->barcode === '' ? null : $product->barcode;

        if ($kind === 'approved') {
            if ($current === $barcode) {
                return $fields + ['product_id' => $product->id, 'outcome' => self::OUTCOME_ALREADY];
            }
            if ($current !== null) {
                Log::warning('Zijia approved a barcode different from the product\'s', ['product_id' => $product->id, 'ours' => $current, 'theirs' => $barcode]);

                return $fields + ['product_id' => $product->id, 'outcome' => self::OUTCOME_CONFLICT];
            }
            $this->write($product, $current, $barcode);

            return $fields + ['product_id' => $product->id, 'outcome' => self::OUTCOME_SET];
        }

        // Rejected or withdrawn: only a barcode that is theirs is cleared.
        if ($current !== $barcode) {
            return $fields + ['product_id' => $product->id, 'outcome' => self::OUTCOME_NOT_OURS];
        }
        $this->write($product, $current, null);

        return $fields + ['product_id' => $product->id, 'outcome' => self::OUTCOME_CLEARED];
    }

    private function write(Product $product, ?string $from, ?string $to): void
    {
        Product::withoutGlobalScopes()->whereKey($product->id)->update(['barcode' => $to, 'updated_at' => now()]);
        // A webhook has no web user; record the change by hand so the product's history says who.
        DB::table('user_logs')->insert([
            'user_id' => null,
            'user_name' => 'Zijia approval',
            'event' => 'updated',
            'auditable_type' => Product::class,
            'auditable_id' => $product->id,
            'changes' => json_encode(['barcode' => [$from, $to]]),
            'source' => 'zijia-sku-notify',
            'ip' => request()?->ip(),
            'url' => 'api/smart-freezer/zijia/sku/notify',
            'created_at' => now(),
        ]);
        Log::info('Zijia product audit applied', ['product_id' => $product->id, 'barcode' => [$from, $to]]);
    }

    /** approved | withdrawn (rejected / revoked) | null when the word is unknown. */
    public static function statusKind(?string $status): ?string
    {
        $s = mb_strtolower(trim((string) $status));

        return match (true) {
            in_array($s, ['approved', 'approve', 'pass', 'passed', 'success', '通过', '已通过', '审核通过'], true) => 'approved',
            in_array($s, ['rejected', 'reject', 'refused', 'revoked', 'revoke', 'cancelled', 'canceled', 'withdrawn',
                '驳回', '已驳回', '撤销', '已撤销', '审核驳回'], true) => 'withdrawn',
            default => null,
        };
    }

    /** @param  list<string>  $keys */
    private static function first(array $biz, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($biz[$key]) && is_scalar($biz[$key]) && trim((string) $biz[$key]) !== '') {
                return trim((string) $biz[$key]);
            }
        }

        return null;
    }
}
