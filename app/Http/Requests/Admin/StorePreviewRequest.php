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

        $this->merge([
            'slug' => Str::slug($slug !== '' ? $slug : (string) $this->string('name')),
            'hostname' => $this->qualifiedHostname(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // A draft may still be incomplete; anything the customer can already be
        // offered needs a hostname and a target. The status comes from the
        // stored preview, never from the request -- it is changed by the
        // provision and disable actions alone.
        $optional = $this->currentStatus() === PreviewStatus::Draft ? 'nullable' : 'required';

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
                // A preview only becomes reachable with a hostname.
                $optional,
                'string', 'max:255',
                new PreviewHostname,
                Rule::unique('previews', 'hostname')->ignore($this->route('preview')),
            ],
            'target_type' => ['required', Rule::enum(PreviewTargetType::class)],
            'target' => [
                $optional,
                'string', 'max:2048',
                // The allowlist check: a target outside the configured roots or
                // upstream hosts is rejected here, never stored, never proxied.
                new AllowedPreviewTarget($this->enum('target_type', PreviewTargetType::class)),
            ],
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
        ];
    }

    /**
     * The status the preview has right now. A preview being created is always a
     * draft; an existing one keeps whatever its provisioning actions gave it.
     */
    protected function currentStatus(): PreviewStatus
    {
        $preview = $this->route('preview');

        return $preview instanceof Preview ? $preview->status : PreviewStatus::Draft;
    }

    /**
     * The form asks for the subdomain label only -- the base domain is fixed
     * and appended here, which removes a whole class of typos. A value that is
     * already below the base domain is left untouched, so pasting a full
     * hostname still works and is still validated as strictly as before.
     */
    private function qualifiedHostname(): ?string
    {
        $hostname = mb_strtolower(trim((string) $this->string('hostname')));
        $base = mb_strtolower((string) config('previews.base_domain'));

        if ($hostname === '') {
            return null;
        }

        if ($base === '' || $hostname === $base || str_ends_with($hostname, '.'.$base)) {
            return $hostname;
        }

        return $hostname.'.'.$base;
    }
}
