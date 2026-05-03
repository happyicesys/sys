<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('contract_detail_updated_at')->nullable()->after('contract_notice_period');
            $table->foreignId('contract_detail_updated_by')->nullable()->after('contract_detail_updated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['contract_detail_updated_by']);
            $table->dropColumn(['contract_detail_updated_at', 'contract_detail_updated_by']);
        });
    }
};
