<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendChannelErrorLogsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $vendChannelErrorLogs;
    public $intervalHours;
    public $now;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($vendChannelErrorLogs, $intervalHours)
    {
        $this->vendChannelErrorLogs = $vendChannelErrorLogs;
        $this->intervalHours = $intervalHours;
        $this->now = Carbon::now();
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->subject('Vending Machine Channel Error Logs ('.$this->now->format('y-m-d').')')
            ->view('emails.vend-channel-error-logs');
    }
}
