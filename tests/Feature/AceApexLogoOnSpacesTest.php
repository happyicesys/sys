<?php

namespace Tests\Feature;

use App\Models\PaymentMerchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The Ace & Apex logo moves from public/ to the default disk under payment-merchant/, like every other logo. */
class AceApexLogoOnSpacesTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_URL = 'https://sys.happyice.com.sg/img/payment-merchant/mindef-ace-apex.png';

    private function migration(): object
    {
        return require database_path('migrations/2026_10_29_180000_move_mindef_ace_apex_logo_to_spaces.php');
    }

    public function test_the_logo_is_uploaded_and_the_merchant_points_at_it(): void
    {
        Storage::fake();
        $merchant = PaymentMerchant::create(['name' => 'Ace Credits & Apex Credits', 'image_url' => self::OLD_URL]);

        $this->migration()->up();

        Storage::disk()->assertExists('payment-merchant/mindef-ace-apex.png');
        $this->assertSame(
            file_get_contents(public_path('img/payment-merchant/mindef-ace-apex.png')),
            Storage::disk()->get('payment-merchant/mindef-ace-apex.png'),
        );
        $this->assertSame(Storage::disk()->url('payment-merchant/mindef-ace-apex.png'), $merchant->fresh()->image_url);
    }

    public function test_other_merchants_and_a_moved_logo_are_left_alone(): void
    {
        Storage::fake();
        $paynow = PaymentMerchant::create(['name' => 'Paynow', 'image_url' => 'https://happyice-space.sgp1.digitaloceanspaces.com/payment-merchant/paynow.png']);

        $this->migration()->up();

        Storage::disk()->assertMissing('payment-merchant/mindef-ace-apex.png');
        $this->assertSame('https://happyice-space.sgp1.digitaloceanspaces.com/payment-merchant/paynow.png', $paynow->fresh()->image_url);
    }

    private function replacement(): object
    {
        return require database_path('migrations/2026_10_29_200000_replace_mindef_ace_apex_logo.php');
    }

    public function test_the_new_artwork_goes_up_under_a_new_name_and_the_old_one_stays(): void
    {
        Storage::fake();
        Storage::disk()->put('payment-merchant/mindef-ace-apex.png', 'old artwork');
        $merchant = PaymentMerchant::create([
            'name' => 'Ace Credits & Apex Credits',
            'image_url' => Storage::disk()->url('payment-merchant/mindef-ace-apex.png'),
        ]);

        $this->replacement()->up();

        $this->assertSame(
            file_get_contents(database_path('data/payment-merchant/mindef-ace-apex-v2.png')),
            Storage::disk()->get('payment-merchant/mindef-ace-apex-v2.png'),
        );
        $this->assertSame('old artwork', Storage::disk()->get('payment-merchant/mindef-ace-apex.png'));
        $this->assertSame(Storage::disk()->url('payment-merchant/mindef-ace-apex-v2.png'), $merchant->fresh()->image_url);

        $this->replacement()->down();
        $this->assertSame(Storage::disk()->url('payment-merchant/mindef-ace-apex.png'), $merchant->fresh()->image_url);
    }

    public function test_a_merchant_still_on_the_public_copy_is_moved_straight_to_the_new_artwork(): void
    {
        Storage::fake();
        $merchant = PaymentMerchant::create(['name' => 'Ace Credits & Apex Credits', 'image_url' => self::OLD_URL]);

        $this->replacement()->up();

        $this->assertSame(Storage::disk()->url('payment-merchant/mindef-ace-apex-v2.png'), $merchant->fresh()->image_url);
    }

    public function test_the_replacement_leaves_other_merchants_alone(): void
    {
        Storage::fake();
        $paynow = PaymentMerchant::create(['name' => 'Paynow', 'image_url' => 'https://happyice-space.sgp1.digitaloceanspaces.com/payment-merchant/paynow.png']);

        $this->replacement()->up();

        Storage::disk()->assertMissing('payment-merchant/mindef-ace-apex-v2.png');
        $this->assertSame('https://happyice-space.sgp1.digitaloceanspaces.com/payment-merchant/paynow.png', $paynow->fresh()->image_url);
    }
}
