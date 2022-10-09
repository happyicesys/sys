<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Product;
use App\Models\Uom;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncProductApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:products-api';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate all products from admin happyice';

    /**
     * Execute the console command.
     *
     * @return int
     */

    public $endPointUrl = 'https://admin.happyice.com.sg/api/items/migrate';

    public function handle()
    {
        $response = Http::get($this->endPointUrl);

        $items = $response->collect();
        $className = get_class(new Product());

        if($items) {
            foreach($items as $item) {
                $itemCategoryId = null;
                $itemCategoryGroupId = null;
                if($itemCategoryData = $item['itemcategory']) {
                    $itemCategory = Category::updateOrCreate([
                        'name' => $itemCategoryData['name'],
                        'classname' => $className,
                    ], [
                        'desc' => $itemCategoryData['desc'],
                    ]);
                    $itemCategoryId = $itemCategory->id;
                }

                if($itemCategoryGroupData = $item['item_group']) {
                    $itemCategoryGroup = CategoryGroup::updateOrCreate([
                        'name' => $itemCategoryGroupData['name'],
                        'classname' => $className,
                    ], [
                        'desc' => $itemCategoryGroupData['desc'],
                    ]);
                    $itemCategoryGroupId = $itemCategoryGroup->id;
                }

                $product = Product::updateOrCreate(
                    [
                        'code' => $item['product_id'],
                    ],
                    [
                        'name' => $item['name'],
                        'remarks' => null,
                        'desc' => $item['remark'],
                        'is_active' => $item['is_active'],
                        'is_commission' => $item['is_commission'],
                        'is_inventory' => $item['is_inventory'],
                        'is_supermarket_fee' => $item['is_supermarket_fee'],
                        'category_id' => $itemCategoryId,
                        'category_group_id' => $itemCategoryGroupId,
                    ]
                );

                if($thumbnailData = $item['main_imgpath']) {
                    if(isset(parse_url($thumbnailData)['host']) and parse_url($thumbnailData)['host'] === 'happyice-space.sgp1.digitaloceanspaces.com') {
                        $product->thumbnail()->updateOrCreate([
                            'type' => 1,
                        ], [
                            'full_url' => $thumbnailData,
                            'local_url' => $thumbnailData,
                        ]);
                    }
                }

                if($isInventory = $item['is_inventory']) {
                    $pcs = Uom::where('name', 'pcs')->first();
                    $ctn = Uom::where('name', 'ctn')->first();

                    $product->productUoms()->updateOrCreate([
                        'is_base_uom' => true,
                        'value' => 1,
                    ], [
                        'uom_id' => $pcs->id,
                    ]);

                    if($baseUnit = $item['base_unit']) {
                        if($baseUnit > 1) {
                            $product->productUoms()->updateOrCreate([
                                'uom_id' => $ctn->id,
                            ], [
                                'is_transaction_uom' => true,
                                'value' => $baseUnit,
                            ]);
                        }
                    }
                }
            }
        }
    }
}
