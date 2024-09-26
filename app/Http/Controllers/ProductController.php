<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\UomResource;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\Product;
use App\Models\ProductUom;
use App\Models\SellingPrice;
use App\Models\Uom;
use App\Traits\GetUserTimezone;
use App\Services\CmsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductController extends Controller
{
    use GetUserTimezone;

    public function __construct()
    {
        $this->cmsService = new CmsService();
        $this->middleware(['permission:read products']);
    }

    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $className = get_class(new Product());

        return Inertia::render('Product/Index', [
            'categories' => CategoryResource::collection(
                    Category::where('classname', $className)
                        ->orderBy('name')
                        ->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                    CategoryGroup::where('classname', $className)
                        ->orderBy('name')
                        ->get()
            ),
            'languageOptions' => config('language'),
            'measurementUnitOptions' => Product::MEASUREMENT_UNIT_MAPPINGS,
            'operatorOptions' => OperatorResource::collection(
                Operator::all()
            ),
            'priceTypeOptions' => SellingPrice::TYPE_MAPPINGS,
            'products' => ProductResource::collection(
                Product::with([
                        'attachments',
                        'category',
                        'categoryGroup',
                        'latestUnitCost',
                        'operator',
                        'productUoms.uom',
                        'sellingPrices',
                        'thumbnail',
                        'unitCosts' => function($query) {
                            $query->orderBy('date_from', 'desc')->orderBy('created_at', 'desc');
                        },
                    ])
                    ->filterIndex($request)
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'uoms' => UomResource::collection(
                Uom::query()
                    ->orderBy('sequence')
                    ->get()
            ),
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'code' => 'required',
            'name' => 'required',
            'operator_id' => 'required',
        ], [
            'operator_id.required' => 'Please choose the operator.',
        ]);

        $product = new Product();
        $product = $product->fill($request->all());
        if(!$request->operator_id) {
            $product->operator_id = auth()->user()->operator_id;
        }
        if(!$request->measurement_count) {
            $product->measurement_count = 1;
        }
        $product->save();

        if($request->hasFile('thumbnail')){
            $request->validate([
                'thumbnail' => 'sometimes|image|max:10000',
            ]);
            $url = Storage::url($request->thumbnail->storePublicly('sys/products'));
            $product->thumbnail()->updateOrCreate([
                'type' => 1,
            ], [
                'full_url' => $url,
                'local_url' => $url,
            ]);
        }

        return redirect()->route('products');
    }

    public function availability(Request $request)
    {
        if(!$request->operators) {
            if(auth()->user()->operator->code == 'HIPL') {
                $request->merge(['operators' => [
                    auth()->user()->operator_id, Operator::where('code', 'HIMD')->first()?->id,
                    auth()->user()->operator_id, Operator::where('code', 'LEA')->first()?->id,
                ]]);
            }else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }

        $request->merge([
            'productAvailableDate' => $request->productAvailableDate ? $request->productAvailableDate : Carbon::today()->addDay()->toDateString(),
        ]);

        $products = Product::query()
            ->with([
                'isAvailableUpdatedBy',
                'latestUnitCost',
                'thumbnail',
            ])
            ->when($request->operators, function($query, $search) {
                $query->whereIn('operator_id', $search);
            })
            ->select(
                'id',
                'code',
                'desc',
                'name',
                'is_available',
                'is_available_updated_at',
                'is_available_updated_by',
            )
            ->selectRaw('
                JSON_UNQUOTE(JSON_EXTRACT(max_ops_job_pick_limit_json, ?)) AS max_ops_job_pick_limit',
                ['$."'.$request->productAvailableDate.'"']
            )
            ->selectRaw('(
                SELECT SUM(vend_channels.capacity - vend_channels.qty)
                FROM ops_job_item_channels
                LEFT JOIN ops_jobs ON ops_jobs.id = ops_job_item_channels.ops_job_id
                LEFT JOIN vend_channels ON ops_job_item_channels.vend_channel_id = vend_channels.id
                WHERE ops_job_item_channels.product_id = products.id
                AND DATE(ops_jobs.date) = ?
                AND DATE(ops_jobs.date) >= ?
            ) AS needed_qty', [$request->productAvailableDate, Carbon::today()->toDateString()])
            ->selectRaw('(
                SELECT SUM(
                    CASE
                        WHEN ops_job_items.status >= ? AND ops_job_items.status <> ?
                            THEN ops_job_item_channels.actual_qty
                        WHEN ops_job_items.status = ?
                            THEN ops_job_item_channels.picked_qty
                        ELSE 0
                    END
                ) AS total_qty
                FROM ops_job_item_channels
                LEFT JOIN ops_job_items ON ops_job_items.id = ops_job_item_channels.ops_job_item_id
                LEFT JOIN ops_jobs ON ops_jobs.id = ops_job_item_channels.ops_job_id
                LEFT JOIN vend_channels ON ops_job_item_channels.vend_channel_id = vend_channels.id
                WHERE ops_job_item_channels.product_id = products.id
                AND DATE(ops_jobs.date) >= ?
                AND ops_job_items.cms_transaction_id IS NULL
            ) AS not_yet_sync_api_qty ', [
                OpsJob::STATUS_DELIVERED,
                OpsJob::STATUS_CANCELLED,
                OpsJob::STATUS_PICKED,
                Carbon::today()->toDateString()
            ])
            ->where('is_active', true)
            ->where('is_inventory', true)
            ->orderBy('code')
            ->get();

        $cmsQtyAvailableProducts = $this->cmsService->getCMSQtyAvailableApi();

        foreach($products as $product) {
            if($cmsQtyAvailableProducts) {
                foreach($cmsQtyAvailableProducts as $cmsQtyAvailableProduct) {
                    if($product->code == $cmsQtyAvailableProduct['code']) {
                        $product->qty_available_pcs_api = $cmsQtyAvailableProduct['qty'];
                        $product->net_available_qty_pcs_api = $cmsQtyAvailableProduct['qty'] - $product->not_yet_sync_api_qty;
                    }
                }
            }
        }

        return Inertia::render('Vend/ProductAvailability', [
            'operatorOptions' => OperatorResource::collection(
                Operator::all()
            ),
            'products' => ProductResource::collection(
                $products
            ),
        ]);
    }

    public function update(Request $request, $productId)
    {
        $request->validate([
            'code' => 'required',
            'name' => 'required',
            'operator_id' => 'required',
        ], [
            'operator_id.required' => 'Please choose the operator.',
        ]);

// dd($request->all());

        $product = Product::findOrFail($productId);
        $product->fill($request->except(['is_available', 'is_available_updated_at']));
        $product->save();

        if($request->hasFile('thumbnail')){
            $request->validate([
                'thumbnail' => 'sometimes|image|max:500',
            ]);
            $url = Storage::url($request->thumbnail->storePublicly('sys/products'));
            $product->thumbnail()->updateOrCreate([
                'type' => 1,
            ], [
                'full_url' => $url,
                'local_url' => $url,
            ]);
        }

        if($request->has('languages')) {
            $product->update([
                'translated_names_json' => $request->languages
            ]);
        }

        if($request->has('sellingPrices')) {
            $sellingPrices = $request->sellingPrices;
            if($sellingPrices) {
                foreach($sellingPrices as $sellingPrice) {
                    if(!isset($sellingPrice['id'])) {
                        $product->sellingPrices()->create([
                            'amount' => $sellingPrice['amount'],
                            'type' => $sellingPrice['type'],
                        ]);
                    }
                }
            }
        }

        if($request->has('unitCosts')) {
            $unitCosts = $request->unitCosts;
            if($unitCosts) {
                foreach($unitCosts as $unitCost) {
                    if(!isset($unitCost['id'])) {
                        if($product->unitCosts()->exists()) {
                            $product->unitcosts()->update([
                                'is_current' => false,
                            ]);
                        }
                        $product->unitCosts()->create([
                            'cost' => $unitCost['cost'],
                            'date_from' => $unitCost['date_from'],
                        ]);

                        $currentUnitCost = $product->unitCosts()->whereDate('date_from', '<=', Carbon::today()->setTimezone($this->getUserTimezone())->toDateString())->latest('created_at')->first();
                        $currentUnitCost->is_current = true;
                        $currentUnitCost->save();
                    }
                }
            }
        }

        return redirect()->route('products');
    }

    public function toggleActivateDeactivate($productId)
    {
        $product = Product::findOrFail($productId);
        $product->is_active = !$product->is_active;
        $product->save();

        return redirect()->route('products');
    }

    public function toggleIsAvailable(Request $request)
    {
        $product = Product::findOrFail($request->product_id);
        $product->is_available = !$product->is_available;
        $product->is_available_updated_by = auth()->user()->id;
        $product->is_available_updated_at = Carbon::now();
        $product->save();
    }

    public function bindUom(Request $request, $productId)
    {
        // dd($request->all());
        $product = Product::findOrFail($productId);

        if($request->is_base_uom) {
            $product->productUoms()->update(['is_base_uom' => false]);
        }

        if($request->is_transaction_uom) {
            $product->productUoms()->update(['is_transaction_uom' => false]);
        }

        $product->productUoms()->create($request->all());

        return redirect()->route('products');
    }

    public function delete($productId)
    {
        $product = Product::findOrFail($productId);
        $product->delete();

        return redirect()->route('products');
    }

    public function deleteProductUom($productUomId)
    {
        $productUom = ProductUom::findOrFail($productUomId);

        if($productUom->is_base_uom) {
            $assignProductBaseUom =  $productUom->product->productUoms()->where('value', 1)->first();
            if($assignProductBaseUom) {
                $assignProductBaseUom->is_base_uom = true;
                $assignProductBaseUom->save();
            }
        }

        if($productUom->is_transaction_uom) {
            $assignProductTransactionUom =  $productUom->product->productUoms()->orderBy('value', 'desc')->first();
            if($assignProductTransactionUom) {
                $assignProductTransactionUom->is_transaction_uom = true;
                $assignProductTransactionUom->save();
            }
        }

        $productUom->delete();

        return redirect()->route('products');
    }

    public function deleteSellingPrice($sellingPriceId)
    {
        $sellingPrice = SellingPrice::findOrFail($sellingPriceId);
        $sellingPrice->delete();

        return redirect()->route('products');
    }

    public function updateMaxOpsJobPickLimit(Request $request, $productID)
    {
        $product = Product::findOrFail($productID);

        // Retrieve the current `max_ops_job_pick_limit_json` as an associative array
        $maxOpsJobPickLimitJson = $product->max_ops_job_pick_limit_json;

        // Loop through the existing array and remove entries where the date is in the past
        // if (!empty($maxOpsJobPickLimitJson)) {
        //     foreach ($maxOpsJobPickLimitJson as $date => $value) {
        //         if (Carbon::parse($date)->lt(Carbon::today())) {
        //             unset($maxOpsJobPickLimitJson[$date]);
        //         }
        //     }
        // }

        // Check if new data is provided in the request, then add it to the array
        if ($request->date && $request->max_ops_job_pick_limit) {
            // Add or update the entry for the new date
            $maxOpsJobPickLimitJson[$request->date] = $request->max_ops_job_pick_limit;
        }

        // Encode the updated array back to JSON and save it
        $product->max_ops_job_pick_limit_json = $maxOpsJobPickLimitJson;
        $product->save();

        return redirect()->back();
    }
}
