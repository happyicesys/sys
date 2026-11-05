<?php

namespace App\Mail;

use App\Models\Vend;
use App\Support\ControllerLink;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class VendControllerSilentNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable; // avoid SerializesModels to prevent model rehydrate issues

    public $baseUrl;

    public $now;

    public int $vendId;

    public string $label;

    public string $state;

    public $vend; // populated in build()

    public $vendPrefixName;

    public function __construct(int $vendId, string $label, string $state)
    {
        $this->baseUrl = env('APP_URL');
        $this->now = Carbon::now();
        $this->vendId = $vendId;
        $this->label = $label;
        $this->state = $state;
    }

    public function build()
    {
        // Fetch fresh to avoid serialization restore issues and ensure relations
        $vend = Vend::withoutGlobalScopes()->with('vendPrefix', 'customer')->findOrFail($this->vendId);
        $this->vend = $vend;
        $this->vendPrefixName = $vend->vendPrefix ? $vend->vendPrefix->name : '';

        $title = $this->state === ControllerLink::STATE_SILENT
            ? 'Alert on Controller Not Responding'
            : 'Alert on Machine Data Not Arriving';

        return $this
            ->subject($vend->codeLabel().': (1B) '.$title.' ('.$this->label.')')
            ->view('emails.vend-controller-silent-notification');
    }
}
