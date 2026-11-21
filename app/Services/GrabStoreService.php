<?php

namespace App\Services;

use App\Jobs\NotifyDeliveryPlatformUpdateMenu;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryPlatformStoreEvent;
use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingVend;
use App\Models\Operator;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The one writer for Grab stores and their machine bindings (plan §9, Brian
 * 2026-10-29). A store (merchantID, delivery_platform_ref_numbers) moves between
 * machines over time; each binding row is one period, never edited in place: a
 * move closes it and opens the next at the same instant. At most one open binding
 * per store and per machine, checked here under a lock. Every change is a store
 * event, with the user who made it (null = the system).
 *
 * Until step 2 (menu from the machine) a binding still names the Grab template its
 * menu is built from.
 */
class GrabStoreService
{
    public function __construct(
        protected DeliveryPlatformService $deliveryPlatformService,
        protected DeliveryProductMappingService $deliveryProductMappingService,
    ) {}

    /** A ref number already on file for this merchantID, with or without its "4-". */
    public function findDuplicate(string $refNumber, ?int $exceptId = null): ?DeliveryPlatformRefNumber
    {
        $core = DeliveryPlatformRefNumber::coreRefNumber($refNumber);

        return DeliveryPlatformRefNumber::query()
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->where(fn ($query) => $query->where('ref_number', $core)->orWhere('ref_number', 'LIKE', '%-'.$core))
            ->get()
            ->first(fn ($ref) => DeliveryPlatformRefNumber::coreRefNumber($ref->ref_number) === $core);
    }

    /** The operator's live Grab account (production first), or null. */
    public function grabAccountFor(int $operatorId): ?DeliveryPlatformOperator
    {
        return DeliveryPlatformOperator::query()
            ->where('operator_id', $operatorId)
            ->whereHas('deliveryPlatform', fn ($query) => $query->where('slug', 'grab'))
            ->orderByRaw("type = 'production' DESC")
            ->orderBy('id')
            ->first();
    }

    /**
     * Ask Grab about a merchantID with the operator's account.
     *
     * @return array{ok: bool, message: string, is_open: ?bool, code: ?int}
     */
    public function checkWithGrab(int $operatorId, string $merchantId): array
    {
        $account = $this->grabAccountFor($operatorId);
        if (! $account) {
            return ['ok' => false, 'message' => __('This operator has no Grab account in ConnectVend.'), 'is_open' => null, 'code' => null];
        }

        try {
            $response = $this->deliveryPlatformService->storeStatusFor($account, $merchantId);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => __('Could not reach Grab: :error', ['error' => $e->getMessage()]), 'is_open' => null, 'code' => null];
        }

        if (! ($response['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => __('Grab did not confirm this store (HTTP :code).', ['code' => $response['code'] ?? '?']),
                'is_open' => null,
                'code' => $response['code'] ?? null,
            ];
        }

        $isOpen = (bool) ($response['data']['isOpen'] ?? false);
        $reason = $response['data']['closeReason'] ?? null;

        return [
            'ok' => true,
            'message' => $isOpen ? __('Grab knows this store: open.') : __('Grab knows this store: closed (:reason).', ['reason' => $reason ?: '-']),
            'is_open' => $isOpen,
            'code' => $response['code'] ?? 200,
        ];
    }

    /**
     * New store. Refuses a merchantID already on file (with or without "4-"); asks
     * Grab first unless $skipGrabCheck (a store Grab has not integrated yet).
     */
    public function addStore(int $operatorId, string $refNumber, ?string $remarks, ?int $userId, bool $skipGrabCheck = false): DeliveryPlatformRefNumber
    {
        $refNumber = strtoupper(trim($refNumber));

        if ($duplicate = $this->findDuplicate($refNumber)) {
            throw ValidationException::withMessages([
                'ref_number' => __('This store is already on file as :ref.', ['ref' => $duplicate->ref_number]),
            ]);
        }

        $grabCheck = null;
        if (! $skipGrabCheck) {
            $grabCheck = $this->checkWithGrab($operatorId, $refNumber);
            if (! $grabCheck['ok']) {
                throw ValidationException::withMessages([
                    'ref_number' => $grabCheck['message'].' '.__('Tick "Save without Grab check" to save it anyway.'),
                ]);
            }
        }

        return DB::transaction(function () use ($operatorId, $refNumber, $remarks, $userId, $grabCheck) {
            $ref = DeliveryPlatformRefNumber::create([
                'delivery_platform_id' => 1,
                'operator_id' => $operatorId,
                'ref_number' => $refNumber,
                'remarks' => $remarks,
                'status' => DeliveryPlatformRefNumber::STATUS_ACTIVE,
            ]);

            $this->event($ref, DeliveryPlatformStoreEvent::EVENT_CREATED, $userId, null, null, [
                'grab_check' => $grabCheck ? $grabCheck['message'] : 'skipped',
            ]);

            // A new store never starts on an old Grab menu (Brian, 2026-11-11): it lists its machine from the start.
            $ref->update(['menu_from_machine_at' => now()]);
            $this->event($ref, DeliveryPlatformStoreEvent::EVENT_MENU_SOURCE, $userId, null, null, ['machine_menu' => true, 'new_store' => true]);

            return $ref;
        });
    }

