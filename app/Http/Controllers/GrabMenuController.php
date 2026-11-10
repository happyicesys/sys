<?php

namespace App\Http\Controllers;

use App\Http\Requests\Grab\CompanyPriceRuleRequest;
use App\Http\Requests\Grab\GrabPriceRuleRequest;
use App\Http\Requests\Grab\StorePriceRuleRequest;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryProductMapping;
use App\Models\GrabMenuSetting;
use App\Models\Operator;
use App\Models\SellingPrice;
use App\Services\DeliveryPlatformService;
use App\Services\Grab\Menu\GrabMenuOverview;
use App\Services\Grab\Menu\GrabMenuService;
use App\Services\Grab\Menu\GrabPriceRule;
use App\Services\Grab\Menu\GrabPriceRuleResolver;
use App\Services\Grab\Menu\GrabStorePreview;
use App\Services\Grab\Menu\GrabStorePriceRuleService;
use App\Services\GrabStoreService;
use App\Support\Grab\GrabStoreVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Grab Menu & Prices (Brian, 2026-11-01), one operator at a time: its company price rule, each
 * store's own rule where it has one (2026-11-10), each product's Grab sub-category (and price,
 * in custom mode), and which of its stores list their machine's slots. HIPL staff see and set
 * every operator; others only their own.
 */
class GrabMenuController extends Controller
{
    public function __construct(private GrabMenuService $menus) {}

    public function index(Request $request)
    {
        $operators = $this->operators();
        $operatorId = (int) ($request->operator_id ?: $operators->first()['id'] ?? 0);
        abort_unless(! $operatorId || $operators->contains('id', $operatorId), 403);

        $overview = $operatorId ? new GrabMenuOverview($operatorId) : null;
        $setting = $overview?->setting();

        return Inertia::render('GrabMenu/Index', [
            'operators' => $operators->values(),
            'operatorId' => $operatorId ?: null,
            'setting' => $setting ? [
                'saved' => $setting->exists,
                'pricing_mode' => $setting->pricing_mode->value,
                'rp_type' => $setting->rp_type,
                'multiplier' => round($setting->multiplier_bp / 10000, 4),
                'round_up_cents' => $setting->round_up_cents,
                'category' => $setting->category_json,
                'updated_at' => $setting->updated_at?->toIso8601String(),
                'updated_by' => $setting->updater?->name,
            ] : null,
            'products' => $overview?->products() ?? [],
            'stores' => $overview?->stores() ?? [],
            'subCategories' => $overview?->knownSubCategories() ?? [],
            'rpTypes' => collect(SellingPrice::TYPE_MAPPINGS)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'roundUpSteps' => GrabMenuSetting::ROUND_UP_STEPS,
            'can' => ['update' => $request->user()->can('update delivery-platform-product-mappings')],
        ]);
    }

    public function updateSetting(CompanyPriceRuleRequest $request, int $operatorId)
    {
        $this->allow($operatorId);

        $this->menus->saveSetting($operatorId, [
            ...$request->ruleValues(),
            'category_json' => $request->category ? ['id' => $request->input('category.id'), 'name' => $request->input('category.name') ?: $request->input('category.id')] : GrabMenuSetting::forOperator($operatorId)->category_json,
        ], $request->user()->id);

        return back()->with('success', __('Company price rule saved. Stores that follow it update on Grab within a few minutes.'));
    }

