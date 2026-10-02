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
    /**
     * The customer is not part of the update: it is fixed when the project is
     * created. Moving a project would hand its feedback -- comments and the
     * names of the people who wrote them -- to another customer's users.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        unset($rules['customer_id']);

        $rules['slug'] = [
            'required', 'string', 'max:255',
            'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
            Rule::unique('projects', 'slug')
                ->where('customer_id', $this->route('project')?->customer_id)
                ->ignore($this->route('project')),
        ];

        return $rules;
    }
}