    /**
     * A store that lists its machine once it is put on one or moved (Brian, 2026-11-11: "don't use the old menu … skip
     * it", the old Product Mapping tab is to be retired): on the machine menu already, or never switched either way —
     * every store still on a template moves over the next time it is put on a machine. Only a store someone put back
     * on its old menu stays there.
     */
    public function willUseMachineMenu(DeliveryPlatformRefNumber $ref): bool
    {
        if ($ref->usesMachineMenu()) {
            return true;
        }

        return ! DeliveryPlatformStoreEvent::query()->where('delivery_platform_ref_number_id', $ref->id)->where('event', DeliveryPlatformStoreEvent::EVENT_MENU_SOURCE)->exists();
    }

    /**
     * The template a machine-menu store is linked to: the link still needs one, but it is not what Grab gets. The one
     * most of the operator's open stores use, else its newest.
     */
    public function defaultTemplateFor(int $operatorId): ?DeliveryProductMapping
    {
        $mostUsed = DeliveryProductMappingVend::query()->withoutGlobalScopes()
            ->whereNull('end_date')
            ->whereHas('deliveryPlatformRefNumber', fn ($query) => $query->where('operator_id', $operatorId))
            // Only the operator's own templates: a store that just took this operator still sits on its old one.
            ->whereIn('delivery_product_mapping_id', DeliveryProductMapping::query()->withoutGlobalScopes()->where('operator_id', $operatorId)->select('id'))
            ->selectRaw('delivery_product_mapping_id, COUNT(*) AS n')
            ->groupBy('delivery_product_mapping_id')
            ->orderByDesc('n')
            ->value('delivery_product_mapping_id');

        return DeliveryProductMapping::query()->withoutGlobalScopes()
            ->where('operator_id', $operatorId)
            ->when($mostUsed, fn ($query) => $query->where('id', $mostUsed), fn ($query) => $query->orderByDesc('id'))
            ->first();
    }

    /**
     * A store's operator follows the machine it sells from (Brian, 2026-11-19): put on, or moved to, a machine of
     * another operator, the store becomes that operator's, so its menu, prices and staff filters follow the machine.
     * Every operator shares the one Grab partner account, so Grab sees no change. Refused when the machine's operator
     * has no Grab account in ConnectVend. The checks bind/move would fail on run first, so a refused bind changes
     * nothing. Pass the open binding when moving. Returns true when the operator changed.
     */
    public function followMachineOperator(DeliveryPlatformRefNumber $ref, Vend $vend, ?int $userId, ?DeliveryProductMappingVend $moving = null): bool
    {
        $machineOperator = (int) $vend->operator_id;
        $storeOperator = (int) $ref->operator_id;
        if (! $machineOperator || $machineOperator === $storeOperator) {
            return false;
        }

        $field = $moving ? 'vend_id' : 'vend_code';
        $this->assertNotClosedByGrab($ref, $field);
        if (! $moving) {
            $this->assertStoreFree($ref);
        }
        if (! $moving || (int) $moving->vend_id !== (int) $vend->id) {
            $this->assertMachineFree($vend, $ref);
        }
        $operator = Operator::query()->withoutGlobalScopes()->find($machineOperator);
        if (! $this->grabAccountFor($machineOperator)) {
            throw ValidationException::withMessages([
                $field => __('Machine :code belongs to :operator, which has no Grab account in ConnectVend.', [
                    'code' => $vend->code,
                    'operator' => $operator?->code ?? '#'.$machineOperator,
                ]),
            ]);
        }

        $ref->forceFill(['operator_id' => $machineOperator])->save();
        $this->event($ref, DeliveryPlatformStoreEvent::EVENT_EDITED, $userId, null, __('Operator follows machine :code', ['code' => $vend->code]), [
            'operator_id' => ['from' => $storeOperator, 'to' => $machineOperator],
        ]);

        return true;
    }

