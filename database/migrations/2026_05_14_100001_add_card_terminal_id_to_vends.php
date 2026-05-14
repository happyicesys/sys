<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Adds `card_terminal_id` to `vends` so each vend can store a
     * user-defined card-terminal type (CAS, NYX, PAX, 111, MLS) instead of
     * relying on the VM-reported `acb_vmc_pa_json->CSHL_MFG` value, which has
     * proven unreliable.
     *
     * Nullable + no DB-level FK constraint to mirror the project's other
     * "soft" FK columns (e.g. cashless_terminal_id, vend_prefix_id).
     */
    public function up(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->unsignedBigInteger('card_terminal_id')->nullable()->after('cashless_terminal_id');
            $table->index('card_terminal_id', 'vends_card_terminal_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropIndex('vends_card_terminal_id_index');
            $table->dropColumn('card_terminal_id');
        });
    }
};
