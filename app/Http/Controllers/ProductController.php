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
use App\Models\Product;
use App\Models\ProductUom;
use App\Models\Uom;
use App\Traits\GetUserTimezone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductController extends Controller
{
    use GetUserTimezone;

    public function __construct()
    {
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
            'measurementUnitOptions' => Product::MEASUREMENT_UNIT_MAPPINGS,
            'operatorOptions' => OperatorResource::collection(
                Operator::all()
            ),
            'products' => ProductResource::collection(
                Product::with([
                        'attachments',
                        'category',
                        'categoryGroup',
                        'latestUnitCost',
                        'operator',
                        'productUoms.uom',
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

    public function update(Request $request, $productId)
    {
        $request->validate([
            'code' => 'required',
            'name' => 'required',
            'operator_id' => 'required',
        ], [
            'operator_id.required' => 'Please choose the operator.',
        ]);

        $product = Product::findOrFail($productId);
        $product->update($request->all());

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
        // $currentUnitCost = $product->unitCosts()->whereDate('date_from', '<=', Carbon::today()->setTimezone($this->getUserTimezone())->toDateString())->latest('date_from')->latest('created_at')->get();
        // dd($currentUnitCost->toArray());
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
                            'cost' => $unitCost['cost'] * 100,
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
}
