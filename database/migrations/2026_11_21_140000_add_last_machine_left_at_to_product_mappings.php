<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a machine last moved OFF this mapping (Brian, 2026-11-21: once every machine of a mapping with an upcoming
 * mapping has moved, deactivate it 3 days later). Written by App\Services\ProductMapping\MappingRetirement on every
 * rebind, including the VM Binding bulk update that the user_logs audit never sees. NULL = no move recorded since
 * this column existed; the retirement then reads user_logs instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_mappings', 'last_machine_left_at')) {
            Schema::table('product_mappings', function (Blueprint $table) {
                $table->timestamp('last_machine_left_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_mappings', 'last_machine_left_at')) {
            Schema::table('product_mappings', function (Blueprint $table) {
                $table->dropColumn('last_machine_left_at');
            });
        }
    }
};