    /**
     * Put a store on a machine. Refuses a store already open on a machine (use move)
     * and a machine already carrying another store. The store takes the machine's operator.
     */
    public function bind(DeliveryPlatformRefNumber $ref, Vend $vend, ?DeliveryProductMapping $template, ?int $userId, ?string $remarks = null): DeliveryProductMappingVend
    {
        $this->assertNotClosedByGrab($ref, 'vend_code');
        $this->followMachineOperator($ref, $vend, $userId);
        $machineMenu = $this->willUseMachineMenu($ref);
        // A template picked for the store's old operator does not fit its new one; a machine-menu store takes the usual one.
        if ($template && $machineMenu && (int) $template->operator_id !== (int) $ref->operator_id) {
            $template = null;
        }
        $template ??= $machineMenu ? $this->defaultTemplateFor((int) $ref->operator_id) : null;
        if (! $template) {
            throw ValidationException::withMessages(['delivery_product_mapping_id' => __('Pick a Grab template.')]);
        }
        if ((int) $template->operator_id !== (int) $ref->operator_id) {
            throw ValidationException::withMessages([
                'delivery_product_mapping_id' => __('The Grab template belongs to another operator than this store.'),
            ]);
        }

        $binding = DB::transaction(function () use ($ref, $vend, $template, $userId, $remarks) {
            $this->lockRef($ref);
            $this->assertStoreFree($ref);
            $this->assertMachineFree($vend, $ref);

            return $this->open($ref, $vend, $template, $userId, Carbon::now(), $remarks);
        });

        $this->afterOpen($binding, resumeOnGrab: true);
        // A new store of a machine-menu operator lists its machine from the start (setMenuSource notifies Grab).
        if ($machineMenu && ! $ref->fresh()->usesMachineMenu()) {
            $this->setMenuSource($ref->fresh(), true, $userId);
        }

        return $binding;
    }

    /**
     * Move a store to another machine: the old period closes and the new one opens at
     * the same instant. The store stays open on Grab; its menu is re-sent.
     */
    public function move(DeliveryProductMappingVend $current, Vend $vend, ?DeliveryProductMapping $template, ?int $userId, string $reason = 'moved', ?string $remarks = null): DeliveryProductMappingVend
    {
        $ref = $this->refOf($current);
        if ($ref) {
            $this->assertNotClosedByGrab($ref, 'vend_id');
        }
        // Onto another operator's machine the store becomes that operator's (the Move window may have done it already).
        $operatorChanged = $ref && $this->followMachineOperator($ref, $vend, $userId, $current);
        // Unscoped: HIPL staff move client operators' stores, whose templates their operator scope hides.
        $template ??= DeliveryProductMapping::query()->withoutGlobalScopes()->find($current->delivery_product_mapping_id);
        // Its old operator's template no longer fits: a machine-menu store takes the new operator's usual one.
        if ($ref && $template && (int) $template->operator_id !== (int) $ref->operator_id && $this->willUseMachineMenu($ref->fresh())) {
            $template = $this->defaultTemplateFor((int) $ref->operator_id) ?? $template;
        } elseif ($operatorChanged && (int) $template->operator_id !== (int) $ref->operator_id) {
            throw ValidationException::withMessages(['delivery_product_mapping_id' => __('Pick a Grab template.')]);
        }

        if ((int) $vend->id === (int) $current->vend_id && (int) $template->id === (int) $current->delivery_product_mapping_id) {
            throw ValidationException::withMessages(['vend_id' => __('The store is already on this machine.')]);
        }
        // Only a template being changed is checked: prod has a HIPL store on a DCVIC
        // template (4609), which must still move as it is.
        if ((int) $template->id !== (int) $current->delivery_product_mapping_id && (int) $template->operator_id !== (int) $ref->operator_id) {
            throw ValidationException::withMessages([
                'delivery_product_mapping_id' => __('The Grab template belongs to another operator than this store.'),
            ]);
        }

        [$closed, $binding] = DB::transaction(function () use ($ref, $current, $vend, $template, $userId, $reason, $remarks) {
            $this->lockRef($ref);
            $current = DeliveryProductMappingVend::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($current->id);
            if ($current->end_date) {
                throw ValidationException::withMessages(['vend_id' => __('This binding has already ended; reload the page.')]);
            }
            if ((int) $vend->id !== (int) $current->vend_id) {
                $this->assertMachineFree($vend, $ref);
            }

            $now = Carbon::now();
            $closed = $this->close($current, $userId, $reason, $remarks, $now);
            $binding = $this->open($ref, $vend, $template, $userId, $now, $remarks, [
                'from_vend_code' => $current->vend_code,
                'from_binding_id' => $current->id,
            ]);
            // A paused store stays paused on its new machine.
            if (! $current->is_active) {
                $binding->forceFill([
                    'is_active' => false,
                    'paused_until' => $current->paused_until,
                    'pause_reason' => $current->pause_reason,
                ])->save();
            }

            return [$closed, $binding];
        });

        // A move keeps selling, but its promotions end as before (their items may not exist on the new machine).
        $this->afterClose($closed, pauseOnGrab: false, endPromotions: true);
        $this->afterOpen($binding, resumeOnGrab: false);
        // A store still on an old Grab menu moves over to its machine with the move.
        if ($this->willUseMachineMenu($ref) && ! $ref->fresh()->usesMachineMenu()) {
            $this->setMenuSource($ref->fresh(), true, $userId);
        }

        return $binding;
    }

