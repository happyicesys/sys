<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes for better query performance
        // Using try-catch to handle cases where index might already exist

        // Vends table indexes
        try {
            Schema::table('vends', function (Blueprint $table) {
                $table->index('customer_id', 'vends_customer_id_index');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }

        // Customers table indexes
        try {
            Schema::table('customers', function (Blueprint $table) {
                $table->index('person_id', 'customers_person_id_index');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }

        try {
            Schema::table('customers', function (Blueprint $table) {
                $table->index('operator_id', 'customers_operator_id_index');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }

        // Vouchers table indexes
        try {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->index('code', 'vouchers_code_index');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }

        // Voucher items table indexes
        try {
            Schema::table('voucher_items', function (Blueprint $table) {
                $table->index('code', 'voucher_items_code_index');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }

        // HID cards table indexes
        try {
            Schema::table('hid_cards', function (Blueprint $table) {
                $table->index(['value', 'operator_id'], 'hid_cards_value_operator_id_index');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }

        // Ops job items table indexes
        try {
            Schema::table('ops_job_items', function (Blueprint $table) {
                $table->index('cms_transaction_id', 'ops_job_items_cms_transaction_id_index');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes - wrapped in try-catch in case they don't exist
        try {
            Schema::table('vends', function (Blueprint $table) {
                $table->dropIndex('vends_customer_id_index');
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex('customers_person_id_index');
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex('customers_operator_id_index');
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->dropIndex('vouchers_code_index');
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('voucher_items', function (Blueprint $table) {
                $table->dropIndex('voucher_items_code_index');
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('hid_cards', function (Blueprint $table) {
                $table->dropIndex('hid_cards_value_operator_id_index');
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('ops_job_items', function (Blueprint $table) {
                $table->dropIndex('ops_job_items_cms_transaction_id_index');
            });
        } catch (\Exception $e) {
        }
    }
};
