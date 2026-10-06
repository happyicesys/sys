<?php

namespace App\Services\SmartFreezer\Zijia;

use App\Models\Product;
use App\Models\User;
use App\Models\ZijiaSkuApplication;
use App\Models\ZijiaSkuApplicationEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Smart Freezer AI Training (Brian, 2026-10-06): a product is submitted to Zijia's algorithm for
 * modelling from Product → Edit (算法服务接口文档 §5 `sys.sku.sync.put`), and their approval
 * callback (§7) comes back to /api/smart-freezer/zijia/sku/notify carrying our application number.
 *
 * The only writer of `zijia_sku_applications` and their event log. Every exchange is an event:
 * the draft saved, photos added or removed, the exact envelope sent, their answer, the callback,
 * and what it did to the product's barcode. The barcode itself is only ever set on approval
 * (ZijiaSkuApprovalService) — sending an unapproved barcode makes Zijia reject whole sessions.
 */
class ZijiaSkuApplicationService
{
    /** §5.3 商品分类 */
    public const CATEGORIES = [
        1 => '饮品 Drinks', 2 => '药品 Medicine', 3 => '保健品 Health supplements', 4 => '零食 Snacks',
        5 => '香烟 Cigarettes', 6 => '调味品 Condiments', 7 => '日用品 Daily goods', 8 => '米面 Rice & noodles',
        9 => '食用油 Cooking oil', 10 => '高桶装 Tall tub', 11 => '单根装 Single stick', 12 => '整板装 Full board',
        99 => '其他 Other',
    ];

    /** §5.3 包装类型 */
    public const PACKAGE_TYPES = [
        1 => '瓶装 Bottle', 2 => '罐装 Can', 3 => '袋装 Bag', 4 => '盒装 Box', 5 => '桶装 Tub', 6 => '箱装/塑包 Case / shrink pack',
        7 => '扁袋装 Flat bag', 8 => '大袋装 Large bag', 9 => '袋盒混装 Bag + box', 10 => '高桶装 Tall tub',
        11 => '单根装 Single stick', 12 => '整板装 Full board', 13 => '玻璃瓶装 Glass bottle', 14 => '米面袋装 Rice/flour bag',
        15 => '塑料瓶装 Plastic bottle', 16 => '塑料罐装 Plastic jar', 17 => '玻璃罐装 Glass jar', 18 => '塑料桶装 Plastic tub',
        99 => '其他 Other',
    ];

    /** Photo slots of `skuModelPic`; `high` is required (≥ 1, Zijia suggests 5–10). */
    public const ANGLES = ['high', 'horizontal', 'low'];

    private const UNIT_ZH = ['g' => '克', 'kg' => '千克', 'ml' => '毫升', 'L' => '升', 'pcs' => '个'];

    public function __construct(private readonly ZijiaAlgorithmClient $client) {}

    /** Everything the Product → Edit section needs. */
    public function pageData(Product $product): array
    {
        $current = $this->current($product);

        return [
            'configured' => $this->client->isConfigured(),
            // The product's barcode now — what the AI is asked to look for (set on approval).
            'product_barcode' => $product->barcode ?: null,
            'application' => $current ? $this->present($current) : null,
            'history' => ZijiaSkuApplication::query()->where('product_id', $product->id)->orderByDesc('id')
                ->get(['id', 'application_no', 'status', 'source', 'product_code', 'submitted_at', 'decided_at', 'decision_msg'])
                ->map(fn ($a) => ['id' => $a->id, 'application_no' => $a->application_no, 'status' => $a->status, 'source' => $a->source, 'product_code' => $a->product_code,
                    'submitted_at' => $a->submitted_at?->format('Y-m-d H:i:s'), 'decided_at' => $a->decided_at?->format('Y-m-d H:i:s'), 'decision_msg' => $a->decision_msg])
                ->all(),
            'defaults' => $this->defaults($product),
            'categoryOptions' => self::options(self::CATEGORIES),
            'packageTypeOptions' => self::options(self::PACKAGE_TYPES),
        ];
    }

