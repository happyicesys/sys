<?php

namespace App\Mail;

use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;

class VendPowerRestoredNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable; // avoid SerializesModels to prevent model rehydrate issues

    public $baseUrl;
    public $now;
    public int $vendId;
    public $vend; // populated in build()
    public $vendPrefixName;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(int $vendId)
    {
        $this->baseUrl = env('APP_URL');
        $this->now = Carbon::now();
        $this->vendId = $vendId;
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
        $vend = Vend::withoutGlobalScopes()->with('vendPrefix')->findOrFail($this->vendId);
        $this->vend = $vend;
        $this->vendPrefixName = $vend->vendPrefix ? $vend->vendPrefix->name : '';
        return $this
        ->subject('ID: '.$vend->code.' Machine Offline Alert >= 50 mins ('.$this->now->format('y-m-d').') - Recovered')
            ->view('emails.power-restored-alert');
    }
}
