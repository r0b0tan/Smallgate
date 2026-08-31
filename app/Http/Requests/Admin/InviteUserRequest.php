<?php

namespace App\Http\Requests\Admin;

use App\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('create', Invitation::class) ?? false)
            && ($this->user()?->can('manageUsers', $this->route('customer')) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->string('email'))),
        ]);
    }

    /**
     * The role and the customer are NOT part of this form. They come from the
     * route and are fixed to "customer" in the service, so an administrator
     * cannot accidentally -- and a tampered request cannot deliberately --
     * invite somebody as an administrator.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                // One account per address across the whole portal.
                Rule::unique('users', 'email'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Für diese E-Mail-Adresse existiert bereits ein Zugang.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Name',
            'email' => 'E-Mail-Adresse',
        ];
    }
}
