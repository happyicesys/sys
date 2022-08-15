<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VMChannelErrorLogsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $vendingMachineChannelErrorLogs;
    public $intervalHours;
    public $now;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($vendingMachineChannelErrorLogs, $intervalHours)
    {
        $this->vendingMachineChannelErrorLogs = $vendingMachineChannelErrorLogs;
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
            ->view('emails.vending-machine-channel-error-logs');
    }
}
