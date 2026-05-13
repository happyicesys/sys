<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add three Location Grading columns on customers. Each stores the
     * picked option code (A/B/C) for one of the rubric categories defined
     * in App\Models\Customer::LOCATION_GRADING_CATEGORIES.
     *
     * Editable on Customer/Edit only — not exposed on Create.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->char('location_grading_placement', 1)->nullable()->after('contract_remarks');
            $table->char('location_grading_access', 1)->nullable()->after('location_grading_placement');
            $table->char('location_grading_flexibility', 1)->nullable()->after('location_grading_access');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'location_grading_placement',
                'location_grading_access',
                'location_grading_flexibility',
            ]);
        });
    }
};