    /** Take a store off its machine. It is paused on Grab, since nothing sells it now. */
    public function unbind(DeliveryProductMappingVend $current, ?int $userId, string $reason = 'removed', ?string $remarks = null): DeliveryProductMappingVend
    {
        $ref = $this->refOf($current);

        $closed = DB::transaction(function () use ($ref, $current, $userId, $reason, $remarks) {
            if ($ref) {
                $this->lockRef($ref);
            }
            $current = DeliveryProductMappingVend::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($current->id);
            if ($current->end_date) {
                return $current;
            }

            return $this->close($current, $userId, $reason, $remarks, Carbon::now());
        });

        // Another open binding of the same store (the 2658 leftover) still sells:
        // closing the leftover must neither pause the live one nor end its promotions
        // (they are found by merchant ID, so they are the live store's too).
        $stillOpen = $ref && $ref->openDeliveryProductMappingVends()->withoutGlobalScopes()->exists();
        $this->afterClose($closed, pauseOnGrab: ! $stillOpen, endPromotions: ! $stillOpen);

        return $closed;
    }

    public function pause(DeliveryProductMappingVend $binding, ?int $userId, ?string $reason = null, ?Carbon $until = null, array $data = []): DeliveryProductMappingVend
    {
        $binding->forceFill([
            'is_active' => false,
            'paused_until' => $until,
            'pause_reason' => $reason,
        ])->save();
        $binding->deliveryProductMappingVendChannels()->update(['is_active' => false]);

        $response = $this->safely(fn () => $this->deliveryPlatformService->pauseStore($binding), 'pause');

        $this->event($this->refOf($binding), DeliveryPlatformStoreEvent::EVENT_PAUSED, $userId, $binding, $reason, $data + [
            'until' => $until?->toDateTimeString(),
            'grab' => $response ? 'paused' : 'failed',
        ]);

        return $binding;
    }

    public function resume(DeliveryProductMappingVend $binding, ?int $userId, ?string $reason = null, array $data = []): DeliveryProductMappingVend
    {
        if ($ref = $this->refOf($binding)) {
            $this->assertNotClosedByGrab($ref, 'reason');
        }
        $binding->forceFill([
            'is_active' => true,
            'paused_until' => null,
            'pause_reason' => null,
        ])->save();
        $binding->deliveryProductMappingVendChannels()->update(['is_active' => true]);

        $this->deliveryProductMappingService->syncVendChannels($binding->delivery_product_mapping_id, $binding->vend_id);

        // Resuming is the same Grab call with isPause=false; an Inactive store stays shut.
        $grab = 'not sent (store off)';
        if ($binding->fresh()->isSelling()) {
            $grab = $this->safely(fn () => $this->deliveryPlatformService->pauseStore($binding, false), 'resume') ? 'resumed' : 'failed';
        }

        $this->event($this->refOf($binding), DeliveryPlatformStoreEvent::EVENT_RESUMED, $userId, $binding, $reason, $data + ['grab' => $grab]);

        return $binding;
    }

