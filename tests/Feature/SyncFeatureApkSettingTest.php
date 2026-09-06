<?php

namespace Tests\Feature;

use App\Jobs\Vend\SyncFeatureApkSetting;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FEATUREAPKSETTING ingestion: a board reports the optional features it has on.
 *
 * The property under test is that a key the board did NOT send leaves its column
 * alone. It used to null it (`?? null` per column), which was invisible while only
 * the big-board build sent this message — that build sends all six keys. The
 * small-board (ZC-83A) build began sending FEATUREAPKSETTING in its v11 series with
 * a deliberately narrower DTO (no per-payment-method soft-keyboard flags, because
 * that board has one soft-keyboard switch, not four), so the old behaviour would
 * have wiped those four columns on every MQTT connect.
 */
class SyncFeatureApkSettingTest extends TestCase
{
    use RefreshDatabase;

    /** No VendFactory exists; `code` is the only required column. */
    private function makeVend(int $code): Vend
    {
        return Vend::forceCreate(['code' => $code]);
    }

    public function test_big_board_frame_writes_every_reported_column(): void
    {
        $vend = $this->makeVend(9101);

        (new SyncFeatureApkSetting([
            'isEnableGrabCollection' => true,
            'isEnableSoftKeyboardQrPay' => true,
            'isEnableSoftKeyboardCashPay' => false,
            'isEnableSoftKeyboardCreditCardPay' => true,
            'isEnableSoftKeyboardHidPay' => false,
            'hasDisplayScreen' => true,
        ], $vend))->handle();

        $vend->refresh();

        $this->assertTrue($vend->is_enable_grab_collection);
        $this->assertTrue($vend->is_enable_soft_keyboard_qr_pay);
        $this->assertFalse($vend->is_enable_soft_keyboard_cash_pay);
        $this->assertTrue($vend->is_enable_soft_keyboard_credit_card_pay);
        $this->assertFalse($vend->is_enable_soft_keyboard_hid_pay);
        $this->assertTrue($vend->has_display_screen);
    }

    public function test_small_board_frame_leaves_unreported_columns_untouched(): void
    {
        $vend = $this->makeVend(9102);
        $vend->forceFill([
            'is_enable_soft_keyboard_qr_pay' => true,
            'is_enable_soft_keyboard_cash_pay' => true,
            'is_enable_soft_keyboard_credit_card_pay' => true,
            'is_enable_soft_keyboard_hid_pay' => true,
        ])->save();

        // Exactly what mark1-apk-small's SettingMessagePara emits.
        (new SyncFeatureApkSetting([
            'isEnableGrabCollection' => false,
            'hasDisplayScreen' => true,
        ], $vend))->handle();

        $vend->refresh();

        $this->assertFalse($vend->is_enable_grab_collection);
        $this->assertTrue($vend->has_display_screen);

        $this->assertTrue($vend->is_enable_soft_keyboard_qr_pay);
        $this->assertTrue($vend->is_enable_soft_keyboard_cash_pay);
        $this->assertTrue($vend->is_enable_soft_keyboard_credit_card_pay);
        $this->assertTrue($vend->is_enable_soft_keyboard_hid_pay);
    }

    public function test_explicit_null_is_treated_as_not_reported(): void
    {
        $vend = $this->makeVend(9103);
        $vend->forceFill(['has_display_screen' => true])->save();

        (new SyncFeatureApkSetting([
            'isEnableGrabCollection' => true,
            'hasDisplayScreen' => null,
        ], $vend))->handle();

        $vend->refresh();

        $this->assertTrue($vend->is_enable_grab_collection);
        $this->assertTrue($vend->has_display_screen);
    }

    public function test_frame_with_no_known_keys_writes_nothing(): void
    {
        $vend = $this->makeVend(9104);
        $vend->forceFill(['has_display_screen' => true])->save();
        $updatedAt = $vend->fresh()->updated_at;

        (new SyncFeatureApkSetting(['Type' => 'FEATUREAPKSETTING', 'vid' => 9104], $vend))->handle();

        $vend->refresh();

        $this->assertTrue($vend->has_display_screen);
        $this->assertEquals($updatedAt, $vend->updated_at);
    }
}
