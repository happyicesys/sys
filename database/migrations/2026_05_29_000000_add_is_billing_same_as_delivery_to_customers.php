<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;

/**
 * Adds customers.is_billing_same_as_delivery — drives the "Billing Address
 * same as Delivery Address" checkbox on Customer Create/Edit.
 *
 * Default TRUE (the common case: one address). Backfill flips it to FALSE
 * only for customers that already have a billing address (addresses.type = 1)
 * which differs from their delivery address (type = 2), so existing distinct
 * billing addresses keep showing as distinct.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_billing_same_as_delivery')->default(true)->after('is_gst_registered');
        });

        Customer::withoutGlobalScopes()
            ->with(['billingAddress', 'deliveryAddress'])
            ->chunkById(200, function ($customers) {
                foreach ($customers as $customer) {
                    $billing = $customer->billingAddress;

                    // No billing row → leave the default TRUE (same as delivery).
                    if (!$billing) {
                        continue;
                    }

                    $delivery = $customer->deliveryAddress;
                    $same = true;

                    if (!$delivery) {
                        $same = false;
                    } else {
                        foreach (['postcode', 'unit_num', 'block_num', 'building', 'street_name', 'country_id'] as $field) {
                            if ((string) $billing->{$field} !== (string) $delivery->{$field}) {
                                $same = false;
                                break;
                            }
                        }
                    }

                    if (!$same) {
                        DB::table('customers')
                            ->where('id', $customer->id)
                            ->update(['is_billing_same_as_delivery' => false]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('is_billing_same_as_delivery');
        });
    }
};
