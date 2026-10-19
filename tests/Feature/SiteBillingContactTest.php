<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Site edit → Billing Contact. Until 2026-10-19 the contact was saved only when "Billing Contact Person" was
 * filled, so a Site with no contact yet that was given just a company and an email (6620, "Coliwoo Star Pte Ltd")
 * saved without error and came back empty every time.
 */
class SiteBillingContactTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        Permission::findOrCreate('update customers', 'web');
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo('update customers');

        return $user;
    }

    private function site(): Customer
    {
        return Customer::create(['name' => 'Block C Coliwoo Star', 'code' => 'BC1', 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);
    }

    /** @param  array<string, mixed>  $contact  what the page posts: no `name` key when the person was never typed */
    private function save(Customer $site, array $contact): void
    {
        $this->actingAs($this->editor())->post("/customers/{$site->id}/update", [
            'customer' => ['status_id' => Customer::STATUS_ACTIVE, 'contact' => $contact],
        ])->assertSessionHasNoErrors();
    }

    public function test_a_company_and_email_alone_create_the_billing_contact(): void
    {
        $site = $this->site();

        $this->save($site, ['company' => 'Coliwoo Star Pte Ltd', 'email' => 'accounts@coliwoo.test', 'phone_country_id' => 1, 'alt_phone_country_id' => 1]);

        $contact = $site->fresh()->contact;
        $this->assertNotNull($contact);
        $this->assertSame(['Coliwoo Star Pte Ltd', 'accounts@coliwoo.test', null], [$contact->company, $contact->email, $contact->name]);
    }

    public function test_an_existing_contact_is_updated_even_without_a_contact_person(): void
    {
        $site = $this->site();
        $site->contact()->create(['name' => 'Ann', 'company' => 'Old Co', 'email' => 'old@co.test']);

        $this->save($site, ['name' => '', 'company' => 'New Co', 'email' => 'new@co.test', 'phone_country_id' => 1]);

        $contact = $site->fresh()->contact;
        $this->assertSame(['New Co', 'new@co.test', ''], [$contact->company, $contact->email, (string) $contact->name]);
    }

    public function test_an_empty_billing_contact_creates_nothing(): void
    {
        $site = $this->site();

        $this->save($site, ['phone_country_id' => 1, 'alt_phone_country_id' => 1, 'company' => '', 'email' => '  ']);

        $this->assertNull($site->fresh()->contact);
    }
}