    /**
     * The application the section shows: one being worked on (draft) or waiting for Zijia comes
     * first — a vms4 mirror refreshed later must never hide it — else the latest.
     */
    public function current(Product $product): ?ZijiaSkuApplication
    {
        $query = fn () => ZijiaSkuApplication::query()->with('events', 'submitter:id,name')->where('product_id', $product->id)->latest('id');

        return $query()->whereIn('status', [ZijiaSkuApplication::STATUS_DRAFT, ZijiaSkuApplication::STATUS_SUBMITTED])->first()
            ?? $query()->first();
    }

    /** The values a new draft starts from: what mark1 already knows about the product. */
    public function defaults(Product $product): array
    {
        $spec = null;
        if ((float) $product->measurement_value > 0 && $product->measurement_unit) {
            $value = rtrim(rtrim(number_format((float) $product->measurement_value, 2, '.', ''), '0'), '.');
            $spec = $value.(self::UNIT_ZH[$product->measurement_unit] ?? $product->measurement_unit);
        }

        return [
            'sku_name' => $product->name,
            'brand_name' => '其他/其他',
            'spec' => $spec,
            'category' => 4,
            'package_type' => null,
            'product_code' => $product->barcode ?: null,
            'package_image_url' => $product->thumbnail?->full_url,
            'model_pics' => ['high' => [], 'horizontal' => [], 'low' => []],
        ];
    }

