<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * English / Simplified Chinese UI (Brian, 2026-10-26). `users.locale` is each user's own choice (NULL =
 * English); `ui_translations` holds the wording a person changed on the Translations page, laid over
 * the shipped `lang/{locale}.json`. Keys are the English text and can be long, so the unique index is
 * on its sha1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('locale', 10)->nullable();
            });
        }

        if (! Schema::hasTable('ui_translations')) {
            Schema::create('ui_translations', function (Blueprint $table) {
                $table->id();
                $table->string('locale', 10);
                $table->text('key');
                $table->char('key_hash', 40);
                $table->text('value');
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['locale', 'key_hash']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ui_translations');
        if (Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
    }
};
