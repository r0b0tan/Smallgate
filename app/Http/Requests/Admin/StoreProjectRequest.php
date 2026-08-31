<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Project::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $slug = (string) $this->string('slug');

        $this->merge([
            'slug' => Str::slug($slug !== '' ? $slug : (string) $this->string('name')),
        ]);
    }

    /**
     * Note `customer_id` is validated here but assigned explicitly in the
     * controller -- it is not mass assignable on the model, precisely because
     * it decides who may see the project.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'string', Rule::exists('customers', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('projects', 'slug')->where('customer_id', $this->input('customer_id')),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'Kunde',
            'name' => 'Name',
            'slug' => 'Kürzel',
            'description' => 'Beschreibung',
            'status' => 'Status',
        ];
    }
}
