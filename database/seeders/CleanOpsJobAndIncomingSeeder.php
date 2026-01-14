<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanOpsJobAndIncomingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Schema::disableForeignKeyConstraints();

        DB::table('ops_jobs')->truncate();
        DB::table('ops_job_items')->truncate();
        DB::table('ops_job_item_channels')->truncate();
        DB::table('product_movements')->truncate();

        Schema::enableForeignKeyConstraints();
    }
}
