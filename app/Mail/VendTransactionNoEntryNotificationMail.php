<?php

namespace App\Mail;

use App\Models\Operator;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class VendTransactionNoEntryNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable; // avoid SerializesModels to prevent model rehydrate issues

    public $baseUrl;
    public $now;
    public ?int $operatorId;
    public array $vends;

    public function __construct(?int $operatorId, array $vends)
    {
        $this->baseUrl = env('APP_URL');
        $this->now = Carbon::now();
        $this->operatorId = $operatorId;
        $this->vends = $vends;
    }

    public function build()
    {
        $operator = $this->operatorId
            ? Operator::withoutGlobalScopes()->find($this->operatorId)
            : null;

        $vends = collect($this->vends)
            ->sortBy(fn ($vend) => sprintf('%08s', (string) ($vend['code'] ?? '')))
            ->values();

        $subject = sprintf(
            'Machines Without Transactions (%s) - %d machine(s)',
            $this->now->format('Y-m-d'),
            $vends->count()
        );

        return $this
            ->subject($subject)
            ->view('emails.vend-transaction-no-entry-notification', [
                'baseUrl' => $this->baseUrl,
                'generatedAt' => $this->now,
                'operator' => $operator,
                'vends' => $vends,
            ]);
    }
}
