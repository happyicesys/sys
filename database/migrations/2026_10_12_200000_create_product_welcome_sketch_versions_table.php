<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Welcome-sketch album (Brian, 2026-10-12): every drawing of a product is kept as a version, and
 * people shortlist and pick the one the freezers drop. `product_welcome_sketches` stays the live
 * row the freezer menu reads; `version_id` says which album entry it is, `selected_by` who picked
 * it (a person's pick is never replaced automatically).
 *
 * Backfill: each live drawing becomes its product's first album entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_welcome_sketch_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            // pending → generating → ready | failed
            $table->string('status', 16)->default('pending');
            // seed, generated, cutout, upload
            $table->string('source', 16)->nullable();
            $table->string('path')->nullable();
            $table->string('url', 1024)->nullable();
            $table->string('source_photo_url', 1024)->nullable();
            $table->string('source_photo_hash', 64)->nullable();
            $table->string('model', 64)->nullable();
            $table->string('reason', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('shortlisted_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
        });

        Schema::table('product_welcome_sketches', function (Blueprint $table) {
            $table->unsignedBigInteger('version_id')->nullable()->after('product_id');
            $table->unsignedBigInteger('selected_by')->nullable()->after('requested_by');
            $table->timestamp('selected_at')->nullable()->after('selected_by');
        });

        DB::table('product_welcome_sketches')->whereNotNull('url')->orderBy('id')->each(function ($live) {
            $versionId = DB::table('product_welcome_sketch_versions')->insertGetId([
                'product_id' => $live->product_id,
                'status' => 'ready',
                'source' => $live->source,
                'path' => $live->path,
                'url' => $live->url,
                'source_photo_url' => $live->source_photo_url,
                'source_photo_hash' => $live->source_photo_hash,
                'model' => $live->model,
                'reason' => $live->reason,
                'last_error' => $live->last_error,
                'requested_by' => $live->requested_by,
                'generated_at' => $live->generated_at,
                'created_at' => $live->generated_at ?? $live->created_at,
                'updated_at' => now(),
            ]);
            DB::table('product_welcome_sketches')->where('id', $live->id)->update(['version_id' => $versionId]);
        });
    }

    public function down(): void
    {
        Schema::table('product_welcome_sketches', function (Blueprint $table) {
            $table->dropColumn(['version_id', 'selected_by', 'selected_at']);
        });
        Schema::dropIfExists('product_welcome_sketch_versions');
    }
};
