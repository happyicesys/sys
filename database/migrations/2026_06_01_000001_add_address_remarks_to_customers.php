<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free-text remarks for the delivery address, stored on the customers table
     * (parallels the existing contract_remarks column).
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('address_remarks')->nullable()->after('site_phone_number');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('address_remarks');
        });
    }
};
