<?php

namespace App\Mail;

use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class VendOfflineNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable; // avoid SerializesModels to prevent model rehydrate issues

    public $baseUrl;
    public $now;
    public int $vendId;
    public int $thresholdMinutes;
    public ?string $label = null;
    public $vend; // populated in build()
    public $vendPrefixName;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(int $vendId, int $thresholdMinutes, ?string $label = null)
    {
        $this->baseUrl = env('APP_URL');
        $this->now = Carbon::now();
        $this->vendId = $vendId;
        $this->thresholdMinutes = $thresholdMinutes;
        $this->label = $label;
    }

    // public function envelope()
    // {
    //     return new Envelope(
    //         subject: 'Order Shipped',
    //     );
    // }
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Fetch fresh to avoid serialization restore issues and ensure relations
        $vend = Vend::withoutGlobalScopes()->with('vendPrefix', 'customer')->findOrFail($this->vendId);
        $this->vend = $vend;
        $this->vendPrefixName = $vend->vendPrefix ? $vend->vendPrefix->name : '';

        $subject = 'ID: ' . $vend->code . ' Machine Offline Alert >= ' . $this->thresholdMinutes . ' mins (' . $this->now->format('y-m-d') . ')';

        if ($this->label) {
            $subject = $vend->code . ': Alert on Lost of Connectivity or Electricity (' . $this->label . ')';
        }

        return $this
            ->subject($subject)
            ->view('emails.vend-offline-notification');
    }
}
