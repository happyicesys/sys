<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Vend;
use App\Support\SgMobile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * PayNow destination validation on the public /refund form.
 *
 * 2026-09-01: a customer with the number 89844833 could not submit at all. The
 * number is a perfectly ordinary SG mobile, but the pinned
 * giggsey/libphonenumber-for-php-lite (9.0.2, Mar 2025) still carried the old
 * SG metadata whose mobile pattern stopped at 895. IMDA had since opened the
 * rest of the 89x range; the library had not caught up, so the form rejected a
 * valid number and the customer lost their refund.
 *
 * 2026-09-02: the library is gone from this path. The rule is now our own
 * (App\Support\SgMobile): 8 digits starting with 8 or 9, after stripping an
 * optional +65 prefix. These cases pin that rule end-to-end through the form.
 */
class RefundFormPaynowDestinationTest extends TestCase
{
    use RefreshDatabase;

    private function makeEligibleVend(string $code = '2112'): Vend
    {
        $customer = Customer::create(['code' => 'C1', 'name' => 'Garden Pavilion', 'is_active' => true]);

        return Vend::create(['code' => $code, 'name' => 'M'.$code, 'is_active' => true, 'customer_id' => $customer->id]);
    }

    private function submit(string $destination): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/refund', [
            'machineID' => '2112',
            'is_manual' => true,
            'entered_amount' => 2.5,
            'approx_time' => '3pm',
            'manual_pay_method' => 'PayNow',
            'manual_items_summary' => 'Ice cream x1',
            'reason_text' => 'Did not dispense',
            'contact_email' => 'customer@example.com',
            'refund_method' => 'paynow',
            'payout_destination' => $destination,
            'photos' => [UploadedFile::fake()->image('proof.jpg')],
        ]);
    }

    public function test_valid_sg_mobiles_are_accepted()
    {
        $this->makeEligibleVend();

        $numbers = [
            '89844833', // the number that was rejected on the old metadata
            '89712345', // other newly-opened 89x
            '98844833',
            '81234567',
            '80123456',
            '+65 8984 4833', // country prefix + spaces, as customers often type it
        ];

        foreach ($numbers as $number) {
            $this->submit($number)
                ->assertOk()
                ->assertJsonStructure(['reference', 'status']);

            // RefundTicket stores the canonical 8-digit form.
            $this->assertDatabaseHas('refund_tickets', [
                'refund_method' => 'paynow',
                'payout_destination' => SgMobile::normalise($number),
            ]);
        }
    }

    public function test_non_mobile_destinations_are_refused()
    {
        $this->makeEligibleVend();

        $numbers = [
            '61234567',   // landline
            '8984483',    // too short
            '898448331',  // too long
            'not-a-phone',
            '',           // blank, with paynow chosen
        ];

        foreach ($numbers as $number) {
            $this->submit($number)
                ->assertStatus(422)
                ->assertJsonValidationErrors('payout_destination');
        }

        $this->assertDatabaseCount('refund_tickets', 0);
    }
}