    /** Pauses whose "until" has passed come back on their own (scheduled). */
    public function resumeDuePauses(): int
    {
        $due = DeliveryProductMappingVend::query()->withoutGlobalScopes()
            ->whereNull('end_date')
            ->where('is_active', false)
            ->whereNotNull('paused_until')
            ->where('paused_until', '<=', Carbon::now())
            ->get();

        // A store Grab has closed for good stays paused (resume() refuses it).
        $due = $due->reject(fn ($binding) => $this->refOf($binding)?->isClosedByGrab());
        $due->each(fn ($binding) => $this->resume($binding, null, __('Pause time ended')));

        return $due->count();
    }

    /** Active / Inactive on the store itself. Off pauses Grab at once (the re-assert keeps it). */
    public function setStatus(DeliveryPlatformRefNumber $ref, bool $active, ?int $userId, ?string $reason = null): void
    {
        $status = $active ? DeliveryPlatformRefNumber::STATUS_ACTIVE : DeliveryPlatformRefNumber::STATUS_INACTIVE;
        if ((int) $ref->status === $status) {
            return;
        }
        if ($active) {
            $this->assertNotClosedByGrab($ref, 'active');
        }

        $ref->update(['status' => $status]);

        $grab = [];
        foreach ($ref->openDeliveryProductMappingVends()->withoutGlobalScopes()->get() as $binding) {
            $binding->setRelation('deliveryPlatformRefNumber', $ref);
            if (! $active) {
                $grab[$binding->vend_code] = $this->safely(fn () => $this->deliveryPlatformService->pauseStore($binding), 'switch off') ? 'paused' : 'failed';
            } elseif ($binding->isSelling()) {
                $this->deliveryProductMappingService->syncVendChannels($binding->delivery_product_mapping_id, $binding->vend_id);
                $grab[$binding->vend_code] = $this->safely(fn () => $this->deliveryPlatformService->pauseStore($binding, false), 'switch on') ? 'resumed' : 'failed';
            }
        }

        $this->event(
            $ref,
            $active ? DeliveryPlatformStoreEvent::EVENT_SWITCHED_ON : DeliveryPlatformStoreEvent::EVENT_SWITCHED_OFF,
            $userId,
            null,
            $reason,
            $grab ? ['grab' => $grab] : null,
        );
    }

    /**
     * Grab Menu & Prices: the store lists its machine's slots (true) or its old Grab
     * template (false). Grab is told to read the menu again; it changes on Grab within
     * minutes, item ids and all ("P" + product id on the machine menu).
     */
    public function setMenuSource(DeliveryPlatformRefNumber $ref, bool $machineMenu, ?int $userId): bool
    {
        if ($ref->usesMachineMenu() === $machineMenu) {
            return false;
        }

        $ref->update(['menu_from_machine_at' => $machineMenu ? now() : null]);

        app(\App\Services\Grab\Menu\GrabMenuSyncTracker::class)->record(
            \App\Models\GrabMenuSync::KIND_MENU_SOURCE,
            (int) $ref->operator_id ?: null,
            $userId,
            $machineMenu ? "Store {$ref->ref_number} put on its machine menu" : "Store {$ref->ref_number} back on its old Grab menu",
            function () use ($ref) {
                foreach ($ref->openDeliveryProductMappingVends()->withoutGlobalScopes()->get() as $binding) {
                    if ($binding->isSelling()) {
                        NotifyDeliveryPlatformUpdateMenu::debounced($binding);
                    }
                }
            },
        );

        $this->event($ref, DeliveryPlatformStoreEvent::EVENT_MENU_SOURCE, $userId, null, null, ['machine_menu' => $machineMenu]);

        return true;
    }

