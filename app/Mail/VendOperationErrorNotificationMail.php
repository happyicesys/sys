<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VendOperationErrorNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable; // avoid SerializesModels to prevent model rehydrate issues

    public $baseUrl;
    public $now;
    public int $vendId;
    public string $alertType;
    public string $label;
    public $vend; // populated in build()
    public $vendPrefixName;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(int $vendId, string $alertType, string $label)
    {
        $this->baseUrl = env('APP_URL');
        $this->now = now();
        $this->vendId = $vendId;
        $this->alertType = $alertType;
        $this->label = $label;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Fetch fresh to avoid serialization restore issues and ensure relations
        $vend = \App\Models\Vend::withoutGlobalScopes()->with('vendPrefix', 'customer')->findOrFail($this->vendId);
        $this->vend = $vend;
        $this->vendPrefixName = $vend->vendPrefix ? $vend->vendPrefix->name : '';

        // Determine title based on alert type
        $title = '';
        switch ($this->alertType) {
            case \App\Models\VendSmartAlert::TYPE_T2_BELOW_MINUS_25:
                $title = 'A) T2, below -25°C';
                break;
            case \App\Models\VendSmartAlert::TYPE_TEMPS_ABOVE_0:
                $title = 'B) T1 & or T2, above 0°C';
                break;
            case \App\Models\VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8:
                $title = 'C) T1 & or T2, above -8°C';
                break;
            case \App\Models\VendSmartAlert::TYPE_NOT_REACH_MINUS_18:
                $title = 'D) T1 & or T2, did not reach -18°C';
                break;
            default:
                $title = 'Operation Error / Critical Parts Failure';
        }

        $subject = $vend->code . ': ' . $title . ' (' . $this->label . ')';

        return $this
            ->subject($subject)
            ->view('emails.vend-operation-error-notification')
            ->with([
                'title' => $title,
                'alertType' => $this->alertType,
                'label' => $this->label,
            ]);
    }
}
