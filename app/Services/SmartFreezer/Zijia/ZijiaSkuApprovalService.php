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
    public function __construct(private readonly ZijiaAlgorithmClient $client) {}

    public const OUTCOME_SET = 'set';

    public const OUTCOME_CLEARED = 'cleared';

    public const OUTCOME_ALREADY = 'already_set';

    public const OUTCOME_CONFLICT = 'conflict';

    public const OUTCOME_NO_PRODUCT = 'no_product';

    public const OUTCOME_AMBIGUOUS = 'ambiguous_code';

    public const OUTCOME_NO_CODE = 'missing_fields';

    public const OUTCOME_UNKNOWN_STATUS = 'unknown_status';

    public const OUTCOME_NOT_OURS = 'not_ours';

    public const OUTCOME_NOT_IN_LIBRARY = 'not_in_library';

    public const OUTCOME_UNREACHABLE = 'library_unreachable';

    /** Outcomes a person should look at (the 08:30 health email). */
    public const NEEDS_A_PERSON = [self::OUTCOME_CONFLICT, self::OUTCOME_NO_PRODUCT, self::OUTCOME_AMBIGUOUS,
        self::OUTCOME_NO_CODE, self::OUTCOME_UNKNOWN_STATUS, self::OUTCOME_NOT_IN_LIBRARY, self::OUTCOME_UNREACHABLE];

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

        [$product, $miss] = $this->byCode($code);
        if ($product === null) {
            return $fields + ['product_id' => null, 'outcome' => $miss];
        }

        return $fields + $this->applyTo($product, $kind, $barcode);
    }

    /**
     * Zijia's product approval callback as documented (算法服务接口文档 §7): plain unsigned JSON
     * `{pass, msg, sku: {productCode, skuName, sysSkuId, applicationNo, attach, …}}`.
     *
     * Unsigned, so an approval is applied only once Zijia's own library (§8, public) confirms the
     * barcode exists — a forged push can at worst fill a real barcode. The product is found by
     * `sku.attach` / `sku.applicationNo` = products.code (ours when we apply through §5), else by
     * a unique exact name among smart-freezer planogram products (portal applications).
     *
     * @param  array<string, mixed>  $payload
     * @return array{merchant_goods_code: ?string, product_code: ?string, sku_name: ?string, audit_status: ?string, product_id: ?int, outcome: string}
     */
    public function applyApprovalCallback(array $payload): array
    {
        $sku = is_array($payload['sku'] ?? null) ? $payload['sku'] : [];
        $code = self::first($sku, ['attach', 'applicationNo']);
        $barcode = self::first($sku, ['productCode', 'identificationId']);
        $name = self::first($sku, ['skuName']);
        $pass = $payload['pass'] ?? null;
        $kind = match (true) {
            $pass === true, $pass === 'true', $pass === 1, $pass === '1' => 'approved',
            $pass === false, $pass === 'false', $pass === 0, $pass === '0' => 'withdrawn',
            default => null,
        };
        $fields = ['merchant_goods_code' => $code, 'product_code' => $barcode, 'sku_name' => $name,
            'audit_status' => $kind === null ? null : ($kind === 'approved' ? 'pass' : 'reject')];

        if ($kind === null) {
            return $fields + ['product_id' => null, 'outcome' => self::OUTCOME_UNKNOWN_STATUS];
        }
        if ($barcode === null) {
            return $fields + ['product_id' => null, 'outcome' => self::OUTCOME_NO_CODE];
        }
        if ($kind === 'approved') {
            $page = $this->client->querySkus(1, 20, null, $barcode);
            if ($page === null) {
                return $fields + ['product_id' => null, 'outcome' => self::OUTCOME_UNREACHABLE];
            }
            if (! collect($page['list'])->contains(fn ($s) => (string) ($s['productCode'] ?? '') === $barcode)) {
                return $fields + ['product_id' => null, 'outcome' => self::OUTCOME_NOT_IN_LIBRARY];
            }
        }

        [$product, $miss] = $code !== null ? $this->byCode($code) : [null, self::OUTCOME_NO_PRODUCT];
        if ($product === null && $name !== null) {
            [$product, $miss] = $this->byName($name);
        }
        if ($product === null) {
            return $fields + ['product_id' => null, 'outcome' => $miss];
        }

        return $fields + $this->applyTo($product, $kind, $barcode);
    }

    /** @return array{product_id: int, outcome: string} */
    private function applyTo(Product $product, string $kind, string $barcode): array
    {
        $current = $product->barcode === '' ? null : $product->barcode;

        if ($kind === 'approved') {
            if ($current === $barcode) {
                return ['product_id' => $product->id, 'outcome' => self::OUTCOME_ALREADY];
            }
            if ($current !== null) {
                Log::warning('Zijia approved a barcode different from the product\'s', ['product_id' => $product->id, 'ours' => $current, 'theirs' => $barcode]);

                return ['product_id' => $product->id, 'outcome' => self::OUTCOME_CONFLICT];
            }
            $this->write($product, $current, $barcode);

            return ['product_id' => $product->id, 'outcome' => self::OUTCOME_SET];
        }

        // Rejected or withdrawn: only a barcode that is theirs is cleared.
        if ($current !== $barcode) {
            return ['product_id' => $product->id, 'outcome' => self::OUTCOME_NOT_OURS];
        }
        $this->write($product, $current, null);

        return ['product_id' => $product->id, 'outcome' => self::OUTCOME_CLEARED];
    }

    /**
     * The one product with this code; a code shared by several resolves only to the one on a smart
     * freezer's planogram (prod: U-79, 000).
     *
     * @return array{0: ?Product, 1: string} the product, or null and why
     */
    private function byCode(string $code): array
    {
        $products = Product::withoutGlobalScopes()->where('code', $code)->get(['id', 'code', 'name', 'barcode']);
        if ($products->count() > 1) {
            $onFreezer = $products->whereIn('id', $this->freezerProductIds())->values();
            if ($onFreezer->count() !== 1) {
                return [null, self::OUTCOME_AMBIGUOUS];
            }
            $products = $onFreezer;
        }

        return $products->isEmpty() ? [null, self::OUTCOME_NO_PRODUCT] : [$products->first(), ''];
    }

    /**
     * The one smart-freezer planogram product with exactly this name (case and punctuation aside),
     * the same rule as the 3-minute crawl.
     *
     * @return array{0: ?Product, 1: string}
     */
    private function byName(string $name): array
    {
        $want = ZijiaBarcodeSync::normalise($name);
        $matches = Product::withoutGlobalScopes()->whereIn('id', $this->freezerProductIds())
            ->get(['id', 'code', 'name', 'barcode'])
            ->filter(fn (Product $p) => ZijiaBarcodeSync::normalise((string) $p->name) === $want)
            ->values();

        return match ($matches->count()) {
            1 => [$matches->first(), ''],
            0 => [null, self::OUTCOME_NO_PRODUCT],
            default => [null, self::OUTCOME_AMBIGUOUS],
        };
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function freezerProductIds()
    {
        return DB::table('vend_channels')->join('vends', 'vends.id', '=', 'vend_channels.vend_id')
            ->where('vends.machine_type', Vend::MACHINE_TYPE_SMART_FREEZER)
            ->whereNotNull('vend_channels.product_id')
            ->distinct()->pluck('vend_channels.product_id');
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