    /**
     * Saves the draft: fields, new photos, removed photos. A submitted / approved application is
     * not touched — $startNew begins a fresh draft from it (a new pack, a re-application).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, list<UploadedFile>>  $photos  angle => files
     * @param  list<string>  $removeUrls
     */
    public function saveDraft(Product $product, array $data, array $photos, ?UploadedFile $packageImage, array $removeUrls, ?User $user, bool $startNew = false): ZijiaSkuApplication
    {
        return DB::transaction(function () use ($product, $data, $photos, $packageImage, $removeUrls, $user, $startNew) {
            $current = $this->current($product);
            if ($current === null || ! $current->isEditable()) {
                if ($current !== null && $current->status === ZijiaSkuApplication::STATUS_SUBMITTED && ! $startNew) {
                    throw ValidationException::withMessages(['application' => 'This product is waiting for Zijia\'s review; it cannot be changed until they answer.']);
                }
                $seed = $current ? $current->only(['sku_name', 'brand_name', 'spec', 'category', 'package_type', 'product_code', 'package_image_url', 'model_pics'])
                    : $this->defaults($product);
                $current = ZijiaSkuApplication::query()->create($seed + [
                    'product_id' => $product->id,
                    'application_no' => $this->newApplicationNo($product),
                    'status' => ZijiaSkuApplication::STATUS_DRAFT,
                ]);
                $this->event($current, 'draft.created', $user, ['from' => $seed === $this->defaults($product) ? 'product' : 'previous application']);
            }

            $fields = array_intersect_key($data, array_flip(['sku_name', 'brand_name', 'spec', 'category', 'package_type', 'product_code']));
            $fields = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $fields);
            $pics = array_merge(['high' => [], 'horizontal' => [], 'low' => []], (array) $current->model_pics);

            $removed = [];
            foreach (self::ANGLES as $angle) {
                $keep = array_values(array_diff($pics[$angle], $removeUrls));
                $removed = array_merge($removed, array_values(array_diff($pics[$angle], $keep)));
                $pics[$angle] = $keep;
            }
            $added = [];
            foreach (self::ANGLES as $angle) {
                foreach ($photos[$angle] ?? [] as $file) {
                    $url = Storage::url($file->storePublicly("sys/zijia-sku/{$product->id}"));
                    $pics[$angle][] = $url;
                    $added[] = ['angle' => $angle, 'url' => $url, 'name' => $file->getClientOriginalName()];
                }
            }
            if ($packageImage) {
                $fields['package_image_url'] = Storage::url($packageImage->storePublicly("sys/zijia-sku/{$product->id}"));
                $added[] = ['angle' => 'package', 'url' => $fields['package_image_url'], 'name' => $packageImage->getClientOriginalName()];
            }

            $current->fill($fields + ['model_pics' => $pics]);
            $changed = array_keys($current->getDirty());
            $current->save();

            if ($changed !== [] || $added !== [] || $removed !== []) {
                $this->event($current, 'draft.saved', $user, array_filter([
                    'changed' => array_values(array_diff($changed, ['model_pics'])) ?: null,
                    'photos_added' => $added ?: null,
                    'photos_removed' => $removed ?: null,
                ]));
            }

            return $current->fresh(['events', 'submitter:id,name']);
        });
    }

    /**
     * What still stops this application from being submitted, as messages for the person.
     *
     * @return array<string, string> field => message
     */
    public function missing(ZijiaSkuApplication $app): array
    {
        $pics = (array) $app->model_pics;
        $missing = [];
        if (blank($app->sku_name)) {
            $missing['sku_name'] = 'Product name for the AI is required.';
        }
        if (blank($app->brand_name)) {
            $missing['brand_name'] = 'Brand is required (use 其他/其他 when there is none).';
        }
        if (! array_key_exists((int) $app->category, self::CATEGORIES)) {
            $missing['category'] = 'Pick a category.';
        }
        if (! array_key_exists((int) $app->package_type, self::PACKAGE_TYPES)) {
            $missing['package_type'] = 'Pick a package type.';
        }
        if (blank($app->product_code)) {
            $missing['product_code'] = 'Barcode is required: the code printed on the pack (EAN-13), or a unique code of ours if it has none.';
        } elseif (! preg_match('/^[A-Za-z0-9-]{4,64}$/', (string) $app->product_code)) {
            $missing['product_code'] = 'Barcode may only contain letters, digits and dashes.';
        }
        if (blank($app->package_image_url) || ! str_starts_with((string) $app->package_image_url, 'https://')) {
            $missing['package_image_url'] = 'A package photo (public https link) is required — upload one here or a product photo above.';
        }
        if (count($pics['high'] ?? []) < 1) {
            $missing['model_pics.high'] = 'At least one top-view model photo is required (Zijia suggests 5–10).';
        }

        return $missing;
    }

    /** Sends the draft to Zijia (§5). Every outcome is an event; the exact envelope is kept. */
    public function submit(ZijiaSkuApplication $app, ?User $user): ZijiaSkuApplication
    {
        if (! $app->isEditable()) {
            throw ValidationException::withMessages(['application' => "Only a draft can be submitted (this one is {$app->status})."]);
        }
        if (($missing = $this->missing($app)) !== []) {
            throw ValidationException::withMessages($missing);
        }
        if (! $this->client->isConfigured()) {
            throw ValidationException::withMessages(['application' => 'Zijia\'s algorithm service is not configured on this server.']);
        }

        $pics = array_merge(['high' => [], 'horizontal' => [], 'low' => []], (array) $app->model_pics);
        $sku = array_filter([
            'applicationNo' => $app->application_no,
            'skuName' => $app->sku_name,
            'brandName' => $app->brand_name,
            'spec' => $app->spec,
            'category' => (int) $app->category,
            'packageType' => (int) $app->package_type,
            'packageImageUrl' => $app->package_image_url,
            'callbackUrl' => $this->callbackUrl(),
            'productCode' => $app->product_code,
            'skuModelPic' => ['horizontal' => array_values($pics['horizontal']), 'low' => array_values($pics['low']), 'high' => array_values($pics['high'])],
            'attach' => mb_substr((string) $app->product?->code, 0, 50),
        ], fn ($v) => $v !== null && $v !== '');

        $app->update(['attach' => $sku['attach'] ?? null, 'callback_url' => $sku['callbackUrl'], 'submitted_by' => $user?->id, 'submitted_at' => now()]);
        [$envelope, $answer] = $this->client->applySku($sku);
        $this->event($app, 'submit.sent', $user, ['method' => ZijiaAlgorithmClient::METHOD_SKU_APPLY, 'envelope' => $envelope, 'sku' => $sku]);

        if ($answer->ok()) {
            $app->update([
                'status' => ZijiaSkuApplication::STATUS_SUBMITTED,
                'zijia_sku_id' => isset($answer->raw['data']['skuId']) ? (string) $answer->raw['data']['skuId'] : null,
                'last_error' => null,
            ]);
            $this->event($app, 'submit.accepted', null, ['code' => $answer->code, 'msg' => $answer->message, 'answer' => $answer->raw]);
        } else {
            $app->update(['status' => ZijiaSkuApplication::STATUS_FAILED, 'last_error' => mb_substr("{$answer->code}: {$answer->message}", 0, 1000)]);
            $this->event($app, 'submit.refused', null, ['code' => $answer->code, 'msg' => $answer->message, 'answer' => $answer->raw], 'error');
        }

        return $app->fresh(['events', 'submitter:id,name']);
    }

    /**
     * Their approval callback for one of our applications (called from ZijiaSkuApprovalService
     * with what it did to the product's barcode).
     *
     * @param  array<string, mixed>  $payload  the callback as received
     */
    public function recordDecision(ZijiaSkuApplication $app, array $payload, bool $approved, string $outcome): void
    {
        $sku = (array) ($payload['sku'] ?? []);
        $app->update([
            'status' => $approved ? ZijiaSkuApplication::STATUS_APPROVED : ZijiaSkuApplication::STATUS_REJECTED,
            'decision_msg' => isset($payload['msg']) && is_scalar($payload['msg']) ? mb_substr((string) $payload['msg'], 0, 2000) : null,
            'sys_sku_id' => isset($sku['sysSkuId']) ? (string) $sku['sysSkuId'] : $app->sys_sku_id,
            'decided_at' => now(),
        ]);
        $this->event($app, $approved ? 'callback.approved' : 'callback.rejected', null, ['payload' => $payload], $approved ? 'info' : 'warning');
        $this->event($app, 'barcode.'.$outcome, null, ['product_code' => $sku['productCode'] ?? null], in_array($outcome, ['set', 'already_set', 'cleared', 'not_ours'], true) ? 'info' : 'warning');
    }

    public function event(ZijiaSkuApplication $app, string $event, ?User $user, array $detail = [], string $level = 'info'): void
    {
        ZijiaSkuApplicationEvent::query()->create([
            'zijia_sku_application_id' => $app->id,
            'event' => $event,
            'level' => $level,
            'detail' => $detail ?: null,
            'user_name' => $user?->name ?? ($event === 'submit.sent' ? null : 'Zijia'),
            'created_at' => now(),
        ]);
    }

    public function callbackUrl(): string
    {
        $token = (string) config('smart_freezer.zijia.sku_callback_token');

        return url('/api/smart-freezer/zijia/sku/notify').($token !== '' ? '?token='.urlencode($token) : '');
    }

    /** Digits only, like their example; unique per application (a resubmission is a new number). */
    private function newApplicationNo(Product $product): string
    {
        $suffix = str_pad((string) ($product->id % 1000), 3, '0', STR_PAD_LEFT);
        $ms = now()->getTimestampMs();
        while (ZijiaSkuApplication::query()->where('application_no', $ms.$suffix)->exists()) {
            $ms++;
        }

        return $ms.$suffix;
    }

    private function present(ZijiaSkuApplication $app): array
    {
        return [
            'id' => $app->id,
            'application_no' => $app->application_no,
            'status' => $app->status,
            'source' => $app->source,
            'library_updated_at' => $app->library_updated_at?->format('Y-m-d H:i:s'),
            'editable' => $app->isEditable(),
            'sku_name' => $app->sku_name,
            'brand_name' => $app->brand_name,
            'spec' => $app->spec,
            'category' => $app->category,
            'package_type' => $app->package_type,
            'product_code' => $app->product_code,
            'package_image_url' => $app->package_image_url,
            'model_pics' => array_merge(['high' => [], 'horizontal' => [], 'low' => []], (array) $app->model_pics),
            'attach' => $app->attach,
            'zijia_sku_id' => $app->zijia_sku_id,
            'sys_sku_id' => $app->sys_sku_id,
            'decision_msg' => $app->decision_msg,
            'last_error' => $app->last_error,
            'submitted_by' => $app->submitter?->name,
            'submitted_at' => $app->submitted_at?->format('Y-m-d H:i:s'),
            'decided_at' => $app->decided_at?->format('Y-m-d H:i:s'),
            'missing' => $app->isEditable() ? $this->missing($app) : [],
            'events' => $app->events->map(fn (ZijiaSkuApplicationEvent $e) => [
                'id' => $e->id, 'event' => $e->event, 'level' => $e->level, 'user_name' => $e->user_name,
                'at' => $e->created_at?->format('Y-m-d H:i:s'), 'detail' => $e->detail,
            ])->all(),
        ];
    }

    /** @return list<array{id: int, name: string}> */
    private static function options(array $map): array
    {
        return array_map(fn ($id, $name) => ['id' => $id, 'name' => $name], array_keys($map), $map);
    }
}
