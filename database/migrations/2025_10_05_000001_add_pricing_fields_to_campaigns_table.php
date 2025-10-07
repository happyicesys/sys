<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('promo_type')->nullable()->after('description');
            $table->unsignedInteger('bundle_qty')->nullable()->after('promo_type');
            $table->decimal('value', 12, 2)->nullable()->after('bundle_qty');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['promo_type', 'bundle_qty', 'value']);
        });
    }
};
