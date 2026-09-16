<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A command may leave a file behind that is not a log: the `photo` op's camera still (APK v15).
 * Kept beside `log_path` rather than in it so the timeline can tell "view 1 200 lines" from
 * "open image" without sniffing the path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freezer_control_commands', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('log_lines');
            $table->string('attachment_type', 16)->nullable()->after('attachment_path');
        });
    }

    public function down(): void
    {
        Schema::table('freezer_control_commands', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_type']);
        });
    }
};