    /**
     * Edit a store: its merchantID (a typo fix), operator and remarks. A new merchantID
     * is asked of Grab first unless $skipGrabCheck. The operator decides which Grab
     * account serves the store, so it changes only while the store is on no machine.
     */
    public function editStore(DeliveryPlatformRefNumber $ref, string $refNumber, int $operatorId, ?string $remarks, ?int $userId, bool $skipGrabCheck = false): void
    {
        $refNumber = strtoupper(trim($refNumber));
        $remarks = ($remarks = trim((string) $remarks)) === '' ? null : $remarks;

        if ($operatorId !== (int) $ref->operator_id && $ref->openDeliveryProductMappingVends()->withoutGlobalScopes()->exists()) {
            throw ValidationException::withMessages([
                'operator_id' => __('Take the store off its machine first: a store on a machine takes the operator of that machine.'),
            ]);
        }

        if ($refNumber !== $ref->ref_number) {
            if ($duplicate = $this->findDuplicate($refNumber, $ref->id)) {
                throw ValidationException::withMessages([
                    'ref_number' => __('This store is already on file as :ref.', ['ref' => $duplicate->ref_number]),
                ]);
            }
            if (! $skipGrabCheck) {
                $grabCheck = $this->checkWithGrab($operatorId, $refNumber);
                if (! $grabCheck['ok']) {
                    throw ValidationException::withMessages([
                        'ref_number' => $grabCheck['message'].' '.__('Tick "Save without Grab check" to save it anyway.'),
                    ]);
                }
            }
        }

        DB::transaction(function () use ($ref, $refNumber, $operatorId, $remarks, $userId) {
            $this->rename($ref, $refNumber, $userId);

            $changes = [];
            if ($operatorId !== (int) $ref->operator_id) {
                $changes['operator_id'] = ['from' => (int) $ref->operator_id, 'to' => $operatorId];
            }
            if ($remarks !== $ref->remarks) {
                $changes['remarks'] = ['from' => $ref->remarks, 'to' => $remarks];
            }
            if (! $changes) {
                return;
            }

            $ref->update(['operator_id' => $operatorId, 'remarks' => $remarks]);
            $this->event($ref, DeliveryPlatformStoreEvent::EVENT_EDITED, $userId, null, null, $changes);
        });
    }

    /** A ref number edited by hand: the bindings' copies follow, and the change is on record. */
    public function rename(DeliveryPlatformRefNumber $ref, string $newRefNumber, ?int $userId): void
    {
        $old = $ref->ref_number;
        if ($old === $newRefNumber) {
            return;
        }

        if ($duplicate = $this->findDuplicate($newRefNumber, $ref->id)) {
            throw ValidationException::withMessages([
                'ref_number' => __('This store is already on file as :ref.', ['ref' => $duplicate->ref_number]),
            ]);
        }

        DB::transaction(function () use ($ref, $old, $newRefNumber, $userId) {
            $ref->update(['ref_number' => $newRefNumber]);
            DeliveryProductMappingVend::query()->withoutGlobalScopes()
                ->where('delivery_platform_ref_number_id', $ref->id)
                ->update(['platform_ref_id' => $newRefNumber]);
            $this->event($ref, DeliveryPlatformStoreEvent::EVENT_RENAMED, $userId, null, null, ['from' => $old, 'to' => $newRefNumber]);
        });
    }

    public function event(?DeliveryPlatformRefNumber $ref, string $event, ?int $userId, ?DeliveryProductMappingVend $binding = null, ?string $reason = null, ?array $data = null): ?DeliveryPlatformStoreEvent
    {
        if (! $ref) {
            return null;
        }

        return DeliveryPlatformStoreEvent::create([
            'delivery_platform_ref_number_id' => $ref->id,
            'delivery_product_mapping_vend_id' => $binding?->id,
            'vend_id' => $binding?->vend_id,
            'event' => $event,
            'reason' => $reason,
            'data' => $data,
            'user_id' => $userId,
        ]);
    }

    private function open(DeliveryPlatformRefNumber $ref, Vend $vend, DeliveryProductMapping $template, ?int $userId, Carbon $at, ?string $remarks, array $data = []): DeliveryProductMappingVend
    {
        $binding = DeliveryProductMappingVend::create([
            'bound_by' => $userId,
            'customer_id' => $vend->customer_id,
            'delivery_platform_ref_number_id' => $ref->id,
            'delivery_product_mapping_id' => $template->id,
            'is_active' => true,
            'platform_ref_id' => $ref->ref_number,
            'start_date' => $at,
            'vend_code' => $vend->code,
            'vend_id' => $vend->id,
        ]);

        $this->event($ref, DeliveryPlatformStoreEvent::EVENT_BOUND, $userId, $binding, $remarks, array_filter($data + [
            'vend_code' => $vend->code,
            'customer_id' => $vend->customer_id,
            'template' => $template->name,
        ], fn ($value) => $value !== null));

        return $binding;
    }

