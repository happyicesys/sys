<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('handled_by');
            $table->dropColumn('created_by');
            $table->dropColumn('deactivated_at');
            $table->dropColumn('is_parent');
            $table->dropColumn('parent_id');
            $table->dropColumn('payment_method_id');
            $table->dropColumn('price_template_id');
            $table->dropColumn('updated_by');
            $table->json('account_manager_json')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('account_manager_json');
        });
    }
};
