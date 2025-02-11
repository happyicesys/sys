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
        Schema::table('dispense_records', function (Blueprint $table) {
            $table->boolean('is_vm_receive_dispense_signal')->default(false)->index()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispense_records', function (Blueprint $table) {
            //
        });
    }
};
