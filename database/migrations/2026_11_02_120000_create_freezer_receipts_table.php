<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Live tax invoice behind the freezer's thank-you QR (Brian, 2026-11-02).
 *
 * - `freezer_receipts`: one row per door session whose QR link was opened. It keeps the signature
 *   the link was first opened with, so a later change of the freezer's key never breaks a link
 *   a customer already holds.
 * - `profiles.gst_reg_no`: the GST registration number a tax invoice must print. Left NULL here —
 *   it is not published anywhere we can read it from; the page says "Receipt" until it is set.
 * - HIPL is linked to HAPPY ICE PTE LTD (profile 1) when it has no profile yet, and that profile's
 *   contact gets the enquiry email from happyice.com.sg/contact-us when it has none. Nothing read
 *   operators.profile_id before this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freezer_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vend_id');
            $table->string('session_ref', 64);
            $table->string('signature', 64);
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();

            $table->unique(['vend_id', 'session_ref']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->string('gst_reg_no', 32)->nullable()->after('uen');
        });

        $profileId = DB::table('profiles')->where('id', 1)->where('name', 'HAPPY ICE PTE LTD')->value('id');
        if ($profileId !== null) {
            DB::table('operators')->where('code', 'HIPL')->whereNull('profile_id')->update(['profile_id' => $profileId]);
            DB::table('contacts')
                ->where('modelable_type', 'App\\Models\\Profile')
                ->where('modelable_id', $profileId)
                ->whereNull('email')
                ->update(['email' => 'enquiry@happyice.com.sg']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('freezer_receipts');
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('gst_reg_no');
        });
    }
};
