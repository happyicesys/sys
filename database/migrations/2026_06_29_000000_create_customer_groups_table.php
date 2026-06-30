<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer (Site) grouping — links 2-3 physically co-located sites into one
 * cluster so they can be made to "travel together" on listing/report pages.
 *
 * Membership is EXCLUSIVE: a site belongs to at most one group, tracked by the
 * nullable customers.customer_group_id FK. Group membership is a *current*
 * attribute resolved live from this table — it is intentionally NOT frozen into
 * historical snapshots (ops_machine_daily_snapshots etc.), which key off
 * customer_id and resolve the group at read time.
 *
 * A group is scoped to a single operator (denormalised operator_id) to match
 * how the rest of the app scopes by operator. Names are find-or-created from
 * the Site edit form (type the same group name on co-located sites to link
 * them), so (operator_id, name) is unique to avoid accidental duplicates.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_groups')) {
            Schema::create('customer_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);

                // Denormalised operator for cheap scoping. Nullable, no hard FK
                // so a legacy/operator-less site never blocks a write.
                $table->unsignedBigInteger('operator_id')->nullable()->index();

                $table->text('notes')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                // One group name per operator — drives the find-or-create on save.
                $table->unique(['operator_id', 'name'], 'customer_groups_operator_name_uq');
            });
        }

        if (! Schema::hasColumn('customers', 'customer_group_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unsignedBigInteger('customer_group_id')->nullable()->after('zone_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'customer_group_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('customer_group_id');
            });
        }

        Schema::dropIfExists('customer_groups');
    }
};
