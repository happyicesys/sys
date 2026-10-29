<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brian, 2026-10-29: each operator chooses which payment options its machines' QR menu shows (Operator → Edit →
 * Machine payment menu). A row exists only where an operator differs from a method's default — a normal method
 * (`payment_methods.menu_variant` NULL) shows unless switched off, an opt-in one (Ace & Apex, "mindef") stays
 * hidden unless switched on — so an operator nobody touched has no rows and keeps today's menu.
 *
 * Carries `operators.payment_menu_variant` over: each operator with a variant gets that variant's methods
 * switched on (HIMD → Ace & Apex, code 802). The column stays; the code that reads these rows replaces it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_shown');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['operator_id', 'payment_method_id']);
        });

        $rows = DB::table('operators')
            ->join('payment_methods', DB::raw('LOWER(payment_methods.menu_variant)'), '=', DB::raw('LOWER(TRIM(operators.payment_menu_variant))'))
            ->whereNotNull('operators.payment_menu_variant')
            ->select('operators.id as operator_id', 'payment_methods.id as payment_method_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('operator_payment_methods')->insertOrIgnore([
                'operator_id' => $row->operator_id,
                'payment_method_id' => $row->payment_method_id,
                'is_shown' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_payment_methods');
    }
};
