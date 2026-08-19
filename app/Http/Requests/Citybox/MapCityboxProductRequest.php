<?php

namespace App\Http\Requests\Citybox;

use Illuminate\Foundation\Http\FormRequest;

/** Link (or unlink) one CityBox SKU to a mark1 product. */
class MapCityboxProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update products') ?? false;
    }

    public function rules(): array
    {
        return [
            // null = unlink. Many CityBox ids may point at one product (their catalog has duplicate names).
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ];
    }
}
