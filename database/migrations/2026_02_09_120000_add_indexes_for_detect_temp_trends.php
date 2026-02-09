<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Wrap each index creation in try-catch to allow partial success and re-running without failure

        try {
            Schema::table('vend_temps', function (Blueprint $table) {
                $table->index(['vend_id', 'type', 'created_at'], 'idx_vend_type_created');
            });
        } catch (\Exception $e) {
            // Only catch "index already exists" errors. Rethrow others (e.g. timeout).
            if (strpos($e->getMessage(), 'Duplicate key name') === false && strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }

        try {
            Schema::table('vend_temps', function (Blueprint $table) {
                $table->index(['vend_id', 'type', 'value'], 'idx_vend_type_value');
            });
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') === false && strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }

        try {
            Schema::table('vend_temps', function (Blueprint $table) {
                $table->index(['type', 'created_at', 'vend_id'], 'idx_type_created_vend');
            });
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') === false && strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }

        try {
            Schema::table('vend_smart_alerts', function (Blueprint $table) {
                $table->index(['vend_id', 'alert_type'], 'idx_vend_alert_type');
            });
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') === false && strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('vend_temps', function (Blueprint $table) {
                $table->dropIndex('idx_vend_type_created');
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('vend_temps', function (Blueprint $table) {
                $table->dropIndex('idx_vend_type_value');
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('vend_temps', function (Blueprint $table) {
                $table->dropIndex('idx_type_created_vend');
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('vend_smart_alerts', function (Blueprint $table) {
                $table->dropIndex('idx_vend_alert_type');
            });
        } catch (\Exception $e) {
        }
    }
};
