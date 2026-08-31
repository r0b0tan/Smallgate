<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends StoreCustomerRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('customer')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['slug'] = [
            'required', 'string', 'max:255',
            'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
            Rule::unique('customers', 'slug')->ignore($this->route('customer')),
        ];

        return $rules;
    }
}
