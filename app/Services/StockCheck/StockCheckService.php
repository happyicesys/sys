<?php

namespace App\Services\StockCheck;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Models\OpsJob;
use App\Models\StockCheck;
use App\Models\StockCheckChannel;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Services\OpsJobStops\StopCodeGenerator;
use App\Services\StockCheck\Sync\ChannelSyncResult;
use App\Services\StockCheck\Sync\StockCheckSyncTarget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every write to a stock check ("Stock Count" in the UI): draw the sample,
 * re-draw it, take the driver's counts, reopen, and sync the result back to
 * the system quantity. Controllers validate and authorise; the rules live here.
 */
class StockCheckService
{
    /** @param  iterable<StockCheckSyncTarget>  $syncTargets */
    public function __construct(
        private StopCodeGenerator $codes,
        private StockCheckSampler $sampler,
        private iterable $syncTargets,
    ) {}

    /**
     * @param  array{is_random:bool, sample_size:?int, product_ids:?array, remarks:?string, sequence:int|float|null}  $options
     */
    public function create(OpsJob $opsJob, Vend $vend, array $options, User $by): StockCheck
    {
        $isRandom = (bool) ($options['is_random'] ?? false);
        $sampleSize = $isRandom ? (int) ($options['sample_size'] ?? 0) : null;
        $productIds = array_values(array_filter(array_map('intval', $options['product_ids'] ?? [])));

        if ($isRandom && $sampleSize < 1) {
            throw ValidationException::withMessages(['sample_size' => 'Say how many channels to draw.']);
        }

        $drawn = $this->drawFor($vend, $isRandom, $sampleSize, $productIds);

        return DB::transaction(function () use ($opsJob, $vend, $options, $isRandom, $sampleSize, $productIds, $drawn, $by) {
            $check = $this->codes->createWithNextCode(
                StockCheck::class,
                (int) $opsJob->operator_id,
                fn (int $code) => StockCheck::create([
                    'code' => $code,
                    'operator_id' => $opsJob->operator_id,
                    'ops_job_id' => $opsJob->id,
                    'vend_id' => $vend->id,
                    'customer_id' => $vend->customer_id,
                    'sequence' => $options['sequence'] ?? null,
                    'status' => StockCheck::STATUS_PENDING,
                    'is_random' => $isRandom,
                    'sample_size' => $sampleSize,
                    'product_filter' => $productIds ?: null,
                    'remarks' => $options['remarks'] ?? null,
                    'created_by' => $by->id,
                    'updated_by' => $by->id,
                ]),
            );

            $this->writeChannels($check, $drawn);

            return $check;
        });
    }

    /** Throw the sample away and draw again under the same rule. */
    public function redraw(StockCheck $check, User $by): StockCheck
    {
        $this->assertPending($check);

        $drawn = $this->drawFor($check->vend, $check->is_random, $check->sample_size, $check->product_filter ?? []);

        DB::transaction(function () use ($check, $drawn, $by) {
            $check->channels()->delete();
            $this->writeChannels($check, $drawn);
            $check->update(['updated_by' => $by->id]);
        });

        return $check;
    }

    /**
     * Take the driver's counts. Every drawn channel must be answered — picking
     * the same number the system shows is a valid answer, leaving one blank is
     * not. `system_qty` is frozen here, at the instant of submit.
     *
     * @param  array<int, array{id:int, counted_qty:int|string|null, note?:?string}>  $lines
     */
    public function submit(StockCheck $check, array $lines, User $by): StockCheck
    {
        $this->assertPending($check);

        $answers = collect($lines)->keyBy(fn ($line) => (int) ($line['id'] ?? 0));
        $channels = $check->channels()->with('vendChannel')->get();
        $errors = [];

        foreach ($channels as $channel) {
            $answer = $answers->get($channel->id);
            $counted = $answer['counted_qty'] ?? null;

            if ($counted === null || $counted === '') {
                $errors["channels.{$channel->id}"] = "Channel {$channel->vend_channel_code}: choose the real quantity.";

                continue;
            }

            $ceiling = $this->countCeiling($channel);

            if ((int) $counted < 0 || (int) $counted > $ceiling) {
                $errors["channels.{$channel->id}"] = "Channel {$channel->vend_channel_code}: must be between 0 and {$ceiling}.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($check, $channels, $answers, $by) {
            foreach ($channels as $channel) {
                $answer = $answers->get($channel->id);
                // A machine that reports no quantity (a freezer before its ledger
                // exists) has nothing to compare against: keep the count, no variance.
                $system = $channel->vendChannel?->qty;
                $counted = (int) $answer['counted_qty'];

                $channel->update([
                    'system_qty' => $system,
                    'counted_qty' => $counted,
                    'variance_qty' => $system === null ? null : $counted - (int) $system,
                    'note' => isset($answer['note']) ? mb_substr(trim((string) $answer['note']), 0, 500) ?: null : null,
                ]);
            }

            $check->update([
                'status' => StockCheck::STATUS_COMPLETED,
                'counted_at' => now(),
                'counted_by' => $by->id,
                'updated_by' => $by->id,
            ]);
        });

        return $check;
    }

