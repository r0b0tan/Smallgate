<?php

namespace App\Http\Requests\Admin;

use App\Enums\PreviewStatus;
use App\Enums\PreviewTargetType;
use App\Models\Preview;
use App\Rules\AllowedPreviewTarget;
use App\Rules\PreviewHostname;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Preview::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $slug = (string) $this->string('slug');
        $hostname = trim((string) $this->string('hostname'));

        $this->merge([
            'slug' => Str::slug($slug !== '' ? $slug : (string) $this->string('name')),
            'hostname' => $hostname === '' ? null : mb_strtolower($hostname),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $status = $this->enum('status', PreviewStatus::class);

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('previews', 'slug')
                    ->where('project_id', $this->route('project')?->id)
                    ->ignore($this->route('preview')),
            ],
            'hostname' => [
                // A preview only becomes reachable with a hostname, so it is
                // required as soon as the status leaves "draft".
                $status === PreviewStatus::Draft ? 'nullable' : 'required',
                'string', 'max:255',
                new PreviewHostname,
                Rule::unique('previews', 'hostname')->ignore($this->route('preview')),
            ],
            'target_type' => ['required', Rule::enum(PreviewTargetType::class)],
            'target' => [
                $status === PreviewStatus::Draft ? 'nullable' : 'required',
                'string', 'max:2048',
                // The allowlist check: a target outside the configured roots or
                // upstream hosts is rejected here, never stored, never proxied.
                new AllowedPreviewTarget($this->enum('target_type', PreviewTargetType::class)),
            ],
            'status' => ['required', Rule::enum(PreviewStatus::class)],
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
            'hostname' => 'Hostname',
            'target_type' => 'Zieltyp',
            'target' => 'Ziel',
            'status' => 'Status',
        ];
    }
}
