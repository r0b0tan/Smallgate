<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateProjectRequest extends StoreProjectRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('project')) ?? false;
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
            Rule::unique('projects', 'slug')
                ->where('customer_id', $this->input('customer_id'))
                ->ignore($this->route('project')),
        ];

        return $rules;
    }
}
