<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Welcome-sketch steering (Brian, 2026-10-14): a person's Draw may carry extra instructions for
 * the image model ("text exactly ICE BALL, no bite"). Kept on the album entry it was drawn with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_welcome_sketch_versions', function (Blueprint $table) {
            $table->text('instructions')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('product_welcome_sketch_versions', function (Blueprint $table) {
            $table->dropColumn('instructions');
        });
    }
};
