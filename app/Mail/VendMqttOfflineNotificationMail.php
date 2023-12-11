<?php

namespace App\Mail;

use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VendMqttOfflineNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $now;
    public $vend;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Vend $vend)
    {
        $this->now = Carbon::now();
        $this->vend = $vend;
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
            ->subject('VM MQTT Offline Alert > 30 mins ('.$this->now->format('y-m-d').')')
            ->view('emails.vend-mqtt-offline-notification');
    }
}
