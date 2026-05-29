<?php

namespace App\Jobs;

use App\Models\Country;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Update-only sync of the NEW CMS mirror fields onto an already-linked
 * mark1 customer.
 *
 * Deliberately narrow in scope — this is NOT a replacement for
 * SyncVendCustomerCms. It exists so we can backfill / refresh the extra
 * fields the original sync never carried (billing address, company remark,
 * site name, cost rate, payment terms, contact email) WITHOUT touching the
 * fields SyncVendCustomerCms owns (name, status, delivery address,
 * contact name / phone, etc.).
 *
 * Guarantees:
 *  - Never creates a Customer. If no customer has this person_id, it no-ops.
 *  - Skips silently if CMS has no migrate record for the person_id
 *    (e.g. the person is not a vending/dvm/combi person).
 *  - Only writes the columns/relations listed below.
 *
 * Source endpoint: CMS GET /api/person/migrate/{personID}
 * (PersonController@retrieveCustomerMigration).
 */
class UpdateCustomerCmsFields implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $personID;

    public function __construct($personID = null)
    {
        $this->personID = $personID;
    }

    /**
     * @return string One of: updated | skipped_no_customer | skipped_no_cms |
     *                skipped_no_base_url | skipped_no_person_id
     */
    public function handle(): string
    {
        if (!$this->personID) {
            return 'skipped_no_person_id';
        }

        $baseUrl = config('app.cms_url');
        if (!$baseUrl) {
            return 'skipped_no_base_url';
        }

        // Never create — only operate on an already-linked customer.
        $customer = Customer::withoutGlobalScopes()
            ->where('person_id', $this->personID)
            ->first();

        if (!$customer) {
            return 'skipped_no_customer';
        }

        $response = Http::get($baseUrl . '/api/person/migrate/' . $this->personID);

        if (!$response->successful()) {
            Log::warning('UpdateCustomerCmsFields: CMS request failed', [
                'person_id' => $this->personID,
                'status' => $response->status(),
            ]);
            return 'skipped_no_cms';
        }

        $collection = $response->collect();

        // Endpoint returns a (possibly empty) list. Empty == person not found
        // / not a vending person → nothing to update.
        if (!$collection || !isset($collection[0])) {
            return 'skipped_no_cms';
        }

        $data = collect($collection[0]);

        // ── 1. New scalar mirror columns on the customer ──────────────────
        // These columns did not exist before this feature, so writing them
        // (including null) cannot clobber anything a user set in mark1.
        $customer->forceFill([
            'company_remark' => $data->get('com_remark'),
            'site_name' => $data->get('site_name'),
            'cost_rate' => $data->get('cost_rate'),
            'payterm' => $data->get('payterm'),
        ])->save();

        // ── 2. Billing address (addresses.type = 1) ──────────────────────
        // SyncVendCustomerCms only ever writes the delivery address (type 2),
        // so the billing row is "new" territory and safe to mirror.
        //
        // If CMS has no billing address, fall back to the delivery address
        // (del_*) so the billing row is never left empty.
        $billStreet = $data->get('bill_address');
        $billPostcode = $data->get('bill_postcode');
        $billCountry = $data->get('billing_country');

        $hasBilling = $billStreet
            || $billPostcode
            || (is_array($billCountry) && !empty($billCountry['name']));

        if (!$hasBilling) {
            // Copy delivery → billing.
            $billStreet = $data->get('del_address');
            $billPostcode = $data->get('del_postcode');
            $billCountry = $data->get('delivery_country');
        }

        $hasAddress = $billStreet
            || $billPostcode
            || (is_array($billCountry) && !empty($billCountry['name']));

        if ($hasAddress) {
            $billingCountryId = null;
            if (is_array($billCountry) && !empty($billCountry['name'])) {
                $country = Country::where('name', $billCountry['name'])->first();
                $billingCountryId = $country?->id;
            }

            $customer->addresses()->updateOrCreate(
                ['type' => Customer::ADDRESS_TYPE_BILLING],
                [
                    'street_name' => $billStreet,
                    'postcode' => $billPostcode,
                    'country_id' => $billingCountryId,
                ]
            );
        }

        // ── 3. Contact email (only the NEW field) ─────────────────────────
        // Do NOT touch contact name / phone — those belong to
        // SyncVendCustomerCms. Only update email, and only when CMS actually
        // has one, so a transient null can't wipe a previously-synced value.
        $email = $data->get('email');
        if ($customer->contact && !empty($email)) {
            $customer->contact()->update(['email' => $email]);
        }

        return 'updated';
    }
}
