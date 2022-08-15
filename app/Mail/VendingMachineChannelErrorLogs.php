<?php

namespace App\Mail;

use App\Http\Resources\VendingMachineResource;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendingMachineChannelErrorLogs extends Mailable
{
    use Queueable, SerializesModels;

    public $vendingMachines;
    public $now;
    public $intervalHours;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($vendingMachines, $intervalHours)
    {
        $this->vendingMachines = $vendingMachines;
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
