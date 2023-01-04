<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\UomResource;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Product;
use App\Models\ProductUom;
use App\Models\Uom;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'code';
        $sortBy = $request->sortBy ? $request->sortBy : true;
        $isActive = isset($request->is_active) ? $request->is_active : 1;
        $isInventory = isset($request->is_inventory) ? $request->is_inventory : 1;
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
            'products' => ProductResource::collection(
                Product::with([
                        'attachments',
                        'category',
                        'categoryGroup',
                        'productUoms.uom',
                        'thumbnail',
                        'unitCosts',
                    ])
                    ->when($request->code, function($query, $search) {
                        $query->where('code', 'LIKE', "%{$search}%");
                    })
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($isActive, function($query, $search) {
                        $query->where('is_active', $search);
                    }, function($query, $search) {
                        if($search !== '') {
                            $query->where('is_active', $search);
                        }
                    })
                    ->when($isInventory, function($query, $search) {
                        $query->where('is_inventory', $search);
                    }, function($query, $search) {
                        if($search !== '') {
                            $query->where('is_inventory', $search);
                        }
                    })
                    ->when($request->is_comm_or_sf, function($query, $search) {
                        switch($search) {
                            case 'comm':
                                $query->where('is_commission', 1)->where('is_supermarket_fee', 0);
                                break;
                            case 'sf':
                                $query->where('is_commission', 0)->where('is_supermarket_fee', 1);
                                break;
                            case 'both':
                                $query->where(function($query)  {
                                    $query->where('is_commission', 1)->orWhere('is_supermarket_fee', 1);
                                });
                                break;
                        }
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
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
        ]);

        $product = new Product();
        $product = $product->fill($request->all());
        $product->operator_id = auth()->user()->operator_id;
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
        ]);

        $product = Product::findOrFail($productId);
        $product->update($request->all());

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
