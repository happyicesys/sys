<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The AI Recognition sidebar badge reads door faults by op + time on every page load of a viewer. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freezer_control_commands', function (Blueprint $table) {
            $table->index(['op', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('freezer_control_commands', function (Blueprint $table) {
            $table->dropIndex(['op', 'created_at']);
        });
    }
};
