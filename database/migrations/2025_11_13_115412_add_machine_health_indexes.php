<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->addIndex('vends', ['code'], 'idx_vends_code');

        $this->addIndex('customers', ['operator_id', 'is_active'], 'idx_customers_operator_active');
        $this->addIndex('customers', ['location_type_id', 'is_active'], 'idx_customers_location_active');

        $this->addIndex(
            'delivery_product_mapping_vend',
            ['platform_ref_id', 'vend_code', 'is_active'],
            'idx_dpmv_platform_vend_active'
        );
        $this->addIndex(
            'delivery_product_mapping_vend',
            ['delivery_product_mapping_id', 'is_active'],
            'idx_dpmv_mapping_active'
        );
        $this->addIndex(
            'delivery_product_mapping_vend',
            ['customer_id', 'is_active'],
            'idx_dpmv_customer_active'
        );

        $this->addIndex(
            'delivery_product_mapping_vend_channels',
            ['delivery_product_mapping_vend_id', 'vend_channel_code'],
            'idx_dpmvc_mapping_channel_code'
        );
        $this->addIndex(
            'delivery_product_mapping_vend_channels',
            ['vend_channel_id'],
            'idx_dpmvc_vend_channel'
        );

        $this->addIndex(
            'vend_channel_error_logs',
            ['vend_channel_id', 'created_at'],
            'idx_vcel_channel_created'
        );

        $this->addIndex(
            'vend_temp_metrics',
            ['temp_type', 'period_type', 'period_start', 'vend_id'],
            'idx_vtm_sensor_period_vend'
        );

        $this->addIndex(
            'vend_temps',
            ['type', 'vend_id', 'created_at'],
            'idx_vt_type_vend_created'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('vends', 'idx_vends_code');

        $this->dropIndexIfExists('customers', 'idx_customers_operator_active');
        $this->dropIndexIfExists('customers', 'idx_customers_location_active');

        $this->dropIndexIfExists('delivery_product_mapping_vend', 'idx_dpmv_platform_vend_active');
        $this->dropIndexIfExists('delivery_product_mapping_vend', 'idx_dpmv_mapping_active');
        $this->dropIndexIfExists('delivery_product_mapping_vend', 'idx_dpmv_customer_active');

        $this->dropIndexIfExists('delivery_product_mapping_vend_channels', 'idx_dpmvc_mapping_channel_code');
        $this->dropIndexIfExists('delivery_product_mapping_vend_channels', 'idx_dpmvc_vend_channel');

        $this->dropIndexIfExists('vend_channel_error_logs', 'idx_vcel_channel_created');

        $this->dropIndexIfExists('vend_temp_metrics', 'idx_vtm_sensor_period_vend');

        $this->dropIndexIfExists('vend_temps', 'idx_vt_type_vend_created');
    }

    private function addIndex(string $table, array $columns, string $name, bool $unique = false): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $name, $unique) {
            if ($unique) {
                $table->unique($columns, $name);
            } else {
                $table->index($columns, $name);
            }
        });
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        if (! $this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($name) {
            $table->dropIndex($name);
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        $connection = Schema::getConnection();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $connection->getDatabaseName())
            ->where('table_name', $connection->getTablePrefix() . $table)
            ->where('index_name', $name)
            ->exists();
    }
};
