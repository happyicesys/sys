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
    use Queueable, SerializesModels;

    public $baseUrl;
    public $now;
    public $vend;
    public $vendPrefixName;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Vend $vend)
    {
        $this->baseUrl = env('APP_URL');
        $this->now = Carbon::now();
        $this->vend = $vend;
        $this->vendPrefixName = $vend->vendPrefix ? $vend->vendPrefix->name : '';
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
        return $this
            ->subject('ID: '.$this->vend->code.' Machine Back Online ('.$this->now->format('y-m-d').')')
            ->view('emails.power-restored-alert');
    }
}