    /**
     * One or more stores' price rule: their own (with a note why), or back to the company rule. Stores still on
     * the old Grab menu keep it until they switch; the rule is ready for them.
     */
    public function updateStorePriceRule(StorePriceRuleRequest $request, int $operatorId, GrabStorePriceRuleService $storeRules)
    {
        $this->allow($operatorId);
        $refs = $this->storesOf($operatorId, $request->ref_ids);

        $useCompany = $request->boolean('use_company_rule');
        $values = $useCompany ? null : $request->ruleValues();
        DB::transaction(fn () => $refs->each(fn ($ref) => $useCompany
            ? $storeRules->useCompanyRule($ref, $request->user()->id)
            : $storeRules->useOwnRule($ref, $values, $request->note, $request->user()->id)));

        return back()->with('success', $useCompany
            ? trans_choice(':count store now follows the company rule.|:count stores now follow the company rule.', $refs->count(), ['count' => $refs->count()])
            : trans_choice(':count store now has its own price rule.|:count stores now have their own price rule.', $refs->count(), ['count' => $refs->count()]));
    }

    // One product's typed Grab price at a store whose own rule is custom (dollars in, integer cents stored; empty removes it).
    public function updateStoreCustomPrice(Request $request, int $refId, int $productId, GrabStorePriceRuleService $storeRules)
    {
        $ref = DeliveryPlatformRefNumber::query()->findOrFail($refId);
        $this->allow((int) $ref->operator_id);
        $request->validate(['price' => ['present', 'nullable', 'numeric', 'min:0.01', 'max:10000']]);
        abort_unless(DB::table('products')->where('id', $productId)->exists(), 404);

        $storeRules->saveCustomPrice($ref, $productId, $request->filled('price') ? (int) round(((float) $request->price) * 100) : null, $request->user()->id);

        return response()->json(['ok' => true]);
    }

    // One product: its Grab sub-category and / or custom price (dollars in, integer cents stored).
    public function updateProduct(Request $request, int $operatorId, int $productId)
    {
        $this->allow($operatorId);
        $request->validate([
            'sub_category' => ['sometimes', 'nullable', 'array'],
            'sub_category.id' => ['required_with:sub_category', 'string', 'max:64'],
            'sub_category.name' => ['nullable', 'string', 'max:255'],
            'custom_price' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:10000'],
        ]);

        $data = [];
        if ($request->has('sub_category')) {
            $data['sub_category_json'] = $request->sub_category ? ['id' => $request->input('sub_category.id'), 'name' => $request->input('sub_category.name') ?: $request->input('sub_category.id')] : null;
        }
        if ($request->has('custom_price')) {
            $data['custom_amount'] = $request->filled('custom_price') ? (int) round(((float) $request->custom_price) * 100) : null;
        }
        $this->menus->saveProduct($operatorId, $productId, $data, $request->user()->id);

        return back()->with('success', __('Saved.'));
    }

