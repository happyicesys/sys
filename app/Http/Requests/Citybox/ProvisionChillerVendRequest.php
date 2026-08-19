<?php

namespace App\Http\Requests\Citybox;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a Smart Chiller vend from a CityBox device. Exactly ONE of
 * customer_id / new_customer must be given (binding is structurally required
 * — ops_job_items.customer_id is NOT NULL). The equipment id must not already
 * be linked (unique index is the real guard; this is the friendly error).
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
            'customer_id' => ['nullable', 'integer', 'exists:customers,id', 'required_without:new_customer'],
            'new_customer' => ['nullable', 'array', 'required_without:customer_id', 'prohibits:customer_id'],
            'new_customer.name' => ['required_with:new_customer', 'string', 'max:255'],
            'new_customer.address' => ['nullable', 'array'],
            'new_customer.person_id' => ['nullable', 'integer'],
            'new_customer.location_type_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'equipment_id.unique' => 'This CityBox device is already linked to a vend.',
            'customer_id.required_without' => 'Pick an existing site or create a new one — a chiller must be bound to a customer to appear in ops jobs.',
        ];
    }
}
