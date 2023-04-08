<?php

namespace App\Console\Commands;

use App\Models\CategoryGroup;
use Illuminate\Console\Command;

class SyncVMCategory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:vm-category';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync VM Category';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $categoryGroups = CategoryGroup::all();

        if($categoryGroups) {
            foreach($categoryGroups as $categoryGroup) {
                if($categoryGroup->categories()->exists()) {
                    if(!str_starts_with($categoryGroup->name, 'VM-')) {
                        foreach($categoryGroup->categories as $category) {
                            if($category->customers()->exists()) {
                                foreach($category->customers as $customer) {
                                    $customer->category_id = null;
                                    $customer->save();
                                }
                            }
                            $category->delete();
                        }
                        $categoryGroup->delete();
                    }
                }
            }
        }
    }
}
