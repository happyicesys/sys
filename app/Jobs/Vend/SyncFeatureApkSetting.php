<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncFeatureApkSetting implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * FEATUREAPKSETTING keys, in APK spelling, mapped to their vend column.
     *
     * Not every board sends every key: the small-board (ZC-83A) DTO carries only
     * isEnableGrabCollection and hasDisplayScreen, because that build has ONE
     * "Enable Soft Keyboard module" switch rather than the big board's four
     * per-payment-method flags, and mapping one onto four would invent data.
     */
    private const COLUMN_BY_KEY = [
        'isEnableGrabCollection' => 'is_enable_grab_collection',
        'isEnableSoftKeyboardQrPay' => 'is_enable_soft_keyboard_qr_pay',
        'isEnableSoftKeyboardCashPay' => 'is_enable_soft_keyboard_cash_pay',
        'isEnableSoftKeyboardCreditCardPay' => 'is_enable_soft_keyboard_credit_card_pay',
        'isEnableSoftKeyboardHidPay' => 'is_enable_soft_keyboard_hid_pay',
        'hasDisplayScreen' => 'has_display_screen',
    ];

    protected $input;

    protected $vend;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($input, Vend $vend)
    {
        $this->input = $input;
        $this->vend = $vend;
    }

    /**
     * Write only the keys this frame actually carried.
     *
     * Until 2026-09-06 every column was written with `?? null`, so a key the board did
     * not send was ERASED rather than left alone. That was harmless while only the
     * big-board build sent this message (it sends all six keys), but the small-board
     * build started sending FEATUREAPKSETTING in its v11 series with a narrower DTO —
     * which would have nulled the four soft-keyboard columns on every MQTT connect,
     * silently undoing whatever had been set on the mark1 Setting page.
     *
     * "Absent" and "explicitly null" are treated identically on purpose: both mean the
     * board is not reporting that feature, and neither is a reason to discard a value
     * already on the row.
     */
    public function handle()
    {
        $attributes = [];

        foreach (self::COLUMN_BY_KEY as $key => $column) {
            if (($this->input[$key] ?? null) !== null) {
                $attributes[$column] = $this->input[$key];
            }
        }

        if ($attributes === []) {
            return;
        }

        $this->vend->update($attributes);
    }
}
