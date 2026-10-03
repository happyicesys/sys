<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Card Terminal Company gets "can one terminal serve several machines?" (default no —
 * every company today binds one terminal to one machine), and Payrallel (T05) joins the
 * company list so a T05 freezer can name its card reader like any other machine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_terminals', function (Blueprint $table) {
            $table->boolean('can_bind_multiple_vends')->default(false)->after('name');
        });

        if (! DB::table('card_terminals')->where('name', 'Payrallel (T05)')->exists()) {
            DB::table('card_terminals')->insert([
                'name' => 'Payrallel (T05)',
                'can_bind_multiple_vends' => false,
                'remarks' => 'Remote card terminal (smart freezers). Bound per machine on Setting/Edit with its Payrallel access token.',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('card_terminals', function (Blueprint $table) {
            $table->dropColumn('can_bind_multiple_vends');
        });
    }
};
