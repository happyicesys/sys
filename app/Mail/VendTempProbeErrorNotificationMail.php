<?php

namespace App\Mail;

use App\Models\Vend;
use App\Support\TempProbe;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

/** (1C) Temperature probe error (App\Support\TempProbe). */
class VendTempProbeErrorNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable; // avoid SerializesModels to prevent model rehydrate issues

    public $baseUrl;

    public $now;

    public int $vendId;

    public string $label;

    public $vend; // populated in build()

    public $vendPrefixName;

    public string $probes = '';

    public ?float $t2 = null;

    public function __construct(int $vendId, string $label)
    {
        $this->baseUrl = env('APP_URL');
        $this->now = Carbon::now();
        $this->vendId = $vendId;
        $this->label = $label;
    }

    public function build()
    {
        $vend = Vend::withoutGlobalScopes()->with('vendPrefix', 'customer')->findOrFail($this->vendId);
        $this->vend = $vend;
        $this->vendPrefixName = $vend->vendPrefix ? $vend->vendPrefix->name : '';
        $this->probes = implode(' & ', TempProbe::probes($vend)) ?: 'T1';

        // The probe that still reads, so the email says how cold the machine is now.
        $t2 = is_array($vend->parameter_json) ? ($vend->parameter_json['t2'] ?? null) : null;
        if (is_numeric($t2) && ! TempProbe::isError($t2)) {
            $this->t2 = round($t2 / 10, 1);
        }

        return $this
            ->subject($vend->codeLabel().': (1C) Alert on Temperature Probe Error '.$this->probes.' ('.$this->label.')')
            ->view('emails.vend-temp-probe-error-notification');
    }
}
