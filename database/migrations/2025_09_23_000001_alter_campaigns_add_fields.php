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
        Schema::table('campaigns', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->string('slug')->nullable()->after('name');
            $table->string('description')->nullable()->after('slug');
            $table->dateTime('start_at')->nullable()->after('remarks');
            $table->dateTime('end_at')->nullable()->after('start_at');

            $table->unique('uuid');
            $table->index('start_at');
            $table->index('end_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropIndex(['start_at']);
            $table->dropIndex(['end_at']);
            $table->dropColumn(['uuid', 'slug', 'description', 'start_at', 'end_at']);
        });
    }
};

