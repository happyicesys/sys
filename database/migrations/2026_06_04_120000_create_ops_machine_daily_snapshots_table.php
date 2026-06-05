<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-machine end-of-day telemetry snapshot powering Operations > Ops
 * Performance. One row per machine per day, instance-wide.
 *
 * Finer grain than a fleet rollup so the page's component/status section can
 * be sliced by operator / location type / machine prefix / model / category at
 * read time. Dimension keys are denormalized onto each row (operator_id,
 * vend_prefix_id, vend_model_id, location_type_id, category_id) so filtering
 * and grouping never need a join back to vends/customers.
 *
 * Financial rows on the page do NOT live here — they are derived live from
 * gp_metrics (which already keeps the same dimensions and full history). This
 * table only freezes machine STATE, which vends keeps no history of.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Supersedes the earlier fleet-level table, if it was created.
        Schema::dropIfExists('ops_daily_snapshots');

        Schema::create('ops_machine_daily_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('snapshot_date');

            // Machine + denormalized dimension keys for filtering / grouping.
            $table->unsignedBigInteger('vend_id');
            $table->integer('vend_code')->nullable();
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->unsignedBigInteger('vend_prefix_id')->nullable();
            $table->unsignedBigInteger('vend_model_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('location_type_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('category_group_id')->nullable();

            // Status flags.
            $table->boolean('is_active')->default(false);
            $table->boolean('is_testing')->default(false);

            // Component / version state captured at run time.
            $table->integer('lcd_monitor_id')->nullable();
            $table->smallInteger('bill_stat')->nullable();   // parameter_json.BILLStat
            $table->smallInteger('coin_stat')->nullable();   // parameter_json.CHGEStat
            $table->unsignedBigInteger('card_terminal_id')->nullable();
            $table->string('card_terminal_name')->nullable();
            $table->string('firmware_ver')->nullable();      // hex of parameter_json.Ver
            $table->string('apk_ver')->nullable();           // apk_ver_json.apkver
            $table->string('acb_rev')->nullable();           // acb_vmc_pa_json.ACBVer

            $table->timestamps();

            $table->unique(['snapshot_date', 'vend_id'], 'omds_date_vend_unique');
            $table->index(['snapshot_date', 'is_active'], 'omds_date_active');
            $table->index(['snapshot_date', 'operator_id'], 'omds_date_operator');
            $table->index(['snapshot_date', 'location_type_id'], 'omds_date_location');
            $table->index(['snapshot_date', 'vend_prefix_id'], 'omds_date_prefix');
            $table->index(['snapshot_date', 'vend_model_id'], 'omds_date_model');
            $table->index(['snapshot_date', 'category_id'], 'omds_date_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_machine_daily_snapshots');
    }
};
