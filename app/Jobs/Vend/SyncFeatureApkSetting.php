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
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->vend->update([
            'is_enable_grab_collection' => $this->input['isEnableGrabCollection'] ?? null,
            'is_enable_soft_keyboard_qr_pay' => $this->input['isEnableSoftKeyboardQrPay'] ?? null,
            'is_enable_soft_keyboard_cash_pay' => $this->input['isEnableSoftKeyboardCashPay'] ?? null,
            'is_enable_soft_keyboard_credit_card_pay' => $this->input['isEnableSoftKeyboardCreditCardPay'] ?? null,
            'is_enable_soft_keyboard_hid_pay' => $this->input['isEnableSoftKeyboardHidPay'] ?? null,
            'has_display_screen' => $this->input['hasDisplayScreen'] ?? null,
        ]);
    }
}
