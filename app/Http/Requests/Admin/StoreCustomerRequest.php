<?php

namespace App\Http\Requests\Admin;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }

    /**
     * A missing slug is derived from the name, so the admin form stays short.
     */
    protected function prepareForValidation(): void
    {
        $slug = (string) $this->string('slug');

        $this->merge([
            'slug' => Str::slug($slug !== '' ? $slug : (string) $this->string('name')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('customers', 'slug'),
            ],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Das Kürzel darf nur Kleinbuchstaben, Ziffern und Bindestriche enthalten.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Name',
            'slug' => 'Kürzel',
            'contact_email' => 'Kontakt-E-Mail',
            'is_active' => 'Status',
        ];
    }
}