    public function undo(StockCheck $check, User $by): StockCheck
    {
        abort_unless($check->isCompleted(), 422, 'Only a counted stock count can be reopened.');
        abort_if($check->hasSyncedChannels(), 422, 'This count has already been synced to the system quantity and can no longer be reopened.');

        DB::transaction(function () use ($check, $by) {
            $check->channels()->update([
                'system_qty' => null, 'counted_qty' => null, 'variance_qty' => null,
            ]);
            $check->update([
                'status' => StockCheck::STATUS_PENDING,
                'counted_at' => null,
                'counted_by' => null,
                'undo_counted_at' => now(),
                'undo_counted_by' => $by->id,
                'updated_by' => $by->id,
            ]);
        });

        return $check;
    }

    public function cancel(StockCheck $check, User $by): StockCheck
    {
        $this->assertPending($check);

        $check->update([
            'status' => StockCheck::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $by->id,
            'updated_by' => $by->id,
        ]);

        return $check;
    }

    public function updateHeader(StockCheck $check, array $fields, User $by): StockCheck
    {
        $check->update($fields + ['updated_by' => $by->id]);

        return $check;
    }

    public function delete(StockCheck $check): void
    {
        abort_if($check->hasSyncedChannels(), 422, 'A synced stock count is history and cannot be deleted.');

        $check->delete(); // channels cascade
    }

    // ------------------------------------------------------------------ sync

    public function syncTargetFor(Vend $vend): StockCheckSyncTarget
    {
        foreach ($this->syncTargets as $target) {
            if ($target->supports($vend)) {
                return $target;
            }
        }

        abort(500, 'No stock check sync target supports this machine.');
    }

    /**
     * Apply every unsynced mismatch to the system quantity.
     *
     * @return ChannelSyncResult[]
     */
    public function sync(StockCheck $check, User $by): array
    {
        // ---- validate before ----------------------------------------------------
        abort_unless($check->isCompleted(), 422, 'Count first, then sync.');

        $target = $this->syncTargetFor($check->vend);

        if ($refusal = $target->refusal($check->vend)) {
            throw ValidationException::withMessages(['sync' => $refusal]);
        }

        $pending = $check->channels->filter(fn (StockCheckChannel $c) => $c->hasVariance() && ! $c->isSynced());

        if ($pending->isEmpty()) {
            throw ValidationException::withMessages(['sync' => 'Nothing to sync: every counted channel already matches.']);
        }

        // ---- apply (each channel validates itself before and after) ------------
        $results = $pending->map(fn (StockCheckChannel $c) => $target->apply($c, $by))->values()->all();

        if (collect($results)->contains(fn (ChannelSyncResult $r) => $r->applied)) {
            $check->update(['synced_at' => now(), 'synced_by' => $by->id, 'updated_by' => $by->id]);
            // Dashboards read the denormalised channel JSON on the vend row.
            SaveVendChannelsJson::dispatch($check->vend_id)->onQueue('default');
        }

        return $results;
    }

    // --------------------------------------------------------------- internals

    /** @return Collection<int, VendChannel> */
    private function drawFor(Vend $vend, bool $isRandom, ?int $sampleSize, array $productIds): Collection
    {
        $eligible = $this->sampler->eligible(
            VendChannel::query()->where('vend_id', $vend->id)->get(),
            $productIds,
        );

        if ($eligible->isEmpty()) {
            throw ValidationException::withMessages([
                'vend_id' => 'No channel to count: this machine has no stocked channel'
                    .($productIds ? ' holding the chosen product(s).' : '.'),
            ]);
        }

        return $this->sampler->draw($eligible, $isRandom, $sampleSize);
    }

    /** @param  Collection<int, VendChannel>  $drawn */
    private function writeChannels(StockCheck $check, Collection $drawn): void
    {
        foreach ($drawn as $vendChannel) {
            $check->channels()->create([
                'vend_channel_id' => $vendChannel->id,
                'vend_channel_code' => $vendChannel->code,
                'product_id' => $vendChannel->product_id,
                'capacity' => (int) $vendChannel->capacity,
                'amount' => (int) $vendChannel->amount,
            ]);
        }
    }

    /** The dropdown's top value: capacity, or the system figure when that is higher. */
    public function countCeiling(StockCheckChannel $channel): int
    {
        return max((int) $channel->capacity, (int) ($channel->vendChannel?->qty ?? 0));
    }

    private function assertPending(StockCheck $check): void
    {
        abort_unless($check->isPending(), 422, 'This stock count is '.strtolower($check->statusName()).' — reopen it first.');
    }
}