    // One sub-category for many products at once.
    public function updateSubCategories(Request $request, int $operatorId)
    {
        $this->allow($operatorId);
        $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer'],
            'sub_category.id' => ['required', 'string', 'max:64'],
            'sub_category.name' => ['nullable', 'string', 'max:255'],
        ]);

        $count = $this->menus->saveSubCategory($operatorId, $request->product_ids, [
            'id' => $request->input('sub_category.id'),
            'name' => $request->input('sub_category.name') ?: $request->input('sub_category.id'),
        ], $request->user()->id);

        return back()->with('success', trans_choice('Sub-category set on :count product.|Sub-category set on :count products.', $count, ['count' => $count]));
    }

    public function fillFromTemplates(Request $request, int $operatorId)
    {
        $this->allow($operatorId);
        $result = $this->menus->fillFromTemplates($operatorId, $request->user()->id);

        return back()->with('success', __('Filled from the old Grab menus: :subs sub-categories, :prices custom prices:category.', [
            'subs' => $result['sub_categories'],
            'prices' => $result['prices'],
            'category' => $result['category'] ? __(', and the Grab category') : '',
        ]));
    }

    // Grab's own category list (a GET on Grab; nothing changes there).
    public function grabCategories(int $operatorId, GrabStoreService $stores, DeliveryPlatformService $platform)
    {
        $this->allow($operatorId, false);
        $account = $stores->grabAccountFor($operatorId);
        abort_unless($account?->externalOauthToken?->access_token, 404, __('This operator has no Grab account in ConnectVend.'));

        $categories = collect(($platform->getCategories($account) ?? [])['categories'] ?? []);

        return response()->json($categories->map(fn ($category) => [
            'id' => (string) ($category['id'] ?? ''),
            'name' => (string) ($category['name'] ?? ''),
            'sub_categories' => collect($category['subCategories'] ?? [])->map(fn ($sub) => ['id' => (string) $sub['id'], 'name' => (string) ($sub['name'] ?? $sub['id'])])->values(),
        ])->filter(fn ($category) => $category['id'] !== '')->values());
    }

    /**
     * One store's panel: the rule it follows and what its Grab customers would see change. With a rule in the
     * query (or use_company_rule), that draft is tried instead — nothing is saved.
     */
    public function preview(Request $request, int $refId, GrabStorePreview $preview)
    {
        $ref = DeliveryPlatformRefNumber::query()->findOrFail($refId);
        $this->allow((int) $ref->operator_id, false);

        $draft = null;
        if ($request->boolean('use_company_rule')) {
            $draft = app(GrabPriceRuleResolver::class)->forCompany((int) $ref->operator_id);
        } elseif ($request->filled('pricing_mode')) {
            $request->validate(GrabPriceRuleRequest::ruleFields());
            $draft = GrabPriceRule::draftForStore(GrabPriceRuleRequest::valuesFrom($request->all()), (int) $ref->operator_id, $ref->id);
        }

        return response()->json($preview->for($ref, $draft));
    }

    // Switch stores to (or back from) the machine menu: one store, or all of an operator's.
    public function setMenuSource(Request $request, int $operatorId, GrabStoreService $stores)
    {
        $this->allow($operatorId);
        $request->validate([
            'machine_menu' => ['required', 'boolean'],
            'ref_ids' => ['required', 'array', 'min:1'],
            'ref_ids.*' => ['integer'],
        ]);

        $refs = $this->storesOf($operatorId, $request->ref_ids);

        $changed = $refs->filter(fn ($ref) => $stores->setMenuSource($ref, $request->boolean('machine_menu'), $request->user()->id))->count();

        return back()->with('success', $request->boolean('machine_menu')
            ? trans_choice(':count store now lists its machine. Grab shows the new menu within a few minutes.|:count stores now list their machines. Grab shows the new menus within a few minutes.', $changed, ['count' => $changed])
            : trans_choice(':count store is back on its old Grab menu.|:count stores are back on their old Grab menus.', $changed, ['count' => $changed]));
    }

    // Operators the viewer may see that have a Grab store or a Grab template.
    private function operators()
    {
        $withGrab = DeliveryPlatformRefNumber::query()->whereNotNull('operator_id')->distinct()->pluck('operator_id')
            ->merge(DeliveryProductMapping::query()->withoutGlobalScopes()->distinct()->pluck('operator_id'))
            ->map(fn ($id) => (int) $id)->unique();

        return Operator::withoutGlobalScopes()
            ->whereIn('id', array_intersect(GrabStoreVisibility::operatorIds(), $withGrab->all()))
            ->orderByRaw("code = 'HIPL' DESC")
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn ($operator) => ['id' => $operator->id, 'code' => $operator->code, 'name' => $operator->name]);
    }

    // The picked stores, all of this operator, or the request is refused.
    private function storesOf(int $operatorId, array $refIds)
    {
        $refs = DeliveryPlatformRefNumber::query()->where('operator_id', $operatorId)->whereIn('id', $refIds)->get();
        if ($refs->count() !== count(array_unique($refIds))) {
            throw ValidationException::withMessages(['ref_ids' => __('Pick stores of this operator.')]);
        }

        return $refs;
    }

    private function allow(int $operatorId, bool $write = true): void
    {
        abort_unless(GrabStoreVisibility::allows($operatorId), 403);
        abort_if($write && ! auth()->user()->can('update delivery-platform-product-mappings'), 403);
    }
}
