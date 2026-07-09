<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Analysis-friendly daily projection of the `holidays` table: exactly one row
     * per calendar date, so it joins to vend_transactions on an equality
     * (holiday_days.date = <txn date>) instead of a BETWEEN range. Fully derived
     * — rebuilt from `holidays` by HolidayDayRebuildService on every sync/seed,
     * so it is safe to truncate/regenerate. Presence of a row = that date is a
     * holiday of some kind; the flags classify it.
     */
    public function up(): void
    {
        Schema::create('holiday_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_school')->default(false);
            $table->string('name')->nullable(); // best label for the date (public > school > other)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_days');
    }
};
