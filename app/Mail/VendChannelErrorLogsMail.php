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
    public int $intervalHours;

    public function __construct(?Operator $operator, Collection $vendErrorsByVend, int $intervalHours = 24)
    {
        $this->operator = $operator;
        $this->vendErrorsByVend = $vendErrorsByVend;
        $this->now = Carbon::now();
        $this->intervalHours = $intervalHours;
    }

    public function build()
    {
        // Flatten grouped logs to match legacy view expectation
        $vendChannelErrorLogs = $this->vendErrorsByVend->values()->flatten(1);

        return $this
            ->subject('Channel Error Logs - ' . ($this->operator?->name ?? 'Global') . ' (' . $this->now->format('Y-m-d') . ')')
            ->view('emails.vend-channel-error-logs')
            ->with([
                'vendChannelErrorLogs' => $vendChannelErrorLogs,
                'intervalHours' => $this->intervalHours,
                'now' => $this->now,
            ]);
    }
}
