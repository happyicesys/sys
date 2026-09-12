<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who put this terminal on this machine.
 *
 * A binding row already carries WHEN it was recorded (created_at) and the
 * dates it covers (bound_from/bound_until), but not the hand behind it. The
 * machine Setting/Edit screen now shows both, so ops can tell a deliberate
 * fitting from one the Card Settlement page's matcher worked out.
 *
 * NULL means "not a person" — the settlement page's auto-match buttons, the
 * CSV importer, any console path (Brian, 2026-09-12: those stay "sys"; only
 * a real user changing the terminal on Setting/Edit gets a name). Forward-only:
 * rows written before this column exists read as sys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_terminal_bindings', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('card_terminal_bindings', function (Blueprint $table) {
            $table->dropColumn('created_by');
        });
    }
};