    private function close(DeliveryProductMappingVend $binding, ?int $userId, string $reason, ?string $remarks, Carbon $at): DeliveryProductMappingVend
    {
        $binding->forceFill([
            'end_date' => $at,
            'unbound_by' => $userId,
            'unbind_reason' => $reason,
            'unbind_remarks' => $remarks,
        ])->save();

        $this->event($this->refOf($binding), DeliveryPlatformStoreEvent::EVENT_UNBOUND, $userId, $binding, $remarks, [
            'reason' => $reason,
            'vend_code' => $binding->vend_code,
        ]);

        return $binding;
    }

    private function afterOpen(DeliveryProductMappingVend $binding, bool $resumeOnGrab): void
    {
        $this->deliveryProductMappingService->syncVendChannels($binding->delivery_product_mapping_id, $binding->vend_id);

        // A store taken off a machine was paused on Grab; it reopens with its new one.
        if ($resumeOnGrab && $binding->fresh()->isSelling()) {
            $this->safely(fn () => $this->deliveryPlatformService->pauseStore($binding, false), 'resume after bind');
        }
    }

    // The promotions ConnectVend made on this store end on Grab (as unbindVend did
    // before Grab Stores), found in Grab's own list, not in records of ours.
    private function afterClose(DeliveryProductMappingVend $binding, bool $pauseOnGrab, bool $endPromotions): void
    {
        $ref = $binding->deliveryPlatformRefNumber;
        if ($ref && $endPromotions) {
            $this->safely(fn () => app(GrabPromotionService::class)->endForStore($ref, auth()->id()), 'end promotions');
        }

        if ($pauseOnGrab) {
            $this->safely(fn () => $this->deliveryPlatformService->pauseStore($binding), 'pause after unbind');
        }
    }

    /** A store whose Grab name starts with "[CLOSED]" never sells again: no machine, no Switch on, no Resume. */
    public function assertNotClosedByGrab(DeliveryPlatformRefNumber $ref, string $field): void
    {
        if ($ref->isClosedByGrab()) {
            throw ValidationException::withMessages([
                $field => __('Grab has closed store :ref for good ([CLOSED] in its Grab name). It cannot sell again: ask Grab for a new store.', ['ref' => $ref->ref_number]),
            ]);
        }
    }

    private function assertStoreFree(DeliveryPlatformRefNumber $ref): void
    {
        $open = $ref->openDeliveryProductMappingVends()->withoutGlobalScopes()->first();
        if ($open) {
            throw ValidationException::withMessages([
                'vend_id' => __('This store is already on machine :code. Use Move to put it on another machine.', ['code' => $open->vend_code]),
            ]);
        }
    }

    private function assertMachineFree(Vend $vend, DeliveryPlatformRefNumber $ref): void
    {
        $open = DeliveryProductMappingVend::query()->withoutGlobalScopes()
            ->whereNull('end_date')
            ->where('vend_id', $vend->id)
            ->where(fn ($query) => $query->whereNull('delivery_platform_ref_number_id')->orWhere('delivery_platform_ref_number_id', '!=', $ref->id))
            ->lockForUpdate()
            ->first();
        if ($open) {
            throw ValidationException::withMessages([
                'vend_id' => __('Machine :code already carries Grab store :ref. Remove or move that store first.', [
                    'code' => $vend->code,
                    'ref' => $open->deliveryPlatformRefNumber?->ref_number ?? $open->platform_ref_id,
                ]),
            ]);
        }
    }

    private function lockRef(DeliveryPlatformRefNumber $ref): void
    {
        DeliveryPlatformRefNumber::query()->whereKey($ref->id)->lockForUpdate()->first();
    }

    private function refOf(DeliveryProductMappingVend $binding): ?DeliveryPlatformRefNumber
    {
        return $binding->deliveryPlatformRefNumber
            ?? DeliveryPlatformRefNumber::query()->where('ref_number', $binding->platform_ref_id)->first();
    }

    // A Grab call never undoes a change ops made in mark1: it is logged and shown on
    // the store's history; the 10-min pause re-assert retries pauses.
    private function safely(callable $call, string $what)
    {
        try {
            return $call();
        } catch (\Throwable $e) {
            Log::warning("Grab store {$what} failed", ['error' => $e->getMessage()]);

            return null;
        }
    }
}
