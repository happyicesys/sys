<?php

namespace App\Mail;

use App\Models\Operator;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class VendChannelErrorLogsMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public ?Operator $operator;
    /** @var Collection  // keyed by vend_id => Collection<VendChannelErrorLog> */
    public Collection $vendErrorsByVend;
    public Carbon $now;

    public function __construct(?Operator $operator, Collection $vendErrorsByVend)
    {
        $this->operator = $operator;
        $this->vendErrorsByVend = $vendErrorsByVend;
        $this->now = Carbon::now();
    }

    public function build()
    {
        return $this
            ->subject('Channel Error Logs - ' . ($this->operator?->name ?? 'Global') . ' (' . $this->now->format('Y-m-d') . ')')
            ->view('emails.vend-channel-error-logs');
    }
}
