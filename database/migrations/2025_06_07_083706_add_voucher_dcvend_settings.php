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
        Schema::table('vouchers', function (Blueprint $table) {
            $table->boolean('is_dcvend')->default(false)->after('is_batch_code');
            $table->integer('dcvend_member_type')->after('date_to')->nullable();
            $table->boolean('is_recurring')->after('is_batch_code')->nullable();
            $table->integer('valid_duration')->after('used_qty')->nullable();
            $table->string('valid_unit')->after('used_qty')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('is_dcvend');
            $table->dropColumn('dcvend_member_type');
            $table->dropColumn('is_recurring');
            $table->dropColumn('valid_duration');
            $table->dropColumn('valid_unit');
        });
    }
};
