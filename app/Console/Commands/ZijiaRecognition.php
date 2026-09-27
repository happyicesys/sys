<?php

namespace App\Console\Commands;

use App\Models\SmartFreezerRecognition;
use App\Services\SmartFreezer\FreezerRecognitionService;
use Illuminate\Console\Command;

/**
 * Shows one freezer door-session recognition and, on request, moves it along by hand:
 *  --retry     puts a FAILED row back to pending (it will be billed again); with --force also a row
 *              stuck in `submitting` after a worker died — whose first call may have reached them.
 *  --submit    asks the algorithm (a metered call — only a `pending` row is ever sent).
 *  --evaluate  re-checks a completed result against the sale (after a late TRADE, or once barcodes
 *              were filled in; the 10-minute sweep does this on its own for the last week).
 */
class ZijiaRecognition extends Command
{
    protected $signature = 'smart-freezer:zijia-recognition {id : smart_freezer_recognitions.id}
        {--retry : put a failed recognition back to pending}
        {--force : with --retry, also release one stuck in submitting}
        {--submit}
        {--evaluate}';

    protected $description = 'Show, submit or re-evaluate a freezer AI recognition';

    public function handle(FreezerRecognitionService $recognitions): int
    {
        $recognition = SmartFreezerRecognition::find($this->argument('id'));
        if ($recognition === null) {
            $this->error('No such recognition.');

            return self::FAILURE;
        }

        if ($this->option('retry')) {
            $released = $recognitions->retry($recognition, (bool) $this->option('force'));
            $released
                ? $this->info('Back to pending — it will be billed again when submitted.')
                : $this->warn("Not released: it is {$recognition->status}.".($this->option('force') ? '' : ' (--force also releases one stuck in submitting.)'));
        }
        if ($this->option('submit')) {
            $recognition = $recognitions->submit($recognition);
        }
        if ($this->option('evaluate')) {
            $recognition = $recognitions->evaluate($recognition);
        }

        $this->table(['field', 'value'], [
            ['vend', $recognition->vend?->code ?? '—'],
            ['trade id', $recognition->trade_id],
            ['session ref', $recognition->session_ref ?? '—'],
            ['status', $recognition->status],
            ['waiting on', $recognition->status_reason ?? $recognitions->blocker($recognition) ?? '—'],
            ['request id', $recognition->request_id ?? '—'],
            ['algorithm status', $recognition->order_status ?? '—'],
            ['verdict', $recognition->verdict ?? '—'],
            ['sale', $recognition->vend_transaction_id ?? '—'],
            ['callback signature', $recognition->callback_verified === null ? '—' : ($recognition->callback_verified ? 'verified' : 'NOT verified')],
        ]);
        foreach ((array) $recognition->verdict_lines as $line) {
            $this->line(sprintf('  product %s (%s): paid %d, taken %d', $line['product_id'] ?? '?', $line['code'] ?? 'no barcode', $line['paid'], $line['taken']));
        }

        return self::SUCCESS;
    }
}
