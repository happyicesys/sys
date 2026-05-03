<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('contract_commission_type')->nullable()->after('zone_id');   // F, R, U, PS, PS+U, PSORU
            $table->decimal('contract_commission_value', 10, 2)->nullable()->after('contract_commission_type');  // amount OR percent
            $table->decimal('contract_commission_value2', 10, 2)->nullable()->after('contract_commission_value'); // utility amount (PS+U / PSORU)
            $table->decimal('contract_ps_term', 5, 2)->nullable()->after('contract_commission_value2');          // PS term %
            $table->date('contract_until')->nullable()->after('contract_ps_term');
            $table->boolean('contract_auto_renewal')->default(false)->after('contract_until');
            $table->unsignedSmallInteger('contract_min_commitment_period')->nullable()->after('contract_auto_renewal'); // months
            $table->unsignedSmallInteger('contract_notice_period')->nullable()->after('contract_min_commitment_period'); // months
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'contract_commission_type',
                'contract_commission_value',
                'contract_commission_value2',
                'contract_ps_term',
                'contract_until',
                'contract_auto_renewal',
                'contract_min_commitment_period',
                'contract_notice_period',
            ]);
        });
    }
};
