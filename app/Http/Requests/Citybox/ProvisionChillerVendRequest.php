<?php

namespace App\Http\Requests\Citybox;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Import a Smart Chiller from a CityBox device. The Site is OPTIONAL and never
 * made here (Brian, 2026-09-19: "Site — Primary: Sys"): a machine arrives with
 * no site, the Site is created in mark1 like any other, and bound on Machine
 * Settings. Importing used to require a site and offered to create one from the
 * device name, which is how the fleet got sites called "Singapore5". An existing
 * site may still be picked here as a shortcut. An unbound chiller cannot join an
 * ops job (ops_job_items.customer_id is NOT NULL) — the intended state until
 * someone binds it. The equipment id must not already be linked (the unique
 * index is the real guard; this is the friendly error).
 */
class ProvisionChillerVendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create machine-settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'equipment_id' => ['required', 'string', 'max:64', Rule::unique('vends', 'citybox_equipment_id')],
            'name' => ['nullable', 'string', 'max:255'],
            'begin_date' => ['nullable', 'date'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            // Sites are never created from a device any more — refuse an old client that still tries.
            'new_customer' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'equipment_id.unique' => 'This CityBox device is already linked to a vend.',
            'new_customer.prohibited' => 'Sites are created in ConnectVend, not from the CityBox device — import the machine, then bind a site in Machine Settings.',
        ];
    }
}
