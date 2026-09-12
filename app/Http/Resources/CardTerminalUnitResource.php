<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CardTerminalUnitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // Read-only context so ops can see a TID is already in use before
        // assigning it. `bindings` is eager-loaded already filtered to the
        // binding effective today (CardTerminalUnitController@index); the
        // binding itself is only ever created on the machine Setting/Edit page.
        $binding = $this->resource->relationLoaded('bindings')
            ? $this->bindings->first()
            : null;

        return [
            'id' => $this->id,
            'card_terminal_id' => $this->card_terminal_id,
            'card_terminal_name' => $this->company?->name,
            'terminal_id' => $this->terminal_id,
            // Auresys' own EZ terminal ID, where ops used to type "EZTID: …"
            // into remarks. Shown under the TID, not as its own column.
            'auresys_terminal_id' => $this->auresys_terminal_id,
            'remarks' => $this->remarks,
            // Hardware batch + "Auto refund?" (Brian's flag, seeded from the
            // partner workbook; null = unknown). Informational since 2026-09-09:
            // the reconciler's "NA in NETS" tick follows the report, not this.
            'batch' => $this->batch,
            'is_will_auto_refund' => $this->willAutoRefund(),
            'auto_refund_flag_source' => $this->auto_refund_flag_source,
            'auto_refund_stats' => $this->auto_refund_stats_json,
            // vend id as well as code: the Machine ID cell links straight to
            // that machine's Setting/Edit page (/settings/vend/{id}/update),
            // which is where its terminal binding is actually changed.
            'current_vend_id' => $binding?->vend?->id,
            'current_vend_code' => $binding?->vend?->code,
            'current_vend_name' => $binding?->vend?->name,
            // The SITE under that machine, shown as "<ref id> - <name>".
            // ref_id is customers.id + RUNNING_NUMBER_INIT — the number the
            // rest of mark1 calls the Site ID, not the raw primary key.
            'current_site_ref_id' => $binding?->vend?->customer
                ? $binding->vend->customer->id + \App\Models\Customer::RUNNING_NUMBER_INIT
                : null,
            'current_site_name' => $binding?->vend?->customer?->name,
            // When TODAY's binding was recorded and by whom — "sys" for every
            // unattended path (settlement auto-match, importer). `bound_at` is
            // the record time, not bound_from, so a back-dated binding still
            // says when someone actually did it. `binding_history` is the last
            // three machines, hung on the model by the controller.
            'bound_at' => $binding?->created_at?->toIso8601String(),
            'bound_by' => $binding?->boundByLabel(),
            'binding_history' => $this->resource->binding_history ?? [],
        ];
    }
}
